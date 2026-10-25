<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Thank you, {{ $enquiry->firstName() }} — Owlvix</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
    <style>
        .thanks { min-height: 100vh; min-height: 100dvh; display: grid; grid-template-rows: auto 1fr auto; }
        .thanks__top { padding: 20px 16px; display: flex; justify-content: center; }
        .thanks__main { display: grid; place-items: center; padding: 8px 16px 40px; }
        .thanks__card { width: min(560px, 100%); text-align: center; position: relative; }
        .thanks__card h1 { font-size: clamp(2rem, 6vw, 3rem); font-weight: 800; letter-spacing: -.02em; margin: 6px 0 10px; }
        .thanks__card .lead { margin: 0 auto 26px; }
        .thanks__owl { position: relative; display: flex; flex-direction: column; align-items: center; gap: 10px; padding-top: 6px; }
        .thanks__owl .say { max-width: 400px; }
        .thanks__owl .owl-wrap--static { width: min(260px, 70vw); }
        .thanks__owl .glow { position: absolute; left: 50%; bottom: 30px; width: 300px; height: 140px; transform: translateX(-50%); }
        .thanks .sound-hint { position: static; margin: 4px auto 0; }
        .thanks__actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 28px; }
        .thanks__summary { margin: 26px auto 0; max-width: 460px; text-align: left; padding: 18px 20px; }
        .thanks__summary p { margin: 0; color: var(--muted); font-size: .95rem; }
        .thanks__summary strong { color: var(--text); }
        .thanks .board__play { border-color: var(--card-border); }
    </style>
</head>
<body>
    <div class="bg-grid" aria-hidden="true"></div>
    <div class="thanks">
        <header class="thanks__top">
            <a href="{{ route('home') }}" class="brand" aria-label="Owlvix home">
                <img src="{{ asset('images/logo-mark.svg') }}" alt="" width="36" height="36">
                <span>Owl<span class="accent">vix</span></span>
            </a>
        </header>

        <main class="thanks__main">
            <div class="thanks__card">
                <span class="pill">Request received</span>
                <h1>Thanks, <span class="accent">{{ $enquiry->firstName() }}</span>!</h1>
                <p class="lead">Our owl has a quick message for you.</p>

                <div class="thanks__owl" id="thanks-owl">
                    <div class="say"><p class="say__text" aria-live="polite" data-owl-text>You'll hear back from us within one business day. Talk soon!</p></div>
                    <div class="owl-wrap owl-wrap--static">
                        <div class="owl-flip">
                            @include('partials.owl', ['alt' => 'Owlvix owl'])
                        </div>
                    </div>
                    <div class="glow" aria-hidden="true"></div>
                    <button type="button" class="sound-hint" aria-label="Turn on the owl's voice">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9H4z" class="fill"/><path d="M16 8.5a5 5 0 0 1 0 7M18.5 6a8.5 8.5 0 0 1 0 12"/></svg>
                        <span>Tap for sound</span>
                    </button>
                    <button type="button" class="board__play" data-owl-play aria-label="Play the owl's message">
                        <svg class="i-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                        <svg class="i-stop" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h10v10H7z"/></svg>
                        <span>Play message</span>
                    </button>
                </div>

                <div class="card thanks__summary">
                    <p><strong>Your request:</strong> {{ $enquiry->service ?: 'Project' }} · sent {{ $enquiry->created_at->format('d M Y') }}</p>
                </div>

                <div class="thanks__actions">
                    <a href="{{ route('home') }}" class="btn">Back to owlvix.com</a>
                    <a href="{{ route('home') }}#services" class="btn btn--ghost">See what we build</a>
                </div>
            </div>
        </main>
    </div>

    <script>window.OWLVIX = @json(\App\Support\OwlVoice::sets(['thanks']));</script>
    <script src="{{ asset('js/owl.js') }}?v={{ filemtime(public_path('js/owl.js')) }}"></script>
    <script>
        (() => {
            const root = document.getElementById('thanks-owl');
            const lines = window.OWLVIX.thanks;
            const owl = window.Owl.createOwl(root, {
                lines, idleText: lines[lines.length - 1].text, playLabel: 'Play message',
            });
            // Try to talk with sound; if the browser blocks it, talk silently and wait for a tap.
            let muted = false;
            const setMuted = on => { muted = on; root.classList.toggle('is-muted', on); };
            const enableSound = () => { if (!muted) return; setMuted(false); owl.start().catch(() => {}); };
            setTimeout(() => owl.start().catch(() => { setMuted(true); owl.start(true); }), 600);
            document.addEventListener('pointerdown', e => { if (!e.target.closest('a, [data-owl-play]')) enableSound(); });
            document.addEventListener('keydown', e => { if (e.key !== 'Tab') enableSound(); });
        })();
    </script>
</body>
</html>
