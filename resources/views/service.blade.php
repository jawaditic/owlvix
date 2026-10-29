{{-- One service in depth (e.g. /ai-chatbot-development). Content: config/service_pages.php, route: routes/web.php.
     Same look and quote form as the landing page (/services), which links here. --}}
@php
    $p = config("service_pages.$slug");
    $url = route("service.$slug");
    $t = \App\Support\Testimonials::data();
    $projects = ! empty($p['category']) && \Illuminate\Support\Facades\Schema::hasTable('projects')
        ? \App\Models\Project::published()->ordered()->where('category', $p['category'])->limit(6)->get() : collect();

    // Other services: their own page if it exists, otherwise their section on /services
    $landingAnchors = ['ai-chatbot-development' => 'ai-chatbots', 'ai-agent-development' => 'ai-agents', 'web-app-development' => 'web-apps', 'mobile-app-development' => 'mobile-apps'];
    $related = collect($p['related'] ?? [])->map(fn ($s) => config("service_pages.$s")
        ? ['name' => config("service_pages.$s.name"), 'url' => route("service.$s")]
        : ['name' => \Illuminate\Support\Str::headline(str_replace('-development', '', $s)).' Development', 'url' => route('landing').'#'.($landingAnchors[$s] ?? 'services')]);

    $org = ['@id' => url('/').'#business'];
    $graph = [
        '@context' => 'https://schema.org',
        '@graph' => [
            \App\Support\Owlvix::organization(),
            [
                '@type' => 'WebPage', '@id' => $url.'#page', 'url' => $url, 'name' => $p['name'],
                'description' => $p['description'], 'inLanguage' => 'en',
                'isPartOf' => ['@type' => 'WebSite', '@id' => url('/').'#website', 'name' => 'Owlvix', 'url' => url('/')],
                'about' => $org, 'mainEntity' => ['@id' => $url.'#service'],
                'breadcrumb' => ['@type' => 'BreadcrumbList', 'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Services', 'item' => route('landing')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $p['name'], 'item' => $url],
                ]],
            ],
            [
                '@type' => 'Service', '@id' => $url.'#service', 'name' => $p['name'], 'serviceType' => $p['name'],
                'description' => $p['definition']['text'], 'url' => $url, 'provider' => $org, 'areaServed' => 'Worldwide',
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $p['faq']),
            ],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $p['title'] }}</title>
    <meta name="description" content="{{ $p['description'] }}">
    <link rel="canonical" href="{{ $url }}">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#070b14" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f5f7fb" media="(prefers-color-scheme: light)">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Owlvix">
    <meta property="og:locale" content="en_US">
    <meta property="og:title" content="{{ $p['title'] }}">
    <meta property="og:description" content="{{ $p['description'] }}">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:image" content="{{ asset('images/og.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="628">
    <meta property="og:image:alt" content="Owlvix owl mascot next to the text: Need a web app? I build it start to finish.">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $p['title'] }}">
    <meta name="twitter:description" content="{{ $p['description'] }}">
    <meta name="twitter:image" content="{{ asset('images/og.png') }}">
    <meta name="twitter:image:alt" content="Owlvix owl mascot next to the text: Need a web app? I build it start to finish.">

    <script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @include('partials.head-assets')
</head>
<body class="lp" data-no-popup="{{ session('sent') || $errors->any() ? '1' : '0' }}" data-auto-quote="off">
    <div class="bg-grid" aria-hidden="true"></div>

    @include('partials.site-nav', ['local' => array_merge(['process', 'reviews', 'faq', 'contact'], $projects->isNotEmpty() ? ['work'] : []), 'form' => true])

    <main>
        {{-- Hero: the offer and the quote form (service preselected) --}}
        <section class="hero lp-hero" id="contact">
            <div class="container">
                <nav class="crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('landing') }}">Services</a><span aria-hidden="true">›</span><span aria-current="page">{{ $p['name'] }}</span>
                </nav>
            </div>
            <div class="container lp-hero__inner">
                <div class="lp-hero__copy">
                    <span class="pill"><span class="pill__dot" aria-hidden="true"></span>{{ $p['name'] }}</span>
                    <h1>{{ $p['h1'] }}</h1>
                    <p class="lead">{{ $p['lead'] }}</p>
                    <ul class="checks lp-hero__checks">
                        @foreach ($p['checks'] as $c)<li>{{ $c }}</li>@endforeach
                    </ul>
                    <a href="#quote-form" class="btn btn--glow lp-hero__jump">Get my free quote
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M6 13l6 6 6-6"/></svg>
                    </a>
                    @if (! empty($t['profile_url']))
                        <a href="{{ $t['profile_url'] }}" class="rating-badge" target="_blank" rel="noopener">
                            <span class="rating-badge__stars" aria-hidden="true">★★★★★</span>
                            <span><strong>{{ $t['rating'] }}</strong> on Fiverr · {{ $t['count'] }} reviews</span>
                        </a>
                    @endif
                </div>
                @include('partials.quote-form', ['service' => $p['service'], 'title' => $p['form_title'] ?? 'Get your free quote'])
            </div>
        </section>

        {{-- Definition + types --}}
        <section class="section" id="what">
            <div class="container">
                <div class="svc-intro">
                    <h2>{{ $p['definition']['heading'] }}</h2>
                    <p>{{ $p['definition']['text'] }}</p>
                </div>
                <p class="eyebrow">What we build</p>
                <h2>{{ $p['types']['heading'] }}</h2>
                <div class="grid grid--3">
                    @foreach ($p['types']['items'] as [$name, $text])
                        <article class="card">
                            <span class="bar {{ $loop->odd ? '' : 'bar--blue' }}"></span>
                            <h3>{{ $name }}</h3>
                            <p>{{ $text }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- How it works + what's included --}}
        <section class="section section--alt" id="how">
            <div class="container svc-split">
                <div class="svc-prose">
                    <p class="eyebrow">How it works</p>
                    <h2>{{ $p['how']['heading'] }}</h2>
                    @foreach ($p['how']['paragraphs'] as $para)<p>{{ $para }}</p>@endforeach
                    <ul class="chips" aria-label="Tools we use">
                        @foreach ($p['tech'] as $tech)<li>{{ $tech }}</li>@endforeach
                    </ul>
                </div>
                <div class="card svc-gets">
                    <h3>{{ $p['gets']['heading'] }}</h3>
                    <ul class="checks">
                        @foreach ($p['gets']['items'] as $g)<li>{{ $g }}</li>@endforeach
                    </ul>
                    <a href="#quote-form" class="btn btn--block">Get a fixed-price quote</a>
                </div>
            </div>
        </section>

        {{-- Process --}}
        <section class="section" id="process">
            <div class="container">
                <p class="eyebrow">Process</p>
                <h2>From first message to launch</h2>
                <ol class="grid grid--3 steps">
                    @foreach ($p['steps'] as [$name, $text])
                        <li class="card">
                            <span class="num">{{ $loop->iteration }}</span>
                            <h3>{{ $name }}</h3>
                            <p>{{ $text }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Cost --}}
        <section class="section section--alt" id="cost">
            <div class="container container--narrow svc-prose">
                <p class="eyebrow">Pricing</p>
                <h2>{{ $p['cost']['heading'] }}</h2>
                <p>{{ $p['cost']['text'] }}</p>
                <p class="svc-cost__cta">
                    <a href="#quote-form" class="btn btn--glow">Get my fixed price</a>
                    <a href="{{ route('landing') }}#pricing" class="btn btn--ghost">See how we charge</a>
                </p>
            </div>
        </section>

        {{-- Related portfolio projects (Admin → Portfolio, by category) --}}
        @if ($projects->isNotEmpty())
        <section class="section" id="work">
            <div class="container">
                <p class="eyebrow">Our work</p>
                <h2>{{ $p['name'] }} projects</h2>
                <div class="work">
                    @foreach ($projects as $pr)
                        <article class="work-card">
                            <div class="work-card__img">
                                @include('partials.picture', ['src' => $pr->image, 'alt' => $pr->title.' — '.$pr->categoryLabel().' project by Owlvix', 'width' => 720, 'height' => 405, 'loading' => 'lazy'])
                            </div>
                            <div class="work-card__body">
                                <span class="work-card__cat">{{ $pr->categoryLabel() }}</span>
                                <h3>{{ $pr->title }}</h3>
                                @if ($pr->summary)<p>{{ $pr->summary }}</p>@endif
                                @if ($pr->url)<div class="work-card__links"><a href="{{ $pr->url }}" target="_blank" rel="noopener nofollow">View site ↗</a></div>@endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        @include('partials.reviews')

        {{-- FAQ --}}
        <section class="section section--alt" id="faq">
            <div class="container container--narrow">
                <p class="eyebrow">FAQ</p>
                <h2>{{ $p['name'] }} questions</h2>
                <div class="faq">
                    @foreach ($p['faq'] as [$q, $a])
                        <details>
                            <summary>{{ $q }}</summary>
                            <p>{{ $a }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Final call to action + other services --}}
        <section class="section lp-cta">
            <div class="container">
                <div class="card lp-cta__box">
                    @include('partials.picture', ['src' => 'images/owl.png', 'alt' => 'Owlvix owl mascot', 'width' => 120, 'height' => 126, 'loading' => 'lazy'])
                    <div>
                        <h2>Ready to talk about your project?</h2>
                        <p>Send a few lines and get a fixed-price quote within one business day — free, with no obligation.</p>
                    </div>
                    <a href="#quote-form" class="btn btn--glow">Get a free quote
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                </div>
                @if ($related->isNotEmpty())
                    <nav class="lp-jump svc-related" aria-label="Related services">
                        <span>Related services:</span>
                        @foreach ($related as $r)<a href="{{ $r['url'] }}">{{ $r['name'] }}</a>@endforeach
                        <a href="{{ route('landing') }}">All services</a>
                    </nav>
                @endif
            </div>
        </section>
    </main>

    @include('partials.footer', ['top' => route('home')])
    @include('partials.page-scripts')
</body>
</html>
