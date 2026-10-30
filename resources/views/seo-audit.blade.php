{{-- Free full SEO audit request page (also linked from the "Free tools" menu). --}}
@extends('layouts.page')

@section('title', 'Free SEO Audit — Full Website Report')
@section('description', 'Request a free, full SEO audit of your website: technical SEO, content quality, search intent, speed and AI search readiness — delivered as a PDF within 2 business days.')
@section('canonical', route('seo-audit'))
@section('no-auto-quote', '1')

@section('content')
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><span aria-current="page">SEO audit</span>
        </nav>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free · Delivered in 2 business days</span>
        <h1>Free <span class="accent">SEO audit</span></h1>
        <p class="lead">A full, agency-style review of your whole website, done with professional SEO tools and checked by our team — with a clear, prioritised action plan.</p>
    </div>
</section>
<section class="speed-report" style="padding-top:0">
    <div class="container">
        @include('partials.audit-card', ['standalone' => true])
        <p class="speed-info__more">Want an instant result first? Try the <a href="{{ route('seo-checker') }}">free SEO checker</a> or the <a href="{{ route('speed-test') }}">website speed test</a>.</p>
    </div>
</section>
@endsection
