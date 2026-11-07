<?php

use App\Models\Agency;
use App\Models\AgencyClaim;
use App\Models\AgencyReview;
use App\Models\Broker;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function aresSubject(array $over = []): array
{
    return array_merge([
        'ico' => '12345678', 'obchodniJmeno' => 'Reality Vltava s.r.o.', 'pravniForma' => '112', 'datumVzniku' => '2010-05-01',
        'sidlo' => ['textovaAdresa' => 'Vinohradská 1, 12000 Praha 2', 'nazevObce' => 'Praha', 'nazevKraje' => 'Hlavní město Praha'],
        'czNace' => ['68310'],
    ], $over);
}

function aresFake(array $subjects, ?int $total = null): void
{
    Http::fake(['ares.gov.cz/*' => Http::response(['pocetCelkem' => $total ?? count($subjects), 'ekonomickeSubjekty' => $subjects])]);
}

function dirAgency(array $over = []): Agency
{
    return Agency::create(array_merge(['ico' => (string) random_int(10000000, 99999999), 'name' => 'RK Test '.uniqid(), 'city' => 'Praha', 'region' => 'Hlavní město Praha'], $over));
}

function verifiedDirAgency(?User $owner = null): array
{
    $owner ??= User::factory()->role('agency')->create();
    $a = dirAgency();
    $a->forceFill(['claimed_by_user_id' => $owner->id, 'verified_at' => now(), 'source' => 'claimed'])->save();

    return [$a->fresh(), $owner];
}

function dirReview(Agency $a, array $over = []): AgencyReview
{
    $r = new AgencyReview(['rating' => $over['rating'] ?? 4, 'text' => 'Rychlé jednání, férové podmínky a ochotný makléř.']);
    $r->forceFill(['user_id' => User::factory()->create()->id, 'agency_id' => $a->id, 'status' => $over['status'] ?? 'published'])->save();
    $a->recalcRating();

    return $r;
}

beforeEach(fn () => config(['reality.ares.delay_ms' => 0]));

// --- ARES import ---

it('imports agencies from ARES filtered by NACE 68310 and upserts by IČO', function () {
    Http::fakeSequence('ares.gov.cz/*')
        ->push(['pocetCelkem' => 2, 'ekonomickeSubjekty' => [aresSubject(), aresSubject(['ico' => '87654321', 'obchodniJmeno' => 'Zaniklá RK', 'datumZaniku' => '2020-01-01'])]])
        ->push(['pocetCelkem' => 1, 'ekonomickeSubjekty' => [aresSubject(['obchodniJmeno' => 'Reality Vltava a.s.'])]]);
    $this->artisan('reality:agencies:import')->expectsOutputToContain('nových 1')->assertSuccessful();

    Http::assertSent(fn ($req) => str_contains($req->url(), 'ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/vyhledat')
        && $req['czNace'] === ['68310'] && $req->method() === 'POST');
    $a = Agency::sole();
    expect($a->ico)->toBe('12345678')->and($a->region)->toBe('Hlavní město Praha')->and($a->address)->toStartWith('Vinohradská')
        ->and($a->source)->toBe('ares')->and($a->slug)->toBe('reality-vltava-sro-12345678');

    $this->artisan('reality:agencies:import')->expectsOutputToContain('aktualizovaných 1')->assertSuccessful();
    expect(Agency::count())->toBe(1)->and(Agency::sole()->name)->toBe('Reality Vltava a.s.')->and(Agency::sole()->slug)->toBe($a->slug);
});

it('stores no street address for natural persons and keeps claimed contacts', function () {
    aresFake([aresSubject(['ico' => '11112222', 'obchodniJmeno' => 'Jan Novák', 'pravniForma' => '101'])]); // same response on every call
    $this->artisan('reality:agencies:import')->assertSuccessful();
    $a = Agency::sole();
    expect($a->natural_person)->toBeTrue()->and($a->address)->toBeNull()->and($a->city)->toBe('Praha');

    $a->forceFill(['web' => 'novak.cz', 'phone' => '+420 777 000 000'])->save();
    $this->artisan('reality:agencies:import')->assertSuccessful();
    expect($a->fresh()->web)->toBe('novak.cz')->and($a->fresh()->phone)->toBe('+420 777 000 000');
});

it('pages through ARES results', function () {
    config(['reality.ares.page_size' => 1]);
    Http::fakeSequence('ares.gov.cz/*')
        ->push(['pocetCelkem' => 2, 'ekonomickeSubjekty' => [aresSubject()]])
        ->push(['pocetCelkem' => 2, 'ekonomickeSubjekty' => [aresSubject(['ico' => '22223333'])]]);
    $this->artisan('reality:agencies:import')->assertSuccessful();
    expect(Agency::count())->toBe(2);
    Http::assertSentCount(2);
    Http::assertSent(fn ($req) => $req['start'] === 1 && $req['pocet'] === 1);
});

it('fails cleanly on ARES API errors', function () {
    Http::fake(['ares.gov.cz/*' => Http::response('error', 500)]);
    $this->artisan('reality:agencies:import')->assertFailed();
    expect(Agency::count())->toBe(0);
});

// --- public pages ---

it('lists agencies with search, region filter and sorting', function () {
    $a = dirAgency(['name' => 'Alfa Reality', 'region' => 'Jihomoravský kraj']);
    $b = dirAgency(['name' => 'Beta Reality']);
    dirReview($a, ['rating' => 3]);
    dirReview($b, ['rating' => 5]);
    [$c, $owner] = verifiedDirAgency();
    Listing::factory()->count(2)->create(['user_id' => $owner->id]);

    $this->get('/realitni-kancelare')->assertOk()->assertSeeInOrder(['Beta Reality', 'Alfa Reality'])->assertSee('Realitní kanceláře');
    $this->get('/realitni-kancelare?q=Alfa')->assertOk()->assertSee('Alfa Reality')->assertDontSee('Beta Reality');
    $this->get('/realitni-kancelare?kraj=Jihomoravský kraj')->assertOk()->assertSee('Alfa Reality')->assertDontSee('Beta Reality');
    $this->get('/realitni-kancelare?razeni=nabidky')->assertOk()->assertSeeInOrder([$c->name, 'Alfa Reality'])->assertSee('Nabídek: 2');
});

it('shows profile with public listings only, stats and RealEstateAgent JSON-LD', function () {
    [$a, $owner] = verifiedDirAgency();
    Listing::factory()->create(['user_id' => $owner->id, 'offer_type' => 'sale', 'price_halere' => 500000000, 'area_m2' => 50, 'city' => 'Brno']);
    Listing::factory()->create(['user_id' => $owner->id, 'description' => 'Neveřejný koncept '.str_repeat('x', 40)])->forceFill(['status' => 'draft'])->save();
    Listing::factory()->imported()->create(['description' => 'Importovaný inzerát '.str_repeat('y', 40)]);
    dirReview($a, ['rating' => 5]);
    dirReview($a, ['rating' => 1, 'status' => 'pending']);

    $res = $this->get(route('agencies.show', $a))->assertOk()
        ->assertSee('"@type":"RealEstateAgent"', false)->assertSee('"ratingValue":5', false)
        ->assertSee('100 000 Kč')->assertSee('Brno (1)')->assertDontSee('Neveřejný koncept')->assertDontSee('Importovaný inzerát');
    expect($res->getContent())->toContain('data-stat="count">1<');
    $this->get('/realitni-kancelare/neexistuje')->assertNotFound();
});

it('shows no listings for unverified agencies', function () {
    $user = User::factory()->role('agency')->create();
    $a = dirAgency();
    $a->forceFill(['claimed_by_user_id' => $user->id])->save();
    Listing::factory()->create(['user_id' => $user->id]);
    expect($a->publicListings()->count())->toBe(0);
});

it('renders broker profile pages and hides non-public brokers', function () {
    [$a] = verifiedDirAgency();
    $b = $a->brokers()->create(['name' => 'Petra Makléřová', 'bio' => 'Specialistka na Vinohrady.']);
    $hidden = $a->brokers()->create(['name' => 'Skrytý', 'is_public' => false]);
    $this->get(route('agencies.show', $a))->assertSee('Petra Makléřová')->assertDontSee('Skrytý');
    $this->get(route('agencies.broker', [$a, $b]))->assertOk()->assertSee('Specialistka na Vinohrady.')->assertSee('"parentOrganization"', false);
    $this->get(route('agencies.broker', [$a, $hidden]))->assertNotFound();
    $this->get(route('agencies.broker', [dirAgency(), $b]))->assertNotFound();
});

it('links the agency from a listing of its feed account', function () {
    [$a, $owner] = verifiedDirAgency();
    $l = Listing::factory()->create(['user_id' => $owner->id, 'source_name' => 'feed:'.$owner->id]);
    $this->get(route('listings.show', $l))->assertOk()->assertSee('data-agency-link', false)->assertSee($a->name);
    $other = Listing::factory()->create();
    $this->get(route('listings.show', $other))->assertOk()->assertDontSee('data-agency-link', false);
});

it('includes agencies and brokers in the sitemap', function () {
    [$a] = verifiedDirAgency();
    $b = $a->brokers()->create(['name' => 'Karel Makléř']);
    $this->get('/sitemap.xml')->assertOk()->assertSee(route('agencies.index'))->assertSee(route('agencies.show', $a))->assertSee(route('agencies.broker', [$a, $b]));
});

// --- reviews & moderation (DSA) ---

it('accepts reviews only from logged-in users and keeps them pending until moderation', function () {
    $a = dirAgency();
    $this->post(route('agencies.review', $a), ['rating' => 5, 'text' => str_repeat('Skvělé. ', 5), 'good_faith' => 1])->assertRedirect(route('login'));

    $u = User::factory()->create();
    $this->actingAs($u)->post(route('agencies.review', $a), ['rating' => 5, 'text' => 'Vše proběhlo hladce a rychle, doporučuji.', 'good_faith' => 1])->assertRedirect();
    $r = AgencyReview::sole();
    expect($r->status)->toBe('pending')->and($r->verified)->toBeFalse()->and($a->fresh()->reviews_count)->toBe(0);
    $this->get(route('agencies.show', $a))->assertDontSee('Vše proběhlo hladce');

    $this->actingAs($u)->post(route('agencies.review', $a), ['rating' => 1, 'text' => 'Druhá recenze stejného uživatele.', 'good_faith' => 1])->assertSessionHasErrors('text');
    $this->actingAs($u)->post(route('agencies.review', $a), ['rating' => 9, 'text' => 'Neplatné hodnocení mimo rozsah.', 'good_faith' => 1])->assertSessionHasErrors('rating');
    expect(AgencyReview::count())->toBe(1);
});

it('marks reviews verified when tied to a listing contact or viewing', function () {
    [$a, $owner] = verifiedDirAgency();
    $l = Listing::factory()->create(['user_id' => $owner->id]);
    $u = User::factory()->create();
    Message::forceCreate(['listing_id' => $l->id, 'sender_id' => $u->id, 'recipient_id' => $owner->id, 'sender_name' => 'X', 'sender_email' => 'x@example.test', 'body' => 'Dobrý den', 'relay_token' => 'tok'.uniqid()]);
    $this->actingAs($u)->post(route('agencies.review', $a), ['rating' => 4, 'text' => 'Prohlídka proběhla včas, makléř byl připravený.', 'good_faith' => 1]);
    expect(AgencyReview::sole()->verified)->toBeTrue();

    $this->actingAs($owner)->post(route('agencies.review', $a), ['rating' => 5, 'text' => 'Hodnotím vlastní kancelář nejlépe.', 'good_faith' => 1])->assertForbidden();
});

it('reviews a broker and recalculates ratings after admin moderation', function () {
    [$a] = verifiedDirAgency();
    $b = $a->brokers()->create(['name' => 'Eva Makléřka']);
    $u = User::factory()->create();
    $this->actingAs($u)->post(route('agencies.review', $a), ['rating' => 2, 'broker' => $b->slug, 'text' => 'Makléřka nekomunikovala, dlouho jsme čekali.', 'good_faith' => 1])->assertRedirect();
    $r = AgencyReview::sole();
    expect($r->broker_id)->toBe($b->id)->and($r->agency_id)->toBeNull();

    $this->actingAs($u)->get(route('admin.agency-reviews'))->assertForbidden();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
    $this->get(route('admin.agency-reviews'))->assertOk()->assertSee('Makléřka nekomunikovala');
    $this->post(route('admin.agency-reviews.decide', $r), ['status' => 'published'])->assertRedirect();
    expect($b->fresh()->reviews_count)->toBe(1)->and($b->fresh()->rating_avg)->toBe(2.0);
    $this->get(route('agencies.broker', [$a, $b]))->assertSee('Makléřka nekomunikovala');
});

it('lets anyone report a published review and admins hide it with a reason', function () {
    $a = dirAgency();
    $r = dirReview($a, ['rating' => 1]);
    $pending = dirReview($a, ['status' => 'pending']);
    expect($a->fresh()->reviews_count)->toBe(1);

    $this->post(route('agency-reviews.report', $pending), ['reason' => 'false', 'description' => 'Nepravdivé tvrzení o nás.', 'email' => 'a@example.test', 'good_faith' => 1])->assertNotFound();
    $this->post(route('agency-reviews.report', $r), ['reason' => 'false', 'description' => 'Nepravdivé tvrzení o nás.', 'email' => 'a@example.test', 'good_faith' => 1])->assertRedirect();
    $this->post(route('agency-reviews.report', $r), ['reason' => 'bogus', 'description' => 'x'])->assertSessionHasErrors(['reason', 'description', 'good_faith', 'email']);
    expect($r->fresh()->report_count)->toBe(1)->and($r->reports()->sole()->status)->toBe('open');

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
    $this->get(route('admin.agency-reviews'))->assertOk()->assertSee('nahlášeno 1×');
    $this->post(route('admin.agency-reviews.decide', $r), ['status' => 'hidden'])->assertSessionHasErrors('moderation_note');
    $this->post(route('admin.agency-reviews.decide', $r), ['status' => 'hidden', 'moderation_note' => 'Nepodložené obvinění.'])->assertRedirect();
    expect($r->fresh()->status)->toBe('hidden')->and($r->reports()->sole()->status)->toBe('upheld')->and($a->fresh()->reviews_count)->toBe(0);
    $this->get(route('agencies.show', $a))->assertDontSee('★ 1,0');
});

// --- claim flow ---

it('verifies a claim automatically when the feed account matches the agency web domain', function () {
    $a = dirAgency(['web' => 'https://www.rk-vltava.cz']);
    $user = User::factory()->role('agency')->create(['email' => 'info@rk-vltava.cz']);
    $user->forceFill(['feed_token_hash' => hash('sha256', 't'.uniqid()), 'email_verified_at' => now()])->save();

    $this->post(route('agencies.claim', $a), ['confirm' => 1])->assertRedirect(route('login'));
    $this->actingAs($user)->post(route('agencies.claim', $a), ['confirm' => 1])->assertRedirect()->assertSessionHas('status');
    $a->refresh();
    expect($a->isVerified())->toBeTrue()->and($a->claimed_by_user_id)->toBe($user->id)->and($a->source)->toBe('claimed')
        ->and(AgencyClaim::sole()->method)->toBe('feed');

    $this->actingAs(User::factory()->create())->post(route('agencies.claim', $a), ['confirm' => 1])->assertStatus(409);
});

it('sends other claims to admin approval', function () {
    $a = dirAgency(['web' => 'rk-vltava.cz']);
    $user = User::factory()->role('agency')->create(['email' => 'someone@gmail.test']);
    $user->forceFill(['feed_token_hash' => hash('sha256', 'x'.uniqid())])->save();
    $rival = User::factory()->create();

    $this->actingAs($user)->post(route('agencies.claim', $a), ['confirm' => 1, 'message' => 'Jsem jednatel.'])->assertRedirect();
    $this->actingAs($rival)->post(route('agencies.claim', $a), ['confirm' => 1])->assertRedirect();
    expect($a->fresh()->isVerified())->toBeFalse()->and(AgencyClaim::where('status', 'pending')->count())->toBe(2);
    $this->actingAs($user)->get(route('agencies.show', $a))->assertSee('čeká na ověření');

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
    $this->get(route('admin.agency-claims'))->assertOk()->assertSee('Jsem jednatel.');
    $claim = AgencyClaim::where('user_id', $user->id)->sole();
    $this->post(route('admin.agency-claims.decide', $claim), ['decision' => 'approve'])->assertRedirect();
    expect($a->fresh()->claimed_by_user_id)->toBe($user->id)->and($a->fresh()->isVerified())->toBeTrue()
        ->and(AgencyClaim::where('user_id', $rival->id)->sole()->status)->toBe('rejected');
    $this->post(route('admin.agency-claims.decide', $claim), ['decision' => 'reject'])->assertStatus(409);
});

it('lets the verified owner manage public contacts and brokers only', function () {
    [$a, $owner] = verifiedDirAgency();
    $this->actingAs(User::factory()->create())->put(route('agencies.update', $a), ['phone' => '123'])->assertForbidden();
    $this->actingAs($owner)->put(route('agencies.update', $a), ['web' => 'https://rk.cz', 'phone' => '+420 222 333 444', 'email' => 'info@rk.cz'])->assertRedirect();
    $this->get(route('agencies.show', $a))->assertSee('+420 222 333 444')->assertSee('"telephone":"+420 222 333 444"', false);

    $this->actingAs($owner)->post(route('agencies.brokers.store', $a), ['name' => 'Bez souhlasu'])->assertSessionHasErrors('consent');
    $this->actingAs($owner)->post(route('agencies.brokers.store', $a), ['name' => 'Nový Makléř', 'consent' => 1])->assertRedirect();
    $b = Broker::sole();
    $this->actingAs(User::factory()->create())->delete(route('agencies.brokers.destroy', [$a, $b]))->assertForbidden();
    $this->actingAs($owner)->delete(route('agencies.brokers.destroy', [$a, $b]))->assertRedirect();
    expect(Broker::count())->toBe(0);
});
