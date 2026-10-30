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
@foreach (['free-tools' => 'free-tools', 'cost-calculator' => 'tools/cost-calculator', 'security-checker' => 'tools/security', 'tech-stack' => 'tools/tech-stack', 'meta-preview' => 'tools/meta-preview', 'broken-links' => 'tools/broken-links', 'email-check' => 'tools/email-check', 'ads-roi' => 'tools/ads-roi', 'schema-generator' => 'tools/schema-generator', 'redirect-check' => 'tools/redirects', 'robots-check' => 'tools/robots', 'domain-check' => 'tools/domain', 'image-compressor' => 'tools/image-compressor', 'email-signature' => 'tools/email-signature', 'landing-builder' => 'tools/landing-builder', 'funnel-builder' => 'tools/funnel-builder', 'website-builder' => 'tools/kit-builder', 'portfolio-builder' => 'tools/kit-builder', 'link-in-bio-builder' => 'tools/kit-builder', 'coming-soon-builder' => 'tools/kit-builder'] as $route => $view)
    <url>
        <loc>{{ route($route) }}</loc>
        <lastmod>{{ date('Y-m-d', filemtime(resource_path('views/'.$view.'.blade.php'))) }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
@endforeach
    <url>
        <loc>{{ route('seo-checker') }}</loc>
        <lastmod>{{ date('Y-m-d', filemtime(resource_path('views/seo-checker.blade.php'))) }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    <url>
        <loc>{{ route('seo-audit') }}</loc>
        <lastmod>{{ date('Y-m-d', filemtime(resource_path('views/seo-audit.blade.php'))) }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
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
