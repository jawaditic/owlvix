{{-- Free tech stack detector: "what is this website built with?" (App\Support\TechDetect). --}}
@extends('layouts.page')

@section('title', 'Tech Stack Detector — What Is This Website Built With?')
@section('description', 'Find out what any website is built with: CMS, e-commerce platform, frameworks, hosting, CDN, analytics and more. Free, instant, no sign-up.')
@section('canonical', route('tech-stack'))
@section('no-auto-quote', '1')

@php
    $svgs = [];
    foreach (['doc', 'pen', 'cart', 'server', 'monitor', 'cloud', 'chart', 'tag', 'card', 'chat', 'shield'] as $i) {
        $svgs[$i] = \App\Support\Icons::svg($i);
    }
    $faq = [
        ['How does the tech stack detector work?', 'It loads the page like a browser and looks for the fingerprints each technology leaves behind — file paths, code snippets, headers and cookies. It only reads what the site sends to every visitor.'],
        ['Why is a technology missing?', 'Some tools leave no visible trace, or only appear on other pages (like checkout or login). Sites can also hide their technology on purpose.'],
        ['Can you rebuild or improve a site built with this?', 'Yes. We work with WordPress, Shopify, Laravel, React, Next.js and more — from speed and security fixes to full rebuilds. Every project starts free.'],
    ];
@endphp

@section('head')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => [
    ['@type' => 'WebApplication', 'name' => 'Owlvix Tech Stack Detector', 'url' => route('tech-stack'), 'applicationCategory' => 'DeveloperApplication', 'operatingSystem' => 'Any', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'], 'provider' => ['@id' => url('/').'#business']],
    ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq)],
]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endsection

@section('content')
@include('partials.tool-page', ['name' => 'Tech stack detector', 'h1' => 'What is this website <span class="accent">built with?</span>', 'lead' => 'See the CMS, platform, frameworks, hosting and marketing tools behind any website — in seconds.', 'action' => route('tech-stack.run'), 'icon' => 'code', 'button' => 'Detect tech stack'])

<section class="speed-report" hidden data-tool-report aria-live="polite">
    <div class="container">
        <div class="speed-summary card tech-summary">
            <span class="svc-icon tech-summary__icon" aria-hidden="true">{!! \App\Support\Icons::svg('code') !!}</span>
            <div class="speed-summary__text">
                <p class="eyebrow">Built with</p>
                <h2 data-t="platform">–</h2>
                <p data-t="summary"></p>
                <p class="speed-summary__meta"><span data-t="url"></span></p>
            </div>
        </div>
        <div class="tech-groups" data-t="groups"></div>
        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl coding'])</div>
            <div>
                <h2 data-t="cta-title">Want a site like this — or a better one?</h2>
                <p>We build, rebuild and speed up websites and apps on every major platform. Start free: we plan it at no cost, then you pay per milestone.</p>
            </div>
            <a href="{{ route('home') }}#contact" class="btn btn--glow" data-quote data-tool-quote>Get a free quote
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Tech stack detection, explained'])

@include('partials.tool-runner')
<script>
(() => {
    const T = n => document.querySelector(`[data-t="${n}"]`), esc = OwlTool.esc;
    const catIcon = { 'CMS': 'doc', 'Website builder': 'pen', 'E-commerce': 'cart', 'Backend': 'server', 'Frontend': 'monitor', 'UI framework': 'pen', 'Hosting & CDN': 'cloud', 'Web server': 'server', 'Analytics': 'chart', 'Marketing': 'tag', 'Payments': 'card', 'Live chat': 'chat', 'Security': 'shield', 'Fonts': 'pen' };
    const svgs = {!! json_encode($svgs, JSON_HEX_TAG) !!};
    let last = null;
    OwlTool.init({
        form: document.querySelector('[data-tool-form]'),
        steps: ['Loading the page like a browser', 'Looking for platform fingerprints', 'Checking hosting, scripts and cookies'],
        event: 'tech_check',
        render: r => {
            last = r;
            T('platform').textContent = r.platform ? r.platform : (r.count ? 'Custom-built website' : 'Nothing detected');
            T('summary').textContent = r.count ? `We found ${r.count} technologies on this page.` : 'This site hides its technology well — or uses tools that leave no trace on this page.';
            T('url').textContent = r.final_url + (r.server ? ` · Server: ${r.server}` : '');
            T('groups').innerHTML = r.groups.map((g, i) => `
                <div class="tech-group">
                    <span class="svc-icon ${i % 2 ? 'is-blue' : ''}" aria-hidden="true">${svgs[catIcon[g.category]] || svgs.monitor}</span>
                    <div><strong>${esc(g.category)}</strong><div class="chips">${g.items.map(n => `<li>${esc(n)}</li>`).join('')}</div></div>
                </div>`).join('');
            T('cta-title').textContent = r.platform ? `Need help with your ${r.platform} site?` : 'Want a site like this — or a better one?';
        },
    });
    document.querySelector('[data-tool-quote]').addEventListener('click', () => {
        const ta = document.querySelector('#quote-modal textarea[name=message]');
        const text = last ? `I'd like help with ${last.final_url}${last.platform ? ' (built with ' + last.platform + ')' : ''}.` : '';
        if (ta?.quill) ta.quill.setText(text); else if (ta) ta.value = text;
    });
})();
</script>
@endsection
