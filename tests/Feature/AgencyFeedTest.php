<?php

use App\Models\Listing;
use App\Models\User;

function feedAgency(bool $autopublish = false): array
{
    $agency = User::factory()->role('agency')->create(['email' => 'rk'.uniqid().'@example.test']);
    test()->artisan('reality:feed:token', ['email' => $agency->email] + ($autopublish ? ['--autopublish' => true] : []))->assertSuccessful();
    $token = 'rf_test_'.uniqid();
    $agency->forceFill(['feed_token_hash' => hash('sha256', $token)])->save();

    return [$agency->fresh(), $token];
}

function feedRow(array $over = []): array
{
    return array_merge([
        'external_id' => 'RK-1', 'title' => 'Byt 2+kk Vinohrady', 'description' => 'Světlý byt po rekonstrukci, blízko metra a parku.',
        'offer_type' => 'rent', 'property_type' => 'flat', 'disposition' => '2+kk', 'area_m2' => 54,
        'price_czk' => 24000, 'fees_czk' => 3500, 'city' => 'Praha', 'district' => 'Vinohrady', 'penb' => 'C',
    ], $over);
}

it('rejects requests without a valid token', function () {
    $this->postJson('/api/feed/v1/listings', ['listings' => [feedRow()]])->assertStatus(401);
    $this->withToken('bad')->postJson('/api/feed/v1/listings', ['listings' => [feedRow()]])->assertStatus(401);
    $owner = User::factory()->create();
    $owner->forceFill(['feed_token_hash' => hash('sha256', 'owner-token')])->save();
    $this->withToken('owner-token')->postJson('/api/feed/v1/listings', ['listings' => [feedRow()]])->assertStatus(401);
});

it('creates pending listings, updates by external id and reports errors', function () {
    [$agency, $token] = feedAgency();
    $this->withToken($token)->postJson('/api/feed/v1/listings', ['listings' => [feedRow(), feedRow(['external_id' => 'RK-2', 'penb' => 'X'])]])
        ->assertOk()->assertJson(['created' => 1, 'updated' => 0])->assertJsonPath('errors.0.external_id', 'RK-2');

    $l = Listing::where('source_name', 'feed:'.$agency->id)->sole();
    expect($l->status)->toBe('pending')->and($l->user_id)->toBe($agency->id)->and($l->source)->toBe('internal')
        ->and($l->price_halere)->toBe(2400000)->and(Listing::public()->count())->toBe(0);

    $this->withToken($token)->postJson('/api/feed/v1/listings', ['listings' => [feedRow(['price_czk' => 23000])]])->assertJson(['created' => 0, 'updated' => 1]);
    expect($l->fresh()->price_halere)->toBe(2300000)->and(Listing::count())->toBe(1);
});

it('autopublishes for trusted agencies, lists status and withdraws', function () {
    [$agency, $token] = feedAgency(true);
    expect($agency->feed_autopublish)->toBeTrue();
    $this->withToken($token)->postJson('/api/feed/v1/listings', ['listings' => [feedRow()]])->assertOk();
    expect(Listing::public()->count())->toBe(1);

    $this->withToken($token)->getJson('/api/feed/v1/listings')->assertOk()->assertJsonPath('listings.0.status', 'active');
    $this->withToken($token)->deleteJson('/api/feed/v1/listings/RK-1')->assertOk();
    expect(Listing::public()->count())->toBe(0);
    $this->withToken($token)->deleteJson('/api/feed/v1/listings/NOPE')->assertNotFound();
});

it('keeps agencies isolated from each other', function () {
    [, $t1] = feedAgency(true);
    [, $t2] = feedAgency(true);
    $this->withToken($t1)->postJson('/api/feed/v1/listings', ['listings' => [feedRow()]]);
    $this->withToken($t2)->deleteJson('/api/feed/v1/listings/RK-1')->assertNotFound();
    $this->withToken($t2)->postJson('/api/feed/v1/listings', ['listings' => [feedRow()]])->assertJson(['created' => 1]);
    expect(Listing::public()->count())->toBe(2);
});

it('limits batch size', function () {
    [, $token] = feedAgency();
    $this->withToken($token)->postJson('/api/feed/v1/listings', ['listings' => []])->assertStatus(422);
    $this->withToken($token)->postJson('/api/feed/v1/listings', ['listings' => array_fill(0, 201, feedRow())])->assertStatus(422);
});
