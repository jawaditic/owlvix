{{-- Free QR code generator — runs fully in the browser (public/js/qr.js + public/js/qr-builder.js). Codes are static:
     the link or details are inside the code itself, so they never expire, and nothing is uploaded. --}}
@extends('layouts.page')

@php
    $faq = [
        ['Is this QR code generator really free?', 'Yes. No sign-up, no watermark, no limit on scans and no trial that runs out. Download as many codes as you like in PNG or SVG.'],
        ['Do the QR codes expire?', 'No. These are “static” QR codes: your link or details are stored inside the code itself, not on our server. They keep working forever, even without Owlvix. (Sites that make “dynamic” codes send scans through their own server — when your plan ends, the code stops working.)'],
        ['Can I change where the code goes after printing it?', 'Not with a static code — what’s inside it is fixed. If you might change the address later, point the code at a page you control (like your own website or a free Owlvix link-in-bio page) and change that page instead.'],
        ['Can I put my logo in the middle?', 'Yes. Add your logo and the code switches to High error correction, which lets it still scan with part of it covered. Keep the logo small or medium and test it with your phone before printing.'],
        ['What size should I print my QR code?', 'At least 2 cm (about 0.8 in) wide for a simple link, and bigger for Wi-Fi or contact codes, which hold more. For posters, make it about a tenth of the scanning distance — 30 cm wide to scan from 3 metres.'],
        ['PNG or SVG — which should I download?', 'PNG works everywhere: Word, Canva, social media. SVG is for printing and designers: it stays sharp at any size, from a business card to a billboard.'],
        ['How does the Wi-Fi QR code work?', 'Guests point their phone camera at it and tap to join — no typing the password. It works with the camera on iPhone and Android. Your password stays in the code; we never see it.'],
        ['Is my information private?', 'Yes. The code is made in your browser. Your links, Wi-Fi passwords and contact details are never sent to us, and your codes are kept only on this device under “My QR codes”.'],
    ];
@endphp

@section('title', 'Free QR Code Generator — Logo, Colours, No Expiry, No Sign-up')
@section('description', 'Make a free QR code for a link, Wi-Fi, contact card, WhatsApp, email, text or event. Add your logo and colours, download PNG or SVG. Never expires, no sign-up, nothing uploaded.')
@section('canonical', route('qr-code-generator'))
@section('no-auto-quote', '1')

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Free QR Code Generator', 'url' => route('qr-code-generator'), 'category' => 'DesignApplication', 'faq' => $faq])
@endsection

@section('content')
@unless ($editor ?? false)
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">QR code generator</span>
        </nav>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · Never expires · Nothing uploaded</span>
        <h1>Free <span class="accent">QR code generator</span> — with your logo</h1>
        <p class="lead">Links, Wi-Fi, contact cards, WhatsApp and more. Pick your colours, add a logo and a “Scan me” label, then download PNG or SVG. No sign-up, no watermark, and the codes never stop working.</p>
        @include('guides.partials.link')
    </div>
</section>
@endunless

<section class="calc-wrap">
    <div class="container">
        @unless ($editor ?? false)
        @include('partials.wizard-banner', [
            'say' => 'Hoo! Need a QR code? Let’s make yours — under a minute.',
            'title' => 'Let’s make <span class="accent">your QR code</span> together',
            'sub' => 'Pick what it opens, type your details and choose a style. It never expires — and nothing is uploaded.',
            'steps' => [['Pick what', 'it opens'], ['Your link', 'or details'], ['Style it, then', 'download']],
            'go' => 'Make my QR code',
            'pill' => 'Quick setup · under a minute',
        ])
        @endunless
        <div class="fb rb qb card" data-qb @if ($editorUrl = \App\Support\FreeTools::editorUrl(Route::currentRouteName())) data-editor-url="{{ $editorUrl }}" @endif>
            <div class="fb-top">
                <div class="fb-tpls"><span>Static QR code · works forever</span></div>
                <div class="fb-top__actions">
                    <button type="button" class="btn btn--sm btn--glow" data-dl="png">Download PNG</button>
                </div>
            </div>
            <div class="rb-filebar">
                <span class="fb-hint" data-saved>Saved on this device</span>
                <span class="ib-filebar__btns"><button type="button" class="rb-link" data-new>{!! \App\Support\Icons::i('plus') !!} New QR code</button><button type="button" class="rb-link" data-reset>Start again</button></span>
            </div>
            <details class="ib-mine qb-mine" data-mine hidden>
                <summary>My QR codes (<span data-mine-count>0</span>) <small>— kept on this device</small></summary>
                <ul data-mine-list></ul>
            </details>

            <div class="fb-layout rb-layout">
                <form class="fb-edit" data-form novalidate onsubmit="return false"></form>
                <div class="fb-prev-wrap">
                    <div class="qb-stage" data-stage aria-live="polite"></div>
                    <p class="qb-scan" data-scan hidden></p>
                    <div class="qb-dl">
                        <label class="sr-only" for="qb-px">PNG size</label><select id="qb-px" data-px></select>
                        <button type="button" class="btn btn--sm btn--glow" data-dl="png">Download PNG</button>
                        <button type="button" class="btn btn--sm btn--ghost" data-dl="svg">SVG</button>
                        <button type="button" class="btn btn--sm btn--ghost" data-copy>Copy</button>
                    </div>
                    <p class="qb-info" data-info></p>
                    <div class="rb-tips" data-tips-box hidden><b>Before you print</b><ul data-tips></ul></div>
                </div>
            </div>
            <input type="file" accept="image/jpeg,image/png,image/webp" data-logo-file hidden>
        </div>

        <div class="editor-hidden" @if (! empty($editor)) hidden @endif>
        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl'])</div>
            <div><h2>Need a page for your code to open?</h2><p>Make a free link-in-bio page or a digital menu, and point your QR code at it — change the page any time, the printed code keeps working.</p></div>
            <a href="{{ route('link-in-bio-builder') }}" class="btn btn--glow">Make my free page <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
        </div>
    </div>
</section>

@unless ($editor ?? false)
@include('tools.partials.faq', ['faq' => $faq, 'title' => 'QR codes, explained'])
@endunless

<script src="{{ \App\Support\Asset::url('js/qr.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/builder-wizard.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/qr-builder.js') }}" defer></script>
@endsection
