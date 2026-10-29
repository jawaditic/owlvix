{{-- <head> assets shared by the landing and service pages: icons, fonts, form libraries' CSS, theme, styles, analytics --}}
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700;800&display=swap" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700;800&display=swap"></noscript>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.7/dist/css/intlTelInput.min.css" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.7/dist/css/intlTelInput.min.css"></noscript>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css"></noscript>
<script>(function () { var t; try { t = localStorage.getItem('owlvix-theme'); } catch (e) {} if (t !== 'light' && t !== 'dark') t = matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'; document.documentElement.setAttribute('data-theme', t); })();</script>
<link rel="stylesheet" href="{{ \App\Support\Asset::url('css/theme.css') }}">
<link rel="stylesheet" href="{{ \App\Support\Asset::url('css/site.css') }}">
<noscript><style>[data-reviews].is-loading > * { visibility: visible; } [data-reviews].is-loading::after { display: none; }</style></noscript>
@include('partials.analytics')
