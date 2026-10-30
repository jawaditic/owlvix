{{-- Free website security & SSL checker (App\Support\SecurityCheck). Passive checks only. --}}
@extends('layouts.page')

@section('title', 'Website Security & SSL Checker — Free Scan')
@section('description', 'Check any website\'s SSL certificate, HTTPS, security headers, cookies and version leaks — with a clear fix for every issue. Free, instant, no sign-up.')
@section('canonical', route('security-checker'))
@section('no-auto-quote', '1')

@php
    $faq = [
        ['What does the security checker test?', 'It checks HTTPS and the http→https redirect, the SSL certificate (trust, domain, expiry, TLS version), security headers (HSTS, CSP, clickjacking, MIME sniffing, referrer and permissions policies), cookie flags, software version leaks and mixed content.'],
        ['Is this a full penetration test?', 'No. It is a safe, passive check of what your site sends to every visitor. It does not try to break in or look for hidden files. For a deeper review, our team can audit your site and server.'],
        ['My site scored low — is it hacked?', 'Not necessarily. Most low scores come from missing protective headers, which are quick to add. Red items like an expired certificate or no HTTPS should be fixed first.'],
    ];
@endphp

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Website Security & SSL Checker', 'url' => route('security-checker'), 'category' => 'SecurityApplication', 'faq' => $faq])
@endsection

@section('content')
@include('partials.tool-page', ['name' => 'Security & SSL checker', 'h1' => 'Website <span class="accent">security &amp; SSL</span> checker', 'lead' => 'Check your SSL certificate, HTTPS and security headers — and get a clear fix for every issue.', 'action' => route('security-checker.run'), 'icon' => 'shield', 'button' => 'Check security'])

<section class="speed-report" hidden data-tool-report aria-live="polite">
    <div class="container">
        <div class="speed-summary card">
            <div class="speed-gauge" data-rating="good">
                <svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="52" class="speed-gauge__track"/><circle cx="60" cy="60" r="52" class="speed-gauge__bar" pathLength="100"/></svg>
                <strong data-t="score">–</strong>
                <span>Security</span>
            </div>
            <div class="speed-summary__text">
                <p class="eyebrow">Your result</p>
                <h2 data-t="headline">–</h2>
                <p data-t="summary"></p>
                <p class="speed-summary__meta"><span data-t="url"></span></p>
                <div class="seo-tally">
                    <span class="seo-tally__i" data-k="fail"><b data-t="fail">0</b> to fix first</span>
                    <span class="seo-tally__i" data-k="warn"><b data-t="warn">0</b> to improve</span>
                    <span class="seo-tally__i" data-k="pass"><b data-t="pass">0</b> passed</span>
                </div>
                <p class="sec-cert" data-t="cert" hidden></p>
            </div>
        </div>
        <div data-t="checks"></div>
        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl securing a website'])</div>
            <div>
                <h2>Want us to secure it for you?</h2>
                <p>We fix SSL, headers, updates and hosting settings — and keep your site patched with ongoing support. Start free: we plan it at no cost.</p>
            </div>
            <a href="{{ route('service.hosting-and-support') }}#contact" class="btn btn--glow">Get it fixed
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Website security, explained'])

@include('partials.tool-runner')
<script>
(() => {
    const T = n => document.querySelector(`[data-t="${n}"]`);
    const heads = { good: 'Well protected', average: 'Some protections are missing', poor: 'Your site needs attention' };
    OwlTool.init({
        form: document.querySelector('[data-tool-form]'),
        steps: ['Connecting securely to the site', 'Reading the SSL certificate', 'Checking security headers and cookies'],
        event: 'security_check',
        render: r => {
            const g = document.querySelector('.speed-gauge'); g.dataset.rating = r.rating; g.style.setProperty('--score', r.score);
            T('score').textContent = r.score; T('headline').textContent = heads[r.rating]; T('summary').textContent = r.summary; T('url').textContent = r.final_url;
            ['fail', 'warn', 'pass'].forEach(k => T(k).textContent = r.counts[k] || 0);
            const c = r.cert, cert = T('cert');
            cert.hidden = !c;
            if (c) cert.innerHTML = `🔒 Certificate by <b>${OwlTool.esc(c.issuer)}</b> · ${c.days_left >= 0 ? `expires in <b>${c.days_left} days</b>` : '<b>expired</b>'}${c.protocol ? ` · ${OwlTool.esc(c.protocol)}` : ''}`;
            T('checks').innerHTML = OwlTool.checks(r.checks);
        },
    });
})();
</script>
@endsection
