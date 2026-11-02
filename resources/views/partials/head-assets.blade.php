{{-- <head> assets shared by the landing and service pages: icons, fonts, form libraries' CSS, theme, styles, analytics --}}
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
@include('partials.font-preload')
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.7/dist/css/intlTelInput.min.css" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.7/dist/css/intlTelInput.min.css"></noscript>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css"></noscript>
<script>(function () { var t; try { t = localStorage.getItem('owlvix-theme'); } catch (e) {} if (t !== 'light' && t !== 'dark') t = matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'; document.documentElement.setAttribute('data-theme', t); })();</script>
<link rel="stylesheet" href="{{ \App\Support\Asset::url('css/theme.css') }}">
<link rel="stylesheet" href="{{ \App\Support\Asset::url('css/site.css') }}">
<script src="{{ \App\Support\Asset::url('js/icons.js') }}" defer></script>{{-- window.OwlIcon: our own icons, ready before the builders start --}}
<noscript><style>[data-reviews].is-loading > * { visibility: visible; } [data-reviews].is-loading::after { display: none; }</style></noscript>
@include('partials.analytics')
