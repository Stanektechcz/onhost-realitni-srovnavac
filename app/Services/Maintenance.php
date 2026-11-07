<?php

namespace App\Services;

use App\Jobs\ExpireListings;
use App\Mail\ListingExpiringSoon;
use App\Models\ContactMessage;
use App\Models\Listing;
use Illuminate\Support\Facades\Mail;

/** Denní údržba portálu. Idempotentní – opakované spuštění nic nezdvojí. */
class Maintenance
{
    /** @return array{expired:int, reminded:int, pruned:int} */
    public function run(): array
    {
        $before = Listing::where('status', 'expired')->count();
        app()->call([new ExpireListings, 'handle']);
        $expired = Listing::where('status', 'expired')->count() - $before;

        $reminded = 0;
        Listing::where('source', 'internal')->where('status', 'active')->whereNull('expiry_reminded_at')
            ->whereNotNull('expires_at')->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays((int) config('reality.automation.reminder_days', 3)))
            ->with('user')->each(function (Listing $l) use (&$reminded) {
                if ($l->user?->email) {
                    Mail::to($l->user->email)->queue(new ListingExpiringSoon($l));
                    $reminded++;
                }
                $l->forceFill(['expiry_reminded_at' => now()])->saveQuietly();
            });

        $pruned = ContactMessage::whereNotNull('handled_at')
            ->where('handled_at', '<', now()->subDays((int) config('reality.automation.contact_retention_days', 180)))->delete();

        return ['expired' => max(0, $expired), 'reminded' => $reminded, 'pruned' => $pruned];
    }
}
