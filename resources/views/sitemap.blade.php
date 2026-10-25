{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <url>
        <loc>{{ url('/') }}</loc>
        <lastmod>{{ $lastmod }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
        <image:image><image:loc>{{ asset('images/og.png') }}</image:loc></image:image>
@foreach ($projects as $p)
@if ($p->image)
        <image:image><image:loc>{{ $p->imageUrl() }}</image:loc></image:image>
@endif
@endforeach
    </url>
</urlset>
