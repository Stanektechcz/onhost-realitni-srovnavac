<?php

use App\Models\Listing;

it('renders public pages', function (string $url) {
    Listing::factory()->count(3)->create();
    $this->get($url)->assertOk();
})->with(['/', '/hledat', '/prihlaseni', '/registrace', '/info/obchodni-podminky', '/info/cookies', '/sitemap.xml', '/robots.txt', '/api/mapa']);

it('shows legal placeholders as drafts', function () {
    $this->get('/info/ochrana-osobnich-udaju')->assertSee('NÁVRH – čeká na advokáta');
});

it('hides pricing unless the feature flag is on', function () {
    $this->get('/clenstvi')->assertNotFound();
    config(['reality.features.memberships' => true]);
    $this->seed();
    $this->get('/clenstvi')->assertOk()->assertSee('Profi');
});

it('renders a detail page with schema.org markup and PENB warning', function () {
    $l = Listing::factory()->create(['penb' => 'none']);
    $this->get(route('listings.show', $l))->assertOk()
        ->assertSee('"@type":"RealEstateListing"', false)
        ->assertSee('PENB) nebyl vydán', false);
});

it('lists public listings in the sitemap only', function () {
    $pub = Listing::factory()->create();
    $draft = Listing::factory()->status('draft')->create();
    $imp = Listing::factory()->imported()->create();
    $this->get('/sitemap.xml')->assertSee($pub->slug)->assertDontSee($draft->slug)->assertDontSee($imp->slug);
});

it('seeds about forty fictional Prague rentals', function () {
    $this->seed();
    expect(Listing::where('city', 'Praha')->where('offer_type', 'rent')->count())->toBe(40)
        ->and(Listing::public()->count())->toBeGreaterThan(30);
});
