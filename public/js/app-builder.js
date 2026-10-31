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
    const BLOCK_ICONS = { heading: 'M6 4v16M18 4v16M6 12h12', text: 'M4 6h16M4 10h16M4 14h16M4 18h10', button: 'M3 8.5A2.5 2.5 0 0 1 5.5 6h13A2.5 2.5 0 0 1 21 8.5v7a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 15.5zM8 12h8', divider: 'M3 12h18M7 7h10M7 17h10', list: 'M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01', gallery: 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
        slider: 'M6 6h12v10H6zM2.5 8v6M21.5 8v6M10 20h.01M12 20h.01M14 20h.01', cards: 'M3 5h8v6H3zM13 5h8v6h-8zM3 13h8v6H3zM13 13h8v6h-8z', faq: 'M9.1 9a3 3 0 1 1 4.2 2.7c-.8.4-1.3 1.2-1.3 2.1v.2M12 18h.01M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z',
        social: 'M18 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM6 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM18 22a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM8.6 13.5l6.8 4M15.4 6.5l-6.8 4', notice: 'M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z', spacer: 'M12 3v18M8 7l4-4 4 4M8 17l4 4 4-4' };
    const svgIcon = (name, color, size = 22) => `<svg viewBox="0 0 24 24" width="${size}" height="${size}" fill="none" stroke="${color}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${ICONS[name] || BLOCK_ICONS[name] || ICONS.home}"/></svg>`;
    const STORE = 'owlvix-app-builder';
    const MAX_TABS = 15, MAX_BAR = 5, MAX_BLOCKS = 40, MAX_PENDING = 9e6; // MAX_PENDING: new pictures (as text) before a save is needed

    /* ------------------------------------------------ blocks: what the builder offers and their starting values */
    const BLOCKS = {
        hero: { name: 'Banner', icon: 'image', make: () => ({ title: 'Welcome', text: 'Tell people what you do in a line or two.', image: null, button: { label: '', action: { kind: '', value: '' } } }) },
        heading: { name: 'Heading', icon: 'heading', make: () => ({ text: 'Heading', align: 'left' }) },
        text: { name: 'Text', icon: 'text', make: () => ({ text: '', align: 'left' }) },
        image: { name: 'Picture', icon: 'image', make: () => ({ image: null, caption: '', alt: '', round: true, action: { kind: '', value: '' } }) },
        button: { name: 'Button', icon: 'button', make: () => ({ label: 'Call us', style: 'solid', action: { kind: 'tel', value: '' } }) },
        list: { name: 'List / menu / prices', icon: 'list', make: () => ({ title: '', items: [{ title: 'Item', text: '', price: '', image: null, action: { kind: '', value: '' } }] }) },
        gallery: { name: 'Gallery', icon: 'gallery', make: () => ({ images: [], columns: 2 }) },
        contact: { name: 'Contact', icon: 'phone', make: () => ({ title: 'Get in touch', phone: '', whatsapp: '', email: '', address: '', website: '' }) },
        hours: { name: 'Opening hours', icon: 'calendar', make: () => ({ title: 'Opening hours', rows: [{ day: 'Mon – Fri', time: '9:00 – 17:00' }, { day: 'Sat – Sun', time: 'Closed' }] }) },
        video: { name: 'YouTube video', icon: 'play', make: () => ({ url: '', title: '' }) },
        divider: { name: 'Divider', icon: 'divider', make: () => ({}) },
        slider: { name: 'Picture slider', icon: 'slider', make: () => ({ images: [] }) },
        cards: { name: 'Feature cards', icon: 'cards', make: () => ({ title: '', columns: 2, items: [newItem('cards'), { ...newItem('cards'), icon: 'heart', title: 'Made with care' }] }) },
        reviews: { name: 'Reviews', icon: 'star', make: () => ({ title: 'What customers say', items: [newItem('reviews')] }) },
        faq: { name: 'FAQ', icon: 'faq', make: () => ({ title: 'Questions', items: [newItem('faq')] }) },
        social: { name: 'Social links', icon: 'social', make: () => ({ title: 'Follow us', links: {} }) },
        notice: { name: 'Notice', icon: 'notice', make: () => ({ text: 'We’re open on bank holidays!', tone: 'info' }) },
        spacer: { name: 'Space', icon: 'spacer', make: () => ({ size: 'medium' }) },
        products: { name: 'Products & orders', icon: 'cart', make: () => ({ title: '', items: [newItem('products'), { ...newItem('products'), name: 'Second product', price: '12' }] }) },
    };
    /** A new row for blocks with items. */
    const newItem = type => ({
        list: { title: '', text: '', price: '', image: null, action: { kind: '', value: '' } },
        cards: { icon: 'star', title: 'Great service', text: 'Say why people choose you.', action: { kind: '', value: '' } },
        reviews: { name: 'Happy customer', text: 'Lovely place, friendly people — highly recommended!', stars: 5 },
        faq: { q: 'Do I need to book?', a: 'Walk-ins are welcome, but booking means no waiting.' },
        products: { name: 'First product', price: '9.50', category: '', text: '', image: null },
    })[type];
    const MAX_ITEMS = { list: 50, cards: 12, reviews: 20, faq: 30, products: 60 };
    const MAX_PICS = { gallery: 24, slider: 12 };
    const SOCIAL = { instagram: ['Instagram', '#e1306c'], facebook: ['Facebook', '#1877f2'], tiktok: ['TikTok', '#111111'], youtube: ['YouTube', '#ff0000'], x: ['X', '#111111'], linkedin: ['LinkedIn', '#0a66c2'], pinterest: ['Pinterest', '#e60023'], snapchat: ['Snapchat', '#fffc00'] };
    const TONES = { info: ['#eff6ff', '#1d4ed8', 'Blue'], success: ['#ecfdf5', '#047857', 'Green'], warning: ['#fffbeb', '#b45309', 'Amber'] };
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
        app: { name: '', package: '', url: '', version_name: '1.0.0', external_links: 'browser', menu: 'show', privacy: { owner: '', email: '', extra: '' }, shop: { currency: '$', whatsapp: '', email: '', note: '' }, tabs: starter(),
            colors: { primary: '#16a34a', bar: '#ffffff', icon: '#16a34a', splash: '#ffffff' },
            offline: { title: '', text: '', button: '' } },
        icon: { mode: 'letter', letter: '', image: null, zoom: 70, fill: false },
        splash: { mode: 'icon', size: 'medium', image: null, bg: 'color', bgImage: null, bgDirty: false, hasVideo: false },
    });
    let D;
    try { D = JSON.parse(localStorage.getItem(STORE) || 'null'); } catch {}
    /** Fills in what older saved designs don't have. */
    const tidy = () => {
        if (!D?.app?.colors) D = fresh();
        // Designs kept from the website-only builder: their tabs were website pages
        D.app.tabs = (D.app.tabs || []).map((t, i) => ({ label: t.label || '', icon: t.icon || 'home', type: t.type || 'web', bar: t.bar ?? i < 5, menu: t.menu, path: t.path || '', blocks: Array.isArray(t.blocks) ? t.blocks : [] }));
        if (!D.app.tabs.length) D.app.tabs = starter();
        D.base ||= '';
        D.app.privacy = { owner: '', email: '', extra: '', ...(D.app.privacy || {}) };
        D.app.shop = { currency: '$', whatsapp: '', email: '', note: '', ...(D.app.shop || {}) };
        D.splash = { mode: 'icon', size: 'medium', image: null, bg: 'color', bgImage: null, bgDirty: false, hasVideo: false, ...(D.splash || {}) };
    };
    const inIdb = !!D?.idb; // the design was too big for localStorage (pictures): it's in IndexedDB, loaded below
    tidy();
    const MAX_VIDEO = 15 * 1048576, VIDEO_SECONDS = 15;
    let bgVideo = null; // { file, url } — a video picked here and not uploaded yet (it's too big to keep in the browser)
    /** Splash picture width on the phone, in Android dp (app.config.js imageWidth); the preview phone is about 0.75 px per dp. */
    const SPLASH_DP = { small: 120, medium: 180, large: 260 };
    const A = () => D.app;
    /* The design is kept in this browser until it's saved: in localStorage, or — when new pictures make it too big
       for localStorage's ~5 MB — in IndexedDB, which can hold far more. If neither works, the owner is told. */
    const idb = (mode, fn) => new Promise((res, rej) => {
        const open = indexedDB.open('owlvix-app-builder', 1);
        open.onupgradeneeded = () => open.result.createObjectStore('drafts');
        open.onerror = () => rej(open.error);
        open.onsuccess = () => { const tx = open.result.transaction('drafts', mode), r = fn(tx.objectStore('drafts')); tx.oncomplete = () => res(r?.result); tx.onerror = () => rej(tx.error); };
    });
    let draftAtRisk = false; // new pictures that this browser couldn't keep
    const save = () => {
        try { localStorage.setItem(STORE, JSON.stringify(D)); draftAtRisk = false; return; } catch {}
        const copy = JSON.parse(JSON.stringify(D));
        idb('readwrite', st => st.put(copy, 'draft'))
            .then(() => { try { localStorage.setItem(STORE, JSON.stringify({ idb: true, id: D.id })); } catch {} draftAtRisk = false; syncSave(); })
            .catch(() => { draftAtRisk = true; syncSave(); });
    };
    let view = 'app', activeTab = 0, previewMenu = false, previewIntro = false;
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
    /** Pages shown in the bottom bar (the others are only in the ☰ menu). */
    const inBar = (t, i) => (t.bar === undefined ? i < MAX_BAR : !!t.bar);
    /** Pages listed in the ☰ menu (by default the ones not in the bottom bar). */
    const inMenu = (t, i) => (t.menu === undefined ? !inBar(t, i) : !!t.menu);
    const barCount = () => A().tabs.filter(inBar).length;
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
    /**
     * The status-bar icon for push notifications: Android draws it as a white silhouette, so it's the logo's shape in
     * white (when the logo has see-through parts) or the letters, on transparent, 96 × 96.
     */
    const drawNotificationIcon = () => {
        const n = 96, c = document.createElement('canvas'); c.width = c.height = n;
        const x = c.getContext('2d');
        let shaped = false;
        if (D.icon.mode === 'image' && logo) {
            const t = document.createElement('canvas'); t.width = t.height = 32;
            const tx = t.getContext('2d'); tx.drawImage(logo, 0, 0, 32, 32);
            const px = tx.getImageData(0, 0, 32, 32).data;
            shaped = [0, 31, 32 * 31, 32 * 32 - 1].some(i => px[i * 4 + 3] < 200); // see-through corners: the logo has a shape
            if (shaped) {
                const r = Math.min(n * 0.86 / logo.width, n * 0.86 / logo.height);
                x.drawImage(logo, (n - logo.width * r) / 2, (n - logo.height * r) / 2, logo.width * r, logo.height * r);
                x.globalCompositeOperation = 'source-in'; x.fillStyle = '#ffffff'; x.fillRect(0, 0, n, n);
            }
        }
        if (!shaped) {
            const t = initials();
            x.fillStyle = '#ffffff'; x.textAlign = 'center'; x.textBaseline = 'middle';
            x.font = `800 ${t.length > 1 ? 50 : 64}px Inter, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif`;
            x.fillText(t, n / 2, n / 2 + 4);
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
    let splashPic = null; // the splash's own picture as an Image
    const loadSplashPic = () => new Promise(res => {
        if (!D.splash.image) { splashPic = null; return res(); }
        const img = new Image(); img.onload = () => { splashPic = img; res(); }; img.onerror = () => { splashPic = null; res(); }; img.src = D.splash.image;
    });
    /** Light colours far enough apart to read one on the other. */
    const contrast = (a, b) => { const l = h => { const [r, g, bl] = [1, 3, 5].map(i => parseInt(h.slice(i, i + 2), 16)); return r * 0.299 + g * 0.587 + bl * 0.114; }; return Math.abs(l(a) - l(b)); };
    /**
     * The splash picture, n×n on see-through: the app icon as a rounded square, the logo (or letters) on its own, or
     * another picture. Android shows it centred on the splash colour, SPLASH_DP wide.
     */
    const drawSplash = n => {
        const c = document.createElement('canvas'); c.width = c.height = n;
        const x = c.getContext('2d'), mode = D.splash.mode;
        const fit = img => { const r = Math.min(n / img.width, n / img.height); x.drawImage(img, (n - img.width * r) / 2, (n - img.height * r) / 2, img.width * r, img.height * r); };
        if (mode === 'image' && splashPic) fit(splashPic);
        else if (mode === 'logo' && D.icon.mode === 'image' && logo) fit(logo);
        else if (mode === 'logo') {
            const t = initials(), bg = A().colors.splash, col = contrast(A().colors.icon, bg) > 60 ? A().colors.icon : ink(bg);
            x.fillStyle = col; x.textAlign = 'center'; x.textBaseline = 'middle';
            x.font = `800 ${Math.round(n * (t.length > 1 ? 0.5 : 0.62))}px Inter, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif`;
            x.fillText(t, n / 2, n / 2 + n * 0.04);
        } else {
            x.save(); x.beginPath(); x.roundRect(0, 0, n, n, n * 0.22); x.clip(); x.drawImage(drawIcon(n), 0, 0, n, n); x.restore();
        }
        return c;
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
            case 'image': return b.image ? `<div class="ab-n-pad">${pv.tap(b.action, `<img src="${esc(imgSrc(b.image))}" alt="${esc(b.alt || b.caption || '')}" class="ab-n-img${b.round ? ' is-round' : ''}">`)}${b.caption ? `<p class="ab-n-cap">${esc(b.caption)}</p>` : ''}</div>` : '';
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
            case 'slider': return b.images?.length ? `<div class="ab-n-pad"><div class="ab-n-slide"><img src="${esc(imgSrc(b.images[0]))}" alt=""></div>${b.images.length > 1 ? `<div class="ab-n-dots">${b.images.map((_, i) => `<i${i ? '' : ` style="background:${c.primary};width:14px"`}></i>`).join('')}</div>` : ''}</div>` : '';
            case 'cards': {
                const items = (b.items || []).filter(i => i.title || i.text);
                return items.length ? `<div class="ab-n-pad">${b.title ? `<h5 class="ab-n-ct">${esc(b.title)}</h5>` : ''}<div class="ab-n-cards${b.columns === 1 ? ' is-one' : ''}">${items.map(i =>
                    `<div><span style="background:${c.primary}1a">${svgIcon(i.icon, c.primary, 15)}</span>${i.title ? `<b>${esc(i.title)}</b>` : ''}${i.text ? `<small>${esc(i.text)}</small>` : ''}</div>`).join('')}</div></div>` : '';
            }
            case 'reviews': {
                const items = (b.items || []).filter(i => i.text);
                return items.length ? `<div class="ab-n-revs">${b.title ? `<h5 class="ab-n-ct">${esc(b.title)}</h5>` : ''}<div class="ab-n-revrow">${items.map(i =>
                    `<div class="ab-n-rev"><span class="ab-n-stars">${'★'.repeat(i.stars || 5)}<u>${'★'.repeat(5 - (i.stars || 5))}</u></span><p>“${esc(i.text)}”</p>${i.name ? `<small>— ${esc(i.name)}</small>` : ''}</div>`).join('')}</div></div>` : '';
            }
            case 'faq': {
                const items = (b.items || []).filter(i => i.q);
                return items.length ? `<div class="ab-n-pad">${b.title ? `<h5 class="ab-n-ct">${esc(b.title)}</h5>` : ''}<div class="ab-n-card">${items.map((i, n) =>
                    `<div class="ab-n-faq"><div class="ab-n-row"><span class="ab-n-rb"><b>${esc(i.q)}</b></span><em style="color:${c.primary}">${n ? '+' : '−'}</em></div>${n ? '' : `<small>${esc(i.a)}</small>`}</div>`).join('')}</div></div>` : '';
            }
            case 'social': {
                const links = Object.entries(b.links || {}).filter(([k, v]) => SOCIAL[k] && v?.trim());
                return links.length ? `<div class="ab-n-pad">${b.title ? `<h5 class="ab-n-ct" style="text-align:center">${esc(b.title)}</h5>` : ''}<div class="ab-n-social">${links.map(([k]) => `<span style="background:${SOCIAL[k][1]};color:${k === 'snapchat' ? '#111' : '#fff'}">${SOCIAL[k][0]}</span>`).join('')}</div></div>` : '';
            }
            case 'notice': { const [bg, fg] = TONES[b.tone] || TONES.info; return b.text?.trim() ? `<div class="ab-n-pad ab-n-note" style="background:${bg};color:${fg};border-color:${fg}33">${esc(b.text)}</div>` : ''; }
            case 'spacer': return `<div style="height:${{ small: 6, large: 30 }[b.size] ?? 15}px"></div>`;
            case 'products': {
                const items = (b.items || []).filter(i => i.name?.trim()), cur = A().shop?.currency || '$';
                const price = p => (String(p ?? '').trim() === '' || isNaN(+String(p).replace(',', '.')) ? 'Ask us' : cur + (+String(p).replace(',', '.') % 1 ? (+String(p).replace(',', '.')).toFixed(2) : +String(p).replace(',', '.')));
                const cats = [...new Set(items.map(i => i.category?.trim() || ''))];
                return items.length ? `<div class="ab-n-pad">${b.title ? `<h5 class="ab-n-ct">${esc(b.title)}</h5>` : ''}${cats.map(cat => `${cat ? `<b class="ab-n-cat">${esc(cat)}</b>` : ''}<div class="ab-n-prods">${items.filter(i => (i.category?.trim() || '') === cat).map(i =>
                    `<div>${i.image ? `<img src="${esc(imgSrc(i.image))}" alt="">` : `<span style="background:${c.primary}14">${svgIcon('store', c.primary, 18)}</span>`}<b>${esc(i.name)}</b><em style="color:${c.primary}">${esc(price(i.price))}</em></div>`).join('')}</div>`).join('')}</div>` : '';
            }
            default: return '';
        }
    };

    const drawPhone = () => {
        const a = A(), c = a.colors, phone = $('[data-phone]'), tabs = a.tabs;
        const time = '9:41', barInk = ink(c.bar), muted = isDark(c.bar) ? 'rgba(255,255,255,.6)' : 'rgba(15,23,42,.5)';
        const status = `<div class="ab-ph__status" style="background:${c.bar};color:${barInk}"><span>${time}</span><span class="ab-ph__sys">▮▮▮ ◔ ▭</span></div>`;
        if (view === 'splash') {
            const intro = D.splash.bg === 'image' && D.splash.bgImage ? `<img src="${esc(D.splash.bgImage)}" alt="">`
                : D.splash.bg === 'video' && bgVideo ? `<video src="${esc(bgVideo.url)}" autoplay muted loop playsinline></video>`
                : D.splash.bg === 'video' && D.splash.hasVideo ? '<span class="ab-ph__vid">▶ Your video plays here</span>' : '';
            if (intro && previewIntro) {
                phone.innerHTML = `<div class="ab-ph__screen ab-ph__intro">${intro}<span class="ab-ph__skip">Skip</span><button type="button" class="ab-ph__step" data-pintro>← Splash</button></div>`;
                paintIcon($('[data-icon-prev]'));
                return;
            }
            const px = Math.round(SPLASH_DP[D.splash.size] * 0.75), dpr = Math.min(3, window.devicePixelRatio || 1);
            phone.innerHTML = `<div class="ab-ph__screen ab-ph__splash" style="background:${c.splash}"><canvas width="${px * dpr}" height="${px * dpr}" style="width:${px}px;height:${px}px" data-splash-icon></canvas>${intro ? '<button type="button" class="ab-ph__step" data-pintro>Then: intro →</button>' : ''}</div>`;
            $('[data-splash-icon]', phone).getContext('2d').drawImage(drawSplash(px * dpr), 0, 0);
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
            const bar = tabs.map((x, i) => i).filter(i => inBar(tabs[i], i)).slice(0, MAX_BAR), menuPages = tabs.map((x, i) => i).filter(i => inMenu(tabs[i], i)), hasMenu = a.menu !== 'hide' && menuPages.length > 0;
            const header = hasMenu ? `<div class="ab-ph__head" style="background:${c.bar};color:${barInk}"><button type="button" data-pmenu aria-label="Open menu"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button><b>${esc(t.label || 'Page')}</b></div>` : '';
            const drawer = hasMenu && previewMenu ? `<div class="ab-ph__drawer"><div><b>${esc(a.name || 'Your app')}</b>${menuPages.map(i => [tabs[i], i]).map(([x, i]) => `<button type="button" data-ptab="${i}" class="${i === activeTab ? 'is-on' : ''}" style="${i === activeTab ? `background:${c.primary}1a;color:${c.primary}` : ''}">${svgIcon(x.icon, i === activeTab ? c.primary : '#475569', 16)}<span>${esc(x.label || 'Page')}</span></button>`).join('')}</div><button type="button" class="ab-ph__shade" data-pmenu aria-label="Close menu"></button></div>` : '';
            phone.innerHTML = `<div class="ab-ph__screen">${status}${header}${body}
                ${bar.length > 1 ? `<div class="ab-ph__tabs" style="background:${c.bar}">${bar.map(i => {
                    const x = tabs[i], col = i === activeTab ? c.primary : muted;
                    return `<button type="button" data-ptab="${i}" style="color:${col}">${svgIcon(x.icon, col)}<span>${esc(x.label || 'Tab')}</span></button>`;
                }).join('')}</div>` : ''}${drawer}</div>`;
        }
        paintIcon($('[data-icon-prev]'));
    };

    /* ------------------------------------------------ editor: tabs */
    const drawTabs = () => {
        const tabs = A().tabs, base = siteUrl() ? siteUrl().href.replace(/[?#].*$/, '').replace(/\/$/, '') + '/' : 'https://yourwebsite.com/';
        $('[data-tabs]').innerHTML = tabs.map((t, i) => `<div class="ab-tab${i === activeTab ? ' is-on' : ''}" data-tabrow="${i}">
            <select data-t="icon" data-i="${i}" aria-label="Tab ${i + 1} icon">${Object.keys(ICONS).map(k => `<option value="${k}" ${t.icon === k ? 'selected' : ''}>${ICON_NAMES[k] || k}</option>`).join('')}</select>
            <span class="ab-tab__ic" aria-hidden="true">${svgIcon(t.icon, 'currentColor')}</span>
            <input data-t="label" data-i="${i}" maxlength="24" value="${esc(t.label)}" placeholder="Name" aria-label="Page ${i + 1} name">
            <span class="fb-q__tools"><button type="button" data-tmove="-1" data-i="${i}" title="Move left" ${i ? '' : 'disabled'}>↑</button><button type="button" data-tmove="1" data-i="${i}" title="Move right" ${i < tabs.length - 1 ? '' : 'disabled'}>↓</button><button type="button" data-tdel="${i}" title="Remove" class="fb-del" ${tabs.length > 1 ? '' : 'disabled'}>✕</button></span>
            <div class="ab-tab__kind">
                <div class="ab-tab__where"><span>Show in:</span>
                    <label class="fb-check"><input type="checkbox" data-t="bar" data-i="${i}" ${inBar(t, i) ? 'checked' : ''}> Bottom bar</label>
                    <label class="fb-check"><input type="checkbox" data-t="menu" data-i="${i}" ${inMenu(t, i) ? 'checked' : ''}> ☰ Menu</label></div>
                <select data-t="type" data-i="${i}" aria-label="Page ${i + 1} shows"><option value="screen" ${t.type !== 'web' ? 'selected' : ''}>App screen (native)</option><option value="web" ${t.type === 'web' ? 'selected' : ''}>A page of my website</option></select>
                ${t.type === 'web' ? `<div class="ab-tab__path"><span title="${esc(base)}">${esc(base.replace(/^https:\/\//, ''))}</span><input data-t="path" data-i="${i}" maxlength="200" value="${esc(t.path)}" placeholder="(home page)" aria-label="Tab ${i + 1} page" spellcheck="false" autocapitalize="off"></div>` : ''}
            </div>
        </div>`).join('');
        $('[data-add-tab]').disabled = tabs.length >= MAX_TABS;
        drawMenuNote();
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
    /** Rows of a block (list, cards, reviews, FAQ) with move/remove, and an "add" button. */
    const items = (b, label, body) => {
        const n = (b.items || []).length;
        return `<div class="ab-items">${(b.items || []).map((it, i) => `<div class="ab-item"><div class="ab-item__head"><b>${label} ${i + 1}</b><span class="fb-q__tools"><button type="button" data-imove="-1" data-ii="${i}" title="Move up" ${i ? '' : 'disabled'}>↑</button><button type="button" data-imove="1" data-ii="${i}" title="Move down" ${i < n - 1 ? '' : 'disabled'}>↓</button><button type="button" data-idel="${i}" title="Remove" class="fb-del">✕</button></span></div>${body(it, i)}</div>`).join('')}</div>
            <button type="button" class="btn btn--sm btn--ghost" data-iadd ${n >= MAX_ITEMS[b.type] ? 'disabled' : ''}>＋ Add ${label.toLowerCase()}</button>`;
    };
    const pics = (b, type) => `<div class="ab-gal">${(b.images || []).map((img, i) => `<span><img src="${esc(imgSrc(img))}" alt=""><button type="button" data-gdel="${i}" aria-label="Remove picture ${i + 1}">✕</button></span>`).join('')}</div>
        <button type="button" class="btn btn--sm btn--ghost" data-pick="images" ${(b.images || []).length >= MAX_PICS[type] ? 'disabled' : ''}>＋ Add pictures</button>`;
    const blockFields = b => {
        switch (b.type) {
            case 'hero': return field('Title', 'title', b.title, { max: 80 }) + field('Text', 'text', b.text, { max: 300, area: true, rows: 2 }) + imageField('Background picture', 'image', b.image)
                + field('Button label <small>— optional</small>', 'button.label', b.button?.label, { max: 40 }) + actionField('Button does', 'button.action', b.button?.action);
            case 'heading': return field('Heading', 'text', b.text, { max: 120 }) + align('align', b.align);
            case 'text': return field('Text', 'text', b.text, { max: 3000, area: true, rows: 5 }) + align('align', b.align);
            case 'image': return imageField('Picture', 'image', b.image) + field('Caption <small>— optional</small>', 'caption', b.caption, { max: 200 })
                + field('Describe the picture <small>— read aloud to blind users</small>', 'alt', b.alt, { max: 150, ph: 'e.g. Our bakery counter with fresh bread' })
                + `<label class="fb-check"><input type="checkbox" data-f="round" ${b.round ? 'checked' : ''}> Rounded corners</label>` + actionField('Tapping it', 'action', b.action);
            case 'button': return field('Label', 'label', b.label, { max: 40 }) + actionField('Tapping it', 'action', b.action)
                + `<label>Style<select data-f="style"><option value="solid" ${b.style !== 'outline' ? 'selected' : ''}>Filled</option><option value="outline" ${b.style === 'outline' ? 'selected' : ''}>Outline</option></select></label>`;
            case 'list': return field('Title <small>— optional</small>', 'title', b.title, { max: 80 }) + `<div class="ab-items">${(b.items || []).map((it, i) => `<div class="ab-item">
                    <div class="ab-item__head"><b>Item ${i + 1}</b><span class="fb-q__tools"><button type="button" data-imove="-1" data-ii="${i}" title="Move up" ${i ? '' : 'disabled'}>↑</button><button type="button" data-imove="1" data-ii="${i}" title="Move down" ${i < b.items.length - 1 ? '' : 'disabled'}>↓</button><button type="button" data-idel="${i}" title="Remove" class="fb-del">✕</button></span></div>
                    <div class="fb-2">${field('Name', `items.${i}.title`, it.title, { max: 80 })}${field('Price <small>— optional</small>', `items.${i}.price`, it.price, { max: 20, ph: '$10' })}</div>
                    ${field('Description <small>— optional</small>', `items.${i}.text`, it.text, { max: 400, area: true, rows: 2 })}
                    ${imageField('Picture <small>— optional</small>', `items.${i}.image`, it.image)}${actionField('Tapping it', `items.${i}.action`, it.action)}</div>`).join('')}</div>
                <button type="button" class="btn btn--sm btn--ghost" data-iadd ${(b.items || []).length >= 50 ? 'disabled' : ''}>＋ Add item</button>`;
            case 'gallery': return pics(b, 'gallery') + `
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
            case 'slider': return `<p class="fb-hint">Pictures people swipe through — best when they're all the same shape.</p>` + pics(b, 'slider');
            case 'cards': return field('Title <small>— optional</small>', 'title', b.title, { max: 80 })
                + `<label>Layout<select data-f="columns" data-num><option value="2" ${b.columns !== 1 ? 'selected' : ''}>Two side by side</option><option value="1" ${b.columns === 1 ? 'selected' : ''}>One per row</option></select></label>`
                + items(b, 'Card', (it, i) => `<div class="ab-item__icon"><label>Icon<select data-f="items.${i}.icon" data-redraw>${Object.keys(ICONS).map(k => `<option value="${k}" ${it.icon === k ? 'selected' : ''}>${ICON_NAMES[k] || k}</option>`).join('')}</select></label>${field('Title', `items.${i}.title`, it.title, { max: 60 })}</div>`
                    + field('Text <small>— optional</small>', `items.${i}.text`, it.text, { max: 200, area: true, rows: 2 }) + actionField('Tapping it', `items.${i}.action`, it.action));
            case 'reviews': return field('Title <small>— optional</small>', 'title', b.title, { max: 80 })
                + items(b, 'Review', (it, i) => field('What they said', `items.${i}.text`, it.text, { max: 500, area: true, rows: 3 })
                    + `<div class="fb-2">${field('Name <small>— optional</small>', `items.${i}.name`, it.name, { max: 60 })}<label>Stars<select data-f="items.${i}.stars" data-num>${[5, 4, 3, 2, 1].map(n => `<option value="${n}" ${+it.stars === n ? 'selected' : ''}>${'★'.repeat(n)}</option>`).join('')}</select></label></div>`);
            case 'faq': return field('Title <small>— optional</small>', 'title', b.title, { max: 80 })
                + items(b, 'Question', (it, i) => field('Question', `items.${i}.q`, it.q, { max: 160 }) + field('Answer', `items.${i}.a`, it.a, { max: 1500, area: true, rows: 3 }));
            case 'social': return field('Title <small>— optional</small>', 'title', b.title, { max: 80 }) + '<p class="fb-hint">Paste the full address of each page you have — leave the rest empty.</p><div class="fb-2">'
                + Object.entries(SOCIAL).map(([k, [name]]) => field(name, `links.${k}`, b.links?.[k], { max: 300, type: 'url', ph: 'https://' })).join('') + '</div>';
            case 'notice': return field('Text', 'text', b.text, { max: 300, area: true, rows: 2 })
                + `<label>Colour<select data-f="tone">${Object.entries(TONES).map(([k, t]) => `<option value="${k}" ${b.tone === k ? 'selected' : ''}>${t[2]}</option>`).join('')}</select></label>`;
            case 'products': return field('Title <small>— optional</small>', 'title', b.title, { max: 80 })
                + `<p class="fb-hint">People tap a product, choose how many and send you the order on WhatsApp or by email (set up under “More settings → Orders”).</p>`
                + items(b, 'Product', (it, i) => `<div class="fb-2">${field('Name', `items.${i}.name`, it.name, { max: 80 })}${field(`Price <small>— ${esc(A().shop?.currency || '$')}, empty = “Ask us”</small>`, `items.${i}.price`, it.price, { max: 12, mode: 'decimal', ph: '9.50' })}</div>`
                    + field('Category <small>— optional, e.g. Drinks</small>', `items.${i}.category`, it.category, { max: 40 })
                    + field('Description <small>— optional</small>', `items.${i}.text`, it.text, { max: 400, area: true, rows: 2 })
                    + imageField('Picture <small>— optional</small>', `items.${i}.image`, it.image));
            case 'spacer': return `<label>Height<select data-f="size">${['small', 'medium', 'large'].map(k => `<option value="${k}" ${b.size === k ? 'selected' : ''}>${k[0].toUpperCase() + k.slice(1)}</option>`).join('')}</select></label>`;
            default: return '';
        }
    };
    const blockSummary = b => {
        const s = b.title || b.text || b.label || b.caption || (b.items?.[0]?.title) || (b.items?.[0]?.name) || (b.images?.length ? b.images.length + ' pictures' : '') || b.phone || b.email || '';
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
    const drawSplashSettings = () => {
        $$('[data-splash-mode]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.splashMode === D.splash.mode)));
        $$('[data-splash-size]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.splashSize === D.splash.size)));
        $('[data-splash-file-wrap]').hidden = D.splash.mode !== 'image';
        $('[data-splash-file-title]').textContent = D.splash.image ? 'Change picture' : 'Upload a picture';
        $('[data-splash-file-wrap]').classList.toggle('has-file', !!D.splash.image);
        const n = $('[data-splash-note]');
        n.hidden = !(D.splash.mode === 'logo' && D.icon.mode !== 'image');
        n.textContent = 'No logo uploaded, so your letters are shown. Upload a logo under “Name & icon” to use it here.';
        const bg = D.splash.bg;
        $$('[data-bg-mode]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.bgMode === bg)));
        $('[data-bg-file-wrap]').hidden = bg === 'color';
        const f = $('[data-bg-file]');
        f.accept = bg === 'video' ? 'video/mp4' : 'image/png,image/jpeg,image/webp';
        const has = bg === 'image' ? !!D.splash.bgImage : !!(bgVideo || D.splash.hasVideo);
        $('[data-bg-file-title]').textContent = has ? (bg === 'video' ? 'Change video' : 'Change picture') : bg === 'video' ? 'Upload a video' : 'Upload a picture';
        $('[data-bg-file-hint]').textContent = bg === 'video' ? `MP4, up to ${VIDEO_SECONDS} seconds and ${MAX_VIDEO / 1048576} MB — plays without sound, with a Skip button` : 'A tall (portrait) picture works best — shown full screen for 2.5 seconds';
        $('[data-bg-file-wrap]').classList.toggle('has-file', has);
        const bn = $('[data-bg-note]');
        bn.hidden = bg === 'color';
        bn.textContent = bg === 'video' && bgVideo ? 'The video uploads when you save.' : 'Android first shows your colour and picture above for a moment, then this intro. It’s built into the app, so changing it needs a new build.';
    };
    /** Warns when pages would be hard to reach: not in the bottom bar and no ☰ menu. */
    const drawMenuNote = () => {
        const hidden = A().menu === 'hide', lost = A().tabs.filter((t, i) => !inBar(t, i) && (hidden || !inMenu(t, i))).map(t => t.label || 'Page');
        const n = $('[data-menu-note]');
        n.hidden = !lost.length;
        n.textContent = `${lost.join(', ')} ${lost.length > 1 ? 'are' : 'is'} in neither the bottom bar nor the ☰ menu, so people can only open ${lost.length > 1 ? 'them' : 'it'} from a button on another page (Tapping it → Open a tab).`;
    };
    const drawSettings = () => {
        const a = A();
        $$('[data-a]').forEach(el => (el.value = a[el.dataset.a] ?? ''));
        $('[data-menu]').value = a.menu === 'hide' ? 'hide' : 'show';
        drawMenuNote();
        $$('[data-c]').forEach(el => (el.value = a.colors[el.dataset.c]));
        $$('[data-o]').forEach(el => (el.value = a.offline[el.dataset.o] ?? ''));
        $$('[data-pv]').forEach(el => (el.value = a.privacy[el.dataset.pv] ?? ''));
        $$('[data-shop]').forEach(el => (el.value = a.shop[el.dataset.shop] ?? ''));
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
        drawSplashSettings();
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
        if (el.matches('[data-menu]')) { a.menu = el.value; drawMenuNote(); previewMenu = false; view = 'app'; $$('[data-view]').forEach(x => x.setAttribute('aria-pressed', String(x.dataset.view === 'app'))); drawPhone(); markDirty(); return; }
        if (el.dataset.a) {
            a[el.dataset.a] = el.value;
            if (el.dataset.a === 'name') { if (!current()?.version_code && (!a.package || a.package === D.autoPackage)) { a.package = D.autoPackage = suggestPackage(); $('[data-a="package"]').value = a.package; } if (D.icon.mode === 'letter' && !D.icon.letter) D.iconDirty = true; }
            if (el.dataset.a === 'url') { drawTabs(); drawBlocks(); }
        } else if (el.dataset.c) { a.colors[el.dataset.c] = el.value; if (el.dataset.c === 'icon' || el.dataset.c === 'splash') D.iconDirty = true; if (el.dataset.c === 'splash' && view !== 'splash') showSplash(); }
        else if (el.dataset.o) a.offline[el.dataset.o] = el.value;
        else if (el.dataset.pv) a.privacy[el.dataset.pv] = el.value;
        else if (el.dataset.shop) { a.shop[el.dataset.shop] = el.value; if (el.dataset.shop === 'currency') drawBlocks(); }
        else if (el.dataset.t) { // (tab fields carry data-i too — their index — so they're matched first)
            const t = a.tabs[+el.dataset.i]; activeTab = +el.dataset.i;
            if (el.dataset.t === 'bar') {
                if (el.checked && barCount() >= MAX_BAR) { el.checked = false; note(`Up to ${MAX_BAR} pages fit in the bottom bar. Untick another page first — the rest stay in the ☰ menu.`); return; }
                if (t.menu === undefined) t.menu = inMenu(t, +el.dataset.i); // keep the menu as it was
                t.bar = el.checked; drawTabs(); drawPhone(); markDirty(); return;
            }
            if (el.dataset.t === 'menu') { if (t.bar === undefined) t.bar = inBar(t, +el.dataset.i); t.menu = el.checked; previewMenu = false; drawTabs(); drawPhone(); markDirty(); return; }
            t[el.dataset.t] = el.value;
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
    const useSplash = async file => {
        if (!file) return;
        try { D.splash.image = await readLogo(file); D.splash.mode = 'image'; await loadSplashPic(); showSplash(); markDirty(true); track('app_builder_splash'); }
        catch (ex) { note(ex.message); }
    };
    const sdrop = $('[data-splash-file-wrap]');
    ['dragenter', 'dragover'].forEach(ev => sdrop.addEventListener(ev, e => { e.preventDefault(); sdrop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach(ev => sdrop.addEventListener(ev, () => sdrop.classList.remove('is-over')));
    sdrop.addEventListener('drop', e => { e.preventDefault(); useSplash(e.dataTransfer?.files?.[0]); });
    const useBackground = async file => {
        if (!file) return;
        try {
            if (D.splash.bg === 'video') {
                if (file.type !== 'video/mp4') throw new Error('Please pick an MP4 video.');
                if (file.size > MAX_VIDEO) throw new Error(`That video is too big. Please use one under ${MAX_VIDEO / 1048576} MB (shorter, or exported at 720p).`);
                const url = URL.createObjectURL(file);
                const secs = await new Promise(res => { const v = document.createElement('video'); v.preload = 'metadata'; v.onloadedmetadata = () => res(v.duration); v.onerror = () => res(NaN); v.src = url; });
                if (!Number.isFinite(secs)) { URL.revokeObjectURL(url); throw new Error('We couldn’t play that video. Please export it as an H.264 MP4.'); }
                if (secs > VIDEO_SECONDS + 0.5) note(`Your video is ${Math.round(secs)} seconds — the app shows the first ${VIDEO_SECONDS}.`);
                if (bgVideo) URL.revokeObjectURL(bgVideo.url);
                bgVideo = { file, url };
            } else {
                const img = await loadPicture(file, 300);
                D.splash.bgImage = shrink(img, 1920, 'image/jpeg', 0.85).url; D.splash.bgDirty = true;
            }
            previewIntro = true; showSplash(); markDirty(); track('app_builder_intro', { kind: D.splash.bg });
        } catch (ex) { note(ex.message); }
    };
    const bdrop = $('[data-bg-file-wrap]');
    ['dragenter', 'dragover'].forEach(ev => bdrop.addEventListener(ev, e => { e.preventDefault(); bdrop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach(ev => bdrop.addEventListener(ev, () => bdrop.classList.remove('is-over')));
    bdrop.addEventListener('drop', e => { e.preventDefault(); useBackground(e.dataTransfer?.files?.[0]); });
    /** Sends the picked video to the server in 2 MB parts (under the server's upload limit). */
    const uploadVideo = async () => {
        if (!bgVideo || !D.id) return true;
        const { file } = bgVideo, size = 2 * 1048576;
        let r;
        for (let offset = 0; offset < file.size; offset += size) {
            $('[data-status]').textContent = `Uploading your intro video… ${Math.round(offset / file.size * 100)}%`;
            const res = await fetch(`/builder/apps/${D.id}/splash-video?offset=${offset}&total=${file.size}`, { method: 'POST', body: file.slice(offset, offset + size),
                headers: { Accept: 'application/json', 'Content-Type': 'application/octet-stream', 'X-CSRF-TOKEN': csrf, 'X-Builder-Token': token } });
            r = await res.json().catch(() => ({}));
            if (!res.ok || r.ok === false) throw new Error(r.error || 'The video didn’t upload. Please try again.');
        }
        URL.revokeObjectURL(bgVideo.url); bgVideo = null; D.splash.hasVideo = true;
        if (r.app) apps = apps.map(y => (y.id === r.app.id ? r.app : y));
        return true;
    };
    /** Show the splash in the preview phone (while its settings change). */
    const showSplash = () => { view = 'splash'; $$('[data-view]').forEach(x => x.setAttribute('aria-pressed', String(x.dataset.view === 'splash'))); drawSplashSettings(); drawPhone(); };
    root.addEventListener('change', async e => {
        if (e.target.matches('[data-bg-file]')) { await useBackground(e.target.files?.[0]); e.target.value = ''; return; }
        if (e.target.matches('[data-splash-file]')) { await useSplash(e.target.files?.[0]); e.target.value = ''; return; }
        if (e.target === photoInput) {
            const files = [...(photoInput.files || [])]; photoInput.value = '';
            if (!files.length || !picking) return;
            const { block: b, path } = picking; picking = null;
            try {
                for (const file of path === 'images' ? files.slice(0, (MAX_PICS[b.type] || 24) - (b.images || []).length) : files.slice(0, 1)) {
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
        else if (t.dataset.splashMode) { D.splash.mode = t.dataset.splashMode; previewIntro = false; showSplash(); markDirty(true); if (D.splash.mode === 'image' && !D.splash.image) $('[data-splash-file]').click(); }
        else if (t.dataset.bgMode) {
            D.splash.bg = t.dataset.bgMode; previewIntro = D.splash.bg !== 'color'; showSplash(); markDirty();
            const need = D.splash.bg === 'image' ? !D.splash.bgImage : D.splash.bg === 'video' ? !(bgVideo || D.splash.hasVideo) : false;
            if (need) $('[data-bg-file]').click();
        }
        else if (t.matches('[data-pintro]')) { previewIntro = !previewIntro; drawPhone(); }
        else if (t.dataset.splashSize) { D.splash.size = t.dataset.splashSize; previewIntro = false; showSplash(); markDirty(true); }
        else if (t.matches('[data-view-splash]')) showSplash();
        else if (t.dataset.tpl) useTemplate(t.dataset.tpl);
        else if (t.dataset.iconMode) {
            D.icon.mode = t.dataset.iconMode; drawSettings(); drawPhone(); markDirty(true);
            if (D.icon.mode === 'image' && !D.icon.image) $('[data-icon-file]').click();
        } else if (t.dataset.ptab) { previewMenu = false; selectTab(+t.dataset.ptab); }
        else if (t.dataset.pmenu !== undefined) { previewMenu = !previewMenu; drawPhone(); }
        else if (t.dataset.pact) {
            const act = JSON.parse(t.dataset.pact);
            if (act.kind === 'tab' && tabs[+act.value]) { selectTab(+act.value); drawTabs(); }
        } else if (t.matches('[data-add-tab]')) {
            if (tabs.length >= MAX_TABS) return;
            const used = tabs.map(x => x.icon), icon = ['grid', 'info', 'chat', 'calendar', 'store', 'location'].find(k => !used.includes(k)) || 'star';
            tabs.push({ label: ICON_NAMES[icon], icon, type: 'screen', bar: barCount() < MAX_BAR, menu: barCount() >= MAX_BAR, path: '', blocks: [{ type: 'heading', text: ICON_NAMES[icon], align: 'left' }] });
            if (!tabs[tabs.length - 1].bar) note('This page is in the ☰ menu — the bottom bar is full (5 pages).');
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
            else if (t.dataset.iadd !== undefined) { if (b.items.length < MAX_ITEMS[b.type]) b.items.push(b.type === 'list' ? newItem('list') : { ...newItem(b.type), title: '', text: '', q: '', a: '', name: '' }); }
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
        if (draftAtRisk && D.dirty) { $('[data-status]').textContent = '⚠️ This browser can’t keep your new pictures — save now so you don’t lose them.'; return; }
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
            name: a.name.trim(), package: a.package.trim().toLowerCase(), url: a.url.trim(), version_name: a.version_name.trim() || '1.0.0', external_links: a.external_links, menu: a.menu === 'hide' ? 'hide' : 'show', privacy: a.privacy, shop: a.shop,
            status_bar: isDark(a.colors.bar) ? 'light' : 'dark', colors: a.colors, offline: a.offline,
            tabs: a.tabs.map((t, i) => (t.type === 'web' ? { label: t.label.trim(), icon: t.icon, type: 'web', bar: inBar(t, i), menu: inMenu(t, i), url: tabUrl(t) } : { label: t.label.trim(), icon: t.icon, type: 'screen', bar: inBar(t, i), menu: inMenu(t, i), blocks: t.blocks.map(blockOut) })),
        };
    };
    const check = () => {
        const a = A();
        if (!a.name.trim()) return 'Please give your app a name.';
        if (D.icon.mode === 'image' && !logo) return 'Please add your logo, or switch the icon to letters.';
        if (a.tabs.some(t => !t.label.trim())) return 'Please give each tab a name.';
        if (a.tabs.some(t => (t.blocks || []).some(b => b.type === 'products' && (b.items || []).some(i => i.name?.trim()))) && !a.shop.whatsapp.trim() && !a.shop.email.trim()) return 'Your app has products: add a WhatsApp number or email for orders under “More settings → Orders”.';
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
                design: { mode: D.icon.mode, letter: D.icon.letter, zoom: D.icon.zoom, fill: D.icon.fill }, logo: D.icon.mode === 'image' ? D.icon.image : undefined,
                notification_icon: drawNotificationIcon().toDataURL('image/png'),
                splash: drawSplash(1024).toDataURL('image/png'), splash_design: { mode: D.splash.mode === 'image' && !splashPic ? 'icon' : D.splash.mode, size: D.splash.size }, splash_image: D.splash.mode === 'image' && splashPic ? D.splash.image : undefined });
            if (D.iconDirty || !D.id) addIcon();
            body.splash_bg_mode = D.splash.bg;
            if (D.splash.bg === 'image' && D.splash.bgImage && (D.splash.bgDirty || !D.id)) body.splash_bg = D.splash.bgImage;
            let r;
            try { r = await api('POST', '/builder/apps', body); }
            catch (ex) { if (ex.status === 404 && D.id) { D.id = null; delete body.id; addIcon(); r = await api('POST', '/builder/apps', body); } else throw ex; }
            const first = !D.id; D.id = r.app.id; D.dirty = false; D.iconDirty = false; D.splash.bgDirty = false;
            D.base = r.app.content_url.replace(/content\.json$/, '');
            eachImage(img => { if (img.src?.startsWith('data:') && img.name) { img.src = img.name; delete img.name; } }); // now stored on the server
            save();
            apps = [...(apps || []).filter(x => x.id !== r.app.id), r.app]; drawMine(); drawSettings();
            if (D.splash.bg === 'video' && bgVideo) {
                try { await uploadVideo(); }
                catch (ex) { D.dirty = true; save(); note(ex.message); return false; }
            }
            D.splash.hasVideo = D.splash.bg === 'video' && (current()?.settings?.splash?.bg === 'video' || D.splash.hasVideo); drawSettings();
            note(first ? 'App saved! Now open “Build & download” to make your Android app.'
                : r.app.out_of_date?.length ? `Saved. Your screens update on phones within a minute — for the rest, build again (${outOfDate(r.app)}).`
                : r.app.version_code ? 'Saved — phones with your app will show the changes within a minute. No new build needed.' : 'Changes saved.');
            track(first ? 'app_builder_create' : 'app_builder_save', { tabs: A().tabs.length });
            return true;
        } catch (ex) { note(ex.message); if (ex.status === 401) signIn(saveApp); return false; }
        finally { syncSave(); }
    };
    $('[data-save]').addEventListener('click', saveApp);
    $('[data-new]').addEventListener('click', async () => {
        if (D.dirty && !await ask('Start a new app?', 'Unsaved changes to this one will be lost.', 'Start new app')) return;
        D = fresh(); logo = null; splashPic = null; activeTab = 0; save(); drawAll(); drawMine(); showTab('design'); $('[data-tpl-wrap]').open = true;
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
            ? { label: t.label, icon: t.icon, type: 'web', bar: t.bar, menu: t.menu, blocks: [], path: t.url === x.url ? '' : base && t.url.startsWith(base + '/') ? t.url.slice(base.length + 1) : t.url }
            : { label: t.label, icon: t.icon, type: 'screen', bar: t.bar, menu: t.menu, path: '', blocks: JSON.parse(JSON.stringify(t.blocks || [])).map(fillBlock) }));
        D = { id: x.id, dirty: false, iconDirty: false, autoPackage: null, base: x.content_url.replace(/content\.json$/, ''),
            app: { name: x.name, package: x.package, url: x.url || '', version_name: x.settings.version_name, external_links: x.settings.external_links, menu: x.settings.menu === 'hide' ? 'hide' : 'show', privacy: { owner: '', email: '', extra: '', ...(x.settings.privacy || {}) }, shop: { currency: '$', whatsapp: '', email: '', note: '', ...(x.settings.shop || {}), whatsapp: x.settings.shop?.whatsapp ? '+' + x.settings.shop.whatsapp : '' }, tabs: tabs.length ? tabs : starter(), colors: { ...x.settings.colors }, offline: { ...x.settings.offline } },
            icon: keepIcon, splash: same ? D.splash : { ...fresh().splash, ...(x.settings.splash || {}) } };
        if (!x.has_notification_icon) D.iconDirty = true; // apps saved before notifications: send the icon on the next save
        activeTab = 0; save(); Promise.all([loadLogo(), loadSplashPic()]).then(drawAll); drawMine();
        if (!same) api('GET', `/builder/apps/${x.id}/icon`).then(r => {
            if (D.id !== x.id) return;
            D.icon = { ...fresh().icon, ...r.design, image: r.logo || null };
            if (D.icon.mode === 'image' && !D.icon.image) D.icon.mode = 'letter';
            D.splash = { ...fresh().splash, ...(r.splash_design || {}), image: r.splash_image || null, bgImage: r.splash_bg || null, hasVideo: r.splash_design?.bg === 'video' };
            if (D.splash.mode === 'image' && !D.splash.image) D.splash.mode = 'icon';
            save(); Promise.all([loadLogo(), loadSplashPic()]).then(drawAll);
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
        if (b.type === 'cards') out.items = (b.items || []).map(i => ({ icon: 'star', title: '', text: '', ...i, action: i.action || { kind: '', value: '' } }));
        if (b.type === 'social') out.links = { ...(b.links || {}) };
        if (b.type === 'products') out.items = (b.items || []).map(i => ({ name: '', price: '', category: '', text: '', image: null, ...i, price: i.price ?? '' }));
        return out;
    };

    /* ------------------------------------------------ build & download */
    let poll = null;
    const OUTDATED = { features: 'it doesn’t have the newest features (statistics, push notifications, ☰ menu, new blocks)', push: 'it can’t receive notifications yet',
        name: 'the name changed', icon: 'the icon changed', splash: 'the splash screen changed', intro: 'the intro changed' };
    const outOfDate = x => (x.out_of_date || []).map(k => OUTDATED[k] || k).join('; ');
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
        if (x.removed) {
            box.innerHTML = '<div class="fb-empty"><b>This app was taken down</b><p>It was reported and broke our rules, so it can’t be changed or built. If you think this is a mistake, reply to any email from us.</p></div>';
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
            ${x.out_of_date?.length ? `<p class="ab-warn">📱 Phones have an older version of your app — ${esc(outOfDate(x))}. Build again (and upload the new .aab to Google Play) to update it.</p>` : ''}
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
        ${statsHtml(x)}
        ${pushHtml(x)}
        ${kitHtml(x)}
        <div class="ab-ios"><b>🍎 Want it on the iPhone App Store?</b><p class="fb-hint">Apple only takes apps from the owner’s own developer account. We publish iPhone apps for you, with logins, payments and push notifications if you need them.</p><a href="#contact" class="btn btn--sm btn--ghost" data-act="ios">Ask for a free quote</a></div>
        <button type="button" class="fb-danger" data-act="delete">Delete this app</button>`;
        $$('[data-qr-for]', box).forEach(el => withQR(() => (el.innerHTML = window.OwlQR.svg(el.dataset.qrFor, { color: '#0f172a' }))));
        if (running) poll = setTimeout(refresh, 20000);
    };
    /* ------------------------------------------------ statistics (anonymous daily totals sent by phones) */
    const stats = {}; // app id → { at, data }
    const statsHtml = x => {
        if (!x.version_code) return '';
        const s = stats[x.id];
        if (!s || Date.now() - s.at > 300000) {
            stats[x.id] = { at: Date.now(), data: s?.data || null };
            api('GET', `/builder/apps/${x.id}/stats`).then(r => { stats[x.id] = { at: Date.now(), data: r }; if (current()?.id === x.id && !$('[data-panel="build"]').hidden) { const el = $('[data-stats]'); if (el) el.outerHTML = statsHtml(x); } }).catch(() => {});
        }
        const d = stats[x.id]?.data;
        if (!d) return '<div class="ab-stats" data-stats><b>📊 Statistics</b><p class="fb-hint">Loading…</p></div>';
        const fmt = n => Number(n).toLocaleString();
        const max = Math.max(1, ...d.days.map(r => r.active));
        const none = !d.days.some(r => r.active);
        const day = iso => new Date(iso + 'T12:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
        const topMax = Math.max(1, ...d.pages.map(p => p.views));
        return `<div class="ab-stats" data-stats><b>📊 Statistics <small>— last 30 days</small></b>
            <div class="ab-stats__tiles">
                <div><span>Installs</span><strong>${fmt(d.installs)}</strong><small>all time</small></div>
                <div><span>Daily users</span><strong>${fmt(d.daily_users)}</strong><small>average, last 7 days</small></div>
                <div><span>App opens</span><strong>${fmt(d.opens)}</strong><small>last 30 days</small></div>
            </div>
            ${none ? `<p class="fb-hint">No numbers yet. Phones send a summary once a day, so they appear the day after people start using ${x.out_of_date?.includes('features') ? 'the new build of ' : ''}your app.</p>` : `
            <figure class="ab-chart" aria-label="Phones that used the app each day">
                <figcaption>People using the app each day</figcaption>
                <div class="ab-chart__plot" role="list">${d.days.map(r => `<span role="listitem" class="ab-chart__bar" tabindex="0" style="--h:${Math.round(r.active / max * 100)}%"
                    data-tip="${esc(day(r.day))}: ${fmt(r.active)} ${r.active === 1 ? 'person' : 'people'}, ${fmt(r.opens)} opens${r.installs ? `, ${fmt(r.installs)} new` : ''}"
                    aria-label="${esc(day(r.day))}: ${r.active} people, ${r.opens} opens"><i></i></span>`).join('')}</div>
                <div class="ab-chart__axis"><span>${esc(day(d.days[0].day))}</span><span>Yesterday</span></div>
                <div class="ab-chart__tip" data-chart-tip hidden></div>
            </figure>`}
            ${d.pages.length ? `<div class="ab-stats__pages"><span class="ab-sub">Most viewed pages</span><ol>${d.pages.map(p => `<li><span>${esc(p.page)}</span><i style="--w:${Math.round(p.views / topMax * 100)}%"></i><b>${fmt(p.views)}</b></li>`).join('')}</ol></div>` : ''}
        </div>`;
    };
    // Hover / focus a bar: show its day's numbers
    const showTip = bar => {
        const tip = bar.closest('.ab-chart')?.querySelector('[data-chart-tip]'); if (!tip) return;
        tip.textContent = bar.dataset.tip; tip.hidden = false;
        const fig = bar.closest('.ab-chart').getBoundingClientRect(), b = bar.getBoundingClientRect();
        tip.style.left = Math.min(Math.max(0, b.left - fig.left + b.width / 2 - tip.offsetWidth / 2), fig.width - tip.offsetWidth) + 'px';
    };
    const hideTip = el => { const tip = el.closest('.ab-chart')?.querySelector('[data-chart-tip]'); if (tip) tip.hidden = true; };
    root.addEventListener('mouseover', e => { const bar = e.target.closest('.ab-chart__bar'); if (bar) showTip(bar); });
    root.addEventListener('mouseout', e => { const bar = e.target.closest('.ab-chart__bar'); if (bar && !bar.contains(e.relatedTarget)) hideTip(bar); });
    root.addEventListener('focusin', e => { if (e.target.matches?.('.ab-chart__bar')) showTip(e.target); });
    root.addEventListener('focusout', e => { if (e.target.matches?.('.ab-chart__bar')) hideTip(e.target); });

    /* ------------------------------------------------ push notifications */
    const pushes = {}; // app id → sent notifications (newest first)
    const pushDraft = { title: '', body: '', tab: '' };
    const pushHtml = x => {
        if (!x.push?.available) return '';
        if (!x.push.ready) {
            return `<div class="ab-push"><b>🔔 Push notifications</b><p class="fb-hint">${x.version_code ? 'Build your app again (and upload the new .aab to Google Play) — the version on phones can’t receive notifications yet.' : 'After your first build you can send a message to everyone who has your app — offers, news, reminders.'}</p></div>`;
        }
        if (!pushes[x.id]) api('GET', `/builder/apps/${x.id}/pushes`).then(r => { pushes[x.id] = r.pushes; if (current()?.id === x.id && !$('[data-panel="build"]').hidden) drawPushList(); }).catch(() => {});
        const tabs = A().tabs;
        return `<div class="ab-push"><b>🔔 Send a notification</b>
            <p class="fb-hint">It pops up on the phone of everyone who has your app and allowed notifications. Up to 3 a day — keep them useful.</p>
            <label>Title <small data-push-count="title">${pushDraft.title.length}/65</small><input data-push="title" maxlength="65" value="${esc(pushDraft.title)}" placeholder="Weekend offer 🎉"></label>
            <label>Message <small data-push-count="body">${pushDraft.body.length}/240</small><textarea data-push="body" rows="3" maxlength="240" placeholder="20% off all cakes this Saturday and Sunday.">${esc(pushDraft.body)}</textarea></label>
            <label>When tapped, open<select data-push="tab"><option value="">The app</option>${tabs.map((t, i) => `<option value="${i}" ${pushDraft.tab === String(i) ? 'selected' : ''}>${esc(t.label || 'Page ' + (i + 1))}</option>`).join('')}</select></label>
            <button type="button" class="btn btn--glow btn--sm" data-act="push-send">Send to everyone</button>
            <div class="ab-push__list" data-push-list>${pushListHtml(x.id)}</div></div>`;
    };
    const pushListHtml = id => {
        const list = pushes[id];
        if (!list) return '';
        if (!list.length) return '<p class="fb-hint">No notifications sent yet.</p>';
        return `<p class="ab-sub">Sent</p><ul>${list.map(p => `<li class="${p.status === 'failed' ? 'is-failed' : ''}"><b>${esc(p.title)}</b><span>${esc(p.body)}</span><small>${p.status === 'failed' ? 'Not sent · ' : ''}${esc(new Date(p.sent_at).toLocaleString())}</small></li>`).join('')}</ul>`;
    };
    const drawPushList = () => { const el = $('[data-push-list]'); if (el && current()) el.innerHTML = pushListHtml(current().id); };
    root.addEventListener('input', e => {
        const k = e.target.dataset?.push; if (!k) return;
        pushDraft[k] = e.target.value; const c = $(`[data-push-count="${k}"]`); if (c) c.textContent = `${e.target.value.length}/${k === 'title' ? 65 : 240}`;
    });
    const sendPush = async x => {
        if (!pushDraft.title.trim() || !pushDraft.body.trim()) { note('Please write a title and a message.'); return; }
        if (!await ask('Send this notification?', `“${pushDraft.title.trim()}” goes to everyone with ${x.name}. It can’t be taken back.`, 'Send')) return;
        const r = await api('POST', `/builder/apps/${x.id}/push`, { title: pushDraft.title.trim(), body: pushDraft.body.trim(), tab: pushDraft.tab });
        pushes[x.id] = r.pushes; Object.assign(pushDraft, { title: '', body: '', tab: '' });
        note('🔔 Sent! Phones get it within a few seconds.'); track('app_builder_push');
    };

    /* ------------------------------------------------ Google Play listing kit */
    const kitText = { short: '', full: '' };
    let kitOpen = false;
    root.addEventListener('toggle', e => { if (e.target.matches?.('.ab-kit')) kitOpen = e.target.open; }, true);
    /** Suggested store texts, written from the app's own pages and blocks. */
    const suggestTexts = () => {
        const a = A(), blocks = a.tabs.flatMap(t => t.blocks || []), hero = blocks.find(b => b.type === 'hero');
        const first = (hero?.text || blocks.find(b => b.type === 'text')?.text || '').trim();
        const short = (first || `${a.name} — everything about us in one app.`).replace(/\s+/g, ' ');
        const has = t => blocks.some(b => b.type === t);
        const points = [
            has('list') && 'See our menu, services and prices',
            (has('contact') || blocks.some(b => ['tel', 'whatsapp'].includes(b.action?.kind || b.button?.action?.kind))) && 'Call, email or message us in one tap',
            has('hours') && 'Check our opening hours',
            (has('gallery') || has('slider')) && 'Browse our photos',
            has('reviews') && 'Read what customers say',
            has('faq') && 'Find answers to common questions',
            has('video') && 'Watch our videos',
            has('social') && 'Follow us on social media',
        ].filter(Boolean);
        const full = [`${a.name}${hero?.title ? ' — ' + hero.title : ''}`, first, points.length ? 'In the app:\n' + points.map(p => '• ' + p).join('\n') : '',
            `Pages: ${a.tabs.map(t => t.label).filter(Boolean).join(', ')}.`, 'The app works offline and always shows our latest information.'].filter(Boolean).join('\n\n');
        return { short: short.length > 80 ? short.slice(0, 79).replace(/\s+\S*$/, '') + '…' : short, full: full.slice(0, 4000) };
    };
    const kitHtml = x => {
        if (!kitText.short) Object.assign(kitText, suggestTexts());
        const use = x.data_use || {}, dirty = D.dirty ? '<p class="fb-hint">Save your app first so the privacy policy shows your latest details.</p>' : '';
        const yes = `Answer <b>Yes</b> to collecting data, then tick <b>App activity → App interactions</b> (anonymous counts of opens and page views) — used for <b>Analytics</b>${use.push ? ', and <b>Device or other IDs</b> (push notifications use Firebase Cloud Messaging) — used for <b>App functionality</b>' : ''}. For each: collected, not shared, processed ephemerally: <b>No</b>, required: <b>No</b>. Data is encrypted in transit: <b>Yes</b>. Way to request deletion: <b>No</b> (nothing is linked to a person).${use.website ? ` Also answer for what <b>${esc(use.website)}</b> collects (forms, bookings, accounts).` : ''}`;
        return `<details class="fb-settings ab-kit" ${kitOpen ? 'open' : ''}><summary>📋 Google Play listing kit</summary>
            <p class="fb-hint">Everything Google Play asks for in the store listing — made from your app.</p>
            <h3 class="ab-sub">1. Graphics</h3>
            <div class="ab-kit__btns">
                <button type="button" class="btn btn--sm btn--ghost" data-act="kit-icon">⬇ App icon&nbsp;<small>512 × 512</small></button>
                <button type="button" class="btn btn--sm btn--ghost" data-act="kit-feature">⬇ Feature graphic&nbsp;<small>1024 × 500</small></button>
                <button type="button" class="btn btn--sm btn--glow" data-act="kit-shots">📸 Make phone screenshots</button>
            </div>
            <div class="ab-kit__shots" data-kit-shots></div>
            <h3 class="ab-sub">2. Texts</h3>
            <label>Short description <small data-kit-count="short">${kitText.short.length}/80</small><textarea data-kit="short" rows="2" maxlength="80">${esc(kitText.short)}</textarea></label>
            <button type="button" class="btn btn--sm btn--ghost" data-copyfrom='[data-kit="short"]'>Copy</button>
            <label>Full description <small data-kit-count="full">${kitText.full.length}/4000</small><textarea data-kit="full" rows="8" maxlength="4000">${esc(kitText.full)}</textarea></label>
            <button type="button" class="btn btn--sm btn--ghost" data-copyfrom='[data-kit="full"]'>Copy</button>
            <h3 class="ab-sub">3. Privacy policy</h3>
            <p class="fb-hint">Paste this link in Play Console → App content → Privacy policy. Add your business name and email under Design → More settings.</p>
            <div class="fb-copyrow"><input readonly value="${esc(x.privacy_url)}"><button type="button" class="btn btn--sm" data-copy="${esc(x.privacy_url)}">Copy</button></div>
            <a class="ab-link" href="${esc(x.privacy_url)}" target="_blank" rel="noopener">Open the policy</a>${dirty}
            <h3 class="ab-sub">4. App content answers</h3>
            <ul class="ab-kit__answers">
                <li><b>App access:</b> All functionality is available without special access.</li>
                <li><b>Ads:</b> No, my app does not contain ads.</li>
                <li><b>Data safety:</b> ${yes}</li>
                <li><b>Content rating:</b> fill in the questionnaire — for a business app most answers are <b>No</b>.</li>
                <li><b>Target audience:</b> choose <b>18 and over</b> unless your app is made for children (the review is quicker).</li>
                <li><b>News app, government app, financial or health features:</b> No (unless yours is one).</li>
                <li><b>App category:</b> pick the one that fits your business, e.g. Food &amp; Drink, Beauty, Health &amp; Fitness, Shopping or Business.</li>
            </ul>
        </details>`;
    };
    root.addEventListener('input', e => {
        const k = e.target.dataset?.kit; if (!k) return;
        kitText[k] = e.target.value; const c = $(`[data-kit-count="${k}"]`); if (c) c.textContent = `${e.target.value.length}/${k === 'short' ? 80 : 4000}`;
    });
    const download = (blob, name) => { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 4000); };
    const toBlob = (c, type = 'image/jpeg') => new Promise(res => c.toBlob(res, type, 0.92));
    const fileBase = () => slug(A().name) || 'app';
    /** Wraps text into lines that fit `max` px; returns at most `rows` lines. */
    const wrap = (x, text, max, rows) => {
        const out = []; let line = '';
        for (const w of String(text).split(/\s+/)) { const t = line ? line + ' ' + w : w; if (x.measureText(t).width > max && line) { out.push(line); line = w; } else line = t; }
        if (line) out.push(line);
        if (out.length > rows) { out.length = rows; out[rows - 1] = out[rows - 1].replace(/\s*\S*$/, '…'); }
        return out;
    };
    /** Google Play's feature graphic: 1024 × 500, no see-through parts (JPEG). */
    const featureGraphic = () => {
        const c = document.createElement('canvas'); c.width = 1024; c.height = 500;
        const x = c.getContext('2d'), col = A().colors.primary, on = ink(col);
        x.fillStyle = col; x.fillRect(0, 0, 1024, 500);
        const g = x.createLinearGradient(0, 0, 1024, 500); g.addColorStop(0, 'rgba(255,255,255,.14)'); g.addColorStop(1, 'rgba(0,0,0,.22)');
        x.fillStyle = g; x.fillRect(0, 0, 1024, 500);
        x.fillStyle = on === '#ffffff' ? 'rgba(255,255,255,.07)' : 'rgba(15,23,42,.06)';
        [[930, 60, 220], [860, 470, 160], [60, 520, 140]].forEach(([cx, cy, r]) => { x.beginPath(); x.arc(cx, cy, r, 0, Math.PI * 2); x.fill(); });
        x.save(); x.shadowColor = 'rgba(0,0,0,.28)'; x.shadowBlur = 30; x.shadowOffsetY = 12;
        x.beginPath(); x.roundRect(90, 140, 220, 220, 48); x.fillStyle = A().colors.icon; x.fill(); x.restore();
        x.save(); x.beginPath(); x.roundRect(90, 140, 220, 220, 48); x.clip(); x.drawImage(drawIcon(440), 90, 140, 220, 220); x.restore();
        x.fillStyle = on; x.textBaseline = 'alphabetic';
        let size = 64; x.font = `800 ${size}px Inter, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif`;
        while (x.measureText(A().name || 'My app').width > 600 && size > 36) { size -= 4; x.font = `800 ${size}px Inter, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif`; }
        x.fillText(A().name || 'My app', 360, 236);
        x.globalAlpha = .9; x.font = `500 28px Inter, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif`;
        wrap(x, kitText.short || suggestTexts().short, 600, 2).forEach((l, i) => x.fillText(l, 362, 290 + i * 40));
        x.globalAlpha = 1;
        return c;
    };
    /** The CSS that draws the preview phone, for the screenshot (an SVG picture can't see the page's stylesheets). */
    const previewCss = () => {
        let css = '';
        for (const sheet of document.styleSheets) {
            let rules; try { rules = sheet.cssRules; } catch { continue; }
            for (const r of rules || []) if (r.selectorText && /\.ab-(ph|n)-?_?/.test(r.selectorText)) css += r.cssText + '\n';
        }
        return css;
    };
    const asDataUrl = src => fetch(src, { mode: 'cors' }).then(r => (r.ok ? r.blob() : Promise.reject())).then(b => new Promise(res => { const f = new FileReader(); f.onload = () => res(f.result); f.readAsDataURL(b); })).catch(() => '');
    /**
     * A phone screenshot of one page, drawn from the live preview at 4× (1080 × 2160, the tallest Google Play takes).
     * The preview is copied into an SVG picture with its pictures and styles inlined, then drawn on a canvas.
     */
    const screenshot = async (i, kind = 'app') => {
        const keep = { view, activeTab, previewMenu, previewIntro };
        view = kind; activeTab = i; previewMenu = false; previewIntro = false; drawPhone();
        const screen = $('[data-phone] .ab-ph__screen').cloneNode(true);
        const canvases = [...$('[data-phone]').querySelectorAll('canvas')].map(cv => cv.toDataURL());
        ({ view, activeTab, previewMenu, previewIntro } = keep); drawPhone();
        [...screen.querySelectorAll('canvas')].forEach((cv, n) => { const im = document.createElement('img'); im.src = canvases[n]; im.style.cssText = cv.style.cssText; im.width = cv.width; cv.replaceWith(im); });
        for (const im of screen.querySelectorAll('img')) if (!im.src.startsWith('data:')) im.src = await asDataUrl(im.src) || 'data:,';
        for (const el of screen.querySelectorAll('[style*="url("]')) {
            const m = el.getAttribute('style').match(/url\(['"]?([^'")]+)['"]?\)/);
            if (m && !m[1].startsWith('data:')) { const d = await asDataUrl(m[1]); el.style.backgroundImage = d ? `url("${d}")` : 'none'; }
        }
        screen.querySelectorAll('[data-pintro],.ab-ph__step').forEach(el => el.remove());
        screen.style.cssText += ';height:540px;width:270px;border-radius:0;';
        const html = new XMLSerializer().serializeToString(screen);
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="270" height="540"><foreignObject width="270" height="540"><div xmlns="http://www.w3.org/1999/xhtml" style="width:270px;height:540px;overflow:hidden"><style>${previewCss().replace(/</g, '\\3c ')}</style>${html}</div></foreignObject></svg>`;
        const img = await new Promise((res, rej) => { const im = new Image(); im.onload = () => res(im); im.onerror = rej; im.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg); });
        const c = document.createElement('canvas'); c.width = 1080; c.height = 2160;
        const x = c.getContext('2d'); x.fillStyle = '#ffffff'; x.fillRect(0, 0, 1080, 2160); x.scale(4, 4); x.drawImage(img, 0, 0);
        return c;
    };
    const kitAction = async t => {
        t.disabled = true;
        try {
            if (t.dataset.act === 'kit-icon') download(await toBlob(drawIcon(512), 'image/png'), `${fileBase()}-icon-512.png`);
            else if (t.dataset.act === 'kit-feature') download(await toBlob(featureGraphic()), `${fileBase()}-feature-graphic.jpg`);
            else if (t.dataset.act === 'kit-shots') {
                const box = $('[data-kit-shots]'); box.innerHTML = '<p class="fb-hint">Making screenshots…</p>';
                const pages = A().tabs.map((tab, i) => [tab, i]).filter(([tab]) => tab.type !== 'web').slice(0, 6);
                const shots = [];
                for (const [tab, i] of pages) shots.push([tab.label || 'Page', await screenshot(i)]);
                if (shots.length < 2) shots.push(['Splash', await screenshot(0, 'splash')]);
                const items = [];
                for (const [label, c] of shots) items.push([label, URL.createObjectURL(await toBlob(c))]);
                box.innerHTML = `<p class="fb-hint">Tap a screenshot to download it (1080 × 2160). Google Play needs at least 2.</p><div class="ab-kit__grid">${items.map(([label, url], n) =>
                    `<a href="${url}" download="${fileBase()}-screen-${n + 1}.jpg" data-dl="screenshot"><img src="${url}" alt="Screenshot: ${esc(label)}"><span>${esc(label)}</span></a>`).join('')}</div>`;
                track('app_builder_play_kit', { shots: items.length });
            }
        } catch (ex) {
            note(/security|tainted/i.test(String(ex)) ? 'Your browser didn’t allow making screenshots. Please try Chrome, Edge or Firefox — or take them on your phone.' : 'We couldn’t make that. Please try again.');
            if (t.dataset.act === 'kit-shots') $('[data-kit-shots]').innerHTML = '';
        } finally { t.disabled = false; }
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
        const t = e.target.closest('[data-act],[data-copy],[data-copyfrom],[data-dl]'); if (!t) return;
        if (t.dataset.copyfrom) { const src = $(t.dataset.copyfrom); try { await navigator.clipboard.writeText(src.value); t.textContent = 'Copied ✓'; } catch { src.select(); } setTimeout(() => (t.textContent = 'Copy'), 2000); return; }
        if (t.dataset.dl) { track('app_builder_download', { kind: t.dataset.dl }); return; }
        if (t.dataset.act?.startsWith('kit-')) { kitAction(t); return; }
        if (t.dataset.act === 'push-send') { t.disabled = true; try { await sendPush(current()); } catch (ex) { note(ex.message); } finally { t.disabled = false; drawBuild(); } return; }
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

    /* ------------------------------------------------ templates (public/js/app-templates.js) */
    const TEMPLATES = window.OwlAppTemplates || [];
    const drawTemplates = () => {
        $('[data-tpl-wrap]').hidden = !TEMPLATES.length;
        $('[data-tpls]').innerHTML = TEMPLATES.map(t => {
            const c = t.colors, on = ink(c.primary), muted = isDark(c.bar) ? 'rgba(255,255,255,.5)' : 'rgba(15,23,42,.35)';
            return `<button type="button" class="ab-tplc" data-tpl="${t.id}" aria-label="Use the ${esc(t.name)} template">
                <span class="ab-tplc__ph" style="background:${c.bar}"><span class="ab-tplc__hero" style="background:${c.primary};color:${on}"><b>${esc(t.sample)}</b><i style="background:${on}"></i></span>
                    <span class="ab-tplc__body"><i></i><i class="s"></i><span><em></em><em></em></span></span>
                    <span class="ab-tplc__tabs">${t.tabs.slice(0, 4).map((x, i) => `<span style="color:${i ? muted : c.primary}">${svgIcon(x.icon, 'currentColor', 11)}</span>`).join('')}</span></span>
                <b>${esc(t.name)}</b><small>${esc(t.note)}</small></button>`;
        }).join('');
    };
    const useTemplate = async id => {
        const t = TEMPLATES.find(x => x.id === id); if (!t) return;
        const has = A().tabs.some(x => (x.blocks || []).length > 0);
        if (has && !await ask(`Use the ${t.name} template?`, 'Your tabs, screens and colours are replaced. Your app name, icon and settings stay.', 'Use template')) return;
        const a = A();
        a.colors = { ...t.colors };
        if (t.shop && !a.shop.whatsapp.trim() && !a.shop.email.trim()) a.shop = { ...a.shop, ...t.shop };
        a.tabs = JSON.parse(JSON.stringify(t.tabs));
        if (!a.name.trim()) { a.name = t.sample; if (!current()?.version_code && (!a.package || a.package === D.autoPackage)) a.package = D.autoPackage = suggestPackage(); }
        activeTab = 0; view = 'app'; $$('[data-view]').forEach(x => x.setAttribute('aria-pressed', String(x.dataset.view === 'app')));
        drawAll(); markDirty(true); track('app_builder_template', { template: t.id });
        note(`${t.name} template added — now change the words and add your pictures.`);
        $('.ab-screen')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    /* ------------------------------------------------ start */
    $('[data-add-types]').innerHTML = Object.entries(BLOCKS).map(([k, v]) => `<button type="button" data-add-type="${k}">${svgIcon(v.icon, 'currentColor', 20)}<span>${v.name}</span></button>`).join('');
    drawTemplates();
    $('[data-tpl-wrap]').open = !D.id; // new apps start here; saved apps keep it folded
    (inIdb ? idb('readonly', st => st.get('draft')).then(d => { if (d?.app) { D = d; tidy(); $('[data-tpl-wrap]').open = !D.id; } }).catch(() => {}) : Promise.resolve())
        .then(() => Promise.all([loadLogo(), loadSplashPic()]))
        .then(() => { drawAll(); drawMine(); if (document.fonts?.ready) document.fonts.ready.then(drawPhone); });
    if (token) loadAccount().catch(() => {});
})();
