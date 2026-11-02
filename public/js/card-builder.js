/* Owlvix free business card maker (/business-card-maker). Everything stays in the browser (localStorage 'owlvix-card'):
   nothing is uploaded. Both sides are drawn as SVG at real size (1 unit = 0.1 mm), so the screen, the PNG and the
   printed PDF are the same picture. Print PDFs come from the browser's own "Save as PDF" in a clean window:
   one card with 3 mm bleed for print shops, or 10 cards on A4 / Letter with cut marks for printing at home.
   Icons are Owlvix's own (window.OwlIcon); the QR code is drawn by public/js/qr.js. */
(() => {
    const root = document.querySelector('[data-cb]');
    if (!root) return;
    const $ = (s, el = root) => el.querySelector(s), $$ = (s, el = root) => [...el.querySelectorAll(s)];
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const hex = (c, d) => (/^#[0-9a-f]{6}$/i.test(c || '') ? c.toLowerCase() : d);
    const ic = (v, o) => (window.OwlIcon ? window.OwlIcon(v, o) : '');
    const track = (e, d = {}) => window.track?.(e, d);
    const ask = async o => {
        for (let i = 0; i < 20 && !window.sweet; i++) await new Promise(r => setTimeout(r, 100));
        if (window.sweet) return window.sweet(o);
        status(o.title);
        return o.cancel === false;
    };

    const STORE = 'owlvix-card';
    const DESIGNS = { classic: 'Classic', band: 'Colour band', bold: 'Bold', minimal: 'Minimal', split: 'Split', dark: 'Dark' };
    const FONTS = { 'Helvetica, Arial, sans-serif': 'Modern', "Georgia, 'Times New Roman', serif": 'Classic serif', "'Trebuchet MS', Verdana, sans-serif": 'Friendly', "'Courier New', Courier, monospace": 'Typewriter' };
    // size => [name, width, height] in 0.1 mm
    const SIZES = { us: ['US — 3.5 × 2 in', 889, 508], eu: ['UK / Europe — 85 × 55 mm', 850, 550] };
    const QRS = { none: 'No QR code', web: 'QR code to my website', vcard: 'QR code that saves my contact' };
    const BLEED = 30, SAFE = 45;

    /* ------------------------------------------------ data */
    const str = (v, max = 120) => String(v ?? '').slice(0, max);
    const LOCALE_US = /-(US|CA|MX|PH)$/i.test(navigator.language || '');
    const sample = () => ({ design: 'classic', color: '#1e3a8a', font: 'Helvetica, Arial, sans-serif', size: LOCALE_US ? 'us' : 'eu', logo: '',
        name: 'Sara Khan', title: 'Interior Designer', company: 'Khan Studio', tagline: 'Rooms that feel like you', phone: '+44 7700 900123', email: 'sara@khanstudio.com', web: 'khanstudio.com', address: '12 Market Street, Leeds', qr: 'web' });
    const SAMPLE = sample();
    const FIELDS = ['name', 'title', 'company', 'tagline', 'phone', 'email', 'web', 'address'];
    const clean = d => {
        if (!d || typeof d !== 'object') return sample();
        const o = { design: Object.hasOwn(DESIGNS, d.design) ? d.design : 'classic', color: hex(d.color, '#1e3a8a'), font: Object.hasOwn(FONTS, d.font) ? d.font : 'Helvetica, Arial, sans-serif',
            size: Object.hasOwn(SIZES, d.size) ? d.size : SAMPLE.size, qr: Object.hasOwn(QRS, d.qr) ? d.qr : 'web',
            logo: /^data:image\/(jpeg|png|webp);base64,[a-z0-9+/=]+$/i.test(d.logo || '') && d.logo.length < 400000 ? d.logo : '' };
        FIELDS.forEach(k => (o[k] = str(d[k], k === 'address' ? 160 : 80)));
        return o;
    };
    const read = () => { try { return JSON.parse(localStorage.getItem(STORE) || 'null'); } catch { return null; } };
    let C = clean(read());
    const isSample = () => FIELDS.every(k => C[k] === SAMPLE[k]);
    const status = t => { const s = $('[data-saved]'); if (s) s.textContent = t; };
    const save = () => { try { localStorage.setItem(STORE, JSON.stringify(C)); status('Saved on this device'); } catch { status('Too big to save here — try a smaller logo'); } };

    /* ------------------------------------------------ drawing helpers */
    const lum = c => { const v = [1, 3, 5].map(i => parseInt(c.slice(i, i + 2), 16) / 255).map(x => (x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4)); return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2]; };
    const on = bg => (lum(bg) > 0.45 ? '#111827' : '#ffffff'); // readable text on a background
    const ctx = document.createElement('canvas').getContext('2d');
    /** Font size that fits `text` in `max` width (never above `size`, never below 55% of it). */
    const fit = (text, size, max, weight = 400) => { ctx.font = `${weight} ${size}px ${C.font}`; const w = ctx.measureText(text).width; return w > max ? Math.max(size * 0.55, Math.floor(size * max / w)) : size; };
    const T = (text, x, y, size, o = {}) => {
        if (!text) return '';
        const s = o.max ? fit(text, size, o.max, o.weight || 400) : size;
        return `<text x="${x}" y="${y}" font-size="${s}" font-family="${esc(C.font)}" font-weight="${o.weight || 400}" fill="${o.fill || '#111827'}"${o.anchor ? ` text-anchor="${o.anchor}"` : ''}${o.ls ? ` letter-spacing="${o.ls}"` : ''}${o.op ? ` fill-opacity="${o.op}"` : ''}>${esc(text)}</text>`;
    };
    /** One of our icons at (x, y), `s` units tall, in `color`. */
    const icon = (name, x, y, s, color) => {
        const p = window.OwlIcon?.paths(name) || '';
        return p ? `<g transform="translate(${x},${y}) scale(${s / 24})" fill="none" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" color="${color}">${p}</g>` : '';
    };
    const logo = (x, y, w, h, align = 'xMinYMid') => (C.logo ? `<image href="${C.logo}" x="${x}" y="${y}" width="${w}" height="${h}" preserveAspectRatio="${align} meet"/>` : '');
    const site = () => C.web.trim().replace(/^https?:\/\//i, '').replace(/\/$/, '');
    const contacts = () => [['call', C.phone], ['mail', C.email], ['globe', site()], ['pin', C.address]].filter(([, v]) => v && v.trim());
    /** Contact lines with icons, from (x, y) down, `gap` apart. */
    const lines = (x, y, gap, size, color, iconColor, max, anchor) => contacts().map(([n, v], i) => {
        const yy = y + i * gap;
        if (anchor === 'middle') return T(v.trim(), x, yy, size, { fill: color, anchor, max });
        return icon(n, x, yy - size * 0.95, size * 1.15, iconColor) + T(v.trim(), x + size * 1.7, yy, size, { fill: color, max: max - size * 1.7 });
    }).join('');
    const vcard = () => {
        const e = s => s.replace(/\\/g, '\\\\').replace(/([,;])/g, '\\$1');
        const [first, ...rest] = C.name.trim().split(/\s+/);
        return ['BEGIN:VCARD', 'VERSION:3.0', `N:${e(rest.join(' '))};${e(first || '')};;;`, `FN:${e(C.name.trim() || C.company.trim())}`,
            C.company.trim() && 'ORG:' + e(C.company.trim()), C.title.trim() && 'TITLE:' + e(C.title.trim()), C.phone.trim() && 'TEL;TYPE=CELL:' + C.phone.replace(/[^\d+]/g, ''),
            C.email.trim() && 'EMAIL:' + C.email.trim(), site() && 'URL:https://' + site(), 'END:VCARD'].filter(Boolean).join('\r\n');
    };
    const qrText = () => (C.qr === 'web' ? (site() ? 'https://' + site() : '') : C.qr === 'vcard' ? (C.name.trim() || C.company.trim() ? vcard() : '') : '');
    /** The QR code as one path in a white rounded box, `s` units wide. */
    const qr = (x, y, s) => {
        const text = qrText(); if (!text || !window.OwlQR) return '';
        let q; try { q = window.OwlQR.make(text, { ec: 'M' }); } catch { return ''; }
        const pad = s * 0.08, u = (s - 2 * pad) / q.size; let d = '';
        for (let r = 0; r < q.size; r++) for (let c = 0; c < q.size; c++) if (q.dark(c, r)) d += `M${(x + pad + c * u).toFixed(2)},${(y + pad + r * u).toFixed(2)}h${u.toFixed(2)}v${u.toFixed(2)}h-${u.toFixed(2)}z`;
        return `<rect x="${x}" y="${y}" width="${s}" height="${s}" rx="${s * 0.06}" fill="#fff"/><path d="${d}" fill="#111827" shape-rendering="crispEdges"/>`;
    };
    const initials = () => (C.company.trim() || C.name.trim()).split(/\s+/).slice(0, 2).map(w => w[0] || '').join('').toUpperCase();
    /** Logo, or the initials in a circle when there's no logo. */
    const mark = (cx, cy, r, bg, fg) => (C.logo ? logo(cx - r * 1.6, cy - r, r * 3.2, r * 2, 'xMidYMid') : initials() ? `<circle cx="${cx}" cy="${cy}" r="${r}" fill="${bg}"/>${T(initials(), cx, cy + r * 0.36, r, { fill: fg, anchor: 'middle', weight: 700 })}` : '');

    /* ------------------------------------------------ the six designs: front and back, inside W × H (bleed drawn around) */
    const B = BLEED;
    const bg = (W, H, fill) => `<rect x="${-B}" y="${-B}" width="${W + 2 * B}" height="${H + 2 * B}" fill="${fill}"/>`;
    const DRAW = {
        classic: (W, H, k) => {
            const c = C.color, ink = '#111827', soft = '#475569', m = SAFE;
            const front = bg(W, H, '#fff') + (C.logo ? logo(m, m - 5, 220, 80) : T(C.company, m, m + 40, 34, { fill: c, weight: 700, max: W / 2 }))
                + T(C.name, m, H * 0.47, 58, { weight: 700, fill: ink, max: W - 2 * m }) + T(C.title, m, H * 0.47 + 46, 30, { fill: c, weight: 600, max: W - 2 * m })
                + `<rect x="${m}" y="${H * 0.47 + 70}" width="70" height="5" rx="2.5" fill="${c}"/>` + lines(m, H - m - (contacts().length - 1) * 38, 38, 25, soft, c, W - 2 * m);
            const t = on(c), hasQr = !!qrText(), qs = H * 0.42;
            const back = bg(W, H, c) + mark(hasQr ? W * 0.36 : W / 2, H * 0.4, 70, t, c) + T(C.company, hasQr ? W * 0.36 : W / 2, H * 0.4 + 130, 38, { fill: t, anchor: 'middle', weight: 700, max: hasQr ? W * 0.6 : W - 2 * m })
                + T(C.tagline, hasQr ? W * 0.36 : W / 2, H * 0.4 + 175, 25, { fill: t, anchor: 'middle', op: 0.85, max: hasQr ? W * 0.6 : W - 2 * m }) + (hasQr ? qr(W - m - qs, (H - qs) / 2, qs) : '');
            return k === 'front' ? front : back;
        },
        band: (W, H, k) => {
            const c = C.color, t = on(c), bw = W * 0.34, m = SAFE, x = bw + 50;
            const front = bg(W, H, '#fff') + `<rect x="${-B}" y="${-B}" width="${bw + B}" height="${H + 2 * B}" fill="${c}"/>` + mark(bw / 2, H / 2 - 20, 62, t, c)
                + T(C.company, bw / 2, H / 2 + 95, 26, { fill: t, anchor: 'middle', weight: 700, max: bw - 2 * m })
                + T(C.name, x, m + 70, 52, { weight: 700, max: W - x - m }) + T(C.title, x, m + 112, 27, { fill: c, weight: 600, max: W - x - m })
                + lines(x, H - m - (contacts().length - 1) * 36, 36, 23, '#475569', c, W - x - m);
            const hasQr = !!qrText(), qs = H * 0.44;
            const back = bg(W, H, c) + (hasQr ? qr((W - qs) / 2, H * 0.14, qs) + T(C.tagline || C.company, W / 2, H * 0.14 + qs + 70, 28, { fill: t, anchor: 'middle', weight: 600, max: W - 2 * m })
                : mark(W / 2, H * 0.42, 80, t, c) + T(C.tagline || C.company, W / 2, H * 0.42 + 150, 30, { fill: t, anchor: 'middle', weight: 600, max: W - 2 * m }));
            return k === 'front' ? front : back;
        },
        bold: (W, H, k) => {
            const c = C.color, t = on(c), m = SAFE;
            const front = bg(W, H, c) + T(C.name, m, H * 0.36, 66, { fill: t, weight: 800, max: W - 2 * m }) + T(C.title, m, H * 0.36 + 48, 30, { fill: t, op: 0.85, max: W - 2 * m })
                + lines(m, H - m - (contacts().length - 1) * 38, 38, 25, t, t, W - 2 * m);
            const hasQr = !!qrText(), qs = H * 0.4;
            const back = bg(W, H, '#fff') + `<rect x="${-B}" y="${H - 40}" width="${W + 2 * B}" height="${40 + B}" fill="${c}"/>` + mark(m + 60, H * 0.42, 60, c, on(c))
                + T(C.company, m + 150, H * 0.42 - 5, 40, { fill: '#111827', weight: 800, max: (hasQr ? W - qs - 2 * m : W - m) - m - 170 }) + T(C.tagline, m + 150, H * 0.42 + 38, 24, { fill: '#475569', max: (hasQr ? W - qs - 2 * m : W - m) - m - 170 })
                + (hasQr ? qr(W - m - qs, H * 0.42 - qs / 2, qs) : '');
            return k === 'front' ? front : back;
        },
        minimal: (W, H, k) => {
            const c = C.color, m = SAFE, cs = contacts().map(([, v]) => v.trim());
            const front = bg(W, H, '#fff') + T(C.name, W / 2, H * 0.42, 54, { weight: 600, anchor: 'middle', ls: 1, max: W - 2 * m }) + T(C.title.toUpperCase(), W / 2, H * 0.42 + 44, 21, { fill: c, anchor: 'middle', ls: 3, weight: 600, max: W - 2 * m })
                + T(cs.slice(0, 2).join('   ·   '), W / 2, H - m - 40, 22, { fill: '#475569', anchor: 'middle', max: W - 2 * m }) + T(cs.slice(2).join('   ·   '), W / 2, H - m, 22, { fill: '#475569', anchor: 'middle', max: W - 2 * m });
            const hasQr = !!qrText(), qs = H * 0.36;
            const back = bg(W, H, '#fff') + (hasQr ? mark(W / 2, H * 0.3, 52, c, on(c)) + qr((W - qs) / 2, H * 0.5, qs)
                : mark(W / 2, H * 0.42, 74, c, on(c)) + T(C.company, W / 2, H * 0.42 + 140, 30, { anchor: 'middle', weight: 600, ls: 2, max: W - 2 * m }));
            return k === 'front' ? front : back;
        },
        split: (W, H, k) => {
            const c = C.color, t = on(c), m = SAFE, top = H * 0.48;
            const front = bg(W, H, '#fff') + `<rect x="${-B}" y="${-B}" width="${W + 2 * B}" height="${top + B}" fill="${c}"/>`
                + T(C.name, m, top - 70, 54, { fill: t, weight: 700, max: W - 2 * m - 130 }) + T(C.title, m, top - 28, 27, { fill: t, op: 0.88, max: W - 2 * m - 130 })
                + (C.logo ? `<rect x="${W - m - 120}" y="${top - 145}" width="120" height="120" rx="16" fill="#fff"/>` + logo(W - m - 110, top - 135, 100, 100, 'xMidYMid') : '')
                + (() => { const cs = contacts(), half = Math.ceil(cs.length / 2), y0 = top + (H - top) / 2 - (half - 1) * 22 + 8, col = (list, x) => list.map(([n, v], i) => icon(n, x, y0 + i * 44 - 24, 27, c) + T(v.trim(), x + 40, y0 + i * 44, 23, { fill: '#334155', max: W / 2 - m - 50 })).join(''); return col(cs.slice(0, half), m) + col(cs.slice(half), W / 2 + 10); })();
            const hasQr = !!qrText(), qs = H * 0.42;
            const back = bg(W, H, c) + mark(hasQr ? W * 0.33 : W / 2, H * 0.42, 66, t, c) + T(C.company, hasQr ? W * 0.33 : W / 2, H * 0.42 + 125, 34, { fill: t, anchor: 'middle', weight: 700, max: hasQr ? W * 0.55 : W - 2 * m })
                + (hasQr ? qr(W - m - qs - 20, (H - qs) / 2, qs) : '');
            return k === 'front' ? front : back;
        },
        dark: (W, H, k) => {
            const c = lum(C.color) < 0.12 ? '#38bdf8' : C.color, d = '#0f172a', m = SAFE;
            const front = bg(W, H, d) + `<rect x="${m}" y="${m}" width="6" height="${H - 2 * m}" rx="3" fill="${c}"/>`
                + T(C.name, m + 40, m + 75, 54, { fill: '#fff', weight: 700, max: W - 2 * m - 40 }) + T(C.title, m + 40, m + 117, 27, { fill: c, weight: 600, max: W - 2 * m - 40 })
                + lines(m + 40, H - m - (contacts().length - 1) * 37, 37, 23, '#cbd5e1', c, W - 2 * m - 40);
            const hasQr = !!qrText(), qs = H * 0.42;
            const back = bg(W, H, d) + mark(hasQr ? W * 0.34 : W / 2, H * 0.42, 66, c, on(c)) + T(C.company, hasQr ? W * 0.34 : W / 2, H * 0.42 + 125, 34, { fill: '#fff', anchor: 'middle', weight: 700, max: hasQr ? W * 0.58 : W - 2 * m })
                + T(C.tagline, hasQr ? W * 0.34 : W / 2, H * 0.42 + 165, 22, { fill: c, anchor: 'middle', max: hasQr ? W * 0.58 : W - 2 * m }) + (hasQr ? qr(W - m - qs, (H - qs) / 2, qs) : '');
            return k === 'front' ? front : back;
        },
    };
    /** One side as SVG. bleed: include the 3 mm around it (print shops); px: pixel size for PNGs. */
    const side = (k, o = {}) => {
        const [, W, H] = SIZES[C.size], b = o.bleed ? B : 0;
        const size = o.mm ? ` width="${(W + 2 * b) / 10}mm" height="${(H + 2 * b) / 10}mm"` : o.px ? ` width="${o.px}" height="${Math.round(o.px * (H + 2 * b) / (W + 2 * b))}"` : '';
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${-b} ${-b} ${W + 2 * b} ${H + 2 * b}"${size}${o.label ? ` role="img" aria-label="${esc(o.label)}"` : ''}><defs><clipPath id="cb-clip-${k}"><rect x="${-b}" y="${-b}" width="${W + 2 * b}" height="${H + 2 * b}"/></clipPath></defs><g clip-path="url(#cb-clip-${k})">${DRAW[C.design](W, H, k)}</g></svg>`;
    };

    /* ------------------------------------------------ tips */
    const tips = () => {
        const t = [];
        if (!C.name.trim() && !C.company.trim()) t.push('Add your name or your business name.');
        if (!C.phone.trim() && !C.email.trim()) t.push('Add at least a phone number or an email so people can reach you.');
        if (C.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(C.email.trim())) t.push('The email address doesn’t look complete.');
        if (C.qr === 'web' && !site()) t.push('The QR code needs your website — add it, or pick “QR code that saves my contact”.');
        FIELDS.forEach(k => { const v = C[k].trim(); if (v.length > (k === 'address' ? 48 : 34)) t.push(`“${v.slice(0, 24)}…” is long — it’s printed smaller. A shorter version reads better.`); });
        if (isSample()) t.push('This is an example — type your own details.');
        return t.slice(0, 4);
    };

    /* ------------------------------------------------ editor */
    const fld = (k, label, o = {}) => `<label class="${o.wide ? 'rb-wide' : ''}">${label}<input data-c="${k}" type="${o.type || 'text'}" maxlength="${k === 'address' ? 160 : 80}" value="${esc(C[k])}" placeholder="${esc(o.ph || '')}"${o.type === 'email' || o.type === 'url' ? ' autocapitalize="off" spellcheck="false"' : ''}></label>`;
    const draw = () => {
        $('[data-form]').innerHTML = `
        <details class="fb-settings" open><summary>You</summary>
            <div class="fb-2">${fld('name', 'Your name')}${fld('title', 'Job title')}${fld('company', 'Business name')}${fld('tagline', 'Tagline (back of the card)', { ph: 'e.g. Fresh flowers, every day' })}</div>
        </details>
        <details class="fb-settings" open><summary>How to reach you</summary>
            <div class="fb-2">${fld('phone', 'Phone', { type: 'tel' })}${fld('email', 'Email', { type: 'email' })}${fld('web', 'Website', { type: 'url', ph: 'yourbusiness.com' })}${fld('address', 'Address (optional)', { ph: 'Street, town' })}</div>
        </details>
        <details class="fb-settings" open><summary>Logo, colour &amp; QR code</summary>
            <div class="rb-photo cb-logo">${C.logo ? `<img src="${C.logo}" alt="Your logo">` : `<span aria-hidden="true">${ic('image')}</span>`}<div><button type="button" class="btn btn--sm btn--ghost" data-logo>${C.logo ? 'Change logo' : 'Add your logo'}</button>${C.logo ? '<button type="button" class="rb-link" data-logo-clear>Remove</button>' : ''}<small class="cb-note">No logo? Your initials are used.</small></div></div>
            <div class="fb-2">
                <label>Colour<input type="color" data-c="color" value="${C.color}"></label>
                <label>Font<select data-c="font">${Object.entries(FONTS).map(([k, v]) => `<option value="${esc(k)}" ${C.font === k ? 'selected' : ''}>${v}</option>`).join('')}</select></label>
                <label>Card size<select data-c="size">${Object.entries(SIZES).map(([k, v]) => `<option value="${k}" ${C.size === k ? 'selected' : ''}>${v[0]}</option>`).join('')}</select></label>
                <label>Back of the card<select data-c="qr">${Object.entries(QRS).map(([k, v]) => `<option value="${k}" ${C.qr === k ? 'selected' : ''}>${v}</option>`).join('')}</select></label>
            </div>
        </details>`;
    };
    const drawLook = () => { $('[data-designs]').innerHTML = Object.entries(DESIGNS).map(([k, v]) => `<button type="button" class="fb-chip${C.design === k ? ' is-on' : ''}" data-design="${k}" aria-pressed="${C.design === k}">${v}</button>`).join(''); };
    const preview = () => {
        $('[data-front]').innerHTML = side('front', { label: 'Front of your card' });
        $('[data-back]').innerHTML = side('back', { label: 'Back of your card' });
        const t = tips(); $('[data-tips]').innerHTML = t.map(x => `<li>${esc(x)}</li>`).join(''); $('[data-tips-box]').hidden = !t.length;
    };
    let saveTimer = 0;
    const changed = () => { clearTimeout(saveTimer); saveTimer = setTimeout(save, 300); preview(); };
    addEventListener('pagehide', () => { clearTimeout(saveTimer); save(); });

    root.addEventListener('input', e => { const k = e.target.dataset.c; if (!k) return; C[k] = e.target.value; changed(); });
    root.addEventListener('click', e => {
        const t = e.target.closest('button'); if (!t || !root.contains(t)) return;
        if (t.dataset.design) { C.design = t.dataset.design; drawLook(); changed(); track('card_design', { design: C.design }); }
        else if ('logo' in t.dataset) $('[data-logo-file]').click();
        else if ('logoClear' in t.dataset) { C.logo = ''; draw(); changed(); }
    });
    $('[data-logo-file]').addEventListener('change', async e => {
        const f = e.target.files[0]; e.target.value = ''; if (!f) return;
        const bmp = await createImageBitmap(f).catch(() => null);
        if (!bmp) return ask({ icon: 'info', title: 'That image couldn’t be read', text: 'Please choose a JPG, PNG or WebP logo.', cancel: false, confirm: 'OK' });
        const k = Math.min(1, 900 / bmp.width, 600 / bmp.height), c = document.createElement('canvas'); // big enough to print sharp
        c.width = Math.max(1, Math.round(bmp.width * k)); c.height = Math.max(1, Math.round(bmp.height * k));
        c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
        let url = c.toDataURL('image/png');
        if (url.length > 380000) { const g = c.getContext('2d'); g.globalCompositeOperation = 'destination-over'; g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height); url = c.toDataURL('image/jpeg', 0.88); }
        C.logo = url; draw(); changed(); wzRefresh?.();
    });

    /* ------------------------------------------------ downloads */
    const needOwn = async () => {
        if (!isSample()) return true;
        return ask({ icon: 'info', title: 'This is still the example card', text: 'Type your own details first — or download the example anyway.', confirm: 'Download anyway', cancel: 'Add my details' });
    };
    const fileBase = () => 'business-card-' + ((C.name || C.company).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'owlvix');
    const png = (k, px) => new Promise((ok, no) => {
        const img = new Image(), url = URL.createObjectURL(new Blob([side(k, { px })], { type: 'image/svg+xml' }));
        img.onload = () => { const c = document.createElement('canvas'); c.width = img.naturalWidth || px; c.height = img.naturalHeight; c.getContext('2d').drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(url); c.toBlob(b => (b ? ok(b) : no()), 'image/png'); };
        img.onerror = () => { URL.revokeObjectURL(url); no(); };
        img.src = url;
    });
    const saveFile = (blob, name) => { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 4000); };
    /** A print window: `pages` are SVG strings, each on its own page of `size` (CSS @page size). */
    const printWin = (title, size, pages, w) => {
        w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(title)}</title><style>@page{size:${size};margin:0}*{box-sizing:border-box}html{-webkit-print-color-adjust:exact;print-color-adjust:exact}html,body{margin:0;background:#fff}.p{break-after:page;display:block;overflow:hidden}.p:last-child{break-after:auto}svg{display:block}@media screen{body{background:#e2e8f0;padding:20px}.p{margin:0 auto 20px;box-shadow:0 8px 24px rgba(15,23,42,.18);background:#fff}}</style></head><body>${pages.map(p => `<div class="p">${p}</div>`).join('')}<script>addEventListener("load",()=>setTimeout(()=>print(),200))<\/script></body></html>`);
        w.document.close();
    };
    const openWin = () => { const w = open('', '_blank'); if (!w) ask({ icon: 'info', title: 'Allow pop-ups to save your PDF', text: 'Your browser blocked the print window. Allow pop-ups for this site and try again.', cancel: false, confirm: 'OK' }); return w; };
    /** For a print shop: each side on its own page, card size + 3 mm bleed on every edge. */
    const pdfShop = () => {
        const w = openWin(); if (!w) return;
        const [, W, H] = SIZES[C.size];
        printWin(`${fileBase()} — print shop`, `${(W + 2 * B) / 10}mm ${(H + 2 * B) / 10}mm`, [side('front', { bleed: true, mm: true }), side('back', { bleed: true, mm: true })], w);
        track('card_pdf', { kind: 'shop', design: C.design });
    };
    /** At home: 10 cards on A4 or Letter with cut marks; page 2 is the backs, mirrored so they line up when printed on both sides. */
    const pdfHome = paper => {
        const w = openWin(); if (!w) return;
        const [, W, H] = SIZES[C.size], [PW, PH] = paper === 'letter' ? [2159, 2794] : [2100, 2970], cols = 2, rows = 5;
        const x0 = (PW - cols * W) / 2, y0 = (PH - rows * H) / 2, mark = 40, gap = 15;
        const sheet = k => {
            const one = DRAW[C.design](W, H, k);
            let cards = '', marks = '';
            for (let r = 0; r < rows; r++) for (let c = 0; c < cols; c++) {
                const col = k === 'back' ? cols - 1 - c : c, x = x0 + col * W, y = y0 + r * H;
                cards += `<svg x="${x}" y="${y}" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" overflow="hidden">${one.replace(/id="/g, `id="${k}${r}${c}`)}</svg>`;
            }
            for (let c = 0; c <= cols; c++) { const x = x0 + c * W; marks += `<path d="M${x},${y0 - gap - mark}v${mark}M${x},${y0 + rows * H + gap}v${mark}"/>`; }
            for (let r = 0; r <= rows; r++) { const y = y0 + r * H; marks += `<path d="M${x0 - gap - mark},${y}h${mark}M${x0 + cols * W + gap},${y}h${mark}"/>`; }
            return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${PW} ${PH}" width="${PW / 10}mm" height="${PH / 10}mm">${cards}<g stroke="#64748b" stroke-width="2">${marks}</g></svg>`;
        };
        printWin(`${fileBase()} — 10 per sheet`, paper === 'letter' ? 'letter' : 'A4', [sheet('front'), sheet('back')], w);
        track('card_pdf', { kind: 'home', design: C.design });
    };
    root.addEventListener('click', async e => {
        const b = e.target.closest('[data-get]'); if (!b) return;
        const kind = b.dataset.get;
        // the print window must open straight from the click (pop-up blockers), so only the example card asks first
        if (isSample() && !(await needOwn())) { $('[data-c="name"]')?.focus(); return; }
        if (kind === 'shop') pdfShop();
        else if (kind === 'a4' || kind === 'letter') pdfHome(kind);
        else if (kind === 'png') {
            try { saveFile(await png('front', 1050), fileBase() + '-front.png'); saveFile(await png('back', 1050), fileBase() + '-back.png'); track('card_png', { design: C.design }); }
            catch { ask({ icon: 'info', title: 'Your browser couldn’t make the pictures', text: 'Use the PDF instead.', cancel: false, confirm: 'OK' }); }
        }
    });
    $('[data-reset]').addEventListener('click', async () => {
        if (!(await ask({ icon: 'warning', title: 'Start again?', text: 'Empty every box and remove your logo?', confirm: 'Start again', cancel: 'Cancel', danger: true }))) return;
        C = clean({ design: C.design, color: C.color, font: C.font, size: C.size, qr: C.qr }); FIELDS.forEach(k => (C[k] = '')); draw(); changed();
    });
    $('[data-paper]').value = LOCALE_US ? 'letter' : 'a4';
    $('[data-paper]').addEventListener('change', e => { $('[data-get-home]').dataset.get = e.target.value; });
    $('[data-get-home]').dataset.get = $('[data-paper]').value;

    /* ------------------------------------------------ quick setup wizard (public/js/builder-wizard.js) — everything stays on this device */
    let wzRefresh = null;
    const WZ = window.OwlWizard;
    if (WZ) {
        let fresh = isSample(), wbox = null, wt = 0;
        const own = () => { if (!fresh) return; fresh = false; FIELDS.forEach(k => (C[k] = '')); };
        wzRefresh = () => { if (wbox) wbox.innerHTML = side('front') + side('back'); };
        const upd = () => { save(); clearTimeout(wt); wt = setTimeout(wzRefresh, 120); };
        const field = (k, label, ph, type = 'text') => `<label class="wz-field">${label}<input data-wz="${k}" type="${type}" maxlength="80" value="${esc(fresh ? '' : C[k])}" placeholder="${esc(ph)}"></label>`;
        const EM = { classic: 'doc', band: 'grid', bold: 'bolt', minimal: 'sparkle', split: 'card', dark: 'moon' };
        const steps = [
            { title: 'Pick a design', sub: 'You can change it any time — your details move with you.',
                draw: () => WZ.cards(Object.entries(DESIGNS).map(([k, v]) => [k, EM[k], v]), C.design),
                click: (e, el, box) => { if (!el.dataset.wzPick) return; C.design = el.dataset.wzPick; WZ.press(box, '[data-wz-pick]', el); upd(); } },
            { title: 'Who is it for?', sub: 'Your name and what you do.',
                draw: () => `<div class="wz-2">${field('name', 'Your name', 'e.g. Sara Khan')}${field('title', 'Job title', 'e.g. Interior Designer')}${field('company', 'Business name', 'e.g. Khan Studio')}${field('tagline', 'Tagline (optional)', 'e.g. Rooms that feel like you')}</div>`,
                input: (e, el) => { const k = el.dataset.wz; if (!k) return; own(); C[k] = el.value; upd(); },
                ok: () => (!fresh && (C.name.trim() || C.company.trim()) ? '' : 'Please type your name or business name.') },
            { title: 'How can people reach you?', sub: 'Add the ones you want on the card.',
                draw: () => `<div class="wz-2">${field('phone', 'Phone', '+44 7700 900123', 'tel')}${field('email', 'Email', 'you@business.com', 'email')}${field('web', 'Website', 'yourbusiness.com')}${field('address', 'Address (optional)', 'Street, town')}</div>`,
                input: (e, el) => { const k = el.dataset.wz; if (!k) return; own(); C[k] = el.value; upd(); },
                ok: () => (C.phone.trim() || C.email.trim() || C.web.trim() ? '' : 'Please add a phone, email or website.') },
            { title: 'Logo and colour', sub: 'No logo? Your initials are used. The back can carry a QR code too.',
                draw: () => `<div class="wz-look"><b>Logo</b><div class="wz-chips"><button type="button" data-wz-logo>${C.logo ? 'Change logo' : 'Add your logo'}</button></div></div>
                    <div class="wz-look"><b>Back of the card</b><div class="wz-chips">${Object.entries(QRS).map(([k, v]) => `<button type="button" data-wz-qr="${k}" aria-pressed="${C.qr === k}">${v}</button>`).join('')}</div></div>${WZ.palette(C.color)}`,
                click: (e, el, box) => {
                    if ('wzLogo' in el.dataset) { $('[data-logo-file]').click(); return; }
                    if (el.dataset.wzQr) { C.qr = el.dataset.wzQr; WZ.press(box, '[data-wz-qr]', el); }
                    else if (el.dataset.pal) { C.color = el.dataset.pal; WZ.press(box, '[data-pal]', el); box.querySelector('[data-wz-color]').value = el.dataset.pal; }
                    else return;
                    upd();
                },
                input: (e, el, box) => { if (!el.matches('[data-wz-color]')) return; C.color = el.value; box.querySelectorAll('[data-pal]').forEach(x => x.setAttribute('aria-pressed', 'false')); upd(); } },
        ];
        WZ.mount({
            root, banner: document.querySelector('[data-wz-banner]'), offKey: 'owlvix-wz-card-off',
            say: ['Hoo! Need business cards? Let’s make yours — about a minute.', 'Your details stay on this device. Nothing is uploaded.', 'Watch both sides fill in as you type. Ready?'],
            hasSaved: () => !isSample(),
            quickSlot: root.querySelector('.fb-top__actions'),
            onRestart: () => { fresh = isSample(); },
            steps,
            preview: {
                mount: s => { s.innerHTML = '<p class="wz-prev__label">Live preview</p><div class="cb-wz-prev" data-wz-card></div>'; wbox = s.querySelector('[data-wz-card]'); wzRefresh(); },
                unmount: () => { wbox = null; draw(); drawLook(); preview(); },
                refresh: () => wzRefresh(),
            },
            building: 'Making your cards…',
            tasks: ['Setting your details', 'Drawing both sides', 'Adding your QR code', 'Getting it ready to print'],
            done: {
                title: 'Your business card is ready',
                sub: 'Save a PDF for a print shop, print 10 on a sheet at home, or keep editing.',
                actions: [
                    { label: 'Print-ready PDF', icon: 'download', cls: 'btn btn--glow', then: 'pdf', act: async (close, o) => {
                        close();
                        if (o?.replay) { if (await window.sweet({ icon: 'success', title: 'Your business card is ready', text: 'Save it as a PDF — or keep editing first.', confirm: 'Print-ready PDF', cancel: 'Keep editing' })) pdfShop(); return; }
                        pdfShop();
                    } },
                    { label: 'Keep editing', icon: 'pen', act: close => close() },
                ],
            },
            editorUrl: root.dataset.editorUrl || '', noun: 'business card',
            hasWork: () => !isSample(),
            beforeLeave: () => { try { localStorage.setItem(STORE, JSON.stringify(C)); return true; } catch { return false; } },
        });
    }

    draw(); drawLook(); preview();
})();
