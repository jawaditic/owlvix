/* Quick setup wizard for the mobile app builder (/mobile-app-builder): seven short animated steps — business type,
   name & logo, colours, pages, contact, look, payments — with the phone preview beside them updating live, then a
   short "building" moment and the finished app to tap through. Every answer goes straight into the builder (through
   window.OwlAppBuilder, set by app-builder.js), so the full builder shows the same app afterwards, and running the
   wizard again starts from the app as it is: it's how owners update their app quickly too. */
(() => {
    const start = () => {
        const B = window.OwlAppBuilder, root = document.querySelector('[data-ab]');
        if (!B || !root || root.dataset.wizard) return;
        root.dataset.wizard = '1';
        const $ = (s, el = document) => el.querySelector(s);
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
        const still = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
        const OFF = 'owlvix-app-banner-off'; // set when the visitor finishes the wizard or says they'd rather use the full builder
        const made = () => { try { return !!localStorage.getItem(OFF + '-made'); } catch { return false; } }; // finished the quick setup
        const banner = document.querySelector('[data-wz-banner]');

        const EMOJI = { restaurant: '🍽️', cafe: '☕', salon: '💇', gym: '🏋️', shop: '🛍️', clinic: '🩺', realestate: '🏡', events: '🎟️', agency: '💼', church: '⛪', school: '🎓', hotel: '🏨' };
        const PALETTES = [
            ['Forest', '#16a34a'], ['Ocean', '#2563eb'], ['Sunset', '#e8590c'], ['Berry', '#be185d'],
            ['Royal', '#6d28d9'], ['Coffee', '#7c4a2d'], ['Teal', '#0d9488'], ['Midnight', '#1e1b49'],
        ];
        // pages the visitor can add on the "pages" step, each ready with suitable blocks
        const EXTRA = [
            ['About', 'info', '👋', [{ type: 'heading', text: 'About us' }, { type: 'text', text: 'Tell people your story — who you are, what you do and why you love it.' }]],
            ['Gallery', 'image', '🖼️', ['gallery']],
            ['Contact', 'location', '📍', ['contact', 'hours']],
            ['Reviews', 'star', '⭐', ['reviews']],
            ['FAQ', 'chat', '💬', ['faq']],
            ['Offers', 'tag', '🏷️', [{ type: 'notice', text: 'This week only: 10% off everything!', tone: 'success' }, { type: 'list', title: 'Current offers' }]],
            ['Book', 'calendar', '📅', ['booking']],
            ['Shop', 'cart', '🛒', ['products']],
            ['Videos', 'play', '🎬', ['video']],
            ['Rewards', 'heart', '🎁', ['loyalty']],
            ['Follow us', 'globe', '📣', ['social']],
        ];
        /* ---------- phone numbers: a flag and country code to pick, then the number ---------- */
        const CODES = 'AF93 AL355 DZ213 AR54 AM374 AU61 AT43 AZ994 BH973 BD880 BY375 BE32 BJ229 BO591 BA387 BW267 BR55 BN673 BG359 KH855 CM237 CA1 CL56 CN86 CO57 CR506 HR385 CY357 CZ420 DK45 DO1 EC593 EG20 SV503 EE372 ET251 FI358 FR33 GE995 DE49 GH233 GR30 GT502 HN504 HK852 HU36 IS354 IN91 ID62 IR98 IQ964 IE353 IL972 IT39 CI225 JM1 JP81 JO962 KZ7 KE254 KW965 KG996 LA856 LV371 LB961 LY218 LT370 LU352 MO853 MY60 MV960 MT356 MU230 MX52 MD373 MN976 ME382 MA212 MZ258 MM95 NA264 NP977 NL31 NZ64 NI505 NG234 MK389 NO47 OM968 PK92 PS970 PA507 PY595 PE51 PH63 PL48 PT351 PR1 QA974 RO40 RU7 RW250 SA966 SN221 RS381 SG65 SK421 SI386 SO252 ZA27 KR82 ES34 LK94 SD249 SE46 CH41 SY963 TW886 TJ992 TZ255 TH66 TT1 TN216 TR90 TM993 UG256 UA380 AE971 GB44 US1 UY598 UZ998 VE58 VN84 YE967 ZM260 ZW263'
            .split(' ').map(x => [x.slice(0, 2), x.slice(2)]);
        const flag = iso => String.fromCodePoint(...[...iso].map(c => 127397 + c.charCodeAt(0)));
        let names = null; try { names = new Intl.DisplayNames([navigator.language || 'en'], { type: 'region' }); } catch {}
        const nameOf = iso => names?.of(iso) || iso;
        const BY_NAME = [...CODES].sort((a, b) => nameOf(a[0]).localeCompare(nameOf(b[0])));
        // the visitor's own country first guess: their time zone, then their browser's language
        const TZ = { Karachi: 'PK', Kolkata: 'IN', Calcutta: 'IN', Dubai: 'AE', Riyadh: 'SA', Qatar: 'QA', London: 'GB', Dublin: 'IE', Dhaka: 'BD', Lagos: 'NG', Nairobi: 'KE', Cairo: 'EG', Istanbul: 'TR', Kuala_Lumpur: 'MY', Singapore: 'SG', Jakarta: 'ID', Manila: 'PH', Sydney: 'AU', Melbourne: 'AU', Toronto: 'CA', Berlin: 'DE', Paris: 'FR', Madrid: 'ES', Rome: 'IT', Amsterdam: 'NL', Johannesburg: 'ZA', Tokyo: 'JP' };
        const myCountry = (() => {
            try { const z = Intl.DateTimeFormat().resolvedOptions().timeZone.split('/').pop(); if (TZ[z]) return TZ[z]; } catch {}
            const r = (navigator.language || '').split('-')[1]?.toUpperCase();
            return CODES.some(([c]) => c === r) ? r : 'US';
        })();
        /** "+44 7700 900123" → ['GB', '7700 900123'] (the longest matching code wins; +1 and +7 go to the guessed country when it shares them). */
        const split = v => {
            const t = String(v || '').trim();
            if (!t.startsWith('+')) return [myCountry, t];
            const digits = t.slice(1).replace(/\D/g, '');
            const hit = [...CODES].sort((a, b) => b[1].length - a[1].length).filter(([, c]) => digits.startsWith(c));
            if (!hit.length) return [myCountry, t];
            const best = hit.find(([iso]) => iso === myCountry && hit[0][1] === CODES.find(([x]) => x === myCountry)[1]) || hit[0];
            return [best[0], t.slice(1).replace(/^\s*/, '').slice(best[1].length).replace(/^[\s-]+/, '')];
        };
        const telField = (key, label, value, ph) => {
            const [iso, num] = split(value), code = CODES.find(([c]) => c === iso)[1];
            return `<label class="wz-field">${label}<span class="wz-tel"><span class="wz-tel__cc"><span data-wz-ccshow="${key}">${flag(iso)} +${code}</span><select data-wz-cc="${key}" aria-label="${label} country code">${BY_NAME.map(([c, d]) => `<option value="${c}" ${c === iso ? 'selected' : ''}>${flag(c)} ${esc(nameOf(c))} (+${d})</option>`).join('')}</select></span>
                <input data-wz-num="${key}" type="tel" inputmode="tel" maxlength="20" value="${esc(num)}" placeholder="${ph}"></span></label>`;
        };
        /** The full number from a field's country and number ("" when no number); a leading 0 (dialled at home) is dropped. */
        const telValue = key => {
            const iso = $(`[data-wz-cc="${key}"]`, box).value, num = $(`[data-wz-num="${key}"]`, box).value.trim().replace(/^0+/, '');
            return num ? `+${CODES.find(([c]) => c === iso)[1]} ${num}` : '';
        };

        const LOOKS = [
            ['theme', 'Light or dark', [['light', '☀️ Light'], ['dark', '🌙 Dark'], ['auto', '🌓 Follow the phone']]],
            ['corners', 'Corners', [['rounded', 'Rounded'], ['soft', 'Soft'], ['square', 'Square']]],
            ['font', 'Font', [['modern', 'Modern'], ['classic', 'Classic'], ['condensed', 'Condensed'], ['plain', 'Plain']]],
            ['nav', 'Bottom bar', [['floating', 'Floating'], ['classic', 'Classic']]],
        ];

        let step = 0, kind = null, applied = null, drop = new Set(), box, home, prev, prevNext;
        let finished = false, fromBanner = false, fresh = -1; // fresh: the page just added (it pops in) // the builder shows after a wizard opened from the banner only once it's finished
        const A = () => B.app();
        const blocksOf = type => A().tabs.flatMap(t => t.blocks || []).filter(b => b.type === type);
        const sells = () => blocksOf('products').length > 0;

        /* ---------- the steps: each draws its question, and can apply its answer when leaving ---------- */
        const STEPS = [
            {
                title: 'What kind of business is it?', sub: 'We’ll start you with pages made for it — you can change everything.',
                draw: () => {
                    const keep = B.current() || applied || kind === 'keep' || B.hasContent();
                    return `<div class="wz-grid wz-grid--types">${keep ? `<button type="button" class="wz-card" data-kind="keep" aria-pressed="${kind === 'keep'}"><span class="wz-card__em">✅</span><b>Keep my app</b><small>Update what’s there</small></button>` : ''}
                        ${B.templates.map((t, i) => `<button type="button" class="wz-card" data-kind="${t.id}" aria-pressed="${kind === t.id}" style="--i:${i + (keep ? 1 : 0)}"><span class="wz-card__em">${EMOJI[t.id] || '📱'}</span><b>${esc(t.name)}</b><small>${esc(t.note)}</small></button>`).join('')}</div>`;
                },
                ok: () => (kind ? '' : 'Pick the closest one — you can change everything later.'),
                leave: async () => { if (kind && kind !== 'keep' && kind !== applied) { await B.useTemplate(kind); applied = kind; drop = new Set(); } },
            },
            {
                title: 'Name your app', sub: 'It’s shown under the icon on people’s phones.',
                draw: () => `<label class="wz-field">App name<input data-wz="name" maxlength="30" value="${esc(A().name)}" placeholder="e.g. Bean There"></label>
                    <div class="wz-icon"><canvas width="132" height="132" data-wz-icon aria-label="Your app icon"></canvas>
                        <div class="wz-icon__ctl"><b>Icon</b>
                            <div class="wz-chips"><button type="button" data-wz-imode="letter" aria-pressed="${B.state().icon.mode !== 'image'}">Letters</button><button type="button" data-wz-imode="image" aria-pressed="${B.state().icon.mode === 'image'}">My logo</button></div>
                            <label class="wz-field" data-wz-letters ${B.state().icon.mode === 'image' ? 'hidden' : ''}>Letters<input data-wz="letter" maxlength="2" value="${esc(B.state().icon.letter)}" placeholder="${esc((A().name || 'A').trim().charAt(0).toUpperCase())}"></label>
                            <label class="wz-upload" data-wz-logo ${B.state().icon.mode === 'image' ? '' : 'hidden'}><input type="file" accept="image/png,image/jpeg,image/webp" data-wz-file class="sr-only"><span>⬆️ ${B.state().icon.image ? 'Change logo' : 'Upload your logo'}</span><small>Square PNG, JPG or WebP</small></label>
                        </div></div>`,
                ok: () => (A().name.trim() ? '' : 'Please give your app a name.'),
                after: () => paint(),
            },
            {
                title: 'Pick your colours', sub: 'Buttons, the bottom bar and your icon use your main colour.',
                draw: () => `<div class="wz-grid wz-grid--pal">${PALETTES.map(([n, c], i) => `<button type="button" class="wz-swatch" data-pal="${c}" aria-pressed="${A().colors.primary.toLowerCase() === c}" style="--c:${c};--i:${i}"><i></i><span>${n}</span></button>`).join('')}</div>
                    <label class="wz-field wz-field--row">Or your own colour<input type="color" data-wz-color value="${esc(A().colors.primary)}"></label>`,
            },
            {
                title: 'Choose your pages', sub: 'Untick the pages you don’t need. You can add more pages and blocks later.',
                draw: () => {
                    const have = new Set(A().tabs.map(t => (t.label || '').trim().toLowerCase())), full = A().tabs.length >= B.maxPages;
                    const more = EXTRA.filter(([label]) => !have.has(label.toLowerCase()));
                    const barFull = A().tabs.filter((t, i) => B.inBar(t, i)).length >= B.maxBar;
                    return `<p class="wz-hint wz-place-hint">Show each page in the <b>bottom bar</b> (up to ${B.maxBar}), the <b>☰ menu</b>, or both.</p>
                        <div class="wz-pages">${A().tabs.map((t, i) => {
                        const bar = B.inBar(t, i), menu = B.inMenu(t, i);
                        return `<div class="wz-page wz-page--place${i === fresh ? ' is-new' : ''}${drop.has(i) ? ' is-off' : ''}" style="--i:${i === fresh ? 0 : i}">
                            <div class="wz-page__main" title="Show it on the phone"><input type="checkbox" data-wz-page="${i}" ${drop.has(i) ? '' : 'checked'} aria-label="Keep the ${esc(t.label || 'page')} page"><span class="wz-page__ic">${B.svgIcon(t.icon, 'currentColor', 20)}</span><b>${esc(t.label || 'Page')}</b><small>${t.type === 'web' ? 'Website page' : `${(t.blocks || []).length} block${(t.blocks || []).length === 1 ? '' : 's'}`}</small></div>
                            <span class="wz-place" role="group" aria-label="Where ${esc(t.label || 'this page')} shows">
                                <button type="button" data-wz-place="${i}:bar" aria-pressed="${bar}" ${!bar && barFull ? 'title="The bottom bar is full (5 pages)"' : ''}><i class="wz-place__bar"></i>Bottom bar</button>
                                <button type="button" data-wz-place="${i}:menu" aria-pressed="${menu}"><i class="wz-place__menu">☰</i>Menu</button>
                            </span></div>`;
                    }).join('')}</div>
                        <div class="wz-add"><b>Add a page</b>${full ? `<p class="wz-hint">You’ve reached ${B.maxPages} pages — the most an app can have.</p>` : `
                            <div class="wz-chips wz-add__chips">${more.map(([label, , em], i) => `<button type="button" data-wz-add="${i}" style="--i:${i}">${em} ${esc(label)}</button>`).join('')}</div>
                            <div class="wz-add__own"><input data-wz-own maxlength="20" placeholder="Or name your own page, e.g. Our team" aria-label="Name of a new page"><button type="button" class="btn btn--sm btn--ghost" data-wz-own-add>＋ Add</button></div>`}</div>`;
                },
                ok: () => (drop.size >= A().tabs.length ? 'Keep at least one page.' : ''),
                leave: () => { if (drop.size) { B.removePages([...drop]); drop = new Set(); B.changed(); } },
            },
            {
                title: 'How do customers reach you?', sub: 'Shown on your contact page; orders and bookings are sent here too.',
                draw: () => {
                    const c = blocksOf('contact')[0] || {};
                    const sample = v => (/555 ?01/.test(v || '') ? '' : v); // (the templates' made-up numbers)
                    return `<div class="wz-2">
                        ${telField('whatsapp', 'WhatsApp', sample(A().shop.whatsapp || c.whatsapp || ''), '7700 900123')}
                        <label class="wz-field">Email<input data-wz="email" type="email" maxlength="150" value="${esc(A().shop.email || (c.email !== 'hello@example.com' ? c.email : '') || '')}" placeholder="hello@yourbusiness.com"></label>
                        ${telField('phone', 'Phone', sample(c.phone), '20 7946 0000')}
                        <label class="wz-field">Address<input data-wz="address" maxlength="200" value="${esc(c.address && !/Your Town/.test(c.address) ? c.address : '')}" placeholder="12 High Street, London"></label></div>
                        <p class="wz-hint">Leave anything you don’t want to show empty.</p>`;
                },
                ok: () => ((sells() || blocksOf('booking').length) && !A().shop.whatsapp.trim() && !A().shop.email.trim() ? 'Your app takes orders or bookings: add a WhatsApp number or email for them.' : ''),
            },
            {
                title: 'Make it look yours', sub: 'Watch the preview change as you pick.',
                draw: () => LOOKS.map(([k, label, opts]) => `<div class="wz-look"><b>${label}</b><div class="wz-chips">${opts.map(([v, l]) => `<button type="button" data-wz-style="${k}:${v}" aria-pressed="${A().style[k] === v}">${l}</button>`).join('')}</div></div>`).join(''),
            },
            {
                title: 'How can customers pay?', sub: 'Turn on the ways you take. Card payments online can be set up in “More settings” after saving.',
                skip: () => !sells(),
                draw: () => {
                    const p = A().shop.pay;
                    return `<div class="wz-pays">${[['cash', '💵', 'Cash on delivery or collection'], ['card', '💳', 'Card on delivery or collection'], ['bank', '🏦', 'Bank transfer'], ['online', '⚡', 'Pay now by card online (Stripe)']]
                        .map(([k, em, l], i) => `<label class="wz-page" style="--i:${i}"><input type="checkbox" data-wz-pay="${k}" ${p[k] ? 'checked' : ''}><span class="wz-page__ic">${em}</span><b>${l}</b></label>`).join('')}</div>
                        <label class="wz-field" data-wz-bank ${p.bank ? '' : 'hidden'}>Your bank details<textarea data-wz="bank_details" rows="2" maxlength="300" placeholder="Account name, IBAN or account number, bank">${esc(p.bank_details)}</textarea></label>`;
                },
                ok: () => (A().shop.pay.bank && !A().shop.pay.bank_details.trim() ? 'Add your bank details, or untick bank transfer.' : ''),
            },
        ];
        const live = () => STEPS.filter(s => !s.skip?.());

        /* ---------- the dialog ---------- */
        const open = () => {
            if (box) return;
            root.classList.remove('ab-reveal');
            fromBanner = !!banner && !banner.hidden; finished = false;
            hideBanner(false);
            step = 0; kind = B.current() || applied || made() ? 'keep' : null; drop = new Set(); // (opened again: keep what's there unless they pick another)
            box = document.createElement('div');
            box.className = 'wz' + (still() ? ' wz--still' : '');
            box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('aria-labelledby', 'wz-title');
            box.innerHTML = `<div class="wz__shade" data-wz-close></div>
                <div class="wz__panel">
                    <div class="wz__main">
                        <div class="wz__top"><span class="wz__brand">✨ Quick setup</span><span class="wz__count" data-wz-count></span><button type="button" class="wz__x" data-wz-close aria-label="Close">✕</button></div>
                        <div class="wz__bar"><i data-wz-bar></i></div>
                        <div class="wz__step" data-wz-step aria-live="polite"></div>
                        <p class="wz__err" data-wz-err role="alert" hidden></p>
                        <div class="wz__nav"><button type="button" class="btn btn--ghost" data-wz-back>← Back</button><button type="button" class="btn btn--glow" data-wz-next>Next →</button></div>
                    </div>
                    <div class="wz__side" data-wz-side></div>
                </div>`;
            root.appendChild(box); // inside the builder, so its preview (moved in below) keeps working
            document.documentElement.classList.add('wz-open');
            // the builder's own phone preview moves into the wizard while it's open
            prev = root.querySelector('.ab-prev-wrap'); home = prev.parentNode; prevNext = prev.nextSibling;
            $('[data-wz-side]', box).appendChild(prev);
            B.showApp();
            requestAnimationFrame(() => box.classList.add('is-in'));
            draw(1);
        };
        // On the builder's main page, finishing opens its editor page (routes/web.php) with the app kept in this browser;
        // "Save my app" pressed here runs there (#then=save). If the draft can't be kept, they stay on this page.
        const editorUrl = root.dataset.editorUrl || '';
        const leave = async then => {
            if (!editorUrl || !(await B.keepDraft().catch(() => false))) return false;
            $('[data-wz-step]', box)?.insertAdjacentHTML('beforeend', '<p class="wz-leaving" role="status">Opening the editor…</p>');
            if (then) { try { sessionStorage.setItem('owlvix-then', new URL(editorUrl, location.href).pathname + '#' + then); } catch {} }
            location.assign(editorUrl + (then ? '#then=' + then : ''));
            return true;
        };
        const close = async () => {
            if (!box) return;
            if (finished && await leave()) return;
            home.insertBefore(prev, prevNext);
            B.changed();
            const b = box; box = null;
            document.documentElement.classList.remove('wz-open');
            b.classList.remove('is-in');
            setTimeout(() => b.remove(), still() ? 0 : 320);
            // closed before the end: back to the owl's invitation; finished: the builder, with everything filled in
            if (fromBanner && !finished && !B.state().id) showBanner();
            else if (fromBanner) setTimeout(() => root.scrollIntoView({ behavior: still() ? 'auto' : 'smooth', block: 'start' }), 80);
            fromBanner = false;
        };
        const paint = () => { const c = box && $('[data-wz-icon]', box); if (c) B.paintIcon(c); };
        const bump = () => { const ph = prev?.querySelector('[data-phone]'); if (!ph || still()) return; ph.classList.remove('wz-bump'); void ph.offsetWidth; ph.classList.add('wz-bump'); };
        const draw = dir => {
            const steps = live(), s = steps[step];
            $('[data-wz-count]', box).textContent = `Step ${step + 1} of ${steps.length}`;
            $('[data-wz-bar]', box).style.width = `${((step + 1) / (steps.length + 1)) * 100}%`;
            const el = $('[data-wz-step]', box);
            el.innerHTML = `<div class="wz__in ${dir < 0 ? 'from-left' : dir > 0 ? 'from-right' : 'is-same'}"><h2 id="wz-title">${s.title}</h2><p class="wz__sub">${s.sub}</p>${s.draw()}</div>`;
            $('[data-wz-err]', box).hidden = true;
            $('[data-wz-back]', box).style.visibility = step ? 'visible' : 'hidden';
            $('[data-wz-next]', box).textContent = step === steps.length - 1 ? 'Finish ✨' : 'Next →';
            s.after?.();
            if (dir) setTimeout(() => el.querySelector('input:not([type=checkbox]):not([type=color]), [aria-pressed="true"], button')?.focus({ preventScroll: true }), still() ? 0 : 280);
        };
        const next = async () => {
            const steps = live(), s = steps[step], problem = s.ok?.() || '';
            if (problem) { const e = $('[data-wz-err]', box); e.textContent = problem; e.hidden = false; e.classList.remove('wz-shake'); void e.offsetWidth; e.classList.add('wz-shake'); return; }
            $('[data-wz-err]', box).hidden = true;
            const btn = $('[data-wz-next]', box); btn.disabled = true;
            try { await s.leave?.(); } finally { btn.disabled = false; }
            B.changed(); bump();
            if (step < live().length - 1) { step++; draw(1); } else finish();
        };
        const back = () => { if (step) { step--; draw(-1); } };

        /* ---------- the finish: a short "building" moment, then the app to tap through ---------- */
        const finish = () => {
            finished = true;
            try { localStorage.setItem(OFF, '1'); localStorage.setItem(OFF + '-made', '1'); } catch {} // they've made their app: from now on the page opens on the builder
            const tasks = ['Laying out your pages', 'Painting your colours', 'Drawing your icon', 'Polishing the animations'];
            // the step counter becomes a "Done" button that closes the wizard (like ✕ or Keep editing)
            $('[data-wz-count]', box).innerHTML = '<button type="button" class="wz__done" data-wz-close>✓ Done</button>';
            $('[data-wz-bar]', box).style.width = '100%';
            $('.wz__nav', box).hidden = true;
            const el = $('[data-wz-step]', box);
            el.innerHTML = `<div class="wz__in from-right wz-build"><div class="wz-orbit" aria-hidden="true"><span></span></div><h2 id="wz-title">Building your app…</h2>
                <ul class="wz-tasks">${tasks.map((t, i) => `<li style="--i:${i}"><i></i>${t}</li>`).join('')}</ul></div>`;
            const t = still() ? 0 : 1900;
            setTimeout(() => {
                if (!box) return;
                B.showPage(0); B.showApp(); bump(); // the app opens on its first screen (Home), like on a phone
                box.classList.add('is-done');
                el.innerHTML = `<div class="wz__in from-right wz-done">${still() ? '' : '<div class="wz-confetti" aria-hidden="true">' + Array.from({ length: 18 }, (_, i) => `<i style="--i:${i}"></i>`).join('') + '</div>'}
                    <div class="wz-done__badge" aria-hidden="true">🎉</div>
                    <h2 id="wz-title">Your app is ready to try</h2>
                    <p class="wz__sub">Tap around the phone preview — pages, buttons and the menu all work. Save it to keep it, then build it for Android when you’re happy.</p>
                    <div class="wz-done__actions">
                        <button type="button" class="btn btn--glow" data-wz-save>💾 Save my app</button>
                        <button type="button" class="btn btn--ghost" data-wz-edit>✏️ Keep editing</button>
                        <button type="button" class="ab-link" data-wz-again>Run quick setup again</button>
                    </div></div>`;
                $('[data-wz-save]', box).focus({ preventScroll: true });
            }, t);
        };

        /* ---------- answers go straight into the app ---------- */
        document.addEventListener('click', async e => {
            if (!box || !box.contains(e.target)) return;
            const t = e.target.closest('button, [data-wz-close]'); if (!t) return;
            const a = A(), D = B.state();
            if (t.matches('[data-wz-close]')) close();
            else if (t.matches('[data-wz-next]')) next();
            else if (t.matches('[data-wz-back]')) back();
            else if (t.dataset.kind) { kind = t.dataset.kind; box.querySelectorAll('[data-kind]').forEach(x => x.setAttribute('aria-pressed', String(x === t))); if (kind !== 'keep' && kind !== applied) { await B.useTemplate(kind); applied = kind; drop = new Set(); bump(); } $('[data-wz-err]', box).hidden = true; }
            else if (t.dataset.wzImode) { D.icon.mode = t.dataset.wzImode; box.querySelectorAll('[data-wz-imode]').forEach(x => x.setAttribute('aria-pressed', String(x === t))); $('[data-wz-letters]', box).hidden = D.icon.mode === 'image'; $('[data-wz-logo]', box).hidden = D.icon.mode !== 'image'; B.changed(true); paint(); }
            else if (t.dataset.pal) { a.colors.primary = a.colors.icon = t.dataset.pal; box.querySelectorAll('[data-pal]').forEach(x => x.setAttribute('aria-pressed', String(x === t))); $('[data-wz-color]', box).value = t.dataset.pal; B.changed(true); bump(); }
            else if (t.dataset.wzStyle) { const [k, v] = t.dataset.wzStyle.split(':'); a.style[k] = v; box.querySelectorAll(`[data-wz-style^="${k}:"]`).forEach(x => x.setAttribute('aria-pressed', String(x === t))); B.changed(); bump(); }
            else if (t.dataset.wzAdd !== undefined || t.matches('[data-wz-own-add]')) {
                const have = new Set(a.tabs.map(x => (x.label || '').trim().toLowerCase()));
                let page;
                if (t.dataset.wzAdd !== undefined) page = EXTRA.filter(([label]) => !have.has(label.toLowerCase()))[+t.dataset.wzAdd];
                else {
                    const name = ($('[data-wz-own]', box)?.value || '').trim();
                    if (!name) { $('[data-wz-own]', box)?.focus(); return; }
                    const used = a.tabs.map(x => x.icon), icon = ['star', 'grid', 'user', 'heart', 'book', 'bell', 'search', 'image'].find(k => !used.includes(k)) || 'star';
                    page = [name.slice(0, 20), icon, '', [{ type: 'heading', text: name.slice(0, 20) }, { type: 'text', text: 'Write something about this page.' }]];
                }
                if (page && B.addPage(page[0], page[1], page[3])) {
                    fresh = a.tabs.length - 1; B.changed(); bump(); // (the preview shows the new page)
                    const sc = $('[data-wz-step]', box).scrollTop; draw(0); $('[data-wz-step]', box).scrollTop = sc; fresh = -1;
                    $('.wz-page.is-new', box)?.scrollIntoView({ block: 'nearest', behavior: still() ? 'auto' : 'smooth' });
                }
            }
            else if (t.dataset.wzPlace) {
                const [i, where] = t.dataset.wzPlace.split(':'), on = t.getAttribute('aria-pressed') !== 'true', tab = a.tabs[+i];
                const other = where === 'bar' ? B.inMenu(tab, +i) : B.inBar(tab, +i);
                const say = msg => { const e = $('[data-wz-err]', box); e.textContent = msg; e.hidden = false; e.classList.remove('wz-shake'); void e.offsetWidth; e.classList.add('wz-shake'); };
                if (!on && !other) { say('Keep each page in the bottom bar or the menu (or both), so people can find it.'); return; }
                if (!B.place(+i, where, on)) { say(`The bottom bar holds ${B.maxBar} pages. Take another page out of it first — it can stay in the menu.`); return; }
                $('[data-wz-err]', box).hidden = true;
                B.changed(); B.showPage(+i); bump();
                const sc = $('[data-wz-step]', box).scrollTop; draw(0); $('[data-wz-step]', box).scrollTop = sc;
            }
            else if (t.matches('[data-wz-save]')) { t.disabled = true; try { if (await leave('save')) return; if (await B.save()) { close(); } } finally { t.disabled = false; } }
            else if (t.matches('[data-wz-edit]')) close();
            else if (t.matches('[data-wz-again]')) { box.classList.remove('is-done'); $('.wz__nav', box).hidden = false; step = 0; kind = 'keep'; draw(-1); }
        });
        // a click on a page's row in the wizard shows that page on the phone
        document.addEventListener('click', e => {
            if (!box || !box.contains(e.target)) return;
            const row = e.target.closest('.wz-page--place'); if (!row || e.target.closest('[data-wz-place]')) return;
            const i = +row.querySelector('[data-wz-page]')?.dataset.wzPage; if (Number.isInteger(i)) B.showPage(i);
        });
        document.addEventListener('focusin', e => { if (box && e.target.matches?.('[data-wz-page]')) B.showPage(+e.target.dataset.wzPage); });
        document.addEventListener('input', e => {
            if (!box || !box.contains(e.target)) return;
            const el = e.target, a = A(), D = B.state(), k = el.dataset.wz;
            if (k === 'name') { a.name = el.value; B.changed(D.icon.mode === 'letter' && !D.icon.letter); paint(); }
            else if (k === 'letter') { D.icon.letter = el.value.toUpperCase(); B.changed(true); paint(); }
            else if (k === 'whatsapp' || k === 'email') { a.shop[k] = el.value; blocksOf('contact').forEach(b => { b[k] = el.value; }); B.changed(); }
            else if (k === 'phone' || k === 'address') { blocksOf('contact').forEach(b => { b[k] = el.value; }); B.changed(); }
            else if (el.dataset.wzNum || el.dataset.wzCc) {
                const key = el.dataset.wzNum || el.dataset.wzCc, v = telValue(key);
                if (el.dataset.wzCc) { const iso = el.value; $(`[data-wz-ccshow="${key}"]`, box).textContent = `${flag(iso)} +${CODES.find(([c]) => c === iso)[1]}`; }
                if (key === 'whatsapp') { a.shop.whatsapp = v; blocksOf('contact').forEach(b => { b.whatsapp = v; }); } else blocksOf('contact').forEach(b => { b.phone = v; });
                B.changed();
            }
            else if (k === 'bank_details') { a.shop.pay.bank_details = el.value; B.changed(); }
            else if (el.matches('[data-wz-color]')) { a.colors.primary = a.colors.icon = el.value; box.querySelectorAll('[data-pal]').forEach(x => x.setAttribute('aria-pressed', 'false')); B.changed(true); }
            else if (el.dataset.wzPage) { const i = +el.dataset.wzPage; if (el.checked) drop.delete(i); else drop.add(i); el.closest('.wz-page')?.classList.toggle('is-off', !el.checked); }
            else if (el.dataset.wzPay) { a.shop.pay[el.dataset.wzPay] = el.checked; const bank = $('[data-wz-bank]', box); if (bank) bank.hidden = !a.shop.pay.bank; B.changed(); }
        });
        document.addEventListener('change', async e => {
            if (!box || !e.target.matches?.('[data-wz-file]')) return;
            await B.setLogo(e.target.files[0]); paint(); bump();
            const s = e.target.closest('label')?.querySelector('span'); if (s) s.textContent = '⬆️ Change logo';
        });
        document.addEventListener('keydown', e => {
            if (!box) return;
            if (e.key === 'Escape') close();
            else if (e.key === 'Enter' && e.target.matches?.('[data-wz-own]')) { e.preventDefault(); $('[data-wz-own-add]', box)?.click(); }
            else if (e.key === 'Enter' && e.target.matches?.('input:not([type=checkbox])')) { e.preventDefault(); next(); }
            else if (e.key === 'Tab') { // keep the keyboard inside the dialog
                const f = [...box.querySelectorAll('button:not([disabled]), input, textarea, select, [tabindex]:not([tabindex="-1"])')].filter(x => x.offsetParent);
                if (!f.length) return;
                if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
                else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
            }
        });

        /* ---------- the way in: the owl's banner on a first visit, and the button any time ---------- */
        // while the banner invites the visitor, the builder waits out of sight; it appears with the wizard or "full builder"
        const showBuilder = (rise = true) => {
            if (root.style.display !== 'none') return;
            root.style.display = '';
            // (the rise uses a transform, which would trap the wizard's full-screen layer inside the builder: never with the wizard)
            if (rise && !still()) { root.classList.remove('ab-reveal'); void root.offsetWidth; root.classList.add('ab-reveal'); root.addEventListener('animationend', () => root.classList.remove('ab-reveal'), { once: true }); }
            B.redraw(); // (drawn again now that it can be measured)
        };
        const hideBanner = (rise = true) => {
            showBuilder(rise);
            if (!banner || banner.hidden) return;
            banner.classList.add('is-leaving');
            document.documentElement.classList.remove('wzb-on');
            setTimeout(() => { banner.hidden = true; banner.classList.remove('is-leaving'); }, still() ? 0 : 380);
        };
        banner?.addEventListener('click', e => {
            if (e.target.closest('[data-wz-start]')) open();
            else if (e.target.closest('[data-wzb-no]')) { try { localStorage.setItem(OFF, '1'); if (banner.classList.contains('wzb--back')) sessionStorage.setItem(OFF + '-back', '1'); } catch {} hideBanner(); setTimeout(() => root.scrollIntoView({ behavior: still() ? 'auto' : 'smooth', block: 'start' }), 60); }
        });
        const actions = root.querySelector('.fb-top__actions');
        if (actions && !actions.querySelector('[data-wz-open]')) actions.insertAdjacentHTML('afterbegin', '<button type="button" class="btn btn--sm btn--ghost wz-open" data-wz-open>✨ Quick setup</button>');
        root.addEventListener('click', e => { if (e.target.closest('[data-wz-open]')) open(); });
        // a new app starts with the wizard, from the first question
        document.addEventListener('owl-app-new', () => { if (box) return; applied = null; open(); kind = null; draw(0); });
        /** Shows the owl's invitation (the builder waits out of sight), with its tips and the little phone changing colour. */
        let run = 0;
        function showBanner() {
            if (!banner) return;
            const me = ++run, live = () => me === run && !banner.hidden; // (timers from an earlier showing stop)
            banner.hidden = false;
            root.style.display = 'none';
            document.documentElement.classList.add('wzb-on');
            const watch = setInterval(() => { if (!live()) clearInterval(watch); else if (B.state().id) { hideBanner(); clearInterval(watch); } }, 1500); // an app was saved or opened
            if (still()) return;
            const colours = ['#1ec773', '#2563eb', '#e8590c', '#be185d', '#6d28d9'];
            let c = 0;
            const tint = setInterval(() => { if (!live()) { clearInterval(tint); return; } c = (c + 1) % colours.length; banner.style.setProperty('--wc', colours[c]); }, 6000);
            const lines = banner.classList.contains('wzb--back')
                ? ['Hoo! Welcome back — your app is right where you left it.', 'Press Resume to carry on in the editor.', 'Want a fresh start? The quick setup is right here too.']
                : ['Hoo! Want your own app? I’ll help you make it — it takes about 2 minutes.', 'Pick your kind of business and I’ll set up the pages for you.', 'You’ll see your app on the phone as you go. Ready? 🚀'];
            let n = 0; const say = banner.querySelector('[data-wzb-say]');
            const timer = setInterval(() => {
                if (!live() || !say) { clearInterval(timer); return; }
                n = (n + 1) % lines.length; say.parentNode.classList.remove('is-new'); void say.offsetWidth; say.textContent = lines[n]; say.parentNode.classList.add('is-new');
            }, 4200);
        }
        // the banner shows while no saved app is open, until the visitor says no thanks
        if (location.hash === '#then=save') { // "Save my app" pressed on the main page: do it here, once the draft is in
            try { history.replaceState(history.state, '', location.pathname + location.search); } catch {}
            let handed = ''; try { handed = sessionStorage.getItem('owlvix-then') || ''; sessionStorage.removeItem('owlvix-then'); } catch {}
            if (!editorUrl && handed === location.pathname + '#save') (B.ready || Promise.resolve()).then(() => B.save());
        }
        let off = false, backOff = false; try { off = !!localStorage.getItem(OFF); backOff = !!sessionStorage.getItem(OFF + '-back'); } catch {}
        // Back on the main page with an app of their own (made with the quick setup, or saved): the same banner,
        // welcoming them back, with "Resume" first. Not after "no thanks" alone, nor after "edit it here" this visit.
        if (banner && editorUrl && !backOff && (made() || B.state().id)) {
            banner.classList.add('wzb--back');
            const set = (sel, html) => { const el = banner.querySelector(sel); if (el) el.innerHTML = html; };
            set('.wzb__pill', '<i aria-hidden="true"></i>Saved in this browser');
            set('h2', 'Welcome back! <span class="accent">Your app is waiting</span>');
            set('.wzb__text > p', 'Pick up right where you left off — everything you made is still here. Or run the quick setup again to start fresh.');
            banner.querySelector('.wzb__steps')?.setAttribute('hidden', '');
            const go = banner.querySelector('[data-wz-start]');
            if (go) {
                go.insertAdjacentHTML('beforebegin', `<a class="btn btn--glow wzb__go" href="${esc(editorUrl)}" data-wz-resume>▶ Resume my app</a>`);
                go.className = 'btn btn--ghost wzb__again'; go.textContent = '✨ Quick setup again';
            }
            const no = banner.querySelector('[data-wzb-no]'); if (no) no.textContent = 'or edit it here on this page →';
            const say = banner.querySelector('[data-wzb-say]'); if (say) say.textContent = 'Hoo! Welcome back — your app is right where you left it.';
            showBanner();
        } else if (banner && !off && !B.state().id) showBanner();
    };
    if (window.OwlAppBuilder) start(); else document.addEventListener('owl-app-builder-ready', start);
})();
