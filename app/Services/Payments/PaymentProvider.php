<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentProvider
{
    /** Create a payment at the provider; returns redirect URL for the payer (or null when settled instantly). */
    public function create(Payment $payment): ?string;

    /** Query the provider for the current state: created|paid|failed. */
    public function status(Payment $payment): string;

    public function name(): string;
}
