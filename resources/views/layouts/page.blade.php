{{-- Simple page layout (About, Privacy, 404): the homepage menu, footer and quote popup around plain content. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | Owlvix</title>
    @hasSection('description')<meta name="description" content="@yield('description')">@endif
    @hasSection('canonical')<link rel="canonical" href="@yield('canonical')">@endif
    {{-- a builder's own editor page (routes/web.php, "editor"): just the builder --}}
    @php
        $isEditor = ! empty($editor);
    @endphp
    <meta name="robots" content="{{ $isEditor ? 'noindex, follow' : trim($__env->yieldContent('robots', 'index, follow')) }}">
    {{-- How the page looks when shared (WhatsApp, Facebook, LinkedIn, X): its own picture from images/og/{route}.jpg
         when there is one (guides, builders, the gallery), else the site's main picture. --}}
    @php
        $shareName = Route::currentRouteName();
        $shareFile = $shareName ? 'images/og/'.$shareName.'.jpg' : null;
        $shareImage = $shareFile && is_file(public_path($shareFile)) ? asset($shareFile).'?v='.filemtime(public_path($shareFile)) : asset('images/og.png');
        // (sections given as text arrive already escaped; undo that so {{ }} escapes them once)
        $plain = fn ($name) => html_entity_decode(trim($__env->yieldContent($name)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $shareTitle = $plain('title') ?: 'Owlvix';
        $shareText = $plain('description');
        $shareUrl = $plain('canonical') ?: url()->current();
    @endphp
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Owlvix">
    <meta property="og:locale" content="en_US">
    <meta property="og:title" content="{{ $shareTitle }}">
    @if ($shareText)<meta property="og:description" content="{{ $shareText }}">@endif
    <meta property="og:url" content="{{ $shareUrl }}">
    <meta property="og:image" content="{{ $shareImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="628">
    <meta property="og:image:alt" content="{{ $shareTitle }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $shareTitle }}">
    @if ($shareText)<meta name="twitter:description" content="{{ $shareText }}">@endif
    <meta name="twitter:image" content="{{ $shareImage }}">
    <meta name="theme-color" content="#070b14" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f5f7fb" media="(prefers-color-scheme: light)">
    @if ($builderName = \App\Support\FreeTools::builderOf(Route::currentRouteName()))<meta name="owl-builder" content="{{ $builderName }}">@endif
    @include('partials.head-assets')
    @yield('head')
</head>
<body data-no-popup="{{ session('sent') ? '1' : '0' }}" @hasSection('no-auto-quote') data-auto-quote="off" @endif @class(['is-editor' => $isEditor])>
    <div class="bg-grid" aria-hidden="true"></div>

    @if ($isEditor)
        @include('partials.editor-bar', ['builder' => $builderName])
    @else
        @include('partials.site-nav')
    @endif

    <main>
        @yield('content')
    </main>

    @unless ($isEditor)
        @include('partials.footer', ['top' => route('home')])
    @endunless
    @include('partials.page-scripts')
</body>
</html>
