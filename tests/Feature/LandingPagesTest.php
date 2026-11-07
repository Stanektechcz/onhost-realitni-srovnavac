<?php

use App\Mail\ContactFormSubmitted;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

function landingContactPayload(array $over = []): array
{
    return array_merge(['name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'topic' => 'obecne', 'body' => 'Dobrý den, mám dotaz k inzerci.', 'gdpr' => '1'], $over);
}

it('renders the landing page with all sections and FAQ schema', function () {
    $html = $this->get(route('home'))->assertOk()
        ->assertSee('Jak to funguje')->assertSee('Porovnejte nabídky i ceny lokalit')->assertSee('Katalog realitních kanceláří')
        ->assertSee('"@type":"FAQPage"', false)->assertSee('<details', false)
        ->assertDontSee('noindex', false)->getContent();
    expect($html)->toContain(route('compare'))->toContain(route('insights.index'))->toContain(route('pages.faq'));
});

it('renders every public subpage indexable with key text', function (string $route, string $text) {
    $this->get(route($route))->assertOk()->assertSee($text)->assertDontSee('noindex', false)
        ->assertDontSee('s.r.o.')->assertDontSee('největší');
})->with([
    ['pages.how', 'Jak to funguje'],
    ['pages.owners', 'Pronajímejte a prodávejte sami'],
    ['pages.agencies', 'RPC2'],
    ['pages.agencies.docs', '/api/feed/v1/listings'],
    ['pages.comparator', 'Český statistický úřad'],
    ['pages.about', '08094616'],
    ['pages.contact', 'Odeslat zprávu'],
    ['pages.faq', 'Časté dotazy'],
]);

it('states truthful sourcing on the comparator page', function () {
    $this->get(route('pages.comparator'))->assertSee('inzerované (nabídkové) ceny')
        ->assertSee('Nepřebíráme, nestahujeme ani nezobrazujeme inzeráty či ceny z jiných realitních portálů');
});

it('shows operator as OSVČ and FAQ schema on the FAQ page', function () {
    $this->get(route('pages.about'))->assertSee('Adrian Staněk')->assertSee('OSVČ');
    $this->get(route('pages.faq'))->assertSee('"@type":"FAQPage"', false)->assertSee('ONhost ID je jen volitelná');
});

it('keeps the compare tool noindex', function () {
    $this->get(route('compare'))->assertOk()->assertSee('noindex', false);
});

it('links new pages from header and footer', function () {
    $html = $this->get(route('pages.faq'))->getContent();
    foreach (['pages.how', 'pages.owners', 'pages.agencies', 'pages.about', 'pages.contact'] as $r) {
        expect($html)->toContain('href="'.route($r).'"');
    }
    expect(substr_count($html, 'href="'.route('pages.comparator').'"'))->toBeGreaterThanOrEqual(2);
});

it('lists the pages in the sitemap', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    foreach (['pages.how', 'pages.owners', 'pages.agencies', 'pages.agencies.docs', 'pages.comparator', 'pages.about', 'pages.contact', 'pages.faq'] as $r) {
        expect($xml)->toContain('<loc>'.route($r).'</loc>');
    }
});

it('stores a contact message and mails the configured address', function () {
    Mail::fake();
    config(['reality.contact_email' => 'podpora@example.cz']);
    $this->post(route('pages.contact.send'), landingContactPayload(['topic' => 'rk']))->assertRedirect(route('pages.contact'))->assertSessionHas('status');
    expect(ContactMessage::where('email', 'jana@example.cz')->where('topic', 'rk')->exists())->toBeTrue();
    Mail::assertQueued(ContactFormSubmitted::class, fn ($m) => $m->hasTo('podpora@example.cz'));
});

it('stores without mailing when no address is configured', function () {
    Mail::fake();
    config(['reality.contact_email' => null]);
    $this->post(route('pages.contact.send'), landingContactPayload())->assertRedirect();
    expect(ContactMessage::count())->toBe(1);
    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

it('validates the contact form', function () {
    Mail::fake();
    $this->from(route('pages.contact'))->post(route('pages.contact.send'), landingContactPayload(['email' => 'x', 'body' => 'krátké', 'topic' => 'neexistuje', 'gdpr' => null]))
        ->assertRedirect(route('pages.contact'))->assertSessionHasErrors(['email', 'body', 'topic', 'gdpr']);
    expect(ContactMessage::count())->toBe(0);
    $this->from(route('pages.contact'))->followingRedirects()->post(route('pages.contact.send'), landingContactPayload(['email' => 'x']))
        ->assertSee('aria-invalid="true"', false)->assertSee('Formulář obsahuje chyby');
});

it('silently drops honeypot submissions', function () {
    Mail::fake();
    config(['reality.contact_email' => 'podpora@example.cz']);
    $this->post(route('pages.contact.send'), landingContactPayload(['website' => 'http://spam.example']))->assertRedirect(route('pages.contact'))->assertSessionHas('status');
    expect(ContactMessage::count())->toBe(0);
    Mail::assertNothingQueued();
});

it('throttles the contact form', function () {
    Mail::fake();
    RateLimiter::clear('contact');
    foreach (range(1, 3) as $i) {
        $this->post(route('pages.contact.send'), landingContactPayload())->assertRedirect();
    }
    $this->post(route('pages.contact.send'), landingContactPayload())->assertStatus(429);
    expect(ContactMessage::count())->toBe(3);
});

it('preselects topic from query string', function () {
    $this->get(route('pages.contact', ['tema' => 'rk']))->assertSee('<option value="rk" selected', false);
});
