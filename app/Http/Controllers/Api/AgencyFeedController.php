<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Feed\AgencyListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Push feed for real-estate agencies (export from their CRM). The agency owns the content and sends it
 * with consent, so listings are source=internal under the agency's account and go through moderation
 * unless an admin enabled feed_autopublish for that agency. Shared logic: AgencyListingService.
 */
class AgencyFeedController extends Controller
{
    private const MAX_BATCH = 200;

    public function __construct(private AgencyListingService $feed) {}

    public function upsert(Request $request): JsonResponse
    {
        $agency = $this->agency($request);
        $items = $request->input('listings');
        if (! is_array($items) || $items === [] || count($items) > self::MAX_BATCH) {
            return response()->json(['error' => 'Pole "listings" musí mít 1 až '.self::MAX_BATCH.' položek.'], 422);
        }

        $result = ['created' => 0, 'updated' => 0, 'errors' => []];
        foreach (array_values($items) as $i => $row) {
            $r = $this->feed->upsert($agency, $row);
            if (isset($r['errors'])) {
                $result['errors'][] = ['index' => $i, 'external_id' => is_array($row) ? ($row['external_id'] ?? null) : null, 'messages' => $r['errors']];

                continue;
            }
            $r['created'] ? $result['created']++ : $result['updated']++;
        }
        $this->feed->touch($agency);
        AuditLog::record('feed.upsert', null, ['agency' => $agency->id, 'created' => $result['created'], 'updated' => $result['updated'], 'errors' => count($result['errors'])]);

        return response()->json($result, $result['errors'] && ! $result['created'] && ! $result['updated'] ? 422 : 200);
    }

    public function destroy(Request $request, string $externalId): JsonResponse
    {
        $agency = $this->agency($request);
        $listing = $this->feed->find($agency, $externalId);
        if (! $listing) {
            return response()->json(['error' => 'Inzerát nenalezen.'], 404);
        }
        $this->feed->withdraw($listing);
        AuditLog::record('feed.withdraw', $listing, ['agency' => $agency->id]);

        return response()->json(['withdrawn' => $externalId]);
    }

    public function index(Request $request): JsonResponse
    {
        $rows = $this->feed->all($this->agency($request))->map(fn ($l) => [
            'external_id' => $l->source_id, 'status' => $l->status, 'moderation_note' => $l->moderation_note,
            'url' => $l->isPublic() ? route('listings.show', $l) : null,
            'published_at' => $l->published_at?->toIso8601String(), 'expires_at' => $l->expires_at?->toIso8601String(),
        ]);

        return response()->json(['listings' => $rows]);
    }

    private function agency(Request $request): User
    {
        return $request->attributes->get('feed_agency');
    }
}
