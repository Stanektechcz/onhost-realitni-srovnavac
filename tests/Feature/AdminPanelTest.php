<?php

use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;

function adminPanelConfirmed(): User
{
    $admin = User::factory()->admin()->create();
    test()->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    return $admin;
}

it('denies every new admin page to non-admins and guests', function (string $path) {
    $this->get($path)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get($path)->assertForbidden();
})->with(['/admin/uzivatele', '/admin/inzeraty', '/admin/platby', '/admin/predplatne', '/admin/tarify', '/admin/nastaveni', '/admin/audit']);

it('denies admin write actions to non-admins', function () {
    $victim = User::factory()->create();
    $this->actingAs(User::factory()->create())->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.users.role', $victim), ['role' => 'admin'])->assertForbidden();
    expect($victim->fresh()->role)->toBe('owner');
});

it('lists, searches and shows users', function () {
    adminPanelConfirmed();
    User::factory()->create(['name' => 'Jana Hledaná', 'email' => 'jana@example.test']);
    User::factory()->create(['name' => 'Petr Jiný', 'email' => 'petr@example.test']);
    $this->get('/admin/uzivatele?q=jana')->assertOk()->assertSee('jana@example.test')->assertDontSee('petr@example.test');
    $this->get(route('admin.users.show', User::where('email', 'jana@example.test')->first()))->assertOk()->assertSee('Jana Hledaná');
});

it('changes role only after password confirmation and writes audit', function () {
    $admin = User::factory()->admin()->create();
    $u = User::factory()->create();
    $this->actingAs($admin)->post(route('admin.users.role', $u), ['role' => 'agency'])->assertRedirect(route('password.confirm'));
    $this->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.users.role', $u), ['role' => 'agency'])->assertRedirect();
    expect($u->fresh()->role)->toBe('agency');
    $log = AuditLog::where('action', 'admin.user.role')->first();
    expect($log->data)->toBe(['from' => 'owner', 'to' => 'agency'])->and($log->user_id)->toBe($admin->id);

    $this->post(route('admin.users.role', $admin), ['role' => 'owner'])->assertSessionHasErrors('role');
    expect($admin->fresh()->role)->toBe('admin');
});

it('blocks and unblocks users; blocked users cannot log in and lose their session', function () {
    adminPanelConfirmed();
    $u = User::factory()->create(['email' => 'blok@example.test', 'password' => 'heslo12345']);
    $this->post(route('admin.users.block', $u), ['action' => 'block'])->assertSessionHasErrors('reason');
    $this->post(route('admin.users.block', $u), ['action' => 'block', 'reason' => 'Podvodné inzeráty'])->assertRedirect();
    expect($u->fresh()->isBlocked())->toBeTrue()
        ->and(AuditLog::where('action', 'admin.user.block')->where('subject_id', $u->id)->exists())->toBeTrue();

    auth()->logout();
    $this->flushSession();
    $this->post('/prihlaseni', ['email' => 'blok@example.test', 'password' => 'heslo12345'])->assertSessionHasErrors('email');
    $this->assertGuest();

    // existing session is terminated by middleware
    $this->actingAs($u->fresh())->get('/')->assertRedirect(route('login'));
    $this->assertGuest();

    adminPanelConfirmed();
    $this->post(route('admin.users.block', $u), ['action' => 'unblock'])->assertRedirect();
    expect($u->fresh()->isBlocked())->toBeFalse()->and(AuditLog::where('action', 'admin.user.unblock')->exists())->toBeTrue();
});

it('lists all listings with filters and marks imported as internal', function () {
    adminPanelConfirmed();
    Listing::factory()->status('active')->create(['title' => 'Veřejný byt Alfa', 'offer_type' => 'rent']);
    Listing::factory()->imported()->create(['title' => 'Importovaný byt Beta', 'offer_type' => 'sale']);
    $this->get('/admin/inzeraty')->assertOk()->assertSee('Veřejný byt Alfa')->assertSee('Importovaný byt Beta')->assertSee('interní');
    $this->get('/admin/inzeraty?source=imported')->assertOk()->assertSee('Importovaný byt Beta')->assertDontSee('Veřejný byt Alfa');
    $this->get('/admin/inzeraty?offer_type=rent')->assertOk()->assertSee('Veřejný byt Alfa')->assertDontSee('Importovaný byt Beta');
    $this->get('/admin/inzeraty?status=draft')->assertOk()->assertDontSee('Veřejný byt Alfa');
    $imp = Listing::imported()->first();
    $this->get(route('admin.listings.show', $imp->id))->assertOk()->assertSee('interní');
    // still never public
    $this->get(route('listings.show', $imp))->assertNotFound();
});

it('deactivates and extends listings with reason and audit', function () {
    adminPanelConfirmed();
    $l = Listing::factory()->status('active')->create(['published_at' => now(), 'expires_at' => now()->addDays(5)]);
    $this->post(route('admin.listings.extend', $l->id), ['days' => 10])->assertSessionHasErrors('reason');
    $this->post(route('admin.listings.extend', $l->id), ['days' => 10, 'reason' => 'Žádost inzerenta'])->assertRedirect();
    expect((int) round(now()->diffInDays($l->fresh()->expires_at)))->toBe(15)
        ->and(AuditLog::where('action', 'admin.listing.extend')->exists())->toBeTrue();

    $this->post(route('admin.listings.deactivate', $l->id), ['reason' => 'Porušení podmínek'])->assertRedirect();
    expect($l->fresh()->status)->toBe('expired')->and($l->fresh()->isPublic())->toBeFalse()
        ->and($l->fresh()->moderation_note)->toContain('Porušení podmínek')
        ->and(AuditLog::where('action', 'admin.listing.deactivate')->first()->data['reason'])->toBe('Porušení podmínek');
});

it('requires password confirmation for listing actions', function () {
    $l = Listing::factory()->status('active')->create();
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.listings.deactivate', $l->id), ['reason' => 'Porušení podmínek'])->assertRedirect(route('password.confirm'));
    expect($l->fresh()->status)->toBe('active');
});

it('shows payments and subscriptions read-only with filters and the feature banner', function () {
    config(['reality.features.payments' => false, 'reality.features.memberships' => false]);
    adminPanelConfirmed();
    $u = User::factory()->create(['email' => 'platic@example.test']);
    $plan = MembershipPlan::create(['code' => 'pro', 'name' => 'Profi', 'audience' => 'advertiser', 'price_halere' => 49900, 'period_days' => 30]);
    Payment::create(['user_id' => $u->id, 'service' => 'membership', 'provider' => 'stub', 'amount_halere' => 49900, 'status' => 'paid']);
    Payment::create(['user_id' => $u->id, 'service' => 'boost', 'provider' => 'stub', 'amount_halere' => 19900, 'status' => 'failed']);
    Subscription::create(['user_id' => $u->id, 'membership_plan_id' => $plan->id, 'status' => 'active']);

    $this->get('/admin/platby')->assertOk()->assertSee('Peněžní funkce jsou vypnuté')->assertSee('499 Kč')->assertSee('199 Kč');
    $this->get('/admin/platby?status=paid')->assertOk()->assertSee('499 Kč')->assertDontSee('199 Kč');
    $this->get('/admin/predplatne?status=active')->assertOk()->assertSee('Profi')->assertSee('platic@example.test');
    $this->get('/admin/predplatne?status=expired')->assertOk()->assertDontSee('platic@example.test');

    config(['reality.features.payments' => true, 'reality.features.memberships' => true]);
    $this->get('/admin/platby')->assertOk()->assertDontSee('Peněžní funkce jsou vypnuté');
});

it('manages membership plans with prices in halere and audit', function () {
    adminPanelConfirmed();
    $this->get('/admin/tarify/novy')->assertOk();
    $this->post('/admin/tarify', ['code' => 'zakladni', 'name' => 'Základní', 'audience' => 'advertiser', 'price_halere' => '9.5', 'period_days' => 30])
        ->assertSessionHasErrors('price_halere');
    $this->post('/admin/tarify', ['code' => 'zakladni', 'name' => 'Základní', 'audience' => 'advertiser', 'price_halere' => 9900, 'period_days' => 30, 'features' => "A\nB", 'is_active' => 1])
        ->assertRedirect(route('admin.plans.index'));
    $plan = MembershipPlan::where('code', 'zakladni')->firstOrFail();
    expect($plan->price_halere)->toBe(9900)->and($plan->features)->toBe(['A', 'B'])->and($plan->is_active)->toBeTrue();

    $this->put(route('admin.plans.update', $plan), ['code' => 'zakladni', 'name' => 'Základní+', 'audience' => 'seeker', 'price_halere' => 12900, 'period_days' => 60, 'is_active' => 0])->assertRedirect();
    expect($plan->fresh()->name)->toBe('Základní+')->and($plan->fresh()->is_active)->toBeFalse();
    $this->get('/admin/tarify')->assertOk()->assertSee('129 Kč');

    $this->delete(route('admin.plans.destroy', $plan))->assertRedirect();
    expect(MembershipPlan::count())->toBe(0);
    expect(AuditLog::whereIn('action', ['admin.plan.create', 'admin.plan.update', 'admin.plan.delete'])->count())->toBe(3);
});

it('keeps plans with subscriptions and only deactivates them', function () {
    adminPanelConfirmed();
    $plan = MembershipPlan::create(['code' => 'x', 'name' => 'X', 'audience' => 'seeker', 'price_halere' => 100, 'period_days' => 30]);
    Subscription::create(['user_id' => User::factory()->create()->id, 'membership_plan_id' => $plan->id]);
    $this->delete(route('admin.plans.destroy', $plan))->assertRedirect();
    expect($plan->fresh())->not->toBeNull()->and($plan->fresh()->is_active)->toBeFalse();
});

it('shows feature flags and never renders secrets on the settings page', function () {
    config([
        'reality.features.contracts' => true,
        'reality.payments.provider' => 'comgate',
        'reality.payments.comgate.merchant' => 'MERCHANT-123456',
        'reality.payments.comgate.secret' => 'comgate-secret-xyz',
        'oidc.enabled' => true,
        'oidc.client_secret' => 'oidc-client-secret-abc',
        'onhost.id.client_secret' => 'onhost-id-secret-def',
        'ecosystem.bus.secrets' => ['bus-secret-ghi'],
    ]);
    adminPanelConfirmed();
    $res = $this->get('/admin/nastaveni')->assertOk()
        ->assertSee('REALITY_FEATURE_CONTRACTS')->assertSee('ONHOST_CREDIT_ENABLED')->assertSee('ONHOST_OIDC_ENABLED')
        ->assertSee('ECOSYSTEM_BUS_ENABLED')->assertSee('Comgate')->assertSee('zapnuto')->assertSee('vypnuto');
    foreach (array_keys(config('reality.features')) as $flag) {
        $res->assertSee('reality.features.'.$flag);
    }
    foreach (['MERCHANT-123456', 'comgate-secret-xyz', 'oidc-client-secret-abc', 'onhost-id-secret-def', 'bus-secret-ghi'] as $secret) {
        $res->assertDontSee($secret);
    }
    $res->assertDontSee((string) config('app.key'));
});

it('shows a filterable paginated audit log', function () {
    $admin = adminPanelConfirmed();
    foreach (range(1, 55) as $i) {
        AuditLog::create(['user_id' => $admin->id, 'action' => 'test.bulk', 'data' => ['i' => $i]]);
    }
    AuditLog::create(['user_id' => null, 'action' => 'special.event']);
    $this->get('/admin/audit')->assertOk()->assertSee('special.event')->assertSee('?page=2', false);
    $this->get('/admin/audit?action=special.event')->assertOk()->assertSee('special.event')->assertDontSee('test.bulk</td>', false);
    $this->get('/admin/audit?user='.urlencode($admin->email))->assertOk()->assertDontSee('special.event</td>', false);
});
