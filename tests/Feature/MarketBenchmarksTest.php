<?php

use App\Models\Listing;
use App\Models\MarketBenchmark;
use App\Services\Benchmarks\CsuBenchmarks;
use Database\Seeders\MarketBenchmarkSeeder;
use Illuminate\Support\Facades\Http;

function csuFakeCsv(): array
{
    $prices = "\u{FEFF}\"Ukazatel\",\"Území-Stát\",\"Území-Kraj\",\"Území-Okres\",\"Druh nemovitosti\",\"Roky\",\"Hodnota\",\"MJ_TEXT\",\"MJ_SYMBOL\",\"POZNAMKA\",\"POZNAMKA_TEXT\"\n"
        ."\"Průměrná jednotková kupní cena nemovitosti\",\"Česko\",\"Hlavní město Praha\",\"\",\"Byty [Kč/m2]\",\"2024\",\"100000.0\",\"koruna česká\",\"Kč\",\"\",\"\"\n"
        ."\"Průměrná jednotková kupní cena nemovitosti\",\"Česko\",\"Hlavní město Praha\",\"\",\"Byty [Kč/m2]\",\"2025\",\"110000.0\",\"koruna česká\",\"Kč\",\"\",\"\"\n";
    $index = "\"Ukazatel\",\"Druh nemovitosti\",\"Typ údaje\",\"Území-Stát\",\"Území-Kraj\",\"Roky\",\"Hodnota\",\"MJ_TEXT\",\"MJ_SYMBOL\",\"POZNAMKA\",\"POZNAMKA_TEXT\"\n"
        ."\"Index cen nemovitostí (%)\",\"Byty\",\"Bazický index (2015 = 100)\",\"Česko\",\"\",\"2025\",\"250.5\",\"procento\",\"%\",\"\",\"\"\n";

    return ['*CEN0402T02*' => Http::response($prices), '*CEN0401T01*' => Http::response($index)];
}

it('fetches ČSÚ benchmarks from public URLs and stores them', function () {
    Http::fake(csuFakeCsv());
    $this->artisan('reality:benchmarks:fetch')->assertSuccessful();
    expect(MarketBenchmark::count())->toBe(3);
    $b = MarketBenchmark::where('area', 'Hlavní město Praha')->where('period', '2025')->first();
    expect($b->value)->toBe(110000.0)->and($b->unit)->toBe('Kč/m²')->and($b->indicator)->toBe('Průměrná kupní cena – byty')
        ->and($b->license)->toContain('ČSÚ');
    Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://data.csu.gov.cz/api/dotaz/v1/data/vybery/'));
    // idempotent
    $this->artisan('reality:benchmarks:fetch')->assertSuccessful();
    expect(MarketBenchmark::count())->toBe(3);
});

it('fails gracefully when ČSÚ is unavailable', function () {
    Http::fake(['*' => Http::response('', 503)]);
    $this->artisan('reality:benchmarks:fetch')->assertFailed();
    expect(MarketBenchmark::count())->toBe(0);
});

it('seeds real committed ČSÚ values from CSV', function () {
    $this->seed(MarketBenchmarkSeeder::class);
    expect(MarketBenchmark::where('area', 'Hlavní město Praha')->where('source', 'CSU:CEN0402T02')->count())->toBeGreaterThan(5);
});

it('shows an empty state for official data when none are loaded', function () {
    $this->get('/lokality')->assertOk()->assertSee('Oficiální údaje zatím nejsou k dispozici');
});

it('shows official benchmarks with source citation on locality pages', function () {
    Http::fake(csuFakeCsv());
    $this->artisan('reality:benchmarks:fetch');
    $this->get('/lokality')->assertOk()->assertSee('Oficiální statistiky cen')->assertSee('110 000')->assertSee('+10,0 %', false)
        ->assertSee('Český statistický úřad')->assertSee('data.csu.gov.cz', false);
    Listing::factory()->create(['district' => 'Karlín', 'city' => 'Praha']);
    $this->get('/lokality/karlin')->assertOk()->assertSee('Hlavní město Praha')->assertSee('110 000');
    expect(CsuBenchmarks::areaFor('Brno'))->toBeNull();
});
