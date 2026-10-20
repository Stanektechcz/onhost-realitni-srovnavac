<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\User;
use App\Services\Onhost\OnhostCreditClient;
use App\Services\Onhost\OnhostCreditException;

/**
 * "Zaplatit kreditem ONhost" — settles a Payment instantly from the payer's ONhost credit (behind ONHOST_CREDIT_ENABLED).
 * The idempotency key is derived from our own payment id, so a retried or repeated request can never charge twice.
 */
class OnhostCreditPaymentProvider implements PaymentProvider
{
    public const NAME = 'onhost_credit';

    public function __construct(private readonly OnhostCreditClient $credit) {}

    public static function chargeKey(Payment $payment): string { return 'reality-payment-'.$payment->id; }

    public static function reversalKey(Payment $payment): string { return 'reality-payment-'.$payment->id.'-reversal'; }

    /** @throws OnhostCreditException (the payment is then marked failed) */
    public function create(Payment $payment): ?string
    {
        $user = User::findOrFail($payment->user_id);
        try {
            $charge = $this->credit->charge($user, self::chargeKey($payment), (int) $payment->amount_halere,
                'ONhost Reality: '.($payment->meta['description'] ?? $payment->service), 'reality-payment-'.$payment->id);
        } catch (OnhostCreditException $e) {
            // a network failure leaves the outcome unknown: keep `created`, the same key settles it on the next attempt
            $payment->forceFill(['status' => $e->error === 'unavailable' ? 'created' : 'failed',
                'meta' => array_merge($payment->meta ?? [], ['onhost_error' => $e->error])])->save();
            throw $e;
        }
        $payment->forceFill([
            'status' => 'paid', 'provider_ref' => (string) $charge['transaction_id'],
            'meta' => array_merge($payment->meta ?? [], ['onhost_charge_id' => $charge['id'], 'onhost_split' => $charge['split'] ?? null]),
        ])->save();

        return null;
    }

    public function status(Payment $payment): string { return $payment->status; }

    public function name(): string { return self::NAME; }

    /** Cancellation / refund: reverses the whole ONhost charge (ONhost returns purchased and bonus credit by its own rules). */
    public function reverse(Payment $payment, ?string $reason = null): void
    {
        abort_unless($payment->provider === self::NAME && $payment->status === 'paid' && isset($payment->meta['onhost_charge_id']), 422);
        $reversal = $this->credit->reverse(User::findOrFail($payment->user_id), (string) $payment->meta['onhost_charge_id'], self::reversalKey($payment), null, $reason);
        $payment->forceFill(['status' => 'refunded', 'meta' => array_merge($payment->meta, ['onhost_reversal_id' => $reversal['id'] ?? null, 'onhost_reversal_tx' => $reversal['transaction_id'] ?? null])])->save();
    }
}
