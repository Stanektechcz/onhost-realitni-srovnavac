<?php

namespace App\Services\Payments\Comgate;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Minimal Comgate Payments API v2.0 client (REST/JSON, HTTP Basic merchant:secret), the same contract as the ONhost control
 * plane (providers/Payments/Comgate). Shared byte-for-byte by the ecosystem projects; each project wires its own merchant.
 *
 *   POST /payment                    create (prepareOnly) -> transId + redirect
 *   GET  /payment/transId/{transId}  status — the only source of truth (a notification is never trusted on its own)
 *   POST /payment/transId/{id}/refund
 *
 * Nothing here logs a request or a response body. Tests only ever talk to Http::fake() (ComgateSimulator).
 */
final class ComgateClient
{
    public const STATES = ['PENDING', 'PAID', 'CANCELLED', 'AUTHORIZED'];

    /** @param list<string> $callbackAllowlist */
    public function __construct(
        private readonly string $merchant,
        #[\SensitiveParameter] private readonly string $secret,
        private readonly bool $test = true,
        private readonly string $baseUrl = 'https://payments.comgate.cz/v2.0',
        private readonly array $callbackAllowlist = [],
        private readonly int $timeout = 15,
    ) {}

    public function configured(): bool
    {
        return trim($this->merchant) !== '' && trim($this->secret) !== '';
    }

    /**
     * @param  array{reference: string, label: string, email?: string, method?: string, lang?: string, url_paid?: string, url_cancelled?: string, url_pending?: string}  $input
     * @return array{trans_id: string, redirect: string}
     */
    public function create(int $amountMinor, string $currency, array $input): array
    {
        if ($amountMinor < 100) {
            throw new ComgateException('amount_too_small');
        }
        $body = array_filter([
            'price' => $amountMinor,
            'curr' => strtoupper($currency),
            'label' => mb_substr(trim($input['label']), 0, 16) ?: 'Platba', // Comgate refuses an empty label
            'refId' => mb_substr($input['reference'], 0, 40),
            'method' => $input['method'] ?? 'ALL',
            'email' => $input['email'] ?? null,
            'prepareOnly' => true,
            'test' => $this->test,
            'lang' => in_array($input['lang'] ?? 'cs', ['cs', 'sk', 'en', 'pl', 'de'], true) ? ($input['lang'] ?? 'cs') : 'cs',
            'country' => 'CZ',
            'expirationTime' => '2h',
            'url_paid' => $input['url_paid'] ?? null,
            'url_cancelled' => $input['url_cancelled'] ?? null,
            'url_pending' => $input['url_pending'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $json = $this->send('post', '/payment', $body, $input['reference']);
        $transId = (string) ($json['transId'] ?? '');
        $redirect = (string) ($json['redirect'] ?? '');
        if (! self::validTransId($transId) || ! str_starts_with($redirect, 'https://')) {
            throw new ComgateException('invalid_response');
        }

        return ['trans_id' => $transId, 'redirect' => $redirect];
    }

    /** @return array{state: string, amount_minor: ?int, currency: ?string, reference: ?string} */
    public function status(string $transId): array
    {
        if (! self::validTransId($transId)) {
            throw new ComgateException('invalid_trans_id');
        }
        $json = $this->send('get', '/payment/transId/'.rawurlencode($transId));
        $state = strtoupper((string) ($json['status'] ?? 'PENDING'));

        return [
            'state' => in_array($state, self::STATES, true) ? $state : 'PENDING',
            'amount_minor' => isset($json['price']) ? (int) $json['price'] : null,
            'currency' => isset($json['curr']) ? strtoupper((string) $json['curr']) : null,
            'reference' => isset($json['refId']) ? (string) $json['refId'] : null,
        ];
    }

    public function refund(string $transId, int $amountMinor, string $currency, string $refundKey): void
    {
        if (! self::validTransId($transId) || $amountMinor <= 0) {
            throw new ComgateException('invalid_refund');
        }
        $this->send('post', '/payment/transId/'.rawurlencode($transId).'/refund', [
            'amount' => $amountMinor, 'curr' => strtoupper($currency), 'refId' => mb_substr($refundKey, 0, 40), 'test' => $this->test,
        ], $refundKey);
    }

    /**
     * Checks a push notification and returns its transId. Fail closed: the sender must be on the allow-list (an empty list
     * refuses everyone) and the shared secret must match. The caller must then re-read status() — the notification's own
     * status and price are never used.
     */
    public function verifyNotification(Request $request): string
    {
        $ip = (string) $request->ip();
        $allow = array_values(array_filter(array_map('trim', $this->callbackAllowlist), fn ($e) => $e !== '' && preg_match('~/0+$~', $e) !== 1));
        if ($allow === [] || $ip === '' || ! IpUtils::checkIp($ip, $allow)) {
            throw new ComgateException('source_rejected', 403);
        }
        $secret = $request->input('secret');
        if (! $this->configured() || ! is_string($secret) || ! hash_equals($this->secret, $secret)) {
            throw new ComgateException('signature_invalid', 403);
        }
        if ((string) $request->input('merchant', $this->merchant) !== $this->merchant) {
            throw new ComgateException('merchant_mismatch', 403);
        }
        $transId = (string) $request->input('transId', '');
        if (! self::validTransId($transId)) {
            throw new ComgateException('invalid_trans_id', 400);
        }

        return $transId;
    }

    public static function validTransId(string $transId): bool
    {
        return preg_match('/^[A-Z0-9\-]{4,40}$/i', $transId) === 1;
    }

    /** @param  string|list<string>|null  $configured  @return list<string> */
    public static function allowlist(string|array|null $configured): array
    {
        $entries = is_array($configured) ? $configured : explode(',', (string) $configured);

        return array_values(array_filter(array_map(fn ($e) => trim((string) $e), $entries), fn ($e) => $e !== ''));
    }

    /** @return array<string, mixed> */
    private function send(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array
    {
        if (! $this->configured()) {
            throw new ComgateException('not_configured');
        }
        $http = Http::withBasicAuth($this->merchant, $this->secret)->acceptJson()->asJson()->timeout($this->timeout)->withoutRedirecting();
        if ($idempotencyKey !== null) {
            $http = $http->withHeaders(['Idempotency-Key' => mb_substr($idempotencyKey, 0, 64)]);
        }
        try {
            $response = $method === 'get' ? $http->get(rtrim($this->baseUrl, '/').$path) : $http->post(rtrim($this->baseUrl, '/').$path, $body ?? []);
        } catch (ConnectionException) {
            throw new ComgateException('unreachable', 502);
        }
        $json = $response->json();
        if (! is_array($json)) {
            throw new ComgateException('invalid_response', 502);
        }
        if (! $response->successful() || (int) ($json['code'] ?? 0) !== 0) {
            throw new ComgateException('refused', 502, (int) ($json['code'] ?? 0));
        }

        return $json;
    }
}
