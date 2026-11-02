{{-- Free invoice generator — runs fully in the browser (public/js/invoice-builder.js). Invoices, clients and bank
     details are never uploaded: they stay in this browser, and the PDF is made with the browser's own "Save as PDF". --}}
@extends('layouts.page')

@php
    $faq = [
        ['Is the invoice generator really free?', 'Yes. No sign-up, no watermark and no limit. Make as many invoices, quotes and receipts as you like and save them as PDF.'],
        ['Is my information private?', 'Yes. Everything happens in your browser and is saved only on your device — your clients, prices and bank details are never sent to us. Clearing your browser data removes them, so keep the PDFs you send.'],
        ['How do I download my invoice as a PDF?', 'Press “Download PDF”. A print window opens — choose “Save as PDF” as the printer and save. Tick “Background graphics” to keep the colours.'],
        ['What should an invoice include?', 'Your business name and contact details, your client’s name, a unique invoice number, the date, a due date, what you’re charging for with prices, any tax, the total, and how to pay. In many countries a VAT or tax invoice also needs your tax number.'],
        ['How do I number my invoices?', 'Give every invoice its own number, in order — for example INV-0001, INV-0002. Press “New invoice” and the next number is filled in for you, with your business details, tax and payment details kept.'],
        ['Can I make quotes, estimates and receipts too?', 'Yes. Change the type to Quote, Estimate, Receipt, Tax invoice or Proforma invoice. A quote shows “Valid until” instead of a due date, and a receipt shows a “Paid” stamp.'],
        ['Does it work with VAT, GST and sales tax?', 'Yes. Name your tax (VAT, GST, Sales tax), set the rate, and choose whether your prices already include it. Discounts, shipping and deposits are worked out for you in 50 currencies.'],
    ];
@endphp

@section('title', 'Free Invoice Generator — Make & Download PDF Invoices, No Sign-up')
@section('description', 'Make a professional invoice in minutes: your logo, VAT/GST and discounts, 50 currencies, 4 designs, and free PDF download. Quotes and receipts too. Private — nothing leaves your browser.')
@section('canonical', route('invoice-generator'))
@section('no-auto-quote', '1')

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Free Invoice Generator', 'url' => route('invoice-generator'), 'category' => 'BusinessApplication', 'faq' => $faq])
@endsection

@section('content')
@unless ($editor ?? false)
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">Invoice generator</span>
        </nav>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · Your details stay on your device</span>
        <h1>A <span class="accent">professional invoice</span> in minutes — free PDF</h1>
        <p class="lead">Add your logo, your client and what you’re charging for. Tax, discounts and totals are worked out for you. No sign-up, no watermark, nothing uploaded.</p>
        @include('guides.partials.link')
    </div>
</section>
@endunless

<section class="calc-wrap">
    <div class="container">
        @unless ($editor ?? false)
        @include('partials.wizard-banner', [
            'say' => 'Hoo! Need an invoice? I’ll make it with you — about 2 minutes.',
            'title' => 'Let’s make <span class="accent">your invoice</span> together',
            'sub' => 'Answer a few easy questions and watch your invoice fill in as you go. Your details stay on this device — nothing is uploaded.',
            'steps' => [['Pick a', 'design'], ['You, your client', '& your prices'], ['Preview, then', 'download PDF']],
            'go' => '🚀 Start my invoice',
            'pill' => 'Quick setup · about 2 minutes',
        ])
        @endunless
        <div class="fb rb ib card" data-ib @if ($editorUrl = \App\Support\FreeTools::editorUrl(Route::currentRouteName())) data-editor-url="{{ $editorUrl }}" @endif>
            <div class="fb-top">
                <div class="fb-tpls"><span>Design:</span><div data-designs></div></div>
                <div class="fb-top__actions">
                    <label class="rb-mini" title="Pick the invoice’s colour"><span class="sr-only">Colour</span><input type="color" data-look="color"></label>
                    <label class="sr-only" for="ib-font">Font</label><select id="ib-font" class="rb-font" data-look="font"></select>
                    <button type="button" class="btn btn--sm btn--glow" data-pdf>Download PDF</button>
                </div>
            </div>
            <div class="rb-filebar">
                <span class="fb-hint" data-saved>Saved on this device</span>
                <span class="ib-filebar__btns"><button type="button" class="rb-link" data-new>＋ New invoice</button><button type="button" class="rb-link" data-reset>Clear</button></span>
            </div>
            <details class="ib-mine" data-mine hidden>
                <summary>My invoices (<span data-mine-count>0</span>) <small>— kept on this device</small></summary>
                <ul data-mine-list></ul>
            </details>

            <div class="fb-layout rb-layout">
                <form class="fb-edit" data-form novalidate onsubmit="return false"></form>
                <div class="fb-prev-wrap">
                    <div class="rb-tips" data-tips-box hidden><b>Before you send</b><ul data-tips></ul></div>
                    <div class="rb-screen"><iframe data-preview title="Your invoice — A4 preview" sandbox="allow-same-origin"></iframe></div>
                </div>
            </div>
            <input type="file" accept="image/jpeg,image/png,image/webp" data-logo-file hidden>
        </div>

        <div class="editor-hidden" @if (! empty($editor)) hidden @endif>
        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl'])</div>
            <div><h2>Want clients to pay online?</h2><p>Make a free online store with card payments, or a website with your prices and a contact form — no code.</p></div>
            <a href="{{ route('store-builder') }}" class="btn btn--glow">Build my store <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
        </div>
    </div>
</section>

@unless ($editor ?? false)
@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Invoices, explained'])
@endunless

<script src="{{ \App\Support\Asset::url('js/builder-wizard.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/invoice-builder.js') }}" defer></script>
@endsection
