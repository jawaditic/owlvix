<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Dashboard') · Owlvix Admin</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body class="@yield('body_class')">
    @hasSection('bare')
        @yield('content')
    @else
        <header class="top">
            <a href="{{ route('admin.messages.index') }}" class="brand">
                <img src="{{ asset('images/logo-mark.svg') }}" alt="" width="30" height="30">
                <span>Owl<b>vix</b> <small>Admin</small></span>
            </a>
            <nav>
                <a href="{{ route('home') }}" target="_blank" rel="noopener">View site ↗</a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="link">Log out</button>
                </form>
            </nav>
        </header>
        <main class="wrap">
            @if (session('status'))
                <div class="flash" role="status">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    @endif
</body>
</html>
