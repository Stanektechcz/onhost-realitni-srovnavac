<?php

namespace App\Services\Benchmarks;

use App\Models\MarketBenchmark;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Official price benchmarks from the Czech Statistical Office (DataStat open data API).
 * Licence per NKOD (data.gov.cz): no copyright work, no protected database, no sui generis
 * right — free reuse; we still cite the source.
 */
class CsuBenchmarks
{
    public const LICENSE = 'Otevřená data ČSÚ – volné užití (dle NKOD: neobsahuje autorská díla, není chráněnou databází); zdroj: ČSÚ';

    public const CSV_PATH = 'database/data/market_benchmarks_csu.csv';

    /** výběr => [label, indicator builder] */
    public const SOURCES = [
        'CEN0402T02' => 'Průměrné kupní ceny nemovitostí (ČSÚ z dat ČÚZK)',
        'CEN0401T01' => 'Indexy cen nemovitostí (ČSÚ z dat ČÚZK)',
    ];

    public const COLUMNS = ['source', 'source_url', 'license', 'area', 'period', 'indicator', 'value', 'unit'];

    public static function url(string $code): string
    {
        return "https://data.csu.gov.cz/api/dotaz/v1/data/vybery/{$code}?format=CSV";
    }

    /** Download and normalise all configured sources. */
    public function fetch(): Collection
    {
        $rows = collect();
        foreach (array_keys(self::SOURCES) as $code) {
            $res = Http::timeout(60)->retry(2, 500)->accept('text/csv')->get(self::url($code));
            $res->throw();
            $rows = $rows->merge($this->parse($code, $res->body()));
        }

        return $rows->values();
    }

    public function parse(string $code, string $csv): Collection
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));
        $head = str_getcsv(array_shift($lines), ',', '"', '');
        $out = collect();
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $r = array_combine($head, array_pad(str_getcsv($line, ',', '"', ''), count($head), ''));
            if (! is_numeric($r['Hodnota'] ?? null)) {
                continue;
            }
            $area = ($r['Území-Okres'] ?? '') ?: (($r['Území-Kraj'] ?? '') ?: ($r['Území-Stát'] ?? ''));
            $kind = $r['Druh nemovitosti'] ?? '';
            if ($code === 'CEN0402T02') {
                $indicator = 'Průměrná kupní cena – '.mb_strtolower(trim(preg_replace('/\[.*\]/', '', $kind)));
                $unit = 'Kč/m²';
            } else {
                $indicator = 'Index cen – '.mb_strtolower($kind).' ('.($r['Typ údaje'] ?? '').')';
                $unit = '%';
            }
            $out->push([
                'source' => 'CSU:'.$code,
                'source_url' => self::url($code),
                'license' => self::LICENSE,
                'area' => $area,
                'period' => (string) ($r['Roky'] ?? ''),
                'indicator' => $indicator,
                'value' => round((float) $r['Hodnota'], 2),
                'unit' => $unit,
            ]);
        }

        return $out;
    }

    public function store(iterable $rows): int
    {
        $n = 0;
        foreach (collect($rows)->chunk(200) as $chunk) {
            $now = now();
            MarketBenchmark::upsert(
                $chunk->map(fn ($r) => $r + ['created_at' => $now, 'updated_at' => $now])->values()->all(),
                ['source', 'area', 'period', 'indicator'],
                ['source_url', 'license', 'value', 'unit', 'updated_at'],
            );
            $n += $chunk->count();
        }
        \App\Services\MarketStats::flush();

        return $n;
    }

    public function writeCsv(Collection $rows, string $path): void
    {
        $fh = fopen($path, 'w');
        fwrite($fh, "# Zdroj: Český statistický úřad, DataStat (data.csu.gov.cz), výběry ".implode(', ', array_keys(self::SOURCES)).". Staženo ".now()->toDateString().". ".self::LICENSE."\n");
        fputcsv($fh, self::COLUMNS, ',', '"', '');
        foreach ($rows as $r) {
            fputcsv($fh, array_map(fn ($c) => $r[$c], self::COLUMNS), ',', '"', '');
        }
        fclose($fh);
    }

    public function readCsv(string $path): Collection
    {
        $out = collect();
        $fh = fopen($path, 'r');
        $head = null;
        while (($line = fgets($fh)) !== false) {
            if (str_starts_with($line, '#') || trim($line) === '') {
                continue;
            }
            $cells = str_getcsv(rtrim($line, "\r\n"), ',', '"', '');
            if (! $head) {
                $head = $cells;

                continue;
            }
            $out->push(array_combine($head, $cells));
        }
        fclose($fh);

        return $out;
    }

    /** Benchmarks for display: latest period per indicator for the given areas. */
    public static function forAreas(array $areas): Collection
    {
        return MarketBenchmark::whereIn('area', $areas)->orderBy('area')->orderBy('indicator')->orderByDesc('period')->get()
            ->groupBy(fn ($b) => $b->area.'|'.$b->indicator)
            ->map(function ($g) {
                $latest = $g->first();
                $prev = $g->firstWhere('period', (string) ((int) $latest->period - 1));
                $latest->change = $prev && $prev->value ? round(($latest->value / $prev->value - 1) * 100, 1) : null;

                return $latest;
            })->values()->sortBy(fn ($b) => array_search($b->area, $areas))->values();
    }

    /** Map our district/city to an official area name. */
    public static function areaFor(?string $city): ?string
    {
        return $city && str_starts_with(mb_strtolower($city), 'praha') ? 'Hlavní město Praha' : null;
    }
}
