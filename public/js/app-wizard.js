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
        const SEEN = 'owlvix-app-wizard-seen';

        const EMOJI = { restaurant: '🍽️', cafe: '☕', salon: '💇', gym: '🏋️', shop: '🛍️', clinic: '🩺', realestate: '🏡', events: '🎟️', agency: '💼', church: '⛪', school: '🎓', hotel: '🏨' };
        const PALETTES = [
            ['Forest', '#16a34a'], ['Ocean', '#2563eb'], ['Sunset', '#e8590c'], ['Berry', '#be185d'],
            ['Royal', '#6d28d9'], ['Coffee', '#7c4a2d'], ['Teal', '#0d9488'], ['Midnight', '#1e1b49'],
        ];
        const LOOKS = [
            ['theme', 'Light or dark', [['light', '☀️ Light'], ['dark', '🌙 Dark'], ['auto', '🌓 Follow the phone']]],
            ['corners', 'Corners', [['rounded', 'Rounded'], ['soft', 'Soft'], ['square', 'Square']]],
            ['font', 'Font', [['modern', 'Modern'], ['classic', 'Classic'], ['condensed', 'Condensed'], ['plain', 'Plain']]],
            ['nav', 'Bottom bar', [['floating', 'Floating'], ['classic', 'Classic']]],
        ];

        let step = 0, kind = null, applied = null, drop = new Set(), box, home, prev, prevNext;
        const A = () => B.app();
        const blocksOf = type => A().tabs.flatMap(t => t.blocks || []).filter(b => b.type === type);
        const sells = () => blocksOf('products').length > 0;

        /* ---------- the steps: each draws its question, and can apply its answer when leaving ---------- */
        const STEPS = [
            {
                title: 'What kind of business is it?', sub: 'We’ll start you with pages made for it — you can change everything.',
                draw: () => {
                    const keep = B.current() || applied || kind === 'keep';
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
                draw: () => `<div class="wz-pages">${A().tabs.map((t, i) => `<label class="wz-page" style="--i:${i}"><input type="checkbox" data-wz-page="${i}" ${drop.has(i) ? '' : 'checked'}><span class="wz-page__ic">${B.svgIcon(t.icon, 'currentColor', 20)}</span><b>${esc(t.label || 'Page')}</b><small>${t.type === 'web' ? 'Website page' : `${(t.blocks || []).length} block${(t.blocks || []).length === 1 ? '' : 's'}`}</small></label>`).join('')}</div>`,
                ok: () => (drop.size >= A().tabs.length ? 'Keep at least one page.' : ''),
                leave: () => { if (drop.size) { B.removePages([...drop]); drop = new Set(); B.changed(); } },
            },
            {
                title: 'How do customers reach you?', sub: 'Shown on your contact page; orders and bookings are sent here too.',
                draw: () => {
                    const c = blocksOf('contact')[0] || {};
                    return `<div class="wz-2">
                        <label class="wz-field">WhatsApp<input data-wz="whatsapp" type="tel" maxlength="25" value="${esc(A().shop.whatsapp || c.whatsapp || '')}" placeholder="+44 7700 900123"></label>
                        <label class="wz-field">Email<input data-wz="email" type="email" maxlength="150" value="${esc(A().shop.email || (c.email !== 'hello@example.com' ? c.email : '') || '')}" placeholder="hello@yourbusiness.com"></label>
                        <label class="wz-field">Phone<input data-wz="phone" type="tel" maxlength="30" value="${esc(c.phone && c.phone !== '+1 555 0100' ? c.phone : '')}" placeholder="+44 20 7946 0000"></label>
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
            try { localStorage.setItem(SEEN, '1'); } catch {}
            step = 0; kind = B.current() ? 'keep' : null; drop = new Set();
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
        const close = () => {
            if (!box) return;
            home.insertBefore(prev, prevNext);
            B.changed();
            const b = box; box = null;
            document.documentElement.classList.remove('wz-open');
            b.classList.remove('is-in');
            setTimeout(() => b.remove(), still() ? 0 : 320);
        };
        const paint = () => { const c = box && $('[data-wz-icon]', box); if (c) B.paintIcon(c); };
        const bump = () => { const ph = prev?.querySelector('[data-phone]'); if (!ph || still()) return; ph.classList.remove('wz-bump'); void ph.offsetWidth; ph.classList.add('wz-bump'); };
        const draw = dir => {
            const steps = live(), s = steps[step];
            $('[data-wz-count]', box).textContent = `Step ${step + 1} of ${steps.length}`;
            $('[data-wz-bar]', box).style.width = `${((step + 1) / (steps.length + 1)) * 100}%`;
            const el = $('[data-wz-step]', box);
            el.innerHTML = `<div class="wz__in ${dir < 0 ? 'from-left' : 'from-right'}"><h2 id="wz-title">${s.title}</h2><p class="wz__sub">${s.sub}</p>${s.draw()}</div>`;
            $('[data-wz-err]', box).hidden = true;
            $('[data-wz-back]', box).style.visibility = step ? 'visible' : 'hidden';
            $('[data-wz-next]', box).textContent = step === steps.length - 1 ? 'Finish ✨' : 'Next →';
            s.after?.();
            setTimeout(() => el.querySelector('input:not([type=checkbox]):not([type=color]), [aria-pressed="true"], button')?.focus({ preventScroll: true }), still() ? 0 : 280);
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
            const tasks = ['Laying out your pages', 'Painting your colours', 'Drawing your icon', 'Polishing the animations'];
            $('[data-wz-count]', box).textContent = 'Done';
            $('[data-wz-bar]', box).style.width = '100%';
            $('.wz__nav', box).hidden = true;
            const el = $('[data-wz-step]', box);
            el.innerHTML = `<div class="wz__in from-right wz-build"><div class="wz-orbit" aria-hidden="true"><span></span></div><h2 id="wz-title">Building your app…</h2>
                <ul class="wz-tasks">${tasks.map((t, i) => `<li style="--i:${i}"><i></i>${t}</li>`).join('')}</ul></div>`;
            const t = still() ? 0 : 1900;
            setTimeout(() => {
                if (!box) return;
                B.showApp(); bump();
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
            else if (t.dataset.kind) { kind = t.dataset.kind; box.querySelectorAll('[data-kind]').forEach(x => x.setAttribute('aria-pressed', String(x === t))); if (kind !== 'keep' && kind !== applied) { await B.useTemplate(kind); applied = kind; drop = new Set(); bump(); } setTimeout(next, still() ? 0 : 260); }
            else if (t.dataset.wzImode) { D.icon.mode = t.dataset.wzImode; box.querySelectorAll('[data-wz-imode]').forEach(x => x.setAttribute('aria-pressed', String(x === t))); $('[data-wz-letters]', box).hidden = D.icon.mode === 'image'; $('[data-wz-logo]', box).hidden = D.icon.mode !== 'image'; B.changed(true); paint(); }
            else if (t.dataset.pal) { a.colors.primary = a.colors.icon = t.dataset.pal; box.querySelectorAll('[data-pal]').forEach(x => x.setAttribute('aria-pressed', String(x === t))); $('[data-wz-color]', box).value = t.dataset.pal; B.changed(true); bump(); }
            else if (t.dataset.wzStyle) { const [k, v] = t.dataset.wzStyle.split(':'); a.style[k] = v; box.querySelectorAll(`[data-wz-style^="${k}:"]`).forEach(x => x.setAttribute('aria-pressed', String(x === t))); B.changed(); bump(); }
            else if (t.matches('[data-wz-save]')) { t.disabled = true; try { if (await B.save()) { close(); } } finally { t.disabled = false; } }
            else if (t.matches('[data-wz-edit]')) close();
            else if (t.matches('[data-wz-again]')) { box.classList.remove('is-done'); $('.wz__nav', box).hidden = false; step = 0; kind = 'keep'; draw(-1); }
        });
        document.addEventListener('input', e => {
            if (!box || !box.contains(e.target)) return;
            const el = e.target, a = A(), D = B.state(), k = el.dataset.wz;
            if (k === 'name') { a.name = el.value; B.changed(D.icon.mode === 'letter' && !D.icon.letter); paint(); }
            else if (k === 'letter') { D.icon.letter = el.value.toUpperCase(); B.changed(true); paint(); }
            else if (k === 'whatsapp' || k === 'email') { a.shop[k] = el.value; blocksOf('contact').forEach(b => { b[k] = el.value; }); B.changed(); }
            else if (k === 'phone' || k === 'address') { blocksOf('contact').forEach(b => { b[k] = el.value; }); B.changed(); }
            else if (k === 'bank_details') { a.shop.pay.bank_details = el.value; B.changed(); }
            else if (el.matches('[data-wz-color]')) { a.colors.primary = a.colors.icon = el.value; box.querySelectorAll('[data-pal]').forEach(x => x.setAttribute('aria-pressed', 'false')); B.changed(true); }
            else if (el.dataset.wzPage) { const i = +el.dataset.wzPage; if (el.checked) drop.delete(i); else drop.add(i); }
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
            else if (e.key === 'Enter' && e.target.matches?.('input:not([type=checkbox])')) { e.preventDefault(); next(); }
            else if (e.key === 'Tab') { // keep the keyboard inside the dialog
                const f = [...box.querySelectorAll('button:not([disabled]), input, textarea, select, [tabindex]:not([tabindex="-1"])')].filter(x => x.offsetParent);
                if (!f.length) return;
                if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
                else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
            }
        });

        /* ---------- the way in: a button, and on a first visit it opens by itself ---------- */
        const actions = root.querySelector('.fb-top__actions');
        if (actions && !actions.querySelector('[data-wz-open]')) actions.insertAdjacentHTML('afterbegin', '<button type="button" class="btn btn--sm btn--ghost wz-open" data-wz-open>✨ Quick setup</button>');
        root.addEventListener('click', e => { if (e.target.closest('[data-wz-open]')) open(); });
        let seen = true; try { seen = !!localStorage.getItem(SEEN); } catch {}
        if (!seen && !B.state().id && !new URLSearchParams(location.search).has('stripe')) setTimeout(() => { if (!B.state().id) open(); }, 900);
    };
    if (window.OwlAppBuilder) start(); else document.addEventListener('owl-app-builder-ready', start);
})();
