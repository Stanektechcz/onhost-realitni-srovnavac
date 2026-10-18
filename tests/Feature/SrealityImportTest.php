<?php

use App\Models\Listing;
use App\Models\User;
use App\Services\Feed\XmlRpc;

const RPC2_SOFTWARE_KEY = 'crm-test-key';

function rpc2Agency(bool $autopublish = false): array
{
    config(['reality.import.software_keys' => [RPC2_SOFTWARE_KEY]]);
    $agency = User::factory()->role('agency')->create(['email' => 'rpc'.uniqid().'@example.test']);
    test()->artisan('reality:feed:token', ['email' => $agency->email, '--rpc' => true] + ($autopublish ? ['--autopublish' => true] : []))->assertSuccessful();
    $agency = $agency->fresh();

    return [$agency, $agency->feed_import_password];
}

function rpc2Call(string $method, array $params = []): array
{
    $xml = '<?xml version="1.0"?><methodCall><methodName>'.$method.'</methodName><params>'
        .implode('', array_map(fn ($p) => '<param>'.XmlRpc::encode($p).'</param>', $params)).'</params></methodCall>';

    return rpc2Raw($xml);
}

function rpc2Raw(string $xml): array
{
    $res = test()->call('POST', '/import/RPC2', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);
    $res->assertOk();
    $doc = simplexml_load_string($res->getContent());
    if (isset($doc->fault)) {
        return ['fault' => true];
    }
    [, $params] = XmlRpc::parseCall(str_replace(['methodResponse', '<params>'], ['methodCall', '<methodName>r</methodName><params>'], $res->getContent()));

    return $params[0];
}

/** Sreality client: holds the session and computes the next session_id for every call. */
function rpc2Session(User $agency, string $password, string $key = RPC2_SOFTWARE_KEY): object
{
    return new class($agency->id, $password, $key)
    {
        public string $sid;

        public function __construct(public int $client, public string $pw, public string $key)
        {
            $r = rpc2Call('getHash', [$client]);
            expect($r['status'])->toBe(200);
            $this->sid = $r['output'][0]['sessionId'];
        }

        public function call(string $method, array $args = []): array
        {
            $this->sid = substr($this->sid, 0, 48).md5($this->sid.md5($this->pw).$this->key);

            return rpc2Call($method, array_merge([$this->sid], $args));
        }
    };
}

function rpc2Advert(array $over = []): array
{
    return array_merge([
        'advert_rkid' => 'RK-77', 'advert_function' => 2, 'advert_type' => 1, 'advert_subtype' => 4,
        'advert_price' => 24000, 'advert_price_currency' => 1, 'advert_price_unit' => 2,
        'usable_area' => 54, 'locality_city' => 'Praha', 'locality_citypart' => 'Vinohrady', 'locality_street' => 'Mánesova', 'locality_cp' => '12',
        'energy_efficiency_rating' => 3, 'furnished' => 3, 'floor_number' => 3, 'elevator' => 1, 'balcony' => true,
        'description' => 'Světlý byt po rekonstrukci, blízko metra a parku.', 'ready_date' => '2026-11-01',
    ], $over);
}

it('logs in with the Sreality hash scheme and rejects bad credentials', function () {
    [$agency, $pw] = rpc2Agency();
    $s = rpc2Session($agency, $pw);
    expect($s->call('login')['status'])->toBe(200)
        ->and($s->call('listAdvert')['status'])->toBe(200)
        ->and($s->call('logout')['status'])->toBe(200)
        ->and($s->call('listAdvert')['status'])->toBe(407);

    expect(rpc2Session($agency, 'wrong')->call('login')['status'])->toBe(407)
        ->and(rpc2Session($agency, $pw, 'other-vendor')->call('login')['status'])->toBe(407)
        ->and(rpc2Call('getHash', [999999])['status'])->toBe(402)
        ->and(rpc2Call('listAdvert', [str_repeat('a', 80)])['status'])->toBe(407);

    // without login, other calls are refused
    expect(rpc2Session($agency, $pw)->call('listAdvert')['status'])->toBe(407);
    // owner accounts never get an import session
    $owner = User::factory()->create();
    $owner->forceFill(['feed_import_password' => 'x'])->save();
    expect(rpc2Call('getHash', [$owner->id])['status'])->toBe(402);
});

it('upserts adverts by advert_rkid with mapped codebooks and moderation', function () {
    [$agency, $pw] = rpc2Agency();
    $s = rpc2Session($agency, $pw);
    $s->call('login');
    $r = $s->call('addAdvert', [rpc2Advert()]);
    expect($r['status'])->toBe(200);

    $l = Listing::findOrFail($r['output'][0]['advert_id']);
    expect($l->status)->toBe('pending')->and($l->source)->toBe('internal')->and($l->user_id)->toBe($agency->id)
        ->and($l->source_name)->toBe('feed:'.$agency->id)->and($l->source_id)->toBe('RK-77')
        ->and($l->offer_type)->toBe('rent')->and($l->property_type)->toBe('flat')->and($l->disposition)->toBe('2+kk')
        ->and($l->penb)->toBe('C')->and($l->furnishing)->toBe('partial')->and($l->price_halere)->toBe(2400000)
        ->and($l->street)->toBe('Mánesova 12')->and((bool) $l->has_elevator)->toBeTrue()->and((bool) $l->has_balcony)->toBeTrue()
        ->and(Listing::public()->count())->toBe(0);

    $r2 = $s->call('addAdvert', [rpc2Advert(['advert_price' => 23000])]);
    expect($r2['output'][0]['advert_id'])->toBe($l->id)->and($l->fresh()->price_halere)->toBe(2300000)->and(Listing::count())->toBe(1);

    expect($s->call('addAdvert', [rpc2Advert(['advert_rkid' => 'RK-8', 'description' => null])])['status'])->toBe(452)
        ->and($s->call('addAdvert', [rpc2Advert(['advert_rkid' => 'RK-9', 'advert_function' => 3])])['status'])->toBe(452)
        ->and($s->call('addAdvert', [rpc2Advert(['advert_rkid' => 'RK-10', 'advert_price_currency' => 3])])['status'])->toBe(452)
        ->and($s->call('addPhoto', [['advert_rkid' => 'RK-77']])['status'])->toBe(501)
        ->and($s->call('listAdvert')['status'])->toBe(200); // session chain still intact after unsupported call
});

it('lists, autopublishes and deletes adverts', function () {
    [$agency, $pw] = rpc2Agency(true);
    $s = rpc2Session($agency, $pw);
    $s->call('login');
    $id = $s->call('addAdvert', [rpc2Advert()])['output'][0]['advert_id'];
    expect(Listing::public()->count())->toBe(1);

    $list = $s->call('listAdvert')['output'];
    expect($list)->toHaveCount(1)->and($list[0]['advert_rkid'])->toBe('RK-77')->and($list[0]['status'])->toBe('active')
        ->and($list[0]['advert_url'])->not->toBe('');

    expect($s->call('delAdvert', [$id, ''])['status'])->toBe(200)
        ->and(Listing::public()->count())->toBe(0)
        ->and($s->call('listAdvert')['output'])->toBe([])
        ->and($s->call('delAdvert', [0, 'NOPE'])['status'])->toBe(404);
});

it('keeps agencies isolated', function () {
    [$a1, $p1] = rpc2Agency(true);
    [$a2, $p2] = rpc2Agency(true);
    $s1 = rpc2Session($a1, $p1);
    $s1->call('login');
    $id = $s1->call('addAdvert', [rpc2Advert()])['output'][0]['advert_id'];

    $s2 = rpc2Session($a2, $p2);
    $s2->call('login');
    expect($s2->call('delAdvert', [$id, ''])['status'])->toBe(404)
        ->and($s2->call('delAdvert', [0, 'RK-77'])['status'])->toBe(404)
        ->and($s2->call('listAdvert')['output'])->toBe([]);
    // a2 cannot use a1's session with its own password
    $s2->sid = $s1->sid;
    expect($s2->call('listAdvert')['status'])->toBe(407);

    expect($s2->call('addAdvert', [rpc2Advert()])['status'])->toBe(407);
    $s2b = rpc2Session($a2, $p2);
    $s2b->call('login');
    $s2b->call('addAdvert', [rpc2Advert()]);
    expect(Listing::public()->count())->toBe(2)->and(Listing::find($id)->user_id)->toBe($a1->id);
});

it('rejects XXE, oversized and malformed requests', function () {
    $file = tempnam(sys_get_temp_dir(), 'xxe');
    file_put_contents($file, 'SECRET-XXE');
    $xxe = '<?xml version="1.0"?><!DOCTYPE m [<!ENTITY x SYSTEM "file://'.$file.'">]><methodCall><methodName>getHash</methodName><params><param><value><string>&x;</string></value></param></params></methodCall>';
    $res = test()->call('POST', '/import/RPC2', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xxe);
    expect($res->getContent())->toContain('<fault>')->not->toContain('SECRET-XXE');

    expect(rpc2Raw(str_repeat(' ', XmlRpc::MAX_BYTES + 1)))->toHaveKey('fault')
        ->and(rpc2Raw('<methodCall><methodName>x'))->toHaveKey('fault')
        ->and(rpc2Call('fooBar')['status'])->toBe(501)
        ->and(rpc2Call('version')['status'])->toBe(200);
    @unlink($file);
});

it('excludes the RPC2 endpoint from CSRF', function () {
    $mw = new class(app(), app('encrypter')) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
        public function rpcExcluded(): array { return $this->getExcludedPaths(); }
    };
    expect($mw->rpcExcluded())->toContain('import/RPC2');
});
