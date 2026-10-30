{{-- Free broken link checker (App\Support\BrokenLinks): homepage + up to 7 linked pages, every link and image checked. --}}
@extends('layouts.page')

@section('title', 'Broken Link Checker — Find Dead Links Free')
@section('description', 'Find broken links, missing images and redirects on your website. We scan your pages and show exactly where each dead link is. Free, no sign-up.')
@section('canonical', route('broken-links'))
@section('no-auto-quote', '1')

@php
    $faq = [
        ['How does the broken link checker work?', 'It reads your homepage and up to 7 pages it links to, collects every link and image (up to 150), and checks each one. You see which ones are broken and the page they appear on.'],
        ['Why do broken links matter?', 'Visitors who hit a "page not found" often leave, and Google sees many dead links as a sign that a site is not looked after. Broken images make pages look unfinished.'],
        ['Some links say "could not check" — are they broken?', 'Not necessarily. Sites like LinkedIn, Instagram and some shops block automated checks. Open those links yourself to be sure.'],
        ['How do I fix a broken link?', 'Update the link to the right address, remove it, or — if the page moved on your own site — add a 301 redirect from the old address to the new one.'],
    ];
@endphp

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Broken Link Checker', 'url' => route('broken-links'), 'category' => 'DeveloperApplication', 'faq' => $faq])
@endsection

@section('content')
@include('partials.tool-page', ['name' => 'Broken link checker', 'h1' => 'Find <span class="accent">broken links</span> on your website', 'lead' => 'We scan your pages, check every link and image, and show you exactly where each dead link is.', 'action' => route('broken-links.run'), 'icon' => 'link', 'button' => 'Find broken links', 'wait' => 'We check up to 150 links — this usually takes 15–45 seconds.'])

<section class="speed-report" hidden data-tool-report aria-live="polite">
    <div class="container">
        <div class="speed-summary card links-summary">
            <div class="links-big" data-rating="good"><strong data-t="broken">0</strong><span>broken</span></div>
            <div class="speed-summary__text">
                <p class="eyebrow">Your result</p>
                <h2 data-t="headline">–</h2>
                <p data-t="summary"></p>
                <p class="speed-summary__meta"><span data-t="meta"></span></p>
                <div class="seo-tally">
                    <span class="seo-tally__i" data-k="fail"><b data-t="n-broken">0</b> broken</span>
                    <span class="seo-tally__i" data-k="warn"><b data-t="n-blocked">0</b> could not check</span>
                    <span class="seo-tally__i" data-k="info"><b data-t="n-redirects">0</b> redirects</span>
                    <span class="seo-tally__i" data-k="pass"><b data-t="n-ok">0</b> working</span>
                </div>
            </div>
        </div>
        <div data-t="lists"></div>
        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl fixing links'])</div>
            <div>
                <h2>Want us to fix them for you?</h2>
                <p>We repair broken links, set up redirects, and keep your site healthy with ongoing care — so visitors and Google never hit a dead end.</p>
            </div>
            <a href="{{ route('service.hosting-and-support') }}#contact" class="btn btn--glow">Get it fixed
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Broken links, explained'])

@include('partials.tool-runner')
<script>
(() => {
    const T = n => document.querySelector(`[data-t="${n}"]`), esc = OwlTool.esc;
    const heads = { good: 'No broken links found', average: 'A few broken links to fix', poor: 'Lots of broken links' };
    const short = u => { u = u.replace(/^https?:\/\/(www\.)?/, ''); return u.length > 90 ? u.slice(0, 87) + '…' : u; };
    const link = u => `<a href="${esc(u)}" target="_blank" rel="noopener nofollow">${esc(short(u))}</a>`;
    const row = (i, extra) => `
        <div class="link-row" data-kind="${i.internal ? 'internal' : 'external'}">
            <span class="link-row__code">${i.code || '—'}</span>
            <div class="link-row__body">
                <div class="link-row__url">${i.type === 'image' ? '🖼 ' : ''}${link(i.url)}</div>
                <small>${extra}</small>
                <small class="link-row__on">${i.type === 'image' ? 'Image' : 'Link'} “${esc(i.text)}” · on ${i.pages.map(link).join(', ')}</small>
            </div>
        </div>`;
    OwlTool.init({
        form: document.querySelector('[data-tool-form]'),
        steps: ['Reading your homepage', 'Opening the pages it links to', 'Checking every link and image', 'Double-checking the ones that failed'],
        event: 'broken_links',
        render: r => {
            const c = r.counts;
            document.querySelector('.links-big').dataset.rating = r.rating;
            T('broken').textContent = c.broken; T('headline').textContent = heads[r.rating]; T('summary').textContent = r.summary;
            T('meta').textContent = `${r.final_url} · ${r.pages.length} page${r.pages.length > 1 ? 's' : ''} scanned · ${c.checked} links checked`;
            T('n-broken').textContent = c.broken; T('n-blocked').textContent = c.blocked; T('n-redirects').textContent = c.redirects; T('n-ok').textContent = c.ok;
            let html = '';
            if (r.broken.length) html += `<h3 class="speed-h">Broken links <small>fix these first</small></h3><div class="link-list card">${r.broken.map(i => row(i, `${esc(i.reason)}${i.internal ? ' · on your own site' : ''}`)).join('')}</div>`;
            if (r.blocked.length) html += `<details class="link-more card"><summary>Could not check (${r.blocked.length}) <small>these sites block automated checks — open them to be sure</small></summary>${r.blocked.map(i => row(i, esc(i.reason))).join('')}</details>`;
            if (r.redirects.length) html += `<details class="link-more card"><summary>Redirects (${r.redirects.length}) <small>they work, but linking straight to the final address is faster</small></summary>${r.redirects.map(i => row(i, '→ ' + esc(short(i.location || '')))).join('')}</details>`;
            T('lists').innerHTML = html;
        },
    });
})();
</script>
@endsection
