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

    public function home(\App\Services\MarketStats $stats)
    {
        $latest = Listing::public()->with('photos')->latest('published_at')->limit(8)->get();
        $count = Listing::public()->count();
        $districts = $stats->districts()->filter(fn ($d) => $d['rent']['enough'] || $d['sale']['enough'])->take(6);

        return view('home', compact('latest', 'count', 'districts'));
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

    public const SITEMAP_PAGE = 5000;

    /** All sitemap URLs: [loc, lastmod|null, changefreq|null]. */
    public static function sitemapUrls(): array
    {
        $urls = [
            [route('home'), null, 'daily'], [route('search', ['offer_type' => 'rent']), null, 'hourly'], [route('search', ['offer_type' => 'sale']), null, 'hourly'],
            [route('insights.index'), null, 'daily'],
        ];
        $localities = app(\App\Services\Localities::class);
        $agg = $localities->aggregates();
        foreach ($localities->all() as $slug => $loc) {
            $a = $agg[$slug];
            $last = $a['lastmod'] ? \Illuminate\Support\Carbon::parse($a['lastmod'])->toAtomString() : null;
            if (\App\Services\Localities::hasContent($a)) {
                $urls[] = [route('insights.show', $slug), $last, 'weekly'];
            }
            foreach (['rent' => 'localities.rent', 'sale' => 'localities.sale'] as $offer => $name) {
                if (\App\Services\Localities::hasContent($a, $offer)) {
                    $urls[] = [route($name, $slug), $last, 'daily'];
                }
            }
        }
        $urls[] = [route('agencies.index'), null, 'daily'];
        foreach (\App\Models\Agency::query()->get(['id', 'slug', 'updated_at']) as $a) {
            $urls[] = [route('agencies.show', $a), $a->updated_at?->toAtomString(), 'weekly'];
        }
        foreach (\App\Models\Broker::with('agency:id,slug')->where('is_public', true)->get(['id', 'slug', 'agency_id', 'updated_at']) as $b) {
            $urls[] = [route('agencies.broker', [$b->agency, $b]), $b->updated_at?->toAtomString(), 'monthly'];
        }
        $urls[] = [route('tools.calculators'), null, null];
        $urls[] = [route('guides.index'), null, null];
        foreach (array_keys(config('reality_content.guides')) as $g) {
            $urls[] = [route('guides.show', $g), null, null];
        }
        foreach (array_keys(self::LEGAL) as $slug) {
            $urls[] = [route('legal', $slug), null, null];
        }
        foreach (Listing::public()->latest('published_at')->limit(45000)->get(['slug', 'updated_at']) as $l) {
            $urls[] = [route('listings.show', $l), $l->updated_at->toAtomString(), null];
        }

        return $urls;
    }

    public function sitemap()
    {
        $urls = self::sitemapUrls();
        if (count($urls) <= self::SITEMAP_PAGE) {
            return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
        }
        $pages = (int) ceil(count($urls) / self::SITEMAP_PAGE);

        return response()->view('sitemap-index', ['pages' => $pages, 'lastmod' => now()->toAtomString()])->header('Content-Type', 'application/xml');
    }

    public function sitemapPart(int $page)
    {
        $chunk = array_slice(self::sitemapUrls(), ($page - 1) * self::SITEMAP_PAGE, self::SITEMAP_PAGE);
        abort_if($page < 1 || ! $chunk, 404);

        return response()->view('sitemap', ['urls' => $chunk])->header('Content-Type', 'application/xml');
    }

    public function robots()
    {
        $body = "User-agent: *\nDisallow: /admin\nDisallow: /moje\nDisallow: /api\nSitemap: ".route('sitemap')."\n";

        return response($body)->header('Content-Type', 'text/plain');
    }
}
