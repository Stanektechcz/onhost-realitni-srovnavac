<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\MarketBenchmark;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Locality tree (kraje, obce, Prague districts) for SEO landing pages, plus cached aggregates.
 * Individual listings are only ever loaded through Listing::public(); imported data feeds aggregates only.
 */
class Localities
{
    public const OFFERS = ['rent' => 'pronajem', 'sale' => 'prodej'];

    public function __construct(private MarketStats $stats) {}

    /** All localities keyed by slug: name, kind (kraj|obec|ctvrt), parent, cities, districts, area, search, neighbours. */
    public function all(): Collection
    {
        return collect(Cache::remember(MarketStats::derivedKey('localities:tree'), now()->addHour(), fn () => $this->build()));
    }

    public function find(string $slug): ?array
    {
        return $this->all()->get($slug);
    }

    private function build(): array
    {
        $cfg = config('reality_localities');
        $out = [];
        foreach ($cfg['kraje'] as $slug => $k) {
            $out[$slug] = ['slug' => $slug, 'name' => $k['name'], 'place' => $k['place'] ?? $k['name'], 'kind' => 'kraj', 'parent' => null,
                'cities' => $k['cities'], 'districts' => [], 'area' => $k['name'], 'search' => $k['cities'][0], 'neighbours' => $k['neighbours']];
        }
        foreach ($cfg['kraje'] as $kslug => $k) {
            if ($kslug === 'praha') {
                continue;
            }
            foreach ($k['cities'] as $city) {
                $out[Str::slug($city)] ??= $this->obec($city, $kslug, $k);
            }
        }
        $praha = $cfg['praha_districts'];
        foreach ($praha as $i => $name) {
            $out[Str::slug($name)] ??= $this->ctvrt($name, $praha, $i);
        }
        // Districts/cities that exist in our market data but not in the static tree.
        foreach ($this->stats->districts() as $d) {
            if (isset($out[$d['slug']]) || $d['slug'] === '' || $d['name'] === 'Neuvedeno') {
                continue;
            }
            $inPraha = str_starts_with(mb_strtolower((string) $d['city']), 'praha');
            $kraj = $inPraha ? 'praha' : (collect($cfg['kraje'])->search(fn ($k) => in_array($d['city'], $k['cities'], true)) ?: null);
            $out[$d['slug']] = ['slug' => $d['slug'], 'name' => $d['name'], 'place' => $d['name'], 'kind' => $inPraha ? 'ctvrt' : 'obec', 'parent' => $kraj,
                'cities' => $d['name'] === $d['city'] ? [$d['name']] : [], 'districts' => [$d['name']],
                'area' => $kraj ? $cfg['kraje'][$kraj]['name'] : null, 'search' => $d['name'], 'neighbours' => $kraj ? [$kraj] : []];
        }

        return $out;
    }

    private function obec(string $city, string $kslug, array $k): array
    {
        $siblings = array_values(array_filter($k['cities'], fn ($c) => $c !== $city));

        return ['slug' => Str::slug($city), 'name' => $city, 'place' => $city, 'kind' => 'obec', 'parent' => $kslug, 'cities' => [$city], 'districts' => [],
            'area' => $k['name'], 'search' => $city, 'neighbours' => array_merge([$kslug], array_map(fn ($c) => Str::slug($c), array_slice($siblings, 0, 5)))];
    }

    private function ctvrt(string $name, array $all, int $i): array
    {
        $near = array_filter([$all[$i - 1] ?? null, $all[$i + 1] ?? null, $all[$i - 2] ?? null, $all[$i + 2] ?? null]);

        return ['slug' => Str::slug($name), 'name' => $name, 'place' => $name, 'kind' => 'ctvrt', 'parent' => 'praha', 'cities' => [], 'districts' => [$name],
            'area' => 'Hlavní město Praha', 'search' => $name, 'neighbours' => array_merge(['praha'], array_map(fn ($n) => Str::slug($n), array_values($near)))];
    }

    /** Restrict a listing query to a locality. */
    public function scope(Builder $q, array $loc): Builder
    {
        return $q->where(function ($w) use ($loc) {
            if ($loc['cities']) {
                $w->whereIn('city', $loc['cities']);
            }
            if ($loc['districts']) {
                $w->orWhereIn('district', $loc['districts']);
            }
            if (! $loc['cities'] && ! $loc['districts']) {
                $w->whereRaw('1 = 0');
            }
        });
    }

    private static function matchesRow(array $loc, ?string $city, ?string $district): bool
    {
        return in_array($city, $loc['cities'], true) || in_array($district, $loc['districts'], true);
    }

    /**
     * Aggregates for every locality in one pass (one grouped SQL query + the cached market dataset).
     * slug => [public => [rent, sale], lastmod, rent, sale (Kč/m² summaries), rent_price, sale_price, n, benchmarks (bool)].
     */
    public function aggregates(): Collection
    {
        return collect(Cache::remember(MarketStats::derivedKey('localities:agg'), now()->addMinutes(30), function () {
            $all = $this->all();
            $public = Listing::public()->toBase()
                ->selectRaw('city, district, offer_type, count(*) as n, max(updated_at) as lastmod')
                ->groupBy('city', 'district', 'offer_type')->get();
            $rows = $this->stats->dataset()->all();
            $areas = MarketBenchmark::query()->distinct()->pluck('area')->flip();
            $bmLast = MarketBenchmark::max('updated_at');

            $out = [];
            foreach ($all as $slug => $loc) {
                $count = ['rent' => 0, 'sale' => 0];
                $last = null;
                foreach ($public as $p) {
                    if (self::matchesRow($loc, $p->city, $p->district) && isset($count[$p->offer_type])) {
                        $count[$p->offer_type] += (int) $p->n;
                        $last = max($last, $p->lastmod);
                    }
                }
                $mine = ['rent' => ['ppm2' => [], 'price' => []], 'sale' => ['ppm2' => [], 'price' => []]];
                foreach ($rows as $r) {
                    // dataset 'district' falls back to city, so match both columns.
                    if (isset($mine[$r['offer']]) && self::matchesRow($loc, $r['city'], $r['district'])) {
                        $mine[$r['offer']]['ppm2'][] = $r['ppm2'];
                        $mine[$r['offer']]['price'][] = $r['price'];
                    }
                }
                $out[$slug] = [
                    'public' => $count, 'lastmod' => $last ?? $bmLast,
                    'rent' => MarketStats::summary($mine['rent']['ppm2']), 'sale' => MarketStats::summary($mine['sale']['ppm2']),
                    'rent_price' => MarketStats::summary($mine['rent']['price']), 'sale_price' => MarketStats::summary($mine['sale']['price']),
                    'n' => count($mine['rent']['ppm2']) + count($mine['sale']['ppm2']),
                    'benchmarks' => $loc['area'] !== null && isset($areas[$loc['area']]),
                ];
            }

            return $out;
        }));
    }

    public function aggregate(string $slug): array
    {
        return $this->aggregates()->get($slug);
    }

    /** Locality page has content: live listings, own market data or official benchmarks. */
    public static function hasContent(array $agg, ?string $offer = null): bool
    {
        if ($offer) {
            return $agg['public'][$offer] > 0 || $agg[$offer]['enough'];
        }

        return array_sum($agg['public']) > 0 || $agg['n'] > 0 || $agg['benchmarks'];
    }

    public static function offerFromSegment(string $segment): ?string
    {
        return array_search($segment, self::OFFERS, true) ?: null;
    }
}
