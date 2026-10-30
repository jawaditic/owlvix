{{-- Free page builder: a visual editor in public/js/landing-builder.js (runs in the browser). Server parts —
     email sign-in, contact forms, publishing to /p/{name} — are under /builder (BuilderController). --}}
@extends('layouts.page')

@php
    $faq = [
        ['Is the page builder really free?', 'Yes. Build as many pages as you like, download them, or publish them free on sites.owlvix.com — no payment and no watermark on your content. A small "made with" link sits in the footer.'],
        ['How do I edit my page?', 'Click any text on the page and type. Click a photo or the logo to replace it. Drag widgets from the Add tab into any column, and use the Edit tab for each element\'s Content, Style and Advanced settings — just like Elementor.'],
        ['Can I style things differently on mobile?', 'Yes. Switch the preview to tablet or mobile in the top bar, then change sizes, spacing or alignment in the Style tab — those values apply only to that screen size. You can also hide any element on a device in the Advanced tab.'],
        ['Does the contact form really work?', 'Yes. Switch it on with your email (free) and every message a visitor sends is emailed to you — even if you put the page on your own hosting.'],
        ['Can I keep editing later?', 'Your work is saved in this browser automatically. Use "Save project" to download a project file, then "Open" it on any computer to carry on.'],
        ['Will my published page show on Google?', 'Pages published on sites.owlvix.com are hidden from search engines. For your own domain and Google ranking, download the page and upload it to your hosting — or ask us to set it up.'],
    ];
@endphp

@section('title', 'Free Website Builder — Drag & Drop, Like Elementor')
@section('description', 'Free drag-and-drop page builder: 45+ widgets, templates, animations, popups and a working contact form. Download or publish. No sign-up to start.')
@section('canonical', route('landing-builder'))
@section('no-auto-quote', '1')

@section('head')
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('tools.partials.schema', ['name' => 'Owlvix Free Page Builder', 'url' => route('landing-builder'), 'category' => 'DesignApplication', 'faq' => $faq])
@endsection

@section('content')
<section class="hero speed-hero lpb-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">Page builder</span>
        </nav>
        <div class="page-owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl building a page'])</div>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · No sign-up to start</span>
        <h1>Build your <span class="accent">website</span> — just click and type</h1>
        <p class="lead">Drag in 45+ widgets, style every element for desktop, tablet and mobile, add animations, popups and a working contact form — then download it or publish it free.</p>
    </div>
</section>

@include('tools.partials.builder-app')

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
            <div><h2>Want a site built to convert?</h2><p>Our designers build custom websites and landing pages — with forms, booking, payments and tracking. Start free: we design the first version at no cost.</p></div>
            <a href="{{ route('service.landing-page-design') }}#contact" class="btn btn--glow" data-quote data-lpb-quote>Get a custom site <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'The page builder, explained'])

<script src="{{ \App\Support\Asset::url('js/builder-engine.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/landing-builder.js') }}" defer></script>
@endsection
