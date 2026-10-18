<?php

namespace App\Services\Payments;

use App\Models\CommissionRule;

/**
 * Commission on paid portal services only (membership, boosts). Never applied to property prices.
 * Default: 0 % (no rules, or feature flag off).
 */
class CommissionCalculator
{
    public const SERVICES = ['membership', 'listing_boost'];

    public function forService(string $service, int $amountHalere): int
    {
        if (! config('reality.features.commissions') || ! in_array($service, self::SERVICES, true)) {
            return 0;
        }
        $rule = CommissionRule::where('service', $service)->where('is_active', true)->latest('id')->first();
        if (! $rule) {
            return 0;
        }

        return (int) round($amountHalere * $rule->percent / 100) + $rule->fixed_halere;
    }
}
