<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Services\ImageProcessor;
use App\Support\ListingSteps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ListingController extends Controller
{
    public function show(Request $request, Listing $listing, \App\Services\MarketStats $stats)
    {
        abort_unless($request->user()?->can('view', $listing) ?? $listing->isPublic(), 404);
        $listing->load('photos');
        if ($listing->isPublic()) {
            $listing->increment('views');
            if (! $request->user() || $request->user()->id !== $listing->user_id) {
                \App\Models\ListingStat::bump($listing, 'views');
            }
        }
        $market = $stats->versusMarket($listing);
        $slots = $listing->slots()->free()->limit(30)->get();
        $myBooking = $request->user() ? $listing->slots()->where('booked_by', $request->user()->id)->where('starts_at', '>', now())->first() : null;
        $isFavorite = $request->user()?->favorites()->where('listing_id', $listing->id)->exists() ?? false;
        $similar = Listing::public()->where('id', '!=', $listing->id)->where('offer_type', $listing->offer_type)
            ->where('city', $listing->city)->with('photos')->latest('published_at')->limit(3)->get();

        $agency = $listing->isPublic() ? \App\Models\Agency::forListing($listing) : null;

        return view('listings.show', compact('listing', 'isFavorite', 'similar', 'market', 'slots', 'myBooking', 'agency'));
    }

    public function mine(Request $request)
    {
        $listings = $request->user()->listings()->with('photos')->latest()->paginate(20);

        return view('listings.mine', compact('listings'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Listing::class);

        return view('listings.wizard', ['listing' => new Listing(['offer_type' => 'rent', 'property_type' => 'flat']), 'step' => 1]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Listing::class);
        $data = $request->validate(ListingSteps::rules(1, $request->all()));
        $listing = new Listing(ListingSteps::toAttributes(1, $data));
        $listing->user_id = $request->user()->id;
        $listing->status = 'draft';
        $listing->source = 'internal';
        $listing->save();
        AuditLog::record('listing.created', $listing);

        return redirect()->route('listings.step', [$listing, 2]);
    }

    public function step(Listing $listing, int $step)
    {
        $this->authorize('update', $listing);
        abort_unless(isset(ListingSteps::STEPS[$step]), 404);
        $listing->load('photos');

        return view('listings.wizard', ['listing' => $listing, 'step' => $step, 'missing' => ListingSteps::missing($listing)]);
    }

    public function saveStep(Request $request, Listing $listing, int $step)
    {
        $this->authorize('update', $listing);
        abort_unless(in_array($step, ListingSteps::FORM_STEPS, true), 404);
        $data = $request->validate(ListingSteps::rules($step, $request->all()));
        $listing->fill(ListingSteps::toAttributes($step, $data));
        if ($listing->status === 'active' && $listing->isDirty()) {
            $listing->status = 'pending'; // changed active listing goes back to moderation
        }
        $listing->save();

        return redirect()->route('listings.step', [$listing, $step + 1]);
    }

    public function uploadPhotos(Request $request, Listing $listing, ImageProcessor $images)
    {
        $this->authorize('update', $listing);
        $max = config('reality.max_photos') - $listing->photos()->count();
        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:'.max($max, 0)],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('reality.photo.max_kb'), 'dimensions:min_width=400,min_height=300'],
        ], ['photos.max' => 'Maximální počet fotografií je '.config('reality.max_photos').'.']);
        $pos = (int) $listing->photos()->max('position');
        foreach ($request->file('photos') as $file) {
            $stored = $images->store($file, 'listings/'.$listing->id);
            $listing->photos()->create($stored + ['position' => ++$pos]);
        }

        return back()->with('status', 'Fotografie byly nahrány.');
    }

    public function deletePhoto(Listing $listing, ListingPhoto $photo)
    {
        $this->authorize('update', $listing);
        abort_unless($photo->listing_id === $listing->id, 404);
        Storage::disk('public')->delete(array_filter([$photo->path, $photo->thumb_path, $photo->avif_path]));
        $photo->delete();

        return back()->with('status', 'Fotografie byla odstraněna.');
    }

    public function submit(Listing $listing)
    {
        $this->authorize('update', $listing);
        if ($missing = ListingSteps::missing($listing)) {
            return redirect()->route('listings.step', [$listing, $missing[0]])->withErrors(['submit' => 'Doplňte prosím povinné údaje.']);
        }
        abort_unless(in_array($listing->status, ['draft', 'rejected', 'expired', 'pending'], true), 409);
        $listing->status = 'pending';
        $listing->save();
        AuditLog::record('listing.submitted', $listing);

        return redirect()->route('listings.mine')->with('status', 'Inzerát byl odeslán ke schválení.');
    }

    public function destroy(Listing $listing)
    {
        $this->authorize('delete', $listing);
        Storage::disk('public')->deleteDirectory('listings/'.$listing->id);
        AuditLog::record('listing.deleted', null, ['listing_id' => $listing->id, 'slug' => $listing->slug]);
        $listing->delete();

        return redirect()->route('listings.mine')->with('status', 'Inzerát byl smazán.');
    }
}
