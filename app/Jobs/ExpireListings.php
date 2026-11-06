<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\Ecosystem\ListingBusPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireListings implements ShouldQueue
{
    use Queueable;

    public function handle(ListingBusPublisher $bus): void
    {
        if ($bus->enabled()) {
            Listing::where('source', 'internal')->where('status', 'active')->whereNotNull('expires_at')->where('expires_at', '<=', now())
                ->each(fn (Listing $l) => $bus->removed($l, 'expired'));
        }
        Listing::where('status', 'active')->whereNotNull('expires_at')->where('expires_at', '<=', now())->update(['status' => 'expired']);
    }
}
