<?php

use App\Jobs\SendSavedSearchAlerts;
use App\Mail\SavedSearchAlert;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

function overpassPayload(): array
{
    return ['elements' => [
        ['type' => 'node', 'lat' => 50.0801, 'lon' => 14.4201, 'tags' => ['public_transport' => 'platform', 'name' => 'Muzeum']],
        ['type' => 'way', 'center' => ['lat' => 50.081, 'lon' => 14.421], 'tags' => ['amenity' => 'school', 'name' => 'ZŠ Vinohrady']],
        ['type' => 'node', 'lat' => 50.079, 'lon' => 14.419, 'tags' => ['shop' => 'bakery']],
        ['type' => 'relation', 'tags' => ['leisure' => 'park']], // no coordinates -> dropped
    ]];
}

it('shows agency directory, calculators and guides in navigation and footer', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();
    expect(substr_count($html, route('agencies.index')))->toBeGreaterThanOrEqual(2)
        ->and(substr_count($html, route('tools.calculators')))->toBeGreaterThanOrEqual(2)
        ->and(substr_count($html, route('guides.index')))->toBeGreaterThanOrEqual(2);
});

it('serves nearby amenities server-side and caches per rounded coordinate', function () {
    Http::fake(['overpass-api.de/*' => Http::response(overpassPayload())]);
    $this->getJson(route('amenities', ['lat' => 50.08012, 'lng' => 14.42033]))->assertOk()
        ->assertJsonCount(3, 'pois')->assertJsonPath('pois.0.cat', 'transit')->assertJsonPath('pois.1.cat', 'school')->assertJsonPath('pois.2.cat', 'shop');
    $this->getJson(route('amenities', ['lat' => 50.08049, 'lng' => 14.41961]))->assertOk()->assertJsonCount(3, 'pois');
    Http::assertSentCount(1);
    Http::assertSent(fn (HttpRequest $r) => str_contains($r['data'], 'around:1000,50.08,14.42'));
});

it('returns an empty amenity list when Overpass fails', function () {
    Http::fake(['overpass-api.de/*' => Http::response('busy', 504)]);
    $this->getJson(route('amenities', ['lat' => 49.2, 'lng' => 16.6]))->assertOk()->assertExactJson(['pois' => []]);
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));
    Cache::flush();
    $this->getJson(route('amenities', ['lat' => 49.2, 'lng' => 16.6]))->assertOk()->assertExactJson(['pois' => []]);
});

it('validates and rate-limits the amenities endpoint', function () {
    Http::fake(['*' => Http::response(['elements' => []])]);
    $this->getJson(route('amenities', ['lat' => 'x']))->assertUnprocessable();
    for ($i = 0; $i < 29; $i++) {
        $this->getJson(route('amenities', ['lat' => 50, 'lng' => 14]));
    }
    $this->getJson(route('amenities', ['lat' => 50, 'lng' => 14]))->assertStatus(429);
});

it('no longer calls Overpass from the browser bundle', function () {
    expect(file_get_contents(resource_path('js/map.js')))->not->toContain('overpass-api.de');
});

it('sends no saved-search digest while the alerts flag is off', function () {
    Mail::fake();
    config(['reality.features.alerts' => false]);
    $u = User::factory()->create();
    $u->savedSearches()->create(['name' => 'Byty', 'filters' => [], 'last_notified_at' => now()->subDay()]);
    Listing::factory()->create(['published_at' => now()]);
    expect((new SendSavedSearchAlerts)->handle())->toBe(0);
    Mail::assertNothingSent();
});

it('sends a daily digest of public matches with a signed unsubscribe link', function () {
    Mail::fake();
    config(['reality.features.alerts' => true]);
    $u = User::factory()->create();
    $s = $u->savedSearches()->create(['name' => 'Pronájmy', 'filters' => ['offer_type' => 'rent'], 'last_notified_at' => now()->subDay()]);
    Listing::factory()->count(2)->create(['offer_type' => 'rent', 'published_at' => now()]);
    Listing::factory()->imported()->create(['offer_type' => 'rent', 'published_at' => now()]);
    Listing::factory()->create(['offer_type' => 'rent', 'published_at' => now()->subDays(3)]);

    $this->artisan('reality:alerts:send')->assertSuccessful();
    $mail = null;
    Mail::assertSent(SavedSearchAlert::class, function ($m) use ($u, &$mail) { $mail = $m; return $m->hasTo($u->email) && $m->total === 2; });
    expect($mail->listings->pluck('source')->unique()->all())->not->toContain('imported')
        ->and($mail->render())->toContain('Odhlásit upozornění');

    $this->get(route('searches.unsubscribe', $s))->assertForbidden();
    $this->get($mail->unsubscribeUrl)->assertOk()->assertSee('Upozornění odhlášeno');
    expect($s->fresh()->notify)->toBeFalse();

    Listing::factory()->create(['offer_type' => 'rent', 'published_at' => now()->addMinute()]);
    $this->travel(2)->minutes();
    $this->artisan('reality:alerts:send')->assertSuccessful();
    Mail::assertSent(SavedSearchAlert::class, 1);
});

it('lets the owner toggle e-mail alerts of a saved search', function () {
    config(['reality.features.alerts' => true]);
    $u = User::factory()->create();
    $s = $u->savedSearches()->create(['name' => 'X', 'filters' => []]);
    $this->actingAs(User::factory()->create())->patch(route('account.searches.toggle', $s))->assertForbidden();
    $this->actingAs($u)->patch(route('account.searches.toggle', $s))->assertRedirect();
    expect($s->fresh()->notify)->toBeFalse();
});

it('records price changes and shows the drop badge and history behind the flag', function () {
    $l = Listing::factory()->create(['price_halere' => 2_000_000]);
    $l->update(['price_halere' => 1_800_000]);
    $l->update(['title' => 'Jen nový titulek']);
    expect($l->priceChanges()->count())->toBe(1)->and($l->fresh()->priceDropPercent())->toBe(10);

    $this->get(route('listings.show', $l))->assertOk()->assertDontSee('Cena snížena');
    config(['reality.features.price_history' => true]);
    $this->get(route('listings.show', $l))->assertOk()->assertSee('Cena snížena o 10 %')->assertSee('Historie ceny');

    $l->update(['price_halere' => 2_100_000]);
    expect($l->fresh()->priceDropPercent())->toBeNull();
});

it('schedules the monthly per-region agency import only behind its flag', function () {
    Http::fake(['*' => Http::response(['ekonomickeSubjekty' => [], 'pocetCelkem' => 0])]);
    config(['reality.agency_import_pause_s' => 0, 'reality.ares.delay_ms' => 0]);
    $this->artisan('reality:agencies:import-all')->expectsOutputToContain('vypnutý')->assertSuccessful();
    Http::assertNothingSent();

    config(['reality.features.agencies_auto_import' => true]);
    $this->artisan('reality:agencies:import-all')->assertSuccessful();
    Http::assertSentCount(count(config('reality.agency_import_regions')));
    Http::assertSent(fn (HttpRequest $r) => ($r['sidlo']['kodKraje'] ?? null) === 141);

    $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'reality:agencies:import-all'));
    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 3 10 * *');
});
