<?php

use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function wizardOwner(): User { return User::factory()->create(['role' => 'owner']); }

it('creates a listing through all steps and submits it', function () {
    Storage::fake('public');
    $u = wizardOwner();
    $this->actingAs($u)->get('/pridat-inzerat')->assertOk();
    $this->post('/pridat-inzerat', ['offer_type' => 'rent', 'property_type' => 'flat', 'disposition' => '2+kk', 'area_m2' => 50, 'price_czk' => 22000, 'fees_czk' => 3000, 'deposit_czk' => 44000, 'city' => 'Praha', 'district' => 'Karlín', 'lat' => 50.09, 'lng' => 14.45])->assertRedirect();
    $l = Listing::firstOrFail();
    expect($l->price_halere)->toBe(2200000)->and($l->status)->toBe('draft')->and($l->user_id)->toBe($u->id);

    expect($l->city)->toBe('Praha');
    $this->get(route('listings.step', [$l, 2]))->assertOk()->assertSee('Navrhnout text');

    // PENB is mandatory
    $this->put(route('listings.step.save', [$l, 2]), ['description' => str_repeat('Hezký byt. ', 5)])->assertSessionHasErrors('penb');
    $this->put(route('listings.step.save', [$l, 2]), ['penb' => 'X', 'description' => str_repeat('Hezký byt. ', 5)])->assertSessionHasErrors('penb');
    $this->put(route('listings.step.save', [$l, 2]), ['penb' => 'none', 'has_balcony' => 1, 'description' => str_repeat('Hezký byt. ', 5)])->assertRedirect(route('listings.step', [$l, 3]));

    $this->post(route('listings.photos', $l), ['photos' => [UploadedFile::fake()->image('a.jpg', 1200, 900)]])->assertSessionHasNoErrors();
    $photo = $l->photos()->first();
    expect($photo->path)->toEndWith('.webp');
    Storage::disk('public')->assertExists([$photo->path, $photo->thumb_path]);

    $this->get(route('listings.step', [$l, 4]))->assertOk()->assertSee('PENB nevydán');
    $this->post(route('listings.submit', $l))->assertRedirect(route('listings.mine'));
    expect($l->fresh()->status)->toBe('pending')->and($l->fresh()->has_balcony)->toBeTrue();
});

it('does not allow submitting incomplete listings', function () {
    $l = Listing::factory()->status('draft')->create(['penb' => null]);
    $this->actingAs($l->user)->post(route('listings.submit', $l))->assertRedirect(route('listings.step', [$l, 2]));
    expect($l->fresh()->status)->toBe('draft');
});

it('forbids seekers from creating listings and others from editing', function () {
    $this->actingAs(User::factory()->create(['role' => 'seeker']))->get('/pridat-inzerat')->assertForbidden();
    $l = Listing::factory()->create();
    $this->actingAs(wizardOwner())->get(route('listings.step', [$l, 1]))->assertForbidden();
    $this->put(route('listings.step.save', [$l, 2]), ['city' => 'Brno'])->assertForbidden();
});

it('rejects non-image uploads', function () {
    Storage::fake('public');
    $l = Listing::factory()->status('draft')->create();
    $this->actingAs($l->user)->post(route('listings.photos', $l), ['photos' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]])
        ->assertSessionHasErrors('photos.0');
});

it('sends an edited active listing back to moderation', function () {
    $l = Listing::factory()->create();
    $this->actingAs($l->user)->put(route('listings.step.save', [$l, 1]), ['offer_type' => $l->offer_type, 'property_type' => 'flat', 'disposition' => '2+kk', 'area_m2' => 50, 'price_czk' => 20000, 'city' => 'Brno'])->assertSessionHasNoErrors();
    expect($l->fresh()->status)->toBe('pending');
});

it('requires password confirmation to delete a listing', function () {
    $l = Listing::factory()->create();
    $this->actingAs($l->user)->delete(route('listings.destroy', $l))->assertRedirect(route('password.confirm'));
    $this->withSession(['auth.password_confirmed_at' => time()])->delete(route('listings.destroy', $l))->assertRedirect(route('listings.mine'));
    expect(Listing::find($l->id))->toBeNull();
});

it('rejects the old location-only step and requires city in step 1', function () {
    $this->actingAs(wizardOwner())->post('/pridat-inzerat', ['offer_type' => 'rent', 'property_type' => 'flat', 'disposition' => '2+kk', 'area_m2' => 50, 'price_czk' => 22000])
        ->assertSessionHasErrors('city');
    $l = Listing::factory()->status('draft')->create();
    $this->actingAs($l->user)->put(route('listings.step.save', [$l, 3]), [])->assertNotFound();
    $this->get(route('listings.step', [$l, 1]))->assertOk()->assertSee('Adresa – začněte psát', false)->assertSee('ČÚZK – on-line');
    $this->get(route('listings.step', [$l, 5]))->assertNotFound();
});
