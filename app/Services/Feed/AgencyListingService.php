<?php

namespace App\Services\Feed;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Shared write/read logic for agency import channels (JSON feed v1, Sreality-compatible XML-RPC).
 * Listings are source=internal under the agency's account, keyed by source_name feed:{id} + source_id
 * (the agency's own id), and go through moderation unless the agency has feed_autopublish.
 */
class AgencyListingService
{
    public static function sourceName(User $agency): string
    {
        return 'feed:'.$agency->id;
    }

    public static function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'title' => ['nullable', 'string', 'max:160'],
            'description' => ['required', 'string', 'min:30', 'max:10000'],
            'offer_type' => ['required', Rule::in(['rent', 'sale'])],
            'property_type' => ['required', Rule::in(['flat', 'house', 'land', 'commercial', 'room'])],
            'disposition' => ['nullable', 'string', 'max:10'],
            'area_m2' => ['nullable', 'numeric', 'min:1', 'max:100000'],
            'price_czk' => ['required', 'numeric', 'min:0', 'max:10000000000'],
            'fees_czk' => ['nullable', 'numeric', 'min:0'],
            'deposit_czk' => ['nullable', 'numeric', 'min:0'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:150'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'penb' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'none'])],
            'furnishing' => ['nullable', Rule::in(['none', 'partial', 'full'])],
            'floor' => ['nullable', 'integer', 'between:-5,200'],
            'has_elevator' => ['nullable', 'boolean'],
            'has_balcony' => ['nullable', 'boolean'],
            'has_parking' => ['nullable', 'boolean'],
            'pets_allowed' => ['nullable', 'boolean'],
            'available_from' => ['nullable', 'date'],
            'photos' => ['nullable', 'array', 'max:'.config('reality.max_photos')],
            'photos.*' => ['required', 'string', 'max:2048', 'url:https', 'distinct'],
        ];
    }

    /**
     * Validate and upsert one row. Returns ['listing' => Listing, 'created' => bool] or ['errors' => string[]].
     */
    public function upsert(User $agency, mixed $row): array
    {
        $v = Validator::make(is_array($row) ? $row : [], self::rules());
        if ($v->fails()) {
            return ['errors' => $v->errors()->all()];
        }
        $d = $v->validated();
        $listing = Listing::firstOrNew(['source_name' => self::sourceName($agency), 'source_id' => (string) $d['external_id']]);
        $exists = $listing->exists;
        $listing->fill(self::attributes($d));
        $listing->forceFill(['user_id' => $agency->id, 'source' => 'internal']);
        if (! $exists || $listing->isDirty()) {
            $this->moderate($listing, $agency);
        }
        $listing->save();
        if (array_key_exists('photos', $d)) {
            \App\Jobs\ImportFeedPhotos::dispatchIfChanged($listing, $d['photos'] ?? []);
        }

        return ['listing' => $listing, 'created' => ! $exists];
    }

    public function find(User $agency, string $externalId): ?Listing
    {
        return Listing::where('source_name', self::sourceName($agency))->where('source_id', $externalId)->first();
    }

    public function findById(User $agency, int $id): ?Listing
    {
        return Listing::where('source_name', self::sourceName($agency))->whereKey($id)->first();
    }

    public function withdraw(Listing $listing): void
    {
        $listing->forceFill(['status' => 'expired', 'expires_at' => now()])->save();
    }

    public function all(User $agency): Collection
    {
        return Listing::where('source_name', self::sourceName($agency))->orderBy('id')
            ->get(['id', 'source', 'source_id', 'slug', 'status', 'moderation_note', 'published_at', 'expires_at']);
    }

    public function touch(User $agency): void
    {
        $agency->forceFill(['feed_last_used_at' => now()])->save();
    }

    private static function attributes(array $d): array
    {
        $money = fn ($k) => isset($d[$k]) ? (int) round($d[$k] * 100) : null;

        return [
            'title' => $d['title'] ?? null, 'description' => $d['description'],
            'offer_type' => $d['offer_type'], 'property_type' => $d['property_type'],
            'disposition' => $d['disposition'] ?? null, 'area_m2' => $d['area_m2'] ?? null,
            'price_halere' => $money('price_czk'), 'fees_halere' => $money('fees_czk'), 'deposit_halere' => $money('deposit_czk'),
            'city' => $d['city'], 'district' => $d['district'] ?? null, 'street' => $d['street'] ?? null,
            'lat' => $d['lat'] ?? null, 'lng' => $d['lng'] ?? null, 'penb' => $d['penb'],
            'furnishing' => $d['furnishing'] ?? null, 'floor' => $d['floor'] ?? null,
            'has_elevator' => (bool) ($d['has_elevator'] ?? false), 'has_balcony' => (bool) ($d['has_balcony'] ?? false),
            'has_parking' => (bool) ($d['has_parking'] ?? false), 'pets_allowed' => (bool) ($d['pets_allowed'] ?? false),
            'available_from' => $d['available_from'] ?? null,
        ];
    }

    private function moderate(Listing $listing, User $agency): void
    {
        if ($agency->feed_autopublish) {
            $listing->forceFill([
                'status' => 'active', 'moderation_note' => null,
                'published_at' => $listing->published_at ?? now(),
                'expires_at' => now()->addDays(config('reality.listing_lifetime_days')),
            ]);
        } else {
            $listing->forceFill(['status' => 'pending']);
        }
    }
}
