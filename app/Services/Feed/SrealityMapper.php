<?php

namespace App\Services\Feed;

/**
 * Maps Sreality import `advert_data` (codebooks of the Sreality XML-RPC import, v3.0.0) to the
 * feed row understood by AgencyListingService. Returns null fields where Sreality gives no value;
 * unsupported categories map to invalid values so validation reports status 452.
 */
final class SrealityMapper
{
    /** advert_subtype codes for flats (Sreality codebook) → disposition */
    private const DISPOSITIONS = [
        2 => '1+kk', 3 => '1+1', 4 => '2+kk', 5 => '2+1', 6 => '3+kk', 7 => '3+1', 8 => '4+kk', 9 => '4+1',
        10 => '5+kk', 11 => '5+1', 12 => '6+', 16 => 'atyp',
    ];

    private const PENB = [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G'];

    public static function toFeedRow(array $a, string $rkid): array
    {
        $function = (int) ($a['advert_function'] ?? 0);
        $type = (int) ($a['advert_type'] ?? 0);
        $subtype = (int) ($a['advert_subtype'] ?? 0);

        $propertyType = match (true) {
            $subtype === 47 => 'room',
            $type === 1 => 'flat',
            $type === 2 => 'house',
            $type === 3 => 'land',
            $type === 4 => 'commercial',
            default => 'unsupported',
        };
        $street = trim(implode(' ', array_filter([
            $a['locality_street'] ?? null,
            trim(implode('/', array_filter([$a['locality_cp'] ?? null, $a['locality_co'] ?? null], fn ($v) => $v !== null && $v !== ''))),
        ], fn ($v) => $v !== null && $v !== '')));
        $yesNo = fn ($k) => isset($a[$k]) ? in_array((string) $a[$k], ['1', 'true'], true) || ($a[$k] === true) : null;
        $hasArea = fn ($k) => isset($a[$k]) && (is_bool($a[$k]) ? $a[$k] : (float) $a[$k] > 0);
        $penbCode = (int) ($a['energy_efficiency_rating'] ?? 0);

        return array_filter([
            'external_id' => $rkid,
            'title' => null,
            'description' => isset($a['description']) ? (string) $a['description'] : null,
            'offer_type' => match ($function) { 1 => 'sale', 2 => 'rent', default => 'unsupported' },
            'property_type' => $propertyType,
            'disposition' => $propertyType === 'flat' ? (self::DISPOSITIONS[$subtype] ?? null) : null,
            'area_m2' => $a['usable_area'] ?? $a['floor_area'] ?? $a['estate_area'] ?? null,
            // only CZK (advert_price_currency 1) is accepted
            'price_czk' => ((int) ($a['advert_price_currency'] ?? 1)) === 1 ? ($a['advert_price'] ?? null) : 'unsupported-currency',
            'city' => $a['locality_city'] ?? null,
            'district' => $a['locality_citypart'] ?? null,
            'street' => $street !== '' ? $street : null,
            'lat' => $a['locality_latitude'] ?? null,
            'lng' => $a['locality_longitude'] ?? null,
            'penb' => self::PENB[$penbCode] ?? 'none',
            'furnishing' => match ((int) ($a['furnished'] ?? 0)) { 1 => 'full', 2 => 'none', 3 => 'partial', default => null },
            'floor' => isset($a['floor_number']) ? (int) $a['floor_number'] : null,
            'has_elevator' => $yesNo('elevator'),
            'has_balcony' => $hasArea('balcony') || $hasArea('balcony_area') || $hasArea('loggia') || $hasArea('terrace') ?: null,
            'has_parking' => $hasArea('parking_lots') || $hasArea('garage') ?: null,
            'available_from' => isset($a['ready_date']) ? self::date((string) $a['ready_date']) : null,
        ], fn ($v) => $v !== null);
    }

    private static function date(string $v): ?string
    {
        // XML-RPC dateTime.iso8601 (20261101T00:00:00) or YYYY-MM-DD
        if (preg_match('/^(\d{4})-?(\d{2})-?(\d{2})/', $v, $m)) {
            return "$m[1]-$m[2]-$m[3]";
        }

        return null;
    }
}
