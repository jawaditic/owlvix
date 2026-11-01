{{-- Hub page listing every step-by-step guide (App\Support\Guides), each with the icon of the builder it uses. --}}
@extends('layouts.page')

@php
    $tools = array_column(\App\Support\FreeTools::all(), null, 'route');
    $guides = \App\Support\Guides::ALL;
    $ld = ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => 'Free step-by-step guides', 'url' => route('guides'),
        'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => array_map(fn ($r, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $guides[$r][2], 'url' => route($r)],
            array_keys($guides), range(0, count($guides) - 1))]];
@endphp

@section('title', 'Free Step-by-Step Guides — Websites, Shops, Menus, CVs & Apps')
@section('description', 'Plain-English guides to selling online, making a business website, QR code menu, wedding website, portfolio, CV, link in bio page, sales funnel, newsletter and Android app — each with a free builder.')
@section('canonical', route('guides'))
@section('no-auto-quote', '1')

@section('head')
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endsection

@section('content')
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">Guides</span></nav>
        <div class="page-owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl with a guide'])</div>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free · Plain English</span>
        <h1>Step-by-step <span class="accent">guides</span></h1>
        <p class="lead">Short, practical guides for small businesses and creators. Each one walks you through it step by step — and comes with a free builder so you can do it as you read.</p>
    </div>
</section>
<section class="tools-hub">
    <div class="container">
        <div class="tools-grid">
            @foreach ($guides as $route => [, , $title, $builder, , $blurb])
                @php $t = $tools[$builder] ?? null; @endphp
                <a href="{{ route($route) }}" class="tool-card card">
                    @if ($t)<span class="svc-icon {{ $t['tone'] }}" aria-hidden="true">{!! \App\Support\Icons::svg($t['icon']) !!}</span>@endif
                    <strong>{{ $title }}</strong>
                    <p>{{ $blurb }}</p>
                    <span class="tool-card__go">Read the guide <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                </a>
            @endforeach
        </div>
        <p class="guides-hub__more">Want to jump straight in? <a href="{{ route('free-tools') }}#builders">See all free builders →</a></p>
    </div>
</section>
@endsection
