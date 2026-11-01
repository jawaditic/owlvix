/* Quick setup wizard for the free builders (the look and feel of the mobile app builder's wizard, app-wizard.js).
   Each builder hands over its own steps, a live preview and its finish buttons:

     OwlWizard.mount({
       root,                        // the builder (kept out of sight while the owl's banner invites the visitor)
       banner,                      // [data-wz-banner] (resources/views/partials/wizard-banner.blade.php), or null
       offKey,                      // localStorage flag: set once the visitor finishes or says "use the full builder"
       say: ['…', '…'],             // the owl's lines on the banner
       hasSaved: () => bool,        // a saved project is open: no banner
       quickSlot,                   // where the "✨ Quick setup" button goes
       steps: [{ title, sub, draw() → html, after(box), ok() → '' | problem, leave() (async), skip(),
                 click(e, el, box), input(e, el, box), change(e, el, box) }],
       preview: { mount(side), unmount(), refresh() },   // shown beside the steps, updated as answers come in
       tasks: ['Laying out your pages', …],               // the short "building" moment
       done: { title, sub, actions: [{ label, cls, act(close), then }] },
       editorUrl,                   // on a builder's main page: its editor page (routes/web.php). Finishing goes there; a
                                    // finish button with `then` runs again on the editor page (#then=…)
       beforeLeave: () => bool,     // save everything now; false (couldn't keep it in the browser) = stay on this page
     }) → { open(fromStart), close(), refresh() }

   Answers go straight into the builder, so the full builder shows the same project afterwards, and running the
   wizard again starts from the project as it is. */
(() => {
    const still = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
    const $ = (s, el = document) => el.querySelector(s);

    const mount = cfg => {
        const { root, banner } = cfg;
        let box = null, step = 0, finished = false, fromBanner = false;
        const live = () => cfg.steps.filter(s => !s.skip?.());
        const bump = () => { cfg.preview?.refresh?.(); const ph = box && $('.wz-site, .wz-prev', box); if (!ph || still()) return; ph.classList.remove('wz-bump'); void ph.offsetWidth; ph.classList.add('wz-bump'); };

        /* ---------- the dialog ---------- */
        const open = (fromStart = false) => {
            if (box) return;
            fromBanner = !!banner && !banner.hidden; finished = false;
            hideBanner(false);
            step = 0;
            if (fromStart) cfg.onRestart?.();
            box = document.createElement('div');
            box.className = 'wz wz--builder' + (still() ? ' wz--still' : '');
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
            document.body.appendChild(box);
            document.documentElement.classList.add('wz-open');
            cfg.preview?.mount?.($('[data-wz-side]', box));
            requestAnimationFrame(() => box.classList.add('is-in'));
            draw(1);
        };
        /** Finished on a builder's main page: open its editor page, with the work saved in the browser first. */
        const leave = async then => {
            let kept = false;
            try { kept = (await cfg.beforeLeave?.()) !== false; } catch { kept = false; }
            if (!kept) return false;
            $('[data-wz-step]', box)?.insertAdjacentHTML('beforeend', '<p class="wz-leaving" role="status">Opening the editor…</p>');
            location.assign(cfg.editorUrl + (then ? '#then=' + encodeURIComponent(then) : ''));
            return true;
        };
        const close = async () => {
            if (!box) return;
            if (finished && cfg.editorUrl && await leave()) return;
            cfg.preview?.unmount?.();
            const b = box; box = null;
            document.documentElement.classList.remove('wz-open');
            b.classList.remove('is-in');
            setTimeout(() => b.remove(), still() ? 0 : 320);
            cfg.onClose?.(finished);
            // closed before the end: back to the owl's invitation; finished: the builder, with everything filled in
            if (fromBanner && !finished && !cfg.hasSaved?.()) showBanner();
            else if (fromBanner) setTimeout(() => root.scrollIntoView({ behavior: still() ? 'auto' : 'smooth', block: 'start' }), 80);
            fromBanner = false;
        };
        const draw = dir => {
            const steps = live(), s = steps[step];
            $('[data-wz-count]', box).textContent = `Step ${step + 1} of ${steps.length}`;
            $('[data-wz-bar]', box).style.width = `${((step + 1) / (steps.length + 1)) * 100}%`;
            const el = $('[data-wz-step]', box);
            el.innerHTML = `<div class="wz__in ${dir < 0 ? 'from-left' : dir > 0 ? 'from-right' : 'is-same'}"><h2 id="wz-title">${s.title}</h2><p class="wz__sub">${s.sub}</p>${s.draw()}</div>`;
            $('[data-wz-err]', box).hidden = true;
            $('[data-wz-back]', box).style.visibility = step ? 'visible' : 'hidden';
            $('[data-wz-next]', box).textContent = step === steps.length - 1 ? 'Finish ✨' : 'Next →';
            s.after?.(box);
            if (dir) setTimeout(() => el.querySelector('input:not([type=checkbox]):not([type=color]):not([type=file]), [aria-pressed="true"], button')?.focus({ preventScroll: true }), still() ? 0 : 280);
        };
        const redraw = () => box && !finished && draw(0);
        const next = async () => {
            const steps = live(), s = steps[step], problem = s.ok?.() || '';
            if (problem) { const e = $('[data-wz-err]', box); e.textContent = problem; e.hidden = false; e.classList.remove('wz-shake'); void e.offsetWidth; e.classList.add('wz-shake'); return; }
            $('[data-wz-err]', box).hidden = true;
            const btn = $('[data-wz-next]', box); btn.disabled = true;
            try { await s.leave?.(); } finally { btn.disabled = false; }
            bump();
            if (step < live().length - 1) { step++; draw(1); } else finish();
        };
        const back = () => { if (step) { step--; draw(-1); } };

        /* ---------- the finish: a short "building" moment, then the result to look at ---------- */
        const finish = () => {
            finished = true;
            try { localStorage.setItem(cfg.offKey, '1'); } catch {} // from now on the page opens on the builder
            const tasks = cfg.tasks || ['Laying out your pages', 'Painting your colours', 'Adding your details', 'Polishing the details'];
            $('[data-wz-count]', box).innerHTML = '<button type="button" class="wz__done" data-wz-close>✓ Done</button>';
            $('[data-wz-bar]', box).style.width = '100%';
            $('.wz__nav', box).hidden = true;
            const el = $('[data-wz-step]', box);
            el.innerHTML = `<div class="wz__in from-right wz-build"><div class="wz-orbit" aria-hidden="true"><span></span></div><h2 id="wz-title">${cfg.building || 'Building it…'}</h2>
                <ul class="wz-tasks">${tasks.map((t, i) => `<li style="--i:${i}"><i></i>${t}</li>`).join('')}</ul></div>`;
            setTimeout(() => {
                if (!box) return;
                cfg.onFinish?.(); bump();
                box.classList.add('is-done');
                const d = cfg.done;
                el.innerHTML = `<div class="wz__in from-right wz-done">${still() ? '' : '<div class="wz-confetti" aria-hidden="true">' + Array.from({ length: 18 }, (_, i) => `<i style="--i:${i}"></i>`).join('') + '</div>'}
                    <div class="wz-done__badge" aria-hidden="true">🎉</div>
                    <h2 id="wz-title">${d.title}</h2>
                    <p class="wz__sub">${d.sub}</p>
                    <div class="wz-done__actions">
                        ${d.actions.map((a, i) => `<button type="button" class="${a.cls || 'btn btn--ghost'}" data-wz-act="${i}">${a.label}</button>`).join('')}
                        <button type="button" class="ab-link" data-wz-again>Run quick setup again</button>
                    </div></div>`;
                $('[data-wz-act="0"]', box)?.focus({ preventScroll: true });
            }, still() ? 0 : 1900);
        };

        /* ---------- events: the engine's own buttons, then the step's handlers ---------- */
        document.addEventListener('click', async e => {
            if (!box || !box.contains(e.target)) return;
            const t = e.target.closest('button, [data-wz-close], [data-wz-pick], label'); if (!t) return;
            if (t.matches('[data-wz-close]')) return close();
            if (t.matches('[data-wz-next]')) return next();
            if (t.matches('[data-wz-back]')) return back();
            if (t.matches('[data-wz-again]')) { finished = false; box.classList.remove('is-done'); $('.wz__nav', box).hidden = false; step = 0; return draw(-1); }
            if (t.dataset.wzAct !== undefined) {
                const a = cfg.done.actions[+t.dataset.wzAct];
                if (cfg.editorUrl && await leave(a.then)) return; // the button runs on the editor page
                return a.act(close);
            }
            if (finished) return;
            const s = live()[step]; if (!s?.click) return;
            await s.click(e, t, box);
            $('[data-wz-err]', box).hidden = true;
        });
        document.addEventListener('input', e => { if (box && box.contains(e.target) && !finished) live()[step]?.input?.(e, e.target, box); });
        document.addEventListener('change', e => { if (box && box.contains(e.target) && !finished) live()[step]?.change?.(e, e.target, box); });
        document.addEventListener('keydown', e => {
            if (!box) return;
            if (e.key === 'Escape' && !document.querySelector('dialog[open]')) close();
            else if (e.key === 'Enter' && e.target.matches?.('input:not([type=checkbox]):not([type=file])') && !e.target.closest('[data-wz-noenter]')) { e.preventDefault(); if (!finished) next(); }
            else if (e.key === 'Tab') { // keep the keyboard inside the dialog
                const f = [...box.querySelectorAll('button:not([disabled]), input, textarea, select, [tabindex]:not([tabindex="-1"])')].filter(x => x.offsetParent);
                if (!f.length) return;
                if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
                else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
            }
        });

        /* ---------- the way in: the owl's banner on a first visit, and the button any time ---------- */
        const showBuilder = () => { if (root.style.display === 'none') { root.style.display = ''; cfg.onShowBuilder?.(); } };
        const hideBanner = () => {
            showBuilder();
            if (!banner || banner.hidden) return;
            banner.classList.add('is-leaving');
            document.documentElement.classList.remove('wzb-on');
            setTimeout(() => { banner.hidden = true; banner.classList.remove('is-leaving'); }, still() ? 0 : 380);
        };
        banner?.addEventListener('click', e => {
            if (e.target.closest('[data-wz-start]')) open();
            else if (e.target.closest('[data-wzb-no]')) { try { localStorage.setItem(cfg.offKey, '1'); } catch {} hideBanner(); setTimeout(() => root.scrollIntoView({ behavior: still() ? 'auto' : 'smooth', block: 'start' }), 60); }
        });
        if (cfg.quickSlot && !cfg.quickSlot.querySelector('[data-wz-open]')) cfg.quickSlot.insertAdjacentHTML('afterbegin', `<button type="button" class="${cfg.quickClass || 'btn btn--sm btn--ghost'} wz-open" data-wz-open>✨ Quick setup</button>`);
        root.addEventListener('click', e => { if (e.target.closest('[data-wz-open]')) open(); });
        let run = 0;
        function showBanner() {
            if (!banner) return;
            const me = ++run, alive = () => me === run && !banner.hidden;
            banner.hidden = false;
            root.style.display = 'none';
            document.documentElement.classList.add('wzb-on');
            const watch = setInterval(() => { if (!alive()) clearInterval(watch); else if (cfg.hasSaved?.()) { hideBanner(); clearInterval(watch); } }, 1500);
            if (still()) return;
            const colours = ['#1ec773', '#2563eb', '#e8590c', '#be185d', '#6d28d9'];
            let c = 0;
            const tint = setInterval(() => { if (!alive()) { clearInterval(tint); return; } c = (c + 1) % colours.length; banner.style.setProperty('--wc', colours[c]); }, 6000);
            const lines = cfg.say || [];
            let n = 0; const say = banner.querySelector('[data-wzb-say]');
            const timer = setInterval(() => {
                if (!alive() || !say || !lines.length) { clearInterval(timer); return; }
                n = (n + 1) % lines.length; say.parentNode.classList.remove('is-new'); void say.offsetWidth; say.textContent = lines[n]; say.parentNode.classList.add('is-new');
            }, 4200);
        }
        // Arrived on the editor page from a finish button on the main page (#then=save, …): run it here
        const then = decodeURIComponent((location.hash.match(/^#then=([a-z-]{1,20})$/) || [])[1] || '');
        if (then) {
            try { history.replaceState(history.state, '', location.pathname + location.search); } catch {}
            const a = cfg.done?.actions?.find(x => x.then === then);
            if (a) setTimeout(() => a.act(() => {}), 500);
        }
        let off = false; try { off = !!localStorage.getItem(cfg.offKey); } catch {}
        if (banner && !off && !cfg.hasSaved?.()) showBanner();

        return { open, close, redraw, refresh: bump, isOpen: () => !!box };
    };

    /* ---------- shared helpers for the steps ---------- */
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const PALETTES = [['Forest', '#16a34a'], ['Ocean', '#2563eb'], ['Sunset', '#e8590c'], ['Berry', '#be185d'], ['Royal', '#6d28d9'], ['Coffee', '#7c4a2d'], ['Teal', '#0d9488'], ['Midnight', '#1e1b49']];
    /** Big cards to pick one of (data-wz-pick="value"). */
    const cards = (items, current) => `<div class="wz-grid wz-grid--types">${items.map(([v, em, name, small], i) => `<button type="button" class="wz-card" data-wz-pick="${esc(v)}" aria-pressed="${current === v}" style="--i:${i}"><span class="wz-card__em">${em}</span><b>${esc(name)}</b>${small ? `<small>${esc(small)}</small>` : ''}</button>`).join('')}</div>`;
    /** The colour swatches (data-pal) and a colour input (data-wz-color). */
    const palette = (current, label = 'Or your own colour') => `<div class="wz-grid wz-grid--pal">${PALETTES.map(([n, c], i) => `<button type="button" class="wz-swatch" data-pal="${c}" aria-pressed="${String(current).toLowerCase() === c}" style="--c:${c};--i:${i}"><i></i>${n}</button>`).join('')}</div>
        <label class="wz-field wz-field--row">${label}<input type="color" data-wz-color value="${esc(/^#[0-9a-f]{6}$/i.test(current) ? current : '#16a34a')}"></label>`;
    /** Mark one choice pressed in a group. */
    const press = (box, sel, el) => box.querySelectorAll(sel).forEach(x => x.setAttribute('aria-pressed', String(x === el)));
    /** A phone-shaped preview frame holding an iframe (for pages and emails). */
    const phoneFrame = () => '<div class="wz-site"><div class="wz-site__screen"><iframe title="Preview" data-wz-frame></iframe></div><div class="wz-site__bar"><i></i></div></div>';

    window.OwlWizard = { mount, esc, cards, palette, press, phoneFrame, PALETTES };
    document.dispatchEvent(new Event('owl-wizard-ready'));
})();
