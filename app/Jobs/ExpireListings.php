<?php

namespace App\Jobs;

use App\Models\Listing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireListings implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Listing::where('status', 'active')->whereNotNull('expires_at')->where('expires_at', '<=', now())->update(['status' => 'expired']);
    }
}
