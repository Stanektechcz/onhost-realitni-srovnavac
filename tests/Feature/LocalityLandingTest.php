<?php

use App\Models\Listing;
use App\Services\Localities;
use Database\Seeders\MarketBenchmarkSeeder;

function locLdBlocks(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    return array_map(fn ($j) => json_decode($j, true, 512, JSON_THROW_ON_ERROR), $m[1]);
}

function locBrnoListing(array $o = []): Listing
{
    return Listing::factory()->create($o + ['city' => 'Brno', 'district' => null, 'disposition' => '2+kk', 'area_m2' => 50, 'price_halere' => 2000000, 'offer_type' => 'rent']);
}

it('renders kraj, obec and Prague district landing pages with SEO metadata and valid JSON-LD', function (string $slug, string $place) {
    $this->seed(MarketBenchmarkSeeder::class);
    locBrnoListing(['title' => 'BRNO-VEREJNY']);
    $res = $this->get('/lokality/'.$slug)->assertOk()
        ->assertSee('Byty k pronájmu a prodeji – '.$place)
        ->assertSee('<link rel="canonical" href="'.route('insights.show', $slug).'">', false)
        ->assertSee('<meta name="description"', false)->assertSee('og:url', false)
        ->assertSee('Český statistický úřad')->assertSee('Časté otázky')
        ->assertDontSee('noindex', false);
    $blocks = locLdBlocks($res->getContent());
    expect(collect($blocks)->pluck('@type')->all())->toBe(['BreadcrumbList', 'FAQPage'])
        ->and($blocks[1]['mainEntity'])->not->toBeEmpty()
        ->and(end($blocks[0]['itemListElement'])['item'])->toBe(route('insights.show', $slug));
})->with([['jihomoravsky-kraj', 'Jihomoravský kraj'], ['brno', 'Brno'], ['praha-5', 'Praha 5'], ['praha', 'Praha']]);

it('shows public listing counts and latest listings, never imported ones', function () {
    locBrnoListing(['title' => 'BRNO-VEREJNY']);
    foreach ([300, 400, 500] as $p) {
        locBrnoListing(['title' => 'BRNO-TAJNY-IMPORT', 'price_halere' => $p * 50 * 100])->forceFill(['source' => 'imported'])->save();
    }
    $agg = app(Localities::class)->aggregate('brno');
    expect($agg['public']['rent'])->toBe(1)->and($agg['rent']['enough'])->toBeTrue();
    foreach (['/lokality/brno', '/pronajem/brno', '/lokality/jihomoravsky-kraj'] as $url) {
        $this->get($url)->assertOk()->assertSee('BRNO-VEREJNY')->assertDontSee('BRNO-TAJNY-IMPORT');
    }
    $this->get('/pronajem/brno')->assertSee('Byty k pronájmu – Brno')->assertSee(e(route('search', ['city' => 'Brno', 'offer_type' => 'rent'])), false);
});

it('marks combination pages noindex when they have no content', function () {
    locBrnoListing();
    $this->get('/pronajem/brno')->assertOk()->assertDontSee('noindex', false);
    $this->get('/prodej/brno')->assertOk()->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    $this->get('/lokality/zlin')->assertOk()->assertSee('noindex', false);
    $this->get('/prodej/neexistuje')->assertNotFound();
});

it('flushes cached aggregates when listings change', function () {
    expect(app(Localities::class)->aggregate('brno')['public']['rent'])->toBe(0);
    locBrnoListing();
    expect(app(Localities::class)->aggregate('brno')['public']['rent'])->toBe(1);
});

it('lists indexable locality and combination pages in the sitemap with lastmod', function () {
    $this->seed(MarketBenchmarkSeeder::class);
    locBrnoListing();
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('<loc>'.route('insights.show', 'jihomoravsky-kraj').'</loc><lastmod>')
        ->toContain(route('insights.show', 'praha-5'))
        ->toContain('<loc>'.route('localities.rent', 'brno').'</loc>')
        ->not->toContain('<loc>'.route('localities.sale', 'brno').'</loc>');
    expect(simplexml_load_string($xml))->not->toBeFalse();
});

it('splits the sitemap into an index above 5000 URLs', function () {
    Listing::factory()->count(5001)->create();
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('<sitemapindex')->toContain(route('sitemap.part', 2));
    $this->get(route('sitemap.part', 2))->assertOk()->assertSee('<urlset', false);
    $this->get(route('sitemap.part', 9))->assertNotFound();
});
