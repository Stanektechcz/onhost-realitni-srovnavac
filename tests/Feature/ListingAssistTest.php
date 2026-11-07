<?php

use App\Models\Listing;
use App\Models\MarketBenchmark;
use App\Models\User;
use App\Services\Geo\RuianGeocoder;
use App\Support\DescriptionGenerator;
use Illuminate\Support\Facades\Http;

function assistOwner(): User { return User::factory()->create(['role' => 'owner']); }

function fakeRuian(): void
{
    Http::preventStrayRequests();
    Http::fake([
        RuianGeocoder::BASE.'/suggest*' => Http::response(['suggestions' => [
            ['text' => 'Sokolovská 146/150a, Libeň, 18000 Praha 8', 'magicKey' => '1_1180493', 'isCollection' => false, 'type' => 'AdresniMisto'],
            ['text' => 'Parcela 123', 'magicKey' => '0_1', 'isCollection' => false, 'type' => 'ParcelaDefinicniBod'],
        ]]),
        RuianGeocoder::BASE.'/findAddressCandidates*' => Http::response(['spatialReference' => ['wkid' => 4326], 'candidates' => [
            ['address' => 'Sokolovská 146/150a, Libeň, 18000 Praha 8', 'location' => ['x' => 14.4721, 'y' => 50.1012], 'score' => 100],
        ]]),
    ]);
}

it('suggests addresses from RÚIAN and resolves them to listing fields (contract)', function () {
    fakeRuian();
    $this->actingAs(assistOwner())->getJson(route('assist.address', ['q' => 'Sokolovská 150']))
        ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.key', '1_1180493')->assertJsonPath('attribution', RuianGeocoder::ATTRIBUTION);
    $this->getJson(route('assist.address.resolve', ['label' => 'Sokolovská 146/150a, Libeň, 18000 Praha 8', 'key' => '1_1180493']))
        ->assertOk()->assertJson(['street' => 'Sokolovská 146/150a', 'district' => 'Libeň', 'city' => 'Praha', 'lat' => 50.1012, 'lng' => 14.4721]);

    Http::assertSent(fn ($r) => str_contains($r->url(), '/suggest') && $r['text'] === 'Sokolovská 150' && $r['f'] === 'json');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/findAddressCandidates') && $r['magicKey'] === '1_1180493' && (string) $r['outSR'] === '4326');
});

it('caches geocoder results', function () {
    fakeRuian();
    $u = assistOwner();
    $this->actingAs($u)->getJson(route('assist.address', ['q' => 'Sokolovská 150']))->assertOk();
    $this->getJson(route('assist.address', ['q' => 'sokolovská  150']))->assertOk();
    Http::assertSentCount(1);
});

it('degrades gracefully when ČÚZK is down', function () {
    Http::fake([RuianGeocoder::BASE.'/*' => Http::response('err', 500)]);
    $this->actingAs(assistOwner())->getJson(route('assist.address', ['q' => 'Sokolovská']))->assertOk()->assertJsonCount(0, 'items');
    $this->getJson(route('assist.address.resolve', ['label' => 'X', 'key' => '1_2']))->assertNotFound();
    $this->getJson(route('assist.address.resolve', ['label' => 'X', 'key' => '../etc']))->assertNotFound();
});

it('rate limits the address endpoint and requires an owner', function () {
    fakeRuian();
    $this->getJson(route('assist.address', ['q' => 'Sokolovská']))->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'seeker']))->getJson(route('assist.address', ['q' => 'Sokolovská']))->assertForbidden();
    $this->actingAs(assistOwner());
    for ($i = 0; $i < 40; $i++) {
        $this->getJson(route('assist.address', ['q' => 'Sokolovská']))->assertOk();
    }
    $this->getJson(route('assist.address', ['q' => 'Sokolovská']))->assertStatus(429);
});

it('parses RÚIAN address formats', function () {
    expect(RuianGeocoder::parse('Sokolovská 150, 25087 Mochov'))->toBe(['street' => 'Sokolovská 150', 'district' => null, 'city' => 'Mochov'])
        ->and(RuianGeocoder::parse('Vinohradská 12, 12000 Praha 2'))->toBe(['street' => 'Vinohradská 12', 'district' => 'Praha 2', 'city' => 'Praha'])
        ->and(RuianGeocoder::parse('Sokolovská, Bohumín'))->toBe(['street' => 'Sokolovská', 'district' => null, 'city' => 'Bohumín']);
});

it('suggests a price range labelled as an estimate with its source', function () {
    foreach ([18000, 20000, 22000, 24000] as $p) {
        Listing::factory()->create(['offer_type' => 'rent', 'property_type' => 'flat', 'disposition' => '2+kk', 'city' => 'Praha', 'district' => 'Karlín',
            'area_m2' => 50, 'price_halere' => $p * 100, 'status' => 'active', 'published_at' => now()->subMonth(), 'source' => 'internal']);
    }
    $r = $this->actingAs(assistOwner())->getJson(route('assist.price', ['offer_type' => 'rent', 'city' => 'Praha', 'district' => 'Karlín', 'disposition' => '2+kk', 'area_m2' => 60]))
        ->assertOk()->assertJsonPath('estimate.enough', true)->assertJsonPath('label', 'Orientační odhad');
    expect($r->json('estimate.mid'))->toBe(25200)->and($r->json('source'))->toContain('4 srovnatelných')->toContain('Karlín, 2+kk');

    $this->getJson(route('assist.price', ['offer_type' => 'rent', 'city' => 'Aš', 'area_m2' => 60]))->assertOk()->assertJsonPath('estimate.enough', false);
});

it('adds the official ČSÚ benchmark for sales', function () {
    MarketBenchmark::create(['source' => 'CSU:CEN0402T02', 'source_url' => 'https://data.csu.gov.cz/x', 'license' => 'ČSÚ', 'area' => 'Hlavní město Praha',
        'period' => '2025', 'indicator' => 'Průměrná kupní cena – byty', 'value' => 130000, 'unit' => 'Kč/m²']);
    $this->actingAs(assistOwner())->getJson(route('assist.price', ['offer_type' => 'sale', 'property_type' => 'flat', 'city' => 'Praha', 'area_m2' => 50]))
        ->assertOk()->assertJsonPath('benchmark.url', 'https://data.csu.gov.cz/x')
        ->assertJsonPath('benchmark.text', 'Průměrná kupní cena – byty, Hlavní město Praha (2025): 130 000 Kč/m² (≈ 6 500 000 Kč pro vaši plochu)');
});

it('drafts a Czech description from the entered fields', function () {
    $text = DescriptionGenerator::make(['offer_type' => 'rent', 'property_type' => 'flat', 'disposition' => '2+kk', 'area_m2' => 54.0, 'street' => 'Sokolovská',
        'district' => 'Karlín', 'city' => 'Praha', 'floor' => 3, 'has_elevator' => true, 'has_balcony' => '1', 'has_parking' => '0', 'penb' => 'B',
        'furnishing' => 'partial', 'available_from' => now()->addMonth()->toDateString(), 'locality' => 'tramvaj 2 minuty pěšky', 'pets_allowed' => '0']);
    expect($text)->toStartWith('Pronájem bytu 2+kk o ploše 54 m², Sokolovská, Karlín, Praha.')
        ->toContain('Nachází se v 3. patře, v domě je výtah.')->toContain('K dispozici je balkon nebo terasa.')
        ->toContain('třída B – velmi úsporná')->toContain('Volné od '.now()->addMonth()->format('j. n. Y'))->toContain('Tramvaj 2 minuty pěšky.')
        ->not->toContain('parkování')->not->toContain('mazlíčci');
    expect(DescriptionGenerator::make(['offer_type' => 'rent']))->toBe(DescriptionGenerator::make(['offer_type' => 'rent'])); // deterministic
});

it('serves the description draft only to the listing owner', function () {
    $l = Listing::factory()->status('draft')->create(['offer_type' => 'sale', 'property_type' => 'house', 'disposition' => '4+1', 'area_m2' => 120, 'city' => 'Brno', 'district' => null, 'street' => null]);
    $this->actingAs($l->user)->postJson(route('assist.describe', $l), ['penb' => 'none', 'floor' => '', 'locality' => '<b>klidná</b> lokalita'])
        ->assertOk()->assertJsonPath('text', fn ($t) => str_starts_with($t, 'Prodej rodinného domu 4+1 o ploše 120 m², Brno.') && str_contains($t, 'PENB) zatím nebyl vydán') && str_contains($t, 'Klidná lokalita.') && mb_strlen($t) >= 30);
    $this->actingAs(assistOwner())->postJson(route('assist.describe', $l), [])->assertForbidden();
});
