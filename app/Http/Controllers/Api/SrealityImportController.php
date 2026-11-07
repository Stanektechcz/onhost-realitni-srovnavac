<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Feed\AgencyListingService;
use App\Services\Feed\SrealityMapper;
use App\Services\Feed\XmlRpc;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Sreality-compatible XML-RPC import endpoint (POST /import/RPC2), so CRM vendors can reuse their Sreality
 * connector. Session scheme as in the Sreality spec: getHash(client_id) → session_id; every following call
 * sends session_id' = session_id[0:48] . md5(session_id . md5(password) . software_key).
 */
class SrealityImportController extends Controller
{
    private const VERSION = '3.0.0-onhost';

    private const UNSUPPORTED = [
        'topAdvert', 'addPhoto', 'delPhoto', 'listPhoto', 'addVideo', 'delVideo', 'listVideo',
        'addSeller', 'delSeller', 'listSeller', 'addSellerVideo', 'delSellerVideo', 'addSellerCertificate', 'delSellerCertificate',
        'addProject', 'delProject', 'listProject', 'addProjectPhoto', 'delProjectPhoto', 'listProjectPhoto', 'addProjectVideo', 'delProjectVideo',
        'listStat', 'listDailyStat', 'listAllDailyStat', 'listSellerStat', 'listInquiry', 'listFullInquiry',
    ];

    public function __construct(private AgencyListingService $feed) {}

    public function __invoke(Request $request): Response
    {
        try {
            [$method, $params] = XmlRpc::parseCall($request->getContent());
        } catch (InvalidArgumentException $e) {
            return $this->xml(XmlRpc::fault(400, $e->getMessage()));
        }

        return $this->xml(XmlRpc::response($this->dispatch($method, $params)));
    }

    private function dispatch(string $method, array $p): array
    {
        if ($method === 'version') {
            return $this->ok([['version' => self::VERSION]]);
        }
        if ($method === 'getHash') {
            return $this->getHash($p[0] ?? null);
        }
        $known = in_array($method, ['login', 'logout', 'addAdvert', 'delAdvert', 'listAdvert'], true) || in_array($method, self::UNSUPPORTED, true);
        if (! $known) {
            return $this->status(501, 'Unknown method');
        }
        [$agency, $session] = $this->authenticate(is_string($p[0] ?? null) ? $p[0] : '', $method === 'login');
        if (! $agency) {
            return $this->status(407, 'Invalid session');
        }
        if (in_array($method, self::UNSUPPORTED, true)) {
            return $this->status(501, 'Method not supported by reality.onhost.cz');
        }

        return match ($method) {
            'login' => $this->ok([]),
            'logout' => tap($this->ok([]), fn () => Cache::forget($this->key($session))),
            'addAdvert' => $this->addAdvert($agency, is_array($p[1] ?? null) ? $p[1] : []),
            'delAdvert' => $this->delAdvert($agency, $p[1] ?? null, $p[2] ?? null),
            'listAdvert' => $this->listAdvert($agency),
        };
    }

    private function getHash(mixed $clientId): array
    {
        $agency = is_numeric($clientId) ? User::find((int) $clientId) : null;
        if (! $this->eligible($agency)) {
            return $this->status(402, 'Unknown client');
        }
        $sessionId = bin2hex(random_bytes(24)).md5(random_bytes(16)); // 48 fixed + 32 variable chars
        Cache::put($this->key(substr($sessionId, 0, 48)), ['agency' => $agency->id, 'current' => $sessionId, 'logged_in' => false], $this->ttl());

        return $this->ok([['sessionId' => $sessionId]]);
    }

    /** @return array{0: ?User, 1: string} */
    private function authenticate(string $sessionId, bool $isLogin): array
    {
        if (! preg_match('/^[0-9a-f]{80}$/', $sessionId)) {
            return [null, ''];
        }
        $fixed = substr($sessionId, 0, 48);
        $state = Cache::get($this->key($fixed));
        $agency = $state ? User::find($state['agency']) : null;
        if (! $state || ! $this->eligible($agency) || (! $isLogin && ! $state['logged_in'])) {
            return [null, $fixed];
        }
        $pwHash = md5((string) $agency->feed_import_password);
        $valid = false;
        foreach ((array) config('reality.import.software_keys') as $key) {
            $valid = $valid || hash_equals($fixed.md5($state['current'].$pwHash.$key), $sessionId);
        }
        if (! $valid) {
            return [null, $fixed];
        }
        Cache::put($this->key($fixed), ['agency' => $agency->id, 'current' => $sessionId, 'logged_in' => true], $this->ttl());

        return [$agency, $fixed];
    }

    private function addAdvert(User $agency, array $data): array
    {
        $rkid = (string) ($data['advert_rkid'] ?? '');
        $r = $this->feed->upsert($agency, SrealityMapper::toFeedRow($data, $rkid));
        if (isset($r['errors'])) {
            return $this->status(452, 'Missing or invalid items: '.implode(' ', $r['errors']));
        }
        $this->feed->touch($agency);
        AuditLog::record('feed.upsert', $r['listing'], ['agency' => $agency->id, 'channel' => 'rpc2', 'created' => $r['created']]);

        return $this->ok([['advert_id' => $r['listing']->id]]);
    }

    private function delAdvert(User $agency, mixed $advertId, mixed $rkid): array
    {
        $listing = (is_numeric($advertId) && (int) $advertId > 0) ? $this->feed->findById($agency, (int) $advertId)
            : ((is_string($rkid) && $rkid !== '') ? $this->feed->find($agency, $rkid) : null);
        if (! $listing) {
            return $this->status(404, 'Advert not found');
        }
        $this->feed->withdraw($listing);
        AuditLog::record('feed.withdraw', $listing, ['agency' => $agency->id, 'channel' => 'rpc2']);

        return $this->ok([]);
    }

    private function listAdvert(User $agency): array
    {
        $rows = $this->feed->all($agency)->filter(fn ($l) => $l->status !== 'expired')->values()->map(fn ($l) => [
            'advert_id' => $l->id, 'advert_rkid' => (string) $l->source_id, 'status' => $l->status,
            'advert_url' => $l->isPublic() ? route('listings.show', $l) : '',
        ])->all();

        return $this->ok($rows);
    }

    private function eligible(?User $agency): bool
    {
        return $agency && ! $agency->is_banned && in_array($agency->role, ['agency', 'developer'], true)
            && (string) $agency->feed_import_password !== '';
    }

    private function key(string $fixed): string
    {
        return 'rpc2:session:'.$fixed;
    }

    private function ttl(): \DateTimeInterface
    {
        return now()->addMinutes((int) config('reality.import.session_minutes', 15));
    }

    private function ok(array $output): array
    {
        return ['status' => 200, 'statusMessage' => 'OK', 'output' => $output];
    }

    private function status(int $code, string $message): array
    {
        return ['status' => $code, 'statusMessage' => $message, 'output' => []];
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'text/xml; charset=UTF-8']);
    }
}
