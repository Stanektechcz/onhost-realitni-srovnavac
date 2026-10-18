{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('home') }}</loc><changefreq>daily</changefreq></url>
    <url><loc>{{ route('search', ['offer_type' => 'rent']) }}</loc><changefreq>hourly</changefreq></url>
    <url><loc>{{ route('search', ['offer_type' => 'sale']) }}</loc><changefreq>hourly</changefreq></url>
    <url><loc>{{ route('insights.index') }}</loc><changefreq>daily</changefreq></url>
@foreach (app(\App\Services\MarketStats::class)->districts() as $d)
    <url><loc>{{ route('insights.show', $d['slug']) }}</loc><changefreq>weekly</changefreq></url>
@endforeach
    <url><loc>{{ route('tools.calculators') }}</loc></url>
    <url><loc>{{ route('guides.index') }}</loc></url>
@foreach (array_keys(config('reality_content.guides')) as $g)
    <url><loc>{{ route('guides.show', $g) }}</loc></url>
@endforeach
@foreach ($legal as $slug)
    <url><loc>{{ route('legal', $slug) }}</loc></url>
@endforeach
@foreach ($listings as $l)
    <url><loc>{{ route('listings.show', $l) }}</loc><lastmod>{{ $l->updated_at->toAtomString() }}</lastmod></url>
@endforeach
</urlset>
