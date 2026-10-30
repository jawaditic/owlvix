{{-- Free form builder: design a form in the browser (public/js/form-builder.js), publish it to /form/{id}, share or
     embed it, and read the answers. Server side: BuilderController (surveys…) and BuilderPublicController::survey. --}}
@extends('layouts.page')

@php
    $faq = [
        ['Is the form builder really free?', 'Yes. Make up to 20 forms with up to 30 questions each, and get up to 500 answers a day per form. No payment and no ads on your forms.'],
        ['Where do the answers go?', 'Every answer is emailed to you and saved in the Responses tab, where you can see them in a table and download them as a CSV file for Excel or Google Sheets.'],
        ['Can I put the form on my website?', 'Yes. In the Share tab copy the embed code and paste it into your page — it works on WordPress, Wix, Squarespace, Shopify, Webflow and the Owlvix page builder. Or just share the link.'],
        ['What kinds of questions can I ask?', 'Short and long answers, email, phone, number, date, dropdowns, multiple choice, checkboxes and 1–5 ratings — plus section titles to split long forms into parts.'],
        ['Can I change the form after sharing it?', 'Yes. Edit it and press “Save changes” — the link stays the same and new visitors see the new version straight away.'],
        ['Is it safe?', 'Forms are protected against spam bots and floods. For everyone’s safety, forms can’t ask for passwords or card details. You can delete a form and all its answers at any time.'],
    ];
@endphp

@section('title', 'Free Form Builder — Surveys, RSVPs, Order & Contact Forms')
@section('description', 'Make free online forms and surveys: contact, feedback, RSVP, job application, quote and order forms. Share a link or embed it on your site. Answers by email and CSV.')
@section('canonical', route('form-builder'))
@section('no-auto-quote', '1')

@section('head')
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('tools.partials.schema', ['name' => 'Owlvix Free Form Builder', 'url' => route('form-builder'), 'category' => 'BusinessApplication', 'faq' => $faq])
@endsection

@section('content')
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">Form builder</span>
        </nav>
        <div class="page-owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'desk', 'alt' => 'Owlvix owl making a form'])</div>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · No sign-up to start</span>
        <h1>Make a <span class="accent">form or survey</span> in minutes</h1>
        <p class="lead">Contact forms, feedback surveys, RSVPs, job applications and order forms. Share the link or put it on your website — every answer is emailed to you.</p>
    </div>
</section>

<section class="calc-wrap">
    <div class="container">
        <div class="fb card" data-fb>
            <div class="fb-top">
                <div class="fb-tabs" role="tablist">
                    <button type="button" role="tab" data-tab="build" aria-selected="true">✏️ Build</button>
                    <button type="button" role="tab" data-tab="share" aria-selected="false">🔗 Share</button>
                    <button type="button" role="tab" data-tab="responses" aria-selected="false">📊 Responses</button>
                </div>
                <div class="fb-top__actions">
                    <label class="sr-only" for="fb-mine">Open one of my forms</label>
                    <select id="fb-mine" class="fb-mine" data-mine hidden></select>
                    <button type="button" class="btn btn--sm btn--ghost" data-new>＋ New form</button>
                    <button type="button" class="btn btn--sm btn--glow" data-publish>Publish form</button>
                </div>
            </div>
            <p class="fb-status" data-status></p>
            <p class="fb-note" data-note role="status" hidden></p>

            <div data-panel="build">
                <div class="fb-tpls"><span>Start from:</span><div data-templates></div></div>
                <div class="fb-layout">
                    <div class="fb-edit">
                        <details class="fb-settings" open><summary>Form title &amp; settings</summary>
                            <label>Title<input data-set="title" maxlength="120"></label>
                            <label>Intro text (optional)<textarea data-set="intro" rows="2" maxlength="1000"></textarea></label>
                            <div class="fb-2">
                                <label>Button text<input data-set="button" maxlength="40"></label>
                                <label>Colour<input data-set="color" type="color"></label>
                            </div>
                            <label>Thank-you message<input data-set="thanks" maxlength="300"></label>
                        </details>
                        <h3 class="fb-sub">Questions</h3>
                        <div class="fb-fields" data-fields></div>
                        <h3 class="fb-sub">Add a question</h3>
                        <div class="fb-add" data-add-types></div>
                    </div>
                    <div class="fb-prev-wrap">
                        <p class="fb-hint">Preview — click a question to edit it</p>
                        <div class="fb-prev" data-preview></div>
                    </div>
                </div>
            </div>
            <div data-panel="share" hidden><div class="fb-share" data-share></div></div>
            <div data-panel="responses" hidden><div data-responses></div></div>

            <dialog class="lpb-dialog" data-dialog aria-label="Sign in">
                <button type="button" class="lpb-dialog__x" data-dialog-close aria-label="Close">×</button>
                <div data-dialog-body></div>
            </dialog>
        </div>

        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl'])</div>
            <div><h2>Need more than a form?</h2><p>We build booking systems, customer portals and apps — with payments, logins and dashboards. Start free: we plan the first version at no cost.</p></div>
            <a href="{{ route('service.landing-page-design') }}#contact" class="btn btn--glow" data-quote>Talk to us <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'The form builder, explained'])

<script src="{{ \App\Support\Asset::url('js/form-builder.js') }}" defer></script>
@endsection
