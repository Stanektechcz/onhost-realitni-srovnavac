<?php

namespace App\Observers;

use App\Models\Listing;
use App\Services\Ecosystem\ListingBusPublisher;

/** Převádí přechody veřejnosti inzerátu na události sběrnice (bez příznaku nedělá nic). */
class ListingObserver
{
    public function __construct(private readonly ListingBusPublisher $bus) {}

    public function updated(Listing $listing): void
    {
        if (! $this->bus->enabled()) {
            return;
        }
        $was = $this->wasPublic($listing);
        $is = $listing->isPublic();
        if (! $was && $is) {
            $this->bus->created($listing);
        } elseif ($was && ! $is) {
            $this->bus->removed($listing, match ($listing->status) {
                'rejected' => 'moderated',
                'expired' => 'expired',
                default => 'withdrawn',
            });
        }
    }

    public function created(Listing $listing): void
    {
        if ($this->bus->enabled() && $listing->isPublic()) {
            $this->bus->created($listing);
        }
    }

    public function deleted(Listing $listing): void
    {
        if ($this->bus->enabled() && $this->wasPublic($listing)) {
            $this->bus->removed($listing, 'withdrawn');
        }
    }

    private function wasPublic(Listing $listing): bool
    {
        $o = $listing->getOriginal();
        $expires = $o['expires_at'] ?? null;

        return ($o['source'] ?? null) === 'internal' && ($o['status'] ?? null) === 'active' && ! empty($o['published_at'])
            && ($expires === null || $expires->isFuture());
    }
}
