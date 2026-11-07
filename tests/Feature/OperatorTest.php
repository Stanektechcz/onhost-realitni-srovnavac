<?php

use App\Models\Payment;
use App\Models\User;
use App\Support\LegalEntity;
use App\Support\Operator;

function operator_config_with_env(array $env): array
{
    foreach ($env as $k => $v) {
        $_ENV[$k] = $_SERVER[$k] = $v;
        putenv("$k=$v");
    }
    try {
        return (static fn (): array => require config_path('operator.php'))();
    } finally {
        foreach (array_keys($env) as $k) {
            unset($_ENV[$k], $_SERVER[$k]);
            putenv($k);
        }
    }
}

it('defaults to Adrian Staněk as a non-VAT-payer operator', function () {
    expect(config('operator.operator'))->toBe('osvc-stanek-08094616')
        ->and(Operator::seller())->toBe(['name' => 'Adrian Staněk', 'ico' => '08094616', 'dic' => '', 'vat_payer' => false, 'legal_form' => 'osvc'])
        ->and(Operator::vatPayer())->toBeFalse()
        ->and(Operator::footer())->toBe('Provozovatel: Adrian Staněk, IČO 08094616, neplátce DPH');
});

it('overrides the active operator and fields via ECOSYSTEM_OPERATOR_* env', function () {
    $cfg = operator_config_with_env([
        'ECOSYSTEM_OPERATOR' => 'adrian-stanek',
        'ECOSYSTEM_OPERATOR_ADRIAN_STANEK_VAT_PAYER' => 'true',
        'ECOSYSTEM_OPERATOR_ADRIAN_STANEK_DIC' => 'CZ0000000000',
        'ECOSYSTEM_OPERATOR_ADRIAN_STANEK_ADDRESS_CITY' => 'Praha',
    ]);
    expect($cfg['operators']['adrian-stanek']['vat_payer'])->toBeTrue()
        ->and($cfg['operators']['adrian-stanek']['dic'])->toBe('CZ0000000000')
        ->and($cfg['operators']['adrian-stanek']['address']['city'])->toBe('Praha');

    expect(operator_config_with_env(['ECOSYSTEM_OPERATOR' => 'xyz-sro'])['operator'])->toBe('xyz-sro');
});

it('switches to a registered subsidiary and falls back on an unknown slug', function () {
    config(['operator.operators.xyz-sro' => ['name' => 'XYZ s.r.o.', 'legal_form' => 'sro', 'ico' => '12345678', 'dic' => 'CZ12345678', 'vat_payer' => true]]);
    config(['operator.operator' => 'xyz-sro']);
    expect(Operator::seller())->toBe(['name' => 'XYZ s.r.o.', 'ico' => '12345678', 'dic' => 'CZ12345678', 'vat_payer' => true, 'legal_form' => 'sro']);

    config(['operator.operator' => 'neexistuje']);
    expect(Operator::slug())->toBe('adrian-stanek');
});

it('freezes the seller on a payment so switching the operator keeps the old document', function () {
    $user = User::factory()->create();
    $p = Payment::create(['user_id' => $user->id, 'service' => 'membership', 'provider' => 'fake', 'amount_halere' => 10000, 'commission_halere' => 0, 'currency' => 'CZK', 'status' => 'created']);
    expect($p->seller_snapshot['ico'])->toBe('08094616');

    config(['operator.operators.xyz-sro' => ['name' => 'XYZ s.r.o.', 'legal_form' => 'sro', 'ico' => '12345678', 'vat_payer' => true], 'operator.operator' => 'xyz-sro']);
    expect($p->fresh()->seller_snapshot['name'])->toBe('Adrian Staněk');
    test()->get('/')->assertSee('provozovatel XYZ s.r.o., IČO 12345678', false);
});

it('keeps the default footer text identical', function () {
    test()->get('/')->assertSee('provozovatel Adrian Staněk, OSVČ, IČO 08094616, neplátce DPH.', false);
});

it('uses the entity id as default, accepts the adrian-stanek alias and derives vat_payer from ECOSYSTEM_OPERATOR_VAT_MODE', function () {
    expect(config('operator.operator'))->toBe('osvc-stanek-08094616')
        ->and(Operator::id())->toBe('osvc-stanek-08094616')
        ->and(Operator::snapshot()['id'])->toBe('osvc-stanek-08094616')
        ->and(Operator::get('vat_mode'))->toBe('non_payer');
    config(['operator.operator' => 'adrian-stanek']);
    expect(Operator::slug())->toBe('adrian-stanek');

    foreach (['payer' => true, 'group' => true, 'identified_person' => false, 'non_payer' => false] as $mode => $payer) {
        $cfg = operator_config_with_env(['ECOSYSTEM_OPERATOR_VAT_MODE' => $mode]);
        expect($cfg['operators']['adrian-stanek']['vat_mode'])->toBe($mode)
            ->and($cfg['operators']['adrian-stanek']['vat_payer'])->toBe($payer);
    }
});

it('hides the sole trader street on public pages but keeps it on payment documents', function () {
    expect(LegalEntity::showAddressPublic())->toBeFalse()
        ->and(operator_config_with_env([])['operators']['adrian-stanek']['show_address_public'])->toBeFalse()
        ->and(operator_config_with_env(['ECOSYSTEM_OPERATOR_ADRIAN_STANEK_SHOW_ADDRESS_PUBLIC' => 'true'])['operators']['adrian-stanek']['show_address_public'])->toBeTrue();
    foreach (['/', route('pages.about'), route('pages.contact'), route('legal', 'obchodni-podminky'), route('legal', 'ochrana-osobnich-udaju'), route('pages.faq')] as $url) {
        test()->get($url)->assertOk()->assertDontSee('Molákova');
    }
    $user = User::factory()->create();
    $p = Payment::create(['user_id' => $user->id, 'service' => 'membership', 'provider' => 'fake', 'amount_halere' => 10000, 'commission_halere' => 0, 'currency' => 'CZK', 'status' => 'created']);
    expect($p->fresh()->seller_snapshot['address']['street'])->toBe('Molákova 2145/5');
});

it('shows a company registered address publicly when the flag is true', function () {
    config(['operator.operators.xyz-sro' => ['name' => 'XYZ s.r.o.', 'legal_form' => 'sro', 'ico' => '12345678', 'show_address_public' => true,
        'address' => ['street' => 'Molákova 1', 'zip' => '628 00', 'city' => 'Brno']], 'operator.operator' => 'xyz-sro']);
    test()->get(route('pages.contact'))->assertOk()->assertSee('Sídlo Molákova 1, 628 00 Brno');
    test()->get(route('pages.about'))->assertOk()->assertSee('Molákova 1, 628 00 Brno');
    config(['operator.operators.xyz-sro.show_address_public' => false]);
    test()->get(route('pages.contact'))->assertOk()->assertDontSee('Molákova');
});
