{{-- Free ads budget & ROI calculator — runs fully in the browser. Platform presets are typical starting numbers
     the visitor can change. The call-to-action is about landing pages: a better page converts more of the same clicks. --}}
@extends('layouts.page')

@php
    // [label, typical cost per click ($), typical conversion rate (%)]
    $platforms = [
        'google' => ['Google Search', 2.70, 4.5, 'search'],
        'shopping' => ['Google Shopping / PMax', 0.70, 2.0, 'cart'],
        'meta' => ['Facebook & Instagram', 1.10, 3.0, 'users'],
        'linkedin' => ['LinkedIn', 5.50, 6.0, 'team'],
        'tiktok' => ['TikTok', 1.00, 1.5, 'phone'],
    ];
    $faq = [
        ['Where do the starting numbers come from?', 'They are typical averages across industries for each platform. Your own cost per click and conversion rate can be very different — use the numbers from your ads account if you have them.'],
        ['What is ROAS?', 'Return on ad spend: the revenue you make for every $1 spent on ads. A ROAS of 4× means $4 of sales for each $1 of ads. Whether that is profitable depends on your margin.'],
        ['How can I get more customers without spending more?', 'Raise your conversion rate. A faster, clearer landing page with one goal often converts 2–3× better than sending ad clicks to a homepage — the same budget then brings in far more leads.'],
        ['What is a break-even cost per click?', 'The most you can pay for a click before the ads lose money, given your conversion rate, close rate, sale value and margin.'],
    ];
@endphp

@section('title', 'Ads Budget & ROI Calculator — Google, Meta, LinkedIn')
@section('description', 'How many leads and sales will your ad budget bring? Calculate clicks, cost per lead, ROAS and ROI for Google, Facebook, LinkedIn and TikTok ads. Free.')
@section('canonical', route('ads-roi'))
@section('no-auto-quote', '1')

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Ads Budget & ROI Calculator', 'url' => route('ads-roi'), 'category' => 'BusinessApplication', 'faq' => $faq])
@endsection

@section('content')
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">Ads ROI calculator</span>
        </nav>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · Instant results</span>
        <h1>What will your <span class="accent">ad budget</span> bring in?</h1>
        <p class="lead">Enter your budget and numbers — see the clicks, leads, sales and return you can expect from Google, Meta, LinkedIn or TikTok ads.</p>
    </div>
</section>

<section class="calc-wrap">
    <div class="container calc">
        <form class="calc__steps" data-roi novalidate onsubmit="return false">
            <div class="calc-step card">
                <p class="calc-step__n">1</p>
                <h2>Where will you advertise?</h2>
                <div class="calc-options roi-platforms">
                    @foreach ($platforms as $key => [$label, $cpc, $cvr, $icon])
                        <label class="calc-opt">
                            <input type="radio" name="platform" value="{{ $key }}" data-cpc="{{ $cpc }}" data-cvr="{{ $cvr }}" @checked($loop->first)>
                            <span class="calc-opt__box"><span class="svc-icon {{ $loop->even ? 'is-blue' : '' }}" aria-hidden="true">{!! \App\Support\Icons::svg($icon) !!}</span><strong>{{ $label }}</strong></span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="calc-step card">
                <p class="calc-step__n">2</p>
                <h2>What is the goal?</h2>
                <div class="calc-options calc-options--2">
                    <label class="calc-opt"><input type="radio" name="goal" value="leads" checked><span class="calc-opt__box calc-opt__box--text"><strong>Leads</strong><small>Form fills, calls or bookings that you then close</small></span></label>
                    <label class="calc-opt"><input type="radio" name="goal" value="sales"><span class="calc-opt__box calc-opt__box--text"><strong>Online sales</strong><small>People buy straight away on your website</small></span></label>
                </div>
            </div>
            <div class="calc-step card">
                <p class="calc-step__n">3</p>
                <h2>Your numbers</h2>
                <div class="roi-fields">
                    <label class="roi-field roi-field--wide">Monthly ad budget
                        <span class="roi-budget"><input type="range" name="budget_range" min="100" max="20000" step="100" value="1500" aria-label="Monthly ad budget slider">
                        <span class="roi-input"><i>$</i><input type="number" name="budget" min="1" step="50" value="1500" inputmode="decimal"></span></span>
                    </label>
                    <label class="roi-field">Cost per click <span class="roi-input"><i>$</i><input type="number" name="cpc" min="0.01" step="0.05" value="2.70" inputmode="decimal"></span></label>
                    <label class="roi-field">Conversion rate <span class="roi-input"><input type="number" name="cvr" min="0.1" max="100" step="0.1" value="4.5" inputmode="decimal"><i>%</i></span><small data-r="cvr-hint">of clicks become leads</small></label>
                    <label class="roi-field" data-leads-only>Leads that become customers <span class="roi-input"><input type="number" name="close" min="1" max="100" step="1" value="20" inputmode="decimal"><i>%</i></span></label>
                    <label class="roi-field">Average sale value <span class="roi-input"><i>$</i><input type="number" name="value" min="1" step="10" value="1000" inputmode="decimal"></span><small>per customer</small></label>
                    <label class="roi-field">Profit margin <span class="roi-input"><input type="number" name="margin" min="1" max="100" step="1" value="40" inputmode="decimal"><i>%</i></span><small>after your costs, before ads</small></label>
                </div>
                <p class="roi-note">Starting numbers are typical averages for the platform — change them to yours.</p>
            </div>
        </form>

        <aside class="calc__result">
            <div class="calc-result card">
                <span class="perch-owl" aria-hidden="true">@include('partials.live-owl', ['width' => 96, 'variant' => 'dev', 'alt' => 'Owlvix owl calculating'])</span>
                <p class="eyebrow">Every month you can expect</p>
                <div class="roi-stats">
                    <div><b data-r="clicks">0</b><span>clicks</span></div>
                    <div><b data-r="conv">0</b><span data-r="conv-label">leads</span></div>
                    <div><b data-r="customers">0</b><span>customers</span></div>
                    <div><b data-r="cpa">$0</b><span data-r="cpa-label">per lead</span></div>
                </div>
                <ul class="calc-result__list roi-money">
                    <li><span>Revenue</span><b data-r="revenue">$0</b></li>
                    <li><span>Return on ad spend (ROAS)</span><b data-r="roas">0×</b></li>
                    <li><span>Profit after ad cost</span><b data-r="profit">$0</b></li>
                    <li><span>Break-even cost per click</span><b data-r="breakeven">$0</b></li>
                </ul>
                <p class="roi-verdict" data-r="verdict"></p>
                <div class="calc-result__free roi-boost" data-r="boost"></div>
                <a href="{{ route('service.landing-page-design') }}#contact" class="btn btn--glow btn--block" data-quote data-roi-quote>Get a landing page that converts
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <p class="calc-result__note">Estimates only — real results depend on your offer, targeting and landing page.</p>
            </div>
        </aside>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Ad returns, explained'])

<script>
(() => {
    const form = document.querySelector('[data-roi]');
    const R = n => document.querySelector(`[data-r="${n}"]`);
    const num = n => Math.max(0, parseFloat(form[n].value) || 0);
    const usd = (n, d = 0) => (n < 0 ? '−$' : '$') + Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
    const whole = n => Math.round(n).toLocaleString('en-US');
    let last = null;
    const calc = () => {
        const leads = form.goal.value === 'leads';
        document.querySelector('[data-leads-only]').hidden = !leads;
        R('cvr-hint').textContent = leads ? 'of clicks become leads' : 'of clicks buy';
        const budget = num('budget'), cpc = num('cpc') || 0.01, cvr = num('cvr') / 100, close = leads ? num('close') / 100 : 1, value = num('value'), margin = num('margin') / 100;
        const clicks = budget / cpc, conv = clicks * cvr, customers = conv * close, revenue = customers * value;
        const profit = revenue * margin - budget, roas = budget ? revenue / budget : 0, breakeven = cvr * close * value * margin;
        R('clicks').textContent = whole(clicks); R('conv').textContent = whole(conv); R('customers').textContent = whole(customers);
        R('conv-label').textContent = leads ? 'leads' : 'orders';
        R('cpa').textContent = conv ? usd(budget / conv) : '—'; R('cpa-label').textContent = leads ? 'per lead' : 'per order';
        R('revenue').textContent = usd(revenue); R('roas').textContent = roas.toFixed(1) + '×';
        R('profit').textContent = usd(profit); R('profit').dataset.neg = profit < 0 ? '1' : '';
        R('breakeven').textContent = usd(breakeven, 2);
        R('verdict').dataset.good = profit >= 0 ? '1' : '';
        R('verdict').textContent = profit >= 0
            ? `Profitable: every $1 of ads returns ${usd(1 + profit / (budget || 1), 2)} after costs.`
            : `Losing money: you would need clicks under ${usd(breakeven, 2)} — or a higher conversion rate — to break even.`;
        // Same budget, better landing page: conversion rate × 1.5
        const better = customers * 1.5, extra = (better - customers) * value * margin;
        R('boost').innerHTML = `<strong>Better landing page, same budget:</strong> at ${(cvr * 150).toFixed(1)}% conversion you'd get about ${whole(better)} customers — <b>${usd(extra)}</b> more profit every month.`;
        last = { platform: form.querySelector('[name=platform]:checked').closest('label').innerText.trim(), budget, clicks, conv, customers, leads, roas };
    };
    // Picking a platform loads its typical numbers
    form.querySelectorAll('[name=platform]').forEach(r => r.addEventListener('change', () => { form.cpc.value = r.dataset.cpc; form.cvr.value = r.dataset.cvr; calc(); }));
    form.budget_range.addEventListener('input', () => { form.budget.value = form.budget_range.value; calc(); });
    form.budget.addEventListener('input', () => { form.budget_range.value = Math.min(20000, num('budget')); });
    form.addEventListener('input', calc);
    form.addEventListener('change', calc);
    calc();
    document.querySelector('[data-roi-quote]').addEventListener('click', () => {
        if (!last) return;
        const modal = document.getElementById('quote-modal');
        const service = modal?.querySelector('select[name=service]'); if (service) service.value = 'Landing page';
        const ta = modal?.querySelector('textarea[name=message]');
        const text = `I'm planning ${last.platform} ads with about ${usd(last.budget)}/month (roughly ${whole(last.clicks)} clicks, ${whole(last.conv)} ${last.leads ? 'leads' : 'orders'}). I'd like a landing page that converts more of those clicks.`;
        if (ta?.quill) ta.quill.setText(text); else if (ta) ta.value = text;
        window.track?.('ads_roi_quote', { platform: last.platform });
    });
})();
</script>
@endsection
