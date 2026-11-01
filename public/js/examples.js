/* Example designs gallery (/examples). Fills each [data-examples="kind"] grid with every ready design of that builder:
   a live thumbnail drawn by the page engine (window.OwlB), a full-size preview and a "Use this design" link that opens
   the builder on it (?design=key, read by landing-builder.js). Previews run in sandboxed frames with no access to this page. */
(() => {
    const B = window.OwlB;
    const root = document.querySelector('[data-ex]');
    if (!B || !root) return;
    const RUNTIME = root.dataset.runtime;
    const esc = B.esc;

    /** kind → [examples, make a project, builder path] */
    const kindOf = kind => kind === 'landing' ? [B.EXAMPLES, k => B.newProject(k), 'landing-page-builder']
        : kind === 'funnel' ? [B.FUNNELS, k => B.newFunnel(k), B.KIT_PATH.funnel]
        : B.KITS[kind] ? [B.KITS[kind].examples, k => B.newKit(kind, k), B.KIT_PATH[kind]] : null;

    // Thumbnails: top of the page only, no pop-ups or motion, nothing clickable
    const THUMB_CSS = '<style>html,body{overflow:hidden!important;pointer-events:none}.popup,[data-popup]{display:none!important}[data-anim]{opacity:1!important;transform:none!important;transition:none!important}</style>';
    const PREVIEW_CSS = '<style>.popup,[data-popup]{display:none!important}</style>';
    const html = (kind, key, css) => {
        const [ex, make] = kindOf(kind);
        if (!ex[key]) return '';
        try { const P = make(key); return B.render(P, P.pages[0], 'download', { runtime: RUNTIME, extraHead: css }); } catch { return ''; }
    };
    const frame = (src, title) => {
        const f = document.createElement('iframe');
        f.setAttribute('sandbox', 'allow-scripts'); // its own origin: the design can't touch this page
        f.title = title; f.srcdoc = src;
        return f;
    };
    const useUrl = (path, key) => `/${path}?design=${encodeURIComponent(key)}`;

    /* ---------- the cards ---------- */
    root.querySelectorAll('[data-examples]').forEach(grid => {
        const kind = grid.dataset.examples, k = kindOf(kind);
        if (!k) return;
        const [ex, , path] = k, builder = grid.dataset.builder || 'builder';
        grid.innerHTML = Object.entries(ex).map(([key, [name]]) => `
            <article class="ex-card card">
                <button type="button" class="ex-shot" data-ex-open="${esc(kind)}:${esc(key)}" data-ex-thumb="${esc(kind)}:${esc(key)}" aria-label="Preview the ${esc(name)} design"></button>
                <div class="ex-card__body">
                    <strong>${esc(name)}</strong><small>${esc(builder)}</small>
                    <div class="ex-card__btns">
                        <button type="button" class="btn btn--sm btn--ghost" data-ex-open="${esc(kind)}:${esc(key)}">Preview</button>
                        <a class="btn btn--sm btn--glow" href="${useUrl(path, key)}">Use this design</a>
                    </div>
                </div>
            </article>`).join('');
    });

    const thumbs = new IntersectionObserver(entries => entries.forEach(en => {
        if (!en.isIntersecting) return;
        const el = en.target; thumbs.unobserve(el);
        const [kind, key] = el.dataset.exThumb.split(':');
        const src = html(kind, key, THUMB_CSS); if (!src) return;
        const f = frame(src, '');
        f.setAttribute('tabindex', '-1'); f.setAttribute('aria-hidden', 'true');
        const fit = () => { const s = el.clientWidth / 1200; f.style.transform = `scale(${s})`; f.style.height = Math.ceil(el.clientHeight / s) + 'px'; };
        fit(); new ResizeObserver(fit).observe(el);
        f.onload = () => el.classList.add('is-ready');
        el.appendChild(f);
    }), { rootMargin: '300px 0px' });
    root.querySelectorAll('[data-ex-thumb]').forEach(el => thumbs.observe(el));

    /* ---------- full-size preview ---------- */
    const dlg = document.querySelector('[data-ex-dlg]');
    let opener = null;
    root.addEventListener('click', e => {
        const b = e.target.closest('[data-ex-open]'); if (!b) return;
        const [kind, key] = b.dataset.exOpen.split(':');
        const [ex, , path] = kindOf(kind);
        const src = html(kind, key, PREVIEW_CSS); if (!src) return;
        opener = b;
        dlg.querySelector('[data-ex-name]').textContent = ex[key][0];
        dlg.querySelector('[data-ex-use]').href = useUrl(path, key);
        const box = dlg.querySelector('[data-ex-frame]');
        box.replaceChildren(frame(src, `Preview of the ${ex[key][0]} design`));
        setDevice(window.innerWidth < 760 ? 'mobile' : 'desktop');
        dlg.showModal();
        window.track?.('example_preview', { kind, key });
    });
    const setDevice = d => {
        dlg.querySelector('[data-ex-frame]').classList.toggle('is-mobile', d === 'mobile');
        dlg.querySelectorAll('[data-ex-device]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.exDevice === d)));
    };
    dlg.addEventListener('click', e => {
        const d = e.target.closest('[data-ex-device]'); if (d) return setDevice(d.dataset.exDevice);
        if (e.target.closest('[data-ex-close]') || e.target === dlg) dlg.close();
    });
    dlg.addEventListener('close', () => { dlg.querySelector('[data-ex-frame]').replaceChildren(); opener?.focus(); });

    /* ---------- filter chips ---------- */
    const chips = document.querySelectorAll('[data-ex-filter]');
    chips.forEach(c => c.addEventListener('click', () => {
        const want = c.dataset.exFilter;
        chips.forEach(x => x.setAttribute('aria-pressed', String(x === c)));
        root.querySelectorAll('[data-ex-group]').forEach(g => (g.hidden = want !== 'all' && g.dataset.exGroup !== want));
    }));
})();
