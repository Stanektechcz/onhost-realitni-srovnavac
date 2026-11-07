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

    // ---------------------------------------------------------------------------------------------------------
    // Pravidla ověřená 20. 10. 2026 – viz config/reality_rules.php (zdroje a data ověření).
    // ---------------------------------------------------------------------------------------------------------

    /** Remaining principal after $months annuity payments. */
    public static function remainingBalance(float $principal, float $ratePct, int $years, int $months): float
    {
        $n = $years * 12;
        if ($principal <= 0 || $months >= $n) {
            return 0.0;
        }
        $r = $ratePct / 100 / 12;
        if ($r == 0.0) {
            return $principal * (1 - $months / $n);
        }
        $pmt = self::mortgagePayment($principal, $ratePct, $years);

        return max(0.0, $principal * (1 + $r) ** $months - $pmt * (((1 + $r) ** $months - 1) / $r));
    }

    /**
     * ČNB limits for a residential mortgage (LTV binding; DTI/DSTI deactivated for owner-occupied housing,
     * investment purchases: recommended LTV 70 % and DTI 7 from 1 April 2026).
     *
     * @return array{ltv: float, ltv_max: int, max_loan: float, ltv_ok: bool, dti: ?float, dti_max: ?int, dti_ok: bool, dsti: ?float}
     */
    public static function mortgageCheck(float $price, float $loan, bool $under36 = false, bool $investment = false, float $netMonthlyIncome = 0, float $monthlyPayment = 0, float $otherDebt = 0): array
    {
        $r = config('reality_rules.cnb');
        $ltvMax = $investment ? $r['ltv_investment'] : ($under36 ? $r['ltv_under36'] : $r['ltv']);
        $ltv = $price > 0 ? $loan / $price * 100 : 0.0;
        $dti = $netMonthlyIncome > 0 ? ($loan + $otherDebt) / ($netMonthlyIncome * 12) : null;
        $dtiMax = $investment ? $r['dti_investment'] : null;

        return [
            'ltv' => round($ltv, 2), 'ltv_max' => $ltvMax, 'max_loan' => round($price * $ltvMax / 100), 'ltv_ok' => $ltv <= $ltvMax + 1e-9,
            'dti' => $dti === null ? null : round($dti, 2), 'dti_max' => $dtiMax, 'dti_ok' => $dtiMax === null || $dti === null || $dti <= $dtiMax,
            'dsti' => $netMonthlyIncome > 0 ? round($monthlyPayment / $netMonthlyIncome * 100, 1) : null,
        ];
    }

    /**
     * Rent vs buy over N years: wealth of the buyer (property value − remaining loan) vs the renter, who invests the
     * down payment + purchase costs and every month the difference between owner's outlay and rent.
     *
     * @return array{buy: float, rent: float, diff: float, payment: float, balance: float, value: float}
     */
    public static function rentVsBuy(float $price, float $ownPct, float $ratePct, int $loanYears, float $monthlyRent, int $horizon,
        float $priceGrowthPct = 3, float $rentGrowthPct = 3, float $ownerCostPct = 1, float $investReturnPct = 4, float $buyCosts = 0): array
    {
        $loan = $price * (1 - $ownPct / 100);
        $pmt = self::mortgagePayment($loan, $ratePct, $loanYears);
        $portfolio = $price * $ownPct / 100 + $buyCosts;
        $value = $price;
        for ($y = 1; $y <= $horizon; $y++) {
            $rent = $monthlyRent * 12 * (1 + $rentGrowthPct / 100) ** ($y - 1);
            $outlay = ($y <= $loanYears ? $pmt * 12 : 0) + $value * $ownerCostPct / 100;
            $portfolio = $portfolio * (1 + $investReturnPct / 100) + ($outlay - $rent);
            $value *= 1 + $priceGrowthPct / 100;
        }
        $balance = self::remainingBalance($loan, $ratePct, $loanYears, $horizon * 12);
        $buy = $value - $balance;

        return ['buy' => round($buy), 'rent' => round($portfolio), 'diff' => round($buy - $portfolio), 'payment' => round($pmt, 2), 'balance' => round($balance), 'value' => round($value)];
    }

    /**
     * Annual cash-flow of a rented flat incl. income tax under § 9 ZDP: flat-rate expenses 30 % (max 600 000 Kč, cannot
     * be combined with other costs or interest) vs actual expenses (costs + mortgage interest + tax depreciation).
     *
     * @return array{income: float, flat: array, actual: array, better: string}
     */
    public static function rentalCashflow(float $monthlyRent, float $vacancyPct, float $annualCosts, float $annualDebtService, float $annualInterest, float $depreciation, float $taxPct = 15): array
    {
        $r = config('reality_rules.tax');
        $income = $monthlyRent * 12 * (1 - $vacancyPct / 100);
        $variant = function (float $expenses) use ($income, $taxPct, $annualCosts, $annualDebtService) {
            $base = max(0.0, $income - $expenses);
            $tax = round($base * $taxPct / 100);

            return ['expenses' => round($expenses), 'base' => round($base), 'tax' => $tax, 'cashflow' => round($income - $annualCosts - $annualDebtService - $tax)];
        };
        $flat = $variant(min($income * $r['flat_expense_pct'] / 100, $r['flat_expense_cap']));
        $actual = $variant($annualCosts + $annualInterest + $depreciation);

        return ['income' => round($income), 'flat' => $flat, 'actual' => $actual, 'better' => $actual['tax'] < $flat['tax'] ? 'actual' : 'flat'];
    }

    /** First-year mortgage interest (sum of 12 interest parts). */
    public static function firstYearInterest(float $principal, float $ratePct, int $years): float
    {
        $paid = self::mortgagePayment($principal, $ratePct, $years) * min(12, $years * 12);

        return max(0.0, $paid - ($principal - self::remainingBalance($principal, $ratePct, $years, 12)));
    }

    /**
     * One-off costs of buying. Daň z nabytí nemovitých věcí was abolished (zákon č. 386/2020 Sb.); vklad 2 000 Kč per návrh
     * (kupní smlouva + zástavní právo when financed by a mortgage).
     *
     * @return array{items: array<string, float>, total: float}
     */
    public static function buyingCosts(float $price, bool $mortgage = true, float $valuation = 5000, float $escrowPct = 0.3, float $escrowMin = 10000, float $lawyer = 15000, float $brokerPct = 0, float $other = 0): array
    {
        $fee = config('reality_rules.cadastre.deposit_fee');
        $items = [
            'Poplatek za vklad vlastnického práva' => $fee,
            'Poplatek za vklad zástavního práva' => $mortgage ? $fee : 0,
            'Odhad nemovitosti' => $mortgage ? $valuation : 0,
            'Úschova kupní ceny (advokát/notář/banka)' => round(max($escrowMin, $price * $escrowPct / 100)),
            'Sepis smlouvy a návrh na vklad' => $lawyer,
            'Provize makléře (pokud ji platí kupující)' => round($price * $brokerPct / 100),
            'Ostatní (ověření podpisů, výpisy, stěhování)' => $other,
        ];

        return ['items' => $items, 'total' => array_sum($items)];
    }

    /**
     * Income tax on the sale of a flat/house by an individual (§ 4 odst. 1 písm. a), b) and § 10 ZDP).
     * Exempt: bydliště ≥ 2 years immediately before sale (only RD/jednotka), or ownership longer than 10 years
     * (5 years if acquired before 1. 1. 2021), or proceeds used for own housing (needs proof, user flag).
     *
     * @return array{exempt: bool, reason: string, profit: float, tax: float, test_years: int, exempt_from: string}
     */
    public static function saleTax(float $salePrice, float $purchasePrice, float $costs, string $acquired, string $sold, float $residenceYears = 0, bool $isHome = true, bool $reinvest = false, float $otherIncome = 0): array
    {
        $r = config('reality_rules.tax');
        $acq = \Carbon\CarbonImmutable::parse($acquired);
        $sale = \Carbon\CarbonImmutable::parse($sold);
        $testYears = $acq->lt('2021-01-01') ? 5 : 10;
        $exemptFrom = $acq->addYears($testYears)->addDay();
        $profit = max(0.0, $salePrice - $purchasePrice - $costs);
        $reason = match (true) {
            $sale->gte($exemptFrom) => "časový test {$testYears} let splněn",
            $isHome && $residenceYears >= 2 => 'bydliště alespoň 2 roky bezprostředně před prodejem',
            $reinvest => 'prostředky použity na vlastní bytovou potřebu',
            default => '',
        };
        $tax = 0.0;
        if ($reason === '') {
            $limit = max(0.0, $r['high_rate_threshold'] - $otherIncome);
            $tax = round(min($profit, $limit) * $r['rate'] / 100 + max(0.0, $profit - $limit) * $r['rate_high'] / 100);
        }

        return ['exempt' => $reason !== '', 'reason' => $reason, 'profit' => round($profit), 'tax' => $tax, 'test_years' => $testYears, 'exempt_from' => $exemptFrom->toDateString()];
    }

    /** Max. jistota (kauce) by § 2254 OZ: together with contractual penalties at most 3× monthly rent (without services). */
    public static function maxDeposit(float $monthlyRent): float
    {
        return $monthlyRent * config('reality_rules.lease.deposit_max_months');
    }

    /**
     * End of a notice period: runs from the first day of the month after delivery (§ 2286 OZ); 3 months for flats.
     *
     * @return array{start: string, end: string}
     */
    public static function noticeEnd(string $delivered, int $months = 3): array
    {
        $start = \Carbon\CarbonImmutable::parse($delivered)->startOfMonth()->addMonth();

        return ['start' => $start->toDateString(), 'end' => $start->addMonths($months)->subDay()->toDateString()];
    }
}
