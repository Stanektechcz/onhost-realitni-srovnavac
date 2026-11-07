<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Address autocomplete + geocoding over RÚIAN via the keyless ČÚZK ArcGIS GeocodeSOE service.
 * Terms: ČÚZK network services are free for commercial and non-commercial use; attribution
 * "ČÚZK – on-line" with a link to the terms is required (RÚIAN data: CC BY 4.0).
 */
class RuianGeocoder
{
    public const BASE = 'https://ags.cuzk.gov.cz/arcgis/rest/services/RUIAN/Vyhledavaci_sluzba_nad_daty_RUIAN/MapServer/exts/GeocodeSOE';

    public const ATTRIBUTION = 'Adresy: RÚIAN, ČÚZK – on-line';

    public const TERMS_URL = 'https://cuzk.gov.cz/Predpisy/Podminky-poskytovani-sitovych-sluzeb-CUZK/Podminky-poskytovani-sitovych-sluzeb-CUZK.aspx';

    /** @return list<array{label:string, key:string}> */
    public function suggest(string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) < 3) {
            return [];
        }

        return Cache::remember('ruian:s:'.md5(mb_strtolower($text)), now()->addDay(), function () use ($text) {
            try {
                $res = Http::timeout(5)->acceptJson()->get(self::BASE.'/suggest', ['text' => mb_substr($text, 0, 100), 'maxSuggestions' => 8, 'f' => 'json']);
                if (! $res->ok()) {
                    return [];
                }
            } catch (\Throwable $e) {
                Log::warning('ruian.suggest_failed', ['error' => class_basename($e)]);

                return [];
            }

            return collect($res->json('suggestions', []))
                ->filter(fn ($s) => is_string($s['text'] ?? null) && is_string($s['magicKey'] ?? null))
                ->filter(fn ($s) => in_array($s['type'] ?? '', ['AdresniMisto', 'Ulice', 'Obec', 'CastObce', 'Momc', 'ZakladniSidelniJednotka'], true))
                ->map(fn ($s) => ['label' => $s['text'], 'key' => $s['magicKey']])->values()->all();
        });
    }

    /** Resolve one suggestion to listing fields. @return array{street:?string,district:?string,city:?string,lat:?float,lng:?float,label:string}|null */
    public function resolve(string $label, string $key): ?array
    {
        if (! preg_match('/^\d{1,2}_\d{1,12}$/', $key)) {
            return null;
        }

        return Cache::remember('ruian:r:'.$key, now()->addWeek(), function () use ($label, $key) {
            try {
                $res = Http::timeout(5)->acceptJson()->get(self::BASE.'/findAddressCandidates', [
                    'SingleLine' => mb_substr($label, 0, 150), 'magicKey' => $key, 'outSR' => 4326, 'maxLocations' => 1, 'f' => 'json',
                ]);
                $c = $res->ok() ? $res->json('candidates.0') : null;
            } catch (\Throwable $e) {
                Log::warning('ruian.resolve_failed', ['error' => class_basename($e)]);

                return null;
            }
            if (! is_array($c) || ! isset($c['location']['x'], $c['location']['y'])) {
                return null;
            }

            return self::parse((string) ($c['address'] ?? $label)) + [
                'lat' => round((float) $c['location']['y'], 7), 'lng' => round((float) $c['location']['x'], 7), 'label' => (string) ($c['address'] ?? $label),
            ];
        });
    }

    /**
     * "Sokolovská 146/150a, Libeň, 18000 Praha 8" -> street, district (část obce), city.
     * "Sokolovská, Bohumín" (street only) / "Karlín (Praha)" also handled.
     */
    public static function parse(string $address): array
    {
        if (preg_match('/^(.+?) \((.+)\)$/u', $address, $m)) {
            return ['street' => null, 'district' => $m[1], 'city' => self::city($m[2])];
        }
        $parts = array_map('trim', explode(',', $address));
        $last = preg_replace('/^\d{3} ?\d{2}\s+/', '', array_pop($parts));
        $street = $parts ? array_shift($parts) : null;
        $district = $parts ? array_shift($parts) : null;
        $city = self::city($last);
        if (! $district && $city !== $last) {
            $district = $last; // e.g. "Praha 8"
        }

        return ['street' => $street, 'district' => $district, 'city' => $city];
    }

    private static function city(string $obec): string
    {
        return preg_replace('/^(Praha) \d+$/u', '$1', $obec);
    }
}
