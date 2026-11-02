/* Owlvix booking page (/book/{id}, resources/views/builder/booking.blade.php) and the booking builder's live preview.
   Standalone: no site.js, no icons.js, no sweet dialogs (the page's CSP allows only this script). Brings its own CSS.

     OwlBooking.mount(el, cfg, api, opts?) → { update(cfg), destroy() }
        api  = { slots(serviceKey, fromDate) → Promise<{ days:[{date, slots:['09:00', …]}], more }>,
                 book(payload) → Promise<{ ok, booking? , error?, taken?, field? }> }
        opts = { preview: true }   (in the builder: no <h1>, no page-level focus moves on the first draw)
     OwlBooking.localSlots(cfg, serviceKey, fromDate, now?) → { ok, tz, days, more }   (same rules as the server, no bookings)
     OwlBooking.today(tz, now?) → 'YYYY-MM-DD' in that time zone

   The business's time zone (cfg.tz) is the truth: "today", "now + notice" and every time shown are its wall clock.
   On the public page it mounts itself on #booking-root, reading { cfg, slots, book } from <script id="booking-cfg">. */
(() => {
    /* ------------------------------------------------ small helpers */
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    // a few of Owlvix's icons (resources/icons/icons.json), inline: this page doesn't load icons.js
    const ICONS = {
        clock: '<circle class="d" cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        pin: '<path class="d" d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        calendar: '<rect class="d" x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 10h18M8 3v4M16 3v4M8 14h.01M12 14h.01M16 14h.01M8 17.5h.01M12 17.5h.01"/>',
        tick: '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        globe: '<circle class="d" cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>',
        'arrow-left': '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'arrow-right': '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'chevron-right': '<path d="M9 6l6 6-6 6"/>',
        warning: '<path class="d" d="M12 3.5L2.5 20h19z"/><path d="M12 10v4.5M12 17.5h.01"/>',
        info: '<circle class="d" cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/>',
        download: '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
        external: '<path d="M7 17L17 7M9 7h8v8"/>',
        refresh: '<path d="M20 11a8 8 0 0 0-14.3-4.6L4 8M4 4v4h4M4 13a8 8 0 0 0 14.3 4.6L20 16m0 4v-4h-4"/><circle class="d" cx="12" cy="12" r="3"/>',
        tag: '<path class="d" d="M3 12.2V4a1 1 0 0 1 1-1h8.2a1 1 0 0 1 .7.3l8 8a1 1 0 0 1 0 1.4l-8.2 8.2a1 1 0 0 1-1.4 0l-8-8a1 1 0 0 1-.3-.7z"/><path d="M7.5 7.5h.01"/>',
        note: '<path class="d" d="M5 3h10l4 4v14H5z"/><path d="M15 3v4h4M8.5 12h7M8.5 16h5"/>',
    };
    const ic = n => `<svg class="obk-i" viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${(ICONS[n] || '').replace(/ class="d"/g, ' fill="currentColor" fill-opacity=".16"')}</svg>`;

    /* ------------------------------------------------ dates: 'YYYY-MM-DD' strings, worked out in UTC so no local zone gets in the way */
    const D = s => new Date(s + 'T00:00:00Z');
    const ymd = d => d.toISOString().slice(0, 10);
    const addDays = (s, n) => { const d = D(s); d.setUTCDate(d.getUTCDate() + n); return ymd(d); };
    const isoDow = s => ((D(s).getUTCDay() + 6) % 7) + 1; // Mon = 1 … Sun = 7
    const mondayOf = s => addDays(s, 1 - isoDow(s));
    const dayDiff = (a, b) => Math.round((D(b) - D(a)) / 864e5);
    const mins = t => { const m = /^(\d{1,2}):(\d{2})$/.exec(t || ''); return m ? +m[1] * 60 + +m[2] : NaN; };
    const hhmm = n => `${String(Math.floor(n / 60)).padStart(2, '0')}:${String(n % 60).padStart(2, '0')}`;
    const okDate = s => /^\d{4}-\d{2}-\d{2}$/.test(s || '') && !isNaN(D(s));

    const zoneFmt = {};
    const validTz = tz => { try { new Intl.DateTimeFormat('en', { timeZone: tz }); return true; } catch { return false; } };
    /** The wall clock in a time zone: { date: 'YYYY-MM-DD', min: minutes since midnight }. */
    const wall = (tz, now = new Date()) => {
        const z = validTz(tz) ? tz : 'UTC';
        const f = zoneFmt[z] || (zoneFmt[z] = new Intl.DateTimeFormat('en-GB', { timeZone: z, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }));
        const p = {}; f.formatToParts(now).forEach(x => (p[x.type] = x.value));
        const h = +p.hour % 24;
        return { date: `${p.year}-${p.month}-${p.day}`, min: h * 60 + +p.minute };
    };
    const today = (tz, now) => wall(tz, now).date;
    /** The moment a wall-clock time in a time zone happens (for the calendar file and "your time"). */
    const instant = (date, time, tz) => {
        const t = Date.UTC(+date.slice(0, 4), +date.slice(5, 7) - 1, +date.slice(8, 10)) + mins(time) * 6e4;
        const off = ts => { const w = wall(tz, new Date(ts)); return (Date.UTC(+w.date.slice(0, 4), +w.date.slice(5, 7) - 1, +w.date.slice(8, 10)) + w.min * 6e4) - Math.floor(ts / 6e4) * 6e4; };
        let g = t - off(t); g = t - off(g);
        return new Date(g);
    };

    /* ------------------------------------------------ the slot rules (the server is the authority; this copy has no bookings) */
    const serviceOf = (cfg, key) => (cfg.services || []).find(s => s.key === key) || null;
    const localSlots = (cfg, serviceKey, fromDate, now = new Date()) => {
        const tz = validTz(cfg.tz) ? cfg.tz : 'UTC', w = wall(tz, now), s = serviceOf(cfg, serviceKey);
        const first = w.date, last = addDays(first, Math.max(1, Math.min(180, +cfg.horizon || 60)));
        const from = okDate(fromDate) ? fromDate : first;
        const len = Math.max(5, +s?.mins || 0), step = [15, 30, 60].includes(+cfg.step) ? +cfg.step : len;
        const earliest = w.min + Math.max(0, +cfg.notice || 0) * 60; // minutes after today's midnight
        const off = new Set(Array.isArray(cfg.off) ? cfg.off : []);
        const days = [];
        for (let i = 0; i < 7; i++) {
            const date = addDays(from, i), slots = [];
            if (s && date >= first && date <= last && !off.has(date)) {
                const base = dayDiff(first, date) * 1440;
                ((cfg.hours || [])[isoDow(date) - 1] || []).forEach(r => {
                    const a = mins(r?.[0]), b = r?.[1] === '24:00' ? 1440 : mins(r?.[1]);
                    if (!(a < b)) return;
                    for (let t = a; t + len <= b; t += step) if (base + t >= earliest) slots.push(hhmm(t));
                });
            }
            days.push({ date, slots: [...new Set(slots)].sort() });
        }
        return { ok: true, tz, days, more: addDays(from, 7) <= last };
    };

    /* ------------------------------------------------ colours: readable text on the business colour */
    const hexOk = c => /^#[0-9a-f]{6}$/i.test(c || '');
    const lum = c => { const v = [1, 3, 5].map(i => parseInt(c.slice(i, i + 2), 16) / 255).map(x => (x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4)); return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2]; };
    const onColor = c => ((lum(c) + 0.05) / 0.05 > 1.05 / (lum(c) + 0.05) ? '#0b1220' : '#ffffff');

    /* ------------------------------------------------ styles (injected once; the CSP allows inline styles) */
    const CSS = `
.obk{--bk-card:#fff;--bk-text:#0f172a;--bk-muted:#5b6b80;--bk-line:#e2e8f0;--bk-field:#f8fafc;--bk-err:#b91c1c;--bk-ink:color-mix(in srgb,var(--bk-c) 74%,var(--bk-text));--bk-soft:color-mix(in srgb,var(--bk-c) 11%,var(--bk-card));
 box-sizing:border-box;max-width:680px;margin:0 auto;background:var(--bk-card);color:var(--bk-text);border:1px solid var(--bk-line);border-top:6px solid var(--bk-c);border-radius:18px;padding:26px 26px 24px;font:16px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;box-shadow:0 12px 40px rgba(15,23,42,.07);text-align:left;overflow-wrap:anywhere}
@media (prefers-color-scheme:dark){.obk{--bk-card:#0f1626;--bk-text:#e2e8f0;--bk-muted:#94a3b8;--bk-line:#1f2a3d;--bk-field:#0b1220;--bk-err:#fca5a5;box-shadow:none}}
.obk :is(h1,h2,h3,p,dl,dd){color:inherit;text-align:inherit;font-family:inherit;letter-spacing:normal;text-transform:none;max-width:none}
.obk *,.obk *::before,.obk *::after{box-sizing:border-box}
.obk .obk-sr{position:absolute!important;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}
.obk .obk-i{width:1.1em;height:1.1em;vertical-align:-.18em;flex:none;display:inline-block}
.obk button,.obk input,.obk textarea{font:inherit;color:inherit}
.obk button{cursor:pointer}
.obk :focus-visible{outline:3px solid color-mix(in srgb,var(--bk-c) 60%,var(--bk-text));outline-offset:2px}
.obk .obk-head{display:flex;gap:14px;align-items:flex-start;margin-bottom:18px}
.obk .obk-mono{flex:none;width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:var(--bk-c);color:var(--bk-on);font-weight:800;font-size:1.3rem;letter-spacing:-.02em}
.obk .obk-name{color:var(--bk-text);margin:0;font-size:1.45rem;line-height:1.2;font-weight:800;letter-spacing:-.01em}
.obk .obk-intro{margin:6px 0 0;color:var(--bk-muted);white-space:pre-line}
.obk .obk-loc{margin:6px 0 0;color:var(--bk-muted);font-size:.92rem;display:flex;gap:6px;align-items:flex-start}
.obk .obk-loc .obk-i{margin-top:.2em;color:var(--bk-ink)}
.obk .obk-steps{list-style:none;display:flex;gap:6px;margin:0 0 20px;padding:0;counter-reset:s}
.obk .obk-steps li{flex:1;min-width:0;font-size:.8rem;font-weight:600;color:var(--bk-muted);padding-top:9px;border-top:4px solid var(--bk-line);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.obk .obk-steps li.is-on,.obk .obk-steps li.is-done{border-top-color:var(--bk-c);color:var(--bk-text)}
.obk .obk-h{color:var(--bk-text);margin:0 0 4px;font-size:1.2rem;line-height:1.3;outline:none}
.obk .obk-sub{margin:0 0 16px;color:var(--bk-muted);font-size:.95rem}
.obk .obk-list{display:grid;gap:10px}
.obk .obk-svc{display:flex;align-items:center;gap:14px;width:100%;min-height:68px;padding:14px 16px;border:1px solid var(--bk-line);border-radius:14px;background:var(--bk-field);text-align:left;transition:border-color .15s,background .15s}
.obk .obk-svc:hover{border-color:var(--bk-c);background:var(--bk-soft)}
.obk .obk-svc__main{flex:1;min-width:0;display:grid;gap:2px}
.obk .obk-svc__main b{font-size:1.02rem}
.obk .obk-svc__main small{color:var(--bk-muted);font-size:.88rem;line-height:1.4}
.obk .obk-svc__meta{flex:none;display:grid;justify-items:end;gap:2px;font-size:.88rem;color:var(--bk-muted);text-align:right}
.obk .obk-svc__meta b{color:var(--bk-text);font-size:.98rem}
.obk .obk-svc>.obk-i{color:var(--bk-muted)}
.obk .obk-picked{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:6px 12px;padding:12px 14px;margin-bottom:16px;border-radius:12px;background:var(--bk-soft);font-size:.94rem}
.obk .obk-picked b{font-weight:700}
.obk .obk-picked span{color:var(--bk-muted)}
.obk .obk-link{white-space:nowrap;background:none;border:0;padding:6px 2px;color:var(--bk-ink);font-weight:700;text-decoration:underline;text-underline-offset:3px;min-height:36px}
.obk .obk-week{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
.obk .obk-week b{font-size:1rem;text-align:center}
.obk .obk-nav{display:grid;place-items:center;width:44px;height:44px;border-radius:12px;border:1px solid var(--bk-line);background:var(--bk-card);font-size:1.1rem}
.obk .obk-nav:disabled{opacity:.35;cursor:default}
.obk .obk-nav:not(:disabled):hover{border-color:var(--bk-c);color:var(--bk-ink)}
.obk .obk-days{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px;margin-bottom:18px}
.obk .obk-day{display:grid;justify-items:center;gap:1px;min-height:64px;padding:8px 2px 6px;border:1px solid var(--bk-line);border-radius:12px;background:var(--bk-field);line-height:1.2}
.obk .obk-day small{font-size:.72rem;font-weight:600;color:var(--bk-muted);text-transform:uppercase;letter-spacing:.03em}
.obk .obk-day b{font-size:1.1rem}
.obk .obk-day i{display:block;justify-self:center;flex:none;width:6px;height:6px;border-radius:50%;background:var(--bk-c);margin-top:3px}
.obk .obk-day:disabled{opacity:.42;cursor:default;background:transparent;border-style:dashed}
.obk .obk-day:disabled i{visibility:hidden}
.obk .obk-day:not(:disabled):hover{border-color:var(--bk-c)}
.obk .obk-day[aria-pressed="true"]{background:var(--bk-c);border-color:var(--bk-c);color:var(--bk-on)}
.obk .obk-day[aria-pressed="true"] small{color:inherit;opacity:.85}
.obk .obk-day[aria-pressed="true"] i{background:var(--bk-on)}
.obk .obk-dayname{margin:0 0 10px;font-size:1rem}
.obk .obk-times{display:grid;grid-template-columns:repeat(auto-fill,minmax(92px,1fr));gap:8px}
.obk .obk-time{min-height:48px;padding:8px 6px;border:1px solid color-mix(in srgb,var(--bk-c) 45%,var(--bk-line));border-radius:12px;background:var(--bk-card);color:var(--bk-ink);font-weight:700;font-variant-numeric:tabular-nums}
.obk .obk-time:hover{background:var(--bk-soft);border-color:var(--bk-c)}
.obk .obk-time[aria-pressed="true"]{background:var(--bk-c);border-color:var(--bk-c);color:var(--bk-on)}
.obk .obk-tz{display:flex;gap:6px;align-items:flex-start;margin:16px 0 0;font-size:.86rem;color:var(--bk-muted)}
.obk .obk-tz .obk-i{margin-top:.15em}
.obk .obk-empty{display:grid;justify-items:center;gap:10px;text-align:center;padding:26px 14px;border:1px dashed var(--bk-line);border-radius:14px;color:var(--bk-muted)}
.obk .obk-empty>.obk-i{width:34px;height:34px;color:var(--bk-ink)}
.obk .obk-empty b{color:var(--bk-text);font-size:1.02rem}
.obk .obk-empty p{margin:0;max-width:360px}
.obk .obk-wait{display:grid;grid-template-columns:repeat(auto-fill,minmax(92px,1fr));gap:8px}
.obk .obk-wait i{height:48px;border-radius:12px;background:var(--bk-field);border:1px solid var(--bk-line);animation:obk-pulse 1.2s ease-in-out infinite alternate}
@keyframes obk-pulse{to{opacity:.45}}
@media (prefers-reduced-motion:reduce){.obk .obk-wait i{animation:none}.obk .obk-svc{transition:none}}
.obk .obk-sum{margin:0 0 18px;padding:14px 16px;border-radius:14px;background:var(--bk-soft);display:grid;gap:8px}
.obk .obk-sum div{display:flex;gap:10px;align-items:flex-start}
.obk .obk-sum dt{flex:none;color:var(--bk-ink);margin-top:.1em}
.obk .obk-sum dd{margin:0;min-width:0}
.obk .obk-sum dd small{display:block;color:var(--bk-muted);font-size:.86rem}
.obk .obk-sum .obk-link{margin-left:auto;padding-top:0;min-height:0}
.obk .obk-form{display:grid;gap:14px}
.obk .obk-f{display:grid;gap:6px}
.obk .obk-f label{display:block;color:var(--bk-text);font-weight:600;font-size:.95rem}
.obk .obk-f label small{font-weight:400;color:var(--bk-muted)}
.obk .obk-f input,.obk .obk-f textarea{width:100%;min-height:48px;padding:11px 13px;border:1px solid var(--bk-line);border-radius:12px;background:var(--bk-field);font-size:16px}
.obk .obk-f textarea{min-height:92px;resize:vertical}
.obk .obk-f input:focus,.obk .obk-f textarea:focus{outline:none;border-color:var(--bk-c);box-shadow:0 0 0 3px color-mix(in srgb,var(--bk-c) 25%,transparent)}
.obk .obk-f [aria-invalid="true"]{border-color:var(--bk-err)}
.obk .obk-ferr{margin:0;color:var(--bk-err);font-size:.88rem;font-weight:600}
.obk .obk-hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden}
.obk .obk-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:52px;padding:12px 24px;border:0;border-radius:999px;background:var(--bk-c);color:var(--bk-on);font-weight:700;font-size:1.02rem;text-decoration:none}
.obk .obk-btn:disabled{opacity:.65;cursor:progress}
.obk .obk-btn--ghost{background:transparent;color:var(--bk-ink);border:1.5px solid color-mix(in srgb,var(--bk-c) 55%,var(--bk-line))}
.obk .obk-alert{display:flex;gap:10px;align-items:flex-start;margin:0 0 16px;padding:12px 14px;border-radius:12px;background:color-mix(in srgb,var(--bk-err) 10%,transparent);color:var(--bk-err);font-weight:600;font-size:.94rem}
.obk .obk-alert .obk-i{margin-top:.15em}
.obk .obk-alert--info{background:var(--bk-soft);color:var(--bk-text)}
.obk .obk-alert--info .obk-i{color:var(--bk-ink)}
.obk .obk-alert button{margin-left:auto}
.obk .obk-fine{margin:2px 0 0;font-size:.82rem;color:var(--bk-muted)}
.obk .obk-done{text-align:center}
.obk .obk-done__tick{width:68px;height:68px;margin:4px auto 14px;border-radius:50%;display:grid;place-items:center;background:var(--bk-c);color:var(--bk-on);font-size:34px}
.obk .obk-done .obk-sum{text-align:left;margin-top:18px}
.obk .obk-done__note{margin:0 0 18px;padding:12px 14px;border-left:4px solid var(--bk-c);border-radius:4px 12px 12px 4px;background:var(--bk-field);text-align:left;white-space:pre-line}
.obk .obk-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:center}
.obk .obk-again{margin-top:14px}
@media (max-width:520px){.obk{padding:20px 16px 18px;border-radius:16px}.obk .obk-name{font-size:1.25rem}.obk .obk-mono{width:42px;height:42px;font-size:1.1rem;border-radius:12px}
 .obk .obk-days{gap:4px}.obk .obk-day{min-height:60px;border-radius:10px}.obk .obk-day b{font-size:1rem}.obk .obk-times{grid-template-columns:repeat(3,minmax(0,1fr))}.obk .obk-wait{grid-template-columns:repeat(3,minmax(0,1fr))}
 .obk .obk-form .obk-btn,.obk .obk-actions .obk-btn{width:100%}.obk .obk-svc{padding:12px 14px;gap:10px}}
`;
    const addCss = () => {
        if (document.getElementById('obk-css')) return;
        const s = document.createElement('style'); s.id = 'obk-css'; s.textContent = CSS; document.head.appendChild(s);
    };

    /* ------------------------------------------------ the booking widget */
    const LANG = (() => { try { return new Intl.DateTimeFormat(navigator.languages?.[0] || navigator.language || 'en-GB').resolvedOptions().locale; } catch { return 'en-GB'; } })();
    const dateFmt = (o) => { try { return new Intl.DateTimeFormat(LANG, { ...o, timeZone: 'UTC' }); } catch { return new Intl.DateTimeFormat('en-GB', { ...o, timeZone: 'UTC' }); } };
    const F = {
        long: dateFmt({ weekday: 'long', day: 'numeric', month: 'long' }),
        full: dateFmt({ weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
        wd: dateFmt({ weekday: 'short' }), day: dateFmt({ day: 'numeric' }), dm: dateFmt({ day: 'numeric', month: 'short' }), dmy: dateFmt({ day: 'numeric', month: 'short', year: 'numeric' }),
        time: dateFmt({ hour: 'numeric', minute: '2-digit' }),
    };
    const showTime = t => F.time.format(new Date(Date.UTC(2000, 0, 1) + mins(t) * 6e4));
    const showDate = (s, f = F.long) => f.format(D(s));
    const weekLabel = mon => {
        const sun = addDays(mon, 6);
        try { return F.dm.formatRange(D(mon), D(sun)); } catch { return `${showDate(mon, F.dm)} – ${showDate(sun, F.dm)}`; }
    };
    const lengthText = m => { m = +m || 0; const h = Math.floor(m / 60), r = m % 60; return h ? `${h} hr${h > 1 ? 's' : ''}${r ? ` ${r} min` : ''}` : `${r} min`; };
    const visitorTz = (() => { try { return Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch { return ''; } })();
    const sameClock = tz => { // the visitor's clock shows the same time as the business's (now)
        if (!visitorTz || visitorTz === tz) return true;
        const n = new Date(), a = wall(tz, n), b = wall(visitorTz, n);
        return a.date === b.date && a.min === b.min;
    };
    const tzName = tz => String(tz || 'UTC').replace(/_/g, ' ');

    const mount = (el, cfgIn, api, opts = {}) => {
        addCss();
        let cfg = cfgIn || {};
        const S = { step: 'service', svc: '', week: '', day: '', time: '', cache: {}, loading: false, loadErr: '', alert: null, booking: null, sent: null, form: { name: '', email: '', phone: '', note: '' }, ferr: {}, jumped: false, busy: false };
        let interacted = false, run = 0;
        const tz = () => (validTz(cfg.tz) ? cfg.tz : 'UTC');
        const svc = () => serviceOf(cfg, S.svc);
        const services = () => (Array.isArray(cfg.services) ? cfg.services : []).filter(s => s && s.key && s.name);
        const setColour = () => {
            const c = hexOk(cfg.color) ? cfg.color : '#16a34a';
            el.style.setProperty('--bk-c', c); el.style.setProperty('--bk-on', onColor(c));
        };
        el.classList.add('obk');

        /* ---------- pieces */
        const header = () => {
            const name = String(cfg.name || '').trim() || 'Book an appointment';
            const mono = (name.match(/\p{L}|\p{N}/u) || ['B'])[0].toUpperCase();
            const H = opts.preview ? 'p' : 'h1';
            return `<header class="obk-head"><span class="obk-mono" aria-hidden="true">${esc(mono)}</span><div>
                <${H} class="obk-name">${esc(name)}</${H}>
                ${cfg.intro ? `<p class="obk-intro">${esc(cfg.intro)}</p>` : ''}
                ${cfg.location ? `<p class="obk-loc">${ic('pin')}<span>${esc(cfg.location)}</span></p>` : ''}</div></header>`;
        };
        const stepper = () => {
            if (S.step === 'done') return '';
            const list = [['service', 'Service'], ['time', 'Day & time'], ['details', 'Your details']].filter(([k]) => k !== 'service' || services().length > 1);
            const at = list.findIndex(([k]) => k === S.step);
            return `<ol class="obk-steps" aria-label="Booking steps">${list.map(([k, t], i) => `<li class="${i < at ? 'is-done' : i === at ? 'is-on' : ''}"${i === at ? ' aria-current="step"' : ''}><span class="obk-sr">Step ${i + 1}: </span>${t}</li>`).join('')}</ol>`;
        };
        const alertBox = () => {
            if (!S.alert) return '';
            const info = S.alert.kind === 'info';
            return `<p class="obk-alert${info ? ' obk-alert--info' : ''}" role="${info ? 'status' : 'alert'}">${ic(info ? 'info' : 'warning')}<span>${esc(S.alert.text)}</span></p>`;
        };
        const svcMeta = s => [lengthText(s.mins), s.price ? esc(s.price) : ''].filter(Boolean);
        const picked = (withTime) => {
            const s = svc(); if (!s) return '';
            return `<dl class="obk-sum">
                <div><dt>${ic('tag')}<span class="obk-sr">Service</span></dt><dd><b>${esc(s.name)}</b><small>${svcMeta(s).join(' · ')}</small></dd>${services().length > 1 ? '<button type="button" class="obk-link" data-go="service">Change</button>' : ''}</div>
                ${withTime ? `<div><dt>${ic('calendar')}<span class="obk-sr">When</span></dt><dd><b>${esc(showDate(S.day, F.full))}</b><small>${esc(showTime(S.time))} – ${esc(showTime(hhmm(mins(S.time) + (+s.mins || 0))))}${yourTime()}</small></dd><button type="button" class="obk-link" data-go="time">Change</button></div>` : ''}
                ${withTime && cfg.location ? `<div><dt>${ic('pin')}<span class="obk-sr">Where</span></dt><dd>${esc(cfg.location)}</dd></div>` : ''}
            </dl>`;
        };
        const yourTime = () => {
            if (sameClock(tz())) return '';
            try {
                const at = instant(S.day, S.time, tz());
                const t = new Intl.DateTimeFormat(LANG, { weekday: 'short', hour: 'numeric', minute: '2-digit', timeZone: visitorTz }).format(at);
                return ` <span>(${esc(t)} your time)</span>`;
            } catch { return ''; }
        };
        const tzNote = () => (sameClock(tz()) ? '' : `<p class="obk-tz">${ic('globe')}<span>Times are in ${esc(tzName(tz()))} time.</span></p>`);

        /* ---------- steps */
        const drawService = () => {
            const list = services();
            if (!list.length) return `<div class="obk-empty">${ic('calendar')}<b>No services to book yet</b><p>This business hasn’t added anything to book. Please check back soon.</p></div>`;
            return `<h2 class="obk-h" tabindex="-1">Choose a service</h2><p class="obk-sub">Pick what you’d like to book.</p>
                <div class="obk-list">${list.map(s => `<button type="button" class="obk-svc" data-svc="${esc(s.key)}">
                    <span class="obk-svc__main"><b>${esc(s.name)}</b>${s.desc ? `<small>${esc(s.desc)}</small>` : ''}</span>
                    <span class="obk-svc__meta"><span>${ic('clock')} ${esc(lengthText(s.mins))}</span>${s.price ? `<b>${esc(s.price)}</b>` : ''}</span>${ic('chevron-right')}</button>`).join('')}</div>`;
        };
        const weekData = () => S.cache[`${S.svc}|${S.week}`];
        const drawTime = () => {
            const first = mondayOf(today(tz())), data = weekData();
            const byDate = {}; (data?.days || []).forEach(d => (byDate[d.date] = Array.isArray(d.slots) ? d.slots : []));
            const dates = Array.from({ length: 7 }, (_, i) => addDays(S.week, i));
            const anyFree = dates.some(d => byDate[d]?.length);
            const more = !!data?.more;
            let body;
            if (S.loadErr) body = `<div class="obk-empty">${ic('warning')}<b>We couldn’t load the free times</b><p>${esc(S.loadErr)}</p><button type="button" class="obk-btn obk-btn--ghost" data-retry>${ic('refresh')} Try again</button></div>`;
            else if (!data) body = `<p class="obk-sub" role="status">Finding free times…</p><div class="obk-wait" aria-hidden="true">${'<i></i>'.repeat(6)}</div>`;
            else if (!anyFree) body = `<div class="obk-empty">${ic('calendar')}<b>No free times this week</b><p>${more ? 'Try next week — there may be space then.' : 'There are no more free times to book right now. Please check back later.'}</p>${more ? `<button type="button" class="obk-btn obk-btn--ghost" data-week="1">Next week ${ic('arrow-right')}</button>` : ''}</div>`;
            else {
                const slots = byDate[S.day] || [];
                body = `<h3 class="obk-dayname" id="obk-dayname">${esc(showDate(S.day))}</h3>
                    <div class="obk-times" role="group" aria-labelledby="obk-dayname">${slots.map(t => `<button type="button" class="obk-time" data-time="${esc(t)}" aria-pressed="${S.time === t}">${esc(showTime(t))}</button>`).join('')}</div>`;
            }
            return `<h2 class="obk-h" tabindex="-1">Pick a day and time</h2>
                ${S.jumped && anyFree ? '<p class="obk-sub">This week is full or closed — here are the next free times.</p>' : '<p class="obk-sub">Days with free times are marked with a dot.</p>'}
                ${picked(false)}
                <div class="obk-week"><button type="button" class="obk-nav" data-week="-1" aria-label="Previous week" ${S.week <= first || !data ? 'disabled' : ''}>${ic('arrow-left')}</button>
                    <b aria-live="polite">${esc(weekLabel(S.week))}</b>
                    <button type="button" class="obk-nav" data-week="1" aria-label="Next week" ${more ? '' : 'disabled'}>${ic('arrow-right')}</button></div>
                <div class="obk-days" role="group" aria-label="Days this week">${dates.map(d => {
                    const n = byDate[d]?.length || 0;
                    return `<button type="button" class="obk-day" data-day="${d}" aria-pressed="${S.day === d && n > 0}" ${n ? '' : 'disabled'} aria-label="${esc(showDate(d))}${data ? (n ? `, ${n} free time${n === 1 ? '' : 's'}` : ', no free times') : ''}"><small>${esc(showDate(d, F.wd))}</small><b>${esc(showDate(d, F.day))}</b><i></i></button>`;
                }).join('')}</div>
                ${body}${tzNote()}`;
        };
        const field = (k, label, o = {}) => `<div class="obk-f"><label for="obk-${k}">${label}${o.opt ? ' <small>(optional)</small>' : ''}</label>
            ${o.area ? `<textarea id="obk-${k}" name="${k}" maxlength="${o.max || 500}" rows="3"${o.ph ? ` placeholder="${esc(o.ph)}"` : ''}>${esc(S.form[k])}</textarea>`
            : `<input id="obk-${k}" name="${k}" type="${o.type || 'text'}" maxlength="${o.max || 100}" value="${esc(S.form[k])}" autocomplete="${o.ac || 'off'}"${o.im ? ` inputmode="${o.im}"` : ''}${o.opt ? '' : ' required aria-required="true"'}${S.ferr[k] ? ` aria-invalid="true" aria-describedby="obk-${k}-err"` : ''}${o.type === 'email' ? ' autocapitalize="off" spellcheck="false"' : ''}>`}
            ${S.ferr[k] ? `<p class="obk-ferr" id="obk-${k}-err">${esc(S.ferr[k])}</p>` : ''}</div>`;
        const drawDetails = () => `<h2 class="obk-h" tabindex="-1">Your details</h2><p class="obk-sub">Almost done — tell us who’s coming.</p>
            ${picked(true)}${alertBox()}
            <form class="obk-form" novalidate data-form>
                ${field('name', 'Your name', { ac: 'name', max: 100 })}
                ${field('email', 'Email', { type: 'email', ac: 'email', im: 'email', max: 150 })}
                ${cfg.askPhone ? field('phone', 'Phone', { type: 'tel', ac: 'tel', im: 'tel', max: 30 }) : ''}
                ${field('note', 'Anything we should know?', { area: true, opt: true, max: 500 })}
                <div class="obk-hp" aria-hidden="true"><label for="obk-hp">Leave this empty</label><input id="obk-hp" name="_hp" tabindex="-1" autocomplete="off"></div>
                <button type="submit" class="obk-btn" ${S.busy ? 'disabled' : ''}>${S.busy ? 'Booking…' : `Confirm booking ${ic('arrow-right')}`}</button>
                <p class="obk-fine">We’ll email your confirmation, with a link to change or cancel.</p>
            </form>`;
        const drawDone = () => {
            const b = S.booking || {}, s = svc();
            const note = b.note || cfg.note || '';
            return `<div class="obk-done"><div class="obk-done__tick" aria-hidden="true">${ic('tick')}</div>
                <h2 class="obk-h" tabindex="-1">You’re booked!</h2>
                <p class="obk-sub">${S.sent?.email ? `We’ve sent the details to <b>${esc(S.sent.email)}</b>.` : 'Your booking is confirmed.'}</p>
                <dl class="obk-sum">
                    <div><dt>${ic('tag')}<span class="obk-sr">Service</span></dt><dd><b>${esc(b.service || s?.name || '')}</b>${cfg.name ? `<small>${esc(cfg.name)}</small>` : ''}</dd></div>
                    <div><dt>${ic('calendar')}<span class="obk-sr">When</span></dt><dd><b>${esc(b.when || `${showDate(S.day, F.full)}, ${showTime(S.time)}`)}</b>${sameClock(b.tz || tz()) ? '' : `<small>${esc(tzName(b.tz || tz()))} time${yourTime()}</small>`}</dd></div>
                    ${cfg.location ? `<div><dt>${ic('pin')}<span class="obk-sr">Where</span></dt><dd>${esc(cfg.location)}</dd></div>` : ''}
                </dl>
                ${note ? `<p class="obk-done__note">${esc(note)}</p>` : ''}
                <div class="obk-actions">
                    <button type="button" class="obk-btn" data-ics>${ic('calendar')} Add to calendar</button>
                    ${b.manage ? `<a class="obk-btn obk-btn--ghost" href="${esc(b.manage)}" target="_blank" rel="noopener">Change or cancel ${ic('external')}</a>` : ''}
                </div>
                <button type="button" class="obk-link obk-again" data-again>Book another appointment</button></div>`;
        };

        const draw = (focus) => {
            setColour();
            const body = { service: drawService, time: drawTime, details: drawDetails, done: drawDone }[S.step]();
            el.innerHTML = header() + stepper() + (S.step === 'details' ? '' : alertBox()) + `<div class="obk-body">${body}</div>`;
            el.setAttribute('aria-busy', S.step === 'time' && !weekData() && !S.loadErr ? 'true' : 'false');
            if (focus && interacted) {
                const h = el.querySelector('.obk-h');
                if (h) { h.focus({ preventScroll: true }); const r = h.getBoundingClientRect(); if (r.top < 0 || r.top > innerHeight - 80) el.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); }
            }
        };

        /* ---------- data */
        const load = async (jumpIfEmpty) => {
            const me = ++run, key = `${S.svc}|${S.week}`;
            S.loadErr = '';
            if (!S.cache[key]) {
                draw();
                try {
                    const r = await api.slots(S.svc, S.week);
                    if (me !== run) return;
                    if (!r || r.ok === false || !Array.isArray(r.days)) throw new Error(r?.error || '');
                    S.cache[key] = { days: r.days, more: !!r.more };
                } catch (ex) {
                    if (me !== run) return;
                    S.loadErr = (ex && ex.message) || 'Please check your internet connection and try again.';
                    return draw();
                }
            }
            const data = S.cache[key], free = (data.days || []).filter(d => d.slots?.length && d.date >= S.week && d.date <= addDays(S.week, 6));
            // nothing this week on the first look: move on to the first week that has free times (up to ~3 months)
            if (!free.length && jumpIfEmpty && data.more && jumpIfEmpty < 14) { S.week = addDays(S.week, 7); S.jumped = true; return load(jumpIfEmpty + 1); }
            if (!free.some(d => d.date === S.day)) { S.day = free[0]?.date || ''; S.time = ''; }
            draw();
        };
        const startTime = () => {
            S.step = 'time'; S.week = mondayOf(today(tz())); S.day = ''; S.time = ''; S.jumped = false;
            draw(true); load(1);
        };

        /* ---------- the calendar file */
        const icsStamp = d => d.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
        const icsEsc = s => String(s || '').replace(/\\/g, '\\\\').replace(/\r?\n/g, '\\n').replace(/([,;])/g, '\\$1');
        const fold = l => { let out = ''; while (l.length > 73) { out += l.slice(0, 73) + '\r\n '; l = l.slice(73); } return out + l; };
        const downloadIcs = () => {
            const s = svc(), b = S.booking || {};
            const start = instant(S.day, S.time, tz()), end = new Date(start.getTime() + (+s?.mins || 30) * 6e4);
            const desc = [b.note || cfg.note || '', b.manage ? `Change or cancel: ${b.manage}` : ''].filter(Boolean).join('\n\n');
            const L = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Owlvix//Booking page//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
                `UID:${S.day.replace(/-/g, '')}-${S.time.replace(':', '')}-${Math.random().toString(36).slice(2, 10)}@owlvix.com`, `DTSTAMP:${icsStamp(new Date())}`,
                `DTSTART:${icsStamp(start)}`, `DTEND:${icsStamp(end)}`, `SUMMARY:${icsEsc(`${b.service || s?.name || 'Appointment'}${cfg.name ? ' — ' + cfg.name : ''}`)}`];
            if (cfg.location) L.push(`LOCATION:${icsEsc(cfg.location)}`);
            if (desc) L.push(`DESCRIPTION:${icsEsc(desc)}`);
            if (b.manage) L.push(`URL:${b.manage}`);
            L.push('BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:Reminder', 'TRIGGER:-PT1H', 'END:VALARM', 'END:VEVENT', 'END:VCALENDAR');
            const blob = new Blob([L.map(fold).join('\r\n') + '\r\n'], { type: 'text/calendar;charset=utf-8' });
            const a = document.createElement('a'); a.href = URL.createObjectURL(blob);
            a.download = `${(cfg.name || 'booking').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 40) || 'booking'}-${S.day}.ics`;
            document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 5000);
        };

        /* ---------- booking */
        const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        const submit = async form => {
            if (S.busy) return;
            const v = k => String(form.elements[k]?.value ?? '').trim();
            S.form = { name: v('name'), email: v('email'), phone: v('phone'), note: v('note') };
            const fe = {};
            if (!S.form.name) fe.name = 'Please type your name.';
            if (!S.form.email) fe.email = 'Please type your email — your confirmation goes there.';
            else if (!EMAIL.test(S.form.email)) fe.email = 'That email doesn’t look right. Please check it, e.g. name@example.com.';
            if (cfg.askPhone && S.form.phone.replace(/\D/g, '').length < 6) fe.phone = S.form.phone ? 'That phone number looks too short.' : 'Please type your phone number.';
            S.ferr = fe; S.alert = null;
            if (Object.keys(fe).length) { draw(); el.querySelector('[aria-invalid="true"]')?.focus(); return; }
            S.busy = true; draw();
            let r;
            try { r = await api.book({ service: S.svc, date: S.day, time: S.time, name: S.form.name, email: S.form.email, phone: cfg.askPhone ? S.form.phone : '', note: S.form.note, _hp: v('_hp') }); }
            catch { r = { ok: false, error: 'We couldn’t reach the booking system. Please check your internet connection and try again.' }; }
            S.busy = false;
            if (r && r.ok) { S.booking = r.booking || {}; S.sent = { email: S.form.email }; S.step = 'done'; S.alert = null; draw(true); return; }
            const msg = r?.error || 'Something went wrong and your booking wasn’t made. Please try again.';
            if (r?.taken) {
                Object.keys(S.cache).forEach(k => k.startsWith(S.svc + '|') && delete S.cache[k]);
                S.alert = { text: r.error || 'Sorry — someone has just booked that time. Please pick another.' };
                S.step = 'time'; S.time = ''; draw(true); load(0); return;
            }
            if (r?.field && ['name', 'email', 'phone', 'note'].includes(r.field)) { S.ferr = { [r.field]: msg }; draw(); el.querySelector('[aria-invalid="true"]')?.focus(); return; }
            S.alert = { text: msg, kind: opts.preview ? 'info' : '' };
            draw(); el.querySelector('.obk-alert')?.scrollIntoView({ block: 'nearest' });
        };

        /* ---------- events */
        const onClick = e => {
            const b = e.target.closest('button'); if (!b || !el.contains(b) || b.disabled) return;
            interacted = true;
            const ds = b.dataset;
            if (ds.svc) { S.svc = ds.svc; S.alert = null; startTime(); }
            else if (ds.go === 'service') { S.step = 'service'; S.alert = null; draw(true); }
            else if (ds.go === 'time') { S.step = 'time'; S.alert = null; draw(true); load(0); }
            else if (ds.week) { S.week = addDays(S.week, 7 * +ds.week); S.jumped = false; S.alert = null; load(0); }
            else if (ds.day) { S.day = ds.day; S.time = ''; draw(); el.querySelector(`[data-day="${ds.day}"]`)?.focus(); }
            else if (ds.time) { S.time = ds.time; S.alert = null; S.ferr = {}; S.step = 'details'; draw(true); }
            else if ('retry' in ds) load(0);
            else if ('ics' in ds) downloadIcs();
            else if ('again' in ds) {
                Object.assign(S, { step: 'service', time: '', day: '', booking: null, alert: null, ferr: {}, cache: {}, form: { ...S.form, note: '' } });
                if (services().length === 1) { S.svc = services()[0].key; startTime(); } else draw(true);
            }
        };
        const onSubmit = e => { if (!e.target.matches('[data-form]')) return; e.preventDefault(); interacted = true; submit(e.target); };
        const onInput = e => { const n = e.target.name; if (n && n in S.form) S.form[n] = e.target.value; };
        el.addEventListener('click', onClick);
        el.addEventListener('submit', onSubmit);
        el.addEventListener('input', onInput);

        const begin = () => {
            const list = services();
            if (list.length === 1) { S.svc = list[0].key; S.step = 'time'; S.week = mondayOf(today(tz())); load(1); }
            else { S.step = 'service'; draw(); }
        };
        begin();

        return {
            /** New settings (the builder's live preview): keeps the visitor's place where it still makes sense. */
            update(next) {
                cfg = next || {}; S.cache = {}; run++;
                const list = services();
                if (S.step === 'done') S.step = 'details';
                if (!svc() || !list.length) { S.svc = ''; S.time = ''; S.alert = null; return begin(); }
                if (list.length === 1 && S.step === 'service') return begin();
                if (S.step === 'time' || S.step === 'details') { S.step = 'time'; S.time = ''; S.alert = null; load(0); return; }
                draw();
            },
            destroy() { run++; el.removeEventListener('click', onClick); el.removeEventListener('submit', onSubmit); el.removeEventListener('input', onInput); el.innerHTML = ''; el.classList.remove('obk'); },
        };
    };

    window.OwlBooking = { mount, localSlots, today };

    /* ------------------------------------------------ the public page: mount on #booking-root */
    const boot = () => {
        const data = document.getElementById('booking-cfg'), root = document.getElementById('booking-root');
        if (!data || !root) return;
        let P; try { P = JSON.parse(data.textContent); } catch { return; }
        const getJson = async res => { try { return await res.json(); } catch { return {}; } };
        const api = {
            slots: async (key, from) => {
                const u = new URL(P.slots, location.href); u.searchParams.set('service', key); u.searchParams.set('from', from);
                const res = await fetch(u, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const j = await getJson(res);
                if (res.status === 429) throw new Error('Lots of people are looking right now. Please wait a moment and try again.');
                if (!res.ok || !j.ok) throw new Error(j.error || 'Please check your internet connection and try again.');
                return j;
            },
            book: async payload => {
                const res = await fetch(P.book, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(payload) });
                const j = await getJson(res);
                if (res.status === 429) return { ok: false, error: 'Too many tries in a short time. Please wait a minute and try again.' };
                if (j && typeof j.ok === 'boolean') return j;
                return { ok: false, error: res.ok ? 'Something went wrong. Please try again.' : 'The booking system isn’t answering right now. Please try again in a few minutes.' };
            },
        };
        mount(root, P.cfg || {}, api);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
