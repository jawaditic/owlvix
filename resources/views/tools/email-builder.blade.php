{{-- Free email template / newsletter builder — runs fully in the browser (public/js/email-builder.js). Makes
     email-safe HTML to paste into Mailchimp, Brevo, Klaviyo and similar tools. Nothing is uploaded. --}}
@extends('layouts.page')

@php
    $faq = [
        ['Is the email template builder free?', 'Yes, completely. No sign-up, no limits and no branding in your emails. Everything happens in your browser — your email is never uploaded to us.'],
        ['How do I use it in Mailchimp?', 'Press “Copy HTML”. In Mailchimp create an email, choose “Code your own” → “Paste in code”, paste and save. Pick “Mailchimp” under Settings → Unsubscribe link first, so the unsubscribe link works.'],
        ['How do I use it in Brevo or Klaviyo?', 'Brevo: Campaigns → Create → “Paste your code”. Klaviyo: Templates → Create → “Import HTML” or a code block. Choose your platform under Settings so the unsubscribe link is filled in for you.'],
        ['Why don’t my images show?', 'Emails can only show images that are online. Upload them to your email platform’s image library or your website and paste the address starting with https://. Google Drive and Dropbox links don’t work.'],
        ['Will it look right in Outlook and on phones?', 'Yes. It is built the way email apps expect — tables and inline styles, 600 pixels wide, with columns that stack on phones. Always send yourself a test before sending to your list.'],
        ['Can I send emails from here?', 'No — this tool makes the design. Send it with your email platform, which handles your list, unsubscribes and delivery. Need help with that? We set up email marketing too.'],
    ];
@endphp

@section('title', 'Free Email Template Builder — Newsletter & Marketing Email HTML')
@section('description', 'Design newsletters and marketing emails from simple blocks, then copy the HTML into Mailchimp, Brevo or Klaviyo. Outlook-safe, mobile-friendly. Free, no sign-up.')
@section('canonical', route('email-builder'))
@section('no-auto-quote', '1')

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Email Template Builder', 'url' => route('email-builder'), 'category' => 'DesignApplication', 'faq' => $faq])
@endsection

@section('content')
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">Email template builder</span>
        </nav>
        <div class="page-owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'desk', 'alt' => 'Owlvix owl writing an email'])</div>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · Nothing is uploaded</span>
        <h1>Design <span class="accent">newsletters &amp; emails</span> that look great everywhere</h1>
        <p class="lead">Start from a template, change the words, pictures and colours, then copy the HTML into Mailchimp, Brevo or Klaviyo. Works in Outlook, Gmail and on phones.</p>
    </div>
</section>

<section class="calc-wrap">
    <div class="container">
        <div class="fb eb card" data-eb>
            <div class="fb-top">
                <div class="fb-tpls"><span>Start from:</span><div data-templates></div></div>
                <div class="fb-top__actions">
                    <div class="eb-devices" role="group" aria-label="Preview size">
                        <button type="button" data-device="desktop" aria-pressed="true" title="Desktop">🖥</button>
                        <button type="button" data-device="mobile" aria-pressed="false" title="Phone">📱</button>
                    </div>
                    <button type="button" class="btn btn--sm btn--ghost" data-download>Download .html</button>
                    <button type="button" class="btn btn--sm btn--glow" data-copy>Copy HTML</button>
                </div>
            </div>
            <p class="fb-note" data-note role="status" hidden></p>

            <div class="fb-layout eb-layout">
                <div class="fb-edit">
                    <details class="fb-settings" open><summary>Settings</summary>
                        <label>Subject line<input data-set="subject" maxlength="150" placeholder="What people see in their inbox"></label>
                        <label>Preview text (optional)<input data-set="preheader" maxlength="200" placeholder="The grey line after the subject in the inbox"></label>
                        <div class="fb-2">
                            <label>Main colour<input data-set="color" type="color"></label>
                            <label>Text colour<input data-set="ink" type="color"></label>
                        </div>
                        <div class="fb-2">
                            <label>Background<input data-set="bg" type="color"></label>
                            <label>Email box<input data-set="card" type="color"></label>
                        </div>
                        <label>Font<select data-set="font"></select></label>
                        <label>Unsubscribe link for<select data-set="platform"></select></label>
                        <label data-unsub-url hidden>Your unsubscribe link<input data-set="unsubUrl" maxlength="500" placeholder="https://…" inputmode="url"></label>
                    </details>
                    <h3 class="fb-sub">Blocks <small class="fb-hint">— click one in the preview to edit it</small></h3>
                    <div class="fb-fields" data-blocks></div>
                    <h3 class="fb-sub">Add a block</h3>
                    <div class="fb-add" data-add></div>
                </div>
                <div class="fb-prev-wrap">
                    <ul class="eb-issues" data-issues></ul>
                    <div class="eb-screen" data-device="desktop"><iframe data-preview title="Email preview"></iframe></div>
                </div>
            </div>
        </div>

        <div class="card esig-how eb-how">
            <h2>Send it with your email platform</h2>
            <div class="esig-tabs" role="tablist">
                <button type="button" role="tab" aria-selected="true" data-how="mailchimp">Mailchimp</button>
                <button type="button" role="tab" aria-selected="false" data-how="brevo">Brevo</button>
                <button type="button" role="tab" aria-selected="false" data-how="klaviyo">Klaviyo</button>
                <button type="button" role="tab" aria-selected="false" data-how="other">Other / Gmail</button>
            </div>
            <ol data-how-panel="mailchimp"><li>Under Settings choose <b>Mailchimp</b> as the unsubscribe link, then press <b>Copy HTML</b>.</li><li>In Mailchimp create an email and pick <b>Code your own → Paste in code</b>.</li><li>Paste, save, and send yourself a test.</li></ol>
            <ol data-how-panel="brevo" hidden><li>Choose <b>Brevo</b> as the unsubscribe link, then press <b>Copy HTML</b>.</li><li>In Brevo go to <b>Campaigns → Create</b> and pick <b>Paste your code</b>.</li><li>Paste, save, and send a test.</li></ol>
            <ol data-how-panel="klaviyo" hidden><li>Choose <b>Klaviyo</b> as the unsubscribe link, then press <b>Copy HTML</b>.</li><li>In Klaviyo go to <b>Content → Templates → Create → Import HTML</b> (or add an HTML block).</li><li>Paste, save, and preview it.</li></ol>
            <ol data-how-panel="other" hidden><li>Most email tools (MailerLite, ConvertKit, HubSpot, ActiveCampaign) have an <b>HTML / code</b> editor — paste it there.</li><li>Gmail and Outlook can’t send designed emails to a list: use an email platform for newsletters.</li><li>Choose <b>My own link</b> for unsubscribe if your tool isn’t listed, and paste your tool’s unsubscribe address.</li></ol>
        </div>

        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl'])</div>
            <div><h2>Want email marketing that sells?</h2><p>We set up your email platform, welcome series and automations — and design templates that match your brand.</p></div>
            <a href="{{ route('service.landing-page-design') }}#contact" class="btn btn--glow" data-quote>Talk to us <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Email templates, explained'])

<script src="{{ \App\Support\Asset::url('js/email-builder.js') }}" defer></script>
@endsection
