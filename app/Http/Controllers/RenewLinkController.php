<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Listing;
use Illuminate\Http\Request;

/** Prodloužení inzerátu z odkazu v připomínce (podepsaný URL, bez přihlášení, jednou na připomínku). */
class RenewLinkController extends Controller
{
    public function show(Request $request, Listing $listing)
    {
        abort_unless(config('reality.automation.renew_link'), 404);

        return view('listings.renew-link', ['listing' => $listing, 'usable' => $this->usable($request, $listing)]);
    }

    public function renew(Request $request, Listing $listing)
    {
        abort_unless(config('reality.automation.renew_link'), 404);
        if (! $this->usable($request, $listing)) {
            return redirect()->route('listings.show', $listing)->with('status', 'Odkaz už byl použit nebo inzerát nelze prodloužit.');
        }
        $listing->forceFill([
            'status' => 'active', 'renewed_at' => now(), 'expiry_reminded_at' => null,
            'published_at' => $listing->status === 'expired' ? now() : $listing->published_at,
            'expires_at' => now()->addDays(config('reality.listing_lifetime_days')),
        ])->save();
        AuditLog::record('listing.renewed', $listing, ['via' => 'email_link']);

        return redirect()->route('listings.show', $listing)->with('status', 'Inzerát je prodloužen do '.$listing->expires_at->format('j. n. Y').'.');
    }

    private function usable(Request $request, Listing $listing): bool
    {
        return $listing->source === 'internal' && $listing->anonymized_at === null
            && in_array($listing->status, ['active', 'expired'], true)
            && $listing->expiry_reminded_at !== null
            && $listing->expiry_reminded_at->getTimestamp() === (int) $request->query('r');
    }
}
