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
    const cssSafe = s => String(s || '').replace(/</g, '');
    const cls = s => String(s || '').replace(/[^\w\s-]/g, '').trim();
    /** The style key for a content path: items.3.title → items.*.title (styles apply to every item). */
    const styleKey = path => path.replace(/\.\d+(?=\.|$)/g, '.*');
    const classKey = key => 'k-' + key.replace(/\.\*/g, '').replace(/\./g, '-');

    /** Rich text: only simple formatting survives (bold, italic, underline, links, lists, paragraphs, line breaks). */
    const ALLOWED = { B: 'b', STRONG: 'b', I: 'i', EM: 'i', U: 'u', A: 'a', UL: 'ul', OL: 'ol', LI: 'li', BR: 'br', P: 'p', DIV: 'p' };
    const sanitize = html => {
        const tpl = document.createElement('template'); tpl.innerHTML = String(html || '');
        const walk = node => [...node.childNodes].map(n => {
            if (n.nodeType === 3) return esc(n.nodeValue);
            if (n.nodeType !== 1) return '';
            const tag = ALLOWED[n.nodeName];
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
        if (x.mode === 'publish') return (x.siteBase || '/p/') + (x.P.site.publishSlug || 'site') + (slug === 'index' ? '' : '/' + slug);
        return (slug === 'index' ? 'index' : slug) + '.html';
    };
    const hasContact = pg => pg.sections.some(s => s.cols.some(c => c.widgets.some(w => w.type === 'contact' || w.type === 'form')));
    const href = (l, x) => {
        l = l || { to: 'contact' };
        const v = String(l.val || '').trim();
        switch (l.to) {
            case 'page': { const pg = x.P.pages.find(p => p.slug === v); return pg ? (x.edit ? '#' : pageHref(pg.slug, x)) : '#'; }
            case 'url': return /^https?:\/\//i.test(v) ? v : (v.startsWith('#') ? v : '#');
            case 'popup': return '#popup';
            case 'next': { const n = nextOf(x.page, x.P); return n && !x.edit ? pageHref(n.slug, x) : '#'; }
            case 'pay': return /^https:\/\//i.test(x.page.pay || '') ? x.page.pay : '#';
            case 'whatsapp': return 'https://wa.me/' + digits(v).replace(/^\+/, '');
            case 'phone': return 'tel:' + digits(v);
            case 'email': return 'mailto:' + v.replace(/[^\w.+@-]/g, '');
            default: {
                if (hasContact(x.page)) return '#contact';
                const other = x.P.pages.find(hasContact);
                return other && !x.edit ? pageHref(other.slug, x) + '#contact' : '#contact';
            }
        }
    };

    /* ================================================================ render helpers for one widget */
    const helpers = (w, x) => {
        const E = (path, ph = '', o = {}) => (x.edit ? ` data-e="${w.id}|${path}" data-ph="${esc(ph)}"${o.ml ? ' data-ml' : ''}${o.rich ? ' data-rich' : ''}` : '');
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
                return `<a class="${extra} ${K(textPath)}" href="${esc(href(getPath(w.p, linkPath), x))}"${E(textPath, 'Button')}>${esc(v)}</a>`;
            },
            pic: (path, alt, extra = 'pic', fallback = '') => {
                const src = getPath(w.p, path);
                const attr = x.edit ? ` data-img="${w.id}|${path}"` : '';
                if (src) return `<div class="${extra} ${K(path)}"${attr}><img src="${esc(src)}" alt="${esc(alt)}" loading="lazy"></div>`;
                if (x.edit) return `<div class="${extra} pic-empty"${attr}><span>＋ Add photo</span></div>`;
                return fallback;
            },
            icon: path => { const v = getPath(w.p, path); return v || x.edit ? `<span class="${K(path)}"${x.edit ? ` data-icon="${w.id}|${path}"` : ''}>${icon(v || '⭐')}</span>` : ''; },
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
    const plus7 = () => new Date(Date.now() + 7 * 864e5).toISOString().slice(0, 16);

    const W = {};
    const def = (type, d) => (W[type] = { type, ...d });

    /* ---------- basic widgets (go inside columns) */
    def('heading', { label: 'Heading', icon: 'H', group: 'basic', make: () => ({ text: 'Add your heading', tag: 'h2' }), fields: [F.select('tag', 'HTML tag', { h1: 'H1 — page title', h2: 'H2', h3: 'H3', h4: 'H4', p: 'Paragraph' })],
        render: (w, x, H) => H.t('text', w.p.tag || 'h2', 'hd', 'Heading', { keep: true }) });
    def('text', { label: 'Text', icon: '¶', group: 'basic', make: () => ({ html: '<p>Write your text here. Select words to make them <b>bold</b>, add links or lists.</p>' }), fields: [],
        render: (w, x, H) => H.t('html', 'div', 'rt', 'Text', { rich: true }) });
    def('button', { label: 'Button', icon: '⏺', group: 'basic', make: () => ({ text: 'Click here', link: { to: 'contact' }, kind: 'primary', size: 'md', align: 'left' }),
        fields: [F.link('link', 'Link'), F.select('kind', 'Style', { primary: 'Filled', ghost: 'Outline', light: 'White' }), F.select('size', 'Size', { sm: 'Small', md: 'Medium', lg: 'Large' }), F.select('align', 'Alignment', { left: 'Left', center: 'Centre', right: 'Right', full: 'Full width' })],
        render: (w, x, H) => `<div class="btn-wrap al-${w.p.align || 'left'}">${H.btn('text', 'link', `btn btn-${w.p.size || 'md'} ${w.p.kind === 'ghost' ? 'btn-ghost' : w.p.kind === 'light' ? 'btn-light' : ''}`)}</div>` });
    def('image', { label: 'Image', icon: '🖼', group: 'basic', make: () => ({ src: '', alt: '', caption: '', url: '' }), fields: [F.img('src', 'Image'), F.text('alt', 'Alt text (for Google & screen readers)'), F.text('url', 'Link (optional)', 'https://…')],
        render: (w, x, H) => { const img = H.pic('src', w.p.alt, 'img-w', ''); const u = safeUrl(w.p.url); return `<figure class="fig">${u && !x.edit ? `<a href="${esc(u)}">${img}</a>` : img}${H.t('caption', 'figcaption', '', 'Caption (optional)')}</figure>`; } });
    def('spacer', { label: 'Spacer', icon: '↕', group: 'basic', make: () => ({ h: 40 }), fields: [F.num('h', 'Height (px)', 0, 400, 4)],
        render: w => `<div class="spacer" style="height:${Math.max(0, +w.p.h || 0)}px"></div>` });
    def('divider', { label: 'Divider', icon: '—', group: 'basic', make: () => ({ style: 'solid', width: 100, weight: 2 }), fields: [F.select('style', 'Line', { solid: 'Solid', dashed: 'Dashed', dotted: 'Dotted', double: 'Double' }), F.num('width', 'Width (%)', 5, 100, 5), F.num('weight', 'Thickness (px)', 1, 12)],
        render: w => `<hr class="divider" style="border-top:${+w.p.weight || 2}px ${['solid', 'dashed', 'dotted', 'double'].includes(w.p.style) ? w.p.style : 'solid'} currentColor;width:${Math.min(100, +w.p.width || 100)}%">` });
    def('iconbox', { label: 'Icon box', icon: '★', group: 'basic', make: () => ({ icon: 'i:bolt', title: 'Fast results', text: 'Describe this benefit in one or two sentences.', layout: 'top' }),
        fields: [F.icon('icon', 'Icon'), F.select('layout', 'Icon position', { top: 'Above', left: 'Left' })],
        render: (w, x, H) => `<div class="iconbox lay-${w.p.layout || 'top'}"><div class="iconbox__i">${H.icon('icon')}</div><div>${H.t('title', 'h3', '', 'Title')}${H.t('text', 'div', 'rt', 'Text', { rich: true })}</div></div>` });
    def('imagebox', { label: 'Image box', icon: '🗂', group: 'basic', make: () => ({ src: '', title: 'Image box title', text: 'A short description of this item.' }), fields: [F.img('src', 'Image')],
        render: (w, x, H) => `<div class="imagebox">${H.pic('src', w.p.title, 'pic', '<div class="pic pic-fill"></div>')}${H.t('title', 'h3', '', 'Title')}${H.t('text', 'p', 'muted', 'Text', { ml: true })}</div>` });
    def('iconlist', { label: 'Icon list', icon: '☰', group: 'basic', make: () => ({ items: [{ icon: 'i:check', text: 'First point' }, { icon: 'i:check', text: 'Second point' }, { icon: 'i:check', text: 'Third point' }] }),
        fields: [F.list('items', 'Items', { icon: 'i:check', text: 'New point' }, [['icon', 'icon'], ['text', 'text']])],
        render: (w, x, H) => `<ul class="iconlist">${H.items().map((it, i) => `<li>${H.icon(`items.${i}.icon`)}${H.t(`items.${i}.text`, 'span', '', 'Text', { keep: true })}</li>`).join('')}</ul>` });
    def('video1', { label: 'Video', icon: '▶', group: 'basic', make: () => ({ url: '' }), fields: [F.text('url', 'YouTube link', 'https://www.youtube.com/watch?v=…')],
        render: (w, x) => { const id = ytId(w.p.url); return id ? `<div class="embed"><iframe src="https://www.youtube-nocookie.com/embed/${id}" title="Video" loading="lazy" allowfullscreen allow="encrypted-media; picture-in-picture"></iframe></div>` : (x.edit ? '<div class="embed pic-empty"><span>Paste a YouTube link in the Content tab</span></div>' : ''); } });

    /* ---------- pro widgets */
    def('counter', { label: 'Counter', icon: '123', group: 'pro', make: () => ({ num: '500', prefix: '', suffix: '+', label: 'Happy customers' }), fields: [F.text('num', 'Number'), F.text('prefix', 'Before the number'), F.text('suffix', 'After the number')],
        render: (w, x, H) => `<div class="counter"><b class="${H.K('num')}"><span>${esc(w.p.prefix)}</span><span${x.edit ? '' : ` data-count="${esc(parseFloat(String(w.p.num).replace(/,/g, '')) || 0)}"`}>${esc(w.p.num)}</span><span>${esc(w.p.suffix)}</span></b>${H.t('label', 'p', '', 'Label')}</div>`, runtime: true });
    def('progress', { label: 'Progress bars', icon: '▰', group: 'pro', make: () => ({ items: [{ label: 'Design', pct: 90 }, { label: 'Development', pct: 80 }, { label: 'Support', pct: 95 }] }),
        fields: [F.list('items', 'Bars', { label: 'Skill', pct: 75 }, [['label', 'text'], ['pct', 'number']])],
        render: (w, x, H) => `<div class="bars">${H.items().map((it, i) => `<div class="bar"><div class="bar__top">${H.t(`items.${i}.label`, 'span', '', 'Label', { keep: true })}<span>${Math.max(0, Math.min(100, +it.pct || 0))}%</span></div><div class="bar__track"><i style="width:${Math.max(0, Math.min(100, +it.pct || 0))}%"></i></div></div>`).join('')}</div>` });
    def('rating', { label: 'Star rating', icon: '⭐', group: 'pro', make: () => ({ stars: 4.8, label: 'Rated 4.8 from 200+ reviews' }), fields: [F.num('stars', 'Stars (0–5)', 0, 5, 0.1)],
        render: (w, x, H) => { const s = Math.max(0, Math.min(5, +w.p.stars || 0)); return `<div class="rating"><span class="stars-v" style="--v:${(s / 5) * 100}%" aria-label="${s} out of 5">★★★★★</span>${H.t('label', 'span', 'muted', 'Label')}</div>`; } });
    def('social', { label: 'Social icons', icon: '＠', group: 'pro', make: () => ({ shape: 'round', items: [{ net: 'facebook', url: '' }, { net: 'instagram', url: '' }, { net: 'linkedin', url: '' }] }),
        fields: [F.select('shape', 'Shape', { round: 'Circle', rounded: 'Rounded', square: 'Square' }), F.list('items', 'Networks', { net: 'x', url: '' }, [['net', 'social'], ['url', 'text']])],
        render: (w, x) => `<div class="socials sh-${w.p.shape || 'round'}">${(w.p.items || []).map(it => { const n = SOCIAL[it.net] || SOCIAL.facebook, u = safeUrl(it.url); return u || x.edit ? `<a href="${esc(u || '#')}" style="--sc:${n[2]}" title="${n[0]}" aria-label="${n[0]}" rel="noopener">${n[1]}</a>` : ''; }).join('')}</div>` });
    def('notice', { label: 'Notice box', icon: 'ℹ', group: 'pro', make: () => ({ kind: 'info', title: 'Good to know', text: 'Add an important note, offer or announcement here.' }), fields: [F.select('kind', 'Type', { info: 'Info', success: 'Success', warning: 'Warning', danger: 'Important' })],
        render: (w, x, H) => `<div class="notice nt-${w.p.kind || 'info'}">${H.t('title', 'b', '', 'Title')}${H.t('text', 'div', 'rt', 'Text', { rich: true })}</div>` });
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
            const n = `t${w.id}`;
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
            <div class="car__dots">${items.map((_, i) => `<a href="#" data-dot class="${i === 0 ? 'is-on' : ''}" aria-label="Slide ${i + 1}"></a>`).join('')}</div></div>`;
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
            return `<div class="cd"${x.edit ? '' : ` data-countdown="${esc(w.p.date)}"`}>${[['d', 'Days'], ['h', 'Hours'], ['m', 'Minutes'], ['s', 'Seconds']].map(([k, l]) => `<div><b data-u="${k}">${String(v[k]).padStart(2, '0')}</b><span>${l}</span></div>`).join('')}<p class="cd__done">${esc(w.p.done)}</p></div>`;
        }, runtime: true });
    def('animhead', { label: 'Animated headline', icon: 'Aa', group: 'pro', make: () => ({ before: 'We build', words: 'websites\nonline stores\nmobile apps', after: 'that grow your business', tag: 'h2' }),
        fields: [F.area('words', 'Rotating words (one per line)'), F.select('tag', 'HTML tag', { h1: 'H1', h2: 'H2', h3: 'H3' })],
        render: (w, x, H) => { const words = String(w.p.words || '').split('\n').map(s => s.trim()).filter(Boolean); const tag = ['h1', 'h2', 'h3'].includes(w.p.tag) ? w.p.tag : 'h2';
            return `<${tag} class="ahead hd">${H.t('before', 'span', '', 'Text before')} <span class="ahead__w"${x.edit ? '' : ' data-rotate'}>${x.edit ? `<span class="is-on">${esc(words.join(' / '))}</span>` : words.map((wd, i) => `<span class="${i === 0 ? 'is-on' : ''}">${esc(wd)}</span>`).join('')}</span> ${H.t('after', 'span', '', 'Text after')}</${tag}>`; }, runtime: true });
    def('form', { label: 'Contact form', icon: '✉', group: 'pro', make: () => ({ btn: 'Send message', phone: true }), fields: [F.text('btn', 'Button text'), F.bool('phone', 'Ask for a phone number')],
        render: (w, x) => formHtml(x, w.p) });

    def('optin', { label: 'Opt-in form', icon: '✉', group: 'funnel', make: () => ({ btn: 'Get instant access', name: true, phone: false, note: 'We respect your privacy. Unsubscribe any time.' }),
        fields: [F.text('btn', 'Button text'), F.bool('name', 'Ask for a name'), F.bool('phone', 'Ask for a phone number'), F.text('note', 'Small print under the button')],
        render: (w, x) => {
            const p = w.p;
            if (!x.P.site.leadAction) return x.edit ? '<div class="form pic-empty"><span>📥 Opt-in form — switch it on in the Content tab to collect leads (free)</span></div>' : '';
            const next = nextOf(x.page, x.P), nextUrl = next && x.mode === 'publish' ? new URL(pageHref(next.slug, x), x.origin).href : next ? pageHref(next.slug, x) : '';
            return `<form class="form optin" method="post" action="${esc(x.P.site.leadAction)}">
                ${p.name !== false ? '<input name="name" maxlength="100" autocomplete="name" placeholder="Your name" aria-label="Your name">' : ''}
                <input name="email" type="email" required maxlength="150" autocomplete="email" placeholder="Your best email" aria-label="Email">
                ${p.phone ? '<input name="phone" type="tel" maxlength="30" autocomplete="tel" placeholder="Phone (optional)" aria-label="Phone">' : ''}
                <input class="hp" name="_hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                <input type="hidden" name="_back" value="${esc(x.mode === 'publish' ? new URL(pageHref(x.page.slug, x), x.origin).href : '')}">
                <input type="hidden" name="_next" value="${esc(nextUrl)}"><input type="hidden" name="_site" value="${esc(x.P.site.publishSlug || '')}">
                <input type="hidden" name="_step" value="${esc(x.page.slug)}"><input type="hidden" name="_v" value="${x.variant === 'b' ? 'b' : 'a'}">
                <button class="btn btn-lg" type="submit">${esc(p.btn || 'Get instant access')}</button>${p.note ? `<small class="optin__note">🔒 ${esc(p.note)}</small>` : ''}</form>`;
        } });
    def('paybtn', { label: 'Buy button', icon: '💳', group: 'funnel', make: () => ({ text: 'Yes! Get it now', sub: '30-day money-back guarantee', align: 'center', no: '' }),
        fields: [F.text('sub', 'Small text under the button'), F.select('align', 'Alignment', { left: 'Left', center: 'Centre', right: 'Right', full: 'Full width' }), F.text('no', '“No thanks” link text (upsells)')],
        render: (w, x, H) => `<div class="paybtn al-${w.p.align || 'center'}"><a class="btn btn-lg ${H.K('text')}" href="${esc(href({ to: 'pay' }, x))}"${H.E('text', 'Button')}>${esc(w.p.text)}</a>${H.t('sub', 'small', 'muted', 'Small print')}${w.p.no ? `<a class="paybtn__no" href="${esc(href({ to: 'next' }, x))}">${esc(w.p.no)}</a>` : ''}</div>` });
    const formHtml = (x, p) => {
        if (x.P.site.formAction) return `<form class="form" method="post" action="${esc(x.P.site.formAction)}">
            <label>Your name<input name="name" required maxlength="100" autocomplete="name"></label>
            <label>Email<input name="email" type="email" required maxlength="150" autocomplete="email"></label>
            ${p.phone !== false ? '<label>Phone (optional)<input name="phone" type="tel" maxlength="30" autocomplete="tel"></label>' : ''}
            <label>Message<textarea name="message" required rows="4" maxlength="5000"></textarea></label>
            <input class="hp" name="_hp" tabindex="-1" autocomplete="off" aria-hidden="true">
            <input type="hidden" name="_back" value="${esc(x.mode === 'publish' ? new URL(pageHref(x.page.slug, x), x.origin).href : '')}">
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
            const v = w.v || 'center', bg = v === 'cover' && w.p.image ? ` style="background-image:linear-gradient(rgba(10,15,30,.62),rgba(10,15,30,.62)),url('${esc(w.p.image)}')"` : '';
            const initial = (x.P.site.name || 'A').trim()[0]?.toUpperCase() || 'A';
            const copy = `<div class="hero-copy">${H.t('eyebrow', 'p', 'eyebrow', 'Tagline')}${H.t('title', 'h1', '', 'Your headline')}${H.t('text', 'p', 'lead', 'A short description', { ml: true })}<div class="actions">${H.btn('btn', 'link')}${H.btn('btn2', 'link2', 'btn btn-ghost')}</div></div>`;
            const media = v === 'split' ? H.pic('image', x.P.site.name, 'pic hero-pic', `<div class="pic hero-pic pic-fill" aria-hidden="true">${esc(initial)}</div>`) : '';
            return `<div class="wrap hero-${v}"${bg}${v === 'cover' && x.edit ? ` data-img="${w.id}|image"` : ''}>${copy}${media}</div>`;
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
    const migrate = P => {
        if (P.v === 3) return P;
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
        sticky: true, backTop: false, announce: { on: false, text: 'Free delivery this week only!', link: { to: 'contact' } }, footer: '', social: {}, formAction: '', css: '',
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
    };
    const fill = (sec, p) => { Object.assign(sec.cols[0].widgets[0].p, p); return sec; };
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
            soon: ['Coming soon', () => { const s = cols('1', [wp('animhead', { before: 'Something', words: 'new\nexciting\nbig', after: 'is coming', tag: 'h1' }), wp('text', { html: '<p>We are launching soon. Leave your details and be the first to know.</p>' }), 'countdown', 'form']); s.st._ = { d: { align: 'center', minH: 640 } }; s.bg = 'soft'; return [s]; }],
        },
    };
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
    /** Give a copied section (and everything in it) new ids. */
    const reId = sec => { sec = clone(sec); sec.id = uid(); sec.cols.forEach(c => { c.id = uid(); c.widgets.forEach(w => (w.id = uid())); }); return sec; };

    /* ================================================================ styles → CSS */
    const FONTS = { Poppins: 'Poppins', Inter: 'Inter', 'DM Sans': 'DM Sans', Outfit: 'Outfit', Montserrat: 'Montserrat', 'Plus Jakarta Sans': 'Plus Jakarta Sans', Lato: 'Lato', 'Open Sans': 'Open Sans', Raleway: 'Raleway', 'Playfair Display': 'Playfair Display (elegant)', Merriweather: 'Merriweather (classic)', Lora: 'Lora (classic)', 'Space Grotesk': 'Space Grotesk (tech)', Caveat: 'Caveat (handwritten)' };
    const GLOBAL = ['primary', 'secondary', 'accent', 'text', 'bg'];
    const col = v => (String(v).startsWith('g:') ? `var(--${v.slice(2)})` : String(v).replace(/[;{}<]/g, ''));
    const SHADOWS = { sm: '0 4px 14px rgba(15,23,42,.08)', md: '0 14px 34px -12px rgba(15,23,42,.22)', lg: '0 30px 60px -24px rgba(15,23,42,.35)', glow: '0 0 0 1px var(--primary),0 18px 40px -16px var(--primary)' };
    const num = v => (v === '' || v === undefined || v === null || isNaN(+v) ? null : +v);
    /** One device's style values → CSS declarations. */
    const decl = (s, isSection) => {
        const o = [];
        if (s.family) o.push(`font-family:${s.family === 'heading' ? 'var(--hf)' : s.family === 'body' ? 'var(--bf)' : `'${cssSafe(s.family).replace(/'/g, '')}'`},sans-serif`);
        if (num(s.size) !== null) o.push(`font-size:${num(s.size)}px`);
        if (s.weight) o.push(`font-weight:${+s.weight || 400}`);
        if (num(s.lh) !== null) o.push(`line-height:${num(s.lh)}`);
        if (num(s.ls) !== null) o.push(`letter-spacing:${num(s.ls)}px`);
        if (s.transform) o.push(`text-transform:${cls(s.transform)}`);
        if (s.italic) o.push('font-style:italic');
        if (s.align) o.push(`text-align:${cls(s.align)}`);
        if (s.color) o.push(`color:${col(s.color)}`);
        const bgImg = s.bgImg ? `url("${String(s.bgImg).replace(/["\\]/g, '')}")` : '';
        if (s.bgType === 'gradient' && s.g1 && s.g2) o.push(`background:linear-gradient(${num(s.gAngle) ?? 135}deg,${col(s.g1)},${col(s.g2)})`);
        else if (s.bgType === 'image' && bgImg) o.push(`background:${s.overlay ? `linear-gradient(${col(s.overlay)},${col(s.overlay)}),` : ''}${bgImg} center/cover no-repeat${s.fixed ? ' fixed' : ''}`);
        else if (s.bg && s.bgType !== 'none') o.push(`background:${col(s.bg)}`);
        ['pt', 'pr', 'pb', 'pl'].forEach((k, i) => { if (num(s[k]) !== null) o.push(`padding-${['top', 'right', 'bottom', 'left'][i]}:${num(s[k])}px`); });
        ['mt', 'mb'].forEach((k, i) => { if (num(s[k]) !== null) o.push(`margin-${['top', 'bottom'][i]}:${num(s[k])}px`); });
        if (num(s.radius) !== null) o.push(`border-radius:${num(s.radius)}px;overflow:hidden`);
        if (num(s.bw) !== null && +s.bw > 0) o.push(`border:${num(s.bw)}px ${['solid', 'dashed', 'dotted'].includes(s.bs) ? s.bs : 'solid'} ${s.bc ? col(s.bc) : 'var(--border)'}`);
        if (s.shadow && SHADOWS[s.shadow]) o.push(`box-shadow:${SHADOWS[s.shadow]}`);
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
                const sel = `.x-${id}` + (target === '_' ? '' : ` .${classKey(target)}`);
                ['d', 't', 'm'].forEach(k => { const css = dev?.[k] && decl(dev[k], isSection && target === '_'); if (css) rules[k].push(`${sel}{${css}}`); });
                // Text alignment on a section/column also centres buttons and lists inside it
                ['d', 't', 'm'].forEach(k => { const a = dev?.[k]?.align; if (a && target === '_') rules[k].push(`.x-${id} .actions,.x-${id} .btn-wrap{justify-content:${a === 'center' ? 'center' : a === 'right' ? 'flex-end' : 'flex-start'}}`); });
            });
            if (adv?.css) custom.push(cssSafe(adv.css).replace(/\bselector\b/g, `.x-${id}`));
        };
        [...pg.sections, ...(pg.ab?.on ? pg.sectionsB || [] : [])].forEach(s => { visit(s.id, s.st, s.adv, true); s.cols.forEach(c => { visit(c.id, c.st, c.adv); c.widgets.forEach(w => visit(w.id, w.st, w.adv)); }); });
        return rules.d.join('') + (rules.t.length ? `${BP.t}{${rules.t.join('')}}` : '') + (rules.m.length ? `${BP.m}{${rules.m.join('')}}` : '') + custom.join('\n');
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
.nav nav{display:flex;align-items:center;gap:22px;flex-wrap:wrap}.nav nav>a:not(.btn){text-decoration:none;color:var(--muted);font-weight:500}.nav nav>a[aria-current]{color:var(--primary)}
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
.form{display:grid;gap:12px}.form label{display:grid;gap:6px;font-weight:600;font-size:.9rem}.form input,.form textarea{padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:var(--bg);color:var(--text);font:inherit}.form input:focus,.form textarea:focus{outline:2px solid var(--primary);outline-offset:1px}.hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
.iconbox{display:flex;flex-direction:column;gap:12px}.iconbox.lay-left{flex-direction:row;align-items:flex-start;text-align:left}.iconbox__i{width:60px;height:60px;flex:none;border-radius:16px;display:grid;place-items:center;background:var(--soft);color:var(--primary);font-size:1.7rem}.iconbox h3{margin:0 0 6px}.iconbox .rt{color:var(--muted)}
.s .x-al-c .iconbox__i{margin-inline:auto}.imagebox h3{margin:16px 0 6px}.imagebox p{margin:0}
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
.car__dots{display:flex;justify-content:center;gap:8px;margin-top:14px}.car__dots a{width:10px;height:10px;border-radius:50%;background:var(--border)}.car__dots a.is-on{background:var(--primary);width:24px;border-radius:999px}
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
.optin{gap:10px}.optin input{padding:15px 16px;font-size:1rem}.optin .btn{width:100%}.optin__note{color:var(--muted);text-align:center;font-size:.82rem}
.paybtn{display:flex;flex-direction:column;align-items:center;gap:8px}.paybtn.al-left{align-items:flex-start}.paybtn.al-right{align-items:flex-end}.paybtn.al-full .btn{width:100%}.paybtn .btn{font-size:1.2rem;padding:20px 38px;box-shadow:0 16px 34px -14px var(--primary)}.paybtn__no{color:var(--muted);font-size:.9rem;margin-top:4px}
[data-variant=b]{display:none}html.ob-v-b [data-variant=a]{display:none}html.ob-v-b [data-variant=b]{display:block}
html.ob-ab:not(.ob-ready) main{opacity:0;animation:ob-show 0s 1.2s forwards}@keyframes ob-show{to{opacity:1}}
.foot{padding:40px 0 28px;border-top:1px solid var(--border);background:var(--alt)}.foot .wrap{display:grid;gap:14px}.foot p{margin:0;color:var(--muted)}.foot .socials a{width:36px;height:36px;font-size:.8rem}.credit{font-size:.85rem}.credit a{color:var(--muted)}
@media (min-width:1025px){.hide-d{display:none!important}}@media (min-width:768px) and (max-width:1024px){.hide-t{display:none!important}}@media (max-width:767px){.hide-m{display:none!important}}
@media (max-width:1024px){.cols.c-4{grid-template-columns:1fr 1fr!important}.g4{grid-template-columns:1fr 1fr}}
@media (max-width:860px){.hero-split,.split,.contact-grid,.g3{grid-template-columns:1fr}.split.flip-side>:first-child{order:0}.hero-split{text-align:center}.hero-split .actions{justify-content:center}.nav nav>a:not(.btn){display:none}}
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
        return `<div ${advAttrs(w, x, `w w-${w.type} x-${w.id}`)}${x.edit ? ` data-w="${w.id}"` : ''}>${d.render(w, x, helpers(w, x))}</div>`;
    };
    const sectionBg = (s, i) => (s.bg && s.bg !== 'auto' ? s.bg : (i % 2 ? 'alt' : 'plain'));
    const renderSection = (s, i, x) => {
        const full = isFull(s), types = s.cols.flatMap(c => c.widgets.map(w => w.type));
        const anchor = !s.adv?.id && (types.includes('contact') || types.includes('form')) ? ' id="contact"' : '';
        const first = full ? ` s-${s.cols[0].widgets[0].type}` : '';
        let inner;
        if (full) inner = s.cols[0].widgets.map(w => renderWidget(w, x)).join('');
        else {
            const grid = (LAYOUTS[s.layout] || LAYOUTS[1])[0];
            inner = `<div class="wrap${s.width === 'full' ? ' full' : ''}"><div class="cols c-${s.cols.length}" style="grid-template-columns:${grid}">${s.cols.map(c =>
                `<div ${advAttrs(c, x, `col x-${c.id}`)}${x.edit ? ` data-c="${c.id}"` : ''}>${c.widgets.map(w => renderWidget(w, x)).join('') || (x.edit ? '<div class="ed-empty">＋ Drag a widget here<br><small>or pick one in the Add tab</small></div>' : '')}</div>`).join('')}</div></div>`;
        }
        return `<section ${advAttrs(s, x, `s${full ? ' s-full' : ''}${first} bg-${sectionBg(s, i)} x-${s.id}`)}${anchor}${x.edit ? ` data-s="${s.id}"` : ''}>${inner}</section>`;
    };
    const header = x => {
        const s = x.P.site, name = s.name || 'Your business', menu = x.P.pages.filter(p => p.menu);
        const logo = s.logo ? `<img src="${esc(s.logo)}" alt="${esc(name)}" class="logo-pic">` : `<span class="mark">${esc(name.trim()[0]?.toUpperCase() || 'A')}</span>`;
        const bar = s.announce?.on && s.announce.text ? `<div class="ob-bar"${x.edit ? ' data-hdr' : ''}><span${x.edit ? ' data-e="site|announce.text" data-ph="Announcement"' : ''}>${esc(s.announce.text)}</span>${s.announce.linkText ? ` <a href="${esc(href(s.announce.link, x))}">${esc(s.announce.linkText)}</a>` : ''}</div>` : '';
        return `${bar}<header class="nav${s.sticky !== false ? ' is-sticky' : ''}"${x.edit ? ' data-hdr' : ''}><div class="wrap"><a class="brand" href="${x.edit ? '#' : pageHref('index', x)}"><span${x.edit ? ' data-img="site|logo"' : ''}>${logo}</span><span${x.edit ? ' data-e="site|name" data-ph="Business name"' : ''}>${esc(name)}</span></a>
            <nav>${menu.length > 1 ? menu.map(p => `<a href="${x.edit ? '#' : pageHref(p.slug, x)}"${x.edit ? ` data-goto="${p.id}"` : ''}${p === x.page ? ' aria-current="page"' : ''}>${esc(p.title)}</a>`).join('') : ''}
            ${s.navBtn ? `<a class="btn btn-sm" href="${esc(href(s.navLink, x))}"${x.edit ? ' data-e="site|navBtn" data-ph="Button"' : ''}>${esc(s.navBtn)}</a>` : ''}</nav></div></header>`;
    };
    const footer = x => {
        const s = x.P.site, name = s.name || 'Your business';
        const social = Object.entries(s.social || {}).filter(([, u]) => /^https?:\/\//i.test(u || '')).map(([k, u]) => { const n = SOCIAL[k]; return n ? `<a href="${esc(u)}" style="--sc:${n[2]}" aria-label="${n[0]}" rel="noopener">${n[1]}</a>` : ''; }).join('');
        return `<footer class="foot"${x.edit ? ' data-ftr' : ''}><div class="wrap"><div><b>${esc(name)}</b><p${x.edit ? ' data-e="site|footer" data-ph="A short line about your business (optional)" data-ml' : ''}>${nl(s.footer)}</p></div>
            ${social ? `<div class="socials sh-round">${social}</div>` : ''}<p class="credit">© ${new Date().getFullYear()} ${esc(name)} · Made with the <a href="https://owlvix.com/landing-page-builder">free page builder by Owlvix</a></p></div></footer>`;
    };
    const popupHtml = (pg, x) => {
        const p = pg.popup; if (!p?.on) return '';
        return `<div class="ob-pop" data-popup="${esc(p.trigger || 'delay')}" data-delay="${Math.max(1, +p.delay || 6)}" hidden role="dialog" aria-modal="true" aria-label="${esc(p.title)}"><div class="ob-pop__box"><button type="button" class="ob-pop__x" data-popup-close aria-label="Close">×</button>
            ${p.image ? `<div class="pic"><img src="${esc(p.image)}" alt=""></div>` : ''}<div class="ob-pop__in"><h3>${esc(p.title)}</h3>${p.text ? `<p>${nl(p.text)}</p>` : ''}${p.btn ? `<a class="btn" href="${esc(href(p.link, x))}" data-popup-close>${esc(p.btn)}</a>` : ''}</div></div></div>`;
    };
    /** Does this page need the runtime script (animations, sliders, counters, popup…)? */
    const needsRuntime = (pg, P) => !!(P.kind === 'funnel' || pg.popup?.on || P.site.backTop || P.site.sticky !== false
        || pg.sections.some(s => s.adv?.anim || s.cols.some(c => c.adv?.anim || c.widgets.some(w => w.adv?.anim || W[w.type]?.runtime))));
    const fontsLink = P => {
        const f = [...new Set([P.site.fonts?.heading, P.site.fonts?.body].filter(n => FONTS[n]))];
        const fam = (f.length ? f : ['Poppins', 'Inter']).map(n => `family=${encodeURIComponent(n)}:wght@400;500;600;700;800`).join('&');
        return `<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?${fam}&display=swap" rel="stylesheet">`;
    };
    /** Full HTML document. mode: edit | download | publish. opts: { origin, runtime (script URL), extraHead } */
    const render = (P, pg, mode, opts = {}) => {
        const x = { edit: mode === 'edit', mode, page: pg, P, origin: opts.origin || location.origin, siteBase: opts.siteBase }, s = P.site, name = s.name || 'Your business';
        const hero = pg.sections.flatMap(sc => sc.cols.flatMap(c => c.widgets)).find(w => w.type === 'hero');
        const title = pg.seo.title.trim() || (pg.slug === 'index' ? `${name}${hero?.p.title ? ' — ' + hero.p.title : ''}` : `${pg.title} | ${name}`);
        const desc = pg.seo.description.trim() || hero?.p.text || '';
        const runtime = !x.edit && opts.runtime && needsRuntime(pg, P) ? `<script src="${esc(opts.runtime)}" defer></script>` : '';
        const ab = !x.edit && pg.ab?.on && pg.sectionsB?.length;
        // Funnel pages report views per step to owlvix (published only); A/B pages carry both versions
        const fs = P.kind === 'funnel' && mode === 'publish' && opts.statsUrl ? ` data-fs-url="${esc(opts.statsUrl)}" data-fs-site="${esc(s.publishSlug || '')}" data-fs-step="${esc(pg.slug)}"` : '';
        const body = ab
            ? `<div data-variant="a">${pg.sections.map((sc, i) => renderSection(sc, i, x)).join('\n')}</div><div data-variant="b">${pg.sectionsB.map((sc, i) => renderSection(sc, i, { ...x, variant: 'b' })).join('\n')}</div>`
            : pg.sections.map((sc, i) => renderSection(sc, i, x)).join('\n');
        return `<!doctype html>
<html lang="en"${ab ? ' class="ob-ab"' : ''}${fs}>
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
${x.edit ? '' : popupHtml(pg, x)}${!x.edit && s.backTop ? '<a href="#" class="ob-top" aria-label="Back to top">↑</a>' : ''}
</body>
</html>`;
    };

    window.OwlB = {
        uid, esc, nl, clone, hex, slugify, getPath, setPath, sanitize, styleKey, classKey, cssSafe, safeUrl,
        ICONS, icon, SOCIAL, LINK_TYPES, FONTS, GLOBAL, SHADOWS, ANIMS, LAYOUTS, W, F, EXAMPLES, TEMPLATES,
        widget, column, section, sectionOf, isFull, migrate, newProject, newPage, reId, defaultsSite, defaultsPopup,
        FUNNELS, STEP_TYPES, FUNNEL_LINKS, newFunnel, newStep, nextOf,
        render, renderSection, renderWidget, header, footer, siteCss, pageCss, needsRuntime, href, pageHref, fontsLink,
    };
})();
