<?php

use App\Models\Payment;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\Operator;
use Illuminate\Support\Facades\DB;

function document_number_payment(User $user): Payment
{
    return Payment::create(['user_id' => $user->id, 'service' => 'membership', 'provider' => 'fake', 'amount_halere' => 10000,
        'commission_halere' => 0, 'currency' => 'CZK', 'status' => 'created']);
}

it('starts a new number series when the operator switches and keeps old documents', function () {
    $this->travelTo('2026-11-07 12:00:00');
    $user = User::factory()->create();
    $a1 = document_number_payment($user);
    expect($a1->document_number)->toBe('ADRIAN-STANEK-2026-00001')
        ->and(document_number_payment($user)->document_number)->toBe('ADRIAN-STANEK-2026-00002');

    config(['operator.operators.xyz-sro' => ['id' => 'sro-xyz-12345678', 'name' => 'XYZ s.r.o.', 'legal_form' => 'sro', 'ico' => '12345678',
        'vat_payer' => true, 'number_prefix' => 'XYZ'], 'operator.operator' => 'sro-xyz-12345678']);
    $x1 = document_number_payment($user);
    expect($x1->document_number)->toBe('XYZ-2026-00001')
        ->and($x1->seller_snapshot['id'])->toBe('sro-xyz-12345678');

    $a1->refresh();
    expect($a1->document_number)->toBe('ADRIAN-STANEK-2026-00001')
        ->and($a1->seller_snapshot['ico'])->toBe('08094616')
        ->and($a1->seller_snapshot['number_prefix'])->toBe('ADRIAN-STANEK');

    config(['operator.operator' => 'adrian-stanek']);
    expect(document_number_payment($user)->document_number)->toBe('ADRIAN-STANEK-2026-00003');
    $this->travelTo('2027-01-02 08:00:00');
    expect(document_number_payment($user)->document_number)->toBe('ADRIAN-STANEK-2027-00001')
        ->and(Payment::distinct()->count('document_number'))->toBe(5);
});

it('derives the prefix from the slug and leaves rows without a number untouched', function () {
    config(['operator.operators.nova-firma' => ['name' => 'Nová firma s.r.o.']]);
    expect(DocumentNumber::prefix('nova-firma'))->toBe('NOVA-FIRMA');
    $user = User::factory()->create();
    $id = DB::table('payments')->insertGetId(['user_id' => $user->id, 'service' => 'membership', 'provider' => 'fake', 'amount_halere' => 1,
        'commission_halere' => 0, 'currency' => 'CZK', 'status' => 'paid', 'created_at' => now(), 'updated_at' => now()]);
    document_number_payment($user);
    expect(Payment::find($id)->document_number)->toBeNull();
});

it('picks the operator version with the highest valid_from not in the future', function () {
    $base = (array) config('operator.operators.adrian-stanek');
    config(['operator.operators.adrian-stanek' => [
        $base,
        ['id' => 'osvc-stanek-08094616-v2', 'vat_mode' => 'payer', 'vat_payer' => true, 'dic' => 'CZ0000000000',
            'valid_from' => '2027-01-01T00:00:00+01:00', 'number_prefix' => 'AS'] + $base,
        ['name' => 'Budoucí', 'valid_from' => '2030-01-01T00:00:00+01:00'] + $base,
    ]]);

    $this->travelTo('2026-12-31 22:00:00');
    expect(Operator::id())->toBe('osvc-stanek-08094616')
        ->and(Operator::vatPayer())->toBeFalse()
        ->and(DocumentNumber::prefix())->toBe('ADRIAN-STANEK');

    $this->travelTo('2027-01-01 00:00:01');
    expect(Operator::id())->toBe('osvc-stanek-08094616-v2')
        ->and(Operator::vatPayer())->toBeTrue()
        ->and(document_number_payment(User::factory()->create())->document_number)->toBe('AS-2027-00001');

    config(['operator.operator' => 'osvc-stanek-08094616-v2']);
    expect(Operator::slug())->toBe('adrian-stanek')
        ->and(Operator::versions('adrian-stanek'))->toHaveCount(3);
});
