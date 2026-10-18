<?php

use App\Models\CommissionRule;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\CommissionCalculator;
use App\Services\Payments\PaymentProvider;
use App\Services\Payments\StubPaymentProvider;

it('binds the stub payment provider by default', function () {
    expect(app(PaymentProvider::class))->toBeInstanceOf(StubPaymentProvider::class);
});

it('charges zero commission by default and only for portal services', function () {
    $c = app(CommissionCalculator::class);
    CommissionRule::create(['service' => 'membership', 'percent' => 10]);
    expect($c->forService('membership', 10000))->toBe(0); // flag off
    config(['reality.features.commissions' => true]);
    expect($c->forService('membership', 10000))->toBe(1000)
        ->and($c->forService('property_price', 500000000))->toBe(0);
});

it('blocks subscriptions while flags are off', function () {
    $plan = MembershipPlan::create(['code' => 'p', 'name' => 'P', 'audience' => 'seeker', 'price_halere' => 9900]);
    $this->actingAs(User::factory()->create(['role' => 'seeker']))->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('pricing.subscribe', $plan))->assertNotFound();
});

it('activates a subscription through the stub provider when enabled', function () {
    config(['reality.features.memberships' => true, 'reality.features.payments' => true]);
    $plan = MembershipPlan::create(['code' => 'p', 'name' => 'P', 'audience' => 'seeker', 'price_halere' => 9900]);
    $u = User::factory()->create(['role' => 'seeker']);
    $this->actingAs($u)->withSession(['auth.password_confirmed_at' => time()])->post(route('pricing.subscribe', $plan))->assertRedirect(route('dashboard'));
    expect(Payment::first()->status)->toBe('paid')->and(Payment::first()->provider)->toBe('stub')
        ->and($u->subscriptions()->first()->status)->toBe('active');
});
