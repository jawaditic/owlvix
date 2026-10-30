{{-- Free domain age & expiry checker (App\Support\DomainCheck): RDAP, with WHOIS for endings without RDAP. --}}
@extends('layouts.page')

@section('title', 'Domain Age & Expiry Checker — Free WHOIS Lookup')
@section('description', 'How old is a domain and when does it expire? See the registrar, creation and expiry dates, transfer lock and name servers for any domain. Free, no sign-up.')
@section('canonical', route('domain-check'))
@section('no-auto-quote', '1')

@php
    $faq = [
        ['Where does this information come from?', 'From the domain\'s official registry, using RDAP (the modern replacement for WHOIS). For endings that do not support RDAP yet, such as .pk or .io, we use WHOIS.'],
        ['What happens if my domain expires?', 'Your website and email stop working, usually within days. After a short grace period, anyone can register it — and good domains are snapped up quickly. Turn on auto-renew.'],
        ['Does domain age help SEO?', 'Not directly. Google has said age itself is not a ranking factor, but older domains often have more links and history, which do help.'],
        ['What is a transfer lock?', 'A setting at your registrar that stops anyone moving your domain to another company without your approval. It should always be on unless you are transferring the domain yourself.'],
    ];
@endphp

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Domain Age & Expiry Checker', 'url' => route('domain-check'), 'category' => 'UtilitiesApplication', 'faq' => $faq])
@endsection

@section('content')
@include('partials.tool-page', ['name' => 'Domain age & expiry', 'h1' => 'Domain age &amp; <span class="accent">expiry</span> checker', 'lead' => 'See who a domain is registered with, how old it is, when it expires — and whether it is protected.', 'action' => route('domain-check.run'), 'icon' => 'globe', 'button' => 'Look up domain', 'label' => 'Domain name'])

<section class="speed-report" hidden data-tool-report aria-live="polite">
    <div class="container">
        <div class="speed-summary card domain-summary">
            <div class="links-big" data-rating="good"><strong data-t="big">–</strong><span data-t="big-label">days left</span></div>
            <div class="speed-summary__text">
                <p class="eyebrow">Your result</p>
                <h2 data-t="domain">–</h2>
                <p data-t="summary"></p>
                <p class="speed-summary__meta"><span data-t="source"></span></p>
            </div>
        </div>
        <div class="domain-facts" data-t="facts"></div>
        <div data-t="checks"></div>
        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl'])</div>
            <div>
                <h2>Need help with your domain, hosting or email?</h2>
                <p>We move domains, set up DNS and email, and look after hosting and renewals — so nothing goes offline by surprise.</p>
            </div>
            <a href="{{ route('service.hosting-and-support') }}#contact" class="btn btn--glow">Get help
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Domains, explained'])

@include('partials.tool-runner')
<script>
(() => {
    const T = n => document.querySelector(`[data-t="${n}"]`), esc = OwlTool.esc;
    const fact = (label, value) => value ? `<div class="domain-fact card"><small>${label}</small><b>${esc(value)}</b></div>` : '';
    OwlTool.init({
        form: document.querySelector('[data-tool-form]'),
        steps: ['Finding the domain registry', 'Reading registration details', 'Checking expiry and protection'],
        event: 'domain_check',
        render: r => {
            const big = document.querySelector('.links-big');
            T('domain').textContent = r.domain; T('summary').textContent = r.summary;
            if (!r.registered) {
                big.dataset.rating = 'average'; T('big').textContent = '?'; T('big-label').textContent = 'not registered';
                T('source').textContent = ''; T('facts').innerHTML = ''; T('checks').innerHTML = ''; return;
            }
            big.dataset.rating = r.rating;
            const stale = r.days_left !== null && r.days_left < -60;
            T('big').textContent = r.days_left === null || stale ? '?' : r.days_left;
            T('big-label').textContent = r.days_left === null || stale ? 'expiry unknown' : r.days_left < 0 ? 'days expired' : 'days left';
            if (r.days_left < 0 && !stale) T('big').textContent = Math.abs(r.days_left);
            T('source').textContent = `Source: ${r.source} (official registry data)`;
            T('facts').innerHTML = fact('Registrar', r.registrar) + fact('Domain age', r.age) + fact('Registered', r.created) + fact('Expires', r.expires) + fact('Last updated', r.updated)
                + (r.nameservers.length ? `<div class="domain-fact card domain-fact--wide"><small>Name servers</small><b>${r.nameservers.map(esc).join(' · ')}</b></div>` : '');
            T('checks').innerHTML = OwlTool.checks(r.checks);
        },
    });
})();
</script>
@endsection
