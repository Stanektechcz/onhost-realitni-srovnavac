<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Services\MarketStats;
use Illuminate\Http\Request;

class InsightsController extends Controller
{
    public function index(Request $request, MarketStats $stats)
    {
        $districts = $stats->districts();
        $compare = collect((array) $request->query('c', []))->filter(fn ($s) => is_string($s))->take(4)
            ->map(fn ($s) => $districts->firstWhere('slug', $s))->filter()->values();

        return view('insights.index', ['districts' => $districts, 'compare' => $compare, 'total' => $stats->dataset()->count()]);
    }

    public function show(string $slug, MarketStats $stats)
    {
        $district = $stats->findDistrict($slug);
        abort_unless($district, 404);
        $detail = $stats->districtDetail($district['name']);
        // Only public internal listings are shown individually.
        $listings = Listing::public()->where(fn ($q) => $q->where('district', $district['name'])->orWhere(fn ($w) => $w->whereNull('district')->where('city', $district['name'])))
            ->with('photos')->latest('published_at')->limit(6)->get();

        return view('insights.show', compact('district', 'detail', 'listings'));
    }

    /** Coarsened price/m² points for the heatmap (aggregate use only). */
    public function heat(Request $request, MarketStats $stats)
    {
        $offer = $request->query('offer') === 'sale' ? 'sale' : 'rent';

        return response()->json([
            'points' => $stats->heatPoints($offer),
            'districts' => $stats->districts()->map(fn ($d) => [
                'name' => $d['name'], 'url' => route('insights.show', $d['slug']), 'lat' => $d['lat'], 'lng' => $d['lng'], 'count' => $d['count'],
                'median' => $d[$offer]['enough'] ? (int) round($d[$offer]['median']) : null,
            ])->filter(fn ($d) => $d['lat'])->values(),
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
