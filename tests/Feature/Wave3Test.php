<?php

use App\Mail\ListingExpiringSoon;
use App\Models\ContactMessage;
use App\Models\Listing;
use App\Models\User;
use App\Support\LegalEntity;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;

function wave3Admin(): User
{
    $admin = User::factory()->admin()->create();
    test()->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    return $admin;
}

function wave3Company(): void
{
    config(['operator.operators.reality-sro' => ['id' => 'sro-reality-12345678', 'name' => 'ONhost Reality s.r.o.', 'legal_form' => 'sro',
        'ico' => '12345678', 'dic' => 'CZ12345678', 'vat_mode' => 'payer', 'vat_payer' => true, 'registry' => 'C 999 vedená u KS v Brně'],
        'operator.operator' => 'reality-sro']);
}

// --- 1. Provozovatel z konfigurace ---

it('renders default operator as OSVČ non-VAT payer', function () {
    expect(LegalEntity::displayLine())->toBe('Adrian Staněk, OSVČ, IČO 08094616, neplátce DPH')
        ->and(LegalEntity::priceNote())->toBe('nejsme plátci DPH');
    $this->get('/')->assertOk()->assertSee('Adrian Staněk, OSVČ, IČO 08094616, neplátce DPH');
    $this->get(route('pages.contact'))->assertOk()->assertSee('08094616')->assertSee('neplátce DPH');
    $this->get(route('pages.about'))->assertOk()->assertSee('fyzická osoba podnikající');
});

it('switches all texts to a VAT-paying company', function () {
    wave3Company();
    expect(LegalEntity::priceNote())->toBe('vč. DPH');
    foreach (['/', route('pages.contact'), route('pages.about')] as $url) {
        $this->get($url)->assertOk()->assertSee('ONhost Reality s.r.o.')->assertSee('12345678')->assertDontSee('08094616')->assertDontSee('Adrian Staněk');
    }
    $this->get(route('pages.contact'))->assertSee('C 999 vedená u KS v Brně')->assertSee('DIČ CZ12345678');
    $this->get(route('pages.about'))->assertSee('právnická osoba');
    config(['reality.features.memberships' => true]);
    $this->get(route('pricing'))->assertOk()->assertSee('vč. DPH')->assertDontSee('nejsme plátci DPH');
    wave3Admin();
    $this->get(route('admin.settings'))->assertOk()->assertSee('ONhost Reality s.r.o., IČO 12345678, plátce DPH');
});

it('has no hard-coded operator IČO outside config', function () {
    $hits = [];
    foreach (['app', 'resources'] as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && str_contains((string) file_get_contents($f->getPathname()), '08094616')) {
                $hits[] = $f->getPathname();
            }
        }
    }
    expect($hits)->toBe([]);
});

// --- 2. Automatická údržba ---

it('maintenance is a no-op when automation flag is off', function () {
    Mail::fake();
    $l = Listing::factory()->create(['status' => 'active', 'expires_at' => now()->subDay()]);
    $this->artisan('reality:maintenance')->expectsOutputToContain('vypnutá')->assertSuccessful();
    expect($l->fresh()->status)->toBe('active');
    Mail::assertNothingQueued();
});

it('maintenance expires, reminds once and prunes old handled messages', function () {
    config(['reality.automation.enabled' => true]);
    Mail::fake();
    $owner = User::factory()->create();
    $old = Listing::factory()->create(['status' => 'active', 'expires_at' => now()->subHour()]);
    $soon = Listing::factory()->create(['status' => 'active', 'user_id' => $owner->id, 'source' => 'internal', 'expires_at' => now()->addDays(2)]);
    $later = Listing::factory()->create(['status' => 'active', 'user_id' => $owner->id, 'source' => 'internal', 'expires_at' => now()->addDays(20)]);
    $oldMsg = ContactMessage::create(['name' => 'A', 'email' => 'a@example.cz', 'topic' => 'obecne', 'body' => 'x']);
    $oldMsg->forceFill(['handled_at' => now()->subDays(200)])->save();
    $newMsg = ContactMessage::create(['name' => 'B', 'email' => 'b@example.cz', 'topic' => 'obecne', 'body' => 'y']);
    $newMsg->forceFill(['handled_at' => now()->subDays(10)])->save();
    $open = ContactMessage::create(['name' => 'C', 'email' => 'c@example.cz', 'topic' => 'obecne', 'body' => 'z']);

    $this->artisan('reality:maintenance')->expectsOutputToContain('Vypršelo: 1, připomínek: 1, smazaných zpráv: 1')->assertSuccessful();
    expect($old->fresh()->status)->toBe('expired')
        ->and($soon->fresh()->expiry_reminded_at)->not->toBeNull()
        ->and($later->fresh()->expiry_reminded_at)->toBeNull()
        ->and(ContactMessage::find($oldMsg->id))->toBeNull()
        ->and(ContactMessage::find($newMsg->id))->not->toBeNull()
        ->and(ContactMessage::find($open->id))->not->toBeNull();
    Mail::assertQueued(ListingExpiringSoon::class, fn ($m) => $m->hasTo($owner->email) && $m->listing->is($soon));

    // idempotence + time travel: second run the same day sends nothing new
    $this->artisan('reality:maintenance')->expectsOutputToContain('připomínek: 0')->assertSuccessful();
    Mail::assertQueuedCount(1);

    $this->travel(18)->days();
    $this->artisan('reality:maintenance')->expectsOutputToContain('připomínek: 1')->assertSuccessful();
    $this->travel(3)->days();
    $this->artisan('reality:maintenance')->assertSuccessful();
    expect($soon->fresh()->status)->toBe('expired')->and($later->fresh()->status)->toBe('expired');
});

it('schedules daily maintenance', function () {
    $events = collect(app(Schedule::class)->events());
    expect($events->contains(fn ($e) => str_contains((string) $e->command, 'reality:maintenance')))->toBeTrue();
});

// --- 3. KPI na nástěnce ---

it('shows KPI tiles on admin dashboard', function () {
    Listing::factory()->create(['status' => 'pending', 'source' => 'internal']);
    ContactMessage::create(['name' => 'A', 'email' => 'a@example.cz', 'topic' => 'obecne', 'body' => 'x']);
    wave3Admin();
    $this->get('/admin')->assertOk()->assertSee('Nové inzeráty (7 dní)')->assertSee('Nové inzeráty (30 dní)')
        ->assertSee('Nové kontaktní zprávy')->assertSee('Aktivní uživatelé (30 dní)')->assertSee('Ke schválení');
});
