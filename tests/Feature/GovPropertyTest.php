<?php

use App\Console\Commands\FetchGovProperty;
use App\Models\Listing;
use Illuminate\Support\Facades\Http;

function govCsv(array $rows): string
{
    $head = ['ID_ZAZNAMU', 'PUBLIKOVANO', 'PUBLIKUJCI', 'NABIZEJICI_ZAKLADATEL', 'POPIS', 'PLATNOST_DO', 'KONTAKT_OSOBA', 'KATASTRALNI_UZEMI', 'PARCELNI_CISLO', 'TYP_POZEMEK', 'TYP_BUDOVA', 'TYP_JEDNOTKA', 'TYP_JINY', 'ULICE', 'CP_CEV', 'POPIS_POLOZKY', 'POZADOVANA_CENA'];
    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, $head, escape: '');
    foreach ($rows as $r) {
        fputcsv($fh, array_map(fn ($k) => $r[$k] ?? '', $head), escape: '');
    }
    rewind($fh);

    return stream_get_contents($fh);
}

function govRow(array $over = []): array
{
    return array_merge(['ID_ZAZNAMU' => '109083', 'PUBLIKOVANO' => now()->subDay()->toDateString(), 'PUBLIKUJCI' => 'Správa železnic, státní organizace',
        'NABIZEJICI_ZAKLADATEL' => 'Ministerstvo dopravy', 'POPIS' => 'Pozemky p. č. 4047/5', 'PLATNOST_DO' => now()->addMonth()->toDateString(),
        'KONTAKT_OSOBA' => 'Jana Úřednice', 'KATASTRALNI_UZEMI' => 'Horní Počernice, Praha, okres Hlavní město Praha', 'PARCELNI_CISLO' => '4047/5',
        'TYP_POZEMEK' => 'ano', 'TYP_BUDOVA' => 'ne', 'TYP_JEDNOTKA' => 'ne', 'TYP_JINY' => 'ne', 'POZADOVANA_CENA' => '485000'], $over);
}

it('imports state property offers as public gov listings without personal contacts', function () {
    Http::fake([FetchGovProperty::URL => Http::response(govCsv([govRow(), govRow(['ID_ZAZNAMU' => '1', 'PLATNOST_DO' => now()->subDay()->toDateString()])]))]);
    $this->artisan('reality:gov:fetch')->expectsOutputToContain('nových 1')->assertSuccessful();

    $l = Listing::public()->sole();
    expect($l->source)->toBe('gov')->and($l->property_type)->toBe('land')->and($l->offer_type)->toBe('sale')
        ->and($l->price_halere)->toBe(48500000)->and($l->city)->toBe('Praha')->and($l->district)->toBe('Horní Počernice')
        ->and($l->source_url)->toBe('https://portal.gov.cz/nepotrebny-nemovity-majetek/109083')
        ->and($l->description)->not->toContain('Úřednice');

    $this->get(route('listings.show', $l))->assertOk()->assertSee('Kontakt u nabízejícího úřadu')->assertDontSee('Napsat inzerentovi');
    $this->post(route('listings.message', $l), ['name' => 'X', 'email' => 'x@example.test', 'body' => 'Dobrý den, mám zájem.'])->assertNotFound();
});

it('withdraws offers that left the register and rejects bad input', function () {
    Http::fake([FetchGovProperty::URL => Http::sequence()->push(govCsv([govRow()]))->push(govCsv([]))->push('<html>error</html>')]);
    $this->artisan('reality:gov:fetch')->assertSuccessful();
    $this->artisan('reality:gov:fetch')->expectsOutputToContain('staženo 1')->assertSuccessful();
    expect(Listing::public()->count())->toBe(0);
    $this->artisan('reality:gov:fetch')->assertFailed();
});
