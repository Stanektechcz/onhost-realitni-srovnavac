<?php

use App\Models\Listing;

it('filters and sorts results', function () {
    Listing::factory()->create(['disposition' => '2+kk', 'price_halere' => 2000000, 'district' => 'Karlín']);
    Listing::factory()->create(['disposition' => '2+kk', 'price_halere' => 3000000, 'district' => 'Karlín']);
    Listing::factory()->create(['disposition' => '3+1', 'price_halere' => 1000000, 'district' => 'Žižkov']);

    $res = $this->get('/hledat?disposition[]=2%2Bkk&sort=price_desc&city=Karl')->assertOk();
    $items = $res->viewData('listings')->items();
    expect($items)->toHaveCount(2)->and($items[0]->price_halere)->toBe(3000000);

    expect($this->get('/hledat?price_max=15000')->viewData('listings')->total())->toBe(1);
});

it('paginates', function () {
    Listing::factory()->count(15)->create();
    expect($this->get('/hledat')->viewData('listings')->count())->toBe(12);
    expect($this->get('/hledat?page=2')->viewData('listings')->count())->toBe(3);
});

it('never exposes imported, draft, pending or expired listings', function () {
    $visible = Listing::factory()->create();
    $hidden = collect([
        Listing::factory()->imported()->create(),
        Listing::factory()->status('pending')->create(),
        Listing::factory()->status('draft')->create(),
        Listing::factory()->create(['expires_at' => now()->subDay()]),
    ]);
    $ids = collect($this->get('/hledat')->viewData('listings')->items())->pluck('id');
    expect($ids->all())->toBe([$visible->id]);
    $hidden->each(fn ($l) => $this->get(route('listings.show', $l))->assertNotFound());
    $map = $this->getJson('/api/mapa')->json('features');
    expect($map)->toHaveCount(1);
});

it('rejects invalid filter values', function () {
    $this->get('/hledat?sort=hack')->assertSessionHasErrors('sort');
});
