<?php

use App\Models\HandoverProtocol;
use App\Models\Listing;
use App\Models\ListingStat;
use App\Models\User;
use App\Models\ViewingSlot;
use App\Services\MarketStats;
use App\Support\Calculators;
use App\Support\ListingQuality;

function marketListing(array $o = []): Listing
{
    return Listing::factory()->create($o + ['district' => 'Karlín', 'disposition' => '2+kk', 'area_m2' => 50, 'price_halere' => 2000000]);
}

it('renders new public pages with an empty database', function (string $url) {
    $this->get($url)->assertOk();
})->with(['/lokality', '/kalkulacky', '/kalkulacky?tab=vynos', '/pruvodce', '/pruvodce/checklist-prohlidky', '/pruvodce/pruvodce-najmem', '/porovnat', '/api/lokality/heat']);

it('shows "málo dat" on locality pages without enough data', function () {
    $this->get('/lokality')->assertSee('Málo dat');
    marketListing();
    $this->get('/lokality/karlin')->assertOk()->assertSee('málo dat');
    $this->get('/lokality/neexistuje')->assertNotFound();
});

it('computes median and P25–P75 per district', function () {
    foreach ([300, 400, 500, 600, 700] as $ppm2) {
        marketListing(['price_halere' => $ppm2 * 50 * 100]);
    }
    $d = app(MarketStats::class)->findDistrict('karlin');
    expect($d['rent']['median'])->toEqual(500)->and($d['rent']['p25'])->toEqual(400)->and($d['rent']['p75'])->toEqual(600)->and($d['count'])->toBe(5);
    $this->get('/lokality/karlin')->assertOk()->assertSee('Karlín')->assertSee('500');
    $this->get('/lokality?c[]=karlin&c[]=karlin')->assertOk();
});

it('uses imported listings only in aggregates, never individually', function () {
    foreach ([300, 400, 500] as $p) {
        Listing::factory()->imported()->create(['district' => 'Karlín', 'area_m2' => 50, 'price_halere' => $p * 50 * 100, 'title' => 'TAJNY-IMPORT']);
    }
    $d = app(MarketStats::class)->findDistrict('karlin');
    expect($d['rent']['enough'])->toBeTrue();
    $this->get('/lokality/karlin')->assertOk()->assertDontSee('TAJNY-IMPORT');
    $heat = $this->getJson('/api/lokality/heat')->assertOk()->json();
    expect(json_encode($heat))->not->toContain('TAJNY-IMPORT')->not->toContain('slug');
    $imp = Listing::imported()->first();
    $this->get('/porovnat?l='.$imp->slug)->assertOk()->assertDontSee('TAJNY-IMPORT');
});

it('shows price vs market on the detail page', function () {
    foreach ([400, 400, 400] as $p) {
        marketListing(['price_halere' => $p * 50 * 100]);
    }
    $l = marketListing(['price_halere' => 500 * 50 * 100]);
    expect(app(MarketStats::class)->versusMarket($l)['diff_pct'])->toBe(25);
    $this->get(route('listings.show', $l))->assertOk()->assertSee('+25 % nad mediánem')->assertSee('Celkem měsíčně');
});

it('tracks views, saves and inquiries in daily stats', function () {
    $l = marketListing();
    $u = User::factory()->create();
    $this->get(route('listings.show', $l));
    $this->actingAs($u)->post(route('listings.favorite', $l));
    $this->post(route('listings.message', $l), ['name' => 'A', 'email' => 'a@example.test', 'body' => 'Dobrý den, mám zájem.', 'gdpr' => 1]);
    $s = ListingStat::where('listing_id', $l->id)->first();
    expect($s->views)->toBe(1)->and($s->saves)->toBe(1)->and($s->inquiries)->toBe(1);
    $this->actingAs($l->user)->get(route('owner.tools', $l))->assertOk()->assertSee('Kvalita inzerátu')->assertSee('Plánovač prohlídek');
    $this->actingAs($u)->get(route('owner.tools', $l))->assertForbidden();
});

it('filters search by bounding box and drawn polygon', function () {
    $in = marketListing(['lat' => 50.09, 'lng' => 14.45, 'title' => 'UVNITR']);
    $out = marketListing(['lat' => 49.5, 'lng' => 16.0, 'title' => 'VENKU']);
    $this->get('/hledat?bbox=14.4,50.0,14.5,50.2')->assertOk()->assertSee('UVNITR')->assertDontSee('VENKU');
    $this->get('/hledat?poly='.urlencode('14.40 50.05;14.50 50.05;14.50 50.15;14.40 50.15'))->assertOk()->assertSee('UVNITR')->assertDontSee('VENKU');
    $this->getJson('/api/mapa')->assertOk()->assertJsonPath('features.0.properties.short', fn ($v) => str_contains($v, 'tis.'));
});

it('lets a seeker book a viewing slot only once and the owner manage slots', function () {
    $l = marketListing();
    $this->actingAs($l->user)->post(route('owner.slots.store', $l), ['date' => now()->addDay()->toDateString(), 'from' => '17:00', 'to' => '18:00', 'duration' => 30])->assertSessionHasNoErrors();
    expect($l->slots()->count())->toBe(2);
    [$a, $b] = $l->slots()->get()->all();
    $seeker = User::factory()->create(['role' => 'seeker']);
    $this->actingAs($seeker)->post(route('viewings.book', $a), ['name' => 'Hledající'])->assertSessionHasNoErrors();
    expect($a->fresh()->booked_by)->toBe($seeker->id);
    $other = User::factory()->create();
    $this->actingAs($other)->post(route('viewings.book', $a), ['name' => 'Jiný'])->assertSessionHasErrors('slot');
    $this->actingAs($seeker)->post(route('viewings.book', $b), ['name' => 'Hledající'])->assertStatus(422);
    $this->actingAs($seeker)->get('/moje/prohlidky')->assertOk()->assertSee($l->displayTitle());
    $this->actingAs($l->user)->get('/moje/prohlidky')->assertOk()->assertSee('Hledající');
    $this->actingAs($seeker)->delete(route('viewings.cancel', $a))->assertRedirect();
    expect($a->fresh()->booked_by)->toBeNull();
    $this->actingAs($l->user)->delete(route('owner.slots.destroy', [$l, $b]))->assertRedirect();
    expect(ViewingSlot::count())->toBe(1);
});

it('estimates price from comparables and widens the scope', function () {
    foreach ([400, 450, 500] as $p) {
        marketListing(['price_halere' => $p * 50 * 100, 'disposition' => '3+1']);
    }
    $e = app(MarketStats::class)->estimate('rent', 'Karlín', 'Praha', '1+kk', 40);
    expect($e['enough'])->toBeTrue()->and($e['scope'])->toBe('Karlín')->and($e['mid'])->toBe(18000);
    $owner = User::factory()->create();
    $this->actingAs($owner)->get('/moje/odhad-ceny?offer_type=rent&district=Karlín&area=40')->assertOk()->assertSee('18 000');
});

it('scores listing quality', function () {
    $q = ListingQuality::check(marketListing(['penb' => 'none']));
    expect($q['score'])->toBeLessThan(100)->and($q['tips'])->toContain('Energetický štítek (PENB) vydán');
});

it('renews an expired listing and blocks boosting while payments are off', function () {
    $l = marketListing(['status' => 'expired', 'expires_at' => now()->subDay()]);
    $this->actingAs($l->user)->post(route('owner.renew', $l))->assertRedirect();
    expect($l->fresh()->status)->toBe('active')->and($l->fresh()->expires_at->isFuture())->toBeTrue();
    $this->actingAs($l->user)->withSession(['auth.password_confirmed_at' => time()])->post(route('owner.boost', $l))->assertNotFound();
});

it('boosts via the payment provider when the flags are on', function () {
    config(['reality.features.boost' => true, 'reality.features.payments' => true]);
    $l = marketListing();
    $this->actingAs($l->user)->withSession(['auth.password_confirmed_at' => time()])->post(route('owner.boost', $l))->assertRedirect();
    expect($l->fresh()->isBoosted())->toBeTrue();
    $this->assertDatabaseHas('payments', ['service' => 'listing_boost']);
});

it('creates a printable handover protocol', function () {
    $l = marketListing();
    $this->actingAs($l->user)->get(route('owner.handover', $l))->assertOk();
    $this->actingAs($l->user)->post(route('owner.handover.store', $l), [
        'landlord_name' => 'Majitel', 'tenant_name' => 'Nájemník Test', 'date' => now()->toDateString(), 'keys' => 3,
        'meters' => ['electricity' => '12345'], 'rooms' => ['Kuchyň' => 'škrábanec na lince'], 'checklist' => ['Předány klíče a čipy'],
    ])->assertRedirect();
    $p = HandoverProtocol::firstOrFail();
    $this->actingAs($l->user)->get(route('owner.handover.print', [$l, $p]))->assertOk()->assertSee('Nájemník Test')->assertSee('12345')->assertSee('škrábanec');
    $this->actingAs(User::factory()->create())->get(route('owner.handover.print', [$l, $p]))->assertForbidden();
});

it('shows contract templates as drafts prefilled from a listing', function () {
    $l = marketListing();
    $this->actingAs($l->user)->get('/moje/smlouvy')->assertOk()->assertSee('NÁVRH – čeká na advokáta');
    $this->actingAs($l->user)->get('/moje/smlouvy/najemni-smlouva-byt?l='.$l->slug)->assertOk()->assertSee('NÁVRH – čeká na advokáta')->assertSee('20 000 Kč');
    $this->actingAs($l->user)->get('/moje/smlouvy/neni')->assertNotFound();
});

it('compares up to four public listings side by side', function () {
    $ls = collect(range(1, 5))->map(fn ($i) => marketListing(['title' => "Byt číslo $i"]));
    $draft = marketListing(['status' => 'draft', 'title' => 'Koncept X']);
    $r = $this->get('/porovnat?l='.$ls->pluck('slug')->push($draft->slug)->join(','))->assertOk()
        ->assertSee('Byt číslo 1')->assertSee('Byt číslo 4')->assertDontSee('Byt číslo 5')->assertDontSee('Koncept X');
});

it('stores notes on favorites and archives messages', function () {
    $l = marketListing();
    $u = User::factory()->create();
    $this->actingAs($u)->post(route('listings.favorite', $l));
    $this->actingAs($u)->patch(route('account.favorites.note', $l), ['note' => 'Zavolat v pondělí'])->assertRedirect();
    $this->actingAs($u)->get('/moje/oblibene')->assertSee('Zavolat v pondělí');
    $this->post(route('listings.message', $l), ['name' => 'A', 'email' => 'a@example.test', 'body' => 'Dobrý den, mám zájem.', 'gdpr' => 1]);
    $m = \App\Models\Message::firstOrFail();
    $this->actingAs($u)->post(route('account.messages.archive', $m))->assertForbidden();
    $this->actingAs($l->user)->post(route('account.messages.archive', $m))->assertRedirect();
    $this->actingAs($l->user)->get('/moje/zpravy')->assertDontSee('mám zájem');
    $this->actingAs($l->user)->get('/moje/zpravy?box=archiv')->assertSee('mám zájem');
});

it('calculates mortgage, rental yield and energy orientačně', function () {
    expect(round(Calculators::mortgagePayment(5_000_000, 5, 30)))->toEqual(26841.0)
        ->and(Calculators::mortgagePayment(1200, 0, 1))->toEqual(100.0);
    $y = Calculators::rentalYield(6_000_000, 25_000);
    expect($y['gross'])->toEqual(5.0)->and($y['tax'])->toEqual(31500.0);
    expect(Calculators::energyMonthly('C', 60))->toBe(2240)->and(Calculators::energyMonthly('none', 60))->toBeNull();
    $this->get('/kalkulacky')->assertSee('257/2016');
});

it('does not run the demo seeder in production', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->seed();
    expect(Listing::count())->toBe(0);
});

it('renders account pages with the new navigation', function (string $url) {
    $u = User::factory()->create();
    $this->actingAs($u)->get($url)->assertOk()->assertSee('Prohlídky');
})->with(['/moje', '/moje/oblibene', '/moje/hlidaci-psi', '/moje/zpravy', '/moje/prohlidky', '/moje/odhad-ceny', '/moje/smlouvy', '/moje/inzeraty']);
