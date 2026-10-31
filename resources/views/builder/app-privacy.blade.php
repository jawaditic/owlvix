{{-- Privacy policy of an app made with the mobile app builder (AppBuilderController::privacy), written from what the
     app does (App\Support\AppBuilder::dataUse) and the owner's details. Linked from the app's Google Play listing. --}}
@php
    $owner = ($p['owner'] ?? '') !== '' ? $p['owner'] : $app->name;
    $email = $p['email'] ?? '';
    $updated = $app->updated_at ?? now();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Privacy policy — {{ $app->name }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --bg: #f4f6fb; --card: #fff; --text: #0f172a; --muted: #5b6b82; --line: #e2e8f0; --accent: #17a85f; }
        @media (prefers-color-scheme: dark) { :root { --bg: #070b14; --card: #0f1626; --text: #e2e8f0; --muted: #94a3b8; --line: #1e293b; --accent: #1ec773; } }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px 16px; background: var(--bg); color: var(--text); font: 16px/1.65 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        main { max-width: 720px; margin: 0 auto; background: var(--card); border: 1px solid var(--line); border-radius: 20px; padding: 36px 32px; }
        h1 { margin: 0 0 6px; font-size: 1.7rem; line-height: 1.25; }
        h2 { margin: 28px 0 8px; font-size: 1.1rem; }
        p, li { color: var(--muted); }
        p { margin: 0 0 12px; }
        ul { margin: 0 0 12px; padding-left: 22px; }
        a { color: var(--accent); }
        .meta { font-size: .9rem; }
        .note { white-space: pre-line; }
        .credit { max-width: 720px; margin: 18px auto 0; text-align: center; font-size: .8rem; color: var(--muted); }
        .credit a { color: inherit; }
        @media (max-width: 520px) { main { padding: 26px 20px; } }
    </style>
</head>
<body>
<main>
    <h1>Privacy policy for {{ $app->name }}</h1>
    <p class="meta">The {{ $app->name }} Android app ({{ $app->package }}) is provided by {{ $owner }}. Last updated {{ $updated->format('j F Y') }}.</p>

    <h2>In short</h2>
    <p>The app doesn’t ask you to sign up, doesn’t show ads, doesn’t track you and doesn’t collect personal information such as your name, contacts, photos or location. It only counts, anonymously, how it’s used.</p>

    <h2>What the app does</h2>
    <ul>
        <li>It shows information from {{ $owner }} (pages, pictures, prices, opening hours and so on). To keep this up to date it downloads it over the internet from owlvix.com, the service the app was made with, and keeps a copy on your phone so it also works offline.</li>
        <li>Like any website, the server that sends this information keeps standard technical logs (such as your IP address and the time of the request) for a short time, for security and to keep the service running. They aren’t used to identify you or for advertising.</li>
        <li>To show {{ $owner }} how the app is used, it counts on your phone how often it’s opened and which pages are viewed, and sends these counts once a day. Only daily totals are kept; nothing in them identifies you or your phone.</li>
        <li>The app only needs internet access{{ $use['push'] ? ' (and, if you allow it, permission to show notifications)' : '' }}. It doesn’t ask for permission to use your camera, microphone, location, contacts or files.</li>
    </ul>

    @if ($use['push'])
        <h2>Notifications</h2>
        <p>{{ $owner }} can send news and offers as notifications. To receive them, the app uses Google’s Firebase Cloud Messaging, which gives this installation of the app a random ID so Google can deliver messages to it. That ID isn’t linked to your name or account, and we don’t store it. You can turn notifications off at any time in your phone’s settings for this app.</p>
    @endif

    @if ($use['website'] || $use['youtube'] || $use['social'] || $use['contact'] || $use['links'])
        <h2>Other apps and websites</h2>
        <ul>
            @if ($use['website'])
                <li>Some pages of the app show the website <strong>{{ $use['website'] }}</strong>. That website may use cookies or collect information as described in its own privacy policy.</li>
            @endif
            @if ($use['youtube'])
                <li>Videos are shown with their picture from YouTube, and tapping one opens it in YouTube. Loading the picture and watching the video are covered by Google’s privacy policy.</li>
            @endif
            @if ($use['contact'])
                <li>Buttons to call, email, message on WhatsApp or find us on a map open the matching app on your phone. Anything you send that way goes directly to us through that app.</li>
            @endif
            @if ($use['social'] || $use['links'])
                <li>Links to social media and other websites open them outside this app, where their own privacy policies apply.</li>
            @endif
        </ul>
    @endif

    @if (($p['extra'] ?? '') !== '')
        <h2>More from {{ $owner }}</h2>
        <p class="note">{{ $p['extra'] }}</p>
    @endif

    <h2>Children</h2>
    <p>The app doesn’t knowingly collect information from anyone, including children.</p>

    <h2>Changes</h2>
    <p>If the app starts doing something new with information, this page will be updated first, with a new date at the top.</p>

    <h2>Contact</h2>
    @if ($email !== '')
        <p>Questions about privacy? Email <a href="mailto:{{ $email }}">{{ $email }}</a>.</p>
    @else
        <p>Questions about privacy? Contact {{ $owner }} using the details on the app’s Google Play page.</p>
    @endif
</main>
<p class="credit">App made with the free <a href="{{ route('app-builder') }}">Owlvix mobile app builder</a> · <a href="{{ $app->reportUrl() }}">Report this app</a></p>
</body>
</html>
