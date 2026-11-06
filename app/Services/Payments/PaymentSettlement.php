<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/** Applies the gateway's state to a payment once: paid → the subscription becomes active; failed → it stays inactive. */
class PaymentSettlement
{
    public function __construct(private readonly PaymentProvider $provider) {}

    public function settle(Payment $payment): Payment
    {
        if ($payment->status !== 'created' || $payment->provider !== $this->provider->name()) {
            return $payment;
        }
        $state = $this->provider->status($payment); // read outside the lock: a network call

        return DB::transaction(function () use ($payment, $state) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if ($p->status !== 'created' || $state === 'created') {
                return $p;
            }
            $p->update(['status' => $state]);
            $sub = $p->payable;
            if ($sub instanceof Subscription && $sub->status === 'pending') {
                if ($state === 'paid') {
                    $plan = $sub->plan;
                    $sub->update(['status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays($plan->period_days)]);
                } else {
                    $sub->update(['status' => 'cancelled']);
                }
            }
            AuditLog::record('payment.'.$state, $p, ['provider' => $p->provider]);

            return $p;
        });
    }
}
