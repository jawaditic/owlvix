{{-- Main menu, the same on every public page. $local: sections that exist on this page (those links scroll
     here); every other section link goes to the homepage (e.g. /#pricing). $form: the page has its own quote
     form at #contact, so "Get a free quote" scrolls to it instead of opening the popup. --}}
@php
    $onHome = request()->routeIs('home');
    $local = $onHome ? ['services', 'work', 'process', 'reviews', 'pricing', 'faq', 'contact'] : ($local ?? []);
    $form = $onHome ? false : ($form ?? false);
    $h = fn ($id) => in_array($id, $local, true) ? "#$id" : route('home')."#$id";
@endphp
<header class="nav" id="top">
    <div class="container nav__inner">
        <a href="{{ $onHome ? '#top' : route('home') }}" class="brand" aria-label="Owlvix home">
            <img src="{{ asset('images/logo-mark.svg') }}" alt="Owlvix logo" width="36" height="36">
            <span>Owl<span class="accent">vix</span></span>
        </a>
        <button class="nav__toggle" aria-label="Open menu" aria-expanded="false" aria-controls="nav-links">
            <span></span><span></span><span></span>
        </button>
        <nav class="nav__links" id="nav-links">
            <a href="{{ $h('services') }}" @if (request()->routeIs('landing', 'service.*')) aria-current="page" @endif>Services</a>
            <a href="{{ $h('work') }}">Work</a>
            <a href="{{ $h('process') }}">Process</a>
            @if (count(\App\Support\Testimonials::data()['reviews']))<a href="{{ $h('reviews') }}">Reviews</a>@endif
            <a href="{{ $h('pricing') }}" @unless (in_array('pricing', $local, true)) data-pricing @endunless>Pricing</a>
            <a href="{{ $h('faq') }}">FAQ</a>
            <a href="{{ route('about') }}" @if (request()->routeIs('about')) aria-current="page" @endif>About</a>
            {{-- Free tools: dropdown on desktop (hover, focus or click), plain links in the phone menu --}}
            <div class="nav-drop" data-nav-drop>
                <button type="button" class="nav-drop__btn" aria-expanded="false" aria-haspopup="true" @if (request()->routeIs(...\App\Support\FreeTools::routes())) aria-current="page" @endif>
                    Free tools <span class="nav-drop__new">New</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="nav-drop__menu nav-drop__menu--wide">
                    <div class="nav-drop__grid">
                        @foreach (\App\Support\FreeTools::all() as $t)
                            <a href="{{ route($t['route']) }}" class="nav-drop__item" @if (request()->routeIs($t['route'])) aria-current="page" @endif>
                                <span class="svc-icon {{ $t['tone'] }}" aria-hidden="true">{!! \App\Support\Icons::svg($t['icon']) !!}</span>
                                <span><strong>{{ $t['name'] }}</strong><small>{{ $t['desc'] }}</small></span>
                            </a>
                        @endforeach
                    </div>
                    <a href="{{ route('seo-audit') }}" class="nav-drop__item nav-drop__item--feature" @if (request()->routeIs('seo-audit')) aria-current="page" @endif>
                        <span class="svc-icon" aria-hidden="true">{!! \App\Support\Icons::svg('sparkle') !!}</span>
                        <span><strong>Full SEO audit <em class="nav-drop__tag">Free</em></strong><small>Whole-site report by our team, in 2 days</small></span>
                    </a>
                    <a href="{{ route('free-tools') }}" class="nav-drop__all">See all free tools →</a>
                </div>
            </div>
            <button type="button" class="theme-btn" data-theme-toggle aria-label="Switch to light mode" title="Switch theme">
                <svg class="i-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                <svg class="i-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/></svg>
                <span class="theme-btn__label">Theme</span>
            </button>
            <a href="{{ $h('contact') }}" class="btn btn--sm" @unless ($form) data-quote @endunless>Get a free quote</a>
        </nav>
    </div>
</header>
