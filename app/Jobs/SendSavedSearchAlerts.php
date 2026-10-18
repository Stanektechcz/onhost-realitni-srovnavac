<?php

namespace App\Jobs;

use App\Mail\SavedSearchAlert;
use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/** Hlídací pes — e-mails go through the configured mailer (log in dev/MVP). */
class SendSavedSearchAlerts implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        SavedSearch::with('user')->chunkById(200, function ($searches) {
            foreach ($searches as $search) {
                $since = $search->last_notified_at ?? $search->created_at;
                $new = Listing::public()->filter($search->filters ?? [])->where('published_at', '>', $since)->limit(20)->get();
                if ($new->isNotEmpty() && $search->user) {
                    Mail::to($search->user->email)->send(new SavedSearchAlert($search, $new));
                }
                $search->forceFill(['last_notified_at' => now()])->save();
            }
        });
    }
}
