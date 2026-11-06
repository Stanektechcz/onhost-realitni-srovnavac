<?php

namespace App\Http\Controllers\Ecosystem;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Services\Ecosystem\BusSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class EcosystemController extends Controller
{
    public function catalog(): JsonResponse
    {
        abort_unless(config('ecosystem.catalog.enabled'), 404);

        // vlastní bezplatná služba – vložení inzerátu (cenu nevymýšlíme)
        $offers = [[
            'key' => 'reality-listing',
            'name' => 'Inzerát nemovitosti',
            'description' => 'Vložení inzerátu k prodeji nebo pronájmu nemovitosti.',
            'price_minor' => null,
            'currency' => 'CZK',
            'period' => 'one_time',
            'order_path' => '/pridat-inzerat?offer=reality-listing&source=onhost-id',
        ]];

        // členství jen když je placená funkce zapnutá (jinak by objednávková cesta vracela 404)
        if (config('reality.features.memberships')) {
            MembershipPlan::where('is_active', true)->orderBy('audience')->orderBy('price_halere')->limit(49)->get()
                ->each(function (MembershipPlan $p) use (&$offers) {
                    $key = 'reality-plan-'.preg_replace('/[^a-z0-9_-]/', '-', strtolower($p->code));
                    $offers[] = [
                        'key' => $key,
                        'name' => mb_substr($p->name, 0, 120),
                        'description' => 'Členství na portálu reality ('.$p->audience.').',
                        'price_minor' => $p->price_halere,
                        'currency' => 'CZK',
                        'period' => $p->period_days >= 365 ? 'year' : 'month',
                        'order_path' => '/clenstvi?offer='.rawurlencode($key).'&source=onhost-id',
                    ];
                });
        }

        return response()->json([
            'format' => 'onhost-ecosystem-catalog/1',
            'offers' => array_slice($offers, 0, (int) config('ecosystem.catalog.max_offers', 50)),
        ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)->header('Cache-Control', 'public, max-age=300');
    }

    public function health(): JsonResponse
    {
        abort_unless(config('ecosystem.health.enabled'), 404);

        $checks = [
            'database' => [fn () => DB::connection(config('ecosystem.health.db_connection'))->select('select 1') !== [], true],
            'cache' => [function () {
                Cache::put('ecosystem:health', 1, 10);

                return (int) Cache::get('ecosystem:health') === 1;
            }, false],
        ];
        $results = [];
        $status = 'ok';
        foreach ($checks as $name => [$check, $critical]) {
            $start = hrtime(true);
            try {
                $ok = $check() === true;
            } catch (Throwable) {
                $ok = false;
            }
            $results[$name] = ['ok' => $ok, 'ms' => (int) round((hrtime(true) - $start) / 1_000_000)];
            if (! $ok) {
                $status = $critical ? 'down' : ($status === 'down' ? 'down' : 'degraded');
            }
        }

        return response()->json(['project' => config('ecosystem.project'), 'status' => $status, 'checks' => $results], $status === 'down' ? 503 : 200)
            ->header('Cache-Control', 'no-store');
    }

    public function webhook(Request $request): JsonResponse
    {
        abort_unless(config('ecosystem.bus.enabled'), 404);

        $body = $request->getContent();
        $verifier = new BusSignature(config('ecosystem.bus.secrets', []), (int) config('ecosystem.bus.tolerance', 300));
        if (! $verifier->verify($request->header(BusSignature::HEADER), $body)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $payload = json_decode($body, true, 32);
        if (! is_array($payload) || ! is_string($payload['id'] ?? null) || $payload['id'] === '' || strlen($payload['id']) > 100
            || ! is_string($payload['type'] ?? null) || preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)+$/D', $payload['type']) !== 1) {
            return response()->json(['error' => 'invalid_envelope'], 422);
        }

        // idempotence: stejné id se zpracuje jen jednou (7 dní)
        if (! Cache::add('ecosystem:bus:seen:'.hash('sha256', $payload['id']), 1, now()->addDays(7))) {
            return response()->json(['status' => 'duplicate']);
        }

        // zatím jen potvrzujeme příjem; logujeme pouze typ, žádná data zprávy
        Log::info('ecosystem.bus.received', ['type' => $payload['type']]);

        return response()->json(['status' => 'accepted'], 202);
    }
}
