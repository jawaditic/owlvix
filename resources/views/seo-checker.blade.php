{{-- Free SEO checker. The form posts to seo-checker.run (SeoCheckerController / App\Support\SeoCheck); the
     report is filled in by the script below. "Email me these tips" posts to seo-checker.email and saves a lead. --}}
@extends('layouts.page')

@section('title', 'Free SEO Checker — Instant SEO Audit & Tips')
@section('description', 'Check any web page for SEO problems in seconds: titles, meta descriptions, headings, speed, mobile, schema, robots.txt and sitemap — with a tip for every issue.')
@section('canonical', route('seo-checker'))
@section('no-auto-quote', '1')

@section('head')
@php
    $faq = [
        ['What does the SEO checker test?', 'It checks around 20 on-page and technical SEO basics: page title, meta description, headings, amount of text, image alt text, internal links, HTTPS, indexability, canonical tag, mobile viewport, language, structured data, robots.txt, XML sitemap, server response, compression, redirects and social sharing tags.'],
        ['Is a high score a guarantee of good rankings?', 'No. These checks cover the foundations Google needs to find and understand a page. Rankings also depend on content quality, competition and links from other sites — but fixing the basics is always the first step.'],
        ['How often should I check my site?', 'Whenever you publish or change an important page, and at least every few months. Plugins, theme updates and new pages can quietly undo good SEO settings.'],
        ['Is it free?', 'Yes, completely free with no sign-up. If you would like us to fix what it finds, we can — and every project starts free.'],
    ];
    $graph = ['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'WebApplication', 'name' => 'Owlvix SEO Checker', 'url' => route('seo-checker'), 'applicationCategory' => 'BusinessApplication',
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
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><span aria-current="page">SEO checker</span>
        </nav>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · No sign-up</span>
        <h1>Free <span class="accent">SEO checker</span></h1>
        <p class="lead">Find out what is stopping your page from ranking on Google — and get a clear tip for every issue. Takes about 10 seconds.</p>

        <form class="speed-form seo-form card" id="seo-form" data-live action="{{ route('seo-checker.run') }}" method="POST" novalidate>
        <span class="perch-owl" aria-hidden="true">@include('partials.live-owl', ['width' => 96, 'variant' => 'dev', 'alt' => 'Owlvix owl testing websites'])</span>
            @csrf
            <label class="speed-form__url">
                <span class="sr-only">Website address</span>
                <span class="speed-form__icon" aria-hidden="true">{!! \App\Support\Icons::svg('search') !!}</span>
                <input type="text" name="url" inputmode="url" autocomplete="url" placeholder="example.com" value="{{ $url }}" required maxlength="500">
            </label>
            <button type="submit" class="btn btn--glow">Check my SEO
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </form>
        <p class="speed-error" role="alert" hidden data-seo-error></p>
    </div>
</section>

<section class="speed-loading" hidden data-seo-loading>
    <div class="container container--narrow">
        <div class="card speed-loading__card">
            @include('partials.live-owl', ['width' => 140, 'variant' => 'dev', 'alt' => 'Owlvix owl checking your website'])
            <div>
                <strong>Checking your page…</strong>
                <p data-seo-step>Reading your page like Google does</p>
                <div class="speed-loading__bar"><span></span></div>
                <small>This usually takes 5–15 seconds.</small>
            </div>
        </div>
    </div>
</section>

<section class="speed-report" hidden data-seo-report aria-live="polite">
    <div class="container">
        <div class="speed-summary card">
            <div class="speed-gauge" data-rating="good">
                <svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="52" class="speed-gauge__track"/><circle cx="60" cy="60" r="52" class="speed-gauge__bar" pathLength="100"/></svg>
                <strong data-s="score">–</strong>
                <span>SEO score</span>
            </div>
            <div class="speed-summary__text">
                <p class="eyebrow">Your result</p>
                <h2 data-s="headline">–</h2>
                <p data-s="summary"></p>
                <p class="speed-summary__meta"><span data-s="url"></span></p>
                <div class="seo-tally">
                    <span class="seo-tally__i" data-k="fail"><b data-s="fail">0</b> to fix first</span>
                    <span class="seo-tally__i" data-k="warn"><b data-s="warn">0</b> to improve</span>
                    <span class="seo-tally__i" data-k="pass"><b data-s="pass">0</b> passed</span>
                </div>
                <div class="speed-actions">
                    <a href="#seo-email" class="btn btn--sm">Email me these tips</a>
                    <a href="#ai-audit" class="btn btn--ghost btn--sm">Full SEO audit</a>
                    <button type="button" class="btn btn--ghost btn--sm" data-seo-share>Copy share link</button>
                    <a href="{{ route('speed-test') }}" class="btn btn--ghost btn--sm" data-seo-speed>Test speed too</a>
                </div>
            </div>
        </div>

        <div class="seo-groups" data-s="groups"></div>

        {{-- Email the tips (saves a lead) --}}
        <div class="card seo-email" id="seo-email">
            <div>
                <p class="eyebrow">Keep these tips</p>
                <h2>Email me my SEO tips</h2>
                <p>Get the full list in your inbox, ordered by what to fix first. No spam — just your report.</p>
            </div>
            <form data-seo-email data-live action="{{ route('seo-checker.email') }}" method="POST" novalidate>
                @csrf
                <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <input type="text" name="name" placeholder="Your name (optional)" maxlength="100" autocomplete="name" aria-label="Your name">
                <input type="email" name="email" placeholder="you@company.com" required maxlength="150" autocomplete="email" aria-label="Your email">
                <button type="submit" class="btn btn--glow">Send my tips</button>
                <p class="seo-email__msg" role="status" hidden></p>
                <p class="form__privacy">We'll email your report and may follow up once. <a href="{{ route('privacy') }}">Privacy policy</a></p>
            </form>
        </div>

        @include('partials.audit-card')

        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl ready to fix your SEO'])</div>
            <div>
                <h2>Want us to fix it for you?</h2>
                <p>We make these fixes directly in your site — WordPress, Shopify, Laravel or custom code. Start free: we plan the work at no cost, then you pay per milestone.</p>
            </div>
            <a href="{{ route('home') }}#contact" class="btn btn--glow" data-quote data-seo-quote>Get my SEO fixed
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
    </div>
</section>

<section class="section section--alt speed-info">
    <div class="container container--narrow">
        <p class="eyebrow">Good to know</p>
        <h2>SEO checks, explained</h2>
        <div class="faq">
            @foreach ($faq as [$q, $a])
                <details><summary>{{ $q }}</summary><p>{{ $a }}</p></details>
            @endforeach
        </div>
        <p class="speed-info__more">Also try our <a href="{{ route('speed-test') }}">free website speed test</a>, or see our <a href="{{ route('service.seo-services') }}">SEO services</a>.</p>
    </div>
</section>

<script>
(() => {
    const form = document.getElementById('seo-form');
    const $ = s => document.querySelector(s), S = n => document.querySelector(`[data-s="${n}"]`);
    const loading = $('[data-seo-loading]'), report = $('[data-seo-report]'), errorBox = $('[data-seo-error]');
    const steps = ['Reading your page like Google does', 'Checking titles, headings and content', 'Looking at robots.txt and your sitemap', 'Writing your tips'];
    const heads = { good: 'Your SEO basics look strong', average: 'Your SEO needs some work', poor: 'Your SEO needs attention' };
    const icon = { pass: '<path d="M5 12.5l4.5 4.5L19 7.5"/>', warn: '<path d="M12 7v6M12 17h.01"/>', fail: '<path d="M7 7l10 10M17 7L7 17"/>' };
    const word = { pass: 'Passed', warn: 'Improve', fail: 'Fix first' };
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    let last = null, timer = 0;

    const show = d => {
        last = d;
        const g = $('.speed-gauge'); g.dataset.rating = d.rating; g.style.setProperty('--score', d.score);
        S('score').textContent = d.score; S('headline').textContent = heads[d.rating]; S('summary').textContent = d.summary;
        S('url').textContent = d.final_url; ['fail', 'warn', 'pass'].forEach(k => S(k).textContent = d.counts[k] || 0);
        $('[data-audit-site]').textContent = new URL(d.final_url).hostname;
        document.querySelector('[data-seo-audit] input[name=url]').value = d.url;
        const groups = {};
        d.checks.forEach(c => (groups[c.cat] ||= []).push(c));
        S('groups').innerHTML = Object.entries(groups).map(([cat, list]) => `
            <h3 class="speed-h">${esc(cat)} <small>${list.filter(c => c.status === 'pass').length}/${list.length} passed</small></h3>
            <div class="seo-checks">${list.map(c => `
                <div class="seo-check" data-status="${c.status}">
                    <span class="speed-metric__icon" aria-hidden="true"><svg viewBox="0 0 24 24">${icon[c.status]}</svg></span>
                    <div class="seo-check__body">
                        <div class="seo-check__head"><strong>${esc(c.title)}</strong><span class="speed-badge">${word[c.status]}</span></div>
                        <p class="seo-check__found">${esc(c.found)}</p>
                        ${c.tip ? `<p class="seo-check__tip"><b>Tip:</b> ${esc(c.tip)}</p>` : ''}
                    </div>
                </div>`).join('')}</div>`).join('');
        $('[data-seo-speed]').href = `{{ route('speed-test') }}?url=${encodeURIComponent(d.url)}`;
        report.hidden = false;
        report.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(null, '', `?url=${encodeURIComponent(d.url)}`);
        window.track?.('seo_check', { score: d.score });
    };

    const run = async () => {
        errorBox.hidden = true; report.hidden = true; loading.hidden = false;
        const btn = form.querySelector('button'); btn.disabled = true;
        let i = 0; $('[data-seo-step]').textContent = steps[0];
        timer = setInterval(() => { i = Math.min(i + 1, steps.length - 1); $('[data-seo-step]').textContent = steps[i]; }, 3000);
        try {
            const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.ok) throw new Error(json.error || json.message || 'Something went wrong. Please try again.');
            show(json.report);
        } catch (e) { errorBox.textContent = e.message; errorBox.hidden = false; }
        finally { clearInterval(timer); loading.hidden = true; btn.disabled = false; }
    };
    form.addEventListener('submit', e => { e.preventDefault(); if (!form.url.value.trim()) { form.url.focus(); return; } run(); });

    // Email me these tips
    const ef = $('[data-seo-email]'), msg = ef.querySelector('.seo-email__msg');
    ef.addEventListener('submit', async e => {
        e.preventDefault();
        if (!last) return;
        const fd = new FormData(ef); fd.append('url', last.url);
        const btn = ef.querySelector('button'); btn.disabled = true; btn.textContent = 'Sending…';
        try {
            fd.append('g-recaptcha-response', await (window.owlvixCaptcha?.('seo_email') ?? ''));
            const res = await fetch(ef.action, { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.ok) throw new Error(json.error || json.errors?.email?.[0] || json.message || 'Could not send. Please try again.');
            msg.textContent = '✓ Sent! Check your inbox (and spam folder) in a minute.'; msg.dataset.ok = '1';
            ef.querySelectorAll('input').forEach(i => i.disabled = true);
            window.track?.('generate_lead', { form: 'seo_checker' });
        } catch (err) { msg.textContent = err.message; msg.dataset.ok = ''; btn.disabled = false; }
        finally { msg.hidden = false; btn.textContent = 'Send my tips'; }
    });

    $('[data-seo-share]').addEventListener('click', async e => {
        try { await navigator.clipboard.writeText(location.href); e.target.textContent = 'Link copied ✓'; } catch { window.sweet({ icon: 'info', title: 'Copy this link', text: 'Select the link and copy it.', input: { value: location.href, readonly: true, max: 2000 }, confirm: 'Done', cancel: false }); }
    });
    $('[data-seo-quote]').addEventListener('click', () => {
        if (!last) return;
        const modal = document.getElementById('quote-modal');
        const sel = modal?.querySelector('select[name=service]'); if (sel) sel.value = 'SEO';
        const ta = modal?.querySelector('textarea[name=message]');
        const text = `Please help me fix the SEO of ${last.final_url}. SEO checker score: ${last.score}/100 (${last.counts.fail} critical, ${last.counts.warn} warnings).`;
        if (ta?.quill) ta.quill.setText(text); else if (ta) ta.value = text;
    });
    if (form.url.value.trim()) run();
})();
</script>
@endsection
