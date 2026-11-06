<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\Payments\Comgate\ComgateException;
use Illuminate\Support\Str;

/** Local stub — never talks to the network. Production uses ComgatePaymentProvider. */
class StubPaymentProvider implements PaymentProvider
{
    public function create(Payment $payment): ?string
    {
        // fail closed: the stub "pays" everything, so it must never run in production (memberships for free)
        if (app()->isProduction()) {
            throw new ComgateException('stub_in_production', 503);
        }
        $payment->forceFill(['provider_ref' => 'STUB-'.Str::upper(Str::random(12)), 'status' => 'paid'])->save();

        return null;
    }

    public function status(Payment $payment): string
    {
        return $payment->status;
    }

    public function name(): string
    {
        return 'stub';
    }
}
