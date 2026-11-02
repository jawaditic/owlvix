{{-- Free booking page builder — the owner sets services, hours and rules here (public/js/booking-builder.js) and
     publishes a booking page at /book/{id} (resources/views/builder/booking.blade.php, public/js/booking-page.js).
     The preview is the real booking page running in the browser on the same slot rules, without bookings. --}}
@extends('layouts.page')

@php
    $faq = [
        ['Is the booking page builder really free?', 'Yes. No monthly fee, no card and no limit on bookings. Make up to five booking pages with one email sign-in, and share them as often as you like.'],
        ['How do clients book?', 'They open your link, pick a service, choose a free day and time, and type their name and email. They get a confirmation email straight away with a link to change or cancel, and you get an email too.'],
        ['Can people book a time I’m not free?', 'No. Only times inside your opening hours are shown, never on your days off, and a time disappears as soon as someone books it. You choose how much notice you need, how far ahead people can book, and a gap after each booking.'],
        ['What about time zones?', 'Your page uses your business’s time zone. If a client is somewhere else, the page tells them the times are in your zone and shows the time where they are, so nobody turns up an hour late.'],
        ['Can I put the booking page on my own website?', 'Yes. Copy the embed code from the Share tab and paste it into WordPress, Wix, Squarespace or Shopify. On an Owlvix website, paste your booking link into any “Book now” button.'],
        ['Where do I see my bookings?', 'In the Bookings tab, grouped by day, with each client’s email and phone. You can cancel a booking there (the client is emailed) and download everything as a spreadsheet (CSV).'],
        ['Do I need an account?', 'Only to publish. You build and preview your page without signing up; when you publish, we email you a 6-digit code — no password. Booking emails go to that address.'],
    ];
@endphp

@section('title', 'Free Booking Page Builder — Online Appointment Booking, No Fees')
@section('description', 'Make a free online booking page in minutes: your services, opening hours and rules. Clients pick a free time and book; you both get an email. Share a link, a QR code or put it on your website.')
@section('canonical', route('booking-page-builder'))
@section('no-auto-quote', '1')

@section('head')
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('tools.partials.schema', ['name' => 'Owlvix Free Booking Page Builder', 'url' => route('booking-page-builder'), 'category' => 'BusinessApplication', 'faq' => $faq])
@endsection

@section('content')
@unless ($editor ?? false)
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">Booking page builder</span>
        </nav>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · No fees · No sign-up to start</span>
        <h1>A free <span class="accent">booking page</span> for your business</h1>
        <p class="lead">Add your services and opening hours, and clients book a free time online — day or night. You both get an email, and you can share it as a link, a QR code or on your own website.</p>
        @include('guides.partials.link')
    </div>
</section>
@endunless

<section class="calc-wrap">
    <div class="container">
        @unless ($editor ?? false)
        @include('partials.wizard-banner', [
            'say' => 'Hoo! Want clients to book online? Let’s set it up — about 2 minutes.',
            'title' => 'Let’s make <span class="accent">your booking page</span> together',
            'sub' => 'Tell me what you do, what people can book and when you’re open. Watch your booking page come together as you go.',
            'steps' => [['Your type', 'of business'], ['Services', '& hours'], ['Publish and', 'share the link']],
            'go' => 'Start my booking page',
            'pill' => 'Quick setup · about 2 minutes',
        ])
        @endunless
        <div class="fb rb bk card" data-bk @if ($editorUrl = \App\Support\FreeTools::editorUrl(Route::currentRouteName())) data-editor-url="{{ $editorUrl }}" @endif>
            <div class="fb-top">
                <div class="fb-tabs" role="tablist" aria-label="Booking page builder">
                    <button type="button" role="tab" data-tab="build" aria-selected="true">{!! \App\Support\Icons::i('pen') !!} Build</button>
                    <button type="button" role="tab" data-tab="share" aria-selected="false">{!! \App\Support\Icons::i('link') !!} Share</button>
                    <button type="button" role="tab" data-tab="bookings" aria-selected="false">{!! \App\Support\Icons::i('calendar') !!} Bookings<span data-bk-count></span></button>
                </div>
                <div class="fb-top__actions">
                    <label class="sr-only" for="bk-mine">Open one of my booking pages</label>
                    <select id="bk-mine" class="fb-mine" data-mine hidden></select>
                    <button type="button" class="btn btn--sm btn--ghost" data-new>{!! \App\Support\Icons::i('plus') !!} New page</button>
                    <button type="button" class="btn btn--sm btn--glow" data-publish>Publish booking page</button>
                </div>
            </div>
            <p class="fb-status" data-status role="status"></p>

            <div data-panel="build">
                <div class="fb-layout rb-layout">
                    <form class="fb-edit" data-form novalidate onsubmit="return false"></form>
                    <div class="fb-prev-wrap">
                        <p class="fb-hint">Live preview — try booking a time</p>
                        <div class="rb-tips" data-tips-box hidden><b>Before you publish</b><ul data-tips></ul></div>
                        <div class="bk-stage" data-preview></div>
                    </div>
                </div>
            </div>
            <div data-panel="share" hidden><div class="fb-share" data-share></div></div>
            <div data-panel="bookings" hidden><div class="bk-bookings" data-bookings></div></div>
        </div>

        <div class="editor-hidden" @if (! empty($editor)) hidden @endif>
        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl'])</div>
            <div><h2>Need a website to go with it?</h2><p>Make a free one-page website for your business and put your booking link on a “Book now” button — no code.</p></div>
            <a href="{{ route('link-in-bio-builder') }}" class="btn btn--glow">Make my free page <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
        </div>
    </div>
</section>

@unless ($editor ?? false)
@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Online booking, explained'])
@endunless

<script src="{{ \App\Support\Asset::url('js/qr.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/builder-account.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/builder-wizard.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/booking-page.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/booking-builder.js') }}" defer></script>
@endsection
