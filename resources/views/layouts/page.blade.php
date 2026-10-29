{{-- Simple page layout (privacy policy, error pages): same look as the site, no popups or form scripts. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | Owlvix</title>
    @hasSection('description')<meta name="description" content="@yield('description')">@endif
    @hasSection('canonical')<link rel="canonical" href="@yield('canonical')">@endif
    <meta name="robots" content="@yield('robots', 'index, follow')">
    <meta name="theme-color" content="#070b14" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f5f7fb" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700;800&display=swap" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700;800&display=swap"></noscript>
    <script>(function () { var t; try { t = localStorage.getItem('owlvix-theme'); } catch (e) {} if (t !== 'light' && t !== 'dark') t = matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'; document.documentElement.setAttribute('data-theme', t); })();</script>
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/theme.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/site.css') }}">
    @yield('head')
</head>
<body>
    <div class="bg-grid" aria-hidden="true"></div>

    <header class="nav" id="top">
        <div class="container nav__inner">
            <a href="{{ route('home') }}" class="brand" aria-label="Owlvix home">
                <img src="{{ asset('images/logo-mark.svg') }}" alt="Owlvix logo" width="36" height="36">
                <span>Owl<span class="accent">vix</span></span>
            </a>
            <nav class="page-nav" aria-label="Main">
                <a href="{{ route('landing') }}">Services</a>
                <a href="{{ route('landing') }}#contact" class="btn btn--sm">Get a free quote</a>
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    @include('partials.footer', ['top' => route('home')])
</body>
</html>
