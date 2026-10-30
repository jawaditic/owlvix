{{-- Free meta tag & social preview: reads a page's tags (App\Support\MetaPreview), shows Google / WhatsApp /
     Facebook & LinkedIn / X previews, and generates corrected tags the visitor can edit and copy. --}}
@extends('layouts.page')

@section('title', 'Meta Tag & Social Preview Tool — Google, WhatsApp, Facebook')
@section('description', 'See how your page looks on Google, WhatsApp, Facebook, LinkedIn and X — then fix and copy the perfect meta tags. Free, instant, no sign-up.')
@section('canonical', route('meta-preview'))
@section('no-auto-quote', '1')

@php
    $faq = [
        ['What are meta tags?', 'Small pieces of code in your page\'s head that tell Google and social apps what the page is about: the title, the description and (for sharing) an image. They decide how your link looks in search results and chats.'],
        ['Why does my link show no image on WhatsApp?', 'The page is missing an og:image tag, or the image is too small or blocked. Use an image of at least 1200×630 pixels and an absolute https:// address.'],
        ['I changed my tags but the preview didn\'t update', 'Social apps cache previews. Facebook and LinkedIn have "debugger" tools to refresh them; WhatsApp usually updates within a few days.'],
    ];
@endphp

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Meta Tag & Social Preview', 'url' => route('meta-preview'), 'category' => 'DeveloperApplication', 'faq' => $faq])
@endsection

@section('content')
@include('partials.tool-page', ['name' => 'Meta tag & social preview', 'h1' => 'Meta tag &amp; <span class="accent">social preview</span>', 'lead' => 'See how your page looks on Google, WhatsApp, Facebook, LinkedIn and X — then fix and copy the perfect tags.', 'action' => route('meta-preview.run'), 'icon' => 'eye', 'button' => 'Preview my page'])

<section class="speed-report" hidden data-tool-report aria-live="polite">
    <div class="container">
        <div class="meta-layout">
            <div class="meta-edit card">
                <p class="eyebrow">Edit &amp; preview live</p>
                <h2>Your tags</h2>
                <ul class="meta-issues" data-m="issues"></ul>
                <label>Page title <small data-count="title"></small><input type="text" data-f="title" maxlength="120"></label>
                <label>Description <small data-count="description"></small><textarea data-f="description" rows="3" maxlength="320"></textarea></label>
                <label>Share image URL (og:image) <input type="text" data-f="image" placeholder="https://example.com/share.jpg"></label>
                <label>Site name <input type="text" data-f="site" maxlength="60"></label>
                <label>Page URL <input type="text" data-f="url"></label>
            </div>
            <div class="meta-previews">
                <div class="mp-block">
                    <p class="mp-label">Google search</p>
                    <div class="mp-google card">
                        <div class="mp-google__site"><img data-p="favicon" alt="" width="18" height="18"><span><b data-p="site"></b><small data-p="crumb"></small></span></div>
                        <p class="mp-google__title" data-p="title"></p>
                        <p class="mp-google__desc" data-p="desc"></p>
                    </div>
                </div>
                <div class="mp-row">
                    <div class="mp-block">
                        <p class="mp-label">WhatsApp</p>
                        <div class="mp-wa">
                            <div class="mp-wa__card"><div class="mp-img" data-p="img"></div><div class="mp-wa__text"><b data-p="title"></b><small data-p="desc"></small><small class="mp-host" data-p="host"></small></div></div>
                            <span class="mp-wa__link" data-p="link"></span>
                        </div>
                    </div>
                    <div class="mp-block">
                        <p class="mp-label">Facebook &amp; LinkedIn</p>
                        <div class="mp-fb card"><div class="mp-img mp-img--wide" data-p="img"></div><div class="mp-fb__text"><small class="mp-host" data-p="host"></small><b data-p="title"></b><small data-p="desc"></small></div></div>
                    </div>
                </div>
                <div class="mp-block">
                    <p class="mp-label">X (Twitter)</p>
                    <div class="mp-x"><div class="mp-img mp-img--wide" data-p="img"><span class="mp-x__title" data-p="title"></span></div><small class="mp-host" data-p="from"></small></div>
                </div>
            </div>
        </div>
        <div class="meta-code card">
            <div class="meta-code__head"><h3>Copy these tags into your page's &lt;head&gt;</h3><button type="button" class="btn btn--sm" data-m="copy">Copy code</button></div>
            <pre><code data-m="code"></code></pre>
        </div>
        <div class="lp-cta__box card speed-cta">
            <div class="lp-cta__owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => 'Owlvix owl'])</div>
            <div><h2>Want us to set this up across your site?</h2><p>We add correct titles, descriptions, share images and schema on every page — and fix the SEO basics while we're at it.</p></div>
            <a href="{{ route('service.seo-services') }}#contact" class="btn btn--glow">Get it done <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Meta tags, explained'])

@include('partials.tool-runner')
<script>
(() => {
    const $$ = s => document.querySelectorAll(s), F = n => document.querySelector(`[data-f="${n}"]`), M = n => document.querySelector(`[data-m="${n}"]`);
    const cut = (s, n) => (s.length > n ? s.slice(0, n - 1).trimEnd() + '…' : s);
    const escAttr = s => s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    let favicon = '';
    const update = () => {
        const v = { title: F('title').value.trim(), desc: F('description').value.trim(), image: F('image').value.trim(), site: F('site').value.trim(), url: F('url').value.trim() };
        let host = ''; try { host = new URL(v.url).hostname.replace(/^www\./, ''); } catch {}
        const set = (k, val) => $$(`[data-p="${k}"]`).forEach(el => el.textContent = val);
        set('title', cut(v.title || 'Untitled page', 60)); set('desc', cut(v.desc || 'No description — Google will choose text from your page.', 160));
        set('site', v.site || host); set('crumb', v.url ? cut(v.url.replace(/^https?:\/\//, ''), 50) : ''); set('host', host.toUpperCase()); set('link', v.url); set('from', 'From ' + host);
        $$('[data-p="img"]').forEach(el => { el.style.backgroundImage = v.image ? `url("${v.image.replace(/"/g, '%22')}")` : ''; el.classList.toggle('is-empty', !v.image); });
        $$('[data-p="favicon"]').forEach(el => { el.src = favicon || ''; el.hidden = !favicon; });
        ['title', 'description'].forEach(k => {
            const n = F(k).value.trim().length, max = k === 'title' ? 60 : 160, el = document.querySelector(`[data-count="${k}"]`);
            el.textContent = `${n}/${max}`; el.dataset.over = n > max || n === 0 ? '1' : '';
        });
        const lines = [
            `<title>${escAttr(v.title)}</title>`,
            `<meta name="description" content="${escAttr(v.desc)}">`,
            v.url && `<link rel="canonical" href="${escAttr(v.url)}">`,
            '',
            `<meta property="og:type" content="website">`,
            v.site && `<meta property="og:site_name" content="${escAttr(v.site)}">`,
            `<meta property="og:title" content="${escAttr(v.title)}">`,
            `<meta property="og:description" content="${escAttr(v.desc)}">`,
            v.url && `<meta property="og:url" content="${escAttr(v.url)}">`,
            v.image && `<meta property="og:image" content="${escAttr(v.image)}">`,
            v.image && `<meta property="og:image:width" content="1200">`,
            v.image && `<meta property="og:image:height" content="630">`,
            '',
            `<meta name="twitter:card" content="${v.image ? 'summary_large_image' : 'summary'}">`,
            `<meta name="twitter:title" content="${escAttr(v.title)}">`,
            `<meta name="twitter:description" content="${escAttr(v.desc)}">`,
            v.image && `<meta name="twitter:image" content="${escAttr(v.image)}">`,
        ].filter(l => l !== false && l !== null && l !== undefined && l !== 0);
        M('code').textContent = lines.join('\n').replace(/\n{3,}/g, '\n\n');
    };
    document.querySelectorAll('[data-f]').forEach(el => el.addEventListener('input', update));
    M('copy').addEventListener('click', async e => {
        try { await navigator.clipboard.writeText(M('code').textContent); e.target.textContent = 'Copied ✓'; setTimeout(() => e.target.textContent = 'Copy code', 2000); } catch {}
    });
    OwlTool.init({
        form: document.querySelector('[data-tool-form]'),
        steps: ['Loading the page', 'Reading title, description and social tags', 'Building your previews'],
        event: 'meta_preview',
        render: r => {
            const t = r.tags;
            F('title').value = t['og:title'] || t.title || ''; F('description').value = t['og:description'] || t.description || '';
            F('image').value = t['og:image'] || t['twitter:image'] || ''; F('site').value = t['og:site_name'] || ''; F('url').value = t.canonical || t['og:url'] || r.final_url;
            favicon = t.favicon || '';
            M('issues').innerHTML = r.issues.length
                ? r.issues.map(([s, m]) => `<li data-status="${s}">${s === 'fail' ? '✕' : '!'} ${OwlTool.esc(m)}</li>`).join('')
                : '<li data-status="pass">✓ All the important tags are there.</li>';
            update();
        },
    });
})();
</script>
@endsection
