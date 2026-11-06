<?php

namespace App\Services\Ecosystem;

use App\Models\Listing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Odchozí události `listing.created.v1` / `listing.removed.v1`. Jen veřejné inzeráty (Listing::public()),
 * nikdy importované, jen veřejná pole, žádné osobní údaje. Bez příznaku nebo platné https adresy nedělá nic.
 */
class ListingBusPublisher
{
    public const REMOVAL_REASONS = ['sold', 'withdrawn', 'moderated', 'expired'];

    public function enabled(): bool
    {
        $url = (string) config('ecosystem.bus.subscriber_url');

        return (bool) config('ecosystem.bus.enabled')
            && str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false
            && (config('ecosystem.bus.secrets')[0] ?? '') !== '';
    }

    public function created(Listing $listing): bool
    {
        // znovu ověřit proti databázi přes scope public() – importované se nikdy neodešlou
        if (! $this->enabled() || $listing->source !== 'internal' || ! Listing::public()->whereKey($listing->getKey())->exists()) {
            return false;
        }
        $data = array_filter([
            'listing_id' => (string) $listing->slug,
            'category' => in_array($listing->offer_type, ['sale', 'rent'], true) ? $listing->offer_type : 'other',
            'title' => Str::limit($listing->displayTitle(), 197),
            'locality' => $listing->city ? Str::limit(collect([$listing->district, $listing->city])->filter()->join(', '), 117) : null,
            'price_minor' => $listing->price_halere !== null && $listing->price_halere >= 0 ? (int) $listing->price_halere : null,
            'currency' => $listing->price_halere !== null ? 'CZK' : null,
            'url' => route('listings.show', $listing),
        ], fn ($v) => $v !== null && $v !== '');

        return $this->send('listing.created', $data);
    }

    public function removed(Listing $listing, string $reason): bool
    {
        if (! $this->enabled() || $listing->source !== 'internal' || ! in_array($reason, self::REMOVAL_REASONS, true)) {
            return false;
        }

        return $this->send('listing.removed', ['listing_id' => (string) $listing->slug, 'reason' => $reason]);
    }

    /** @param array<string, mixed> $data */
    private function send(string $type, array $data): bool
    {
        $body = json_encode([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'version' => 1,
            'occurred_at' => now()->toAtomString(),
            'scope' => 'listing:read',
            'consent_ref' => null,
            'personal_data' => false,
            'data' => $data,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $signature = BusSignature::sign(config('ecosystem.bus.secrets')[0], $body, time());

        try {
            $response = Http::timeout(config('ecosystem.bus.timeout', 5))
                ->withHeaders([BusSignature::HEADER => $signature, 'Content-Type' => 'application/json'])
                ->withBody($body, 'application/json')
                ->post(config('ecosystem.bus.subscriber_url'));

            if (! $response->successful()) {
                Log::warning('ecosystem.bus.delivery_failed', ['type' => $type, 'status' => $response->status()]);
            }

            return $response->successful();
        } catch (Throwable) {
            Log::warning('ecosystem.bus.delivery_failed', ['type' => $type]);

            return false;
        }
    }
}
