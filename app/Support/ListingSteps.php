<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class ListingSteps
{
    public const STEPS = [1 => 'Základní údaje', 2 => 'Lokalita', 3 => 'Parametry a PENB', 4 => 'Fotografie', 5 => 'Kontrola a odeslání'];

    public static function rules(int $step, array $input = []): array
    {
        return match ($step) {
            1 => [
                'offer_type' => ['required', Rule::in(array_keys(config('reality.offer_types')))],
                'property_type' => ['required', Rule::in(array_keys(config('reality.property_types')))],
                'disposition' => [Rule::requiredIf(in_array($input['property_type'] ?? null, ['flat', 'house'], true)), 'nullable', Rule::in(config('reality.dispositions'))],
                'area_m2' => ['required', 'numeric', 'min:1', 'max:100000'],
                'price_czk' => ['required', 'integer', 'min:1', 'max:2000000000'],
                'fees_czk' => ['nullable', 'integer', 'min:0', 'max:1000000'],
                'deposit_czk' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            ],
            2 => [
                'city' => ['required', 'string', 'max:100'],
                'district' => ['nullable', 'string', 'max:100'],
                'street' => ['nullable', 'string', 'max:150'],
                'lat' => ['nullable', 'numeric', 'between:48.5,51.1'],
                'lng' => ['nullable', 'numeric', 'between:12.0,18.9'],
            ],
            3 => [
                'penb' => ['required', Rule::in(config('reality.penb'))],
                'furnishing' => ['nullable', Rule::in(array_keys(config('reality.furnishing')))],
                'floor' => ['nullable', 'integer', 'between:-3,100'],
                'available_from' => ['nullable', 'date'],
                'has_elevator' => ['boolean'], 'has_balcony' => ['boolean'], 'has_parking' => ['boolean'], 'pets_allowed' => ['boolean'],
                'title' => ['nullable', 'string', 'max:150'],
                'description' => ['required', 'string', 'min:30', 'max:5000'],
            ],
            default => [],
        };
    }

    /** Map validated form data to model attributes (CZK -> haléře). */
    public static function toAttributes(int $step, array $data): array
    {
        if ($step === 1) {
            foreach (['price' => 'price_czk', 'fees' => 'fees_czk', 'deposit' => 'deposit_czk'] as $col => $key) {
                $data[$col.'_halere'] = isset($data[$key]) && $data[$key] !== '' ? (int) $data[$key] * 100 : null;
                unset($data[$key]);
            }
        }
        if ($step === 3) {
            foreach (['has_elevator', 'has_balcony', 'has_parking', 'pets_allowed'] as $f) {
                $data[$f] = (bool) ($data[$f] ?? false);
            }
        }

        return $data;
    }

    /** Is the listing complete enough to be submitted? Returns list of missing step numbers. */
    public static function missing(\App\Models\Listing $l): array
    {
        $missing = [];
        if (! $l->offer_type || ! $l->property_type || ! $l->price_halere || ! $l->area_m2) $missing[] = 1;
        if (! $l->city) $missing[] = 2;
        if (! $l->penb || ! $l->description) $missing[] = 3;

        return $missing;
    }
}
