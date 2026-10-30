{{-- Free website speed test. The form posts to speed-test.run (SpeedTestController), which runs Google PageSpeed
     Insights; the report below is filled in by the script at the bottom. "Download PDF" uses the print layout. --}}
@extends('layouts.page')

@section('title', 'Free Website Speed Test & Report')
@section('description', 'Test how fast your website loads on mobile and desktop. Get a free speed score, Core Web Vitals and the top fixes, powered by Google PageSpeed Insights.')
@section('canonical', route('speed-test'))
@section('no-auto-quote', '1')

@section('head')
@php
    $faq = [
        ['What is a good website speed score?', 'A score of 90–100 is good, 50–89 needs improvement and below 50 is poor. The score comes from Google Lighthouse, the same test used in PageSpeed Insights.'],
        ['Why does website speed matter?', 'Slow pages lose visitors — many people leave if a page takes more than a few seconds on a phone — and Google uses page experience, including Core Web Vitals, when ranking pages.'],
        ['Why is my mobile score lower than desktop?', 'The mobile test simulates a mid-range phone on a slower connection, so it is stricter. Most of your visitors are probably on phones, so the mobile score matters most.'],
        ['Is this speed test free?', 'Yes, completely free, with no sign-up. If you would like help fixing what it finds, we can do that too — and every project starts free.'],
    ];
    $graph = ['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'WebApplication', 'name' => 'Owlvix Website Speed Test', 'url' => route('speed-test'), 'applicationCategory' => 'DeveloperApplication',
            'operatingSystem' => 'Any', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'], 'provider' => ['@id' => url('/').'#business']],
        ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq)],
    ]];
@endphp
<script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endsection

@section('content')
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><span aria-current="page">Website speed test</span>
        </nav>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · No sign-up</span>
        <h1>Free website <span class="accent">speed test</span></h1>
        <p class="lead">See how fast your website loads, what is slowing it down and how to fix it — in about 30 seconds. Powered by Google PageSpeed Insights.</p>

        <form class="speed-form card" id="speed-form" data-live action="{{ route('speed-test.run') }}" method="POST" novalidate>
        <span class="perch-owl" aria-hidden="true">@include('partials.live-owl', ['width' => 96, 'variant' => 'dev', 'alt' => 'Owlvix owl testing websites'])</span>
            @csrf
            <label class="speed-form__url">
                <span class="sr-only">Website address</span>
                <span class="speed-form__icon" aria-hidden="true">{!! \App\Support\Icons::svg('globe') !!}</span>
                <input type="text" name="url" inputmode="url" autocomplete="url" placeholder="example.com" value="{{ $url }}" required maxlength="500">
            </label>
            <div class="speed-form__device" role="radiogroup" aria-label="Device">
                <label><input type="radio" name="strategy" value="mobile" @checked($strategy === 'mobile')><span>📱 Mobile</span></label>
                <label><input type="radio" name="strategy" value="desktop" @checked($strategy === 'desktop')><span>💻 Desktop</span></label>
            </div>
            <button type="submit" class="btn btn--glow">Test my site
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </form>
        <p class="speed-error" role="alert" hidden data-speed-error></p>
    </div>
</section>

{{-- While the test runs --}}
<section class="speed-loading" hidden data-speed-loading>
    <div class="container container--narrow">
        <div class="card speed-loading__card">
            @include('partials.live-owl', ['width' => 140, 'variant' => 'dev', 'alt' => 'Owlvix owl testing your website'])
            <div>
                <strong>Testing your website…</strong>
                <p data-speed-step>Loading your page like a real visitor</p>
                <div class="speed-loading__bar"><span></span></div>
                <small>This usually takes 20–40 seconds.</small>
            </div>
        </div>
    </div>
</section>

{{-- The report (filled in by the script below) --}}
<section class="speed-report" hidden data-speed-report aria-live="polite">
    <div class="container">
        <div class="speed-report__print-head">
            <img src="{{ asset('images/logo-mark.svg') }}" alt="Owlvix logo" width="32" height="32"> <strong>Owlvix</strong> · Website speed report · owlvix.com
        </div>

        <div class="speed-summary card">
            <div class="speed-gauge" data-rating="good">
                <svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="52" class="speed-gauge__track"/><circle cx="60" cy="60" r="52" class="speed-gauge__bar" pathLength="100"/></svg>
                <strong data-r="score">–</strong>
                <span>Performance</span>
            </div>
            <div class="speed-summary__text">
                <p class="eyebrow">Your result</p>
                <h2 data-r="headline">–</h2>
                <p data-r="summary"></p>
                <p class="speed-summary__meta"><span data-r="url"></span> · <span data-r="device"></span> · <span data-r="date"></span></p>
                <div class="speed-actions">
                    <button type="button" class="btn btn--sm" data-speed-pdf>Download PDF report</button>
                    <button type="button" class="btn btn--ghost btn--sm" data-speed-share>Copy share link</button>
                    <button type="button" class="btn btn--ghost btn--sm" data-speed-again>Test another site</button>
                </div>
            </div>
            <img class="speed-summary__shot" alt="Screenshot of the tested page" hidden data-r="shot">
        </div>

        <h3 class="speed-h">Key timings <small>lab test</small></h3>
        <div class="speed-metrics" data-r="metrics"></div>

        <div data-r="field-wrap" hidden>
            <h3 class="speed-h">Real visitors <small>Chrome users, last 28 days</small></h3>
            <div class="speed-metrics speed-metrics--field" data-r="field"></div>
        </div>

        <h3 class="speed-h">What to fix first</h3>
        <ol class="speed-fixes" data-r="fixes"></ol>

        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl ready to speed up your site'])</div>
            <div>
                <h2>Want us to make it faster?</h2>
                <p>We fix speed problems at the source — images, scripts, hosting and code. Start free: we review your report and plan the fixes at no cost, then you pay per milestone.</p>
            </div>
            <a href="{{ route('home') }}#contact" class="btn btn--glow" data-quote data-speed-quote>Get my site fixed
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
    </div>
</section>

{{-- What the numbers mean (also helps this page rank for "website speed test") --}}
<section class="section section--alt speed-info">
    <div class="container container--narrow">
        <p class="eyebrow">Good to know</p>
        <h2>Website speed, explained</h2>
        <div class="faq">
            @foreach ($faq as [$q, $a])
                <details>
                    <summary>{{ $q }}</summary>
                    <p>{{ $a }}</p>
                </details>
            @endforeach
        </div>
        <p class="speed-info__more">Need more than speed? See our <a href="{{ route('service.seo-services') }}">SEO services</a> and <a href="{{ route('service.hosting-and-support') }}">hosting &amp; support</a>.</p>
    </div>
</section>

<script>
(() => {
    const form = document.getElementById('speed-form');
    const $ = s => document.querySelector(s);
    const r = name => document.querySelector(`[data-r="${name}"]`);
    const loading = $('[data-speed-loading]'), report = $('[data-speed-report]'), errorBox = $('[data-speed-error]');
    const steps = ['Loading your page like a real visitor', 'Measuring how fast content appears', 'Checking images, scripts and fonts', 'Looking for the biggest wins', 'Writing your report'];
    const labels = { good: 'Good', average: 'Needs improvement', poor: 'Poor' };
    const headlines = { good: 'Your site is fast', average: 'Your site could be faster', poor: 'Your site is slow' };
    let last = null, timer = 0;

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const icons = { good: '<path d="M5 12.5l4.5 4.5L19 7.5"/>', average: '<path d="M12 7v6M12 17h.01"/>', poor: '<path d="M7 7l10 10M17 7L7 17"/>' };
    const metricCard = m => `<div class="speed-metric" data-rating="${m.rating}">
        <div class="speed-metric__top">
            <span class="speed-metric__icon" aria-hidden="true"><svg viewBox="0 0 24 24">${icons[m.rating]}</svg></span>
            <span class="speed-metric__label">${esc(m.label)}</span>
            <span class="speed-metric__abbr">${esc(m.abbr || '')}</span>
        </div>
        <div class="speed-metric__value"><strong>${esc(m.value)}</strong><span class="speed-badge">${labels[m.rating]}</span></div>
        <div class="speed-scale" aria-hidden="true"><span class="speed-scale__mark" style="left:${Number(m.position) || 0}%"></span></div>
        <div class="speed-scale__labels" aria-hidden="true"><span>Good</span><span>Needs work</span><span>Poor</span></div>
        ${m.help ? `<p>${esc(m.help)}</p>` : ''}
    </div>`;
    const impactLabel = { high: 'High impact', medium: 'Medium impact', low: 'Low impact' };

    const show = data => {
        last = data;
        const g = $('.speed-gauge');
        g.dataset.rating = data.rating;
        g.style.setProperty('--score', data.score);
        r('score').textContent = data.score;
        r('headline').textContent = headlines[data.rating];
        r('summary').textContent = data.summary;
        r('url').textContent = data.final_url || data.url;
        r('device').textContent = data.strategy === 'desktop' ? 'Desktop' : 'Mobile';
        r('date').textContent = new Date(data.tested_at).toLocaleString();
        const shot = r('shot');
        if (data.screenshot) { shot.src = data.screenshot; shot.hidden = false; } else shot.hidden = true;
        r('metrics').innerHTML = data.metrics.map(metricCard).join('');
        r('field-wrap').hidden = !data.field.length;
        r('field').innerHTML = data.field.map(metricCard).join('');
        r('fixes').innerHTML = data.fixes.length
            ? data.fixes.map(f => `<li data-impact="${f.impact}"><div class="speed-fix__body">
                    <div class="speed-fix__head"><strong>${esc(f.title)}</strong><span class="speed-impact">${impactLabel[f.impact] || ''}</span></div>
                    <p>${esc(f.description)}</p>
                    <div class="speed-fix__bar" aria-hidden="true"><span style="width:${Number(f.share) || 0}%"></span></div>
                </div><span class="speed-save">save ~${esc(f.savings)}</span></li>`).join('')
            : '<li><div><strong>No big problems found</strong><p>Nice work — this page already avoids the common speed issues.</p></div></li>';
        report.hidden = false;
        report.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(null, '', `?url=${encodeURIComponent(data.url)}&strategy=${data.strategy}`);
        window.track?.('speed_test', { score: data.score, device: data.strategy });
    };

    const run = async () => {
        errorBox.hidden = true;
        report.hidden = true;
        loading.hidden = false;
        loading.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        let i = 0;
        $('[data-speed-step]').textContent = steps[0];
        timer = setInterval(() => { i = Math.min(i + 1, steps.length - 1); $('[data-speed-step]').textContent = steps[i]; }, 6000);
        try {
            const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.ok) throw new Error(json.error || json.message || 'Something went wrong. Please try again.');
            show(json.report);
        } catch (e) {
            errorBox.textContent = e.message;
            errorBox.hidden = false;
        } finally {
            clearInterval(timer);
            loading.hidden = true;
            btn.disabled = false;
        }
    };

    form.addEventListener('submit', e => {
        e.preventDefault();
        if (!form.url.value.trim()) { form.url.focus(); return; }
        run();
    });
    // PDF is built on the server from the same (cached) result
    $('[data-speed-pdf]').addEventListener('click', e => {
        if (!last) return;
        const btn = e.currentTarget, text = btn.textContent;
        btn.textContent = 'Preparing PDF…';
        window.location.href = `{{ route('speed-test.pdf') }}?url=${encodeURIComponent(last.url)}&strategy=${last.strategy}`;
        window.track?.('speed_pdf', { score: last.score });
        setTimeout(() => { btn.textContent = text; }, 4000);
    });
    $('[data-speed-share]').addEventListener('click', async e => {
        try { await navigator.clipboard.writeText(location.href); e.target.textContent = 'Link copied ✓'; } catch { window.sweet({ icon: 'info', title: 'Copy this link', text: 'Select the link and copy it.', input: { value: location.href, readonly: true, max: 2000 }, confirm: 'Done', cancel: false }); }
    });
    $('[data-speed-again]').addEventListener('click', () => { form.url.value = ''; window.scrollTo({ top: 0, behavior: 'smooth' }); form.url.focus(); });
    // "Get my site fixed": fill in the quote popup with the result
    $('[data-speed-quote]').addEventListener('click', () => {
        if (!last) return;
        const modal = document.getElementById('quote-modal');
        const service = modal?.querySelector('select[name=service]');
        if (service) service.value = 'SEO';
        const ta = modal?.querySelector('textarea[name=message]');
        const msg = `Please help me speed up ${last.final_url || last.url}. Speed score: ${last.score}/100 (${last.strategy}).`;
        if (ta?.quill) ta.quill.setText(msg); else if (ta) ta.value = msg;
    });
    // Shared link (?url=...) runs the test straight away
    if (form.url.value.trim()) run();
})();
</script>
@endsection
