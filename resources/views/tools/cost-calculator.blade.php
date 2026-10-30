{{-- Free project cost calculator — runs fully in the browser from config/cost_calculator.php.
     "Get my exact quote" fills in the quote popup with the chosen project and estimate. --}}
@extends('layouts.page')

@php
    $cc = config('cost_calculator');
    $faq = [
        ['How accurate is this estimate?', 'It is a realistic range based on the projects we build most often. Your exact price depends on the details, so we send a free, fixed-price quote within one business day — and the first stage (plan and key screen designs) is free.'],
        ['Why is there a price range?', 'Two projects with the same features can differ a lot in rules, content and integrations. The range covers a simple and a more involved version; your quote pins it down.'],
        ['Do I pay everything upfront?', 'No. Every project starts free, then you pay milestone by milestone — only after you have seen and approved each one.'],
        ['What affects the price most?', 'The number of screens or pages, user roles and rules, integrations with other systems, and how custom the design is. Starting with a focused first version keeps it affordable.'],
    ];
@endphp

@section('title', 'App & Website Cost Calculator — Free Estimate')
@section('description', 'How much does an app or website cost? Pick your project type and features to get an instant price range and timeline. Free, no sign-up.')
@section('canonical', route('cost-calculator'))
@section('no-auto-quote', '1')

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix App & Website Cost Calculator', 'url' => route('cost-calculator'), 'category' => 'BusinessApplication', 'faq' => $faq])
@endsection

@section('content')
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">Cost calculator</span>
        </nav>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · Instant estimate</span>
        <h1>How much will my <span class="accent">app or website</span> cost?</h1>
        <p class="lead">Pick what you want to build and the features you need — get a realistic price range and timeline in seconds.</p>
    </div>
</section>

<section class="calc-wrap">
    <div class="container calc">
        <form class="calc__steps" data-calc novalidate onsubmit="return false">
            <div class="calc-step card">
                <p class="calc-step__n">1</p>
                <h2>What do you want to build?</h2>
                <div class="calc-options calc-options--types">
                    @foreach ($cc['types'] as $key => $t)
                        <label class="calc-opt">
                            <input type="radio" name="type" value="{{ $key }}" @checked($loop->first)>
                            <span class="calc-opt__box"><span class="svc-icon {{ $loop->even ? 'is-blue' : '' }}" aria-hidden="true">{!! \App\Support\Icons::svg($t['icon']) !!}</span><strong>{{ $t['label'] }}</strong></span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="calc-step card" data-calc-size>
                <p class="calc-step__n">2</p>
                <h2>How big is it?</h2>
                <div class="calc-options calc-options--3">
                    @foreach ($cc['sizes'] as $key => $s)
                        <label class="calc-opt"><input type="radio" name="size" value="{{ $key }}" @checked($loop->first)><span class="calc-opt__box calc-opt__box--text"><strong>{{ $s['label'] }}</strong><small>{{ $s['hint'] }}</small></span></label>
                    @endforeach
                </div>
            </div>
            <div class="calc-step card">
                <p class="calc-step__n" data-calc-num="3">3</p>
                <h2>What design level?</h2>
                <div class="calc-options calc-options--3">
                    @foreach ($cc['design'] as $key => $d)
                        <label class="calc-opt"><input type="radio" name="design" value="{{ $key }}" @checked($key === 'custom')><span class="calc-opt__box calc-opt__box--text"><strong>{{ $d['label'] }}</strong><small>{{ $d['hint'] }}</small></span></label>
                    @endforeach
                </div>
            </div>
            <div class="calc-step card">
                <p class="calc-step__n" data-calc-num="4">4</p>
                <h2>Which features do you need?</h2>
                <div class="calc-features">
                    @foreach ($cc['features'] as $key => [$label])
                        <label class="calc-feat" data-feature="{{ $key }}"><input type="checkbox" name="features[]" value="{{ $key }}"><span>{{ $label }}</span></label>
                    @endforeach
                </div>
            </div>
        </form>

        <aside class="calc__result">
            <div class="calc-result card">
                <span class="perch-owl" aria-hidden="true">@include('partials.live-owl', ['width' => 96, 'variant' => 'dev', 'alt' => 'Owlvix owl calculating'])</span>
                <p class="eyebrow">Your estimate</p>
                <p class="calc-result__price" aria-live="polite"><span data-c="min">$0</span> – <span data-c="max">$0</span></p>
                <p class="calc-result__time">⏱ <span data-c="weeks">–</span></p>
                <ul class="calc-result__list" data-c="list"></ul>
                <p class="calc-result__free"><strong>Start free:</strong> plan + key screen designs at no cost, then pay per milestone.</p>
                <a href="{{ route('home') }}#contact" class="btn btn--glow btn--block" data-quote data-calc-quote>Get my exact fixed quote
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <p class="calc-result__note">An estimate based on typical projects — your free quote gives the exact fixed price within one business day.</p>
            </div>
        </aside>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Project costs, explained'])

<script>
(() => {
    const C = @json($cc);
    const form = document.querySelector('[data-calc]');
    const out = n => document.querySelector(`[data-c="${n}"]`);
    const money = n => '$' + (Math.round(n / 50) * 50).toLocaleString('en-US');
    let last = null;
    const calc = () => {
        const type = form.type.value, t = C.types[type];
        const sizeKey = t.sizes ? form.size.value : 'small', size = C.sizes[sizeKey], design = C.design[form.design.value];
        document.querySelector('[data-calc-size]').hidden = !t.sizes;
        document.querySelectorAll('[data-calc-num]').forEach((el, i) => el.textContent = (t.sizes ? 3 : 2) + i);
        // Show only the features that make sense for this project type
        const chosen = [];
        document.querySelectorAll('[data-feature]').forEach(el => {
            const f = C.features[el.dataset.feature], ok = f[4] === '*' || f[4].includes(type);
            el.hidden = !ok;
            if (!ok) el.querySelector('input').checked = false;
            if (ok && el.querySelector('input').checked) chosen.push(f);
        });
        let min = t.min * size.factor, max = t.max * size.factor, weeksMin = t.weeks[0], weeksMax = t.weeks[1] * (size.factor > 1 ? 1.3 : 1);
        chosen.forEach(f => { min += f[1]; max += f[2]; weeksMax += f[3]; weeksMin += f[3] * .5; });
        min *= design.factor; max *= design.factor;
        weeksMin = Math.max(1, Math.round(weeksMin)); weeksMax = Math.max(weeksMin, Math.round(weeksMax));
        out('min').textContent = money(min); out('max').textContent = money(max);
        out('weeks').textContent = weeksMin === weeksMax ? `about ${weeksMin} week${weeksMin > 1 ? 's' : ''}` : `${weeksMin}–${weeksMax} weeks`;
        out('list').innerHTML = [`<li><span>${t.label}${t.sizes ? ' · ' + size.label.toLowerCase() : ''}</span></li>`, `<li><span>${design.label}</span></li>`]
            .concat(chosen.map(f => `<li><span>+ ${f[0]}</span></li>`)).join('');
        last = { type: t, size: t.sizes ? size : null, design, chosen, min, max, weeks: out('weeks').textContent };
    };
    form.addEventListener('change', calc);
    calc();
    // Fill the quote popup with the chosen project
    document.querySelector('[data-calc-quote]').addEventListener('click', () => {
        if (!last) return;
        const modal = document.getElementById('quote-modal');
        const service = modal?.querySelector('select[name=service]'); if (service) service.value = last.type.service;
        const budget = modal?.querySelector('select[name=budget]');
        if (budget) budget.value = last.max <= 500 ? 'Under $500' : last.max <= 2000 ? '$500 – $2,000' : last.max <= 5000 ? '$2,000 – $5,000' : '$5,000+';
        const ta = modal?.querySelector('textarea[name=message]');
        const text = `${last.type.label}${last.size ? ' (' + last.size.label.toLowerCase() + ')' : ''}, ${last.design.label.toLowerCase()}${last.chosen.length ? ', with ' + last.chosen.map(f => f[0].toLowerCase()).join(', ') : ''}. Calculator estimate: ${money(last.min)}–${money(last.max)}, ${last.weeks}.`;
        if (ta?.quill) ta.quill.setText(text); else if (ta) ta.value = text;
        window.track?.('cost_calculator_quote', { type: last.type.label });
    });
})();
</script>
@endsection
