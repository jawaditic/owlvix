{{-- About Owlvix: who runs it, where, since when, and the link to Jawad's Fiverr profile @anjtec (trust / E-E-A-T).
     Facts confirmed by the owner on 30 Oct 2026. Keep them in sync with partials/structured-data and llms.txt. --}}
@extends('layouts.page')

@php
    $t = \App\Support\Testimonials::data();
@endphp

@section('title', 'About Owlvix — Jawad Ahmad and Team')
@section('description', 'Owlvix is a web, mobile and AI development studio founded by Jawad Ahmad (Fiverr: @anjtec), building software since 2018 for clients in 70+ countries.')
@section('canonical', route('about'))

@section('head')
@php
    $graph = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'AboutPage', '@id' => route('about').'#page', 'url' => route('about'), 'name' => 'About Owlvix',
                'inLanguage' => 'en', 'about' => ['@id' => url('/').'#business'], 'mainEntity' => ['@id' => url('/').'#business'],
                'isPartOf' => ['@type' => 'WebSite', '@id' => url('/').'#website', 'name' => 'Owlvix', 'url' => url('/')],
                'breadcrumb' => ['@type' => 'BreadcrumbList', 'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'About', 'item' => route('about')],
                ]],
            ],
            \App\Support\Owlvix::organization(),
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endsection

@section('content')
{{-- Hero in the homepage style: copy on the left, the owl with glass cards on the right --}}
<section class="hero about-hero">
    <div class="container">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><span aria-current="page">About</span>
        </nav>
    </div>
    <div class="container hero__inner">
        <div class="hero__copy">
            <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Since 2018 • Based in Pakistan</span>
            <h1>The people behind <span class="accent">Owlvix</span></h1>
            <p class="lead">Owlvix is a small web, mobile and AI development studio founded by <strong>Jawad Ahmad</strong>. We've been building software for clients in the USA, the UK and 70+ countries since 2018.</p>
            <div class="hero__cta">
                <a href="{{ route('landing') }}#contact" class="btn btn--glow">Get a free quote
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <a href="{{ route('home') }}#work" class="btn btn--ghost">See our work</a>
            </div>
            <ul class="hero__trust">
                <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Fixed-price quotes</li>
                <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>You own 100% of the code</li>
            </ul>
        </div>

        {{-- Talking owl: click the owl or the button to hear the intro (owl.js, voice lines in config/owl.php) --}}
        <div class="hero__art" id="owl-about" data-owl-talk="intro">
            <div class="stage">
                <div class="orb" aria-hidden="true"></div>
                <div class="ring" aria-hidden="true"></div>

                <div class="chat glass">
                    <div class="chat__head">
                        <img src="{{ asset('favicon.svg') }}" alt="Owlvix" width="34" height="34" class="chat__avatar">
                        <div>
                            <strong>Jawad Ahmad</strong>
                            <small><span class="live-dot" aria-hidden="true"></span>Founder &amp; lead developer</small>
                        </div>
                    </div>
                    <p class="chat__text" aria-live="polite" data-owl-text>Hoo-hoo! We plan it, build it, launch it — and stay around.</p>
                    <button type="button" class="board__play chat__play" data-owl-play>
                        <svg class="i-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                        <svg class="i-stop" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h10v10H7z"/></svg>
                        <span>Hear the intro</span>
                    </button>
                </div>

                <div class="float float--web glass" aria-hidden="true">
                    <span class="float__icon"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
                    <div><strong>200+ orders</strong><small>completed on Fiverr</small></div>
                </div>
                <div class="float float--ai glass" aria-hidden="true">
                    <span class="float__icon float__icon--blue"><svg viewBox="0 0 24 24"><path class="d" d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg></span>
                    <div><strong>{{ $t['five_star'] ?: $t['count'] }} five-star</strong><small>of {{ $t['count'] }} reviews</small></div>
                </div>
                <div class="float float--mobile glass" aria-hidden="true">
                    <span class="float__icon"><svg viewBox="0 0 24 24"><circle class="d" cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/></svg></span>
                    <div><strong>70+ countries</strong><small>clients worldwide</small></div>
                </div>

                <div class="owl-wrap">
                    @include('partials.owl', ['alt' => 'Owlvix owl mascot'])
                </div>
                <div class="glow" aria-hidden="true"></div>
            </div>
        </div>
    </div>
</section>

{{-- Key facts, same strip as the homepage --}}
<section class="strip">
    <div class="container strip__inner">
        <div><strong>2018</strong><span>building software since</span></div>
        <div><strong>200+</strong><span>projects delivered</span></div>
        <div><strong>{{ $t['five_star'] ?: $t['count'] }}</strong><span>{{ $t['five_star'] ? 'five-star reviews' : 'client reviews' }}</span></div>
        <div><strong>70+</strong><span>countries served</span></div>
    </div>
</section>

{{-- Our story: text + the Fiverr profile card --}}
<section class="section">
    <div class="container about-split">
        <div class="about-split__text">
            <p class="eyebrow">Our story</p>
            <h2>From Fiverr to <span class="accent">Owlvix</span></h2>
            <p>Many of our clients first met us on Fiverr, where Jawad's profile, <strong>Jawad Ahmad (@anjtec)</strong>, has more than 200 completed orders for clients in the USA, the UK and 70+ other countries. The reviews on this site come from that profile — that's why some of them mention Anjtec or Jawad.</p>
            <p>Owlvix is the same team and the same way of working, with its own home. Our work covers websites and Shopify stores, custom web apps, iPhone and Android apps, and AI chatbots and automation — one team that can take an idea from the first sketch to the server it runs on.</p>
        </div>
        <div class="profile-card">
            <div class="profile-card__head">
                <img src="{{ asset('favicon.svg') }}" alt="Owlvix" width="52" height="52">
                <div>
                    <strong>Jawad Ahmad</strong>
                    <small>@anjtec · Fiverr</small>
                </div>
            </div>
            <p class="profile-card__title">Full Stack Developer for Web, Mobile and AI Apps</p>
            <ul class="profile-card__stats">
                <li><strong>200+</strong><span>orders</span></li>
                <li><strong>{{ $t['five_star'] ?: $t['count'] }}/{{ $t['count'] }}</strong><span>five-star</span></li>
                <li><strong>70+</strong><span>countries</span></li>
            </ul>
            @if (! empty($t['profile_url']))
                <a href="{{ $t['profile_url'] }}" class="btn btn--ghost btn--block" target="_blank" rel="noopener">View Fiverr profile
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>
                </a>
            @endif
        </div>
    </div>
</section>

{{-- Who we are: founder + the stack --}}
<section class="section section--alt">
    <div class="container about-split about-split--reverse">
        <div class="about-split__text">
            <p class="eyebrow">Who we are</p>
            <h2>A founder-led team</h2>
            <p><strong>Jawad Ahmad</strong> is the founder and lead developer. He has been building websites and software professionally since 2018, and works with a small team of developers and designers, so each project gets the right skills without the overhead of a large agency.</p>
            <p>We work across the full stack, so one team can handle your whole project — from design and code to the AI features and the server it runs on.</p>
        </div>
        <div class="stack-grid">
            <div class="stack-card">
                <span class="svc-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect class="d" x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg></span>
                <strong>Web apps</strong>
                <ul class="chips"><li>Laravel</li><li>React</li><li>Next.js</li><li>Vue</li></ul>
            </div>
            <div class="stack-card">
                <span class="svc-icon is-blue" aria-hidden="true"><svg viewBox="0 0 24 24"><rect class="d" x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/></svg></span>
                <strong>Mobile apps</strong>
                <ul class="chips"><li>React Native</li><li>Expo</li></ul>
            </div>
            <div class="stack-card">
                <span class="svc-icon is-blue" aria-hidden="true"><svg viewBox="0 0 24 24"><path class="d" d="M4.5 10v10h15V10"/><path d="M3 10l1.6-5.5h14.8L21 10z"/><path d="M10 20v-5h4v5"/></svg></span>
                <strong>Stores &amp; websites</strong>
                <ul class="chips"><li>Shopify</li><li>WordPress</li></ul>
            </div>
            <div class="stack-card">
                <span class="svc-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path class="d" d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg></span>
                <strong>AI</strong>
                <ul class="chips"><li>OpenAI</li><li>Claude</li><li>Gemini</li></ul>
            </div>
        </div>
    </div>
</section>

{{-- How we work: five promises --}}
<section class="section">
    <div class="container">
        <p class="eyebrow">How we work</p>
        <h2>What you can count on</h2>
        <div class="grid grid--3 values">
            <article class="card">
                <span class="svc-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 7h6M9 11h6M9 15h3"/><rect class="d" x="5" y="3" width="14" height="18" rx="2"/></svg></span>
                <h3>Clear quotes</h3>
                <p>You get a fixed price, scope and timeline before any work starts, usually within one business day.</p>
            </article>
            <article class="card">
                <span class="svc-icon is-blue" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle class="d" cx="12" cy="12" r="3"/></svg></span>
                <h3>No surprises</h3>
                <p>You can see progress every week on a private preview link and change direction early.</p>
            </article>
            <article class="card">
                <span class="svc-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect class="d" x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span>
                <h3>You own everything</h3>
                <p>Source code, designs and accounts are handed over to you when the project is paid.</p>
            </article>
            <article class="card">
                <span class="svc-icon is-blue" aria-hidden="true"><svg viewBox="0 0 24 24"><path class="d" d="M12 21s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.6-7 10-7 10z"/></svg></span>
                <h3>We stay around</h3>
                <p>Bug fixes after launch are free, and new features can be added as new milestones whenever you need them.</p>
            </article>
            <article class="card">
                <span class="svc-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle class="d" cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/></svg></span>
                <h3>Remote by design</h3>
                <p>We work online with clients worldwide and plan calls around your time zone.</p>
            </article>
        </div>
    </div>
</section>

{{-- Get in touch: a contact panel with the three ways to reach us --}}
<section class="section contact-panel-wrap" id="contact">
    <div class="container">
        <div class="contact-panel">
            <div class="contact-panel__glow" aria-hidden="true"></div>
            <div class="contact-panel__copy">
                <p class="eyebrow">Get in touch</p>
                <h2>Let's build something <span class="accent">together</span></h2>
                <p>Tell us a little about your project and get a fixed-price quote within one business day — free, with no obligation.</p>
                <div class="hero__cta">
                    <a href="{{ route('landing') }}#contact" class="btn btn--glow">Get a free quote
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                    <a href="{{ route('landing') }}" class="btn btn--ghost">See our services</a>
                </div>
            </div>
            <div class="contact-panel__options">
                <a href="{{ route('landing') }}#contact" class="contact-option">
                    <span class="contact-option__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path class="d" d="M4 5h16v11H9l-5 4z"/><path d="M8 10h8M8 13h5"/></svg></span>
                    <span><strong>Quote form</strong><small>Reply within one business day</small></span>
                    <svg class="contact-option__arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <a href="mailto:{{ config('mail.from.address') }}" class="contact-option">
                    <span class="contact-option__icon is-blue" aria-hidden="true"><svg viewBox="0 0 24 24"><rect class="d" x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5l8.5 6.5 8.5-6.5"/></svg></span>
                    <span><strong>Email us</strong><small>{{ config('mail.from.address') }}</small></span>
                    <svg class="contact-option__arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                @if (! empty($t['profile_url']))
                <a href="{{ $t['profile_url'] }}" class="contact-option" target="_blank" rel="noopener">
                    <span class="contact-option__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path class="d" d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg></span>
                    <span><strong>Reviews on Fiverr</strong><small>{{ $t['five_star'] ? $t['five_star'].' of '.$t['count'].' reviews are 5 stars' : $t['count'].' client reviews' }}</small></span>
                    <svg class="contact-option__arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>
                </a>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
