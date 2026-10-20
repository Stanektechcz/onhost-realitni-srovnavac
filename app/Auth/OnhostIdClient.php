<?php

namespace App\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * OpenID Connect relying party for ONhost ID (Authorization Code + PKCE S256).
 * Never logs provider payloads; failures surface as RuntimeException with a generic message.
 */
class OnhostIdClient
{
    private const ALGS = ['RS256' => OPENSSL_ALGO_SHA256, 'ES256' => OPENSSL_ALGO_SHA256];

    public function enabled(): bool
    {
        return (bool) config('oidc.enabled') && config('oidc.issuer') && config('oidc.client_id');
    }

    public function discovery(): array
    {
        $issuer = config('oidc.issuer');

        return Cache::remember('oidc:discovery:'.sha1($issuer), config('oidc.cache_ttl', 3600), function () use ($issuer) {
            $res = Http::acceptJson()->timeout(10)->get($issuer.'/.well-known/openid-configuration');
            $doc = $res->successful() ? $res->json() : null;
            if (! is_array($doc) || ($doc['issuer'] ?? null) !== $issuer || empty($doc['authorization_endpoint']) || empty($doc['token_endpoint']) || empty($doc['jwks_uri'])) {
                throw new RuntimeException('OIDC discovery failed.');
            }

            return $doc;
        });
    }

    /** Builds the authorization URL and returns [url, state, nonce, code_verifier]. */
    public function authorizationRequest(): array
    {
        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = Jwk::b64urlEncode(random_bytes(48));
        $challenge = Jwk::b64urlEncode(hash('sha256', $verifier, true));
        $url = $this->discovery()['authorization_endpoint'].'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('oidc.client_id'),
            'redirect_uri' => config('oidc.redirect_uri'),
            'scope' => config('oidc.scopes'),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return [$url, $state, $nonce, $verifier];
    }

    /** Exchanges the code and returns verified ID token claims. */
    public function exchange(string $code, string $verifier, string $nonce): array
    {
        $res = Http::asForm()->acceptJson()->timeout(10)
            ->withBasicAuth((string) config('oidc.client_id'), (string) config('oidc.client_secret'))
            ->post($this->discovery()['token_endpoint'], [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => config('oidc.redirect_uri'),
                'code_verifier' => $verifier,
            ]);
        $idToken = $res->successful() ? $res->json('id_token') : null;
        if (! is_string($idToken)) {
            throw new RuntimeException('OIDC token exchange failed.');
        }

        return $this->verifyIdToken($idToken, $nonce);
    }

    public function verifyIdToken(string $jwt, string $nonce): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed ID token.');
        }
        [$h, $p, $s] = $parts;
        $header = json_decode(Jwk::b64urlDecode($h), true);
        $claims = json_decode(Jwk::b64urlDecode($p), true);
        if (! is_array($header) || ! is_array($claims)) {
            throw new RuntimeException('Malformed ID token.');
        }
        $alg = $header['alg'] ?? null;
        if (! is_string($alg) || ! isset(self::ALGS[$alg])) {
            throw new RuntimeException('Unsupported ID token algorithm.');
        }
        $kid = $header['kid'] ?? null;
        $jwk = $this->findKey($kid, $alg);
        $sig = Jwk::b64urlDecode($s);
        if ($alg === 'ES256') {
            $sig = Jwk::rawToDer($sig);
        }
        if (openssl_verify("$h.$p", $sig, Jwk::toPem($jwk), self::ALGS[$alg]) !== 1) {
            throw new RuntimeException('Invalid ID token signature.');
        }

        $now = time();
        $leeway = (int) config('oidc.leeway', 60);
        $aud = (array) ($claims['aud'] ?? []);
        $checks = [
            ($claims['iss'] ?? null) === config('oidc.issuer'),
            in_array(config('oidc.client_id'), $aud, true),
            count($aud) === 1 || ($claims['azp'] ?? null) === config('oidc.client_id'),
            is_numeric($claims['exp'] ?? null) && $claims['exp'] + $leeway >= $now,
            is_numeric($claims['iat'] ?? null) && $claims['iat'] - $leeway <= $now,
            ! isset($claims['nbf']) || (is_numeric($claims['nbf']) && $claims['nbf'] - $leeway <= $now),
            is_string($claims['nonce'] ?? null) && hash_equals($nonce, $claims['nonce']),
            is_string($claims['sub'] ?? null) && $claims['sub'] !== '' && strlen($claims['sub']) <= 255,
        ];
        if (in_array(false, $checks, true)) {
            throw new RuntimeException('ID token claims rejected.');
        }

        return $claims;
    }

    private function findKey(mixed $kid, string $alg): array
    {
        foreach ([false, true] as $refresh) {
            foreach ($this->jwks($refresh) as $key) {
                if (! is_array($key) || ($kid !== null && ($key['kid'] ?? null) !== $kid)) {
                    continue;
                }
                $kty = $alg === 'RS256' ? 'RSA' : 'EC';
                if (($key['kty'] ?? null) === $kty && (! isset($key['alg']) || $key['alg'] === $alg) && (! isset($key['use']) || $key['use'] === 'sig')) {
                    return $key;
                }
            }
        }

        throw new RuntimeException('Unknown ID token key.');
    }

    private function jwks(bool $refresh): array
    {
        $key = 'oidc:jwks:'.sha1(config('oidc.issuer'));
        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, config('oidc.cache_ttl', 3600), function () {
            $res = Http::acceptJson()->timeout(10)->get($this->discovery()['jwks_uri']);
            $keys = $res->successful() ? $res->json('keys') : null;
            if (! is_array($keys)) {
                throw new RuntimeException('JWKS fetch failed.');
            }

            return $keys;
        });
    }
}
