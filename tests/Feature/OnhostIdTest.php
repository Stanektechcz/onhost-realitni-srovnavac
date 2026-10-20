<?php

use App\Auth\Jwt;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Lokálně vygenerovaný RSA klíč pro podepsání testovacích JWT (nic se nestahuje). */
function oidcKey(): array
{
    static $key;
    if (! $key) {
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $d = openssl_pkey_get_details($res);
        $key = ['res' => $res, 'n' => Jwt::b64e($d['rsa']['n']), 'e' => Jwt::b64e($d['rsa']['e'])];
    }

    return $key;
}

function oidcJwt(array $claims, string $kid = 'k1'): string
{
    $h = Jwt::b64e(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $kid]));
    $p = Jwt::b64e(json_encode($claims));
    openssl_sign("$h.$p", $sig, oidcKey()['res'], OPENSSL_ALGO_SHA256);

    return "$h.$p.".Jwt::b64e($sig);
}

function oidcEnable(): void
{
    config(['oidc.enabled' => true, 'oidc.issuer' => 'https://id.onhost.test', 'oidc.client_id' => 'reality',
        'oidc.client_secret' => 'client-secret-placeholder', 'oidc.redirect_uri' => 'https://reality.test/auth/onhost/callback']);
}

/** Http::fake pro discovery, JWKS, token a userinfo; $claims přepíše výchozí claimy id_tokenu. */
function oidcFake(array $claims = [], array $userinfo = [], ?Closure $token = null): void
{
    Http::fake([
        'id.onhost.test/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://id.onhost.test', 'authorization_endpoint' => 'https://id.onhost.test/authorize',
            'token_endpoint' => 'https://id.onhost.test/token', 'userinfo_endpoint' => 'https://id.onhost.test/userinfo',
            'jwks_uri' => 'https://id.onhost.test/jwks',
        ]),
        'id.onhost.test/jwks' => Http::response(['keys' => [['kty' => 'RSA', 'kid' => 'k1', 'use' => 'sig', 'alg' => 'RS256', 'n' => oidcKey()['n'], 'e' => oidcKey()['e']]]]),
        'id.onhost.test/token' => $token ?? function (Request $r) use ($claims) {
            $nonce = session('oidc.nonce') ?? $GLOBALS['oidcNonce'];

            return Http::response(['access_token' => 'at-placeholder', 'token_type' => 'Bearer', 'id_token' => oidcJwt($claims + [
                'iss' => 'https://id.onhost.test', 'aud' => 'reality', 'sub' => 'onhost-user-42', 'exp' => time() + 300,
                'iat' => time(), 'nonce' => $nonce, 'email' => 'hrac@onhost.test', 'email_verified' => true, 'name' => 'Hráč ONhost',
            ])]);
        },
        'id.onhost.test/userinfo' => Http::response($userinfo + ['sub' => 'onhost-user-42', 'email' => 'hrac@onhost.test', 'email_verified' => true, 'name' => 'Hráč ONhost']),
    ]);
}

/** Projde redirect → callback; vrátí odpověď callbacku. */
function oidcRoundTrip(?string $state = null)
{
    $redirect = test()->get('/auth/onhost');
    $redirect->assertRedirect();
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $q);
    $GLOBALS['oidcNonce'] = $q['nonce'];

    return test()->get('/auth/onhost/callback?code=auth-code&state='.($state ?? $q['state']));
}

it('is disabled by default (feature flag)', function () {
    $this->get('/auth/onhost')->assertNotFound();
    $this->get('/auth/onhost/callback?code=x&state=y')->assertNotFound();
    $this->get('/prihlaseni')->assertOk()->assertDontSee('data-oidc-login', false);
});

it('builds an authorization request with PKCE S256, state and nonce from discovery', function () {
    oidcEnable();
    oidcFake();
    $res = $this->get('/auth/onhost')->assertRedirect();
    $url = $res->headers->get('Location');
    parse_str(parse_url($url, PHP_URL_QUERY), $q);

    expect($url)->toStartWith('https://id.onhost.test/authorize?')
        ->and($q['response_type'])->toBe('code')->and($q['client_id'])->toBe('reality')
        ->and($q['code_challenge_method'])->toBe('S256')->and($q['state'])->toBe(session('oidc.state'))
        ->and($q['code_challenge'])->toBe(Jwt::b64e(hash('sha256', session('oidc.verifier'), true)))
        ->and($q['scope'])->toBe('openid email profile');
    $this->get('/prihlaseni')->assertSee('data-oidc-login', false);
});

it('registers and logs in a new user linked by onhost_sub', function () {
    oidcEnable();
    oidcFake();
    oidcRoundTrip()->assertRedirect();

    $user = User::where('onhost_sub', 'onhost-user-42')->first();
    expect($user)->not->toBeNull()->and($user->email)->toBe('hrac@onhost.test');
    $this->assertAuthenticatedAs($user);
    Http::assertSent(fn (Request $r) => $r->url() === 'https://id.onhost.test/token'
        && $r['grant_type'] === 'authorization_code' && $r['code'] === 'auth-code' && strlen($r['code_verifier']) > 40
        && $r->hasHeader('Authorization'));
});

it('logs in an existing linked user by subject even if the e-mail changed', function () {
    oidcEnable();
    oidcFake(['email' => 'novy@onhost.test'], ['email' => 'novy@onhost.test']);
    $user = User::factory()->create(['email' => 'stary@example.test']);
    $user->forceFill(['onhost_sub' => 'onhost-user-42'])->save();

    oidcRoundTrip()->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

it('does not auto-link an existing local account with the same e-mail', function () {
    oidcEnable();
    oidcFake();
    User::factory()->create(['email' => 'hrac@onhost.test']);

    oidcRoundTrip()->assertRedirect('/prihlaseni')->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(User::whereNotNull('onhost_sub')->count())->toBe(0);
});

it('links the logged-in account only after a fresh password confirmation', function () {
    oidcEnable();
    oidcFake();
    $user = User::factory()->create(['email' => 'hrac@onhost.test']);
    $this->actingAs($user);
    $this->get('/moje/onhost-id/propojit')->assertRedirect('/potvrdit-heslo');

    $this->withSession(['auth.password_confirmed_at' => time()]);
    $redirect = $this->get('/moje/onhost-id/propojit')->assertRedirect();
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $q);
    $GLOBALS['oidcNonce'] = $q['nonce'];
    $this->get('/auth/onhost/callback?code=auth-code&state='.$q['state'])->assertRedirect('/moje');
    expect($user->refresh()->onhost_sub)->toBe('onhost-user-42');
});

it('refuses to link a subject already bound to another account', function () {
    oidcEnable();
    oidcFake();
    User::factory()->create()->forceFill(['onhost_sub' => 'onhost-user-42'])->save();
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    $redirect = $this->get('/moje/onhost-id/propojit');
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $q);
    $GLOBALS['oidcNonce'] = $q['nonce'];
    $this->get('/auth/onhost/callback?code=auth-code&state='.$q['state'])->assertRedirect('/moje')->assertSessionHasErrors('onhost');
    expect($user->refresh()->onhost_sub)->toBeNull();
});

it('does not log in a banned linked user', function () {
    oidcEnable();
    oidcFake();
    $user = User::factory()->create();
    $user->forceFill(['onhost_sub' => 'onhost-user-42', 'is_banned' => true])->save();
    oidcRoundTrip()->assertRedirect('/prihlaseni');
    $this->assertGuest();
});

it('registers new ONhost ID users as seekers, never admins', function () {
    oidcEnable();
    oidcFake(['role' => 'admin'], ['role' => 'admin']);
    oidcRoundTrip();
    expect(User::where('onhost_sub', 'onhost-user-42')->value('role'))->toBe('seeker');
});

it('rejects a wrong state', function () {
    oidcEnable();
    oidcFake();
    oidcRoundTrip('forged-state')->assertRedirect('/prihlaseni');
    $this->assertGuest();
    Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/token'));
});

it('rejects tokens with bad nonce, audience, issuer, expiry or unverified e-mail', function (array $claims) {
    oidcEnable();
    oidcFake($claims, array_intersect_key($claims, ['email_verified' => 1]));
    oidcRoundTrip()->assertRedirect('/prihlaseni');
    $this->assertGuest();
})->with([
    'nonce' => [['nonce' => 'other']],
    'aud' => [['aud' => 'someone-else']],
    'iss' => [['iss' => 'https://evil.test']],
    'exp' => [['exp' => time() - 3600]],
    'unverified email' => [['email_verified' => false]],
]);

it('rejects an id_token signed by an unknown key', function () {
    oidcEnable();
    $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    oidcFake([], [], function () use ($other) {
        $h = Jwt::b64e(json_encode(['alg' => 'RS256', 'kid' => 'k1']));
        $p = Jwt::b64e(json_encode(['iss' => 'https://id.onhost.test', 'aud' => 'reality', 'sub' => 'x', 'exp' => time() + 60, 'nonce' => $GLOBALS['oidcNonce']]));
        openssl_sign("$h.$p", $sig, $other, OPENSSL_ALGO_SHA256);

        return Http::response(['id_token' => "$h.$p.".Jwt::b64e($sig), 'access_token' => 'a']);
    });
    oidcRoundTrip()->assertRedirect('/prihlaseni');
    $this->assertGuest();
});

function oidcLogoutToken(array $claims = []): string
{
    return oidcJwt($claims + ['iss' => 'https://id.onhost.test', 'aud' => 'reality', 'iat' => time(), 'jti' => bin2hex(random_bytes(8)),
        'sub' => 'onhost-user-42', 'events' => ['http://schemas.openid.net/event/backchannel-logout' => new stdClass]]);
}

it('keeps back-channel logout off without its own flag', function () {
    oidcEnable();
    oidcFake();
    $this->post('/auth/onhost/backchannel-logout', ['logout_token' => oidcLogoutToken()])->assertNotFound();
});

it('ends all sessions of the linked user on a valid back-channel logout', function () {
    oidcEnable();
    config(['oidc.backchannel_logout' => true, 'session.driver' => 'database']);
    oidcFake();
    $user = User::factory()->create();
    $user->forceFill(['onhost_sub' => 'onhost-user-42', 'remember_token' => 'old'])->save();
    $other = User::factory()->create();
    DB::table('sessions')->insert([
        ['id' => 's1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 's2', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
    ]);

    $token = oidcLogoutToken();
    $this->post('/auth/onhost/backchannel-logout', ['logout_token' => $token])->assertOk();
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 's2')->exists())->toBeTrue()
        ->and($user->refresh()->remember_token)->not->toBe('old');
    // replay stejného jti je odmítnut
    $this->post('/auth/onhost/backchannel-logout', ['logout_token' => $token])->assertStatus(400);
});

it('rejects invalid logout tokens', function (array $claims) {
    oidcEnable();
    config(['oidc.backchannel_logout' => true]);
    oidcFake();
    $this->post('/auth/onhost/backchannel-logout', ['logout_token' => oidcLogoutToken($claims)])->assertStatus(400);
})->with([
    'aud' => [['aud' => 'someone-else']],
    'iss' => [['iss' => 'https://evil.test']],
    'nonce' => [['nonce' => 'x']],
    'event' => [['events' => ['other' => new stdClass]]],
    'stale iat' => [['iat' => time() - 3600]],
]);
