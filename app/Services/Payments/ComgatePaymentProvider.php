<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\Payments\Comgate\ComgateClient;

/**
 * Own payment gateway of ONhost Reality (Comgate, merchant = operator from config/operator.php) — works without ONhost credit or ONhost ID.
 * create() returns the redirect to the gateway; the payment is settled only by PaymentSettlement after re-reading status().
 */
class ComgatePaymentProvider implements PaymentProvider
{
    public function __construct(private readonly ComgateClient $client) {}

    public static function fromConfig(): self
    {
        $c = (array) config('reality.payments.comgate');

        return new self(new ComgateClient((string) ($c['merchant'] ?? ''), (string) ($c['secret'] ?? ''), (bool) ($c['test'] ?? true),
            (string) ($c['base_url'] ?? 'https://payments.comgate.cz/v2.0'), ComgateClient::allowlist($c['callback_ips'] ?? '')));
    }

    public function client(): ComgateClient
    {
        return $this->client;
    }

    public function create(Payment $payment): ?string
    {
        $created = $this->client->create((int) $payment->amount_halere, (string) ($payment->currency ?: 'CZK'), [
            'reference' => 'reality-'.$payment->id,
            'label' => 'ONhost Reality',
            'email' => (string) optional($payment->user)->email,
            'url_paid' => route('payments.return', $payment),
            'url_cancelled' => route('payments.return', $payment),
            'url_pending' => route('payments.return', $payment),
        ]);
        $payment->forceFill(['provider_ref' => $created['trans_id']])->save();

        return $created['redirect'];
    }

    public function status(Payment $payment): string
    {
        if (! $payment->provider_ref) {
            return 'failed';
        }
        $s = $this->client->status($payment->provider_ref);
        // amount, currency and our reference must match what we created, otherwise it is not this payment
        if ($s['state'] === 'PAID' && ($s['amount_minor'] !== (int) $payment->amount_halere
            || $s['currency'] !== strtoupper((string) ($payment->currency ?: 'CZK')) || $s['reference'] !== 'reality-'.$payment->id)) {
            return 'failed';
        }

        return match ($s['state']) {
            'PAID' => 'paid',
            'CANCELLED' => 'failed',
            default => 'created',
        };
    }

    public function name(): string
    {
        return 'comgate';
    }
}
