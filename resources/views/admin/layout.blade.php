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
    {{-- Theme: saved choice, else the device setting. Runs before paint so there's no flash. --}}
    <script>(function () { var t; try { t = localStorage.getItem('owlvix-theme'); } catch (e) {} if (t !== 'light' && t !== 'dark') t = matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'; document.documentElement.setAttribute('data-theme', t); })();</script>
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/theme.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/admin.css') }}">
</head>
<body class="@yield('body_class')">
    <script>try { if (localStorage.getItem('owlvix-side') === 'mini') document.body.classList.add('side-mini'); } catch (e) {}</script>
    @hasSection('bare')
        <button type="button" class="theme-btn theme-btn--corner" data-theme-toggle title="Switch theme" aria-label="Switch theme">
            <svg class="i-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            <svg class="i-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/></svg>
        </button>
        @yield('content')
    @else
        @php
            $unread = \App\Models\ContactMessage::unread()->count();
            $newFeedback = \App\Models\SurveyResponse::whereNull('read_at')->count();
            $reportedSites = \App\Models\BuilderSite::where('status', 'live')->whereNotNull('reported_at')->count();
            $menu = [
                ['admin.dashboard', 'Dashboard', 'M4 13h6v7H4z M4 4h6v6H4z M14 4h6v9h-6z M14 16h6v4h-6z', null],
                ['admin.traffic', 'Traffic', 'M4 19V9 M10 19V5 M16 19v-7 M22 19H2', null],
                ['admin.messages', 'Messages', 'M4 6h16v12H4z M4 7l8 6 8-6', $unread],
                ['admin.projects', 'Portfolio', 'M4 5h7v6H4z M13 5h7v6h-7z M4 13h7v6H4z M13 13h7v6h-7z', null],
                ['admin.testimonials', 'Reviews', 'M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.7l5.9-.9z', null],
                ['admin.feedback', 'Feedback', 'M4 5h16v11H9l-5 4z M8 9h8 M8 12h5', $newFeedback],
                ['admin.builder', 'Page builder', 'M4 4h16v16H4z M4 9h16 M9 9v11', $reportedSites],
            ];
        @endphp
        <div class="shell">
            {{-- Left sidebar (slides in on phones) --}}
            <aside class="side" id="side">
                <div class="side__top">
                    <a href="{{ route('admin.dashboard') }}" class="brand">
                        <img src="{{ asset('images/logo-mark.svg') }}" alt="" width="32" height="32">
                        <span>Owl<b>vix</b> <small>Admin</small></span>
                    </a>
                    <button type="button" class="side__toggle" data-side-collapse aria-label="Collapse menu" aria-pressed="false" title="Collapse menu">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>
                </div>
                <nav class="side__nav" aria-label="Admin">
                    <p class="side__label">Manage</p>
                    @foreach ($menu as [$route, $label, $icon, $badge])
                        <a href="{{ route(in_array($route, ['admin.dashboard', 'admin.traffic']) ? $route : $route.'.index') }}" title="{{ $label }}" @class(['side__link', 'is-active' => request()->routeIs($route, $route.'.*')])>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                            <span>{{ $label }}</span>
                            @if ($badge)<em class="side__badge" title="{{ $badge }} unread">{{ $badge }}</em>@endif
                        </a>
                    @endforeach
                </nav>
                <div class="side__foot">
                    <button type="button" class="side__link" data-theme-toggle title="Switch theme">
                        <svg class="i-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                        <svg class="i-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/></svg>
                        <span data-theme-label>Light mode</span>
                    </button>
                    <a href="{{ route('home') }}" target="_blank" rel="noopener" class="side__link" title="View site">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h6v6 M20 4l-9 9 M18 14v6H4V6h6"/></svg>
                        <span>View site</span>
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="side__link" title="Log out">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4h4v16h-4 M10 8l-4 4 4 4 M6 12h10"/></svg>
                            <span>Log out</span>
                        </button>
                    </form>
                </div>
            </aside>
            <div class="side__scrim" data-side-close></div>

            <div class="shell__main">
                {{-- Phone top bar --}}
                <header class="mobile-top">
                    <button type="button" class="menu-btn" aria-controls="side" aria-expanded="false" data-side-open aria-label="Open menu">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>
                    <a href="{{ route('admin.dashboard') }}" class="brand">
                        <img src="{{ asset('images/logo-mark.svg') }}" alt="" width="28" height="28">
                        <span>Owl<b>vix</b></span>
                    </a>
                </header>
                <main class="wrap">
                    @if (session('status'))
                        <div class="flash" role="status">{{ session('status') }}</div>
                    @endif
                    @yield('content')
                </main>
            </div>
        </div>
        <script>
            // Phone menu: open/close the sidebar
            (() => {
                const btn = document.querySelector('[data-side-open]');
                const set = open => { document.body.classList.toggle('side-open', open); btn.setAttribute('aria-expanded', open); };
                btn.addEventListener('click', () => set(true));
                document.querySelector('[data-side-close]').addEventListener('click', () => set(false));
                document.addEventListener('keydown', e => { if (e.key === 'Escape') set(false); });

                // Desktop: ☰ next to the logo shrinks the sidebar to icons only (remembered)
                const mini = document.querySelector('[data-side-collapse]');
                const setMini = on => {
                    document.body.classList.toggle('side-mini', on);
                    mini.setAttribute('aria-pressed', on);
                    mini.setAttribute('aria-label', on ? 'Expand menu' : 'Collapse menu');
                    mini.title = on ? 'Expand menu' : 'Collapse menu';
                    try { localStorage.setItem('owlvix-side', on ? 'mini' : 'full'); } catch (e) {}
                };
                setMini(document.body.classList.contains('side-mini'));
                mini.addEventListener('click', () => setMini(!document.body.classList.contains('side-mini')));
            })();
        </script>
    @endif
    <script src="{{ \App\Support\Asset::url('js/sweet.js') }}"></script>
    <script src="{{ \App\Support\Asset::url('js/theme.js') }}"></script>
</body>
</html>
