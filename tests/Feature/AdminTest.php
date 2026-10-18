<?php

use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\User;

it('restricts admin to admins', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
});

it('approves and rejects listings with password confirmation and audit trail', function () {
    $admin = User::factory()->admin()->create();
    $a = Listing::factory()->status('pending')->create(['published_at' => null]);
    $b = Listing::factory()->status('pending')->create(['published_at' => null]);
    $this->actingAs($admin)->get('/admin/moderace')->assertOk()->assertSee($a->displayTitle());
    $this->post(route('admin.moderation.decide', $a), ['decision' => 'approve'])->assertRedirect(route('password.confirm'));

    $this->withSession(['auth.password_confirmed_at' => time()]);
    $this->post(route('admin.moderation.decide', $a), ['decision' => 'approve']);
    $this->post(route('admin.moderation.decide', $b), ['decision' => 'reject'])->assertSessionHasErrors('reason');
    $this->post(route('admin.moderation.decide', $b), ['decision' => 'reject', 'reason' => 'Chybí fotografie.']);

    expect($a->fresh()->isPublic())->toBeTrue()->and($a->fresh()->expires_at)->not->toBeNull()
        ->and($b->fresh()->status)->toBe('rejected')->and($b->fresh()->moderation_note)->toBe('Chybí fotografie.');
    expect(AuditLog::where('action', 'listing.approve')->exists())->toBeTrue();
});

it('imports scraper jsonl as internal-only data and shows stats', function () {
    $file = tempnam(sys_get_temp_dir(), 'imp');
    file_put_contents($file, implode("\n", [
        json_encode(['source' => 'scr', 'source_id' => '1', 'url' => 'https://example.test/1', 'offer_type' => 'rent', 'disposition' => '2+kk', 'area_m2' => 50, 'price_czk' => 25000, 'fees_czk' => 3000, 'city' => 'Praha', 'district' => 'Testovice', 'lat' => 50.09, 'lng' => 14.45, 'penb' => 'c', 'published_at' => '2026-10-01']),
        json_encode(['source' => 'scr', 'source_id' => '2', 'offer_type' => 'rent', 'disposition' => '2+kk', 'area_m2' => 55, 'price_czk' => 27000, 'city' => 'Praha', 'district' => 'Testovice']),
        '{broken json',
        json_encode(['source' => 'scr', 'source_id' => '1', 'offer_type' => 'rent', 'disposition' => '2+kk', 'area_m2' => 50, 'price_czk' => 26000, 'city' => 'Praha', 'district' => 'Testovice']),
    ]));
    $this->artisan('reality:import', ['file' => $file])->expectsOutputToContain('nových 2, aktualizovaných 1, přeskočených 1')->assertSuccessful();
    unlink($file);

    expect(Listing::imported()->count())->toBe(2)->and(Listing::public()->count())->toBe(0)
        ->and(Listing::where('source_id', '1')->value('price_halere'))->toBe(2600000);
    $this->get('/hledat')->assertDontSee('Testovice');
    $imp = Listing::imported()->first();
    $this->actingAs(User::factory()->admin()->create())->get(route('listings.show', $imp))->assertNotFound();
    $this->get('/admin/statistiky')->assertOk()->assertSee('Testovice')->assertSee('26 500 Kč');
});
