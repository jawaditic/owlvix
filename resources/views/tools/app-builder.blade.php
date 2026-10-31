{{-- Free mobile app builder: design a native Android app from screens and blocks (public/js/app-builder.js). The
     design is kept in the browser; saving publishes it to phones and building uses the page builder's email sign-in
     (AppBuilderController). Builds run on GitHub Actions (.github/workflows/build-android-app.yml, app-shell/). --}}
@extends('layouts.page')

@php
    $faq = [
        ['Is the mobile app builder really free?', 'Yes. Design your app, build it for Android and download the files free — up to 3 apps and 5 builds a day. The only cost is Google’s own one-time $25 fee if you want to publish on Google Play.'],
        ['Is it a real native app or just a website?', 'A real native app. Every screen is built from native Android parts — banners, text, pictures, buttons, menus and price lists, galleries, contact buttons, opening hours and videos — not a web page. You can still add a tab that shows a page of your website (a shop or booking page, for example) if you want to.'],
        ['Do I need a new build when I change something?', 'No. When you save, phones that have your app show the new screens, tabs and colours within a minute — even after it’s on Google Play. The app also keeps a copy, so it opens and works without internet. Build again only to change the app’s name, icon or splash screen.'],
        ['Do I need a website?', 'No. Build your app from blocks alone. If you want a tab to show a page of your own website, add its address — any https:// website you own. For sites not made with Owlvix, you add a small tag to your home page to show it’s yours.'],
        ['How do I put my app on Google Play?', 'Make a Google Play developer account (one-time $25), create an app, fill in the store listing, then upload the .aab file we make for you. The .apk file is for trying the app on your own phone first.'],
        ['Can I get it on the iPhone App Store?', 'Apple only accepts apps sent from the owner’s own Apple developer account ($99 a year), built on a Mac. We can do this for you as a paid service, with logins, payments and push notifications if you need them — ask for a free quote.'],
        ['How do I update my app on Google Play?', 'Screens, tabs and colours update by themselves when you save. To change the icon, name or splash screen, edit them here, raise the version number, build again, then upload the new .aab to Google Play.'],
    ];
@endphp

@section('title', 'Free Mobile App Builder — Make a Native Android App, No Code')
@section('description', 'Make a real native Android app for free: drag in banners, menus, galleries, contact buttons and opening hours. Changes go live without rebuilding. Get the APK and AAB for Google Play.')
@section('canonical', route('app-builder'))
@section('no-auto-quote', '1')

@section('head')
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('tools.partials.schema', ['name' => 'Owlvix Mobile App Builder', 'url' => route('app-builder'), 'category' => 'DeveloperApplication', 'faq' => $faq])
@endsection

@section('content')
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">Mobile app builder</span>
        </nav>
        <div class="page-owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl building an app'])</div>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · Native Android app</span>
        <h1>Make your own <span class="accent">mobile app</span></h1>
        <p class="lead">Build your screens from native blocks — banner, menu and prices, gallery, contact buttons, opening hours — and we build a real Android app for you, ready for your phone and Google Play. Change it any time: phones update without a new build.</p>
    </div>
</section>

<section class="calc-wrap">
    <div class="container">
        <div class="fb ab card" data-ab data-qr="{{ \App\Support\Asset::url('js/qr.js') }}" data-site-builder="{{ route('website-builder') }}">
            <div class="fb-top">
                <div class="fb-tabs" role="tablist">
                    <button type="button" role="tab" data-tab="design" aria-selected="true">✏️ Design</button>
                    <button type="button" role="tab" data-tab="build" aria-selected="false">📦 Build &amp; download</button>
                </div>
                <div class="fb-top__actions">
                    <label class="sr-only" for="ab-mine">Open one of my apps</label>
                    <select id="ab-mine" class="fb-mine" data-mine hidden></select>
                    <button type="button" class="btn btn--sm btn--ghost" data-new>＋ New app</button>
                    <button type="button" class="btn btn--sm btn--glow" data-save>Save app</button>
                </div>
            </div>
            <p class="fb-status" data-status></p>
            <p class="fb-note" data-note role="status" hidden></p>

            <div class="fb-layout ab-layout">
                <div class="fb-edit">
                    <div data-panel="design">
                        <details class="fb-settings ab-tpl" data-tpl-wrap open><summary>Start from a template</summary>
                            <p class="fb-hint">Pick a ready-made app, then change the words and add your own pictures. Your name, icon and settings stay.</p>
                            <div class="ab-tpls" data-tpls></div>
                        </details>

                        <details class="fb-settings" open><summary>1. Name &amp; icon</summary>
                            <label>App name <small>— shown under the icon on the phone</small><input data-a="name" maxlength="30" placeholder="My Bakery"></label>
                            <div class="ab-iconrow">
                                <canvas class="ab-iconprev" width="120" height="120" data-icon-prev aria-label="Your app icon"></canvas>
                                <div class="ab-iconctl">
                                    <div class="ab-seg" role="group" aria-label="Icon type">
                                        <button type="button" data-icon-mode="letter">Letters</button>
                                        <button type="button" data-icon-mode="image">My logo</button>
                                    </div>
                                    <label data-icon-letter-wrap>Letters<input data-i="letter" maxlength="2" placeholder="B"></label>
                                    <label class="ab-drop" data-icon-file-wrap hidden>
                                        <input type="file" accept="image/png,image/jpeg,image/webp" data-icon-file class="sr-only">
                                        <span class="ab-drop__ic" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg></span>
                                        <span class="ab-drop__txt"><b data-icon-file-title>Upload your logo</b><small>Click or drop a PNG, JPG or WebP · square, at least 512 × 512 px</small></span>
                                    </label>
                                    <label data-icon-zoom-wrap hidden>Size<input type="range" min="40" max="100" data-i="zoom"></label>
                                    <label class="fb-check" data-icon-fill-wrap hidden><input type="checkbox" data-i="fill"> Fill the whole square</label>
                                </div>
                            </div>
                        </details>

                        <details class="fb-settings" open><summary>2. Colours</summary>
                            <div class="fb-2">
                                <label>Main colour <small>— buttons, active tab</small><input type="color" data-c="primary"></label>
                                <label>Top &amp; bottom bars<input type="color" data-c="bar"></label>
                                <label>Icon background<input type="color" data-c="icon"></label>
                            </div>
                        </details>

                        <details class="fb-settings ab-splash" open><summary>3. Splash screen</summary>
                            <p class="fb-hint">What people see for a moment while your app opens. <button type="button" class="ab-link" data-view-splash>See it in the preview</button></p>
                            <div class="fb-2">
                                <label>Background colour<input type="color" data-c="splash"></label>
                                <div class="ab-field"><span>Picture size</span>
                                    <div class="ab-seg" role="group" aria-label="Splash picture size">
                                        <button type="button" data-splash-size="small">Small</button>
                                        <button type="button" data-splash-size="medium">Medium</button>
                                        <button type="button" data-splash-size="large">Large</button>
                                    </div>
                                </div>
                            </div>
                            <div class="ab-field"><span>Picture</span>
                                <div class="ab-seg" role="group" aria-label="Splash picture">
                                    <button type="button" data-splash-mode="icon">My app icon</button>
                                    <button type="button" data-splash-mode="logo">Logo only</button>
                                    <button type="button" data-splash-mode="image">Another picture</button>
                                </div>
                            </div>
                            <label class="ab-drop" data-splash-file-wrap hidden>
                                <input type="file" accept="image/png,image/jpeg,image/webp" data-splash-file class="sr-only">
                                <span class="ab-drop__ic" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg></span>
                                <span class="ab-drop__txt"><b data-splash-file-title>Upload a picture</b><small>A logo or wordmark — PNG with a see-through background looks best</small></span>
                            </label>
                            <p class="fb-hint" data-splash-note hidden></p>
                            <div class="ab-field"><span>Full-screen intro <small>— after the splash, once each time the app opens</small></span>
                                <div class="ab-seg" role="group" aria-label="Full-screen intro">
                                    <button type="button" data-bg-mode="color">None</button>
                                    <button type="button" data-bg-mode="image">Picture</button>
                                    <button type="button" data-bg-mode="video">Video</button>
                                </div>
                            </div>
                            <label class="ab-drop" data-bg-file-wrap hidden>
                                <input type="file" data-bg-file class="sr-only">
                                <span class="ab-drop__ic" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg></span>
                                <span class="ab-drop__txt"><b data-bg-file-title>Upload</b><small data-bg-file-hint></small></span>
                            </label>
                            <p class="fb-hint" data-bg-note hidden></p>
                        </details>

                        <details class="fb-settings" open><summary>4. Pages &amp; menu</summary>
                            <p class="fb-hint">Up to 15 pages, each a native screen you build from blocks (or a page of your website). For each page choose where its link goes: the bottom bar (up to 5), the ☰ menu at the top, or both.</p>
                            <label>☰ Menu at the top
                                <select data-menu>
                                    <option value="show">Show it — with the pages ticked “☰ Menu” below</option>
                                    <option value="hide">Don’t show it</option>
                                </select>
                            </label>
                            <p class="ab-err" data-menu-note hidden></p>
                            <div class="ab-tabs" data-tabs></div>
                            <button type="button" class="btn btn--sm btn--ghost" data-add-tab>＋ Add page</button>
                        </details>

                        <details class="fb-settings ab-screen" open><summary>5. Screen: <span data-screen-name>Home</span></summary>
                            <div class="ab-blocks" data-blocks></div>
                            <details class="ab-add" data-add-block><summary class="btn btn--sm btn--glow">＋ Add block</summary><div class="ab-add__grid" data-add-types></div></details>
                            <input type="file" accept="image/png,image/jpeg,image/webp" data-photo-file hidden>
                        </details>

                        <details class="fb-settings"><summary>6. More settings</summary>
                            <label data-site-pick hidden>Pick one of your sites<select data-site></select></label>
                            <label>Your website <small>— only needed for tabs that show a page of it</small><input data-a="url" type="url" inputmode="url" placeholder="https://www.yourbusiness.com" maxlength="500" autocomplete="url"></label>
                            <label>Links to other websites <small>— on website tabs</small><select data-a="external_links"><option value="browser">Open in the phone’s browser (recommended)</option><option value="app">Open inside the app</option></select></label>
                            <div class="fb-2">
                                <label>Version<input data-a="version_name" maxlength="11" placeholder="1.0.0"></label>
                                <label>App ID <small>— can’t change after the first build</small><input data-a="package" maxlength="100" placeholder="com.yourbusiness.app" spellcheck="false" autocapitalize="off"></label>
                            </div>
                            <label>Offline title <small>— shown when a website tab can’t load</small><input data-o="title" maxlength="60" placeholder="You’re offline"></label>
                            <label>Offline message<input data-o="text" maxlength="200" placeholder="Check your internet connection and try again."></label>
                            <label>Offline button<input data-o="button" maxlength="30" placeholder="Try again"></label>
                            <h3 class="ab-sub">Privacy policy <small>— Google Play asks every app for one; we host it for you</small></h3>
                            <div class="fb-2">
                                <label>Business or your name<input data-pv="owner" maxlength="100" placeholder="e.g. Joe’s Bakery Ltd"></label>
                                <label>Contact email <small>— shown on the policy</small><input data-pv="email" type="email" maxlength="150" placeholder="hello@yourbusiness.com"></label>
                            </div>
                            <label>Anything else to say <small>— optional, e.g. if your website tab has a shop or bookings</small><textarea data-pv="extra" rows="3" maxlength="3000"></textarea></label>
                        </details>
                    </div>
                    <div data-panel="build" hidden><div class="ab-build" data-build></div></div>
                </div>

                <div class="fb-prev-wrap ab-prev-wrap">
                    <div class="ab-seg ab-views" role="group" aria-label="Preview">
                        <button type="button" data-view="app" aria-pressed="true">App</button>
                        <button type="button" data-view="splash" aria-pressed="false">Splash</button>
                        <button type="button" data-view="home" aria-pressed="false">Home screen</button>
                    </div>
                    <div class="ab-phone" data-phone></div>
                    <p class="fb-hint">Preview — on a phone every screen is drawn with native Android parts.</p>
                </div>
            </div>

            <dialog class="lpb-dialog" data-dialog aria-label="Sign in">
                <button type="button" class="lpb-dialog__x" data-dialog-close aria-label="Close">×</button>
                <div data-dialog-body></div>
            </dialog>
        </div>

        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl'])</div>
            <div><h2>Want it on the iPhone App Store too?</h2><p>We build and publish apps for iPhone and Android — with logins, payments, push notifications and the native features Apple asks for. Start free: we plan the first version at no cost.</p></div>
            <a href="{{ route('service.mobile-app-development') }}#contact" class="btn btn--glow" data-quote>Get a free quote <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'The mobile app builder, explained'])

<script type="application/json" id="ab-icons">{!! json_encode(\App\Support\AppBuilder::icons(), JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<script src="{{ \App\Support\Asset::url('js/app-templates.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/app-builder.js') }}" defer></script>
@endsection
