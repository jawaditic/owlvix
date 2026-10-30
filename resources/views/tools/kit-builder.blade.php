{{-- The extra free builders (website, portfolio, link in bio, coming soon): the page builder editor
     (public/js/landing-builder.js) started in a kit mode. Page text lives in App\Support\BuilderKits. --}}
@extends('layouts.page')

@php
    $k = \App\Support\BuilderKits::get($kit);
    $route = \App\Support\BuilderKits::ROUTES[$kit];
    $faq = $k['faq'];
@endphp

@section('title', $k['title'])
@section('description', $k['description'])
@section('canonical', route($route))
@section('no-auto-quote', '1')

@section('head')
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('tools.partials.schema', ['name' => 'Owlvix Free '.$k['crumb'], 'url' => route($route), 'category' => 'DesignApplication', 'faq' => $faq])
@endsection

@section('content')
<section class="hero speed-hero lpb-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">{{ $k['crumb'] }}</span>
        </nav>
        <div class="page-owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl building a website'])</div>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · No sign-up to start</span>
        <h1>{!! $k['h1'] !!}</h1>
        <p class="lead">{{ $k['lead'] }}</p>
    </div>
</section>

@include('tools.partials.builder-app', ['mode' => $kit])

<section class="lpb-below">
    <div class="container">
        <div class="lpb-after card" data-lpb-after hidden>
            <span class="perch-owl" aria-hidden="true">@include('partials.live-owl', ['width' => 96, 'variant' => 'desk', 'alt' => 'Owlvix owl'])</span>
            <h2>🎉 Your website is downloaded</h2>
            <ol>
                <li data-after-zip hidden>Unzip the file — every page is its own .html file.</li>
                <li>Upload the file(s) to your hosting (cPanel File Manager, Netlify, GitHub Pages…). The home page must be called <code>index.html</code>.</li>
                <li>Open your domain — your site is live. The contact form keeps working wherever you host it.</li>
            </ol>
            <p>Rather not deal with hosting? We'll put it live on your domain with SSL, analytics and speed tuning — and polish the design for you.</p>
            <a href="{{ route('service.landing-page-design') }}#contact" class="btn btn--glow" data-quote data-lpb-quote>Make it live for me
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>

        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl designing a page'])</div>
            <div><h2>{{ $k['cta'][0] }}</h2><p>{{ $k['cta'][1] }}</p></div>
            <a href="{{ route('service.landing-page-design') }}#contact" class="btn btn--glow" data-quote data-lpb-quote>Get a custom site <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => $k['crumb'].', explained'])

<script src="{{ \App\Support\Asset::url('js/builder-engine.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/landing-builder.js') }}" defer></script>
@endsection
