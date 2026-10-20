<?php

namespace App\Services\Onhost;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server client of ONhost's ecosystem wallet API (/v1/wallet/ecosystem/*). Sends the user's ONhost ID access token
 * as Bearer and, for charges and reversals, this app's client_id/client_secret in the body. A network failure is retried once
 * with the same idempotency key (ONhost answers a replay with the original charge). Secrets and tokens are never logged.
 */
class OnhostCreditClient
{
    public function __construct(private readonly OnhostIdClient $id) {}

    public static function enabled(): bool
    {
        return (bool) config('onhost.credit.enabled');
    }

    /** @return array<string, mixed> ONhost's balance view: purchased.minor, bonus.balance.minor, bonus.cap_pct, charge_max.minor, frozen */
    public function balance(User $user): array
    {
        $family = config('onhost.credit.family');
        $currency = config('onhost.credit.currency');

        return $this->send($user, fn (PendingRequest $http) => $http->get($this->url('/wallet/ecosystem/balance'), ['currency' => $currency, 'family' => $family]));
    }

    /** @return array<string, mixed> the charge: id, transaction_id, amount, split, replayed */
    public function charge(User $user, string $idempotencyKey, int $amountMinor, string $description, string $externalReference): array
    {
        $body = [
            'idempotency_key' => $idempotencyKey, 'amount_minor' => $amountMinor, 'currency' => config('onhost.credit.currency'),
            'family' => config('onhost.credit.family'), 'allow_bonus' => (bool) config('onhost.credit.allow_bonus'),
            'description' => mb_substr($description, 0, 200), 'external_reference' => mb_substr($externalReference, 0, 100),
        ] + $this->clientAuth();

        return $this->send($user, fn (PendingRequest $http) => $http->post($this->url('/wallet/ecosystem/charges'), $body), retry: true);
    }

    /** @return array<string, mixed> the reversal: id, charge_id, transaction_id, refunded, remaining */
    public function reverse(User $user, string $chargeId, string $idempotencyKey, ?int $amountMinor = null, ?string $reason = null): array
    {
        $body = array_filter(['idempotency_key' => $idempotencyKey, 'amount_minor' => $amountMinor, 'reason' => $reason], fn ($v) => $v !== null) + $this->clientAuth();

        return $this->send($user, fn (PendingRequest $http) => $http->post($this->url('/wallet/ecosystem/charges/'.rawurlencode($chargeId).'/reversals'), $body), retry: true);
    }

    /** @return array<string, mixed> */
    private function send(User $user, callable $call, bool $retry = false): array
    {
        $http = Http::acceptJson()->asJson()->withToken($this->id->accessToken($user))
            ->timeout((int) config('onhost.credit.timeout', 10))->connectTimeout((int) config('onhost.credit.connect_timeout', 3));
        $attempts = $retry ? 2 : 1;
        for ($i = 1; ; $i++) {
            try {
                $response = $call($http);
                break;
            } catch (ConnectionException) {
                if ($i >= $attempts) {
                    Log::warning('onhost credit api unreachable');
                    throw new OnhostCreditException('unavailable');
                }
            }
        }

        return $this->unwrap($response);
    }

    /** @return array<string, mixed> */
    private function unwrap(Response $response): array
    {
        if ($response->successful() && is_array($response->json('data'))) {
            return $response->json('data');
        }
        $error = (string) ($response->json('error') ?: ($response->status() === 404 ? 'unavailable' : 'error'));
        // slug and status only, never the payload
        Log::notice('onhost credit api refused', ['status' => $response->status(), 'error' => $error]);
        throw new OnhostCreditException($error, $response->status());
    }

    /** @return array{client_id: string, client_secret: string} */
    private function clientAuth(): array
    {
        return ['client_id' => (string) config('onhost.id.client_id'), 'client_secret' => (string) config('onhost.id.client_secret')];
    }

    private function url(string $path): string
    {
        return config('onhost.credit.api_url').$path;
    }
}
