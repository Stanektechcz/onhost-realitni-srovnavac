<?php

use App\Models\AuditLog;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\Comgate\ComgateException;
use App\Services\Payments\ComgatePaymentProvider;
use App\Services\Payments\PaymentProvider;
use App\Services\Payments\StubPaymentProvider;
use Tests\Support\ComgateSimulator;

function realityComgate(): array
{
    config(['reality.features.memberships' => true, 'reality.features.payments' => true, 'reality.payments.provider' => 'comgate',
        'reality.payments.comgate' => ['merchant' => 'M1', 'secret' => 'S3CRET', 'test' => true, 'base_url' => 'https://payments.comgate.cz/v2.0', 'callback_ips' => '10.9.9.0/24']]);
    $sim = ComgateSimulator::install();
    $plan = MembershipPlan::create(['code' => 'p', 'name' => 'P', 'audience' => 'seeker', 'price_halere' => 9900, 'period_days' => 30]);
    $user = User::factory()->create(['role' => 'seeker']);

    return [$sim, $plan, $user];
}

function realitySubscribe($test, $user, $plan)
{
    return $test->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('pricing.subscribe', $plan));
}

it('binds Comgate when configured', function () {
    config(['reality.payments.provider' => 'comgate']);
    expect(app(PaymentProvider::class))->toBeInstanceOf(ComgatePaymentProvider::class);
});

it('redirects to the gateway and activates only after the status re-read says PAID', function () {
    [$sim, $plan, $user] = realityComgate();
    realitySubscribe($this, $user, $plan)->assertRedirectContains('https://payments.comgate.cz/');
    $p = Payment::first();
    expect($p->status)->toBe('created')->and($p->provider_ref)->toBe($sim->lastTransId())
        ->and($sim->payments[$p->provider_ref]['price'])->toBe(9900)->and($user->subscriptions()->first()->status)->toBe('pending');

    $this->actingAs($user)->get(route('payments.return', $p))->assertRedirect(route('dashboard'));
    expect($p->refresh()->status)->toBe('created'); // still pending at the gateway

    $sim->markPaid($p->provider_ref);
    $this->actingAs($user)->get(route('payments.return', $p))->assertSessionHas('status');
    expect($p->refresh()->status)->toBe('paid')->and($user->subscriptions()->first()->status)->toBe('active');
});

it('settles through a verified notification, once', function () {
    [$sim, $plan, $user] = realityComgate();
    realitySubscribe($this, $user, $plan);
    $p = Payment::first();
    $sim->markPaid($p->provider_ref);
    $send = fn (array $body, string $ip = '10.9.9.5') => $this->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('payments.notify'), $body);

    $send(['transId' => $p->provider_ref, 'secret' => 'S3CRET', 'merchant' => 'M1'], '8.8.8.8')->assertForbidden();
    $send(['transId' => $p->provider_ref, 'secret' => 'wrong', 'merchant' => 'M1'])->assertForbidden();
    expect($p->refresh()->status)->toBe('created');

    $send(['transId' => $p->provider_ref, 'secret' => 'S3CRET', 'merchant' => 'M1', 'status' => 'PAID'])->assertOk()->assertSee('code=0');
    $send(['transId' => $p->provider_ref, 'secret' => 'S3CRET', 'merchant' => 'M1', 'status' => 'PAID'])->assertOk();
    expect($p->refresh()->status)->toBe('paid')->and($user->subscriptions()->first()->status)->toBe('active')
        ->and(AuditLog::where('action', 'payment.paid')->count())->toBe(1);
});

it('ignores a notification that claims PAID while the gateway says otherwise', function () {
    [$sim, $plan, $user] = realityComgate();
    realitySubscribe($this, $user, $plan);
    $p = Payment::first();
    $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.5'])->post(route('payments.notify'), ['transId' => $p->provider_ref, 'secret' => 'S3CRET', 'status' => 'PAID'])->assertOk();
    expect($p->refresh()->status)->toBe('created');
});

it('refuses every notification when the allow-list is empty', function () {
    [$sim, $plan, $user] = realityComgate();
    config(['reality.payments.comgate.callback_ips' => '']);
    $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.5'])->post(route('payments.notify'), ['transId' => 'SIM-0001-AAAAAA', 'secret' => 'S3CRET'])->assertForbidden();
});

it('cancels the subscription when the payer cancels', function () {
    [$sim, $plan, $user] = realityComgate();
    realitySubscribe($this, $user, $plan);
    $p = Payment::first();
    $sim->markCancelled($p->provider_ref);
    $this->actingAs($user)->get(route('payments.return', $p));
    expect($p->refresh()->status)->toBe('failed')->and($user->subscriptions()->first()->status)->toBe('cancelled');
});

it('rejects a PAID status whose amount does not match', function () {
    [$sim, $plan, $user] = realityComgate();
    realitySubscribe($this, $user, $plan);
    $p = Payment::first();
    $sim->payments[$p->provider_ref]['price'] = 100;
    $sim->markPaid($p->provider_ref);
    $this->actingAs($user)->get(route('payments.return', $p));
    expect($p->refresh()->status)->toBe('failed')->and($user->subscriptions()->first()->status)->toBe('cancelled');
});

it('does not let another user settle or see a payment', function () {
    [$sim, $plan, $user] = realityComgate();
    realitySubscribe($this, $user, $plan);
    $this->actingAs(User::factory()->create())->get(route('payments.return', Payment::first()))->assertNotFound();
});

it('fails cleanly when the gateway is down', function () {
    [$sim, $plan, $user] = realityComgate();
    config(['reality.payments.comgate.secret' => '']);
    realitySubscribe($this, $user, $plan)->assertRedirect(route('pricing'))->assertSessionHasErrors('payment');
    expect(Payment::first()->status)->toBe('failed');
});

it('never runs the stub in production', function () {
    app()->detectEnvironment(fn () => 'production');
    $payment = new Payment(['service' => 'membership', 'provider' => 'stub', 'amount_halere' => 9900, 'status' => 'created']);
    expect(fn () => (new StubPaymentProvider)->create($payment))
        ->toThrow(ComgateException::class);
});
