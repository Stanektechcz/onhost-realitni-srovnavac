<?php

namespace App\Services\Onhost;

use App\Models\OnhostConnection;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Minimal server-side ONhost ID (OIDC) client: authorization code + PKCE (S256), confidential (client_secret_post), refresh.
 * This app has its own e-mail/password login; ONhost ID is used only to connect the account for ONhost credit payments.
 * Tokens stay on the server (OnhostConnection, encrypted at rest) and are never logged.
 */
class OnhostIdClient
{
    public function configured(): bool
    {
        return config('onhost.id.issuer') !== '' && filled(config('onhost.id.client_id')) && filled(config('onhost.id.client_secret'));
    }

    /** Scopes asked for; `wallet:charge` only while the credit flag is on (ONhost then shows the organization picker on consent). */
    public function scopes(): string
    {
        return config('onhost.credit.enabled') ? 'openid email wallet:charge' : 'openid email';
    }

    /** @return array{url: string, state: string, verifier: string} */
    public function authorizationRequest(): array
    {
        $state = Str::random(40);
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $query = http_build_query([
            'response_type' => 'code', 'client_id' => config('onhost.id.client_id'), 'redirect_uri' => $this->redirectUri(),
            'scope' => $this->scopes(), 'state' => $state, 'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
            'prompt' => 'consent',
        ], '', '&', PHP_QUERY_RFC3986);

        return ['url' => config('onhost.id.issuer').'/oidc/authorize?'.$query, 'state' => $state, 'verifier' => $verifier];
    }

    public function exchangeCode(User $user, string $code, string $verifier): OnhostConnection
    {
        $tokens = $this->tokenRequest(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri(), 'code_verifier' => $verifier]);

        return $this->store($user, $tokens);
    }

    /** A usable access token for the user, refreshed when it expires within 30 s. */
    public function accessToken(User $user): string
    {
        $conn = $user->onhostConnection()->first();
        if ($conn === null) {
            throw new OnhostCreditException('not_connected');
        }
        if ($conn->expires_at === null || $conn->expires_at->isAfter(now()->addSeconds(30))) {
            return $conn->access_token;
        }
        if (! $conn->refresh_token) {
            $conn->delete();
            throw new OnhostCreditException('invalid_token', 401);
        }
        try {
            $tokens = $this->tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $conn->refresh_token]);
        } catch (OnhostCreditException $e) {
            if ($e->status >= 400 && $e->status < 500) {
                $conn->delete(); // refresh token revoked or expired: the user connects again
                throw new OnhostCreditException('invalid_token', 401);
            }
            throw $e;
        }

        return $this->store($user, $tokens + ['refresh_token' => $conn->refresh_token])->access_token;
    }

    public function disconnect(User $user): void
    {
        $user->onhostConnection()->delete();
    }

    public function redirectUri(): string
    {
        return config('onhost.id.redirect') ?: route('onhost.callback');
    }

    /** @return array<string, mixed> */
    private function tokenRequest(array $form): array
    {
        try {
            $response = $this->http()->asForm()->post(config('onhost.id.issuer').'/oidc/token', $form + [
                'client_id' => config('onhost.id.client_id'), 'client_secret' => config('onhost.id.client_secret'),
            ]);
        } catch (ConnectionException) {
            Log::warning('onhost id token endpoint unreachable');
            throw new OnhostCreditException('unavailable');
        }
        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            // never log the body: it may echo codes or tokens
            Log::warning('onhost id token request refused', ['status' => $response->status(), 'error' => (string) $response->json('error')]);
            throw new OnhostCreditException((string) ($response->json('error') ?: 'unavailable'), $response->status());
        }

        return (array) $response->json();
    }

    private function store(User $user, array $tokens): OnhostConnection
    {
        return OnhostConnection::updateOrCreate(['user_id' => $user->id], [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_at' => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
            'scope' => $tokens['scope'] ?? $this->scopes(),
        ]);
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->timeout((int) config('onhost.credit.timeout', 10))->connectTimeout((int) config('onhost.credit.connect_timeout', 3));
    }
}
