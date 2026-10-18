<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\HandoverProtocol;
use App\Models\Listing;
use App\Models\ListingStat;
use App\Models\Payment;
use App\Models\ViewingSlot;
use App\Services\MarketStats;
use App\Services\Payments\CommissionCalculator;
use App\Services\Payments\PaymentProvider;
use App\Support\ListingQuality;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class OwnerToolsController extends Controller
{
    public function tools(Listing $listing, MarketStats $stats)
    {
        $this->authorize('update', $listing);
        $listing->load('photos');
        $series = ListingStat::series($listing, 30);
        $totals = [
            'views' => $listing->views,
            'saves' => $listing->favorites()->count(),
            'inquiries' => $listing->messages()->count(),
            'bookings' => $listing->slots()->whereNotNull('booked_by')->count(),
        ];

        return view('owner.tools', [
            'listing' => $listing, 'series' => $series, 'totals' => $totals,
            'quality' => ListingQuality::check($listing),
            'estimate' => $stats->estimate($listing->offer_type, $listing->district, $listing->city, $listing->disposition, (float) $listing->area_m2),
            'market' => $stats->versusMarket($listing),
            'slots' => $listing->slots()->where('starts_at', '>', now()->subDay())->get(),
        ]);
    }

    public function estimate(Request $request, MarketStats $stats)
    {
        $data = $request->validate([
            'offer_type' => ['nullable', Rule::in(['rent', 'sale'])],
            'district' => ['nullable', 'string', 'max:100'], 'city' => ['nullable', 'string', 'max:100'],
            'disposition' => ['nullable', Rule::in(config('reality.dispositions'))],
            'area' => ['nullable', 'numeric', 'min:5', 'max:2000'],
        ]);
        $result = isset($data['area']) ? $stats->estimate($data['offer_type'] ?? 'rent', $data['district'] ?? null, $data['city'] ?? null, $data['disposition'] ?? null, (float) $data['area']) : null;

        return view('owner.estimate', ['data' => $data, 'result' => $result, 'districts' => $stats->districts()->pluck('name')]);
    }

    public function renew(Listing $listing)
    {
        $this->authorize('update', $listing);
        abort_unless(in_array($listing->status, ['active', 'expired'], true), 409);
        $listing->forceFill([
            'status' => 'active', 'renewed_at' => now(),
            'published_at' => $listing->status === 'expired' ? now() : $listing->published_at,
            'expires_at' => now()->addDays(config('reality.listing_lifetime_days')),
        ])->save();
        AuditLog::record('listing.renewed', $listing);

        return back()->with('status', 'Inzerát je prodloužen do '.$listing->expires_at->format('j. n. Y').'.');
    }

    public function boost(Request $request, Listing $listing, PaymentProvider $provider, CommissionCalculator $commission)
    {
        abort_unless(config('reality.features.boost') && config('reality.features.payments'), 404);
        $this->authorize('update', $listing);
        abort_unless($listing->isPublic(), 409);
        $price = (int) config('reality.boost.price_halere');
        $payment = new Payment([
            'user_id' => $request->user()->id, 'service' => 'listing_boost', 'provider' => $provider->name(),
            'amount_halere' => $price, 'commission_halere' => $commission->forService('listing_boost', $price),
            'status' => 'created', 'meta' => ['days' => config('reality.boost.days')],
        ]);
        $payment->payable()->associate($listing);
        $payment->save();
        if ($redirect = $provider->create($payment)) {
            return redirect()->away($redirect);
        }
        if ($provider->status($payment->refresh()) === 'paid') {
            $from = $listing->isBoosted() ? $listing->boosted_until : now();
            $listing->forceFill(['boosted_until' => $from->copy()->addDays(config('reality.boost.days'))])->save();
        }
        AuditLog::record('listing.boosted', $listing, ['payment_id' => $payment->id]);

        return back()->with('status', 'Inzerát je topován.');
    }

    public function storeSlots(Request $request, Listing $listing)
    {
        $this->authorize('update', $listing);
        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'from' => ['required', 'date_format:H:i'], 'to' => ['required', 'date_format:H:i', 'after:from'],
            'duration' => ['required', 'integer', Rule::in([15, 20, 30, 45, 60])],
        ]);
        $start = Carbon::parse($data['date'].' '.$data['from']);
        $end = Carbon::parse($data['date'].' '.$data['to']);
        $n = 0;
        abort_if($listing->slots()->where('starts_at', '>', now())->count() > 100, 422, 'Příliš mnoho termínů.');
        for ($t = $start->copy(); $t->copy()->addMinutes($data['duration']) <= $end && $n < 24; $t->addMinutes($data['duration'])) {
            if ($t->isFuture() && ! $listing->slots()->where('starts_at', $t)->exists()) {
                $listing->slots()->create(['starts_at' => $t->copy(), 'duration_min' => $data['duration']]);
                $n++;
            }
        }

        return back()->with('status', "Přidáno termínů: $n.");
    }

    public function destroySlot(Listing $listing, ViewingSlot $slot)
    {
        $this->authorize('update', $listing);
        abort_unless($slot->listing_id === $listing->id, 404);
        $slot->delete();

        return back()->with('status', 'Termín byl smazán.');
    }

    public function handover(Listing $listing)
    {
        $this->authorize('update', $listing);

        return view('owner.handover', ['listing' => $listing, 'protocols' => HandoverProtocol::where('listing_id', $listing->id)->latest()->get()]);
    }

    public function storeHandover(Request $request, Listing $listing)
    {
        $this->authorize('update', $listing);
        $data = $request->validate([
            'tenant_name' => ['required', 'string', 'max:150'], 'landlord_name' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date'], 'keys' => ['nullable', 'integer', 'min:0', 'max:50'],
            'meters' => ['nullable', 'array'], 'meters.*' => ['nullable', 'string', 'max:40'],
            'rooms' => ['nullable', 'array'], 'rooms.*' => ['nullable', 'string', 'max:1000'],
            'inventory' => ['nullable', 'string', 'max:5000'], 'defects' => ['nullable', 'string', 'max:5000'],
            'checklist' => ['nullable', 'array'], 'checklist.*' => ['string', 'max:100'],
        ]);
        $p = HandoverProtocol::create(['listing_id' => $listing->id, 'user_id' => $request->user()->id, 'data' => $data]);

        return redirect()->route('owner.handover.print', [$listing, $p]);
    }

    public function printHandover(Request $request, Listing $listing, HandoverProtocol $protocol)
    {
        $this->authorize('update', $listing);
        abort_unless($protocol->listing_id === $listing->id, 404);

        return view('owner.handover-print', ['listing' => $listing, 'p' => $protocol, 'd' => $protocol->data]);
    }

    public function contracts(Request $request)
    {
        return view('owner.contracts', ['contracts' => config('reality_content.contracts'), 'listings' => $request->user()->listings()->latest()->get()]);
    }

    public function contract(Request $request, string $slug)
    {
        $tpl = config("reality_content.contracts.$slug");
        abort_unless($tpl, 404);
        $listing = $request->query('l') ? $request->user()->listings()->where('slug', $request->query('l'))->first() : null;

        return view('owner.contract', ['slug' => $slug, 'tpl' => $tpl, 'listing' => $listing, 'user' => $request->user()]);
    }
}
