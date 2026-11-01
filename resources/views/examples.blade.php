{{-- Example designs gallery: every ready design of every page builder, drawn live by public/js/examples.js, with a
     preview and a "Use this design" link that opens the builder on it (?design=key). --}}
@extends('layouts.page')

@php
    // kind (as in builder-engine.js) => [filter label, heading, intro, builder route, builder name]
    $groups = [
        'store' => ['Shops', 'Online stores', 'Product pages, a cart and checkout — card, cash on delivery or bank transfer.', 'store-builder', 'Online store builder'],
        'website' => ['Websites', 'Business websites', 'Multi-page sites with Home, About, Services and Contact pages and a working contact form.', 'website-builder', 'Website builder'],
        'menu' => ['Menus', 'Restaurant menus', 'Digital menus with prices, specials, opening hours and a QR code for every table.', 'menu-builder', 'Restaurant menu builder'],
        'event' => ['Events', 'Wedding & event websites', 'Invitations with a countdown, the plan for the day, directions and an RSVP form.', 'event-website-builder', 'Wedding & event builder'],
        'portfolio' => ['Portfolios', 'Portfolios', 'Show your work in a gallery, with your story, prices, reviews and an enquiry form.', 'portfolio-builder', 'Portfolio builder'],
        'links' => ['Link in bio', 'Link in bio pages', 'One link for Instagram, TikTok and YouTube, with big buttons and your socials.', 'link-in-bio-builder', 'Link in bio builder'],
        'soon' => ['Coming soon', 'Coming soon pages', 'A countdown, a waitlist form and your socials while you get ready.', 'coming-soon-builder', 'Coming soon builder'],
        'landing' => ['Landing pages', 'Landing pages', 'One page that turns visitors into calls, bookings or sign-ups.', 'landing-builder', 'Landing page builder'],
        'funnel' => ['Funnels', 'Sales funnels', 'Opt-in, sales, upsell and thank-you steps, with leads, payments and A/B tests.', 'funnel-builder', 'Funnel builder'],
    ];
    $featured = \App\Models\BuilderSite::featured()->latest('showcase_at')->limit(12)->get();
    $faq = [
        ['Are these designs free to use?', 'Yes. Every design is free to use, change and publish — on sites.owlvix.com or your own domain. No payment and no watermark on your content.'],
        ['Can I change the colours, photos and words?', 'Yes. Press “Use this design” and the builder opens with it. Click any text or photo to change it, and set your colours and fonts in the Site tab.'],
        ['What happens to the page I was already working on?', 'If you’ve already edited something in that builder, we ask before replacing it — and you can press Undo to get yours back.'],
        ['Do the designs work on phones?', 'Yes. Every design fits phones, tablets and computers. Use “Phone” in the preview to see how it looks on a phone.'],
        ['Can you make a custom design for me?', 'Yes. Our team designs custom websites, shops and apps. Ask for a free quote and we design the first version at no cost.'],
    ];
    $ld = ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => 'Free website, shop and page designs', 'url' => route('examples'),
        'hasPart' => array_values(array_map(fn ($g) => ['@type' => 'WebApplication', 'name' => 'Owlvix '.$g[4], 'url' => route($g[3]), 'applicationCategory' => 'DesignApplication', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD']], $groups))];
@endphp

@section('title', 'Free Website, Shop & Page Designs — Examples You Can Use')
@section('description', 'Over 50 free ready-made designs: online stores, business websites, restaurant menus, wedding sites, portfolios, link in bio pages, landing pages and sales funnels. Preview any design and use it in one click.')
@section('canonical', route('examples'))
@section('no-auto-quote', '1')

@section('head')
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endsection

@section('content')
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}#builders">Free builders</a><span aria-hidden="true">›</span><span aria-current="page">Example designs</span></nav>
        <div class="page-owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl showing example designs'])</div>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free · Use any design in one click</span>
        <h1>Ready-made <span class="accent">designs</span> you can use free</h1>
        <p class="lead">Shops, websites, menus, wedding pages, portfolios and more. Preview any design, then press “Use this design” — the builder opens with it ready for your words and photos.</p>
    </div>
</section>

<section class="ex" data-ex data-runtime="{{ \App\Support\BuilderHtml::runtimeUrl() }}">
    <div class="container">
        <div class="ex-filters" role="group" aria-label="Show designs for">
            <button type="button" class="lpb-chip" data-ex-filter="all" aria-pressed="true">All</button>
            @foreach ($groups as $kind => $g)<button type="button" class="lpb-chip" data-ex-filter="{{ $kind }}" aria-pressed="false">{{ $g[0] }}</button>@endforeach
        </div>
        @foreach ($groups as $kind => [, $heading, $intro, $route, $builder])
            <div class="ex-group" id="{{ $kind }}" data-ex-group="{{ $kind }}">
                <div class="ex-group__head">
                    <div><h2>{{ $heading }}</h2><p>{{ $intro }}</p></div>
                    <a href="{{ route($route) }}" class="ex-group__link">Open the {{ \Illuminate\Support\Str::lower($builder) }} →</a>
                </div>
                <div class="ex-grid" data-examples="{{ $kind }}" data-builder="{{ $builder }}">
                    <noscript><p><a href="{{ route($route) }}">Open the free {{ \Illuminate\Support\Str::lower($builder) }}</a> to see its designs.</p></noscript>
                </div>
            </div>
        @endforeach
    </div>
</section>

@if ($featured->isNotEmpty())
<section class="ex ex-made" id="made-with-owlvix">
    <div class="container">
        <div class="ex-group__head">
            <div><h2>Made with Owlvix</h2><p>Real sites built by small businesses and creators with our free builders.</p></div>
        </div>
        <div class="ex-grid">
            @foreach ($featured as $site)
                <a class="ex-made__card card" href="{{ $site->url() }}" target="_blank" rel="noopener" style="--site: {{ $site->color ?: '#16a34a' }}">
                    <span class="ex-made__top" aria-hidden="true"><span>{{ mb_strtoupper(mb_substr($site->name, 0, 1)) }}</span></span>
                    <span class="ex-card__body">
                        <strong>{{ $site->name }}</strong><small>{{ $site->kindLabel() }} · {{ $site->displayUrl() }}</small>
                        <span class="ex-made__go">Visit site →</span>
                    </span>
                </a>
            @endforeach
        </div>
        <p class="guides-hub__more">Published a site with a free builder? Tick “Feature my site” when you publish.</p>
    </div>
</section>
@endif

<dialog class="ex-dlg" data-ex-dlg aria-labelledby="ex-dlg-name">
    <div class="ex-dlg__bar">
        <strong id="ex-dlg-name" data-ex-name></strong>
        <div class="ex-dlg__devices" role="group" aria-label="Preview size">
            <button type="button" class="lpb-chip" data-ex-device="desktop" aria-pressed="true">Computer</button>
            <button type="button" class="lpb-chip" data-ex-device="mobile" aria-pressed="false">Phone</button>
        </div>
        <a class="btn btn--sm btn--glow" href="#" data-ex-use>Use this design</a>
        <button type="button" class="ex-dlg__x" data-ex-close aria-label="Close preview">✕</button>
    </div>
    <div class="ex-dlg__frame" data-ex-frame></div>
</dialog>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Example designs, explained'])

<script src="{{ \App\Support\Asset::url('js/builder-engine.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/examples.js') }}" defer></script>
@endsection
