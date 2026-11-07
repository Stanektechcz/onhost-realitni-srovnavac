<?php

namespace App\Http\Controllers;

use App\Services\Geo\NearbyAmenities;
use Illuminate\Http\Request;

class AmenitiesController extends Controller
{
    public function __invoke(Request $request, NearbyAmenities $amenities)
    {
        $v = $request->validate(['lat' => ['required', 'numeric', 'between:-90,90'], 'lng' => ['required', 'numeric', 'between:-180,180']]);

        return response()->json(['pois' => $amenities->around((float) $v['lat'], (float) $v['lng'])])
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
