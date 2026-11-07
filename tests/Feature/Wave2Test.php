<?php

use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Listing;
use App\Models\User;

function wave2Admin(): User
{
    $admin = User::factory()->admin()->create();
    test()->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    return $admin;
}

function wave2Message(array $attrs = []): ContactMessage
{
    return ContactMessage::create($attrs + ['name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'topic' => 'obecne', 'body' => 'Dobrý den, mám dotaz <script>alert(1)</script>.']);
}

// --- 1. Kontaktní zprávy v adminu ---

it('denies contact messages admin to guests and non-admins', function () {
    $m = wave2Message();
    $this->get('/admin/kontakt')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/admin/kontakt')->assertForbidden();
    $this->get(route('admin.contact.show', $m))->assertForbidden();
    $this->post(route('admin.contact.status', $m), ['action' => 'handled'])->assertForbidden();
    expect($m->fresh()->handled_at)->toBeNull();
});

it('lists, filters and shows contact messages with escaped body', function () {
    $open = wave2Message();
    $done = wave2Message(['email' => 'petr@example.cz', 'topic' => 'rk']);
    $done->forceFill(['handled_at' => now()])->save();
    wave2Admin();

    $this->get('/admin/kontakt')->assertOk()->assertSee('jana@example.cz')->assertSee('petr@example.cz')->assertSee('Kontaktní zprávy');
    $this->get('/admin/kontakt?stav=nove')->assertSee('jana@example.cz')->assertDontSee('petr@example.cz');
    $this->get('/admin/kontakt?tema=rk')->assertSee('petr@example.cz')->assertDontSee('jana@example.cz');
    $this->get(route('admin.contact.show', $open))->assertOk()->assertSee('Označit jako vyřízené')
        ->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
});

it('marks a contact message handled and reopens it with audit log', function () {
    $m = wave2Message();
    wave2Admin();
    $this->post(route('admin.contact.status', $m), ['action' => 'handled'])->assertRedirect();
    expect($m->fresh()->handled_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'admin.contact.handled')->where('subject_id', $m->id)->exists())->toBeTrue();
    $this->post(route('admin.contact.status', $m), ['action' => 'open']);
    expect($m->fresh()->handled_at)->toBeNull()
        ->and(AuditLog::where('action', 'admin.contact.reopened')->count())->toBe(1);
    $this->post(route('admin.contact.status', $m), ['action' => 'smazat'])->assertSessionHasErrors('action');
});

it('does not allow handled_at mass assignment from the contact form', function () {
    expect((new ContactMessage)->isFillable('handled_at'))->toBeFalse();
});

// --- 3. OnPass branding ---

it('uses the configurable brand name on login when OIDC is enabled', function () {
    config(['oidc.enabled' => true, 'oidc.client_id' => 'reality', 'oidc.client_secret' => 's', 'oidc.issuer' => 'https://id.onhost.test']);
    $this->get('/prihlaseni')->assertOk()->assertSee('Přihlásit přes OnPass')->assertDontSee('ONhost ID');
    config(['oidc.brand_name' => 'TestPass']);
    $this->get('/prihlaseni')->assertSee('Přihlásit přes TestPass');
});

it('shows no brand login button when OIDC is disabled', function () {
    config(['oidc.enabled' => false]);
    $this->get('/prihlaseni')->assertOk()->assertDontSee('OnPass')->assertDontSee('ONhost ID');
});

it('defaults brand name to OnPass', function () {
    expect(config('oidc.brand_name'))->toBe('OnPass');
});

// --- 4. Sjednocení blokace ---

it('treats legacy is_banned and blocked_at as blocked', function () {
    expect(User::factory()->make(['is_banned' => true])->isBlocked())->toBeTrue()
        ->and(User::factory()->make(['blocked_at' => now()])->isBlocked())->toBeTrue()
        ->and(User::factory()->make()->isBlocked())->toBeFalse();
});

it('logs out a legacy banned user with an existing session', function () {
    $u = User::factory()->create(['is_banned' => true]);
    $this->actingAs($u)->get('/moje')->assertRedirect();
    $this->assertGuest();
});

it('refuses login for users blocked via blocked_at', function () {
    $u = User::factory()->create(['blocked_at' => now()]);
    $this->post('/prihlaseni', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('denies listing creation to blocked users of either kind', function () {
    foreach ([['is_banned' => true], ['blocked_at' => now()]] as $attrs) {
        $u = User::factory()->create($attrs);
        expect($u->can('create', Listing::class))->toBeFalse();
    }
});

it('admin block sets blocked_at and unblock clears legacy flag too', function () {
    wave2Admin();
    $u = User::factory()->create(['is_banned' => true]);
    $this->get('/admin/uzivatele/'.$u->id)->assertOk()->assertSee('starší blokace');
    $this->get('/admin/uzivatele?stav=blokovany')->assertSee($u->email);
    $this->post(route('admin.users.block', $u), ['action' => 'unblock']);
    expect($u->fresh()->isBlocked())->toBeFalse()->and($u->fresh()->is_banned)->toBeFalse();
    $this->post(route('admin.users.block', $u), ['action' => 'block', 'reason' => 'Spam']);
    expect($u->fresh()->blocked_at)->not->toBeNull()->and($u->fresh()->is_banned)->toBeFalse();
});
