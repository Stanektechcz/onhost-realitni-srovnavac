{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('home') }}</loc><changefreq>daily</changefreq></url>
    <url><loc>{{ route('search', ['offer_type' => 'rent']) }}</loc><changefreq>hourly</changefreq></url>
    <url><loc>{{ route('search', ['offer_type' => 'sale']) }}</loc><changefreq>hourly</changefreq></url>
@foreach ($legal as $slug)
    <url><loc>{{ route('legal', $slug) }}</loc></url>
@endforeach
@foreach ($listings as $l)
    <url><loc>{{ route('listings.show', $l) }}</loc><lastmod>{{ $l->updated_at->toAtomString() }}</lastmod></url>
@endforeach
</urlset>
