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
            <a href="{{ $h('pricing') }}">Pricing</a>
            <a href="{{ $h('faq') }}">FAQ</a>
            <a href="{{ route('about') }}" @if (request()->routeIs('about')) aria-current="page" @endif>About</a>
            <button type="button" class="theme-btn" data-theme-toggle aria-label="Switch to light mode" title="Switch theme">
                <svg class="i-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                <svg class="i-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/></svg>
                <span class="theme-btn__label">Theme</span>
            </button>
            <a href="{{ $h('contact') }}" class="btn btn--sm" @unless ($form) data-quote @endunless>Get a free quote</a>
        </nav>
    </div>
</header>
