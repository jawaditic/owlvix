{{-- The slim bar on a builder's editor page (layouts/page with $editor): back to the builder's page, its name, the
     guide (when there is one) and the theme switch. --}}
@php
    $tool = collect(\App\Support\FreeTools::builders())->firstWhere('route', $builder);
    $guide = \App\Support\Guides::forBuilder($builder);
@endphp
<header class="ebar">
    <a href="{{ route($builder) }}" class="ebar__back" aria-label="Back to the {{ $tool['name'] ?? 'builder' }} page">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        <img src="{{ asset('images/logo-mark.svg') }}" alt="" width="28" height="28">
        <span class="ebar__brand">Owl<span class="accent">vix</span></span>
    </a>
    <span class="ebar__name">{{ $tool['name'] ?? 'Builder' }}</span>
    <span class="ebar__right">
        @if ($guide)<a href="{{ route($guide[0]) }}" class="ebar__link" target="_blank" rel="noopener">Step-by-step guide</a>@endif
        <button type="button" class="theme-btn" data-theme-toggle aria-label="Switch theme" title="Switch theme">
            <svg class="i-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            <svg class="i-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/></svg>
        </button>
    </span>
</header>
