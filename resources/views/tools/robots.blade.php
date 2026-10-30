{{-- Free robots.txt & AI crawler checker (App\Support\RobotsCheck). --}}
@extends('layouts.page')

@section('title', 'Robots.txt Checker — Can Google & AI Bots Read Your Site?')
@section('description', 'Test your robots.txt, sitemap and noindex tags, and see whether Google, ChatGPT, Claude, Perplexity and other AI crawlers can read your site. Free.')
@section('canonical', route('robots-check'))
@section('no-auto-quote', '1')

@php
    $faq = [
        ['What is robots.txt?', 'A small text file at yoursite.com/robots.txt that tells search engines and other bots which parts of your site they may crawl. It also points them to your sitemap.'],
        ['Should I block AI crawlers?', 'There are two kinds. AI search bots (OAI-SearchBot, Claude-SearchBot, PerplexityBot) fetch pages to show and cite them in answers — blocking them means AI assistants cannot recommend you. AI training bots (GPTBot, ClaudeBot, Google-Extended, CCBot) collect content to train models — blocking those is a business choice and does not affect Google Search.'],
        ['Does robots.txt remove a page from Google?', 'No. It only stops crawling. To keep a page out of Google, allow crawling and add a "noindex" robots meta tag.'],
        ['Why is my sitemap important?', 'It lists every page you want found, so Google discovers new and updated pages faster. Link it from robots.txt and submit it in Google Search Console.'],
    ];
@endphp

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Robots.txt & AI Crawler Checker', 'url' => route('robots-check'), 'category' => 'DeveloperApplication', 'faq' => $faq])
@endsection

@section('content')
@include('partials.tool-page', ['name' => 'Robots.txt checker', 'h1' => 'Can Google and <span class="accent">AI bots</span> read your site?', 'lead' => 'Check your robots.txt, sitemap and noindex tags — and see which search engines and AI assistants are allowed in.', 'action' => route('robots-check.run'), 'icon' => 'book', 'button' => 'Check my site', 'placeholder' => 'example.com or a full page address'])

<section class="speed-report" hidden data-tool-report aria-live="polite">
    <div class="container">
        <div class="speed-summary card">
            <div class="speed-gauge" data-rating="good">
                <svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="52" class="speed-gauge__track"/><circle cx="60" cy="60" r="52" class="speed-gauge__bar" pathLength="100"/></svg>
                <strong data-t="score">–</strong>
                <span>Crawlability</span>
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
            </div>
        </div>
        <div data-t="checks"></div>
        <h3 class="speed-h">Search &amp; AI bots <small>can they read your site?</small></h3>
        <div class="bot-groups" data-t="bots"></div>
        <details class="link-more card" data-t="robots-box">
            <summary>Your robots.txt</summary>
            <pre class="robots-txt"><code data-t="robots"></code></pre>
        </details>
        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl'])</div>
            <div>
                <h2>Want to be found on Google and in AI answers?</h2>
                <p>We fix crawling and indexing problems, set up sitemaps, and get your site ready for AI search — then help it rank.</p>
            </div>
            <a href="{{ route('service.seo-services') }}#contact" class="btn btn--glow">Get help
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Robots.txt & AI crawlers, explained'])

@include('partials.tool-runner')
<script>
(() => {
    const T = n => document.querySelector(`[data-t="${n}"]`), esc = OwlTool.esc;
    const heads = { good: 'Google and AI assistants can read this page', average: 'Mostly readable, a few things to fix', poor: 'Google cannot properly crawl or index this page' };
    const groups = [['search', 'Search engines', 'These decide if you appear in Google and Bing.'], ['ai-search', 'AI answer engines', 'These fetch pages to show and cite them in AI answers.'], ['ai-training', 'AI training bots', 'These collect content to train AI models. Allowing or blocking them is your choice.']];
    OwlTool.init({
        form: document.querySelector('[data-tool-form]'),
        steps: ['Loading the page', 'Reading robots.txt', 'Checking the sitemap', 'Testing search and AI bots'],
        event: 'robots_check',
        render: r => {
            const g = document.querySelector('.speed-gauge'); g.dataset.rating = r.rating; g.style.setProperty('--score', r.score);
            T('score').textContent = r.score; T('headline').textContent = heads[r.rating]; T('summary').textContent = r.summary; T('url').textContent = r.final_url;
            ['fail', 'warn', 'pass'].forEach(k => T(k).textContent = r.counts[k] || 0);
            T('checks').innerHTML = OwlTool.checks(r.checks);
            T('bots').innerHTML = groups.map(([k, title, note]) => `
                <div class="bot-group card"><h4>${title}</h4><p>${note}</p>
                    ${r.bots.filter(b => b.kind === k).map(b => `
                        <div class="bot" data-ok="${b.allowed ? 1 : 0}"><span class="bot__dot" aria-hidden="true"></span>
                            <div><b>${esc(b.bot)}</b><small>${esc(b.owner)} · ${esc(b.purpose)}</small></div>
                            <span class="bot__state">${b.allowed ? 'Allowed' : 'Blocked'}</span></div>`).join('')}
                </div>`).join('');
            T('robots-box').hidden = !r.robots;
            T('robots').textContent = r.robots || '';
        },
    });
})();
</script>
@endsection
