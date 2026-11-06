<?php

namespace Tests\Support;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Test double of the Comgate API on top of Http::fake(): keeps created payments in memory, lets a test mark them paid or
 * cancelled, and records every call. Never used outside tests — nothing reaches payments.comgate.cz.
 */
final class ComgateSimulator
{
    /** @var array<string, array{price: int, curr: string, refId: string, status: string}> */
    public array $payments = [];

    public int $refunds = 0;

    private int $seq = 0;

    public static function install(string $baseUrl = 'https://payments.comgate.cz/v2.0'): self
    {
        $sim = new self;
        $base = rtrim($baseUrl, '/');
        Http::preventStrayRequests();
        Http::fake([$base.'/*' => fn (Request $r) => $sim->handle($r, $base)]);

        return $sim;
    }

    public function markPaid(string $transId): void
    {
        $this->payments[$transId]['status'] = 'PAID';
    }

    public function markCancelled(string $transId): void
    {
        $this->payments[$transId]['status'] = 'CANCELLED';
    }

    public function lastTransId(): ?string
    {
        return array_key_last($this->payments);
    }

    private function handle(Request $r, string $base): PromiseInterface
    {
        $path = substr(strtok($r->url(), '?'), strlen($base));
        if ($r->method() === 'POST' && $path === '/payment') {
            $data = $r->data();
            if (! isset($data['price'], $data['curr'], $data['refId']) || (int) $data['price'] < 100) {
                return Http::response(['code' => 1100, 'message' => 'Invalid'], 400);
            }
            $id = sprintf('SIM-%04d-%s', ++$this->seq, strtoupper(substr(md5((string) $data['refId']), 0, 6)));
            $this->payments[$id] = ['price' => (int) $data['price'], 'curr' => (string) $data['curr'], 'refId' => (string) $data['refId'], 'status' => 'PENDING'];

            return Http::response(['code' => 0, 'message' => 'OK', 'transId' => $id, 'redirect' => 'https://payments.comgate.cz/client/instructions/index?id='.$id]);
        }
        if (preg_match('~^/payment/transId/([^/]+)(/refund)?$~', $path, $m) === 1) {
            $p = $this->payments[rawurldecode($m[1])] ?? null;
            if ($p === null) {
                return Http::response(['code' => 1400, 'message' => 'Not found'], 404);
            }
            if (($m[2] ?? '') === '/refund') {
                $this->refunds++;

                return Http::response(['code' => 0, 'message' => 'OK']);
            }

            return Http::response(['code' => 0, 'transId' => $m[1], 'status' => $p['status'], 'price' => $p['price'], 'curr' => $p['curr'], 'refId' => $p['refId']]);
        }

        return Http::response(['code' => 1500, 'message' => 'Unknown'], 404);
    }
}
