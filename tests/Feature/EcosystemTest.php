<?php

use App\Jobs\ExpireListings;
use App\Models\Listing;
use App\Models\MembershipPlan;
use App\Services\Ecosystem\BusSignature;
use App\Services\Ecosystem\ListingBusPublisher;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

function ecoBusOn(): void
{
    config([
        'ecosystem.bus.enabled' => true,
        'ecosystem.bus.secrets' => ['new-secret', 'old-secret'],
        'ecosystem.bus.subscriber_url' => 'https://bus.example.test/in',
    ]);
}

function ecoPost(string $body, ?string $header)
{
    return test()->call('POST', '/bus/webhook', [], [], [], array_filter([
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_ONHOST_SIGNATURE' => $header,
    ]), $body);
}

function ecoEnvelope(string $id = 'evt-1'): string
{
    return json_encode(['id' => $id, 'type' => 'subscription.activated', 'version' => 1, 'occurred_at' => now()->toAtomString(), 'data' => []]);
}

// --- flags off: standalone ---
it('hides every ecosystem endpoint while flags are off', function () {
    Http::fake();
    $this->getJson('/ecosystem/catalog.json')->assertNotFound();
    $this->getJson('/health')->assertNotFound();
    ecoPost(ecoEnvelope(), BusSignature::sign('x', ecoEnvelope(), time()))->assertNotFound();
    Listing::factory()->create();
    Http::assertNothingSent();
});

// --- catalog ---
it('serves the catalog in the exact format without personal data', function () {
    config(['ecosystem.catalog.enabled' => true]);
    $res = $this->getJson('/ecosystem/catalog.json')->assertOk()->assertJsonPath('format', 'onhost-ecosystem-catalog/1');
    $offers = $res->json('offers');
    expect($offers)->toHaveCount(1)
        ->and(array_keys($offers[0]))->toBe(['key', 'name', 'description', 'price_minor', 'currency', 'period', 'order_path'])
        ->and($offers[0]['price_minor'])->toBeNull();
});

it('lists own membership prices only when memberships are enabled, max 50, safe paths', function () {
    config(['ecosystem.catalog.enabled' => true]);
    foreach (range(1, 60) as $i) {
        MembershipPlan::create(['code' => "p$i", 'name' => "Plán $i", 'audience' => 'agency', 'price_halere' => 9900 + $i, 'period_days' => $i === 1 ? 365 : 30]);
    }
    expect($this->getJson('/ecosystem/catalog.json')->json('offers'))->toHaveCount(1);

    config(['reality.features.memberships' => true]);
    $offers = $this->getJson('/ecosystem/catalog.json')->json('offers');
    expect($offers)->toHaveCount(50);
    $plan = collect($offers)->firstWhere('key', 'reality-plan-p1');
    expect($plan['price_minor'])->toBe(9901)->and($plan['period'])->toBe('year')->and($plan['currency'])->toBe('CZK');
    foreach ($offers as $o) {
        expect($o['order_path'])->toStartWith('/')->not->toContain('//')->not->toContain('..')
            ->and(in_array($o['period'], ['one_time', 'month', 'year'], true))->toBeTrue()
            ->and($o['price_minor'] === null || is_int($o['price_minor']))->toBeTrue();
    }
});

it('accepts the order path with offer and source and keeps the normal flow', function () {
    config(['reality.features.memberships' => true]);
    $this->get('/clenstvi?offer=reality-plan-p1&source=onhost-id')->assertOk()
        ->assertSessionHas('ecosystem.referral', ['offer' => 'reality-plan-p1', 'source' => 'onhost-id']);
    $this->get('/pridat-inzerat?offer=reality-listing&source=onhost-id')->assertRedirect(route('login'));
    $this->flushSession();
    $this->get('/clenstvi?offer=../evil&source=onhost-id')->assertOk()->assertSessionMissing('ecosystem.referral');
});

// --- health ---
it('reports health without leaking details', function () {
    config(['ecosystem.health.enabled' => true]);
    $res = $this->getJson('/health')->assertOk()->assertJsonPath('project', 'reality')->assertJsonPath('status', 'ok');
    expect(array_keys($res->json()))->toBe(['project', 'status', 'checks'])
        ->and(array_keys($res->json('checks.database')))->toBe(['ok', 'ms']);
});

it('returns 503 when the database is down', function () {
    config(['ecosystem.health.enabled' => true, 'ecosystem.health.db_connection' => 'nonexistent_conn']);
    $res = $this->getJson('/health')->assertStatus(503)->assertJsonPath('status', 'down');
    expect($res->getContent())->not->toContain('nonexistent');
});

// --- bus receiver ---
it('accepts a valid signature, also with the rotated secret, idempotently', function () {
    ecoBusOn();
    $body = ecoEnvelope('evt-42');
    ecoPost($body, BusSignature::sign('old-secret', $body, time()))->assertStatus(202);
    ecoPost($body, BusSignature::sign('new-secret', $body, time()))->assertOk()->assertJsonPath('status', 'duplicate');
});

it('rejects bad, missing and expired signatures with 401', function () {
    ecoBusOn();
    $body = ecoEnvelope();
    ecoPost($body, BusSignature::sign('wrong', $body, time()))->assertStatus(401);
    ecoPost($body, null)->assertStatus(401);
    ecoPost($body, BusSignature::sign('new-secret', $body, time() - 301))->assertStatus(401);
    ecoPost($body.' ', BusSignature::sign('new-secret', $body, time()))->assertStatus(401);
    config(['ecosystem.bus.secrets' => []]);
    ecoPost($body, BusSignature::sign('', $body, time()))->assertStatus(401);
});

it('refuses a malformed envelope', function () {
    ecoBusOn();
    $body = json_encode(['id' => 'x', 'type' => 'BAD TYPE']);
    ecoPost($body, BusSignature::sign('new-secret', $body, time()))->assertStatus(422);
});

// --- bus publisher ---
it('publishes signed listing.created with public fields only', function () {
    ecoBusOn();
    Http::fake();
    $l = Listing::factory()->status('pending')->create(['published_at' => null]);
    Http::assertNothingSent();
    $l->forceFill(['status' => 'active', 'published_at' => now()])->save();

    Http::assertSent(function (HttpRequest $r) use ($l) {
        $env = json_decode($r->body(), true);
        $sig = new BusSignature(['new-secret']);

        return $r->url() === 'https://bus.example.test/in' && $sig->verify($r->header(BusSignature::HEADER)[0], $r->body())
            && $env['type'] === 'listing.created' && $env['version'] === 1 && $env['personal_data'] === false
            && $env['data']['listing_id'] === $l->slug
            && array_diff(array_keys($env['data']), ['listing_id', 'category', 'title', 'locality', 'price_minor', 'currency', 'url']) === []
            && ! str_contains($r->body(), (string) $l->user->email);
    });
});

it('publishes listing.removed on moderation, deletion and expiry', function () {
    ecoBusOn();
    Http::fake();
    $a = Listing::factory()->create();
    $a->forceFill(['status' => 'rejected'])->save();
    $b = Listing::factory()->create();
    $b->delete();
    $c = Listing::factory()->create(['expires_at' => now()->subMinute()]);
    (new ExpireListings)->handle(app(ListingBusPublisher::class));

    $reasons = collect(Http::recorded())->map(fn ($p) => json_decode($p[0]->body(), true))
        ->where('type', 'listing.removed')->pluck('data.reason', 'data.listing_id');
    expect($reasons[$a->slug])->toBe('moderated')->and($reasons[$b->slug])->toBe('withdrawn')->and($reasons[$c->slug])->toBe('expired');
});

it('never publishes imported listings', function () {
    ecoBusOn();
    Http::fake();
    $l = Listing::factory()->imported()->status('pending')->create();
    $l->forceFill(['status' => 'active'])->save();
    $l->forceFill(['status' => 'rejected'])->save();
    Listing::factory()->imported()->create(['expires_at' => now()->subMinute()]);
    (new ExpireListings)->handle(app(ListingBusPublisher::class));
    $l->delete();
    Http::assertNothingSent();
});

it('does not publish to a non-https subscriber', function () {
    ecoBusOn();
    config(['ecosystem.bus.subscriber_url' => 'http://bus.example.test/in']);
    Http::fake();
    Listing::factory()->create();
    Http::assertNothingSent();
});
