<?php

namespace App\Services;

use App\Jobs\ExpireListings;
use App\Mail\ListingExpiringSoon;
use App\Models\ContactMessage;
use App\Models\Listing;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/** Denní údržba portálu. Idempotentní – opakované spuštění nic nezdvojí. */
class Maintenance
{
    /** @return array{expired:int, reminded:int, pruned:int, anonymized:int, messages:int} */
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
                $l->forceFill(['expiry_reminded_at' => now()])->saveQuietly();
                if ($l->user?->email) {
                    Mail::to($l->user->email)->queue(new ListingExpiringSoon($l, self::renewUrl($l)));
                    $reminded++;
                }
            });

        $pruned = ContactMessage::whereNotNull('handled_at')
            ->where('handled_at', '<', now()->subDays((int) config('reality.automation.contact_retention_days', 180)))->delete();

        $retention = app(Retention::class)->run();
        app(Heartbeat::class)->beat();

        return ['expired' => max(0, $expired), 'reminded' => $reminded, 'pruned' => $pruned] + $retention;
    }

    /** Podepsaný odkaz „Prodloužit“ (bez přihlášení), vázaný na konkrétní připomínku. */
    public static function renewUrl(Listing $l): ?string
    {
        if (! config('reality.automation.renew_link') || ! $l->expiry_reminded_at) {
            return null;
        }

        return URL::temporarySignedRoute('listings.renew-link', now()->addDays((int) config('reality.automation.renew_link_days', 7)),
            ['listing' => $l, 'r' => $l->expiry_reminded_at->getTimestamp()]);
    }
}
