/* Owlvix free booking page builder (/booking-page-builder). The owner sets their services, weekly hours and rules here;
   the draft is kept in this browser (owlvix-booking: { id, url, cfg, dirty }) and published to their builder account
   (POST /builder/bookings) as a booking page at /book/{id}. Clients book there; the owner sees and cancels bookings in
   the Bookings tab. The live preview is the real booking page (public/js/booking-page.js, window.OwlBooking) running on
   the same slot rules in the browser, without bookings. Settings `cfg`: see App\Support\BookingSchema. */
(() => {
    const root = document.querySelector('[data-bk]');
    if (!root || !window.OwlBooking) return;
    const OB = window.OwlBooking;
    const $ = (s, el = root) => el.querySelector(s), $$ = (s, el = root) => [...el.querySelectorAll(s)];
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    /** One of our icons (icons.js), as inline SVG. */
    const ic = (v, o) => (window.OwlIcon ? window.OwlIcon(v, o) : '');
    const track = (e, d = {}) => window.track?.(e, d);
    const toast = (msg, kind) => { if (window.owlToast) return window.owlToast(msg, kind); status(msg); };
    /** Styled dialog (window.sweet). If its script hasn't loaded after a short wait, questions answer "no". */
    const ask = async o => {
        for (let i = 0; i < 20 && !window.sweet; i++) await new Promise(r => setTimeout(r, 100));
        if (window.sweet) return window.sweet(o);
        status(o.title);
        return o.cancel === false;
    };
    const Acc = () => window.OwlAccount;

    const STORE = 'owlvix-booking', MAX_SVC = 12, MAX_RANGES = 3, MAX_OFF = 100;
    const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const BROWSER_TZ = (() => { try { return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'; } catch { return 'UTC'; } })();
    const LOCALE = (() => { try { return new Intl.NumberFormat(navigator.language).resolvedOptions().locale; } catch { return 'en-GB'; } })();
    /** Prices in the presets, in the owner's own currency (a guess from the browser's language region). */
    const money = (() => {
        const region = (LOCALE.split('-')[1] || '').toUpperCase();
        const EURO = 'AT BE CY DE EE ES FI FR GR HR IE IT LT LU LV MT NL PT SI SK'.split(' ');
        const BY = { GB: 'GBP', US: 'USD', CA: 'CAD', AU: 'AUD', NZ: 'NZD', IN: 'INR', PK: 'PKR', AE: 'AED', SA: 'SAR', ZA: 'ZAR', NG: 'NGN', KE: 'KES', SG: 'SGD', MY: 'MYR', PH: 'PHP', IE: 'EUR', CH: 'CHF', SE: 'SEK', NO: 'NOK', DK: 'DKK', PL: 'PLN', TR: 'TRY', BR: 'BRL', MX: 'MXN' };
        const cur = EURO.includes(region) ? 'EUR' : BY[region] || (region ? 'USD' : 'GBP');
        let f; try { f = new Intl.NumberFormat(LOCALE, { style: 'currency', currency: cur, maximumFractionDigits: 0 }); } catch { f = null; }
        return n => (n === 0 ? 'Free' : f ? f.format(n) : String(n));
    })();

    /* ------------------------------------------------ settings */
    const H = (...r) => r; // a day's ranges
    const WEEK = (days, ranges) => DAYS.map((_, i) => (days.includes(i) ? ranges.map(x => [...x]) : []));
    const HOURS = {
        wk95: ['Mon–Fri 9–5', () => WEEK([0, 1, 2, 3, 4], H(['09:00', '17:00']))],
        ms96: ['Mon–Sat 9–6', () => WEEK([0, 1, 2, 3, 4, 5], H(['09:00', '18:00']))],
        ed108: ['Every day 10–8', () => WEEK([0, 1, 2, 3, 4, 5, 6], H(['10:00', '20:00']))],
    };
    const svc = (key, name, mins, price, desc = '') => ({ key, name, mins, price: typeof price === 'number' ? money(price) : price, desc });
    // business type => [name, icon, small, () => partial cfg]
    const TYPES = {
        salon: ['Hair or beauty salon', 'flower', 'Cuts, colour, nails', () => ({ intro: 'Book your next appointment online — it only takes a minute.', color: '#be185d',
            services: [svc('cut', 'Haircut', 45, 35, 'Wash, cut and style'), svc('colour', 'Cut & colour', 120, 85, 'Full colour with a cut and blow-dry'), svc('blowdry', 'Blow-dry', 30, 25)],
            hours: WEEK([1, 2, 3, 4, 5], H(['09:00', '18:00'])), step: 15 })],
        barber: ['Barber', 'scissors', 'Cuts, fades, beards', () => ({ intro: 'Pick a time that suits you — walk in, sit down, look sharp.', color: '#1e1b49',
            services: [svc('cut', 'Haircut', 30, 20), svc('fade', 'Skin fade', 45, 25), svc('beard', 'Beard trim', 15, 10)],
            hours: WEEK([0, 1, 2, 3, 4, 5], H(['09:00', '18:00'])), step: 15 })],
        clinic: ['Clinic or therapist', 'health', 'Sessions, check-ups', () => ({ intro: 'Book a session at a time that works for you. Everything you tell us stays private.', color: '#0d9488',
            services: [svc('first', 'First consultation', 60, 70, 'We talk through what you need and make a plan'), svc('session', 'Follow-up session', 45, 55), svc('checkin', 'Short check-in', 20, 25)],
            hours: WEEK([0, 1, 2, 3, 4], H(['09:00', '12:30'], ['13:30', '17:00'])), step: 15, buffer: 15 })],
        consultant: ['Consultant', 'briefcase', 'Calls and meetings', () => ({ intro: 'Book a call to talk about your project. I’ll send the video link by email.', location: 'Online / video call', color: '#2563eb',
            services: [svc('intro', 'Free intro call', 15, 0, 'A quick chat to see if we’re a good fit'), svc('session', 'Strategy session', 60, 150), svc('workshop', 'Half-day workshop', 240, 500)],
            hours: WEEK([0, 1, 2, 3, 4], H(['09:00', '17:00'])), step: 30, notice: 12 })],
        tutor: ['Tutor or coach', 'graduation', 'Lessons, coaching', () => ({ intro: 'Book a lesson online. Your first trial lesson is free.', color: '#e8590c',
            services: [svc('trial', 'Trial lesson', 30, 0), svc('lesson', 'Lesson', 60, 40), svc('double', 'Double lesson', 120, 75)],
            hours: [[['15:00', '20:00']], [['15:00', '20:00']], [['15:00', '20:00']], [['15:00', '20:00']], [['15:00', '19:00']], [['09:00', '13:00']], []], step: 30 })],
        fitness: ['Personal trainer', 'dumbbell', 'Training sessions', () => ({ intro: 'Book your training session — mornings and evenings.', color: '#16a34a',
            services: [svc('taster', 'Taster session', 30, 0), svc('pt', 'Personal training', 60, 45), svc('duo', 'Train with a friend', 60, 60)],
            hours: [...Array(5)].map(() => [['06:00', '09:00'], ['17:00', '21:00']]).concat([[['08:00', '12:00']], []]), step: 30 })],
        photographer: ['Photographer', 'camera', 'Shoots and sessions', () => ({ intro: 'Book a photo session. We’ll be in touch to plan the details.', color: '#7c4a2d',
            services: [svc('mini', 'Mini shoot', 30, 90, '10 edited photos'), svc('portrait', 'Portrait session', 60, 180), svc('family', 'Family shoot', 90, 250)],
            hours: WEEK([2, 3, 4, 5, 6], H(['10:00', '18:00'])), step: 30, buffer: 30, notice: 24 })],
        other: ['Something else', 'calendar', 'Any appointments', () => ({ intro: 'Pick a time that suits you and book online.', color: '#2563eb',
            services: [svc('appt', 'Appointment', 30, ''), svc('long', 'Longer appointment', 60, '')],
            hours: WEEK([0, 1, 2, 3, 4], H(['09:00', '17:00'])), step: 0 })],
    };
    const example = () => ({
        name: 'Glow Hair Studio', intro: 'Book your next cut or colour online — it only takes a minute.', location: '12 Market Street, Leeds', color: '#7c3aed', tz: BROWSER_TZ,
        services: [{ key: 'cut', name: 'Haircut', mins: 45, price: money(35), desc: 'Wash, cut and style' }, { key: 'colour', name: 'Cut & colour', mins: 120, price: money(85), desc: 'Full colour with a cut and blow-dry' }, { key: 'blowdry', name: 'Blow-dry', mins: 30, price: money(25), desc: '' }],
        hours: WEEK([1, 2, 3, 4, 5], H(['09:00', '18:00'])), off: [], notice: 2, horizon: 60, buffer: 0, step: 15, askPhone: true,
        note: 'Please arrive 5 minutes early. Need to change your time? Use the link in your confirmation email.',
    });

    const str = (v, max) => String(v ?? '').slice(0, max);
    const hex = c => (/^#[0-9a-f]{6}$/i.test(c || '') ? c.toLowerCase() : '#7c3aed');
    const pick = (v, list, d) => (list.includes(+v) ? +v : d);
    const int = (v, lo, hi, d) => { const n = Math.round(+v); return Number.isFinite(n) ? Math.min(hi, Math.max(lo, n)) : d; };
    const T = /^([01]\d|2[0-3]):[0-5]\d$|^24:00$/;
    const keyOk = k => /^[a-z0-9]{1,24}$/.test(k || '');
    const newKey = taken => { let k; do k = 's' + (Math.random().toString(36).slice(2) + '000000').slice(0, 6); while (taken.includes(k)); return k; };
    const clean = c => {
        c = c && typeof c === 'object' ? c : example();
        const keys = [];
        const services = (Array.isArray(c.services) ? c.services : []).slice(0, MAX_SVC).map(s => {
            let key = keyOk(s?.key) && !keys.includes(s.key) ? s.key : newKey(keys); keys.push(key);
            return { key, name: str(s?.name, 80), mins: int(Math.round((+s?.mins || 30) / 5) * 5, 5, 480, 30), price: str(s?.price, 30), desc: str(s?.desc, 200) };
        });
        const hours = DAYS.map((_, i) => (Array.isArray(c.hours?.[i]) ? c.hours[i] : []).slice(0, MAX_RANGES).filter(r => Array.isArray(r) && T.test(r[0] || '') && T.test(r[1] || '')).map(r => [r[0], r[1]]));
        return {
            name: str(c.name, 80), intro: str(c.intro, 500), location: str(c.location, 200), color: hex(c.color), tz: str(c.tz || BROWSER_TZ, 64),
            services: services.length ? services : [{ key: 'appt', name: 'Appointment', mins: 30, price: '', desc: '' }], hours,
            off: [...new Set((Array.isArray(c.off) ? c.off : []).filter(d => /^\d{4}-\d{2}-\d{2}$/.test(d)))].sort().slice(0, MAX_OFF),
            notice: int(c.notice ?? 2, 0, 168, 2), horizon: int(c.horizon ?? 60, 1, 180, 60), buffer: int(c.buffer ?? 0, 0, 120, 0),
            step: pick(c.step, [0, 15, 30, 60], 0), askPhone: c.askPhone !== false, note: str(c.note, 500),
        };
    };
    /** What goes to the server and the preview: no unnamed services, blank-trimmed text. */
    const outCfg = () => {
        const c = structuredClone(C());
        c.services = c.services.filter(s => s.name.trim()).map(s => ({ ...s, name: s.name.trim(), price: s.price.trim(), desc: s.desc.trim() }));
        ['name', 'intro', 'location', 'note'].forEach(k => (c[k] = c[k].trim()));
        return c;
    };

    let D;
    try { D = JSON.parse(localStorage.getItem(STORE) || 'null'); } catch {}
    if (!D || typeof D !== 'object' || !D.cfg) D = { id: null, url: '', cfg: example(), dirty: false };
    D.cfg = clean(D.cfg);
    const C = () => D.cfg;
    const status = t => { const s = $('[data-status]'); if (s) s.textContent = t; };
    const save = () => { try { localStorage.setItem(STORE, JSON.stringify(D)); } catch {} };
    const isExample = () => JSON.stringify(C()) === JSON.stringify(clean(example()));

    /* ------------------------------------------------ checks before publishing */
    const mins = t => (t === '24:00' ? 1440 : +t.slice(0, 2) * 60 + +t.slice(3, 5));
    const problems = (c = C()) => {
        const out = [];
        if (!c.name.trim()) out.push('Add your business name.');
        if (!c.services.some(s => s.name.trim())) out.push('Add at least one service people can book.');
        const bad = c.hours.findIndex(day => day.some(r => mins(r[0]) >= mins(r[1])));
        if (bad >= 0) out.push(`${DAYS[bad]}: a closing time is before its opening time.`);
        const overlap = c.hours.findIndex(day => day.some((r, i) => day.some((q, j) => i !== j && mins(r[0]) < mins(q[1]) && mins(q[0]) < mins(r[1]))));
        if (overlap >= 0) out.push(`${DAYS[overlap]}: two sets of hours overlap.`);
        if (!c.hours.some(day => day.some(r => mins(r[0]) < mins(r[1])))) out.push('Open at least one day in your opening hours.');
        else {
            const shortest = Math.min(...c.services.filter(s => s.name.trim()).map(s => s.mins));
            if (Number.isFinite(shortest) && !c.hours.some(day => day.some(r => mins(r[1]) - mins(r[0]) >= shortest))) out.push('Your opening hours are shorter than your services — no times can be booked.');
        }
        return out;
    };
    const hints = (c = C()) => {
        const out = [];
        if (!c.location.trim()) out.push('Say where — an address, or “Online / video call”.');
        if (c.services.some(s => !s.name.trim())) out.push('Services without a name are left off your page.');
        if (c.tz !== BROWSER_TZ) out.push(`Your page uses ${c.tz.replace(/_/g, ' ')} time — your computer is on ${BROWSER_TZ.replace(/_/g, ' ')}. Check that’s right.`);
        return out;
    };

    /* ------------------------------------------------ time zones: yours first */
    const ZONES = (() => {
        let z = []; try { z = Intl.supportedValuesOf('timeZone'); } catch {}
        if (!z.length) z = ['UTC', 'Europe/London', 'Europe/Dublin', 'Europe/Paris', 'Europe/Berlin', 'Europe/Madrid', 'Europe/Rome', 'Europe/Amsterdam', 'Europe/Istanbul', 'Africa/Lagos', 'Africa/Johannesburg', 'Africa/Nairobi', 'Africa/Cairo', 'Asia/Dubai', 'Asia/Karachi', 'Asia/Kolkata', 'Asia/Dhaka', 'Asia/Singapore', 'Asia/Manila', 'Asia/Tokyo', 'Australia/Sydney', 'Pacific/Auckland', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'America/Toronto', 'America/Mexico_City', 'America/Sao_Paulo'];
        return [...new Set([BROWSER_TZ, ...z])];
    })();
    let zoneOpts = null;
    const zoneOptions = cur => {
        if (!zoneOpts) {
            const off = z => { try { return new Intl.DateTimeFormat('en', { timeZone: z, timeZoneName: 'shortOffset' }).formatToParts(new Date()).find(p => p.type === 'timeZoneName')?.value || ''; } catch { return ''; } };
            zoneOpts = ZONES.map((z, i) => [z, `${z.replace(/_/g, ' ')}${off(z) ? ` (${off(z)})` : ''}${i === 0 ? ' — your time zone' : ''}`]);
        }
        const list = zoneOpts.some(([z]) => z === cur) ? zoneOpts : [[cur, cur.replace(/_/g, ' ')], ...zoneOpts];
        return list.map(([z, l]) => `<option value="${esc(z)}" ${z === cur ? 'selected' : ''}>${esc(l)}</option>`).join('');
    };
    const sel = (key, label, cur, opts) => {
        if (!opts.some(([v]) => v === cur)) opts = [...opts, [cur, String(cur)]].sort((a, b) => a[0] - b[0]);
        return `<label>${label}<select data-c="${key}" data-num>${opts.map(([v, t]) => `<option value="${v}" ${v === cur ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select></label>`;
    };
    const NOTICE = [[0, 'Any time — no notice'], [1, '1 hour'], [2, '2 hours'], [3, '3 hours'], [4, '4 hours'], [6, '6 hours'], [12, '12 hours'], [24, '1 day'], [48, '2 days'], [72, '3 days'], [168, '1 week']];
    const HORIZON = [[7, '1 week'], [14, '2 weeks'], [30, '30 days'], [60, '60 days'], [90, '90 days'], [120, '4 months'], [180, '6 months']];
    const BUFFER = [[0, 'No gap'], [5, '5 minutes'], [10, '10 minutes'], [15, '15 minutes'], [20, '20 minutes'], [30, '30 minutes'], [45, '45 minutes'], [60, '1 hour'], [90, '1½ hours'], [120, '2 hours']];
    const STEPS = [[0, 'Back to back (the service’s length)'], [15, 'Every 15 minutes'], [30, 'Every 30 minutes'], [60, 'Every hour']];
    const SWATCHES = (window.OwlWizard?.PALETTES || [['Forest', '#16a34a'], ['Ocean', '#2563eb'], ['Sunset', '#e8590c'], ['Berry', '#be185d'], ['Royal', '#6d28d9'], ['Coffee', '#7c4a2d'], ['Teal', '#0d9488'], ['Midnight', '#1e1b49']]);
    const showDay = s => { try { return new Date(s + 'T12:00:00Z').toLocaleDateString(LOCALE, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }); } catch { return s; } };

    /* ------------------------------------------------ the editor */
    const timeIn = (d, r, e, v) => `<input type="time" step="300" data-h="${d}" data-r="${r}" data-e="${e}" value="${v === '24:00' ? '00:00' : esc(v)}" aria-label="${DAYS[d]} ${e ? 'closes' : 'opens'}${r ? ` (part ${r + 1})` : ''}">`;
    const openState = {};
    const draw = (openKey = '') => {
        $$('[data-form] details').forEach(d => (openState[d.dataset.sec] = d.open));
        if (openKey) openState[openKey] = true;
        const c = C(), n = c.services.length;
        $('[data-form]').innerHTML = `
        <details class="fb-settings" data-sec="biz" open><summary>Your business</summary>
            <div class="fb-2">
                <label>Business name<input data-c="name" maxlength="80" value="${esc(c.name)}" placeholder="e.g. Glow Hair Studio"></label>
                <label><span>Where <small>— address, or “Online / video call”</small></span><input data-c="location" maxlength="200" value="${esc(c.location)}" placeholder="12 Market Street, Leeds"></label>
                <label class="rb-wide"><span>Short intro <small>— optional</small></span><textarea data-c="intro" rows="2" maxlength="500" placeholder="Book your next appointment online — it only takes a minute.">${esc(c.intro)}</textarea></label>
            </div>
            <div class="bk-colour"><b>Colour</b><div class="bk-swatches" role="group" aria-label="Colours">${SWATCHES.map(([nm, col]) => `<button type="button" class="bk-swatch" data-swatch="${col}" style="--c:${col}" aria-label="${esc(nm)}" title="${esc(nm)}" aria-pressed="${c.color === col}"></button>`).join('')}</div>
                <label class="bk-colour__own"><span class="sr-only">Your own colour</span><input type="color" data-c="color" value="${esc(c.color)}"></label></div>
        </details>
        <details class="fb-settings" data-sec="svc" open><summary>Services <small>(${n})</small></summary>
            <div class="bk-svcs">${c.services.map((s, i) => `<div class="bk-svc" data-list="svc" data-i="${i}">
                <label class="bk-svc__name"><span>Service</span><input data-s="name" maxlength="80" value="${esc(s.name)}" placeholder="e.g. Haircut"></label>
                <label class="bk-svc__mins"><span>Minutes</span><input data-s="mins" type="number" min="5" max="480" step="5" inputmode="numeric" value="${s.mins}"></label>
                <label class="bk-svc__price"><span>Price <small>— optional</small></span><input data-s="price" maxlength="30" value="${esc(s.price)}" placeholder="e.g. ${esc(money(35))}"></label>
                <span class="fb-q__tools bk-svc__tools"><button type="button" data-move="-1" ${i ? '' : 'disabled'} aria-label="Move ${esc(s.name || 'this service')} up" title="Move up">${ic('arrow-up')}</button><button type="button" data-move="1" ${i < n - 1 ? '' : 'disabled'} aria-label="Move ${esc(s.name || 'this service')} down" title="Move down">${ic('arrow-down')}</button><button type="button" class="fb-del" data-del-svc ${n > 1 ? '' : 'disabled'} aria-label="Delete ${esc(s.name || 'this service')}" title="Delete">${ic('close')}</button></span>
                <label class="bk-svc__desc"><span>Short description <small>— optional</small></span><input data-s="desc" maxlength="200" value="${esc(s.desc)}" placeholder="What’s included"></label>
            </div>`).join('')}</div>
            ${n < MAX_SVC ? `<button type="button" class="btn btn--sm btn--ghost rb-add" data-add-svc>${ic('plus')} Add a service</button>` : '<p class="fb-hint">That’s the most services one page can have (12).</p>'}
        </details>
        <details class="fb-settings" data-sec="hours" open><summary>Opening hours</summary>
            <p class="fb-hint">When people can book. Add a second set of hours for a lunch break.</p>
            <div class="bk-hours">${c.hours.map((day, d) => `<div class="bk-day${day.length ? '' : ' is-off'}">
                <label class="fb-check bk-day__on"><input type="checkbox" data-day-on="${d}" ${day.length ? 'checked' : ''}> ${DAYS[d]}</label>
                <div class="bk-ranges">${day.length ? day.map((r, i) => `<span class="bk-range${mins(r[0]) >= mins(r[1]) ? ' is-bad' : ''}">${timeIn(d, i, 0, r[0])}<span aria-hidden="true">–</span>${timeIn(d, i, 1, r[1])}${day.length > 1 ? `<button type="button" class="bk-x" data-del-range="${d}" data-r="${i}" aria-label="Remove these hours" title="Remove">${ic('close')}</button>` : ''}</span>`).join('')
                    + (day.length < MAX_RANGES ? `<button type="button" class="rb-link bk-addrange" data-add-range="${d}">${ic('plus')} Add hours</button>` : '') : '<span class="bk-closed">Closed</span>'}</div>
            </div>`).join('')}</div>
            <button type="button" class="btn btn--sm btn--ghost bk-copy" data-copy-mon ${c.hours[0].length ? '' : 'disabled'}>${ic('copy')} Copy Monday to all weekdays</button>
        </details>
        <details class="fb-settings" data-sec="rules"><summary>Booking rules</summary>
            <div class="fb-2">
                <label class="rb-wide"><span>Time zone <small>— the times on your page are in this zone</small></span><select data-c="tz">${zoneOptions(c.tz)}</select></label>
                ${sel('notice', 'Book at least … ahead', c.notice, NOTICE)}
                ${sel('horizon', 'How far ahead people can book', c.horizon, HORIZON)}
                ${sel('buffer', 'Gap after each booking', c.buffer, BUFFER)}
                ${sel('step', 'Start times', c.step, STEPS)}
                <label class="fb-check rb-wide"><input type="checkbox" data-c="askPhone" ${c.askPhone ? 'checked' : ''}> Ask for a phone number</label>
                <label class="rb-wide"><span>Message after booking <small>— also in the confirmation email</small></span><textarea data-c="note" rows="2" maxlength="500" placeholder="e.g. Please arrive 5 minutes early.">${esc(c.note)}</textarea></label>
            </div>
        </details>
        <details class="fb-settings" data-sec="off" ${c.off.length ? '' : ''}><summary>Days off <small>(${c.off.length})</small></summary>
            <p class="fb-hint">Holidays and days you’re away — nobody can book them.</p>
            ${c.off.length ? `<ul class="bk-off">${c.off.map(d => `<li class="${d < OB.today(c.tz) ? 'is-past' : ''}">${esc(showDay(d))}<button type="button" class="bk-x" data-del-off="${d}" aria-label="Remove ${esc(showDay(d))}" title="Remove">${ic('close')}</button></li>`).join('')}</ul>` : ''}
            <div class="bk-addoff"><label><span class="sr-only">Day off</span><input type="date" data-off-in min="${OB.today(c.tz)}"></label><button type="button" class="btn btn--sm btn--ghost" data-add-off>${ic('plus')} Add day off</button></div>
        </details>`;
        $$('[data-form] details').forEach(d => { if (d.dataset.sec in openState) d.open = openState[d.dataset.sec]; });
    };

    /* ------------------------------------------------ preview: the real booking page, on the same rules, without bookings */
    let ctrl = null, ptimer = 0;
    const previewApi = {
        slots: (key, from) => Promise.resolve(OB.localSlots(outCfg(), key, from)),
        book: () => Promise.resolve({ ok: false, error: 'This is a preview — publish your page to take real bookings.' }),
    };
    const preview = (now = false) => {
        clearTimeout(ptimer);
        const go = () => { if (ctrl) ctrl.update(outCfg()); else ctrl = OB.mount($('[data-preview]'), outCfg(), previewApi, { preview: true }); };
        if (now) go(); else ptimer = setTimeout(go, 250);
        const p = problems(), h = hints();
        $('[data-tips]').innerHTML = [...p, ...h].map(x => `<li>${esc(x)}</li>`).join('');
        $('[data-tips-box]').hidden = !(p.length + h.length);
    };
    let saveTimer = 0;
    const changed = () => { D.dirty = true; clearTimeout(saveTimer); saveTimer = setTimeout(save, 250); syncPublish(); preview(); };
    addEventListener('pagehide', () => { clearTimeout(saveTimer); save(); });

    /* ------------------------------------------------ editing */
    root.addEventListener('input', e => {
        const el = e.target, c = C();
        if (el.dataset.c) {
            const k = el.dataset.c;
            if (el.type === 'checkbox') c[k] = el.checked;
            else if ('num' in el.dataset) c[k] = +el.value;
            else c[k] = el.value;
            if (k === 'color') $$('[data-swatch]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.swatch === el.value)));
            if (k === 'tz') draw();
            return changed();
        }
        if (el.dataset.s) {
            const s = c.services[+el.closest('[data-list]').dataset.i], k = el.dataset.s;
            if (k === 'mins') { if (el.value === '') return; s.mins = Math.min(480, Math.max(5, +el.value || 5)); } else s[k] = el.value;
            return changed();
        }
        if (el.dataset.h !== undefined) {
            const d = +el.dataset.h, r = +el.dataset.r, end = el.dataset.e === '1';
            if (!el.value) return;
            let v = el.value.slice(0, 5); if (end && v === '00:00') v = '24:00';
            c.hours[d][r][end ? 1 : 0] = v;
            el.closest('.bk-range')?.classList.toggle('is-bad', mins(c.hours[d][r][0]) >= mins(c.hours[d][r][1]));
            return changed();
        }
        if (el.dataset.dayOn !== undefined) {
            const d = +el.dataset.dayOn;
            c.hours[d] = el.checked ? (c.hours.find(x => x.length)?.map(r => [...r]) || [['09:00', '17:00']]) : [];
            draw(); $(`[data-day-on="${d}"]`)?.focus(); return changed();
        }
    });
    root.addEventListener('change', e => {
        const el = e.target;
        if (el.dataset.s === 'mins') { // tidy to whole 5 minutes once they leave the box
            const s = C().services[+el.closest('[data-list]').dataset.i];
            s.mins = Math.min(480, Math.max(5, Math.round((+el.value || s.mins) / 5) * 5)); el.value = s.mins; changed();
        }
    });
    root.addEventListener('click', async e => {
        const t = e.target.closest('button'); if (!t || !root.contains(t) || t.closest('[data-preview]')) return;
        const c = C(), ds = t.dataset, card = t.closest('[data-list]'), i = card ? +card.dataset.i : -1;
        if (ds.tab) return showTab(ds.tab);
        if (ds.swatch) { c.color = ds.swatch; const inp = $('[data-c="color"]'); if (inp) inp.value = c.color; $$('[data-swatch]').forEach(b => b.setAttribute('aria-pressed', String(b === t))); return changed(); }
        if ('addSvc' in ds) {
            if (c.services.length >= MAX_SVC) return;
            c.services.push({ key: newKey(c.services.map(s => s.key)), name: '', mins: 30, price: '', desc: '' });
            draw('svc'); changed(); $$('[data-list="svc"]').pop()?.querySelector('[data-s="name"]')?.focus(); return;
        }
        if (ds.move) { const j = i + +ds.move, L = c.services; [L[i], L[j]] = [L[j], L[i]]; draw(); changed(); const at = $(`[data-list="svc"][data-i="${j}"]`); (at?.querySelector(`[data-move="${ds.move}"]:not([disabled])`) || at?.querySelector('[data-move]:not([disabled])'))?.focus(); return; }
        if ('delSvc' in ds) {
            const s = c.services[i]; if (c.services.length < 2) return;
            if (s.name.trim() && !(await ask({ icon: 'warning', title: `Delete “${s.name}”?`, text: D.id ? 'People can’t book it any more once you save. Bookings already made stay.' : 'It’s taken off your booking page.', confirm: 'Delete', cancel: 'Keep it', danger: true }))) return;
            c.services.splice(i, 1); draw(); changed(); ($(`[data-list="svc"][data-i="${Math.min(i, c.services.length - 1)}"] [data-s="name"]`) || $('[data-add-svc]'))?.focus(); return;
        }
        if (ds.addRange !== undefined) {
            const d = +ds.addRange, day = c.hours[d]; if (day.length >= MAX_RANGES) return;
            const last = day[day.length - 1], from = Math.min(mins(last[1]) + 60, 1380);
            const hh = n => `${String(Math.floor(n / 60)).padStart(2, '0')}:${String(n % 60).padStart(2, '0')}`;
            day.push([hh(from), hh(Math.min(from + 120, 1440))]);
            draw(); changed(); $(`[data-h="${d}"][data-r="${day.length - 1}"][data-e="0"]`)?.focus(); return;
        }
        if (ds.delRange !== undefined) { const d = +ds.delRange; c.hours[d].splice(+ds.r, 1); draw(); changed(); $(`[data-add-range="${d}"]`)?.focus(); return; }
        if ('copyMon' in ds) {
            if (!c.hours[0].length) return;
            for (let d = 1; d < 5; d++) c.hours[d] = c.hours[0].map(r => [...r]);
            draw(); changed(); toast('Monday’s hours copied to Tuesday–Friday', 'success'); return;
        }
        if ('addOff' in ds) {
            const inp = $('[data-off-in]'), v = inp?.value || '';
            if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) { toast('Pick a date first.', 'error'); inp?.focus(); return; }
            if (c.off.includes(v)) { toast('That day is already in your list.'); return; }
            if (c.off.length >= MAX_OFF) { toast('You can add up to 100 days off. Remove some past ones first.', 'error'); return; }
            c.off = [...c.off, v].sort(); draw('off'); changed(); $('[data-off-in]')?.focus(); return;
        }
        if (ds.delOff) { c.off = c.off.filter(d => d !== ds.delOff); draw('off'); changed(); $('[data-off-in]')?.focus(); }
    });
    // Enter in the "day off" box adds it
    root.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.matches('[data-off-in]')) { e.preventDefault(); $('[data-add-off]').click(); } });

    /* ------------------------------------------------ tabs */
    const showTab = name => {
        $$('[data-tab]').forEach(b => b.setAttribute('aria-selected', b.dataset.tab === name ? 'true' : 'false'));
        $$('[data-panel]').forEach(p => (p.hidden = p.dataset.panel !== name));
        if (name === 'share') drawShare();
        if (name === 'bookings') drawBookings();
    };

    /* ------------------------------------------------ account, publishing, my booking pages */
    let pages = null;
    const api = (m, u, b) => Acc().api(m, u, b);
    const loadPages = async () => { pages = (await api('GET', '/builder/bookings')).pages || []; drawMine(); syncPublish(); return pages; };
    const drawMine = () => {
        const s = $('[data-mine]');
        s.hidden = !pages?.length;
        s.innerHTML = `<option value="">My booking pages (${pages?.length || 0})…</option>` + (pages || []).map(x => `<option value="${esc(x.id)}" ${x.id === D.id ? 'selected' : ''}>${esc(x.cfg?.name || 'Booking page')} · ${+x.upcoming || 0} upcoming</option>`).join('');
    };
    const mine = () => pages?.find(x => x.id === D.id);
    const syncPublish = () => {
        const b = $('[data-publish]');
        b.textContent = D.id ? (D.dirty ? 'Save changes' : 'Saved') : 'Publish booking page';
        b.disabled = !!D.id && !D.dirty;
        const x = mine();
        $('[data-status]').textContent = x?.disabled ? 'This booking page has been switched off and can’t take bookings. Contact us if you think that’s a mistake.'
            : D.id ? (D.dirty ? 'Unsaved changes — your live page still shows the last saved version.' : 'Live — people can book now.')
            : 'Not published yet — your work is kept in this browser.';
        $('[data-bk-count]').textContent = x && +x.upcoming ? ` (${+x.upcoming})` : '';
    };
    let publishing = false;
    const publish = async () => {
        if (publishing) return;
        const p = problems();
        if (p.length) { await ask({ icon: 'info', title: 'Nearly there', text: p.join(' '), confirm: 'OK', cancel: false }); showTab('build'); return; }
        if (!Acc()) return toast('Please reload the page and try again.', 'error');
        if (!Acc().signedIn() && !(await Acc().signIn('Publish your booking page and see your bookings on any device. Booking emails come to this address.'))) return;
        const b = $('[data-publish]'); publishing = true; b.disabled = true; b.textContent = 'Saving…';
        try {
            let r;
            try { r = await api('POST', '/builder/bookings', { id: D.id || undefined, cfg: outCfg() }); }
            catch (ex) { if (ex.status === 404 && D.id) { D.id = null; r = await api('POST', '/builder/bookings', { cfg: outCfg() }); } else throw ex; } // deleted, or another account's page
            const first = !D.id;
            D.id = r.page.id; D.url = r.page.url || ''; D.dirty = false; save();
            await loadPages().catch(() => {});
            toast(first ? 'Your booking page is live! Share the link to start taking bookings.' : 'Changes saved — your page is up to date.', 'success');
            track(first ? 'booking_publish' : 'booking_save', { services: C().services.length });
            if (first) showTab('share');
        } catch (ex) {
            toast(ex.message, 'error');
            if (ex.status === 401 && await Acc().signIn('Your sign-in ran out. Sign in again to publish.')) { publishing = false; return publish(); }
        } finally { publishing = false; syncPublish(); }
    };
    $('[data-publish]').addEventListener('click', publish);
    $('[data-new]').addEventListener('click', async () => {
        if (D.dirty && !isExample() && !(await ask({ icon: 'question', title: 'Start a new booking page?', text: D.id ? 'Unsaved changes to this one will be lost. Your live page stays as it is.' : 'This one isn’t published — it will be replaced.', confirm: 'Start new page', cancel: 'Cancel' }))) return;
        D = { id: null, url: '', cfg: clean(example()), dirty: false }; save(); draw(); drawMine(); syncPublish(); preview(true); showTab('build');
    });
    $('[data-mine]').addEventListener('change', async e => {
        const x = pages?.find(y => y.id === e.target.value); if (!x) return;
        if (D.dirty && !isExample() && !(await ask({ icon: 'question', title: 'Open this booking page?', text: 'Unsaved changes to the one you’re editing will be lost.', confirm: 'Open it', cancel: 'Cancel' }))) { e.target.value = D.id || ''; return; }
        D = { id: x.id, url: x.url || '', cfg: clean(x.cfg), dirty: false }; save(); draw(); syncPublish(); preview(true); drawMine();
        const open = $('[data-panel]:not([hidden])')?.dataset.panel || 'build'; showTab(open);
    });

    /* ------------------------------------------------ share */
    const pageUrl = () => mine()?.url || D.url || `${location.origin}/book/${D.id}`;
    const fileName = ext => `booking-${(C().name || 'page').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 40) || 'page'}.${ext}`;
    const saveFile = (blob, name) => { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 4000); };
    const drawShare = () => {
        const box = $('[data-share]');
        if (!D.id) {
            box.innerHTML = `<div class="fb-empty"><b>Publish your page to share it</b><p>You get a link to send to clients, a QR code for your counter or flyers, and a code to put the booking page on your own website.</p><button type="button" class="btn btn--glow" data-publish-now>${ic('rocket')} Publish booking page</button></div>`;
            return;
        }
        const url = pageUrl(), embed = `<iframe src="${url}?embed=1" style="width:100%;min-height:720px;border:0" title="Book with ${esc(C().name || 'us')}" loading="lazy"></iframe>`;
        const qr = window.OwlQR ? window.OwlQR.svg(url, { margin: 2 }) : '';
        box.innerHTML = `<div class="bk-share">
            <div class="bk-share__main">
                <label>Your booking page<div class="fb-copyrow"><input readonly value="${esc(url)}"><button type="button" class="btn btn--sm" data-copy="${esc(url)}">Copy</button><a class="btn btn--sm btn--ghost" href="${esc(url)}" target="_blank" rel="noopener">Open ${ic('external')}</a></div></label>
                <p class="fb-hint">Send it on WhatsApp, put it in your Instagram or Google Business profile, or add it to your email signature.</p>
                <label>Put it on your website<div class="fb-copyrow"><textarea readonly rows="3">${esc(embed)}</textarea><button type="button" class="btn btn--sm" data-copy="${esc(embed)}">Copy</button></div></label>
                <p class="fb-hint">Paste this code into WordPress (Custom HTML block), Wix (Embed code), Squarespace or Shopify.</p>
                <p class="bk-tipline">${ic('bulb')} <span><b>Put it on your Owlvix website:</b> paste this link into any button — for example a “Book now” button.</span></p>
                <p class="fb-hint">You and your client both get an email for every booking. See them all in the Bookings tab.</p>
                <button type="button" class="fb-danger" data-delete>Delete this booking page</button>
            </div>
            ${qr ? `<div class="bk-share__qr"><div class="bk-qr" role="img" aria-label="QR code for your booking page">${qr}</div><p class="fb-hint">Print it for your counter, window or flyers — people scan it to book.</p><span class="bk-qr__btns"><button type="button" class="btn btn--sm btn--ghost" data-qr="png">${ic('download')} PNG</button><button type="button" class="btn btn--sm btn--ghost" data-qr="svg">SVG</button></span></div>` : ''}
        </div>`;
    };
    $('[data-share]').addEventListener('click', async e => {
        const t = e.target.closest('button'); if (!t) return;
        if ('publishNow' in t.dataset) return publish();
        if (t.dataset.copy) {
            try { await navigator.clipboard.writeText(t.dataset.copy); } catch { const f = t.previousElementSibling; f?.select(); document.execCommand('copy'); }
            t.textContent = 'Copied!'; setTimeout(() => (t.textContent = 'Copy'), 2000); track('booking_copy'); return;
        }
        if (t.dataset.qr) {
            const url = pageUrl();
            if (t.dataset.qr === 'svg') return saveFile(new Blob([window.OwlQR.svg(url, { margin: 4, size: 1024 })], { type: 'image/svg+xml' }), fileName('svg'));
            try { const cv = window.OwlQR.canvas(url, 24); cv.toBlob(b => b && saveFile(b, fileName('png')), 'image/png'); }
            catch { toast('Your browser couldn’t make the PNG — download the SVG instead.', 'error'); }
            return;
        }
        if ('delete' in t.dataset) {
            if (!(await ask({ icon: 'warning', title: 'Delete this booking page?', text: 'Its link stops working and its bookings are deleted. People who already booked are not told — cancel their bookings first if you need to. This can’t be undone.', confirm: 'Delete page', cancel: 'Keep it', danger: true }))) return;
            try {
                await api('POST', `/builder/bookings/${D.id}/delete`);
                D.id = null; D.url = ''; D.dirty = true; save(); await loadPages().catch(() => {}); syncPublish(); drawShare();
                toast('Booking page deleted. Your settings are still here if you want to publish it again.');
            } catch (ex) { toast(ex.message, 'error'); }
        }
    });

    /* ------------------------------------------------ bookings */
    let list = null;
    const fmtDay = s => { try { return new Date(s + 'T12:00:00Z').toLocaleDateString(LOCALE, { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' }); } catch { return s; } };
    const fmtTime = t => { try { const [h, m] = t.split(':').map(Number); return new Date(Date.UTC(2000, 0, 1, h, m)).toLocaleTimeString(LOCALE, { hour: 'numeric', minute: '2-digit', timeZone: 'UTC' }); } catch { return t; } };
    const row = x => {
        const off = x.status === 'cancelled';
        return `<li class="bk-bk${off ? ' is-cancelled' : ''}">
            <span class="bk-bk__time">${esc(fmtTime(x.time))}<small>to ${esc(fmtTime(x.end))}</small></span>
            <span class="bk-bk__who"><b>${esc(x.name)}</b> <span class="bk-bk__svc">${esc(x.service)}${x.mins ? ` · ${+x.mins} min` : ''}</span>
                <small><a href="mailto:${esc(x.email)}">${esc(x.email)}</a>${x.phone ? ` · <a href="tel:${esc(String(x.phone).replace(/[^\d+]/g, ''))}">${esc(x.phone)}</a>` : ''}</small>
                ${x.note ? `<small class="bk-bk__note">“${esc(x.note)}”</small>` : ''}</span>
            <span class="bk-bk__act">${off ? '<em>Cancelled</em>' : `<button type="button" class="btn btn--sm btn--ghost" data-cancel="${esc(x.id)}">Cancel</button>`}</span></li>`;
    };
    const group = arr => {
        const by = {}; arr.forEach(x => (by[x.date] = by[x.date] || []).push(x));
        return Object.keys(by).map(d => `<section class="bk-group"><h3>${esc(fmtDay(d))}</h3><ul class="bk-bks">${by[d].sort((a, b) => a.time.localeCompare(b.time)).map(row).join('')}</ul></section>`).join('');
    };
    const drawBookings = async () => {
        const box = $('[data-bookings]');
        if (!D.id) { box.innerHTML = `<div class="fb-empty"><b>No bookings yet</b><p>Publish your booking page and share the link — every booking shows up here and is emailed to you.</p><button type="button" class="btn btn--glow" data-publish-now>${ic('rocket')} Publish booking page</button></div>`; return; }
        if (!Acc()?.signedIn()) { box.innerHTML = '<div class="fb-empty"><b>Sign in to see your bookings</b><p>Use the email you published this page with.</p><button type="button" class="btn btn--glow" data-signin-now>Sign in with email</button></div>'; return; }
        if (!list) box.innerHTML = '<p class="fb-hint bk-loading">Loading bookings…</p>';
        try {
            const r = await api('GET', `/builder/bookings/${D.id}/list`);
            list = r.bookings || [];
            const today = OB.today(C().tz);
            const up = list.filter(x => x.date >= today), past = list.filter(x => x.date < today).sort((a, b) => (b.date + b.time).localeCompare(a.date + a.time));
            const live = up.filter(x => x.status !== 'cancelled').length;
            const m = mine(); if (m && m.upcoming !== live) { m.upcoming = live; drawMine(); syncPublish(); } // keep the switcher's count in step
            box.innerHTML = `<div class="fb-resp-head"><b>${live ? `${live} upcoming booking${live === 1 ? '' : 's'}` : 'No upcoming bookings'}</b><span class="bk-head-btns"><button type="button" class="btn btn--sm btn--ghost" data-refresh>${ic('refresh')} Refresh</button> <button type="button" class="btn btn--sm" data-csv ${r.total ? '' : 'disabled'}>${ic('download')} Download CSV</button></span></div>
                ${up.length ? group(up) : `<div class="fb-empty bk-none"><b>Nothing booked yet</b><p>Share your link — new bookings appear here and are emailed to you.</p><button type="button" class="btn btn--sm btn--ghost" data-tab="share">${ic('link')} Share my page</button></div>`}
                ${past.length ? `<details class="bk-past"><summary>Past bookings (${past.length})</summary>${group(past)}</details>` : ''}
                ${r.total > list.length ? `<p class="fb-hint">Showing the latest ${list.length} of ${r.total}. Download the CSV for all of them.</p>` : ''}`;
        } catch (ex) {
            box.innerHTML = `<div class="fb-empty"><b>We couldn’t load your bookings</b><p>${esc(ex.message)}</p><button type="button" class="btn btn--sm btn--ghost" data-refresh>${ic('refresh')} Try again</button></div>`;
        }
    };
    $('[data-bookings]').addEventListener('click', async e => {
        const t = e.target.closest('button'); if (!t) return;
        if ('publishNow' in t.dataset) return publish();
        if ('signinNow' in t.dataset) { if (await Acc()?.signIn('See your bookings and manage your booking page.')) { await loadPages().catch(() => {}); drawBookings(); } return; }
        if ('refresh' in t.dataset) { t.disabled = true; await drawBookings(); loadPages().catch(() => {}); return; }
        if ('csv' in t.dataset) {
            let token = ''; try { token = localStorage.getItem('owlvix-builder-token') || ''; } catch {}
            try {
                const res = await fetch(`/builder/bookings/${D.id}/list?format=csv`, { headers: { 'X-Builder-Token': token, Accept: 'text/csv' } });
                if (!res.ok) throw new Error();
                saveFile(await res.blob(), fileName('csv').replace('booking-', 'bookings-'));
                track('booking_csv');
            } catch { toast('Couldn’t download the file. Please try again.', 'error'); }
            return;
        }
        if (t.dataset.cancel) {
            const x = list?.find(y => String(y.id) === t.dataset.cancel); if (!x) return;
            if (!(await ask({ icon: 'warning', title: `Cancel ${x.name}’s booking?`, text: `${x.service} on ${fmtDay(x.date)} at ${fmtTime(x.time)}. We’ll email ${x.name} to say it’s cancelled, and the time becomes free again.`, confirm: 'Cancel booking', cancel: 'Keep it', danger: true }))) return;
            t.disabled = true;
            try { await api('POST', `/builder/bookings/${D.id}/cancel/${encodeURIComponent(x.id)}`); toast(`Cancelled — ${x.name} has been emailed.`, 'success'); track('booking_cancel'); await drawBookings(); loadPages().catch(() => {}); }
            catch (ex) { t.disabled = false; toast(ex.message, 'error'); }
        }
    });

    /* ------------------------------------------------ quick setup wizard (public/js/builder-wizard.js) */
    const WZ = window.OwlWizard;
    if (WZ) {
        let wctrl = null, wt = 0, picked = null;
        const upd = () => { D.dirty = true; save(); clearTimeout(wt); wt = setTimeout(() => wctrl?.update(outCfg()), 150); };
        const sameHours = (a, b) => JSON.stringify(a) === JSON.stringify(b);
        /** The quick setup offers three service rows; empty ones go when it closes (one always stays). */
        const tidy = () => { const c = C(), named = c.services.filter(s => s.name.trim()); c.services = named.length ? named : c.services.slice(0, 1); save(); };
        const svcRow = i => {
            const c = C(), s = c.services[i] || (c.services[i] = { key: newKey(c.services.map(x => x.key)), name: '', mins: 30, price: '', desc: '' });
            return `<div class="wz-ib-row bk-wz-row"><label class="wz-field">${i ? '' : 'Service'}<input data-wz-svc="${i}" data-k="name" maxlength="80" value="${esc(s.name)}" placeholder="${esc(['e.g. Haircut', 'e.g. Consultation', 'e.g. Follow-up'][i])}" aria-label="Service ${i + 1}"></label><label class="wz-field">${i ? '' : 'Minutes'}<input data-wz-svc="${i}" data-k="mins" type="number" min="5" max="480" step="5" inputmode="numeric" value="${s.mins}" aria-label="Service ${i + 1} minutes"></label><label class="wz-field">${i ? '' : 'Price'}<input data-wz-svc="${i}" data-k="price" maxlength="30" value="${esc(s.price)}" placeholder="${esc(money(30))}" aria-label="Service ${i + 1} price"></label></div>`;
        };
        const steps = [
            {
                title: 'What kind of business is it?', sub: 'We’ll start you with the services and hours it usually needs — you can change everything.',
                draw: () => WZ.cards(Object.entries(TYPES).map(([k, [n, em, s]]) => [k, em, n, s]), picked),
                click: (e, el, box) => {
                    if (!el.dataset.wzPick) return;
                    picked = el.dataset.wzPick; WZ.press(box, '[data-wz-pick]', el);
                    const p = TYPES[picked][3](), c = C(), ex = example();
                    // the example's words aren't theirs: swap them for the type's; anything they typed stays
                    Object.assign(c, { services: p.services, hours: p.hours, step: p.step ?? 0, buffer: p.buffer ?? 0, notice: p.notice ?? 2 });
                    if (!c.intro.trim() || c.intro === ex.intro || Object.values(TYPES).some(x => x[3]().intro === c.intro)) c.intro = p.intro;
                    if (c.color === ex.color || Object.values(TYPES).some(x => x[3]().color === c.color)) c.color = p.color;
                    if (c.location === ex.location || !c.location.trim() || c.location === 'Online / video call') c.location = p.location || '';
                    if (c.name === ex.name) c.name = '';
                    D.cfg = clean(c); upd();
                },
                ok: () => (picked ? '' : 'Pick the closest one — you can change everything later.'),
            },
            {
                title: 'Your business', sub: 'Shown at the top of your booking page.',
                draw: () => `<label class="wz-field">Business name<input data-wz="name" maxlength="80" value="${esc(C().name)}" placeholder="e.g. Glow Hair Studio"></label>
                    <label class="wz-field">Short intro <small>— optional</small><textarea data-wz="intro" rows="2" maxlength="500">${esc(C().intro)}</textarea></label>
                    <label class="wz-field">Where <small>— address, or “Online / video call”</small><input data-wz="location" maxlength="200" value="${esc(C().location)}" placeholder="12 Market Street, Leeds"></label>`,
                input: (e, el) => { const k = el.dataset.wz; if (!k) return; C()[k] = el.value; upd(); },
                ok: () => (C().name.trim() ? '' : 'Please type your business name.'),
            },
            {
                title: 'What can people book?', sub: 'Up to three here, with how long each takes — add more in the editor.',
                draw: () => svcRow(0) + svcRow(1) + svcRow(2),
                input: (e, el) => {
                    if (el.dataset.wzSvc === undefined) return;
                    const s = C().services[+el.dataset.wzSvc];
                    if (el.dataset.k === 'mins') { if (el.value !== '') s.mins = Math.min(480, Math.max(5, Math.round((+el.value || 5) / 5) * 5)); } else s[el.dataset.k] = el.value;
                    upd();
                },
                ok: () => (C().services.some(s => s.name.trim()) ? '' : 'Please add at least one service.'),
                leave: () => tidy(),
            },
            {
                title: 'Opening hours and colour', sub: 'When people can book — fine-tune each day, breaks and days off in the editor.',
                draw: () => `<div class="wz-look"><b>Hours</b><div class="wz-chips">${Object.entries(HOURS).map(([k, [l, f]]) => `<button type="button" data-wz-hours="${k}" aria-pressed="${sameHours(C().hours, f())}">${esc(l)}</button>`).join('')}</div></div>`
                    + `<p class="wz__sub bk-wz-hours">${esc(hoursText(C().hours))}</p>` + WZ.palette(C().color, 'Or your own colour'),
                click: (e, el, box) => {
                    if (el.dataset.wzHours) { C().hours = HOURS[el.dataset.wzHours][1](); WZ.press(box, '[data-wz-hours]', el); const p = box.querySelector('.bk-wz-hours'); if (p) p.textContent = hoursText(C().hours); }
                    else if (el.dataset.pal) { C().color = el.dataset.pal; WZ.press(box, '[data-pal]', el); box.querySelector('[data-wz-color]').value = el.dataset.pal; }
                    else return;
                    upd();
                },
                input: (e, el, box) => { if (!el.matches('[data-wz-color]')) return; C().color = el.value; box.querySelectorAll('[data-pal]').forEach(x => x.setAttribute('aria-pressed', 'false')); upd(); },
            },
        ];
        WZ.mount({
            root, banner: document.querySelector('[data-wz-banner]'), offKey: 'owlvix-wz-booking-off',
            say: ['Hoo! Want clients to book online? Let’s set it up — about 2 minutes.', 'Your services, your hours — clients pick a free time.', 'You get an email for every booking. Ready?'],
            hasSaved: () => !!D.id,
            quickSlot: root.querySelector('.fb-top__actions'),
            onRestart: () => { picked = null; },
            steps,
            preview: {
                mount: side => { side.innerHTML = '<p class="wz-prev__label">Live preview — try it</p><div class="bk-wz-prev wz-prev"><div class="bk-stage" data-wz-bk></div></div>'; wctrl = OB.mount(side.querySelector('[data-wz-bk]'), outCfg(), previewApi, { preview: true }); },
                unmount: () => { wctrl?.destroy(); wctrl = null; tidy(); draw(); syncPublish(); preview(true); },
                refresh: () => wctrl?.update(outCfg()),
            },
            building: 'Setting up your booking page…',
            tasks: ['Adding your services', 'Opening your hours', 'Painting your colours', 'Getting it ready for bookings'],
            done: {
                title: 'Your booking page is ready',
                sub: 'Publish it to get your link, a QR code and a code for your website. You get an email for every booking.',
                actions: [
                    { label: 'Publish my booking page', icon: 'rocket', cls: 'btn btn--glow', then: 'publish', act: (close, o) => { close(); setTimeout(publish, o?.replay ? 0 : 350); } },
                    { label: 'Keep editing', icon: 'pen', act: close => close() },
                ],
            },
            editorUrl: root.dataset.editorUrl || '', noun: 'booking page',
            hasWork: () => !!D.id || !isExample(),
            beforeLeave: () => { tidy(); try { localStorage.setItem(STORE, JSON.stringify(D)); return true; } catch { return false; } },
        });
    }
    /** "Mon–Fri 09:00–17:00 · Sat 10:00–14:00" (for the quick setup). */
    function hoursText(h) {
        const S = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], out = [];
        let i = 0;
        while (i < 7) {
            let j = i; const k = JSON.stringify(h[i]);
            while (j + 1 < 7 && JSON.stringify(h[j + 1]) === k) j++;
            if (h[i].length) out.push(`${S[i]}${j > i ? '–' + S[j] : ''} ${h[i].map(r => `${r[0]}–${r[1]}`).join(', ')}`);
            i = j + 1;
        }
        return out.length ? out.join(' · ') : 'Closed every day';
    }

    /* ------------------------------------------------ start */
    draw(); syncPublish(); preview(true);
    if (Acc()?.signedIn()) loadPages().catch(() => {});
})();
