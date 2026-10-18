<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Str;

/** Local stub — never talks to the network. Comgate implementation will come later behind the same contract. */
class StubPaymentProvider implements PaymentProvider
{
    public function create(Payment $payment): ?string
    {
        $payment->forceFill(['provider_ref' => 'STUB-'.Str::upper(Str::random(12)), 'status' => 'paid'])->save();

        return null;
    }

    public function status(Payment $payment): string { return $payment->status; }

    public function name(): string { return 'stub'; }
}
