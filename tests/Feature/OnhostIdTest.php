<?php

use App\Auth\Jwk;
use App\Models\User;
use Illuminate\Support\Facades\Http;

const OIDC_ISS = 'https://id.onhost.test';

function oidcKeys(): array
{
    static $keys;
    if (! $keys) {
        $rsa = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $ec = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $rsaOther = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $keys = compact('rsa', 'ec', 'rsaOther');
    }

    return $keys;
}

function oidcJwk(string $which, string $kid): array
{
    $d = openssl_pkey_get_details(oidcKeys()[$which]);
    if ($d['type'] === OPENSSL_KEYTYPE_EC) {
        return ['kty' => 'EC', 'crv' => 'P-256', 'kid' => $kid, 'use' => 'sig', 'alg' => 'ES256',
            'x' => Jwk::b64urlEncode(str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT)), 'y' => Jwk::b64urlEncode(str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT))];
    }

    return ['kty' => 'RSA', 'kid' => $kid, 'use' => 'sig', 'alg' => 'RS256', 'n' => Jwk::b64urlEncode($d['rsa']['n']), 'e' => Jwk::b64urlEncode($d['rsa']['e'])];
}

function oidcJwt(array $claims, string $alg = 'RS256', string $kid = 'rsa1', string $key = 'rsa'): string
{
    $h = Jwk::b64urlEncode(json_encode(['alg' => $alg, 'kid' => $kid, 'typ' => 'JWT']));
    $p = Jwk::b64urlEncode(json_encode($claims));
    if ($alg === 'none') {
        return "$h.$p.";
    }
    if ($alg === 'HS256') {
        return "$h.$p.".Jwk::b64urlEncode(hash_hmac('sha256', "$h.$p", 'secret', true));
    }
    openssl_sign("$h.$p", $sig, oidcKeys()[$key], OPENSSL_ALGO_SHA256);
    if ($alg === 'ES256') {
        $sig = Jwk::derToRaw($sig);
    }

    return "$h.$p.".Jwk::b64urlEncode($sig);
}

function oidcClaims(array $over = []): array
{
    return array_merge(['iss' => OIDC_ISS, 'aud' => 'reality', 'sub' => 'pairwise-123', 'email' => 'nova@example.test', 'email_verified' => true,
        'name' => 'Nová Uživatelka', 'iat' => time(), 'exp' => time() + 300, 'nonce' => '__NONCE__'], $over);
}

/** Fakes the provider; $token is a closure(nonce) => id_token. $jwksSeq: list of key sets returned in order. */
function oidcFake(Closure $token, ?array $jwksSeq = null): void
{
    $jwksSeq ??= [[oidcJwk('rsa', 'rsa1'), oidcJwk('ec', 'ec1')]];
    $seq = Http::sequence();
    foreach ($jwksSeq as $set) {
        $seq->push(['keys' => $set]);
    }
    Http::fake([
        OIDC_ISS.'/.well-known/openid-configuration' => Http::response(['issuer' => OIDC_ISS, 'authorization_endpoint' => OIDC_ISS.'/authorize',
            'token_endpoint' => OIDC_ISS.'/token', 'jwks_uri' => OIDC_ISS.'/jwks']),
        OIDC_ISS.'/jwks' => $seq,
        OIDC_ISS.'/token' => function ($request) use ($token) {
            return Http::response(['access_token' => 'at', 'token_type' => 'Bearer', 'id_token' => $token(session('oidc_test_nonce'))]);
        },
    ]);
}

/** Runs redirect → callback; returns the callback response. */
function oidcFlow($test, ?Closure $token = null, ?string $state = null, ?array $jwksSeq = null)
{
    $token ??= fn ($n) => oidcJwt(oidcClaims(['nonce' => $n]));
    oidcFake($token, $jwksSeq);
    $r = $test->get('/auth/onhost/redirect');
    $r->assertRedirect();
    parse_str(parse_url($r->headers->get('Location'), PHP_URL_QUERY), $q);
    expect($q['code_challenge_method'])->toBe('S256')->and($q['client_id'])->toBe('reality');
    session(['oidc_test_nonce' => $q['nonce']]);

    return $test->get('/auth/onhost/callback?code=abc&state='.($state ?? $q['state']));
}

beforeEach(function () {
    config(['oidc.enabled' => true, 'oidc.issuer' => OIDC_ISS, 'oidc.client_id' => 'reality', 'oidc.client_secret' => 's3cret', 'oidc.redirect_uri' => 'http://localhost/auth/onhost/callback']);
});

it('returns 404 and hides the button when disabled', function () {
    config(['oidc.enabled' => false]);
    $this->get('/auth/onhost/redirect')->assertNotFound();
    $this->get('/auth/onhost/callback?code=x&state=y')->assertNotFound();
    $this->post('/auth/onhost/backchannel-logout')->assertNotFound();
    $this->get('/prihlaseni')->assertOk()->assertDontSee('Přihlásit přes ONhost ID');
});

it('shows the button when enabled and backchannel logout is prepared without CSRF', function () {
    $this->get('/prihlaseni')->assertSee('Přihlásit přes ONhost ID');
    $this->get('/registrace')->assertSee('Přihlásit přes ONhost ID');
    $this->post('/auth/onhost/backchannel-logout')->assertStatus(501);
});

it('privacy page carries the ONhost ID sentence', function () {
    $this->get('/info/ochrana-osobnich-udaju')->assertSee('IČO 08094616');
});

it('creates a new seeker on first RS256 login and logs in by sub next time', function () {
    oidcFlow($this)->assertRedirect(route('dashboard'));
    $u = User::where('onhost_sub', 'pairwise-123')->firstOrFail();
    expect($u->role)->toBe('seeker')->and($u->email)->toBe('nova@example.test')->and($u->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($u);
    Http::assertSent(fn ($r) => $r->url() === OIDC_ISS.'/token' && filled($r['code_verifier']) && $r['grant_type'] === 'authorization_code');

    $this->post('/odhlasit');
    oidcFlow($this)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($u);
    expect(User::count())->toBe(1);
});

it('accepts ES256 and leaves email unverified unless claim is true', function () {
    oidcFlow($this, fn ($n) => oidcJwt(oidcClaims(['nonce' => $n, 'email_verified' => false]), 'ES256', 'ec1', 'ec'))->assertRedirect(route('dashboard'));
    expect(User::where('onhost_sub', 'pairwise-123')->value('email_verified_at'))->toBeNull();
});

it('rejects invalid tokens', function (Closure $mk) {
    oidcFlow($this, $mk)->assertRedirect(route('login'))->assertSessionHasErrors('onhost');
    $this->assertGuest();
    expect(User::count())->toBe(0);
})->with([
    'wrong nonce' => [fn () => fn ($n) => oidcJwt(oidcClaims(['nonce' => 'other']))],
    'wrong aud' => [fn () => fn ($n) => oidcJwt(oidcClaims(['nonce' => $n, 'aud' => 'someone-else']))],
    'wrong iss' => [fn () => fn ($n) => oidcJwt(oidcClaims(['nonce' => $n, 'iss' => 'https://evil.test']))],
    'expired' => [fn () => fn ($n) => oidcJwt(oidcClaims(['nonce' => $n, 'exp' => time() - 61, 'iat' => time() - 400]))],
    'iat in future' => [fn () => fn ($n) => oidcJwt(oidcClaims(['nonce' => $n, 'iat' => time() + 120]))],
    'bad signature' => [fn () => fn ($n) => oidcJwt(oidcClaims(['nonce' => $n]), 'RS256', 'rsa1', 'rsaOther')],
    'alg none' => [fn () => fn ($n) => oidcJwt(oidcClaims(['nonce' => $n]), 'none')],
    'alg HS256' => [fn () => fn ($n) => oidcJwt(oidcClaims(['nonce' => $n]), 'HS256')],
]);

it('accepts expiry within leeway', function () {
    oidcFlow($this, fn ($n) => oidcJwt(oidcClaims(['nonce' => $n, 'exp' => time() - 30])))->assertRedirect(route('dashboard'));
});

it('rejects a wrong state without calling the token endpoint', function () {
    oidcFlow($this, null, 'forged')->assertRedirect(route('login'))->assertSessionHasErrors('onhost');
    $this->assertGuest();
    Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/token'));
});

it('refreshes JWKS once on an unknown kid', function () {
    $old = [oidcJwk('rsa', 'rsa1')];
    $new = [oidcJwk('rsaOther', 'rsa2')];
    oidcFlow($this, fn ($n) => oidcJwt(oidcClaims(['nonce' => $n]), 'RS256', 'rsa2', 'rsaOther'), null, [$old, $new])->assertRedirect(route('dashboard'));
    Http::assertSentCount(4); // discovery, token, jwks, jwks refresh
});

it('fails after a single refresh when kid stays unknown', function () {
    $old = [oidcJwk('rsa', 'rsa1')];
    oidcFlow($this, fn ($n) => oidcJwt(oidcClaims(['nonce' => $n]), 'RS256', 'zzz', 'rsaOther'), null, [$old, $old, $old])->assertSessionHasErrors('onhost');
    expect(collect(Http::recorded())->filter(fn ($p) => str_ends_with($p[0]->url(), '/jwks'))->count())->toBe(2);
});

it('never auto-links by email and offers opt-in linking with password', function () {
    $local = User::factory()->create(['email' => 'nova@example.test']);
    oidcFlow($this)->assertRedirect(route('onhost.link'));
    $this->assertGuest();
    expect($local->fresh()->onhost_sub)->toBeNull()->and(User::count())->toBe(1);

    $this->get('/auth/onhost/propojit')->assertOk()->assertSee('nova@example.test');
    $this->post('/auth/onhost/propojit', ['password' => 'wrong'])->assertSessionHasErrors('password');
    $this->assertGuest();
    expect($local->fresh()->onhost_sub)->toBeNull();

    $this->post('/auth/onhost/propojit', ['password' => 'password'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($local);
    expect($local->fresh()->onhost_sub)->toBe('pairwise-123');
});

it('links from the account page after password confirmation', function () {
    $u = User::factory()->create(['email' => 'jina@example.test']);
    $this->actingAs($u)->post('/moje/onhost-id', ['password' => 'bad'])->assertSessionHasErrors('password');

    oidcFake(fn ($n) => oidcJwt(oidcClaims(['nonce' => $n])));
    $r = $this->actingAs($u)->post('/moje/onhost-id', ['password' => 'password']);
    parse_str(parse_url($r->headers->get('Location'), PHP_URL_QUERY), $q);
    session(['oidc_test_nonce' => $q['nonce']]);
    $this->get('/auth/onhost/callback?code=abc&state='.$q['state'])->assertRedirect(route('dashboard'));
    expect($u->fresh()->onhost_sub)->toBe('pairwise-123')->and(User::count())->toBe(1);
});

it('round-trips ES256 signature formats and builds PEMs', function () {
    $raw = random_bytes(64);
    expect(Jwk::derToRaw(Jwk::rawToDer($raw)))->toBe($raw);
    expect(openssl_pkey_get_public(Jwk::toPem(oidcJwk('rsa', 'a'))))->not->toBeFalse();
    expect(openssl_pkey_get_public(Jwk::toPem(oidcJwk('ec', 'b'))))->not->toBeFalse();
});
