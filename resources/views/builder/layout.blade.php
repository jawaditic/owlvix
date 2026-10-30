{{-- Small standalone page for the free page builder (form "sent" page, report form). No site popups or scripts. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --bg: #f4f6fb; --card: #fff; --text: #0f172a; --muted: #64748b; --line: #e2e8f0; --accent: #17a85f; --error: #b91c1c; }
        @media (prefers-color-scheme: dark) { :root { --bg: #070b14; --card: #0f1626; --text: #e2e8f0; --muted: #94a3b8; --line: #1e293b; --accent: #1ec773; --error: #f87171; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px 16px; background: var(--bg); color: var(--text); font: 16px/1.6 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .card { width: 100%; max-width: 460px; background: var(--card); border: 1px solid var(--line); border-radius: 20px; padding: 36px 30px; text-align: center; box-shadow: 0 12px 40px rgba(15, 23, 42, .08); }
        .icon { width: 64px; height: 64px; margin: 0 auto 18px; border-radius: 50%; display: grid; place-items: center; background: color-mix(in srgb, var(--accent) 14%, transparent); color: var(--accent); }
        .icon--error { background: color-mix(in srgb, var(--error) 14%, transparent); color: var(--error); }
        .icon svg { width: 32px; height: 32px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }
        h1 { margin: 0 0 8px; font-size: 1.5rem; line-height: 1.25; }
        p { margin: 0 0 12px; color: var(--muted); }
        ul.errors { text-align: left; margin: 0 0 16px; padding-left: 20px; color: var(--error); }
        .btn { display: inline-block; margin-top: 10px; padding: 11px 22px; border-radius: 999px; border: 0; background: var(--accent); color: #fff; font: 600 15px/1.2 inherit; font-family: inherit; text-decoration: none; cursor: pointer; }
        form { text-align: left; margin-top: 18px; }
        label { display: block; margin: 0 0 6px; font-size: .9rem; font-weight: 600; }
        textarea, input { width: 100%; margin: 0 0 14px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; background: var(--bg); color: var(--text); font: inherit; }
        textarea { min-height: 120px; resize: vertical; }
        .hp { position: absolute; left: -9999px; }
        .error { color: var(--error); font-size: .9rem; margin: -8px 0 12px; }
        .credit { margin-top: 18px; font-size: .8rem; color: var(--muted); }
        .credit a { color: inherit; }
    </style>
</head>
<body>
    <main class="card">
        @yield('content')
    </main>
    <p class="credit">@yield('credit', 'Form by') <a href="{{ url('/landing-page-builder') }}">Owlvix</a></p>
</body>
</html>
