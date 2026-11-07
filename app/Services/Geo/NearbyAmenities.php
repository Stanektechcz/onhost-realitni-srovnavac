<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Nearby amenities (OpenStreetMap data via Overpass API), fetched server-side only and cached per
 * coordinate rounded to 3 decimals (~100 m) for 30 days. Failures yield an empty list (cached briefly).
 */
class NearbyAmenities
{
    public const RADIUS = 1000;

    public const CATS = [
        'transit' => 'node["public_transport"="platform"]',
        'school' => 'nwr["amenity"~"^(school|kindergarten)$"]',
        'shop' => 'nwr["shop"~"^(supermarket|convenience|bakery)$"]',
        'health' => 'nwr["amenity"~"^(doctors|pharmacy|clinic|hospital)$"]',
        'park' => 'nwr["leisure"~"^(park|playground)$"]',
    ];

    /** @return list<array{cat:string,name:string,lat:float,lng:float}> */
    public function around(float $lat, float $lng): array
    {
        $lat = round($lat, 3);
        $lng = round($lng, 3);
        $key = sprintf('poi:%.3f:%.3f', $lat, $lng);
        if (($hit = Cache::get($key)) !== null) {
            return $hit;
        }
        $pois = $this->fetch($lat, $lng);
        Cache::put($key, $pois ?? [], $pois === null ? now()->addMinutes(10) : now()->addDays(30));

        return $pois ?? [];
    }

    private function fetch(float $lat, float $lng): ?array
    {
        $parts = implode('', array_map(fn ($q) => $q.'(around:'.self::RADIUS.",{$lat},{$lng});", self::CATS));
        try {
            $res = Http::asForm()->timeout(25)->withUserAgent('reality-onhost/1.0')
                ->post(config('reality.overpass_url'), ['data' => "[out:json][timeout:20];({$parts});out center 400;"]);
        } catch (\Throwable) {
            return null;
        }
        if (! $res->successful() || ! is_array($res->json('elements'))) {
            return null;
        }
        $out = [];
        foreach ($res->json('elements') as $e) {
            $t = $e['tags'] ?? [];
            $pLat = $e['lat'] ?? $e['center']['lat'] ?? null;
            $pLng = $e['lon'] ?? $e['center']['lon'] ?? null;
            if ($pLat === null || $pLng === null) {
                continue;
            }
            $cat = isset($t['public_transport']) ? 'transit'
                : (preg_match('/school|kindergarten/', $t['amenity'] ?? '') ? 'school'
                : (isset($t['shop']) ? 'shop' : (isset($t['leisure']) ? 'park' : 'health')));
            $out[] = ['cat' => $cat, 'name' => mb_substr((string) ($t['name'] ?? ''), 0, 120), 'lat' => (float) $pLat, 'lng' => (float) $pLng];
        }

        return $out;
    }
}
