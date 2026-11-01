/* Owlvix page builder — the engine: data model, widgets, templates and page rendering (window.OwlB).
   The editor (landing-builder.js) uses it to draw the canvas, and to export / publish pages.

   Project  { v:3, site:{…}, pages:[ { id, slug, title, menu, seo, popup, sections:[Section] } ] }
   Section  { id, layout:'1'|'2'|'2-1'|'1-2'|'3'|'4', width:'boxed'|'full', bg:'auto'|…, st, adv, cols:[Column] }
   Column   { id, st, widgets:[Widget] }
   Widget   { id, type, v, p:{content}, st:{ target:{ d:{…}, t:{…}, m:{…} } }, adv:{ id, cls, css, anim, delay, hide:{d,t,m} } }
   Styles are per device (d = desktop, t = tablet ≤1024px, m = mobile ≤767px). Target "_" is the element itself;
   other targets are inner parts like "title" or "items.*.title". */
(() => {
    /* ================================================================ helpers */
    const uid = () => Math.random().toString(36).slice(2, 9);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const nl = s => esc(s).replace(/\n/g, '<br>');
    const clone = o => JSON.parse(JSON.stringify(o));
    const hex = c => (/^#[0-9a-f]{6}$/i.test(c || '') ? c : '#16a34a');
    const mix = (c, pct, to = 255) => '#' + [1, 3, 5].map(i => Math.round(parseInt(c.substr(i, 2), 16) * (1 - pct) + to * pct).toString(16).padStart(2, '0')).join('');
    const ink = c => { const [r, g, b] = [1, 3, 5].map(i => parseInt(c.substr(i, 2), 16) / 255); return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.6 ? '#111827' : '#ffffff'; };
    const slugify = s => String(s || '').toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);
    const digits = s => String(s || '').replace(/[^\d+]/g, '');
    const getPath = (o, path) => path.split('.').reduce((a, k) => a?.[k], o);
    const setPath = (o, path, v) => { const ks = path.split('.'); const last = ks.pop(); ks.reduce((a, k) => (a[k] ??= {}), o)[last] = v; };
    const ytId = url => (String(url).match(/(?:youtu\.be\/|v=|embed\/|shorts\/)([\w-]{11})/) || [])[1];
    const safeUrl = u => { u = String(u || '').trim(); return /^(https?:\/\/|mailto:|tel:|#)/i.test(u) ? u : ''; };
    const cssSafe = s => String(s || '').replace(/<\/|<!--/g, '');
    const has = (o, k) => typeof k === 'string' && Object.prototype.hasOwnProperty.call(o, k);
    /** A URL for use inside CSS url(...): only images (data:image or https), with quotes/brackets percent-encoded. */
    const cssUrl = u => { u = String(u || ''); return /^(data:image\/|https:\/\/)/i.test(u) ? u.replace(/["'()\\\s<>]/g, c => '%' + c.charCodeAt(0).toString(16).padStart(2, '0')) : ''; };
    const cls = s => String(s || '').replace(/[^\w\s-]/g, '').trim();
    /** The style key for a content path: items.3.title → items.*.title (styles apply to every item). */
    const styleKey = path => path.replace(/\.\d+(?=\.|$)/g, '.*');
    const classKey = key => 'k-' + key.replace(/\.\*/g, '').replace(/\./g, '-');

    /** Rich text: only simple formatting survives (bold, italic, underline, links, lists, paragraphs, line breaks). */
    const ALLOWED = { B: 'b', STRONG: 'b', I: 'i', EM: 'i', U: 'u', A: 'a', UL: 'ul', OL: 'ol', LI: 'li', BR: 'br', P: 'p', DIV: 'p' };
    const DROP = new Set(['STYLE', 'SCRIPT', 'TEMPLATE', 'HEAD', 'TITLE', 'NOSCRIPT', 'META', 'LINK', 'OBJECT', 'IFRAME', 'SVG', 'MATH']);
    const sanitize = html => {
        const tpl = document.createElement('template'); tpl.innerHTML = String(html || '');
        const walk = node => [...node.childNodes].map(n => {
            if (n.nodeType === 3) return esc(n.nodeValue);
            if (n.nodeType !== 1 || DROP.has(n.nodeName.toUpperCase())) return '';
            let tag = ALLOWED[n.nodeName];
            if (tag === 'p' && n.querySelector('p,div,ul,ol')) tag = null; // a wrapper around blocks: keep only its contents
            const inner = walk(n);
            if (!tag) return inner;
            if (tag === 'br') return '<br>';
            if (tag === 'a') { const h = safeUrl(n.getAttribute('href')); return h ? `<a href="${esc(h)}"${/^https?:/i.test(h) ? ' target="_blank" rel="noopener"' : ''}>${inner}</a>` : inner; }
            return `<${tag}>${inner}</${tag}>`;
        }).join('');
        return walk(tpl.content).replace(/<p><\/p>/g, '');
    };

    /* ================================================================ icons */
    const I = d => `<svg viewBox="0 0 24 24" aria-hidden="true">${d}</svg>`;
    const ICONS = {
        check: '<path d="M5 12.5l4.5 4.5L19 7.5"/>', star: '<path d="M12 3l2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>',
        phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>', mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        pin: '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>', clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        heart: '<path d="M12 20s-7-4.5-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.5-9 9-9 9z"/>', bolt: '<path d="M13 3L4 14h7l-1 7 9-11h-7z"/>',
        shield: '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>', truck: '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        gift: '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v8h14v-8M12 8v12M12 8S10 3 7.5 4.5 9 8 12 8zM12 8s2-5 4.5-3.5S15 8 12 8z"/>', chat: '<path d="M4 5h16v11H8l-4 4z"/>',
        user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>', calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        rocket: '<path d="M12 2c3 2 5 6 5 10l-2 4H9l-2-4c0-4 2-8 5-10z"/><circle cx="12" cy="9" r="2"/><path d="M9 16l-2 4M15 16l2 4"/>', leaf: '<path d="M5 19C5 9 11 4 20 4c0 9-5 15-15 15zM5 19l7-7"/>',
        home: '<path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>', cart: '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l3 12h11l2-8H6"/>',
        card: '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>', globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        camera: '<path d="M4 7h4l2-3h4l2 3h4v13H4z"/><circle cx="12" cy="13" r="4"/>', play: '<circle cx="12" cy="12" r="9"/><path d="M10 8l6 4-6 4z"/>',
        award: '<circle cx="12" cy="9" r="6"/><path d="M8.5 14L7 22l5-3 5 3-1.5-8"/>', thumb: '<path d="M7 11v10H3V11zM7 11l4-8a3 3 0 0 1 3 3v4h6a2 2 0 0 1 2 2l-2 7a2 2 0 0 1-2 2H7"/>',
        lock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>', coffee: '<path d="M4 8h13v6a6 6 0 0 1-6 6H10a6 6 0 0 1-6-6zM17 10h2a3 3 0 0 1 0 6h-2M8 2v3M12 2v3"/>',
        tool: '<path d="M14 6a4 4 0 0 0 5 5l-9 9a2 2 0 0 1-3-3l9-9a4 4 0 0 0-2-2z"/>', chart: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        smile: '<circle cx="12" cy="12" r="9"/><path d="M8 14a5 5 0 0 0 8 0M9 9h.01M15 9h.01"/>', target: '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>', wifi: '<path d="M2 9a15 15 0 0 1 20 0M5 13a10 10 0 0 1 14 0M8.5 16.5a5 5 0 0 1 7 0M12 20h.01"/>',
    };
    /** An icon value is either "i:name" (built-in line icon) or any emoji / text. */
    const icon = v => (String(v || '').startsWith('i:') && ICONS[v.slice(2)] ? `<span class="ic">${I(ICONS[v.slice(2)])}</span>` : `<span class="ic ic-txt">${esc(v)}</span>`);
    const SOCIAL = { facebook: ['Facebook', 'f', '#1877f2'], instagram: ['Instagram', 'IG', '#e1306c'], linkedin: ['LinkedIn', 'in', '#0a66c2'], x: ['X (Twitter)', '𝕏', '#111111'], youtube: ['YouTube', '▶', '#ff0000'], tiktok: ['TikTok', '♪', '#111111'], whatsapp: ['WhatsApp', 'WA', '#25d366'], pinterest: ['Pinterest', 'P', '#e60023'] };

    /* ================================================================ links */
    const LINK_TYPES = { contact: 'Contact section', page: 'Another page', url: 'Web address', popup: 'Open the popup', whatsapp: 'WhatsApp', phone: 'Phone call', email: 'Email' };
    const FUNNEL_LINKS = { next: 'Next funnel step', pay: 'Payment link (this step)' };
    /** The step after this one: its chosen "next", or simply the following step. */
    const nextOf = (pg, P) => (pg.next && P.pages.find(p => p.slug === pg.next)) || P.pages[P.pages.indexOf(P.pages.find(p => p.id === pg.id)) + 1] || null;
    const pageHref = (slug, x) => {
        // Published: absolute address on the sites subdomain (or /p/ locally), e.g. https://sites.owlvix.com/anj-tec/about
        if (x.mode === 'publish') return (x.siteBase || '/p/') + (slugify(x.P.site.publishSlug) || 'site') + (slug === 'index' ? '' : '/' + slugify(slug));
        if (x.P.pages.length === 1) return '#'; // a one-page download is saved under its own name
        return (slug === 'index' ? 'index' : slugify(slug)) + '.html';
    };
    const hasContact = pg => pg.sections.some(s => s.cols.some(c => c.widgets.some(w => w.type === 'contact' || w.type === 'form')));
    const href = (l, x) => {
        l = l || { to: 'contact' };
        const v = String(l.val || '').trim();
        switch (l.to) {
            case 'page': { const pg = x.P.pages.find(p => p.slug === v); return pg ? (x.edit ? '#' : pageHref(pg.slug, x)) : '#'; }
            case 'url': return /^https?:\/\//i.test(v) ? v : (v.startsWith('#') ? v : '#');
            case 'popup': return x.page.popup?.on ? '#popup' : '#';
            case 'next': { const n = nextOf(x.page, x.P); return n && !x.edit ? pageHref(n.slug, x) : '#'; }
            case 'pay': return /^https:\/\//i.test(x.page.pay || '') ? x.page.pay : '#';
            case 'whatsapp': return digits(v) ? 'https://wa.me/' + digits(v).replace(/^\+/, '') : '#';
            case 'phone': return digits(v) ? 'tel:' + digits(v) : '#';
            case 'email': return v.includes('@') ? 'mailto:' + v.replace(/[^\w.+@-]/g, '') : '#';
            default: {
                if (hasContact(x.page)) return x.variant === 'b' ? '#contact-b' : '#contact';
                const other = x.P.pages.find(hasContact);
                return other && !x.edit ? pageHref(other.slug, x) + '#contact' : '#contact';
            }
        }
    };

    /** A button whose link isn't set yet: left off finished pages (kept in the editor so it can be filled in). */
    const goesNowhere = (l, x) => !x.edit && href(l, x) === '#' && l?.to !== 'contact';

    /* ================================================================ render helpers for one widget */
    const helpers = (w, x) => {
        const E = (path, ph = '', o = {}) => (x.edit ? ` data-e="${esc(w.id)}|${esc(path)}" data-ph="${esc(ph)}"${o.ml ? ' data-ml' : ''}${o.rich ? ' data-rich' : ''}` : '');
        const K = path => classKey(styleKey(path));
        const H = {
            E, K,
            /** Editable text. o: { ml: multi-line, rich: formatted, keep: render even when empty } */
            t: (path, tag, extra = '', ph = 'Type here', o = {}) => {
                const v = getPath(w.p, path) ?? '';
                const empty = !String(v).replace(/<[^>]*>/g, '').trim();
                if (empty && !x.edit && !o.keep) return '';
                const body = o.rich ? sanitize(v) : o.ml ? nl(v) : esc(v);
                return `<${tag} class="${K(path)}${extra ? ' ' + extra : ''}"${E(path, ph, o)}>${body}</${tag}>`;
            },
            btn: (textPath, linkPath, extra = 'btn') => {
                const v = getPath(w.p, textPath);
                if (!String(v || '').trim()) return '';
                const link = getPath(w.p, linkPath), url = href(link, x);
                if (goesNowhere(link, x)) return '';
                return `<a class="${extra} ${K(textPath)}" href="${esc(url)}"${E(textPath, 'Button')}>${esc(v)}</a>`;
            },
            pic: (path, alt, extra = 'pic', fallback = '') => {
                const src = getPath(w.p, path);
                const attr = x.edit ? ` data-img="${esc(w.id)}|${esc(path)}"` : '';
                if (src) return `<div class="${extra} ${K(path)}"${attr}><img src="${esc(src)}" alt="${esc(alt)}" loading="lazy"></div>`;
                if (x.edit) return `<div class="${extra} pic-empty"${attr}><span>＋ Add photo</span></div>`;
                return fallback;
            },
            icon: path => { const v = getPath(w.p, path); return v || x.edit ? `<span class="${K(path)}"${x.edit ? ` data-icon="${esc(w.id)}|${esc(path)}"` : ''}>${icon(v || '⭐')}</span>` : ''; },
            href: l => href(l, x),
            items: () => w.p.items || [],
        };
        return H;
    };

    /* ================================================================ widgets */
    // Field types for the Content tab: text, area, rich, link, img, bool, select, number, date, icon, list
    const F = {
        text: (k, label, ph = '') => ({ k, label, type: 'text', ph }), area: (k, label) => ({ k, label, type: 'textarea' }),
        link: (k, label) => ({ k, label, type: 'link' }), img: (k, label) => ({ k, label, type: 'img' }), bool: (k, label) => ({ k, label, type: 'bool' }),
        select: (k, label, options) => ({ k, label, type: 'select', options }), num: (k, label, min, max, step = 1) => ({ k, label, type: 'number', min, max, step }),
        date: (k, label) => ({ k, label, type: 'datetime' }), icon: (k, label) => ({ k, label, type: 'icon' }),
        list: (k, label, item, fields, max = 12) => ({ k, label, type: 'list', item, fields, max }),
    };
    /** The value if it's one of the allowed options, else the first option. */
    const pick = (v, allowed) => (allowed.includes(v) ? v : allowed[0]);
    const plus7 = () => new Date(Date.now() + 7 * 864e5).toISOString().slice(0, 16);

    const W = {};
    const def = (type, d) => (W[type] = { type, ...d });

    /* ---------- basic widgets (go inside columns) */
    def('heading', { label: 'Heading', icon: 'H', group: 'basic', make: () => ({ text: 'Add your heading', tag: 'h2' }), fields: [F.select('tag', 'HTML tag', { h1: 'H1 — page title', h2: 'H2', h3: 'H3', h4: 'H4', p: 'Paragraph' })],
        render: (w, x, H) => H.t('text', ['h1', 'h2', 'h3', 'h4', 'p'].includes(w.p.tag) ? w.p.tag : 'h2', 'hd', 'Heading', { keep: true }) });
    def('text', { label: 'Text', icon: '¶', group: 'basic', make: () => ({ html: '<p>Write your text here. Select words to make them <b>bold</b>, add links or lists.</p>' }), fields: [],
        render: (w, x, H) => H.t('html', 'div', 'rt', 'Text', { rich: true }) });
    def('button', { label: 'Button', icon: '⏺', group: 'basic', make: () => ({ text: 'Click here', link: { to: 'contact' }, kind: 'primary', size: 'md', align: 'auto' }),
        fields: [F.link('link', 'Link'), F.select('kind', 'Style', { primary: 'Filled', ghost: 'Outline', light: 'White' }), F.select('size', 'Size', { sm: 'Small', md: 'Medium', lg: 'Large' }), F.select('align', 'Alignment', { auto: 'Same as the section', left: 'Left', center: 'Centre', right: 'Right', full: 'Full width' })],
        render: (w, x, H) => `<div class="btn-wrap al-${pick(w.p.align, ['auto', 'left', 'center', 'right', 'full'])}">${H.btn('text', 'link', `btn btn-${pick(w.p.size, ['md', 'sm', 'lg'])} ${w.p.kind === 'ghost' ? 'btn-ghost' : w.p.kind === 'light' ? 'btn-light' : ''}`)}</div>` });
    def('image', { label: 'Image', icon: '🖼', group: 'basic', make: () => ({ src: '', alt: '', caption: '', url: '' }), fields: [F.img('src', 'Image'), F.text('alt', 'Alt text (for Google & screen readers)'), F.text('url', 'Link (optional)', 'https://…')],
        render: (w, x, H) => { const img = H.pic('src', w.p.alt, 'img-w', ''); const u = safeUrl(w.p.url); return `<figure class="fig">${u && !x.edit ? `<a href="${esc(u)}">${img}</a>` : img}${H.t('caption', 'figcaption', '', 'Caption (optional)')}</figure>`; } });
    def('spacer', { label: 'Spacer', icon: '↕', group: 'basic', make: () => ({ h: 40 }), fields: [F.num('h', 'Height (px)', 0, 400, 4)],
        render: w => `<div class="spacer" style="height:${Math.max(0, +w.p.h || 0)}px"></div>` });
    def('divider', { label: 'Divider', icon: '—', group: 'basic', make: () => ({ style: 'solid', width: 100, weight: 2 }), fields: [F.select('style', 'Line', { solid: 'Solid', dashed: 'Dashed', dotted: 'Dotted', double: 'Double' }), F.num('width', 'Width (%)', 5, 100, 5), F.num('weight', 'Thickness (px)', 1, 12)],
        render: w => `<hr class="divider" style="border-top:${+w.p.weight || 2}px ${['solid', 'dashed', 'dotted', 'double'].includes(w.p.style) ? w.p.style : 'solid'} currentColor;width:${Math.min(100, +w.p.width || 100)}%">` });
    def('iconbox', { label: 'Icon box', icon: '★', group: 'basic', make: () => ({ icon: 'i:bolt', title: 'Fast results', text: 'Describe this benefit in one or two sentences.', layout: 'top' }),
        fields: [F.icon('icon', 'Icon'), F.select('layout', 'Icon position', { top: 'Above', left: 'Left' })],
        render: (w, x, H) => `<div class="iconbox lay-${pick(w.p.layout, ['top', 'left'])}"><div class="iconbox__i">${H.icon('icon')}</div><div>${H.t('title', 'h3', '', 'Title')}${H.t('text', 'div', 'rt', 'Text', { rich: true })}</div></div>` });
    def('imagebox', { label: 'Image box', icon: '🗂', group: 'basic', make: () => ({ src: '', title: 'Image box title', text: 'A short description of this item.' }), fields: [F.img('src', 'Image')],
        render: (w, x, H) => `<div class="imagebox">${H.pic('src', w.p.title, 'pic', '<div class="pic pic-fill"></div>')}${H.t('title', 'h3', '', 'Title')}${H.t('text', 'p', 'muted', 'Text', { ml: true })}</div>` });
    def('iconlist', { label: 'Icon list', icon: '☰', group: 'basic', make: () => ({ items: [{ icon: 'i:check', text: 'First point' }, { icon: 'i:check', text: 'Second point' }, { icon: 'i:check', text: 'Third point' }] }),
        fields: [F.list('items', 'Items', { icon: 'i:check', text: 'New point' }, [['icon', 'icon'], ['text', 'text']])],
        render: (w, x, H) => `<ul class="iconlist">${H.items().map((it, i) => `<li>${H.icon(`items.${i}.icon`)}${H.t(`items.${i}.text`, 'span', '', 'Text', { keep: true })}</li>`).join('')}</ul>` });
    def('video1', { label: 'Video', icon: '▶', group: 'basic', make: () => ({ url: '' }), fields: [F.text('url', 'YouTube link', 'https://www.youtube.com/watch?v=…')],
        render: (w, x) => { const id = ytId(w.p.url); return id ? `<div class="embed"><iframe src="https://www.youtube-nocookie.com/embed/${id}" title="Video" loading="lazy" allowfullscreen allow="encrypted-media; picture-in-picture"></iframe></div>` : (x.edit ? '<div class="embed pic-empty"><span>Paste a YouTube link in the Content tab</span></div>' : ''); } });

    /* ---------- pro widgets */
    def('counter', { label: 'Counter', icon: '123', group: 'pro', make: () => ({ num: '500', prefix: '', suffix: '+', label: 'Happy customers' }), fields: [F.text('num', 'Number'), F.text('prefix', 'Before the number'), F.text('suffix', 'After the number')],
        render: (w, x, H) => `<div class="counter"><b class="${H.K('num')}"><span>${esc(w.p.prefix)}</span><span${x.edit || !/^\d[\d,]*(\.\d+)?$/.test(String(w.p.num).trim()) ? '' : ` data-count="${esc(String(w.p.num).replace(/,/g, ''))}"`}>${esc(w.p.num)}</span><span>${esc(w.p.suffix)}</span></b>${H.t('label', 'p', '', 'Label')}</div>`, runtime: true });
    def('progress', { label: 'Progress bars', icon: '▰', group: 'pro', make: () => ({ items: [{ label: 'Design', pct: 90 }, { label: 'Development', pct: 80 }, { label: 'Support', pct: 95 }] }),
        fields: [F.list('items', 'Bars', { label: 'Skill', pct: 75 }, [['label', 'text'], ['pct', 'number']])],
        render: (w, x, H) => `<div class="bars">${H.items().map((it, i) => `<div class="bar"><div class="bar__top">${H.t(`items.${i}.label`, 'span', '', 'Label', { keep: true })}<span>${Math.max(0, Math.min(100, +it.pct || 0))}%</span></div><div class="bar__track"><i style="width:${Math.max(0, Math.min(100, +it.pct || 0))}%"></i></div></div>`).join('')}</div>` });
    def('rating', { label: 'Star rating', icon: '⭐', group: 'pro', make: () => ({ stars: 4.8, label: 'Rated 4.8 from 200+ reviews' }), fields: [F.num('stars', 'Stars (0–5)', 0, 5, 0.1)],
        render: (w, x, H) => { const s = Math.max(0, Math.min(5, +w.p.stars || 0)); return `<div class="rating"><span class="stars-v" style="--v:${(s / 5) * 100}%" aria-label="${s} out of 5">★★★★★</span>${H.t('label', 'span', 'muted', 'Label')}</div>`; } });
    def('social', { label: 'Social icons', icon: '＠', group: 'pro', make: () => ({ shape: 'round', items: [{ net: 'facebook', url: '' }, { net: 'instagram', url: '' }, { net: 'linkedin', url: '' }] }),
        fields: [F.select('shape', 'Shape', { round: 'Circle', rounded: 'Rounded', square: 'Square' }), F.list('items', 'Networks', { net: 'x', url: '' }, [['net', 'social'], ['url', 'text']])],
        render: (w, x) => `<div class="socials sh-${pick(w.p.shape, ['round', 'rounded', 'square'])}">${(w.p.items || []).map(it => { const n = SOCIAL[it.net] || SOCIAL.facebook, u = safeUrl(it.url); return u || x.edit ? `<a href="${esc(u || '#')}" style="--sc:${n[2]}" title="${n[0]}" aria-label="${n[0]}" rel="noopener">${n[1]}</a>` : ''; }).join('')}</div>` });
    def('notice', { label: 'Notice box', icon: 'ℹ', group: 'pro', make: () => ({ kind: 'info', title: 'Good to know', text: 'Add an important note, offer or announcement here.' }), fields: [F.select('kind', 'Type', { info: 'Info', success: 'Success', warning: 'Warning', danger: 'Important' })],
        render: (w, x, H) => `<div class="notice nt-${pick(w.p.kind, ['info', 'success', 'warning', 'danger'])}">${H.t('title', 'b', '', 'Title')}${H.t('text', 'div', 'rt', 'Text', { rich: true })}</div>` });
    def('hours', { label: 'Opening hours', icon: '🕘', group: 'pro', make: () => ({ items: [{ day: 'Monday – Friday', time: '9:00 – 18:00' }, { day: 'Saturday', time: '10:00 – 14:00' }, { day: 'Sunday', time: 'Closed' }] }),
        fields: [F.list('items', 'Rows', { day: 'Day', time: 'Time' }, [['day', 'text'], ['time', 'text']])],
        render: (w, x, H) => `<dl class="hours">${H.items().map((it, i) => `<div>${H.t(`items.${i}.day`, 'dt', '', 'Day', { keep: true })}${H.t(`items.${i}.time`, 'dd', '', 'Time', { keep: true })}</div>`).join('')}</dl>` });
    def('pricelist', { label: 'Price list / menu', icon: '📋', group: 'pro', make: () => ({ items: [{ name: 'Item name', desc: 'Short description', price: '$12' }, { name: 'Another item', desc: 'Short description', price: '$15' }, { name: 'Third item', desc: 'Short description', price: '$9' }] }),
        fields: [F.list('items', 'Items', { name: 'Item', desc: '', price: '$0' }, [['name', 'text'], ['price', 'text']], 30)],
        render: (w, x, H) => `<ul class="pricelist">${H.items().map((it, i) => `<li><div class="pl-top">${H.t(`items.${i}.name`, 'b', '', 'Name', { keep: true })}<i></i>${H.t(`items.${i}.price`, 'b', 'pl-price', '$0', { keep: true })}</div>${H.t(`items.${i}.desc`, 'p', 'muted', 'Description')}</li>`).join('')}</ul>` });
    def('testimonial', { label: 'Testimonial', icon: '❝', group: 'pro', make: () => ({ quote: 'Fantastic service from start to finish. Highly recommended!', name: 'Customer name', role: 'Customer', photo: '' }), fields: [F.img('photo', 'Photo')],
        render: (w, x, H) => `<figure class="tst"><div class="stars">★★★★★</div>${H.t('quote', 'blockquote', '', 'What they said', { ml: true })}<figcaption>${H.pic('photo', w.p.name, 'avatar sm', '')}<span>${H.t('name', 'b', '', 'Name')}${H.t('role', 'small', 'muted', 'Role')}</span></figcaption></figure>` });
    def('tabs', { label: 'Tabs', icon: '⊟', group: 'pro', make: () => ({ items: [{ title: 'First tab', html: '<p>Content of the first tab.</p>' }, { title: 'Second tab', html: '<p>Content of the second tab.</p>' }, { title: 'Third tab', html: '<p>Content of the third tab.</p>' }] }),
        fields: [F.list('items', 'Tabs', { title: 'New tab', html: '<p>Tab content.</p>' }, [['title', 'text']], 8)],
        render: (w, x, H) => {
            if (x.edit) return `<div class="tabs is-edit">${H.items().map((it, i) => `<div class="tab-edit">${H.t(`items.${i}.title`, 'b', 'tab-label', 'Tab title', { keep: true })}${H.t(`items.${i}.html`, 'div', 'rt tab-panel', 'Content', { rich: true, keep: true })}</div>`).join('')}</div>`;
            const n = `t${String(w.id).replace(/[^a-z0-9]/gi, '')}`;
            return `<div class="tabs">${H.items().map((it, i) => `<input type="radio" name="${n}" id="${n}-${i}" ${i === 0 ? 'checked' : ''}><label for="${n}-${i}" class="tab-label ${H.K('items.0.title')}">${esc(it.title)}</label>`).join('')}<div class="tab-panels">${H.items().map(it => `<div class="tab-panel rt">${sanitize(it.html)}</div>`).join('')}</div></div>`;
        } });
    def('accordion', { label: 'Accordion', icon: '≡', group: 'pro', make: () => ({ items: [{ title: 'What is included?', html: '<p>Explain what customers get.</p>' }, { title: 'How long does it take?', html: '<p>Give a realistic time frame.</p>' }] }),
        fields: [F.list('items', 'Items', { title: 'New question', html: '<p>The answer.</p>' }, [['title', 'text']], 15)],
        render: (w, x, H) => `<div class="faq">${H.items().map((it, i) => `<details${x.edit || i === 0 ? ' open' : ''}><summary${H.E(`items.${i}.title`, 'Title')} class="${H.K(`items.${i}.title`)}">${esc(it.title)}</summary>${H.t(`items.${i}.html`, 'div', 'rt', 'Content', { rich: true, keep: true })}</details>`).join('')}</div>` });
    const slides = (w, x, H, inner) => {
        const items = H.items(), per = Math.max(1, Math.min(4, +w.p.per || 1));
        if (x.edit) return `<div class="car-edit" style="--per:${Math.min(per, 3)}">${items.map(inner).join('')}</div>`;
        return `<div class="car" data-carousel${w.p.auto ? ' data-auto="1"' : ''} style="--per:${per}"><div class="ob-track">${items.map((it, i) => `<div class="car__slide">${inner(it, i)}</div>`).join('')}</div>
            <button type="button" class="car__nav prev" data-prev aria-label="Previous">‹</button><button type="button" class="car__nav next" data-next aria-label="Next">›</button>
            <div class="car__dots">${items.map((_, i) => `<button type="button" data-dot class="${i === 0 ? 'is-on' : ''}" aria-label="Slide ${i + 1}"></button>`).join('')}</div></div>`;
    };
    def('carousel', { label: 'Image slider', icon: '🎞', group: 'pro', make: () => ({ per: 1, auto: true, items: [{ src: '', caption: '' }, { src: '', caption: '' }, { src: '', caption: '' }] }),
        fields: [F.num('per', 'Slides visible at once', 1, 4), F.bool('auto', 'Play automatically'), F.list('items', 'Slides', { src: '', caption: '' }, [['src', 'img'], ['caption', 'text']], 20)],
        render: (w, x, H) => slides(w, x, H, (it, i) => `<figure class="fig">${H.pic(`items.${i}.src`, it.caption || 'Slide', 'pic slide-pic', '<div class="pic slide-pic pic-fill"></div>')}${H.t(`items.${i}.caption`, 'figcaption', '', 'Caption (optional)')}</figure>`), runtime: true });
    def('tcarousel', { label: 'Review slider', icon: '💬', group: 'pro', make: () => ({ per: 1, auto: true, items: [{ quote: 'Fantastic service from start to finish.', name: 'Customer name', role: 'Customer' }, { quote: 'Quick, friendly and great value.', name: 'Customer name', role: 'Customer' }, { quote: 'Exactly what we needed. Thank you!', name: 'Customer name', role: 'Customer' }] }),
        fields: [F.num('per', 'Reviews visible at once', 1, 3), F.bool('auto', 'Play automatically'), F.list('items', 'Reviews', { quote: 'What they said.', name: 'Name', role: '' }, [['name', 'text']], 12)],
        render: (w, x, H) => slides(w, x, H, (it, i) => `<figure class="tst card">${'<div class="stars">★★★★★</div>'}${H.t(`items.${i}.quote`, 'blockquote', '', 'What they said', { ml: true, keep: true })}<figcaption><span>${H.t(`items.${i}.name`, 'b', '', 'Name')}${H.t(`items.${i}.role`, 'small', 'muted', 'Role')}</span></figcaption></figure>`), runtime: true });
    def('flipbox', { label: 'Flip box', icon: '⟲', group: 'pro', make: () => ({ icon: 'i:rocket', title: 'Hover me', text: 'Front side text.', backTitle: 'More details', backText: 'Back side text with more information.', btn: 'Learn more', link: { to: 'contact' } }),
        fields: [F.icon('icon', 'Icon'), F.link('link', 'Button link')],
        render: (w, x, H) => `<div class="flip${x.edit ? ' is-edit' : ''}" tabindex="0"><div class="flip__in"><div class="flip__f">${H.icon('icon')}${H.t('title', 'h3', '', 'Title')}${H.t('text', 'p', '', 'Text', { ml: true })}</div><div class="flip__b">${H.t('backTitle', 'h3', '', 'Back title')}${H.t('backText', 'p', '', 'Back text', { ml: true })}${H.btn('btn', 'link', 'btn btn-light btn-sm')}</div></div></div>` });
    def('beforeafter', { label: 'Before / after', icon: '◐', group: 'pro', make: () => ({ before: '', after: '', lb: 'Before', la: 'After' }), fields: [F.img('before', 'Before image'), F.img('after', 'After image'), F.text('lb', 'Before label'), F.text('la', 'After label')],
        render: (w, x, H) => x.edit
            ? `<div class="ba-edit">${H.pic('before', 'Before', 'pic', '')}${H.pic('after', 'After', 'pic', '')}</div>`
            : (w.p.before && w.p.after ? `<div class="ba" data-ba style="--pos:50%"><img src="${esc(w.p.after)}" alt="${esc(w.p.la)}"><div class="ba__b"><img src="${esc(w.p.before)}" alt="${esc(w.p.lb)}"></div><span class="ba__l l">${esc(w.p.lb)}</span><span class="ba__l r">${esc(w.p.la)}</span><input type="range" min="0" max="100" value="50" aria-label="Compare before and after"></div>` : ''),
        runtime: true });
    def('countdown', { label: 'Countdown', icon: '⏳', group: 'pro', make: () => ({ date: plus7(), done: 'This offer has ended.' }), fields: [F.date('date', 'Counts down to'), F.text('done', 'Text when finished')],
        render: (w, x) => {
            const left = Math.max(0, (Date.parse(w.p.date) - Date.now()) / 1000) || 0, v = { d: Math.floor(left / 86400), h: Math.floor(left / 3600) % 24, m: Math.floor(left / 60) % 60, s: Math.floor(left % 60) };
            const at = Date.parse(w.p.date); // the author's local time, saved as an exact moment
            return `<div class="cd"${x.edit || !at ? '' : ` data-countdown="${new Date(at).toISOString()}"`}>${[['d', 'Days'], ['h', 'Hours'], ['m', 'Minutes'], ['s', 'Seconds']].map(([k, l]) => `<div><b data-u="${k}">${String(v[k]).padStart(2, '0')}</b><span>${l}</span></div>`).join('')}<p class="cd__done">${esc(w.p.done)}</p></div>`;
        }, runtime: true });
    def('animhead', { label: 'Animated headline', icon: 'Aa', group: 'pro', make: () => ({ before: 'We build', words: 'websites\nonline stores\nmobile apps', after: 'that grow your business', tag: 'h2' }),
        fields: [F.area('words', 'Rotating words (one per line)'), F.select('tag', 'HTML tag', { h1: 'H1', h2: 'H2', h3: 'H3' })],
        render: (w, x, H) => { const words = String(w.p.words || '').split('\n').map(s => s.trim()).filter(Boolean); const tag = ['h1', 'h2', 'h3'].includes(w.p.tag) ? w.p.tag : 'h2';
            return `<${tag} class="ahead hd"${x.edit ? '' : ` aria-label="${esc([w.p.before, words.join(', '), w.p.after].filter(Boolean).join(' '))}"`}>${H.t('before', 'span', '', 'Text before')} <span class="ahead__w"${x.edit ? '' : ' data-rotate aria-hidden="true"'}>${x.edit ? `<span class="is-on">${esc(words.join(' / '))}</span>` : words.map((wd, i) => `<span class="${i === 0 ? 'is-on' : ''}">${esc(wd)}</span>`).join('')}</span> ${H.t('after', 'span', '', 'Text after')}</${tag}>`; }, runtime: true });
    def('form', { label: 'Contact form', icon: '✉', group: 'pro', make: () => ({ btn: 'Send message', phone: true, name: true, message: true }),
        fields: [F.text('btn', 'Button text'), F.bool('name', 'Ask for a name'), F.bool('phone', 'Ask for a phone number'), F.bool('message', 'Ask for a message (untick for a waitlist or “notify me” sign-up)')],
        render: (w, x) => formHtml(x, w.p) });

    /* RSVP: posts to a form-builder form whose questions match these fields (rsvpSchema keeps them in step) */
    const RSVP_IDS = { name: 'rname', email: 'remail', come: 'rcome', guests: 'rguests', meal: 'rmeal', msg: 'rmsg' };
    const opt = v => String(v || '').trim().slice(0, 100); // the server keeps choices up to 100 characters
    const lines = v => [...new Set(String(v || '').split('\n').map(opt).filter(Boolean))].slice(0, 20);
    const rsvpYesNo = p => [opt(p.yes) || 'Yes, I will be there', opt(p.no) || 'Sorry, I can’t make it'];
    const rsvpWidget = P => P.pages.flatMap(pg => [...pg.sections, ...(pg.sectionsB || [])].flatMap(sc => sc.cols.flatMap(c => c.widgets))).find(w => w.type === 'rsvp'); // A/B version B too
    /** The questions of the site's RSVP form (null when the site has no RSVP widget). */
    const rsvpSchema = P => {
        const w = rsvpWidget(P); if (!w) return null;
        const p = w.p, I = RSVP_IDS, meals = lines(p.meals);
        return {
            managed: true, // made by a builder page: the form builder leaves its questions alone
            title: ('RSVP — ' + (P.site.name || 'My event')).slice(0, 120), intro: '', button: (p.btn || 'Send my RSVP').slice(0, 40),
            thanks: (p.thanks || 'Thank you! Your RSVP has been sent.').slice(0, 300), color: hex(P.site.colors?.primary),
            fields: [
                { id: I.name, type: 'text', label: 'Your name', required: true },
                { id: I.email, type: 'email', label: 'Email', required: !!p.emailReq },
                { id: I.come, type: 'radio', label: 'Will you come?', required: true, options: rsvpYesNo(p) },
                ...(p.guests ? [{ id: I.guests, type: 'number', label: 'How many guests (including you)?', min: 1, max: 20 }] : []),
                ...(p.meal && meals.length ? [{ id: I.meal, type: 'select', label: 'Meal choice', options: meals }] : []),
                ...(p.note ? [{ id: I.msg, type: 'textarea', label: 'A message for us (optional)' }] : []),
            ],
        };
    };
    const rsvpHtml = (x, own) => {
        // All RSVP widgets on a site send to one form, so they all ask the first widget's questions
        const p = { ...(rsvpWidget(x.P)?.p || own), btn: own.btn }, I = RSVP_IDS, meals = lines(p.meals);
        if (!x.P.site.rsvpAction) return x.edit ? '<div class="form pic-empty"><span>💌 RSVP form — switch it on in the Content tab (free, takes a minute)</span></div>' : '';
        return `<form class="form rsvp" method="post" action="${esc(x.P.site.rsvpAction)}">
            <label>Your name<input name="${I.name}" required maxlength="100" autocomplete="name"></label>
            <label>Email${p.emailReq ? '' : ' (optional)'}<input name="${I.email}" type="email" maxlength="150" autocomplete="email"${p.emailReq ? ' required' : ''}></label>
            <fieldset class="rsvp-come"><legend>Will you come?</legend>${rsvpYesNo(p).map(o => `<label class="rsvp-opt"><input type="radio" name="${I.come}" value="${esc(o)}" required> ${esc(o)}</label>`).join('')}</fieldset>
            ${p.guests ? `<label>How many guests (including you)?<input name="${I.guests}" type="number" min="1" max="20" value="1"></label>` : ''}
            ${p.meal && meals.length ? `<label>Meal choice<select name="${I.meal}"><option value="">Choose…</option>${meals.map(m => `<option value="${esc(m)}">${esc(m)}</option>`).join('')}</select></label>` : ''}
            ${p.note ? `<label>A message for us (optional)<textarea name="${I.msg}" rows="3" maxlength="5000"></textarea></label>` : ''}
            <input class="hp" name="_hp" tabindex="-1" autocomplete="off" aria-hidden="true">
            ${backInput(x)}
            <button class="btn" type="submit">${esc(p.btn || 'Send my RSVP')}</button></form>`;
    };
    def('rsvp', { label: 'RSVP form', icon: '💌', group: 'pro', make: () => ({ btn: 'Send my RSVP', yes: 'Yes, I will be there', no: 'Sorry, I can’t make it', guests: true, meal: false, meals: 'Chicken\nFish\nVegetarian', note: true, emailReq: false, thanks: 'Thank you! Your RSVP has been sent.' }),
        fields: [F.text('btn', 'Button text'), F.text('yes', '“Yes” answer'), F.text('no', '“No” answer'), F.bool('guests', 'Ask how many guests'), F.bool('meal', 'Ask for a meal choice'), F.area('meals', 'Meal choices (one per line)'), F.bool('note', 'Ask for a message'), F.bool('emailReq', 'Email is required'), F.text('thanks', 'Thank-you message')],
        render: (w, x) => rsvpHtml(x, w.p) });

    def('optin', { label: 'Opt-in form', icon: '✉', group: 'funnel', make: () => ({ btn: 'Get instant access', name: true, phone: false, note: 'We respect your privacy. Unsubscribe any time.' }),
        fields: [F.text('btn', 'Button text'), F.bool('name', 'Ask for a name'), F.bool('phone', 'Ask for a phone number'), F.text('note', 'Small print under the button')],
        render: (w, x) => {
            const p = w.p;
            if (!x.P.site.leadAction) return x.edit ? '<div class="form pic-empty"><span>📥 Opt-in form — switch it on in the Content tab to collect leads (free)</span></div>' : '';
            const next = nextOf(x.page, x.P), nextUrl = next && x.mode === 'publish' ? new URL(pageHref(next.slug, x), x.origin).href : next ? pageHref(next.slug, x) : '';
            return `<form class="form optin" method="post" action="${esc(x.P.site.leadAction)}">
                ${p.name !== false ? '<input name="name" maxlength="100" autocomplete="name" placeholder="Your name" aria-label="Your name">' : ''}
                <input name="email" type="email" required maxlength="150" autocomplete="email" placeholder="Your best email" aria-label="Email">
                ${p.phone ? dialPhone({ label: 'Phone (optional)', compact: true }) : ''}
                <input class="hp" name="_hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                ${backInput(x)}
                <input type="hidden" name="_next" value="${esc(nextUrl)}"><input type="hidden" name="_site" value="${esc(x.P.site.publishSlug || '')}">
                <input type="hidden" name="_step" value="${esc(x.page.slug)}"><input type="hidden" name="_v" value="${x.variant === 'b' ? 'b' : 'a'}">
                <button class="btn btn-lg" type="submit">${esc(p.btn || 'Get instant access')}</button>${p.note ? `<small class="optin__note">🔒 ${esc(p.note)}</small>` : ''}</form>`;
        } });
    def('paybtn', { label: 'Buy button', icon: '💳', group: 'funnel', make: () => ({ text: 'Yes! Get it now', sub: '30-day money-back guarantee', align: 'center', no: '' }),
        fields: [F.text('sub', 'Small text under the button'), F.select('align', 'Alignment', { left: 'Left', center: 'Centre', right: 'Right', full: 'Full width' }), F.text('no', '“No thanks” link text (upsells)')],
        render: (w, x, H) => `<div class="paybtn al-${pick(w.p.align, ['center', 'left', 'right', 'full'])}"><a class="btn btn-lg ${H.K('text')}" href="${esc(href({ to: 'pay' }, x))}"${H.E('text', 'Button')}>${esc(w.p.text)}</a>${H.t('sub', 'small', 'muted', 'Small print')}${w.p.no ? `<a class="paybtn__no" href="${esc(href({ to: 'next' }, x))}">${esc(w.p.no)}</a>` : ''}</div>` });
    /** Where a form sends people back to after they submit (published pages; downloaded ones use the browser's Referer). */
    const backInput = x => `<input type="hidden" name="_back" value="${esc(x.mode === 'publish' ? new URL(pageHref(x.page.slug, x), x.origin).href : '')}">`;
    const formHtml = (x, p) => {
        if (x.P.site.formAction) return `<form class="form" method="post" action="${esc(x.P.site.formAction)}">
            ${p.name !== false ? '<label>Your name<input name="name" required maxlength="100" autocomplete="name"></label>' : ''}
            <label>Email<input name="email" type="email" required maxlength="150" autocomplete="email"${p.message === false ? ' placeholder="you@example.com"' : ''}></label>
            ${p.phone !== false ? dialPhone({ label: 'Phone (optional)' }) : ''}
            ${p.message !== false ? '<label>Message<textarea name="message" required rows="4" maxlength="5000"></textarea></label>' : ''}
            <input class="hp" name="_hp" tabindex="-1" autocomplete="off" aria-hidden="true">
            ${backInput(x)}
            <button class="btn" type="submit">${esc(p.btn || 'Send message')}</button></form>`;
        return x.edit ? '<div class="form pic-empty"><span>📨 Contact form — switch it on in the Content tab (free, takes a minute)</span></div>' : '';
    };

    /* ---------- sections (full-width blocks with their own layout) */
    const items = w => w.p.items || [];
    const g = n => `g${Math.min(n, 4)}`;
    def('hero', { label: 'Hero', icon: '✨', group: 'section', full: true, variants: { center: 'Centred', split: 'Image on the right', cover: 'Image background' },
        make: () => ({ v: 'center', eyebrow: 'Your tagline here', title: 'A clear headline that says what you do', text: 'One or two sentences about who you help and why they should choose you.', btn: 'Get in touch', link: { to: 'contact' }, btn2: '', link2: { to: 'url', val: '' }, image: '' }),
        fields: [F.text('eyebrow', 'Small line above the headline'), F.link('link', 'Button link'), F.text('btn2', 'Second button text (optional)'), F.link('link2', 'Second button link'), F.img('image', 'Photo')],
        render: (w, x, H) => {
            const v = pick(w.v, ['center', 'split', 'cover']), img = cssUrl(w.p.image), bg = v === 'cover' && img ? ` style="background-image:linear-gradient(rgba(10,15,30,.62),rgba(10,15,30,.62)),url('${img}')"` : '';
            const initial = (x.P.site.name || 'A').trim()[0]?.toUpperCase() || 'A';
            const copy = `<div class="hero-copy">${H.t('eyebrow', 'p', 'eyebrow', 'Tagline')}${H.t('title', 'h1', '', 'Your headline')}${H.t('text', 'p', 'lead', 'A short description', { ml: true })}<div class="actions">${H.btn('btn', 'link')}${H.btn('btn2', 'link2', 'btn btn-ghost')}</div></div>`;
            const media = v === 'split' ? H.pic('image', x.P.site.name, 'pic hero-pic', `<div class="pic hero-pic pic-fill" aria-hidden="true">${esc(initial)}</div>`) : '';
            return `<div class="wrap hero-${v}"${bg}${v === 'cover' && x.edit ? ` data-img="${esc(w.id)}|image"` : ''}>${copy}${media}</div>`;
        } });
    def('features', { label: 'Features', icon: '⭐', group: 'section', full: true, make: () => ({ title: 'Why choose us', intro: '', items: [{ icon: '⚡', title: 'Fast', text: 'Explain this benefit in a sentence.' }, { icon: '💎', title: 'Quality', text: 'Explain this benefit in a sentence.' }, { icon: '🤝', title: 'Friendly', text: 'Explain this benefit in a sentence.' }] }),
        fields: [F.area('intro', 'Intro text (optional)'), F.list('items', 'Features', { icon: '✅', title: 'New feature', text: 'Describe it.' }, [['icon', 'icon'], ['title', 'text']])],
        render: (w, x, H) => `<div class="wrap">${H.t('title', 'h2', 'hd', 'Heading')}${H.t('intro', 'p', 'intro', '', { ml: true })}<div class="grid ${g(items(w).length)}">${items(w).map((it, i) => `<div class="card"><div class="icon">${H.icon(`items.${i}.icon`)}</div>${H.t(`items.${i}.title`, 'h3', '', 'Title')}${H.t(`items.${i}.text`, 'p', '', 'Text', { ml: true })}</div>`).join('')}</div></div>` });
    def('imagetext', { label: 'Image + text', icon: '🖼️', group: 'section', full: true, variants: { left: 'Image on the left', right: 'Image on the right' },
        make: () => ({ v: 'left', eyebrow: '', title: 'Tell your story', text: '<p>Explain what makes you different, who is behind the business, or how your product works.</p>', btn: '', link: { to: 'contact' }, image: '' }),
        fields: [F.text('eyebrow', 'Small line above the heading'), F.text('btn', 'Button text (optional)'), F.link('link', 'Button link'), F.img('image', 'Photo')],
        render: (w, x, H) => `<div class="wrap split ${w.v === 'right' ? 'flip-side' : ''}">${H.pic('image', w.p.title, 'pic', '<div class="pic pic-fill" aria-hidden="true"></div>')}<div>${H.t('eyebrow', 'p', 'eyebrow', 'Tagline')}${H.t('title', 'h2', 'left hd', 'Heading')}${H.t('text', 'div', 'rt body', 'Text', { rich: true })}${H.btn('btn', 'link')}</div></div>` });
    def('steps', { label: 'How it works', icon: '🔢', group: 'section', full: true, make: () => ({ title: 'How it works', intro: '', items: [{ title: 'Get in touch', text: 'Tell us what you need.' }, { title: 'We plan it', text: 'You get a clear plan and price.' }, { title: 'Done', text: 'We deliver, you enjoy the result.' }] }),
        fields: [F.area('intro', 'Intro text (optional)'), F.list('items', 'Steps', { title: 'New step', text: 'Describe it.' }, [['title', 'text']], 6)],
        render: (w, x, H) => `<div class="wrap">${H.t('title', 'h2', 'hd', 'Heading')}${H.t('intro', 'p', 'intro', '', { ml: true })}<div class="grid ${g(items(w).length)}">${items(w).map((it, i) => `<div class="card"><div class="num">${i + 1}</div>${H.t(`items.${i}.title`, 'h3', '', 'Step')}${H.t(`items.${i}.text`, 'p', '', 'Text', { ml: true })}</div>`).join('')}</div></div>` });
    def('stats', { label: 'Numbers', icon: '📊', group: 'section', full: true, make: () => ({ items: [{ num: '500+', label: 'Happy customers' }, { num: '10 yrs', label: 'Experience' }, { num: '4.9★', label: 'Average rating' }, { num: '24/7', label: 'Support' }] }),
        fields: [F.list('items', 'Numbers', { num: '100', label: 'New number' }, [['label', 'text']], 6)],
        render: (w, x, H) => `<div class="wrap"><div class="stats ${g(items(w).length)}">${items(w).map((it, i) => `<div>${H.t(`items.${i}.num`, 'b', '', '100')}${H.t(`items.${i}.label`, 'span', '', 'Label')}</div>`).join('')}</div></div>` });
    def('pricing', { label: 'Pricing table', icon: '💲', group: 'section', full: true, make: () => ({ title: 'Simple pricing', intro: '', items: [
        { name: 'Basic', price: '$49', period: '/month', features: 'Feature one\nFeature two\nFeature three', btn: 'Choose Basic', featured: false },
        { name: 'Pro', price: '$99', period: '/month', features: 'Everything in Basic\nFeature four\nPriority support', btn: 'Choose Pro', featured: true },
        { name: 'Business', price: '$199', period: '/month', features: 'Everything in Pro\nFeature five\nDedicated manager', btn: 'Choose Business', featured: false }] }),
        fields: [F.area('intro', 'Intro text (optional)'), F.list('items', 'Plans', { name: 'New plan', price: '$0', period: '/month', features: 'Feature', btn: 'Choose', featured: false }, [['name', 'text'], ['featured', 'star']], 4)],
        render: (w, x, H) => `<div class="wrap">${H.t('title', 'h2', 'hd', 'Heading')}${H.t('intro', 'p', 'intro', '', { ml: true })}<div class="grid ${g(items(w).length)} plans">${items(w).map((it, i) =>
            `<div class="card plan${it.featured ? ' featured' : ''}">${it.featured ? '<span class="badge">Most popular</span>' : ''}${H.t(`items.${i}.name`, 'h3', '', 'Plan')}<p class="price">${H.t(`items.${i}.price`, 'b', '', '$0')}${H.t(`items.${i}.period`, 'span', '', '/month')}</p>
            <ul${H.E(`items.${i}.features`, 'One feature per line', { ml: true })} class="${H.K(`items.${i}.features`)}">${x.edit ? nl(it.features) : String(it.features || '').split('\n').filter(s => s.trim()).map(s => `<li>${esc(s)}</li>`).join('')}</ul>
            ${it.btn ? `<a class="btn ${it.featured ? '' : 'btn-ghost'}" href="${esc(href({ to: 'contact' }, x))}"${H.E(`items.${i}.btn`, 'Button')}>${esc(it.btn)}</a>` : ''}</div>`).join('')}</div></div>` });
    def('gallery', { label: 'Gallery', icon: '🖼', group: 'section', full: true, make: () => ({ title: 'Our work', items: [{ src: '', caption: '' }, { src: '', caption: '' }, { src: '', caption: '' }] }),
        fields: [F.list('items', 'Photos', { src: '', caption: '' }, [['src', 'img'], ['caption', 'text']], 24)],
        render: (w, x, H) => `<div class="wrap">${H.t('title', 'h2', 'hd', 'Heading')}<div class="gallery">${items(w).map((it, i) => (it.src || x.edit) ? `<figure>${H.pic(`items.${i}.src`, it.caption || x.P.site.name, 'pic')}${H.t(`items.${i}.caption`, 'figcaption', '', 'Caption (optional)')}</figure>` : '').join('')}</div></div>` });
    def('team', { label: 'Team', icon: '👥', group: 'section', full: true, make: () => ({ title: 'Meet the team', items: [{ photo: '', name: 'Your name', role: 'Founder' }, { photo: '', name: 'Team member', role: 'Role' }, { photo: '', name: 'Team member', role: 'Role' }] }),
        fields: [F.list('items', 'People', { photo: '', name: 'Name', role: 'Role' }, [['photo', 'img'], ['name', 'text']], 12)],
        render: (w, x, H) => `<div class="wrap">${H.t('title', 'h2', 'hd', 'Heading')}<div class="grid ${g(items(w).length)} team">${items(w).map((it, i) =>
            `<div class="person">${H.pic(`items.${i}.photo`, it.name, 'avatar', `<div class="avatar pic-fill" aria-hidden="true">${esc((it.name || '?').trim()[0] || '?')}</div>`)}${H.t(`items.${i}.name`, 'h3', '', 'Name')}${H.t(`items.${i}.role`, 'p', '', 'Role')}</div>`).join('')}</div></div>` });
    def('testimonials', { label: 'Reviews', icon: '💬', group: 'section', full: true, make: () => ({ title: 'What customers say', items: [{ quote: 'Fantastic service from start to finish. Highly recommended!', name: 'Customer name', role: 'Customer' }, { quote: 'Quick, friendly and great value. We will be back.', name: 'Customer name', role: 'Customer' }] }),
        fields: [F.list('items', 'Reviews', { quote: 'What they said.', name: 'Name', role: '' }, [['name', 'text']], 6)],
        render: (w, x, H) => `<div class="wrap">${H.t('title', 'h2', 'hd', 'Heading')}<div class="grid ${g(Math.min(items(w).length, 3))}">${items(w).map((it, i) =>
            `<figure class="card tst"><div class="stars">★★★★★</div>${H.t(`items.${i}.quote`, 'blockquote', '', 'What they said', { ml: true, keep: true })}<figcaption><span>${H.t(`items.${i}.name`, 'b', '', 'Name')}${H.t(`items.${i}.role`, 'small', 'muted', 'Who they are')}</span></figcaption></figure>`).join('')}</div></div>` });
    def('logos', { label: 'Client logos', icon: '🏷️', group: 'section', full: true, make: () => ({ title: 'Trusted by', items: [{ src: '' }, { src: '' }, { src: '' }, { src: '' }] }),
        fields: [F.list('items', 'Logos', { src: '' }, [['src', 'img']], 12)],
        render: (w, x, H) => `<div class="wrap">${H.t('title', 'p', 'logos-title', 'Trusted by')}<div class="logos">${items(w).map((it, i) => (it.src || x.edit) ? H.pic(`items.${i}.src`, 'Client logo', 'logo-img') : '').join('')}</div></div>` });
    def('faq', { label: 'FAQ', icon: '❓', group: 'section', full: true, make: () => ({ title: 'Questions', items: [{ q: 'A question customers often ask?', a: 'A short, helpful answer.' }, { q: 'Another common question?', a: 'Another clear answer.' }] }),
        fields: [F.list('items', 'Questions', { q: 'New question?', a: 'The answer.' }, [['q', 'text']], 15)],
        render: (w, x, H) => `<div class="wrap narrow">${H.t('title', 'h2', 'hd', 'Heading')}<div class="faq">${items(w).map((it, i) =>
            `<details${x.edit ? ' open' : ''}><summary${H.E(`items.${i}.q`, 'Question')} class="${H.K(`items.${i}.q`)}">${esc(it.q)}</summary>${H.t(`items.${i}.a`, 'p', '', 'Answer', { ml: true, keep: true })}</details>`).join('')}</div></div>` });
    def('video', { label: 'Video section', icon: '▶️', group: 'section', full: true, make: () => ({ title: 'See it in action', text: '', url: '' }), fields: [F.text('url', 'YouTube link', 'https://www.youtube.com/watch?v=…'), F.area('text', 'Text under the heading (optional)')],
        render: (w, x, H) => `<div class="wrap narrow">${H.t('title', 'h2', 'hd', 'Heading')}${H.t('text', 'p', 'intro', '', { ml: true })}${W.video1.render(w, x, H)}</div>` });
    def('map', { label: 'Map', icon: '📍', group: 'section', full: true, make: () => ({ title: 'Find us', address: '' }), fields: [F.text('address', 'Address shown on the map', '12 Main Street, City')],
        render: (w, x, H) => `<div class="wrap">${H.t('title', 'h2', 'hd', 'Heading')}${w.p.address ? `<div class="embed map"><iframe src="https://www.google.com/maps?q=${encodeURIComponent(w.p.address)}&output=embed" title="Map" loading="lazy"></iframe></div><p class="intro">📍 ${esc(w.p.address)}</p>` : (x.edit ? '<div class="embed map pic-empty"><span>Type your address in the Content tab</span></div>' : '')}</div>` });
    def('textblock', { label: 'Text section', icon: '📝', group: 'section', full: true, make: () => ({ title: 'A heading', text: '<p>Write anything here — your story, details, terms or an announcement.</p>' }), fields: [],
        render: (w, x, H) => `<div class="wrap narrow prose">${H.t('title', 'h2', 'left hd', 'Heading')}${H.t('text', 'div', 'rt', 'Text', { rich: true })}</div>` });
    def('cta', { label: 'Call to action', icon: '📣', group: 'section', full: true, make: () => ({ title: 'Ready to get started?', text: 'Get in touch today — we reply within one business day.', btn: 'Contact us', link: { to: 'contact' } }), fields: [F.link('link', 'Button link')], bg: 'brand',
        render: (w, x, H) => `<div class="wrap narrow center">${H.t('title', 'h2', 'hd', 'Heading')}${H.t('text', 'p', 'intro', '', { ml: true })}${H.btn('btn', 'link', 'btn btn-light')}</div>` });
    def('contact', { label: 'Contact', icon: '📞', group: 'section', full: true, make: () => ({ title: 'Get in touch', text: 'Call, message us or fill in the form — we reply the same day.', whatsapp: '', phone: '', email: '', address: '', form: true, btn: 'Send message' }),
        fields: [F.text('whatsapp', 'WhatsApp number', '+1 555 123 4567'), F.text('phone', 'Phone'), F.text('email', 'Email'), F.text('address', 'Address (optional)'), F.bool('form', 'Show a contact form')],
        render: (w, x, H) => {
            const p = w.p, wa = digits(p.whatsapp).replace(/^\+/, ''), tel = digits(p.phone), mail = String(p.email || '').trim();
            const acts = [wa && `<a class="btn" href="https://wa.me/${esc(wa)}">💬 WhatsApp</a>`, tel && `<a class="btn btn-ghost" href="tel:${esc(tel)}">📞 ${esc(p.phone)}</a>`, mail && `<a class="btn btn-ghost" href="mailto:${esc(mail)}">✉️ ${esc(mail)}</a>`].filter(Boolean).join('');
            const form = p.form ? formHtml(x, p) : '';
            return `<div class="wrap ${form ? 'contact-grid' : 'narrow center'}"><div>${H.t('title', 'h2', (form ? 'left ' : '') + 'hd', 'Heading')}${H.t('text', 'p', 'intro', '', { ml: true })}
                ${acts ? `<div class="actions ${form ? '' : 'center'}">${acts}</div>` : (x.edit ? '<p class="intro">Add WhatsApp, phone or email in the Content tab.</p>' : '')}${p.address ? `<p class="addr">📍 ${esc(p.address)}</p>` : ''}</div>${form ? `<div class="card">${form}</div>` : ''}</div>`;
        } });
    /* ---------- online store (/online-store-builder): product cards, a cart and checkout.
       Cards carry data-product / data-name / data-price for the cart (builder-runtime.js keeps it in window.name, so
       it follows the shopper from page to page). Checkout posts the cart to owlvix (/shop/{id}), which prices it from
       the products saved before every publish and download (shopProducts) — never from the page. */
    // Must match App\Support\Stripe::CURRENCIES and ::ZERO_DECIMAL (StoreBuilderTest checks this)
    const CURRENCIES = ['usd', 'eur', 'gbp', 'cad', 'aud', 'nzd', 'chf', 'sek', 'nok', 'dkk', 'pln', 'czk', 'huf', 'ron', 'try', 'aed', 'sar', 'qar', 'inr', 'pkr', 'bdt', 'lkr', 'myr', 'sgd', 'hkd', 'idr', 'php', 'thb', 'jpy', 'krw', 'cny', 'brl', 'mxn', 'zar', 'ngn', 'kes', 'egp'];
    const ZERO_DEC = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];
    /** A typed price ("24.99", "1,500") as a number, or null when it isn't one. */
    const MONEY = v => { const t = String(v ?? '').replace(/,/g, '').trim(), n = +t; return t !== '' && isFinite(n) && n >= 0 && n <= 1e6 ? n : null; };
    const money = (v, cur) => {
        const n = MONEY(v); if (n === null) return '';
        cur = CURRENCIES.includes(cur) ? cur : 'usd';
        const zero = ZERO_DEC.includes(cur), whole = zero || Math.round(n * 100) % 100 === 0;
        try { return new Intl.NumberFormat('en', { style: 'currency', currency: cur.toUpperCase(), minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: zero ? 0 : 2 }).format(n); }
        catch { return cur.toUpperCase() + ' ' + (whole ? n : n.toFixed(2)); }
    };
    /** "Size: S, M, L" (older projects) → { label: 'Size', list: ['S', 'M', 'L'] }. */
    const optionsOf = v => {
        let t = String(v || '').trim(), label = '';
        const m = t.match(/^([^:,]{1,30}):(.*)$/s); if (m) { label = m[1].trim(); t = m[2]; }
        return { label: label || 'Option', list: [...new Set(t.split(',').map(o => o.trim().slice(0, 60)).filter(Boolean))].slice(0, 30) };
    };
    // Every country a shipping zone can name (ISO 3166 codes; names come from the browser)
    const COUNTRIES = 'AD AE AF AG AI AL AM AO AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GT GU GW GY HK HN HR HT HU ID IE IL IM IN IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG US UY UZ VA VC VE VG VI VN VU WF WS XK YE YT ZA ZM ZW'.split(' ');
    // Each country's calling code (for the phone box at checkout)
    const DIAL = Object.fromEntries('AD376 AE971 AF93 AG1268 AI1264 AL355 AM374 AO244 AR54 AS1684 AT43 AU61 AW297 AX358 AZ994 BA387 BB1246 BD880 BE32 BF226 BG359 BH973 BI257 BJ229 BL590 BM1441 BN673 BO591 BQ599 BR55 BS1242 BT975 BW267 BY375 BZ501 CA1 CC61 CD243 CF236 CG242 CH41 CI225 CK682 CL56 CM237 CN86 CO57 CR506 CU53 CV238 CW599 CX61 CY357 CZ420 DE49 DJ253 DK45 DM1767 DO1809 DZ213 EC593 EE372 EG20 EH212 ER291 ES34 ET251 FI358 FJ679 FK500 FM691 FO298 FR33 GA241 GB44 GD1473 GE995 GF594 GG44 GH233 GI350 GL299 GM220 GN224 GP590 GQ240 GR30 GT502 GU1671 GW245 GY592 HK852 HN504 HR385 HT509 HU36 ID62 IE353 IL972 IM44 IN91 IQ964 IR98 IS354 IT39 JE44 JM1876 JO962 JP81 KE254 KG996 KH855 KI686 KM269 KN1869 KP850 KR82 KW965 KY1345 KZ7 LA856 LB961 LC1758 LI423 LK94 LR231 LS266 LT370 LU352 LV371 LY218 MA212 MC377 MD373 ME382 MF590 MG261 MH692 MK389 ML223 MM95 MN976 MO853 MP1670 MQ596 MR222 MS1664 MT356 MU230 MV960 MW265 MX52 MY60 MZ258 NA264 NC687 NE227 NF672 NG234 NI505 NL31 NO47 NP977 NR674 NU683 NZ64 OM968 PA507 PE51 PF689 PG675 PH63 PK92 PL48 PM508 PN64 PR1787 PS970 PT351 PW680 PY595 QA974 RE262 RO40 RS381 RU7 RW250 SA966 SB677 SC248 SD249 SE46 SG65 SH290 SI386 SK421 SL232 SM378 SN221 SO252 SR597 SS211 ST239 SV503 SX1721 SY963 SZ268 TC1649 TD235 TG228 TH66 TJ992 TK690 TL670 TM993 TN216 TO676 TR90 TT1868 TV688 TW886 TZ255 UA380 UG256 US1 UY598 UZ998 VA39 VC1784 VE58 VG1284 VI1340 VN84 VU678 WF681 WS685 XK383 YE967 YT262 ZA27 ZM260 ZW263'.split(' ').map(x => [x.slice(0, 2), '+' + x.slice(2)]));
    const flag = c => String.fromCodePoint(...[...c].map(ch => 0x1F1E6 + ch.charCodeAt(0) - 65));
    const REGION = (() => { try { return new Intl.DisplayNames(['en'], { type: 'region' }); } catch { return null; } })();
    const countryName = c => { try { return REGION?.of(c) || c; } catch { return c; } };
    const productWidgets = P => P.pages.flatMap(pg => pg.sections.flatMap(sc => sc.cols.flatMap(c => c.widgets))).filter(w => w.type === 'products');
    /**
     * Every product (and each of its sizes / colours) needs its own id: a new or copied one has none, or shares one.
     * Older projects kept options as text ("Size: S, M"): they become a list with their own prices. Fixes P in place.
     */
    const fixPids = P => {
        const seen = new Set();
        productWidgets(P).forEach(w => (w.p.items || []).forEach(it => {
            if (!/^[a-z0-9]{1,24}$/i.test(it.pid || '') || seen.has(it.pid)) it.pid = uid();
            seen.add(it.pid);
            if (!Array.isArray(it.vals)) { const o = optionsOf(it.options); it.vals = o.list.map(v => ({ vid: '', v, price: '' })); if (o.list.length) it.optName = o.label; }
            delete it.options;
            it.optName ??= 'Size';
            if (!Array.isArray(it.images)) it.images = [];
            const vs = new Set(); it.vals.forEach(v => { if (!/^[a-z0-9]{1,24}$/i.test(v.vid || '') || vs.has(v.vid)) v.vid = uid(); vs.add(v.vid); });
            // a second choice (size × colour) and the product's type: item, digital (a file) or gift (a gift card)
            if (!Array.isArray(it.vals2)) it.vals2 = [];
            it.opt2Name ??= 'Colour';
            const vs2 = new Set(); it.vals2.forEach(v => { if (!/^[a-z0-9]{1,24}$/i.test(v.vid || '') || vs2.has(v.vid)) v.vid = uid(); vs2.add(v.vid); });
            if (!['item', 'digital', 'gift'].includes(it.kind)) it.kind = 'item';
        }));
        return P;
    };
    /** A product's sizes / colours with a name (the same name twice counts once, as on the server). */
    const named = list => { const seen = new Set(); return (list || []).filter(v => { const n = String(v.v || '').trim(); if (!n || seen.has(n)) return false; seen.add(n); return true; }); };
    const variants = it => (it.kind === 'gift' ? [] : named(it.vals));
    /** The second choice (e.g. colour): only next to a first one, as on the server. */
    const variants2 = it => (variants(it).length ? named(it.vals2) : []);
    /** The product's own price — or, when only its sizes have prices, the cheapest of those. */
    const basePrice = it => MONEY(it.price) ?? (variants(it).map(v => MONEY(v.price)).filter(n => n !== null).sort((a, b) => a - b)[0] ?? null);
    /** The products checkout sells: sent to owlvix before every publish and download. */
    const shopProducts = P => productWidgets(fixPids(P)).flatMap(w => (w.p.items || []).filter(it => basePrice(it) !== null && String(it.name || '').trim())
        .map(it => ({ id: it.pid, name: String(it.name).trim().slice(0, 200), price: String(basePrice(it)), sold: !!it.sold, opt: String(it.optName || 'Option').trim().slice(0, 30),
            vars: variants(it).map(v => ({ id: v.vid, name: String(v.v).trim().slice(0, 60), price: MONEY(v.price) === null ? '' : String(MONEY(v.price)) })),
            opt2: String(it.opt2Name || 'Option').trim().slice(0, 30), vars2: variants2(it).map(v => ({ id: v.vid, name: String(v.v).trim().slice(0, 60) })),
            kind: it.kind || 'item', file: it.kind === 'digital' ? it.file || null : null })));
    /** Stock keys, as the server keeps them: "product", "product:size" or "product:size_colour". */
    const stockKeys = it => {
        const a = variants(it), b = variants2(it);
        if (!a.length) return [[it.pid, '']];
        return a.flatMap(v => (b.length ? b.map(c => [`${it.pid}:${v.vid}_${c.vid}`, `${String(v.v).trim()} / ${String(c.v).trim()}`]) : [[`${it.pid}:${v.vid}`, String(v.v).trim()]]));
    };
    const defaultsShop = () => ({ currency: 'usd', delivery: true, fee: '4.99', freeOver: '50', zones: [], pickup: false, pickupText: '', cod: true, bank: false, bankText: '', card: false, cardReady: false, mode: 'test',
        taxRate: '', taxIncl: true, taxLabel: 'Tax', taxShip: false, codes: false, phoneReq: true, thanks: '', whatsapp: '', remind: true });
    /** Checkout works on this finished page: a store whose checkout is switched on. */
    const shopOn = x => x.P.kind === 'store' && !!x.P.site.shopAction;
    /** The ways to pay the page offers (cards only once a Stripe account is connected). */
    const payWays = s => [s.card && s.cardReady && ['card', '💳 Pay by card'], s.cod && ['cod', s.pickup && !s.delivery ? '💵 Pay when you collect' : '💵 Cash on delivery'], s.bank && ['bank', '🏦 Bank transfer']].filter(Boolean);
    /** The price a product shows: its own, or "from" the cheapest size when sizes cost different amounts. */
    const priceOf = it => { const b = basePrice(it), vs = variants(it), ps = vs.length ? vs.map(v => MONEY(v.price) ?? b) : [b]; const ok = ps.filter(n => n !== null); return { low: ok.length ? Math.min(...ok) : null, varies: new Set(ok).size > 1 }; };
    def('products', { label: 'Products', icon: '🛍', group: 'shop', full: true,
        make: () => ({ title: 'Shop our bestsellers', intro: '', cols: '3', btn: 'Add to cart', filters: true, items: [
            { pid: '', name: 'Product name', price: '24', old: '', desc: 'A short description of this product.', long: '', cat: '', badge: 'New', image: '', images: [], sold: false, optName: 'Size', vals: [] },
            { pid: '', name: 'Another product', price: '39', old: '49', desc: 'What makes it special, in a sentence.', long: '', cat: '', badge: 'Sale', image: '', images: [], sold: false, optName: 'Size', vals: [{ vid: '', v: 'S', price: '' }, { vid: '', v: 'M', price: '' }, { vid: '', v: 'L', price: '' }] },
            { pid: '', name: 'Third product', price: '18.50', old: '', desc: 'Materials, size or what is included.', long: '', cat: '', badge: '', image: '', images: [], sold: false, optName: 'Size', vals: [] }] }),
        fields: [F.area('intro', 'Intro text (optional)'), F.select('cols', 'Products per row', { 3: 'Three', 2: 'Two', 4: 'Four' }), F.text('btn', 'Button text'), F.bool('filters', 'Show categories & search (when there are some)'),
            F.list('items', 'Products', { pid: '', name: 'New product', price: '19.99', old: '', desc: 'A short description.', long: '', cat: '', badge: '', image: '', images: [], sold: false, optName: 'Size', vals: [] }, [['image', 'img'], ['name', 'text'], ['price', 'text']], 50)],
        render: (w, x, H) => {
            const cur = x.P.site.shop?.currency, n = pick(String(w.p.cols), ['3', '2', '4']), canBuy = x.edit || shopOn(x);
            const items = H.items(), cats = [...new Set(items.map(it => String(it.cat || '').trim()).filter(Boolean))];
            const cards = items.map((it, i) => {
                const vs = variants(it), pr = priceOf(it), price = pr.low === null ? '' : money(pr.low, cur), old = !pr.varies && MONEY(it.old) > MONEY(it.price) ? money(it.old, cur) : '';
                const vs2 = variants2(it);
                const opts = (vs.length ? `<div class="popt"><span class="popt__l">${esc(it.optName || 'Option')}</span><div class="popt__v" role="group" aria-label="${esc(it.optName || 'Option')}">${vs.map(v => {
                    const vp = MONEY(v.price); return `<button type="button" data-optv="${esc(v.vid)}" data-v="${esc(String(v.v).trim())}"${vp !== null ? ` data-price="${vp}"` : ''} aria-pressed="${vs.length === 1}">${esc(String(v.v).trim())}</button>`;
                }).join('')}</div></div>` : '') + (vs2.length ? `<div class="popt"><span class="popt__l">${esc(it.opt2Name || 'Option')}</span><div class="popt__v" role="group" data-second aria-label="${esc(it.opt2Name || 'Option')}">${vs2.map(v =>
                    `<button type="button" data-optv2="${esc(v.vid)}" data-v="${esc(String(v.v).trim())}" aria-pressed="${vs2.length === 1}">${esc(String(v.v).trim())}</button>`).join('')}</div></div>` : '');
                const tag = it.kind === 'digital' ? '<span class="pkind">⬇️ Instant download</span>' : it.kind === 'gift' ? '<span class="pkind">🎁 Gift card · code by email</span>' : '';
                const buy = it.sold ? '<span class="pcard__sold">Sold out</span>' : !canBuy || !price ? '' : `${opts}<button type="button" class="btn btn-sm pcard__add ${H.K('btn')}" data-add>${esc(w.p.btn || 'Add to cart')}</button>`;
                const data = x.edit ? '' : ` id="p-${esc(it.pid || '')}" data-product="${esc(it.pid || '')}" data-name="${esc(String(it.name || '').trim())}" data-price="${esc(String(basePrice(it) ?? ''))}" data-cat="${esc(String(it.cat || '').trim())}" data-kind="${esc(it.kind || 'item')}"`;
                const initial = esc((String(it.name || '?').trim()[0] || '?').toUpperCase());
                // no photo yet: the same coloured tile as the finished page, with an "Add photo" button on it in the editor
                const pic = it.image ? H.pic(`items.${i}.image`, it.name || 'Product', 'pic')
                    : `<div class="pic pic-fill pcard__ph"${x.edit ? ` data-img="${esc(w.id)}|items.${i}.image"` : ' aria-hidden="true"'}><b>${initial}</b>${x.edit ? '<span>＋ Add photo</span>' : ''}</div>`;
                const extra = (it.images || []).filter(Boolean);
                // what the product's own page (opened from the card) adds: more photos and the longer description
                const more = x.edit || (!extra.length && !String(it.long || '').trim()) ? '' : `<div class="pcard__more" hidden>${extra.map(src => `<img src="${esc(src)}" alt="${esc(it.name || '')}" loading="lazy">`).join('')}${String(it.long || '').trim() ? `<div class="pcard__long">${nl(it.long)}</div>` : ''}</div>`;
                const open = !x.edit && it.pid ? `<a class="pcard__open" href="#p-${esc(it.pid)}" data-quick aria-label="${esc(String(it.name || 'Product').trim())} — see details"></a>` : '';
                return `<article class="pcard${it.sold ? ' is-sold' : ''}"${data}><div class="pcard__pic">${pic}${it.badge ? `<span class="pbadge">${esc(it.badge)}</span>` : ''}${extra.length && !x.edit ? `<span class="pcard__n">＋${extra.length}</span>` : ''}${open}</div>
                    <div class="pcard__b">${H.t(`items.${i}.name`, 'h3', '', 'Product name', { keep: true })}${H.t(`items.${i}.desc`, 'p', 'muted', 'Short description', { ml: true })}<p class="prating" data-prating hidden></p><p class="pprice"><b data-pprice>${pr.varies ? 'From ' : ''}${price || (x.edit ? 'Set a price →' : '')}</b>${old ? ` <s>${old}</s>` : ''}</p>${tag}<p class="pstock" data-pstock hidden></p>${buy}</div>${more}</article>`;
            }).join('');
            const off = x.edit && x.P.kind === 'store' && !x.P.site.shopAction ? '<p class="shop-off">🛒 Checkout is off — switch it on in the Content tab</p>' : '';
            const filters = w.p.filters !== false && (cats.length > 1 || items.length > 8)
                ? `<div class="pfilter">${cats.length > 1 ? `<div class="pfilter__cats" role="group" aria-label="Categories"><button type="button" data-pcat="" aria-pressed="true">All</button>${cats.map(c => `<button type="button" data-pcat="${esc(c)}" aria-pressed="false">${esc(c)}</button>`).join('')}</div>` : ''}${items.length > 8 ? '<input type="search" name="_q" data-psearch placeholder="Search products" aria-label="Search products" maxlength="60">' : ''}</div>` : '';
            return `<div class="wrap">${H.t('title', 'h2', 'hd', 'Heading')}${H.t('intro', 'p', 'intro', '', { ml: true })}${off}${filters}<div class="grid g${n} shop-grid">${cards}</div><p class="pnone" data-pnone hidden>No products match. Try another search.</p></div>`;
        } });
    /** The cart drawer with the checkout form (finished store pages, once checkout is on). */
    const shopHtml = x => {
        if (!shopOn(x)) return '';
        const s = x.P.site.shop, cur = s.currency, fee = MONEY(s.fee) || 0, free = MONEY(s.freeOver) || 0, zones = (s.zones || []).filter(z => z && typeof z === 'object');
        const byZone = !!zones.length && s.delivery;
        const dels = [s.delivery && ['delivery', byZone ? '🚚 Delivery' : `🚚 Delivery — ${fee ? money(fee, cur) + (free ? ` (free over ${money(free, cur)})` : '') : 'free'}`], s.pickup && ['pickup', `🏪 Pick up${s.pickupText ? ' — ' + s.pickupText : ' — free'}`]].filter(Boolean);
        const pays = payWays(s);
        const choice = (name, list, legend) => list.length > 1
            ? `<fieldset class="ob-choice"><legend>${legend}</legend>${list.map(([v, l], i) => `<label><input type="radio" name="${name}" value="${v}"${i ? '' : ' checked'}> ${esc(l)}</label>`).join('')}</fieldset>`
            : list.length ? `<div class="ob-choice"><b>${legend}</b><input type="hidden" name="${name}" value="${list[0][0]}"><p class="ob-one">${esc(list[0][1])}</p></div>` : '';
        const delivers = !!s.delivery, first = dels[0]?.[0] === 'delivery';
        // shipping by country: the countries the zones name, or every country when one zone is "everywhere else"
        const anywhere = zones.some(z => !(z.countries || []).length), named = [...new Set(zones.flatMap(z => z.countries || []))];
        const list = (anywhere ? COUNTRIES : named).map(c => [c, countryName(c)]).sort((a, b) => a[1].localeCompare(b[1]));
        const country = byZone ? `<label data-country${first ? '' : ' hidden'}>Country<select name="country" autocomplete="country"${first ? ' required' : ''}><option value="">Choose your country…</option>${list.map(([c, l]) => `<option value="${c}">${esc(l)}</option>`).join('')}</select></label>` : '';
        const tax = MONEY(s.taxRate) > 0;
        // the code box: for discount codes, and always when the shop sells gift cards (they're spent there)
        const sellsGifts = productWidgets(x.P).some(w => (w.p.items || []).some(it => it.kind === 'gift'));
        const home = zones.flatMap(z => z.countries || [])[0] || ''; // the shop's own country, until the shopper's browser says otherwise
        const form = pays.length ? `<form class="form ob-checkout" method="post" action="${esc(x.P.site.shopAction)}" data-checkout>
                ${s.codes || sellsGifts ? '<div class="ob-code"><label>Discount or gift card code<span><input name="_code" maxlength="40" autocomplete="off" autocapitalize="characters" data-code><button type="button" class="btn btn-sm btn-ghost" data-code-apply>Apply</button></span></label><small data-code-msg hidden></small></div>' : ''}
                <div class="ob-sum"><div><span>Subtotal</span><b data-sum="sub"></b></div><div data-sum-row="off" hidden><span>Discount <small data-sum-code></small></span><b data-sum="off"></b></div><div data-sum-row="fee"><span data-sum-label>Delivery</span><b data-sum="fee"></b></div>${tax && !s.taxIncl ? `<div><span>${esc(s.taxLabel || 'Tax')}</span><b data-sum="tax"></b></div>` : ''}<div data-sum-row="gift" hidden><span>Gift card</span><b data-sum="gift"></b></div><div class="ob-total"><span>Total</span><b data-sum="total"></b></div>${tax && s.taxIncl ? `<small class="ob-taxnote">Includes <span data-sum="tax"></span> ${esc(!s.taxLabel || s.taxLabel === 'Tax' ? 'tax' : s.taxLabel)}</small>` : ''}</div>
                <div class="ob-ship" data-ship>${choice('_delivery', dels, 'Delivery')}</div>
                <label>Your name<input name="name" required maxlength="100" autocomplete="name"></label>
                <label>Email<input name="email" type="email" required maxlength="150" autocomplete="email"></label>
                <div class="ob-ship" data-ship>${country}</div>
                ${dialPhone({ label: 'Phone' + (s.phoneReq ? '' : ' (optional)'), required: s.phoneReq, home })}
                <label data-ship data-address${delivers ? '' : ' hidden'}>Delivery address<textarea name="address" rows="3" maxlength="1000" autocomplete="street-address"${delivers && first ? ' required' : ''}></textarea></label>
                <label>Note for the shop (optional)<textarea name="note" rows="2" maxlength="1000"></textarea></label>
                ${choice('_pay', pays, 'Payment')}
                <input type="hidden" name="_cart" value="[]" data-cart-field><input class="hp" name="_hp" tabindex="-1" autocomplete="off" aria-hidden="true">${backInput(x)}
                <button class="btn btn-lg ob-place" type="submit" data-place>Place order</button>
                ${pays.some(p => p[0] === 'card') ? `<small class="ob-small">🔒 Card payments are handled securely by Stripe${s.mode === 'test' ? ' (test mode — no real money)' : ''}.</small>` : ''}</form>`
            : '<p class="ob-one">This shop isn’t taking orders online yet.</p>';
        return `${x.P.site.bare ? `<a href="#cart" class="nav-cart ob-cartfab" data-cart-open aria-label="Your cart">${I(ICONS.cart)}<span class="nav-cart__n" data-cart-count hidden>0</span></a>` : ''}
<div class="ob-cart" data-cart hidden role="dialog" aria-modal="true" aria-labelledby="ob-cart-t"><div class="ob-cart__box">
<div class="ob-cart__top"><h3 id="ob-cart-t">Your cart</h3><button type="button" class="ob-cart__x" data-cart-close aria-label="Close">×</button></div>
<p class="ob-cart__empty" data-cart-empty>Your cart is empty.</p><ul class="ob-lines" data-cart-lines></ul>${form}
<p class="ob-myorders"><a href="${esc(x.P.site.shopAction)}/account" target="_blank" rel="noopener">📦 My orders</a></p></div></div>
<div class="ob-quick" data-quickview hidden role="dialog" aria-modal="true" aria-label="Product"><div class="ob-quick__box"><button type="button" class="ob-cart__x ob-quick__x" data-quick-close aria-label="Close">×</button><div data-quick-body></div></div></div>`;
    };
    /**
     * A phone box with the country's flag and calling code in front (checkout, contact and opt-in forms). The real
     * <select name="_dial"> lies invisibly over the flag, so it works with a finger, the keyboard and screen readers,
     * and without the runtime too; the runtime starts it on the visitor's country (from their IP address). The server
     * puts the code in front of the number (App\Support\Phone::combine).
     */
    const dialPhone = ({ label = 'Phone', required = false, home = '', compact = false } = {}) => {
        const start = DIAL[home] ? home : 'US', id = 'ph-' + uid();
        const opts = COUNTRIES.filter(c => DIAL[c]).map(c => [c, countryName(c)]).sort((a, b) => a[1].localeCompare(b[1]))
            .map(([c, l]) => `<option value="${DIAL[c]}" data-c="${c}"${c === start ? ' selected' : ''}>${flag(c)} ${esc(l)} (${DIAL[c]})</option>`).join('');
        return `<div class="ob-phone${compact ? ' ob-phone--compact' : ''}">${compact ? '' : `<span class="ob-phone__l" id="${id}">${esc(label)}</span>`}<div class="ob-phone__row" data-dial-row>
            <label class="ob-dial"><span class="ob-dial__face" aria-hidden="true" data-dial-face>${flag(start)} ${DIAL[start]}</span><select name="_dial" aria-label="Country calling code" data-dial>${opts}</select></label>
            <input name="phone" type="tel" maxlength="24" autocomplete="tel-national" inputmode="tel" placeholder="${compact ? esc(label) : 'Phone number'}" ${compact ? `aria-label="${esc(label)}"` : `aria-labelledby="${id}"`}${required ? ' required' : ''}></div></div>`;
    };
    /** What the cart needs to price delivery and tax like the server does (Shop::totals). */
    const shopData = s => JSON.stringify({ fee: s.delivery ? MONEY(s.fee) || 0 : 0, free: MONEY(s.freeOver) || 0, zones: s.delivery ? (s.zones || []).map(z => ({ c: z.countries || [], fee: MONEY(z.fee) || 0, free: MONEY(z.freeOver) || 0 })) : [],
        tax: Math.round(Math.min(50, MONEY(s.taxRate) || 0) * 1000) / 1000, incl: s.taxIncl !== false, ship: !!s.taxShip });

    // Older projects used "text" for the text section
    const LEGACY_TYPES = { text: 'textblock' };

    /* ================================================================ model */
    const LAYOUTS = { 1: ['1fr', 1, 'One column'], 2: ['1fr 1fr', 2, 'Two columns'], '2-1': ['2fr 1fr', 2, 'Wide + narrow'], '1-2': ['1fr 2fr', 2, 'Narrow + wide'], 3: ['repeat(3,1fr)', 3, 'Three columns'], 4: ['repeat(4,1fr)', 4, 'Four columns'] };
    const widget = type => { const d = W[type], p = clone(d.make()); const v = p.v || ''; delete p.v; return { id: uid(), type, v, p, st: {}, adv: {} }; };
    const column = (widgets = []) => ({ id: uid(), st: {}, widgets });
    const section = (layout = '1', cols) => ({ id: uid(), layout, width: 'boxed', bg: 'auto', st: {}, adv: {}, cols: cols || Array.from({ length: LAYOUTS[layout][1] }, () => column()) });
    const sectionOf = type => { const w = widget(type), s = section('1', [column([w])]); if (W[type].bg) s.bg = W[type].bg; return s; };
    const isFull = s => s.cols.length === 1 && s.cols[0].widgets.length > 0 && s.cols[0].widgets.every(w => W[w.type]?.full);

    /** Upgrade a v2 project (blocks) to v3 (sections > columns > widgets). */
    const migrate = P => normalize(upgrade(P));
    const upgrade = P => {
        if (!P || typeof P !== 'object' || !Array.isArray(P.pages)) throw new Error('Not a project');
        if (+P.v === 3 || P.pages.some(pg => Array.isArray(pg.sections))) { P.v = 3; return P; }
        const s = P.site || {};
        P.site = { ...defaultsSite(), ...s, colors: { ...defaultsSite().colors, primary: hex(s.color) }, fonts: { ...defaultsSite().fonts, heading: s.font || 'Poppins' } };
        P.pages.forEach(pg => {
            pg.sections = (pg.blocks || []).filter(b => W[LEGACY_TYPES[b.type] || b.type]).map(b => {
                const type = LEGACY_TYPES[b.type] || b.type;
                const sec = section('1', [column([{ id: b.id, type, v: b.v || '', p: b.p, st: {}, adv: {} }])]);
                sec.bg = b.bg && b.bg !== 'auto' ? b.bg : (type === 'cta' ? 'brand' : 'auto');
                if (type === 'textblock' || type === 'imagetext') { const t = sec.cols[0].widgets[0].p; if (t.text && !/<p>/.test(t.text)) t.text = '<p>' + esc(t.text).replace(/\n/g, '<br>') + '</p>'; }
                return sec;
            });
            delete pg.blocks;
            pg.popup ||= defaultsPopup();
        });
        P.v = 3;
        return P;
    };
    const defaultsSite = () => ({
        name: 'Your business', logo: '', colors: { primary: '#4f46e5', secondary: '#0ea5e9', accent: '#f59e0b', text: '#0f172a', bg: '#ffffff' },
        fonts: { heading: 'Poppins', body: 'Inter', base: 17 }, theme: 'light', radius: 'round', buttons: 'pill', navBtn: 'Get in touch', navLink: { to: 'contact' },
        sticky: true, bare: false, backTop: false, announce: { on: false, text: 'Free delivery this week only!', link: { to: 'contact' } }, footer: '', social: {}, formAction: '', css: '', shop: defaultsShop(),
    });
    const defaultsPopup = () => ({ on: false, trigger: 'delay', delay: 6, title: 'Get 10% off your first order', text: 'Leave your details and we will send you the offer today.', btn: 'Claim my offer', link: { to: 'contact' }, image: '' });

    /* ================================================================ examples & templates */
    const EXAMPLES = {
        agency: ['Consultant / agency', 'Northwind Consulting', '#4f46e5', ['Growth consulting for small businesses', 'Grow your business with a clear, practical plan', 'We find what is holding your sales back and fix it with you — marketing, pricing and processes that actually work.', 'Book a free call'],
            [['📈', 'More customers', 'A marketing plan built around the channels your buyers use.'], ['💡', 'Better pricing', 'Package and price your services so they sell — and pay.'], ['⚙️', 'Simple systems', 'Free your time with tools and processes that run themselves.']]],
        dental: ['Dental clinic', 'BrightSmile Dental', '#0ea5e9', ['Family & cosmetic dentistry', 'A healthier, brighter smile — without the fear', 'Gentle check-ups, whitening and implants from a friendly team. New patients welcome.', 'Book a check-up'],
            [['🦷', 'Pain-free treatment', 'Modern tools and a calm approach.'], ['📅', 'Same-week appointments', 'Evening and Saturday slots.'], ['💳', 'Clear prices', 'You get the cost before we start.']]],
        restaurant: ['Restaurant / café', 'Olive & Ember', '#ea580c', ['Wood-fired kitchen · Open daily', 'Fresh, wood-fired food made to share', 'Seasonal plates, hand-stretched pizza and natural wines in the heart of the city.', 'Book a table'],
            [['🔥', 'Wood-fired everything', 'Our oven runs at 450°C.'], ['🥬', 'Local & seasonal', 'Menus change with the season.'], ['🎉', 'Private events', 'Parties for up to 40 guests.']]],
        course: ['Online course', 'Code in 30 Days', '#16a34a', ['Beginner-friendly · Learn at your pace', 'Learn to build websites in 30 days', 'Short daily lessons, real projects and a friendly community. No experience needed.', 'Join the course'],
            [['🎥', '60+ short lessons', 'Clear videos for phone or laptop.'], ['🛠️', '5 real projects', 'Finish with a portfolio.'], ['💬', 'Help when stuck', 'Community and live sessions.']]],
        realestate: ['Real estate', 'Keystone Homes', '#0f766e', ['Buy · Sell · Rent', 'Find the right home — or sell yours for more', 'Local agents who know every street. Free valuations and honest advice.', 'Get a free valuation'],
            [['🏡', 'Local experts', '15 years selling homes here.'], ['📸', 'Homes that stand out', 'Pro photos and video tours.'], ['🤝', 'Honest advice', 'Clear pricing and updates.']]],
        services: ['Home services', 'FixRight Plumbing', '#dc2626', ['24/7 emergency call-outs', 'Fast, reliable plumbing — fixed right the first time', 'Leaks, drains, boilers and bathrooms. Upfront prices and a 12-month guarantee.', 'Get a free quote'],
            [['⚡', 'Here in 60 minutes', 'Emergency call-outs day and night.'], ['💰', 'Upfront prices', 'You approve the price first.'], ['✅', '12-month guarantee', 'If it fails, we come back free.']]],
        salon: ['Hair & beauty salon', 'Velvet Hair Studio', '#be185d', ['Hair · Nails · Brows', 'Walk out feeling like the best version of you', 'Cuts, colour and treatments from stylists who listen. Book online in under a minute.', 'Book an appointment'],
            [['✂️', 'Expert stylists', 'Every stylist trained for 5+ years.'], ['🌿', 'Kind products', 'Vegan, cruelty-free colour.'], ['📅', 'Easy booking', 'Evening and Sunday slots.']]],
        fitness: ['Gym & personal trainer', 'Peak Fitness', '#ea580c', ['Personal training · Small classes', 'Get stronger, fitter and more confident — with a coach on your side', 'One-to-one training and small group classes for every level. Your first session is free.', 'Book a free session'],
            [['🏋️', 'Plans made for you', 'Built around your goals and time.'], ['👥', 'Small classes', 'Never more than 8 people.'], ['📈', 'Real progress', 'We check in and track results every month.']]],
        cleaning: ['Cleaning company', 'Sparkle Cleaning Co.', '#0891b2', ['Homes · Offices · End of tenancy', 'A spotless home, without lifting a finger', 'Trusted, insured cleaners who arrive on time and bring everything. Weekly, one-off or deep cleans.', 'Get a free quote'],
            [['🧽', 'Everything included', 'We bring products and equipment.'], ['🛡️', 'Insured & vetted', 'Background-checked cleaners.'], ['😊', 'Happiness promise', 'Not happy? We come back free.']]],
        accounting: ['Accountant / bookkeeper', 'Ledger & Co. Accountants', '#1e40af', ['Small business accounting', 'Less paperwork, lower tax bills, more time for your business', 'Bookkeeping, tax returns and payroll for sole traders and small companies — at a fixed monthly fee.', 'Book a free call'],
            [['📊', 'Fixed monthly fee', 'No surprise bills, ever.'], ['🧾', 'Tax done for you', 'Returns filed on time, every time.'], ['☎️', 'A real person', 'Your own accountant, a call away.']]],
    };
    const fill = (sec, { v, ...p }) => { const w = sec.cols[0].widgets[0]; if (v) w.v = v; Object.assign(w.p, p); return sec; }; // v = variant (layout)
    const newProject = key => {
        const [, name, color, hero, feats] = EXAMPLES[key] || EXAMPLES.agency;
        const site = defaultsSite(); site.name = name; site.colors.primary = color; site.navBtn = hero[3];
        const sections = [
            fill(sectionOf('hero'), { eyebrow: hero[0], title: hero[1], text: hero[2], btn: hero[3] }),
            fill(sectionOf('features'), { items: feats.map(([icon, title, text]) => ({ icon, title, text })) }),
            sectionOf('imagetext'), sectionOf('steps'), sectionOf('testimonials'), sectionOf('faq'), sectionOf('contact'),
        ];
        return { v: 3, site, pages: [{ id: uid(), slug: 'index', title: 'Home', menu: true, seo: { title: '', description: '' }, popup: defaultsPopup(), sections }] };
    };
    // Ready-made sections and full pages (the "Templates" library)
    const cols = (layout, ...lists) => section(layout, lists.map(l => column(l.map(t => (typeof t === 'string' ? widget(t) : t)))));
    const wp = (type, p) => { const w = widget(type); Object.assign(w.p, p); return w; };
    const TEMPLATES = {
        sections: {
            'intro-2col': ['About us — text & image', () => cols('2', [wp('heading', { text: 'About us', tag: 'h2' }), wp('text', { html: '<p>Tell visitors who you are, what you believe in and how you help. Keep it short and friendly.</p>' }), 'iconlist', wp('button', { text: 'Work with us' })], ['image'])],
            'services-3': ['Services — three icon boxes', () => { const s = cols('3', [wp('iconbox', { icon: 'i:rocket', title: 'Service one' })], [wp('iconbox', { icon: 'i:shield', title: 'Service two' })], [wp('iconbox', { icon: 'i:heart', title: 'Service three' })]); s.bg = 'alt'; return s; }],
            'counters-4': ['Numbers — four counters', () => cols('4', [wp('counter', { num: '500', suffix: '+', label: 'Clients' })], [wp('counter', { num: '12', suffix: '', label: 'Years' })], [wp('counter', { num: '98', suffix: '%', label: 'Happy customers' })], [wp('counter', { num: '24', suffix: '/7', label: 'Support' })])],
            'offer-countdown': ['Limited offer — countdown', () => { const s = cols('1', [wp('heading', { text: 'Limited-time offer ends in', tag: 'h2' }), 'countdown', wp('button', { text: 'Claim the offer', align: 'center' })]); s.bg = 'brand'; s.st._ = { d: { align: 'center' } }; return s; }],
            'skills': ['Why us — text & progress bars', () => cols('2', [wp('heading', { text: 'Why clients choose us' }), wp('text', { html: '<p>Share what you are best at and back it up with numbers.</p>' })], ['progress'])],
            'menu': ['Menu / price list — two columns', () => cols('2', [wp('heading', { text: 'Starters', tag: 'h3' }), 'pricelist'], [wp('heading', { text: 'Mains', tag: 'h3' }), 'pricelist'])],
            'visit': ['Visit us — hours, map & contact', () => cols('3', [wp('heading', { text: 'Opening hours', tag: 'h3' }), 'hours'], [wp('heading', { text: 'Find us', tag: 'h3' }), wp('text', { html: '<p>12 Main Street<br>Your City</p>' })], [wp('heading', { text: 'Follow us', tag: 'h3' }), 'social'])],
            'tabs-info': ['Details — tabs', () => cols('1', [wp('heading', { text: 'Everything you need to know' }), 'tabs'])],
            'reviews-slider': ['Reviews — slider', () => { const s = cols('1', [wp('heading', { text: 'What customers say' }), 'tcarousel']); s.st._ = { d: { align: 'center' } }; s.bg = 'alt'; return s; }],
            'flip-3': ['Services — three flip boxes', () => cols('3', ['flipbox'], [wp('flipbox', { icon: 'i:bolt' })], [wp('flipbox', { icon: 'i:award' })])],
        },
        pages: {
            about: ['About', () => [fill(sectionOf('hero'), { eyebrow: 'Our story', title: 'About us', text: 'Who we are, what we do and why we do it.', btn: 'Get in touch' }), TEMPLATES.sections['intro-2col'][1](), sectionOf('stats'), sectionOf('team'), sectionOf('cta')]],
            services: ['Services', () => [fill(sectionOf('hero'), { eyebrow: 'What we do', title: 'Our services', text: 'Everything we offer, explained simply.', btn: 'Get a quote' }), TEMPLATES.sections['services-3'][1](), sectionOf('steps'), sectionOf('pricing'), sectionOf('faq'), sectionOf('cta')]],
            pricing: ['Pricing', () => [fill(sectionOf('hero'), { eyebrow: 'Simple, fair prices', title: 'Pricing', text: 'Pick the plan that fits you. Upgrade or cancel any time.', btn: 'Talk to us' }), sectionOf('pricing'), sectionOf('faq'), sectionOf('cta')]],
            contact: ['Contact', () => [fill(sectionOf('hero'), { eyebrow: 'We reply the same day', title: 'Contact us', text: 'Call, message or send the form below.', btn: '' }), sectionOf('contact'), TEMPLATES.sections.visit[1](), sectionOf('map')]],
            sales: ['Sales page with countdown', () => [fill(sectionOf('hero'), { v: 'split', eyebrow: 'Limited offer', title: 'The offer headline that makes people stop', text: 'Explain the result customers get and why now is the time.', btn: 'Get it now' }), TEMPLATES.sections['offer-countdown'][1](), sectionOf('features'), TEMPLATES.sections['reviews-slider'][1](), sectionOf('pricing'), sectionOf('faq'), sectionOf('cta')]],
            menu: ['Menu', () => [fill(sectionOf('hero'), { eyebrow: 'Fresh every day', title: 'Our menu', text: 'Seasonal dishes made from scratch — ask us about vegan and gluten-free options.', btn: 'Book a table' }), TEMPLATES.sections.menu[1](), TEMPLATES.sections.visit[1](), sectionOf('cta')]],
            soon: ['Coming soon', () => { const s = cols('1', [wp('animhead', { before: 'Something', words: 'new\nexciting\nbig', after: 'is coming', tag: 'h1' }), wp('text', { html: '<p>We are launching soon. Leave your details and be the first to know.</p>' }), 'countdown', 'form']); s.st._ = { d: { align: 'center', minH: 640 } }; s.bg = 'soft'; return [s]; }],
        },
    };
    /* =========================================================== template library: 40 sections + 12 pages, grouped by kind.
       Built only from the widgets above, so every one can be edited like anything else on the page. [label, make, category] */
    const look = (s, bg, align) => { if (bg) s.bg = bg; if (align) s.st._ = { d: { align } }; return s; };
    const card = (widgets, o = {}) => { const c = column(widgets.map(t => (typeof t === 'string' ? widget(t) : t))); c.st._ = { d: { bgType: 'color', bg: 'g:bg', pt: 28, pr: 26, pb: 28, pl: 26, radius: 20, shadow: 'md', ...o } }; return c; };
    const head = (text, tag = 'h2') => wp('heading', { text, tag });
    const para = text => wp('text', { html: `<p>${text}</p>` });
    const ey = text => wp('heading', { text, tag: 'p' });
    const btn = (text, o = {}) => wp('button', { text, ...o });
    const ib = (icon, title, text, layout = 'top') => wp('iconbox', { icon: 'i:' + icon, title, text, layout });
    const list = (...items) => wp('iconlist', { items: items.map(text => ({ icon: 'i:check', text })) });
    const LIB = {
        // Heroes
        'hero-center': ['Hero — big centred headline', () => fill(sectionOf('hero'), { eyebrow: 'Trusted by 2,000+ customers', title: 'Grow faster with a website that works as hard as you do', text: 'A clear offer, proof people trust and one simple next step — everything a visitor needs to say yes.', btn: 'Get started', btn2: 'See how it works', link2: { to: 'url', val: '#' } }), 'Heroes'],
        'hero-split': ['Hero — text & image', () => fill(sectionOf('hero'), { v: 'split', eyebrow: 'New season', title: 'Made by hand, made to last', text: 'Small-batch products from people who care about every detail.', btn: 'Shop the collection', btn2: 'Our story', link2: { to: 'url', val: '#' } }), 'Heroes'],
        'hero-cover': ['Hero — photo background', () => fill(sectionOf('hero'), { v: 'cover', eyebrow: 'Open 7 days', title: 'Your table is waiting', text: 'Seasonal food, good wine and a warm welcome in the heart of town.', btn: 'Book a table' }), 'Heroes'],
        'hero-brand': ['Hero — bold colour band', () => look(fill(sectionOf('hero'), { eyebrow: 'Free 14-day trial', title: 'Run your whole business from one simple app', text: 'Bookings, invoices and reminders — set up in minutes, no training needed.', btn: 'Start free', btn2: 'Book a demo', link2: { to: 'url', val: '#' } }), 'brand'), 'Heroes'],
        'hero-words': ['Hero — rotating words', () => look(cols('1', [ey('Design · Build · Grow'), wp('animhead', { before: 'We build', words: 'websites\nonline stores\nmobile apps', after: 'that win you customers', tag: 'h1' }), para('A small team that plans, designs and builds with you — on time and on budget.'), btn('Start a project', { align: 'center', size: 'lg' })]), 'soft', 'center'), 'Heroes'],
        'hero-form': ['Hero — headline & sign-up form', () => { const s = section('2-1', [column([ey('Free guide'), head('The 10-minute checklist for a website that sells', 'h1'), para('Join 5,000+ owners who used it to get more calls and enquiries.'), list('Fix the 3 things that lose most visitors', 'Write a headline that converts', 'Add proof people trust')]), card([head('Get the checklist', 'h3'), 'form'])]); return look(s, 'alt'); }, 'Heroes'],
        // Features & services
        'features-6': ['Features — six icon boxes', () => look(cols('3', [ib('bolt', 'Fast set-up', 'Ready in minutes, not weeks.'), ib('shield', 'Secure', 'Your data is encrypted and backed up.')], [ib('chart', 'Clear reports', 'See what works at a glance.'), ib('phone', 'Works on phones', 'Looks great on every screen.')], [ib('chat', 'Real support', 'Talk to a person, not a bot.'), ib('heart', 'Loved by teams', 'Rated 4.9 by our customers.')]), '', 'center'), 'Features'],
        'features-left': ['Features — icons beside text', () => cols('2', [ib('rocket', 'Launch quickly', 'Start from a ready design and change only what you need.', 'left'), ib('target', 'Reach the right people', 'Built-in SEO so customers can find you.', 'left')], [ib('lock', 'Safe and private', 'Encrypted, backed up and always yours.', 'left'), ib('smile', 'Easy to use', 'No training needed — click and type.', 'left')]), 'Features'],
        'features-cards': ['Services — three cards with buttons', () => look(section('3', [card([ib('home', 'Residential', 'Homes, flats and extensions — done properly.'), btn('Learn more', { kind: 'ghost', size: 'sm' })]), card([ib('tool', 'Repairs', 'Fast fixes from people who turn up on time.'), btn('Learn more', { kind: 'ghost', size: 'sm' })]), card([ib('award', 'Commercial', 'Offices and shops, out of hours if needed.'), btn('Learn more', { kind: 'ghost', size: 'sm' })])]), 'alt'), 'Features'],
        'features-list': ['Benefits — headline & checklist', () => cols('2', [ey('Why us'), head('Everything you need, nothing you don’t'), para('We keep it simple so you can focus on your customers.')], [list('No contracts — cancel any time', 'Free set-up and training', 'Support 7 days a week', 'Your data, always exportable', 'Price locked for 12 months')]), 'Features'],
        'features-image': ['Feature — image & text', () => fill(sectionOf('imagetext'), { eyebrow: 'How it helps', title: 'Spend less time on admin, more on customers', text: '<p>Reminders, invoices and follow-ups go out on their own, so nothing slips through the cracks.</p>', btn: 'See all features' }), 'Features'],
        'features-image-r': ['Feature — text & image (right)', () => fill(sectionOf('imagetext'), { v: 'right', eyebrow: 'Made for teams', title: 'Everyone on the same page', text: '<p>Share jobs, notes and photos in one place — from the office or on the road.</p>', btn: 'Learn more' }), 'Features'],
        'flip-4': ['Services — four flip boxes', () => look(cols('4', [wp('flipbox', { icon: 'i:camera', title: 'Photography', backText: 'Weddings, portraits and products.' })], [wp('flipbox', { icon: 'i:play', title: 'Video', backText: 'Ads, events and social clips.' })], [wp('flipbox', { icon: 'i:globe', title: 'Websites', backText: 'Fast sites that bring enquiries.' })], [wp('flipbox', { icon: 'i:chart', title: 'Marketing', backText: 'Ads and SEO that pay for themselves.' })]), 'soft'), 'Features'],
        // About & story
        'about-story': ['About — our story', () => fill(sectionOf('imagetext'), { eyebrow: 'Our story', title: 'Started in a garage, trusted by thousands', text: '<p>We began in 2014 with one van and a simple promise: do the job right, and leave the place cleaner than we found it.</p><p>Ten years on, the promise hasn’t changed.</p>', btn: 'Meet the team' }), 'About'],
        'about-values': ['About — our values', () => look(cols('3', [ib('heart', 'Care', 'We treat every job like it’s for our own family.')], [ib('shield', 'Honesty', 'Clear prices, no surprises, no pressure.')], [ib('leaf', 'Responsibility', 'Local suppliers and as little waste as possible.')]), 'alt', 'center'), 'About'],
        'about-mission': ['About — mission statement', () => look(cols('1', [ey('Our mission'), head('To make great design simple and affordable for every small business'), para('We believe a good website shouldn’t cost a fortune or take months.')]), 'brand', 'center'), 'About'],
        'about-stats': ['About — text & numbers', () => cols('2', [ey('Who we are'), head('A team of 12 who love what they do'), para('Designers, developers and writers working together from one studio.')], [wp('counter', { num: '320', suffix: '+', label: 'Projects delivered' }), wp('counter', { num: '12', suffix: '', label: 'Years in business' }), wp('counter', { num: '98', suffix: '%', label: 'Clients who return' })]), 'About'],
        // Numbers & proof
        'stats-band': ['Numbers — colour band', () => look(sectionOf('stats'), 'brand'), 'Proof'],
        'logos': ['Logos — trusted by', () => fill(sectionOf('logos'), { title: 'Trusted by teams at' }), 'Proof'],
        'rating-strip': ['Proof — rating & quote', () => look(cols('2', [wp('rating', { stars: 4.9, label: 'Rated 4.9 from 1,200+ reviews' }), head('“The best decision we made this year.”', 'h3'), para('— Sarah, owner of Bloom Florist')], [list('Free quotes within 24 hours', 'Fully insured and certified', '12-month guarantee on all work')]), 'soft'), 'Proof'],
        // Testimonials
        'testimonials-3': ['Reviews — three cards', () => look(sectionOf('testimonials'), 'alt'), 'Reviews'],
        'testimonial-big': ['Review — one big quote', () => look(cols('1', [wp('rating', { stars: 5, label: '' }), head('“They rebuilt our website in three weeks and enquiries doubled the next month.”'), para('James Carter — Director, Carter & Co')]), 'soft', 'center'), 'Reviews'],
        'testimonials-grid': ['Reviews — quote grid', () => cols('2', [wp('testimonial', { quote: 'Friendly, fast and fair on price. Highly recommended.', name: 'Aisha K.', role: 'Homeowner' }), wp('testimonial', { quote: 'They explained everything and stuck to the plan.', name: 'Tom R.', role: 'Shop owner' })], [wp('testimonial', { quote: 'The best service we have had in years.', name: 'Maria L.', role: 'Restaurant owner' }), wp('testimonial', { quote: 'Quick replies and great results. Thank you!', name: 'David P.', role: 'Clinic manager' })]), 'Reviews'],
        // Pricing
        'pricing-3': ['Pricing — three plans', () => look(fill(sectionOf('pricing'), { title: 'Plans for every size', intro: 'Start small and upgrade any time. No hidden fees.' }), 'alt'), 'Pricing'],
        'pricing-list': ['Pricing — price list', () => cols('2', [head('Treatments', 'h3'), wp('pricelist', { items: [{ name: 'Consultation', desc: '30 minutes', price: '$40' }, { name: 'Full treatment', desc: '60 minutes', price: '$85' }, { name: 'Follow-up', desc: '20 minutes', price: '$30' }] })], [head('Packages', 'h3'), wp('pricelist', { items: [{ name: 'Starter pack', desc: '3 sessions', price: '$220' }, { name: 'Full course', desc: '6 sessions', price: '$420' }, { name: 'Gift card', desc: 'Any amount', price: 'From $25' }] })]), 'Pricing'],
        'pricing-single': ['Pricing — one offer', () => look(section('1-2', [column([ey('Simple pricing'), head('One plan. Everything included.'), para('No tiers, no add-ons, no surprises.')]), card([head('$29 / month', 'h3'), list('Unlimited pages', 'Your own domain', 'Email support', 'Cancel any time'), btn('Start free trial', { align: 'full' })])]), 'soft'), 'Pricing'],
        // Team
        'team-4': ['Team — four people', () => fill(sectionOf('team'), { title: 'The people behind it', items: [{ photo: '', name: 'Alex Morgan', role: 'Founder' }, { photo: '', name: 'Priya Shah', role: 'Design lead' }, { photo: '', name: 'Sam Lee', role: 'Developer' }, { photo: '', name: 'Jo Brown', role: 'Support' }] }), 'Team'],
        'team-join': ['Team — we’re hiring', () => look(cols('2', [ey('Careers'), head('Join a small team that ships big things'), para('Remote-friendly, flexible hours and real ownership of your work.')], [list('Product designer — remote', 'Frontend developer — hybrid', 'Customer success — London'), btn('See open roles')]), 'alt'), 'Team'],
        // Gallery & work
        'gallery-6': ['Gallery — six photos', () => fill(sectionOf('gallery'), { title: 'Recent work', items: Array.from({ length: 6 }, () => ({ src: '', caption: '' })) }), 'Gallery'],
        'gallery-slider': ['Gallery — slider', () => cols('1', [head('Take a look inside'), wp('carousel', { per: 2, auto: true })]), 'Gallery'],
        'before-after': ['Gallery — before & after', () => look(cols('2', [ey('Real results'), head('See the difference'), para('Drag the slider to compare. Every project is photographed before and after.'), btn('Get a free quote')], ['beforeafter']), 'soft'), 'Gallery'],
        'video-feature': ['Video — watch how it works', () => look(fill(sectionOf('video'), { title: 'See it in 90 seconds', text: 'A quick tour of everything you can do.' }), 'alt'), 'Gallery'],
        // Process & FAQ
        'steps-3': ['Process — three steps', () => fill(sectionOf('steps'), { title: 'How it works', intro: 'Three simple steps from first call to finished job.' }), 'Process'],
        'faq-2col': ['FAQ — questions & contact', () => section('1-2', [column([ey('FAQ'), head('Questions people ask'), para('Can’t find your answer? We reply within a few hours.'), btn('Ask us anything', { kind: 'ghost' })]), column([wp('accordion', { items: [{ title: 'How much does it cost?', html: '<p>Most projects cost between $500 and $2,000 — you get a fixed price before we start.</p>' }, { title: 'How long does it take?', html: '<p>Usually 1–3 weeks, depending on size.</p>' }, { title: 'Do you offer a guarantee?', html: '<p>Yes — 12 months on all work.</p>' }, { title: 'Can I pay in stages?', html: '<p>Yes, you pay per milestone.</p>' }] })])]), 'Process'],
        'tabs-features': ['Details — tabs', () => look(cols('1', [head('Everything in one place'), wp('tabs', { items: [{ title: 'For owners', html: '<p>See bookings, sales and reviews on one screen.</p>' }, { title: 'For staff', html: '<p>Clear schedules and tasks on any phone.</p>' }, { title: 'For customers', html: '<p>Book, pay and get reminders without calling.</p>' }] })]), 'alt', 'center'), 'Process'],
        // Calls to action & offers
        'cta-brand': ['Call to action — colour band', () => look(fill(sectionOf('cta'), { title: 'Ready to get started?', text: 'Free quote in 24 hours. No obligation.', btn: 'Get my free quote' }), 'brand'), 'Calls to action'],
        'cta-dark': ['Call to action — dark', () => look(cols('1', [head('Let’s build something great together'), para('Tell us about your project — we’ll reply the same day.'), btn('Start a project', { align: 'center', size: 'lg', kind: 'light' })]), 'dark', 'center'), 'Calls to action'],
        'cta-split': ['Call to action — text & buttons', () => look(cols('2-1', [head('Book your free consultation'), para('A 20-minute call to plan your next step — no sales pitch.')], [btn('Book a call', { align: 'full' }), btn('WhatsApp us', { align: 'full', kind: 'ghost' })]), 'soft'), 'Calls to action'],
        'offer-banner': ['Offer — announcement banner', () => look(cols('1', [wp('notice', { kind: 'success', title: 'Spring offer: 20% off all services', text: 'Book before 31 May and save — mention this website when you call.' })]), 'plain'), 'Calls to action'],
        // Contact
        'contact-split': ['Contact — details & form', () => look(sectionOf('contact'), 'alt'), 'Contact'],
        'contact-cards': ['Contact — three ways to reach us', () => look(cols('3', [ib('phone', 'Call us', '+1 555 010 2030 · Mon–Fri 9–6')], [ib('mail', 'Email', 'hello@yourbusiness.com — we reply same day')], [ib('pin', 'Visit', '12 Main Street, Your City')]), '', 'center'), 'Contact'],
        'contact-hours': ['Contact — hours & map', () => cols('2', [head('Opening hours', 'h3'), 'hours', btn('Get directions', { kind: 'ghost' })], [wp('heading', { text: 'Where to find us', tag: 'h3' }), para('12 Main Street, Your City — free parking behind the building.'), 'social']), 'Contact'],
    };
    // the first ten templates get a group too
    Object.entries({ 'intro-2col': 'About', 'services-3': 'Features', 'counters-4': 'Proof', 'offer-countdown': 'Calls to action', skills: 'About', menu: 'Pricing', visit: 'Contact', 'tabs-info': 'Process', 'reviews-slider': 'Reviews', 'flip-3': 'Features' })
        .forEach(([k, c]) => { if (TEMPLATES.sections[k]) TEMPLATES.sections[k][2] = c; });
    Object.keys(TEMPLATES.pages).forEach(k => { TEMPLATES.pages[k][2] ||= 'Inner pages'; });
    Object.entries(LIB).forEach(([k, v]) => { TEMPLATES.sections[k] = v; });
    const S = k => TEMPLATES.sections[k][1]();
    const H = (o, v) => fill(sectionOf('hero'), { ...(v ? { v } : {}), ...o });
    Object.assign(TEMPLATES.pages, {
        'home-agency': ['Home — agency / consultant', () => [H({ eyebrow: 'Strategy · Design · Growth', title: 'We help small businesses grow online', text: 'Websites, ads and SEO that bring real enquiries — not just traffic.', btn: 'Book a free call', btn2: 'See our work', link2: { to: 'url', val: '#' } }, 'split'), S('logos'), S('features-6'), S('about-stats'), S('testimonials-3'), S('cta-brand')], 'Business'],
        'home-restaurant': ['Home — restaurant / café', () => [H({ eyebrow: 'Wood-fired · Open daily', title: 'Fresh food, good wine, warm welcome', text: 'Seasonal plates and hand-stretched pizza in the heart of town.', btn: 'Book a table', btn2: 'See the menu', link2: { to: 'url', val: '#' } }, 'cover'), S('about-story'), S('pricing-list'), S('gallery-6'), S('testimonial-big'), S('contact-hours')], 'Food & drink'],
        'home-clinic': ['Home — clinic / dentist', () => [H({ eyebrow: 'New patients welcome', title: 'Gentle care for the whole family', text: 'Check-ups, whitening and implants from a friendly team.', btn: 'Book an appointment' }, 'split'), S('features-cards'), S('about-values'), S('pricing-list'), S('faq-2col'), S('contact-split')], 'Health'],
        'home-saas': ['Home — app / software', () => [S('hero-brand'), S('logos'), S('features-left'), S('features-image'), S('tabs-features'), S('pricing-3'), S('faq-2col'), S('cta-dark')], 'Tech'],
        'home-realestate': ['Home — real estate', () => [H({ eyebrow: 'Local experts since 2008', title: 'Find the home that fits your life', text: 'Buy, sell or rent with an agent who knows every street.', btn: 'Book a valuation' }, 'cover'), S('stats-band'), S('gallery-6'), S('features-image-r'), S('testimonials-grid'), S('cta-split')], 'Business'],
        'home-gym': ['Home — gym / fitness', () => [H({ eyebrow: 'First week free', title: 'Stronger every week', text: 'Classes, coaching and a community that keeps you going.', btn: 'Start free week' }, 'cover'), S('features-6'), S('before-after'), S('pricing-3'), S('team-4'), S('cta-brand')], 'Health'],
        'home-salon': ['Home — salon / beauty', () => [H({ eyebrow: 'Hair · Nails · Skin', title: 'Feel your best', text: 'An award-winning team in a calm, friendly space.', btn: 'Book now' }, 'split'), S('flip-4'), S('pricing-list'), S('gallery-slider'), S('testimonial-big'), S('contact-hours')], 'Health'],
        'home-law': ['Home — law firm / advisor', () => [H({ eyebrow: 'Clear advice, fixed fees', title: 'Legal help you can understand', text: 'Family, property and business law — explained in plain English.', btn: 'Free 15-minute call' }), S('features-left'), S('about-mission'), S('steps-3'), S('testimonials-3'), S('faq-2col'), S('contact-split')], 'Business'],
        'home-trades': ['Home — builder / trades', () => [H({ eyebrow: 'Free quotes · Fully insured', title: 'Quality work, on time, on budget', text: 'Extensions, repairs and renovations by a local team you can trust.', btn: 'Get a free quote' }, 'split'), S('rating-strip'), S('features-cards'), S('before-after'), S('steps-3'), S('cta-brand')], 'Business'],
        'home-course': ['Home — online course', () => [S('hero-form'), S('features-list'), S('video-feature'), S('testimonials-grid'), S('pricing-single'), S('faq-2col')], 'Education'],
        'home-event': ['Home — conference / event', () => [S('hero-brand'), S('offer-banner'), S('team-4'), S('tabs-features'), S('pricing-3'), S('contact-cards')], 'Events'],
        'home-charity': ['Home — charity / non-profit', () => [H({ eyebrow: 'Together since 2012', title: 'Every child deserves a safe place to learn', text: 'Your support builds classrooms, trains teachers and feeds families.', btn: 'Donate today', btn2: 'Volunteer', link2: { to: 'url', val: '#' } }, 'cover'), S('about-mission'), S('stats-band'), S('features-image'), S('testimonial-big'), S('cta-dark')], 'Non-profit'],
    });
    /* Funnel templates: each step is a page with a type, an optional "next" step and an optional payment link. */
    const optinCard = (title, btn, extra = []) => { const c = column([wp('heading', { text: title, tag: 'h3' }), ...extra, wp('optin', { btn })]); c.st._ = { d: { bgType: 'color', bg: 'g:bg', pt: 30, pr: 30, pb: 30, pl: 30, radius: 22, shadow: 'lg' } }; return c; };
    const thanks = (title, text, btnText, extra = []) => { const s = section('1', [column([wp('heading', { text: title, tag: 'h1' }), wp('text', { html: `<p>${text}</p>` }), ...extra, ...(btnText ? [wp('button', { text: btnText, align: 'center', link: { to: 'url', val: '' } })] : [])])]); s.st._ = { d: { align: 'center', minH: 520 } }; s.bg = 'soft'; return s; };
    const step = (title, slug, type, sections, extra = {}) => ({ id: uid(), slug, title, type, menu: false, next: '', pay: '', ab: { on: false }, sectionsB: null, seo: { title: '', description: '' }, popup: defaultsPopup(), sections, ...extra });
    const STEP_TYPES = { optin: 'Opt-in', sales: 'Sales page', checkout: 'Order / checkout', upsell: 'Upsell', downsell: 'Downsell', webinar: 'Webinar sign-up', thankyou: 'Thank you', custom: 'Custom page' };
    const FUNNELS = {
        leadmagnet: ['Lead magnet (free guide)', () => [
            step('Opt-in', 'index', 'optin', [(() => { const s = section('2-1', [column([wp('heading', { text: 'Get the free guide: 7 ways to double your leads', tag: 'h1' }), wp('text', { html: '<p>A short, practical PDF you can use today. Join 5,000+ business owners who already read it.</p>' }), wp('iconlist', { items: [{ icon: 'i:check', text: 'The 3-step offer formula' }, { icon: 'i:check', text: 'Copy-and-paste email templates' }, { icon: 'i:check', text: 'A 30-day action plan' }] })]), optinCard('Send me the guide', 'Yes, send it to me!')]); s.bg = 'soft'; s.st._ = { d: { minH: 620 } }; return s; })(), sectionOf('testimonials')]),
            step('Thank you', 'thank-you', 'thankyou', [thanks('🎉 Check your inbox!', 'Your free guide is on its way. While you wait, here is something that will help even more.', 'Book a free call')]),
        ]],
        webinar: ['Webinar registration', () => [
            step('Register', 'index', 'webinar', [(() => { const s = section('2', [column([wp('heading', { text: 'FREE live training: How to get your first 100 customers', tag: 'h1' }), wp('text', { html: '<p>Join us live on Thursday at 7 pm. Seats are limited.</p>' }), wp('countdown', {})]), optinCard('Save my seat', 'Register for free')]); s.bg = 'soft'; s.st._ = { d: { minH: 600 } }; return s; })(), sectionOf('faq')]),
            step('Confirmed', 'confirmed', 'thankyou', [thanks('✅ You are registered!', 'We sent the link to your email. Add it to your calendar so you don\'t miss it.', 'Add to my calendar', [wp('video1', { url: '' })])]),
        ]],
        launch: ['Product launch (sales page)', () => [
            step('Sales page', 'index', 'sales', [fill(sectionOf('hero'), { v: 'split', eyebrow: 'New', title: 'The product headline that sells the result', text: 'Explain the transformation customers get — and why now.', btn: 'Get it now', link: { to: 'pay' } }), sectionOf('features'), sectionOf('testimonials'), (() => { const s = section('1', [column([wp('heading', { text: 'Get instant access today', tag: 'h2' }), wp('paybtn', {})])]); s.st._ = { d: { align: 'center' } }; s.bg = 'soft'; return s; })(), sectionOf('faq')]),
            step('Thank you', 'thank-you', 'thankyou', [thanks('🎉 Thank you for your order!', 'Your receipt and access details are on their way to your email.', '')]),
        ]],
        tripwire: ['Low-price offer + upsell', () => [
            step('Offer', 'index', 'sales', [fill(sectionOf('hero'), { eyebrow: 'Today only', title: 'Get the starter kit for just $7', text: 'Everything you need to get started — instant download.', btn: 'Get it for $7', link: { to: 'pay' } }), sectionOf('features'), sectionOf('faq')]),
            step('Special upgrade', 'upgrade', 'upsell', [(() => { const s = section('1', [column([wp('heading', { text: 'Wait! Add the complete course for $47', tag: 'h1' }), wp('text', { html: '<p>One-time offer: upgrade your order and get everything you need to go further — only on this page.</p>' }), wp('paybtn', { text: 'Yes, add it to my order', no: 'No thanks, I\'ll skip this offer' })])]); s.st._ = { d: { align: 'center', minH: 560 } }; s.bg = 'soft'; return s; })()]),
            step('Thank you', 'thank-you', 'thankyou', [thanks('🎉 You\'re all set!', 'Check your email for your download links.', '')]),
        ]],
        consult: ['Free consultation booking', () => [
            step('Book a call', 'index', 'optin', [(() => { const s = section('2', [column([wp('heading', { text: 'Book your free 30-minute strategy call', tag: 'h1' }), wp('text', { html: '<p>Tell us about your goals and we will show you the fastest way to reach them. No pressure, no cost.</p>' }), wp('iconlist', {})]), optinCard('Request my call', 'Book my free call', [])]); s.cols[1].widgets[1].p.phone = true; s.bg = 'soft'; s.st._ = { d: { minH: 600 } }; return s; })(), sectionOf('testimonials')]),
            step('Pick a time', 'thank-you', 'thankyou', [thanks('Almost done — pick a time', 'Choose a time that suits you in our calendar. We will call you then.', 'Open the calendar')]),
        ]],
    };
    const newFunnel = key => {
        const [, steps] = FUNNELS[key] || FUNNELS.leadmagnet;
        const site = defaultsSite(); site.name = 'My funnel'; site.navBtn = ''; site.sticky = false;
        return { v: 3, kind: 'funnel', site, pages: steps() };
    };
    const newStep = (title, type, n) => step(title, slugify(title) || 'step-' + n, type, type === 'thankyou' ? [thanks('Thank you!', 'Here is what happens next.', '')]
        : type === 'upsell' || type === 'downsell' ? [(() => { const s = section('1', [column([wp('heading', { text: 'Add this to your order?', tag: 'h1' }), wp('text', { html: '<p>Describe the extra offer.</p>' }), wp('paybtn', { text: 'Yes, add it', no: 'No thanks' })])]); s.st._ = { d: { align: 'center', minH: 520 } }; return s; })()]
        : type === 'optin' || type === 'webinar' ? [(() => { const s = section('2', [column([wp('heading', { text: 'Your headline', tag: 'h1' }), wp('text', {})]), optinCard('Get access', 'Sign me up')]); s.bg = 'soft'; return s; })()]
        : [fill(sectionOf('hero'), { title, btn: 'Get it now', link: { to: type === 'sales' || type === 'checkout' ? 'pay' : 'next' } })]);
    const newPage = (title, n, tpl) => {
        const sections = tpl && TEMPLATES.pages[tpl] ? TEMPLATES.pages[tpl][1]() : [fill(sectionOf('hero'), { eyebrow: '', title, text: 'Introduce this page in a sentence or two.' }), sectionOf('textblock'), sectionOf('cta')];
        return { id: uid(), slug: slugify(title) || 'page-' + n, title, menu: true, seo: { title: '', description: '' }, popup: defaultsPopup(), sections };
    };

    /* Builder kits: the same editor with its own starting projects — /website-builder, /portfolio-builder,
       /link-in-bio-builder and /coming-soon-page-builder. Each example is [label, () => project]. */
    const kitSite = (name, color, o = {}) => { const s = Object.assign(defaultsSite(), { name }, o); s.colors.primary = color; return s; };
    const homePage = sections => ({ id: uid(), slug: 'index', title: 'Home', menu: true, seo: { title: '', description: '' }, popup: defaultsPopup(), sections });
    const withPages = (P, list) => { list.forEach(([title, tpl]) => P.pages.push(newPage(title, P.pages.length, tpl))); return P; };
    const styled = (s, st, bg) => { s.st._ = { d: st }; if (bg) s.bg = bg; return s; };
    /** A portfolio: hero, work gallery (#work), about, one extra section, reviews and contact. */
    const folio = (name, color, [eyebrow, title, text, btn], captions, about, extra) => {
        const work = fill(sectionOf('gallery'), { title: 'Selected work', items: captions.map(caption => ({ src: '', caption })) }); work.adv = { id: 'work' };
        const me = fill(sectionOf('imagetext'), { eyebrow: 'About me', title: about[0], text: `<p>${about[1]}</p>`, btn: 'Work with me' });
        return { v: 3, site: kitSite(name, color, { navBtn: btn }), pages: [homePage([
            fill(sectionOf('hero'), { v: 'split', eyebrow, title, text, btn, btn2: 'See my work', link2: { to: 'url', val: '#work' } }),
            work, me, ...extra(), sectionOf('testimonials'), sectionOf('contact'),
        ])] };
    };
    /** A link-in-bio page: photo, name, short bio, big link buttons and social icons — no header menu. */
    const bio = (name, color, text, links, nets) => {
        const avatar = wp('image', { alt: name });
        avatar.st = { _: { d: { maxw: 112 } } }; // a round profile photo, also while it is still empty
        avatar.adv = { css: 'selector{width:100%}selector .img-w,selector .pic-empty{border-radius:50%;aspect-ratio:1;min-height:0;padding:0;font-size:.72rem}selector img{height:100%;object-fit:cover}' };
        const head = wp('heading', { text: name, tag: 'h1' }); head.st = { _: { d: { size: 28, mb: 0 } } };
        const buttons = links.map(([label, to = 'url']) => wp('button', { text: label, link: { to, val: '' }, kind: 'light', size: 'lg', align: 'full' }));
        const col = column([avatar, head, wp('text', { html: `<p>${text}</p>` }), ...buttons, wp('social', { items: nets.map(net => ({ net, url: '' })) })]);
        col.st._ = { d: { maxw: 480, align: 'center' } };
        const s = styled(section('1', [col]), { minH: 760, align: 'center' }, 'brand');
        return { v: 3, kind: 'links', site: kitSite(name, color, { bare: true, sticky: false, navBtn: '' }), pages: [homePage([s])] };
    };
    /** An event page: hero with the date, countdown, story, schedule, venue, RSVP and FAQ. */
    /** A date some days from now: [value for the countdown, "Saturday 14 June 2025"]. Templates never show a past date. */
    const evDate = (days, hour) => {
        const d = new Date(Date.now() + days * 864e5); d.setHours(hour, 0, 0, 0);
        const pad = n => String(n).padStart(2, '0');
        return [`${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(hour)}:00`, d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })];
    };
    const eventPage = (name, color, o) => {
        const [date, day] = evDate(o.days, o.hour); o = { ...o, date, when: `${day} · ${o.time}\n${o.place}` };
        const site = kitSite(name, color, { navBtn: 'RSVP', navLink: { to: 'url', val: '#rsvp' }, sticky: true, ...(o.site || {}) });
        const hero = fill(sectionOf('hero'), { v: o.cover ? 'cover' : 'center', eyebrow: o.eyebrow, title: o.title, text: o.when, btn: 'RSVP', link: { to: 'url', val: '#rsvp' }, btn2: o.btn2 || '', link2: { to: 'url', val: o.btn2 ? '#details' : '' } });
        const count = styled(section('1', [column([wp('heading', { text: o.countTitle || 'Counting down', tag: 'h2' }), wp('countdown', { date: o.date, done: o.done || 'Today is the day!' })])]), { align: 'center' }, 'soft');
        const story = fill(sectionOf('imagetext'), { eyebrow: o.storyEyebrow, title: o.storyTitle, text: `<p>${o.story}</p>` }); story.adv = { id: 'details' };
        const plan = fill(sectionOf('steps'), { title: o.planTitle || 'The day', items: o.plan.map(([title, text]) => ({ title, text })) });
        const venue = section('2', [column([wp('heading', { text: 'Where', tag: 'h2' }), wp('text', { html: `<p><b>${o.venue}</b><br>${o.address}</p>` }), wp('button', { text: 'Get directions', link: { to: 'url', val: '' }, kind: 'ghost' })]), column([wp('heading', { text: 'When', tag: 'h2' }), wp('text', { html: `<p>${esc(o.when).replace('\n', '<br>')}</p>` }), ...(o.extra || [])])]);
        const rsvp = styled(section('1', [column([wp('heading', { text: o.rsvpTitle || 'Will you join us?', tag: 'h2' }), wp('text', { html: `<p>${o.rsvpText || 'Please let us know by replying below.'}</p>` }), wp('rsvp', o.rsvp || {})])]), { align: 'center' }, 'alt');
        rsvp.cols[0].st._ = { d: { maxw: 620 } }; rsvp.adv = { id: 'rsvp' };
        const faq = fill(sectionOf('faq'), { title: 'Good to know', items: o.faq.map(([q, a]) => ({ q, a })) });
        return { v: 3, kind: 'event', site, pages: [homePage([hero, count, story, plan, venue, ...(o.more || []), rsvp, faq])] };
    };
    /** A menu page: hero, quick order buttons, menu sections (two price lists each), specials, hours & map. */
    const menuPage = (name, color, o) => {
        const site = kitSite(name, color, { navBtn: 'See the menu', navLink: { to: 'url', val: '#menu' }, ...(o.site || {}) });
        const hero = fill(sectionOf('hero'), { v: 'cover', eyebrow: o.eyebrow, title: o.title, text: o.text, btn: 'See the menu', link: { to: 'url', val: '#menu' }, btn2: o.order ? 'Order on WhatsApp' : 'Call to book', link2: o.order ? { to: 'whatsapp', val: '' } : { to: 'phone', val: '' } });
        const quick = styled(section('3', [
            column([wp('iconbox', { icon: 'i:clock', title: o.hoursShort, text: 'Open today', layout: 'left' })]),
            column([wp('iconbox', { icon: 'i:pin', title: o.area, text: 'Address & hours below', layout: 'left' })]),
            column([wp('iconbox', { icon: o.order ? 'i:truck' : 'i:phone', title: o.order ? 'Delivery & pick-up' : 'Book a table', text: o.order ? 'Order on WhatsApp or call us' : 'Call us or send a message', layout: 'left' })]),
        ]), { pt: 28, pb: 28 }, 'soft');
        const cat = ([title, left, right], k) => {
            const list = items => wp('pricelist', { items: items.map(([name, price, desc = '']) => ({ name, price, desc })) });
            const s = section('2', [column([wp('heading', { text: left[0], tag: 'h3' }), list(left[1])]), column([wp('heading', { text: right[0], tag: 'h3' }), list(right[1])])]);
            const head = styled(section('1', [column([wp('heading', { text: title, tag: 'h2' })])]), { align: 'center', pb: 0 }, k % 2 ? 'alt' : 'plain');
            if (!k) head.adv = { id: 'menu' };
            s.bg = k % 2 ? 'alt' : 'plain'; s.st._ = { d: { pt: 24 } };
            return [head, s];
        };
        const special = styled(section('1', [column([wp('notice', { kind: 'success', title: o.special[0], text: `<p>${o.special[1]}</p>` })])]), { pt: 32, pb: 32 });
        const visit = TEMPLATES.sections.visit[1]();
        visit.cols[0].widgets[1].p.items = o.hours.map(([day, time]) => ({ day, time }));
        visit.cols[1].widgets[1].p.html = `<p>${o.address}</p>`;
        return { v: 3, kind: 'menu', site, pages: [homePage([hero, quick, ...o.menu.flatMap(cat), special, ...(o.more || []), visit])] };
    };
    const soonSite = (name, color, o = {}) => kitSite(name, color, { bare: true, sticky: false, navBtn: '', ...o });
    /** An online store: hero, perks, the products (#shop), story, reviews, FAQ and contact — plus a delivery & returns page. */
    const storePage = (name, color, o) => {
        const site = kitSite(name, color, { navBtn: '', ...(o.site || {}) });
        site.shop = { ...defaultsShop(), ...(o.shop || {}) };
        const hero = fill(sectionOf('hero'), { v: 'split', eyebrow: o.eyebrow, title: o.title, text: o.text, btn: 'Shop now', link: { to: 'url', val: '#shop' }, btn2: '', image: '' });
        const perks = styled(section('3', o.perks.map(([ic, title, text]) => column([wp('iconbox', { icon: 'i:' + ic, title, text, layout: 'left' })]))), { pt: 28, pb: 28 }, 'soft');
        const shop = sectionOf('products');
        Object.assign(shop.cols[0].widgets[0].p, { title: o.shopTitle, intro: o.shopIntro || '', items: o.products.map(([pname, price, desc, extra = {}]) => ({ pid: uid(), name: pname, price, old: '', desc, long: '', cat: '', options: '', badge: '', image: '', images: [], sold: false, ...extra })) });
        shop.adv = { id: 'shop' };
        const story = fill(sectionOf('imagetext'), { eyebrow: o.story[0], title: o.story[1], text: `<p>${o.story[2]}</p>`, btn: '', image: '' });
        const reviews = styled(fill(sectionOf('testimonials'), { title: 'Happy customers', items: o.reviews.map(([quote, rname]) => ({ quote, name: rname, role: 'Customer' })) }), {}, 'alt');
        const faq = fill(sectionOf('faq'), { title: 'Good to know', items: o.faq.map(([q, a]) => ({ q, a })) });
        const contact = fill(sectionOf('contact'), { title: 'Questions? We’re here to help', text: 'Message us about sizes, gifts or big orders — we reply the same day.', form: false });
        const policy = { id: uid(), slug: 'delivery', title: 'Delivery & returns', menu: true, seo: { title: '', description: '' }, popup: defaultsPopup(), sections: [fill(sectionOf('textblock'), { title: 'Delivery & returns', text: o.policy }), contact] };
        return { v: 3, kind: 'store', site, pages: [homePage([hero, perks, shop, story, reviews, faq, fill(sectionOf('contact'), { ...contact.cols[0].widgets[0].p })]), policy] };
    };
    const KITS = {
        website: { label: 'website', examples: {
            business: ['Consultant / agency', () => withPages(newProject('agency'), [['About', 'about'], ['Services', 'services'], ['Contact', 'contact']])],
            clinic: ['Clinic / dentist', () => withPages(newProject('dental'), [['Treatments', 'services'], ['About', 'about'], ['Contact', 'contact']])],
            restaurant: ['Restaurant / café', () => withPages(newProject('restaurant'), [['Menu', 'menu'], ['About', 'about'], ['Contact', 'contact']])],
            trades: ['Home services', () => withPages(newProject('services'), [['Services', 'services'], ['Pricing', 'pricing'], ['Contact', 'contact']])],
            realestate: ['Real estate', () => withPages(newProject('realestate'), [['About', 'about'], ['Services', 'services'], ['Contact', 'contact']])],
            salon: ['Hair & beauty salon', () => withPages(newProject('salon'), [['Services', 'services'], ['Prices', 'pricing'], ['Contact', 'contact']])],
            fitness: ['Gym & personal trainer', () => withPages(newProject('fitness'), [['Classes', 'services'], ['Memberships', 'pricing'], ['Contact', 'contact']])],
            cleaning: ['Cleaning company', () => withPages(newProject('cleaning'), [['Services', 'services'], ['Prices', 'pricing'], ['Contact', 'contact']])],
            accounting: ['Accountant / bookkeeper', () => withPages(newProject('accounting'), [['Services', 'services'], ['About', 'about'], ['Contact', 'contact']])],
        } },
        portfolio: { label: 'portfolio', examples: {
            photographer: ['Photographer', () => folio('Maya Lens Photography', '#111827', ['Wedding & portrait photographer', 'Photos you will look at for the rest of your life', 'Natural, relaxed wedding and family photography in and around the city.', 'Check my dates'],
                ['Anna & Tom — garden wedding', 'Family session at the beach', 'Studio portrait', 'Engagement shoot', 'City hall wedding', 'Newborn session'],
                ['Hi, I\'m Maya', 'I have photographed more than 200 weddings. I keep things relaxed, so you can enjoy the day and still get photos that feel like you.'],
                () => [fill(sectionOf('pricing'), { title: 'Packages', items: [
                    { name: 'Portrait', price: '$250', period: '', features: '1-hour session\n30 edited photos\nOnline gallery', btn: 'Book a session', featured: false },
                    { name: 'Wedding', price: '$1,900', period: '', features: '8 hours of coverage\n400+ edited photos\nOnline gallery & prints', btn: 'Check my dates', featured: true },
                    { name: 'Family', price: '$350', period: '', features: '90-minute session\n50 edited photos\nTwo locations', btn: 'Book a session', featured: false }] })])],
            designer: ['Designer', () => folio('Studio Juno', '#db2777', ['Brand & web designer', 'Brands people remember — and websites that sell', 'I design logos, brand identities and websites for small businesses that want to look as good as they are.', 'Start a project'],
                ['Bloom Café — brand identity', 'Northwind — website', 'Fable Books — packaging', 'Kite App — UI design', 'Oak & Co. — logo', 'Harbor Gym — social media kit'],
                ['Designing for 10 years', 'I worked at two branding agencies before going independent. Now I work directly with founders — no middlemen, no surprises.'],
                () => [sectionOf('logos'), fill(sectionOf('steps'), { title: 'How we work together', items: [{ title: 'Discovery call', text: 'We talk about your business, customers and goals.' }, { title: 'Concepts', text: 'You get two or three directions to choose from.' }, { title: 'Final files', text: 'Everything you need, ready for print and web.' }] })])],
            developer: ['Developer', () => folio('Alex Carter', '#2563eb', ['Freelance web developer', 'Fast, reliable websites and web apps', 'I build websites, online stores and custom tools with clean code that is easy to grow.', 'Hire me'],
                ['Online store — Shopify', 'Booking app — Laravel', 'Company website — WordPress', 'Dashboard — React', 'Landing page — 98 speed score', 'API integration — Stripe'],
                ['Developer since 2015', 'I have built more than 80 projects for startups and small businesses. I write code that loads fast, ranks well and does not break.'],
                () => [TEMPLATES.sections['services-3'][1](), TEMPLATES.sections.skills[1]()])],
            artist: ['Artist / illustrator', () => folio('Ink & Ivy Illustration', '#7c3aed', ['Illustrator & artist', 'Hand-drawn art for books, brands and homes', 'Custom illustrations, portraits and prints — made with care, from sketch to final piece.', 'Commission a piece'],
                ['Children\'s book — "The Lost Kite"', 'Pet portrait — watercolour', 'Café wall mural', 'Botanical print series', 'Album cover', 'Wedding invitation'],
                ['Drawing since I could hold a pencil', 'I studied illustration in London and now work from my small studio by the sea. Every piece is drawn by hand.'],
                () => [fill(sectionOf('steps'), { title: 'How commissions work', items: [{ title: 'Tell me your idea', text: 'Send a few words and any reference photos.' }, { title: 'Sketch & approve', text: 'You see a sketch before I start the final piece.' }, { title: 'Delivered', text: 'Get a high-resolution file or a framed original.' }] })])],
        } },
        links: { label: 'link-in-bio page', examples: {
            creator: ['Creator / influencer', () => bio('Jess Rivera', '#e11d48', '✨ Lifestyle & travel creator · New videos every Friday', [['▶ Watch my latest video'], ['🛍 Shop my favourites'], ['📸 My photo presets'], ['📩 Work with me', 'email']], ['instagram', 'tiktok', 'youtube'])],
            musician: ['Musician / band', () => bio('The Night Owls', '#7c3aed', '🎸 Indie rock from Manchester · New single out now', [['🎧 Listen on Spotify'], ['🎟 Tour dates & tickets'], ['👕 Merch store'], ['▶ Watch the music video']], ['instagram', 'youtube', 'tiktok'])],
            shop: ['Small shop', () => bio('Bloom & Co. Flowers', '#16a34a', '🌸 Fresh flowers delivered same day · Order by 2 pm', [['💐 Order flowers online'], ['💬 Order on WhatsApp', 'whatsapp'], ['📍 Visit our shop'], ['⭐ Leave us a review']], ['instagram', 'facebook', 'pinterest'])],
            coach: ['Coach / consultant', () => bio('Dr. Sam Patel', '#0ea5e9', '🧠 Helping founders beat burnout · Coach & speaker', [['📅 Book a free intro call'], ['📘 Free guide: 5 signs of burnout'], ['🎙 Listen to my podcast'], ['✉️ Email me', 'email']], ['linkedin', 'youtube', 'x'])],
        } },
        event: { label: 'event website', examples: {
            wedding: ['Wedding', () => eventPage('Emma & James', '#b45309', { site: { fonts: { heading: 'Playfair Display', body: 'Lato', base: 17 } }, eyebrow: 'We’re getting married', title: 'Emma & James', days: 180, hour: 14, time: '2 pm', place: 'Rose Garden Hall, Bath', countTitle: 'Until we say “I do”',
                storyEyebrow: 'Our story', storyTitle: 'How we met', story: 'We met on a rainy Tuesday in a tiny bookshop, both reaching for the last copy of the same novel. Ten years, two cats and one proposal later — we can’t wait to celebrate with you.',
                planTitle: 'The day', plan: [['2:00 pm · Ceremony', 'In the rose garden — please arrive by 1:45.'], ['3:30 pm · Drinks & photos', 'Canapés and music on the lawn.'], ['6:00 pm · Dinner & dancing', 'Dinner in the hall, then the dance floor is open until midnight.']],
                venue: 'Rose Garden Hall', address: '12 Garden Lane, Bath BA1 2AB', extra: [wp('text', { html: '<p><b>Dress code:</b> garden formal</p>' })],
                rsvpTitle: 'Will you celebrate with us?', rsvpText: 'Please reply at least six weeks before the day.', rsvp: { meal: true, meals: 'Chicken\nSalmon\nVegetarian\nVegan' },
                faq: [['Can I bring a plus one?', 'Your invitation says how many seats we have saved for you. Please contact us if you are not sure.'], ['Are children welcome?', 'We love your little ones, but we have chosen to make our day adults-only.'], ['Is there parking?', 'Yes, free parking is available at the venue.'], ['Do you have a gift list?', 'Your presence is the best gift! If you would like to give something, a contribution to our honeymoon would mean the world.']] })],
            birthday: ['Birthday party', () => eventPage('Sam’s 30th', '#db2777', { eyebrow: 'You’re invited', title: 'Sam is turning 30! 🎉', days: 30, hour: 20, time: '8 pm till late', place: 'The Rooftop Bar, Manchester', countTitle: 'The party starts in',
                storyEyebrow: 'The plan', storyTitle: 'Drinks, music and way too much cake', story: 'Come celebrate three decades of Sam with good friends, a great DJ and a view over the city. Dress to impress — there will be a photo booth!',
                planTitle: 'The night', plan: [['8 pm · Welcome drinks', 'First drink is on us.'], ['9 pm · Food & cake', 'Pizza, snacks and the big cake moment.'], ['10 pm · Dance', 'DJ until 2 am.']],
                venue: 'The Rooftop Bar', address: '45 King Street, Manchester M2 4LQ', rsvp: { guests: true, note: true }, rsvpText: 'Let us know a week before so we can plan food and drinks.',
                faq: [['Can I bring someone?', 'Yes! Just add them to your guest number.'], ['Is there a dress code?', 'Party best — sparkles welcome.'], ['Gifts?', 'No gifts please — just bring your dancing shoes.']] })],
            conference: ['Conference / workshop', () => eventPage('Grow Summit', '#2563eb', { eyebrow: 'One-day conference for small business owners', title: 'Grow Summit', days: 90, hour: 9, time: '9 am – 5 pm', place: 'City Conference Centre, Leeds', countTitle: 'Doors open in', btn2: 'See the agenda',
                storyEyebrow: 'Why come', storyTitle: 'Learn what works — from people who did it', story: 'Eight practical talks on marketing, sales and hiring, plus time to meet 300 other owners. No theory, just what you can use on Monday.',
                planTitle: 'Agenda', plan: [['9:00 · Welcome & coffee', 'Badges, breakfast and networking.'], ['10:00 · Morning talks', 'Marketing that pays for itself; pricing with confidence.'], ['13:30 · Workshops', 'Pick one: hiring, social media or cash flow.'], ['16:30 · Panel & drinks', 'Ask the experts, then drinks.']],
                venue: 'City Conference Centre', address: '1 Park Row, Leeds LS1 5HD', more: [fill(sectionOf('team'), { title: 'Speakers', items: [{ photo: '', name: 'Speaker name', role: 'Founder, Company' }, { photo: '', name: 'Speaker name', role: 'Marketing lead, Company' }, { photo: '', name: 'Speaker name', role: 'CEO, Company' }] })],
                rsvpTitle: 'Save your seat', rsvpText: 'Free for members. Seats are limited.', rsvp: { btn: 'Register', yes: 'Yes, save me a seat', no: 'I can’t come, but keep me posted', guests: false, note: false, emailReq: true },
                faq: [['Is lunch included?', 'Yes — lunch, coffee and snacks are included.'], ['Will talks be recorded?', 'Yes, everyone who registers gets the recordings.'], ['Can I get a refund?', 'Tickets are free, so no refunds are needed — just let us know if you can’t come.']] })],
            babyshower: ['Baby shower', () => eventPage('Baby Lily’s shower', '#0d9488', { eyebrow: 'Oh baby!', title: 'A baby shower for Priya', days: 45, hour: 13, time: '1 pm – 4 pm', place: 'Priya’s mum’s house, Leicester', countTitle: 'Countdown',
                storyEyebrow: 'Join us', storyTitle: 'Little Lily is on her way', story: 'Join us for an afternoon of tea, cake and silly games as we celebrate Priya and the little one arriving this autumn.',
                planTitle: 'The afternoon', plan: [['1 pm · Tea & cake', 'Sweet and savoury treats.'], ['2 pm · Games', 'Guess the bump and baby bingo.'], ['3 pm · Gifts & wishes', 'Write a note for baby Lily.']],
                venue: 'Priya’s mum’s house', address: '8 Oak Road, Leicester LE2 3FG', rsvp: { guests: true, note: true },
                faq: [['Is there a gift list?', 'Yes — ask us for the link. Nappies are always welcome too!'], ['Can I bring my children?', 'Of course — little ones are very welcome.']] })],
        } },
        menu: { label: 'restaurant menu', examples: {
            restaurant: ['Restaurant', () => menuPage('Olive & Ember', '#c2410c', { site: { fonts: { heading: 'Playfair Display', body: 'Lato', base: 17 } }, eyebrow: 'Wood-fired kitchen · Open daily', title: 'Our menu', text: 'Seasonal plates, hand-stretched pizza and natural wines. Ask us about vegan and gluten-free options.',
                hoursShort: '12 – 10 pm', area: 'City centre',
                menu: [['Starters & mains', ['To start', [['Burrata', '$12', 'Heritage tomatoes, basil oil'], ['Wood-fired bread', '$6', 'Whipped butter, sea salt'], ['Calamari', '$11', 'Lemon aioli']]], ['Mains', [['Margherita pizza', '$14', 'San Marzano, mozzarella, basil'], ['Grilled sea bass', '$24', 'Salsa verde, new potatoes'], ['Mushroom risotto', '$18', 'Parmesan, truffle oil (v)']]]],
                    ['Desserts & drinks', ['Desserts', [['Tiramisu', '$8'], ['Affogato', '$6', 'Vanilla gelato, espresso'], ['Chocolate torte', '$8', 'Gluten-free']]], ['Drinks', [['House wine (glass)', '$7'], ['Craft beer', '$6'], ['Fresh lemonade', '$4']]]]],
                special: ['Tonight’s special', 'Slow-roasted lamb shoulder for two — $38. Ask your server.'],
                hours: [['Monday – Thursday', '12:00 – 22:00'], ['Friday – Saturday', '12:00 – 23:00'], ['Sunday', '12:00 – 21:00']], address: '12 Market Street<br>Your City' })],
            cafe: ['Café / coffee shop', () => menuPage('Little Bean Café', '#7c5c3b', { eyebrow: 'Specialty coffee · Fresh bakes', title: 'Coffee, cake & good mornings', text: 'Locally roasted beans, oat milk at no extra cost and pastries baked every morning.',
                hoursShort: '7 am – 5 pm', area: 'High Street',
                menu: [['Coffee & tea', ['Coffee', [['Espresso', '$2.50'], ['Flat white', '$3.40'], ['Cappuccino', '$3.40'], ['Iced latte', '$3.80']]], ['Not coffee', [['Matcha latte', '$3.90'], ['Hot chocolate', '$3.50'], ['Chai latte', '$3.60'], ['Pot of tea', '$2.80']]]],
                    ['Food', ['Breakfast', [['Avocado toast', '$8', 'Sourdough, chilli, lime'], ['Granola bowl', '$6', 'Yoghurt, berries, honey'], ['Bacon roll', '$5']]], ['Bakes', [['Croissant', '$3'], ['Banana bread', '$3.50'], ['Cinnamon bun', '$3.50']]]]],
                special: ['Loyalty card', 'Your 10th coffee is on us — ask for a card at the counter.'],
                hours: [['Monday – Friday', '7:00 – 17:00'], ['Saturday', '8:00 – 17:00'], ['Sunday', '9:00 – 15:00']], address: '45 High Street<br>Your City' })],
            pizzeria: ['Pizza & takeaway', () => menuPage('Napoli Express', '#dc2626', { order: true, eyebrow: 'Pizza · Pasta · Delivery', title: 'Hot pizza at your door in 30 minutes', text: 'Order on WhatsApp or call us. Free delivery on orders over $25.',
                hoursShort: '11 am – 11 pm', area: 'We deliver city-wide',
                menu: [['Pizza', ['Classics', [['Margherita', '$10'], ['Pepperoni', '$12'], ['Hawaiian', '$12'], ['Four cheese', '$13']]], ['Specials', [['BBQ chicken', '$14'], ['Veggie supreme', '$13'], ['Spicy beef', '$14'], ['Truffle mushroom', '$15']]]],
                    ['Sides & drinks', ['Sides', [['Garlic bread', '$4'], ['Chicken wings (6)', '$7'], ['Fries', '$3']]], ['Drinks', [['Soft drink (can)', '$1.50'], ['Water', '$1'], ['Milkshake', '$4']]]]],
                special: ['Deal of the day', 'Any 2 large pizzas + garlic bread + 2 drinks for $29.'],
                hours: [['Every day', '11:00 – 23:00']], address: '8 Station Road<br>Your City' })],
            foodtruck: ['Food truck / street food', () => menuPage('Taco Loco', '#16a34a', { order: true, eyebrow: 'Street tacos · Burritos', title: 'Tacos made fresh, right in front of you', text: 'Find us at the market on weekends and at the business park on weekdays.',
                hoursShort: '11:30 am – 3 pm', area: 'See where we are today',
                menu: [['Food', ['Tacos (3 per plate)', [['Carnitas', '$9', 'Slow-cooked pork, salsa verde'], ['Chicken tinga', '$9'], ['Crispy fish', '$10'], ['Mushroom & bean (v)', '$8']]], ['Burritos & bowls', [['Classic burrito', '$11'], ['Burrito bowl', '$11', 'Rice, beans, salsa, guac'], ['Loaded nachos', '$8']]]],
                    ['Extras', ['Sides', [['Guacamole & chips', '$5'], ['Elote (street corn)', '$4']]], ['Drinks', [['Horchata', '$4'], ['Agua fresca', '$4'], ['Mexican cola', '$3']]]]],
                special: ['Taco Tuesday', 'Any 3 tacos for $7 every Tuesday.'],
                hours: [['Monday – Friday', '11:30 – 15:00 · Business park'], ['Saturday – Sunday', '10:00 – 16:00 · Central market']], address: 'Different spot each day —<br>follow us for today’s location' })],
            sushi: ['Sushi & Japanese', () => menuPage('Hana Sushi', '#be123c', { eyebrow: 'Sushi · Ramen · Bento', title: 'Fresh sushi, rolled to order', text: 'Fish delivered every morning, rice seasoned by hand. Eat in, take away or order for delivery.',
                hoursShort: '12 – 10 pm', area: 'Riverside',
                menu: [['Sushi', ['Rolls (8 pcs)', [['California roll', '$9', 'Crab, avocado, cucumber'], ['Spicy tuna roll', '$11'], ['Salmon avocado roll', '$10'], ['Veggie dragon roll (v)', '$10']]], ['Nigiri (2 pcs)', [['Salmon', '$5'], ['Tuna', '$6'], ['Yellowtail', '$6'], ['Eel', '$6', 'Sweet soy glaze']]]],
                    ['Hot dishes & drinks', ['Ramen & bowls', [['Tonkotsu ramen', '$14', 'Pork broth, chashu, egg'], ['Chicken katsu curry', '$13'], ['Teriyaki salmon bowl', '$15']]], ['Drinks', [['Green tea', '$2'], ['Japanese beer', '$5'], ['Yuzu soda', '$4']]]]],
                special: ['Lunch bento', 'Any roll + miso soup + salad for $13, weekdays 12 – 3 pm.'],
                hours: [['Tuesday – Sunday', '12:00 – 22:00'], ['Monday', 'Closed']], address: '3 River Walk<br>Your City' })],
            curry: ['Indian & curry house', () => menuPage('Spice Route', '#b45309', { order: true, eyebrow: 'Indian kitchen · Takeaway & delivery', title: 'Slow-cooked curries, fresh from the tandoor', text: 'Family recipes, spices ground every day. Order on WhatsApp or call — free delivery over $30.',
                hoursShort: '5 – 11 pm', area: 'Delivering nearby',
                menu: [['Curries & tandoori', ['Curries', [['Butter chicken', '$13', 'Creamy tomato, mild'], ['Lamb rogan josh', '$15', 'Medium'], ['Chana masala (vg)', '$10'], ['Prawn jalfrezi', '$15', 'Hot']]], ['Tandoori', [['Chicken tikka', '$12'], ['Paneer tikka (v)', '$11'], ['Mixed grill', '$18']]]],
                    ['Sides & breads', ['Rice & breads', [['Pilau rice', '$3.50'], ['Garlic naan', '$3.50'], ['Peshwari naan', '$4']]], ['Sides & drinks', [['Onion bhaji', '$4.50'], ['Samosas (2)', '$4.50'], ['Mango lassi', '$3.50']]]]],
                special: ['Family feast', '2 curries, 2 rice, 2 naan and a side for $36 — feeds 3 to 4.'],
                hours: [['Every day', '17:00 – 23:00']], address: '21 King Street<br>Your City' })],
            burger: ['Burger joint', () => menuPage('Smash Bros. Burgers', '#ca8a04', { order: true, site: { theme: 'dark' }, eyebrow: 'Smash burgers · Fries · Shakes', title: 'Crispy-edged smash burgers, made to order', text: '100% beef, toasted brioche and our secret sauce. Eat in or order for pick-up.',
                hoursShort: '11 am – 11 pm', area: 'Old Town',
                menu: [['Burgers', ['Beef', [['Classic smash', '$9', 'Double patty, cheese, pickles, sauce'], ['Bacon BBQ', '$11'], ['Hot honey', '$11', 'Spicy'], ['Triple stack', '$13']]], ['Not beef', [['Crispy chicken', '$10'], ['Veggie smash (v)', '$10'], ['Kids’ burger + fries', '$7']]]],
                    ['Sides & shakes', ['Sides', [['Fries', '$3.50'], ['Loaded cheese fries', '$6'], ['Onion rings', '$4']]], ['Shakes & drinks', [['Vanilla shake', '$5'], ['Oreo shake', '$5.50'], ['Soft drink', '$2']]]]],
                special: ['Meal deal', 'Any burger + fries + drink for $3.50 extra.'],
                hours: [['Sunday – Thursday', '11:00 – 22:00'], ['Friday – Saturday', '11:00 – 23:00']], address: '9 Old Town Square<br>Your City' })],
            bar: ['Cocktail bar & pub', () => menuPage('The Copper Still', '#0f766e', { site: { theme: 'dark', fonts: { heading: 'Playfair Display', body: 'Lato', base: 17 } }, eyebrow: 'Cocktails · Craft beer · Small plates', title: 'Good drinks, good company', text: 'Hand-made cocktails, local beer on tap and plates to share. Book a table for groups of 6 or more.',
                hoursShort: '4 pm – late', area: 'Canal Street',
                menu: [['Drinks', ['Cocktails', [['Old fashioned', '$12'], ['Espresso martini', '$12'], ['Spicy margarita', '$11'], ['Garden spritz (low alcohol)', '$9']]], ['Beer & wine', [['Local IPA (pint)', '$7'], ['Lager (pint)', '$6'], ['House red / white (glass)', '$8'], ['Alcohol-free beer', '$5']]]],
                    ['Small plates', ['To share', [['Truffle fries', '$6'], ['Halloumi bites', '$7'], ['Chicken wings', '$8'], ['Nachos', '$8']]], ['Sweet', [['Churros & chocolate', '$6'], ['Cheese board', '$12']]]]],
                special: ['Happy hour', 'Cocktails $8, Monday to Friday, 4 – 7 pm.'],
                hours: [['Monday – Thursday', '16:00 – 00:00'], ['Friday – Saturday', '16:00 – 02:00'], ['Sunday', '14:00 – 23:00']], address: '5 Canal Street<br>Your City' })],
        } },
        store: { label: 'online store', examples: {
            fashion: ['Clothing & fashion', () => storePage('Thread & Co.', '#0f766e', { eyebrow: 'New season · Free delivery over $75', title: 'Everyday clothes, made to last', text: 'Soft organic cotton, honest prices and pieces you will wear for years. Order today, delivered in 2–3 days.',
                shop: { fee: '4.99', freeOver: '75' }, shopTitle: 'Bestsellers', shopIntro: 'Pick your size — every order ships within 24 hours.', perks: [['truck', 'Free delivery', 'On orders over $75'], ['award', '30-day returns', 'Easy, no questions asked'], ['shield', 'Secure checkout', 'Card, cash or bank transfer']],
                products: [['Classic white tee', '24', '100% organic cotton, relaxed fit.', { cat: 'Tops', options: 'Size: S, M, L, XL', badge: 'Bestseller', long: 'Our everyday tee in heavyweight organic cotton. Pre-washed, so it keeps its shape and size.\nMachine wash at 30°. Model is 1.80 m and wears M.' }], ['Linen shirt', '49', 'Breathable linen for warm days.', { cat: 'Tops', options: 'Size: S, M, L, XL' }], ['Denim jacket', '89', 'Washed denim with a timeless cut.', { cat: 'Jackets', old: '110', badge: 'Sale', options: 'Size: S, M, L, XL' }],
                    ['Everyday hoodie', '59', 'Brushed inside, built to keep its shape.', { cat: 'Tops', options: 'Size: S, M, L, XL', opt2Name: 'Colour', vals2: [{ vid: '', v: 'Grey' }, { vid: '', v: 'Navy' }] }], ['Canvas tote bag', '19', 'Roomy, sturdy and machine washable.', { cat: 'Accessories' }], ['Wool beanie', '22', 'Merino blend, one size.', { cat: 'Accessories', options: 'Colour: Black, Grey, Mustard' }]],
                story: ['Our story', 'Fewer, better clothes', 'We started Thread & Co. because we were tired of clothes that fell apart after a few washes. Every piece is made in small batches by a family-run workshop we know by name.'],
                reviews: [['The tee is the softest I own — I bought three more.', 'Hannah W.'], ['Fast delivery and the jacket fits perfectly.', 'Marcus D.'], ['Great quality for the price. Returns were easy too.', 'Priya S.']],
                faq: [['How long does delivery take?', 'Orders ship within 24 hours and arrive in 2–3 working days.'], ['Can I return something?', 'Yes — send it back within 30 days for a full refund or exchange.'], ['How do I choose my size?', 'Our sizes are true to size. Between sizes? Pick the bigger one for a relaxed fit.']],
                policy: '<p><b>Delivery:</b> orders ship within 24 hours and arrive in 2–3 working days. Delivery is $4.99, or free on orders over $75.</p><p><b>Returns:</b> send unworn items back within 30 days for a refund or exchange. Message us and we will help.</p>' })],
            bakery: ['Bakery & cakes', () => storePage('Golden Crust Bakery', '#d97706', { eyebrow: 'Baked fresh every morning', title: 'Fresh bread, cakes and treats — to your door', text: 'Order by 2 pm for next-day delivery, or collect from our shop. Everything is baked by hand with real butter.',
                shop: { fee: '3.50', freeOver: '40', pickup: true, pickupText: '12 Market Street, 8 am – 4 pm' }, shopTitle: 'Order today', perks: [['truck', 'Next-day delivery', 'Order by 2 pm'], ['home', 'Pick up in store', 'Ready in 2 hours'], ['heart', 'Made by hand', 'Real butter, no shortcuts']],
                products: [['Sourdough loaf', '6.50', 'Slow-fermented for 24 hours, crackly crust.', { cat: 'Bread', badge: 'Bestseller' }], ['Butter croissants (4)', '9', 'Flaky, golden and baked this morning.', { cat: 'Pastries' }], ['Celebration cake', '35', 'Chocolate, vanilla or red velvet — tell us in the order note.', { cat: 'Cakes', optName: 'Size', vals: [{ v: '6 inch (serves 8)', price: '' }, { v: '8 inch (serves 14)', price: '48' }, { v: '10 inch (serves 20)', price: '62' }], long: 'Three layers of sponge with buttercream, finished by hand. Add your message (up to 30 letters) and flavour in the order note.\nPlease order two days ahead.' }],
                    ['Cinnamon buns (6)', '14', 'Soft, sticky and finished with icing.', { cat: 'Pastries' }], ['Cookie box (12)', '12', 'A mix of our four favourite cookies.', { cat: 'Gifts' }], ['Afternoon tea hamper', '45', 'Scones, jam, cakes and loose-leaf tea.', { cat: 'Gifts', badge: 'New' }]],
                story: ['Since 2009', 'A small bakery with a big oven', 'Three bakers, one wood-fired oven and recipes from our grandmother’s notebook. We bake in small batches through the night so everything reaches you fresh.'],
                reviews: [['The best sourdough in town. It arrived still warm!', 'Tom R.'], ['Ordered a birthday cake — beautiful and delicious.', 'Sofia M.'], ['The hamper made a perfect gift for my mum.', 'Daniel K.']],
                faq: [['When will my order arrive?', 'Order by 2 pm for delivery the next morning. Pick-up orders are ready in 2 hours.'], ['Do you make gluten-free bakes?', 'We have gluten-free brownies and cookies — ask us in the order note.'], ['Can I order a custom cake?', 'Yes! Choose the celebration cake and write your message in the note.']],
                policy: '<p><b>Delivery:</b> next morning for orders placed by 2 pm. Delivery is $3.50, free over $40.</p><p><b>Pick-up:</b> collect from 12 Market Street, 8 am – 4 pm — usually ready within 2 hours.</p><p>Fresh food can’t be returned, but if anything is wrong, message us and we will put it right.</p>' })],
            beauty: ['Beauty & skincare', () => storePage('Glow Botanics', '#be185d', { site: { fonts: { heading: 'Playfair Display', body: 'Lato', base: 17 } }, eyebrow: 'Clean, plant-powered skincare', title: 'Skin that feels as good as it looks', text: 'Gentle formulas made with botanical oils, tested on people — never on animals.',
                shop: { fee: '3.99', freeOver: '45' }, shopTitle: 'Shop skincare', perks: [['leaf', 'Natural ingredients', 'Vegan and cruelty-free'], ['truck', 'Free delivery', 'On orders over $45'], ['gift', 'Gift wrapping', 'Ask in the order note']],
                products: [['Vitamin C serum', '28', 'Brightens dull skin in two weeks.', { cat: 'Face', badge: 'Bestseller', optName: 'Size', vals: [{ v: '30 ml', price: '' }, { v: '50 ml', price: '42' }] }], ['Hydrating face cream', '32', 'Rich but light, for all skin types.', { cat: 'Face' }], ['Gentle cleanser', '18', 'Removes make-up without drying.', { cat: 'Face' }],
                    ['Lip balm trio', '12', 'Shea, rose and vanilla.', { cat: 'Lips' }], ['Clay face mask', '22', 'Deep cleans in 10 minutes.', { cat: 'Face' }], ['Glow gift set', '65', 'Serum, cream and cleanser in a gift box.', { cat: 'Gift sets', old: '78', badge: 'Sale' }]],
                story: ['Why we started', 'Skincare without the long words', 'Glow Botanics began in a kitchen with three ingredients and one sensitive-skinned founder. Today every product still has a short ingredient list you can actually read.'],
                reviews: [['My skin has never looked this good. The serum is magic.', 'Aisha K.'], ['Lovely packaging and super fast delivery.', 'Emma L.'], ['Finally a cream that doesn’t make me break out.', 'Jo P.']],
                faq: [['Are your products vegan?', 'Yes, every product is vegan and cruelty-free.'], ['Which product is right for me?', 'Message us with your skin type and we will suggest a routine.'], ['How long does delivery take?', '2–4 working days. Free on orders over $45.']],
                policy: '<p><b>Delivery:</b> 2–4 working days. $3.99, free over $45.</p><p><b>Returns:</b> unopened products can be returned within 14 days. If a product doesn’t suit your skin, tell us — we will help.</p>' })],
            handmade: ['Handmade & gifts', () => storePage('Clay & Cotton', '#7c3aed', { eyebrow: 'Handmade in small batches', title: 'Thoughtful gifts, made by hand', text: 'Ceramics, candles and home goods from our little studio — each one a little different.',
                shop: { fee: '5', freeOver: '60', bank: true, bankText: 'Bank: Your Bank\nAccount name: Clay & Cotton\nAccount number: 12345678\nSort code: 00-00-00' }, shopTitle: 'From the studio', perks: [['heart', 'Made by hand', 'No two pieces the same'], ['gift', 'Ready to gift', 'Wrapped with care'], ['truck', 'Careful delivery', 'Free over $60']],
                products: [['Handmade mug', '26', 'Wheel-thrown stoneware, holds 350 ml.', { cat: 'Kitchen', options: 'Colour: Sage, Sand, Ocean', badge: 'Bestseller' }], ['Ceramic planter', '34', 'With a drainage hole and saucer.', { cat: 'Home' }], ['Scented soy candle', '18', '40-hour burn, cotton wick.', { cat: 'Home', options: 'Scent: Lavender, Fig, Cedar' }],
                    ['Macramé wall hanging', '45', 'Natural cotton cord, 60 cm long.', { cat: 'Home' }], ['Linen napkins (set of 4)', '28', 'Soft stonewashed linen.', { cat: 'Kitchen' }], ['Gift card', '25', 'Let them choose — the code is emailed.', { cat: 'Gifts', badge: 'New', kind: 'gift' }]],
                story: ['Meet the maker', 'From our studio to your home', 'Hi, I’m Lena. I make everything in a small studio behind my house, one piece at a time. Thank you for supporting handmade.'],
                reviews: [['The mug is even prettier in real life.', 'Clara B.'], ['Beautifully wrapped — a perfect birthday present.', 'Sam T.'], ['The fig candle smells amazing.', 'Nadia F.']],
                faq: [['Do you ship gifts directly?', 'Yes — add the recipient’s address and a gift message in the order note.'], ['Why does my mug look different?', 'Every piece is handmade, so colours and shapes vary a little. That’s the charm!'], ['How do I pay by bank transfer?', 'Choose bank transfer at checkout and you’ll see our bank details straight away.']],
                policy: '<p><b>Delivery:</b> every order is packed by hand and sent within 3 working days. $5, free over $60.</p><p><b>Returns:</b> if something arrives broken, send us a photo within 7 days and we will replace it.</p>' })],
            coffee: ['Coffee roaster', () => storePage('Ridge Roasters', '#78350f', { eyebrow: 'Roasted this week · Free delivery over $35', title: 'Fresh coffee, roasted in small batches', text: 'Single-origin beans and house blends, roasted every Monday and sent the same week. Whole bean or ground for your brewer.',
                shop: { fee: '3.95', freeOver: '35' }, shopTitle: 'Our coffee', shopIntro: 'Pick whole bean or your grind.', perks: [['coffee', 'Roasted weekly', 'Never sits on a shelf'], ['truck', 'Free delivery', 'On orders over $35'], ['heart', 'Fairly traded', 'We know our farmers']],
                products: [['House espresso blend', '12', 'Chocolate, caramel, hazelnut.', { cat: 'Blends', badge: 'Bestseller', optName: 'Size', vals: [{ v: '250 g', price: '' }, { v: '1 kg', price: '38' }], opt2Name: 'Grind', vals2: [{ vid: '', v: 'Whole bean' }, { vid: '', v: 'Espresso' }, { vid: '', v: 'Filter' }, { vid: '', v: 'Cafetière' }] }], ['Ethiopia Yirgacheffe', '15', 'Bright, floral, lemony.', { cat: 'Single origin', options: 'Grind: Whole bean, Espresso, Filter, Cafetière' }], ['Colombia Huila', '14', 'Red apple, toffee, smooth.', { cat: 'Single origin', options: 'Grind: Whole bean, Espresso, Filter, Cafetière' }],
                    ['Decaf Brazil', '13', 'Nutty and sweet — all taste, no buzz.', { cat: 'Blends', options: 'Grind: Whole bean, Espresso, Filter, Cafetière' }], ['Pour-over starter kit', '45', 'Dripper, filters, scale and a bag of coffee.', { cat: 'Gear', badge: 'New' }], ['Coffee gift card', '30', 'Let them choose — the code is emailed.', { cat: 'Gifts', kind: 'gift' }]],
                story: ['From farm to cup', 'Coffee we’d drink every day', 'We roast on a small drum roaster, buy directly from farms we visit, and print the roast date on every bag — so you always know it’s fresh.'],
                reviews: [['The house blend has ruined supermarket coffee for me.', 'Ollie P.'], ['Arrived two days after roasting. You can taste it.', 'Grace H.'], ['The starter kit was the perfect gift for my dad.', 'Ravi M.']],
                faq: [['Whole bean or ground?', 'Whole bean stays fresh longest. If you don’t have a grinder, pick the grind for your brewer and we’ll grind it on roast day.'], ['How long does coffee stay fresh?', 'Best within 6 weeks of the roast date. Keep it sealed, away from light and heat.'], ['Do you offer subscriptions?', 'Message us — we can send a bag every 2 or 4 weeks.']],
                policy: '<p><b>Delivery:</b> we roast on Monday and post by Wednesday. $3.95, free over $35.</p><p><b>Returns:</b> not happy with a coffee? Tell us within 14 days and we’ll send a different one.</p>' })],
            florist: ['Florist & plants', () => storePage('Wild Bloom Florist', '#15803d', { site: { fonts: { heading: 'Playfair Display', body: 'Lato', base: 17 } }, eyebrow: 'Same-day delivery · Order by 1 pm', title: 'Fresh flowers, delivered today', text: 'Hand-tied bouquets and easy-care plants, arranged the morning they’re sent. Add a card message at checkout.',
                shop: { fee: '6', freeOver: '60', pickup: true, pickupText: '4 Church Lane, 9 am – 5 pm' }, shopTitle: 'Bouquets & plants', perks: [['clock', 'Same-day delivery', 'Order by 1 pm'], ['leaf', 'Seasonal & local', 'Flowers that last'], ['gift', 'Free card message', 'Write it at checkout']],
                products: [['Seasonal bouquet', '35', 'Our florist’s pick of the freshest stems.', { cat: 'Bouquets', badge: 'Bestseller', optName: 'Size', vals: [{ v: 'Classic', price: '' }, { v: 'Large', price: '50' }, { v: 'Luxe', price: '70' }] }], ['Dozen red roses', '55', 'Hand-tied with eucalyptus.', { cat: 'Bouquets' }], ['Sunshine tulips', '28', 'A bright bunch of 15 tulips.', { cat: 'Bouquets' }],
                    ['Monstera plant', '32', 'Easy-care, in a ceramic pot.', { cat: 'Plants', options: 'Pot: White, Terracotta' }], ['Dried flower posy', '24', 'Lasts for months — no water needed.', { cat: 'Dried', badge: 'New' }], ['Birthday bundle', '48', 'Bouquet, chocolates and a balloon.', { cat: 'Gifts', old: '56', badge: 'Sale' }]],
                story: ['Our little shop', 'Flowers that feel personal', 'We buy from local growers and the flower market every morning, and arrange everything by hand in our shop on Church Lane.'],
                reviews: [['My mum cried happy tears — the bouquet was stunning.', 'Leah S.'], ['Ordered at noon, delivered by 4. Lifesaver!', 'James O.'], ['The monstera is thriving months later.', 'Chloe N.']],
                faq: [['When will my flowers arrive?', 'Order by 1 pm for delivery the same day, Monday to Saturday.'], ['Can I add a card message?', 'Yes — write your message in the order note and we’ll hand-write the card.'], ['How do I keep flowers fresh?', 'Trim the stems, change the water every two days and keep them out of direct sun.']],
                policy: '<p><b>Delivery:</b> same day for orders placed by 1 pm, Monday – Saturday. $6, free over $60.</p><p><b>Freshness promise:</b> if your flowers don’t last 5 days, send us a photo and we’ll replace them.</p>' })],
            pets: ['Pet shop', () => storePage('Happy Paws', '#2563eb', { eyebrow: 'Treats, toys & everyday essentials', title: 'Everything your pet loves — delivered', text: 'Natural treats, tough toys and comfy beds for dogs and cats. Fast delivery and friendly advice.',
                shop: { fee: '4.50', freeOver: '50' }, shopTitle: 'Shop for your pet', perks: [['heart', 'Vet-approved', 'Natural ingredients'], ['truck', 'Free delivery', 'On orders over $50'], ['smile', 'Happy pet promise', 'Not a fan? Swap it']],
                products: [['Natural chew sticks (10)', '12', 'Long-lasting, grain-free.', { cat: 'Dogs', badge: 'Bestseller' }], ['Rope tug toy', '9', 'Tough cotton rope for tug and fetch.', { cat: 'Dogs' }], ['Cosy pet bed', '45', 'Washable cover, memory foam.', { cat: 'Beds', options: 'Size: S, M, L', opt2Name: 'Colour', vals2: [{ vid: '', v: 'Grey' }, { vid: '', v: 'Sand' }] }],
                    ['Cat feather wand', '7', 'Hours of chasing fun.', { cat: 'Cats' }], ['Salmon cat treats', '6', 'Crunchy outside, soft inside.', { cat: 'Cats' }], ['Personalised collar', '18', 'Name and phone stitched on.', { cat: 'Dogs', options: 'Size: S, M, L', badge: 'New' }]],
                story: ['Pet people', 'We’re pet parents too', 'Happy Paws started with one very fussy beagle called Biscuit. We test every product with our own pets before it goes on the shelf.'],
                reviews: [['My dog goes crazy for the chew sticks.', 'Mia R.'], ['The bed is so soft — my cat won’t leave it.', 'Ben A.'], ['Quick delivery and great advice by message.', 'Sara D.']],
                faq: [['Are your treats natural?', 'Yes — no artificial colours or preservatives.'], ['Which bed size do I need?', 'Measure your pet from nose to tail while lying down and add 15 cm.'], ['Can I swap a toy my pet doesn’t like?', 'Yes, within 30 days — just message us.']],
                policy: '<p><b>Delivery:</b> 1–3 working days. $4.50, free over $50.</p><p><b>Returns:</b> unused items within 30 days. Food and treats can’t be returned once opened.</p>' })],
            jewellery: ['Jewellery', () => storePage('Aurelia Jewellery', '#a16207', { site: { fonts: { heading: 'Playfair Display', body: 'Lato', base: 17 } }, eyebrow: 'Handmade · Recycled gold & silver', title: 'Everyday jewellery, made to keep', text: 'Delicate pieces in recycled sterling silver and gold, made by hand in our studio. Gift-wrapped for free.',
                shop: { fee: '4', freeOver: '50' }, shopTitle: 'The collection', perks: [['award', 'Recycled metals', 'Silver and 14k gold'], ['gift', 'Free gift wrap', 'Every order'], ['shield', '1-year guarantee', 'Free repairs']],
                products: [['Pearl drop earrings', '45', 'Freshwater pearls on silver hooks.', { cat: 'Earrings', badge: 'Bestseller', optName: 'Metal', vals: [{ v: 'Silver', price: '' }, { v: 'Gold vermeil', price: '58' }] }], ['Initial necklace', '52', 'Your letter on a fine 45 cm chain.', { cat: 'Necklaces', options: 'Letter: A, B, C, D, E, J, L, M, S' }], ['Stacking rings (set of 3)', '38', 'Plain, twisted and dotted bands.', { cat: 'Rings', options: 'Size: 5, 6, 7, 8, 9' }],
                    ['Birthstone bracelet', '42', 'A tiny stone for their month.', { cat: 'Bracelets', options: 'Month: Jan, Feb, Mar, Apr, May, Jun, Jul, Aug, Sep, Oct, Nov, Dec' }], ['Hoop earrings', '34', 'Light, everyday hoops.', { cat: 'Earrings', optName: 'Size', vals: [{ v: '12 mm', price: '' }, { v: '20 mm', price: '39' }] }], ['Jewellery gift card', '50', 'The code is emailed straight away.', { cat: 'Gifts', kind: 'gift' }]],
                story: ['Made by hand', 'Small studio, careful hands', 'Every piece is cut, soldered and polished by hand in our studio, using recycled silver and gold — beautiful jewellery that’s kinder to the planet.'],
                reviews: [['I haven’t taken my initial necklace off since.', 'Ella G.'], ['Beautiful packaging — perfect for a gift.', 'Nora B.'], ['They resized my ring for free. Lovely service.', 'Imogen T.']],
                faq: [['What metals do you use?', 'Recycled sterling silver, and gold vermeil (thick 14k gold over silver).'], ['How do I find my ring size?', 'Wrap a strip of paper around your finger, measure it in mm and message us — we’ll tell you your size.'], ['Will it tarnish?', 'Silver can darken over time. A soft cloth brings back the shine; keep it away from perfume and water.']],
                policy: '<p><b>Delivery:</b> sent within 2 working days, gift-wrapped. $4, free over $50.</p><p><b>Returns:</b> unworn pieces within 30 days. Personalised pieces can’t be returned. Free repairs for a year.</p>' })],
        } },
        soon: { label: 'coming soon page', examples: {
            launch: ['Launching soon (countdown)', () => {
                const col = column([wp('heading', { text: 'Northwind', tag: 'p' }), wp('animhead', { before: 'Something', words: 'new\nexciting\nbig', after: 'is coming', tag: 'h1' }), wp('text', { html: '<p>We are launching soon. Leave your details and be the first to know — plus get 20% off on launch day.</p>' }), widget('countdown'), wp('form', { btn: 'Notify me', phone: false, name: false, message: false }), widget('social')]);
                col.widgets[0].st = { _: { d: { size: 20, weight: 800, transform: 'uppercase', ls: 3 } } }; col.st._ = { d: { maxw: 680 } };
                return { v: 3, kind: 'soon', site: soonSite('Northwind', '#4f46e5'), pages: [homePage([styled(section('1', [col]), { align: 'center', minH: 760 }, 'soft')])] };
            }],
            maintenance: ['Under maintenance', () => {
                const col = column([wp('heading', { text: '🛠 We\'ll be back soon', tag: 'h1' }), wp('text', { html: '<p>Our website is getting an upgrade. It will be back in a few hours — thanks for your patience.</p>' }), wp('button', { text: 'Email us', link: { to: 'email', val: '' }, align: 'center' }), widget('social')]);
                col.st._ = { d: { maxw: 620 } };
                return { v: 3, kind: 'soon', site: soonSite('Your business', '#0ea5e9', { theme: 'dark' }), pages: [homePage([styled(section('1', [col]), { align: 'center', minH: 760 })])] };
            }],
            waitlist: ['App waitlist', () => {
                const s = section('2', [column([wp('heading', { text: 'Coming soon', tag: 'p' }), wp('heading', { text: 'The simplest way to plan your week', tag: 'h1' }), wp('text', { html: '<p>Kite turns your to-do list into a calm, realistic plan. Join the waitlist for early access.</p>' }),
                    wp('iconlist', { items: [{ icon: 'i:check', text: 'Free for early users' }, { icon: 'i:check', text: 'iPhone, Android and web' }, { icon: 'i:check', text: 'No spam — one email when we launch' }] }), wp('form', { btn: 'Join the waitlist', phone: false, message: false })]), column([wp('image', { alt: 'App preview' })])]);
                s.cols[0].widgets[0].st = { _: { d: { color: 'g:primary', weight: 700, transform: 'uppercase', ls: 2, size: 14 } } };
                return { v: 3, kind: 'soon', site: soonSite('Kite', '#16a34a'), pages: [homePage([styled(s, { minH: 760 }, 'soft')])] };
            }],
            opening: ['Shop opening soon', () => {
                const col = column([wp('heading', { text: 'Opening on 1 March', tag: 'h1' }), wp('text', { html: '<p>Our new bakery is almost ready! Fresh bread, pastries and the best coffee in town — come and say hello.</p>' }), widget('countdown'), wp('text', { html: '<p>📍 12 Main Street, Your City</p>' }), widget('hours'), widget('social')]);
                col.st._ = { d: { maxw: 640 } };
                return { v: 3, kind: 'soon', site: soonSite('Golden Crust Bakery', '#d97706'), pages: [homePage([styled(section('1', [col]), { align: 'center', minH: 760 }, 'soft')])] };
            }],
        } },
    };
    const KINDS = ['funnel', ...Object.keys(KITS)];
    // Must match the routes in routes/web.php (FreeToolsTest checks this)
    const KIT_PATH = { funnel: 'funnel-builder', menu: 'restaurant-menu-builder', store: 'online-store-builder', event: 'event-website-builder', website: 'website-builder', portfolio: 'portfolio-builder', links: 'link-in-bio-builder', soon: 'coming-soon-page-builder' };
    /** A new project for a kit (website, portfolio, links, soon), from one of its examples. */
    const newKit = (kit, key) => {
        const ex = KITS[kit]?.examples || KITS.website.examples;
        const P = (ex[key] || Object.values(ex)[0])[1]();
        P.kind = KITS[kit] ? kit : 'website';
        return P;
    };
    /** Give a copied section (and everything in it) new ids. */
    const reId = sec => {
        sec = clone(sec); sec.id = uid();
        // copied products are new products: new ids, so they never take over the original's stock or links
        sec.cols.forEach(c => { c.id = uid(); c.widgets.forEach(w => { w.id = uid(); if (w.type === 'products') (w.p.items || []).forEach(it => { it.pid = ''; (it.vals || []).forEach(v => (v.vid = '')); }); }); });
        return sec;
    };

    /* ================================================================ styles → CSS */
    const FONTS = { Poppins: 'Poppins', Inter: 'Inter', 'DM Sans': 'DM Sans', Outfit: 'Outfit', Montserrat: 'Montserrat', 'Plus Jakarta Sans': 'Plus Jakarta Sans', Lato: 'Lato', 'Open Sans': 'Open Sans', Raleway: 'Raleway', 'Playfair Display': 'Playfair Display (elegant)', Merriweather: 'Merriweather (classic)', Lora: 'Lora (classic)', 'Space Grotesk': 'Space Grotesk (tech)', Caveat: 'Caveat (handwritten)' };
    const GLOBAL = ['primary', 'secondary', 'accent', 'text', 'bg'];
    const col = v => { v = String(v); if (v.startsWith('g:')) return GLOBAL.includes(v.slice(2)) ? `var(--${v.slice(2)})` : 'inherit'; return /^(#[0-9a-f]{3,8}|rgba?\([\d\s.,%]+\)|hsla?\([\d\s.,%deg]+\)|[a-z]{3,20})$/i.test(v.trim()) ? v.trim() : 'inherit'; };
    const SHADOWS = { sm: '0 4px 14px rgba(15,23,42,.08)', md: '0 14px 34px -12px rgba(15,23,42,.22)', lg: '0 30px 60px -24px rgba(15,23,42,.35)', glow: '0 0 0 1px var(--primary),0 18px 40px -16px var(--primary)' };
    const num = v => (v === undefined || v === null || String(v).trim() === '' || !isFinite(+v) ? null : +v);
    /** One device's style values → CSS declarations. */
    const decl = (s, isSection) => {
        const o = [];
        if (s.family && (s.family === 'heading' || s.family === 'body' || has(FONTS, s.family))) o.push(`font-family:${s.family === 'heading' ? 'var(--hf)' : s.family === 'body' ? 'var(--bf)' : `'${s.family}'`},sans-serif`);
        if (num(s.size) !== null) o.push(`font-size:${num(s.size)}px`);
        if (num(s.weight) !== null) o.push(`font-weight:${Math.min(900, Math.max(100, num(s.weight)))}`);
        if (num(s.lh) !== null) o.push(`line-height:${num(s.lh)}`);
        if (num(s.ls) !== null) o.push(`letter-spacing:${num(s.ls)}px`);
        if (['uppercase', 'lowercase', 'capitalize'].includes(s.transform)) o.push(`text-transform:${s.transform}`);
        if (s.italic) o.push('font-style:italic');
        if (['left', 'center', 'right', 'justify'].includes(s.align)) o.push(`text-align:${s.align}`);
        if (s.color) o.push(`color:${col(s.color)};--text:${col(s.color)};--muted:${col(s.color)}`); // headings and body text follow
        const bgImg = cssUrl(s.bgImg) ? `url("${cssUrl(s.bgImg)}")` : '';
        if (s.bgType === 'gradient' && s.g1 && s.g2) o.push(`background:linear-gradient(${num(s.gAngle) ?? 135}deg,${col(s.g1)},${col(s.g2)})`);
        else if (s.bgType === 'image' && bgImg) o.push(`background:${s.overlay ? `linear-gradient(${col(s.overlay)},${col(s.overlay)}),` : ''}${bgImg} center/cover no-repeat${s.fixed ? ' fixed' : ''}`);
        else if (s.bg && s.bgType !== 'none') o.push(`background:${col(s.bg)}`);
        ['pt', 'pr', 'pb', 'pl'].forEach((k, i) => { if (num(s[k]) !== null) o.push(`padding-${['top', 'right', 'bottom', 'left'][i]}:${num(s[k])}px`); });
        ['mt', 'mb'].forEach((k, i) => { if (num(s[k]) !== null) o.push(`margin-${['top', 'bottom'][i]}:${num(s[k])}px`); });
        if (num(s.radius) !== null) o.push(`border-radius:${num(s.radius)}px`);
        if (num(s.bw) !== null && +s.bw > 0) o.push(`border:${num(s.bw)}px ${['solid', 'dashed', 'dotted'].includes(s.bs) ? s.bs : 'solid'} ${s.bc ? col(s.bc) : 'var(--border)'}`);
        if (has(SHADOWS, s.shadow)) o.push(`box-shadow:${SHADOWS[s.shadow]}`);
        if (num(s.maxw) !== null) o.push(`max-width:${num(s.maxw)}px;margin-inline:auto`);
        if (num(s.minH) !== null) o.push(`min-height:${num(s.minH)}px;display:flex;flex-direction:column;justify-content:${s.valign === 'bottom' ? 'flex-end' : s.valign === 'top' ? 'flex-start' : 'center'}`);
        else if (s.valign && !isSection) o.push(`align-self:${s.valign === 'bottom' ? 'end' : s.valign === 'middle' ? 'center' : 'start'}`);
        if (num(s.opacity) !== null) o.push(`opacity:${num(s.opacity) / 100}`);
        return o.join(';');
    };
    const BP = { t: '@media (max-width:1024px)', m: '@media (max-width:767px)' };
    /** All per-element CSS for a page: styles for every target and device, custom CSS, and text colour inheritance. */
    const pageCss = pg => {
        const rules = { d: [], t: [], m: [] }, custom = [];
        const visit = (id, st, adv, isSection) => {
            Object.entries(st || {}).forEach(([target, dev]) => {
                if (!/^[\w.*]{1,60}$/.test(target)) return;
                const sel = `.x-${id}` + (target === '_' ? '' : ` .${classKey(target)}`);
                ['d', 't', 'm'].forEach(k => { const css = dev?.[k] && decl(dev[k], isSection && target === '_'); if (css) rules[k].push(`${sel}{${css}}`); });
                // Text alignment on a section/column also centres buttons and lists inside it
                ['d', 't', 'm'].forEach(k => { const a = dev?.[k]?.align; if (a && target === '_') rules[k].push(`.x-${id} .actions,.x-${id} .btn-wrap.al-auto,.x-${id} .socials{justify-content:${a === 'center' ? 'center' : a === 'right' ? 'flex-end' : 'flex-start'}}`); });
            });
            if (adv?.css) custom.push(cssSafe(adv.css).replace(/\bselector\b/g, `.x-${id}`));
        };
        [...pg.sections, ...(pg.ab?.on ? pg.sectionsB || [] : [])].forEach(s => { visit(s.id, s.st, s.adv, true); s.cols.forEach(c => { visit(c.id, c.st, c.adv); c.widgets.forEach(w => visit(w.id, w.st, w.adv)); }); });
        return cssSafe(rules.d.join('') + (rules.t.length ? `${BP.t}{${rules.t.join('')}}` : '') + (rules.m.length ? `${BP.m}{${rules.m.join('')}}` : '') + custom.join('\n'));
    };

    const siteCss = P => {
        const s = P.site, c = s.colors || {}, primary = hex(c.primary), dark = s.theme === 'dark';
        const hf = FONTS[s.fonts?.heading] ? s.fonts.heading : 'Poppins', bf = FONTS[s.fonts?.body] ? s.fonts.body : 'Inter', base = Math.max(13, Math.min(22, +s.fonts?.base || 17));
        const r = { round: '22px', soft: '12px', sharp: '4px' }[s.radius] || '22px', br = { pill: '999px', rounded: '12px', square: '4px' }[s.buttons] || '999px';
        const m = dark ? { bg: '#0b1020', alt: '#0f162b', text: '#e5e7eb', muted: '#9ca3af', card: '#121a2f', border: '#1f2a44' } : { bg: hex(c.bg || '#ffffff'), alt: '#f6f7fb', text: hex(c.text || '#0f172a'), muted: '#475569', card: '#ffffff', border: '#e5e7eb' };
        return `:root{--primary:${primary};--c:${primary};--secondary:${hex(c.secondary)};--accent:${hex(c.accent)};--text:${m.text};--bg:${m.bg};--ink:${ink(primary)};--soft:${dark ? primary + '22' : mix(primary, 0.9)};--deep:${mix(primary, 0.35, 0)};--alt:${m.alt};--muted:${m.muted};--card:${m.card};--border:${m.border};--r:${r};--br:${br};--hf:'${hf}';--bf:'${bf}'}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font:400 ${base}px/1.65 var(--bf),system-ui,sans-serif;color:var(--text);background:var(--bg)}img{max-width:100%;display:block}a{color:inherit}
.wrap{width:min(1140px,100% - 40px);margin:0 auto}.wrap.full{width:100%;padding-inline:20px}.narrow{max-width:800px}.center{text-align:center}.muted{color:var(--muted)}
h1,h2,h3,h4,.hd{font-family:var(--hf),var(--bf),sans-serif;line-height:1.15;margin:0 0 .45em;letter-spacing:-.01em;color:var(--text)}h1{font-size:clamp(2.2rem,5vw,3.6rem)}h2{font-size:clamp(1.7rem,3.4vw,2.4rem)}h3{font-size:1.2rem}h4{font-size:1.05rem}
.s-full>.wrap>h2,.s-full .wrap>.hd{text-align:center}h2.left,.hd.left{text-align:left}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:14px 26px;border-radius:var(--br);background:var(--primary);color:var(--ink);font-weight:700;text-decoration:none;border:2px solid var(--primary);transition:transform .15s,box-shadow .15s;cursor:pointer;font-family:inherit;font-size:1rem}.btn:hover{transform:translateY(-2px);box-shadow:0 12px 26px -12px var(--primary)}
.btn-ghost{background:transparent;color:var(--text);border-color:var(--border)}.btn-sm{padding:9px 18px;font-size:.92rem}.btn-lg{padding:18px 34px;font-size:1.1rem}.btn-light{background:#fff;color:#111827;border-color:#fff}
.actions,.btn-wrap{display:flex;flex-wrap:wrap;gap:12px;margin-top:8px}.actions.center,.al-center{justify-content:center}.al-right{justify-content:flex-end}.al-full .btn{width:100%}
.ob-bar{background:var(--primary);color:var(--ink);text-align:center;padding:9px 16px;font-size:.92rem;font-weight:600}.ob-bar a{margin-left:8px;text-decoration:underline}
.nav{top:0;z-index:20;background:color-mix(in srgb,var(--bg) 90%,transparent);backdrop-filter:blur(10px);border-bottom:1px solid var(--border);transition:box-shadow .2s}.nav.is-sticky{position:sticky}.nav.is-scrolled{box-shadow:0 8px 24px -16px rgba(15,23,42,.35)}
.nav .wrap{display:flex;justify-content:space-between;align-items:center;gap:16px;min-height:70px}.brand{display:flex;align-items:center;gap:10px;font:700 1.15rem var(--hf),sans-serif;text-decoration:none}
.mark{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;background:var(--primary);color:var(--ink)}.logo-pic{height:40px;width:auto;max-width:170px;object-fit:contain}
.nav nav{display:flex;align-items:center;gap:22px;flex-wrap:wrap}.nav .btn-sm{white-space:nowrap}.mnav{display:none;position:relative}.mnav summary{list-style:none;display:grid;place-content:center;gap:4px;width:42px;height:42px;border-radius:10px;border:1px solid var(--border);cursor:pointer}.mnav summary::-webkit-details-marker{display:none}.mnav summary::after,.mnav summary::before{content:none!important;display:none!important}.mnav summary{padding:0!important;margin:0!important;background:none}.nav details.mnav{border:0!important;padding:0!important;margin:0!important;background:none!important;box-shadow:none!important;border-radius:0!important;width:auto!important}.mnav__cta{display:none!important;margin-top:6px;text-align:center;justify-content:center}.mnav summary span{display:block;width:18px;height:2px;border-radius:2px;background:var(--text)}.mnav__list{position:absolute;right:0;top:calc(100% + 8px);z-index:30;display:grid;min-width:200px;padding:8px;border-radius:14px;background:var(--card);border:1px solid var(--border);box-shadow:0 18px 40px -16px rgba(15,23,42,.35)}.mnav__list a{padding:11px 12px;border-radius:10px;text-decoration:none;color:var(--text);font-weight:500}.mnav__list a[aria-current]{color:var(--primary);background:color-mix(in srgb,var(--primary) 10%,transparent)}.nav nav>a:not(.btn){text-decoration:none;color:var(--muted);font-weight:500}.nav nav>a[aria-current]{color:var(--primary)}
.s{padding:clamp(56px,8vw,96px) 0;position:relative}.bg-plain{background:var(--bg)}.bg-alt{background:var(--alt)}.bg-soft{background:var(--soft)}
.bg-brand{background:linear-gradient(135deg,var(--deep),var(--primary));--text:#fff;--muted:rgba(255,255,255,.85);--border:rgba(255,255,255,.35);--card:rgba(255,255,255,.1);color:#fff}.bg-brand .btn:not(.btn-light):not(.btn-ghost){background:#fff;color:#111827;border-color:#fff}.bg-brand .btn-ghost{color:#fff}
.bg-dark{background:#0b1020;--text:#f1f5f9;--muted:#a3adc2;--card:#141c33;--border:#24304d;color:#f1f5f9}
.cols{display:grid;gap:32px;align-items:start}.col{min-width:0;display:flex;flex-direction:column;gap:18px}.col>.w{min-width:0}
.eyebrow{display:inline-block;margin:0 0 14px;padding:6px 14px;border-radius:999px;font-size:.8rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--primary);background:var(--soft)}.bg-brand .eyebrow,.bg-dark .eyebrow{color:#fff;background:rgba(255,255,255,.14)}
.lead{font-size:1.15rem;color:var(--muted);margin:0 0 24px}.intro{color:var(--muted);text-align:center;max-width:640px;margin:-4px auto 0}.body,.rt{color:var(--muted)}.rt p{margin:0 0 .8em}.rt p:last-child{margin-bottom:0}.rt a{color:var(--primary)}.rt ul,.rt ol{padding-left:1.3em;margin:0 0 .8em}
.s-hero{padding:clamp(64px,10vw,120px) 0}.hero-center{text-align:center;max-width:860px}.hero-center .lead{margin-inline:auto;max-width:660px}.hero-center .actions{justify-content:center}.hero-split{display:grid;grid-template-columns:1.05fr .95fr;gap:48px;align-items:center}
.hero-cover{text-align:center;max-width:none;width:100%;padding:clamp(40px,8vw,90px) max(20px,(100% - 1140px)/2);background:linear-gradient(135deg,var(--deep),var(--primary)) center/cover}.s:has(.hero-cover){padding:0}.hero-cover h1,.hero-cover .lead{color:#fff}.hero-cover .lead{margin-inline:auto;max-width:660px}.hero-cover .actions{justify-content:center}.hero-cover .eyebrow{color:#fff;background:rgba(255,255,255,.16)}.hero-cover .btn-ghost{color:#fff;border-color:rgba(255,255,255,.5)}
.pic{border-radius:var(--r);overflow:hidden;aspect-ratio:4/3;background:var(--soft)}.pic img{width:100%;height:100%;object-fit:cover}.hero-pic{box-shadow:0 30px 60px -30px rgba(15,23,42,.45)}
.pic-fill{background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--primary) 45%,#fff));display:grid;place-items:center;color:var(--ink);font:800 5rem var(--hf),sans-serif}
.img-w{border-radius:var(--r);overflow:hidden}.img-w img{width:100%;height:auto}.fig{margin:0}.fig figcaption{margin-top:8px;font-size:.9rem;color:var(--muted);text-align:center}
.grid{display:grid;gap:20px;margin-top:36px}.g1{grid-template-columns:1fr}.g2{grid-template-columns:repeat(2,1fr)}.g3{grid-template-columns:repeat(3,1fr)}.g4{grid-template-columns:repeat(4,1fr)}
.card{padding:28px;border-radius:var(--r);background:var(--card);border:1px solid var(--border)}.card p{margin:0;color:var(--muted)}
.icon{width:56px;height:56px;border-radius:14px;display:grid;place-items:center;font-size:1.6rem;margin-bottom:14px;background:var(--soft);color:var(--primary)}
.ic{display:inline-grid;place-items:center;line-height:1}.ic svg{width:1.1em;height:1.1em;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.num{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;margin-bottom:14px;font-weight:800;background:var(--primary);color:var(--ink)}
.split{display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:center}.split.flip-side>:first-child{order:2}
.stats{display:grid;gap:20px;text-align:center}.stats b{display:block;font:800 clamp(2rem,4vw,2.8rem)/1.1 var(--hf),sans-serif;color:var(--primary)}.bg-brand .stats b,.bg-dark .stats b{color:#fff}.stats span{color:var(--muted)}
.plan{position:relative;display:flex;flex-direction:column;gap:6px}.plan.featured{border:2px solid var(--primary);box-shadow:0 24px 50px -28px var(--primary)}.badge{position:absolute;top:-12px;left:50%;transform:translateX(-50%);padding:4px 12px;border-radius:999px;background:var(--primary);color:var(--ink);font-size:.75rem;font-weight:700;white-space:nowrap}
.price{margin:0}.price b{font:800 2.4rem var(--hf),sans-serif;color:var(--text)}.price span{color:var(--muted)}.plan ul{margin:10px 0 18px;padding-left:20px;color:var(--muted);flex:1}.plan li{margin:4px 0}
.gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;margin-top:32px}.gallery figure{margin:0}.gallery figcaption{margin-top:8px;font-size:.9rem;color:var(--muted);text-align:center}
.team{text-align:center}.avatar{width:120px;height:120px;border-radius:50%;overflow:hidden;margin:0 auto 14px;background:var(--soft)}.avatar img{width:100%;height:100%;object-fit:cover}.avatar.pic-fill{font-size:2.6rem}.avatar.sm{width:48px;height:48px;margin:0;font-size:1rem}.person p{color:var(--muted);margin:0}
.tst{margin:0}.tst blockquote{margin:0 0 16px;font:500 1.08rem/1.6 var(--hf),sans-serif}.tst figcaption{display:flex;align-items:center;gap:12px}.tst figcaption b{display:block}.tst figcaption small{display:block}.stars{color:#f59e0b;letter-spacing:3px;margin-bottom:10px}
.logos-title{text-align:center;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:.82rem;margin:0}.logos{display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:36px;margin-top:22px}.logo-img{width:140px;height:60px;display:grid;place-items:center}.logo-img img{max-height:60px;width:auto;object-fit:contain;filter:grayscale(1);opacity:.75}
.faq{display:grid;gap:12px;margin-top:24px}details{padding:18px 22px;border-radius:14px;background:var(--card);border:1px solid var(--border)}summary{cursor:pointer;font-weight:700;list-style:none;display:flex;justify-content:space-between;gap:12px}summary::after{content:'+';color:var(--primary);font-size:1.3rem;line-height:1}details[open] summary::after{content:'−'}details>p,details>.rt{margin:10px 0 0;color:var(--muted)}
.embed{position:relative;aspect-ratio:16/9;border-radius:var(--r);overflow:hidden;margin-top:20px;background:#000}.embed iframe{position:absolute;inset:0;width:100%;height:100%;border:0}.map{aspect-ratio:21/9;background:var(--alt)}
.prose .rt{color:var(--muted)}.contact-grid{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start}.contact-grid .intro{text-align:left;margin:0 0 20px}.addr{color:var(--muted);margin-top:18px}
.form{display:grid;gap:12px}.form label{display:grid;gap:6px;font-weight:600;font-size:.9rem}.form input,.form textarea,.form select{padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:var(--bg);color:var(--text);font:inherit}.form input:focus,.form textarea:focus,.form select:focus{outline:2px solid var(--primary);outline-offset:1px}.hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
.iconbox{display:block}.iconbox:not(.lay-left) .iconbox__i{display:inline-grid;margin-bottom:12px}.iconbox.lay-left{display:flex;gap:12px}.iconbox.lay-left{flex-direction:row;align-items:flex-start;text-align:left}.iconbox__i{width:60px;height:60px;flex:none;border-radius:16px;display:grid;place-items:center;background:var(--soft);color:var(--primary);font-size:1.7rem}.iconbox h3{margin:0 0 6px}.iconbox .rt{color:var(--muted)}
.imagebox h3{margin:16px 0 6px}.imagebox p{margin:0}
.iconlist{list-style:none;margin:0;padding:0;display:grid;gap:10px}.iconlist li{display:flex;gap:10px;align-items:flex-start}.iconlist .ic{color:var(--primary);font-size:1.15rem;margin-top:.2em}
.counter b{display:block;font:800 clamp(2.2rem,4vw,3rem)/1.1 var(--hf),sans-serif;color:var(--primary)}.counter p{margin:6px 0 0;color:var(--muted)}.bg-brand .counter b,.bg-dark .counter b{color:#fff}
.bars{display:grid;gap:16px}.bar__top{display:flex;justify-content:space-between;font-weight:600;margin-bottom:6px}.bar__track{height:10px;border-radius:999px;background:var(--soft);overflow:hidden}.bar__track i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--primary),var(--secondary))}
.rating{display:flex;flex-wrap:wrap;align-items:center;gap:10px}.stars-v{font-size:1.4rem;letter-spacing:3px;background:linear-gradient(90deg,#f59e0b var(--v),#d1d5db var(--v));-webkit-background-clip:text;background-clip:text;color:transparent}
.socials{display:flex;flex-wrap:wrap;gap:10px}.socials a{width:42px;height:42px;display:grid;place-items:center;background:var(--sc);color:#fff;text-decoration:none;font:800 .9rem var(--bf),sans-serif;transition:transform .15s}.socials a:hover{transform:translateY(-3px)}.sh-round a{border-radius:50%}.sh-rounded a{border-radius:12px}.sh-square a{border-radius:2px}
.notice{padding:18px 20px;border-radius:14px;border-left:5px solid var(--nc);background:color-mix(in srgb,var(--nc) 10%,var(--card))}.notice b{display:block;margin-bottom:4px}.nt-info{--nc:#3b82f6}.nt-success{--nc:#16a34a}.nt-warning{--nc:#f59e0b}.nt-danger{--nc:#dc2626}
.hours{margin:0;display:grid}.hours div{display:flex;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px dashed var(--border)}.hours dt{font-weight:600}.hours dd{margin:0;color:var(--muted)}
.pricelist{list-style:none;margin:0;padding:0;display:grid;gap:16px}.pl-top{display:flex;align-items:baseline;gap:10px}.pl-top i{flex:1;border-bottom:2px dotted var(--border)}.pl-price{color:var(--primary)}.pricelist p{margin:2px 0 0;font-size:.92rem}
.tabs{display:flex;flex-wrap:wrap;gap:6px}.tabs>input{position:absolute;opacity:0;pointer-events:none}.tab-label{padding:10px 18px;border-radius:999px;cursor:pointer;font-weight:600;background:var(--alt);color:var(--muted)}.tab-panels{flex-basis:100%;margin-top:10px}.tab-panel{display:none;padding:22px;border-radius:var(--r);background:var(--card);border:1px solid var(--border)}
${Array.from({ length: 8 }, (_, i) => `.tabs>input:nth-of-type(${i + 1}):checked~.tab-panels>.tab-panel:nth-child(${i + 1}){display:block}.tabs>input:nth-of-type(${i + 1}):checked+label{background:var(--primary);color:var(--ink)}`).join('')}
.tabs>input:focus-visible+label{outline:2px solid var(--primary);outline-offset:2px}.tabs.is-edit{display:grid;gap:12px}.tabs.is-edit .tab-panel{display:block}.tab-edit .tab-label{display:inline-block;margin-bottom:8px;background:var(--primary);color:var(--ink)}
.car{position:relative}.ob-track{display:grid;grid-auto-flow:column;grid-auto-columns:calc((100% - (var(--per) - 1) * 20px) / var(--per));gap:20px;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;padding-bottom:4px}.ob-track::-webkit-scrollbar{display:none}.car__slide{scroll-snap-align:start}
.slide-pic{aspect-ratio:16/9}.car__nav{position:absolute;top:calc(50% - 40px);width:44px;height:44px;border-radius:50%;border:0;background:var(--card);color:var(--text);font-size:1.6rem;box-shadow:0 8px 20px rgba(15,23,42,.18);cursor:pointer}.car__nav.prev{left:-14px}.car__nav.next{right:-14px}
.car__dots{display:flex;justify-content:center;gap:8px;margin-top:14px}.car__dots button{width:10px;height:10px;padding:0;border:0;cursor:pointer;border-radius:50%;background:var(--border)}.car__dots button.is-on{background:var(--primary);width:24px;border-radius:999px}
.car-edit{display:grid;grid-template-columns:repeat(var(--per),1fr);gap:16px}
.flip{perspective:1200px;min-height:260px}.flip__in{position:relative;min-height:260px;transition:transform .7s;transform-style:preserve-3d}.flip:hover .flip__in,.flip:focus-within .flip__in{transform:rotateY(180deg)}
.flip__f,.flip__b{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;gap:8px;padding:26px;border-radius:var(--r);backface-visibility:hidden}
.flip__f{background:var(--card);border:1px solid var(--border)}.flip__f .ic{font-size:2.2rem;color:var(--primary)}.flip__b{transform:rotateY(180deg);background:linear-gradient(135deg,var(--deep),var(--primary));color:#fff}.flip__b h3,.flip__b p{color:#fff;margin:0}
.flip.is-edit .flip__in{display:grid;grid-template-columns:1fr 1fr;gap:10px;transform:none}.flip.is-edit .flip__f,.flip.is-edit .flip__b{position:relative;transform:none}
.ba{position:relative;border-radius:var(--r);overflow:hidden;user-select:none}.ba img{width:100%;display:block}.ba__b{position:absolute;inset:0;width:var(--pos);overflow:hidden;border-right:3px solid #fff}.ba__b img{width:auto;height:100%;max-width:none;object-fit:cover}
.ba input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:ew-resize;margin:0}.ba__l{position:absolute;top:12px;padding:4px 10px;border-radius:999px;background:rgba(0,0,0,.55);color:#fff;font-size:.8rem;font-weight:600}.ba__l.l{left:12px}.ba__l.r{right:12px}.ba-edit{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.cd{display:flex;flex-wrap:wrap;justify-content:center;gap:14px}.cd>div{min-width:86px;padding:16px 10px;border-radius:16px;background:var(--card);border:1px solid var(--border);text-align:center}.cd b{display:block;font:800 2.2rem/1 var(--hf),sans-serif;color:var(--primary)}.cd span{font-size:.8rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
.cd__done{display:none;flex-basis:100%;text-align:center;font-weight:700}.cd.is-done>div{display:none}.cd.is-done .cd__done{display:block}
.ahead__w{display:inline-grid;color:var(--primary)}.ahead__w>span{grid-area:1/1;opacity:0;transform:translateY(.4em);transition:.45s}.ahead__w>span.is-on{opacity:1;transform:none}
.divider{border:0;margin:8px auto;color:var(--border)}
.ob-pop{position:fixed;inset:0;z-index:100;display:grid;place-items:center;padding:20px;background:rgba(10,15,30,.6);opacity:0;transition:opacity .25s}.ob-pop.is-open{opacity:1}.ob-pop[hidden]{display:none}
.ob-pop__box{position:relative;width:min(460px,100%);max-height:90vh;overflow:auto;border-radius:var(--r);background:var(--card);color:var(--text);text-align:center;box-shadow:0 40px 80px -20px rgba(0,0,0,.5);transform:scale(.92);transition:transform .25s}.ob-pop.is-open .ob-pop__box{transform:none}
.ob-pop__box .pic{border-radius:0;aspect-ratio:16/8}.ob-pop__in{padding:26px}.ob-pop__in p{color:var(--muted)}.ob-pop__x{position:absolute;top:10px;right:10px;z-index:2;width:36px;height:36px;border-radius:50%;border:0;background:rgba(0,0,0,.45);color:#fff;font-size:1.3rem;cursor:pointer}
.ob-top{position:fixed;right:18px;bottom:60px;z-index:50;width:46px;height:46px;border-radius:50%;display:grid;place-items:center;background:var(--primary);color:var(--ink);text-decoration:none;font-size:1.3rem;box-shadow:0 10px 24px -8px var(--primary);opacity:0;pointer-events:none;transition:.25s}.ob-top.is-on{opacity:1;pointer-events:auto}
.ob-anim [data-anim]{opacity:0;transition:opacity .8s ease,transform .8s ease}.ob-anim [data-anim=fade-up]{transform:translateY(36px)}.ob-anim [data-anim=fade-down]{transform:translateY(-36px)}.ob-anim [data-anim=zoom-in]{transform:scale(.9)}.ob-anim [data-anim=slide-left]{transform:translateX(-48px)}.ob-anim [data-anim=slide-right]{transform:translateX(48px)}.ob-anim [data-anim].ob-in{opacity:1;transform:none}
.shop-grid{align-items:stretch}.pcard{display:flex;flex-direction:column;border-radius:var(--r);background:var(--card);border:1px solid var(--border);overflow:hidden}.pcard__pic{position:relative}.pcard__pic .pic{border-radius:0;aspect-ratio:1/1}.pcard__pic .pic-fill{font-size:3.4rem}
.pbadge{position:absolute;top:12px;left:12px;padding:4px 11px;border-radius:999px;background:var(--primary);color:var(--ink);font-size:.74rem;font-weight:700;letter-spacing:.02em}
.pcard__b{display:flex;flex-direction:column;gap:8px;padding:18px 18px 20px;flex:1}.pcard h3{margin:0;font-size:1.08rem}.pcard .muted{margin:0;font-size:.92rem;color:var(--muted)}
.pprice{margin:auto 0 0;display:flex;align-items:baseline;flex-wrap:wrap;gap:8px}.pprice b{font:800 1.25rem var(--hf),sans-serif;color:var(--text)}.pprice s{color:var(--muted);font-size:.92rem}
.pcard__ph{position:relative}.pcard__ph b{font:inherit}.pcard__ph span{position:absolute;left:50%;bottom:14px;transform:translateX(-50%);padding:7px 14px;border-radius:999px;background:rgba(255,255,255,.94);color:#111827;font:600 .8rem var(--bf),sans-serif;white-space:nowrap;box-shadow:0 6px 16px rgba(15,23,42,.18)}
.popt{display:grid;gap:7px}.popt__l{font-size:.74rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}.popt__v{display:flex;flex-wrap:wrap;gap:6px}
.popt__v button{min-width:42px;padding:7px 12px;border-radius:10px;border:1.5px solid var(--border);background:var(--bg);color:var(--text);font:600 .88rem var(--bf),sans-serif;cursor:pointer;transition:border-color .15s,background .15s}.popt__v button:hover{border-color:var(--primary)}.popt__v button:focus-visible{outline:2px solid var(--primary);outline-offset:2px}
.popt__v button[aria-pressed=true]{background:var(--primary);border-color:var(--primary);color:var(--ink)}.popt__v.is-need button{border-color:#dc2626;animation:ob-shake .35s}@keyframes ob-shake{25%{transform:translateX(-3px)}75%{transform:translateX(3px)}}
.pcard__add{width:100%}.pcard__add.is-added{background:var(--text);border-color:var(--text);color:var(--bg)}.pcard__sold{display:block;text-align:center;padding:9px;border-radius:var(--br);background:var(--alt);color:var(--muted);font-weight:700;font-size:.9rem}.pcard.is-sold .pic{filter:grayscale(.7);opacity:.7}
.pcard[hidden]{display:none}.prating{margin:0;color:#f59e0b;font-size:.86rem;letter-spacing:1px}.prating span{color:var(--muted);letter-spacing:0;margin-left:4px}.prating[hidden]{display:none}.pkind{font-size:.8rem;font-weight:600;color:var(--muted)}
.ob-myorders{margin:18px 0 0;text-align:center;font-size:.86rem}.ob-myorders a{color:var(--muted)}.ob-ship[hidden],[data-ship][hidden]{display:none!important}
.ob-reviews{display:grid;gap:10px;margin-top:6px;padding-top:12px;border-top:1px solid var(--border)}.ob-reviews h4{margin:0;font-size:1rem}.ob-review{display:grid;gap:2px;font-size:.9rem}.ob-review b{color:#f59e0b;letter-spacing:1px}.ob-review small{color:var(--muted)}.ob-review p{margin:0;color:var(--text)}.pcard__open{position:absolute;inset:0;z-index:1}.pcard__open:focus-visible{outline:3px solid var(--primary);outline-offset:-3px}.pcard__pic .pbadge,.pcard__n{z-index:2;pointer-events:none}
.pcard__n{position:absolute;right:12px;bottom:12px;padding:3px 9px;border-radius:999px;background:rgba(17,24,39,.72);color:#fff;font-size:.74rem;font-weight:700}
.pstock{margin:0;color:#c2410c;font-size:.84rem;font-weight:700}.pstock[hidden]{display:none}
.popt__v button.is-out{opacity:.45;text-decoration:line-through;cursor:not-allowed}.pcard__add.is-soldout{background:var(--alt);border-color:var(--alt);color:var(--muted);cursor:default}
.pfilter{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-top:28px}.pfilter__cats{display:flex;flex-wrap:wrap;gap:8px}
.pfilter__cats button{padding:8px 16px;border-radius:999px;border:1.5px solid var(--border);background:var(--bg);color:var(--text);font:600 .9rem var(--bf),sans-serif;cursor:pointer}.pfilter__cats button[aria-pressed=true]{background:var(--text);border-color:var(--text);color:var(--bg)}
.pfilter input{flex:0 1 260px;min-width:0;padding:10px 16px;border-radius:999px;border:1.5px solid var(--border);background:var(--bg);color:var(--text);font:inherit}.pfilter input:focus{outline:2px solid var(--primary);outline-offset:1px}
.pfilter+.grid{margin-top:20px}.pnone{text-align:center;color:var(--muted);margin:24px 0 0}.pnone[hidden]{display:none}
.ob-quick{position:fixed;inset:0;z-index:105;display:grid;place-items:center;padding:16px;background:rgba(10,15,30,.6);opacity:0;transition:opacity .25s}.ob-quick.is-open{opacity:1}.ob-quick[hidden]{display:none}
.ob-quick__box{position:relative;width:min(920px,100%);max-height:92vh;overflow:auto;overscroll-behavior:contain;border-radius:var(--r);background:var(--card);color:var(--text);box-shadow:0 40px 80px -20px rgba(0,0,0,.5);transform:translateY(16px);transition:transform .25s}.ob-quick.is-open .ob-quick__box{transform:none}
.ob-quick__x{position:absolute;top:12px;right:12px;z-index:3}.ob-quick__in{display:grid;grid-template-columns:1.1fr 1fr;gap:28px;padding:26px}.ob-quick__in .pcard__b{padding:8px 0;gap:12px}.ob-quick__in h3{font-size:1.6rem;margin:0;padding-right:40px}.ob-quick__in .pprice{margin:0}
.pcard__long{color:var(--muted);line-height:1.65;white-space:normal}.ob-gal{display:grid;gap:10px;align-content:start}.ob-gal__big,.ob-gal>.pic{width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:calc(var(--r) - 4px)}.ob-gal__big{background:var(--soft)}
.ob-gal__thumbs{display:flex;flex-wrap:wrap;gap:8px}.ob-gal__thumbs button{width:64px;height:64px;padding:0;border-radius:10px;overflow:hidden;border:2px solid transparent;cursor:pointer;background:var(--soft)}.ob-gal__thumbs button[aria-pressed=true]{border-color:var(--primary)}.ob-gal__thumbs img{width:100%;height:100%;object-fit:cover}
.ob-code label>span{display:flex;gap:8px}.ob-code input{flex:1;min-width:0;text-transform:uppercase}.ob-code small{display:block;margin-top:6px;color:#dc2626;font-size:.84rem}.ob-code small.is-ok{color:#15803d;font-weight:600}.ob-code small[hidden]{display:none}
.ob-phone{display:grid;gap:6px}.ob-phone--compact{gap:0}.optin .ob-phone__row input{padding:15px 16px;font-size:1rem}.ob-phone__l{font-weight:600;font-size:.9rem}.ob-phone__row{display:flex;gap:8px}.ob-phone__row input{flex:1;min-width:0}
.ob-dial{position:relative;display:block!important;flex:none}.ob-dial__face{display:flex;align-items:center;gap:6px;height:100%;min-height:46px;padding:0 30px 0 12px;border-radius:12px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-weight:600;white-space:nowrap}
.ob-dial__face::after{content:'';position:absolute;right:12px;top:50%;width:7px;height:7px;margin-top:-5px;border-right:2px solid var(--muted);border-bottom:2px solid var(--muted);transform:rotate(45deg)}
.form .ob-dial select{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;padding:0;border:0}.ob-dial:focus-within .ob-dial__face{outline:2px solid var(--primary);outline-offset:1px}
.ob-taxnote{color:var(--muted);font-size:.8rem;text-align:right}.ob-sum [hidden]{display:none}
@media (max-width:767px){.ob-quick{padding:0;align-items:end}.ob-quick__box{max-height:94vh;border-radius:var(--r) var(--r) 0 0}.ob-quick__in{grid-template-columns:1fr;gap:16px;padding:18px 18px 72px}.pfilter input{flex-basis:100%}}
.shop-off{display:table;margin:14px auto 0;padding:7px 16px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:.84rem;font-weight:600}
.nav-cart{position:relative;display:inline-flex!important;align-items:center;justify-content:center;flex:none;width:42px;height:42px;border-radius:50%;color:var(--text);text-decoration:none;background:var(--alt)}.nav-cart svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.nav-cart__n{position:absolute;top:-5px;right:-5px;min-width:20px;height:20px;padding:0 5px;border-radius:999px;background:var(--primary);color:var(--ink);font:700 .72rem/20px var(--bf),sans-serif;text-align:center}.nav-cart__n[hidden]{display:none}.nav-cart.is-bump{animation:ob-bump .45s}@keyframes ob-bump{40%{transform:scale(1.18)}}
.ob-cartfab{position:fixed;top:14px;right:14px;z-index:60;background:var(--card);box-shadow:0 8px 22px rgba(15,23,42,.2)}
.ob-cart{position:fixed;inset:0;z-index:110;display:flex;justify-content:flex-end;background:rgba(10,15,30,.55);opacity:0;transition:opacity .25s}.ob-cart.is-open{opacity:1}.ob-cart[hidden]{display:none}
.ob-cart__box{width:min(440px,100%);height:100%;overflow:auto;overscroll-behavior:contain;background:var(--card);color:var(--text);padding:20px 22px 80px;transform:translateX(40px);transition:transform .25s;box-shadow:-20px 0 60px -20px rgba(0,0,0,.4)}.ob-cart.is-open .ob-cart__box{transform:none}
.ob-cart__top{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}.ob-cart__top h3{margin:0}.ob-cart__x{width:38px;height:38px;border-radius:50%;border:0;background:var(--alt);color:var(--text);font-size:1.4rem;line-height:1;cursor:pointer}.ob-cart__empty{color:var(--muted)}
.ob-lines{list-style:none;margin:0 0 14px;padding:0}.ob-lines li{display:grid;grid-template-columns:1fr auto;align-items:center;gap:6px 12px;padding:12px 0;border-bottom:1px solid var(--border)}.ob-lines li>div b{display:block;font-weight:600}.ob-lines small{color:var(--muted)}.ob-lt{text-align:right;white-space:nowrap}
.ob-qty{display:inline-flex;align-items:center;gap:8px}.ob-qty button{width:30px;height:30px;border-radius:50%;border:1px solid var(--border);background:var(--bg);color:var(--text);cursor:pointer;font:600 1rem/1 var(--bf),sans-serif}.ob-rm{justify-self:end;border:0;background:none;padding:0;color:var(--muted);text-decoration:underline;cursor:pointer;font:inherit;font-size:.82rem}
.ob-sum{display:grid;gap:6px;padding:14px 16px;border-radius:14px;background:var(--alt)}.ob-sum>div{display:flex;justify-content:space-between;gap:12px;color:var(--muted)}.ob-sum .ob-total{padding-top:8px;border-top:1px solid var(--border);color:var(--text);font-size:1.1rem}
.ob-choice{border:0;margin:0;padding:0;display:grid;gap:8px}.ob-choice legend,.ob-choice>b{font-weight:700;font-size:.9rem;margin-bottom:6px;padding:0}.form .ob-choice label{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid var(--border);border-radius:12px;font-weight:500;cursor:pointer}.ob-choice label:has(input:checked){border-color:var(--primary);background:var(--soft)}.form .ob-choice input{width:auto;padding:0;accent-color:var(--primary)}
.ob-one{margin:0;padding:10px 14px;border-radius:12px;background:var(--alt);color:var(--muted);font-size:.92rem}.ob-place{width:100%}.ob-place[disabled]{opacity:.6;cursor:wait}.ob-small{color:var(--muted);text-align:center;font-size:.8rem}
.ob-toast{position:fixed;left:50%;bottom:56px;z-index:120;width:max-content;max-width:calc(100vw - 32px);text-align:center;transform:translate(-50%,16px);padding:10px 18px;border-radius:999px;background:#111827;color:#fff;font-weight:600;font-size:.9rem;opacity:0;transition:.25s;pointer-events:none}.ob-toast.is-on{opacity:1;transform:translate(-50%,0)}
.optin{gap:10px}.optin input{padding:15px 16px;font-size:1rem}.optin .btn{width:100%}.optin__note{color:var(--muted);text-align:center;font-size:.82rem}
.paybtn{display:flex;flex-direction:column;align-items:center;gap:8px}.paybtn.al-left{align-items:flex-start}.paybtn.al-right{align-items:flex-end}.paybtn.al-full .btn{width:100%}.paybtn .btn{font-size:1.2rem;padding:20px 38px;box-shadow:0 16px 34px -14px var(--primary)}.paybtn__no{color:var(--muted);font-size:.9rem;margin-top:4px}
[data-variant=b]{display:none}html.ob-v-b [data-variant=a]{display:none}html.ob-v-b [data-variant=b]{display:block}
html.ob-ab:not(.ob-ready) main{opacity:0;animation:ob-show 0s 1.2s forwards}@keyframes ob-show{to{opacity:1}}
.foot{padding:40px 0 28px;border-top:1px solid var(--border);background:var(--alt)}.rsvp{text-align:left}.rsvp fieldset{border:0;padding:0;margin:0;display:grid;gap:6px}.rsvp legend{font-weight:600;margin-bottom:6px;padding:0}.rsvp .rsvp-opt{display:flex;align-items:center;gap:10px;font-weight:500}.rsvp .rsvp-opt input{width:auto;margin:0;accent-color:var(--primary)}
.foot--mini{padding:20px 0 64px;text-align:center}.foot--mini .socials{justify-content:center}.foot .wrap{display:grid;gap:14px}.foot p{margin:0;color:var(--muted)}.foot .socials a{width:36px;height:36px;font-size:.8rem}.credit{font-size:.85rem}.credit a{color:var(--muted)}
@media (min-width:1025px){.hide-d{display:none!important}}@media (min-width:768px) and (max-width:1024px){.hide-t{display:none!important}}@media (max-width:767px){.hide-m{display:none!important}}
@media (max-width:1024px){.cols.c-4{grid-template-columns:1fr 1fr!important}.g4{grid-template-columns:1fr 1fr}}
@media (max-width:860px){.hero-split,.split,.contact-grid,.g3{grid-template-columns:1fr}.split.flip-side>:first-child{order:0}.hero-split{text-align:center}.hero-split .actions{justify-content:center}.nav nav>a:not(.btn){display:none}.mnav{display:block}.nav nav{gap:10px;flex-wrap:nowrap}}@media (max-width:480px){.nav .wrap{gap:10px}.nav .btn-sm{padding:8px 12px;font-size:.85rem}.brand{min-width:0}.brand>span:last-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.nav nav:has(.mnav)>.btn-sm{display:none}.mnav .mnav__cta{display:flex!important;color:var(--ink);background:var(--primary)}}
@media (max-width:767px){.cols{grid-template-columns:1fr!important}.g2,.g4{grid-template-columns:1fr}.stats{grid-template-columns:1fr 1fr}.ob-track{grid-auto-columns:100%}.car__nav{display:none}.car-edit,.ba-edit{grid-template-columns:1fr}}
${cssSafe(s.css || '')}`;
    };

    /* ================================================================ page rendering */
    const ANIMS = { '': 'None', 'fade-up': 'Fade up', 'fade-down': 'Fade down', 'fade-in': 'Fade in', 'zoom-in': 'Zoom in', 'slide-left': 'Slide from left', 'slide-right': 'Slide from right' };
    const advAttrs = (el, x, extraCls = '') => {
        const a = el.adv || {}, hide = a.hide || {};
        const c = [extraCls, cls(a.cls), hide.d ? (x.edit ? 'ed-hide-d' : 'hide-d') : '', hide.t ? (x.edit ? 'ed-hide-t' : 'hide-t') : '', hide.m ? (x.edit ? 'ed-hide-m' : 'hide-m') : ''].filter(Boolean).join(' ');
        return `class="${c}"${a.id ? ` id="${cls(a.id).replace(/\s/g, '-')}"` : ''}${a.anim && ANIMS[a.anim] && !x.edit ? ` data-anim="${a.anim}"${+a.delay ? ` data-delay="${+a.delay}"` : ''}` : ''}`;
    };
    const renderWidget = (w, x) => {
        const d = W[w.type]; if (!d) return '';
        return `<div ${advAttrs(w, x, `w w-${w.type} x-${w.id}`)}${x.edit ? ` data-w="${esc(w.id)}"` : ''}>${d.render(w, x, helpers(w, x))}</div>`;
    };
    const sectionBg = (s, i) => (['plain', 'alt', 'soft', 'brand', 'dark'].includes(s.bg) ? s.bg : (i % 2 ? 'alt' : 'plain'));
    const renderSection = (s, i, x) => {
        const full = isFull(s), types = s.cols.flatMap(c => c.widgets.map(w => w.type));
        const contactId = x.variant === 'b' ? 'contact-b' : 'contact', isContact = types.includes('contact') || types.includes('form');
        const anchor = isContact && !s.adv?.id ? ` id="${contactId}"` : '';
        const innerAnchor = isContact && s.adv?.id ? `<span id="${contactId}" class="anchor"></span>` : ''; // custom ID on the section: keep #contact working
        const first = full ? ` s-${s.cols[0].widgets[0].type}` : '';
        let inner;
        if (full) inner = s.cols[0].widgets.map(w => renderWidget(w, x)).join('');
        else {
            const grid = (has(LAYOUTS, s.layout) ? LAYOUTS[s.layout] : LAYOUTS[1])[0];
            inner = `<div class="wrap${s.width === 'full' ? ' full' : ''}"><div class="cols c-${s.cols.length}" style="grid-template-columns:${grid}">${s.cols.map(c =>
                `<div ${advAttrs(c, x, `col x-${c.id}`)}${x.edit ? ` data-c="${esc(c.id)}"` : ''}>${c.widgets.map(w => renderWidget(w, x)).join('') || (x.edit ? '<div class="ed-empty">＋ Drag a widget here<br><small>or pick one in the Add tab</small></div>' : '')}</div>`).join('')}</div></div>`;
        }
        return `<section ${advAttrs(s, x, `s${full ? ' s-full' : ''}${first} bg-${sectionBg(s, i)} x-${s.id}`)}${anchor}${x.edit ? ` data-s="${esc(s.id)}"` : ''}>${innerAnchor}${inner}</section>`;
    };
    const header = x => {
        const s = x.P.site, name = s.name || 'Your business', menu = x.P.pages.filter(p => p.menu);
        const logo = s.logo ? `<img src="${esc(s.logo)}" alt="${esc(name)}" class="logo-pic">` : `<span class="mark">${esc(name.trim()[0]?.toUpperCase() || 'A')}</span>`;
        const bar = s.announce?.on && s.announce.text ? `<div class="ob-bar"${x.edit ? ' data-hdr' : ''}><span${x.edit ? ' data-e="site|announce.text" data-ph="Announcement"' : ''}>${esc(s.announce.text)}</span>${s.announce.linkText ? ` <a href="${esc(href(s.announce.link, x))}">${esc(s.announce.linkText)}</a>` : ''}</div>` : '';
        if (s.bare) return bar; // simple page (link-in-bio, coming soon): no header or menu
        return `${bar}<header class="nav${s.sticky !== false ? ' is-sticky' : ''}"${x.edit ? ' data-hdr' : ''}><div class="wrap"><a class="brand" href="${x.edit ? '#' : esc(pageHref('index', x))}"><span${x.edit ? ' data-img="site|logo"' : ''}>${logo}</span><span${x.edit ? ' data-e="site|name" data-ph="Business name"' : ''}>${esc(name)}</span></a>
            <nav>${menu.length > 1 ? menu.map(p => `<a href="${x.edit ? '#' : esc(pageHref(p.slug, x))}"${x.edit ? ` data-goto="${esc(p.id)}"` : ''}${p === x.page ? ' aria-current="page"' : ''}>${esc(p.title)}</a>`).join('') : ''}
            ${shopOn(x) || (x.edit && x.P.kind === 'store') ? `<a class="nav-cart" href="#cart" data-cart-open aria-label="Your cart">${I(ICONS.cart)}<span class="nav-cart__n" data-cart-count hidden>0</span></a>` : ''}${s.navBtn && !goesNowhere(s.navLink, x) ? `<a class="btn btn-sm" href="${esc(href(s.navLink, x))}"${x.edit ? ' data-e="site|navBtn" data-ph="Button"' : ''}>${esc(s.navBtn)}</a>` : ''}${
            // phones: the page links fold into a ☰ menu (a <details>, so it works without scripts)
            menu.length > 1 ? `<details class="mnav"><summary aria-label="Menu"><span></span><span></span><span></span></summary><div class="mnav__list">${menu.map(p => `<a href="${x.edit ? '#' : esc(pageHref(p.slug, x))}"${x.edit ? ` data-goto="${esc(p.id)}"` : ''}${p === x.page ? ' aria-current="page"' : ''}>${esc(p.title)}</a>`).join('')}${
                // small phones: the header button moves into the menu, so the name keeps its room
                s.navBtn && !goesNowhere(s.navLink, x) ? `<a class="btn btn-sm mnav__cta" href="${esc(href(s.navLink, x))}">${esc(s.navBtn)}</a>` : ''}</div></details>` : ''}</nav></div></header>`;
    };
    const footer = x => {
        const s = x.P.site, name = s.name || 'Your business';
        const made = `Made with the <a href="https://owlvix.com/${KIT_PATH[x.P.kind] || 'landing-page-builder'}">free ${KITS[x.P.kind]?.label || (x.P.kind === 'funnel' ? 'funnel' : 'page')} builder by Owlvix</a>`;
        const social = Object.entries(s.social || {}).filter(([, u]) => /^https?:\/\//i.test(u || '')).map(([k, u]) => { const n = SOCIAL[k]; return n ? `<a href="${esc(u)}" style="--sc:${n[2]}" aria-label="${n[0]}" rel="noopener">${n[1]}</a>` : ''; }).join('');
        if (s.bare) return `<footer class="foot foot--mini"${x.edit ? ' data-ftr' : ''}><div class="wrap">${s.footer || x.edit ? `<p${x.edit ? ' data-e="site|footer" data-ph="A short line (optional)" data-ml' : ''}>${nl(s.footer)}</p>` : ''}
            ${social ? `<div class="socials sh-round">${social}</div>` : ''}<p class="credit">© ${new Date().getFullYear()} ${esc(name)} · ${made}</p></div></footer>`;
        return `<footer class="foot"${x.edit ? ' data-ftr' : ''}><div class="wrap"><div><b>${esc(name)}</b><p${x.edit ? ' data-e="site|footer" data-ph="A short line about your business (optional)" data-ml' : ''}>${nl(s.footer)}</p></div>
            ${social ? `<div class="socials sh-round">${social}</div>` : ''}<p class="credit">© ${new Date().getFullYear()} ${esc(name)} · ${made}</p></div></footer>`;
    };
    const popupHtml = (pg, x) => {
        const p = pg.popup; if (!p?.on) return '';
        return `<div class="ob-pop" data-popup="${esc(p.trigger || 'delay')}" data-delay="${Math.max(1, +p.delay || 6)}" hidden role="dialog" aria-modal="true" aria-label="${esc(p.title)}"><div class="ob-pop__box"><button type="button" class="ob-pop__x" data-popup-close aria-label="Close">×</button>
            ${p.image ? `<div class="pic"><img src="${esc(p.image)}" alt=""></div>` : ''}<div class="ob-pop__in"><h3>${esc(p.title)}</h3>${p.text ? `<p>${nl(p.text)}</p>` : ''}${p.btn ? `<a class="btn" href="${esc(href(p.link, x))}" data-popup-close>${esc(p.btn)}</a>` : ''}</div></div></div>`;
    };
    /** Does this page need the runtime script (animations, sliders, counters, popup…)? */
    const needsRuntime = (pg, P) => !!(P.kind === 'funnel' || (P.kind === 'store' && P.site.shopAction) || pg.popup?.on || P.site.backTop || (!P.site.bare && P.site.sticky !== false)
        || pg.sections.some(s => s.adv?.anim || s.cols.some(c => c.adv?.anim || c.widgets.some(w => w.adv?.anim || W[w.type]?.runtime))));
    const fontsLink = P => {
        const f = [...new Set([P.site.fonts?.heading, P.site.fonts?.body].filter(n => FONTS[n]))];
        const fam = (f.length ? f : ['Poppins', 'Inter']).map(n => `family=${encodeURIComponent(n)}:wght@400;500;600;700;800`).join('&');
        return `<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?${fam}&display=swap" rel="stylesheet">`;
    };
    /** Full HTML document. mode: edit | download | publish. opts: { origin, runtime (script URL), extraHead } */
    const render = (P, pg, mode, opts = {}) => {
        const x = { edit: mode === 'edit', mode, page: pg, P, origin: opts.origin || location.origin, siteBase: opts.siteBase }, s = P.site, name = s.name || 'Your business';
        if (P.kind === 'store') fixPids(P);
        // Store pages tell the cart (builder-runtime.js) which shop it is and how delivery is charged
        const shopAttrs = shopOn(x) ? ` data-shop="${esc(s.shopId || '')}" data-shop-cur="${esc(s.shop.currency)}" data-shop-fee="${s.shop.delivery ? MONEY(s.shop.fee) || 0 : 0}" data-shop-free="${MONEY(s.shop.freeOver) || 0}" data-shop-api="${esc(s.shopAction)}" data-shop-rules="${esc(shopData(s.shop))}"` : '';
        const hero = pg.sections.flatMap(sc => sc.cols.flatMap(c => c.widgets)).find(w => w.type === 'hero');
        const title = pg.seo.title.trim() || (pg.slug === 'index' ? `${name}${hero?.p.title ? ' — ' + hero.p.title : ''}` : `${pg.title} | ${name}`);
        const desc = pg.seo.description.trim() || hero?.p.text || '';
        const runtime = !x.edit && opts.runtime && needsRuntime(pg, P) ? `<script src="${esc(opts.runtime)}" defer></script>` : '';
        const ab = !x.edit && pg.ab?.on && pg.sectionsB?.length;
        // Funnel pages report views per step to owlvix (published only); A/B pages carry both versions
        const fs = P.kind === 'funnel' && mode === 'publish' && opts.statsUrl ? ` data-fs-url="${esc(opts.statsUrl)}" data-fs-site="${esc(s.publishSlug || '')}" data-fs-step="${esc(pg.slug)}"` : '';
        const xB = { ...x, variant: 'b', page: new Proxy(pg, { get: (t, k) => (k === 'sections' ? t.sectionsB : t[k]) }) }; // B's links look at B's own sections
        const body = ab
            ? `<div data-variant="a">${pg.sections.map((sc, i) => renderSection(sc, i, x)).join('\n')}</div><div data-variant="b">${pg.sectionsB.map((sc, i) => renderSection(sc, i, xB)).join('\n')}</div>`
            : pg.sections.map((sc, i) => renderSection(sc, i, x)).join('\n');
        return `<!doctype html>
<html lang="en"${ab ? ' class="ob-ab"' : ''}${fs}${shopAttrs}>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(title)}</title>
<meta name="description" content="${esc(desc)}">
<meta property="og:type" content="website"><meta property="og:title" content="${esc(title)}"><meta property="og:description" content="${esc(desc)}">
<meta name="theme-color" content="${hex(s.colors?.primary)}">
${fontsLink(P)}
<style id="site-css">${siteCss(P)}</style>
<style id="page-css">${pageCss(pg)}</style>
${runtime}${opts.extraHead || ''}
</head>
<body>
${header(x)}
<main>
${body}
</main>
${footer(x)}
${x.edit ? '' : popupHtml(pg, x) + shopHtml(x)}${!x.edit && s.backTop ? '<a href="#" class="ob-top" aria-label="Back to top">↑</a>' : ''}
</body>
</html>`;
    };

    /* ================================================================ clean-up of untrusted projects
       Projects come from localStorage, opened .owlvix.json files and saved templates — all editable by anyone. The
       editor canvas runs on owlvix.com, so every value is checked or replaced here before it can reach the page. */
    const STR = (v, max = 2000) => (typeof v === 'string' ? v.slice(0, max) : '');
    const ID = v => (typeof v === 'string' && /^[a-z0-9]{1,24}$/i.test(v) ? v : uid());
    const IMG = v => (typeof v === 'string' && (/^data:image\/(png|jpe?g|gif|webp|avif);base64,[a-z0-9+/=]+$/i.test(v) || /^https:\/\/[^\s"'<>()\\]+$/i.test(v)) ? v : '');
    const COLOR = v => (typeof v === 'string' && (/^#[0-9a-f]{3,8}$/i.test(v) || (/^g:/.test(v) && GLOBAL.includes(v.slice(2))) || /^rgba?\([\d\s.,%]+\)$/i.test(v)) ? v : '');
    const LINK = l => {
        if (!l || typeof l !== 'object' || !has(LINK_TYPES, l.to) && !has(FUNNEL_LINKS, l.to)) return { to: 'contact' };
        return { to: l.to, val: STR(l.val, 500) };
    };
    const FORM_URL = /^https?:\/\/[^\s"'<>]+\/f\/[a-z0-9]{16}$/, FORM_ID = /^[a-z0-9]{16}$/;
    /** Site settings tied to the signed-in account (forms, published address): kept on template changes and Undo. */
    const ACCOUNT_KEYS = ['formAction', 'leadAction', 'leadFormId', 'rsvpAction', 'rsvpFormId', 'shopAction', 'shopId', 'publishSlug', 'ref'];
    const SHOP_URL = /^https?:\/\/[^\s"'<>]+\/shop\/[a-z0-9]{16}$/;
    const PRICE = v => (MONEY(v) === null ? '' : String(MONEY(v)));
    /** A store's checkout settings (the server checks them again: App\Support\Shop::settings). */
    const SHOP = s => {
        s = s && typeof s === 'object' ? s : {};
        const d = defaultsShop(), out = {
            currency: CURRENCIES.includes(s.currency) ? s.currency : d.currency, delivery: s.delivery !== false, fee: s.fee === undefined ? d.fee : PRICE(s.fee), freeOver: s.freeOver === undefined ? d.freeOver : PRICE(s.freeOver),
            pickup: !!s.pickup, pickupText: STR(s.pickupText, 200), cod: s.cod === undefined ? d.cod : !!s.cod, bank: !!s.bank, bankText: STR(s.bankText, 1000), card: !!s.card, cardReady: !!s.cardReady,
            mode: s.mode === 'live' ? 'live' : 'test', phoneReq: s.phoneReq !== false, codes: !!s.codes, remind: s.remind !== false,
            zones: (Array.isArray(s.zones) ? s.zones : []).slice(0, 20).filter(z => z && typeof z === 'object').map(z => ({ name: STR(z.name, 60),
                countries: [...new Set((Array.isArray(z.countries) ? z.countries : []).map(c => String(c).toUpperCase()).filter(c => COUNTRIES.includes(c)))], fee: PRICE(z.fee), freeOver: PRICE(z.freeOver) })),
            taxRate: MONEY(s.taxRate) === null ? '' : String(Math.min(50, MONEY(s.taxRate))), taxIncl: s.taxIncl !== false, taxLabel: STR(s.taxLabel, 20) || 'Tax', taxShip: !!s.taxShip, thanks: STR(s.thanks, 300), whatsapp: STR(s.whatsapp, 30).replace(/[^\d+ ]/g, ''),
        };
        if (!out.delivery && !out.pickup) out.delivery = true;
        if (!out.cod && !out.bank && !out.card) out.cod = true;
        return out;
    };
    const STYLE_KEYS = {
        num: ['size', 'lh', 'ls', 'pt', 'pr', 'pb', 'pl', 'mt', 'mb', 'radius', 'bw', 'maxw', 'minH', 'opacity', 'gAngle', 'weight'],
        color: ['color', 'bg', 'g1', 'g2', 'bc', 'overlay'],
        enums: { family: null, transform: ['uppercase', 'lowercase', 'capitalize'], italic: ['1'], align: ['left', 'center', 'right', 'justify'], bs: ['solid', 'dashed', 'dotted'],
            shadow: Object.keys(SHADOWS), valign: ['top', 'middle', 'bottom'], bgType: ['none', 'color', 'gradient', 'image'], fixed: ['1'] },
    };
    const STYLES = st => {
        const out = {};
        if (!st || typeof st !== 'object') return out;
        Object.entries(st).forEach(([target, dev]) => {
            if (!/^[\w.*]{1,60}$/.test(target) || !dev || typeof dev !== 'object') return;
            out[target] = {};
            ['d', 't', 'm'].forEach(k => {
                const src = dev[k] && typeof dev[k] === 'object' ? dev[k] : {}, clean = {};
                STYLE_KEYS.num.forEach(n => { if (src[n] !== undefined && src[n] !== '' && isFinite(+src[n])) clean[n] = +src[n]; });
                STYLE_KEYS.color.forEach(n => { const c = COLOR(src[n]); if (c) clean[n] = c; });
                Object.entries(STYLE_KEYS.enums).forEach(([n, allowed]) => {
                    const v = src[n] === undefined ? undefined : String(src[n]);
                    if (v === undefined) return;
                    if (n === 'family' ? ['heading', 'body'].includes(v) || has(FONTS, v) : allowed.includes(v)) clean[n] = v;
                });
                const img = IMG(src.bgImg); if (img) clean.bgImg = img;
                out[target][k] = clean;
            });
        });
        return out;
    };
    const ADV = a => {
        a = a && typeof a === 'object' ? a : {};
        return { id: cls(STR(a.id, 40)).replace(/\s+/g, '-'), cls: cls(STR(a.cls, 120)), css: cssSafe(STR(a.css, 5000)), anim: has(ANIMS, a.anim) ? a.anim : '',
            delay: isFinite(+a.delay) ? Math.max(0, Math.min(5000, +a.delay)) : 0, hide: { d: !!a.hide?.d, t: !!a.hide?.t, m: !!a.hide?.m } };
    };
    /** Widget content: strings stay strings (escaped when rendered), images must be real images, links/selects/variants checked. */
    const CONTENT = (p, d) => {
        const out = {};
        const clean = (v, depth = 0) => {
            if (typeof v === 'string') return v.slice(0, 20000);
            if (typeof v === 'number' || typeof v === 'boolean') return v;
            if (Array.isArray(v) && depth < 6) return v.slice(0, 50).map(x2 => clean(x2, depth + 1)).filter(x2 => x2 !== undefined);
            if (v && typeof v === 'object' && depth < 6) { const o = {}; Object.keys(v).slice(0, 40).forEach(k => { if (/^[a-zA-Z_]\w{0,30}$/.test(k)) o[k] = clean(v[k], depth + 1); }); return o; }
            return undefined;
        };
        Object.assign(out, clean(p && typeof p === 'object' ? p : {}) || {});
        // before/after are photos only on the before/after slider (the animated headline uses them for text)
        const IMAGE_KEYS = ['image', 'src', 'photo', 'logo', ...(d.type === 'beforeafter' ? ['before', 'after'] : [])];
        const walk = o => { if (!o || typeof o !== 'object') return; Object.keys(o).forEach(k => { if (IMAGE_KEYS.includes(k)) o[k] = IMG(o[k]); else if (k === 'images') o[k] = (Array.isArray(o[k]) ? o[k] : []).map(IMG).filter(Boolean).slice(0, 6); else if (k === 'link' || k === 'link2') o[k] = LINK(o[k]); else walk(o[k]); }); };
        walk(out);
        (d.fields || []).forEach(f => { if (f.type === 'select' && !has(f.options, out[f.k])) out[f.k] = Object.keys(f.options)[0]; });
        return out;
    };
    const WIDGET = w => {
        if (!w || typeof w !== 'object' || !has(W, w.type)) return null;
        const d = W[w.type];
        return { id: ID(w.id), type: w.type, v: d.variants ? (has(d.variants, w.v) ? w.v : Object.keys(d.variants)[0]) : '', p: CONTENT(w.p, d), st: STYLES(w.st), adv: ADV(w.adv) };
    };
    const SECTION = s => {
        if (!s || typeof s !== 'object') return null;
        const cols = (Array.isArray(s.cols) ? s.cols : []).slice(0, 6).filter(c => c && typeof c === 'object')
            .map(c => ({ id: ID(c.id), st: STYLES(c.st), adv: ADV(c.adv), widgets: (Array.isArray(c.widgets) ? c.widgets : []).map(WIDGET).filter(Boolean) }));
        if (!cols.length) return null;
        return { id: ID(s.id), layout: has(LAYOUTS, s.layout) ? s.layout : String(Math.min(4, cols.length)), width: s.width === 'full' ? 'full' : 'boxed',
            bg: ['auto', 'plain', 'alt', 'soft', 'brand', 'dark'].includes(s.bg) ? s.bg : 'auto', st: STYLES(s.st), adv: ADV(s.adv), cols };
    };
    const normalize = P => {
        const s = P.site && typeof P.site === 'object' ? P.site : {}, d = defaultsSite();
        const site = {
            ...d, name: STR(s.name, 60) || d.name, logo: IMG(s.logo),
            colors: Object.fromEntries(GLOBAL.map(k => [k, /^#[0-9a-f]{6}$/i.test(s.colors?.[k] || '') ? s.colors[k] : d.colors[k]])),
            fonts: { heading: has(FONTS, s.fonts?.heading) ? s.fonts.heading : d.fonts.heading, body: has(FONTS, s.fonts?.body) ? s.fonts.body : d.fonts.body, base: isFinite(+s.fonts?.base) ? Math.max(13, Math.min(22, +s.fonts.base)) : d.fonts.base },
            theme: s.theme === 'dark' ? 'dark' : 'light', radius: ['round', 'soft', 'sharp'].includes(s.radius) ? s.radius : 'round', buttons: ['pill', 'rounded', 'square'].includes(s.buttons) ? s.buttons : 'pill',
            navBtn: STR(s.navBtn, 40), navLink: LINK(s.navLink), sticky: s.sticky !== false, bare: !!s.bare, backTop: !!s.backTop,
            announce: { on: !!s.announce?.on, text: STR(s.announce?.text, 200), linkText: STR(s.announce?.linkText, 40), link: LINK(s.announce?.link) },
            footer: STR(s.footer, 500), social: Object.fromEntries(Object.keys(SOCIAL).map(k => [k, /^https?:\/\/[^\s"'<>]+$/i.test(s.social?.[k] || '') ? s.social[k] : ''])),
            formAction: FORM_URL.test(s.formAction || '') ? s.formAction : '',
            leadAction: FORM_URL.test(s.leadAction || '') ? s.leadAction : '',
            leadFormId: FORM_ID.test(s.leadFormId || '') ? s.leadFormId : '',
            rsvpAction: FORM_URL.test(s.rsvpAction || '') ? s.rsvpAction : '',
            rsvpFormId: FORM_ID.test(s.rsvpFormId || '') ? s.rsvpFormId : '', ref: /^[a-z0-9]{6,20}$/.test(s.ref || '') ? s.ref : undefined,
            publishSlug: slugify(STR(s.publishSlug, 40)) || undefined, css: cssSafe(STR(s.css, 20000)),
            shop: SHOP(s.shop), shopAction: SHOP_URL.test(s.shopAction || '') ? s.shopAction : '', shopId: FORM_ID.test(s.shopId || '') ? s.shopId : '',
        };
        const seen = new Set();
        const pages = (P.pages || []).slice(0, 10).filter(pg => pg && typeof pg === 'object').map((pg, i) => {
            let slug = i === 0 ? 'index' : (pg.slug === 'index' ? 'index' : slugify(STR(pg.slug, 40))) || 'page-' + (i + 1);
            if (i > 0 && slug === 'index') slug = 'page-' + (i + 1);
            while (seen.has(slug)) slug += '-2';
            seen.add(slug);
            const pop = pg.popup && typeof pg.popup === 'object' ? pg.popup : {};
            return {
                id: ID(pg.id), slug, title: STR(pg.title, 40) || 'Page ' + (i + 1), menu: pg.menu !== false,
                seo: { title: STR(pg.seo?.title, 70), description: STR(pg.seo?.description, 170) },
                popup: { ...defaultsPopup(), on: !!pop.on, trigger: ['delay', 'exit', 'scroll', 'click'].includes(pop.trigger) ? pop.trigger : 'delay', delay: isFinite(+pop.delay) ? Math.max(1, Math.min(120, +pop.delay)) : 6,
                    title: STR(pop.title, 120), text: STR(pop.text, 500), btn: STR(pop.btn, 40), link: LINK(pop.link), image: IMG(pop.image) },
                type: has(STEP_TYPES, pg.type) ? pg.type : (P.kind === 'funnel' ? 'custom' : undefined),
                next: slugify(STR(pg.next, 40)), pay: /^https:\/\/[^\s"'<>]+$/i.test(pg.pay || '') ? pg.pay : '',
                ab: { on: !!pg.ab?.on }, sectionsB: Array.isArray(pg.sectionsB) ? pg.sectionsB.map(SECTION).filter(Boolean) : null,
                sections: (Array.isArray(pg.sections) ? pg.sections : []).map(SECTION).filter(Boolean),
            };
        });
        if (!pages.length) throw new Error('Not a project');
        // Ids must be unique on a page (a copied project could repeat them)
        pages.forEach(pg => { const ids = new Set(); [...pg.sections, ...(pg.sectionsB || [])].forEach(sc => [sc, ...sc.cols, ...sc.cols.flatMap(c => c.widgets)].forEach(o => { while (ids.has(o.id)) o.id = uid(); ids.add(o.id); })); });
        return { v: 3, kind: KINDS.includes(P.kind) ? P.kind : undefined, site, pages };
    };

    window.OwlB = {
        uid, esc, nl, clone, hex, slugify, getPath, setPath, sanitize, styleKey, classKey, cssSafe, safeUrl,
        ICONS, icon, SOCIAL, LINK_TYPES, FONTS, GLOBAL, SHADOWS, ANIMS, LAYOUTS, W, F, EXAMPLES, TEMPLATES,
        widget, column, section, sectionOf, isFull, migrate, newProject, newPage, reId, defaultsSite, defaultsPopup,
        CURRENCIES, COUNTRIES, countryName, money, MONEY, optionsOf, shopProducts, fixPids, productWidgets, variants, stockKeys, defaultsShop, payWays,
        FUNNELS, STEP_TYPES, FUNNEL_LINKS, newFunnel, KITS, KIT_PATH, newKit, rsvpSchema, rsvpWidget, ACCOUNT_KEYS, newStep, nextOf, normalize, normalizeSection: SECTION,
        render, renderSection, renderWidget, header, footer, siteCss, pageCss, needsRuntime, href, pageHref, fontsLink,
    };
})();
