<?php

namespace App\Support;

/** Orientační výpočty (nejde o finanční poradenství ani doporučení produktu). Logika je zrcadlena v resources/js/calculators.js. */
class Calculators
{
    /** Typical delivered energy per PENB class, kWh/m²/year (orientační střed třídy pro bytové domy). */
    public const PENB_KWH = ['A' => 40, 'B' => 75, 'C' => 112, 'D' => 155, 'E' => 200, 'F' => 260, 'G' => 330];

    public static function mortgagePayment(float $principal, float $ratePct, int $years): float
    {
        $n = $years * 12;
        if ($n <= 0 || $principal <= 0) {
            return 0.0;
        }
        $r = $ratePct / 100 / 12;

        return $r == 0.0 ? $principal / $n : $principal * $r / (1 - (1 + $r) ** -$n);
    }

    /** @return array{gross: float, net: float, tax: float, net_after_tax: float} percentages and annual tax in CZK */
    public static function rentalYield(float $price, float $monthlyRent, float $annualCosts = 0, float $vacancyPct = 0, float $taxPct = 15, float $flatExpensePct = 30): array
    {
        if ($price <= 0) {
            return ['gross' => 0.0, 'net' => 0.0, 'tax' => 0.0, 'net_after_tax' => 0.0];
        }
        $income = $monthlyRent * 12 * (1 - $vacancyPct / 100);
        $netIncome = $income - $annualCosts;
        $tax = max(0, $income * (1 - $flatExpensePct / 100)) * $taxPct / 100;

        return [
            'gross' => round($monthlyRent * 12 / $price * 100, 2),
            'net' => round($netIncome / $price * 100, 2),
            'tax' => round($tax),
            'net_after_tax' => round(($netIncome - $tax) / $price * 100, 2),
        ];
    }

    /** Monthly energy estimate in CZK from PENB class. */
    public static function energyMonthly(?string $penb, float $area, float $czkPerKwh = 4.0): ?int
    {
        $kwh = self::PENB_KWH[$penb] ?? null;

        return $kwh && $area > 0 ? (int) round($kwh * $area * $czkPerKwh / 12) : null;
    }
}
