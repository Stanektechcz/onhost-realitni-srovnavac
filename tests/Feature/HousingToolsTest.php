<?php

use App\Models\Listing;
use App\Support\Calculators;
use App\Support\LocalityLinks;
use App\Support\Sjtsk;

it('computes annuity payment and remaining balance for a known example', function () {
    // 1 000 000 Kč, 5 % p.a., 30 let -> 5 368,22 Kč; zůstatek po 10 letech 813 420,64 Kč
    expect(round(Calculators::mortgagePayment(1_000_000, 5, 30), 2))->toBe(5368.22)
        ->and(round(Calculators::remainingBalance(1_000_000, 5, 30, 120), 2))->toBe(813420.64)
        ->and(Calculators::remainingBalance(1_000_000, 0, 10, 60))->toEqual(500000.0)
        ->and(Calculators::remainingBalance(1_000_000, 5, 30, 360))->toEqual(0.0);
});

it('applies ČNB LTV and DTI limits', function () {
    $own = Calculators::mortgageCheck(5_000_000, 4_000_000);
    expect($own['ltv'])->toEqual(80.0)->and($own['ltv_ok'])->toBeTrue()->and($own['dti_max'])->toBeNull();
    expect(Calculators::mortgageCheck(5_000_000, 4_500_000)['ltv_ok'])->toBeFalse();
    expect(Calculators::mortgageCheck(5_000_000, 4_500_000, under36: true)['ltv_ok'])->toBeTrue();
    $inv = Calculators::mortgageCheck(5_000_000, 3_500_000, investment: true, netMonthlyIncome: 40_000, monthlyPayment: 20_000);
    expect($inv['ltv_max'])->toBe(70)->and($inv['max_loan'])->toEqual(3_500_000)
        ->and($inv['dti'])->toEqual(7.29)->and($inv['dti_ok'])->toBeFalse()->and($inv['dsti'])->toEqual(50.0);
});

it('compares rent vs buy', function () {
    // Bez úroků, růstu a nákladů: koupě 1 000 000 hotově, nájem 0 -> nájemník má investovaných 1 000 000 + rozdíl 0
    $r = Calculators::rentVsBuy(1_000_000, 100, 0, 10, 0, 5, 0, 0, 0, 0, 0);
    expect($r['buy'])->toEqual(1_000_000)->and($r['rent'])->toEqual(1_000_000)->and($r['diff'])->toEqual(0);
    $r = Calculators::rentVsBuy(5_000_000, 20, 5, 30, 20_000, 10, 3, 3, 1, 4, 50_000);
    expect($r['payment'])->toEqual(21472.86)->and($r['buy'])->toBeGreaterThan($r['rent']);
});

it('computes rental cash-flow with flat-rate vs actual expenses', function () {
    $r = Calculators::rentalCashflow(20_000, 0, 30_000, 0, 0, 0, 15);
    expect($r['income'])->toEqual(240_000)
        ->and($r['flat'])->toMatchArray(['expenses' => 72_000, 'base' => 168_000, 'tax' => 25_200, 'cashflow' => 184_800])
        ->and($r['actual'])->toMatchArray(['expenses' => 30_000, 'tax' => 31_500])
        ->and($r['better'])->toBe('flat');
    // Paušál je zastropován na 600 000 Kč
    expect(Calculators::rentalCashflow(250_000, 0, 0, 0, 0, 0)['flat']['expenses'])->toEqual(600_000);
    expect(Calculators::rentalCashflow(20_000, 0, 30_000, 200_000, 150_000, 100_000)['better'])->toBe('actual');
    expect(round(Calculators::firstYearInterest(1_000_000, 5, 30)))->toEqual(49_665.0); // 12 × 5 368,22 − úmor 14 753
});

it('sums buying costs with cadastre fee 2 000 Kč per návrh', function () {
    $c = Calculators::buyingCosts(5_000_000, true, 5000, 0.3, 10000, 15000, 0, 3000);
    expect($c['items']['Poplatek za vklad vlastnického práva'])->toBe(2000)
        ->and($c['items']['Poplatek za vklad zástavního práva'])->toBe(2000)
        ->and($c['total'])->toEqual(42_000);
    expect(Calculators::buyingCosts(5_000_000, false, 5000, 0.3, 10000, 15000)['total'])->toEqual(32_000);
});

it('applies time tests on sale tax', function () {
    // Nabyto po 1. 1. 2021 -> 10 let; zisk 2 mil., 15 % do 1 762 812 Kč a 23 % nad
    $t = Calculators::saleTax(6_000_000, 4_000_000, 0, '2021-03-01', '2026-10-20');
    expect($t['exempt'])->toBeFalse()->and($t['test_years'])->toBe(10)->and($t['tax'])->toEqual(318_975)->and($t['exempt_from'])->toBe('2031-03-02');
    expect(Calculators::saleTax(6_000_000, 4_000_000, 0, '2020-06-01', '2025-06-02')['exempt'])->toBeTrue();
    expect(Calculators::saleTax(6_000_000, 4_000_000, 0, '2020-06-01', '2025-06-01')['exempt'])->toBeFalse();
    expect(Calculators::saleTax(6_000_000, 4_000_000, 0, '2023-01-01', '2026-01-01', residenceYears: 2)['reason'])->toContain('bydliště');
    expect(Calculators::saleTax(6_000_000, 4_000_000, 0, '2023-01-01', '2026-01-01', residenceYears: 2, isHome: false)['exempt'])->toBeFalse();
    expect(Calculators::saleTax(5_000_000, 4_000_000, 200_000, '2023-01-01', '2026-01-01')['tax'])->toEqual(120_000);
});

it('caps deposit at 3× rent and computes notice period end', function () {
    expect(Calculators::maxDeposit(18_000))->toEqual(54_000);
    expect(Calculators::noticeEnd('2026-10-20'))->toBe(['start' => '2026-11-01', 'end' => '2027-01-31']);
    expect(Calculators::noticeEnd('2026-11-30'))->toBe(['start' => '2026-12-01', 'end' => '2027-02-28']);
});

it('converts WGS84 to S-JTSK within a metre (ČÚZK reference points)', function () {
    $p = Sjtsk::fromWgs84(50.086981319032, 14.420574194849236); // Staroměstské nám. 1/4, Praha
    expect(abs($p['x'] + 742865.20))->toBeLessThan(1)->and(abs($p['y'] + 1043059.55))->toBeLessThan(1);
    $b = Sjtsk::fromWgs84(49.19539539604022, 16.6072497972766); // nám. Svobody, Brno
    expect(abs($b['x'] + 598212.69))->toBeLessThan(1)->and(abs($b['y'] + 1160715.55))->toBeLessThan(1);
});

it('builds locality link-outs', function () {
    $links = collect(LocalityLinks::for(50.086981319032, 14.420574194849236, 'Praha'))->keyBy('key');
    expect($links->keys()->all())->toBe(['katastr', 'povodne', 'hluk', 'doprava', 'skoly'])
        ->and($links['katastr']['url'])->toBe('https://nahlizenidokn.cuzk.gov.cz/MapaIdentifikace.aspx?l=KN&x=-742865&y=-1043060')
        ->and($links['doprava']['url'])->toContain('openstreetmap.org/?mlat=50.08698');
    expect(LocalityLinks::for(null, null, 'Brno')[0]['url'])->toBe('https://nahlizenidokn.cuzk.gov.cz/');
});

it('renders new calculator tabs with sources', function (string $tab, string $text) {
    $this->get('/kalkulacky?tab='.$tab)->assertOk()->assertSee($text)
        ->assertSee('Zdroje a pravidla (ověřeno 20. 10. 2026)')
        ->assertSee('cnb.cz', false)->assertSee('zakonyprolidi.cz/cs/1992-586', false)->assertSee('zakonyprolidi.cz/cs/2012-89', false);
})->with([
    ['hypoteka', 'Investiční nemovitost'], ['najem-koupe', 'Nájem, nebo koupě?'], ['cashflow', 'Paušál 30 %'],
    ['koupe', 'Kolik stojí koupě kromě ceny'], ['prodej', 'Prodej: náklady a daň z příjmu'], ['kauce', 'Kauce a výpovědní lhůta'],
    ['energie', 'Energetický odhad podle PENB'],
]);

it('renders bureaucracy checklists with official links', function (string $slug, array $links) {
    $res = $this->get('/pruvodce/'.$slug)->assertOk()->assertSee('x-data="checklist(', false)->assertSee('Ověřeno 20. 10. 2026');
    foreach ($links as $l) {
        $res->assertSee($l, false);
    }
    $this->get('/pruvodce')->assertSee(route('guides.show', $slug), false);
})->with([
    ['pronajimam-byt', ['https://nahlizenidokn.cuzk.gov.cz/', 'https://www.financnisprava.gov.cz/', 'https://portal.gov.cz/sluzby-vs/ohlaseni-zmeny-mista-trvaleho-pobytu-S605']],
    ['stehuji-se-do-najmu', ['https://obcan.portal.gov.cz/', 'https://isir.justice.cz/', 'https://portal.gov.cz/sluzby-vs/ohlaseni-zmeny-mista-trvaleho-pobytu-S605']],
    ['prodavam', ['https://nahlizenidokn.cuzk.gov.cz/', 'Navrh-na-zahajeni-rizeni-o-povoleni-vkladu', 'https://adisspr.mfcr.cz/pmd/home']],
    ['kupuji', ['https://nahlizenidokn.cuzk.gov.cz/', 'Navrh-na-zahajeni-rizeni-o-povoleni-vkladu', 'https://obcan.portal.gov.cz/', 'https://www.financnisprava.gov.cz/', 'https://cds.mzp.cz/']],
]);

it('shows locality data links on listing detail and locality page', function () {
    $l = Listing::factory()->create(['district' => 'Karlín', 'lat' => 50.0927, 'lng' => 14.4511]);
    $this->get(route('listings.show', $l))->assertOk()->assertSee('Data o lokalitě')
        ->assertSee('nahlizenidokn.cuzk.gov.cz/MapaIdentifikace.aspx?l=KN&amp;x=-', false)
        ->assertSee('https://cds.mzp.cz/', false)->assertSee('https://geoportal.mzcr.cz/shm/', false)->assertSee('openstreetmap.org/?mlat=', false);
    $this->get('/lokality/karlin')->assertOk()->assertSee('Data o lokalitě')->assertSee('https://cds.mzp.cz/', false);
});
