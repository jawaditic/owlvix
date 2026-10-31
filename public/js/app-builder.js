/* Owlvix free mobile app builder (/mobile-app-builder). The app is designed here as native screens made of blocks
   (banner, text, list, gallery, contact…) and optionally pages of the owner's website, and kept in this browser
   until saved. Saving (/builder/apps, AppBuilderController) publishes the screens straight to phones that already
   have the app; building (GitHub Actions) makes the APK/AAB. The phone preview copies the app's native look
   (app-shell/blocks.js). Icons are drawn here on a canvas as 1024×1024 PNGs: the full icon, and the "foreground"
   (logo only, smaller) for Android's round/squircle icons and the splash. */
(() => {
    const root = document.querySelector('[data-ab]');
    if (!root) return;
    const $ = (s, el = root) => el.querySelector(s), $$ = (s, el = root) => [...el.querySelectorAll(s)];
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const track = (e, d = {}) => window.track?.(e, d);
    const ask = (title, text, ok, danger = false) => window.sweet({ icon: danger ? 'warning' : 'question', title, text, confirm: ok, danger });
    const ICONS = JSON.parse(document.getElementById('ab-icons')?.textContent || '{}');
    const ICON_NAMES = { home: 'Home', grid: 'Menu', store: 'Shop', cart: 'Cart', tag: 'Offers', food: 'Food', calendar: 'Book', image: 'Gallery', play: 'Videos', book: 'Blog', search: 'Search', heart: 'Saved', star: 'Reviews', bell: 'News', chat: 'Chat', phone: 'Call', mail: 'Email', location: 'Find us', user: 'Account', info: 'About', globe: 'Website' };
    // Drawings for the block menu only (the tab icons come from app-shell/icons.json)
    const BLOCK_ICONS = { heading: 'M6 4v16M18 4v16M6 12h12', text: 'M4 6h16M4 10h16M4 14h16M4 18h10', button: 'M3 8.5A2.5 2.5 0 0 1 5.5 6h13A2.5 2.5 0 0 1 21 8.5v7a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 15.5zM8 12h8', divider: 'M3 12h18M7 7h10M7 17h10', list: 'M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01', gallery: 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z' };
    const svgIcon = (name, color, size = 22) => `<svg viewBox="0 0 24 24" width="${size}" height="${size}" fill="none" stroke="${color}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${ICONS[name] || BLOCK_ICONS[name] || ICONS.home}"/></svg>`;
    const STORE = 'owlvix-app-builder';
    const MAX_TABS = 5, MAX_BLOCKS = 40, MAX_PENDING = 9e6; // MAX_PENDING: new pictures (as text) before a save is needed

    /* ------------------------------------------------ blocks: what the builder offers and their starting values */
    const BLOCKS = {
        hero: { name: 'Banner', icon: 'image', make: () => ({ title: 'Welcome', text: 'Tell people what you do in a line or two.', image: null, button: { label: '', action: { kind: '', value: '' } } }) },
        heading: { name: 'Heading', icon: 'heading', make: () => ({ text: 'Heading', align: 'left' }) },
        text: { name: 'Text', icon: 'text', make: () => ({ text: '', align: 'left' }) },
        image: { name: 'Picture', icon: 'image', make: () => ({ image: null, caption: '', round: true, action: { kind: '', value: '' } }) },
        button: { name: 'Button', icon: 'button', make: () => ({ label: 'Call us', style: 'solid', action: { kind: 'tel', value: '' } }) },
        list: { name: 'List / menu / prices', icon: 'list', make: () => ({ title: '', items: [{ title: 'Item', text: '', price: '', image: null, action: { kind: '', value: '' } }] }) },
        gallery: { name: 'Gallery', icon: 'gallery', make: () => ({ images: [], columns: 2 }) },
        contact: { name: 'Contact', icon: 'phone', make: () => ({ title: 'Get in touch', phone: '', whatsapp: '', email: '', address: '', website: '' }) },
        hours: { name: 'Opening hours', icon: 'calendar', make: () => ({ title: 'Opening hours', rows: [{ day: 'Mon – Fri', time: '9:00 – 17:00' }, { day: 'Sat – Sun', time: 'Closed' }] }) },
        video: { name: 'YouTube video', icon: 'play', make: () => ({ url: '', title: '' }) },
        divider: { name: 'Divider', icon: 'divider', make: () => ({}) },
    };
    const ACTIONS = { '': 'Nothing', tab: 'Open a tab', url: 'Open a website', tel: 'Call', whatsapp: 'WhatsApp', email: 'Email', map: 'Show on a map' };
    const ACTION_HINT = { url: 'https://…', tel: '+1 555 123 4567', whatsapp: 'Full number, e.g. +44 7700 900123', email: 'you@example.com', map: 'Address or place name' };
    const block = type => ({ type, ...BLOCKS[type].make() });
    const starter = () => [
        { label: 'Home', icon: 'home', type: 'screen', path: '', blocks: [block('hero'), { type: 'heading', text: 'About us', align: 'left' },
            { type: 'text', text: 'Write a few lines about your business: what you offer and why people love it.', align: 'left' },
            { type: 'button', label: 'Contact us', style: 'solid', action: { kind: 'tab', value: 2 } }] },
        { label: 'Services', icon: 'grid', type: 'screen', path: '', blocks: [{ type: 'list', title: 'What we offer', items: [
            { title: 'First service', text: 'A short description', price: '$20', image: null, action: { kind: '', value: '' } },
            { title: 'Second service', text: 'A short description', price: '$35', image: null, action: { kind: '', value: '' } }] }] },
        { label: 'Contact', icon: 'chat', type: 'screen', path: '', blocks: [block('contact'), block('hours')] },
    ];

    const fresh = () => ({
        id: null, dirty: true, iconDirty: true, base: '',
        app: { name: '', package: '', url: '', version_name: '1.0.0', external_links: 'browser', tabs: starter(),
            colors: { primary: '#16a34a', bar: '#ffffff', icon: '#16a34a', splash: '#ffffff' },
            offline: { title: '', text: '', button: '' } },
        icon: { mode: 'letter', letter: '', image: null, zoom: 70, fill: false },
    });
    let D;
    try { D = JSON.parse(localStorage.getItem(STORE) || 'null'); } catch {}
    if (!D?.app?.colors) D = fresh();
    // Designs kept from the website-only builder: their tabs were website pages
    D.app.tabs = (D.app.tabs || []).map(t => ({ label: t.label || '', icon: t.icon || 'home', type: t.type || 'web', path: t.path || '', blocks: Array.isArray(t.blocks) ? t.blocks : [] }));
    if (!D.app.tabs.length) D.app.tabs = starter();
    D.base ||= '';
    const A = () => D.app;
    const save = () => {
        try { localStorage.setItem(STORE, JSON.stringify(D)); }
        catch { try { localStorage.setItem(STORE, JSON.stringify({ ...D, icon: { ...D.icon, image: null } })); } catch {} } // full (pictures): the design stays on this page until it's saved
    };
    let view = 'app', activeTab = 0;
    const openBlocks = new WeakSet(); // block cards shown open in the editor

    /* ------------------------------------------------ helpers */
    const isDark = hex => { const [r, g, b] = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16)); return r * 0.299 + g * 0.587 + b * 0.114 < 150; };
    const ink = hex => (isDark(hex) ? '#ffffff' : '#0f172a');
    const slug = s => String(s || '').toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '').slice(0, 30);
    const siteUrl = () => { try { const u = new URL(A().url); return u.protocol === 'https:' ? u : null; } catch { return null; } };
    /** A website tab's full address: its path is relative to the website address. */
    const tabUrl = t => {
        const base = siteUrl(); if (!base) return '';
        const p = (t.path || '').trim();
        if (/^https:\/\//i.test(p)) return p;
        const r = base.href.replace(/[?#].*$/, '').replace(/\/$/, '');
        return p ? r + '/' + p.replace(/^\/+/, '') : base.href;
    };
    const initials = () => (D.icon.letter || A().name.split(/\s+/).filter(Boolean).map(w => w[0]).join('').slice(0, 2) || 'A').toUpperCase();
    const imgSrc = img => (img?.src ? (img.src.startsWith('data:') ? img.src : D.base + img.src) : '');
    /** Reads "items.2.title" from an object. */
    const getPath = (o, path) => path.split('.').reduce((x, k) => (x == null ? x : x[k]), o);
    const setPath = (o, path, v) => { const ks = path.split('.'), last = ks.pop(); const t = ks.reduce((x, k) => (x[k] ??= /^\d+$/.test(k) ? [] : {}), o); t[last] = v; };
    const eachImage = fn => A().tabs.forEach(t => (t.blocks || []).forEach(b => {
        if (b.image) fn(b.image); if (b.items) b.items.forEach(i => i.image && fn(i.image)); if (b.images) b.images.forEach(fn);
    }));
    const pendingBytes = () => { let n = 0; eachImage(i => { if (i.src?.startsWith('data:')) n += i.src.length; }); return n; };
    const youtubeId = url => { const m = String(url || '').match(/(?:youtu\.be\/|[?&]v=|\/(?:shorts|embed|live)\/)([A-Za-z0-9_-]{11})/); return m ? m[1] : ''; };

    /* ------------------------------------------------ icon drawing */
    let logo = null; // the uploaded picture as an Image
    const loadLogo = () => new Promise(res => {
        if (!D.icon.image) { logo = null; return res(); }
        const img = new Image(); img.onload = () => { logo = img; res(); }; img.onerror = () => { logo = null; res(); }; img.src = D.icon.image;
    });
    /** Draws the icon on a canvas of size n. foreground: logo only, on transparent, inside Android's safe zone. */
    const drawIcon = (n, foreground = false) => {
        const c = document.createElement('canvas'); c.width = c.height = n;
        const x = c.getContext('2d'), col = A().colors.icon;
        if (!foreground) { x.fillStyle = col; x.fillRect(0, 0, n, n); }
        const scale = foreground ? 0.6 : 1; // Android crops adaptive icons to a circle or squircle: keep the logo in the middle
        if (D.icon.mode === 'image' && logo) {
            if (D.icon.fill && !foreground) {
                const r = Math.max(n / logo.width, n / logo.height);
                x.drawImage(logo, (n - logo.width * r) / 2, (n - logo.height * r) / 2, logo.width * r, logo.height * r);
            } else {
                const box = n * (D.icon.fill ? 1 : D.icon.zoom / 100) * scale, r = Math.min(box / logo.width, box / logo.height);
                x.drawImage(logo, (n - logo.width * r) / 2, (n - logo.height * r) / 2, logo.width * r, logo.height * r);
            }
        } else {
            const t = initials();
            x.fillStyle = ink(col);
            x.font = `700 ${Math.round(n * (t.length > 1 ? 0.4 : 0.5) * scale)}px Inter, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif`;
            x.textAlign = 'center'; x.textBaseline = 'middle';
            x.fillText(t, n / 2, n / 2 + n * 0.03 * scale);
        }
        return c;
    };
    /** Rounded preview of the icon on a small canvas. */
    const paintIcon = (canvas, radius = 0.22) => {
        const n = canvas.width, x = canvas.getContext('2d');
        x.clearRect(0, 0, n, n); x.save();
        x.beginPath(); x.roundRect(0, 0, n, n, n * radius); x.clip();
        x.drawImage(drawIcon(n * 2), 0, 0, n, n); x.restore();
    };
    /** Loads a picture file and shrinks it to at most `max` px a side. */
    const loadPicture = (file, min) => new Promise((res, rej) => {
        if (!/^image\/(png|jpeg|webp)$/.test(file.type)) return rej(new Error('Please pick a PNG, JPG or WebP picture.'));
        if (file.size > 15e6) return rej(new Error('That picture is too big. Please use one under 15 MB.'));
        const img = new Image(), url = URL.createObjectURL(file);
        img.onload = () => { URL.revokeObjectURL(url); if (img.width < min || img.height < min) return rej(new Error(`That picture is too small. Please use one at least ${min} pixels wide.`)); res(img); };
        img.onerror = () => { URL.revokeObjectURL(url); rej(new Error('We couldn’t read that picture.')); };
        img.src = url;
    });
    const shrink = (img, max, type, quality) => {
        const r = Math.min(1, max / Math.max(img.width, img.height)), c = document.createElement('canvas');
        c.width = Math.round(img.width * r); c.height = Math.round(img.height * r);
        const x = c.getContext('2d');
        if (type === 'image/jpeg') { x.fillStyle = '#ffffff'; x.fillRect(0, 0, c.width, c.height); } // JPEG has no see-through
        x.drawImage(img, 0, 0, c.width, c.height);
        return { url: c.toDataURL(type, quality), w: c.width, h: c.height };
    };
    /** The icon logo: PNG, up to 1024 px. */
    const readLogo = async file => shrink(await loadPicture(file, 192), 1024, 'image/png').url;
    /**
     * A picture for a screen: up to 1280 px; JPEG (small) unless it's a PNG that stays small (logos with see-through
     * parts). Named like the server names it (first 20 hex of its SHA-256), so after saving it's known by that name.
     */
    const readPhoto = async file => {
        const img = await loadPicture(file, 64);
        let out = file.type === 'image/png' ? shrink(img, 1280, 'image/png') : null;
        if (!out || out.url.length > 900e3) out = shrink(img, 1280, 'image/jpeg', 0.82);
        const bytes = Uint8Array.from(atob(out.url.split(',')[1]), ch => ch.charCodeAt(0));
        const hash = [...new Uint8Array(await crypto.subtle.digest('SHA-256', bytes))].map(b => b.toString(16).padStart(2, '0')).join('').slice(0, 20);
        return { src: out.url, name: hash + (out.url.startsWith('data:image/png') ? '.png' : '.jpg'), w: out.w, h: out.h };
    };

    /* ------------------------------------------------ preview: the app's native look (app-shell/blocks.js) */
    const pv = {
        tap: (a, html, cls = '') => { const t = a?.kind && (a.kind === 'tab' || String(a.value ?? '').trim()) ? a : null; return t ? `<button type="button" class="ab-n-tap ${cls}" data-pact="${esc(JSON.stringify(t))}">${html}</button>` : `<div class="${cls}">${html}</div>`; },
        chev: '<i class="ab-n-chev" aria-hidden="true">›</i>',
    };
    const blockHtml = (b, c) => {
        const on = ink(c.primary);
        switch (b.type) {
            case 'hero': {
                const img = imgSrc(b.image), btn = b.button?.label?.trim() ? b.button : null;
                return `<div class="ab-n-hero${img ? ' has-img' : ''}" style="${img ? `background-image:url('${esc(img)}')` : `background:${c.primary};color:${on}`}"><div>
                    ${b.title ? `<h3>${esc(b.title)}</h3>` : ''}${b.text ? `<p>${esc(b.text)}</p>` : ''}
                    ${btn ? pv.tap(btn.action, esc(btn.label), 'ab-n-herobtn" style="background:' + (img ? c.primary : on) + ';color:' + (img ? on : c.primary)) : ''}</div></div>`;
            }
            case 'heading': return b.text?.trim() ? `<h4 class="ab-n-h" style="text-align:${b.align === 'center' ? 'center' : 'left'}">${esc(b.text)}</h4>` : '';
            case 'text': return b.text?.trim() ? `<p class="ab-n-p" style="text-align:${b.align === 'center' ? 'center' : 'left'}">${esc(b.text)}</p>` : '';
            case 'image': return b.image ? `<div class="ab-n-pad">${pv.tap(b.action, `<img src="${esc(imgSrc(b.image))}" alt="" class="ab-n-img${b.round ? ' is-round' : ''}">`)}${b.caption ? `<p class="ab-n-cap">${esc(b.caption)}</p>` : ''}</div>` : '';
            case 'button': return b.label?.trim() ? `<div class="ab-n-pad">${pv.tap(b.action, esc(b.label), 'ab-n-btn" style="' + (b.style === 'outline' ? `border:2px solid ${c.primary};color:${c.primary}` : `background:${c.primary};color:${on}`))}</div>` : '';
            case 'list': {
                const items = (b.items || []).filter(i => i.title || i.text || i.price || i.image);
                return items.length ? `<div class="ab-n-pad">${b.title ? `<h5 class="ab-n-ct">${esc(b.title)}</h5>` : ''}<div class="ab-n-card">${items.map(i => pv.tap(i.action,
                    `${i.image ? `<img src="${esc(imgSrc(i.image))}" alt="">` : ''}<span class="ab-n-rb">${i.title ? `<b>${esc(i.title)}</b>` : ''}${i.text ? `<small>${esc(i.text)}</small>` : ''}</span>${i.price ? `<em style="color:${c.primary}">${esc(i.price)}</em>` : ''}${i.action?.kind && (i.action.kind === 'tab' || i.action.value) ? pv.chev : ''}`, 'ab-n-row')).join('')}</div></div>` : '';
            }
            case 'gallery': return b.images?.length ? `<div class="ab-n-pad ab-n-gal" style="grid-template-columns:repeat(${b.columns === 3 ? 3 : 2},1fr)">${b.images.map(i => `<img src="${esc(imgSrc(i))}" alt="">`).join('')}</div>` : '';
            case 'contact': {
                const rows = [['phone', 'Call', b.phone], ['chat', 'WhatsApp', b.whatsapp], ['mail', 'Email', b.email], ['location', 'Directions', b.address], ['globe', 'Website', (b.website || '').replace(/^https:\/\/(www\.)?/, '')]].filter(r => r[2]?.trim());
                return rows.length ? `<div class="ab-n-pad">${b.title ? `<h5 class="ab-n-ct">${esc(b.title)}</h5>` : ''}<div class="ab-n-card">${rows.map(([ic, l, v]) =>
                    `<div class="ab-n-row"><span class="ab-n-badge" style="background:${c.primary}1f">${svgIcon(ic, c.primary, 16)}</span><span class="ab-n-rb"><small>${l}</small><b>${esc(v)}</b></span>${pv.chev}</div>`).join('')}</div></div>` : '';
            }
            case 'hours': {
                const rows = (b.rows || []).filter(r => r.day || r.time);
                return rows.length ? `<div class="ab-n-pad">${b.title ? `<h5 class="ab-n-ct">${esc(b.title)}</h5>` : ''}<div class="ab-n-card">${rows.map(r => `<div class="ab-n-row"><span class="ab-n-rb"><b>${esc(r.day)}</b></span><small>${esc(r.time)}</small></div>`).join('')}</div></div>` : '';
            }
            case 'video': { const id = youtubeId(b.url); return id ? `<div class="ab-n-pad"><div class="ab-n-vid" style="background-image:url('https://i.ytimg.com/vi/${id}/hqdefault.jpg')"><span>▶</span></div>${b.title ? `<p class="ab-n-cap">${esc(b.title)}</p>` : ''}</div>` : ''; }
            case 'divider': return '<hr class="ab-n-hr">';
            default: return '';
        }
    };

    const drawPhone = () => {
        const a = A(), c = a.colors, phone = $('[data-phone]'), tabs = a.tabs;
        const time = '9:41', barInk = ink(c.bar), muted = isDark(c.bar) ? 'rgba(255,255,255,.6)' : 'rgba(15,23,42,.5)';
        const status = `<div class="ab-ph__status" style="background:${c.bar};color:${barInk}"><span>${time}</span><span class="ab-ph__sys">▮▮▮ ◔ ▭</span></div>`;
        if (view === 'splash') {
            phone.innerHTML = `<div class="ab-ph__screen ab-ph__splash" style="background:${c.splash}"><canvas width="84" height="84" data-splash-icon></canvas></div>`;
            const cv = $('[data-splash-icon]', phone), x = cv.getContext('2d'); x.drawImage(drawIcon(256, true), -42, -42, 168, 168);
        } else if (view === 'home') {
            const others = ['#60a5fa', '#f472b6', '#facc15', '#34d399', '#a78bfa', '#fb923c', '#f87171'];
            phone.innerHTML = `<div class="ab-ph__screen ab-ph__home"><div class="ab-ph__status" style="color:#fff;background:transparent"><span>${time}</span><span class="ab-ph__sys">▮▮▮ ◔ ▭</span></div>
                <div class="ab-ph__grid">${others.map(o => `<div><i style="background:${o}"></i><span></span></div>`).join('')}<div class="is-ours"><canvas width="56" height="56" data-home-icon></canvas><b>${esc(a.name || 'Your app')}</b></div></div></div>`;
            paintIcon($('[data-home-icon]', phone), 0.5); // Android launchers usually show a circle
        } else {
            const t = tabs[activeTab] || tabs[0];
            let body;
            if (t.type === 'web') {
                const host = siteUrl()?.hostname || 'yourwebsite.com', path = (tabUrl(t).replace(/^https:\/\/[^/]+/, '') || '/');
                body = `<div class="ab-ph__web"><div class="ab-ph__url">${esc(host)}${esc(path === '/' ? '' : path)}</div>
                    <div class="ab-ph__sk"><i style="background:${c.primary}"></i><b></b><b></b><b class="s"></b><div class="ab-ph__cards"><span></span><span></span></div><b></b><b class="s"></b><span class="ab-ph__btn" style="background:${c.primary}"></span></div>
                    <p>${siteUrl() ? 'This page of your website shows here' : 'Add your website address in “More settings”'}</p></div>`;
            } else {
                const html = (t.blocks || []).map(b => blockHtml(b, c)).join('');
                body = `<div class="ab-ph__native">${html || '<p class="ab-n-empty">Add blocks to this screen.</p>'}</div>`;
            }
            phone.innerHTML = `<div class="ab-ph__screen">${status}${body}
                ${tabs.length > 1 ? `<div class="ab-ph__tabs" style="background:${c.bar}">${tabs.map((x, i) => {
                    const col = i === activeTab ? c.primary : muted;
                    return `<button type="button" data-ptab="${i}" style="color:${col}">${svgIcon(x.icon, col)}<span>${esc(x.label || 'Tab')}</span></button>`;
                }).join('')}</div>` : ''}</div>`;
        }
        paintIcon($('[data-icon-prev]'));
    };

    /* ------------------------------------------------ editor: tabs */
    const drawTabs = () => {
        const tabs = A().tabs, base = siteUrl() ? siteUrl().href.replace(/[?#].*$/, '').replace(/\/$/, '') + '/' : 'https://yourwebsite.com/';
        $('[data-tabs]').innerHTML = tabs.map((t, i) => `<div class="ab-tab${i === activeTab ? ' is-on' : ''}" data-tabrow="${i}">
            <select data-t="icon" data-i="${i}" aria-label="Tab ${i + 1} icon">${Object.keys(ICONS).map(k => `<option value="${k}" ${t.icon === k ? 'selected' : ''}>${ICON_NAMES[k] || k}</option>`).join('')}</select>
            <span class="ab-tab__ic" aria-hidden="true">${svgIcon(t.icon, 'currentColor')}</span>
            <input data-t="label" data-i="${i}" maxlength="16" value="${esc(t.label)}" placeholder="Name" aria-label="Tab ${i + 1} name">
            <span class="fb-q__tools"><button type="button" data-tmove="-1" data-i="${i}" title="Move left" ${i ? '' : 'disabled'}>↑</button><button type="button" data-tmove="1" data-i="${i}" title="Move right" ${i < tabs.length - 1 ? '' : 'disabled'}>↓</button><button type="button" data-tdel="${i}" title="Remove" class="fb-del" ${tabs.length > 1 ? '' : 'disabled'}>✕</button></span>
            <div class="ab-tab__kind">
                <select data-t="type" data-i="${i}" aria-label="Tab ${i + 1} shows"><option value="screen" ${t.type !== 'web' ? 'selected' : ''}>App screen (native)</option><option value="web" ${t.type === 'web' ? 'selected' : ''}>A page of my website</option></select>
                ${t.type === 'web' ? `<div class="ab-tab__path"><span title="${esc(base)}">${esc(base.replace(/^https:\/\//, ''))}</span><input data-t="path" data-i="${i}" maxlength="200" value="${esc(t.path)}" placeholder="(home page)" aria-label="Tab ${i + 1} page" spellcheck="false" autocapitalize="off"></div>` : ''}
            </div>
        </div>`).join('');
        $('[data-add-tab]').disabled = tabs.length >= MAX_TABS;
    };

    /* ------------------------------------------------ editor: the blocks of the open tab */
    const field = (label, path, v, opts = {}) => {
        const attrs = `data-f="${path}" maxlength="${opts.max || 200}" placeholder="${esc(opts.ph || '')}"${opts.type ? ` type="${opts.type}"` : ''}${opts.mode ? ` inputmode="${opts.mode}"` : ''}`;
        return `<label${opts.wide ? ' class="ab-wide"' : ''}>${label}${opts.area ? `<textarea ${attrs} rows="${opts.rows || 3}">${esc(v)}</textarea>` : `<input ${attrs} value="${esc(v)}">`}</label>`;
    };
    const imageField = (label, path, img) => `<div class="ab-imgf"><span>${label}</span>${img
        ? `<img src="${esc(imgSrc(img))}" alt=""><span class="ab-imgf__tools"><button type="button" class="btn btn--sm btn--ghost" data-pick="${path}">Change</button><button type="button" class="btn btn--sm btn--ghost" data-unimg="${path}">Remove</button></span>`
        : `<button type="button" class="btn btn--sm btn--ghost" data-pick="${path}">＋ Add picture</button>`}</div>`;
    const actionField = (label, path, a) => {
        a = a || { kind: '', value: '' };
        const tabs = A().tabs;
        const value = a.kind === 'tab'
            ? `<select data-f="${path}.value" data-num aria-label="Which tab">${tabs.map((t, i) => `<option value="${i}" ${+a.value === i ? 'selected' : ''}>${esc(t.label || 'Tab ' + (i + 1))}</option>`).join('')}</select>`
            : a.kind ? `<input data-f="${path}.value" value="${esc(a.value)}" maxlength="${a.kind === 'url' ? 500 : 200}" placeholder="${esc(ACTION_HINT[a.kind] || '')}" ${a.kind === 'url' ? 'type="url" inputmode="url"' : a.kind === 'email' ? 'type="email"' : a.kind === 'tel' || a.kind === 'whatsapp' ? 'type="tel"' : ''} aria-label="${esc(ACTIONS[a.kind])}">` : '';
        return `<div class="ab-act"><span>${label}</span><div><select data-f="${path}.kind" data-redraw>${Object.entries(ACTIONS).map(([k, l]) => `<option value="${k}" ${a.kind === k ? 'selected' : ''}>${l}</option>`).join('')}</select>${value}</div></div>`;
    };
    const align = (path, v) => `<label>Align<select data-f="${path}"><option value="left" ${v !== 'center' ? 'selected' : ''}>Left</option><option value="center" ${v === 'center' ? 'selected' : ''}>Centre</option></select></label>`;
    const blockFields = b => {
        switch (b.type) {
            case 'hero': return field('Title', 'title', b.title, { max: 80 }) + field('Text', 'text', b.text, { max: 300, area: true, rows: 2 }) + imageField('Background picture', 'image', b.image)
                + field('Button label <small>— optional</small>', 'button.label', b.button?.label, { max: 40 }) + actionField('Button does', 'button.action', b.button?.action);
            case 'heading': return field('Heading', 'text', b.text, { max: 120 }) + align('align', b.align);
            case 'text': return field('Text', 'text', b.text, { max: 3000, area: true, rows: 5 }) + align('align', b.align);
            case 'image': return imageField('Picture', 'image', b.image) + field('Caption <small>— optional</small>', 'caption', b.caption, { max: 200 })
                + `<label class="fb-check"><input type="checkbox" data-f="round" ${b.round ? 'checked' : ''}> Rounded corners</label>` + actionField('Tapping it', 'action', b.action);
            case 'button': return field('Label', 'label', b.label, { max: 40 }) + actionField('Tapping it', 'action', b.action)
                + `<label>Style<select data-f="style"><option value="solid" ${b.style !== 'outline' ? 'selected' : ''}>Filled</option><option value="outline" ${b.style === 'outline' ? 'selected' : ''}>Outline</option></select></label>`;
            case 'list': return field('Title <small>— optional</small>', 'title', b.title, { max: 80 }) + `<div class="ab-items">${(b.items || []).map((it, i) => `<div class="ab-item">
                    <div class="ab-item__head"><b>Item ${i + 1}</b><span class="fb-q__tools"><button type="button" data-imove="-1" data-ii="${i}" title="Move up" ${i ? '' : 'disabled'}>↑</button><button type="button" data-imove="1" data-ii="${i}" title="Move down" ${i < b.items.length - 1 ? '' : 'disabled'}>↓</button><button type="button" data-idel="${i}" title="Remove" class="fb-del">✕</button></span></div>
                    <div class="fb-2">${field('Name', `items.${i}.title`, it.title, { max: 80 })}${field('Price <small>— optional</small>', `items.${i}.price`, it.price, { max: 20, ph: '$10' })}</div>
                    ${field('Description <small>— optional</small>', `items.${i}.text`, it.text, { max: 400, area: true, rows: 2 })}
                    ${imageField('Picture <small>— optional</small>', `items.${i}.image`, it.image)}${actionField('Tapping it', `items.${i}.action`, it.action)}</div>`).join('')}</div>
                <button type="button" class="btn btn--sm btn--ghost" data-iadd ${(b.items || []).length >= 50 ? 'disabled' : ''}>＋ Add item</button>`;
            case 'gallery': return `<div class="ab-gal">${(b.images || []).map((img, i) => `<span><img src="${esc(imgSrc(img))}" alt=""><button type="button" data-gdel="${i}" aria-label="Remove picture ${i + 1}">✕</button></span>`).join('')}</div>
                <button type="button" class="btn btn--sm btn--ghost" data-pick="images" ${(b.images || []).length >= 24 ? 'disabled' : ''}>＋ Add pictures</button>
                <label>Columns<select data-f="columns" data-num><option value="2" ${b.columns !== 3 ? 'selected' : ''}>2</option><option value="3" ${b.columns === 3 ? 'selected' : ''}>3</option></select></label>`;
            case 'contact': return field('Title <small>— optional</small>', 'title', b.title, { max: 80 }) + '<p class="fb-hint">Fill in what you want to show — each line opens the phone’s dialer, WhatsApp, email or maps.</p><div class="fb-2">'
                + field('Phone', 'phone', b.phone, { max: 25, type: 'tel', ph: '+1 555 123 4567' }) + field('WhatsApp', 'whatsapp', b.whatsapp, { max: 25, type: 'tel', ph: '+44 7700 900123' })
                + field('Email', 'email', b.email, { max: 120, type: 'email' }) + field('Website', 'website', b.website, { max: 500, type: 'url', ph: 'https://' }) + '</div>'
                + field('Address', 'address', b.address, { max: 200, area: true, rows: 2 });
            case 'hours': return field('Title <small>— optional</small>', 'title', b.title, { max: 80 }) + `<div class="ab-hours">${(b.rows || []).map((r, i) => `<div class="ab-hours__row">
                    <input data-f="rows.${i}.day" value="${esc(r.day)}" maxlength="30" placeholder="Monday" aria-label="Day"><input data-f="rows.${i}.time" value="${esc(r.time)}" maxlength="60" placeholder="9:00 – 17:00" aria-label="Hours">
                    <span class="fb-q__tools"><button type="button" data-hdel="${i}" class="fb-del" title="Remove">✕</button></span></div>`).join('')}</div>
                <button type="button" class="btn btn--sm btn--ghost" data-hadd ${(b.rows || []).length >= 14 ? 'disabled' : ''}>＋ Add row</button>`;
            case 'video': return field('YouTube link', 'url', b.url, { max: 300, type: 'url', ph: 'https://www.youtube.com/watch?v=…' }) + field('Title <small>— optional</small>', 'title', b.title, { max: 120 });
            case 'divider': return '<p class="fb-hint">A thin line between blocks.</p>';
            default: return '';
        }
    };
    const blockSummary = b => {
        const s = b.title || b.text || b.label || b.caption || (b.items?.[0]?.title) || (b.images?.length ? b.images.length + ' pictures' : '') || b.phone || b.email || '';
        return String(s).slice(0, 40);
    };
    const drawBlocks = () => {
        const t = A().tabs[activeTab] || A().tabs[0], box = $('[data-blocks]');
        $('[data-screen-name]').textContent = t.label || 'Tab ' + (activeTab + 1);
        if (t.type === 'web') {
            box.innerHTML = `<p class="fb-hint">This tab shows ${siteUrl() ? `<b>${esc(tabUrl(t))}</b>` : 'a page of your website — add your website address under “More settings”'}. Switch it to “App screen” to build it from blocks instead.</p>`;
            $('[data-add-block]').hidden = true;
            return;
        }
        $('[data-add-block]').hidden = false;
        const blocks = t.blocks;
        box.innerHTML = blocks.length ? blocks.map((b, i) => `<details class="ab-blk" data-b="${i}" ${openBlocks.has(b) ? 'open' : ''}>
            <summary><span class="ab-blk__ic" aria-hidden="true">${svgIcon(BLOCKS[b.type]?.icon, 'currentColor', 18)}</span><b>${esc(BLOCKS[b.type]?.name || b.type)}</b><small>${esc(blockSummary(b))}</small>
                <span class="fb-q__tools"><button type="button" data-bmove="-1" title="Move up" ${i ? '' : 'disabled'}>↑</button><button type="button" data-bmove="1" title="Move down" ${i < blocks.length - 1 ? '' : 'disabled'}>↓</button><button type="button" data-bcopy title="Duplicate" ${blocks.length >= MAX_BLOCKS ? 'disabled' : ''}>⧉</button><button type="button" data-bdel title="Remove" class="fb-del">✕</button></span></summary>
            <div class="ab-blk__body">${blockFields(b)}</div></details>`).join('') : '<p class="fb-hint">This screen is empty. Add your first block below.</p>';
        $$('[data-add-type]').forEach(x => (x.disabled = blocks.length >= MAX_BLOCKS));
    };
    const drawSettings = () => {
        const a = A();
        $$('[data-a]').forEach(el => (el.value = a[el.dataset.a] ?? ''));
        $$('[data-c]').forEach(el => (el.value = a.colors[el.dataset.c]));
        $$('[data-o]').forEach(el => (el.value = a.offline[el.dataset.o] ?? ''));
        $('[data-i="letter"]').value = D.icon.letter;
        $('[data-i="zoom"]').value = D.icon.zoom;
        $('[data-i="fill"]').checked = !!D.icon.fill;
        $$('[data-icon-mode]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.iconMode === D.icon.mode)));
        $('[data-icon-letter-wrap]').hidden = D.icon.mode !== 'letter';
        ['file', 'zoom', 'fill'].forEach(k => ($(`[data-icon-${k}-wrap]`).hidden = D.icon.mode !== 'image'));
        $('[data-icon-file-title]').textContent = D.icon.image ? 'Change logo' : 'Upload your logo';
        $('[data-icon-file-wrap]').classList.toggle('has-file', !!D.icon.image);
        if (D.icon.mode === 'image') $('[data-icon-zoom-wrap]').hidden = !!D.icon.fill;
        $('[data-a="package"]').readOnly = !!current()?.version_code;
        drawSitePick();
    };
    const drawAll = () => { drawSettings(); drawTabs(); drawBlocks(); drawPhone(); syncSave(); };
    const selectTab = i => { activeTab = i; $$('.ab-tab').forEach((r, j) => r.classList.toggle('is-on', j === activeTab)); drawBlocks(); drawPhone(); };

    const markDirty = (icon = false) => { D.dirty = true; if (icon) D.iconDirty = true; save(); syncSave(); };
    const curBlocks = () => A().tabs[activeTab].blocks;
    const blockOf = el => { const card = el.closest('[data-b]'); return card ? curBlocks()[+card.dataset.b] : null; };

    root.addEventListener('input', e => {
        const el = e.target, a = A();
        if (el.dataset.f) {
            const b = blockOf(el); if (!b) return;
            const v = el.type === 'checkbox' ? el.checked : el.dataset.num !== undefined ? +el.value : el.value;
            setPath(b, el.dataset.f, v);
            if (el.dataset.f.endsWith('.kind')) { const act = getPath(b, el.dataset.f.replace(/\.kind$/, '')); act.value = v === 'tab' ? 0 : ''; }
            if (el.dataset.redraw !== undefined) drawBlocks();
            else { const card = el.closest('[data-b]'); const sm = card?.querySelector('summary small'); if (sm) sm.textContent = blockSummary(b); }
            drawPhone(); markDirty(); return;
        }
        if (el.dataset.a) {
            a[el.dataset.a] = el.value;
            if (el.dataset.a === 'name') { if (!current()?.version_code && (!a.package || a.package === D.autoPackage)) { a.package = D.autoPackage = suggestPackage(); $('[data-a="package"]').value = a.package; } if (D.icon.mode === 'letter' && !D.icon.letter) D.iconDirty = true; }
            if (el.dataset.a === 'url') { drawTabs(); drawBlocks(); }
        } else if (el.dataset.c) { a.colors[el.dataset.c] = el.value; if (el.dataset.c === 'icon') D.iconDirty = true; }
        else if (el.dataset.o) a.offline[el.dataset.o] = el.value;
        else if (el.dataset.t) { // (tab fields carry data-i too — their index — so they're matched first)
            const t = a.tabs[+el.dataset.i]; t[el.dataset.t] = el.value; activeTab = +el.dataset.i;
            if (el.dataset.t === 'icon') el.nextElementSibling.innerHTML = svgIcon(t.icon, 'currentColor');
            if (el.dataset.t === 'type') drawTabs();
            $$('.ab-tab').forEach((r, i) => r.classList.toggle('is-on', i === activeTab));
            if (el.dataset.t !== 'path') drawBlocks();
        } else if (el.dataset.i) { D.icon[el.dataset.i] = el.type === 'checkbox' ? el.checked : el.dataset.i === 'zoom' ? +el.value : el.value; D.iconDirty = true; if (el.dataset.i === 'fill') drawSettings(); }
        else return;
        drawPhone(); markDirty(D.iconDirty);
    });
    // Remember which block cards are open (toggle doesn't bubble)
    root.addEventListener('toggle', e => {
        const card = e.target.closest?.('[data-b]'); if (!card || card !== e.target) return;
        const b = curBlocks()[+card.dataset.b]; if (!b) return;
        card.open ? openBlocks.add(b) : openBlocks.delete(b);
    }, true);

    /* ------------------------------------------------ pictures on screens */
    const photoInput = $('[data-photo-file]');
    let picking = null; // { block, path }
    const useLogo = async file => {
        if (!file) return;
        try { D.icon.image = await readLogo(file); D.icon.mode = 'image'; await loadLogo(); drawSettings(); drawPhone(); markDirty(true); track('app_builder_logo'); }
        catch (ex) { note(ex.message); }
    };
    /* Drop a logo on the upload box */
    const drop = $('[data-icon-file-wrap]');
    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, () => drop.classList.remove('is-over')));
    drop.addEventListener('drop', e => { e.preventDefault(); useLogo(e.dataTransfer?.files?.[0]); });
    root.addEventListener('change', async e => {
        if (e.target === photoInput) {
            const files = [...(photoInput.files || [])]; photoInput.value = '';
            if (!files.length || !picking) return;
            const { block: b, path } = picking; picking = null;
            try {
                for (const file of path === 'images' ? files.slice(0, 24 - (b.images || []).length) : files.slice(0, 1)) {
                    if (pendingBytes() > MAX_PENDING) { note('You’ve added a lot of new pictures. Save your app, then add more.'); break; }
                    const img = await readPhoto(file);
                    if (path === 'images') (b.images ||= []).push(img); else setPath(b, path, img);
                }
                drawBlocks(); drawPhone(); markDirty(); track('app_builder_picture', { block: b.type });
            } catch (ex) { note(ex.message); }
            return;
        }
        if (e.target.matches('[data-icon-file]')) {
            await useLogo(e.target.files?.[0]);
            e.target.value = '';
        }
        if (e.target.matches('[data-site]') && e.target.value) {
            A().url = e.target.value; $('[data-a="url"]').value = A().url;
            if (!A().name) { const s = sites.find(x => x.url === e.target.value); if (s) { A().name = s.name.slice(0, 30); $('[data-a="name"]').value = A().name; } }
            if (!A().package || A().package === D.autoPackage) A().package = D.autoPackage = suggestPackage();
            drawSettings(); drawTabs(); drawBlocks(); drawPhone(); markDirty(true);
        }
    });

    root.addEventListener('click', async e => {
        const t = e.target.closest('button'); if (!t || !root.contains(t)) return;
        const tabs = A().tabs;
        if (t.closest('summary') && t.closest('.fb-q__tools')) e.preventDefault(); // block tools don't open/close the card
        const b = blockOf(t), blocks = curBlocks();
        if (t.dataset.tab) showTab(t.dataset.tab);
        else if (t.dataset.view) { view = t.dataset.view; $$('[data-view]').forEach(x => x.setAttribute('aria-pressed', String(x === t))); drawPhone(); }
        else if (t.dataset.iconMode) {
            D.icon.mode = t.dataset.iconMode; drawSettings(); drawPhone(); markDirty(true);
            if (D.icon.mode === 'image' && !D.icon.image) $('[data-icon-file]').click();
        } else if (t.dataset.ptab) selectTab(+t.dataset.ptab);
        else if (t.dataset.pact) {
            const act = JSON.parse(t.dataset.pact);
            if (act.kind === 'tab' && tabs[+act.value]) { selectTab(+act.value); drawTabs(); }
        } else if (t.matches('[data-add-tab]')) {
            if (tabs.length >= MAX_TABS) return;
            const used = tabs.map(x => x.icon), icon = ['grid', 'info', 'chat', 'calendar', 'store', 'location'].find(k => !used.includes(k)) || 'star';
            tabs.push({ label: ICON_NAMES[icon], icon, type: 'screen', path: '', blocks: [{ type: 'heading', text: ICON_NAMES[icon], align: 'left' }] });
            activeTab = tabs.length - 1; drawTabs(); drawBlocks(); drawPhone(); markDirty();
            $(`[data-t="label"][data-i="${activeTab}"]`)?.focus();
        } else if (t.dataset.tmove) {
            const i = +t.dataset.i, j = i + +t.dataset.tmove;
            [tabs[i], tabs[j]] = [tabs[j], tabs[i]];
            fixTabLinks(k => (k === i ? j : k === j ? i : k));
            activeTab = j; drawTabs(); drawBlocks(); drawPhone(); markDirty();
        } else if (t.dataset.tdel) {
            const i = +t.dataset.tdel;
            if (tabs[i].type !== 'web' && tabs[i].blocks.length && !await ask('Remove this tab?', `“${tabs[i].label || 'This tab'}” and its blocks will be removed.`, 'Remove tab', true)) return;
            tabs.splice(i, 1); fixTabLinks(k => (k === i ? 0 : k > i ? k - 1 : k));
            activeTab = 0; drawTabs(); drawBlocks(); drawPhone(); markDirty();
        } else if (t.dataset.addType) {
            if (blocks.length >= MAX_BLOCKS) return;
            const nb = block(t.dataset.addType); openBlocks.add(nb); blocks.push(nb);
            t.closest('details')?.removeAttribute('open');
            drawBlocks(); drawPhone(); markDirty(); track('app_builder_block', { type: nb.type });
            $(`[data-b="${blocks.length - 1}"]`)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        } else if (b) {
            const i = blocks.indexOf(b);
            if (t.dataset.bmove !== undefined) { const j = i + +t.dataset.bmove; [blocks[i], blocks[j]] = [blocks[j], blocks[i]]; }
            else if (t.dataset.bdel !== undefined) blocks.splice(i, 1);
            else if (t.dataset.bcopy !== undefined) { const c = JSON.parse(JSON.stringify(b)); openBlocks.add(c); blocks.splice(i + 1, 0, c); }
            else if (t.dataset.pick) { picking = { block: b, path: t.dataset.pick }; photoInput.multiple = t.dataset.pick === 'images'; photoInput.click(); return; }
            else if (t.dataset.unimg) setPath(b, t.dataset.unimg, null);
            else if (t.dataset.gdel) b.images.splice(+t.dataset.gdel, 1);
            else if (t.dataset.iadd !== undefined) b.items.push({ title: '', text: '', price: '', image: null, action: { kind: '', value: '' } });
            else if (t.dataset.idel) b.items.splice(+t.dataset.idel, 1);
            else if (t.dataset.imove) { const k = +t.dataset.ii, j = k + +t.dataset.imove; [b.items[k], b.items[j]] = [b.items[j], b.items[k]]; }
            else if (t.dataset.hadd !== undefined) b.rows.push({ day: '', time: '' });
            else if (t.dataset.hdel) b.rows.splice(+t.dataset.hdel, 1);
            else return;
            drawBlocks(); drawPhone(); markDirty();
        }
    });
    root.addEventListener('focusin', e => { const r = e.target.closest('[data-tabrow]'); if (r && +r.dataset.tabrow !== activeTab) selectTab(+r.dataset.tabrow); });
    /** Buttons that open a tab keep pointing at the same tab when tabs move or go. */
    const fixTabLinks = map => A().tabs.forEach(t => (t.blocks || []).forEach(b => {
        [b.action, b.button?.action, ...(b.items || []).map(i => i.action)].forEach(a => { if (a?.kind === 'tab') a.value = Math.min(map(+a.value), A().tabs.length - 1); });
    }));

    const suggestPackage = () => {
        const host = siteUrl()?.hostname.replace(/^www\./, '') || '';
        const parts = host && !/owlvix\.com$/.test(host) ? host.split('.').reverse().map(slug).filter(Boolean) : ['app', slug(A().name) || 'myapp'];
        let p = parts.map(s => (/^[a-z]/.test(s) ? s : 'a' + s)).join('.');
        if (!p.includes('.')) p = 'app.' + p;
        if (!/\.app$/.test(p) && host && !/owlvix\.com$/.test(host)) p += '.app';
        return p.slice(0, 100);
    };

    const showTab = name => {
        $$('[data-tab]').forEach(b => b.setAttribute('aria-selected', String(b.dataset.tab === name)));
        $$('[data-panel]').forEach(p => (p.hidden = p.dataset.panel !== name));
        if (name === 'build') drawBuild();
    };

    /* ------------------------------------------------ account (same sign-in as the page builder) */
    let csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let token = ''; try { token = localStorage.getItem('owlvix-builder-token') || ''; } catch {}
    let apps = null, sites = [], enabled = true;
    const current = () => apps?.find(x => x.id === D.id) || null;
    const api = async (method, url, body, retried) => {
        const res = await fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Builder-Token': token }, body: body ? JSON.stringify(body) : undefined });
        if (res.status === 419 && !retried) { try { const h = await (await fetch(location.pathname)).text(); csrf = (h.match(/name="csrf-token" content="([^"]+)"/) || [])[1] || csrf; } catch {} return api(method, url, body, true); }
        if (res.status === 413) { const err = new Error('That’s too much to save at once — usually too many new pictures. Remove a few, save, then add them again.'); err.status = 413; throw err; }
        const json = await res.json().catch(() => ({}));
        if (res.status === 401) { token = ''; apps = null; try { localStorage.removeItem('owlvix-builder-token'); } catch {} }
        if (!res.ok || json.ok === false) { const err = new Error(json.error || json.message || 'Something went wrong. Please try again.'); err.status = res.status; throw err; }
        return json;
    };
    const dlg = $('[data-dialog]'), dlgBody = $('[data-dialog-body]');
    let afterSignIn = null;
    const openDialog = html => { dlgBody.innerHTML = html; if (!dlg.open) dlg.showModal(); dlgBody.querySelector('input:not([type=hidden])')?.focus(); };
    dlg.addEventListener('click', e => { if (e.target === dlg || e.target.closest('[data-dialog-close]')) dlg.close(); });
    const signIn = then => {
        afterSignIn = then;
        openDialog(`<h3>Sign in with your email</h3><p>We'll email you a 6-digit code — no password needed. Use the email you publish your Owlvix sites with, and we'll email your app to you when it's built.</p>
            <form data-signin><label>Email<input type="email" name="email" required autocomplete="email" placeholder="you@example.com"></label><p class="lpb-err" hidden></p><button class="btn btn--glow btn--block" type="submit">Send my code</button></form>`);
    };
    dlg.addEventListener('submit', async e => {
        e.preventDefault();
        const fm = e.target, err = fm.querySelector('.lpb-err'), b = fm.querySelector('button[type=submit]');
        err.hidden = true; b.disabled = true;
        try {
            if (fm.matches('[data-signin]')) {
                const email = fm.email.value.trim();
                await api('POST', '/builder/account/code', { email });
                openDialog(`<h3>Check your email</h3><p>We sent a 6-digit code to <b>${esc(email)}</b>. It's valid for 15 minutes.</p>
                    <form data-verify><input type="hidden" name="email" value="${esc(email)}"><label>Code<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required placeholder="123456"></label><p class="lpb-err" hidden></p><button class="btn btn--glow btn--block" type="submit">Sign in</button></form>`);
            } else {
                const r = await api('POST', '/builder/account/verify', { email: fm.email.value, code: fm.code.value.trim() });
                token = r.token; try { localStorage.setItem('owlvix-builder-token', token); } catch {}
                dlg.close(); track('builder_sign_in', { from: 'app_builder' }); await loadAccount(); afterSignIn?.();
            }
        } catch (ex) { err.textContent = ex.message; err.hidden = false; }
        finally { b.disabled = false; }
    });
    const loadAccount = async () => {
        const [acc, list] = await Promise.all([api('GET', '/builder/account'), api('GET', '/builder/apps')]);
        sites = (acc.sites || []).filter(s => s.status === 'live'); apps = list.apps; enabled = list.enabled;
        drawMine(); drawSitePick(); syncSave();
        if (!$('[data-panel="build"]').hidden) drawBuild();
        return apps;
    };
    const drawMine = () => {
        const sel = $('[data-mine]');
        sel.hidden = !apps?.length;
        sel.innerHTML = `<option value="">My apps (${apps?.length || 0})…</option>` + (apps || []).map(x => `<option value="${x.id}" ${x.id === D.id ? 'selected' : ''}>${esc(x.name)}</option>`).join('');
    };
    const drawSitePick = () => {
        const wrap = $('[data-site-pick]'), sel = $('[data-site]');
        wrap.hidden = !sites.length;
        sel.innerHTML = '<option value="">Choose a site…</option>' + sites.map(s => `<option value="${esc(s.url)}" ${s.url === A().url ? 'selected' : ''}>${esc(s.name)} — ${esc(s.url.replace(/^https?:\/\//, ''))}</option>`).join('');
    };

    /* ------------------------------------------------ save */
    const note = msg => { const n = $('[data-note]'); n.textContent = msg; n.hidden = false; clearTimeout(note.t); note.t = setTimeout(() => (n.hidden = true), 7000); };
    const syncSave = () => {
        const b = $('[data-save]');
        b.textContent = D.id ? (D.dirty ? 'Save changes' : 'Saved ✓') : 'Save app';
        b.disabled = !!D.id && !D.dirty;
        $('[data-status]').textContent = D.id
            ? (D.dirty ? 'Unsaved changes — save to update the app on phones.' : current()?.version_code ? 'Saved — phones with your app show these screens within a minute.' : 'Saved to your account.')
            : 'Not saved yet — your work is kept in this browser.';
    };
    const imageOut = img => (img?.src ? { src: img.src } : null);
    const actionOut = a => (a?.kind ? { kind: a.kind, value: a.kind === 'tab' ? +a.value : String(a.value ?? '').trim() } : null);
    const blockOut = b => {
        const o = { ...b };
        if ('image' in o) o.image = imageOut(o.image);
        if (o.images) o.images = o.images.map(imageOut);
        if ('action' in o) o.action = actionOut(o.action);
        if (o.button) o.button = { label: (o.button.label || '').trim(), action: o.button.label?.trim() ? actionOut(o.button.action) : null };
        if (o.items) o.items = o.items.map(i => ({ ...i, image: imageOut(i.image), action: actionOut(i.action) }));
        return o;
    };
    const payload = () => {
        const a = A();
        return {
            name: a.name.trim(), package: a.package.trim().toLowerCase(), url: a.url.trim(), version_name: a.version_name.trim() || '1.0.0', external_links: a.external_links,
            status_bar: isDark(a.colors.bar) ? 'light' : 'dark', colors: a.colors, offline: a.offline,
            tabs: a.tabs.map(t => (t.type === 'web' ? { label: t.label.trim(), icon: t.icon, type: 'web', url: tabUrl(t) } : { label: t.label.trim(), icon: t.icon, type: 'screen', blocks: t.blocks.map(blockOut) })),
        };
    };
    const check = () => {
        const a = A();
        if (!a.name.trim()) return 'Please give your app a name.';
        if (D.icon.mode === 'image' && !logo) return 'Please add your logo, or switch the icon to letters.';
        if (a.tabs.some(t => !t.label.trim())) return 'Please give each tab a name.';
        if (a.tabs.some(t => t.type === 'web') && !siteUrl()) return 'You have a tab that shows your website: add your website address (starting with https://) under “More settings”.';
        return null;
    };
    const saveApp = async () => {
        const problem = check();
        if (problem) { showTab('design'); note(problem); return false; }
        if (!token) { signIn(() => saveApp().then(ok => ok && !$('[data-panel="build"]').hidden && drawBuild())); return false; }
        const b = $('[data-save]'); b.disabled = true; b.textContent = 'Saving…';
        try {
            const body = { id: D.id || undefined, app: payload() };
            const addIcon = () => Object.assign(body, { icon: drawIcon(1024).toDataURL('image/png'), foreground: drawIcon(1024, true).toDataURL('image/png'),
                design: { mode: D.icon.mode, letter: D.icon.letter, zoom: D.icon.zoom, fill: D.icon.fill }, logo: D.icon.mode === 'image' ? D.icon.image : undefined });
            if (D.iconDirty || !D.id) addIcon();
            let r;
            try { r = await api('POST', '/builder/apps', body); }
            catch (ex) { if (ex.status === 404 && D.id) { D.id = null; delete body.id; addIcon(); r = await api('POST', '/builder/apps', body); } else throw ex; }
            const first = !D.id; D.id = r.app.id; D.dirty = false; D.iconDirty = false;
            D.base = r.app.content_url.replace(/content\.json$/, '');
            eachImage(img => { if (img.src?.startsWith('data:') && img.name) { img.src = img.name; delete img.name; } }); // now stored on the server
            save();
            apps = [...(apps || []).filter(x => x.id !== r.app.id), r.app]; drawMine(); drawSettings();
            note(first ? 'App saved! Now open “Build & download” to make your Android app.' : r.app.version_code ? 'Saved — phones with your app will show the changes within a minute. No new build needed.' : 'Changes saved.');
            track(first ? 'app_builder_create' : 'app_builder_save', { tabs: A().tabs.length });
            return true;
        } catch (ex) { note(ex.message); if (ex.status === 401) signIn(saveApp); return false; }
        finally { syncSave(); }
    };
    $('[data-save]').addEventListener('click', saveApp);
    $('[data-new]').addEventListener('click', async () => {
        if (D.dirty && !await ask('Start a new app?', 'Unsaved changes to this one will be lost.', 'Start new app')) return;
        D = fresh(); logo = null; activeTab = 0; save(); drawAll(); drawMine(); showTab('design');
    });
    $('[data-mine]').addEventListener('change', async e => {
        const x = apps?.find(y => y.id === e.target.value); if (!x) return;
        if (D.dirty && !await ask('Open this app?', 'Unsaved changes to the current one will be lost.', 'Open app')) { e.target.value = D.id || ''; return; }
        open(x);
    });
    /** Load a saved app into the editor. Its icon stays on the server until a new one is drawn. */
    const open = x => {
        const same = D.id === x.id, keepIcon = same ? D.icon : fresh().icon;
        const base = (x.url || '').replace(/[?#].*$/, '').replace(/\/$/, '');
        const tabs = (x.tabs || []).map(t => (t.type === 'web'
            ? { label: t.label, icon: t.icon, type: 'web', blocks: [], path: t.url === x.url ? '' : base && t.url.startsWith(base + '/') ? t.url.slice(base.length + 1) : t.url }
            : { label: t.label, icon: t.icon, type: 'screen', path: '', blocks: JSON.parse(JSON.stringify(t.blocks || [])).map(fillBlock) }));
        D = { id: x.id, dirty: false, iconDirty: false, autoPackage: null, base: x.content_url.replace(/content\.json$/, ''),
            app: { name: x.name, package: x.package, url: x.url || '', version_name: x.settings.version_name, external_links: x.settings.external_links, tabs: tabs.length ? tabs : starter(), colors: { ...x.settings.colors }, offline: { ...x.settings.offline } },
            icon: keepIcon };
        activeTab = 0; save(); loadLogo().then(drawAll); drawMine();
        if (!same) api('GET', `/builder/apps/${x.id}/icon`).then(r => {
            if (D.id !== x.id) return;
            D.icon = { ...fresh().icon, ...r.design, image: r.logo || null };
            if (D.icon.mode === 'image' && !D.icon.image) D.icon.mode = 'letter';
            save(); loadLogo().then(drawAll);
        }).catch(() => {});
    };
    /** A saved block has only the fields that were filled in: add the rest back for editing. */
    const fillBlock = b => {
        const base = BLOCKS[b.type]?.make() || {};
        const out = { type: b.type, ...base, ...b };
        if (b.type === 'hero') out.button = { label: b.button?.label || '', action: b.button?.action || { kind: '', value: '' } };
        if ('action' in base) out.action = b.action || { kind: '', value: '' };
        if (b.type === 'image') out.round = !!b.round;
        if (b.type === 'list') out.items = (b.items || []).map(i => ({ title: '', text: '', price: '', image: null, ...i, action: i.action || { kind: '', value: '' } }));
        if (b.type === 'video' && b.id) out.url = 'https://www.youtube.com/watch?v=' + b.id;
        return out;
    };

    /* ------------------------------------------------ build & download */
    let poll = null;
    const fmtMB = n => (n ? (n / 1048576).toFixed(1) + ' MB' : '');
    const drawBuild = () => {
        const box = $('[data-build]'), x = current();
        clearTimeout(poll);
        if (!token) {
            box.innerHTML = `<div class="fb-empty"><b>Sign in to build your app</b><p>We build your Android app on our servers and email it to you. Sign in with your email — no password needed.</p><button type="button" class="btn btn--glow" data-act="signin">Sign in with email</button></div>`;
            return;
        }
        if (!x) {
            box.innerHTML = `<div class="fb-empty"><b>Save your app first</b><p>Design your screens, name and icon in the Design tab, then save it to your account.</p><button type="button" class="btn btn--glow" data-act="save">Save app</button></div>`;
            return;
        }
        const b = x.build, running = b && ['queued', 'building'].includes(b.status);
        const step = (n, done, title, body) => `<div class="ab-step${done ? ' is-done' : ''}"><span class="ab-step__n">${done ? '✓' : n}</span><div><b>${title}</b>${body}</div></div>`;

        const verify = x.allowed
            ? '<p class="fb-hint">' + (x.own_site ? 'It’s one of your Owlvix sites — all set.' : 'Your website is checked — all set.') + '</p>'
            : `<p class="fb-hint">Your app shows pages of <b>${esc(new URL(x.url).hostname)}</b>. Copy this tag into the <code>&lt;head&gt;</code> of your home page there, publish your site, then press Check. (WordPress: a header plugin like “WPCode”; Wix: Settings → Custom code; Shopify: theme.liquid.)</p>
               <div class="fb-copyrow"><input readonly value="${esc(x.tag)}"><button type="button" class="btn btn--sm" data-copy="${esc(x.tag)}">Copy</button></div>
               <button type="button" class="btn btn--sm btn--glow" data-act="verify">Check my website</button>`;

        let buildBody;
        if (!enabled) buildBody = '<p class="fb-hint">App builds are switched off right now. Please try again later — or ask us and we’ll build it for you.</p>';
        else if (running) buildBody = `<div class="ab-running"><span class="ab-spin" aria-hidden="true"></span><div><b>Building version ${esc(b.version_name)}…</b><p class="fb-hint">This usually takes 10–15 minutes. You can close this page — we’ll email you when it’s ready.</p></div></div>`;
        else buildBody = `${b?.status === 'failed' ? `<p class="ab-err">${esc(b.error || 'The last build didn’t finish.')}</p>` : ''}
            ${D.dirty ? '<p class="fb-hint">You have unsaved changes — they’ll be saved first.</p>' : ''}
            ${x.version_code ? '<p class="fb-hint">Changes to your screens, tabs and colours reach phones when you save — build again only to change the name, icon or splash screen, or for a Google Play update.</p>' : ''}
            <p class="fb-hint">Version <b>${esc(A().version_name || '1.0.0')}</b> · build ${x.version_code + 1}${x.version_code ? ' — raise the version in “More settings” for each Google Play update.' : ''}</p>
            <button type="button" class="btn btn--glow" data-act="build" ${x.allowed ? '' : 'disabled'}>📦 Build Android app</button>`;

        let files = '';
        if (b?.status === 'done' && b.files?.apk) {
            files = `<div class="ab-files">
                <div class="ab-file"><div><b>APK — try it on your phone</b><p class="fb-hint">Open this on an Android phone to install. ${fmtMB(b.files.apk.bytes)}</p><a class="btn btn--sm btn--glow" href="${esc(b.files.apk.url)}" data-dl="apk">Download APK</a></div><div class="ab-qr" data-qr-for="${esc(b.files.apk.url)}" aria-label="Scan with your phone to download"></div></div>
                ${b.files.aab ? `<div class="ab-file"><div><b>AAB — for Google Play</b><p class="fb-hint">Upload this in Google Play Console. ${fmtMB(b.files.aab.bytes)}</p><a class="btn btn--sm" href="${esc(b.files.aab.url)}" data-dl="aab">Download AAB</a></div></div>` : ''}
                <p class="fb-hint">Links work for 2 days on this page (7 days in the email). Built ${esc(new Date(b.finished_at || b.created_at).toLocaleString())}.</p>
                ${x.has_keystore ? `<div class="ab-key"><b>🔑 Your signing key</b><p class="fb-hint">Google Play needs every update signed with the same key. We keep it safe for your next builds — download a copy too, and keep it somewhere safe.</p><button type="button" class="btn btn--sm btn--ghost" data-act="keystore">Download signing key</button></div>` : ''}
            </div>
            <details class="fb-settings ab-play"><summary>How to put it on Google Play</summary>
                <ol><li>Make a Google Play developer account at <a href="https://play.google.com/console" target="_blank" rel="noopener">play.google.com/console</a> (one-time $25).</li>
                <li>Press <b>Create app</b>, then fill in the store listing: short and full description, icon (512 × 512), at least 2 phone screenshots and a feature graphic (1024 × 500).</li>
                <li>Answer the app content questions (privacy policy link, ads, target age, data safety).</li>
                <li>Go to <b>Testing → Internal testing</b> (or <b>Production</b>), create a release and upload the <b>.aab</b> file.</li>
                <li>Send it for review. New apps usually take a few days. New personal accounts must first test with 12 people for 14 days.</li></ol>
            </details>`;
        }
        let n = 1;
        box.innerHTML = `<div class="ab-steps">
            ${step(n++, true, 'App saved', `<p class="fb-hint">${esc(x.name)} · ${esc(x.package)}</p>`)}
            ${x.needs_website ? step(n++, x.allowed, 'Show us the website is yours', verify) : ''}
            ${step(n++, b?.status === 'done', 'Build your Android app', buildBody)}
        </div>${files}
        <div class="ab-ios"><b>🍎 Want it on the iPhone App Store?</b><p class="fb-hint">Apple only takes apps from the owner’s own developer account. We publish iPhone apps for you, with logins, payments and push notifications if you need them.</p><a href="#contact" class="btn btn--sm btn--ghost" data-act="ios">Ask for a free quote</a></div>
        <button type="button" class="fb-danger" data-act="delete">Delete this app</button>`;
        $$('[data-qr-for]', box).forEach(el => withQR(() => (el.innerHTML = window.OwlQR.svg(el.dataset.qrFor, { color: '#0f172a' }))));
        if (running) poll = setTimeout(refresh, 20000);
    };
    const withQR = fn => {
        if (window.OwlQR) return fn();
        const sc = document.createElement('script'); sc.src = root.dataset.qr; sc.onload = fn; document.head.append(sc);
    };
    const refresh = async () => {
        if ($('[data-panel="build"]').hidden) { poll = setTimeout(refresh, 20000); return; }
        const was = current()?.build?.status;
        try { const r = await api('GET', '/builder/apps'); apps = r.apps; enabled = r.enabled; } catch {}
        const now = current()?.build?.status;
        if (was !== now && now === 'done') { note('🎉 Your app is ready! Download it below.'); track('app_builder_built'); }
        drawBuild();
    };
    $('[data-build]').addEventListener('click', async e => {
        const t = e.target.closest('[data-act],[data-copy],[data-dl]'); if (!t) return;
        const x = current();
        if (t.dataset.copy) { try { await navigator.clipboard.writeText(t.dataset.copy); t.textContent = 'Copied ✓'; } catch { t.previousElementSibling?.select(); } setTimeout(() => (t.textContent = 'Copy'), 2000); return; }
        if (t.dataset.dl) { track('app_builder_download', { kind: t.dataset.dl }); return; }
        const act = t.dataset.act;
        if (act === 'signin') return signIn(drawBuild);
        if (act === 'save') { if (await saveApp()) drawBuild(); return; }
        if (act === 'ios') { e.preventDefault(); track('app_builder_ios'); document.querySelector('.lp-cta__box [data-quote]')?.click(); return; }
        t.disabled = true;
        try {
            if (act === 'verify') { const r = await api('POST', `/builder/apps/${x.id}/verify`); apps = apps.map(y => (y.id === x.id ? r.app : y)); note('✓ Website checked. You can build your app now.'); track('app_builder_verify'); }
            else if (act === 'build') {
                if (D.dirty && !await saveApp()) return;
                const r = await api('POST', `/builder/apps/${x.id}/build`); apps = apps.map(y => (y.id === x.id ? r.app : y));
                note('Your app is being built. This takes about 10–15 minutes — we’ll email you when it’s ready.'); track('app_builder_build');
            } else if (act === 'keystore') {
                const r = await api('GET', `/builder/apps/${x.id}/keystore`), bytes = Uint8Array.from(atob(r.keystore), ch => ch.charCodeAt(0));
                const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([bytes], { type: 'application/octet-stream' })); a.download = r.file; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 2000);
                window.sweet({ icon: 'info', title: 'Keep these safe', html: `<p>You need the key file and these details only if you ever move the app to another developer or build tool.</p><p><b>Key alias:</b> <code>${esc(r.alias)}</code><br><b>Password:</b> <code>${esc(r.password)}</code></p>`, confirm: 'Got it' });
                track('app_builder_keystore');
            } else if (act === 'delete') {
                if (!await ask('Delete this app?', 'Its builds, download links and signing key are deleted, and phones that have it stop getting your changes. If it’s on Google Play, you won’t be able to update it from here any more. This cannot be undone.', 'Delete app', true)) return;
                await api('POST', `/builder/apps/${x.id}/delete`); apps = apps.filter(y => y.id !== x.id); D.id = null; D.dirty = true; D.iconDirty = true;
                // its pictures were deleted with it: keep only what's still in this browser
                A().tabs.forEach(tab => (tab.blocks || []).forEach(b => { if (b.image && !b.image.src?.startsWith('data:')) b.image = null; if (b.images) b.images = b.images.filter(i => i.src?.startsWith('data:')); (b.items || []).forEach(i => { if (i.image && !i.image.src?.startsWith('data:')) i.image = null; }); }));
                save(); drawMine(); drawBlocks(); drawPhone(); syncSave();
                note('App deleted. Your design is still here if you want to save it again (pictures need adding again).');
            }
        } catch (ex) { note(ex.message); if (ex.status === 401) signIn(drawBuild); }
        finally { t.disabled = false; drawBuild(); }
    });

    /* ------------------------------------------------ start */
    $('[data-add-types]').innerHTML = Object.entries(BLOCKS).map(([k, v]) => `<button type="button" data-add-type="${k}">${svgIcon(v.icon, 'currentColor', 20)}<span>${v.name}</span></button>`).join('');
    loadLogo().then(() => { drawAll(); if (document.fonts?.ready) document.fonts.ready.then(drawPhone); });
    if (token) loadAccount().catch(() => {});
})();
