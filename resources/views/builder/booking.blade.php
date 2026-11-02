{{-- A booking page made with the free booking page builder (/booking-page-builder), shown at /book/{id}.
     The page is drawn by public/js/booking-page.js (window.OwlBooking), which reads the settings and the two addresses
     from the JSON block below — the CSP allows no inline script code. ?embed=1 drops the background and outer padding
     for iframes. Controller passes: $page, $cfg, $embed, $jsUrl, $slotsUrl, $bookUrl, $reportUrl. --}}
@php
    $c = preg_match('/^#[0-9a-f]{6}$/i', $cfg['color'] ?? '') ? $cfg['color'] : '#16a34a';
    $name = trim((string) ($cfg['name'] ?? '')) ?: 'Book an appointment';
    $intro = trim((string) ($cfg['intro'] ?? ''));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="{{ $c }}">
    <meta name="color-scheme" content="light dark">
    <title>Book with {{ $name }}</title>
    @if ($intro !== '')<meta name="description" content="{{ \Illuminate\Support\Str::limit($intro, 155) }}">@endif
    <style>
        :root { --c: {{ $c }}; --bg: #f4f6fb; --card: #fff; --text: #0f172a; --muted: #5b6b80; --line: #e2e8f0; }
        @media (prefers-color-scheme: dark) { :root { --bg: #070b14; --card: #0f1626; --text: #e2e8f0; --muted: #94a3b8; --line: #1f2a3d; } }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; padding: {{ $embed ? '0' : '32px 16px 24px' }}; background: {{ $embed ? 'transparent' : 'var(--bg)' }}; color: var(--text); font: 16px/1.5 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        /* shown until booking-page.js draws the page (and if scripts are off) */
        .wait { max-width: 680px; margin: 0 auto; background: var(--card); border: 1px solid var(--line); border-top: 6px solid var(--c); border-radius: 18px; padding: 26px; }
        .wait h1 { margin: 0 0 6px; font-size: 1.45rem; line-height: 1.2; }
        .wait p { margin: 0 0 6px; color: var(--muted); white-space: pre-line; }
        .wait .bar { height: 48px; margin-top: 18px; border-radius: 14px; background: var(--line); opacity: .6; }
        .credit { max-width: 680px; margin: 14px auto 0; text-align: center; font-size: .8rem; color: var(--muted); }
        .credit a { color: inherit; }
        @media (max-width: 520px) { body { padding: {{ $embed ? '0' : '16px 12px 20px' }}; } .wait { padding: 20px 16px; } }
    </style>
    <script type="application/json" id="booking-cfg">@json(['cfg' => $cfg, 'slots' => $slotsUrl, 'book' => $bookUrl])</script>
    <script src="{{ $jsUrl }}" defer></script>
</head>
<body>
<main id="booking-root">
    <div class="wait">
        <h1>{{ $name }}</h1>
        @if ($intro !== '')<p>{{ $intro }}</p>@endif
        <noscript><p><b>Please turn on JavaScript to book online.</b> It’s needed to show the free times.</p></noscript>
        <div class="bar" aria-hidden="true"></div>
    </div>
</main>
<p class="credit">Made with the <a href="{{ url('/booking-page-builder') }}" target="_blank" rel="noopener">free booking page builder by Owlvix</a> · <a href="{{ $reportUrl }}" target="_blank" rel="noopener nofollow">Report</a></p>
</body>
</html>
