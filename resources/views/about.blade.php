{{-- About Owlvix: who runs it, where, since when, and the Fiverr/Anjtec history (trust / E-E-A-T).
     Facts confirmed by the owner on 30 Oct 2026. Keep them in sync with partials/structured-data and llms.txt. --}}
@extends('layouts.page')

@php
    $t = \App\Support\Testimonials::data();
@endphp

@section('title', 'About Owlvix — Jawad Ahmad and Team')
@section('description', 'Owlvix is a web, mobile and AI development studio founded by Jawad Ahmad in Pakistan, formerly Anjtec on Fiverr. Building software since 2018 for clients worldwide.')
@section('canonical', route('about'))

@section('head')
@php
    $graph = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'AboutPage', '@id' => route('about').'#page', 'url' => route('about'), 'name' => 'About Owlvix',
                'inLanguage' => 'en', 'about' => ['@id' => url('/').'#business'], 'mainEntity' => ['@id' => url('/').'#business'],
                'isPartOf' => ['@type' => 'WebSite', '@id' => url('/').'#website', 'name' => 'Owlvix', 'url' => url('/')],
                'breadcrumb' => ['@type' => 'BreadcrumbList', 'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'About', 'item' => route('about')],
                ]],
            ],
            \App\Support\Owlvix::organization(),
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endsection

@section('content')
<section class="section about-hero">
    <div class="container about-hero__inner">
        <div>
            <nav class="crumbs" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><span aria-current="page">About</span>
            </nav>
            <p class="eyebrow">About us</p>
            <h1>The people behind Owlvix</h1>
            <p class="lead">Owlvix is a small web, mobile and AI development studio founded by <strong>Jawad Ahmad</strong>. We're based in Pakistan and have been building software for clients around the world since 2018.</p>
            <ul class="about-facts">
                <li><strong>2018</strong><span>building software since</span></li>
                <li><strong>200+</strong><span>projects delivered</span></li>
                <li><strong>{{ $t['count'] }}</strong><span>client reviews on Fiverr</span></li>
                <li><strong>🇵🇰</strong><span>based in Pakistan, working worldwide</span></li>
            </ul>
        </div>
        @include('partials.picture', ['src' => 'images/owl.png', 'alt' => 'Owlvix owl mascot', 'width' => 260, 'height' => 273, 'class' => 'about-hero__owl'])
    </div>
</section>

<section class="section section--alt">
    <div class="container container--narrow prose">
        <h2>Our story: from Anjtec to Owlvix</h2>
        <p>Owlvix was previously known as <strong>Anjtec</strong> on Fiverr, where we've completed more than 200 projects for businesses in the United States, the United Kingdom, Canada, Europe and beyond. That's why many of the reviews on this site mention Anjtec or Jawad — it's the same team, now working under one name.</p>
        <p>Our work covers websites and Shopify stores, custom web apps, iPhone and Android apps, and AI chatbots and automation. Owlvix brings all of that together: one team that can take an idea from the first sketch to the server it runs on.</p>

        <h2>Who we are</h2>
        <p><strong>Jawad Ahmad</strong> is the founder and lead developer. He has been building websites and software professionally since 2018, and works with a small team of developers and designers, so each project gets the right skills without the overhead of a large agency.</p>
        <p>We work across the full stack: Laravel, React, Next.js and Vue for web apps; React Native and Expo for iPhone and Android; Shopify and WordPress for stores and websites; and OpenAI, Anthropic Claude and Google Gemini for AI features.</p>

        <h2>How we work</h2>
        <ul>
            <li><strong>Clear quotes.</strong> You get a fixed price, scope and timeline before any work starts, usually within one business day.</li>
            <li><strong>No surprises.</strong> You can see progress every week on a private preview link and change direction early.</li>
            <li><strong>You own everything.</strong> Source code, designs and accounts are handed over to you when the project is paid.</li>
            <li><strong>We stay around.</strong> Bug fixes after launch are free, and our monthly plan covers ongoing work from $100 a month.</li>
            <li><strong>Remote by design.</strong> We work online with clients worldwide and plan calls around your time zone.</li>
        </ul>

        <h2>Get in touch</h2>
        <p>The fastest way to reach us is the quote form — tell us a little about your project and we'll reply within one business day. You can also email <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>@if (! empty($t['profile_url'])) or read our <a href="{{ $t['profile_url'] }}" target="_blank" rel="noopener">reviews on Fiverr</a>@endif.</p>
        <p class="about-cta">
            <a href="{{ route('landing') }}#contact" class="btn btn--glow">Get a free quote</a>
            <a href="{{ route('landing') }}" class="btn btn--ghost">See our services</a>
        </p>
    </div>
</section>
@endsection
