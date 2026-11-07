<?php

use App\Mail\ListingExpiringSoon;
use App\Mail\WeeklyKpiReport;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use App\Services\AdminKpi;
use App\Services\Heartbeat;
use App\Services\Maintenance;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function w4RemindedListing(): Listing
{
    $owner = User::factory()->create();

    return Listing::factory()->create(['status' => 'active', 'user_id' => $owner->id, 'source' => 'internal', 'expires_at' => now()->addDays(2)]);
}

it('includes a signed renew link in the reminder only when enabled', function () {
    Mail::fake();
    config(['reality.automation.enabled' => true, 'reality.automation.renew_link' => true]);
    $l = w4RemindedListing();
    $this->artisan('reality:maintenance')->assertSuccessful();
    Mail::assertQueued(ListingExpiringSoon::class, fn ($m) => $m->listing->is($l) && str_contains((string) $m->renewUrl, 'signature='));

    config(['reality.automation.renew_link' => false]);
    expect(Maintenance::renewUrl($l->fresh()))->toBeNull();
});

it('renews via signed link only on POST and only once per reminder', function () {
    config(['reality.automation.renew_link' => true]);
    $l = w4RemindedListing();
    $l->forceFill(['expiry_reminded_at' => now()])->save();
    $url = Maintenance::renewUrl($l->fresh());

    $this->get($url)->assertOk()->assertSee('Potvrdit prodloužení');
    expect($l->fresh()->renewed_at)->toBeNull(); // GET nic nemění

    $this->post($url)->assertRedirect(route('listings.show', $l));
    $fresh = $l->fresh();
    expect($fresh->renewed_at)->not->toBeNull()->and($fresh->expiry_reminded_at)->toBeNull()
        ->and($fresh->expires_at->gt(now()->addDays(50)))->toBeTrue()
        ->and(AuditLog::where('action', 'listing.renewed')->where('subject_id', $l->id)->exists())->toBeTrue();

    $renewedAt = $fresh->renewed_at;
    $this->travel(1)->minutes();
    $this->post($url)->assertRedirect();
    expect($l->fresh()->renewed_at->eq($renewedAt))->toBeTrue();
    $this->get($url)->assertOk()->assertSee('už byl použit');
});

it('rejects tampered or disabled renew links', function () {
    config(['reality.automation.renew_link' => true]);
    $l = w4RemindedListing();
    $l->forceFill(['expiry_reminded_at' => now()])->save();
    $url = Maintenance::renewUrl($l->fresh());
    $this->post(str_replace('r=', 'r=1', $url))->assertForbidden();
    config(['reality.automation.renew_link' => false]);
    $this->get($url)->assertNotFound();
});

it('anonymizes old expired listings and deletes old messages when retention is enabled', function () {
    Storage::fake('public');
    config(['reality.automation.enabled' => true, 'reality.retention.enabled' => true]);
    $owner = User::factory()->create();
    $old = Listing::factory()->create(['status' => 'expired', 'source' => 'internal', 'user_id' => $owner->id, 'expires_at' => now()->subYears(3),
        'description' => 'Tajný popis', 'street' => 'Ulice 1', 'price_halere' => 1500000, 'city' => 'Brno']);
    $recent = Listing::factory()->create(['status' => 'expired', 'source' => 'internal', 'user_id' => $owner->id, 'expires_at' => now()->subYear(), 'description' => 'Ponechat']);
    Storage::disk('public')->put('listings/a.jpg', 'x');
    $old->photos()->create(['path' => 'listings/a.jpg', 'thumb_path' => 'listings/a.jpg', 'position' => 0]);
    $msg = fn ($l, $at) => tap(Message::create(['listing_id' => $l->id, 'recipient_id' => $owner->id, 'sender_name' => 'A', 'sender_email' => 'a@example.cz',
        'body' => 'Dobrý den', 'relay_token' => Str::random(40)]), fn ($m) => $m->forceFill(['created_at' => $at])->save());
    $msg($recent, now()->subYears(3));
    $keep = $msg($recent, now()->subMonth());

    $this->artisan('reality:maintenance')->expectsOutputToContain('anonymizováno: 1, smazaných dotazů: 1')->assertSuccessful();
    $o = $old->fresh();
    expect($o->anonymized_at)->not->toBeNull()->and($o->description)->toBeNull()->and($o->street)->toBeNull()->and($o->user_id)->toBeNull()
        ->and($o->price_halere)->toBe(1500000)->and($o->city)->toBe('Brno')->and($o->photos()->count())->toBe(0)
        ->and($recent->fresh()->description)->toBe('Ponechat')
        ->and(Message::pluck('id')->all())->toBe([$keep->id]);
    Storage::disk('public')->assertMissing('listings/a.jpg');
});

it('keeps data when retention is disabled', function () {
    config(['reality.automation.enabled' => true, 'reality.retention.enabled' => false]);
    $old = Listing::factory()->create(['status' => 'expired', 'source' => 'internal', 'expires_at' => now()->subYears(3), 'description' => 'X']);
    $this->artisan('reality:maintenance')->assertSuccessful();
    expect($old->fresh()->description)->toBe('X');
});

it('tracks last login and counts active users', function () {
    $u = User::factory()->create(['password' => 'tajne-heslo-123']);
    User::factory()->create()->forceFill(['last_login_at' => now()->subDays(40)])->save();
    $this->post('/prihlaseni', ['email' => $u->email, 'password' => 'tajne-heslo-123'])->assertRedirect();
    expect($u->fresh()->last_login_at)->not->toBeNull()
        ->and(app(AdminKpi::class)->activeUsers())->toBe(1);
});

it('sends weekly KPI report to admins only when enabled', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $this->artisan('reality:report:weekly')->expectsOutputToContain('vypnutý')->assertSuccessful();
    Mail::assertNothingSent();
    config(['reality.weekly_report.enabled' => true]);
    $this->artisan('reality:report:weekly')->assertSuccessful();
    Mail::assertSent(WeeklyKpiReport::class, fn ($m) => $m->hasTo($admin->email) && isset($m->kpi['Aktivní uživatelé (30 dní)']));
    expect((new WeeklyKpiReport(['A' => 1], true))->render())->toContain('neproběhla');
});

it('schedules the weekly report on Monday 07:00', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains((string) $e->command, 'reality:report:weekly'));
    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 7 * * 1');
});

it('writes a heartbeat and warns admins when it is stale', function () {
    config(['reality.automation.enabled' => true]);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Plánovač neběží');
    $this->artisan('reality:maintenance')->assertSuccessful();
    expect(app(Heartbeat::class)->stale())->toBeFalse();
    $this->actingAs($admin)->get('/admin')->assertDontSee('Plánovač neběží');
    $this->travel(49)->hours();
    expect(app(Heartbeat::class)->stale())->toBeTrue();
    Cache::forget(Heartbeat::KEY);
});

it('sends security headers with a CSP that allows the self-hosted map', function () {
    config(['reality.security_headers' => true]);
    $r = $this->get(route('home'))->assertOk();
    $r->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')->assertHeaderMissing('Strict-Transport-Security');
    expect($r->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'")->toContain('https://tiles.openfreemap.org')->toContain("object-src 'none'")
        ->and($r->headers->get('Permissions-Policy'))->toContain('camera=()');
});

it('sends HSTS only in production over https', function () {
    config(['reality.security_headers' => true]);
    app()->detectEnvironment(fn () => 'production');
    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
    $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
});

it('rate limits login per email and IP', function () {
    foreach (range(1, 6) as $i) {
        $this->post('/prihlaseni', ['email' => 'x@example.cz', 'password' => 'spatne']);
    }
    $this->post('/prihlaseni', ['email' => 'x@example.cz', 'password' => 'spatne'])->assertStatus(429);
    $this->post('/prihlaseni', ['email' => 'jiny@example.cz', 'password' => 'spatne'])->assertStatus(302);
});
