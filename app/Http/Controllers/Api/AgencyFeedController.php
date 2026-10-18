<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Push feed for real-estate agencies (export from their CRM). The agency owns the content and sends it
 * with consent, so listings are source=internal under the agency's account and go through moderation
 * unless an admin enabled feed_autopublish for that agency.
 */
class AgencyFeedController extends Controller
{
    private const MAX_BATCH = 200;

    public function upsert(Request $request): JsonResponse
    {
        $agency = $this->agency($request);
        $items = $request->input('listings');
        if (! is_array($items) || $items === [] || count($items) > self::MAX_BATCH) {
            return response()->json(['error' => 'Pole "listings" musí mít 1 až '.self::MAX_BATCH.' položek.'], 422);
        }

        $result = ['created' => 0, 'updated' => 0, 'errors' => []];
        foreach (array_values($items) as $i => $row) {
            $v = Validator::make(is_array($row) ? $row : [], self::rules());
            if ($v->fails()) {
                $result['errors'][] = ['index' => $i, 'external_id' => is_array($row) ? ($row['external_id'] ?? null) : null, 'messages' => $v->errors()->all()];

                continue;
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
            $exists ? $result['updated']++ : $result['created']++;
        }
        $agency->forceFill(['feed_last_used_at' => now()])->save();
        AuditLog::record('feed.upsert', null, ['agency' => $agency->id, 'created' => $result['created'], 'updated' => $result['updated'], 'errors' => count($result['errors'])]);

        return response()->json($result, $result['errors'] && ! $result['created'] && ! $result['updated'] ? 422 : 200);
    }

    public function destroy(Request $request, string $externalId): JsonResponse
    {
        $agency = $this->agency($request);
        $listing = Listing::where('source_name', self::sourceName($agency))->where('source_id', $externalId)->first();
        if (! $listing) {
            return response()->json(['error' => 'Inzerát nenalezen.'], 404);
        }
        $listing->forceFill(['status' => 'expired', 'expires_at' => now()])->save();
        AuditLog::record('feed.withdraw', $listing, ['agency' => $agency->id]);

        return response()->json(['withdrawn' => $externalId]);
    }

    public function index(Request $request): JsonResponse
    {
        $agency = $this->agency($request);
        $rows = Listing::where('source_name', self::sourceName($agency))->orderBy('id')
            ->get(['source_id', 'slug', 'status', 'moderation_note', 'published_at', 'expires_at'])
            ->map(fn ($l) => [
                'external_id' => $l->source_id, 'status' => $l->status, 'moderation_note' => $l->moderation_note,
                'url' => $l->isPublic() ? route('listings.show', $l) : null,
                'published_at' => $l->published_at?->toIso8601String(), 'expires_at' => $l->expires_at?->toIso8601String(),
            ]);

        return response()->json(['listings' => $rows]);
    }

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
        ];
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

    private function agency(Request $request): User
    {
        return $request->attributes->get('feed_agency');
    }
}
