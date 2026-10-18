<?php

namespace App\Services;

use App\Models\Listing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Aggregated market analytics from listing data.
 * Imported listings are used ONLY in aggregates (medians, ranges, counts) — never exposed individually.
 */
class MarketStats
{
    public const MIN_SAMPLE = 3;

    public const CACHE_KEY = 'market:dataset:v2';

    /** Raw aggregate-safe rows (no ids, slugs, texts). */
    public function dataset(): Collection
    {
        return collect(Cache::remember(self::CACHE_KEY, now()->addMinutes(30), function () {
            return Listing::query()
                ->whereIn('source', ['internal', 'imported'])
                ->whereIn('status', ['active', 'expired'])
                ->whereNotNull('published_at')->whereNotNull('price_halere')->where('area_m2', '>', 0)
                ->where('published_at', '>=', now()->subMonths(24))
                ->get(['offer_type', 'property_type', 'disposition', 'area_m2', 'price_halere', 'city', 'district', 'lat', 'lng', 'published_at'])
                ->map(fn (Listing $l) => [
                    'offer' => $l->offer_type, 'type' => $l->property_type, 'disp' => $l->disposition,
                    'area' => (float) $l->area_m2, 'price' => intdiv($l->price_halere, 100),
                    'ppm2' => $l->price_halere / 100 / $l->area_m2,
                    'city' => $l->city ?: 'Neuvedeno', 'district' => $l->district ?: ($l->city ?: 'Neuvedeno'),
                    'lat' => $l->lat, 'lng' => $l->lng, 'month' => $l->published_at->format('Y-m'),
                ])->values()->all();
        }));
    }

    public static function percentile(array $values, float $p): ?float
    {
        if (! $values) {
            return null;
        }
        sort($values);
        $idx = ($p / 100) * (count($values) - 1);
        $lo = (int) floor($idx);
        $hi = (int) ceil($idx);

        return $values[$lo] + ($values[$hi] - $values[$lo]) * ($idx - $lo);
    }

    /** {n, p25, median, p75, min, max} or {n, enough:false}. */
    public static function summary(iterable $values): array
    {
        $v = array_values(array_filter(is_array($values) ? $values : collect($values)->all(), fn ($x) => $x !== null));
        $n = count($v);
        if ($n < self::MIN_SAMPLE) {
            return ['n' => $n, 'enough' => false];
        }

        return ['n' => $n, 'enough' => true, 'p25' => self::percentile($v, 25), 'median' => self::percentile($v, 50),
            'p75' => self::percentile($v, 75), 'min' => min($v), 'max' => max($v)];
    }

    public static function slug(string $district): string { return Str::slug($district); }

    public function districts(): Collection
    {
        return $this->dataset()->groupBy('district')->map(function (Collection $rows, string $name) {
            $geo = $rows->filter(fn ($r) => $r['lat'] && $r['lng']);

            return [
                'name' => $name, 'slug' => self::slug($name), 'city' => $rows->first()['city'], 'count' => $rows->count(),
                'rent' => self::summary($rows->where('offer', 'rent')->pluck('ppm2')),
                'sale' => self::summary($rows->where('offer', 'sale')->pluck('ppm2')),
                'rent_price' => self::summary($rows->where('offer', 'rent')->pluck('price')),
                'lat' => $geo->isNotEmpty() ? $geo->avg('lat') : null, 'lng' => $geo->isNotEmpty() ? $geo->avg('lng') : null,
            ];
        })->sortByDesc('count')->values();
    }

    public function findDistrict(string $slug): ?array
    {
        return $this->districts()->firstWhere('slug', $slug);
    }

    public function districtDetail(string $name): array
    {
        $rows = $this->dataset()->where('district', $name);
        $byDisp = [];
        foreach (['rent', 'sale'] as $offer) {
            foreach ($rows->where('offer', $offer)->groupBy(fn ($r) => $r['disp'] ?: 'jiné') as $disp => $g) {
                $byDisp[$offer][$disp] = ['price' => self::summary($g->pluck('price')), 'ppm2' => self::summary($g->pluck('ppm2')), 'area' => round($g->avg('area'))];
            }
            if (isset($byDisp[$offer])) {
                uksort($byDisp[$offer], fn ($a, $b) => strnatcmp($a, $b));
            }
        }

        return [
            'byDisposition' => $byDisp,
            'histogram' => ['rent' => $this->histogram($rows->where('offer', 'rent')->pluck('ppm2')->all()), 'sale' => $this->histogram($rows->where('offer', 'sale')->pluck('ppm2')->all())],
            'trend' => ['rent' => $this->trend($rows->where('offer', 'rent')), 'sale' => $this->trend($rows->where('offer', 'sale'))],
        ];
    }

    /** Equal-width bins: [[from, to, count], ...]. */
    public function histogram(array $values, int $bins = 8): array
    {
        if (count($values) < self::MIN_SAMPLE) {
            return [];
        }
        $min = min($values);
        $max = max($values);
        if ($max - $min < 1) {
            return [[floor($min), ceil($max), count($values)]];
        }
        $w = ($max - $min) / $bins;
        $out = [];
        for ($i = 0; $i < $bins; $i++) {
            $out[$i] = [$min + $i * $w, $min + ($i + 1) * $w, 0];
        }
        foreach ($values as $v) {
            $out[min($bins - 1, (int) floor(($v - $min) / $w))][2]++;
        }

        return $out;
    }

    /** Monthly median price/m² for last 12 months: [Y-m => median|null]. */
    public function trend(Collection $rows): array
    {
        $out = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonths($i)->format('Y-m');
            $vals = $rows->where('month', $m)->pluck('ppm2')->all();
            $out[$m] = count($vals) ? self::percentile($vals, 50) : null;
        }

        return $out;
    }

    /** Points with price/m² for heatmap (coarsened to ~100 m to avoid pinpointing). */
    public function heatPoints(string $offer): array
    {
        return $this->dataset()->where('offer', $offer)->filter(fn ($r) => $r['lat'] && $r['lng'])
            ->map(fn ($r) => [round($r['lng'], 3), round($r['lat'], 3), (int) round($r['ppm2'])])->values()->all();
    }

    /** Compare listing price/m² with district median. */
    public function versusMarket(Listing $l): ?array
    {
        if (! $l->pricePerM2()) {
            return null;
        }
        $rows = $this->dataset()->where('offer', $l->offer_type)->where('district', $l->district ?: $l->city);
        $scope = $l->district ?: $l->city;
        $same = $rows->where('disp', $l->disposition);
        if ($same->count() >= self::MIN_SAMPLE) {
            $rows = $same;
            $scope .= ', '.$l->disposition;
        }
        $s = self::summary($rows->pluck('ppm2'));
        if (! $s['enough']) {
            return ['enough' => false, 'n' => $s['n']];
        }

        return $s + ['scope' => $scope, 'diff_pct' => (int) round(($l->pricePerM2() / $s['median'] - 1) * 100)];
    }

    /** Price estimate from comparable offers, widening the scope when data is thin. */
    public function estimate(string $offer, ?string $district, ?string $city, ?string $disposition, float $area): array
    {
        $base = $this->dataset()->where('offer', $offer);
        $tries = [];
        if ($district && $disposition) {
            $tries[] = [$district.', '.$disposition, $base->where('district', $district)->where('disp', $disposition)];
        }
        if ($district) {
            $tries[] = [$district, $base->where('district', $district)];
        }
        if ($city) {
            $tries[] = [$city, $base->where('city', $city)];
        }
        foreach ($tries as [$scope, $rows]) {
            $s = self::summary($rows->pluck('ppm2'));
            if ($s['enough'] && $area > 0) {
                return ['enough' => true, 'scope' => $scope, 'n' => $s['n'], 'ppm2' => $s['median'],
                    'low' => (int) round($s['p25'] * $area), 'mid' => (int) round($s['median'] * $area), 'high' => (int) round($s['p75'] * $area)];
            }
        }

        return ['enough' => false, 'n' => 0];
    }
}
