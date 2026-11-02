{{-- Free business card maker — runs fully in the browser (public/js/card-builder.js, QR from public/js/qr.js).
     Details and logos stay on the device; PDFs come from the browser's own "Save as PDF". --}}
@extends('layouts.page')

@php
    $faq = [
        ['Is the business card maker really free?', 'Yes. No sign-up, no watermark and no limit. Download print-ready PDFs and PNGs as often as you like.'],
        ['How do I get my cards printed?', 'Press “PDF for a print shop” and save it. It has both sides at the exact card size with 3 mm bleed — the extra edge printers trim off — so colours run right to the edge. Upload it to any online printer or take it to a local print shop.'],
        ['Can I print business cards at home?', 'Yes. Press “10 per sheet”, choose A4 or Letter, and print on thick card (250–350 gsm). Page 2 holds the backs, lined up for printing on both sides. Cut along the marks with a paper trimmer or a ruler and craft knife.'],
        ['What size is a business card?', 'In the US and Canada, 3.5 × 2 inches (88.9 × 50.8 mm). In the UK and most of Europe, 85 × 55 mm. Pick yours under “Card size”.'],
        ['What should I put on a business card?', 'Your name, job title and business name, then one or two ways to reach you — phone and email are the most useful — plus your website. Keep it short: a card with fewer lines is easier to read.'],
        ['Can I add a QR code?', 'Yes. The back can carry a QR code that opens your website, or one that saves your contact details straight into the phone. The codes never expire.'],
        ['Is my information private?', 'Yes. The card is made in your browser and saved only on this device. Your details and logo are never uploaded.'],
    ];
@endphp

@section('title', 'Free Business Card Maker — Print-Ready PDF, QR Code, No Sign-up')
@section('description', 'Design a business card in minutes: 6 designs, your logo and colours, a QR code on the back. Download a print-ready PDF with bleed, 10 per sheet for home printing, or PNG. Free, nothing uploaded.')
@section('canonical', route('business-card-maker'))
@section('no-auto-quote', '1')

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Free Business Card Maker', 'url' => route('business-card-maker'), 'category' => 'DesignApplication', 'faq' => $faq])
@endsection

@section('content')
@unless ($editor ?? false)
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">Business card maker</span>
        </nav>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · Print-ready · Nothing uploaded</span>
        <h1>Free <span class="accent">business card maker</span> — ready to print</h1>
        <p class="lead">Type your details, add your logo and pick a design. Download a print-ready PDF for any print shop, print 10 on a sheet at home, or save PNGs. No sign-up, no watermark.</p>
        @include('guides.partials.link')
    </div>
</section>
@endunless

<section class="calc-wrap">
    <div class="container">
        @unless ($editor ?? false)
        @include('partials.wizard-banner', [
            'say' => 'Hoo! Need business cards? Let’s make yours — about a minute.',
            'title' => 'Let’s make <span class="accent">your business card</span> together',
            'sub' => 'Pick a design, type your details and add your logo. Your details stay on this device — nothing is uploaded.',
            'steps' => [['Pick a', 'design'], ['Your name', '& contacts'], ['Logo, then', 'print']],
            'go' => 'Make my business card',
            'pill' => 'Quick setup · about a minute',
        ])
        @endunless
        <div class="fb rb cb card" data-cb @if ($editorUrl = \App\Support\FreeTools::editorUrl(Route::currentRouteName())) data-editor-url="{{ $editorUrl }}" @endif>
            <div class="fb-top">
                <div class="fb-tpls"><span>Design:</span><div data-designs></div></div>
                <div class="fb-top__actions">
                    <button type="button" class="btn btn--sm btn--glow" data-get="shop">{!! \App\Support\Icons::i('download') !!} Print-ready PDF</button>
                </div>
            </div>
            <div class="rb-filebar">
                <span class="fb-hint" data-saved>Saved on this device</span>
                <span class="ib-filebar__btns"><button type="button" class="rb-link" data-reset>Start again</button></span>
            </div>

            <div class="fb-layout rb-layout">
                <form class="fb-edit" data-form novalidate onsubmit="return false"></form>
                <div class="fb-prev-wrap">
                    <div class="cb-stage">
                        <p class="cb-side">Front</p><div class="cb-card" data-front></div>
                        <p class="cb-side">Back</p><div class="cb-card" data-back></div>
                    </div>
                    <div class="cb-get">
                        <button type="button" class="btn btn--sm btn--glow" data-get="shop">{!! \App\Support\Icons::i('download') !!} PDF for a print shop</button>
                        <span class="cb-home"><button type="button" class="btn btn--sm btn--ghost" data-get-home data-get="a4">{!! \App\Support\Icons::i('doc') !!} 10 per sheet</button><label class="sr-only" for="cb-paper">Paper</label><select id="cb-paper" data-paper><option value="a4">A4</option><option value="letter">Letter</option></select></span>
                        <button type="button" class="btn btn--sm btn--ghost" data-get="png">{!! \App\Support\Icons::i('image') !!} PNG</button>
                    </div>
                    <p class="cb-note">The print-shop PDF has 3 mm bleed on every side. Print at home on thick card (250–350 gsm) and cut along the marks.</p>
                    <div class="rb-tips" data-tips-box hidden><b>Before you print</b><ul data-tips></ul></div>
                </div>
            </div>
            <input type="file" accept="image/jpeg,image/png,image/webp" data-logo-file hidden>
        </div>

        <div class="editor-hidden" @if (! empty($editor)) hidden @endif>
        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl'])</div>
            <div><h2>Put a website on your card</h2><p>Make a free website for your business in minutes — then its address goes on your card and in your QR code.</p></div>
            <a href="{{ route('website-builder') }}" class="btn btn--glow">Make my free website {!! \App\Support\Icons::i('arrow-right') !!}</a>
        </div>
        </div>
    </div>
</section>

@unless ($editor ?? false)
@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Business cards, explained'])
@endunless

<script src="{{ \App\Support\Asset::url('js/qr.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/builder-wizard.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/card-builder.js') }}" defer></script>
@endsection
