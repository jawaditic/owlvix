{{-- Free schema markup generator — runs fully in the browser. Pick a type, fill the form, copy the JSON-LD. --}}
@extends('layouts.page')

@php
    $types = [
        'localbusiness' => ['Local business', 'store'],
        'organization' => ['Organization', 'users'],
        'product' => ['Product', 'cart'],
        'article' => ['Article / blog post', 'doc'],
        'faq' => ['FAQ page', 'chat'],
        'breadcrumb' => ['Breadcrumbs', 'link'],
    ];
    $faq = [
        ['What is schema markup?', 'A small block of code (JSON-LD) that tells Google, Bing and AI assistants exactly what is on your page — your business, a product, an article — in a format they can read without guessing.'],
        ['Where do I put the code?', 'Paste it inside the <head> of the page it describes (or just before </body>). In WordPress you can use a header-code plugin or your SEO plugin\'s custom schema box.'],
        ['Will this get me rich results?', 'It makes your page eligible: product prices and ratings, article details, breadcrumbs and business info. Google decides when to show them. Test your code with Google\'s Rich Results Test after adding it.'],
        ['Does FAQ markup still work?', 'Google no longer shows FAQ rich results in search, but FAQ markup still helps search engines and AI assistants understand your questions and answers.'],
    ];
@endphp

@section('title', 'Schema Markup Generator — Free JSON-LD for Google')
@section('description', 'Create schema markup (JSON-LD) for your local business, organization, product, article, FAQ or breadcrumbs. Fill in a form, copy the code. Free, no sign-up.')
@section('canonical', route('schema-generator'))
@section('no-auto-quote', '1')

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Schema Markup Generator', 'url' => route('schema-generator'), 'category' => 'DeveloperApplication', 'faq' => $faq])
@endsection

@section('content')
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">Schema generator</span>
        </nav>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · No sign-up</span>
        <h1>Schema markup <span class="accent">generator</span></h1>
        <p class="lead">Fill in a short form and copy ready-to-paste JSON-LD — so Google and AI assistants understand your business, products and pages.</p>
    </div>
</section>

<section class="calc-wrap">
    <div class="container">
        <div class="schema-types" role="radiogroup" aria-label="Schema type">
            @foreach ($types as $key => [$label, $icon])
                <label class="calc-opt"><input type="radio" name="schema_type" value="{{ $key }}" @checked($loop->first)>
                    <span class="calc-opt__box"><span class="svc-icon {{ $loop->even ? 'is-blue' : '' }}" aria-hidden="true">{!! \App\Support\Icons::svg($icon) !!}</span><strong>{{ $label }}</strong></span>
                </label>
            @endforeach
        </div>
        <div class="meta-layout schema-layout">
            <form class="meta-edit card" data-schema-form novalidate onsubmit="return false">
                <p class="eyebrow">Your details</p>
                <h2 data-s="title">Local business</h2>
                <div class="schema-fields" data-s="fields"></div>
            </form>
            <div class="schema-out">
                <ul class="meta-issues" data-s="issues"></ul>
                <div class="meta-code card">
                    <div class="meta-code__head"><h3>Paste this into your page's &lt;head&gt;</h3><button type="button" class="btn btn--sm" data-s="copy">Copy code</button></div>
                    <pre><code data-s="code"></code></pre>
                    <p class="schema-test">Then check it: <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">Google Rich Results Test</a> · <a href="https://validator.schema.org/" target="_blank" rel="noopener">Schema validator</a></p>
                </div>
                <div class="lp-cta__box card speed-cta schema-cta">
                    <div><h2>Want schema on every page?</h2><p>We add and maintain structured data across your whole site as part of our SEO work — products, articles, reviews and more.</p></div>
                    <a href="{{ route('service.seo-services') }}#contact" class="btn btn--glow">Get it done <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
                </div>
            </div>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Schema markup, explained'])

<script>
(() => {
    const S = n => document.querySelector(`[data-s="${n}"]`);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    // Field: [key, label, kind, required?, placeholder or options]
    const TYPES = {
        localbusiness: { title: 'Local business', fields: [
            ['subtype', 'Business type', 'select', 0, ['LocalBusiness', 'ProfessionalService', 'Store', 'Restaurant', 'Dentist', 'MedicalClinic', 'LegalService', 'AccountingService', 'RealEstateAgent', 'AutoRepair', 'BeautySalon', 'HomeAndConstructionBusiness', 'FinancialService', 'TravelAgency']],
            ['name', 'Business name', 'text', 1, 'Acme Dental'], ['url', 'Website', 'url', 1, 'https://example.com'], ['image', 'Logo or photo URL', 'url', 1, 'https://example.com/logo.png'],
            ['telephone', 'Phone', 'tel', 0, '+1 555 123 4567'], ['email', 'Email', 'email', 0, 'hello@example.com'],
            ['street', 'Street address', 'text', 1, '12 Main Street'], ['city', 'City', 'text', 1, 'Austin'], ['region', 'State / region', 'text', 0, 'TX'], ['postal', 'Postal code', 'text', 0, '78701'], ['country', 'Country code', 'text', 1, 'US'],
            ['hours', 'Opening hours', 'lines', 0, 'Mo-Fr 09:00-17:00\nSa 10:00-14:00'], ['priceRange', 'Price range', 'text', 0, '$$'],
            ['sameAs', 'Social profiles (one per line)', 'lines', 0, 'https://www.facebook.com/acme\nhttps://www.instagram.com/acme'], ['description', 'Short description', 'textarea', 0, ''],
        ] },
        organization: { title: 'Organization', fields: [
            ['name', 'Company name', 'text', 1, 'Acme Inc.'], ['url', 'Website', 'url', 1, 'https://example.com'], ['logo', 'Logo URL', 'url', 1, 'https://example.com/logo.png'],
            ['description', 'Short description', 'textarea', 0, ''], ['email', 'Email', 'email', 0, ''], ['telephone', 'Phone', 'tel', 0, ''], ['foundingDate', 'Founded (year)', 'text', 0, '2018'],
            ['sameAs', 'Social profiles (one per line)', 'lines', 0, 'https://www.linkedin.com/company/acme\nhttps://x.com/acme'],
        ] },
        product: { title: 'Product', fields: [
            ['name', 'Product name', 'text', 1, 'Organic Green Tea'], ['image', 'Image URL', 'url', 1, 'https://example.com/tea.jpg'], ['description', 'Description', 'textarea', 0, ''],
            ['brand', 'Brand', 'text', 0, ''], ['sku', 'SKU', 'text', 0, ''], ['url', 'Product page URL', 'url', 0, ''],
            ['price', 'Price', 'number', 1, '19.99'], ['currency', 'Currency', 'select', 1, ['USD', 'EUR', 'GBP', 'AUD', 'CAD', 'AED', 'SAR', 'PKR', 'INR']],
            ['availability', 'Availability', 'select', 0, ['InStock', 'OutOfStock', 'PreOrder', 'LimitedAvailability']],
            ['rating', 'Average rating (1–5)', 'number', 0, '4.8'], ['reviews', 'Number of reviews', 'number', 0, '120'],
        ] },
        article: { title: 'Article / blog post', fields: [
            ['subtype', 'Article type', 'select', 0, ['BlogPosting', 'Article', 'NewsArticle']],
            ['headline', 'Headline', 'text', 1, 'How to choose a web developer'], ['url', 'Article URL', 'url', 0, ''], ['image', 'Main image URL', 'url', 1, ''],
            ['author', 'Author name', 'text', 1, ''], ['authorUrl', 'Author page URL', 'url', 0, ''], ['publisher', 'Publisher (site) name', 'text', 0, ''], ['publisherLogo', 'Publisher logo URL', 'url', 0, ''],
            ['datePublished', 'Published', 'date', 1, ''], ['dateModified', 'Last updated', 'date', 0, ''], ['description', 'Summary', 'textarea', 0, ''],
        ] },
        faq: { title: 'FAQ page', list: ['Question', 'Answer'], fields: [] },
        breadcrumb: { title: 'Breadcrumbs', list: ['Page name', 'Page URL'], fields: [] },
    };
    const form = document.querySelector('[data-schema-form]');
    let type = 'localbusiness';
    const values = {};

    const fieldHtml = ([k, label, kind, req, extra]) => {
        const v = esc(values[type]?.[k] ?? '');
        const star = req ? ' <em class="schema-req">*</em>' : '';
        if (kind === 'select') return `<label><span>${label}${star}</span><select data-k="${k}">${extra.map(o => `<option ${values[type]?.[k] === o ? 'selected' : ''}>${o}</option>`).join('')}</select></label>`;
        if (kind === 'textarea' || kind === 'lines') return `<label><span>${label}${star}</span><textarea data-k="${k}" rows="${kind === 'lines' ? 2 : 3}" placeholder="${esc(extra)}">${v}</textarea></label>`;
        return `<label><span>${label}${star}</span><input data-k="${k}" type="${kind === 'number' ? 'text' : kind}" ${kind === 'number' ? 'inputmode="decimal"' : ''} placeholder="${esc(extra)}" value="${v}"></label>`;
    };
    const rowHtml = (r, i) => `<div class="schema-row"><input data-li="${i}" data-part="0" placeholder="${TYPES[type].list[0]}" value="${esc(r[0])}"><textarea data-li="${i}" data-part="1" rows="2" placeholder="${TYPES[type].list[1]}">${esc(r[1])}</textarea><button type="button" class="schema-row__x" data-remove="${i}" aria-label="Remove">×</button></div>`;

    const draw = () => {
        const t = TYPES[type];
        values[type] ??= t.list ? { rows: type === 'faq' ? [['', ''], ['', '']] : [['Home', location.origin + '/'], ['', '']] } : {};
        t.fields.forEach(([k, , kind, , extra]) => { if (kind === 'select') values[type][k] ??= extra[0]; }); // dropdowns start on their first option
        S('title').textContent = t.title;
        S('fields').innerHTML = t.list
            ? values[type].rows.map(rowHtml).join('') + `<button type="button" class="btn btn--sm btn--ghost" data-add>+ Add ${type === 'faq' ? 'question' : 'page'}</button>`
            : t.fields.map(fieldHtml).join('');
        build();
    };

    const clean = o => { // drop empty values so the code stays tidy
        Object.keys(o).forEach(k => {
            if (o[k] && typeof o[k] === 'object' && !Array.isArray(o[k])) { clean(o[k]); if (Object.keys(o[k]).length <= 1 && o[k]['@type']) delete o[k]; }
            else if (Array.isArray(o[k]) ? !o[k].length : (o[k] === '' || o[k] === undefined || o[k] === null)) delete o[k];
        });
        return o;
    };
    const lines = s => (s || '').split('\n').map(x => x.trim()).filter(Boolean);

    const build = () => {
        const v = values[type], t = TYPES[type];
        let data, missing = [];
        if (!t.list) t.fields.forEach(([k, label, , req]) => { if (req && !String(v[k] ?? '').trim()) missing.push(label); });
        if (type === 'localbusiness') data = { '@type': v.subtype || 'LocalBusiness', name: v.name, url: v.url, image: v.image, telephone: v.telephone, email: v.email, priceRange: v.priceRange, description: v.description,
            address: { '@type': 'PostalAddress', streetAddress: v.street, addressLocality: v.city, addressRegion: v.region, postalCode: v.postal, addressCountry: v.country },
            openingHours: lines(v.hours), sameAs: lines(v.sameAs) };
        if (type === 'organization') data = { '@type': 'Organization', name: v.name, url: v.url, logo: v.logo, description: v.description, email: v.email, telephone: v.telephone, foundingDate: v.foundingDate, sameAs: lines(v.sameAs) };
        if (type === 'product') data = { '@type': 'Product', name: v.name, image: v.image, description: v.description, sku: v.sku, brand: v.brand ? { '@type': 'Brand', name: v.brand } : '',
            offers: { '@type': 'Offer', price: v.price, priceCurrency: v.currency || 'USD', availability: 'https://schema.org/' + (v.availability || 'InStock'), url: v.url },
            aggregateRating: v.rating && v.reviews ? { '@type': 'AggregateRating', ratingValue: v.rating, reviewCount: v.reviews } : '' };
        if (type === 'article') data = { '@type': v.subtype || 'BlogPosting', headline: v.headline, description: v.description, image: v.image, url: v.url, datePublished: v.datePublished, dateModified: v.dateModified || v.datePublished,
            author: { '@type': 'Person', name: v.author, url: v.authorUrl }, publisher: { '@type': 'Organization', name: v.publisher, logo: { '@type': 'ImageObject', url: v.publisherLogo } } };
        if (type === 'faq') {
            const rows = v.rows.filter(r => r[0].trim() && r[1].trim());
            if (!rows.length) missing.push('at least one question with an answer');
            data = { '@type': 'FAQPage', mainEntity: rows.map(r => ({ '@type': 'Question', name: r[0].trim(), acceptedAnswer: { '@type': 'Answer', text: r[1].trim() } })) };
        }
        if (type === 'breadcrumb') {
            const rows = v.rows.filter(r => r[0].trim());
            if (rows.length < 2) missing.push('at least two pages');
            data = { '@type': 'BreadcrumbList', itemListElement: rows.map((r, i) => clean({ '@type': 'ListItem', position: i + 1, name: r[0].trim(), item: r[1].trim() })) };
        }
        const json = JSON.stringify(Object.assign({ '@@context': 'https://schema.org' }, clean(data)), null, 2).replace(/<\//g, '<\\/');
        S('code').textContent = `<script type="application/ld+json">\n${json}\n<\/script>`;
        const issues = missing.map(m => `<li data-status="fail">✕ Add ${esc(m.toLowerCase())} — Google needs it.</li>`);
        if (type === 'faq') issues.push('<li>! Google no longer shows FAQ rich results, but this still helps search engines and AI assistants understand your answers.</li>');
        S('issues').innerHTML = issues.join('') || '<li data-status="pass">✓ All the required details are filled in.</li>';
    };

    document.querySelectorAll('[name=schema_type]').forEach(r => r.addEventListener('change', () => { type = r.value; draw(); }));
    form.addEventListener('input', e => {
        const el = e.target, v = values[type];
        if (el.dataset.k) v[el.dataset.k] = el.value;
        if (el.dataset.li !== undefined) v.rows[el.dataset.li][el.dataset.part] = el.value;
        build();
    });
    form.addEventListener('change', e => { if (e.target.tagName === 'SELECT') { values[type][e.target.dataset.k] = e.target.value; build(); } });
    form.addEventListener('click', e => {
        if (e.target.closest('[data-add]')) { values[type].rows.push(['', '']); draw(); form.querySelector(`[data-li="${values[type].rows.length - 1}"]`)?.focus(); }
        const x = e.target.closest('[data-remove]'); if (x) { values[type].rows.splice(+x.dataset.remove, 1); if (!values[type].rows.length) values[type].rows.push(['', '']); draw(); }
    });
    S('copy').addEventListener('click', async e => {
        try { await navigator.clipboard.writeText(S('code').textContent); e.target.textContent = 'Copied ✓'; setTimeout(() => e.target.textContent = 'Copy code', 2000); window.track?.('schema_copy', { type }); } catch {}
    });
    draw();
})();
</script>
@endsection
