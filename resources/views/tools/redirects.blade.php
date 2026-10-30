{{-- Free redirect & HTTP header checker (App\Support\RedirectCheck). --}}
@extends('layouts.page')

@section('title', 'Redirect Checker & HTTP Header Viewer — Free')
@section('description', 'Follow any URL step by step: see every redirect, status code (301, 302, 404…), the time each step takes and the final HTTP headers. Free, no sign-up.')
@section('canonical', route('redirect-check'))
@section('no-auto-quote', '1')

@php
    $faq = [
        ['What is the difference between a 301 and a 302 redirect?', 'A 301 (or 308) says the page moved for good, so Google passes its ranking to the new address. A 302 (or 307) says the move is temporary, so Google may keep the old address in search. Use 301 for permanent moves like http → https.'],
        ['What is a redirect chain?', 'When one redirect leads to another before the page loads — for example http://example.com → https://example.com → https://www.example.com. Each step adds delay. Redirect straight to the final address instead.'],
        ['Why check http, https, www and non-www?', 'People and links use all four. They should all end on one main address; otherwise Google can treat them as separate sites and split your rankings.'],
    ];
@endphp

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Redirect & HTTP Header Checker', 'url' => route('redirect-check'), 'category' => 'DeveloperApplication', 'faq' => $faq])
@endsection

@section('content')
@include('partials.tool-page', ['name' => 'Redirect checker', 'h1' => 'Redirect &amp; <span class="accent">HTTP header</span> checker', 'lead' => 'Follow any address step by step — every redirect, status code and header, plus a check that all versions of your domain end in one place.', 'action' => route('redirect-check.run'), 'icon' => 'refresh', 'button' => 'Check redirects', 'placeholder' => 'example.com or http://example.com/old-page'])

<section class="speed-report" hidden data-tool-report aria-live="polite">
    <div class="container">
        <div class="speed-summary card">
            <div class="speed-gauge" data-rating="good">
                <svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="52" class="speed-gauge__track"/><circle cx="60" cy="60" r="52" class="speed-gauge__bar" pathLength="100"/></svg>
                <strong data-t="hops">–</strong>
                <span data-t="hops-label">redirects</span>
            </div>
            <div class="speed-summary__text">
                <p class="eyebrow">Your result</p>
                <h2 data-t="headline">–</h2>
                <p data-t="summary"></p>
                <p class="speed-summary__meta"><span data-t="meta"></span></p>
            </div>
        </div>
        <h3 class="speed-h">Redirect path</h3>
        <ol class="hop-list" data-t="chain"></ol>
        <div data-t="checks"></div>
        <h3 class="speed-h">All versions of your address</h3>
        <div class="variant-list card" data-t="variants"></div>
        <details class="link-more card headers-box" open>
            <summary>Response headers <small>from the final page</small></summary>
            <div class="dns-records" data-t="headers"></div>
        </details>
        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl fixing redirects'])</div>
            <div>
                <h2>Moving or rebuilding your site?</h2>
                <p>We plan and set up redirects so you keep your Google rankings — and fix chains, loops and http/www problems on existing sites.</p>
            </div>
            <a href="{{ route('service.seo-services') }}#contact" class="btn btn--glow">Get help
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Redirects, explained'])

@include('partials.tool-runner')
<script>
(() => {
    const T = n => document.querySelector(`[data-t="${n}"]`), esc = OwlTool.esc;
    const heads = { good: 'Clean redirects', average: 'Redirects could be better', poor: 'Redirect problems found' };
    const kind = c => !c ? 'err' : c < 300 ? 'ok' : c < 400 ? 'redir' : 'err';
    const word = { 200: 'OK', 301: 'Moved permanently', 302: 'Found (temporary)', 303: 'See other', 307: 'Temporary redirect', 308: 'Permanent redirect', 404: 'Not found', 410: 'Gone', 500: 'Server error', 503: 'Unavailable' };
    OwlTool.init({
        form: document.querySelector('[data-tool-form]'),
        steps: ['Requesting the address', 'Following each redirect', 'Testing http, https, www and non-www'],
        event: 'redirect_check',
        render: r => {
            const g = document.querySelector('.speed-gauge'); g.dataset.rating = r.rating; g.style.setProperty('--score', r.score);
            T('hops').textContent = r.hops; T('hops-label').textContent = r.hops === 1 ? 'redirect' : 'redirects';
            T('headline').textContent = heads[r.rating]; T('summary').textContent = r.summary;
            T('meta').textContent = `${r.final_url} · ${(r.total_ms / 1000).toFixed(1)}s in total`;
            T('chain').innerHTML = r.chain.map(s => `
                <li class="hop" data-kind="${kind(s.code)}">
                    <span class="hop__code">${s.code || '!'}</span>
                    <div><b>${esc(s.url)}</b><small>${s.code ? esc(word[s.code] || '') : esc(s.error || '')}${s.loop ? ' · loops back' : ''} · ${s.ms} ms</small></div>
                </li>`).join('');
            T('checks').innerHTML = OwlTool.checks(r.checks);
            T('variants').innerHTML = r.variants.map(v => `
                <div class="variant" data-kind="${kind(v.code)}"><code>${esc(v.url)}</code><span>→</span><code>${v.code ? esc(v.final) : esc(v.error || 'no response')}</code><small>${v.hops} hop${v.hops === 1 ? '' : 's'}</small></div>`).join('');
            const h = Object.entries(r.headers);
            T('headers').innerHTML = h.length ? h.map(([k, v]) => `<div class="dns-row"><b>${esc(k)}</b><div><code>${esc(v)}</code></div></div>`).join('') : '<p>No headers.</p>';
        },
    });
})();
</script>
@endsection
