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
    <url>
        <loc>{{ route('landing') }}</loc>
        <lastmod>{{ date('Y-m-d', filemtime(resource_path('views/landing.blade.php'))) }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.9</priority>
    </url>
@foreach (array_keys(config('service_pages')) as $slug)
    <url>
        <loc>{{ route('service.'.$slug) }}</loc>
        <lastmod>{{ date('Y-m-d', max(filemtime(config_path('service_pages.php')), filemtime(resource_path('views/service.blade.php')))) }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
@endforeach
    <url>
        <loc>{{ route('speed-test') }}</loc>
        <lastmod>{{ date('Y-m-d', filemtime(resource_path('views/speed-test.blade.php'))) }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    <url>
        <loc>{{ route('about') }}</loc>
        <lastmod>{{ date('Y-m-d', filemtime(resource_path('views/about.blade.php'))) }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    <url>
        <loc>{{ route('privacy') }}</loc>
        <lastmod>{{ date('Y-m-d', filemtime(resource_path('views/privacy.blade.php'))) }}</lastmod>
        <changefreq>yearly</changefreq>
        <priority>0.2</priority>
    </url>
</urlset>
