/* Owlvix free page builder — the editor (/landing-page-builder). Elementor-style: sections hold columns, columns
   hold widgets; every element has Content, Style (per device) and Advanced settings. Click text to type, click an
   image to replace it, drag widgets in from the panel or drag them around the page. The page model and rendering
   live in builder-engine.js (window.OwlB); interactive widgets on finished pages use builder-runtime.js.
   The canvas is a same-origin iframe: listeners are attached from here, so the page itself carries no editor code. */
(() => {
    const root = document.querySelector('[data-lpb]');
    const B = window.OwlB;
    if (!root || !B) return;
    const { uid, esc, clone, hex, slugify, getPath, setPath, styleKey, W } = B;
    const RUNTIME = root.dataset.runtime || location.origin + '/js/builder-runtime.js';
    const FUNNEL = root.dataset.mode === 'funnel'; // /funnel-builder: steps, opt-in forms, payment links, A/B, results
    // Kits (/website-builder, /portfolio-builder, /link-in-bio-builder, /coming-soon-page-builder): same editor, own examples
    const KIT = !FUNNEL && B.KITS[root.dataset.mode] ? root.dataset.mode : '';
    const STORE = FUNNEL ? 'owlvix-funnel' : KIT ? 'owlvix-' + KIT : 'owlvix-builder';
    const EXAMPLES = FUNNEL ? B.FUNNELS : KIT ? B.KITS[KIT].examples : B.EXAMPLES;
    const fresh = key => (FUNNEL ? B.newFunnel(key) : KIT ? B.newKit(KIT, key) : B.newProject(key));
    const STATS_URL = root.dataset.statsUrl || location.origin + '/fs';
    const SITE_BASE = root.dataset.siteBase || location.origin + '/p/'; // where published sites live, e.g. https://sites.owlvix.com/
    const SITE_HOST = SITE_BASE.replace(/^https?:\/\//, '');

    /* =========================================================== small helpers */
    const $ = (s, el = root) => el.querySelector(s);
    const $$ = (s, el = root) => [...el.querySelectorAll(s)];
    const toast = (msg, kind) => { if (window.owlToast) return window.owlToast(msg, kind); const t = $('[data-toast]'); t.textContent = msg; t.hidden = false; clearTimeout(toast.t); toast.t = setTimeout(() => (t.hidden = true), 2600); };
    const track = (e, d = {}) => window.track?.(e, d);
    const svg = d => `<svg viewBox="0 0 24 24" aria-hidden="true">${d}</svg>`;
    const ICON = {
        up: svg('<path d="M12 19V5M6 11l6-6 6 6"/>'), down: svg('<path d="M12 5v14M6 13l6 6 6-6"/>'), drag: svg('<path d="M9 5h.01M15 5h.01M9 12h.01M15 12h.01M9 19h.01M15 19h.01"/>'),
        copy: svg('<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>'),
        trash: svg('<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>'),
        edit: svg('<path d="M4 20h4L19 9l-4-4L4 16zM14 6l4 4"/>'), save: svg('<path d="M12 3l2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.6 6.6 19.5l1.2-6L3.3 9.3l6.1-.7z"/>'),
        cols: svg('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M12 4v16"/>'),
    };
    /** Owlvix's own icon set (public/js/icons.js, loaded in <head>) → inline <svg>, for HTML strings only. */
    const oi = (name, o) => (window.OwlIcon ? window.OwlIcon(name, o) : '');
    /** Block glyphs from the engine: an icon name → our icon; anything else (H, ¶, 123…) as text. */
    const glyph = v => (window.OwlIcon?.names?.includes(v) ? oi(v) : esc(v));

    /* Styled dialogs in place of confirm()/prompt(): the shared js/sweet.js. sweet({icon,title,text,confirm,cancel,danger,input,html}) → Promise */
    const sweet = opts => window.sweet(opts);

    /* =========================================================== state + history */
    let P = null;
    try { P = JSON.parse(localStorage.getItem(STORE) || 'null'); } catch {}
    try { P = P && P.pages?.length ? B.migrate(P) : null; if (P) P.pages.forEach(pg => B.render(P, pg, 'edit')); } catch {
        try { localStorage.setItem(STORE + '-broken', localStorage.getItem(STORE) || ''); } catch {} // keep it, just in case
        P = null;
    }
    // ?design=key (the "Use this design" links on /examples): open on that ready design. Work someone has already
    // edited is never replaced without asking (see the start, below).
    const DESIGN = (() => { const k = new URLSearchParams(location.search).get('design'); return k && Object.hasOwn(EXAMPLES, k) ? k : ''; })();
    let askDesign = false;
    if (DESIGN) {
        let touched = false; try { touched = !!localStorage.getItem(STORE + '-touched') || !!JSON.parse(localStorage.getItem(STORE + '-cloud') || '{}')?.id; } catch {}
        if (P && touched) askDesign = true; // edited work, or a project opened from the account: ask first
        else {
            const keep = P ? Object.fromEntries(B.ACCOUNT_KEYS.map(k => [k, P.site[k]])) : {}; P = fresh(DESIGN); Object.keys(keep).forEach(k => keep[k] && (P.site[k] = keep[k]));
            try { sessionStorage.setItem('owlvix-design', DESIGN); } catch {} // counted by site.js (Admin → Growth)
        }
        try { const u = new URL(location.href); u.searchParams.delete('design'); history.replaceState(history.state, '', u); } catch {}
    }
    if (!P) P = fresh(FUNNEL ? 'leadmagnet' : KIT ? Object.keys(EXAMPLES)[0] : 'agency');
    let pageIdx = 0, device = 'desktop', variant = 'a'; // variant: which A/B version of the step is being edited
    let sel = null; // { kind: 'section'|'col'|'widget', id, target }
    const DEV = () => ({ desktop: 'd', tablet: 't', mobile: 'm' })[device];
    /** The page (funnel step) being edited. When editing version B of an A/B step, its sections are the B ones. */
    const page = () => {
        const pg = P.pages[pageIdx] || P.pages[0];
        if (variant !== 'b' || !pg.ab?.on) return pg;
        return new Proxy(pg, { get: (t, k) => (k === 'sections' ? (t.sectionsB ||= t.sections.map(B.reId)) : t[k]), set: (t, k, v) => { if (k === 'sections') t.sectionsB = v; else t[k] = v; return true; } });
    };

    /** Find any element on the current page by id. */
    const find = id => {
        const secs = page().sections;
        for (let si = 0; si < secs.length; si++) {
            const s = secs[si];
            if (s.id === id) return { kind: 'section', obj: s, sec: s, si };
            for (let ci = 0; ci < s.cols.length; ci++) {
                const c = s.cols[ci];
                if (c.id === id) return { kind: 'col', obj: c, sec: s, col: c, si, ci };
                const wi = c.widgets.findIndex(w => w.id === id);
                if (wi >= 0) return { kind: 'widget', obj: c.widgets[wi], sec: s, col: c, si, ci, wi };
            }
        }
        return null;
    };

    let undo = [], redo = [], last = JSON.stringify(P), saveTimer = 0;
    const persist = () => {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            cloudStatus();
            try { localStorage.setItem(STORE, JSON.stringify(P)); $('[data-autosave]').textContent = 'Saved on this device'; }
            catch { $('[data-autosave]').textContent = 'Too big to keep in this browser — save it to your account with the Save button'; }
        }, 400);
    };
    const commit = () => {
        const now = JSON.stringify(P);
        if (now === last) return;
        undo.push(last);
        let total = undo.reduce((n, u) => n + u.length, 0);
        while (undo.length > 80 || (undo.length > 5 && total > 60e6)) total -= undo.shift().length; // photos make snapshots big
        redo = []; last = now; persist(); syncHistory();
        try { localStorage.setItem(STORE + '-touched', '1'); } catch {} // a real edit: the quick setup offers to keep this work
    };
    const restore = snap => {
        const fonts = JSON.stringify([P.site.fonts, P.pages.length]);
        const bound = Object.fromEntries(B.ACCOUNT_KEYS.map(k => [k, P.site[k]])); // forms on the server stay connected
        P = JSON.parse(snap); B.ACCOUNT_KEYS.forEach(k => { if (bound[k]) P.site[k] = bound[k]; else delete P.site[k]; }); last = JSON.stringify(P); pageIdx = Math.min(pageIdx, P.pages.length - 1); if (sel && !find(sel.id)) sel = null; persist();
        if (fonts === JSON.stringify([P.site.fonts, P.pages.length]) && doc()?.querySelector('main')) { drawPages(); refreshChrome(); refreshMain(); drawAdd(); drawEdit(); drawSite(); drawPage(); }
        else renderAll();
        syncHistory();
    };
    const flush = () => { clearTimeout(later); commit(); }; // typing still waiting to be saved becomes its own undo step
    const doUndo = () => { flush(); if (!undo.length) return; redo.push(JSON.stringify(P)); restore(undo.pop()); };
    const doRedo = () => { flush(); if (!redo.length) return; undo.push(JSON.stringify(P)); restore(redo.pop()); };
    const syncHistory = () => { $('[data-act=undo]').disabled = !undo.length; $('[data-act=redo]').disabled = !redo.length; };
    let later = 0;
    const commitSoon = () => { clearTimeout(later); later = setTimeout(commit, 600); persist(); };
    addEventListener('pagehide', () => { clearTimeout(later); try { localStorage.setItem(STORE, JSON.stringify(P)); } catch {} });

    /* =========================================================== canvas */
    const frame = $('[data-lpb-frame]'), screen = $('.lpb-screen');
    const doc = () => frame.contentDocument;
    const EDITOR_CSS = `
.nav,.ob-bar{-webkit-backdrop-filter:none!important;backdrop-filter:none!important}
[data-s],[data-w],[data-c]{position:relative}
[data-s]:hover{box-shadow:inset 0 0 0 2px rgba(79,124,255,.35)}
[data-c]:hover{outline:1px dashed rgba(79,124,255,.45);outline-offset:4px}
[data-w]:hover{outline:1px dashed rgba(79,124,255,.6);outline-offset:3px}
.ed-sel-s{box-shadow:inset 0 0 0 2px #4f7cff!important}.ed-sel-c{outline:2px solid #22c55e!important;outline-offset:4px}.ed-sel-w{outline:2px solid #4f7cff!important;outline-offset:3px}
[data-e]{cursor:text;border-radius:3px;min-width:1ch}[data-e]:hover{box-shadow:0 0 0 2px rgba(79,124,255,.35)}
[data-e][contenteditable]{outline:2px solid #4f7cff;outline-offset:3px;box-shadow:none}.ed-target{box-shadow:0 0 0 2px rgba(245,158,11,.75)!important}
[data-e]:empty::before{content:attr(data-ph);opacity:.45}
[data-img],[data-icon]{cursor:pointer;position:relative}[data-img]:hover,[data-icon]:hover{outline:3px solid #4f7cff;outline-offset:-3px}
[data-img]:hover::after{content:'Change image';position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);background:#111827;color:#fff;font:600 12px Inter,sans-serif;padding:6px 10px;border-radius:8px;white-space:nowrap;z-index:5}
.nav [data-img]:hover::after{content:'Logo';top:auto;bottom:-30px}
.pic-empty{display:grid;place-items:center;border:2px dashed color-mix(in srgb,var(--primary) 45%,transparent);background:var(--soft);color:var(--primary);font:600 .95rem Inter,sans-serif;min-height:150px;text-align:center;padding:16px}.avatar.pic-empty,.logo-img.pic-empty{min-height:0;font-size:.72rem}
.ed-empty{border:2px dashed rgba(79,124,255,.45);border-radius:12px;padding:28px 12px;text-align:center;color:#4f7cff;font:600 14px/1.5 Inter,sans-serif;background:rgba(79,124,255,.05)}.ed-empty small{color:#64748b;font-weight:500}
@media (min-width:1025px){.ed-hide-d{opacity:.35;background-image:repeating-linear-gradient(45deg,transparent 0 10px,rgba(100,116,139,.12) 10px 20px)}}
@media (min-width:768px) and (max-width:1024px){.ed-hide-t{opacity:.35;background-image:repeating-linear-gradient(45deg,transparent 0 10px,rgba(100,116,139,.12) 10px 20px)}}
@media (max-width:767px){.ed-hide-m{opacity:.35;background-image:repeating-linear-gradient(45deg,transparent 0 10px,rgba(100,116,139,.12) 10px 20px)}}
.ed-bar{position:absolute;z-index:40;display:flex;gap:1px;background:#111827;padding:3px;border-radius:9px;box-shadow:0 10px 24px rgba(0,0,0,.3);font:600 11px/1 Inter,sans-serif;white-space:nowrap}
.ed-bar--s{top:8px;right:8px}.ed-bar--w{top:-15px;right:-4px;background:#4f7cff}.ed-bar--c{top:-15px;left:-4px;background:#16a34a}
.ed-bar button{all:unset;cursor:pointer;color:#fff;padding:5px 6px;border-radius:6px;display:inline-flex;align-items:center;gap:4px}.ed-bar button:hover{background:rgba(255,255,255,.18)}.ed-bar button.ed-del:hover{background:#dc2626}.ed-bar button[disabled]{opacity:.35;pointer-events:none}.ed-bar button[data-ed=drag]{cursor:grab}
.ed-bar svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.ed-tag{position:absolute;top:8px;left:8px;z-index:40;background:#4f7cff;color:#fff;font:700 11px/1 Inter,sans-serif;padding:6px 9px;border-radius:7px}
.ed-drop{position:absolute;z-index:60;height:4px;border-radius:4px;background:#4f7cff;box-shadow:0 0 0 3px rgba(79,124,255,.25);pointer-events:none}
.ed-drop::before{content:'Drop here';position:absolute;left:50%;top:-11px;transform:translateX(-50%);background:#4f7cff;color:#fff;font:700 10px/1 Inter,sans-serif;padding:4px 8px;border-radius:999px}
.ed-rt{position:absolute;z-index:70;display:flex;gap:2px;background:#111827;padding:4px;border-radius:10px;box-shadow:0 10px 24px rgba(0,0,0,.3)}
.ed-rt button{all:unset;cursor:pointer;color:#fff;font:700 13px/1 Inter,sans-serif;min-width:26px;height:26px;display:grid;place-items:center;border-radius:6px}.ed-rt button:hover{background:#374151}`;

    let scrollY = 0, scrollTo = null;
    let loading = false, dirty = false;
    const renderCanvas = () => {
        loading = true; dirty = false;
        scrollY = doc()?.scrollingElement?.scrollTop || 0;
        frame.srcdoc = B.render(P, page(), 'edit', { extraHead: `<style id="ed-css">${EDITOR_CSS}</style>` });
    };
    frame.addEventListener('load', () => {
        const d = doc(); if (!d?.body) return;
        loading = false;
        d.scrollingElement.scrollTop = scrollY;
        if (scrollTo) { d.querySelector(`[data-s="${scrollTo}"],[data-w="${scrollTo}"]`)?.scrollIntoView({ block: 'center' }); scrollTo = null; }
        d.addEventListener('mousedown', e => { if (e.target.closest('.ed-rt')) e.preventDefault(); }, true);
        d.addEventListener('click', onCanvasClick, true);
        d.addEventListener('input', onCanvasInput);
        d.addEventListener('focusout', onCanvasBlur);
        d.addEventListener('keydown', onKey);
        d.addEventListener('submit', e => e.preventDefault(), true);
        d.addEventListener('scroll', positionRt, { passive: true });
        ['dragover', 'drop', 'dragleave'].forEach(ev => d.addEventListener(ev, onDrag));
        d.addEventListener('dragstart', e => {
            const h = e.target.closest?.('[data-ed=drag]'); if (!h) return;
            const id = h.closest('.ed-bar').dataset.for, f = find(id);
            dragging = f.kind === 'section' || B.isFull(f.sec) ? { kind: 'move-s', id: f.sec.id } : { kind: 'move-w', id };
            e.dataTransfer.setData('text/plain', 'owl'); e.dataTransfer.effectAllowed = 'move';
        });
        d.addEventListener('dragend', () => { dragging = null; clearDrop(); });
        markSelection();
        if (dirty) { dirty = false; refreshChrome(); refreshMain(); } // changes made while it was loading
    });
    const x = () => ({ edit: true, mode: 'edit', page: page(), P, origin: location.origin });
    const refreshEl = id => {
        if (loading) { dirty = true; return; }
        const d = doc(), f = find(id); if (!d?.body || !f) return renderCanvas();
        const el = d.querySelector(`[data-s="${f.sec.id}"]`);
        if (!el) return renderCanvas();
        el.outerHTML = B.renderSection(f.sec, f.si, x());
        markSelection();
    };
    /** Redraw every section in place — the canvas keeps its scroll position (no reload, no jump to the top). */
    const refreshMain = () => {
        if (loading) { dirty = true; return; }
        const d = doc(), main = d?.querySelector('main');
        if (!main) return renderCanvas();
        main.innerHTML = page().sections.map((sc, i) => B.renderSection(sc, i, x())).join('\n');
        refreshCss(); markSelection();
        if (scrollTo) {
            const el = d.querySelector(`[data-w="${scrollTo}"],[data-s="${scrollTo}"]`), r = el?.getBoundingClientRect();
            if (r && (r.top < 0 || r.bottom > d.defaultView.innerHeight)) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            scrollTo = null;
        }
    };
    const refreshChrome = () => {
        if (loading) { dirty = true; return; }
        const d = doc(); if (!d?.body) return;
        d.querySelectorAll('.ob-bar,.nav').forEach(el => el.remove());
        d.body.insertAdjacentHTML('afterbegin', B.header(x()));
        const foot = d.querySelector('.foot'); if (foot) foot.outerHTML = B.footer(x());
    };
    const refreshCss = () => {
        if (loading) { dirty = true; return; }
        const d = doc(); if (!d?.body) return;
        d.getElementById('site-css').textContent = B.siteCss(P);
        d.getElementById('page-css').textContent = B.pageCss(page());
    };

    /* ----- selection markers */
    const markSelection = () => {
        const d = doc(); if (!d?.body) return;
        d.querySelectorAll('.ed-sel-s,.ed-sel-c,.ed-sel-w').forEach(el => el.classList.remove('ed-sel-s', 'ed-sel-c', 'ed-sel-w'));
        d.querySelectorAll('.ed-target').forEach(el => el.classList.remove('ed-target'));
        d.querySelectorAll('.ed-bar,.ed-tag').forEach(el => el.remove());
        if (!sel) return;
        const f = find(sel.id); if (!f) { sel = null; return; }
        const secs = page().sections;
        const sEl = d.querySelector(`[data-s="${f.sec.id}"]`);
        if (sEl) {
            if (f.kind === 'section') sEl.classList.add('ed-sel-s');
            sEl.insertAdjacentHTML('afterbegin', `<span class="ed-tag">Section${B.isFull(f.sec) ? ' · ' + esc(W[f.sec.cols[0].widgets[0].type].label) : ''}</span><div class="ed-bar ed-bar--s" data-for="${f.sec.id}">
                <button data-ed="drag" draggable="true" title="Drag to move this section">${ICON.drag}</button><button data-ed="up" title="Move section up" ${f.si === 0 ? 'disabled' : ''}>${ICON.up}</button><button data-ed="down" title="Move section down" ${f.si === secs.length - 1 ? 'disabled' : ''}>${ICON.down}</button>
                <button data-ed="edit" title="Section settings">${ICON.edit}</button><button data-ed="dup" title="Duplicate section">${ICON.copy}</button><button data-ed="savetpl" title="Save as my template">${ICON.save}</button><button data-ed="del" class="ed-del" title="Delete section">${ICON.trash}</button></div>`);
        }
        if (f.kind === 'col') {
            const cEl = d.querySelector(`[data-c="${f.col.id}"]`);
            cEl?.classList.add('ed-sel-c');
            cEl?.insertAdjacentHTML('afterbegin', `<div class="ed-bar ed-bar--c" data-for="${f.col.id}"><button data-ed="edit">${ICON.cols} Column</button></div>`);
        }
        if (f.kind === 'widget') {
            const wEl = d.querySelector(`[data-w="${f.obj.id}"]`);
            if (wEl && !B.isFull(f.sec)) {
                wEl.classList.add('ed-sel-w');
                const n = f.col.widgets.length;
                wEl.insertAdjacentHTML('afterbegin', `<div class="ed-bar ed-bar--w" data-for="${f.obj.id}"><button data-ed="drag" draggable="true" title="Drag to move this widget">${ICON.drag}</button><button data-ed="edit">${esc(W[f.obj.type].label)}</button>
                    <button data-ed="up" ${f.wi === 0 ? 'disabled' : ''} title="Move this widget up">${ICON.up}</button><button data-ed="down" ${f.wi === n - 1 ? 'disabled' : ''} title="Move this widget down">${ICON.down}</button><button data-ed="dup" title="Make a copy of this widget">${ICON.copy}</button><button data-ed="del" class="ed-del" title="Delete this widget">${ICON.trash}</button></div>`);
            } else if (wEl) sEl?.classList.add('ed-sel-s');
            if (sel.target && sel.target !== '_') wEl?.querySelectorAll(`[data-e^="${f.obj.id}|"]`).forEach(el => { if (styleKey(el.dataset.e.split('|')[1]) === sel.target) el.classList.add('ed-target'); });
        }
    };
    const select = (id, target = '_', tab = 'edit') => {
        const f = find(id); if (!f) return;
        sel = { kind: f.kind, id, target };
        markSelection();
        if (tab) showTab(tab); else drawEdit();
        drawNavigator();
    };

    /* ----- canvas events */
    let picking = null;
    const onCanvasClick = e => {
        const d = doc();
        const rt = e.target.closest('[data-rt]');
        if (rt) { e.preventDefault(); return richCmd(rt.dataset.rt); }
        const bar = e.target.closest('[data-ed]');
        if (bar) { e.preventDefault(); e.stopPropagation(); return barAction(bar.dataset.ed, bar.closest('.ed-bar').dataset.for); }
        const goto = e.target.closest('[data-goto]');
        if (goto) { e.preventDefault(); return switchPage(P.pages.findIndex(p => p.id === goto.dataset.goto)); }
        if (e.target.closest('a,button,summary,label,input,textarea')) e.preventDefault();
        const editable = e.target.closest('[data-e]');
        if (editable?.isContentEditable) return;
        const img = e.target.closest('[data-img]'), ic = e.target.closest('[data-icon]');
        const chrome = e.target.closest('[data-hdr],[data-ftr]');
        const wEl = e.target.closest('[data-w]'), cEl = e.target.closest('[data-c]'), sEl = e.target.closest('[data-s]');
        if (editable) {
            const [id, path] = editable.dataset.e.split('|');
            if (id !== 'site') select(id, styleKey(path));
            startEdit(editable);
            return;
        }
        if (ic) { e.preventDefault(); const [id, path] = ic.dataset.icon.split('|'); select(id, '_'); return pickIcon(id, path); }
        if (img) { e.preventDefault(); const [id] = img.dataset.img.split('|'); if (id !== 'site') select(id, '_'); picking = img.dataset.img; return $('[data-pick-image]').click(); }
        if (chrome) { sel = null; markSelection(); showTab('site'); return; }
        if (wEl) return select(wEl.dataset.w, '_');
        if (cEl) return select(cEl.dataset.c, '_');
        if (sEl) return select(sEl.dataset.s, '_');
        d.getSelection()?.removeAllRanges();
    };
    const ownerOf = key => { const [id, path] = key.split('|'); return id === 'site' ? [P.site, path, 'site'] : [find(id)?.obj.p, path, id]; };
    const startEdit = el => {
        const rich = el.hasAttribute('data-rich');
        try { el.contentEditable = rich ? 'true' : 'plaintext-only'; } catch { el.contentEditable = 'true'; }
        if (!rich && el.contentEditable !== 'plaintext-only') el.contentEditable = 'true';
        el.focus();
        if (rich) showRt(el);
    };
    const onCanvasInput = e => {
        const el = e.target.closest('[data-e]'); if (!el) return;
        const [obj, path] = ownerOf(el.dataset.e); if (!obj) return;
        setPath(obj, path, el.hasAttribute('data-rich') ? el.innerHTML : el.innerText.replace(/\n$/, ''));
        persist();
        if ($('[data-tab-panel=edit]').classList.contains('is-active')) syncPanelInputs();
    };
    const onCanvasBlur = e => {
        const el = e.target.closest?.('[data-e]'); if (!el || !el.isContentEditable) return;
        if (rtBusy) return;
        el.removeAttribute('contenteditable'); hideRt();
        const [obj, path, id] = ownerOf(el.dataset.e);
        if (el.hasAttribute('data-rich') && obj) { const clean = B.sanitize(el.innerHTML); setPath(obj, path, clean); el.innerHTML = clean; }
        commit();
        if (id === 'site' && path === 'name') { // the footer and the logo letter show the name too
            const d = doc(), name = P.site.name || 'Your business';
            const fb = d.querySelector('.foot b'); if (fb) fb.textContent = name;
            const mark = d.querySelector('.nav .mark'); if (mark) mark.textContent = name.trim()[0]?.toUpperCase() || 'A';
        }
    };
    const onKey = e => {
        if (e.target.closest?.('input,textarea,select')) return; // typing in a form field on the page
        const ed = e.target.closest?.('[data-e][contenteditable]');
        if (ed && e.key === 'Enter' && !ed.hasAttribute('data-ml') && !ed.hasAttribute('data-rich')) { e.preventDefault(); ed.blur(); return; }
        if (ed && e.key === 'Escape') { ed.blur(); return; }
        if (ed) return;
        if (e.key === 'Escape' && root.classList.contains('is-full') && !document.querySelector('dialog[open]')) { setFull(false); return; }
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? doRedo() : doUndo(); }
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'y') { e.preventDefault(); doRedo(); }
        if (e.key === 'Delete' && sel && sel.kind !== 'col') { e.preventDefault(); barAction('del', sel.id); }
    };
    document.addEventListener('keydown', e => { if (!e.target.closest('input,textarea,select,[contenteditable]') && !document.querySelector('dialog[open]')) onKey(e); });

    /* ----- rich text toolbar (inside the canvas) */
    let rtEl = null, rtBusy = false;
    const showRt = el => {
        doc().querySelectorAll('.ed-rt').forEach(t => t.remove());
        rtEl = el;
        doc().body.insertAdjacentHTML('beforeend', `<div class="ed-rt" contenteditable="false">
            <button data-rt="bold" title="Bold"><b>B</b></button><button data-rt="italic" title="Italic"><i>I</i></button><button data-rt="underline" title="Underline"><u>U</u></button>
            <button data-rt="link" title="Turn the selected text into a link">${oi('link')}</button><button data-rt="insertUnorderedList" title="Bullet list" aria-label="Bullet list">${oi('list')}</button><button data-rt="insertOrderedList" title="Numbered list" aria-label="Numbered list">${oi('list-ordered')}</button><button data-rt="removeFormat" title="Clear formatting" aria-label="Clear formatting">${oi('eraser')}</button></div>`);
        positionRt();
    };
    const hideRt = () => { doc()?.querySelectorAll('.ed-rt').forEach(t => t.remove()); rtEl = null; };
    const positionRt = () => {
        const t = doc()?.querySelector('.ed-rt'); if (!t || !rtEl) return;
        const r = rtEl.getBoundingClientRect(), sy = doc().scrollingElement.scrollTop;
        t.style.top = Math.max(4, r.top + sy - 40) + 'px'; t.style.left = Math.max(4, r.left) + 'px';
    };
    const richCmd = async cmd => {
        const d = doc(); if (!rtEl) return;
        if (cmd === 'link') {
            const range = d.getSelection().rangeCount ? d.getSelection().getRangeAt(0).cloneRange() : null;
            rtBusy = true;
            const url = await sweet({ icon: 'question', title: 'Add a link', text: 'Select some text first. Paste a web address, mailto: or tel: link.', input: { value: 'https://', max: 300 }, confirm: 'Add link' });
            rtBusy = false; rtEl.focus();
            if (range) { d.getSelection().removeAllRanges(); d.getSelection().addRange(range); }
            if (url && B.safeUrl(url)) d.execCommand('createLink', false, url);
        } else d.execCommand(cmd, false, null);
        rtEl?.dispatchEvent(new Event('input', { bubbles: true }));
    };

    /* ----- element toolbar actions */
    const insertSection = (secObj, afterId) => {
        const secs = page().sections, i = afterId ? secs.findIndex(s => s.id === find(afterId)?.sec.id) : -1;
        secs.splice(i >= 0 ? i + 1 : secs.length, 0, secObj);
        return secObj;
    };
    const barAction = async (act, id) => {
        const f = find(id); if (!f) return;
        const secs = page().sections;
        if (act === 'edit') return select(id, '_');
        if (f.kind === 'section' || (f.kind === 'widget' && B.isFull(f.sec))) {
            const s = f.sec, i = f.si;
            if (act === 'up' && i > 0) [secs[i - 1], secs[i]] = [secs[i], secs[i - 1]];
            if (act === 'down' && i < secs.length - 1) [secs[i + 1], secs[i]] = [secs[i], secs[i + 1]];
            if (act === 'dup') { const c = B.reId(s); secs.splice(i + 1, 0, c); sel = { kind: 'section', id: c.id, target: '_' }; }
            if (act === 'savetpl') return saveTemplate(s);
            if (act === 'del') {
                const what = B.isFull(s) ? `this ${W[s.cols[0].widgets[0].type].label.toLowerCase()} section` : 'this section';
                if (!(await sweet({ icon: 'warning', title: `Delete ${what}?`, text: 'Everything inside it goes too. You can bring it back with Undo.', confirm: 'Yes, delete it', danger: true }))) return;
                secs.splice(secs.indexOf(s), 1); sel = null;
            }
            scrollTo = sel?.id || null;
        } else if (f.kind === 'widget') {
            const list = f.col.widgets, i = f.wi;
            if (act === 'up' && i > 0) [list[i - 1], list[i]] = [list[i], list[i - 1]];
            if (act === 'down' && i < list.length - 1) [list[i], list[i + 1]] = [list[i + 1], list[i]];
            if (act === 'dup') { const c = clone(list[i]); c.id = uid(); list.splice(i + 1, 0, c); sel = { kind: 'widget', id: c.id, target: '_' }; }
            if (act === 'del') { list.splice(i, 1); sel = null; toast('Widget deleted — Undo brings it back'); }
        } else return;
        commit(); refreshMain(); drawEdit(); drawNavigator();
        if (!sel) showTab('add');
    };

    /* ----- images & icons */
    const readImage = (file, max, keepAlpha) => createImageBitmap(file).then(bmp => {
        const scale = Math.min(1, max / Math.max(bmp.width, bmp.height)), cv = document.createElement('canvas');
        cv.width = Math.round(bmp.width * scale); cv.height = Math.round(bmp.height * scale);
        cv.getContext('2d').drawImage(bmp, 0, 0, cv.width, cv.height);
        bmp.close?.();
        let url = cv.toDataURL('image/webp', 0.82);
        if (!url.startsWith('data:image/webp')) url = keepAlpha ? cv.toDataURL('image/png') : cv.toDataURL('image/jpeg', 0.84);
        return url;
    });
    $('[data-pick-image]').addEventListener('change', async e => {
        const file = e.target.files[0]; e.target.value = '';
        if (!file || !picking) return;
        const key = picking; picking = null;
        try {
            if (key.startsWith('style|')) { // background image from the Style tab
                const [, id, target] = key.split('|'), st = styleObj(id, target);
                const dev = DEV(), img = await readImage(file, 1920); st[dev].bgImg = img; st[dev].bgType = 'image';
                commit(); refreshCss(); drawEdit(); return;
            }
            if (key.startsWith('popup|')) { page().popup.image = await readImage(file, 1000); commit(); drawPage(); return; }
            const [obj, path, id] = ownerOf(key), isLogo = key === 'site|logo';
            setPath(obj, path, await readImage(file, isLogo ? 480 : /photo|avatar/.test(path) ? 700 : /^items\.\d+\.(image|images)/.test(path) && find(id)?.obj.type === 'products' ? 1100 : 1600, isLogo));
            commit(); id === 'site' ? (refreshChrome(), drawSite()) : (refreshEl(id), drawEdit());
        } catch { toast('Sorry, that image could not be read. Try a JPG or PNG.'); }
    });
    const pickIcon = async (id, path) => {
        const html = `<div class="icon-grid">${Object.keys(B.ICONS).map(k => `<button type="button" data-pick="i:${k}" title="${k}">${B.icon('i:' + k)}</button>`).join('')}</div><p class="sweet__text">…or type a letter, number or short text instead:</p>`;
        const v = await sweet({ icon: 'info', title: 'Choose an icon', html, input: { value: '', placeholder: 'e.g. 1 or A', max: 4 }, confirm: 'Use this' });
        if (!v) return;
        const [obj] = ownerOf(`${id}|${path}`); if (!obj) return;
        setPath(obj, path, v); commit(); refreshEl(id); drawEdit();
    };

    /* =========================================================== drag & drop */
    let dragging = null, dropAt = null;
    const clearDrop = () => { doc()?.querySelectorAll('.ed-drop').forEach(el => el.remove()); dropAt = null; };
    const showLine = (r, sy) => {
        const d = doc(); let line = d.querySelector('.ed-drop');
        if (!line) { line = d.createElement('div'); line.className = 'ed-drop'; d.body.append(line); }
        Object.assign(line.style, { top: r.top + sy - 2 + 'px', left: r.left + 'px', width: r.width + 'px' });
    };
    const sectionDrop = (e, d, sy) => {
        const secs = [...d.querySelectorAll('[data-s]')].filter(el => el.dataset.s !== dragging.id);
        if (!secs.length) { dropAt = { kind: 'section', beforeId: null }; return showLine({ top: 90, left: 20, width: d.body.clientWidth - 40 }, sy); }
        let idx = secs.length;
        for (let i = 0; i < secs.length; i++) { const r = secs[i].getBoundingClientRect(); if (e.clientY < r.top + r.height / 2) { idx = i; break; } }
        const ref = secs[Math.min(idx, secs.length - 1)].getBoundingClientRect();
        dropAt = { kind: 'section', beforeId: secs[idx]?.dataset.s || null };
        showLine({ top: idx < secs.length ? ref.top : ref.bottom, left: ref.left + 20, width: ref.width - 40 }, sy);
    };
    const onDrag = e => {
        if (!dragging) { if (e.type !== 'dragleave') e.preventDefault(); return; } // e.g. a file from the desktop
        const d = doc(), sy = d.scrollingElement.scrollTop;
        if (e.type === 'dragleave') { if (!e.relatedTarget) clearDrop(); return; }
        e.preventDefault();
        if (e.type === 'dragover') {
            const isWidget = dragging.kind === 'move-w' || (dragging.kind === 'new' && !W[dragging.type]?.full);
            const colEl = isWidget && e.target.closest?.('[data-c]');
            if (colEl) {
                const ws = [...colEl.querySelectorAll(':scope > [data-w]')].filter(el => el.dataset.w !== dragging.id);
                let idx = ws.length;
                for (let i = 0; i < ws.length; i++) { const r = ws[i].getBoundingClientRect(); if (e.clientY < r.top + r.height / 2) { idx = i; break; } }
                const cr = colEl.getBoundingClientRect();
                const y = ws.length ? (idx < ws.length ? ws[idx].getBoundingClientRect().top - 8 : ws[ws.length - 1].getBoundingClientRect().bottom + 8) : cr.top + cr.height / 2;
                dropAt = { kind: 'col', colId: colEl.dataset.c, beforeId: ws[idx]?.dataset.w || null };
                showLine({ top: y, left: cr.left, width: cr.width }, sy);
            } else sectionDrop(e, d, sy);
            return;
        }
        if (e.type === 'drop') { const at = dropAt, what = dragging; clearDrop(); dragging = null; if (at) performDrop(what, at); }
    };
    const performDrop = (what, at) => {
        const secs = page().sections;
        let newSel = null;
        const placeSection = s => { const bi = at.beforeId ? secs.findIndex(x2 => x2.id === at.beforeId) : -1; secs.splice(bi >= 0 ? bi : secs.length, 0, s); newSel = newSel || s.id; };
        const placeInCol = w => { const c = find(at.colId).col, bi = at.beforeId ? c.widgets.findIndex(x2 => x2.id === at.beforeId) : -1; c.widgets.splice(bi >= 0 ? bi : c.widgets.length, 0, w); newSel = w.id; };
        if (what.kind === 'move-s') {
            const i = secs.findIndex(s => s.id === what.id); if (i < 0) return;
            const [s] = secs.splice(i, 1); placeSection(s); newSel = s.id;
        } else if (what.kind === 'move-w') {
            const f = find(what.id); if (!f) return;
            const [w] = f.col.widgets.splice(f.wi, 1);
            if (at.kind === 'col') placeInCol(w); else { newSel = w.id; placeSection(B.section('1', [B.column([w])])); }
            if (!f.sec.cols.some(c => c.widgets.length) && f.sec.cols.length === 1) secs.splice(secs.indexOf(f.sec), 1); // drop sections left empty
        } else if (what.kind === 'new') {
            if (W[what.type].full) placeSection(B.sectionOf(what.type));
            else if (at.kind === 'col') placeInCol(B.widget(what.type));
            else { const w = B.widget(what.type); newSel = w.id; placeSection(B.section('1', [B.column([w])])); }
            track('builder_add', { type: what.type, via: 'drag' });
        } else if (what.kind === 'layout') placeSection(B.section(what.layout));
        else if (what.kind === 'tpl') placeSection(templateSection(what.key));
        commit(); scrollTo = newSel;
        if (newSel) { const f = find(newSel); sel = f ? { kind: f.kind, id: newSel, target: '_' } : null; }
        refreshMain(); showTab('edit'); drawNavigator();
    };
    // Panel tiles start drags; the canvas iframe receives them
    root.addEventListener('dragstart', e => {
        const t = e.target.closest?.('[data-drag]'); if (!t) return;
        const [kind, key] = t.dataset.drag.split(':');
        dragging = kind === 'new' ? { kind, type: key } : kind === 'layout' ? { kind, layout: key } : { kind, key };
        e.dataTransfer.setData('text/plain', 'owl'); e.dataTransfer.effectAllowed = 'copy';
    });
    root.addEventListener('dragend', () => { dragging = null; clearDrop(); });

    /* =========================================================== add panel (widgets, layouts, templates) */
    const myTemplates = () => { try { return JSON.parse(localStorage.getItem('owlvix-builder-tpl') || '[]'); } catch { return []; } };
    const templateSection = key => {
        if (!key.startsWith('my-')) return B.TEMPLATES.sections[key][1]();
        const sec = B.normalizeSection(myTemplates()[+key.slice(3)]?.sec); // saved in the browser: checked like any project data
        if (!sec) throw new Error('That template could not be read.');
        return B.reId(sec);
    };
    const saveTemplate = async s => {
        const name = await sweet({ icon: 'question', title: 'Save as my template', text: 'Reuse this section on any page. Saved in this browser.', input: { value: W[s.cols[0]?.widgets[0]?.type]?.label || 'My section', max: 40 }, confirm: 'Save template' });
        if (!name) return;
        const list = myTemplates(); list.unshift({ name, sec: clone(s) });
        try { localStorage.setItem('owlvix-builder-tpl', JSON.stringify(list.slice(0, 30))); toast('Saved to My templates (Add tab)'); drawAdd(); }
        catch { toast('No space left to save templates in this browser — remove photos or old templates.'); }
    };
    /* Whole pages saved as my templates: every section of a page, reused later as a new page. (In this browser.) */
    const PAGE_TPL = 'owlvix-builder-pagetpl';
    const myPageTemplates = () => { try { return JSON.parse(localStorage.getItem(PAGE_TPL) || '[]'); } catch { return []; } };
    const myPageSections = i => {
        const secs = (myPageTemplates()[i]?.secs || []).map(x => B.normalizeSection(x)).filter(Boolean).map(x => B.reId(x)); // checked like any project data
        if (!secs.length) throw new Error('That page template could not be read.');
        return secs;
    };
    const savePageTemplate = async () => {
        const pg = page();
        if (!pg.sections.length) return toast('This page is empty — add some sections first.', 'error');
        const name = await sweet({ icon: 'question', title: 'Save page as my template', text: `Saves all ${pg.sections.length} sections of “${pg.title}” with your words, pictures and styles. Reuse it any time as a new page. Saved in this browser.`, input: { value: pg.title, max: 40 }, confirm: 'Save page template' });
        if (!name) return;
        const list = myPageTemplates(); list.unshift({ name, secs: clone(pg.sections) });
        try { localStorage.setItem(PAGE_TPL, JSON.stringify(list.slice(0, 20))); toast('Page saved to My templates (Add tab, under My templates)', 'success'); drawAdd(); }
        catch { toast('No space left to save templates in this browser — remove photos or old templates.', 'error'); }
    };
    const tile = (drag, icon, label) => `<button type="button" class="lpb-add" draggable="true" data-drag="${drag}"><span>${icon}</span>${esc(label)}</button>`;
    const drawAdd = () => {
        const q = ($('[data-add-search]')?.value || '').toLowerCase().trim();
        const match = l => !q || l.toLowerCase().includes(q);
        const group = (title, inner) => (inner ? `<h2 class="lpb-ph">${title}</h2><div class="lpb-add-grid">${inner}</div>` : '');
        const widgets = gr => Object.values(W).filter(d => d.group === gr && match(d.label)).map(d => tile(`new:${d.type}`, `<b class="lpb-glyph">${glyph(d.icon)}</b>`, d.label)).join('');
        const layouts = Object.entries(B.LAYOUTS).filter(([, v]) => match(v[2] + ' columns layout')).map(([k, v]) => tile(`layout:${k}`, `<i class="lpb-lay" style="grid-template-columns:${v[0]}">${'<i></i>'.repeat(v[1])}</i>`, v[2])).join('');
        const thumb = key => `<span class="lpb-tthumb" data-thumb="${key}" aria-hidden="true"></span>`;
        const SHOW = q ? Infinity : 6; // the side panel shows a few; "View all" opens every one
        const secT = Object.entries(B.TEMPLATES.sections).filter(([, v]) => match(v[0])).slice(0, SHOW).map(([k, v]) => `<button type="button" class="lpb-tpl-row lpb-tpl-card" draggable="true" data-drag="tpl:${k}">${thumb('sec:' + k)}<span class="lpb-tpl-name">${esc(v[0])}<span>${oi('plus')}</span></span></button>`).join('');
        const pageT = Object.entries(B.TEMPLATES.pages).filter(([, v]) => match(v[0] + ' page')).slice(0, q ? Infinity : 4).map(([k, v]) => `<button type="button" class="lpb-tpl-row lpb-tpl-card" data-page-tpl="${k}">${thumb('page:' + k)}<span class="lpb-tpl-name">${oi('doc')} ${esc(v[0])}<span>${oi('plus')} New page</span></span></button>`).join('');
        const mine = myTemplates().map((t, i) => (match(t.name) ? `<div class="lpb-tpl-row lpb-tpl-card lpb-tpl-mine" draggable="true" data-drag="tpl:my-${i}">${thumb('sec:my-' + i)}<span class="lpb-tpl-name">${oi('star')} ${esc(t.name)}<button type="button" data-tpl-del="${i}" title="Remove this template" aria-label="Remove this template">${oi('close')}</button></span></div>` : '')).join('');
        const minePages = FUNNEL ? '' : myPageTemplates().map((t, i) => (match(t.name + ' page') ? `<div class="lpb-tpl-row lpb-tpl-card lpb-tpl-mine" role="button" tabindex="0" data-my-page="${i}">${thumb('mypage:' + i)}<span class="lpb-tpl-name">${oi('doc')} ${esc(t.name)} <small>${t.secs?.length || 0} sections</small><button type="button" data-pagetpl-del="${i}" title="Remove this page template" aria-label="Remove this page template">${oi('close')}</button></span></div>` : '')).join('');
        // after the last card too, so people who scrolled through the list don't have to go back up
        const more = (kind, label) => { const n = Object.keys(kind === 'sec' ? B.TEMPLATES.sections : B.TEMPLATES.pages).length; return q ? '' : `<button type="button" class="lpb-viewall lpb-viewall--end" data-tpl-all="${kind}">View all ${n} ${label} ${oi('arrow-right')}</button>`; };
        const html = (FUNNEL ? group('Funnel widgets', widgets('funnel')) : '') + (KIT === 'store' ? group('Shop', widgets('shop')) : '') + group('Layout — columns', layouts) + group('Basic widgets', widgets('basic')) + group('Pro widgets', widgets('pro')) + group('Ready sections', widgets('section'))
            + (secT ? `<h2 class="lpb-ph lpb-ph--row">Section templates <button type="button" class="lpb-viewall" data-tpl-all="sec">View all (${Object.keys(B.TEMPLATES.sections).length})</button></h2><div class="lpb-tpl-list">${secT}</div>${more('sec', 'section templates')}` : '')
            + (pageT ? `<h2 class="lpb-ph lpb-ph--row">Page templates <button type="button" class="lpb-viewall" data-tpl-all="page">View all (${Object.keys(B.TEMPLATES.pages).length})</button></h2><div class="lpb-tpl-list">${pageT}</div>${more('page', 'page templates')}` : '')
            + (!q || mine || minePages ? `<h2 class="lpb-ph">My templates</h2><div class="lpb-tpl-list">${mine + minePages || `<p class="lpb-hint">Select a section and press ${oi('star')} on its toolbar to save it here${FUNNEL ? '' : ' — or save a whole page from the Page tab'}.</p>`}</div>` : '');
        $('[data-add-list]').innerHTML = html || '<p class="lpb-hint">Nothing matches your search.</p>';
        $$('[data-thumb]', $('[data-add-list]')).forEach(el => thumbs.observe(el));
    };

    /* All templates in a popup: bigger thumbnails, tabs for sections / pages / my templates, and a search. A click
       adds the section after the selected element (or the page), then the popup closes. */
    let gal = null, galTab = 'sec', galCat = '';
    const drawGallery = () => {
        const q = gal.querySelector('[data-gal-q]').value.toLowerCase().trim(), match = l => !q || l.toLowerCase().includes(q);
        gal.querySelectorAll('[data-gal-tab]').forEach(b => b.setAttribute('aria-selected', String(b.dataset.galTab === galTab)));
        // groups (Heroes, Features, Pricing…) for sections and pages
        const src = galTab === 'sec' ? B.TEMPLATES.sections : galTab === 'page' ? B.TEMPLATES.pages : null;
        const cats = src ? [...new Set(Object.values(src).map(v => v[2]).filter(Boolean))] : [];
        if (!cats.includes(galCat)) galCat = '';
        gal.querySelector('[data-gal-cats]').innerHTML = cats.length ? ['', ...cats].map(c => `<button type="button" data-gal-cat="${esc(c)}" aria-pressed="${c === galCat}">${esc(c || 'All')}</button>`).join('') : '';
        const inCat = v => !galCat || v[2] === galCat;
        const card = (key, label, act, extra = '', pre = '') => `<button type="button" class="lpb-gal-card" ${act}><span class="lpb-tthumb lpb-tthumb--big" data-thumb="${key}" aria-hidden="true"></span><span class="lpb-gal-name">${pre}${esc(label)}${extra}</span></button>`;
        let cards = '';
        if (galTab === 'sec') cards = Object.entries(B.TEMPLATES.sections).filter(([, v]) => match(v[0]) && inCat(v)).map(([k, v]) => card('sec:' + k, v[0], `data-gal-add="tpl:${k}"`, `<i>${oi('plus')} Add</i>`)).join('');
        else if (galTab === 'page') cards = Object.entries(B.TEMPLATES.pages).filter(([, v]) => match(v[0]) && inCat(v)).map(([k, v]) => card('page:' + k, v[0], `data-gal-page="${k}"`, `<i>${oi('plus')} New page</i>`)).join('');
        else cards = myTemplates().map((t, i) => (match(t.name) ? card('sec:my-' + i, t.name, `data-gal-add="tpl:my-${i}"`, `<i>${oi('plus')} Add</i>`) : '')).join('')
            + (FUNNEL ? '' : myPageTemplates().map((t, i) => (match(t.name) ? card('mypage:' + i, t.name, `data-gal-mypage="${i}"`, `<i>${oi('plus')} New page</i>`, oi('doc') + ' ') : '')).join(''));
        const empty = galTab === 'mine' && !q ? `No saved templates yet — select a section on your page and press ${oi('star')} on its toolbar${FUNNEL ? '' : ', or save a whole page from the Page tab'}.` : 'Nothing matches your search.';
        gal.querySelector('[data-gal-grid]').innerHTML = cards || `<p class="lpb-hint">${empty}</p>`;
        gal.querySelectorAll('[data-thumb]').forEach(el => thumbs.observe(el));
    };
    const openGallery = tab => {
        if (!gal) {
            gal = document.createElement('dialog');
            gal.className = 'lpb-gallery';
            gal.setAttribute('aria-label', 'All templates');
            gal.innerHTML = `<div class="lpb-gal-head"><h2>Templates</h2>
                    <div class="lpb-gal-tabs" role="tablist"><button type="button" role="tab" data-gal-tab="sec">Sections</button><button type="button" role="tab" data-gal-tab="page">Pages</button><button type="button" role="tab" data-gal-tab="mine">My templates</button></div>
                    <input type="search" data-gal-q placeholder="Search templates…" aria-label="Search templates">
                    <button type="button" class="lpb-gal-x" data-gal-close aria-label="Close">${oi('close')}</button></div>
                <div class="lpb-gal-cats" data-gal-cats></div>
                <div class="lpb-gal-grid" data-gal-grid></div>`;
            (root.closest('.lpb-app.is-full') ? root : document.body).appendChild(gal);
            gal.addEventListener('click', async e => {
                const r = gal.getBoundingClientRect(), outside = e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
                if ((e.target === gal && outside) || e.target.closest('[data-gal-close]')) return gal.close();
                const tb = e.target.closest('[data-gal-tab]'); if (tb) { galTab = tb.dataset.galTab; galCat = ''; return drawGallery(); }
                const ct = e.target.closest('[data-gal-cat]'); if (ct) { galCat = ct.dataset.galCat; return drawGallery(); }
                const add = e.target.closest('[data-gal-add]'); if (add) { gal.close(); clickAdd(add.dataset.galAdd); return toast('Section added', 'success'); }
                const pg = e.target.closest('[data-gal-page]'); if (pg) { gal.close(); return addPage(pageName(pg.dataset.galPage), pg.dataset.galPage); }
                const mp = e.target.closest('[data-gal-mypage]'); if (mp) { gal.close(); return addPage(myPageTemplates()[+mp.dataset.galMypage]?.name || 'New page', 'mypage-' + mp.dataset.galMypage); }
            });
            gal.querySelector('[data-gal-q]').addEventListener('input', drawGallery);
        }
        galTab = tab || 'sec'; galCat = '';
        gal.querySelector('[data-gal-q]').value = '';
        gal.showModal(); drawGallery();
        gal.querySelector('[data-gal-tab][aria-selected="true"]')?.focus();
    };
    /* Template thumbnails: each template drawn small with this site's colours and fonts (an iframe, drawn once it
       scrolls into view; cached until the site's look changes). */
    const THUMB_CSS = '<style>html,body{overflow:hidden!important;pointer-events:none}body{min-height:100vh;display:grid;align-content:center}main{width:100%}header.nav,.ob-bar,footer.foot,.popup,[data-popup]{display:none!important}[data-anim]{opacity:1!important;transform:none!important;transition:none!important}*{animation:none!important}</style>';
    const thumbCache = new Map();
    const thumbHtml = key => {
        const look = JSON.stringify([P.site.colors, P.site.fonts, P.site.theme, P.site.radius, P.site.buttons]);
        const id = key + '|' + look;
        if (thumbCache.has(id)) return thumbCache.get(id);
        let sections;
        try { sections = key.startsWith('mypage:') ? myPageSections(+key.slice(7)) : key.startsWith('page:') ? B.TEMPLATES.pages[key.slice(5)][1]() : [templateSection(key.slice(4))]; } catch { return ''; }
        const pg = { ...P.pages[0], id: 'thumb', sections, popup: {} };
        let html = '';
        // (with the runtime: sliders and flip boxes need it to draw)
        try { html = B.render({ ...P, site: { ...P.site, announce: { on: false } } }, pg, 'download', { runtime: RUNTIME, extraHead: THUMB_CSS }); } catch { html = ''; }
        thumbCache.set(id, html);
        return html;
    };
    const thumbs = new IntersectionObserver(entries => entries.forEach(en => {
        if (!en.isIntersecting) return;
        const el = en.target; thumbs.unobserve(el);
        const html = thumbHtml(el.dataset.thumb); if (!html) return;
        const f = document.createElement('iframe');
        f.setAttribute('tabindex', '-1'); f.setAttribute('aria-hidden', 'true'); f.title = '';
        const k = (el.clientWidth || 290) / 1200; // drawn 1200px wide, shrunk to the card
        f.style.transform = `scale(${k})`; f.style.height = Math.ceil(el.clientHeight / k) + 'px';
        f.srcdoc = html; f.onload = () => el.classList.add('is-ready');
        el.appendChild(f);
    }), { root: null, rootMargin: '200px 0px' });
    $('[data-add-search]').addEventListener('input', drawAdd);
    $('[data-add-list]').addEventListener('click', e => {
        const del = e.target.closest('[data-tpl-del]');
        if (del) { e.stopPropagation(); const list = myTemplates(); list.splice(+del.dataset.tplDel, 1); try { localStorage.setItem('owlvix-builder-tpl', JSON.stringify(list)); } catch {} return drawAdd(); }
        const pdel = e.target.closest('[data-pagetpl-del]');
        if (pdel) { e.stopPropagation(); const list = myPageTemplates(); list.splice(+pdel.dataset.pagetplDel, 1); try { localStorage.setItem(PAGE_TPL, JSON.stringify(list)); } catch {} return drawAdd(); }
        const myp = e.target.closest('[data-my-page]'); if (myp) return addPage(myPageTemplates()[+myp.dataset.myPage]?.name || 'New page', 'mypage-' + myp.dataset.myPage);
        const all = e.target.closest('[data-tpl-all]'); if (all) return openGallery(all.dataset.tplAll);
        const pt = e.target.closest('[data-page-tpl]');
        if (pt) return addPage(pageName(pt.dataset.pageTpl), pt.dataset.pageTpl);
        const t = e.target.closest('[data-drag]'); if (t) clickAdd(t.dataset.drag);
    });
    // Click instead of drag: put it after the selected element
    const clickAdd = spec => {
        const [kind, key] = spec.split(':');
        const f = sel && find(sel.id);
        let newSel;
        if (kind === 'new' && !W[key].full && f && f.kind !== 'section' && !B.isFull(f.sec)) {
            const w = B.widget(key), list = f.col.widgets; list.splice(f.kind === 'widget' ? f.wi + 1 : list.length, 0, w); newSel = w.id;
        } else {
            const s = kind === 'new' ? (W[key].full ? B.sectionOf(key) : B.section('1', [B.column([B.widget(key)])])) : kind === 'layout' ? B.section(key) : templateSection(key);
            insertSection(s, f?.id); newSel = kind === 'new' && !W[key].full ? s.cols[0].widgets[0].id : s.id;
        }
        commit(); scrollTo = newSel; const nf = find(newSel); sel = { kind: nf.kind, id: newSel, target: '_' };
        refreshMain(); showTab('edit'); drawNavigator();
        track('builder_add', { type: key, via: 'click' });
    };

    /* =========================================================== edit panel: Content / Style / Advanced */
    let editTab = 'content';
    const BG = { auto: 'Automatic', plain: 'White', alt: 'Light grey', soft: 'Light brand tint', brand: 'Brand colour', dark: 'Dark' };
    const linkWidget = (key, l) => {
        l = l || { to: 'contact' };
        const pageOpts = P.pages.map(p => `<option value="${esc(p.slug)}" ${l.val === p.slug ? 'selected' : ''}>${esc(p.title)}</option>`).join('');
        return `<div class="lpb-link"><select data-link-to="${key}" aria-label="Link goes to">${Object.entries(FUNNEL ? { ...B.FUNNEL_LINKS, ...B.LINK_TYPES } : B.LINK_TYPES).map(([k, v]) => `<option value="${k}" ${l.to === k ? 'selected' : ''}>${v}</option>`).join('')}</select>
            ${l.to === 'page' ? `<select data-link-val="${key}" aria-label="Page">${pageOpts}</select>` : ['url', 'whatsapp', 'phone', 'email'].includes(l.to) ? `<input data-link-val="${key}" aria-label="Link address"${['whatsapp', 'phone'].includes(l.to) ? ' type="tel"' : ''} value="${esc(l.val || '')}" placeholder="${{ url: 'https://…', whatsapp: '+1 555 123 4567', phone: '+1 555 123 4567', email: 'you@example.com' }[l.to]}">` : ''}</div>`;
    };
    const fieldHtml = (f, obj, prefix) => {
        const key = prefix + f.k, v = getPath(obj, f.k);
        switch (f.type) {
            case 'text': return `<label>${f.label}<input data-f="${key}"${/(^|\.)(whatsapp|phone)$/.test(f.k) ? ' type="tel"' : ''} value="${esc(v ?? '')}" placeholder="${esc(f.ph || '')}"></label>`;
            case 'textarea': return `<label>${f.label}<textarea data-f="${key}" rows="3">${esc(v ?? '')}</textarea></label>`;
            case 'number': return `<label>${f.label}<input type="number" data-f="${key}" value="${esc(v ?? '')}" min="${f.min}" max="${f.max}" step="${f.step}"></label>`;
            case 'datetime': return `<label>${f.label}<input type="datetime-local" data-f="${key}" value="${esc(v ?? '')}"></label>`;
            case 'bool': return `<label class="lpb-check"><input type="checkbox" data-f="${key}" ${v ? 'checked' : ''}> ${f.label}</label>`;
            case 'select': return `<label>${f.label}<select data-f="${key}">${Object.entries(f.options).map(([k, l]) => `<option value="${k}" ${v === k ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></label>`;
            case 'link': return `<label>${f.label}${linkWidget(key, v)}</label>`;
            case 'icon': return `<div class="lpb-imgf"><span>${f.label}</span><button type="button" class="lpb-iconbtn" data-icon-pick="${key}">${B.icon(v || 'i:star')} Change icon</button></div>`;
            case 'img': return `<div class="lpb-imgf"><span>${f.label}</span>${v ? `<img src="${esc(v)}" alt="">` : ''}<div><button type="button" class="btn btn--sm btn--ghost" data-img-pick="${key}">${v ? 'Change' : 'Upload'}</button>${v ? `<button type="button" class="btn btn--sm btn--ghost" data-img-clear="${key}">Remove</button>` : ''}</div></div>`;
            default: return '';
        }
    };
    const listHtml = (f, w) => {
        const list = w.p[f.k] || [], pre = `${w.id}|${f.k}`;
        const cell = (it, i, [k, kind]) => {
            const key = `${pre}.${i}.${k}`, name = `aria-label="${esc(`${f.label || 'Item'} ${i + 1}: ${k}`)}"`; // (for screen readers: "Menu items 2: price")
            if (kind === 'img') return `<button type="button" class="lpb-thumb" data-img-pick="${key}" ${it[k] ? `style="background-image:url('${esc(it[k])}')"` : ''} title="Image" aria-label="Image">${it[k] ? '' : oi('plus')}</button>`;
            if (kind === 'icon') return `<button type="button" class="lpb-thumb lpb-thumb--ic" data-icon-pick="${key}" title="Icon">${B.icon(it[k] || 'i:star')}</button>`;
            if (kind === 'star') return `<label class="lpb-mini" title="Highlight"><input type="checkbox" data-f="${key}" ${name} ${it[k] ? 'checked' : ''}>${oi('star-solid')}</label>`;
            if (kind === 'number') return `<input type="number" class="lpb-num" data-f="${key}" ${name} value="${esc(it[k] ?? '')}" min="0" max="100">`;
            if (kind === 'social') return `<select class="lpb-net" data-f="${key}" ${name}>${Object.entries(B.SOCIAL).map(([n, s]) => `<option value="${n}" ${it[k] === n ? 'selected' : ''}>${s[0]}</option>`).join('')}</select>`;
            return `<input data-f="${key}" ${name} value="${esc(it[k] ?? '')}" placeholder="${k === 'url' ? 'https://…' : ''}">`;
        };
        return `<div class="lpb-list"><span>${f.label}</span>${list.map((it, i) => `<div class="lpb-row">${f.fields.map(c => cell(it, i, c)).join('')}
            <button type="button" data-list="${f.k}|${i}|up" title="Move this row up" aria-label="Move this row up" ${i === 0 ? 'disabled' : ''}>${oi('arrow-up')}</button><button type="button" data-list="${f.k}|${i}|del" title="Remove this row" aria-label="Remove this row">${oi('close')}</button></div>`).join('')}
            ${list.length < f.max ? `<button type="button" class="btn btn--sm btn--ghost" data-list="${f.k}|-1|add">+ Add</button>` : ''}</div>`;
    };

    /* ----- style controls (per device) */
    const styleObj = (id, target = '_') => { const f = find(id); f.obj.st ||= {}; f.obj.st[target] ||= {}; ['d', 't', 'm'].forEach(k => (f.obj.st[target][k] ||= {})); return f.obj.st[target]; };
    const sv = (st, k) => st[DEV()][k] ?? '';
    const inherited = (st, k) => (DEV() === 'm' ? st.t[k] ?? st.d[k] : DEV() === 't' ? st.d[k] : '') ?? '';
    const devBadge = () => `<span class="lpb-devtag" title="Style for this screen size">${device === 'desktop' ? oi('monitor') : device === 'tablet' ? oi('phone', { cls: 'oi--tablet' }) : oi('phone')}</span>`;
    const ctl = {
        range: (st, k, label, min, max, step = 1, unit = 'px') => `<label class="lpb-ctl">${label} ${devBadge()}<span class="lpb-range"><input type="range" data-sty="${k}" min="${min}" max="${max}" step="${step}" value="${esc(sv(st, k) || inherited(st, k) || min)}"><input type="number" data-sty="${k}" min="${min}" max="${max}" step="${step}" value="${esc(sv(st, k))}" placeholder="${esc(inherited(st, k) || unit)}"></span></label>`,
        select: (st, k, label, opts) => `<label class="lpb-ctl">${label} ${devBadge()}<select data-sty="${k}">${Object.entries(opts).map(([v, l]) => `<option value="${v}" ${String(sv(st, k)) === v ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></label>`,
        align: (st, k = 'align') => `<div class="lpb-ctl">Alignment ${devBadge()}<span class="lpb-seg">${[['', 'Auto'], ['left', 'Left'], ['center', 'Centre'], ['right', 'Right']].map(([v, l]) => `<button type="button" data-sty-val="${k}|${v}" class="${String(sv(st, k)) === v ? 'is-on' : ''}">${l}</button>`).join('')}</span></div>`,
        color: (st, k, label) => {
            const v = sv(st, k);
            return `<div class="lpb-ctl">${label} ${devBadge()}<span class="lpb-colorctl">${B.GLOBAL.map(gl => `<button type="button" class="lpb-sw ${v === 'g:' + gl ? 'is-on' : ''}" style="--sw:var(--g-${gl})" data-sty-val="${k}|g:${gl}" title="Global ${gl} colour"></button>`).join('')}
                <input type="color" data-sty="${k}" value="${/^#/.test(v) ? v : '#000000'}" title="Custom colour"><button type="button" class="lpb-clear" data-sty-val="${k}|" title="Clear this colour (use the default)" aria-label="Clear this colour">${oi('close')}</button></span></div>`;
        },
        box: (st, label, keys) => `<div class="lpb-ctl">${label} ${devBadge()}<span class="lpb-box">${keys.map(([k, l]) => `<label><input type="number" data-sty="${k}" value="${esc(sv(st, k))}" placeholder="${esc(inherited(st, k) || '–')}"><small>${l}</small></label>`).join('')}</span></div>`,
    };
    const typoControls = st => `<details class="lpb-acc" open><summary>Typography</summary>
        ${ctl.select(st, 'family', 'Font', { '': 'Default', heading: 'Heading font (global)', body: 'Body font (global)', ...Object.fromEntries(Object.keys(B.FONTS).map(fn => [fn, fn])) })}
        ${ctl.range(st, 'size', 'Size', 10, 96)}
        ${ctl.select(st, 'weight', 'Weight', { '': 'Default', 300: 'Light', 400: 'Normal', 500: 'Medium', 600: 'Semi-bold', 700: 'Bold', 800: 'Extra bold' })}
        <div class="lpb-2">${ctl.range(st, 'lh', 'Line height', 0.8, 2.4, 0.05, 'em')}${ctl.range(st, 'ls', 'Letter spacing', -3, 10, 0.5)}</div>
        <div class="lpb-2">${ctl.select(st, 'transform', 'Case', { '': 'Default', uppercase: 'UPPERCASE', lowercase: 'lowercase', capitalize: 'Capitalize' })}${ctl.select(st, 'italic', 'Style', { '': 'Normal', 1: 'Italic' })}</div>
        ${ctl.align(st)}${ctl.color(st, 'color', 'Text colour')}</details>`;
    const bgControls = (st, id, target) => {
        const t = sv(st, 'bgType') || (sv(st, 'bg') ? 'color' : '');
        return `<details class="lpb-acc" open><summary>Background</summary>
        <span class="lpb-seg lpb-seg--wide">${[['', 'None'], ['color', 'Colour'], ['gradient', 'Gradient'], ['image', 'Image']].map(([v, l]) => `<button type="button" data-sty-val="bgType|${v}" class="${t === v ? 'is-on' : ''}">${l}</button>`).join('')}</span>
        ${t === 'color' ? ctl.color(st, 'bg', 'Colour') : ''}
        ${t === 'gradient' ? ctl.color(st, 'g1', 'From') + ctl.color(st, 'g2', 'To') + ctl.range(st, 'gAngle', 'Angle', 0, 360, 5, '°') : ''}
        ${t === 'image' ? `<div class="lpb-imgf">${sv(st, 'bgImg') ? `<img src="${esc(sv(st, 'bgImg'))}" alt="">` : ''}<div><button type="button" class="btn btn--sm btn--ghost" data-bg-pick="${id}|${target}">${sv(st, 'bgImg') ? 'Change image' : 'Upload image'}</button></div></div>${ctl.color(st, 'overlay', 'Overlay colour')}${ctl.select(st, 'fixed', 'Effect', { '': 'Normal', 1: 'Parallax (fixed)' })}` : ''}</details>`;
    };
    const boxControls = (st, opts = {}) => `<details class="lpb-acc" ${opts.open ? 'open' : ''}><summary>Spacing &amp; border</summary>
        ${ctl.box(st, 'Padding (px)', [['pt', 'Top'], ['pr', 'Right'], ['pb', 'Bottom'], ['pl', 'Left']])}
        ${opts.margin !== false ? ctl.box(st, 'Margin (px)', [['mt', 'Top'], ['mb', 'Bottom']]) : ''}
        ${opts.maxw ? ctl.range(st, 'maxw', 'Max width', 100, 1400, 10) : ''}
        ${opts.minH ? ctl.range(st, 'minH', 'Min height', 0, 1000, 10) : ''}${opts.minH || opts.valign ? ctl.select(st, 'valign', 'Vertical position', { '': 'Default', top: 'Top', middle: 'Middle', bottom: 'Bottom' }) : ''}
        <div class="lpb-2">${ctl.range(st, 'bw', 'Border', 0, 12)}${ctl.select(st, 'bs', 'Line', { '': 'Solid', dashed: 'Dashed', dotted: 'Dotted' })}</div>
        ${ctl.color(st, 'bc', 'Border colour')}${ctl.range(st, 'radius', 'Corner radius', 0, 80)}
        ${ctl.select(st, 'shadow', 'Shadow', { '': 'None', sm: 'Soft', md: 'Medium', lg: 'Large', glow: 'Brand glow' })}
        ${ctl.range(st, 'opacity', 'Opacity (%)', 0, 100, 5, '%')}</details>`;
    const targetsOf = w => { // inner parts the user can style
        const out = new Map();
        doc()?.querySelectorAll(`[data-w="${w.id}"] [data-e^="${w.id}|"]`).forEach(el => { const k = styleKey(el.dataset.e.split('|')[1]); if (!out.has(k)) out.set(k, k.replace(/^items\.\*\./, '').replace(/^(.)/, c => c.toUpperCase())); });
        return out;
    };
    const advControls = el => {
        const a = el.adv || {}, h = a.hide || {};
        return `<details class="lpb-acc" open><summary>Motion effects</summary>
            <label>Entrance animation<select data-adv="anim">${Object.entries(B.ANIMS).map(([k, l]) => `<option value="${k}" ${(a.anim || '') === k ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
            <label>Delay (ms)<input type="number" data-adv="delay" min="0" max="3000" step="100" value="${esc(a.delay || '')}" placeholder="0"></label>
            <p class="lpb-hint">Plays when visitors scroll to it — see it with Preview.</p></details>
            <details class="lpb-acc" open><summary>Responsive</summary>
            <label class="lpb-check"><input type="checkbox" data-adv="hide.d" ${h.d ? 'checked' : ''}> Hide on desktop</label>
            <label class="lpb-check"><input type="checkbox" data-adv="hide.t" ${h.t ? 'checked' : ''}> Hide on tablet</label>
            <label class="lpb-check"><input type="checkbox" data-adv="hide.m" ${h.m ? 'checked' : ''}> Hide on mobile</label>
            <p class="lpb-hint">Hidden items show faded and striped in the editor at that screen size.</p></details>
            <details class="lpb-acc"><summary>Custom attributes &amp; CSS</summary>
            <label>CSS ID<input data-adv="id" value="${esc(a.id || '')}" placeholder="e.g. offer (link to it with #offer)"></label>
            <label>CSS classes<input data-adv="cls" value="${esc(a.cls || '')}" placeholder="my-class another"></label>
            <label>Custom CSS<textarea data-adv="css" rows="5" class="lpb-code" placeholder="selector { border: 2px solid red; }\nselector h3 { color: #333; }">${esc(a.css || '')}</textarea></label>
            <p class="lpb-hint">Use <code>selector</code> for this element. Scripts are not allowed.</p></details>`;
    };
    const crumbs = f => {
        const c = [`<button type="button" data-crumb="${f.sec.id}" class="${f.kind === 'section' ? 'is-on' : ''}">Section</button>`];
        if (!B.isFull(f.sec) && f.kind !== 'section') c.push(`<button type="button" data-crumb="${f.col.id}" class="${f.kind === 'col' ? 'is-on' : ''}">Column</button>`);
        if (f.kind === 'widget') c.push(`<button type="button" data-crumb="${f.obj.id}" class="${sel.target === '_' ? 'is-on' : ''}">${esc(W[f.obj.type].label)}</button>`);
        if (f.kind === 'widget' && sel.target !== '_') c.push(`<button type="button" class="is-on">${esc(sel.target.replace(/^items\.\*\./, ''))}</button>`);
        return `<nav class="lpb-crumbs">${c.join(`<i>${oi('chevron-right')}</i>`)}</nav>`;
    };
    const openAcc = {};
    const drawEdit = () => {
        const box = $('[data-edit-panel]');
        $$('[data-etab]').forEach(b => b.setAttribute('aria-selected', b.dataset.etab === editTab ? 'true' : 'false'));
        if (!sel || !find(sel.id)) { box.innerHTML = '<div class="lpb-empty-edit"><b>Nothing selected</b><p>Click any section, column, widget or text on the page to edit it — or drag a widget in from the Add tab.</p></div>'; return; }
        const f = find(sel.id), o = f.obj;
        let out = crumbs(f);
        if (editTab === 'content') {
            if (f.kind === 'section') {
                if (!B.isFull(o)) out += `<label>Columns<span class="lpb-lays">${Object.entries(B.LAYOUTS).map(([k, v]) => `<button type="button" data-layout="${k}" class="${o.layout === k ? 'is-on' : ''}" title="${v[2]}"><i class="lpb-lay" style="grid-template-columns:${v[0]}">${'<i></i>'.repeat(v[1])}</i></button>`).join('')}</span></label>
                    <label>Content width<select data-sec="width"><option value="boxed" ${o.width !== 'full' ? 'selected' : ''}>Boxed (centred)</option><option value="full" ${o.width === 'full' ? 'selected' : ''}>Full width</option></select></label>
                    <label>Background preset<select data-sec="bg">${Object.entries(BG).map(([k, l]) => `<option value="${k}" ${o.bg === k ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
                    <p class="lpb-hint">Image, gradient and overlay backgrounds are in the Style tab.</p>`;
                if (B.isFull(o)) { const w = o.cols[0].widgets[0]; out += `<button type="button" class="btn btn--sm btn--ghost" data-crumb="${w.id}">Edit the ${esc(W[w.type].label.toLowerCase())} content ${oi('arrow-right')}</button>`; }
            } else if (f.kind === 'col') {
                out += `<p class="lpb-hint">Drag widgets into this column, or pick one in the Add tab. Background, padding and alignment are in the Style tab.</p>
                    <div class="lpb-block-actions"><button type="button" class="btn btn--sm btn--ghost" data-col-act="add">+ Add a column</button>${f.sec.cols.length > 1 ? '<button type="button" class="btn btn--sm btn--ghost lpb-danger" data-col-act="del">Delete column</button>' : ''}</div>`;
            } else {
                const d = W[o.type];
                if (d.variants) out += `<label>Layout<select data-wv>${Object.entries(d.variants).map(([k, l]) => `<option value="${k}" ${(o.v || Object.keys(d.variants)[0]) === k ? 'selected' : ''}>${l}</option>`).join('')}</select></label>`;
                out += `<p class="lpb-hint">Click text on the page to type over it.</p>`;
                if (o.type === 'products') out += shopPanel();
                for (const fl of d.fields) out += fl.type === 'list' ? (o.type === 'products' ? productsHtml(fl, o) : listHtml(fl, o)) : fieldHtml(fl, o.p, `${o.id}|`);
                if (o.type === 'contact' || o.type === 'form') out += formPanel();
                if (o.type === 'optin') out += leadPanel();
                if (o.type === 'rsvp') out += (B.rsvpWidget(P)?.id !== o.id ? '<p class="lpb-hint">This site has more than one RSVP form. They all send to one guest list, so they all ask the questions of the first one — change them there.</p>' : '') + rsvpPanel();
                if (o.type === 'paybtn') out += `<p class="lpb-hint">The button goes to this step's payment link — set it in the <b>Page</b> tab (Stripe, PayPal, Gumroad…). “No thanks” goes to the next step.</p>`;
            }
            if (f.kind !== 'col') out += `<div class="lpb-block-actions"><button type="button" class="btn btn--sm btn--ghost" data-panel-ed="dup">Duplicate</button><button type="button" class="btn btn--sm btn--ghost lpb-danger" data-panel-ed="del">Delete</button></div>`;
        } else if (editTab === 'style') {
            const target = f.kind === 'widget' ? sel.target || '_' : '_', st = styleObj(o.id, target);
            out += `<p class="lpb-hint">Editing ${device} ${devBadge()} — switch screen size in the top bar to style tablet and mobile.</p>`;
            if (f.kind === 'widget') {
                const parts = targetsOf(o);
                if (parts.size) out += `<label>Style which part?<select data-target>${[['_', 'Whole widget'], ...parts].map(([k, l]) => `<option value="${esc(k)}" ${target === k ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></label>`;
                out += typoControls(st) + (target === '_' ? bgControls(st, o.id, target) + boxControls(st, { open: true, maxw: true }) : boxControls(st, {}));
            } else if (f.kind === 'section') out += bgControls(st, o.id, '_') + boxControls(st, { open: true, minH: true }) + typoControls(st);
            else out += bgControls(st, o.id, '_') + boxControls(st, { open: true, valign: true, margin: false }) + typoControls(st);
            out += `<button type="button" class="btn btn--sm btn--ghost" data-reset-style>Reset ${device} styles</button>`;
        } else out += advControls(o);
        box.innerHTML = out;
        box.querySelectorAll('.lpb-acc').forEach(dd => { const k = dd.querySelector('summary').textContent; if (openAcc[k] !== undefined) dd.open = openAcc[k]; dd.addEventListener('toggle', () => (openAcc[k] = dd.open)); });
        box.querySelectorAll('[data-prod]').forEach(dd => dd.addEventListener('toggle', () => (dd.open ? openProd.add(dd.dataset.prod) : openProd.delete(dd.dataset.prod))));
    };
    /* ----- online store: each product in its own fold-out (photo, name, prices, options, label, sold out) */
    const openProd = new Set();
    const productsHtml = (f, w) => {
        const list = w.p.items || [], cur = P.site.shop?.currency, pre = `${w.id}|`;
        const cats = [...new Set(list.map(it => String(it.cat || '').trim()).filter(Boolean))];
        return `<div class="lpb-list lpb-prods"><span>${f.label} <small>(${list.length})</small></span><datalist id="lpb-cats-${esc(w.id)}">${cats.map(c => `<option value="${esc(c)}">`).join('')}</datalist>${list.map((it, i) => {
            const k = n => `items.${i}.${n}`, pr = B.money(it.price, cur), vals = it.vals || [], imgs = it.images || [];
            return `<details class="lpb-prod" data-prod="${esc(it.pid || '')}"${openProd.has(it.pid) ? ' open' : ''}><summary>
                <span class="lpb-thumb" ${it.image ? `style="background-image:url('${esc(it.image)}')"` : ''} aria-hidden="true">${it.image ? '' : oi('bag')}</span>
                <span class="lpb-prod__t"><b>${esc(it.name || 'Untitled product')}</b><small${pr ? '' : ' class="lpb-err-t"'}>${pr ? esc(pr) : 'No price yet'}${it.cat ? ' · ' + esc(it.cat) : ''}${it.sold ? ' · Sold out' : ''}</small></span></summary>
                <div class="lpb-prod__body">${fieldHtml(B.F.img(k('image'), 'Main photo'), w.p, pre)}
                <div class="lpb-pimgs"><span>More photos <small>(shown on the product’s page)</small></span><div>${imgs.map((src, j) => `<span class="lpb-pimg" style="background-image:url('${esc(src)}')"><button type="button" data-pimg-del="${i}|${j}" aria-label="Remove this photo">${oi('close')}</button></span>`).join('')}${imgs.length < 5 ? `<button type="button" class="lpb-pimg lpb-pimg--add" data-img-pick="${pre}${k('images')}.${imgs.length}" aria-label="Add a photo">${oi('plus')}</button>` : ''}</div></div>
                ${fieldHtml(B.F.text(k('name'), 'Name'), w.p, pre)}
                <div class="lpb-2">${fieldHtml(B.F.text(k('price'), 'Price', '24.99'), w.p, pre)}${fieldHtml(B.F.text(k('old'), 'Old price (shows a sale)', 'optional'), w.p, pre)}</div>
                ${fieldHtml(B.F.area(k('desc'), 'Short description (on the card)'), w.p, pre)}
                <div class="lpb-2"><label>Category<input data-f="${pre}${k('cat')}" value="${esc(it.cat || '')}" list="lpb-cats-${esc(w.id)}" maxlength="40" placeholder="e.g. Tops"></label>${fieldHtml(B.F.text(k('badge'), 'Label on photo', 'New, Sale…'), w.p, pre)}</div>
                <label>Type<select data-f="${pre}${k('kind')}" data-redraw-prod><option value="item" ${it.kind !== 'digital' && it.kind !== 'gift' ? 'selected' : ''}>Item to post or collect</option><option value="digital" ${it.kind === 'digital' ? 'selected' : ''}>Digital download (a file)</option><option value="gift" ${it.kind === 'gift' ? 'selected' : ''}>Gift card</option></select></label>
                ${it.kind === 'digital' ? fileHtml(it, i) : it.kind === 'gift' ? `<p class="lpb-hint">${oi('gift')} A gift card is worth its price. Once it’s paid, the buyer gets a code by email to spend in your shop (in the discount box at checkout). See the cards you sold in the Orders tab, under Codes.</p>` : ''}
                ${it.kind === 'gift' ? '' : `<div class="lpb-vals"><b>Sizes, colours…</b><label>Choice name<input data-f="${pre}${k('optName')}" value="${esc(it.optName || '')}" maxlength="30" placeholder="Size, Colour, Scent…"></label>
                    ${vals.map((v, j) => `<div class="lpb-vals__row"><input data-f="${pre}${k(`vals.${j}.v`)}" value="${esc(v.v || '')}" maxlength="60" placeholder="e.g. M" aria-label="Choice ${j + 1}"><input data-f="${pre}${k(`vals.${j}.price`)}" value="${esc(v.price || '')}" inputmode="decimal" maxlength="12" placeholder="${esc(pr || 'Price')}" aria-label="Price of choice ${j + 1} (empty: same as the product)"><button type="button" data-vals="${i}|${j}|del" aria-label="Remove">${oi('close')}</button></div>`).join('')}
                    <button type="button" class="btn btn--sm btn--ghost" data-vals="${i}|-1|add" ${vals.length >= 30 ? 'disabled' : ''}>${oi('plus')} Add a ${esc(String(it.optName || 'choice').toLowerCase())}</button>
                    <p class="lpb-hint">${vals.length ? 'Leave a price empty to use the product’s price.' : 'Optional — add sizes or colours customers pick from, each with its own price if you like.'}</p></div>
                ${vals.length ? `<div class="lpb-vals"><b>Second choice <small>(optional — e.g. colour with each size)</small></b><label>Choice name<input data-f="${pre}${k('opt2Name')}" value="${esc(it.opt2Name || '')}" maxlength="30" placeholder="Colour, Material…"></label>
                    ${(it.vals2 || []).map((v, j) => `<div class="lpb-vals__row lpb-vals__row--2"><input data-f="${pre}${k(`vals2.${j}.v`)}" value="${esc(v.v || '')}" maxlength="60" placeholder="e.g. Red" aria-label="Second choice ${j + 1}"><button type="button" data-vals2="${i}|${j}|del" aria-label="Remove">${oi('close')}</button></div>`).join('')}
                    <button type="button" class="btn btn--sm btn--ghost" data-vals2="${i}|-1|add" ${(it.vals2 || []).length >= 30 ? 'disabled' : ''}>${oi('plus')} Add a ${esc(String(it.opt2Name || 'choice').toLowerCase())}</button>
                    ${(it.vals2 || []).length ? '<p class="lpb-hint">Customers pick one of each. Stock can be counted for every combination.</p>' : ''}</div>` : ''}`}
                ${fieldHtml(B.F.area(k('long'), 'Product page text (optional)'), w.p, pre)}
                ${stockHtml(it, i)}
                ${fieldHtml(B.F.bool(k('sold'), 'Sold out'), w.p, pre)}
                <div class="lpb-block-actions"><button type="button" class="btn btn--sm btn--ghost" data-list="items|${i}|up" ${i === 0 ? 'disabled' : ''}>${oi('arrow-up')} Move up</button><button type="button" class="btn btn--sm btn--ghost" data-list="items|${i}|dup" ${list.length >= f.max ? 'disabled' : ''}>Duplicate</button><button type="button" class="btn btn--sm btn--ghost lpb-danger" data-list="items|${i}|del">Remove</button></div></div></details>`;
        }).join('')}${list.length < f.max ? `<button type="button" class="btn btn--sm btn--glow" data-list="items|-1|add">${oi('plus')} Add a product</button>` : `<p class="lpb-hint">Up to ${f.max} products in one section — add another Products section for more.</p>`}</div>`;
    };
    /* ----- files sold as downloads: uploaded to owlvix, kept privately, handed only to buyers of paid orders */
    let storeFiles = [], filesLoaded = '', uploadingFor = null;
    const loadFiles = async () => {
        if (!token || !P.site.shopId || filesLoaded === P.site.shopId) return;
        filesLoaded = P.site.shopId;
        try { storeFiles = (await api('GET', `/builder/stores/${P.site.shopId}/files`)).files; } catch { filesLoaded = ''; }
        drawEdit();
    };
    const fileHtml = (it, i) => {
        if (!P.site.shopId || !token) return `<p class="lpb-hint">${oi('download')} Switch on checkout (and sign in) to upload the file you sell.</p>`;
        loadFiles();
        const mb = n => (n < 1048576 ? Math.max(1, Math.round(n / 1024)) + ' KB' : (n / 1048576).toFixed(n < 10485760 ? 1 : 0) + ' MB');
        return `<div class="lpb-file"><b>${oi('download')} The file buyers get</b>
            ${storeFiles.length ? `<select data-file-pick="${i}" aria-label="File"><option value="">Choose a file…</option>${storeFiles.map(x2 => `<option value="${esc(x2.id)}" ${it.file === x2.id ? 'selected' : ''}>${esc(x2.name)} · ${mb(x2.size)}</option>`).join('')}</select>` : ''}
            <button type="button" class="btn btn--sm btn--ghost" data-file-upload="${i}">${oi('upload')} Upload a file</button>
            <p class="lpb-hint">${it.file ? 'Buyers can download it from their order page once they’ve paid (up to 10 times).' : 'PDF, audio, video, images, documents or a .zip — up to 25 MB. Buyers download it once they’ve paid.'}</p></div>`;
    };
    $('[data-pick-file]')?.addEventListener('change', async e => {
        const file = e.target.files[0]; e.target.value = '';
        const f = sel && find(sel.id); if (!file || !f || uploadingFor === null) return;
        if (file.size > 25 * 1048576) return toast('Files can be up to 25 MB — try zipping it, or a smaller version.', 'error');
        const body = new FormData(); body.append('file', file);
        toast('Uploading ' + file.name + '…', 'info');
        try {
            const res = await fetch(`/builder/stores/${P.site.shopId}/files`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Builder-Token': token, Accept: 'application/json' }, body });
            const j = await res.json().catch(() => ({}));
            if (!res.ok || !j.ok) throw new Error(j.error || 'The file couldn’t be uploaded. Please try again.');
            storeFiles.unshift(j.file);
            const it = f.obj.p.items[uploadingFor]; it.file = j.file.id; it.fileName = j.file.name; commit(); drawEdit(); toast('File uploaded — publish again to sell it', 'success');
        } catch (ex) { toast(ex.message, 'error'); }
    });
    /* ----- stock counts: kept by owlvix (orders lower them), saved as soon as they're typed */
    const trackOpen = new Set();
    const stockHtml = (it, i) => {
        if (!P.site.shopId) return `<div class="lpb-stock"><b>${oi('box')} Stock</b><p class="lpb-hint">Switch on checkout to count stock — products sell out by themselves.</p></div>`;
        if (!token) return `<div class="lpb-stock"><b>${oi('box')} Stock</b><p class="lpb-hint">Sign in to count stock.</p></div>`;
        if (!store()) { if (storeError) return `<div class="lpb-stock"><b>${oi('box')} Stock</b>${storeRetry()}</div>`; if (!storeLoading) loadStore().then(r => (r || storeError) && drawEdit()); return `<div class="lpb-stock"><b>${oi('box')} Stock</b><p class="lpb-hint">Loading…</p></div>`; }
        const st = store().stock || {}, keys = B.stockKeys(it), tracked = keys.some(([key]) => key in st) || trackOpen.has(it.pid);
        return `<div class="lpb-stock"><label class="lpb-check"><input type="checkbox" data-stock-track="${i}" ${tracked ? 'checked' : ''}> ${oi('box')} Count stock</label>
            ${tracked ? `${keys.map(([key, label]) => `<label class="lpb-stock__row"><span>${esc(label || 'In stock')}</span><input type="number" min="0" max="1000000" step="1" inputmode="numeric" data-stock-key="${esc(key)}" value="${esc(st[key] ?? '')}" placeholder="0"></label>`).join('')}
            <p class="lpb-hint">Orders take from these counts (cancelled orders give it back). At 0 it shows as sold out — changes reach your live shop straight away.</p>` : ''}</div>`;
    };
    const stockTimers = {};
    const saveStock = async patch => {
        try { const r = await api('POST', `/builder/stores/${P.site.shopId}/stock`, { stock: patch }); if (store()) store().stock = r.stock; }
        catch (ex) { toast(ex.message, 'error'); }
    };
    const syncPanelInputs = () => { $$('[data-edit-panel] [data-f]').forEach(el => { if (el === document.activeElement || el.type === 'checkbox') return; const [obj, path] = ownerOf(el.dataset.f); const v = getPath(obj || {}, path); if (typeof v === 'string') el.value = v; }); };
    $$('[data-etab]').forEach(b => b.addEventListener('click', () => { editTab = b.dataset.etab; drawEdit(); }));

    const editPanel = $('[data-tab-panel=edit]');
    const onPanelInput = e => {
        const el = e.target, f = sel && find(sel.id);
        if (el.dataset.f) {
            const [obj, path, id] = ownerOf(el.dataset.f); if (!obj) return;
            setPath(obj, path, el.type === 'checkbox' ? el.checked : el.value);
            commitSoon(); id === 'site' ? refreshChrome() : refreshEl(id); return;
        }
        if (el.dataset.linkTo || el.dataset.linkVal) {
            const key = el.dataset.linkTo || el.dataset.linkVal, [obj, path, id] = ownerOf(key), l = { ...(getPath(obj, path) || { to: 'contact' }) };
            if (el.dataset.linkTo) { l.to = el.value; l.val = el.value === 'page' ? P.pages[0].slug : ''; } else l.val = el.value;
            setPath(obj, path, l); commitSoon();
            if (id === 'site') { refreshChrome(); if (el.dataset.linkTo) drawSite(); } else { refreshEl(id); if (el.dataset.linkTo) drawEdit(); }
            return;
        }
        if (!f) return;
        if (el.hasAttribute('data-wv')) { f.obj.v = el.value; commit(); refreshEl(f.obj.id); return; }
        if (el.dataset.sec) { f.obj[el.dataset.sec] = el.value; commit(); refreshEl(f.obj.id); return; }
        if (el.hasAttribute('data-target')) { sel.target = el.value; markSelection(); drawEdit(); return; }
        if (el.dataset.sty) {
            const st = styleObj(f.obj.id, f.kind === 'widget' ? sel.target || '_' : '_'), v = el.value;
            if (v === '') delete st[DEV()][el.dataset.sty]; else st[DEV()][el.dataset.sty] = v;
            el.closest('.lpb-range')?.querySelectorAll('input').forEach(i => { if (i !== el) i.value = v; });
            refreshCss(); commitSoon(); return;
        }
        if (el.dataset.adv) {
            f.obj.adv ||= {};
            setPath(f.obj.adv, el.dataset.adv, el.type === 'checkbox' ? el.checked : el.value);
            commitSoon(); if (el.dataset.adv === 'css') refreshCss(); else refreshEl(f.obj.id);
        }
    };
    ['input', 'change'].forEach(ev => editPanel.addEventListener(ev, onPanelInput));
    editPanel.addEventListener('input', e => { // stock counts save on their own, a moment after typing stops
        const el = e.target.closest('[data-stock-key]'); if (!el || el.value === '') return;
        const key = el.dataset.stockKey; clearTimeout(stockTimers[key]);
        stockTimers[key] = setTimeout(() => saveStock({ [key]: Math.max(0, Math.floor(+el.value || 0)) }), 600);
    });
    editPanel.addEventListener('change', async e => {
        if (e.target.matches('[data-redraw-prod]')) return setTimeout(drawEdit); // the product's type: other fields
        const fs = e.target.closest('[data-file-pick]'), fo = sel && find(sel.id);
        if (fs && fo) { const it = fo.obj.p.items[+fs.dataset.filePick]; it.file = fs.value || null; it.fileName = storeFiles.find(x2 => x2.id === fs.value)?.name || ''; commit(); return drawEdit(); }
        const el = e.target.closest('[data-stock-track]'), f = sel && find(sel.id); if (!el || !f) return;
        const it = f.obj.p.items[+el.dataset.stockTrack]; B.fixPids(P);
        if (el.checked) { trackOpen.add(it.pid); drawEdit(); $(`[data-prod="${CSS.escape(it.pid)}"] [data-stock-key]`)?.focus(); return; }
        trackOpen.delete(it.pid);
        await saveStock(Object.fromEntries(B.stockKeys(it).map(([key]) => [key, null]))); drawEdit();
    });
    editPanel.addEventListener('click', e => {
        const f = sel && find(sel.id);
        const crumb = e.target.closest('[data-crumb]'); if (crumb) return select(crumb.dataset.crumb, '_');
        const sval = e.target.closest('[data-sty-val]');
        if (sval && f) {
            const [k, v] = sval.dataset.styVal.split('|'), st = styleObj(f.obj.id, f.kind === 'widget' ? sel.target || '_' : '_'), dev = st[DEV()];
            if (k === 'bgType' && v === '') { ['bgType', 'bg', 'g1', 'g2', 'gAngle', 'bgImg', 'overlay', 'fixed'].forEach(n => delete dev[n]); } // "None"
            else if (v === '') delete dev[k]; else dev[k] = v;
            commit(); refreshCss(); drawEdit(); return;
        }
        const bgp = e.target.closest('[data-bg-pick]'); if (bgp) { picking = 'style|' + bgp.dataset.bgPick; return $('[data-pick-image]').click(); }
        if (e.target.closest('[data-reset-style]') && f) { Object.values(f.obj.st || {}).forEach(t => (t[DEV()] = {})); commit(); refreshCss(); drawEdit(); return toast(`${device} styles cleared`); }
        const lay = e.target.closest('[data-layout]'); if (lay && f) return changeLayout(f.obj, lay.dataset.layout);
        const ca = e.target.closest('[data-col-act]');
        if (ca && f) {
            const s = f.sec;
            if (ca.dataset.colAct === 'add' && s.cols.length < 4) { s.cols.splice(f.ci + 1, 0, B.column()); s.layout = String(s.cols.length); }
            if (ca.dataset.colAct === 'del') { const [gone] = s.cols.splice(f.ci, 1); s.cols[Math.max(0, f.ci - 1)].widgets.push(...gone.widgets); s.layout = String(s.cols.length); sel = { kind: 'section', id: s.id, target: '_' }; }
            commit(); refreshMain(); drawEdit(); drawNavigator(); return;
        }
        const pick = e.target.closest('[data-img-pick]'); if (pick) { picking = pick.dataset.imgPick; return $('[data-pick-image]').click(); }
        const ip = e.target.closest('[data-icon-pick]'); if (ip) { const [id, path] = ip.dataset.iconPick.split('|'); return pickIcon(id, path); }
        const clear = e.target.closest('[data-img-clear]'); if (clear) { const [obj, path, id] = ownerOf(clear.dataset.imgClear); setPath(obj, path, ''); commit(); refreshEl(id); return drawEdit(); }
        const li = e.target.closest('[data-list]');
        if (li && f) {
            const [k, i, act] = li.dataset.list.split('|'), list = f.obj.p[k], fl = W[f.obj.type].fields.find(x2 => x2.k === k), n = +i;
            if (act === 'add') list.push(clone(fl.item));
            if (act === 'dup' && list[n]) list.splice(n + 1, 0, { ...clone(list[n]), pid: '' });
            if (act === 'del') list.splice(n, 1);
            if (f.obj.type === 'products' && (act === 'add' || act === 'dup')) { B.fixPids(P); openProd.add(list[act === 'add' ? list.length - 1 : n + 1].pid); }
            if (act === 'up' && n > 0) [list[n - 1], list[n]] = [list[n], list[n - 1]];
            commit(); refreshEl(f.obj.id); return drawEdit();
        }
        const pv = e.target.closest('[data-vals]');
        if (pv && f) {
            const [i, j, act] = pv.dataset.vals.split('|').map((v, n) => (n < 2 ? +v : v)), it = f.obj.p.items[i]; it.vals ||= [];
            if (act === 'add') it.vals.push({ vid: '', v: '', price: '' });
            if (act === 'del') it.vals.splice(j, 1);
            B.fixPids(P); commit(); refreshEl(f.obj.id); drawEdit();
            if (act === 'add') $(`[data-prod="${CSS.escape(it.pid)}"] .lpb-vals__row:last-of-type input`)?.focus();
            return;
        }
        const pv2 = e.target.closest('[data-vals2]');
        if (pv2 && f) {
            const [i, j, act] = pv2.dataset.vals2.split('|').map((v, n) => (n < 2 ? +v : v)), it = f.obj.p.items[i]; it.vals2 ||= [];
            if (act === 'add') it.vals2.push({ vid: '', v: '' });
            if (act === 'del') it.vals2.splice(j, 1);
            B.fixPids(P); commit(); refreshEl(f.obj.id); drawEdit();
            if (act === 'add') $(`[data-prod="${CSS.escape(it.pid)}"] .lpb-vals__row--2:last-of-type input`)?.focus();
            return;
        }
        const fu = e.target.closest('[data-file-upload]'); if (fu) { uploadingFor = +fu.dataset.fileUpload; return $('[data-pick-file]').click(); }
        const pd = e.target.closest('[data-pimg-del]');
        if (pd && f) { const [i, j] = pd.dataset.pimgDel.split('|').map(Number); f.obj.p.items[i].images.splice(j, 1); commit(); refreshEl(f.obj.id); return drawEdit(); }
        if (e.target.closest('[data-store-retry]')) { storeError = ''; loadStore().then(() => drawEdit()); return; }
        const pe = e.target.closest('[data-panel-ed]'); if (pe && f) return barAction(pe.dataset.panelEd, f.obj.id);
        if (e.target.closest('[data-activate-form]')) activateForm();
        if (e.target.closest('[data-activate-rsvp]')) activateRsvp();
        if (e.target.closest('[data-activate-leads]')) activateLeads();
        if (e.target.closest('[data-activate-shop]')) activateShop();
    });
    const changeLayout = (s, layout) => {
        const n = B.LAYOUTS[layout][1];
        while (s.cols.length < n) s.cols.push(B.column());
        while (s.cols.length > n) { const gone = s.cols.pop(); s.cols[s.cols.length - 1].widgets.push(...gone.widgets); }
        s.layout = layout; commit(); refreshMain(); drawEdit(); drawNavigator();
    };

    /* =========================================================== page panel: navigator, settings, popup */
    const drawNavigator = () => {
        const nav = $('[data-navigator]'); if (!nav) return;
        const secs = page().sections;
        nav.innerHTML = secs.map((s, i) => {
            const full = B.isFull(s), label = full ? W[s.cols[0].widgets[0].type].label : `${s.cols.length} column${s.cols.length > 1 ? 's' : ''}`;
            const on = sel && find(sel.id)?.sec.id === s.id && (sel.kind === 'section' || full);
            const colsHtml = full ? '' : `<ul>${s.cols.map((c, ci) => `<li><button type="button" data-nav="${c.id}" class="${sel?.id === c.id ? 'is-sel' : ''}">${oi('dot')} Column ${ci + 1}</button>${c.widgets.length ? `<ul>${c.widgets.map(w => `<li><button type="button" data-nav="${w.id}" class="${sel?.id === w.id ? 'is-sel' : ''}">${esc(W[w.type].label)}</button></li>`).join('')}</ul>` : ''}</li>`).join('')}</ul>`;
            return `<li><div class="lpb-nav-row"><button type="button" data-nav="${full ? s.cols[0].widgets[0].id : s.id}" class="${on ? 'is-sel' : ''}">${oi('grid')} ${esc(label)}</button>
                <button type="button" data-nav-mv="${i}|-1" ${i === 0 ? 'disabled' : ''} title="Move this section up the page" aria-label="Move this section up the page">${oi('arrow-up')}</button><button type="button" data-nav-mv="${i}|1" ${i === secs.length - 1 ? 'disabled' : ''} title="Move this section down the page" aria-label="Move this section down the page">${oi('arrow-down')}</button></div>${colsHtml}</li>`;
        }).join('') || '<li class="lpb-hint">This page is empty — add something from the Add tab.</li>';
    };
    const pagePanel = $('[data-tab-panel=page]');
    pagePanel.addEventListener('click', e => {
        const n = e.target.closest('[data-nav]');
        if (n) { select(n.dataset.nav, '_', null); doc()?.querySelector(`[data-w="${n.dataset.nav}"],[data-c="${n.dataset.nav}"],[data-s="${n.dataset.nav}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
        const mv = e.target.closest('[data-nav-mv]');
        if (mv) { const [i, d] = mv.dataset.navMv.split('|').map(Number), s = page().sections; [s[i], s[i + d]] = [s[i + d], s[i]]; commit(); refreshMain(); drawNavigator(); return; }
        if (e.target.closest('[data-popup-img]')) { picking = 'popup|image'; return $('[data-pick-image]').click(); }
        if (e.target.closest('[data-popup-img-clear]')) { page().popup.image = ''; commit(); return drawPage(); }
        if (e.target.closest('[data-page-savetpl]')) return savePageTemplate();
        if (e.target.closest('[data-page-del]')) {
            sweet({ icon: 'warning', title: `Delete the page “${page().title}”?`, text: 'All its sections go too. You can bring it back with Undo.', confirm: 'Yes, delete page', danger: true })
                .then(ok => { if (ok) { P.pages.splice(pageIdx, 1); pageIdx = 0; variant = 'a'; sel = null; commit(); renderAll(); syncVariantBadge(); toast('Page deleted'); } });
        }
    });
    const drawPage = () => {
        const pg = page(), pop = pg.popup ||= B.defaultsPopup();
        const keep = {}; $$('[data-page-panel] .lpb-acc').forEach(d => (keep[d.querySelector('summary').textContent] = d.open));
        const funnelHtml = FUNNEL ? funnelPanel(pg) : '';
        $('[data-page-panel]').innerHTML = funnelHtml + (FUNNEL ? '' : `<button type="button" class="btn btn--sm btn--ghost lpb-savepage" data-page-savetpl>${oi('star')} Save this page as my template</button>`) + `
            <details class="lpb-acc" ${FUNNEL ? '' : 'open'}><summary>Navigator</summary><ol class="lpb-nav" data-navigator></ol></details>
            <details class="lpb-acc"><summary>Page settings</summary>
            <label>Page name (in the menu)<input data-pg="title" value="${esc(pg.title)}" maxlength="40"></label>
            ${pg.slug === 'index' ? '<p class="lpb-hint">This is your home page.</p>' : `<label>Page address<span class="lpb-slug"><i>/</i><input data-pg="slug" value="${esc(pg.slug)}" maxlength="40"></span></label>`}
            <label class="lpb-check"><input type="checkbox" data-pg="menu" ${pg.menu ? 'checked' : ''}> Show in the menu</label>
            <label>Google title<input data-pg="seo.title" value="${esc(pg.seo.title)}" maxlength="70" placeholder="Leave empty to make one automatically"></label>
            <label>Google description<textarea data-pg="seo.description" rows="3" maxlength="170" placeholder="Leave empty to use the hero text">${esc(pg.seo.description)}</textarea></label>
            ${pg.slug !== 'index' ? `<button type="button" class="btn btn--sm btn--ghost lpb-danger" data-page-del>Delete this ${FUNNEL ? 'step' : 'page'}</button>` : ''}</details>
            <details class="lpb-acc"><summary>Popup</summary>
            <label class="lpb-check"><input type="checkbox" data-pg="popup.on" ${pop.on ? 'checked' : ''}> Show a popup on this page</label>
            <label>Open the popup<select data-pg="popup.trigger">${Object.entries({ delay: 'After a few seconds', exit: 'When the visitor is about to leave', scroll: 'Half-way down the page', click: 'Only when a button links to it' }).map(([k, l]) => `<option value="${k}" ${pop.trigger === k ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
            ${pop.trigger === 'delay' ? `<label>Seconds<input type="number" data-pg="popup.delay" min="1" max="120" value="${esc(pop.delay)}"></label>` : ''}
            <label>Title<input data-pg="popup.title" value="${esc(pop.title)}"></label>
            <label>Text<textarea data-pg="popup.text" rows="3">${esc(pop.text)}</textarea></label>
            <label>Button text<input data-pg="popup.btn" value="${esc(pop.btn)}"></label>
            <label>Button link${linkWidget('page|popup.link', pop.link)}</label>
            <div class="lpb-imgf"><span>Image (optional)</span>${pop.image ? `<img src="${esc(pop.image)}" alt="">` : ''}<div><button type="button" class="btn btn--sm btn--ghost" data-popup-img>${pop.image ? 'Change' : 'Upload'}</button>${pop.image ? '<button type="button" class="btn btn--sm btn--ghost" data-popup-img-clear>Remove</button>' : ''}</div></div>
            <p class="lpb-hint">Tip: set any button's link to “Open the popup”. Use Preview to see it working.</p></details>`;
        $$('[data-page-panel] .lpb-acc').forEach(d => { const k = d.querySelector('summary').textContent; if (k in keep) d.open = keep[k]; });
        drawNavigator();
    };
    const stepUrl = slug => SITE_BASE + (P.site.publishSlug || 'your-funnel') + (slug === 'index' ? '' : '/' + slug);
    /** The first step is the funnel's entry page (address …/name). After a reorder, give it that address and keep
        every "go to next step" choice pointing at the same steps. */
    /** A page's address changed: every button linking to it, and every "next step", follows. */
    const renameSlug = (from, to) => {
        if (!from || from === to) return;
        const fix = o => { if (!o || typeof o !== 'object') return; if (o.to === 'page' && o.val === from) o.val = to; Object.values(o).forEach(fix); };
        P.pages.forEach(p => { fix(p.sections); fix(p.sectionsB); fix(p.popup); if (p.next === from) p.next = to; });
        fix(P.site.navLink); fix(P.site.announce);
    };
    const ensureHome = () => {
        const first = P.pages[0], home = P.pages.find(p => p.slug === 'index');
        if (!first || first.slug === 'index' || !home) return;
        let renamed = slugify(home.title) || 'step';
        while (P.pages.some(p => p.slug === renamed)) renamed += '-2';
        const firstSlug = first.slug;
        renameSlug('index', '__home__'); renameSlug(firstSlug, 'index'); renameSlug('__home__', renamed);
        home.slug = renamed; first.slug = 'index';
        // Results are kept by step address: after this swap, the last 30 days of "index" belong to the old first step
        if (FUNNEL && P.site.publishSlug) sweet({ icon: 'info', title: `“${first.title}” is now the first step`, text: `It takes over the funnel’s main address. Results are kept by address, so for the next 30 days “${first.title}” also shows the visits of the old first step, and “${home.title}” starts again from 0.`, confirm: 'OK', cancel: false });
        else toast(`“${first.title}” is now the first step (the funnel's main address)`);
    };
    const funnelPanel = pg => {
        const steps = P.pages, nx = B.nextOf(pg, P);
        return `<details class="lpb-acc" open><summary>Funnel steps</summary><ol class="lpb-flow">${steps.map((p, i) => `<li class="${i === pageIdx ? 'is-on' : ''}">
                <button type="button" data-step-go="${i}"><b>${i + 1}</b><span>${esc(p.title)}<small>${esc(B.STEP_TYPES[p.type] || 'Page')}${p.ab?.on ? ' · A/B' : ''}</small></span></button>
                <span class="lpb-flow__mv"><button type="button" data-step-mv="${i}|-1" ${i === 0 ? 'disabled' : ''} title="Move this step earlier in the funnel" aria-label="Move this step earlier in the funnel">${oi('arrow-up')}</button><button type="button" data-step-mv="${i}|1" ${i === steps.length - 1 ? 'disabled' : ''} title="Move this step later in the funnel" aria-label="Move this step later in the funnel">${oi('arrow-down')}</button></span></li>`).join(`<li class="lpb-flow__arrow" aria-hidden="true">${oi('arrow-down')}</li>`)}</ol>
            <button type="button" class="btn btn--sm btn--ghost" data-act-step>${oi('plus')} Add step</button></details>
            <details class="lpb-acc" open><summary>This step</summary>
            <label>Step type<select data-pg="type">${Object.entries(B.STEP_TYPES).map(([k, l]) => `<option value="${k}" ${pg.type === k ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
            <label>After this step, go to<select data-pg="next"><option value="">${nx && !pg.next ? 'The following step (' + esc(nx.title) + ')' : 'The following step'}</option>${steps.filter(p => p.id !== pg.id).map(p => `<option value="${esc(p.slug)}" ${pg.next === p.slug ? 'selected' : ''}>${esc(p.title)}</option>`).join('')}</select></label>
            <p class="lpb-hint">Opt-in forms send people here after they sign up; buttons linked to “Next funnel step” too.</p>
            <label>Payment link (Stripe, PayPal, Gumroad…)<input data-pg="pay" value="${esc(pg.pay || '')}" placeholder="https://buy.stripe.com/…"></label>
            ${pg.pay ? `<p class="lpb-hint">In your payment provider, set the “after payment” page to:<br><code class="lpb-copy" data-copy="${esc(nx ? stepUrl(nx.slug) : '')}">${esc(nx ? stepUrl(nx.slug) : 'add a next step first')}</code></p>` : '<p class="lpb-hint">Buy buttons on this step go to this link. No money passes through Owlvix.</p>'}
            </details>
            <details class="lpb-acc" open><summary>A/B test</summary>
            <label class="lpb-check"><input type="checkbox" data-ab-on ${pg.ab?.on ? 'checked' : ''}> Test two versions of this step</label>
            ${pg.ab?.on ? `<div class="lpb-seg lpb-seg--wide">${['a', 'b'].map(v => `<button type="button" data-variant="${v}" class="${variant === v ? 'is-on' : ''}">Edit version ${v.toUpperCase()}</button>`).join('')}</div>
            <p class="lpb-hint">Visitors get version A or B at random. Compare sign-ups in the Results tab, then keep the winner.</p>
            <div class="lpb-block-actions"><button type="button" class="btn btn--sm btn--ghost" data-ab-copy>Copy A into B</button><button type="button" class="btn btn--sm btn--ghost" data-ab-keep="a">Keep A</button><button type="button" class="btn btn--sm btn--ghost" data-ab-keep="b">Keep B</button></div>` : '<p class="lpb-hint">Try a different headline, offer or layout and see which gets more sign-ups.</p>'}</details>`;
    };
    pagePanel.addEventListener('click', async e => {
        if (!FUNNEL) return;
        const pg = P.pages[pageIdx];
        const go = e.target.closest('[data-step-go]'); if (go) { $('[data-pages]').value = go.dataset.stepGo; return switchPage(+go.dataset.stepGo); }
        const mv = e.target.closest('[data-step-mv]');
        if (mv) { const [i, d] = mv.dataset.stepMv.split('|').map(Number), st = P.pages; [st[i], st[i + d]] = [st[i + d], st[i]]; if (pageIdx === i) pageIdx = i + d; else if (pageIdx === i + d) pageIdx = i; ensureHome(); commit(); drawPages(); refreshChrome(); refreshMain(); return drawPage(); }
        if (e.target.closest('[data-act-step]')) return addStep();
        const v = e.target.closest('[data-variant]'); if (v) { variant = v.dataset.variant; sel = null; renderCanvas(); drawEdit(); drawPage(); syncVariantBadge(); return toast(`Editing version ${variant.toUpperCase()}`); }
        if (e.target.closest('[data-ab-copy]') && await sweet({ icon: 'warning', title: 'Copy version A into B?', text: 'Version B is replaced by a copy of A. You can undo.', confirm: 'Copy A into B' })) { pg.sectionsB = pg.sections.map(B.reId); commit(); if (variant === 'b') renderCanvas(); return toast('B is now a copy of A'); }
        const keep = e.target.closest('[data-ab-keep]');
        if (keep && await sweet({ icon: 'question', title: `Keep version ${keep.dataset.abKeep.toUpperCase()}?`, text: 'The A/B test ends and the other version is removed. You can undo.', confirm: `Keep ${keep.dataset.abKeep.toUpperCase()}` })) {
            if (keep.dataset.abKeep === 'b' && pg.sectionsB) pg.sections = pg.sectionsB;
            pg.sectionsB = null; pg.ab = { on: false }; variant = 'a'; commit(); renderCanvas(); drawPage(); syncVariantBadge(); return toast('A/B test ended');
        }
        const cp = e.target.closest('[data-copy]'); if (cp && cp.dataset.copy) { navigator.clipboard?.writeText(cp.dataset.copy).then(() => toast('Copied')).catch(() => {}); }
    });
    pagePanel.addEventListener('change', e => {
        if (!e.target.matches('[data-ab-on]')) return;
        const pg = P.pages[pageIdx];
        pg.ab = { on: e.target.checked };
        if (e.target.checked && !pg.sectionsB?.length) pg.sectionsB = pg.sections.map(B.reId);
        if (!e.target.checked) variant = 'a';
        commit(); renderCanvas(); drawPage(); syncVariantBadge();
    });
    ['input', 'change'].forEach(ev => pagePanel.addEventListener(ev, e => {
        const el = e.target, pg = page();
        if (el.dataset.linkTo || el.dataset.linkVal) {
            const l = { ...(pg.popup.link || { to: 'contact' }) };
            if (el.dataset.linkTo) { l.to = el.value; l.val = el.value === 'page' ? P.pages[0].slug : ''; } else l.val = el.value;
            pg.popup.link = l; commitSoon(); if (el.dataset.linkTo) drawPage(); return;
        }
        const k = el.dataset.pg; if (!k) return;
        let v = el.type === 'checkbox' ? el.checked : el.value;
        if (k === 'slug') { v = slugify(v) || pg.slug; if (P.pages.some(p => p.id !== pg.id && p.slug === v) || v === 'index') return; renameSlug(pg.slug, v); }
        setPath(pg, k, v); commitSoon();
        if (k === 'title' || k === 'menu') { refreshChrome(); drawPages(); }
        if (k === 'popup.trigger' && e.type === 'change') drawPage();
        if (FUNNEL && ['type', 'next', 'title'].includes(k) && e.type === 'change') { drawPages(); drawPage(); refreshMain(); }
        if (FUNNEL && k === 'pay') { refreshMain(); if (e.type === 'change') drawPage(); }
    }));

    /* =========================================================== site panel (global settings) */
    const syncGlobals = () => { const c = P.site.colors; B.GLOBAL.forEach(k => root.style.setProperty('--g-' + k, hex(c[k]))); };
    const drawSite = () => {
        const s = P.site, c = s.colors, fo = s.fonts;
        const keep = {}; $$('[data-site-panel] .lpb-acc').forEach(d => (keep[d.querySelector('summary').textContent] = d.open));
        $('[data-site-panel]').innerHTML = (KIT === 'store' ? shopSettings() : '') + `
            <details class="lpb-acc" ${KIT === 'store' ? '' : 'open'}><summary>Global colours</summary>
            <div class="lpb-globals">${[['primary', 'Primary'], ['secondary', 'Secondary'], ['accent', 'Accent'], ['text', 'Text'], ['bg', 'Background']].map(([k, l]) => `<label><input type="color" data-site="colors.${k}" value="${hex(c[k])}"><span>${l}</span></label>`).join('')}</div>
            <p class="lpb-hint">Change one and everything using it updates. Pick these colours in any Style tab.</p></details>
            <details class="lpb-acc" open><summary>Global fonts</summary>
            <label>Headings<select data-site="fonts.heading">${Object.entries(B.FONTS).map(([k, l]) => `<option value="${esc(k)}" ${fo.heading === k ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></label>
            <label>Body text<select data-site="fonts.body">${Object.entries(B.FONTS).map(([k, l]) => `<option value="${esc(k)}" ${fo.body === k ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></label>
            <label>Base text size (px)<input type="number" data-site="fonts.base" min="13" max="22" value="${esc(fo.base)}"></label></details>
            <details class="lpb-acc"><summary>Brand &amp; look</summary>
            <label>Business name<input data-site="name" value="${esc(s.name)}" maxlength="60"></label>
            ${fieldHtml(B.F.img('logo', 'Logo (optional)'), s, 'site|')}
            <div class="lpb-2"><label>Theme<select data-site="theme"><option value="light" ${s.theme !== 'dark' ? 'selected' : ''}>Light</option><option value="dark" ${s.theme === 'dark' ? 'selected' : ''}>Dark</option></select></label>
            <label>Corners<select data-site="radius">${['round', 'soft', 'sharp'].map(k => `<option ${s.radius === k ? 'selected' : ''} value="${k}">${{ round: 'Round', soft: 'Soft', sharp: 'Sharp' }[k]}</option>`).join('')}</select></label></div>
            <label>Button shape<select data-site="buttons">${['pill', 'rounded', 'square'].map(k => `<option ${s.buttons === k ? 'selected' : ''} value="${k}">${{ pill: 'Pill', rounded: 'Rounded', square: 'Square' }[k]}</option>`).join('')}</select></label></details>
            <details class="lpb-acc"${s.bare ? ' open' : ''}><summary>Header</summary>
            <label class="lpb-check"><input type="checkbox" data-site="bare" ${s.bare ? 'checked' : ''}> Simple page — no header or menu, small footer</label>
            ${s.bare ? '<p class="lpb-hint">Good for link-in-bio and coming soon pages. Untick to show your name, logo and menu at the top.</p>' : `<label class="lpb-check"><input type="checkbox" data-site="sticky" ${s.sticky !== false ? 'checked' : ''}> Sticky header (stays on top when scrolling)</label>
            <label>Header button text<input data-site="navBtn" value="${esc(s.navBtn || '')}" placeholder="Leave empty to hide"></label>
            <label>Header button link${linkWidget('site|navLink', s.navLink)}</label>`}
            <label class="lpb-check"><input type="checkbox" data-site="announce.on" ${s.announce?.on ? 'checked' : ''}> Announcement bar above the header</label>
            ${s.announce?.on ? `<label>Bar text<input data-site="announce.text" value="${esc(s.announce.text || '')}"></label><label>Link text (optional)<input data-site="announce.linkText" value="${esc(s.announce.linkText || '')}"></label><label>Link${linkWidget('site|announce.link', s.announce.link)}</label>` : ''}</details>
            <details class="lpb-acc"><summary>Footer &amp; social</summary>
            <label>Footer text<textarea data-site="footer" rows="2">${esc(s.footer || '')}</textarea></label>
            ${Object.entries(B.SOCIAL).map(([k, v]) => `<label>${v[0]}<input data-site="social.${k}" value="${esc(s.social?.[k] || '')}" placeholder="https://…"></label>`).join('')}
            <label class="lpb-check"><input type="checkbox" data-site="backTop" ${s.backTop ? 'checked' : ''}> Back-to-top button</label></details>
            <details class="lpb-acc"${KIT === 'menu' ? ' open' : ''}><summary>QR code</summary><div data-qr-box></div></details>
            <details class="lpb-acc"><summary>Custom CSS</summary>
            <textarea data-site="css" rows="8" class="lpb-code" placeholder=".hero-copy h1 { letter-spacing: -2px; }">${esc(s.css || '')}</textarea><p class="lpb-hint">Added to every page. Scripts are not allowed.</p></details>
            <details class="lpb-acc"><summary>Start over</summary><p class="lpb-hint">Replace everything with an example (you can undo).</p>
            <div class="lpb-chips">${Object.entries(EXAMPLES).map(([k, v]) => `<button type="button" class="lpb-chip" data-example="${k}">${esc(v[0])}</button>`).join('')}</div></details>`;
        $$('[data-site-panel] .lpb-acc').forEach(d => { const k = d.querySelector('summary').textContent; if (k in keep) d.open = keep[k]; });
        syncGlobals(); drawQr($('[data-qr-box]'));
    };
    const sitePanel = $('[data-tab-panel=site]');
    sitePanel.addEventListener('change', e => {
        const add = e.target.closest('[data-zone-add]'); if (!add || !add.value) return;
        const z = P.site.shop.zones[+add.dataset.zoneAdd]; P.site.shop.zones.forEach(o => (o.countries = o.countries.filter(c => c !== add.value))); // a country is in one zone
        z.countries.push(add.value); commit(); drawSite();
    });
    ['input', 'change'].forEach(ev => sitePanel.addEventListener(ev, e => {
        const el = e.target;
        if (el.dataset.linkTo || el.dataset.linkVal || el.dataset.f) return onPanelInput(e);
        const k = el.dataset.site; if (!k) return;
        P.site.social ||= {}; P.site.announce ||= {};
        const fontBefore = JSON.stringify([P.site.fonts.heading, P.site.fonts.body]);
        setPath(P.site, k, el.type === 'checkbox' ? el.checked : el.value);
        commitSoon();
        if (k.startsWith('colors.')) syncGlobals();
        if (k.startsWith('fonts.') && JSON.stringify([P.site.fonts.heading, P.site.fonts.body]) !== fontBefore) return renderCanvas();
        if (/^(colors|theme|radius|buttons|css|fonts)/.test(k)) refreshCss(); else refreshChrome();
        if ((k === 'announce.on' || k === 'bare') && e.type === 'change') drawSite();
        if (k.startsWith('shop.')) { refreshMain(); if (e.type === 'change' && (el.type === 'checkbox' || el.tagName === 'SELECT')) drawSite(); }
    }));
    sitePanel.addEventListener('click', e => {
        const exb = e.target.closest('[data-example]');
        if (exb) { useExample(exb.dataset.example); return; }
        const pick = e.target.closest('[data-img-pick]'); if (pick) { picking = pick.dataset.imgPick; return $('[data-pick-image]').click(); }
        if (e.target.closest('[data-img-clear]')) { P.site.logo = ''; commit(); refreshChrome(); drawSite(); }
        if (e.target.closest('[data-qr-publish]')) $('[data-act=publish]').click();
        if (e.target.closest('[data-activate-shop]')) activateShop();
        const sh = P.site.shop, zoneDel = e.target.closest('[data-zone-del]'), zoneC = e.target.closest('[data-zone-c]');
        if (e.target.closest('[data-zones-on]')) { sh.zones = [{ name: 'Home country', countries: [], fee: sh.fee, freeOver: sh.freeOver }, { name: 'Everywhere else', countries: [], fee: '15', freeOver: '' }]; commit(); drawSite(); toast('Add your country to the first zone'); }
        if (e.target.closest('[data-zones-off]')) { sh.zones = []; commit(); drawSite(); }
        if (e.target.closest('[data-zone-new]')) { sh.zones.push({ name: '', countries: [], fee: '', freeOver: '' }); commit(); drawSite(); }
        if (zoneDel) { sh.zones.splice(+zoneDel.dataset.zoneDel, 1); commit(); drawSite(); }
        if (zoneC) { const [zi, c] = zoneC.dataset.zoneC.split('|'); sh.zones[+zi].countries = sh.zones[+zi].countries.filter(x2 => x2 !== c); commit(); drawSite(); }
        if (e.target.closest('[data-store-retry]')) { storeError = ''; loadStore().then(() => drawSite()); }
        if (e.target.closest('[data-shop-signin]')) needAccount(async () => { if (dlg.open) dlg.close(); await loadStore(); drawSite(); });
        const on = e.target.closest('[data-stripe-on]'), off = e.target.closest('[data-stripe-off]');
        if (on) stripeConnect(on.dataset.stripeOn);
        if (off) stripeDisconnect(off.dataset.stripeOff);
    });

    /* =========================================================== tabs, pages, toolbar */
    const showTab = name => {
        $$('[data-tab]').forEach(b => b.setAttribute('aria-selected', b.dataset.tab === name ? 'true' : 'false'));
        $$('[data-tab-panel]').forEach(p => p.classList.toggle('is-active', p.dataset.tabPanel === name));
        if (name === 'page') drawPage();
        if (name === 'site') drawSite();
        if (name === 'edit') drawEdit();
        if (name === 'results') drawResults();
        if (name === 'orders') drawOrders();
    };
    $$('[data-tab]').forEach(b => b.addEventListener('click', () => showTab(b.dataset.tab)));
    const drawPages = () => { $('[data-pages]').innerHTML = P.pages.map((p, i) => `<option value="${i}" ${i === pageIdx ? 'selected' : ''}>${FUNNEL ? `Step ${i + 1} · ${esc(p.title)}` : esc(p.title) + (p.slug === 'index' ? ' (home)' : '')}</option>`).join(''); syncVariantBadge(); };
    const switchPage = i => { if (i < 0 || !P.pages[i]) return; pageIdx = i; variant = 'a'; sel = null; drawPages(); renderCanvas(); drawEdit(); drawPage(); };
    const syncVariantBadge = () => { const b = $('[data-variant-badge]'); if (!b) return; const on = P.pages[pageIdx]?.ab?.on; b.hidden = !on; b.textContent = `A/B · editing ${variant.toUpperCase()}`; b.dataset.v = variant; };
    $('[data-pages]').addEventListener('change', e => switchPage(+e.target.value));
    const addStep = async () => {
        if (P.pages.length >= 10) return sweet({ icon: 'info', title: 'You have 10 steps', text: 'That is the most one funnel can have.', cancel: false, confirm: 'Got it' });
        const html = `<div class="step-types">${Object.entries(B.STEP_TYPES).map(([k, l]) => `<button type="button" data-pick="${k}">${esc(l)}</button>`).join('')}</div>`;
        const type = await sweet({ icon: 'question', title: 'Add a funnel step', text: 'What kind of step is it?', html, confirm: false });
        if (!type || !B.STEP_TYPES[type]) return;
        const title = await sweet({ icon: 'question', title: 'Name this step', input: { value: B.STEP_TYPES[type], max: 40 }, confirm: 'Add step' });
        if (!title) return;
        const pg = B.newStep(title.slice(0, 40), type, P.pages.length);
        while (P.pages.some(p => p.slug === pg.slug)) pg.slug += '-2';
        P.pages.push(pg); commit(); switchPage(P.pages.length - 1); showTab('page');
        track('funnel_add_step', { type });
    };
    /** The name suggested for a page made from a template: "Pricing"; for "Home — gym / fitness" the kind ("Gym"), not a second "Home". */
    const pageName = key => { const l = B.TEMPLATES.pages[key][0], [a, b] = l.split(' — '); if (a !== 'Home' || !b) return a; const n = b.split(' /')[0]; return n[0].toUpperCase() + n.slice(1); };
    const addPage = async (preset, tpl) => {
        if (FUNNEL) return addStep();
        if (P.pages.length >= 10) return sweet({ icon: 'info', title: 'You have 10 pages', text: 'That is the most one site can have. Delete a page to add another.', cancel: false, confirm: 'Got it' });
        const title = await sweet({ icon: 'question', title: 'Add a new page', text: 'What should the page be called? It appears in your menu.', input: { value: preset || 'About', placeholder: 'About, Services, Contact…', max: 40 }, confirm: 'Add page' });
        if (!title) return;
        const pg = B.newPage(title.slice(0, 40), P.pages.length, tpl?.startsWith('mypage-') ? '' : tpl);
        if (tpl?.startsWith('mypage-')) { try { pg.sections = myPageSections(+tpl.slice(7)); } catch (ex) { return toast(ex.message, 'error'); } }
        while (P.pages.some(p => p.slug === pg.slug)) pg.slug += '-2';
        if (P.site.bare) { P.site.bare = false; toast('Header menu turned on so visitors can reach the new page'); } // a simple page has no menu
        P.pages.push(pg); commit(); switchPage(P.pages.length - 1); showTab('page');
        track('builder_add_page', { template: tpl || '' });
    };
    $('[data-act=add-page]').addEventListener('click', () => addPage());

    const fit = () => { const w = { desktop: 1280, tablet: 820 }[device]; screen.style.setProperty('--s', w ? Math.min(1, screen.clientWidth / w).toFixed(4) : 1); screen.style.setProperty('--w', (w || 390) + 'px'); };
    const setDevice = d => {
        device = d; screen.dataset.device = d;
        $$('button[data-device]').forEach(b => b.setAttribute('aria-pressed', b.dataset.device === d ? 'true' : 'false'));
        fit(); if ($('[data-tab-panel=edit]').classList.contains('is-active') && editTab === 'style') drawEdit();
    };
    $$('button[data-device]').forEach(b => b.addEventListener('click', () => setDevice(b.dataset.device)));
    new ResizeObserver(fit).observe(screen);
    $('[data-act=undo]').addEventListener('click', doUndo);
    $('[data-act=redo]').addEventListener('click', doRedo);
    $('[data-act=preview]').addEventListener('click', () => {
        // The page runs in a sandboxed frame (its own, empty origin): even a mistake in its HTML couldn't reach this
        // site's storage (the sign-in token) — the preview window itself only holds the frame
        const w = window.open('', '_blank');
        if (!w) return toast('Allow pop-ups to see the preview.');
        w.document.write(`<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>${esc((P.site.name || 'Preview') + ' — preview')}</title>
            <style>html,body{margin:0;height:100%}iframe{display:block;width:100%;height:100%;border:0}</style></head><body><iframe sandbox="allow-scripts allow-forms allow-popups allow-modals" title="Preview"></iframe></body></html>`);
        w.document.close();
        w.document.querySelector('iframe').srcdoc = B.render(P, page(), 'download', { runtime: RUNTIME });
    });

    // Full screen
    const fullBtn = $('[data-act=fullscreen]');
    const setFull = on => {
        root.classList.toggle('is-full', on);
        document.documentElement.classList.toggle('lpb-locked', on);
        fullBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
        fullBtn.querySelector('span').textContent = on ? 'Exit full screen' : 'Full screen';
        if (on && !document.fullscreenElement) document.documentElement.requestFullscreen?.().catch(() => {});
        if (!on && document.fullscreenElement) document.exitFullscreen?.().catch(() => {});
        setTimeout(fit, 60);
    };
    fullBtn.addEventListener('click', () => setFull(!root.classList.contains('is-full')));
    document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement && root.classList.contains('is-full')) setFull(false); });

    // Phones: the toolbar is one row (page · Save · Publish · ☰). The other tools move into the ☰ menu, and back to
    // their places on wider screens. Moving keeps each button's own click handler.
    (() => {
        const bar = root.querySelector('.lpb-top'), last = bar?.querySelector('.lpb-top__group:last-of-type');
        if (!bar || !last) return;
        last.insertAdjacentHTML('beforeend', '<button type="button" class="lpb-tb lpb-more-btn" data-more aria-expanded="false" aria-controls="lpb-more-menu" aria-label="More tools" title="More tools"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>');
        bar.insertAdjacentHTML('beforeend', '<div class="lpb-more" id="lpb-more-menu" data-more-menu hidden></div>');
        const btn = bar.querySelector('[data-more]'), menu = bar.querySelector('[data-more-menu]');
        const mq = window.matchMedia('(max-width: 760px)');
        // in menu order; [data-cloud-list]/[data-cloud-signin] are "My sites"/"My funnels", .wz-open is Quick setup
        const EXTRA = ['[data-variant-badge]', '.wz-open', '[data-cloud-list]', '[data-cloud-signin]', '[data-act="add-page"]', '[data-act="undo"]', '[data-act="redo"]', '[data-act="preview"]', '[data-act="fullscreen"]', '[data-act="download"]'];
        const close = () => { menu.hidden = true; btn.setAttribute('aria-expanded', 'false'); };
        const sync = () => {
            if (mq.matches) {
                for (const sel of EXTRA) {
                    const el = bar.querySelector(`.lpb-top__group ${sel}`);
                    if (!el) continue;
                    const ph = document.createElement('span'); ph.hidden = true; ph.dataset.morePh = '';
                    el.before(ph); el._morePh = ph; menu.append(el);
                }
                // keep the menu in the order above, whatever order the buttons arrived in
                EXTRA.forEach(sel => { const el = menu.querySelector(`:scope > ${sel}`); if (el) menu.append(el); });
            } else {
                [...menu.children].forEach(el => { if (el._morePh?.isConnected) el._morePh.replaceWith(el); });
                close();
            }
        };
        btn.addEventListener('click', e => {
            e.stopPropagation(); sync(); // Quick setup and My sites can arrive after the page loads
            const open = menu.hidden; menu.hidden = !open; btn.setAttribute('aria-expanded', String(open));
        });
        // pick something in the menu (a button, or a saved site from the list): the menu closes
        menu.addEventListener('click', e => { if (e.target.closest('button') && !e.target.closest('[data-act="undo"],[data-act="redo"]')) close(); });
        menu.addEventListener('change', close);
        document.addEventListener('click', e => { if (!menu.hidden && !menu.contains(e.target) && e.target !== btn) close(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && !menu.hidden) { close(); btn.focus(); } });
        mq.addEventListener('change', sync);
        sync(); setTimeout(sync, 0);
    })();

    // Save / open project
    const save = (blob, name) => { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.append(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 3000); };
    const baseName = () => slugify(P.site.name) || 'my-website';

    /* =========================================================== QR code (Site tab, and after publishing) */
    const loadQr = () => window.OwlQR ? Promise.resolve() : (loadQr.p ||= new Promise((ok, fail) => {
        const sc = document.createElement('script'); sc.src = root.dataset.qr || '/js/qr.js'; sc.onload = ok;
        sc.onerror = () => { loadQr.p = null; sc.remove(); fail(new Error('The QR code maker could not load. Please try again.')); };
        document.head.append(sc);
    }));
    /** The site's address, only when this account has it live (not just a name from an opened file, or an unpublished site). */
    const liveUrl = () => { const slug = P.site.publishSlug; return slug && account?.sites?.some(s => s.slug === slug && s.status === 'live') ? SITE_BASE + slug : ''; };
    let qrMemo = { url: '', svg: '' }, qrTurn = 0, accountLoading = null, accountFailedAt = 0;
    const qrSvg = url => (qrMemo.url === url ? qrMemo.svg : (qrMemo = { url, svg: OwlQR.svg(url) }).svg);
    const redrawSiteQr = () => drawQr($('[data-site-panel] [data-qr-box]'));
    /** The QR box: preview, downloads and printable table cards — once the site has a web address. */
    const drawQr = async box => {
        if (!box) return;
        const turn = String(++qrTurn); box.dataset.qrTurn = turn; const stale = () => box.dataset.qrTurn !== turn; // a later call owns the box
        if (!account && token && P.site.publishSlug && Date.now() - accountFailedAt > 60e3) { // is it live for this account?
            await (accountLoading ||= loadAccount().catch(() => { accountFailedAt = Date.now(); }).finally(() => { accountLoading = null; }));
        }
        if (stale()) return;
        const url = liveUrl();
        if (!url) { box.innerHTML = `<p class="lpb-hint">Publish your site to get a QR code people can scan${KIT === 'menu' ? ' at the table to see your menu' : ''}.</p><button type="button" class="btn btn--sm btn--glow" data-qr-publish>Publish to get a QR code</button>`; return; }
        try { await loadQr(); } catch (ex) { if (!stale()) box.innerHTML = `<p class="lpb-hint">${esc(ex.message)}</p>`; return; }
        if (stale() || url !== liveUrl()) return;
        box.innerHTML = `<div class="lpb-qr"><div class="lpb-qr__img">${qrSvg(url)}</div><div><p class="lpb-hint">Scans to <b>${esc(url.replace(/^https?:\/\//, ''))}</b>${KIT === 'menu' ? ' — print it for tables, windows and flyers.' : '.'}</p></div>
            <div class="lpb-qr__btns"><button type="button" class="btn btn--sm btn--glow" data-qr-dl="png">Download PNG</button><button type="button" class="btn btn--sm btn--ghost" data-qr-dl="svg">SVG (for print shops)</button><button type="button" class="btn btn--sm btn--ghost" data-qr-dl="cards">${KIT === 'menu' ? 'Print table cards' : 'Print QR cards'}</button></div></div>`;
    };
    /** Printable A4 sheet with 4 cards: name, a short line, the code and the address. */
    const printCards = url => {
        const name = esc(P.site.name || 'Scan me'), line = KIT === 'menu' ? 'Scan to see our menu' : KIT === 'event' ? 'Scan for details & RSVP' : 'Scan to visit our website';
        const c = hex(P.site.colors?.primary), card = `<div class="card"><b>${name}</b><span>${line}</span>${OwlQR.svg(url, { color: '#111827' })}<small>${esc(url.replace(/^https?:\/\//, ''))}</small></div>`;
        const w = open('', '_blank'); if (!w) return toast('Allow pop-ups to print the cards');
        w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${name} — QR cards</title><style>@page{size:A4;margin:12mm}body{margin:0;font-family:Arial,Helvetica,sans-serif}
            .sheet{display:grid;grid-template-columns:1fr 1fr;gap:8mm}.card{border:2px dashed #cbd5e1;border-radius:6mm;padding:8mm;text-align:center;display:grid;gap:3mm;justify-items:center;break-inside:avoid}
            .card b{font-size:20pt;color:${c}}.card span{font-size:12pt;font-weight:bold;color:#111827}.card svg{width:52mm;height:52mm}.card small{font-size:9pt;color:#475569}
            .tip{font-size:10pt;color:#475569;margin:0 0 6mm}@media print{.tip{display:none}}</style></head><body><p class="tip">Tip: print, cut along the dashed lines and put one on each table. Test-scan one first.</p><div class="sheet">${card.repeat(4)}</div><script>onload=()=>setTimeout(()=>print(),300)<\/script></body></html>`);
        w.document.close();
    };
    root.addEventListener('click', e => {
        const b = e.target.closest('[data-qr-dl]'); if (!b) return;
        const url = liveUrl(); if (!url || !window.OwlQR) return; // the buttons only appear once the QR maker has loaded
        if (b.dataset.qrDl === 'png') OwlQR.canvas(url, 16).toBlob(blob => save(blob, baseName() + '-qr.png'));
        else if (b.dataset.qrDl === 'svg') save(new Blob([OwlQR.svg(url, { size: 1024 })], { type: 'image/svg+xml' }), baseName() + '-qr.svg');
        else printCards(url);
        track('builder_qr', { kind: b.dataset.qrDl });
    });
    // Download: one page → HTML, several → ZIP
    const crcTable = (() => { const tb = new Uint32Array(256); for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; tb[n] = c >>> 0; } return tb; })();
    const crc32 = bytes => { let c = 0xffffffff; for (let i = 0; i < bytes.length; i++) c = crcTable[(c ^ bytes[i]) & 0xff] ^ (c >>> 8); return (c ^ 0xffffffff) >>> 0; };
    const zip = files => {
        const enc = new TextEncoder(), parts = [], central = []; let offset = 0;
        for (const f of files) {
            const name = enc.encode(f.name), data = enc.encode(f.text), crc = crc32(data);
            const lh = new DataView(new ArrayBuffer(30));
            [[0, 0x04034b50, 4], [4, 20, 2], [6, 0x0800, 2], [8, 0, 2], [10, 0, 2], [12, 0x21, 2], [14, crc, 4], [18, data.length, 4], [22, data.length, 4], [26, name.length, 2], [28, 0, 2]].forEach(([o, v, s]) => (s === 4 ? lh.setUint32(o, v, true) : lh.setUint16(o, v, true)));
            parts.push(new Uint8Array(lh.buffer), name, data);
            const ch = new DataView(new ArrayBuffer(46));
            [[0, 0x02014b50, 4], [4, 20, 2], [6, 20, 2], [8, 0x0800, 2], [10, 0, 2], [12, 0, 2], [14, 0x21, 2], [16, crc, 4], [20, data.length, 4], [24, data.length, 4], [28, name.length, 2], [30, 0, 2], [32, 0, 2], [34, 0, 2], [36, 0, 2], [38, 0, 4], [42, offset, 4]].forEach(([o, v, s]) => (s === 4 ? ch.setUint32(o, v, true) : ch.setUint16(o, v, true)));
            central.push(new Uint8Array(ch.buffer), name);
            offset += 30 + name.length + data.length;
        }
        const size = central.reduce((s, a) => s + a.length, 0), end = new DataView(new ArrayBuffer(22));
        end.setUint32(0, 0x06054b50, true); end.setUint16(8, files.length, true); end.setUint16(10, files.length, true); end.setUint32(12, size, true); end.setUint32(16, offset, true);
        return new Blob([...parts, ...central, new Uint8Array(end.buffer)], { type: 'application/zip' });
    };
    const exportHtml = (pg, mode) => B.render(P, pg, mode, { runtime: RUNTIME, siteBase: SITE_BASE, statsUrl: STATS_URL });
    $('[data-act=download]').addEventListener('click', async () => {
        if (!(await rsvpReady()) || !(await shopReady())) return;
        try { await syncRsvp(); } catch (ex) { toast(ex.message); } // the download goes ahead either way
        try { await syncShop(); } catch (ex) { toast(ex.message, 'error'); }
        if (P.pages.length === 1) save(new Blob([exportHtml(P.pages[0], 'download')], { type: 'text/html' }), baseName() + '.html');
        else save(zip(P.pages.map(p => ({ name: (p.slug === 'index' ? 'index' : p.slug) + '.html', text: exportHtml(p, 'download') }))), baseName() + '.zip');
        const after = document.querySelector('[data-lpb-after]');
        after.querySelector('[data-after-zip]').hidden = P.pages.length === 1;
        after.hidden = false;
        if (root.classList.contains('is-full')) toast('Downloaded'); else after.scrollIntoView({ behavior: 'smooth', block: 'center' });
        track('builder_download', { pages: P.pages.length });
    });

    /* =========================================================== account, form, publish (server) */
    let csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const freshCsrf = async () => { try { const h = await (await fetch(location.pathname, { headers: { Accept: 'text/html' } })).text(); csrf = (h.match(/name="csrf-token" content="([^"]+)"/) || [])[1] || csrf; } catch {} };
    let token = ''; try { token = localStorage.getItem('owlvix-builder-token') || ''; } catch {}
    let account = null;
    const api = async (method, url, body, retried) => {
        const res = await fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Builder-Token': token }, body: body ? JSON.stringify(body) : undefined });
        if (res.status === 419 && !retried) { await freshCsrf(); return api(method, url, body, true); } // the page sat open past the session
        const json = await res.json().catch(() => ({}));
        if (res.status === 401) { token = ''; account = null; try { localStorage.removeItem('owlvix-builder-token'); } catch {} }
        if (!res.ok || json.ok === false) { const err = new Error(json.error || json.message || 'Something went wrong. Please try again.'); err.status = res.status; throw err; }
        return json;
    };
    const dlg = document.querySelector('[data-builder-dialog]'), dlgBody = dlg.querySelector('[data-dialog-body]');
    let afterSignIn = null;
    const openDialog = html => { dlgBody.innerHTML = html; if (!dlg.open) dlg.showModal(); dlgBody.querySelector('input:not([type=hidden])')?.focus(); };
    dlg.addEventListener('click', e => {
        const r = dlg.getBoundingClientRect(), outside = e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
        if ((e.target === dlg && outside) || e.target.closest('[data-dialog-close]')) dlg.close();
    });
    const signIn = then => {
        afterSignIn = then;
        openDialog(`<h3>Sign in with your email</h3><p>We'll email you a 6-digit code — no password needed. We use your email to send form messages and manage your published pages.</p>
            <form data-signin><label>Email<input type="email" name="email" required autocomplete="email" placeholder="you@example.com"></label><p class="lpb-err" hidden></p>
            <button class="btn btn--glow btn--block" type="submit">Send my code</button></form>`);
    };
    const loadAccount = async () => { account = await api('GET', '/builder/account'); return account; };
    const needAccount = async then => { if (!token) return signIn(then); try { await loadAccount(); then(); } catch { signIn(then); } };
    dlg.addEventListener('submit', async e => {
        e.preventDefault();
        const f = e.target, err = f.querySelector('.lpb-err'), btnEl = f.querySelector('button[type=submit]');
        err.hidden = true; btnEl.disabled = true;
        try {
            if (f.matches('[data-signin]')) {
                const email = f.email.value.trim();
                await api('POST', '/builder/account/code', { email });
                openDialog(`<h3>Check your email</h3><p>We sent a 6-digit code to <b>${esc(email)}</b>. It's valid for 15 minutes.</p>
                    <form data-verify><input type="hidden" name="email" value="${esc(email)}"><label>Code<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required placeholder="123456"></label><p class="lpb-err" hidden></p>
                    <button class="btn btn--glow btn--block" type="submit">Sign in</button></form>`);
            } else if (f.matches('[data-verify]')) {
                const r = await api('POST', '/builder/account/verify', { email: f.email.value, code: f.code.value.trim() });
                token = r.token; try { localStorage.setItem('owlvix-builder-token', token); } catch {}
                await loadAccount(); redrawSiteQr(); track('builder_sign_in');
                afterSignIn?.();
            } else if (f.matches('[data-publish]')) await publish(f.slug.value, f.showcase?.checked);
        } catch (ex) { err.textContent = ex.message; err.hidden = false; }
        finally { btnEl.disabled = false; }
    });
    const formPanel = () => P.site.formAction
        ? `<div class="lpb-form-ok">${oi('check')} Contact form is active. Messages are emailed to ${esc(account?.email || 'your sign-in email')}.</div>`
        : `<div class="lpb-form-off"><b>${oi('mail')} Working contact form</b><p>Visitors' messages are emailed to you — it works wherever you put the page. Free: sign in with your email to switch it on.</p><button type="button" class="btn btn--sm btn--glow" data-activate-form>Activate contact form</button></div>`;
    const leadPanel = () => P.site.leadAction
        ? `<div class="lpb-form-ok">${oi('check')} Collecting leads. Each one is emailed to ${esc(account?.email || 'you')} and saved — see them in the <b>Results</b> tab.</div>`
        : `<div class="lpb-form-off"><b>${oi('inbox')} Collect leads</b><p>Sign-ups are emailed to you, saved in your Results tab (download as CSV) and can go to Mailchimp, Brevo or a webhook. Free: sign in with your email to switch it on.</p><button type="button" class="btn btn--sm btn--glow" data-activate-leads>Switch on lead collection</button></div>`;
    const rsvpPanel = () => P.site.rsvpAction
        ? `<div class="lpb-form-ok">${oi('check')} RSVPs are on. Each reply is emailed to ${esc(account?.email || 'you')}. See every reply and download them in the <a href="/form-builder" target="_blank" rel="noopener">form builder</a> (Responses tab).</div>`
        : `<div class="lpb-form-off"><b>${oi('mail')} Collect RSVPs</b><p>Guests' replies are emailed to you and saved in a list you can download — wherever you put the page. Free: sign in with your email to switch it on.</p><button type="button" class="btn btn--sm btn--glow" data-activate-rsvp>Switch on RSVPs</button></div>`;
    let activating = false;
    /** Create the RSVP form (a form-builder form) whose questions match the RSVP widget. */
    const activateRsvp = () => needAccount(async () => {
        if (activating) return; activating = true;
        try {
            const schema = B.rsvpSchema(P), r = await api('POST', '/builder/surveys', { schema });
            P.site.rsvpFormId = r.form.id; P.site.rsvpAction = r.form.action; rsvpSynced = r.form.id + JSON.stringify(schema); commit(); if (dlg.open) dlg.close();
            refreshMain(); drawEdit(); toast('RSVPs are on'); track('builder_rsvp_on');
        } catch (ex) { toast(ex.message); } finally { activating = false; }
    });
    /**
     * Before publishing or downloading: save the RSVP widget's current questions to its form, so the answers the
     * page sends (e.g. a new meal choice) are accepted. Returns false if the page can't be sent out yet.
     */
    let rsvpSynced = ''; // the RSVP questions last saved to the server, so unchanged ones aren't sent again
    const syncRsvp = async () => {
        const schema = B.rsvpSchema(P);
        if (!schema || !P.site.rsvpFormId) return;
        const key = P.site.rsvpFormId + JSON.stringify(schema);
        if (key === rsvpSynced) return;
        if (!token) throw new Error('Sign in again to keep your RSVP form up to date.');
        try { await api('POST', '/builder/surveys', { id: P.site.rsvpFormId, schema }); rsvpSynced = key; }
        catch (ex) {
            if (ex.status === 404) { // deleted in the form builder (another account's form answers 403 and stays connected)
                P.site.rsvpAction = ''; P.site.rsvpFormId = ''; commit(); refreshMain(); drawEdit();
                throw new Error('The form that collected your RSVPs was deleted. Select the RSVP form and switch it on again.');
            }
            throw ex;
        }
    };
    /** An RSVP widget that was never switched on would go out empty: offer to switch it on first. True = go ahead. */
    const rsvpReady = async () => {
        if (!B.rsvpWidget(P) || P.site.rsvpAction) return true;
        const on = await sweet({ icon: 'warning', title: 'Your RSVP form is off', text: 'Guests won’t be able to reply until you switch it on (free — sign in with your email).', confirm: 'Switch on RSVPs', cancel: 'Continue without' });
        if (on) { activateRsvp(); return false; }
        return true;
    };
    const activateLeads = () => needAccount(async () => {
        if (activating) return; activating = true;
        try {
            P.site.ref ||= uid() + uid();
            const r = await api('POST', '/builder/forms', { site_name: P.site.name || 'My funnel', kind: 'leads', ref: P.site.ref });
            P.site.leadAction = r.form.action; P.site.leadFormId = r.form.id; commit(); if (dlg.open) dlg.close();
            refreshMain(); drawEdit(); toast('Lead collection is on'); track('funnel_leads_on');
        } catch (ex) { toast(ex.message); } finally { activating = false; }
    });
    const activateForm = () => needAccount(async () => {
        if (activating) return; activating = true;
        try {
            P.site.ref ||= uid() + uid();
            const r = await api('POST', '/builder/forms', { site_name: P.site.name || 'My website', ref: P.site.ref });
            P.site.formAction = r.form.action; commit(); if (dlg.open) dlg.close();
            refreshMain(); drawEdit(); toast('Contact form activated'); track('builder_form_activated');
        } catch (ex) { toast(ex.message); } finally { activating = false; }
    });
    const publishDialog = () => {
        const mine = account?.sites || [], slug = P.site.publishSlug || slugify(P.site.name) || 'my-site';
        openDialog(`<h3>Publish your site</h3><p>Your ${P.pages.length > 1 ? P.pages.length + ' pages go' : 'page goes'} live at a free web address you can share. Signed in as <b>${esc(account?.email || '')}</b>.</p>
            <form data-publish><label>Web address<span class="lpb-slug lpb-slug--url"><i>${esc(SITE_HOST)}</i><input name="slug" value="${esc(slug)}" required minlength="3" maxlength="40"></span></label>
            <p class="lpb-hint">Lowercase letters, numbers and dashes. Published pages are hidden from Google search — for your own domain and Google ranking, download the page or let us set it up.</p>
            ${(() => { const was = mine.find(s => s.slug === slug)?.showcase; return was === 'no' ? '' : `<label class="lpb-check lpb-showcase"><input type="checkbox" name="showcase" ${was === 'asked' || was === 'yes' ? 'checked' : ''}><span>${was === 'yes' ? 'Featured in the Owlvix gallery — untick to remove it' : 'Feature my site in the Owlvix gallery'}<small>We look at every site first. A free link to your site from owlvix.com.</small></span></label>`; })()}
            <p class="lpb-err" hidden></p>
            <button class="btn btn--glow btn--block" type="submit">${mine.some(s => s.slug === slug) ? 'Update my published site' : 'Publish now'}</button></form>
            ${mine.length ? `<h4>Your published sites</h4><ul class="lpb-sites">${mine.map(s => `<li><a href="${esc(s.url)}" target="_blank" rel="noopener">${esc(s.url.replace(/^https?:\/\//, ''))}</a> <small>${s.status === 'live' ? 'Live' : 'Taken down'}</small>${s.status === 'live' ? ` <button type="button" class="lpb-link-btn" data-unpublish="${esc(s.slug)}">Unpublish</button>` : ''}</li>`).join('')}</ul>` : ''}`);
        dlgBody.querySelector('[name=slug]').addEventListener('input', e => (e.target.value = e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '')));
    };
    const publish = async (slugIn, showcase) => {
        const slug = slugify(slugIn);
        if (slug.length < 3) throw new Error('Please use at least 3 letters or numbers.');
        await syncRsvp(); // errors show in the publish dialog
        await syncShop(); // the prices checkout charges
        const previous = P.site.publishSlug;
        P.site.publishSlug = slug;
        const pages = P.pages.map(p => ({ slug: p.slug, title: p.title, html: exportHtml(p, 'publish') }));
        let r;
        try { r = await api('POST', '/builder/publish', { slug, name: P.site.name || 'My website', pages, kind: KIND, ...(showcase === undefined ? {} : { showcase: !!showcase }) }); }
        catch (ex) { P.site.publishSlug = previous; throw ex; }
        commit(); await loadAccount().catch(() => {});
        openDialog(`<h3>${oi('party')} Your site is live</h3><p><a class="lpb-live" href="${esc(r.url)}" target="_blank" rel="noopener">${esc(r.url.replace(/^https?:\/\//, ''))}</a></p>
            <p>Share the link anywhere. Changed something? Publish again to update it.</p><p>Want it on your own domain, found on Google, with analytics and speed tuning? We can set that up.</p>
            <div class="lpb-dialog-actions"><a class="btn btn--glow" href="${esc(r.url)}" target="_blank" rel="noopener">Open my site</a><button type="button" class="btn btn--ghost" data-lpb-quote-open>Put it on my domain</button></div>
            <div data-qr-box></div>`);
        drawQr(dlgBody.querySelector('[data-qr-box]')); redrawSiteQr();
        track('builder_publish', { pages: pages.length });
    };
    $('[data-act=publish]').addEventListener('click', async () => { if (await rsvpReady() && await shopReady()) needAccount(publishDialog); });
    dlgBody.addEventListener('click', async e => {
        if (e.target.closest('[data-qr-publish]')) { dlg.close(); return $('[data-act=publish]').click(); }
        const un = e.target.closest('[data-unpublish]');
        if (un && await sweet({ icon: 'warning', title: 'Take this site offline?', text: `${SITE_HOST}${un.dataset.unpublish} will stop working. You can publish it again later.`, confirm: 'Yes, unpublish', danger: true })) {
            try { await api('POST', '/builder/unpublish', { slug: un.dataset.unpublish }); await loadAccount(); publishDialog(); redrawSiteQr(); toast('Site unpublished'); } catch (ex) { toast(ex.message); }
        }
        if (e.target.closest('[data-lpb-quote-open]')) { dlg.close(); setFull(false); document.querySelector('.speed-cta [data-lpb-quote]')?.click(); }
    });
    document.querySelectorAll('[data-lpb-quote]').forEach(btnEl => btnEl.addEventListener('click', () => {
        const modal = document.getElementById('quote-modal');
        const service = modal?.querySelector('select[name=service]'); if (service) service.value = 'Landing page';
        const ta = modal?.querySelector('textarea[name=message]');
        const text = FUNNEL ? `I built a ${P.pages.length}-step funnel for ${P.site.name || 'my business'} with your free funnel builder${P.site.publishSlug ? ` (${SITE_BASE}${P.site.publishSlug})` : ''}. I'd like help to set it up and get more conversions.` : `I built a ${P.pages.length > 1 ? P.pages.length + '-page website' : 'landing page'} for ${P.site.name || 'my business'} with your free builder${P.site.publishSlug ? ` (${SITE_BASE}${P.site.publishSlug})` : ''}. I'd like it live on my own domain.`;
        if (ta?.quill) ta.quill.setText(text); else if (ta) ta.value = text;
        track('builder_quote');
    }));

    /* =========================================================== results (funnels): stats, leads, email tools */
    const resultsBox = () => $('[data-results-panel]');
    const drawResults = async () => {
        const box = resultsBox(); if (!box) return;
        if (!token) { box.innerHTML = '<div class="lpb-empty-edit"><b>See your funnel results</b><p>Sign in to see visits and sign-ups per step, your leads, and connect Mailchimp, Brevo or a webhook.</p><button type="button" class="btn btn--sm btn--glow" data-results-signin>Sign in with email</button></div>'; return; }
        box.innerHTML = '<p class="lpb-hint">Loading…</p>';
        try {
            await loadAccount();
            const slug = P.site.publishSlug, rawId = P.site.leadFormId || account.forms?.find(f => f.action === P.site.leadAction)?.id;
            const formId = /^[a-z0-9]{16}$/.test(rawId || '') ? rawId : '';
            const [stats, leads, integ] = await Promise.all([
                slug ? api('GET', '/builder/stats?slug=' + encodeURIComponent(slug)).catch(() => null) : null,
                formId ? api('GET', '/builder/leads?form=' + formId).catch(() => null) : null,
                formId ? api('GET', `/builder/forms/${formId}/integrations`).catch(() => null) : null,
            ]);
            const rows = P.pages.map(p => {
                const s = (stats?.steps || []).filter(x2 => x2.step === p.slug), sum = k => s.reduce((n, x2) => n + (+x2[k] || 0), 0);
                return { p, views: sum('views'), leads: sum('leads'), byV: ['a', 'b'].map(v => ({ v, views: s.filter(x2 => x2.variant === v).reduce((n, x2) => n + (+x2.views || 0), 0), leads: s.filter(x2 => x2.variant === v).reduce((n, x2) => n + (+x2.leads || 0), 0) })) };
            });
            const top = Math.max(1, ...rows.map(r => r.views));
            const pct = (a, b) => (b ? Math.round((a / b) * 1000) / 10 + '%' : '–');
            const plural = (n, w) => `${n.toLocaleString()} ${w}${n === 1 ? '' : 's'}`;
            box.innerHTML = `
                <details class="lpb-acc" open><summary>Funnel — last 30 days</summary>
                ${slug ? `<div class="lpb-fun">${rows.map((r, i) => `<div class="lpb-fun__row"><div class="lpb-fun__top"><b>${i + 1}. ${esc(r.p.title)}</b><span>${plural(r.views, 'visit')}${r.leads ? ` · ${plural(r.leads, 'sign-up')} (${pct(r.leads, r.views)})` : ''}</span></div>
                    <div class="lpb-fun__bar"><i style="width:${(r.views / top) * 100}%"></i></div>${i > 0 && rows[i - 1].views ? `<small>${pct(r.views, rows[i - 1].views)} of the previous step reached this one</small>` : ''}
                    ${r.p.ab?.on ? `<div class="lpb-ab">${r.byV.map(b => `<span><b>${b.v.toUpperCase()}</b> ${plural(b.views, 'visit')} · ${plural(b.leads, 'sign-up')} · ${pct(b.leads, b.views)}</span>`).join('')}</div>` : ''}</div>`).join('')}</div>
                <p class="lpb-hint">Counts come from your published funnel at ${esc(SITE_HOST + slug)}.</p>` : '<p class="lpb-hint">Publish your funnel to start counting visits and sign-ups.</p>'}</details>
                <details class="lpb-acc" open><summary>Leads${leads ? ` (${esc(+leads.total || 0)})` : ''}</summary>
                ${!formId ? '<p class="lpb-hint">Add an Opt-in form widget and switch on lead collection to start collecting leads.</p>'
                    : leads?.leads?.length ? `<div class="lpb-leads"><table><thead><tr><th>Date</th><th>Name</th><th>Email</th><th>Step</th></tr></thead><tbody>${leads.leads.slice(0, 50).map(l => `<tr><td>${esc(new Date(l.created_at).toLocaleDateString())}</td><td>${esc(l.name || '')}</td><td>${esc(l.email)}</td><td>${esc(l.step || '')}${l.variant === 'b' ? ' (B)' : ''}</td></tr>`).join('')}</tbody></table></div>
                        <button type="button" class="btn btn--sm btn--glow" data-leads-csv="${formId}">Download all as CSV</button>` : '<p class="lpb-hint">No leads yet. They appear here as soon as someone signs up.</p>'}</details>
                ${formId ? `<details class="lpb-acc"><summary>Send leads to your email tool</summary><form class="lpb-integ" data-integ="${formId}">
                    <h4>Mailchimp</h4><label>API key<input name="mc_key" placeholder="${esc(integ?.integrations?.mailchimp?.key_hint || 'xxxxxxxx…-us21')}" autocomplete="off"></label><label>Audience ID<input name="mc_list" value="${esc(integ?.integrations?.mailchimp?.list || '')}"></label>
                    <h4>Brevo</h4><label>API key<input name="br_key" placeholder="${esc(integ?.integrations?.brevo?.key_hint || 'xkeysib-…')}" autocomplete="off"></label><label>List ID<input name="br_list" value="${esc(integ?.integrations?.brevo?.list || '')}"></label>
                    <h4>Webhook (Zapier, Make, your CRM)</h4><label>URL<input name="wh_url" value="${esc(integ?.integrations?.webhook?.url || '')}" placeholder="https://hooks.zapier.com/…"></label>
                    <p class="lpb-hint">Leave a list or URL empty to switch that one off. Keys are stored encrypted and never shown again.</p><p class="lpb-err" hidden></p>
                    <button type="submit" class="btn btn--sm btn--glow">Save connections</button></form></details>` : ''}`;
        } catch (ex) { box.innerHTML = `<p class="lpb-err">${esc(ex.message)}</p>`; }
    };
    if (FUNNEL) {
        const rp = $('[data-tab-panel=results]');
        rp.addEventListener('click', async e => {
            if (e.target.closest('[data-results-signin]')) return needAccount(() => { dlg.close(); drawResults(); });
            const csv = e.target.closest('[data-leads-csv]');
            if (csv) {
                try {
                    const res = await fetch(`/builder/leads?form=${csv.dataset.leadsCsv}&format=csv`, { headers: { 'X-Builder-Token': token, Accept: 'text/csv' } });
                    if (!res.ok) throw new Error('Could not download the leads.');
                    save(await res.blob(), `leads-${baseName()}-${new Date().toISOString().slice(0, 10)}.csv`); track('funnel_leads_csv');
                } catch (ex) { toast(ex.message); }
            }
        });
        rp.addEventListener('submit', async e => {
            const f = e.target.closest('[data-integ]'); if (!f) return;
            e.preventDefault();
            const err = f.querySelector('.lpb-err'); err.hidden = true;
            const v = n => f[n].value.trim();
            try {
                await api('POST', `/builder/forms/${f.dataset.integ}/integrations`, {
                    mailchimp: v('mc_list') ? { key: v('mc_key'), list: v('mc_list') } : null,
                    brevo: v('br_list') ? { key: v('br_key'), list: v('br_list') } : null,
                    webhook: v('wh_url') ? { url: v('wh_url') } : null,
                });
                toast('Connections saved'); track('funnel_integrations'); drawResults();
            } catch (ex) { err.textContent = ex.message; err.hidden = false; }
        });
    }


    /* =========================================================== online store (/online-store-builder): checkout, orders, Stripe */
    // The shop's products and settings are copied to owlvix (StoreController) when checkout is switched on and before
    // every publish and download, so checkout charges the prices the owner set. Orders show in the Orders tab.
    let storeInfo = null, storeLoading = null;
    const CUR_NAMES = (() => { try { return new Intl.DisplayNames(['en'], { type: 'currency' }); } catch { return null; } })();
    const currencyOptions = cur => B.CURRENCIES.map(c => `<option value="${c}" ${c === cur ? 'selected' : ''}>${c.toUpperCase()}${CUR_NAMES ? ' — ' + esc(CUR_NAMES.of(c.toUpperCase()) || '') : ''}</option>`).join('');
    const shopPayload = id => ({ id, ref: P.site.ref, name: P.site.name || 'My shop', currency: P.site.shop.currency, products: B.shopProducts(P), settings: P.site.shop });
    const shopGone = () => { P.site.shopAction = ''; P.site.shopId = ''; storeInfo = null; commit(); refreshMain(); drawEdit(); };
    let storeError = '';
    /** The shop this project uses (forgotten when another project — another shop — is open). */
    const store = () => (storeInfo && storeInfo.id === P.site.shopId ? storeInfo : null);
    const loadStore = async () => {
        if (!token || !P.site.shopId) return null;
        storeError = '';
        return (storeLoading ||= api('GET', '/builder/stores/' + P.site.shopId).then(r => (storeInfo = r.store))
            .catch(ex => { if (ex.status === 404) shopGone(); else storeError = ex.message; return null; }).finally(() => (storeLoading = null)));
    };
    const storeRetry = () => `<p class="lpb-err">${esc(storeError)}</p><button type="button" class="btn btn--sm btn--ghost" data-store-retry>Try again</button>`;
    const activateShop = () => needAccount(async () => {
        if (activating) return; activating = true;
        try {
            P.site.ref ||= uid() + uid();
            const r = await api('POST', '/builder/stores', shopPayload(null));
            storeInfo = r.store; P.site.shopId = r.store.id; P.site.shopAction = r.store.action; P.site.shop.cardReady = !!r.store.card_ready;
            commit(); if (dlg.open) dlg.close();
            refreshMain(); refreshChrome(); drawEdit(); drawSite(); toast('Checkout is on — publish your shop to start selling'); track('store_checkout_on');
        } catch (ex) { toast(ex.message, 'error'); } finally { activating = false; }
    });
    /** Before publishing or downloading: save the products and settings checkout uses. Throws if the shop can't sell. */
    const syncShop = async () => {
        if (KIT !== 'store' || !P.site.shopId) return;
        if (!token) throw new Error('Sign in again to keep your shop’s prices up to date.');
        let r;
        try { r = await api('POST', '/builder/stores', shopPayload(P.site.shopId)); }
        catch (ex) {
            if (ex.status === 404 || ex.status === 403) { shopGone(); throw new Error('Your shop’s checkout was removed. Switch checkout on again in the Site tab.'); }
            throw ex;
        }
        storeInfo = r.store; P.site.shopAction = r.store.action; P.site.shop.cardReady = !!r.store.card_ready; commit();
        // a product's sizes / colours changed shape: its new choices start sold out until the owner sets their counts
        if (r.stock_reset?.length) sweet({ icon: 'warning', title: 'Set the stock again', text: `The sizes or colours of ${r.stock_reset.map(n => '“' + n + '”').join(', ')} changed, so their stock counts start at 0 (sold out). Select the product and set how many you have under “Count stock”.`, confirm: 'OK', cancel: false });
        if (!B.payWays(P.site.shop).length) throw new Error('Card payments aren’t ready yet: connect Stripe in the Site tab (Shop & checkout), or also switch on cash on delivery or bank transfer.');
        if (!B.shopProducts(P).length) throw new Error('Your shop has no products with a price yet. Add a price to at least one product.');
    };
    /** A store whose checkout was never switched on would go out without a cart: offer to switch it on. True = go ahead. */
    const shopReady = async () => {
        if (KIT !== 'store' || P.site.shopAction || !B.productWidgets(P).length) return true;
        const on = await sweet({ icon: 'warning', title: 'Checkout is off', text: 'Customers won’t be able to add to cart or order until you switch checkout on (free — sign in with your email).', confirm: 'Switch on checkout', cancel: 'Continue without' });
        if (on) { activateShop(); return false; }
        return true;
    };
    const shopPanel = () => P.site.shopAction
        ? `<div class="lpb-form-ok">${oi('check')} Checkout is on. Orders are emailed to ${esc(account?.email || 'you')} and listed in the <b>Orders</b> tab. Delivery and payment settings are in the <b>Site</b> tab.</div>`
        : `<div class="lpb-form-off"><b>${oi('cart')} Switch on checkout</b><p>Customers add products to a cart and order — by card, cash on delivery or bank transfer. Free: sign in with your email to switch it on.</p><button type="button" class="btn btn--sm btn--glow" data-activate-shop>Switch on checkout</button></div>`;
    const stripeBox = () => {
        const sh = P.site.shop;
        if (!sh.card) return '';
        if (!P.site.shopId) return '<p class="lpb-hint">Switch on checkout first, then connect Stripe here.</p>';
        if (!token) return '<p class="lpb-hint">Sign in to connect Stripe.</p><button type="button" class="btn btn--sm btn--ghost" data-shop-signin>Sign in</button>';
        if (!store()) { if (storeError) return storeRetry(); if (!storeLoading) loadStore().then(r => (r || storeError) && drawSite()); return '<p class="lpb-hint">Checking your Stripe connection…</p>'; }
        const st = store().stripe, modes = ['test', 'live'].filter(m => st[m].available);
        if (!modes.length) return '<p class="lpb-hint">Card payments aren’t available yet — use cash on delivery or bank transfer for now.</p>';
        const m = modes.includes(sh.mode) ? sh.mode : modes[0], c = st[m].connected;
        return `${modes.length > 1 ? `<label>Card payments<select data-site="shop.mode"><option value="test" ${m === 'test' ? 'selected' : ''}>Test mode — test cards, no real money</option><option value="live" ${m === 'live' ? 'selected' : ''}>Live — real payments</option></select></label>` : ''}
            ${c ? `<div class="lpb-form-ok">${oi('check')} Stripe is connected${m === 'test' ? ' (test mode)' : ''}. Card payments go straight to your Stripe account.${sh.cardReady ? '' : ' <b>Publish again</b> so customers can pay by card.'}</div><button type="button" class="lpb-link-btn" data-stripe-off="${m}">Disconnect Stripe</button>`
                : `<button type="button" class="btn btn--sm btn--glow" data-stripe-on="${m}">Connect Stripe${m === 'test' ? ' (test mode)' : ''}</button><p class="lpb-hint">You sign in to Stripe (or open a free account), then come back here. Owlvix never sees your Stripe password. Stripe’s normal card fees apply.</p>`}`;
    };
    const shopSettings = () => {
        const sh = P.site.shop;
        return `<details class="lpb-acc" open><summary>Shop &amp; checkout</summary>
            ${P.site.shopAction ? `<div class="lpb-form-ok">${oi('check')} Checkout is on. Orders are emailed to ${esc(account?.email || 'you')} and listed in the <b>Orders</b> tab.</div>` : `<div class="lpb-form-off"><b>${oi('cart')} Checkout is off</b><p>Switch it on so customers can add to cart and order. Free — sign in with your email.</p><button type="button" class="btn btn--sm btn--glow" data-activate-shop>Switch on checkout</button></div>`}
            <label>Currency<select data-site="shop.currency">${currencyOptions(sh.currency)}</select></label>
            <h4>Delivery</h4>
            <label class="lpb-check"><input type="checkbox" data-site="shop.delivery" ${sh.delivery ? 'checked' : ''}> I deliver orders</label>
            ${!sh.delivery ? '' : (sh.zones || []).length ? zonesHtml(sh) : `<div class="lpb-2"><label>Delivery fee<input data-site="shop.fee" inputmode="decimal" maxlength="12" value="${esc(sh.fee)}" placeholder="0 = free"></label><label>Free delivery over<input data-site="shop.freeOver" inputmode="decimal" maxlength="12" value="${esc(sh.freeOver)}" placeholder="Never"></label></div>
                <button type="button" class="lpb-link-btn" data-zones-on>${oi('globe')} Charge different prices by country</button>`}
            <label class="lpb-check"><input type="checkbox" data-site="shop.pickup" ${sh.pickup ? 'checked' : ''}> Customers can pick up</label>
            ${sh.pickup ? `<label>Where and when<input data-site="shop.pickupText" maxlength="200" value="${esc(sh.pickupText)}" placeholder="12 Market Street, 9 am – 5 pm"></label>` : ''}
            <h4>Payment</h4>
            <label class="lpb-check"><input type="checkbox" data-site="shop.cod" ${sh.cod ? 'checked' : ''}> Cash on delivery${sh.pickup ? ' / when they collect' : ''}</label>
            <label class="lpb-check"><input type="checkbox" data-site="shop.bank" ${sh.bank ? 'checked' : ''}> Bank transfer</label>
            ${sh.bank ? `<label>Your bank details<textarea data-site="shop.bankText" rows="4" maxlength="1000" placeholder="Bank name, account name, account number…">${esc(sh.bankText)}</textarea></label><p class="lpb-hint">Shown to the customer after they order, and in their receipt email. Mark bank orders as paid in the Orders tab.</p>` : ''}
            <label class="lpb-check"><input type="checkbox" data-site="shop.card" ${sh.card ? 'checked' : ''}> Card payments (Stripe)</label>
            ${stripeBox()}
            <h4>Tax</h4>
            <div class="lpb-2"><label>Tax rate (%)<input data-site="shop.taxRate" inputmode="decimal" maxlength="6" value="${esc(sh.taxRate)}" placeholder="0 = no tax"></label><label>Called<input data-site="shop.taxLabel" maxlength="20" value="${esc(sh.taxLabel || 'Tax')}" placeholder="Tax, VAT, GST…"></label></div>
            ${+sh.taxRate > 0 ? `<label class="lpb-check"><input type="checkbox" data-site="shop.taxIncl" ${sh.taxIncl !== false ? 'checked' : ''}> My prices already include tax</label><label class="lpb-check"><input type="checkbox" data-site="shop.taxShip" ${sh.taxShip ? 'checked' : ''}> Charge tax on delivery too</label>
                <p class="lpb-hint">${sh.taxIncl !== false ? 'Checkout shows how much tax the total includes.' : 'Tax is added to the total at checkout.'}</p>` : ''}
            <h4>Discount codes</h4>
            <label class="lpb-check"><input type="checkbox" data-site="shop.codes" ${sh.codes ? 'checked' : ''}> Customers can enter a discount code</label>
            ${sh.codes ? '<p class="lpb-hint">Make your codes (like SAVE10) in the <b>Orders</b> tab, under Discount codes. They’re kept on our side, never in your pages.</p>' : ''}
            <h4>Unfinished orders</h4>
            <label class="lpb-check"><input type="checkbox" data-site="shop.remind" ${sh.remind !== false ? 'checked' : ''}> Remind people who start paying by card but don’t finish (one email)</label>
            <h4>Order form</h4>
            <label class="lpb-check"><input type="checkbox" data-site="shop.phoneReq" ${sh.phoneReq ? 'checked' : ''}> Phone number is required</label>
            <label>Your WhatsApp number (optional)<input data-site="shop.whatsapp" type="tel" maxlength="30" value="${esc(sh.whatsapp)}" placeholder="+1 555 123 4567"></label>
            <p class="lpb-hint">Customers can message you from their order page.</p>
            <label>Thank-you message<input data-site="shop.thanks" maxlength="300" value="${esc(sh.thanks)}" placeholder="Thank you! We’ll be in touch when your order is on its way."></label>
            <p class="lpb-hint">Changes reach your live shop when you publish again.</p></details>`;
    };
    /* shipping zones: a price per group of countries; a zone with no countries covers everywhere else */
    const zonesHtml = sh => `<div class="lpb-zones">${sh.zones.map((z, zi) => `<div class="lpb-zone">
            <div class="lpb-zone__top"><input data-site="shop.zones.${zi}.name" value="${esc(z.name || '')}" maxlength="60" placeholder="Zone name, e.g. Europe" aria-label="Zone name"><button type="button" data-zone-del="${zi}" aria-label="Remove this zone">${oi('close')}</button></div>
            <div class="lpb-zone__cs">${(z.countries || []).length ? z.countries.map(c => `<span class="lpb-cchip">${esc(B.countryName(c))}<button type="button" data-zone-c="${zi}|${c}" aria-label="Remove ${esc(B.countryName(c))}">${oi('close')}</button></span>`).join('') : `<span class="lpb-hint">${oi('globe')} Everywhere else — every country not in another zone</span>`}</div>
            <select data-zone-add="${zi}" aria-label="Add a country to this zone"><option value="">Add a country…</option>${B.COUNTRIES.map(c => [c, B.countryName(c)]).sort((a, b) => a[1].localeCompare(b[1])).filter(([c]) => !(z.countries || []).includes(c)).map(([c, l]) => `<option value="${c}">${esc(l)}</option>`).join('')}</select>
            <div class="lpb-2"><label>Delivery fee<input data-site="shop.zones.${zi}.fee" inputmode="decimal" maxlength="12" value="${esc(z.fee || '')}" placeholder="0 = free"></label><label>Free over<input data-site="shop.zones.${zi}.freeOver" inputmode="decimal" maxlength="12" value="${esc(z.freeOver || '')}" placeholder="Never"></label></div></div>`).join('')}
        ${sh.zones.length < 20 ? `<button type="button" class="btn btn--sm btn--ghost" data-zone-new>${oi('plus')} Add a zone</button>` : ''}
        <p class="lpb-hint">Customers pick their country at checkout.${sh.zones.some(z => !(z.countries || []).length) ? '' : ' Countries in no zone can’t order delivery — add a zone with no countries to cover everywhere else.'}</p>
        <button type="button" class="lpb-link-btn" data-zones-off>Use one delivery price everywhere</button></div>`;
    const stripeConnect = async mode => {
        try {
            await syncShop().catch(() => {}); // the shop exists and knows card payments are wanted
            const r = await api('POST', `/builder/stores/${P.site.shopId}/stripe/connect`, { mode });
            flush(); persist(); track('store_stripe_connect', { mode });
            location.href = r.url;
        } catch (ex) { toast(ex.message, 'error'); }
    };
    const stripeDisconnect = async mode => {
        if (!(await sweet({ icon: 'warning', title: 'Disconnect Stripe?', text: 'Customers won’t be able to pay by card until you connect it again (after you publish).', confirm: 'Disconnect', danger: true }))) return;
        try { storeInfo = (await api('POST', `/builder/stores/${P.site.shopId}/stripe/disconnect`, { mode })).store; P.site.shop.cardReady = false; commit(); drawSite(); toast('Stripe disconnected — publish again to update your shop'); }
        catch (ex) { toast(ex.message, 'error'); }
    };
    // Back from Stripe's connect page (/online-store-builder?stripe=connected-test…)
    if (KIT === 'store') {
        const back = new URLSearchParams(location.search).get('stripe');
        if (back) {
            try { history.replaceState(null, '', location.pathname); } catch {}
            const msg = { 'connected-test': ['Stripe is connected (test mode). Publish again so customers can pay by card.', 'success'], 'connected-live': ['Stripe is connected. Publish again so customers can pay by card.', 'success'],
                cancelled: ['Stripe wasn’t connected.', 'info'], failed: ['Stripe couldn’t be connected. Please try again.', 'error'], expired: ['That took too long — please press Connect Stripe again.', 'error'] }[back];
            if (msg) setTimeout(() => toast(...msg), 400);
            loadStore().then(() => { showTab('site'); drawSite(); });
        }
    }

    /* ----- the Orders tab: orders, and the shop's discount codes */
    let orderFilter = '', ordersView = 'orders', codesCache = [], codeEditing = null;
    const KIND_LABEL = { percent: '% off', amount: 'Amount off', shipping: 'Free delivery' };
    /* ----- Sales: takings, orders and average order, takings per day, best sellers, low stock */
    let salesDays = 30;
    const drawSales = async (box, seg) => {
        box.innerHTML = seg + '<p class="lpb-hint">Loading sales…</p>';
        let r;
        try { r = await api('GET', `/builder/stores/${P.site.shopId}/sales?days=${salesDays}`); }
        catch (ex) { box.innerHTML = seg + `<p class="lpb-err">${esc(ex.message)}</p>`; return; }
        const max = Math.max(0, ...r.per_day.map(d => d.value));
        const fmtDay = day => new Date(day + 'T12:00:00').toLocaleDateString([], { day: 'numeric', month: 'short' });
        const change = r.change === null ? '' : `<small class="${r.change >= 0 ? 'is-up' : 'is-down'}">${oi(r.change >= 0 ? 'arrow-up' : 'arrow-down')} ${Math.abs(r.change)}% vs the ${salesDays} days before</small>`;
        // one bar a day, anchored to the baseline; hover or focus shows the day and its takings
        const bars = r.per_day.map(d => `<span class="lpb-bar" tabindex="0" style="--h:${max ? Math.max(d.value ? 4 : 0, Math.round(d.value / max * 100)) : 0}%" data-tip="${esc(fmtDay(d.day))} · ${esc(d.label)}" aria-label="${esc(fmtDay(d.day))}: ${esc(d.label)}"></span>`).join('');
        box.innerHTML = seg + `<div class="lpb-sales">
            <div class="lpb-chips" role="group" aria-label="Period">${[7, 30, 90].map(n => `<button type="button" class="lpb-chip${n === salesDays ? ' is-on' : ''}" data-sales-days="${n}" aria-pressed="${n === salesDays}">${n} days</button>`).join('')}</div>
            <div class="lpb-kpis"><div class="lpb-kpi lpb-kpi--wide"><span>Takings</span><b>${esc(r.takings)}</b>${change}</div><div class="lpb-kpi"><span>Orders</span><b>${r.orders}</b></div><div class="lpb-kpi"><span>Average order</span><b>${esc(r.average)}</b></div></div>
            <figure class="lpb-chart"><figcaption>Takings per day</figcaption>
                ${max ? `<div class="lpb-chart__plot" style="--gap:${salesDays > 30 ? 1 : 2}px"><span class="lpb-chart__max">${esc(r.per_day.reduce((a, d) => (d.value > a.value ? d : a)).label)}</span>${bars}</div>
                    <div class="lpb-chart__x"><span>${esc(fmtDay(r.per_day[0].day))}</span><span>Today</span></div>
                    <details class="lpb-chart__table"><summary>Show as a table</summary><table><thead><tr><th>Day</th><th>Takings</th></tr></thead><tbody>${r.per_day.filter(d => d.value).map(d => `<tr><td>${esc(fmtDay(d.day))}</td><td>${esc(d.label)}</td></tr>`).join('')}</tbody></table></details>`
                    : '<p class="lpb-hint">No sales in these days yet. Orders that are paid, sent or completed count here (not cancelled ones).</p>'}</figure>
            <h4>Best sellers</h4>${r.best.length ? `<ol class="lpb-best">${r.best.map(b => `<li><span>${esc(b.name)}</span><b>${b.qty}×</b><small>${esc(b.sales)}</small></li>`).join('')}</ol>` : '<p class="lpb-hint">Nothing sold yet in these days.</p>'}
            <h4>Running low</h4>${r.low.length ? `<ul class="lpb-low">${r.low.map(l => `<li><span>${esc(l.name)}</span><b class="${l.left ? '' : 'is-out'}">${l.left ? l.left + ' left' : 'Sold out'}</b></li>`).join('')}</ul>` : '<p class="lpb-hint">Nothing is running low (products you count stock for show here at 5 or fewer).</p>'}
            <p class="lpb-hint">Takings are orders that aren’t cancelled or waiting for card payment, less refunds.</p></div>`;
    };
    const drawCodes = async (box, seg) => {
        box.innerHTML = seg + '<p class="lpb-hint">Loading codes…</p>';
        try { codesCache = (await api('GET', `/builder/stores/${P.site.shopId}/discounts`)).discounts; }
        catch (ex) { box.innerHTML = seg + `<p class="lpb-err">${esc(ex.message)}</p>`; return; }
        const c = codeEditing, cur = (P.site.shop.currency || 'usd').toUpperCase(), kind = c?.kind || 'percent';
        const form = c ? `<form class="lpb-code-form" data-code-form="${esc(c.id || '')}"><h4>${c.id ? 'Change code' : 'New discount code'}</h4>
            <label>Code<input name="code" required maxlength="40" value="${esc(c.code || '')}" placeholder="SAVE10" autocapitalize="characters"></label>
            <label>Discount<select name="kind" data-code-kind>${Object.entries(KIND_LABEL).map(([k, l]) => `<option value="${k}" ${kind === k ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
            <label data-code-value ${kind === 'shipping' ? 'hidden' : ''}>How much (<span data-code-unit>${kind === 'percent' ? '%' : cur}</span>)<input name="value" inputmode="decimal" maxlength="12" value="${esc(c.value || '')}" placeholder="10"></label>
            <div class="lpb-2"><label>Minimum order<input name="min" inputmode="decimal" maxlength="12" value="${esc(c.min || '')}" placeholder="None"></label><label>Uses allowed<input name="max_uses" type="number" min="1" value="${esc(c.max_uses ?? '')}" placeholder="No limit"></label></div>
            <label>Ends on<input name="ends" type="date" value="${esc(c.ends || '')}"></label>
            <label class="lpb-check"><input type="checkbox" name="active" ${c.active !== false ? 'checked' : ''}> Active</label>
            <p class="lpb-err" hidden></p><div class="lpb-block-actions"><button type="submit" class="btn btn--sm btn--glow">Save code</button><button type="button" class="btn btn--sm btn--ghost" data-code-cancel>Cancel</button></div></form>` : '';
        const rows = codesCache.map(d2 => `<div class="lpb-code${d2.active ? '' : ' is-off'}"><div><b>${esc(d2.code)}</b> <span>${esc(d2.label)}</span><small>${[d2.min && 'min ' + B.money(d2.min, P.site.shop.currency), d2.ends && 'until ' + d2.ends, `used ${d2.uses}${d2.max_uses ? '/' + d2.max_uses : ''}×`, !d2.active && 'off'].filter(Boolean).map(esc).join(' · ')}</small></div>
            <span><button type="button" class="lpb-link-btn" data-code-edit="${d2.id}">Edit</button> <button type="button" class="lpb-link-btn lpb-danger" data-code-del="${d2.id}">Delete</button></span></div>`).join('');
        box.innerHTML = seg + (P.site.shop.codes ? '' : '<p class="lpb-hint">The code box is off at checkout — it switches on when you save a code (then publish again).</p>')
            + (rows || (c ? '' : '<div class="lpb-empty-edit"><b>No codes yet</b><p>Make a code like SAVE10 for 10% off, $5 off, or free delivery — with a minimum order, end date or number of uses if you like.</p></div>'))
            + (form || `<button type="button" class="btn btn--sm btn--glow" data-code-new>${oi('plus')} New discount code</button>`);
        // gift cards sold (made when a gift card product is paid for)
        try {
            const cards = (await api('GET', `/builder/stores/${P.site.shopId}/gift-cards`)).cards;
            box.insertAdjacentHTML('beforeend', `<h4>Gift cards sold</h4>${cards.length ? cards.map(g => `<div class="lpb-code"><div><b>${esc(g.code)}</b> <span>${esc(g.balance)} left</span><small>${esc([`worth ${g.initial}`, g.email].filter(Boolean).join(' · '))}</small></div></div>`).join('')
                : '<p class="lpb-hint">None yet. Add a product and set its Type to “Gift card” — each one sold gets its own code.</p>'}`);
        } catch {}
    };
    /* ----- Reviews: what buyers said; hide any from the shop, or delete them */
    const drawReviews = async (box, seg) => {
        box.innerHTML = seg + '<p class="lpb-hint">Loading reviews…</p>';
        let list;
        try { list = (await api('GET', `/builder/stores/${P.site.shopId}/reviews`)).reviews; }
        catch (ex) { box.innerHTML = seg + `<p class="lpb-err">${esc(ex.message)}</p>`; return; }
        box.innerHTML = seg + (list.length ? list.map(r => `<div class="lpb-review${r.hidden ? ' is-off' : ''}"><div><b aria-label="${r.rating} of 5 stars">${oi('star-solid').repeat(r.rating)}${oi('star-outline').repeat(5 - r.rating)}</b> ${esc(r.product)}</div>
                ${r.text ? `<p>${esc(r.text)}</p>` : ''}<small>${esc(r.name)} · ${esc(new Date(r.date).toLocaleDateString())}${r.hidden ? ' · hidden' : ''}</small>
                <span><button type="button" class="lpb-link-btn" data-review="${r.id}|${r.hidden ? 'show' : 'hide'}">${r.hidden ? 'Show on the shop' : 'Hide'}</button> <button type="button" class="lpb-link-btn lpb-danger" data-review="${r.id}|delete">Delete</button></span></div>`).join('')
            : '<div class="lpb-empty-edit"><b>No reviews yet</b><p>Buyers can review what they bought from their order page once you mark the order as sent (downloads and gift cards: once paid). Stars show on your shop by themselves.</p></div>');
    };
    const ORDER_STATUS = { unpaid: 'Waiting for payment', new: 'New', shipped: 'Sent', done: 'Completed', cancelled: 'Cancelled' };
    const PAY_LABEL = { card: 'Card', cod: 'Cash on delivery', bank: 'Bank transfer' };
    const ordersBox = () => $('[data-orders-panel]');
    const ordersBadge = n => { const t = $('[data-tab=orders]'); if (t) t.innerHTML = `Orders${n ? ` <span class="lpb-badge">${n}</span>` : ''}`; };
    const drawOrders = async () => {
        const box = ordersBox(); if (!box) return;
        if (!P.site.shopId) { box.innerHTML = '<div class="lpb-empty-edit"><b>No orders yet</b><p>Switch on checkout so customers can order. Every order is emailed to you and listed here.</p><button type="button" class="btn btn--sm btn--glow" data-activate-shop>Switch on checkout</button></div>'; return; }
        if (!token) { box.innerHTML = '<div class="lpb-empty-edit"><b>See your orders</b><p>Sign in with the email you used for this shop.</p><button type="button" class="btn btn--sm btn--glow" data-orders-signin>Sign in with email</button></div>'; return; }
        const seg = `<div class="lpb-seg" role="tablist"><button type="button" role="tab" data-oview="orders" aria-selected="${ordersView === 'orders'}">Orders</button><button type="button" role="tab" data-oview="sales" aria-selected="${ordersView === 'sales'}">Sales</button><button type="button" role="tab" data-oview="codes" aria-selected="${ordersView === 'codes'}">Codes</button><button type="button" role="tab" data-oview="reviews" aria-selected="${ordersView === 'reviews'}">Reviews</button></div>`;
        if (ordersView === 'codes') return drawCodes(box, seg);
        if (ordersView === 'sales') return drawSales(box, seg);
        if (ordersView === 'reviews') return drawReviews(box, seg);
        box.innerHTML = seg + '<p class="lpb-hint">Loading orders…</p>';
        let r;
        try { r = await api('GET', `/builder/stores/${P.site.shopId}/orders${orderFilter ? '?status=' + orderFilter : ''}`); }
        catch (ex) { if (ex.status === 404) { shopGone(); return drawOrders(); } box.innerHTML = `<p class="lpb-err">${esc(ex.message)}</p>`; return; }
        storeInfo = r.store; ordersBadge(r.counts.new || 0);
        const chips = [['', 'All', r.total], ...Object.entries(ORDER_STATUS).map(([k, l]) => [k, l, r.counts[k] || 0])].filter(([k, , n]) => !k || n || k === orderFilter)
            .map(([k, l, n]) => `<button type="button" class="lpb-chip${k === orderFilter ? ' is-on' : ''}" data-orders-filter="${k}">${esc(l)} <small>${n}</small></button>`).join('');
        const row = o => `<details class="lpb-order"><summary><span class="lpb-order__top"><b>${esc(o.label)}</b> ${esc(o.name)}<b class="lpb-order__sum">${esc(o.total)}</b></span>
                <span class="lpb-order__meta"><span class="lpb-ost is-${esc(o.status)}">${esc(ORDER_STATUS[o.status] || o.status)}</span>${esc(new Date(o.created_at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }))} · ${esc(PAY_LABEL[o.pay] || o.pay)}${o.paid ? ' · paid' : ''}${o.test ? ' · test' : ''}</span></summary>
            <div class="lpb-order__body"><table>${o.items.map(l => `<tr><td>${esc(l.qty)} × ${esc(l.name)}${l.option ? `<small>${esc(l.option)}</small>` : ''}</td><td>${esc(l.total)}</td></tr>`).join('')}
                ${o.discount ? `<tr class="is-sum"><td>Discount ${o.discount_code ? `(${esc(o.discount_code)})` : ''}</td><td>${esc(o.discount)}</td></tr>` : ''}${o.gift ? `<tr class="is-sum"><td>Gift card (${esc(o.gift_code)})</td><td>${esc(o.gift)}</td></tr>` : ''}
                <tr class="is-sum"><td>${o.delivery === 'pickup' ? 'Pick-up' : 'Delivery'}</td><td>${esc(o.delivery_fee)}</td></tr>${o.tax && !o.tax_included ? `<tr class="is-sum"><td>Tax</td><td>${esc(o.tax)}</td></tr>` : ''}
                <tr class="is-total"><td>Total${o.tax && o.tax_included ? ` <small>incl. ${esc(o.tax)} tax</small>` : ''}</td><td>${esc(o.total)}</td></tr>${o.refunded ? `<tr class="is-refund"><td>Refunded</td><td>−${esc(o.refunded)}</td></tr>` : ''}</table>
                <p><a href="mailto:${esc(o.email)}">${esc(o.email)}</a>${o.phone ? ` · <a href="tel:${esc(o.phone.replace(/[^\d+]/g, ''))}">${esc(o.phone)}</a>` : ''}</p>
                ${o.address ? `<p class="lpb-order__addr">${esc(o.address)}${o.country ? '\n' + esc(o.country) : ''}</p>` : '<p class="lpb-hint">Picking it up.</p>'}${o.tracking ? `<p>${oi('truck')} Tracking: ${/^https?:\/\//i.test(o.tracking) ? `<a href="${esc(o.tracking)}" target="_blank" rel="noopener nofollow">${esc(o.tracking)}</a>` : esc(o.tracking)}</p>` : ''}${o.note ? `<p class="lpb-order__note">“${esc(o.note)}”</p>` : ''}
                ${o.status === 'unpaid' ? `<p class="lpb-hint">The customer started paying by card but Stripe hasn’t confirmed it yet.</p><div class="lpb-block-actions"><button type="button" class="btn btn--sm btn--glow" data-order-check="${o.number}">Check payment</button><button type="button" class="btn btn--sm btn--ghost lpb-danger" data-order-set="${o.number}|cancelled">Cancel order</button></div>`
                    : o.status === 'cancelled' ? '<p class="lpb-hint">Cancelled — its stock went back.</p>' : `<label>Status<select data-order-status="${o.number}">${['new', 'shipped', 'done', 'cancelled'].map(k => `<option value="${k}" ${o.status === k ? 'selected' : ''}>${ORDER_STATUS[k]}</option>`).join('')}</select></label>
                    ${o.pay !== 'card' ? `<label class="lpb-check"><input type="checkbox" data-order-paid="${o.number}" ${o.paid ? 'checked' : ''}> Paid</label>` : ''}
                    ${o.refundable ? `<button type="button" class="btn btn--sm btn--ghost" data-order-refund="${o.number}|${esc(o.refundable)}|${o.pay}">${oi('undo')} Refund${o.refunded ? ' more' : ''}…</button>` : ''}`}</div></details>`;
        box.innerHTML = seg + `<div class="lpb-orders-top"><div class="lpb-chips">${chips}</div><button type="button" class="lpb-tb" data-orders-refresh title="Check for new orders" aria-label="Check for new orders">${oi('refresh')}</button></div>
            ${r.orders.length ? r.orders.map(row).join('') : `<div class="lpb-empty-edit"><b>${orderFilter ? 'No orders here' : 'No orders yet'}</b><p>${orderFilter ? 'Try another filter.' : 'Orders appear here (and in your email) as soon as a customer checks out. Publish your shop and share the link!'}</p></div>`}
            ${r.total ? '<button type="button" class="btn btn--sm btn--ghost" data-orders-csv>Download all orders (CSV)</button>' : ''}`;
    };
    const op = ordersBox()?.closest('[data-tab-panel]');
    if (op) {
        const update = async (number, body) => { try { await api('POST', `/builder/stores/${P.site.shopId}/orders/${number}`, body); toast('Order updated', 'success'); } catch (ex) { toast(ex.message, 'error'); } drawOrders(); };
        op.addEventListener('click', async e => {
            if (e.target.closest('[data-orders-signin]')) return needAccount(() => { dlg.close(); drawOrders(); });
            if (e.target.closest('[data-activate-shop]')) return activateShop();
            if (e.target.closest('[data-orders-refresh]')) return drawOrders();
            const ov = e.target.closest('[data-oview]'); if (ov) { ordersView = ov.dataset.oview; return drawOrders(); }
            const rvb = e.target.closest('[data-review]');
            if (rvb) {
                const [rid, act] = rvb.dataset.review.split('|');
                if (act === 'delete' && !(await sweet({ icon: 'warning', title: 'Delete this review?', text: 'It goes from your shop for good. Hiding keeps it, just not on the shop.', confirm: 'Delete', danger: true }))) return;
                try { await api('POST', `/builder/stores/${P.site.shopId}/reviews/${rid}`, act === 'delete' ? { delete: true } : { hidden: act === 'hide' }); toast(act === 'delete' ? 'Review deleted' : act === 'hide' ? 'Hidden from your shop' : 'Showing on your shop', 'success'); } catch (ex) { toast(ex.message, 'error'); }
                return drawOrders();
            }
            const ce = e.target.closest('[data-code-edit]'); if (ce) { codeEditing = codesCache.find(c => String(c.id) === ce.dataset.codeEdit) || null; return drawOrders(); }
            if (e.target.closest('[data-code-new]')) { codeEditing = {}; return drawOrders(); }
            if (e.target.closest('[data-code-cancel]')) { codeEditing = null; return drawOrders(); }
            const cd = e.target.closest('[data-code-del]');
            if (cd) { if (await sweet({ icon: 'warning', title: 'Delete this code?', text: 'Customers won’t be able to use it any more.', confirm: 'Delete', danger: true })) { try { await api('POST', `/builder/stores/${P.site.shopId}/discounts/${cd.dataset.codeDel}/delete`); toast('Code deleted'); } catch (ex) { toast(ex.message, 'error'); } drawOrders(); } return; }
            const f = e.target.closest('[data-orders-filter]'); if (f) { orderFilter = f.dataset.ordersFilter; return drawOrders(); }
            const set = e.target.closest('[data-order-set]');
            if (set) { const [n, st] = set.dataset.orderSet.split('|'); if (await sweet({ icon: 'warning', title: 'Cancel this order?', text: 'It stays in your list as cancelled.', confirm: 'Cancel order', cancel: 'Keep it', danger: true })) update(n, { status: st }); return; }
            const rf = e.target.closest('[data-order-refund]');
            if (rf) {
                const [n, max, pay] = rf.dataset.orderRefund.split('|'), cur = (P.site.shop.currency || 'usd').toUpperCase();
                const amount = await sweet({ icon: 'question', title: 'Refund how much?', text: pay === 'card' ? `The money goes back to the customer’s card through Stripe (up to ${max} ${cur}).` : `Pay it back to the customer yourself — this records it (up to ${max} ${cur}).`,
                    input: { value: max, placeholder: max, max: 12 }, confirm: pay === 'card' ? 'Refund through Stripe' : 'Record refund', cancel: 'Cancel' });
                if (amount === null) return;
                rf.disabled = true;
                try { await api('POST', `/builder/stores/${P.site.shopId}/orders/${n}/refund`, { amount }); toast('Refunded — the customer has been emailed', 'success'); track('store_refund', { pay }); }
                catch (ex) { toast(ex.message, 'error'); }
                return drawOrders();
            }
            const sr = e.target.closest('[data-sales-days]'); if (sr) { salesDays = +sr.dataset.salesDays; return drawOrders(); }
            const chk = e.target.closest('[data-order-check]');
            if (chk) { chk.disabled = true; try { const r = await api('POST', `/builder/stores/${P.site.shopId}/orders/${chk.dataset.orderCheck}/check`); toast(r.paid ? 'Paid — the order is now New' : 'Not paid yet', r.paid ? 'success' : 'info'); } catch (ex) { toast(ex.message, 'error'); } return drawOrders(); }
            if (e.target.closest('[data-orders-csv]')) {
                try {
                    const res = await fetch(`/builder/stores/${P.site.shopId}/orders?format=csv`, { headers: { 'X-Builder-Token': token, Accept: 'text/csv' } });
                    if (!res.ok) throw new Error('Could not download the orders.');
                    save(await res.blob(), `orders-${baseName()}-${new Date().toISOString().slice(0, 10)}.csv`); track('store_orders_csv');
                } catch (ex) { toast(ex.message, 'error'); }
            }
        });
        op.addEventListener('submit', async e => {
            const f = e.target.closest('[data-code-form]'); if (!f) return;
            e.preventDefault();
            const err = f.querySelector('.lpb-err'); err.hidden = true;
            const v = n => f[n]?.value.trim() ?? '';
            try {
                await api('POST', `/builder/stores/${P.site.shopId}/discounts`, { id: f.dataset.codeForm ? +f.dataset.codeForm : null, code: v('code'), kind: v('kind'), value: v('value') || null, min: v('min') || null, ends: v('ends') || null, max_uses: v('max_uses') || null, active: f.active.checked });
                codeEditing = null; toast('Code saved', 'success'); track('store_code_saved');
                if (!P.site.shop.codes) { P.site.shop.codes = true; commit(); toast('Discount codes are on at checkout — publish again to show the box'); }
                drawOrders();
            } catch (ex) { err.textContent = ex.message; err.hidden = false; }
        });
        op.addEventListener('change', e => {
            const kind = e.target.closest('[data-code-kind]'); if (kind) { kind.form.querySelector('[data-code-value]').hidden = kind.value === 'shipping'; kind.form.querySelector('[data-code-unit]').textContent = kind.value === 'percent' ? '%' : (P.site.shop.currency || 'usd').toUpperCase(); return; }
            const st = e.target.closest('[data-order-status]');
            if (st) return (async () => {
                if (st.value === 'cancelled' && !(await sweet({ icon: 'warning', title: 'Cancel this order?', text: 'Its stock goes back on sale. A cancelled order can’t be opened again.', confirm: 'Cancel order', cancel: 'Keep it', danger: true }))) return drawOrders();
                if (st.value !== 'shipped') return update(st.dataset.orderStatus, { status: st.value });
                // "Sent": the customer gets an email that it's on its way, with the tracking number if there is one
                if (!(await sweet({ icon: 'question', title: 'Mark this order as sent?', text: 'We email the customer that their order is on its way.', confirm: 'Yes, it’s sent', cancel: 'Not yet' }))) return drawOrders();
                const tracking = await sweet({ icon: 'info', title: 'Add a tracking number?', text: 'It goes in the email so the customer can follow the parcel.', input: { placeholder: 'Tracking number or link', max: 500 }, confirm: 'Add it', cancel: 'No tracking number' });
                update(st.dataset.orderStatus, { status: 'shipped', tracking: tracking || '' });
            })();
            const pd = e.target.closest('[data-order-paid]'); if (pd) return update(pd.dataset.orderPaid, { paid: pd.checked });
        });
        if (token && P.site.shopId) loadStore().then(si => si && ordersBadge(si.new_orders));
    }


    /* =========================================================== saved to the account ("My sites", Save) */
    // Like the app builder: the whole editable project is saved to the visitor's builder account
    // (BuilderProjectController), listed under "My sites" and opened again on any device. The browser copy
    // (localStorage) keeps working as before.
    const KIND = FUNNEL ? 'funnel' : KIT || 'landing';
    const NOUN = FUNNEL ? 'funnel' : KIT === 'links' ? 'page' : KIT === 'soon' ? 'page' : KIT === 'menu' ? 'menu' : KIT === 'store' ? 'shop' : 'site';
    const CLOUD_KEY = STORE + '-cloud';
    const hashOf = str => { let h = 5381; for (let i = 0; i < str.length; i++) h = (h * 33 ^ str.charCodeAt(i)) >>> 0; return h.toString(36) + ':' + str.length; };
    let cloud = {}; try { cloud = JSON.parse(localStorage.getItem(CLOUD_KEY) || '{}') || {}; } catch {}
    let cloudList = null; // this account's saved projects of this builder (null: not loaded)
    function setCloud(c) { cloud = c || {}; try { localStorage.setItem(CLOUD_KEY, JSON.stringify(cloud)); } catch {} cloudStatus(); }
    const unsaved = () => !cloud.id || cloud.saved !== hashOf(JSON.stringify(P));
    /** The line under the toolbar and the Save button: not saved yet / unsaved changes / saved. */
    function cloudStatus() {
        const st = root.querySelector('[data-cloud-status]'), btn = root.querySelector('[data-act="cloud-save"]');
        if (!st || !btn || typeof P === 'undefined' || !P) return;
        const dirty = unsaved();
        st.textContent = !cloud.id ? `Not saved yet — your ${NOUN} is kept in this browser. Save it to your account to open it anywhere.`
            : dirty ? 'Unsaved changes — press Save to update your account copy.' : 'Saved to your account';
        st.dataset.state = !cloud.id ? 'new' : dirty ? 'dirty' : 'saved';
        btn.innerHTML = !cloud.id ? `${oi('cloud')} Save` : dirty ? `${oi('cloud')} Save changes` : `${oi('tick')} Saved`; // constant strings only
        btn.classList.toggle('btn--glow', dirty); btn.classList.toggle('btn--ghost', !dirty);
    }
    const drawCloudList = () => {
        const sel = root.querySelector('[data-cloud-list]'), signin = root.querySelector('[data-cloud-signin]');
        if (!sel) return;
        const signedIn = !!token && cloudList !== null;
        sel.hidden = !signedIn; signin.hidden = signedIn;
        if (!signedIn) return;
        sel.innerHTML = `<option value="">My ${NOUN}s (${cloudList.length})</option>`
            + cloudList.map(x => `<option value="${esc(x.id)}" ${x.id === cloud.id ? 'selected' : ''}>${esc(x.name)}</option>`).join('')
            + `<option value="new">New ${NOUN}…</option>`;
        if (!cloudList.some(x => x.id === cloud.id)) sel.value = '';
    };
    const loadCloudList = async () => {
        try { cloudList = (await api('GET', '/builder/projects?kind=' + KIND)).projects || []; }
        catch (ex) { cloudList = null; if (ex.status !== 401) toast(ex.message, 'error'); }
        drawCloudList();
    };
    const saveCloud = async () => {
        flush();
        const btn = root.querySelector('[data-act="cloud-save"]'); btn.disabled = true;
        const send = async (id, base) => api('POST', '/builder/projects', { id: id || null, kind: KIND, name: P.site.name || 'Untitled', data: P, base: id ? base || null : null });
        try {
            let r;
            try { r = await send(cloud.id, cloud.at); }
            catch (ex) {
                if (ex.status === 404 && cloud.id) r = await send(null); // deleted elsewhere: save as new
                else if (ex.status === 409) { // saved on another device since this copy was opened
                    if (!(await sweet({ icon: 'warning', title: 'Changed on another device', text: `This ${NOUN} was saved from another device or tab after you opened it. Save yours over it — or keep theirs and open it again from “My ${NOUN}s”.`, confirm: 'Save mine over it', cancel: 'Keep theirs' }))) return false;
                    r = await send(cloud.id);
                } else throw ex;
            }
            setCloud({ id: r.project.id, saved: hashOf(JSON.stringify(P)), at: r.project.updated });
            toast(`Saved to your account — open it any time from “My ${NOUN}s”.`, 'success');
            track('builder_cloud_save', { kind: KIND });
            await loadCloudList();
            return true;
        } catch (ex) { toast(ex.message, 'error'); if (ex.status === 401) signIn(saveCloud); return false; }
        finally { btn.disabled = false; }
    };
    const askLoseChanges = async what => !unsaved() || !!(await sweet({ icon: 'warning', title: 'Unsaved changes', text: `Your ${NOUN} has changes that aren’t saved to your account. ${what} anyway? (They stay in the undo history until you leave the page.)`, confirm: what, cancel: 'Go back' }));
    const openCloud = async id => {
        try {
            const r = await api('GET', '/builder/projects/' + id);
            const data = B.migrate(r.project.data);
            if (KIT) data.kind = KIT;
            data.pages.forEach(pg => B.render(data, pg, 'edit'));
            undo = []; redo = []; P = data; last = JSON.stringify(P); pageIdx = 0; variant = 'a'; sel = null; // (undo can't step back into another project: Save would write it over this one)
            if (KIT === 'store') { codesCache = []; codeEditing = null; orderFilter = ''; } // another shop's codes and orders
            setCloud({ id: r.project.id, saved: hashOf(JSON.stringify(P)), at: r.project.updated });
            persist(); renderAll(); syncHistory(); drawCloudList();
            toast(`Opened “${r.project.name}”`, 'success');
        } catch (ex) { toast(ex.message, 'error'); drawCloudList(); }
    };
    /** A fresh project of this builder (its first example), then the quick setup from the start. */
    const newProjectStart = () => {
        const keep = Object.fromEntries(B.ACCOUNT_KEYS.map(k => [k, P.site[k]]));
        undo.push(JSON.stringify(P)); redo = [];
        P = fresh(Object.keys(EXAMPLES)[0]); Object.keys(keep).forEach(k => keep[k] && (P.site[k] = keep[k]));
        last = JSON.stringify(P); pageIdx = 0; variant = 'a'; sel = null;
        setCloud({}); persist(); renderAll(); syncHistory(); drawCloudList();
        wizard?.open(true);
    };
    root.addEventListener('click', e => {
        if (e.target.closest('[data-act="cloud-save"]')) { if (!unsaved()) return toast('Already saved — nothing new to save.', 'info'); needAccount(async () => { if (dlg.open) dlg.close(); await saveCloud(); }); }
        else if (e.target.closest('[data-cloud-signin]')) needAccount(async () => { dlg.close(); await loadCloudList(); root.querySelector('[data-cloud-list]')?.focus(); });
    });
    root.querySelector('[data-cloud-list]')?.addEventListener('change', async e => {
        const v = e.target.value; if (!v) return;
        if (v === 'new') { if (await askLoseChanges('Start a new one')) newProjectStart(); else drawCloudList(); return; }
        if (v === cloud.id) return;
        if (await askLoseChanges('Open another')) openCloud(v); else drawCloudList();
    });
    if (token) loadCloudList();


    /* =========================================================== quick setup wizard (public/js/builder-wizard.js) */
    // The owl's banner on a first visit, then a few short steps: what it is, name and logo, the main words, colours,
    // the look and (for several pages) which pages to keep — with the page on a phone beside them. Answers go
    // straight into the project, so the full editor shows the same thing afterwards.
    let wizard = null;
    const WZ = window.OwlWizard;
    if (WZ) {
        const EM = { agency: 'briefcase', dental: 'tooth', restaurant: 'food', course: 'graduation', realestate: 'home', services: 'wrench', business: 'briefcase', clinic: 'health', trades: 'wrench',
            photographer: 'camera', designer: 'palette', developer: 'monitor', artist: 'pen', creator: 'film', musician: 'music', shop: 'bag', coach: 'compass',
            wedding: 'gem', birthday: 'cake', conference: 'mic', babyshower: 'bottle', cafe: 'coffee', pizzeria: 'pizza', foodtruck: 'truck',
            launch: 'rocket', maintenance: 'wrench', waitlist: 'phone', opening: 'store', fashion: 'shirt', bakery: 'bakery', beauty: 'bottle', handmade: 'vase', leadmagnet: 'magnet', webinar: 'monitor', tripwire: 'tag', consult: 'call',
            salon: 'scissors', fitness: 'dumbbell', cleaning: 'sparkle', accounting: 'chart', sushi: 'bowl', curry: 'bowl', burger: 'burger', bar: 'cocktail', coffee: 'coffee', florist: 'flower', pets: 'paw', jewellery: 'gem' };
        const WHAT = FUNNEL ? 'funnel' : { website: 'website', portfolio: 'portfolio', links: 'link-in-bio page', soon: 'coming soon page', event: 'event website', menu: 'menu', store: 'online store' }[KIT] || 'page';
        let picked = null, hadWork = false;
        try { hadWork = !!localStorage.getItem(STORE + '-touched') || !!cloud.id; } catch {}
        const widgets = () => P.pages[0].sections.flatMap(sec => sec.cols.flatMap(c => c.widgets));
        /** The words people see first: the top section's headline, text and button. */
        const main = () => {
            const ws = widgets(), hero = ws.find(w => w.type === 'hero');
            if (hero) return { eyebrow: hero.p.eyebrow !== undefined ? [hero.p, 'eyebrow'] : null, title: [hero.p, 'title'], text: [hero.p, 'text', 'plain'], btn: hero.p.btn !== undefined ? [hero.p, 'btn'] : null };
            const h = ws.find(w => w.type === 'heading' && w.p.tag === 'h1') || ws.find(w => w.type === 'heading');
            const t = ws.find(w => w.type === 'text'), b = ws.find(w => w.type === 'button');
            return { title: h ? [h.p, 'text'] : null, text: t ? [t.p, 'html', 'html'] : null, btn: b ? [b.p, 'text'] : null };
        };
        const plain = html => { const d = document.createElement('div'); d.innerHTML = html || ''; return d.textContent.trim(); };
        const val = ref => (ref ? (ref[2] === 'html' ? plain(ref[0][ref[1]]) : ref[0][ref[1]] || '') : '');
        const set = (ref, v) => { if (!ref) return; ref[0][ref[1]] = ref[2] === 'html' ? v.split(/\n+/).filter(Boolean).map(l => `<p>${esc(l)}</p>`).join('') || '<p></p>' : v; };
        let previewFrame = null, previewTimer = 0;
        const changed = () => { commitSoon(); clearTimeout(previewTimer); previewTimer = setTimeout(drawPreview, 120); };
        const drawPreview = () => {
            if (!previewFrame) return;
            const y = previewFrame.contentDocument?.scrollingElement?.scrollTop || 0;
            // (no header blur here: Chrome draws it past the phone's rounded corners)
            previewFrame.srcdoc = B.render(P, P.pages[0], 'download', { runtime: RUNTIME, extraHead: '<style>.nav,.ob-bar{-webkit-backdrop-filter:none!important;backdrop-filter:none!important}</style>' });
            previewFrame.onload = () => { try { previewFrame.contentDocument.scrollingElement.scrollTop = y; previewFrame.contentDocument.addEventListener('click', ev => ev.preventDefault(), true); } catch {} };
        };
        const LOOKS = [
            ['theme', 'Light or dark', [['light', `${oi('sun')} Light`], ['dark', `${oi('moon')} Dark`]]],
            ['fontPair', 'Fonts', [['Poppins|Inter', 'Modern'], ['Playfair Display|Lato', 'Elegant'], ['Montserrat|Open Sans', 'Bold'], ['DM Sans|DM Sans', 'Simple']]],
            ['radius', 'Corners', [['round', 'Rounded'], ['soft', 'Soft'], ['sharp', 'Square']]],
            ['buttons', 'Buttons', [['pill', 'Pill'], ['rounded', 'Rounded'], ['square', 'Square']]],
        ];
        let drop = new Set();
        const steps = [
            {
                title: `What kind of ${WHAT} is it?`, sub: 'We’ll start you with a ready design made for it — you can change everything.',
                draw: () => WZ.cards([...(hadWork ? [['keep', 'check', `Keep my ${NOUN}`, 'Update what’s there']] : []), ...Object.entries(EXAMPLES).map(([k, v]) => [k, EM[k] || 'sparkle', v[0]])], picked),
                click: (e, el, box) => {
                    if (!el.dataset.wzPick) return;
                    picked = el.dataset.wzPick; WZ.press(box, '[data-wz-pick]', el);
                    if (picked === 'keep') return;
                    const keep = Object.fromEntries(B.ACCOUNT_KEYS.map(k => [k, P.site[k]])), name = P.site.name, logo = P.site.logo;
                    P = fresh(picked); Object.keys(keep).forEach(k => keep[k] && (P.site[k] = keep[k]));
                    if (wizardNamed) { P.site.name = name; P.site.logo = logo; } // a new design keeps the name and logo they already gave
                    pageIdx = 0; variant = 'a'; sel = null; drop = new Set(); changed();
                },
                ok: () => (picked ? '' : 'Pick the closest one — you can change everything later.'),
            },
            {
                title: `Name your ${NOUN}`, sub: 'It shows in the header, the browser tab and the footer.',
                draw: () => `<label class="wz-field">Name<input data-wz="name" maxlength="60" value="${esc(P.site.name)}" placeholder="e.g. Bean There Café"></label>
                    <div class="wz-logo">${P.site.logo ? `<img src="${esc(P.site.logo)}" alt="" class="wz-logo__img">` : '<span class="wz-logo__none">No logo</span>'}
                        <label class="wz-upload"><input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" data-wz-file class="sr-only"><span>${oi('upload')} ${P.site.logo ? 'Change logo' : 'Upload a logo (optional)'}</span><small>PNG, JPG or SVG — a wide logo looks best.</small></label>
                        ${P.site.logo ? '<button type="button" class="ab-link" data-wz-nologo>Remove logo</button>' : ''}</div>`,
                input: (e, el) => { if (el.dataset.wz === 'name') { P.site.name = el.value; wizardNamed = true; changed(); } },
                change: async (e, el, box) => {
                    if (!el.matches('[data-wz-file]') || !el.files[0]) return;
                    try { P.site.logo = await readImage(el.files[0], 600, true); wizardNamed = true; changed(); wizard.redraw(); } catch { toast('That picture could not be read — try a PNG or JPG.', 'error'); }
                },
                click: (e, el) => { if (el.matches('[data-wz-nologo]')) { P.site.logo = ''; changed(); wizard.redraw(); } },
                ok: () => (P.site.name.trim() ? '' : `Please give your ${NOUN} a name.`),
            },
            {
                title: 'Your main message', sub: 'The first words visitors read. Say what you offer and what to do next.',
                skip: () => { const m = main(); return !m.title && !m.text && !m.btn; },
                draw: () => { const m = main(); return `${m.eyebrow ? `<label class="wz-field">Small label above it <small>— e.g. what you do, in a few words</small><input data-wz="eyebrow" maxlength="80" value="${esc(val(m.eyebrow))}"></label>` : ''}${m.title ? `<label class="wz-field">Headline<input data-wz="title" maxlength="120" value="${esc(val(m.title))}"></label>` : ''}
                    ${m.text ? `<label class="wz-field">A line or two about it<textarea data-wz="text" rows="3" maxlength="400">${esc(val(m.text))}</textarea></label>` : ''}
                    ${m.btn ? `<label class="wz-field">Button<input data-wz="btn" maxlength="40" value="${esc(val(m.btn))}"></label>` : ''}`; },
                input: (e, el) => { const m = main(), k = el.dataset.wz; if (m[k]) { set(m[k], el.value); changed(); } },
            },
            {
                title: 'How do you sell?', sub: 'Your currency, delivery and how customers pay. You can add card payments (Stripe) later in the Site tab.',
                skip: () => KIT !== 'store',
                draw: () => {
                    const sh = P.site.shop, chip = (k, label) => `<button type="button" data-wz-shop-t="${k}" aria-pressed="${!!sh[k]}">${label}</button>`;
                    return `<label class="wz-field">Currency<select data-wz-shop="currency">${currencyOptions(sh.currency)}</select></label>
                        <div class="wz-look"><b>Getting it to customers</b><div class="wz-chips">${chip('delivery', `${oi('truck')} I deliver`)}${chip('pickup', `${oi('store')} Customers can pick up`)}</div></div>
                        ${sh.delivery ? `<label class="wz-field">Delivery fee <small>— 0 for free delivery</small><input data-wz-shop="fee" inputmode="decimal" maxlength="12" value="${esc(sh.fee)}"></label>` : ''}
                        <div class="wz-look"><b>How customers pay</b><div class="wz-chips">${chip('cod', `${oi('cash')} Cash on delivery`)}${chip('bank', `${oi('bank')} Bank transfer`)}</div></div>`;
                },
                input: (e, el) => { if (el.dataset.wzShop) { P.site.shop[el.dataset.wzShop] = el.value; changed(); } },
                change: (e, el) => { if (el.dataset.wzShop) { P.site.shop[el.dataset.wzShop] = el.value; changed(); } },
                click: (e, el) => {
                    const k = el.dataset.wzShopT; if (!k) return;
                    const sh = P.site.shop; sh[k] = !sh[k];
                    if (!sh.delivery && !sh.pickup) sh[k === 'delivery' ? 'pickup' : 'delivery'] = true; // one way to get it to them
                    if (!sh.cod && !sh.bank && !sh.card) sh[k === 'cod' ? 'bank' : 'cod'] = true; // and one way to pay
                    changed(); wizard.redraw();
                },
            },
            {
                title: 'Pick your colours', sub: 'Buttons, links and highlights use your main colour.',
                draw: () => WZ.palette(P.site.colors.primary),
                click: (e, el, box) => { if (!el.dataset.pal) return; P.site.colors.primary = el.dataset.pal; WZ.press(box, '[data-pal]', el); box.querySelector('[data-wz-color]').value = el.dataset.pal; syncGlobals(); changed(); },
                input: (e, el, box) => { if (!el.matches('[data-wz-color]')) return; P.site.colors.primary = el.value; box.querySelectorAll('[data-pal]').forEach(x => x.setAttribute('aria-pressed', 'false')); syncGlobals(); changed(); },
            },
            {
                title: 'Make it look yours', sub: 'Watch the preview change as you pick.',
                draw: () => LOOKS.map(([k, label, opts]) => {
                    const cur = k === 'fontPair' ? `${P.site.fonts.heading}|${P.site.fonts.body}` : P.site[k];
                    return `<div class="wz-look"><b>${label}</b><div class="wz-chips">${opts.map(([v, l]) => `<button type="button" data-wz-look="${k}:${esc(v)}" aria-pressed="${cur === v}">${l}</button>`).join('')}</div></div>`;
                }).join(''),
                click: (e, el, box) => {
                    if (!el.dataset.wzLook) return;
                    const [k, v] = el.dataset.wzLook.split(/:(.*)/s);
                    if (k === 'fontPair') { const [h, b] = v.split('|'); P.site.fonts.heading = h; P.site.fonts.body = b; } else P.site[k] = v;
                    el.parentNode.querySelectorAll('button').forEach(x => x.setAttribute('aria-pressed', String(x === el)));
                    changed();
                },
            },
            {
                title: 'Choose your pages', sub: 'Untick the pages you don’t need. You can add pages any time in the editor.',
                skip: () => FUNNEL || P.pages.length < 2,
                draw: () => `<div class="wz-pages">${P.pages.map((pg, i) => `<label class="wz-page${drop.has(i) ? ' is-off' : ''}" style="--i:${i}"><input type="checkbox" data-wz-page="${i}" ${drop.has(i) ? '' : 'checked'} ${i === 0 ? 'disabled' : ''}><span class="wz-page__ic">${oi(i === 0 ? 'home' : 'doc')}</span><b>${esc(pg.title)}</b>${i === 0 ? '<small>Home page — always kept</small>' : ''}</label>`).join('')}</div>`,
                change: (e, el) => { if (!el.dataset.wzPage) return; const i = +el.dataset.wzPage; if (el.checked) drop.delete(i); else drop.add(i); el.closest('.wz-page').classList.toggle('is-off', !el.checked); },
                leave: () => { if (drop.size) { P.pages = P.pages.filter((pg, i) => !drop.has(i)); drop = new Set(); pageIdx = 0; changed(); } },
            },
        ];
        let wizardNamed = false;
        wizard = WZ.mount({
            root, banner: document.querySelector('[data-wz-banner]'), offKey: 'owlvix-wz-' + KIND + '-off',
            say: [`Hoo! Want a ${WHAT}? I’ll set it up with you — it takes about 2 minutes.`, 'Pick what it’s for, and I’ll start you with a ready design.', 'You’ll see it on a phone as you go. Ready?'],
            hasSaved: () => !!cloud.id || !!DESIGN, // came to use a design from /examples: straight to the editor
            quickSlot: root.querySelector('.lpb-top .lpb-cloud'), quickClass: 'lpb-tb lpb-quick',
            onRestart: () => { picked = null; wizardNamed = false; hadWork = false; },
            steps,
            preview: {
                mount: side => { side.innerHTML = `<p class="wz-prev__label">Live preview</p>${WZ.phoneFrame()}`; previewFrame = side.querySelector('[data-wz-frame]'); drawPreview(); },
                unmount: () => { previewFrame = null; flush(); renderAll(); syncHistory(); },
                refresh: drawPreview,
            },
            building: `Building your ${WHAT}…`,
            tasks: ['Laying out your sections', 'Painting your colours', 'Setting your fonts', 'Polishing for phones'],
            done: {
                title: `Your ${WHAT} is ready`,
                sub: `Scroll the preview to see it. Save it to your account to open it anywhere, then publish it or download it when you’re happy — everything can still be changed in the editor.`,
                actions: [
                    { label: 'Save to my account', icon: 'cloud', cls: 'btn btn--glow', then: 'save', act: close => { close(); needAccount(async () => { if (dlg.open) dlg.close(); await saveCloud(); }); } },
                    { label: 'Keep editing', icon: 'pen', act: close => close() },
                ],
            },
            // On the builder's main page, finishing opens its editor page (same project, kept in this browser)
            editorUrl: root.dataset.editorUrl || '', noun: WHAT,
            hasWork: () => { try { return !!localStorage.getItem(STORE + '-touched') || !!cloud.id; } catch { return !!cloud.id; } },
            skipBack: () => !!DESIGN, // opening a design from the gallery: straight to the builder
            beforeLeave: () => {
                flush(); clearTimeout(saveTimer);
                try { localStorage.setItem(STORE, JSON.stringify(P)); localStorage.setItem(STORE + '-touched', '1'); return true; } catch { return false; } // too big for the browser: stay here
            },
        });
    }

    /* =========================================================== start */
    const renderAll = () => { drawPages(); renderCanvas(); drawAdd(); drawEdit(); drawSite(); drawPage(); };
    /** Swap in one of the ready designs, keeping the account links (published site, shop); Undo brings the old one back. */
    function useExample(key) {
        const keep = Object.fromEntries(B.ACCOUNT_KEYS.map(k => [k, P.site[k]]));
        P = fresh(key); Object.keys(keep).forEach(k => keep[k] && (P.site[k] = keep[k]));
        pageIdx = 0; variant = 'a'; sel = null; commit(); renderAll(); toast('Template loaded — press Undo to go back');
    }
    renderAll(); syncHistory(); setDevice(window.innerWidth < 760 ? 'mobile' : 'desktop'); showTab('add');
    persist();
    syncVariantBadge();
    if (askDesign) {
        setTimeout(() => root.scrollIntoView({ block: 'start' }), 60);
        // after every deferred script has run, so sweet.js (loaded later in the page) is ready
        const ask = () => sweet({ icon: 'question', title: `Use the “${EXAMPLES[DESIGN][0]}” design?`, text: 'It replaces what you’re working on now. You can press Undo to get yours back.', confirm: 'Use this design', cancel: 'Keep mine' })
            .then(ok => { if (ok) { setCloud({}); useExample(DESIGN); track('builder_design', { design: DESIGN }); } }); // a new project, not saved over the open one
        if (window.sweet) ask(); else document.addEventListener('DOMContentLoaded', ask, { once: true }); // fires after all deferred scripts
    } else if (DESIGN) setTimeout(() => root.scrollIntoView({ block: 'start' }), 60);
    window.__owlBuilder = { get P() { return P; }, select, find }; // for checking things from the browser console
})();
