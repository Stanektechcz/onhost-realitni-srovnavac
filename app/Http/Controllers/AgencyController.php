<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\AgencyClaim;
use App\Models\AgencyReview;
use App\Models\AgencyReviewReport;
use App\Models\Broker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AgencyController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kraj' => ['nullable', 'string', 'max:60'],
            'razeni' => ['nullable', Rule::in(['hodnoceni', 'nabidky', 'nazev'])],
        ]);
        $q = Agency::query()->withOfferCount()->search($f['q'] ?? null)
            ->when($f['kraj'] ?? null, fn ($w, $r) => $w->where('region', $r));
        match ($f['razeni'] ?? 'hodnoceni') {
            'nabidky' => $q->orderByDesc('offers_count')->orderBy('name'),
            'nazev' => $q->orderBy('name'),
            default => $q->orderByRaw('rating_avg is null')->orderByDesc('rating_avg')->orderByDesc('reviews_count')->orderBy('name'),
        };
        $agencies = $q->paginate(24)->withQueryString();
        $regions = Agency::whereNotNull('region')->distinct()->orderBy('region')->pluck('region');

        return view('agencies.index', compact('agencies', 'regions', 'f'));
    }

    public function show(Request $request, Agency $agency)
    {
        $listings = $agency->publicListings()->with('photos')->latest('published_at')->limit(12)->get();
        $all = $agency->publicListings()->get(['id', 'price_halere', 'area_m2', 'offer_type', 'city', 'district']);
        $stats = self::stats($all);
        $reviews = $agency->reviews()->published()->with('user:id,name', 'broker:id,name,slug')->latest()->paginate(10);
        $brokers = $agency->brokers()->where('is_public', true)->orderBy('name')->get();
        $user = $request->user();
        $myClaim = $user ? $agency->claims()->where('user_id', $user->id)->first() : null;

        return view('agencies.show', compact('agency', 'listings', 'stats', 'reviews', 'brokers', 'myClaim'));
    }

    public function broker(Agency $agency, Broker $broker)
    {
        abort_unless($broker->agency_id === $agency->id && $broker->is_public, 404);
        $reviews = $broker->reviews()->published()->with('user:id,name')->latest()->paginate(10);

        return view('agencies.broker', compact('agency', 'broker', 'reviews'));
    }

    /** count, average price per m² (sale and rent separately) and localities of active public offers. */
    public static function stats($listings): array
    {
        $ppm = fn ($type) => $listings->where('offer_type', $type)->filter(fn ($l) => $l->price_halere && $l->area_m2 > 0)
            ->map(fn ($l) => $l->price_halere / 100 / $l->area_m2);

        return [
            'count' => $listings->count(),
            'ppm2_sale' => ($s = $ppm('sale'))->isEmpty() ? null : (int) round($s->avg()),
            'ppm2_rent' => ($r = $ppm('rent'))->isEmpty() ? null : (int) round($r->avg()),
            'cities' => $listings->countBy('city')->sortDesc()->take(8)->all(),
        ];
    }

    public function storeReview(Request $request, Agency $agency)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'text' => ['required', 'string', 'min:20', 'max:3000'],
            'broker' => ['nullable', 'string', Rule::exists('brokers', 'slug')->where('agency_id', $agency->id)->where('is_public', true)],
            'good_faith' => ['accepted'],
        ]);
        $user = $request->user();
        abort_if($agency->claimed_by_user_id === $user->id, 403, 'Vlastní kancelář nelze hodnotit.');
        $broker = isset($data['broker']) ? $agency->brokers()->where('slug', $data['broker'])->first() : null;
        $exists = AgencyReview::where('user_id', $user->id)
            ->where($broker ? 'broker_id' : 'agency_id', $broker?->id ?? $agency->id)->exists();
        if ($exists) {
            return back()->withErrors(['text' => 'Toto hodnocení jste už jednou odeslali.']);
        }
        $review = new AgencyReview(['rating' => $data['rating'], 'text' => $data['text']]);
        $review->forceFill([
            'user_id' => $user->id,
            'agency_id' => $broker ? null : $agency->id,
            'broker_id' => $broker?->id,
            'verified' => AgencyReview::isVerifiedContact($user, $agency),
            'status' => 'pending',
        ])->save();

        return back()->with('status', 'Děkujeme. Recenze se zobrazí po kontrole moderátorem.');
    }

    public function reportReview(Request $request, AgencyReview $review)
    {
        abort_unless($review->status === 'published', 404);
        if (filled($request->input('website'))) {
            return back()->with('status', 'Děkujeme za nahlášení.');
        }
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(config('reality.review_report_reasons')))],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
            'email' => [Rule::requiredIf(! $request->user()), 'nullable', 'email', 'max:190'],
            'good_faith' => ['accepted'],
        ]);
        $review->reports()->create([
            'reporter_id' => $request->user()?->id,
            'reporter_email' => $data['email'] ?? $request->user()?->email,
            'reason' => $data['reason'],
            'description' => $data['description'],
        ]);
        $review->increment('report_count');

        return back()->with('status', 'Děkujeme za nahlášení. Recenzi posoudí moderátor a o výsledku vás budeme informovat.');
    }

    /** Claim: auto-verified when the claimant's feed account matches the agency web domain, otherwise admin approval. */
    public function claim(Request $request, Agency $agency)
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:1000'], 'confirm' => ['accepted']]);
        $user = $request->user();
        abort_if($agency->isVerified(), 409, 'Kancelář je již ověřena.');
        $claim = AgencyClaim::where(['agency_id' => $agency->id, 'user_id' => $user->id])->first()
            ?? (new AgencyClaim)->forceFill(['agency_id' => $agency->id, 'user_id' => $user->id]);
        if ($claim->exists && $claim->status !== 'pending') {
            return back()->withErrors(['message' => 'O tuto kancelář jste už žádali.']);
        }
        $claim->fill(['message' => $data['message'] ?? null])->save();
        $claim->setRelation('agency', $agency);
        if (self::feedMatches($user, $agency)) {
            $claim->approve('feed');

            return back()->with('status', 'Kancelář byla ověřena podle vašeho importního účtu.');
        }

        return back()->with('status', 'Žádost o převzetí profilu jsme přijali, ověří ji administrátor.');
    }

    public static function feedMatches($user, Agency $agency): bool
    {
        $hasFeed = $user->role === 'agency' && ($user->feed_token_hash || $user->feed_import_password);
        $host = $agency->web ? Str::lower(preg_replace('/^www\./', '', (string) parse_url(Str::start($agency->web, 'https://'), PHP_URL_HOST))) : null;
        $domain = Str::lower(Str::after($user->email, '@'));

        return $hasFeed && $user->email_verified_at && $host && $domain === $host;
    }

    public function update(Request $request, Agency $agency)
    {
        abort_unless($agency->isVerified() && $agency->claimed_by_user_id === $request->user()->id, 403);
        $data = $request->validate([
            'web' => ['nullable', 'url:https,http', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
        ]);
        $agency->fill($data)->save();

        return back()->with('status', 'Veřejné kontakty kanceláře byly uloženy.');
    }

    public function storeBroker(Request $request, Agency $agency)
    {
        abort_unless($agency->isVerified() && $agency->claimed_by_user_id === $request->user()->id, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'specialization' => ['nullable', 'string', 'max:190'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ]);
        unset($data['consent']);
        $agency->brokers()->create($data);

        return back()->with('status', 'Makléř byl přidán.');
    }

    public function destroyBroker(Request $request, Agency $agency, Broker $broker)
    {
        abort_unless($agency->isVerified() && $agency->claimed_by_user_id === $request->user()->id && $broker->agency_id === $agency->id, 403);
        $broker->delete();

        return back()->with('status', 'Makléř byl odebrán.');
    }
}
