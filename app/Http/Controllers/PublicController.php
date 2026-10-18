<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\MembershipPlan;

class PublicController extends Controller
{
    public const LEGAL = [
        'obchodni-podminky' => 'Všeobecné obchodní podmínky',
        'ochrana-osobnich-udaju' => 'Zásady ochrany osobních údajů (GDPR)',
        'cookies' => 'Zásady používání cookies',
        'pravidla-moderace' => 'Pravidla moderace a nahlašování obsahu (DSA)',
    ];

    public function home()
    {
        $latest = Listing::public()->with('photos')->latest('published_at')->limit(8)->get();
        $count = Listing::public()->count();

        return view('home', compact('latest', 'count'));
    }

    public function legal(string $page)
    {
        abort_unless(isset(self::LEGAL[$page]), 404);

        return view('pages.legal', ['slug' => $page, 'title' => self::LEGAL[$page]]);
    }

    public function pricing()
    {
        abort_unless(config('reality.features.memberships'), 404);
        $plans = MembershipPlan::where('is_active', true)->orderBy('audience')->orderBy('price_halere')->get()->groupBy('audience');

        return view('pages.pricing', compact('plans'));
    }

    public function sitemap()
    {
        $listings = Listing::public()->latest('published_at')->limit(45000)->get(['slug', 'updated_at']);

        return response()->view('sitemap', ['listings' => $listings, 'legal' => array_keys(self::LEGAL)])
            ->header('Content-Type', 'application/xml');
    }

    public function robots()
    {
        $body = "User-agent: *\nDisallow: /admin\nDisallow: /moje\nDisallow: /api\nSitemap: ".route('sitemap')."\n";

        return response($body)->header('Content-Type', 'text/plain');
    }
}
