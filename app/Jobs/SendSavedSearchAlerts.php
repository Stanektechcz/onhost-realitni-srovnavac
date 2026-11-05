<?php

namespace App\Jobs;

use App\Mail\SavedSearchAlert;
use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/** Hlídací pes — daily digest of new public matches (flag `alerts`); mails go through the configured mailer. */
class SendSavedSearchAlerts implements ShouldQueue
{
    use Queueable;

    public function handle(): int
    {
        if (! config('reality.features.alerts')) {
            return 0;
        }
        $sent = 0;
        SavedSearch::with('user')->where('notify', true)->chunkById(200, function ($searches) use (&$sent) {
            foreach ($searches as $search) {
                $since = $search->last_notified_at ?? $search->created_at;
                $query = Listing::public()->filter($search->filters ?? [])->where('published_at', '>', $since);
                $total = (clone $query)->count();
                if ($total > 0 && $search->user) {
                    Mail::to($search->user->email)->send(new SavedSearchAlert($search, $query->latest('published_at')->limit(20)->get(), $total));
                    $sent++;
                }
                $search->forceFill(['last_notified_at' => now()])->save();
            }
        });

        return $sent;
    }
}
