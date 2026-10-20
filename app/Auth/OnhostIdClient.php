<?php

namespace App\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OIDC klient pro ONhost ID (id.onhost.cz): discovery, Authorization Code + PKCE (S256), state + nonce,
 * výměna kódu, ověření id_tokenu (RS256/JWKS, iss, aud, exp, nonce) a userinfo. Tokeny ani payloady se nelogují.
 */
class OnhostIdClient
{
    public function enabled(): bool
    {
        return (bool) config('oidc.enabled') && filled(config('oidc.client_id'));
    }

    public function discovery(): array
    {
        $issuer = (string) config('oidc.issuer');

        return Cache::remember('oidc.discovery.'.md5($issuer), (int) config('oidc.discovery_ttl'), function () use ($issuer) {
            $doc = Http::timeout((int) config('oidc.timeout'))->acceptJson()->get($issuer.'/.well-known/openid-configuration')->throw()->json();
            if (! is_array($doc) || rtrim((string) ($doc['issuer'] ?? ''), '/') !== $issuer) {
                throw new RuntimeException('discovery_issuer_mismatch');
            }

            return $doc;
        });
    }

    public function redirectUri(): string
    {
        return (string) (config('oidc.redirect_uri') ?: route('oidc.callback'));
    }

    /** @return array{url: string, state: string, nonce: string, verifier: string} */
    public function authorizationRequest(): array
    {
        $state = Jwt::b64e(random_bytes(32));
        $nonce = Jwt::b64e(random_bytes(32));
        $verifier = Jwt::b64e(random_bytes(48));
        $url = $this->discovery()['authorization_endpoint'].'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('oidc.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => config('oidc.scopes'),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => Jwt::b64e(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
        ]);

        return compact('url', 'state', 'nonce', 'verifier');
    }

    /** @return array{sub: string, email: ?string, email_verified: bool, name: ?string} */
    public function authenticate(string $code, string $verifier, string $nonce): array
    {
        $d = $this->discovery();
        $tokens = Http::asForm()->timeout((int) config('oidc.timeout'))->acceptJson()
            ->withBasicAuth((string) config('oidc.client_id'), (string) config('oidc.client_secret'))
            ->post($d['token_endpoint'], [
                'grant_type' => 'authorization_code', 'code' => $code,
                'redirect_uri' => $this->redirectUri(), 'code_verifier' => $verifier,
            ]);
        if (! $tokens->successful() || ! is_string($tokens->json('id_token'))) {
            throw new RuntimeException('token_exchange_failed');
        }
        $claims = Jwt::verifyRs256($tokens->json('id_token'), $this->jwks($d['jwks_uri']));
        $this->validateClaims($claims, $nonce);

        $info = [];
        if (! empty($d['userinfo_endpoint']) && is_string($tokens->json('access_token'))) {
            $res = Http::timeout((int) config('oidc.timeout'))->acceptJson()->withToken($tokens->json('access_token'))->get($d['userinfo_endpoint']);
            // userinfo se použije jen pokud patří ke stejnému subjektu
            if ($res->successful() && ($res->json('sub') === $claims['sub'])) {
                $info = (array) $res->json();
            }
        }
        $all = $info + $claims;

        return [
            'sub' => (string) $claims['sub'],
            'email' => isset($all['email']) ? mb_strtolower((string) $all['email']) : null,
            'email_verified' => filter_var($all['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
            'name' => isset($all['name']) ? (string) $all['name'] : null,
        ];
    }

    /**
     * Ověří logout_token (OIDC Back-Channel Logout 1.0, §2.6): podpis, iss, aud, iat, jti, událost backchannel-logout,
     * sub nebo sid a zákaz nonce. Vrací sub.
     */
    public function verifyLogoutToken(string $token): string
    {
        $c = Jwt::verifyRs256($token, $this->jwks($this->discovery()['jwks_uri']));
        $aud = (array) ($c['aud'] ?? []);
        $leeway = (int) config('oidc.leeway');
        $fail = match (true) {
            rtrim((string) ($c['iss'] ?? ''), '/') !== config('oidc.issuer') => 'bad_iss',
            ! in_array(config('oidc.client_id'), $aud, true) => 'bad_aud',
            ! isset($c['iat']) || abs(time() - (int) $c['iat']) > 300 + $leeway => 'bad_iat',
            isset($c['exp']) && time() > $c['exp'] + $leeway => 'expired',
            ! is_string($c['jti'] ?? null) || $c['jti'] === '' => 'no_jti',
            ! is_array($c['events']['http://schemas.openid.net/event/backchannel-logout'] ?? null) => 'no_event',
            array_key_exists('nonce', $c) => 'nonce_present',
            ! is_string($c['sub'] ?? null) || $c['sub'] === '' => 'no_sub',
            default => null,
        };
        if ($fail) {
            throw new RuntimeException($fail);
        }
        // jednorázovost: stejné jti nesmí projít dvakrát
        if (! Cache::add('oidc.logout_jti.'.hash('sha256', $c['jti']), 1, 600)) {
            throw new RuntimeException('replay');
        }

        return $c['sub'];
    }

    private function jwks(string $uri): array
    {
        return Cache::remember('oidc.jwks.'.md5($uri), (int) config('oidc.discovery_ttl'),
            fn () => (array) Http::timeout((int) config('oidc.timeout'))->acceptJson()->get($uri)->throw()->json());
    }

    private function validateClaims(array $c, string $nonce): void
    {
        $now = time();
        $leeway = (int) config('oidc.leeway');
        $aud = (array) ($c['aud'] ?? []);
        $fail = match (true) {
            rtrim((string) ($c['iss'] ?? ''), '/') !== config('oidc.issuer') => 'bad_iss',
            ! in_array(config('oidc.client_id'), $aud, true) => 'bad_aud',
            count($aud) > 1 && ($c['azp'] ?? null) !== config('oidc.client_id') => 'bad_azp',
            ! isset($c['exp']) || $now > $c['exp'] + $leeway => 'expired',
            isset($c['iat']) && $now < $c['iat'] - $leeway => 'iat_future',
            ! is_string($c['nonce'] ?? null) || ! hash_equals($nonce, $c['nonce']) => 'bad_nonce',
            ! is_string($c['sub'] ?? null) || $c['sub'] === '' => 'no_sub',
            default => null,
        };
        if ($fail) {
            throw new RuntimeException($fail);
        }
    }
}
