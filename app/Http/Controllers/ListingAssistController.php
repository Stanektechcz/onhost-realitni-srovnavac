<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Services\Benchmarks\CsuBenchmarks;
use App\Services\Geo\RuianGeocoder;
use App\Services\MarketStats;
use App\Support\DescriptionGenerator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** JSON helpers for the listing wizard: address lookup, price estimate, description draft. */
class ListingAssistController extends Controller
{
    public function addressSuggest(Request $request, RuianGeocoder $geo)
    {
        $this->authorize('create', Listing::class);
        $q = $request->validate(['q' => ['required', 'string', 'min:3', 'max:100']])['q'];

        return response()->json(['items' => $geo->suggest($q), 'attribution' => RuianGeocoder::ATTRIBUTION, 'terms' => RuianGeocoder::TERMS_URL]);
    }

    public function addressResolve(Request $request, RuianGeocoder $geo)
    {
        $this->authorize('create', Listing::class);
        $d = $request->validate(['label' => ['required', 'string', 'max:150'], 'key' => ['required', 'string', 'max:20']]);
        $hit = $geo->resolve($d['label'], $d['key']);

        return $hit ? response()->json($hit) : response()->json(['message' => 'Adresu se nepodařilo dohledat.'], 404);
    }

    public function priceSuggest(Request $request, MarketStats $stats)
    {
        $this->authorize('create', Listing::class);
        $d = $request->validate([
            'offer_type' => ['required', Rule::in(array_keys(config('reality.offer_types')))],
            'city' => ['nullable', 'string', 'max:100'], 'district' => ['nullable', 'string', 'max:100'],
            'disposition' => ['nullable', Rule::in(config('reality.dispositions'))],
            'area_m2' => ['required', 'numeric', 'min:1', 'max:100000'],
            'property_type' => ['nullable', Rule::in(array_keys(config('reality.property_types')))],
        ]);
        $est = $stats->estimate($d['offer_type'], $d['district'] ?? null, $d['city'] ?? null, $d['disposition'] ?? null, (float) $d['area_m2']);
        $out = ['estimate' => $est, 'label' => 'Orientační odhad', 'source' => null, 'benchmark' => null];
        if ($est['enough']) {
            $out['source'] = "Medián a kvartily ceny za m² z {$est['n']} srovnatelných nabídek na portálu za 24 měsíců ({$est['scope']}). Nejde o znalecký posudek.";
        }
        if ($d['offer_type'] === 'sale' && in_array($d['property_type'] ?? 'flat', ['flat', 'house'], true)) {
            $indicator = ($d['property_type'] ?? 'flat') === 'house' ? 'Průměrná kupní cena – rodinné domy' : 'Průměrná kupní cena – byty';
            $area = CsuBenchmarks::areaFor($d['city'] ?? null) ?? 'Česko';
            $b = CsuBenchmarks::forAreas([$area])->firstWhere('indicator', $indicator);
            if ($b) {
                $out['benchmark'] = [
                    'text' => "{$b->indicator}, {$b->area} ({$b->period}): ".number_format((float) $b->value, 0, ',', ' ')." {$b->unit} (≈ ".number_format((float) $b->value * (float) $d['area_m2'], 0, ',', ' ').' Kč pro vaši plochu)',
                    'source' => 'ČSÚ (realizované kupní ceny z dat ČÚZK)', 'url' => $b->source_url,
                ];
            }
        }

        return response()->json($out);
    }

    public function describe(Request $request, Listing $listing)
    {
        $this->authorize('update', $listing);
        $f = $request->validate([
            'floor' => ['nullable', 'integer', 'between:-3,100'], 'penb' => ['nullable', Rule::in(config('reality.penb'))],
            'furnishing' => ['nullable', Rule::in(array_keys(config('reality.furnishing')))], 'available_from' => ['nullable', 'date'],
            'has_elevator' => ['nullable', 'boolean'], 'has_balcony' => ['nullable', 'boolean'], 'has_parking' => ['nullable', 'boolean'], 'pets_allowed' => ['nullable', 'boolean'],
            'locality' => ['nullable', 'string', 'max:500'],
        ]);
        $base = $listing->only(['offer_type', 'property_type', 'disposition', 'area_m2', 'street', 'district', 'city']);

        return response()->json(['text' => DescriptionGenerator::make($base + $f)]);
    }
}
