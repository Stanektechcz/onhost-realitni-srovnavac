<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Číselné řady dokladů per subjekt (kontrakt onhost-operator/1, pravidlo 4): číslo = <prefix>-<rok>-<pořadí>, kde prefix je
 * pole number_prefix subjektu (prázdné = odvozeno ze slugu) a pořadí se počítá zvlášť pro subjekt + rok + druh dokladu
 * v tabulce document_sequences. Přepnutí ECOSYSTEM_OPERATOR tak začne novou řadu a nikdy nekoliduje s čísly jiného subjektu.
 */
final class DocumentNumber
{
    /** Prefix řady subjektu: number_prefix, jinak slug velkými písmeny (adrian-stanek → ADRIAN-STANEK). */
    public static function prefix(?string $slug = null): string
    {
        $slug ??= Operator::slug();
        $own = trim((string) (Operator::resolve($slug)['number_prefix'] ?? ''));
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9-]+/', '', $own !== '' ? $own : $slug) ?? '');

        return $prefix !== '' ? $prefix : 'DOC';
    }

    /** Přidělí další číslo řady aktivního (nebo zadaného) subjektu; bezpečné při souběhu (zámek řádku v transakci). */
    public static function next(string $series = 'payment', ?string $slug = null, ?\DateTimeInterface $at = null): string
    {
        $slug ??= Operator::slug();
        $year = (int) ($at ?? now())->format('Y');
        $key = ['operator_slug' => $slug, 'series' => $series, 'year' => $year];

        $seq = DB::transaction(function () use ($key): int {
            DB::table('document_sequences')->insertOrIgnore($key + ['last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $row = DB::table('document_sequences')->where($key)->lockForUpdate()->first();
            $next = (int) $row->last_number + 1;
            DB::table('document_sequences')->where($key)->update(['last_number' => $next, 'updated_at' => now()]);

            return $next;
        });

        return sprintf('%s-%d-%05d', self::prefix($slug), $year, $seq);
    }
}
