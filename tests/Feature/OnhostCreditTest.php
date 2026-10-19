<?php

use App\Models\MembershipPlan;
use App\Models\OnhostConnection;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

const OHC_API = 'https://id.onhost.test/v1';

function ohcEnable(): void
{
    config([
        'onhost.credit.enabled' => true, 'reality.features.memberships' => true,
        'onhost.id.issuer' => 'https://id.onhost.test', 'onhost.id.client_id' => 'reality-onhost', 'onhost.id.client_secret' => 'test-secret',
        'onhost.credit.api_url' => OHC_API,
    ]);
}

function ohcUser(bool $connected = true): User
{
    $u = User::factory()->create(['role' => 'seeker']);
    if ($connected) {
        OnhostConnection::create(['user_id' => $u->id, 'access_token' => str_repeat('a', 40), 'refresh_token' => 'rt-1', 'expires_at' => now()->addHour(), 'scope' => 'openid email wallet:charge']);
    }

    return $u;
}

function ohcPlan(): MembershipPlan
{
    return MembershipPlan::create(['code' => 'p', 'name' => 'Plus', 'audience' => 'seeker', 'price_halere' => 9900]);
}

function ohcBalance(int $purchased = 50000): array
{
    return ['data' => ['currency' => 'CZK', 'family' => 'listing', 'frozen' => false, 'purchased' => ['minor' => $purchased, 'currency' => 'CZK'],
        'bonus' => ['balance' => ['minor' => 0, 'currency' => 'CZK'], 'cap_pct' => 50, 'excluded' => false], 'charge_max' => ['minor' => 200000, 'currency' => 'CZK']]];
}

function ohcCharge(bool $replayed = false): array
{
    return ['data' => ['id' => 'chg_01', 'transaction_id' => 'tx_01', 'family' => 'listing', 'amount' => ['minor' => 9900, 'currency' => 'CZK'],
        'split' => ['main' => ['minor' => 9900], 'bonus' => ['minor' => 0]], 'external_reference' => 'reality-payment-1', 'replayed' => $replayed]];
}

function ohcPay($test, User $u, MembershipPlan $plan)
{
    return $test->actingAs($u)->withSession(['auth.password_confirmed_at' => time()])->post(route('onhost.pay.plan', $plan));
}

it('hides credit payment and its routes while the flag is off', function () {
    config(['reality.features.memberships' => true, 'reality.features.payments' => true]);
    Http::fake();
    ohcPlan();
    $u = ohcUser();
    $this->actingAs($u)->get(route('pricing'))->assertOk()->assertDontSee('Zaplatit kreditem ONhost')->assertDontSee('Kredit ONhost');
    $this->actingAs($u)->get(route('dashboard'))->assertOk()->assertDontSee('Kredit ONhost');
    $this->actingAs($u)->get('/onhost/propojit')->assertNotFound();
    ohcPay($this, $u, MembershipPlan::first())->assertNotFound();
    Http::assertNothingSent();
});

it('shows the balance and the credit option when enabled', function () {
    ohcEnable();
    Http::fake([OHC_API.'/wallet/ecosystem/balance*' => Http::response(ohcBalance(), 200)]);
    ohcPlan();
    $this->actingAs(ohcUser())->get(route('pricing'))->assertOk()->assertSee('Zaplatit kreditem ONhost')->assertSee('500 Kč');
    Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Bearer '.str_repeat('a', 40)) && str_contains($r->url(), 'family=listing'));
});

it('asks for wallet:charge with PKCE on connect and stores tokens encrypted', function () {
    ohcEnable();
    $u = ohcUser(false);
    $res = $this->actingAs($u)->get('/onhost/propojit')->assertRedirect();
    parse_str(parse_url($res->headers->get('Location'), PHP_URL_QUERY), $q);
    expect($q['scope'])->toContain('wallet:charge')->and($q['code_challenge_method'])->toBe('S256')->and($q['client_id'])->toBe('reality-onhost');

    Http::fake(['https://id.onhost.test/oidc/token' => Http::response(['access_token' => str_repeat('b', 40), 'refresh_token' => 'rt-9', 'expires_in' => 600, 'scope' => 'openid email wallet:charge'])]);
    $this->actingAs($u)->get('/onhost/callback?code=abc&state='.$q['state'])->assertRedirect();
    $raw = DB::table('onhost_connections')->where('user_id', $u->id)->value('access_token');
    expect($raw)->not->toContain(str_repeat('b', 40))->and($u->onhostConnection->access_token)->toBe(str_repeat('b', 40));
    Http::assertSent(fn (HttpRequest $r) => $r['grant_type'] === 'authorization_code' && $r['client_secret'] === 'test-secret' && isset($r['code_verifier']));
});

it('rejects a callback with a wrong state', function () {
    ohcEnable();
    Http::fake();
    $u = ohcUser(false);
    $this->actingAs($u)->get('/onhost/propojit');
    $this->actingAs($u)->get('/onhost/callback?code=abc&state=wrong')->assertRedirect(route('dashboard'))->assertSessionHas('error');
    Http::assertNothingSent();
    expect($u->onhostConnection()->exists())->toBeFalse();
});

it('pays a membership with ONhost credit and records the transaction id', function () {
    ohcEnable();
    Http::fake([OHC_API.'/wallet/ecosystem/charges' => Http::response(ohcCharge(), 201)]);
    $u = ohcUser();
    ohcPay($this, $u, ohcPlan())->assertRedirect(route('dashboard'));
    $p = Payment::sole();
    expect($p->status)->toBe('paid')->and($p->provider)->toBe('onhost_credit')->and($p->provider_ref)->toBe('tx_01')
        ->and($p->meta['onhost_charge_id'])->toBe('chg_01')->and($u->subscriptions()->first()->status)->toBe('active');
    Http::assertSent(fn (HttpRequest $r) => $r['idempotency_key'] === 'reality-payment-'.$p->id && $r['amount_minor'] === 9900
        && $r['family'] === 'listing' && $r['currency'] === 'CZK' && $r['client_id'] === 'reality-onhost' && $r['client_secret'] === 'test-secret'
        && $r->hasHeader('Authorization', 'Bearer '.str_repeat('a', 40)));
});

it('shows a Czech message and fails the payment on 402 insufficient_funds', function () {
    ohcEnable();
    Http::fake([OHC_API.'/wallet/ecosystem/charges' => Http::response(['error' => 'insufficient_funds', 'message' => 'Insufficient credit', 'status' => 402], 402)]);
    $u = ohcUser();
    ohcPay($this, $u, ohcPlan())->assertRedirect(route('pricing'))->assertSessionHas('error', fn ($m) => str_contains($m, 'nemáte dost'));
    expect(Payment::sole()->status)->toBe('failed')->and($u->subscriptions()->first()->status)->toBe('cancelled');
});

it('retries a lost charge with the same idempotency key', function () {
    ohcEnable();
    Http::fakeSequence(OHC_API.'/wallet/ecosystem/charges')->pushFailedConnection()->push(ohcCharge(true), 200);
    $u = ohcUser();
    ohcPay($this, $u, ohcPlan())->assertRedirect(route('dashboard'));
    $keys = collect(Http::recorded())->map(fn ($pair) => $pair[0]['idempotency_key'] ?? null)->filter()->values();
    expect(Payment::sole()->status)->toBe('paid')->and($keys->unique()->all())->toBe(['reality-payment-'.Payment::sole()->id]);
});

it('maps 409, 422 and 429 errors to user messages', function (int $status, string $error, string $needle) {
    ohcEnable();
    Http::fake([OHC_API.'/wallet/ecosystem/charges' => Http::response(['error' => $error, 'status' => $status], $status)]);
    ohcPay($this, ohcUser(), ohcPlan())->assertSessionHas('error', fn ($m) => str_contains($m, $needle));
})->with([
    [409, 'consent_required', 'limit'],
    [409, 'idempotency_conflict', 'podporu'],
    [422, 'ecosystem_daily_limit', 'Dnešní limit'],
    [429, 'ecosystem_rate_limited', 'za chvíli'],
    [401, 'invalid_client', 'nedostupná'],
]);

it('drops the connection when ONhost reports missing wallet consent', function () {
    ohcEnable();
    Http::fake([OHC_API.'/wallet/ecosystem/charges' => Http::response(['error' => 'wallet_consent_missing', 'status' => 403], 403)]);
    $u = ohcUser();
    ohcPay($this, $u, ohcPlan())->assertSessionHas('error');
    expect($u->onhostConnection()->exists())->toBeFalse();
});

it('refreshes an expired access token before charging', function () {
    ohcEnable();
    $u = ohcUser();
    $u->onhostConnection->update(['expires_at' => now()->subMinute()]);
    Http::fake([
        'https://id.onhost.test/oidc/token' => Http::response(['access_token' => str_repeat('c', 40), 'expires_in' => 600, 'scope' => 'openid email wallet:charge']),
        OHC_API.'/wallet/ecosystem/charges' => Http::response(ohcCharge(), 201),
    ]);
    ohcPay($this, $u, ohcPlan())->assertRedirect(route('dashboard'));
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/charges') && $r->hasHeader('Authorization', 'Bearer '.str_repeat('c', 40)));
    expect($u->onhostConnection()->first()->refresh_token)->toBe('rt-1');
});

it('reverses the charge when an admin refunds the payment', function () {
    ohcEnable();
    Http::fake([
        OHC_API.'/wallet/ecosystem/charges' => Http::response(ohcCharge(), 201),
        OHC_API.'/wallet/ecosystem/charges/chg_01/reversals' => Http::response(['data' => ['id' => 'rfd_01', 'charge_id' => 'chg_01', 'transaction_id' => 'tx_02']], 201),
    ]);
    $u = ohcUser();
    ohcPay($this, $u, ohcPlan());
    $p = Payment::sole();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->from(route('admin.dashboard'))
        ->post(route('admin.payments.onhost_refund', $p))->assertRedirect(route('admin.dashboard'));
    expect($p->fresh()->status)->toBe('refunded')->and($p->fresh()->meta['onhost_reversal_tx'])->toBe('tx_02')
        ->and($u->subscriptions()->first()->status)->toBe('cancelled');
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/reversals') && $r['idempotency_key'] === 'reality-payment-'.$p->id.'-reversal' && $r['client_secret'] === 'test-secret');
});

it('resumes an open payment after an unavailable charge instead of charging twice', function () {
    ohcEnable();
    Http::fakeSequence(OHC_API.'/wallet/ecosystem/charges')->pushFailedConnection()->pushFailedConnection()->push(ohcCharge(true), 200);
    $u = ohcUser();
    $plan = ohcPlan();
    ohcPay($this, $u, $plan)->assertRedirect(route('pricing'));
    expect(Payment::sole()->status)->toBe('created')->and($u->subscriptions()->first()->status)->toBe('pending');
    ohcPay($this, $u, $plan)->assertRedirect(route('dashboard'));
    $p = Payment::sole();
    $keys = collect(Http::recorded())->map(fn ($pair) => $pair[0]['idempotency_key'] ?? null)->filter()->unique()->values();
    expect($p->status)->toBe('paid')->and($u->subscriptions()->count())->toBe(1)
        ->and($u->subscriptions()->first()->status)->toBe('active')->and($keys->all())->toBe(['reality-payment-'.$p->id]);
});

it('does not reuse a failed payment', function () {
    ohcEnable();
    Http::fakeSequence(OHC_API.'/wallet/ecosystem/charges')
        ->push(['error' => 'insufficient_funds', 'status' => 402], 402)->push(ohcCharge(), 201);
    $u = ohcUser();
    $plan = ohcPlan();
    ohcPay($this, $u, $plan)->assertRedirect(route('pricing'));
    ohcPay($this, $u, $plan)->assertRedirect(route('dashboard'));
    expect(Payment::count())->toBe(2)->and(Payment::latest('id')->first()->status)->toBe('paid');
});

it('ignores an external Referer as the post-connect return target', function (string $referer, string $expected) {
    ohcEnable();
    $u = ohcUser(false);
    $res = $this->actingAs($u)->withHeader('Referer', $referer)->get('/onhost/propojit');
    parse_str(parse_url($res->headers->get('Location'), PHP_URL_QUERY), $q);
    Http::fake(['https://id.onhost.test/oidc/token' => Http::response(['access_token' => str_repeat('b', 40), 'refresh_token' => 'rt-9', 'expires_in' => 600, 'scope' => 'openid email wallet:charge'])]);
    $this->actingAs($u)->get('/onhost/callback?code=abc&state='.$q['state'])->assertRedirect(url($expected));
})->with([
    ['https://evil.example/phish', '/moje'],
    ['//evil.example/x', '/moje'],
    ['/\\evil.example', '/moje'],
    ['http://localhost/clenstvi', '/clenstvi'],
]);
