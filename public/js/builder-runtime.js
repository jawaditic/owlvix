/* Owlvix page builder runtime — the only script allowed on pages made with the builder (published pages load it
   from owlvix.com; their security policy blocks every other script). It brings the interactive widgets to life:
   scroll animations, counters, countdowns, rotating headlines, sliders, before/after images, popups, sticky header
   back-to-top, and for funnels the A/B version choice and a visit count per step. Pages still work without it —
   everything is visible by default. */
(() => {
    const d = document, $$ = (s, el = d) => [...el.querySelectorAll(s)];
    const store = { get: k => { try { return sessionStorage.getItem(k); } catch { return null; } }, set: (k, v) => { try { sessionStorage.setItem(k, v); } catch {} } };
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    /** An Owlvix icon as inline SVG (the same markup as window.OwlIcon, which published pages don't load). */
    const svgIcon = shapes => `<svg class="oi" viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${shapes}</svg>`;
    const TICK = svgIcon('<path d="M5 12.5l4.5 4.5L19 7.5"/>');

    /* Funnels: A/B steps carry both versions — show one at random (no cookies), then report the visit */
    const html = d.documentElement;
    // (kept for the visit, per page: a reload shows the same version and isn't counted under the other one). Published
    // pages are sandboxed, with no sessionStorage: then the tab's window.name keeps it — unless a shop cart lives there.
    const abKey = 'ob-ab:' + location.pathname, NAME = 'ob-ab:';
    const fromName = () => { try { return String(window.name).startsWith(NAME) ? JSON.parse(window.name.slice(NAME.length))[location.pathname] : null; } catch { return null; } };
    const keep = v => {
        store.set(abKey, v);
        if (store.get(abKey) === v || !(window.name === '' || String(window.name).startsWith(NAME))) return; // kept already, or a cart is there
        let seen = {}; try { seen = String(window.name).startsWith(NAME) ? JSON.parse(window.name.slice(NAME.length)) || {} : {}; } catch {}
        seen[location.pathname] = v;
        try { window.name = NAME + JSON.stringify(seen); } catch {}
    };
    const was = store.get(abKey) || fromName();
    const variant = !html.classList.contains('ob-ab') ? 'a' : ['a', 'b'].includes(was) ? was : (Math.random() < 0.5 ? 'a' : 'b');
    if (html.classList.contains('ob-ab')) keep(variant);
    if (variant === 'b') {
        html.classList.add('ob-v-b');
        $$('[data-variant=a] form').forEach(f => f.remove()); // only the visible version's form, so sign-ups count for B
    }
    html.classList.add('ob-ready');
    const fs = html.dataset.fsUrl;
    if (fs && html.dataset.fsSite && navigator.sendBeacon) {
        try { navigator.sendBeacon(fs, JSON.stringify({ s: html.dataset.fsSite, p: html.dataset.fsStep, v: variant })); } catch {}
    }

    /* Scroll animations: elements start hidden only once this script runs, then appear when scrolled into view */
    const anim = $$('[data-anim]');
    if (anim.length && !reduce && 'IntersectionObserver' in window) {
        d.documentElement.classList.add('ob-anim');
        const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('ob-in'); io.unobserve(e.target); } }), { threshold: 0.15 });
        anim.forEach(el => { if (el.dataset.delay) el.style.transitionDelay = el.dataset.delay + 'ms'; io.observe(el); });
    }

    /* Counters count up from 0 when they appear */
    const counters = $$('[data-count]');
    if (counters.length && 'IntersectionObserver' in window) {
        const run = el => {
            const end = parseFloat(el.dataset.count) || 0, dec = (String(el.dataset.count).split('.')[1] || '').length, t0 = performance.now(), dur = 1600;
            const step = now => { const p = Math.min(1, (now - t0) / dur), v = end * (1 - Math.pow(1 - p, 3)); el.textContent = v.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }); if (p < 1) requestAnimationFrame(step); };
            reduce ? (el.textContent = end.toLocaleString('en-US')) : requestAnimationFrame(step);
        };
        const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { run(e.target); io.unobserve(e.target); } }), { threshold: 0.4 });
        counters.forEach(el => io.observe(el));
    }

    /* Countdown timers */
    $$('[data-countdown]').forEach(el => {
        const end = Date.parse(el.dataset.countdown);
        if (!end) return;
        const parts = { d: el.querySelector('[data-u=d]'), h: el.querySelector('[data-u=h]'), m: el.querySelector('[data-u=m]'), s: el.querySelector('[data-u=s]') };
        const tick = () => {
            let left = Math.max(0, Math.floor((end - Date.now()) / 1000));
            const v = { d: Math.floor(left / 86400), h: Math.floor(left / 3600) % 24, m: Math.floor(left / 60) % 60, s: left % 60 };
            Object.entries(parts).forEach(([k, p]) => { if (p) p.textContent = String(v[k]).padStart(2, '0'); });
            if (!left) { el.classList.add('is-done'); clearInterval(timer); }
        };
        const timer = setInterval(tick, 1000); tick();
    });

    /* Rotating headline words */
    $$('[data-rotate]').forEach(el => {
        const words = $$('span', el); let i = 0;
        if (words.length < 2 || reduce) return;
        words.forEach((w, n) => w.classList.toggle('is-on', n === 0));
        setInterval(() => { words[i].classList.remove('is-on'); i = (i + 1) % words.length; words[i].classList.add('is-on'); }, 2400);
    });

    /* Sliders: arrows, dots and optional autoplay on top of CSS scroll-snap */
    $$('[data-carousel]').forEach(c => {
        const track = c.querySelector('.ob-track'); if (!track) return;
        const go = dir => track.scrollBy({ left: dir * track.clientWidth, behavior: 'smooth' });
        c.querySelector('[data-prev]')?.addEventListener('click', () => go(-1));
        c.querySelector('[data-next]')?.addEventListener('click', () => go(1));
        const dots = $$('[data-dot]', c), slides = $$('.car__slide', c);
        dots.forEach((dot, n) => dot.addEventListener('click', e => { e.preventDefault(); track.scrollTo({ left: (slides[n]?.offsetLeft || 0) - track.offsetLeft, behavior: 'smooth' }); }));
        track.addEventListener('scroll', () => {
            let n = 0, best = Infinity; // the slide nearest the start of the track is the current one
            slides.forEach((sl, k) => { const d2 = Math.abs(sl.offsetLeft - track.offsetLeft - track.scrollLeft); if (d2 < best) { best = d2; n = k; } });
            dots.forEach((dot, k) => { dot.classList.toggle('is-on', k === n); dot.setAttribute('aria-current', k === n ? 'true' : 'false'); });
        }, { passive: true });
        if (c.dataset.auto && !reduce) {
            let hover = false;
            ['mouseenter', 'focusin'].forEach(ev => c.addEventListener(ev, () => (hover = true)));
            ['mouseleave', 'focusout'].forEach(ev => c.addEventListener(ev, () => (hover = false)));
            setInterval(() => { if (hover) return; const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4; atEnd ? track.scrollTo({ left: 0, behavior: 'smooth' }) : go(1); }, 4500);
        }
    });

    /* Before / after image slider */
    $$('[data-ba]').forEach(el => {
        const r = el.querySelector('input[type=range]');
        r?.addEventListener('input', () => el.style.setProperty('--pos', r.value + '%'));
    });

    /* Popup: after a delay, on exit intent, or when a link to #popup is clicked */
    const pop = d.querySelector('[data-popup]');
    if (pop) {
        const key = 'ob-popup-' + location.pathname, timers = [];
        let shown = false, lastFocus = null;
        const open = byClick => {
            if (shown && !byClick) return; // automatic triggers fire once per visit (published pages can't store "seen")
            shown = true; timers.forEach(clearTimeout);
            lastFocus = d.activeElement; pop.hidden = false;
            requestAnimationFrame(() => { pop.classList.add('is-open'); pop.querySelector('[data-popup-close]')?.focus(); });
        };
        const close = () => { pop.classList.remove('is-open'); setTimeout(() => (pop.hidden = true), 250); store.set(key, '1'); lastFocus?.focus?.(); };
        $$('[data-popup-close]', pop).forEach(b => b.addEventListener('click', close));
        pop.addEventListener('click', e => { if (e.target === pop) close(); });
        d.addEventListener('keydown', e => { if (e.key === 'Escape' && !pop.hidden) close(); });
        $$('a[href="#popup"]').forEach(a => a.addEventListener('click', e => { e.preventDefault(); open(true); }));
        pop.addEventListener('keydown', e => { // keep keyboard focus inside the popup
            if (e.key !== 'Tab') return;
            const f = $$('a,button,input,textarea,select', pop).filter(el => !el.disabled && el.offsetParent !== null);
            if (!f.length) return;
            if (e.shiftKey && d.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
            else if (!e.shiftKey && d.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
        });
        const t = pop.dataset.popup;
        if (!store.get(key)) {
            if (t === 'delay') timers.push(setTimeout(() => open(), (parseInt(pop.dataset.delay, 10) || 5) * 1000));
            if (t === 'exit') { const onOut = e => { if (e.clientY <= 0) { open(); d.removeEventListener('mouseout', onOut); } }; d.addEventListener('mouseout', onOut); timers.push(setTimeout(() => open(), 45000)); }
            if (t === 'scroll') { const onScroll = () => { if (scrollY > (d.documentElement.scrollHeight - innerHeight) * 0.5) { open(); removeEventListener('scroll', onScroll); } }; addEventListener('scroll', onScroll, { passive: true }); }
        }
    }

    /* Phone boxes with a flag + calling code (contact, opt-in, checkout and form-builder forms): the flag follows the
       <select>, and it starts on the visitor's country — from their IP address (geojs.io, as owlvix.com's own forms do),
       else the browser's language. A picker the visitor already changed is left alone. */
    const dials = $$('[data-dial]');
    const showDial = sel => { const o = sel.selectedOptions[0], f = sel.parentNode.querySelector('[data-dial-face]'); if (o && f) f.textContent = `${o.textContent.split(' ')[0]} ${o.value}`; };
    const setDial = (sel, c) => { const o = sel && !sel.dataset.touched && [...sel.options].find(x => x.dataset.c === c); if (o) { o.selected = true; showDial(sel); } };
    if (dials.length) {
        dials.forEach(sel => sel.addEventListener('change', e => { if (e.isTrusted) sel.dataset.touched = '1'; showDial(sel); }));
        const own = (navigator.languages || [navigator.language]).map(l => (String(l).split('-')[1] || '').toUpperCase()).find(r => /^[A-Z]{2}$/.test(r));
        if (own) dials.forEach(sel => setDial(sel, own));
        if ('fetch' in window) {
            fetch('https://get.geojs.io/v1/ip/country.json', { credentials: 'omit' }).then(r => r.json()).then(j => {
                const c = String(j.country || '').toUpperCase();
                if (/^[A-Z]{2}$/.test(c)) dials.forEach(sel => { if (!sel.closest('[data-dial-row]')?.querySelector('input')?.value.trim()) setDial(sel, c); });
            }).catch(() => {});
        }
    }

    /* Online store: product pages, sizes, live stock, categories and search, the cart and checkout. The cart is kept
       in window.name, which a browser tab keeps from page to page of the shop (published pages are sandboxed, so they
       have no other storage). Prices, delivery, tax and discounts shown here are only for the shopper: owlvix prices
       the order again from the shop's saved products (App\Support\Shop). */
    const shopId = html.dataset.shop, drawer = d.querySelector('[data-cart]');
    if (shopId && drawer) {
        const cur = html.dataset.shopCur || 'usd', api = html.dataset.shopApi || '';
        let rules = { fee: 0, free: 0, zones: [], tax: 0, incl: true, ship: false };
        try { rules = { ...rules, ...JSON.parse(html.dataset.shopRules || '{}') }; } catch {}
        const zero = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'].includes(cur);
        // money is worked out in the currency's smallest unit (cents), exactly like the server
        const M = n => (zero ? Math.round(n) : Math.round(n * 100)), W = m => (zero ? m : m / 100);
        const num = v => (v === undefined || v === null || v === '' || !isFinite(+v) ? null : +v);
        const fmt = n => {
            const whole = zero || Math.round(n * 100) % 100 === 0;
            try { return new Intl.NumberFormat('en', { style: 'currency', currency: cur.toUpperCase(), minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: zero ? 0 : 2 }).format(n); }
            catch { return cur.toUpperCase() + ' ' + (whole ? n : n.toFixed(2)); }
        };
        const KEY = 'owlcart:' + shopId + ':';
        const keyOf = l => (l.vid ? l.id + ':' + l.vid : l.id);
        let cart = [];
        try { if (String(window.name).startsWith(KEY)) cart = JSON.parse(window.name.slice(KEY.length)); } catch {}
        cart = (Array.isArray(cart) ? cart : []).filter(l => l && typeof l.id === 'string' && typeof l.name === 'string' && l.qty > 0).slice(0, 50)
            .map(l => ({ id: l.id.slice(0, 24), vid: String(l.vid || '').slice(0, 24), vid2: String(l.vid2 || '').slice(0, 24), kind: ['digital', 'gift'].includes(l.kind) ? l.kind : 'item',
                name: l.name.slice(0, 200), opt: String(l.opt || '').slice(0, 125), price: +l.price || 0, qty: Math.min(99, Math.floor(l.qty)) }));
        // Back from a finished order (the receipt links to #ordered): start with an empty cart
        if (location.hash === '#ordered') { cart = []; try { history.replaceState(null, '', location.pathname + location.search); } catch {} }
        const cardOf = id => d.querySelector(`.pcard[data-product="${CSS.escape(id)}"]`);
        // Names and prices as this page shows them now (they may have changed since the item went in)
        cart.forEach(l => {
            const card = cardOf(l.id); if (!card) return;
            l.name = card.dataset.name || l.name;
            const chip = l.vid ? card.querySelector(`[data-optv="${CSS.escape(l.vid)}"]`) : null;
            l.price = num(chip?.dataset.price) ?? num(card.dataset.price) ?? l.price;
        });
        const save = () => { try { window.name = cart.length ? KEY + JSON.stringify(cart) : ''; } catch {} };
        save();

        /* live stock from owlvix: {"product" or "product:variant": count} for the products the owner tracks */
        let stock = {};
        let reviews = {};
        // Owlvix icons, inline (this script runs without js/icons.js): filled and empty stars for ratings
        const STAR_D = 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z';
        const star = on => svgIcon(`<path${on ? ' fill="currentColor"' : ''} d="${STAR_D}"/>`);
        /** "4.6" → five stars as HTML (constant markup: only the rounded number decides how many are filled). */
        const stars = avg => { const n = Math.max(0, Math.min(5, Math.round(+avg || 0))); return star(true).repeat(n) + star(false).repeat(5 - n); };
        // a size × colour is counted as "product:size_colour"
        const left = (id, vid, vid2) => { const k = vid ? id + ':' + vid + (vid2 ? '_' + vid2 : '') : id; return Object.prototype.hasOwnProperty.call(stock, k) ? stock[k] : Infinity; };
        const inCart = (id, vid, vid2) => cart.filter(l => l.id === id && l.vid === (vid || '') && l.vid2 === (vid2 || '')).reduce((n, l) => n + l.qty, 0);
        const setOut = (c, out) => { c.classList.toggle('is-out', out); c.setAttribute('aria-disabled', String(out)); if (out && c.getAttribute('aria-pressed') === 'true') c.setAttribute('aria-pressed', 'false'); };
        const markStock = root => $$('[data-product]', root).forEach(card => {
            const id = card.dataset.product, chips = $$('[data-optv]', card), chips2 = $$('[data-optv2]', card), add = card.querySelector('[data-add]'), note = card.querySelector('[data-pstock]');
            const v2s = chips2.length ? chips2.map(c => c.dataset.optv2) : [''];
            const pick1 = chips.find(c => c.getAttribute('aria-pressed') === 'true')?.dataset.optv;
            // a size is out when every colour of it is; a colour is out for the chosen size (or for every size)
            chips.forEach(c => setOut(c, v2s.every(v2 => left(id, c.dataset.optv, v2) <= 0)));
            chips2.forEach(c => setOut(c, (pick1 ? [pick1] : chips.map(x => x.dataset.optv)).every(v1 => left(id, v1, c.dataset.optv2) <= 0)));
            const all = chips.length ? chips.every(c => c.classList.contains('is-out')) : left(id) <= 0;
            if (add && all) { add.disabled = true; add.textContent = 'Sold out'; add.classList.add('is-soldout'); }
            const p1 = chips.find(c => c.getAttribute('aria-pressed') === 'true'), p2 = chips2.find(c => c.getAttribute('aria-pressed') === 'true');
            const n = chips.length ? (p1 && (!chips2.length || p2) ? left(id, p1.dataset.optv, p2?.dataset.optv2) : Infinity) : left(id);
            if (note) { note.hidden = !(n > 0 && n <= 5); note.textContent = n === 1 ? 'Only 1 left' : `Only ${n} left`; }
        });
        if (api && 'fetch' in window) {
            fetch(api + '/stock', { credentials: 'omit' }).then(r => (r.ok ? r.json() : null)).then(j => {
                if (!j || !j.stock) return;
                stock = j.stock; markStock(d);
                // nothing in the cart beyond what is left
                cart.forEach(l => { l.qty = Math.min(l.qty, left(l.id, l.vid, l.vid2)); }); cart = cart.filter(l => l.qty > 0); save(); draw();
            }).catch(() => {});
            // reviews from buyers: stars on the cards, the latest ones on each product's page
            fetch(api + '/reviews', { credentials: 'omit' }).then(r => (r.ok ? r.json() : null)).then(j => {
                reviews = (j && j.reviews) || {};
                $$('[data-product]').forEach(card => {
                    const r = reviews[card.dataset.product], el = card.querySelector('[data-prating]'); if (!r || !el) return;
                    el.innerHTML = stars(r.avg); el.setAttribute('aria-label', `Rated ${r.avg} out of 5 from ${r.count} review${r.count === 1 ? '' : 's'}`);
                    const n = d.createElement('span'); n.textContent = `${r.avg} (${r.count})`; el.append(n); el.hidden = false;
                });
            }).catch(() => {});
        }

        const form = drawer.querySelector('[data-checkout]'), list = drawer.querySelector('[data-cart-lines]'), empty = drawer.querySelector('[data-cart-empty]');
        const place = form?.querySelector('[data-place]');
        const picked = name => form?.querySelector(`[name=${name}]:checked`)?.value || form?.querySelector(`input[type=hidden][name=${name}]`)?.value || '';
        let toastTimer = 0;
        const toast = msg => {
            let t = d.querySelector('.ob-toast');
            if (!t) { t = d.createElement('div'); t.className = 'ob-toast'; t.setAttribute('role', 'status'); d.body.append(t); }
            t.textContent = msg; t.classList.add('is-on'); clearTimeout(toastTimer); toastTimer = setTimeout(() => t.classList.remove('is-on'), 2400);
        };

        /* totals, worked out like the server: discount, delivery by country, tax */
        let code = null; // {code, kind, value, label} once the shopper applies one
        // all in the smallest unit (App\Support\Shop::totals does the same sums), shown with W()
        const ships = () => cart.some(l => l.kind === 'item'); // downloads and gift cards aren't posted
        const totals = () => {
            const sub = cart.reduce((n, l) => n + M(l.price) * l.qty, 0), pickup = picked('_delivery') === 'pickup' || !ships();
            // gift cards are sold at face value: codes, free delivery and tax are for goods only
            const cards = cart.reduce((n, l) => n + (l.kind === 'gift' ? M(l.price) * l.qty : 0), 0), goods = sub - cards;
            const off = !code ? 0 : code.kind === 'percent' ? Math.floor(goods * Math.min(100, code.value) / 100) : code.kind === 'amount' ? Math.min(M(code.value), goods) : 0;
            const after = goods - off, country = form?.querySelector('[name=country]')?.value || '';
            let zone = { fee: rules.fee, free: rules.free }, unknown = false;
            if (rules.zones.length) { zone = rules.zones.find(z => z.c.includes(country)) || (country ? rules.zones.find(z => !z.c.length) : null); unknown = !zone; }
            const fee = pickup || !zone || code?.kind === 'shipping' || (zone.free && after >= M(zone.free)) ? 0 : M(zone.fee);
            const base = after + (rules.ship ? fee : 0);
            const tax = rules.tax > 0 ? (rules.incl ? base - Math.round(base / (1 + rules.tax / 100)) : Math.round(base * rules.tax / 100)) : 0;
            const due = after + cards + fee + (rules.incl ? 0 : tax), gift = code?.kind === 'gift' ? Math.min(M(code.value), due) : 0; // a gift card pays what it can
            return { sub, off, fee, tax, gift, pickup, unknown: unknown && ships(), country, total: due - gift };
        };
        const draw = () => {
            const count = cart.reduce((n, l) => n + l.qty, 0);
            $$('[data-cart-count]').forEach(el => { el.textContent = count > 99 ? '99+' : count; el.hidden = !count; });
            $$('[data-cart-open]').forEach(el => el.setAttribute('aria-label', count ? `Your cart, ${count} item${count === 1 ? '' : 's'}` : 'Your cart'));
            empty.hidden = !!cart.length;
            if (form) form.hidden = !cart.length;
            list.textContent = '';
            cart.forEach((l, i) => {
                const li = d.createElement('li');
                li.innerHTML = '<div><b></b><small></small></div><b class="ob-lt"></b><span class="ob-qty"><button type="button" data-q="-1" aria-label="One less">−</button><span></span><button type="button" data-q="1" aria-label="One more">+</button></span><button type="button" class="ob-rm" data-rm>Remove</button>';
                li.dataset.i = i;
                li.querySelector('div b').textContent = l.name;
                li.querySelector('small').textContent = [l.opt, fmt(l.price)].filter(Boolean).join(' · ');
                li.querySelector('.ob-lt').textContent = fmt(W(M(l.price) * l.qty));
                li.querySelector('.ob-qty span').textContent = l.qty;
                if (l.qty >= Math.min(99, left(l.id, l.vid, l.vid2))) li.querySelector('[data-q="1"]').disabled = true;
                list.append(li);
            });
            if (!form) return;
            let t = totals();
            if (code && code.min && t.sub < M(code.min)) { // the cart dropped below the code's minimum order
                say(`${code.code} needs an order of at least ${fmt(code.min)}.`); code = null; t = totals();
            }
            const set = (k, v) => form.querySelectorAll(`[data-sum=${k}]`).forEach(el => (el.textContent = v));
            set('sub', fmt(W(t.sub))); set('off', '−' + fmt(W(t.off))); set('tax', fmt(W(t.tax))); set('total', fmt(W(t.total))); set('gift', '−' + fmt(W(t.gift)));
            const giftRow = form.querySelector('[data-sum-row=gift]'); if (giftRow) giftRow.hidden = !t.gift;
            // only downloads and gift cards: no delivery, address or country
            const post = ships();
            $$('[data-ship]', form).forEach(el => { el.hidden = !post; $$('input,select,textarea', el).forEach(f => (f.disabled = !post)); });
            const feeRow = form.querySelector('[data-sum-row=fee]'); if (feeRow) feeRow.hidden = !post;
            set('fee', t.pickup ? 'Free' : t.unknown ? (t.country ? 'Not available' : 'Choose country') : t.fee ? fmt(W(t.fee)) : 'Free');
            const offRow = form.querySelector('[data-sum-row=off]'); if (offRow) offRow.hidden = !t.off;
            const codeTag = form.querySelector('[data-sum-code]'); if (codeTag) codeTag.textContent = code ? `(${code.code})` : '';
            const label = form.querySelector('[data-sum-label]'); if (label) label.textContent = t.pickup ? 'Pick-up' : 'Delivery';
            const addr = form.querySelector('[data-address]'), area = addr?.querySelector('textarea');
            if (addr) { addr.hidden = t.pickup; area.required = !t.pickup; area.disabled = !post; }
            const ctry = form.querySelector('[data-country]'); if (ctry) { ctry.hidden = t.pickup; ctry.querySelector('select').required = !t.pickup && post; }
            if (place) place.textContent = picked('_pay') === 'card' ? `Continue to payment · ${fmt(W(t.total))}` : `Place order · ${fmt(W(t.total))}`;
        };

        let lastFocus = null;
        const openLayer = (el, focus) => {
            clearTimeout(el._hide); lastFocus = d.activeElement; el.hidden = false;
            requestAnimationFrame(() => { el.classList.add('is-open'); el.querySelector(focus)?.focus(); });
        };
        const closeLayer = el => { el.classList.remove('is-open'); clearTimeout(el._hide); el._hide = setTimeout(() => (el.hidden = true), 250); lastFocus?.focus?.(); };
        const open = () => { draw(); if (quick && !quick.hidden) closeProduct(); openLayer(drawer, '[data-cart-close]'); };
        const close = () => closeLayer(drawer);
        const bump = () => $$('[data-cart-open]').forEach(el => { el.classList.remove('is-bump'); void el.offsetWidth; el.classList.add('is-bump'); });

        /* a product's own page: bigger photos, the longer description, sizes and the button, in a window over the shop */
        const quick = d.querySelector('[data-quickview]'), quickBody = quick?.querySelector('[data-quick-body]');
        const openProduct = id => {
            const card = cardOf(id); if (!card || !quick) return;
            const pics = [...$$('.pcard__pic img', card), ...$$('.pcard__more img', card)];
            const body = card.querySelector('.pcard__b').cloneNode(true), long = card.querySelector('.pcard__long');
            quickBody.textContent = '';
            const wrap = d.createElement('div'); wrap.className = 'ob-quick__in';
            ['product', 'name', 'price', 'cat', 'kind'].forEach(k => { if (card.dataset[k] !== undefined) wrap.dataset[k] = card.dataset[k]; });
            const gal = d.createElement('div'); gal.className = 'ob-gal';
            if (pics.length) {
                const big = d.createElement('img'); big.className = 'ob-gal__big'; big.src = pics[0].src; big.alt = pics[0].alt; gal.append(big);
                if (pics.length > 1) {
                    const thumbs = d.createElement('div'); thumbs.className = 'ob-gal__thumbs';
                    pics.forEach((p, n) => { const b = d.createElement('button'); b.type = 'button'; b.setAttribute('aria-label', `Photo ${n + 1}`); b.setAttribute('aria-pressed', String(!n)); const im = d.createElement('img'); im.src = p.src; im.alt = ''; b.append(im); b.dataset.galSrc = p.src; thumbs.append(b); });
                    gal.append(thumbs);
                }
            } else gal.append(card.querySelector('.pcard__pic .pic')?.cloneNode(true) || d.createElement('div'));
            if (long) body.querySelector('.pprice')?.after(Object.assign(long.cloneNode(true), { hidden: false }));
            const rv = reviews[id];
            if (rv && rv.items.length) { // what buyers said
                const box = d.createElement('div'); box.className = 'ob-reviews';
                const h = d.createElement('h4'); h.innerHTML = stars(rv.avg); h.append(` ${rv.avg} · ${rv.count} review${rv.count === 1 ? '' : 's'}`); box.append(h);
                rv.items.forEach(it => {
                    const r = d.createElement('div'); r.className = 'ob-review';
                    r.innerHTML = '<b></b><p></p><small></small>';
                    r.querySelector('b').innerHTML = stars(it.rating); r.querySelector('p').textContent = it.text; r.querySelector('small').textContent = it.name;
                    if (!it.text) r.querySelector('p').remove();
                    box.append(r);
                });
                body.append(box);
            }
            wrap.append(gal, body); quickBody.append(wrap);
            markStock(quickBody);
            if (!drawer.hidden) closeLayer(drawer);
            openLayer(quick, '[data-quick-close]');
            try { history.replaceState(null, '', '#p-' + id); } catch {}
        };
        const closeProduct = () => { closeLayer(quick); try { history.replaceState(null, '', location.pathname + location.search); } catch {} };

        /* categories and search, per products section */
        const filter = wrap => {
            const cat = wrap.querySelector('[data-pcat][aria-pressed="true"]')?.dataset.pcat || '', q = (wrap.querySelector('[data-psearch]')?.value || '').trim().toLowerCase();
            let shown = 0;
            $$('.pcard', wrap).forEach(c => { const ok = (!cat || c.dataset.cat === cat) && (!q || c.textContent.toLowerCase().includes(q)); c.hidden = !ok; shown += ok; });
            const none = wrap.querySelector('[data-pnone]'); if (none) none.hidden = !!shown;
        };
        $$('[data-psearch]').forEach(inp => inp.addEventListener('input', () => filter(inp.closest('.wrap'))));

        d.addEventListener('click', e => {
            const ov = e.target.closest('[data-optv], [data-optv2]');
            if (ov) {
                if (ov.getAttribute('aria-disabled') === 'true') return toast(`${ov.dataset.v} is sold out`);
                ov.parentNode.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', String(b === ov))); ov.parentNode.classList.remove('is-need');
                const box = ov.closest('[data-product]'), pp = box?.querySelector('[data-pprice]');
                if (pp && ov.dataset.optv) pp.textContent = fmt(num(ov.dataset.price) ?? num(box.dataset.price) ?? 0);
                markStock(box.parentNode); return;
            }
            const cat = e.target.closest('[data-pcat]');
            if (cat) { cat.parentNode.querySelectorAll('[data-pcat]').forEach(b => b.setAttribute('aria-pressed', String(b === cat))); return filter(cat.closest('.wrap')); }
            const gal = e.target.closest('[data-gal-src]');
            if (gal) { quick.querySelector('.ob-gal__big').src = gal.dataset.galSrc; gal.parentNode.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', String(b === gal))); return; }
            const qv = e.target.closest('[data-quick]');
            if (qv) { e.preventDefault(); return openProduct(qv.closest('[data-product]').dataset.product); }
            if (e.target.closest('[data-quick-close]') || e.target === quick) return closeProduct();
            const add = e.target.closest('[data-add]');
            if (add) {
                const box = add.closest('[data-product]'); if (!box || !box.dataset.product) return;
                const groups = $$('.popt__v', box), id = box.dataset.product;
                const need = groups.find(g => !g.querySelector('[aria-pressed="true"]'));
                if (need) { // sizes, colours…: the shopper picks one of each first
                    toast(`Please choose your ${(need.getAttribute('aria-label') || 'option').toLowerCase()} first`);
                    need.classList.remove('is-need'); void need.offsetWidth; need.classList.add('is-need');
                    need.querySelector('button:not([aria-disabled="true"])')?.focus(); return;
                }
                const chip = box.querySelector('[data-optv][aria-pressed="true"]'), chip2 = box.querySelector('[data-optv2][aria-pressed="true"]');
                const vid = chip?.dataset.optv || '', vid2 = chip2?.dataset.optv2 || '';
                const max = Math.min(99, left(id, vid, vid2));
                if (inCart(id, vid, vid2) >= max) return toast(max <= 0 ? 'Sold out' : `Only ${max} available`);
                const line = cart.find(l => l.id === id && l.vid === vid && l.vid2 === vid2);
                if (line) line.qty += 1;
                else if (cart.length < 50) cart.push({ id, vid, vid2, kind: box.dataset.kind || 'item', name: box.dataset.name || 'Item', opt: [chip?.dataset.v, chip2?.dataset.v].filter(Boolean).join(' / '),
                    price: num(chip?.dataset.price) ?? num(box.dataset.price) ?? 0, qty: 1 });
                else return toast('Your cart is full');
                const first = cart.reduce((n, l) => n + l.qty, 0) === 1;
                save(); draw(); bump();
                add.dataset.label ||= add.textContent; add.classList.add('is-added'); add.innerHTML = `${TICK} Added`;
                clearTimeout(add._t); add._t = setTimeout(() => { add.classList.remove('is-added'); add.textContent = add.dataset.label; }, 1400);
                if (first) open(); else toast('Added to your cart');
                return;
            }
            if (e.target.closest('[data-cart-open]') || e.target.closest('a[href="#cart"]')) { e.preventDefault(); return open(); }
            if (e.target.closest('[data-cart-close]') || e.target === drawer) return close();
            if (e.target.closest('[data-code-apply]')) return applyCode();
            const li = e.target.closest('[data-cart-lines] li');
            if (li) {
                const l = cart[+li.dataset.i]; if (!l) return;
                const q = e.target.closest('[data-q]');
                if (q) l.qty = Math.max(0, Math.min(99, left(l.id, l.vid, l.vid2), l.qty + +q.dataset.q));
                if (e.target.closest('[data-rm]')) l.qty = 0;
                cart = cart.filter(x => x.qty > 0); save(); draw();
                if (!cart.length) drawer.querySelector('[data-cart-close]')?.focus();
            }
        });

        /* discount codes: checked with owlvix so the shopper sees the price (the order is checked again) */
        const codeInput = form?.querySelector('[data-code]'), codeMsg = form?.querySelector('[data-code-msg]');
        const say = (msg, ok) => { if (!codeMsg) return; codeMsg.textContent = msg; if (ok && msg) codeMsg.insertAdjacentHTML('afterbegin', `${TICK} `); codeMsg.hidden = !msg; codeMsg.classList.toggle('is-ok', !!ok); };
        const applyCode = async () => {
            const c = (codeInput?.value || '').trim().toUpperCase();
            if (!c) { code = null; say(''); return draw(); }
            say('Checking…');
            try {
                const r = await (await fetch(`${api}/code?c=${encodeURIComponent(c)}&sub=${W(totals().sub)}`, { credentials: 'omit' })).json();
                if (r.ok) { code = { code: r.code, kind: r.kind, value: +r.value || 0, min: +r.min || 0, label: r.label }; codeInput.value = r.code; say(String(r.label || ''), true); }
                else { code = null; say(r.error || 'That code isn’t valid.'); }
            } catch { code = null; say('The code couldn’t be checked — it will be checked when you order.'); }
            draw();
        };
        codeInput?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); applyCode(); } });
        codeInput?.addEventListener('input', () => { if (code) { code = null; say(''); draw(); } });

        d.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            if (quick && !quick.hidden) closeProduct(); else if (!drawer.hidden) close();
        });
        [drawer, quick].filter(Boolean).forEach(layer => layer.addEventListener('keydown', e => { // keep keyboard focus inside the open window
            if (e.key !== 'Tab') return;
            const f = $$('a,button,input,textarea,select', layer).filter(el => !el.disabled && el.offsetParent !== null);
            if (!f.length) return;
            if (e.shiftKey && d.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
            else if (!e.shiftKey && d.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
        }));
        /* phone: the calling code follows the delivery country they pick (until they choose one themselves) */
        const dial = form?.querySelector('[data-dial]'), phoneBox = form?.querySelector('[name=phone]');
        form?.addEventListener('change', e => {
            if (e.target.name === 'country' && e.target.value && phoneBox && !phoneBox.value.trim()) setDial(dial, e.target.value);
            if (['_delivery', '_pay', 'country'].includes(e.target.name)) draw();
        });
        form?.addEventListener('submit', e => {
            if (!cart.length) { e.preventDefault(); return; }
            const t = totals();
            if (!t.pickup && t.unknown && t.country) { e.preventDefault(); toast('Sorry — the shop doesn’t deliver to that country'); return; }
            form.querySelector('[data-cart-field]').value = JSON.stringify(cart.map(l => ({ id: l.id, vid: l.vid, vid2: l.vid2, opt: l.opt, qty: l.qty })));
            if (codeInput && !code) codeInput.value = codeInput.value.trim(); // an unchecked code is still sent and checked
            if (place) { place.disabled = true; place.textContent = picked('_pay') === 'card' ? 'Opening secure payment…' : 'Placing your order…'; }
        });
        // Back to the page (browser back button): the button works again
        addEventListener('pageshow', () => { if (place) place.disabled = false; draw(); });
        draw();
        if (location.hash === '#cart') open();
        else if (/^#p-[a-z0-9]{1,24}$/i.test(location.hash)) openProduct(location.hash.slice(3));
    }

    /* Sticky header shadow and back-to-top button */
    const nav = d.querySelector('.nav.is-sticky'), top = d.querySelector('.ob-top');
    const onScroll = () => { nav?.classList.toggle('is-scrolled', scrollY > 10); top?.classList.toggle('is-on', scrollY > 600); };
    if (nav || top) { addEventListener('scroll', onScroll, { passive: true }); onScroll(); }
})();
