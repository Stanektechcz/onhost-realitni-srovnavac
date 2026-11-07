<?php

use App\Mail\RentalContractCode;
use App\Mail\RentalContractCompleted;
use App\Mail\RentalContractInvitation;
use App\Models\CommissionRule;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\RentalContract;
use App\Models\User;
use App\Models\ViewingSlot;
use App\Services\Contracts\RentalContractService;
use App\Services\Contracts\SpdPayment;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['reality.features.contracts' => true]);
    Mail::fake();
});

function rcPayload(array $over = []): array
{
    return array_replace_recursive([
        'landlord' => ['name' => 'Jan Novák', 'address' => 'Dlouhá 1, Praha', 'birth_or_ico' => '1. 1. 1970', 'email' => 'landlord@example.test', 'phone' => '+420 600 000 000'],
        'tenant' => ['name' => 'Eva Svobodová', 'address' => 'Krátká 2, Brno', 'birth_or_ico' => '2. 2. 1990', 'email' => 'tenant@example.test', 'phone' => ''],
        'property' => ['address' => 'Vinohradská 10, Praha', 'disposition' => '2+kk', 'area_m2' => 55, 'unit_no' => '12'],
        'rent' => 20000, 'fees' => 3000, 'deposit' => 40000,
        'terms' => ['start_date' => now()->addDays(10)->toDateString(), 'term' => 'fixed', 'end_date' => now()->addYear()->toDateString(),
            'notice_months' => 3, 'pets' => 0, 'occupants' => 2, 'payment_day' => 15, 'account' => '19-2000145399/0800', 'meter_readings' => '', 'special_terms' => ''],
    ], $over);
}

function rcSetup(): array
{
    $landlord = User::factory()->create(['role' => 'owner', 'name' => 'Jan Novák', 'email' => 'landlord@example.test']);
    $tenant = User::factory()->create(['role' => 'seeker', 'name' => 'Eva Svobodová', 'email' => 'tenant@example.test']);
    $listing = Listing::factory()->create(['user_id' => $landlord->id, 'price_halere' => 2000000]);

    return [$landlord, $tenant, $listing];
}

function rcConfirmed($test, User $u) { return $test->actingAs($u)->withSession(['auth.password_confirmed_at' => time()]); }

function rcCreate($test, User $landlord, Listing $listing, array $over = []): RentalContract
{
    $test->actingAs($landlord)->post(route('contracts.store', $listing), rcPayload($over))->assertRedirect();

    return RentalContract::latest('id')->firstOrFail();
}

/** Requests a code (captured from the fake mailer) and signs. */
function rcSign($test, RentalContract $c, User $u, ?string $name = null)
{
    $c->refresh();
    rcConfirmed($test, $u)->post(route('contracts.code', $c))->assertRedirect();
    $code = null;
    Mail::assertSent(RentalContractCode::class, function ($m) use (&$code) { $code = $m->code; return true; });
    $sha = app(RentalContractService::class)->hash($c);

    return rcConfirmed($test, $u)->post(route('contracts.sign.store', $c), [
        'consent' => '1', 'typed_name' => $name ?? $c->d($c->roleOf($u).'.name'), 'code' => $code, 'text_sha256' => $sha,
    ]);
}

it('is hidden while the feature flag is off', function () {
    config(['reality.features.contracts' => false]);
    [$landlord, , $listing] = rcSetup();
    $this->actingAs($landlord)->get(route('contracts.create', $listing))->assertNotFound();
    $this->actingAs($landlord)->post(route('contracts.store', $listing), rcPayload())->assertNotFound();
});

it('prefills the form from listing and users', function () {
    [$landlord, , $listing] = rcSetup();
    $this->actingAs($landlord)->get(route('contracts.create', [$listing, 'najemce' => 'tenant@example.test']))
        ->assertOk()->assertSee('Jan Novák')->assertSee('Eva Svobodová')->assertSee('20000');
});

it('creates a draft and renders the Czech template with the lawyer flag', function () {
    [$landlord, , $listing] = rcSetup();
    $c = rcCreate($this, $landlord, $listing);
    expect($c->status)->toBe('draft')->and($c->deposit_halere)->toBe(4000000)->and($c->landlord_id)->toBe($landlord->id);
    $this->actingAs($landlord)->get(route('contracts.show', $c))->assertOk()
        ->assertSee('[PRÁVNÍK]')->assertSee('§ 2235')->assertSee('Smlouva o nájmu bytu');
    $this->actingAs($landlord)->get(route('contracts.print', $c))->assertOk()->assertSee('@page', false);
});

it('enforces the deposit limit of three monthly rents (NOZ § 2254)', function () {
    [$landlord, , $listing] = rcSetup();
    $this->actingAs($landlord)->post(route('contracts.store', $listing), rcPayload(['deposit' => 60001]))->assertSessionHasErrors('deposit');
    $this->actingAs($landlord)->post(route('contracts.store', $listing), rcPayload(['deposit' => 60000]))->assertSessionHasNoErrors();
});

it('only lets the listing owner start a contract', function () {
    [, $tenant, $listing] = rcSetup();
    $this->actingAs($tenant)->post(route('contracts.store', $listing), rcPayload())->assertForbidden();
});

it('runs the full flow draft → signed_landlord → sent → signed_both and closes the listing', function () {
    [$landlord, $tenant, $listing] = rcSetup();
    $future = ViewingSlot::create(['listing_id' => $listing->id, 'starts_at' => now()->addDays(3)]);
    $c = rcCreate($this, $landlord, $listing);

    // tenant cannot sign a draft
    rcConfirmed($this, $tenant)->get(route('contracts.sign', $c))->assertForbidden();

    rcSign($this, $c, $landlord)->assertSessionHasNoErrors()->assertRedirect(route('contracts.show', $c));
    expect($c->refresh()->status)->toBe('signed_landlord');

    $this->actingAs($landlord)->post(route('contracts.send', $c))->assertRedirect();
    expect($c->refresh()->status)->toBe('sent');
    Mail::assertSent(RentalContractInvitation::class, fn ($m) => $m->hasTo('tenant@example.test'));

    $this->actingAs($tenant)->get(route('contracts.show', $c))->assertOk();
    rcSign($this, $c, $tenant)->assertSessionHasNoErrors();
    $c->refresh();
    expect($c->status)->toBe('signed_both')->and($c->tenant_id)->toBe($tenant->id)
        ->and($listing->refresh()->status)->toBe('rented')
        ->and(Listing::public()->whereKey($listing->id)->exists())->toBeFalse()
        ->and(ViewingSlot::find($future->id))->toBeNull();
    Mail::assertSent(RentalContractCompleted::class, 2);
    Mail::assertSent(RentalContractCompleted::class, fn ($m) => $m->hasTo('tenant@example.test') && count($m->attachments()) === 2);

    $sig = $c->signatures()->where('role', 'tenant')->first();
    expect($sig->text_sha256)->toBe(app(RentalContractService::class)->hash($c))
        ->and($sig->typed_name)->toBe('Eva Svobodová')->and($sig->ip)->toBe('127.0.0.1')->and($sig->user_agent)->not->toBeNull();
});

it('invalidates signatures and returns to draft on any edit', function () {
    [$landlord, , $listing] = rcSetup();
    $c = rcCreate($this, $landlord, $listing);
    rcSign($this, $c, $landlord);
    $this->actingAs($landlord)->post(route('contracts.send', $c));
    expect($c->refresh()->status)->toBe('sent');

    $this->actingAs($landlord)->put(route('contracts.update', $c), rcPayload(['rent' => 21000]))->assertSessionHasNoErrors();
    $c->refresh();
    expect($c->status)->toBe('draft')->and($c->version)->toBe(2)->and($c->validSignatures()->count())->toBe(0)
        ->and($c->signatures()->whereNotNull('invalidated_at')->count())->toBe(1);
});

it('rejects a signature on a stale text version', function () {
    [$landlord, , $listing] = rcSetup();
    $c = rcCreate($this, $landlord, $listing);
    rcConfirmed($this, $landlord)->post(route('contracts.code', $c));
    $code = null;
    Mail::assertSent(RentalContractCode::class, function ($m) use (&$code) { $code = $m->code; return true; });
    rcConfirmed($this, $landlord)->post(route('contracts.sign.store', $c), ['consent' => '1', 'typed_name' => 'Jan Novák', 'code' => $code, 'text_sha256' => str_repeat('a', 64)])
        ->assertSessionHasErrors('text_sha256');
    expect($c->refresh()->status)->toBe('draft');
});

it('requires the correct code, consent, typed name and password confirmation', function () {
    [$landlord, , $listing] = rcSetup();
    $c = rcCreate($this, $landlord, $listing);
    $sha = app(RentalContractService::class)->hash($c);

    $this->actingAs($landlord)->post(route('contracts.code', $c))->assertRedirect(route('password.confirm'));

    rcConfirmed($this, $landlord)->post(route('contracts.sign.store', $c), ['consent' => '1', 'typed_name' => 'Jan Novák', 'code' => '123456', 'text_sha256' => $sha])
        ->assertSessionHasErrors('code'); // no code requested
    rcConfirmed($this, $landlord)->post(route('contracts.code', $c));
    $code = null;
    Mail::assertSent(RentalContractCode::class, function ($m) use (&$code) { $code = $m->code; return true; });
    expect($code)->toMatch('/^\d{6}$/');
    $wrong = $code === '000000' ? '111111' : '000000';
    rcConfirmed($this, $landlord)->post(route('contracts.sign.store', $c), ['consent' => '1', 'typed_name' => 'Jan Novák', 'code' => $wrong, 'text_sha256' => $sha])->assertSessionHasErrors('code');
    rcConfirmed($this, $landlord)->post(route('contracts.sign.store', $c), ['typed_name' => 'Jan Novák', 'code' => $code, 'text_sha256' => $sha])->assertSessionHasErrors('consent');
    rcConfirmed($this, $landlord)->post(route('contracts.sign.store', $c), ['consent' => '1', 'typed_name' => 'Někdo Jiný', 'code' => $code, 'text_sha256' => $sha])->assertSessionHasErrors('typed_name');
    rcConfirmed($this, $landlord)->post(route('contracts.sign.store', $c), ['consent' => '1', 'typed_name' => ' jan  novák ', 'code' => $code, 'text_sha256' => $sha])->assertSessionHasNoErrors();
    expect($c->refresh()->status)->toBe('signed_landlord');
});

it('locks the code after too many wrong attempts', function () {
    [$landlord, , $listing] = rcSetup();
    $c = rcCreate($this, $landlord, $listing);
    $sha = app(RentalContractService::class)->hash($c);
    rcConfirmed($this, $landlord)->post(route('contracts.code', $c));
    $code = null;
    Mail::assertSent(RentalContractCode::class, function ($m) use (&$code) { $code = $m->code; return true; });
    $wrong = $code === '000000' ? '111111' : '000000';
    foreach (range(1, 5) as $i) {
        rcConfirmed($this, $landlord)->post(route('contracts.sign.store', $c), ['consent' => '1', 'typed_name' => 'Jan Novák', 'code' => $wrong, 'text_sha256' => $sha]);
    }
    rcConfirmed($this, $landlord)->post(route('contracts.sign.store', $c), ['consent' => '1', 'typed_name' => 'Jan Novák', 'code' => $code, 'text_sha256' => $sha])->assertSessionHasErrors('code');
    expect($c->refresh()->status)->toBe('draft');
});

it('rate limits code sending', function () {
    [$landlord, , $listing] = rcSetup();
    $c = rcCreate($this, $landlord, $listing);
    foreach (range(1, 3) as $i) {
        rcConfirmed($this, $landlord)->post(route('contracts.code', $c))->assertRedirect();
    }
    rcConfirmed($this, $landlord)->post(route('contracts.code', $c))->assertStatus(429);
});

it('shows the contract only to the parties; admin sees the audit only', function () {
    [$landlord, $tenant, $listing] = rcSetup();
    $c = rcCreate($this, $landlord, $listing);
    $stranger = User::factory()->create(['role' => 'seeker']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($stranger)->get(route('contracts.show', $c))->assertForbidden();
    $this->actingAs($stranger)->get(route('contracts.audit', $c))->assertForbidden();
    $this->actingAs($stranger)->get(route('contracts.print', $c))->assertForbidden();
    $this->actingAs($admin)->get(route('contracts.show', $c))->assertForbidden();
    $this->actingAs($admin)->get(route('contracts.audit', $c))->assertOk()->assertSee($c->uuid);
    $this->actingAs($tenant)->get(route('contracts.show', $c))->assertOk();
    $this->actingAs($tenant)->get(route('contracts.edit', $c))->assertForbidden();
    $this->actingAs($tenant)->post(route('contracts.send', $c))->assertForbidden();
    $this->get(route('contracts.index'))->assertOk();
    auth()->logout();
    $this->get(route('contracts.show', $c))->assertRedirect(route('login'));
});

it('cancels and expires contracts', function () {
    [$landlord, $tenant, $listing] = rcSetup();
    $c = rcCreate($this, $landlord, $listing);
    $this->actingAs($tenant)->post(route('contracts.cancel', $c))->assertRedirect();
    expect($c->refresh()->status)->toBe('cancelled');
    $this->actingAs($landlord)->get(route('contracts.edit', $c))->assertForbidden();

    $c2 = rcCreate($this, $landlord, $listing);
    rcSign($this, $c2, $landlord);
    $this->actingAs($landlord)->post(route('contracts.send', $c2));
    $c2->forceFill(['sent_at' => now()->subDays(31)])->save();
    expect(app(RentalContractService::class)->expireStale())->toBe(1)->and($c2->refresh()->status)->toBe('expired');
    $this->actingAs($tenant)->get(route('contracts.sign', $c2))->assertForbidden();
});

it('shows bank details and an SPD QR string when payments are off', function () {
    [$landlord, $tenant, $listing] = rcSetup();
    $c = rcCreate($this, $landlord, $listing);
    rcSign($this, $c, $landlord);
    $this->actingAs($landlord)->post(route('contracts.send', $c));
    rcSign($this, $c, $tenant);

    $this->actingAs($tenant)->get(route('contracts.show', $c))->assertOk()
        ->assertSee('SPD*1.0*ACC:CZ6508000000192000145399*AM:63000.00*CC:CZK', false)->assertDontSee('Zaplatit online');
    rcConfirmed($this, $tenant)->post(route('contracts.pay', $c))->assertNotFound();
    expect(Payment::count())->toBe(0);
});

it('converts Czech account numbers to IBAN', function () {
    expect(SpdPayment::toIban('19-2000145399/0800'))->toBe('CZ6508000000192000145399')
        ->and(SpdPayment::toIban('CZ65 0800 0000 1920 0014 5399'))->toBe('CZ6508000000192000145399')
        ->and(SpdPayment::toIban('CZ66 0800 0000 1920 0014 5399'))->toBeNull()
        ->and(SpdPayment::toIban('nonsense'))->toBeNull();
});

it('creates a payment with commission via the provider when payments are on', function () {
    config(['reality.features.payments' => true, 'reality.features.commissions' => true]);
    CommissionRule::create(['service' => 'rental_contract', 'percent' => 1, 'fixed_halere' => 0]);
    [$landlord, $tenant, $listing] = rcSetup();
    $c = rcCreate($this, $landlord, $listing);
    rcSign($this, $c, $landlord);
    $this->actingAs($landlord)->post(route('contracts.send', $c));
    rcSign($this, $c, $tenant);

    rcConfirmed($this, $landlord)->post(route('contracts.pay', $c))->assertForbidden();
    rcConfirmed($this, $tenant)->post(route('contracts.pay', $c))->assertRedirect(route('contracts.show', $c));
    $p = Payment::sole();
    expect($p->amount_halere)->toBe(6300000)->and($p->commission_halere)->toBe(63000)
        ->and($p->provider)->toBe('stub')->and($p->status)->toBe('paid')->and($p->payable_id)->toBe($c->id);
    rcConfirmed($this, $tenant)->post(route('contracts.pay', $c));
    expect(Payment::count())->toBe(1);
});

it('charges zero commission by default', function () {
    config(['reality.features.payments' => true]);
    [$landlord, $tenant, $listing] = rcSetup();
    $c = rcCreate($this, $landlord, $listing);
    rcSign($this, $c, $landlord);
    $this->actingAs($landlord)->post(route('contracts.send', $c));
    rcSign($this, $c, $tenant);
    rcConfirmed($this, $tenant)->post(route('contracts.pay', $c));
    expect(Payment::sole()->commission_halere)->toBe(0);
});
