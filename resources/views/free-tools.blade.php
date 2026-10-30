{{-- Hub page listing every free tool (App\Support\FreeTools) plus the free SEO audit. --}}
@extends('layouts.page')

@section('title', 'Free Tools for Websites & Apps')
@section('description', 'Free Owlvix tools: cost and ads ROI calculators, SEO checker, speed test, broken link, email and security checkers, schema generator and more.')
@section('canonical', route('free-tools'))
@section('no-auto-quote', '1')

@php
    $ld = ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => 'Free tools for websites & apps', 'url' => route('free-tools'),
        'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => array_map(fn ($t, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $t['name'], 'url' => route($t['route'])],
            \App\Support\FreeTools::all(), array_keys(\App\Support\FreeTools::all()))]];
@endphp
@section('head')
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endsection

@section('content')
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><span aria-current="page">Free tools</span></nav>
        <div class="page-owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl'])</div>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free · No sign-up</span>
        <h1>Free tools for your <span class="accent">website &amp; app</span></h1>
        <p class="lead">Check, test and plan your project in seconds — the same tools our team uses every day.</p>
    </div>
</section>
<section class="tools-hub">
    <div class="container">
        @foreach (\App\Support\FreeTools::GROUPS as $group => [$heading, $intro])
            <div class="tools-group" @if ($group === 'builder') id="builders" @endif>
                <h2>{{ $heading }}</h2>
                <p>{{ $intro }}</p>
            </div>
            <div class="tools-grid">
                @foreach (array_filter(\App\Support\FreeTools::all(), fn ($t) => $t['group'] === $group) as $t)
                    <a href="{{ route($t['route']) }}" class="tool-card card">
                        <span class="svc-icon {{ $t['tone'] }}" aria-hidden="true">{!! \App\Support\Icons::svg($t['icon']) !!}</span>
                        <strong>{{ $t['name'] }}</strong>
                        <p>{{ $t['desc'] }}</p>
                        <span class="tool-card__go">Open tool <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                    </a>
                @endforeach
            </div>
        @endforeach
        <a href="{{ route('seo-audit') }}" class="tool-feature card">
            <span class="svc-icon is-blue" aria-hidden="true">{!! \App\Support\Icons::svg('sparkle') !!}</span>
            <div><strong>Full SEO audit <em class="nav-drop__tag">Free</em></strong><p>Want the whole picture? Our team reviews your entire website and emails you a prioritised report within 2 business days.</p></div>
            <span class="btn btn--glow">Request my audit</span>
        </a>
    </div>
</section>
@endsection
