{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as [$loc, $lastmod, $freq])
    <url><loc>{{ $loc }}</loc>@if ($lastmod)<lastmod>{{ $lastmod }}</lastmod>@endif @if ($freq)<changefreq>{{ $freq }}</changefreq>@endif</url>
@endforeach
</urlset>
