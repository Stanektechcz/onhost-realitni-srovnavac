<?php

use App\Jobs\SendSavedSearchAlerts;
use App\Mail\ListingInquiry;
use App\Mail\SavedSearchAlert;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Report;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function contactPayload(array $o = []): array
{
    return $o + ['name' => 'Zájemce', 'email' => 'zajemce@example.test', 'body' => 'Dobrý den, mám zájem o prohlídku.', 'gdpr' => 1];
}

it('relays a contact message with masked sender', function () {
    Mail::fake();
    $l = Listing::factory()->create();
    $this->post(route('listings.message', $l), contactPayload())->assertSessionHasNoErrors()->assertRedirect();
    $m = Message::firstOrFail();
    expect($m->recipient_id)->toBe($l->user_id);
    Mail::assertQueued(ListingInquiry::class, fn ($mail) => $mail->hasTo($l->user->email));
    expect(Message::mask('jan.novak@example.cz'))->toBe('j*******k@e*****e.cz');
    $this->actingAs($l->user)->get('/moje/zpravy')->assertSee('z*****e@e*****e.test')->assertDontSee('zajemce@example.test');
});

it('silently drops honeypot submissions', function () {
    Mail::fake();
    $l = Listing::factory()->create();
    $this->post(route('listings.message', $l), contactPayload(['website' => 'http://spam']))->assertRedirect();
    expect(Message::count())->toBe(0);
    Mail::assertNothingQueued();
});

it('rate limits the contact form', function () {
    Mail::fake();
    $l = Listing::factory()->create();
    foreach (range(1, 3) as $_) {
        $this->post(route('listings.message', $l), contactPayload())->assertRedirect();
    }
    $this->post(route('listings.message', $l), contactPayload())->assertStatus(429);
});

it('toggles favorites', function () {
    $u = User::factory()->create(['role' => 'seeker']);
    $l = Listing::factory()->create();
    $this->actingAs($u)->post(route('listings.favorite', $l));
    expect($u->favorites()->count())->toBe(1);
    $this->get('/moje/oblibene')->assertSee($l->displayTitle());
    $this->post(route('listings.favorite', $l));
    expect($u->favorites()->count())->toBe(0);
});

it('saves a search and the watchdog mails only new matches', function () {
    Mail::fake();
    $u = User::factory()->create(['role' => 'seeker']);
    $this->actingAs($u)->post(route('account.searches.store'), ['offer_type' => 'rent', 'disposition' => ['2+kk']])->assertRedirect(route('account.searches'));
    $s = SavedSearch::firstOrFail();
    $s->forceFill(['last_notified_at' => now()->subHour()])->save();
    Listing::factory()->create(['disposition' => '2+kk', 'published_at' => now()]);
    Listing::factory()->create(['disposition' => '3+1', 'published_at' => now()]);
    Listing::factory()->imported()->create(['disposition' => '2+kk', 'published_at' => now()]);

    (new SendSavedSearchAlerts)->handle();
    Mail::assertSent(SavedSearchAlert::class, fn ($m) => $m->listings->count() === 1 && $m->hasTo($u->email));

    (new SendSavedSearchAlerts)->handle(); // nothing new
    Mail::assertSent(SavedSearchAlert::class, 1);
});

it('accepts a DSA report and admin resolves it with reasoning', function () {
    $l = Listing::factory()->create();
    $this->post(route('listings.report', $l), ['reason' => 'fraud', 'description' => 'Fotky jsou z jiného inzerátu.', 'email' => 'r@example.test', 'good_faith' => 1])->assertSessionHasNoErrors();
    $r = Report::firstOrFail();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.reports.resolve', $r), ['status' => 'upheld', 'decision_reason' => 'Potvrzen podvod.', 'take_down' => 1])->assertSessionHasNoErrors();
    expect($r->fresh()->status)->toBe('upheld')->and($l->fresh()->status)->toBe('rejected')->and($l->fresh()->isPublic())->toBeFalse();
});
