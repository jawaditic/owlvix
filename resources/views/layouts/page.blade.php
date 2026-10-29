{{-- Simple page layout (About, Privacy, 404): the homepage menu, footer and quote popup around plain content. --}}
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
    @include('partials.head-assets')
    @yield('head')
</head>
<body data-no-popup="{{ session('sent') ? '1' : '0' }}" @hasSection('no-auto-quote') data-auto-quote="off" @endif>
    <div class="bg-grid" aria-hidden="true"></div>

    @include('partials.site-nav')

    <main>
        @yield('content')
    </main>

    @include('partials.footer', ['top' => route('home')])
    @include('partials.page-scripts')
</body>
</html>
