<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Services\Benchmarks\CsuBenchmarks;
use App\Services\Localities;
use App\Services\MarketStats;
use Illuminate\Http\Request;

class InsightsController extends Controller
{
    public function index(Request $request, MarketStats $stats)
    {
        $districts = $stats->districts();
        $compare = collect((array) $request->query('c', []))->filter(fn ($s) => is_string($s))->take(4)
            ->map(fn ($s) => $districts->firstWhere('slug', $s))->filter()->values();

        return view('insights.index', ['districts' => $districts, 'compare' => $compare, 'total' => $stats->dataset()->count(), 'benchmarks' => CsuBenchmarks::forAreas(['Hlavní město Praha', 'Česko'])]);
    }

    public function show(string $slug, MarketStats $stats, Localities $localities)
    {
        return $this->landing($slug, null, $stats, $localities);
    }

    /** Offer type × locality combination page (/pronajem/{slug}, /prodej/{slug}). */
    public function offer(Request $request, string $slug, MarketStats $stats, Localities $localities)
    {
        $offer = Localities::offerFromSegment(explode('/', trim($request->path(), '/'))[0]);
        abort_unless($offer, 404);

        return $this->landing($slug, $offer, $stats, $localities);
    }

    private function landing(string $slug, ?string $offer, MarketStats $stats, Localities $localities)
    {
        $loc = $localities->find($slug);
        abort_unless($loc, 404);
        $agg = $localities->aggregate($slug);
        $all = $localities->all();

        // Only public internal listings are shown individually.
        $listings = $localities->scope(Listing::public(), $loc)->when($offer, fn ($q) => $q->where('offer_type', $offer))
            ->with('photos')->latest('published_at')->limit(6)->get();
        $benchmarks = CsuBenchmarks::forAreas(array_values(array_unique(array_filter([$loc['area'], 'Česko']))));
        $district = $loc['kind'] !== 'kraj' ? $stats->findDistrict($slug) : null;
        $detail = $district ? $stats->districtDetail($district['name']) : null;
        $neighbours = collect($loc['neighbours'])->map(fn ($s) => $all->get($s))->filter()->values();
        $children = $all->where('parent', $slug)->values();
        $parent = $loc['parent'] ? $all->get($loc['parent']) : null;

        return view('insights.show', [
            'loc' => $loc, 'agg' => $agg, 'offer' => $offer, 'listings' => $listings, 'benchmarks' => $benchmarks, 'detail' => $detail,
            'neighbours' => $neighbours, 'children' => $children, 'parent' => $parent,
            'noindex' => ! Localities::hasContent($agg, $offer),
            'localityLinks' => \App\Support\LocalityLinks::for($district['lat'] ?? null, $district['lng'] ?? null, $loc['place']),
        ]);
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
