/* Owlvix free QR code generator (/qr-code-generator). Everything happens in the browser with our own encoder
   (public/js/qr.js): nothing is uploaded and the codes are "static" — the link or details are inside the code itself,
   so they never expire and keep working without us. One data model:
   { id, name, type, data{ [type]: {fields} }, fg, bg, clear, eye, dots, eyes, logo, logoSize, frame, label, ec, px }.
   Earlier codes are kept in "My QR codes" (owlvix-qrs, newest first). */
(() => {
    const root = document.querySelector('[data-qb]');
    if (!root || !window.OwlQR) return;
    const QR = window.OwlQR;
    const $ = (s, el = root) => el.querySelector(s), $$ = (s, el = root) => [...el.querySelectorAll(s)];
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const hex = (c, d) => (/^#[0-9a-f]{6}$/i.test(c || '') ? c.toLowerCase() : d);
    /** One of our icons (icons.js), as inline SVG. */
    const ic = (v, o) => (window.OwlIcon ? window.OwlIcon(v, o) : '');
    const uid = () => (Math.random().toString(36).slice(2) + '00000000').slice(0, 8);
    const track = (e, d = {}) => window.track?.(e, d);
    /** Styled dialog (window.sweet). If its script hasn't loaded after a short wait, questions answer "no". */
    const ask = async o => {
        for (let i = 0; i < 20 && !window.sweet; i++) await new Promise(r => setTimeout(r, 100));
        if (window.sweet) return window.sweet(o);
        status(o.title);
        return o.cancel === false;
    };

    const STORE = 'owlvix-qr', LIST = 'owlvix-qrs', MAX_LIST = 40;
    // type => [name, emoji, what scanning it does]
    const TYPES = {
        url: ['Website', 'link', 'Opens a web page'], wifi: ['Wi-Fi', 'wifi', 'Joins your Wi-Fi'], vcard: ['Contact card', 'user', 'Saves your details'],
        text: ['Text', 'note', 'Shows a message'], email: ['Email', 'mail', 'Writes an email'], phone: ['Phone call', 'call', 'Calls your number'],
        sms: ['SMS', 'chat', 'Writes a text'], whatsapp: ['WhatsApp', 'whatsapp', 'Opens a chat'], location: ['Location', 'pin', 'Opens a map'],
        event: ['Event', 'calendar', 'Adds it to the calendar'],
    };
    // type => fields [key, label, { type, ph, max, area, rows, opts, check, wide }]
    const FIELDS = {
        url: [['url', 'Web address', { type: 'url', ph: 'https://yourbusiness.com', max: 1500, wide: true }]],
        text: [['text', 'Your text', { area: true, rows: 4, max: 1500, wide: true, ph: 'Anything you like — a message, a code, instructions…' }]],
        wifi: [['ssid', 'Network name (SSID)', { ph: 'e.g. Cafe Guest' }], ['pass', 'Password', { ph: 'Wi-Fi password' }],
            ['enc', 'Security', { opts: [['WPA', 'WPA / WPA2 / WPA3 (most)'], ['WEP', 'WEP (old routers)'], ['nopass', 'No password']] }], ['hidden', 'It’s a hidden network', { check: true }]],
        email: [['to', 'Email address', { type: 'email', ph: 'hello@yourbusiness.com' }], ['subject', 'Subject (optional)', { max: 200 }], ['body', 'Message (optional)', { area: true, rows: 3, max: 1000, wide: true }]],
        phone: [['tel', 'Phone number', { type: 'tel', ph: '+44 7700 900123' }]],
        sms: [['tel', 'Phone number', { type: 'tel', ph: '+44 7700 900123' }], ['msg', 'Message (optional)', { area: true, rows: 3, max: 500, wide: true }]],
        whatsapp: [['tel', 'WhatsApp number', { type: 'tel', ph: '+44 7700 900123' }], ['msg', 'Ready-made message (optional)', { area: true, rows: 3, max: 500, wide: true, ph: 'Hi! I’d like to book…' }]],
        vcard: [['first', 'First name'], ['last', 'Last name'], ['org', 'Company (optional)'], ['title', 'Job title (optional)'],
            ['mobile', 'Mobile', { type: 'tel' }], ['work', 'Work phone (optional)', { type: 'tel' }], ['email', 'Email', { type: 'email' }], ['web', 'Website (optional)', { type: 'url', max: 300 }],
            ['street', 'Street (optional)', { max: 200 }], ['city', 'Town or city'], ['zip', 'Postcode / ZIP', { max: 30 }], ['country', 'Country'], ['note', 'Note (optional)', { area: true, rows: 2, max: 300, wide: true }]],
        location: [['place', 'Address, place name or coordinates', { ph: 'e.g. 10 Downing Street, London — or 51.5034, -0.1276', max: 300, wide: true }]],
        event: [['title', 'Event name', { ph: 'e.g. Summer open day', wide: true }], ['start', 'Starts', { type: 'datetime-local' }], ['end', 'Ends (optional)', { type: 'datetime-local' }],
            ['where', 'Where (optional)', { max: 200, wide: true }], ['details', 'Details (optional)', { area: true, rows: 2, max: 500, wide: true }]],
    };
    const DOTS = { square: 'Square', rounded: 'Rounded', dots: 'Dots' };
    const EYES = { square: 'Square', rounded: 'Rounded', circle: 'Circle' };
    const FRAMES = { none: 'No label', below: 'Text below', box: 'Frame' };
    const LOGO_SIZES = { s: ['Small', 0.17], m: ['Medium', 0.21], l: ['Large', 0.25] };
    const ECS = { auto: 'Automatic (recommended)', L: 'Low — smallest code (7%)', M: 'Medium (15%)', Q: 'Quartile (25%)', H: 'High — survives the most damage (30%)' };
    const PXS = [512, 1024, 2048, 4096];
    const LABELS = { url: 'Scan me', wifi: 'Scan to join our Wi-Fi', vcard: 'Scan to save my contact', text: 'Scan me', email: 'Scan to email us', phone: 'Scan to call us',
        sms: 'Scan to text us', whatsapp: 'Chat with us on WhatsApp', location: 'Scan for directions', event: 'Scan to save the date' };

    /* ------------------------------------------------ data */
    const str = (v, max = 200) => String(v ?? '').slice(0, max);
    const blank = (o = {}) => ({ id: uid(), name: '', type: 'url', data: {}, fg: '#111827', bg: '#ffffff', clear: false, eye: '', dots: 'square', eyes: 'square', logo: '', logoSize: 'm', frame: 'none', label: 'Scan me', ec: 'auto', px: 1024, ...o });
    const clean = d => {
        if (!d || typeof d !== 'object') return blank();
        const data = {};
        Object.entries(FIELDS).forEach(([t, fs]) => {
            const src = d.data && typeof d.data[t] === 'object' && d.data[t] ? d.data[t] : {}, out = {};
            fs.forEach(([k, , o = {}]) => { if (o.check) out[k] = !!src[k]; else if (src[k] != null && src[k] !== '') out[k] = str(src[k], o.max || 120); });
            if (Object.keys(out).length) data[t] = out;
        });
        return {
            id: /^[a-z0-9]{8}$/.test(d.id || '') ? d.id : uid(), name: str(d.name, 60), type: Object.hasOwn(TYPES, d.type) ? d.type : 'url', data,
            fg: hex(d.fg, '#111827'), bg: hex(d.bg, '#ffffff'), clear: !!d.clear, eye: hex(d.eye, ''),
            dots: Object.hasOwn(DOTS, d.dots) ? d.dots : 'square', eyes: Object.hasOwn(EYES, d.eyes) ? d.eyes : 'square',
            logo: /^data:image\/(jpeg|png|webp);base64,[a-z0-9+/=]+$/i.test(d.logo || '') && d.logo.length < 200000 ? d.logo : '',
            logoSize: Object.hasOwn(LOGO_SIZES, d.logoSize) ? d.logoSize : 'm', frame: Object.hasOwn(FRAMES, d.frame) ? d.frame : 'none',
            label: str(d.label ?? 'Scan me', 40), ec: Object.hasOwn(ECS, d.ec) ? d.ec : 'auto', px: PXS.includes(+d.px) ? +d.px : 1024,
        };
    };
    const read = (k, dflt) => { try { return JSON.parse(localStorage.getItem(k) || 'null') ?? dflt; } catch { return dflt; } };
    let Q = clean(read(STORE, null));
    let mine = (Array.isArray(read(LIST, [])) ? read(LIST, []) : []).slice(0, MAX_LIST).map(clean);
    const D = () => (Q.data[Q.type] || (Q.data[Q.type] = {}));
    const status = t => { const s = $('[data-saved]'); if (s) s.textContent = t; };
    const save = () => {
        try { localStorage.setItem(STORE, JSON.stringify(Q)); status('Saved on this device'); }
        catch { status('Too big to save here — try a smaller logo'); }
    };
    const saveList = () => {
        try { localStorage.setItem(LIST, JSON.stringify(mine)); return true; }
        catch { mine = mine.map((x, i) => (i ? { ...x, logo: '' } : x)); try { localStorage.setItem(LIST, JSON.stringify(mine)); return true; } catch { return false; } }
    };

    /* ------------------------------------------------ what goes inside the code */
    const enc = encodeURIComponent;
    const wifiEsc = s => s.replace(/([\\;,:"])/g, '\\$1');
    const icsEsc = s => s.replace(/\\/g, '\\\\').replace(/\r?\n/g, '\\n').replace(/([,;])/g, '\\$1');
    const telOf = s => String(s || '').replace(/[^\d+]/g, '').replace(/(?!^)\+/g, '');
    const icsDate = s => (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(s || '') ? s.slice(0, 16).replace(/[-:]/g, '') + '00' : '');
    const plusHour = s => { const d = new Date(s); if (isNaN(d)) return ''; d.setHours(d.getHours() + 1); const p = n => String(n).padStart(2, '0'); return `${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}T${p(d.getHours())}${p(d.getMinutes())}00`; };
    /** The text inside the code for the chosen type, or '' while the needed boxes are still empty. */
    const payload = (q = Q) => {
        const d = q.data[q.type] || {}, v = k => String(d[k] || '').trim();
        switch (q.type) {
            case 'url': {
                const u = v('url'); if (!u) return '';
                return /^[a-z][a-z0-9+.-]*:\/\//i.test(u) || /^(mailto|tel|sms|smsto|geo|market|whatsapp|bitcoin):/i.test(u) ? u : 'https://' + u;
            }
            case 'text': return String(d.text || '').trim() ? String(d.text) : '';
            case 'wifi': {
                if (!v('ssid')) return '';
                const t = ['WPA', 'WEP', 'nopass'].includes(d.enc) ? d.enc : 'WPA';
                return `WIFI:T:${t};S:${wifiEsc(v('ssid'))};${t !== 'nopass' ? `P:${wifiEsc(String(d.pass || ''))};` : ''}${d.hidden ? 'H:true;' : ''};`;
            }
            case 'email': {
                if (!v('to')) return '';
                const qs = [v('subject') && 'subject=' + enc(v('subject')), v('body') && 'body=' + enc(v('body'))].filter(Boolean).join('&');
                return `mailto:${v('to')}${qs ? '?' + qs : ''}`;
            }
            case 'phone': return telOf(v('tel')).replace(/\D/g, '') ? 'tel:' + telOf(v('tel')) : '';
            case 'sms': return telOf(v('tel')).replace(/\D/g, '') ? `SMSTO:${telOf(v('tel'))}:${v('msg')}` : '';
            case 'whatsapp': { const n = telOf(v('tel')).replace(/\D/g, ''); return n ? `https://wa.me/${n}${v('msg') ? '?text=' + enc(v('msg')) : ''}` : ''; }
            case 'vcard': {
                if (!v('first') && !v('last') && !v('org')) return '';
                const L = ['BEGIN:VCARD', 'VERSION:3.0', `N:${icsEsc(v('last'))};${icsEsc(v('first'))};;;`, `FN:${icsEsc([v('first'), v('last')].filter(Boolean).join(' ') || v('org'))}`];
                if (v('org')) L.push('ORG:' + icsEsc(v('org')));
                if (v('title')) L.push('TITLE:' + icsEsc(v('title')));
                if (telOf(v('mobile'))) L.push('TEL;TYPE=CELL:' + telOf(v('mobile')));
                if (telOf(v('work'))) L.push('TEL;TYPE=WORK,VOICE:' + telOf(v('work')));
                if (v('email')) L.push('EMAIL:' + v('email'));
                if (v('web')) L.push('URL:' + v('web'));
                if (v('street') || v('city') || v('zip') || v('country')) L.push(`ADR;TYPE=WORK:;;${icsEsc(v('street'))};${icsEsc(v('city'))};;${icsEsc(v('zip'))};${icsEsc(v('country'))}`);
                if (v('note')) L.push('NOTE:' + icsEsc(v('note')));
                L.push('END:VCARD');
                return L.join('\r\n');
            }
            case 'location': return v('place') ? 'https://www.google.com/maps/search/?api=1&query=' + enc(v('place')) : '';
            case 'event': {
                if (!v('title') || !icsDate(v('start'))) return '';
                const L = ['BEGIN:VEVENT', 'SUMMARY:' + icsEsc(v('title')), 'DTSTART:' + icsDate(v('start')), 'DTEND:' + (icsDate(v('end')) > icsDate(v('start')) ? icsDate(v('end')) : plusHour(v('start')))];
                if (v('where')) L.push('LOCATION:' + icsEsc(v('where')));
                if (v('details')) L.push('DESCRIPTION:' + icsEsc(v('details')));
                L.push('END:VEVENT');
                return L.join('\r\n');
            }
        }
        return '';
    };
    const NEED = { url: 'your web address', text: 'your text', wifi: 'your Wi-Fi network name', email: 'the email address', phone: 'the phone number', sms: 'the phone number',
        whatsapp: 'the WhatsApp number', vcard: 'a name or company', location: 'an address or place', event: 'the event name and start time' };
    /** A short name for "My QR codes". */
    const summary = q => {
        if (q.name) return q.name;
        const d = q.data[q.type] || {};
        const s = { url: d.url, text: d.text, wifi: d.ssid, email: d.to, phone: d.tel, sms: d.tel, whatsapp: d.tel, vcard: [d.first, d.last].filter(Boolean).join(' ') || d.org, location: d.place, event: d.title }[q.type];
        return String(s || '').replace(/^https?:\/\/(www\.)?/i, '').replace(/\s+/g, ' ').slice(0, 60) || TYPES[q.type][0];
    };
    /** The error correction level: automatic = High with a logo (it hides part of the code), else Medium. */
    const levelFor = (text, q = Q) => {
        const want = q.ec === 'auto' ? (q.logo ? 'H' : 'M') : q.ec;
        if (QR.fits(text, want)) return want;
        if (q.ec === 'auto') return ['Q', 'M', 'L'].find(l => QR.fits(text, l)) || '';
        return '';
    };

    /* ------------------------------------------------ drawing: one SVG for the preview, PNG and SVG downloads */
    const U = 10; // one square = 10 units
    const lum = c => { const v = [1, 3, 5].map(i => parseInt(c.slice(i, i + 2), 16) / 255).map(x => (x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4)); return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2]; };
    const contrast = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
    const r1 = n => Math.round(n * 100) / 100;
    /** Rounded rectangle path (x, y, w, h, corner radius). */
    const rr = (x, y, w, h, r) => `M${r1(x + r)},${r1(y)}h${r1(w - 2 * r)}a${r},${r} 0 0 1 ${r},${r}v${r1(h - 2 * r)}a${r},${r} 0 0 1 -${r},${r}h-${r1(w - 2 * r)}a${r},${r} 0 0 1 -${r},-${r}v-${r1(h - 2 * r)}a${r},${r} 0 0 1 ${r},-${r}z`;
    const circ = (cx, cy, r) => `M${r1(cx - r)},${r1(cy)}a${r},${r} 0 1 0 ${r1(2 * r)},0a${r},${r} 0 1 0 -${r1(2 * r)},0z`;
    /**
     * The whole picture as SVG text: quiet zone, squares in the chosen shape, the three corner "eyes", the logo in a
     * cleared middle, and an optional label or frame. Returns { svg, w, h, version, ec } or { error }.
     */
    const render = (text, q = Q, o = {}) => {
        if (!text) return { error: 'empty' };
        const ec = levelFor(text, q);
        if (!ec) return { error: 'long' };
        const code = QR.make(text, { ec }), n = code.size, m = 4, S = (n + 2 * m) * U;
        const fg = q.fg, bg = q.bg, eye = q.eye || fg;
        const finder = (x, y) => (x < 7 && y < 7) || (x >= n - 7 && y < 7) || (x < 7 && y >= n - 7);
        // logo: a cleared square in the middle (same parity as the code so it sits exactly centred)
        let hole = null;
        if (q.logo) { let s = Math.round(n * LOGO_SIZES[q.logoSize][1]); if ((n - s) % 2) s++; const a = (n - s) / 2; hole = [a, a + s]; }
        const inHole = (x, y) => hole && x >= hole[0] && x < hole[1] && y >= hole[0] && y < hole[1];
        const on = (x, y) => x >= 0 && y >= 0 && x < n && y < n && code.dark(x, y) && !finder(x, y) && !inHole(x, y);
        const o0 = m * U;
        let d = '';
        for (let y = 0; y < n; y++) {
            if (q.dots === 'square') { // whole runs in one go: a smaller file
                for (let x = 0; x < n; x++) { if (!on(x, y)) continue; let e = x; while (on(e + 1, y)) e++; d += `M${o0 + x * U},${o0 + y * U}h${(e - x + 1) * U}v${U}h-${(e - x + 1) * U}z`; x = e; }
                continue;
            }
            for (let x = 0; x < n; x++) {
                if (!on(x, y)) continue;
                const X = o0 + x * U, Y = o0 + y * U;
                if (q.dots === 'dots') { d += circ(X + U / 2, Y + U / 2, U * 0.45); continue; }
                // rounded: a corner is round only where no neighbour touches it; sides that meet a neighbour overlap a little (no hairline gaps)
                const up = on(x, y - 1), dn = on(x, y + 1), lf = on(x - 1, y), rt = on(x + 1, y), R = U / 2, e = 0.3;
                const x0 = X - (lf ? e : 0), x1 = X + U + (rt ? e : 0), y0 = Y - (up ? e : 0), y1 = Y + U + (dn ? e : 0);
                const tl = !up && !lf ? R : 0, tr = !up && !rt ? R : 0, br = !dn && !rt ? R : 0, bl = !dn && !lf ? R : 0;
                d += `M${r1(x0 + tl)},${r1(y0)}H${r1(x1 - tr)}${tr ? `a${R},${R} 0 0 1 ${R},${R}` : ''}V${r1(y1 - br)}${br ? `a${R},${R} 0 0 1 -${R},${R}` : ''}H${r1(x0 + bl)}${bl ? `a${R},${R} 0 0 1 -${R},-${R}` : ''}V${r1(y0 + tl)}${tl ? `a${R},${R} 0 0 1 ${R},-${R}` : ''}z`;
            }
        }
        // the corner eyes: a 7×7 ring and a 3×3 centre
        let eyes = '';
        [[0, 0], [n - 7, 0], [0, n - 7]].forEach(([fx, fy]) => {
            const X = o0 + fx * U, Y = o0 + fy * U;
            if (q.eyes === 'circle') eyes += circ(X + 3.5 * U, Y + 3.5 * U, 3.5 * U) + circ(X + 3.5 * U, Y + 3.5 * U, 2.5 * U) + circ(X + 3.5 * U, Y + 3.5 * U, 1.5 * U);
            else if (q.eyes === 'rounded') eyes += rr(X, Y, 7 * U, 7 * U, 2.2 * U) + rr(X + U, Y + U, 5 * U, 5 * U, 1.4 * U) + rr(X + 2 * U, Y + 2 * U, 3 * U, 3 * U, 0.9 * U);
            else eyes += `M${X},${Y}h${7 * U}v${7 * U}h-${7 * U}z` + `M${X + U},${Y + U}h${5 * U}v${5 * U}h-${5 * U}z` + `M${X + 2 * U},${Y + 2 * U}h${3 * U}v${3 * U}h-${3 * U}z`;
        });
        // frame / label
        const label = String(q.label || '').trim(), fs = Math.max(26, Math.round(S * 0.07)), font = 'Arial, Helvetica, sans-serif';
        let W = S, H = S, ox = 0, oy = 0, extra = '', back = '';
        const fit = t => { const max = (W - 2 * U * 2) / (fs * 0.56); return t.length > max ? Math.max(0.5, max / t.length) : 1; }; // shrink long labels
        if (q.frame === 'below' && label) {
            H = S + Math.round(fs * 1.6);
            const k = fit(label);
            extra = `<text x="${W / 2}" y="${S + fs * 0.55}" text-anchor="middle" font-family="${font}" font-weight="700" font-size="${Math.round(fs * k)}" fill="${fg}">${esc(label)}</text>`;
        } else if (q.frame === 'box') {
            const bw = U * 1.2, bar = label ? Math.round(fs * 2) : 0, R = U * 3;
            W = S + 2 * bw; H = S + 2 * bw + bar; ox = bw; oy = bw;
            back = `<path d="${rr(0, 0, W, H, R)}${rr(bw, bw, S, S, R - bw)}" fill="${fg}" fill-rule="evenodd"/>`;
            if (label) { const k = fit(label); extra = `<text x="${W / 2}" y="${S + bw + bar / 2 + fs * k * 0.36}" text-anchor="middle" font-family="${font}" font-weight="700" font-size="${Math.round(fs * k)}" fill="${q.clear ? '#ffffff' : bg}">${esc(label)}</text>`; }
        }
        const bgRect = q.clear ? '' : (q.frame === 'box' ? `<path d="${rr(0, 0, W, H, U * 3)}" fill="${bg}"/>` : `<rect width="${W}" height="${H}" fill="${bg}"/>`);
        let logo = '';
        if (hole) {
            const a = o0 + hole[0] * U, s = (hole[1] - hole[0]) * U, pad = U * 0.6;
            logo = `<image href="${q.logo}" x="${a + pad}" y="${a + pad}" width="${s - 2 * pad}" height="${s - 2 * pad}" preserveAspectRatio="xMidYMid meet"/>`;
        }
        const size = o.px ? ` width="${o.px}" height="${Math.round(o.px * H / W)}"` : '';
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}"${size}${o.title ? ` role="img" aria-label="${esc(o.title)}"` : ''}>${bgRect}${back}<g transform="translate(${ox},${oy})"><path d="${d}" fill="${fg}"/><path d="${eyes}" fill="${eye}" fill-rule="evenodd"/>${logo}</g>${extra}</svg>`;
        return { svg, w: W, h: H, version: code.version, ec, n };
    };

    /** PNG of the picture, `px` wide (a canvas drawn from the SVG). `white` puts it on white (for the scan check). */
    const toCanvas = (svg, w, h, px, white) => new Promise((ok, no) => {
        const img = new Image(), url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
        img.onload = () => {
            const c = document.createElement('canvas'); c.width = px; c.height = Math.round(px * h / w);
            const g = c.getContext('2d'); if (white) { g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height); }
            g.drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(url); ok(c);
        };
        img.onerror = () => { URL.revokeObjectURL(url); no(new Error('draw')); };
        img.src = url;
    });
    const pngBlob = (svg, w, h, px) => toCanvas(svg, w, h, px).then(c => new Promise(ok => c.toBlob(ok, 'image/png')));
    const fileName = ext => `qr-${(summary(Q).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'code').slice(0, 40)}.${ext}`;
    const saveFile = (blob, name) => { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 4000); };

    /* ------------------------------------------------ tips and the scan check */
    const tips = R => {
        const t = [], c = contrast(Q.fg, Q.bg);
        if (R.error) return t;
        if (!Q.clear && lum(Q.fg) > lum(Q.bg)) t.push('Light squares on a dark background: some phone cameras can’t read this. Make the code darker than its background.');
        else if (!Q.clear && c < 3) t.push('The colours are too close. Use a darker code colour or a lighter background so phones can read it.');
        if (Q.eye && contrast(Q.eye, Q.clear ? '#ffffff' : Q.bg) < 3) t.push('The corner colour is too light — the three corners are how phones find the code.');
        if (Q.clear) t.push('See-through background: put the code on something light and plain, never on a busy photo.');
        if (Q.logo && R.ec !== 'H' && R.ec !== 'Q') t.push('With a logo, set error correction to Automatic or High so the code still scans.');
        if (Q.type === 'url' && /^https?:\/\/[^/]*\s/.test(payload())) t.push('The web address has a space in it — check it’s typed right.');
        if (Q.type === 'wifi' && (D().enc || 'WPA') !== 'nopass' && !String(D().pass || '')) t.push('No Wi-Fi password typed. Choose “No password” if the network is open.');
        if (Q.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(D().to || '').trim())) t.push('The email address doesn’t look complete.');
        if (R.version > 15) t.push('That’s a lot of information, so the squares are small. Print it bigger, or shorten the text (a link to a page is smaller).');
        return t;
    };
    const detector = (() => { try { return 'BarcodeDetector' in window ? new window.BarcodeDetector({ formats: ['qr_code'] }) : null; } catch { return null; } })();
    let checkRun = 0;
    /** Reads the finished picture back with the browser's own QR reader (Chrome and Edge on Android, Mac and ChromeOS). */
    const scanCheck = async (R, text) => {
        const box = $('[data-scan]'); if (!box) return;
        if (!detector || R.error) { box.hidden = true; return; }
        const run = ++checkRun;
        try {
            const c = await toCanvas(R.svg, R.w, R.h, 640, true), found = await detector.detect(c);
            if (run !== checkRun) return;
            const ok = found.some(f => f.rawValue === text);
            box.hidden = false; box.className = 'qb-scan ' + (ok ? 'is-ok' : 'is-bad');
            box.innerHTML = ok ? `${ic('tick')} Scans fine — we checked it` : `${ic('warning')} It didn’t scan in our check. Try more contrast, a smaller logo or square dots.`;
        } catch { if (run === checkRun) box.hidden = true; }
    };

    /* ------------------------------------------------ the editor form */
    const fld = (k, label, o = {}) => {
        const val = D()[k] ?? '', id = `qb-${Q.type}-${k}`;
        if (o.check) return `<label class="fb-check rb-wide"><input type="checkbox" data-d="${k}" ${val ? 'checked' : ''}> ${esc(label)}</label>`;
        if (o.opts) return `<label for="${id}">${esc(label)}<select id="${id}" data-d="${k}">${o.opts.map(([v, t]) => `<option value="${v}" ${(val || o.opts[0][0]) === v ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select></label>`;
        if (o.area) return `<label class="${o.wide ? 'rb-wide' : ''}" for="${id}">${esc(label)}<textarea id="${id}" data-d="${k}" rows="${o.rows || 3}" maxlength="${o.max || 500}" placeholder="${esc(o.ph || '')}">${esc(val)}</textarea></label>`;
        return `<label class="${o.wide ? 'rb-wide' : ''}" for="${id}">${esc(label)}<input id="${id}" data-d="${k}" type="${o.type || 'text'}" maxlength="${o.max || 120}" value="${esc(val)}" placeholder="${esc(o.ph || '')}"${o.type === 'url' ? ' inputmode="url" autocapitalize="off" spellcheck="false"' : ''}${o.type === 'email' ? ' autocapitalize="off" spellcheck="false"' : ''}></label>`;
    };
    const chips = (name, map, cur) => `<div class="qb-chips" role="group">${Object.entries(map).map(([k, v]) => `<button type="button" class="fb-chip${cur === k ? ' is-on' : ''}" data-${name}="${k}" aria-pressed="${cur === k}">${esc(Array.isArray(v) ? v[0] : v)}</button>`).join('')}</div>`;
    const openState = {};
    const draw = () => {
        $$('[data-form] details').forEach(d => (openState[d.dataset.sec] = d.open));
        $('[data-form]').innerHTML = `
        <details class="fb-settings" data-sec="what" open><summary>What should it open?</summary>
            <div class="qb-types" role="group" aria-label="Type of QR code">${Object.entries(TYPES).map(([k, [n, em]]) => `<button type="button" class="qb-type${Q.type === k ? ' is-on' : ''}" data-type="${k}" aria-pressed="${Q.type === k}"><span aria-hidden="true">${ic(em)}</span>${esc(n)}</button>`).join('')}</div>
            <div class="fb-2 qb-fields">${FIELDS[Q.type].map(([k, l, o]) => fld(k, l, o)).join('')}</div>
            ${Q.type === 'url' ? '<p class="qb-note">Tip: a short address makes a simpler code that’s easier to scan.</p>' : ''}
            ${Q.type === 'wifi' ? '<p class="qb-note">Phones join straight away — guests don’t need to type the password.</p>' : ''}
        </details>
        <details class="fb-settings" data-sec="look" open><summary>Colours &amp; shape</summary>
            <div class="qb-colors">
                <label>Code colour<input type="color" data-q="fg" value="${Q.fg}"></label>
                <label>Background<input type="color" data-q="bg" value="${Q.bg}" ${Q.clear ? 'disabled' : ''}></label>
                <label>Corners<input type="color" data-q="eye" value="${Q.eye || Q.fg}"></label>
            </div>
            <div class="qb-presets" role="group" aria-label="Ready colour sets">${[['#111827', '#ffffff', 'Black'], ['#1e3a8a', '#ffffff', 'Navy'], ['#065f46', '#ffffff', 'Green'], ['#6d28d9', '#ffffff', 'Purple'], ['#9f1239', '#fff1f2', 'Red'], ['#7c2d12', '#fff7ed', 'Brown']].map(([f, b, n]) => `<button type="button" class="qb-preset" data-preset="${f},${b}" title="${n}" aria-label="${n} on ${b === '#ffffff' ? 'white' : 'light'}" style="--f:${f};--b:${b}"></button>`).join('')}</div>
            <label class="fb-check"><input type="checkbox" data-q="clear" ${Q.clear ? 'checked' : ''}> See-through background (PNG and SVG)</label>
            <div class="qb-row"><b>Squares</b>${chips('dots', DOTS, Q.dots)}</div>
            <div class="qb-row"><b>Corners</b>${chips('eyes', EYES, Q.eyes)}</div>
        </details>
        <details class="fb-settings" data-sec="logo" ${Q.logo ? 'open' : ''}><summary>Logo in the middle</summary>
            <div class="rb-photo qb-logo">${Q.logo ? `<img src="${Q.logo}" alt="Your logo">` : `<span aria-hidden="true">${ic('tag')}</span>`}<div><button type="button" class="btn btn--sm btn--ghost" data-logo>${Q.logo ? 'Change logo' : 'Add your logo'}</button>${Q.logo ? '<button type="button" class="rb-link" data-logo-clear>Remove</button>' : ''}</div></div>
            ${Q.logo ? `<div class="qb-row"><b>Size</b>${chips('logosize', LOGO_SIZES, Q.logoSize)}</div>` : ''}
            <p class="qb-note">Your logo stays on this device. With a logo, the code uses High error correction so it still scans.</p>
        </details>
        <details class="fb-settings" data-sec="frame" ${Q.frame !== 'none' ? 'open' : ''}><summary>Label &amp; frame</summary>
            <div class="qb-row">${chips('frame', FRAMES, Q.frame)}</div>
            ${Q.frame !== 'none' ? `<label class="qb-label">Text<input data-q="label" maxlength="40" value="${esc(Q.label)}" placeholder="Scan me"></label>
            <div class="qb-chips qb-sugg">${[...new Set(['Scan me', LABELS[Q.type], 'Scan for our menu', 'Leave us a review'])].map(t => `<button type="button" class="fb-chip" data-labeltext="${esc(t)}">${esc(t)}</button>`).join('')}</div>` : ''}
        </details>
        <details class="fb-settings" data-sec="more"><summary>More settings</summary>
            <div class="fb-2">
                <label for="qb-ec">Error correction<select id="qb-ec" data-q="ec">${Object.entries(ECS).map(([k, v]) => `<option value="${k}" ${Q.ec === k ? 'selected' : ''}>${esc(v)}</option>`).join('')}</select></label>
                <label for="qb-name">Name in “My QR codes”<input id="qb-name" data-q="name" maxlength="60" value="${esc(Q.name)}" placeholder="${esc(summary({ ...Q, name: '' }))}"></label>
            </div>
            <p class="qb-note">Higher error correction lets a scratched or partly covered code still scan, but makes the squares smaller.</p>
        </details>`;
        $$('[data-form] details').forEach(d => { if (d.dataset.sec in openState) d.open = openState[d.dataset.sec]; });
    };
    const drawMine = () => {
        const box = $('[data-mine]');
        box.hidden = !mine.length;
        $('[data-mine-count]').textContent = mine.length;
        $('[data-mine-list]').innerHTML = mine.map(x => {
            const R = render(payload(x), x);
            return `<li${x.id === Q.id ? ' class="is-open"' : ''}><span class="qb-thumb" aria-hidden="true">${R.svg || ''}</span><span><b>${esc(summary(x))}</b><small>${esc(TYPES[x.type][0])}</small></span>
            <span class="ib-mine__btns"><button type="button" class="btn btn--sm btn--ghost" data-open="${x.id}"${x.id === Q.id ? ' disabled' : ''}>${x.id === Q.id ? 'Open now' : 'Open'}</button><button type="button" class="fb-del" data-forget="${x.id}" aria-label="Delete ${esc(summary(x))} from this device" title="Delete">${ic('close')}</button></span></li>`;
        }).join('');
    };
    /** Put the open code in "My QR codes" (replacing its older copy). */
    const keep = () => {
        if (!payload()) return;
        mine = [{ ...Q }, ...mine.filter(x => x.id !== Q.id)].slice(0, MAX_LIST);
        saveList();
    };

    /* ------------------------------------------------ preview */
    let last = null, timer = 0;
    const SAMPLE = 'https://owlvix.com';
    const preview = () => {
        const text = payload(), R = render(text);
        last = { R, text };
        const stage = $('[data-stage]'), info = $('[data-info]');
        stage.classList.toggle('is-clear', Q.clear && !R.error);
        if (R.error === 'empty') {
            stage.innerHTML = `<div class="qb-empty">${render(SAMPLE, { ...Q, logo: '' }).svg}<p>Type ${esc(NEED[Q.type])} and your code appears here</p></div>`;
            info.textContent = '';
        } else if (R.error === 'long') {
            stage.innerHTML = '<div class="qb-empty qb-empty--long"><p>That’s too much for one QR code. Shorten the text — or put it on a web page and make a code for its link.</p></div>';
            info.textContent = '';
        } else {
            stage.innerHTML = R.svg.replace('<svg ', `<svg role="img" aria-label="${esc('QR code: ' + summary(Q))}" `);
            const cm = Math.max(2, Math.ceil((R.n + 8) * 0.05 * 2) / 2), inch = Math.max(0.8, Math.round(cm / 2.54 * 10) / 10);
            info.textContent = `Print it at least ${cm} cm (${inch} in) wide. Scanned from further away? Make it about a tenth of the distance.`;
        }
        $$('[data-dl]').forEach(b => (b.disabled = !!R.error));
        const t = tips(R); $('[data-tips]').innerHTML = t.map(x => `<li>${esc(x)}</li>`).join(''); $('[data-tips-box]').hidden = !t.length;
        clearTimeout(timer); timer = setTimeout(() => scanCheck(R, text), 500);
        if (R.error) $('[data-scan]').hidden = true;
    };
    let saveTimer = 0;
    const changed = () => { clearTimeout(saveTimer); saveTimer = setTimeout(save, 300); preview(); };
    addEventListener('pagehide', () => { clearTimeout(saveTimer); save(); });

    /* ------------------------------------------------ events */
    root.addEventListener('input', e => {
        const el = e.target;
        if (el.dataset.d) { D()[el.dataset.d] = el.type === 'checkbox' ? el.checked : el.value; if (el.dataset.d === 'enc') draw(); return changed(); }
        const k = el.dataset.q; if (!k) return;
        if (k === 'clear') { Q.clear = el.checked; draw(); $('[data-q="clear"]')?.focus(); }
        else if (k === 'fg') { if (!Q.eye || Q.eye === Q.fg) Q.eye = ''; Q.fg = el.value; const ey = $('[data-q="eye"]'); if (ey && !Q.eye) ey.value = Q.fg; }
        else if (k === 'eye') Q.eye = el.value === Q.fg ? '' : el.value;
        else if (k === 'ec') Q.ec = el.value;
        else Q[k] = el.value;
        if (k === 'name') drawMine();
        changed();
    });
    root.addEventListener('click', e => {
        const t = e.target.closest('button'); if (!t || !root.contains(t)) return;
        const ds = t.dataset;
        if (ds.type) {
            const wasDefault = Q.label === LABELS[Q.type] || Q.label === 'Scan me';
            Q.type = ds.type; if (wasDefault) Q.label = LABELS[Q.type];
            draw(); changed(); $('[data-form] [data-d]')?.focus(); track('qr_type', { type: Q.type });
        }
        else if (ds.dots) { Q.dots = ds.dots; draw(); changed(); $(`[data-dots="${ds.dots}"]`)?.focus(); }
        else if (ds.eyes) { Q.eyes = ds.eyes; draw(); changed(); $(`[data-eyes="${ds.eyes}"]`)?.focus(); }
        else if (ds.frame) { Q.frame = ds.frame; if (Q.frame !== 'none' && !Q.label.trim()) Q.label = LABELS[Q.type]; draw(); changed(); $(`[data-frame="${ds.frame}"]`)?.focus(); }
        else if (ds.logosize) { Q.logoSize = ds.logosize; draw(); changed(); $(`[data-logosize="${ds.logosize}"]`)?.focus(); }
        else if (ds.labeltext) { Q.label = ds.labeltext; const i = $('[data-q="label"]'); if (i) i.value = Q.label; changed(); }
        else if (ds.preset) { [Q.fg, Q.bg] = ds.preset.split(','); Q.eye = ''; Q.clear = false; draw(); changed(); $(`[data-preset="${ds.preset}"]`)?.focus(); }
        else if ('logo' in ds) $('[data-logo-file]').click();
        else if ('logoClear' in ds) { Q.logo = ''; draw(); changed(); }
    });

    // Logo: shrunk in the browser (never uploaded); PNG keeps a see-through background
    $('[data-logo-file]').addEventListener('change', async e => {
        const f = e.target.files[0]; e.target.value = ''; if (!f) return;
        const bmp = await createImageBitmap(f).catch(() => null);
        if (!bmp) return ask({ icon: 'info', title: 'That image couldn’t be read', text: 'Please choose a JPG, PNG or WebP logo.', cancel: false, confirm: 'OK' });
        const k = Math.min(1, 240 / bmp.width, 240 / bmp.height), c = document.createElement('canvas');
        c.width = Math.max(1, Math.round(bmp.width * k)); c.height = Math.max(1, Math.round(bmp.height * k));
        c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
        let url = c.toDataURL('image/png');
        if (url.length > 150000) { const g = c.getContext('2d'); g.globalCompositeOperation = 'destination-over'; g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height); url = c.toDataURL('image/jpeg', 0.85); }
        Q.logo = url; openState.logo = true; draw(); changed(); drawWz?.();
        track('qr_logo');
    });

    /* ------------------------------------------------ downloads, copy, new code, my codes */
    const needContent = () => ask({ icon: 'info', title: 'Nothing to put in the code yet', text: `Type ${NEED[Q.type]} first.`, cancel: false, confirm: 'OK' });
    const download = async kind => {
        const text = payload(), R = render(text, Q, kind === 'svg' ? { px: Q.px } : {});
        if (R.error === 'empty') return needContent();
        if (R.error) return ask({ icon: 'info', title: 'Too much for one QR code', text: 'Shorten the text, or put it on a web page and make a code for its link.', cancel: false, confirm: 'OK' });
        if (kind === 'svg') saveFile(new Blob([R.svg], { type: 'image/svg+xml' }), fileName('svg'));
        else {
            const blob = await pngBlob(R.svg, R.w, R.h, Q.px).catch(() => null);
            if (!blob) return ask({ icon: 'info', title: 'Your browser couldn’t make the PNG', text: 'Download the SVG instead — it works everywhere and stays sharp at any size.', cancel: false, confirm: 'OK' });
            saveFile(blob, fileName('png'));
        }
        keep(); drawMine();
        track('qr_download', { format: kind, type: Q.type });
    };
    $$('[data-dl]').forEach(b => b.addEventListener('click', () => download(b.dataset.dl)));
    $('[data-px]').innerHTML = PXS.map(p => `<option value="${p}">${p} × ${p} px</option>`).join('');
    $('[data-px]').value = String(Q.px);
    $('[data-px]').addEventListener('change', e => { Q.px = +e.target.value; save(); });
    $('[data-copy]')?.addEventListener('click', async () => {
        const text = payload(), R = render(text);
        if (R.error === 'empty') return needContent();
        if (R.error) return;
        try {
            if (!navigator.clipboard?.write || !window.ClipboardItem) throw new Error('no');
            await navigator.clipboard.write([new ClipboardItem({ 'image/png': pngBlob(R.svg, R.w, R.h, 1024) })]);
            status('Copied — paste it into your document or design');
            keep(); drawMine(); track('qr_download', { format: 'copy', type: Q.type });
        } catch { ask({ icon: 'info', title: 'Your browser can’t copy pictures', text: 'Use “Download PNG” instead.', cancel: false, confirm: 'OK' }); }
    });
    $('[data-new]').addEventListener('click', () => {
        keep();
        Q = blank({ type: Q.type, fg: Q.fg, bg: Q.bg, clear: Q.clear, eye: Q.eye, dots: Q.dots, eyes: Q.eyes, logo: Q.logo, logoSize: Q.logoSize, frame: Q.frame, label: Q.label, ec: Q.ec, px: Q.px });
        draw(); drawMine(); changed();
        status('New QR code — your colours and logo are kept');
        $('[data-form] [data-d]')?.focus();
        track('qr_new');
    });
    $('[data-mine]').addEventListener('click', async e => {
        const b = e.target.closest('button'); if (!b) return;
        const id = b.dataset.open || b.dataset.forget, x = mine.find(m => m.id === id); if (!x) return;
        if (b.dataset.forget) {
            if (!(await ask({ icon: 'warning', title: `Delete “${summary(x)}”?`, text: 'It’s removed from My QR codes on this device. Codes you already printed keep working.', confirm: 'Delete', cancel: 'Keep it', danger: true }))) return;
            mine = mine.filter(m => m.id !== id); saveList(); drawMine();
            $('[data-mine-list] button:not([disabled])')?.focus();
            return;
        }
        keep();
        Q = clean(x); $('[data-px]').value = String(Q.px); draw(); drawMine(); changed();
        status(`Opened “${summary(Q)}” — changes save on this device`);
        $('[data-mine]').open = false;
    });
    $('[data-reset]').addEventListener('click', async () => {
        if (!(await ask({ icon: 'warning', title: 'Start this code again?', text: 'Empty the boxes and go back to the plain black design? Codes in “My QR codes” stay.', confirm: 'Start again', cancel: 'Cancel', danger: true }))) return;
        Q = blank({ type: Q.type, px: Q.px }); draw(); changed();
    });

    /* ------------------------------------------------ quick setup wizard (public/js/builder-wizard.js) — everything stays on this device */
    let drawWz = null;
    const WZ = window.OwlWizard;
    if (WZ) {
        let wbox = null, wt = 0;
        drawWz = () => { if (!wbox) return; const R = render(payload()); wbox.innerHTML = R.svg || `<div class="qb-empty">${render(SAMPLE, { ...Q, logo: '' }).svg}</div>`; };
        const upd = () => { save(); clearTimeout(wt); wt = setTimeout(drawWz, 120); };
        const wfield = (k, label, o = {}) => {
            const val = D()[k] ?? '';
            if (o.check) return `<label class="wz-check"><input type="checkbox" data-wz-d="${k}" ${val ? 'checked' : ''}> ${esc(label)}</label>`;
            if (o.opts) return `<label class="wz-field">${esc(label)}<select data-wz-d="${k}">${o.opts.map(([v, t]) => `<option value="${v}" ${(val || o.opts[0][0]) === v ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select></label>`;
            if (o.area) return `<label class="wz-field">${esc(label)}<textarea data-wz-d="${k}" rows="${Math.min(o.rows || 3, 3)}" maxlength="${o.max || 500}" placeholder="${esc(o.ph || '')}">${esc(val)}</textarea></label>`;
            return `<label class="wz-field">${esc(label)}<input data-wz-d="${k}" type="${o.type || 'text'}" maxlength="${o.max || 120}" value="${esc(val)}" placeholder="${esc(o.ph || '')}"></label>`;
        };
        // the contact card has many boxes: the quick setup asks for the main ones
        const WZ_FIELDS = { vcard: ['first', 'last', 'org', 'mobile', 'email', 'web'], event: ['title', 'start', 'end', 'where'] };
        const steps = [
            {
                title: 'What should your QR code open?', sub: 'Pick one — you can change it any time.',
                draw: () => WZ.cards(Object.entries(TYPES).map(([k, [n, em, s]]) => [k, em, n, s]), Q.type),
                click: (e, el, box) => {
                    if (!el.dataset.wzPick) return;
                    const wasDefault = Q.label === LABELS[Q.type] || Q.label === 'Scan me';
                    Q.type = el.dataset.wzPick; if (wasDefault) Q.label = LABELS[Q.type];
                    WZ.press(box, '[data-wz-pick]', el); upd();
                },
            },
            {
                title: 'What goes in it?', sub: 'It’s saved inside the code itself — so it never expires.',
                draw: () => {
                    const fs = FIELDS[Q.type].filter(([k]) => !WZ_FIELDS[Q.type] || WZ_FIELDS[Q.type].includes(k));
                    return `<div class="wz-2 qb-wz-fields">${fs.map(([k, l, o]) => wfield(k, l, o)).join('')}</div>`;
                },
                input: (e, el) => { const k = el.dataset.wzD; if (!k) return; D()[k] = el.type === 'checkbox' ? el.checked : el.value; upd(); },
                change: (e, el) => { const k = el.dataset.wzD; if (!k) return; D()[k] = el.type === 'checkbox' ? el.checked : el.value; upd(); },
                ok: () => { const t = payload(); if (!t) return `Please type ${NEED[Q.type]}.`; return levelFor(t) ? '' : 'That’s too much for one QR code — please shorten it.'; },
            },
            {
                title: 'Colours and shape', sub: 'Keep the code dark on a light background — that’s what phones read best.',
                draw: () => WZ.palette(Q.fg, 'Or your own colour') + `<div class="wz-look"><b>Squares</b><div class="wz-chips">${Object.entries(DOTS).map(([k, v]) => `<button type="button" data-wz-dots="${k}" aria-pressed="${Q.dots === k}">${v}</button>`).join('')}</div></div>`
                    + `<div class="wz-look"><b>Corners</b><div class="wz-chips">${Object.entries(EYES).map(([k, v]) => `<button type="button" data-wz-eyes="${k}" aria-pressed="${Q.eyes === k}">${v}</button>`).join('')}</div></div>`,
                click: (e, el, box) => {
                    if (el.dataset.pal) { Q.fg = el.dataset.pal; Q.eye = ''; WZ.press(box, '[data-pal]', el); box.querySelector('[data-wz-color]').value = el.dataset.pal; }
                    else if (el.dataset.wzDots) { Q.dots = el.dataset.wzDots; WZ.press(box, '[data-wz-dots]', el); }
                    else if (el.dataset.wzEyes) { Q.eyes = el.dataset.wzEyes; WZ.press(box, '[data-wz-eyes]', el); }
                    else return;
                    upd();
                },
                input: (e, el, box) => { if (!el.matches('[data-wz-color]')) return; Q.fg = el.value; Q.eye = ''; box.querySelectorAll('[data-pal]').forEach(x => x.setAttribute('aria-pressed', 'false')); upd(); },
            },
            {
                title: 'Logo and label (optional)', sub: 'A logo in the middle and a short “Scan me” help people trust and use it.',
                draw: () => `<div class="wz-look"><b>Logo</b><div class="wz-chips"><button type="button" data-wz-logo>${Q.logo ? 'Change logo' : `${ic('plus')} Add your logo`}</button>${Q.logo ? '<button type="button" data-wz-logo-clear>Remove logo</button>' : ''}</div></div>
                    <div class="wz-look"><b>Label</b><div class="wz-chips">${Object.entries(FRAMES).map(([k, v]) => `<button type="button" data-wz-frame="${k}" aria-pressed="${Q.frame === k}">${v}</button>`).join('')}</div></div>
                    <label class="wz-field">Label text<input data-wz-label maxlength="40" value="${esc(Q.label)}" placeholder="Scan me"></label>`,
                click: (e, el, box) => {
                    if ('wzLogo' in el.dataset) { $('[data-logo-file]').click(); return; }
                    if ('wzLogoClear' in el.dataset) { Q.logo = ''; el.remove(); box.querySelector('[data-wz-logo]').innerHTML = `${ic('plus')} Add your logo`; upd(); return; }
                    if (!el.dataset.wzFrame) return;
                    Q.frame = el.dataset.wzFrame; WZ.press(box, '[data-wz-frame]', el); upd();
                },
                input: (e, el) => { if (!el.matches('[data-wz-label]')) return; Q.label = el.value; if (Q.frame === 'none' && el.value.trim()) Q.frame = 'below'; upd(); },
            },
        ];
        WZ.mount({
            root, banner: document.querySelector('[data-wz-banner]'), offKey: 'owlvix-wz-qr-off',
            say: ['Hoo! Need a QR code? Let’s make yours — under a minute.', 'It never expires — and nothing is uploaded.', 'Watch it change as you go. Ready?'],
            hasSaved: () => !!payload(),
            quickSlot: root.querySelector('.fb-top__actions'),
            steps,
            preview: {
                mount: side => { side.innerHTML = '<p class="wz-prev__label">Live preview</p><div class="qb-wz-prev" data-wz-qr></div>'; wbox = side.querySelector('[data-wz-qr]'); drawWz(); },
                unmount: () => { wbox = null; draw(); drawMine(); preview(); },
                refresh: () => drawWz(),
            },
            building: 'Making your QR code…',
            tasks: ['Putting your details in', 'Adding error correction', 'Drawing your design', 'Checking it scans'],
            done: {
                title: 'Your QR code is ready',
                sub: 'Download it, or keep editing. It never expires — print it on menus, cards, posters and packaging.',
                actions: [
                    { label: 'Download PNG', icon: 'download', cls: 'btn btn--glow', then: 'png', act: async (close, o) => {
                        close();
                        if (o?.replay) { if (await window.sweet({ icon: 'success', title: 'Your QR code is ready', text: 'Download it as a PNG — or keep editing first.', confirm: 'Download PNG', cancel: 'Keep editing' })) download('png'); return; }
                        setTimeout(() => download('png'), 350);
                    } },
                    { label: 'Keep editing', icon: 'pen', act: close => close() },
                ],
            },
            editorUrl: root.dataset.editorUrl || '', noun: 'QR code',
            hasWork: () => !!payload(),
            beforeLeave: () => { try { localStorage.setItem(STORE, JSON.stringify(Q)); return true; } catch { return false; } },
        });
    }

    draw(); drawMine(); preview();
})();
