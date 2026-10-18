<?php

namespace App\Support;

use Carbon\Carbon;

/** Deterministic Czech listing description from structured fields (no external AI). */
class DescriptionGenerator
{
    private const TYPE = ['flat' => 'byt', 'house' => 'rodinný dům', 'land' => 'pozemek', 'commercial' => 'komerční prostor', 'room' => 'pokoj'];

    private const PENB = ['A' => 'mimořádně úsporná', 'B' => 'velmi úsporná', 'C' => 'úsporná', 'D' => 'méně úsporná', 'E' => 'nehospodárná', 'F' => 'velmi nehospodárná', 'G' => 'mimořádně nehospodárná'];

    public static function make(array $f): string
    {
        $offer = ($f['offer_type'] ?? 'rent') === 'sale' ? 'Prodej' : 'Pronájem';
        $type = self::TYPE[$f['property_type'] ?? 'flat'] ?? 'nemovitost';
        $head = $offer.' '.self::accusative($type);
        if (! empty($f['disposition']) && in_array($f['property_type'] ?? 'flat', ['flat', 'house'], true)) {
            $head .= ' '.$f['disposition'];
        }
        if (! empty($f['area_m2'])) {
            $head .= ' o ploše '.self::num($f['area_m2']).' m²';
        }
        $where = trim(implode(', ', array_filter([$f['street'] ?? null, $f['district'] ?? null, $f['city'] ?? null])));
        $s = [$head.($where ? ', '.$where : '').'.'];

        $p = [];
        if (isset($f['floor']) && $f['floor'] !== '' && $f['floor'] !== null) {
            $fl = (int) $f['floor'];
            $p[] = $fl === 0 ? 'nachází se v přízemí' : ($fl < 0 ? 'nachází se v suterénu' : "nachází se v {$fl}. patře");
        }
        if (! empty($f['has_elevator'])) $p[] = 'v domě je výtah';
        if ($p) $s[] = self::ucfirst(implode(', ', $p)).'.';

        $extra = [];
        if (! empty($f['has_balcony'])) $extra[] = 'balkon nebo terasa';
        if (! empty($f['has_parking'])) $extra[] = 'možnost parkování';
        if ($extra) $s[] = 'K dispozici je '.implode(' a ', $extra).'.';

        $s[] = match ($f['furnishing'] ?? null) {
            'full' => 'Nemovitost je plně vybavena.',
            'partial' => 'Nemovitost je částečně vybavena.',
            'none' => 'Nemovitost je nevybavená, můžete si ji zařídit podle sebe.',
            default => null,
        };
        $penb = $f['penb'] ?? null;
        if ($penb && isset(self::PENB[$penb])) {
            $s[] = "Energetická náročnost budovy: třída {$penb} – ".self::PENB[$penb].'.';
        } elseif ($penb === 'none') {
            $s[] = 'Průkaz energetické náročnosti budovy (PENB) zatím nebyl vydán.';
        }
        if (($f['offer_type'] ?? 'rent') === 'rent' && array_key_exists('pets_allowed', $f)) {
            $s[] = ! empty($f['pets_allowed']) ? 'Domácí mazlíčci jsou vítáni.' : null;
        }
        if (! empty($f['available_from'])) {
            try {
                $d = Carbon::parse($f['available_from']);
                $s[] = $d->isPast() ? 'Volné ihned.' : 'Volné od '.$d->format('j. n. Y').'.';
            } catch (\Throwable) {
            }
        }
        if (! empty($f['locality'])) {
            $s[] = rtrim(self::ucfirst(trim(strip_tags((string) $f['locality']))), '.').'.';
        }
        $s[] = 'Prohlídku rádi domluvíme – napište nám přes formulář u inzerátu.';

        return implode(' ', array_filter($s));
    }

    private static function accusative(string $t): string
    {
        return ['komerční prostor' => 'komerčního prostoru', 'rodinný dům' => 'rodinného domu', 'byt' => 'bytu', 'pozemek' => 'pozemku', 'pokoj' => 'pokoje'][$t] ?? $t;
    }

    private static function num($v): string
    {
        $v = (float) $v;

        return $v == floor($v) ? (string) (int) $v : str_replace('.', ',', (string) round($v, 1));
    }

    private static function ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
    }
}
