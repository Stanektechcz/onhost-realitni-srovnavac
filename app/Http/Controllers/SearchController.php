<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SearchController extends Controller
{
    public static function filterRules(): array
    {
        return [
            'offer_type' => ['nullable', Rule::in(array_keys(config('reality.offer_types')))],
            'property_type' => ['nullable', Rule::in(array_keys(config('reality.property_types')))],
            'disposition' => ['nullable', 'array'], 'disposition.*' => [Rule::in(config('reality.dispositions'))],
            'city' => ['nullable', 'string', 'max:100'],
            'price_min' => ['nullable', 'integer', 'min:0'], 'price_max' => ['nullable', 'integer', 'min:0'],
            'area_min' => ['nullable', 'numeric', 'min:0'], 'area_max' => ['nullable', 'numeric', 'min:0'],
            'penb' => ['nullable', 'array'], 'penb.*' => [Rule::in(config('reality.penb'))],
            'furnishing' => ['nullable', Rule::in(array_keys(config('reality.furnishing')))],
            'has_balcony' => ['nullable', 'boolean'], 'has_elevator' => ['nullable', 'boolean'],
            'has_parking' => ['nullable', 'boolean'], 'pets_allowed' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc', 'area_desc'])],
        ];
    }

    public function index(Request $request)
    {
        $filters = array_filter($request->validate(self::filterRules()), fn ($v) => $v !== null && $v !== '' && $v !== []);
        $listings = Listing::public()->filter($filters)->with('photos')
            ->paginate(config('reality.per_page'))->withQueryString();

        return view('search.index', compact('listings', 'filters'));
    }

    /** GeoJSON for the map — only public internal listings, only coarse fields. */
    public function map(Request $request)
    {
        $filters = array_filter($request->validate(self::filterRules()), fn ($v) => $v !== null && $v !== '' && $v !== []);
        $features = Listing::public()->filter($filters)->whereNotNull('lat')->whereNotNull('lng')->limit(500)
            ->get(['slug', 'title', 'offer_type', 'property_type', 'disposition', 'area_m2', 'price_halere', 'lat', 'lng', 'city', 'district'])
            ->map(fn (Listing $l) => [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [$l->lng, $l->lat]],
                'properties' => ['title' => $l->displayTitle(), 'price' => Listing::formatCzk($l->price_halere), 'url' => route('listings.show', $l)],
            ]);

        return response()->json(['type' => 'FeatureCollection', 'features' => $features])
            ->header('Cache-Control', 'public, max-age=60');
    }
}
