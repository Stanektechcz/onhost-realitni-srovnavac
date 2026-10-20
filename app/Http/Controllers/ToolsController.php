<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Services\MarketStats;
use Illuminate\Http\Request;

class ToolsController extends Controller
{
    public function calculators(Request $request)
    {
        $tabs = ['hypoteka', 'naklady', 'vynos', 'najem-koupe', 'cashflow', 'koupe', 'prodej', 'kauce', 'energie', 'dojezd'];
        $tab = in_array($request->query('tab'), $tabs, true) ? $request->query('tab') : 'hypoteka';

        return view('tools.calculators', [
            'tab' => $tab,
            'price' => (int) $request->query('price', 6500000),
            'rent' => (int) $request->query('rent', 22000),
            'area' => (float) $request->query('area', 60),
            'penb' => in_array($request->query('penb'), array_keys(\App\Support\Calculators::PENB_KWH), true) ? $request->query('penb') : 'C',
        ]);
    }

    public function guides()
    {
        return view('tools.guides', ['guides' => config('reality_content.guides')]);
    }

    public function guide(string $slug)
    {
        $guide = config("reality_content.guides.$slug");
        abort_unless($guide, 404);

        return view('tools.guide', compact('guide', 'slug'));
    }

    public function compare(Request $request, MarketStats $stats)
    {
        $slugs = collect(explode(',', (string) $request->query('l', '')))->map(fn ($s) => trim($s))->filter()->unique()->take(4);
        $listings = Listing::public()->whereIn('slug', $slugs)->with('photos')->get()
            ->sortBy(fn ($l) => $slugs->search($l->slug))->values();
        $market = $listings->mapWithKeys(fn ($l) => [$l->id => $stats->versusMarket($l)]);

        return view('tools.compare', compact('listings', 'market'));
    }
}
