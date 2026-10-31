{{-- Free resume / CV builder — runs fully in the browser (public/js/resume-builder.js). Personal details are never
     uploaded: they stay in this browser, and the PDF is made with the browser's own "Save as PDF". --}}
@extends('layouts.page')

@php
    $faq = [
        ['Is the CV builder really free?', 'Yes. No sign-up, no watermark and no “pay to download”. Make as many CVs as you like and save them as PDF.'],
        ['Is my information private?', 'Yes. Everything happens in your browser and is saved only on your device. Your name, phone number and work history are never sent to us.'],
        ['How do I download my CV as a PDF?', 'Press “Download PDF”. A print window opens — choose “Save as PDF” as the printer and save. Make sure “Margins” is set to “None” or “Default” and “Background graphics” is ticked to keep the colours.'],
        ['Will my CV get past recruiters’ software (ATS)?', 'The PDF has real, selectable text with clear headings, which applicant tracking systems read well. Use the exact words from the job advert in your skills and bullet points where they are true for you.'],
        ['Should I add a photo?', 'In the UK, US, Canada and Australia most employers prefer no photo. In much of Europe, the Middle East and Asia a professional photo is common. The builder works either way.'],
        ['How long should my CV be?', 'One page if you have under 10 years of experience, two pages at most for most jobs. Focus on your last 10 years and on results, not duties.'],
    ];
@endphp

@section('title', 'Free Resume & CV Builder — Download as PDF, No Sign-up')
@section('description', 'Make a professional CV or resume in minutes: 5 designs, live A4 preview, tips to pass ATS scans, and free PDF download. Private — your details never leave your browser.')
@section('canonical', route('resume-builder'))
@section('no-auto-quote', '1')

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Free Resume & CV Builder', 'url' => route('resume-builder'), 'category' => 'BusinessApplication', 'faq' => $faq])
@endsection

@section('content')
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">Resume / CV builder</span>
        </nav>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · Your details stay on your device</span>
        <h1>A <span class="accent">professional CV</span> in minutes — free PDF</h1>
        <p class="lead">Fill in your details, pick a design and download a clean, recruiter-friendly CV as PDF. No sign-up, no watermark, nothing uploaded.</p>
    </div>
</section>

<section class="calc-wrap">
    <div class="container">
        <div class="fb rb card" data-rb>
            <div class="fb-top">
                <div class="fb-tpls"><span>Design:</span><div data-designs></div></div>
                <div class="fb-top__actions">
                    <label class="rb-mini" title="Accent colour"><span class="sr-only">Accent colour</span><input type="color" data-look="color"></label>
                    <label class="sr-only" for="rb-font">Font</label><select id="rb-font" class="rb-font" data-look="font"></select>
                    <button type="button" class="btn btn--sm btn--glow" data-pdf>Download PDF</button>
                </div>
            </div>
            <div class="rb-filebar">
                <span class="fb-hint" data-saved>Saved on this device</span>
                <span><button type="button" class="rb-link" data-save-file>Save file</button> · <button type="button" class="rb-link" data-open-file>Open file</button> · <button type="button" class="rb-link" data-reset>Start over</button></span>
            </div>

            <div class="fb-layout rb-layout">
                <form class="fb-edit" data-form novalidate onsubmit="return false"></form>
                <div class="fb-prev-wrap">
                    <div class="rb-tips" data-tips-box hidden><b>Tips to stand out</b><ul data-tips></ul></div>
                    <div class="rb-screen"><iframe data-preview title="Your CV — A4 preview" sandbox="allow-same-origin"></iframe></div>
                </div>
            </div>
            <input type="file" accept="image/jpeg,image/png,image/webp" data-photo-file hidden>
            <input type="file" accept=".json,application/json" data-open-input hidden>
        </div>

        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl'])</div>
            <div><h2>Want a personal website too?</h2><p>Show your work and get found by recruiters with a portfolio site — build one free, or let us design it for you.</p></div>
            <a href="{{ route('portfolio-builder') }}" class="btn btn--glow">Build my portfolio <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'CVs and resumes, explained'])

<script src="{{ \App\Support\Asset::url('js/resume-builder.js') }}" defer></script>
@endsection
