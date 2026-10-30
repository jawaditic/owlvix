{{-- Hub page listing every free tool (App\Support\FreeTools) plus the free SEO audit. --}}
@extends('layouts.page')

@section('title', 'Free Tools for Websites & Apps')
@section('description', 'Free tools from Owlvix: app and website cost calculator, SEO checker, speed test, security & SSL checker, tech stack detector and social preview — no sign-up.')
@section('canonical', route('free-tools'))
@section('no-auto-quote', '1')

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
        <div class="tools-grid">
            @foreach (\App\Support\FreeTools::all() as $t)
                <a href="{{ route($t['route']) }}" class="tool-card card">
                    <span class="svc-icon {{ $t['tone'] }}" aria-hidden="true">{!! \App\Support\Icons::svg($t['icon']) !!}</span>
                    <strong>{{ $t['name'] }}</strong>
                    <p>{{ $t['desc'] }}</p>
                    <span class="tool-card__go">Open tool <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                </a>
            @endforeach
        </div>
        <a href="{{ route('seo-audit') }}" class="tool-feature card">
            <span class="svc-icon is-blue" aria-hidden="true">{!! \App\Support\Icons::svg('sparkle') !!}</span>
            <div><strong>Full SEO audit <em class="nav-drop__tag">Free</em></strong><p>Want the whole picture? Our team reviews your entire website and emails you a prioritised report within 2 business days.</p></div>
            <span class="btn btn--glow">Request my audit</span>
        </a>
    </div>
</section>
@endsection
