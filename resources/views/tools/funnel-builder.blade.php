{{-- Free funnel builder: the page builder editor (public/js/landing-builder.js) in funnel mode — steps, opt-in forms,
     payment links, A/B tests and a Results tab. Server parts are under /builder and /f, /fs. --}}
@extends('layouts.page')

@php
    $faq = [
        ['What is a sales funnel?', 'A series of pages that walks visitors to one goal — for example a free guide sign-up, then a thank-you page with an offer, then a checkout. Each step has one job and one clear next step, so more visitors take action.'],
        ['Is the funnel builder really free?', 'Yes. Build funnels, collect leads, see your stats and run A/B tests for free. A small "made with" link sits in the footer.'],
        ['How do I take payments?', 'Paste your own Stripe Payment Link, PayPal, Gumroad or any checkout link into a step. Buy buttons go there, and your payment provider sends buyers back to your thank-you step. Money goes straight to you — never through Owlvix.'],
        ['Where do my leads go?', 'Every sign-up is emailed to you, saved in the Results tab (download as CSV), and can be sent automatically to Mailchimp, Brevo or any webhook (Zapier, Make, your CRM).'],
        ['How does A/B testing work?', 'Turn on A/B for a step and edit a second version. Visitors see version A or B at random, and the Results tab shows which one gets more sign-ups — then keep the winner with one click.'],
    ];
@endphp

@section('title', 'Free Funnel Builder — Sales Funnels, Opt-ins & A/B Tests')
@section('description', 'Free sales funnel builder: opt-in, sales, upsell and thank-you pages. Collect leads, connect Mailchimp or Brevo, take payments and A/B test every step.')
@section('canonical', route('funnel-builder'))
@section('no-auto-quote', '1')

@section('head')
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('tools.partials.schema', ['name' => 'Owlvix Free Funnel Builder', 'url' => route('funnel-builder'), 'category' => 'DesignApplication', 'faq' => $faq])
@endsection

@section('content')
<section class="hero speed-hero lpb-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">Funnel builder</span>
        </nav>
        <div class="page-owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl building a funnel'])</div>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · No sign-up to start</span>
        <h1>Build a <span class="accent">sales funnel</span> that converts</h1>
        <p class="lead">Opt-in, sales, upsell and thank-you pages linked step by step — collect leads, take payments with your own Stripe or PayPal link, and A/B test what works. Free.</p>
    </div>
</section>

<section class="lpb-app" data-lpb data-mode="funnel" data-stats-url="{{ url('/fs') }}" data-runtime="{{ \App\Support\BuilderHtml::runtimeUrl() }}" data-site-base="{{ \App\Models\BuilderSite::base() }}" data-qr="{{ \App\Support\Asset::url('js/qr.js') }}">
    <div class="lpb-top">
        <div class="lpb-top__group">
            <label class="sr-only" for="lpb-pages">Funnel step</label>
            <select id="lpb-pages" data-pages class="lpb-select"></select>
            <button type="button" class="lpb-tb" data-act="add-page" title="Add a funnel step">＋ Step</button>
            <span class="lpb-vbadge" data-variant-badge hidden></span>
        </div>
        <div class="lpb-top__group lpb-devices" role="group" aria-label="Preview size">
            <button type="button" data-device="desktop" aria-pressed="true" title="Desktop">🖥</button>
            <button type="button" data-device="tablet" aria-pressed="false" title="Tablet">▭</button>
            <button type="button" data-device="mobile" aria-pressed="false" title="Mobile">📱</button>
        </div>
        <div class="lpb-top__group">
            <button type="button" class="lpb-tb" data-act="undo" title="Undo (Ctrl/Cmd+Z)">↩ Undo</button>
            <button type="button" class="lpb-tb" data-act="redo" title="Redo (Ctrl/Cmd+Shift+Z)">↪ Redo</button>
            <button type="button" class="lpb-tb" data-act="preview" title="Open a preview in a new tab">👁 Preview</button>
            <button type="button" class="lpb-tb" data-act="open" title="Open a saved project">📂 Open</button>
            <button type="button" class="lpb-tb" data-act="save" title="Download a project file to keep editing later">💾 Save project</button>
        </div>
        <div class="lpb-top__group">
            <button type="button" class="btn btn--sm btn--ghost" data-act="download">Download ↓</button>
            <button type="button" class="btn btn--sm btn--glow" data-act="publish">Publish 🚀</button>
            <button type="button" class="lpb-tb lpb-full-btn" data-act="fullscreen" title="Full screen (Esc to exit)" aria-pressed="false">
                <svg viewBox="0 0 24 24" aria-hidden="true" class="i-enter"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
                <svg viewBox="0 0 24 24" aria-hidden="true" class="i-exit"><path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/></svg>
                <span>Full screen</span>
            </button>
        </div>
    </div>

    <div class="lpb-body">
        <aside class="lpb-side">
            <div class="lpb-tabs" role="tablist">
                <button type="button" role="tab" data-tab="add" aria-selected="true">＋ Add</button>
                <button type="button" role="tab" data-tab="edit" aria-selected="false">Edit</button>
                <button type="button" role="tab" data-tab="page" aria-selected="false">Steps</button>
                <button type="button" role="tab" data-tab="site" aria-selected="false">Site</button>
                <button type="button" role="tab" data-tab="results" aria-selected="false">Results</button>
            </div>
            <div class="lpb-panel is-active" data-tab-panel="add">
                <input type="search" class="lpb-search" data-add-search placeholder="Search widgets &amp; templates…" aria-label="Search widgets and templates">
                <p class="lpb-hint">Drag onto the page — or click to add below the selected item.</p>
                <div data-add-list></div>
            </div>
            <div class="lpb-panel" data-tab-panel="edit">
                <div class="lpb-etabs" role="tablist">
                    <button type="button" role="tab" data-etab="content" aria-selected="true">Content</button>
                    <button type="button" role="tab" data-etab="style" aria-selected="false">Style</button>
                    <button type="button" role="tab" data-etab="advanced" aria-selected="false">Advanced</button>
                </div>
                <div data-edit-panel></div>
            </div>
            <div class="lpb-panel" data-tab-panel="page"><div data-page-panel></div></div>
            <div class="lpb-panel" data-tab-panel="site"><div data-site-panel></div></div>
            <div class="lpb-panel" data-tab-panel="results"><div data-results-panel></div></div>
            <p class="lpb-autosave" data-autosave>Saved on this device</p>
        </aside>
        <div class="lpb-canvas">
            <div class="lpb-screen" data-device="desktop">
                <iframe data-lpb-frame title="Your page — click text or images to edit"></iframe>
            </div>
        </div>
    </div>

    <input type="file" accept="image/*" data-pick-image hidden>
    <input type="file" accept=".json,application/json" data-open-file hidden>
    <div class="lpb-toast" data-toast role="status" hidden></div>

    <dialog class="lpb-dialog" data-builder-dialog aria-label="Builder account">
        <button type="button" class="lpb-dialog__x" data-dialog-close aria-label="Close">×</button>
        <div data-dialog-body></div>
    </dialog>
</section>

<section class="lpb-below">
    <div class="container">
        <div class="lpb-after card" data-lpb-after hidden>
            <span class="perch-owl" aria-hidden="true">@include('partials.live-owl', ['width' => 96, 'variant' => 'desk', 'alt' => 'Owlvix owl'])</span>
            <h2>🎉 Your funnel is downloaded</h2>
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
            <div><h2>Want a funnel built for you?</h2><p>We plan, write and build high-converting funnels — with checkout, email follow-ups and tracking. Start free: we design the first version at no cost.</p></div>
            <a href="{{ route('service.landing-page-design') }}#contact" class="btn btn--glow" data-quote data-lpb-quote>Get a custom funnel <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Sales funnels, explained'])

<script src="{{ \App\Support\Asset::url('js/builder-engine.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/landing-builder.js') }}" defer></script>
@endsection
