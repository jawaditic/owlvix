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
    const SITE_BASE = root.dataset.siteBase || location.origin + '/p/'; // where published sites live, e.g. https://sites.owlvix.com/
    const SITE_HOST = SITE_BASE.replace(/^https?:\/\//, '');

    /* =========================================================== small helpers */
    const $ = (s, el = root) => el.querySelector(s);
    const $$ = (s, el = root) => [...el.querySelectorAll(s)];
    const toast = msg => { const t = $('[data-toast]'); t.textContent = msg; t.hidden = false; clearTimeout(toast.t); toast.t = setTimeout(() => (t.hidden = true), 2600); };
    const track = (e, d = {}) => window.track?.(e, d);
    const svg = d => `<svg viewBox="0 0 24 24" aria-hidden="true">${d}</svg>`;
    const ICON = {
        up: svg('<path d="M12 19V5M6 11l6-6 6 6"/>'), down: svg('<path d="M12 5v14M6 13l6 6 6-6"/>'), drag: svg('<path d="M9 5h.01M15 5h.01M9 12h.01M15 12h.01M9 19h.01M15 19h.01"/>'),
        copy: svg('<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>'),
        trash: svg('<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>'),
        edit: svg('<path d="M4 20h4L19 9l-4-4L4 16zM14 6l4 4"/>'), save: svg('<path d="M12 3l2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.6 6.6 19.5l1.2-6L3.3 9.3l6.1-.7z"/>'),
        cols: svg('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M12 4v16"/>'),
    };

    /* Styled dialogs in place of confirm()/prompt(). sweet({icon,title,text,confirm,cancel,danger,input,html}) → Promise */
    const SWEET_ICONS = { warning: '<path d="M12 8v5M12 16.5h.01"/>', question: '<path d="M9.5 9.2a2.6 2.6 0 0 1 5 .8c0 1.8-2.5 2.2-2.5 3.8M12 16.8h.01"/>', success: '<path d="M7 12.5l3.2 3.2L17 9"/>', info: '<path d="M12 11v5M12 8h.01"/>' };
    const sweet = opts => new Promise(resolve => {
        const d = document.createElement('dialog');
        d.className = `sweet sweet--${opts.icon || 'question'}`;
        d.innerHTML = `<form method="dialog" class="sweet__box">
            <span class="sweet__icon" aria-hidden="true"><svg viewBox="0 0 24 24">${SWEET_ICONS[opts.icon || 'question']}</svg></span>
            <h3 class="sweet__title">${esc(opts.title)}</h3>${opts.text ? `<p class="sweet__text">${esc(opts.text)}</p>` : ''}
            ${opts.html || ''}
            ${opts.input ? `<input class="sweet__input" maxlength="${opts.input.max || 60}" value="${esc(opts.input.value || '')}" placeholder="${esc(opts.input.placeholder || '')}">` : ''}
            <div class="sweet__actions">${opts.cancel === false ? '' : `<button type="button" class="sweet__btn sweet__btn--cancel" data-no>${esc(opts.cancel || 'Cancel')}</button>`}
            ${opts.confirm === false ? '' : `<button type="submit" class="sweet__btn ${opts.danger ? 'sweet__btn--danger' : ''}" data-yes>${esc(opts.confirm || 'OK')}</button>`}</div></form>`;
        document.body.append(d);
        const input = d.querySelector('.sweet__input');
        let done = false;
        const finish = val => { if (done) return; done = true; d.classList.add('is-closing'); setTimeout(() => { d.close(); d.remove(); }, 160); resolve(val); };
        d.querySelector('form').addEventListener('submit', e => {
            e.preventDefault();
            if (input && !input.value.trim()) { input.classList.add('is-shake'); setTimeout(() => input.classList.remove('is-shake'), 400); return input.focus(); }
            finish(input ? input.value.trim() : true);
        });
        d.querySelector('[data-no]')?.addEventListener('click', () => finish(input ? null : false));
        d.addEventListener('cancel', e => { e.preventDefault(); finish(input ? null : false); });
        d.addEventListener('click', e => { if (e.target === d) finish(input ? null : false); const pick = e.target.closest('[data-pick]'); if (pick) finish(pick.dataset.pick); });
        d.showModal();
        (input || d.querySelector('[data-yes]'))?.focus();
        input?.select();
    });

    /* =========================================================== state + history */
    let P = null;
    try { P = JSON.parse(localStorage.getItem('owlvix-builder') || 'null'); } catch {}
    try { P = P && P.pages?.length ? B.migrate(P) : null; } catch { P = null; }
    if (!P) P = B.newProject('agency');
    let pageIdx = 0, device = 'desktop';
    let sel = null; // { kind: 'section'|'col'|'widget', id, target }
    const DEV = () => ({ desktop: 'd', tablet: 't', mobile: 'm' })[device];
    const page = () => P.pages[pageIdx] || P.pages[0];

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
            try { localStorage.setItem('owlvix-builder', JSON.stringify(P)); $('[data-autosave]').textContent = 'Saved on this device'; }
            catch { $('[data-autosave]').textContent = 'Too big to auto-save — use “Save project”'; }
        }, 400);
    };
    const commit = () => {
        const now = JSON.stringify(P);
        if (now === last) return;
        undo.push(last); if (undo.length > 80) undo.shift();
        redo = []; last = now; persist(); syncHistory();
    };
    const restore = snap => {
        const fonts = JSON.stringify([P.site.fonts, P.pages.length]);
        P = JSON.parse(snap); last = snap; pageIdx = Math.min(pageIdx, P.pages.length - 1); if (sel && !find(sel.id)) sel = null; persist();
        if (fonts === JSON.stringify([P.site.fonts, P.pages.length]) && doc()?.querySelector('main')) { drawPages(); refreshChrome(); refreshMain(); drawAdd(); drawEdit(); drawSite(); drawPage(); }
        else renderAll();
        syncHistory();
    };
    const doUndo = () => { if (!undo.length) return; redo.push(JSON.stringify(P)); restore(undo.pop()); };
    const doRedo = () => { if (!redo.length) return; undo.push(JSON.stringify(P)); restore(redo.pop()); };
    const syncHistory = () => { $('[data-act=undo]').disabled = !undo.length; $('[data-act=redo]').disabled = !redo.length; };
    let later = 0;
    const commitSoon = () => { clearTimeout(later); later = setTimeout(commit, 600); persist(); };

    /* =========================================================== canvas */
    const frame = $('[data-lpb-frame]'), screen = $('.lpb-screen');
    const doc = () => frame.contentDocument;
    const EDITOR_CSS = `
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
    const renderCanvas = () => {
        scrollY = doc()?.scrollingElement?.scrollTop || 0;
        frame.srcdoc = B.render(P, page(), 'edit', { extraHead: `<style id="ed-css">${EDITOR_CSS}</style>` });
    };
    frame.addEventListener('load', () => {
        const d = doc(); if (!d?.body) return;
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
    });
    const x = () => ({ edit: true, mode: 'edit', page: page(), P, origin: location.origin });
    const refreshEl = id => {
        const d = doc(), f = find(id); if (!d?.body || !f) return renderCanvas();
        const el = d.querySelector(`[data-s="${f.sec.id}"]`);
        if (!el) return renderCanvas();
        el.outerHTML = B.renderSection(f.sec, f.si, x());
        markSelection();
    };
    /** Redraw every section in place — the canvas keeps its scroll position (no reload, no jump to the top). */
    const refreshMain = () => {
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
        const d = doc(); if (!d?.body) return;
        d.querySelectorAll('.ob-bar,.nav').forEach(el => el.remove());
        d.body.insertAdjacentHTML('afterbegin', B.header(x()));
        const foot = d.querySelector('.foot'); if (foot) foot.outerHTML = B.footer(x());
    };
    const refreshCss = () => {
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
                <button data-ed="drag" draggable="true" title="Drag to move">${ICON.drag}</button><button data-ed="up" title="Move section up" ${f.si === 0 ? 'disabled' : ''}>${ICON.up}</button><button data-ed="down" title="Move section down" ${f.si === secs.length - 1 ? 'disabled' : ''}>${ICON.down}</button>
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
                wEl.insertAdjacentHTML('afterbegin', `<div class="ed-bar ed-bar--w" data-for="${f.obj.id}"><button data-ed="drag" draggable="true" title="Drag to move">${ICON.drag}</button><button data-ed="edit">${esc(W[f.obj.type].label)}</button>
                    <button data-ed="up" ${f.wi === 0 ? 'disabled' : ''} title="Move up">${ICON.up}</button><button data-ed="down" ${f.wi === n - 1 ? 'disabled' : ''} title="Move down">${ICON.down}</button><button data-ed="dup" title="Duplicate">${ICON.copy}</button><button data-ed="del" class="ed-del" title="Delete">${ICON.trash}</button></div>`);
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
        el.contentEditable = rich ? 'true' : 'plaintext-only';
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
        if (el.hasAttribute('data-rich') && obj) setPath(obj, path, B.sanitize(el.innerHTML));
        commit();
        if (id === 'site') refreshChrome();
    };
    const onKey = e => {
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
            <button data-rt="link" title="Add link">🔗</button><button data-rt="insertUnorderedList" title="Bullet list">••</button><button data-rt="insertOrderedList" title="Numbered list">1.</button><button data-rt="removeFormat" title="Clear formatting">⌫</button></div>`);
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
                st[DEV()].bgImg = await readImage(file, 1920); st[DEV()].bgType = 'image';
                commit(); refreshCss(); drawEdit(); return;
            }
            if (key.startsWith('popup|')) { page().popup.image = await readImage(file, 1000); commit(); drawPage(); return; }
            const [obj, path, id] = ownerOf(key), isLogo = key === 'site|logo';
            setPath(obj, path, await readImage(file, isLogo ? 480 : /photo|avatar/.test(path) ? 700 : 1600, isLogo));
            commit(); id === 'site' ? (refreshChrome(), drawSite()) : (refreshEl(id), drawEdit());
        } catch { toast('Sorry, that image could not be read. Try a JPG or PNG.'); }
    });
    const pickIcon = async (id, path) => {
        const html = `<div class="icon-grid">${Object.keys(B.ICONS).map(k => `<button type="button" data-pick="i:${k}" title="${k}">${B.icon('i:' + k)}</button>`).join('')}</div><p class="sweet__text">…or type any emoji:</p>`;
        const v = await sweet({ icon: 'info', title: 'Choose an icon', html, input: { value: '', placeholder: '⭐ 🚀 ✅ 💡', max: 4 }, confirm: 'Use emoji' });
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
        if (!dragging) return;
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
    const templateSection = key => (key.startsWith('my-') ? B.reId(myTemplates()[+key.slice(3)].sec) : B.TEMPLATES.sections[key][1]());
    const saveTemplate = async s => {
        const name = await sweet({ icon: 'question', title: 'Save as my template', text: 'Reuse this section on any page. Saved in this browser.', input: { value: W[s.cols[0]?.widgets[0]?.type]?.label || 'My section', max: 40 }, confirm: 'Save template' });
        if (!name) return;
        const list = myTemplates(); list.unshift({ name, sec: clone(s) });
        try { localStorage.setItem('owlvix-builder-tpl', JSON.stringify(list.slice(0, 30))); toast('Saved to My templates (Add tab)'); drawAdd(); }
        catch { toast('No space left to save templates in this browser — remove photos or old templates.'); }
    };
    const tile = (drag, icon, label) => `<button type="button" class="lpb-add" draggable="true" data-drag="${drag}"><span>${icon}</span>${esc(label)}</button>`;
    const drawAdd = () => {
        const q = ($('[data-add-search]')?.value || '').toLowerCase().trim();
        const match = l => !q || l.toLowerCase().includes(q);
        const group = (title, inner) => (inner ? `<h4>${title}</h4><div class="lpb-add-grid">${inner}</div>` : '');
        const widgets = gr => Object.values(W).filter(d => d.group === gr && match(d.label)).map(d => tile(`new:${d.type}`, `<b class="lpb-glyph">${esc(d.icon)}</b>`, d.label)).join('');
        const layouts = Object.entries(B.LAYOUTS).filter(([, v]) => match(v[2] + ' columns layout')).map(([k, v]) => tile(`layout:${k}`, `<i class="lpb-lay" style="grid-template-columns:${v[0]}">${'<i></i>'.repeat(v[1])}</i>`, v[2])).join('');
        const secT = Object.entries(B.TEMPLATES.sections).filter(([, v]) => match(v[0])).map(([k, v]) => `<button type="button" class="lpb-tpl-row" draggable="true" data-drag="tpl:${k}">${esc(v[0])}<span>＋</span></button>`).join('');
        const pageT = Object.entries(B.TEMPLATES.pages).filter(([, v]) => match(v[0] + ' page')).map(([k, v]) => `<button type="button" class="lpb-tpl-row" data-page-tpl="${k}">📄 ${esc(v[0])}<span>＋ New page</span></button>`).join('');
        const mine = myTemplates().map((t, i) => (match(t.name) ? `<div class="lpb-tpl-row lpb-tpl-mine" draggable="true" data-drag="tpl:my-${i}">⭐ ${esc(t.name)}<button type="button" data-tpl-del="${i}" title="Remove">✕</button></div>` : '')).join('');
        const html = group('Layout — columns', layouts) + group('Basic widgets', widgets('basic')) + group('Pro widgets', widgets('pro')) + group('Ready sections', widgets('section'))
            + (secT ? `<h4>Section templates</h4><div class="lpb-tpl-list">${secT}</div>` : '') + (pageT ? `<h4>Page templates</h4><div class="lpb-tpl-list">${pageT}</div>` : '')
            + (!q || mine ? `<h4>My templates</h4><div class="lpb-tpl-list">${mine || '<p class="lpb-hint">Select a section and press ★ on its toolbar to save it here.</p>'}</div>` : '');
        $('[data-add-list]').innerHTML = html || '<p class="lpb-hint">Nothing matches your search.</p>';
    };
    $('[data-add-search]').addEventListener('input', drawAdd);
    $('[data-add-list]').addEventListener('click', e => {
        const del = e.target.closest('[data-tpl-del]');
        if (del) { e.stopPropagation(); const list = myTemplates(); list.splice(+del.dataset.tplDel, 1); try { localStorage.setItem('owlvix-builder-tpl', JSON.stringify(list)); } catch {} return drawAdd(); }
        const pt = e.target.closest('[data-page-tpl]');
        if (pt) return addPage(B.TEMPLATES.pages[pt.dataset.pageTpl][0], pt.dataset.pageTpl);
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
        return `<div class="lpb-link"><select data-link-to="${key}">${Object.entries(B.LINK_TYPES).map(([k, v]) => `<option value="${k}" ${l.to === k ? 'selected' : ''}>${v}</option>`).join('')}</select>
            ${l.to === 'page' ? `<select data-link-val="${key}">${pageOpts}</select>` : ['url', 'whatsapp', 'phone', 'email'].includes(l.to) ? `<input data-link-val="${key}" value="${esc(l.val || '')}" placeholder="${{ url: 'https://…', whatsapp: '+1 555 123 4567', phone: '+1 555 123 4567', email: 'you@example.com' }[l.to]}">` : ''}</div>`;
    };
    const fieldHtml = (f, obj, prefix) => {
        const key = prefix + f.k, v = getPath(obj, f.k);
        switch (f.type) {
            case 'text': return `<label>${f.label}<input data-f="${key}" value="${esc(v ?? '')}" placeholder="${esc(f.ph || '')}"></label>`;
            case 'textarea': return `<label>${f.label}<textarea data-f="${key}" rows="3">${esc(v ?? '')}</textarea></label>`;
            case 'number': return `<label>${f.label}<input type="number" data-f="${key}" value="${esc(v ?? '')}" min="${f.min}" max="${f.max}" step="${f.step}"></label>`;
            case 'datetime': return `<label>${f.label}<input type="datetime-local" data-f="${key}" value="${esc(v ?? '')}"></label>`;
            case 'bool': return `<label class="lpb-check"><input type="checkbox" data-f="${key}" ${v ? 'checked' : ''}> ${f.label}</label>`;
            case 'select': return `<label>${f.label}<select data-f="${key}">${Object.entries(f.options).map(([k, l]) => `<option value="${k}" ${v === k ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></label>`;
            case 'link': return `<label>${f.label}${linkWidget(key, v)}</label>`;
            case 'icon': return `<div class="lpb-imgf"><span>${f.label}</span><button type="button" class="lpb-iconbtn" data-icon-pick="${key}">${B.icon(v || '⭐')} Change icon</button></div>`;
            case 'img': return `<div class="lpb-imgf"><span>${f.label}</span>${v ? `<img src="${esc(v)}" alt="">` : ''}<div><button type="button" class="btn btn--sm btn--ghost" data-img-pick="${key}">${v ? 'Change' : 'Upload'}</button>${v ? `<button type="button" class="btn btn--sm btn--ghost" data-img-clear="${key}">Remove</button>` : ''}</div></div>`;
            default: return '';
        }
    };
    const listHtml = (f, w) => {
        const list = w.p[f.k] || [], pre = `${w.id}|${f.k}`;
        const cell = (it, i, [k, kind]) => {
            const key = `${pre}.${i}.${k}`;
            if (kind === 'img') return `<button type="button" class="lpb-thumb" data-img-pick="${key}" ${it[k] ? `style="background-image:url('${esc(it[k])}')"` : ''} title="Image">${it[k] ? '' : '＋'}</button>`;
            if (kind === 'icon') return `<button type="button" class="lpb-thumb lpb-thumb--ic" data-icon-pick="${key}" title="Icon">${B.icon(it[k] || '⭐')}</button>`;
            if (kind === 'star') return `<label class="lpb-mini" title="Highlight"><input type="checkbox" data-f="${key}" ${it[k] ? 'checked' : ''}>★</label>`;
            if (kind === 'number') return `<input type="number" class="lpb-num" data-f="${key}" value="${esc(it[k] ?? '')}" min="0" max="100">`;
            if (kind === 'social') return `<select class="lpb-net" data-f="${key}">${Object.entries(B.SOCIAL).map(([n, s]) => `<option value="${n}" ${it[k] === n ? 'selected' : ''}>${s[0]}</option>`).join('')}</select>`;
            return `<input data-f="${key}" value="${esc(it[k] ?? '')}" placeholder="${k === 'url' ? 'https://…' : ''}">`;
        };
        return `<div class="lpb-list"><span>${f.label}</span>${list.map((it, i) => `<div class="lpb-row">${f.fields.map(c => cell(it, i, c)).join('')}
            <button type="button" data-list="${f.k}|${i}|up" title="Move up" ${i === 0 ? 'disabled' : ''}>↑</button><button type="button" data-list="${f.k}|${i}|del" title="Remove">✕</button></div>`).join('')}
            ${list.length < f.max ? `<button type="button" class="btn btn--sm btn--ghost" data-list="${f.k}|-1|add">+ Add</button>` : ''}</div>`;
    };

    /* ----- style controls (per device) */
    const styleObj = (id, target = '_') => { const f = find(id); f.obj.st ||= {}; f.obj.st[target] ||= {}; ['d', 't', 'm'].forEach(k => (f.obj.st[target][k] ||= {})); return f.obj.st[target]; };
    const sv = (st, k) => st[DEV()][k] ?? '';
    const inherited = (st, k) => (DEV() === 'm' ? st.t[k] ?? st.d[k] : DEV() === 't' ? st.d[k] : '') ?? '';
    const devBadge = () => `<span class="lpb-devtag" title="Style for this screen size">${{ desktop: '🖥', tablet: '▭', mobile: '📱' }[device]}</span>`;
    const ctl = {
        range: (st, k, label, min, max, step = 1, unit = 'px') => `<label class="lpb-ctl">${label} ${devBadge()}<span class="lpb-range"><input type="range" data-sty="${k}" min="${min}" max="${max}" step="${step}" value="${esc(sv(st, k) || inherited(st, k) || min)}"><input type="number" data-sty="${k}" min="${min}" max="${max}" step="${step}" value="${esc(sv(st, k))}" placeholder="${esc(inherited(st, k) || unit)}"></span></label>`,
        select: (st, k, label, opts) => `<label class="lpb-ctl">${label} ${devBadge()}<select data-sty="${k}">${Object.entries(opts).map(([v, l]) => `<option value="${v}" ${String(sv(st, k)) === v ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></label>`,
        align: (st, k = 'align') => `<div class="lpb-ctl">Alignment ${devBadge()}<span class="lpb-seg">${[['', 'Auto'], ['left', 'Left'], ['center', 'Centre'], ['right', 'Right']].map(([v, l]) => `<button type="button" data-sty-val="${k}|${v}" class="${String(sv(st, k)) === v ? 'is-on' : ''}">${l}</button>`).join('')}</span></div>`,
        color: (st, k, label) => {
            const v = sv(st, k);
            return `<div class="lpb-ctl">${label} ${devBadge()}<span class="lpb-colorctl">${B.GLOBAL.map(gl => `<button type="button" class="lpb-sw ${v === 'g:' + gl ? 'is-on' : ''}" style="--sw:var(--g-${gl})" data-sty-val="${k}|g:${gl}" title="Global ${gl} colour"></button>`).join('')}
                <input type="color" data-sty="${k}" value="${/^#/.test(v) ? v : '#000000'}" title="Custom colour"><button type="button" class="lpb-clear" data-sty-val="${k}|" title="Clear">✕</button></span></div>`;
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
        return `<nav class="lpb-crumbs">${c.join('<i>›</i>')}</nav>`;
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
                out += `<label>Columns<span class="lpb-lays">${Object.entries(B.LAYOUTS).map(([k, v]) => `<button type="button" data-layout="${k}" class="${o.layout === k ? 'is-on' : ''}" title="${v[2]}"><i class="lpb-lay" style="grid-template-columns:${v[0]}">${'<i></i>'.repeat(v[1])}</i></button>`).join('')}</span></label>
                    <label>Content width<select data-sec="width"><option value="boxed" ${o.width !== 'full' ? 'selected' : ''}>Boxed (centred)</option><option value="full" ${o.width === 'full' ? 'selected' : ''}>Full width</option></select></label>
                    <label>Background preset<select data-sec="bg">${Object.entries(BG).map(([k, l]) => `<option value="${k}" ${o.bg === k ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
                    <p class="lpb-hint">Image, gradient and overlay backgrounds are in the Style tab.</p>`;
                if (B.isFull(o)) { const w = o.cols[0].widgets[0]; out += `<button type="button" class="btn btn--sm btn--ghost" data-crumb="${w.id}">Edit the ${esc(W[w.type].label.toLowerCase())} content →</button>`; }
            } else if (f.kind === 'col') {
                out += `<p class="lpb-hint">Drag widgets into this column, or pick one in the Add tab. Background, padding and alignment are in the Style tab.</p>
                    <div class="lpb-block-actions"><button type="button" class="btn btn--sm btn--ghost" data-col-act="add">+ Add a column</button>${f.sec.cols.length > 1 ? '<button type="button" class="btn btn--sm btn--ghost lpb-danger" data-col-act="del">Delete column</button>' : ''}</div>`;
            } else {
                const d = W[o.type];
                if (d.variants) out += `<label>Layout<select data-wv>${Object.entries(d.variants).map(([k, l]) => `<option value="${k}" ${(o.v || Object.keys(d.variants)[0]) === k ? 'selected' : ''}>${l}</option>`).join('')}</select></label>`;
                out += `<p class="lpb-hint">Click text on the page to type over it.</p>`;
                for (const fl of d.fields) out += fl.type === 'list' ? listHtml(fl, o) : fieldHtml(fl, o.p, `${o.id}|`);
                if (o.type === 'contact' || o.type === 'form') out += formPanel();
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
    editPanel.addEventListener('click', e => {
        const f = sel && find(sel.id);
        const crumb = e.target.closest('[data-crumb]'); if (crumb) return select(crumb.dataset.crumb, '_');
        const sval = e.target.closest('[data-sty-val]');
        if (sval && f) { const [k, v] = sval.dataset.styVal.split('|'); const st = styleObj(f.obj.id, f.kind === 'widget' ? sel.target || '_' : '_'); if (v === '') delete st[DEV()][k]; else st[DEV()][k] = v; commit(); refreshCss(); drawEdit(); return; }
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
            if (act === 'del') list.splice(n, 1);
            if (act === 'up' && n > 0) [list[n - 1], list[n]] = [list[n], list[n - 1]];
            commit(); refreshEl(f.obj.id); return drawEdit();
        }
        const pe = e.target.closest('[data-panel-ed]'); if (pe && f) return barAction(pe.dataset.panelEd, f.obj.id);
        if (e.target.closest('[data-activate-form]')) activateForm();
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
            const colsHtml = full ? '' : `<ul>${s.cols.map((c, ci) => `<li><button type="button" data-nav="${c.id}" class="${sel?.id === c.id ? 'is-sel' : ''}">▤ Column ${ci + 1}</button>${c.widgets.length ? `<ul>${c.widgets.map(w => `<li><button type="button" data-nav="${w.id}" class="${sel?.id === w.id ? 'is-sel' : ''}">${esc(W[w.type].label)}</button></li>`).join('')}</ul>` : ''}</li>`).join('')}</ul>`;
            return `<li><div class="lpb-nav-row"><button type="button" data-nav="${full ? s.cols[0].widgets[0].id : s.id}" class="${on ? 'is-sel' : ''}">▦ ${esc(label)}</button>
                <button type="button" data-nav-mv="${i}|-1" ${i === 0 ? 'disabled' : ''} title="Move up">↑</button><button type="button" data-nav-mv="${i}|1" ${i === secs.length - 1 ? 'disabled' : ''} title="Move down">↓</button></div>${colsHtml}</li>`;
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
        if (e.target.closest('[data-page-del]')) {
            sweet({ icon: 'warning', title: `Delete the page “${page().title}”?`, text: 'All its sections go too. You can bring it back with Undo.', confirm: 'Yes, delete page', danger: true })
                .then(ok => { if (ok) { P.pages.splice(pageIdx, 1); pageIdx = 0; sel = null; commit(); renderAll(); toast('Page deleted'); } });
        }
    });
    const drawPage = () => {
        const pg = page(), pop = pg.popup ||= B.defaultsPopup();
        const keep = {}; $$('[data-page-panel] .lpb-acc').forEach(d => (keep[d.querySelector('summary').textContent] = d.open));
        $('[data-page-panel]').innerHTML = `
            <details class="lpb-acc" open><summary>Navigator</summary><ol class="lpb-nav" data-navigator></ol></details>
            <details class="lpb-acc"><summary>Page settings</summary>
            <label>Page name (in the menu)<input data-pg="title" value="${esc(pg.title)}" maxlength="40"></label>
            ${pg.slug === 'index' ? '<p class="lpb-hint">This is your home page.</p>' : `<label>Page address<span class="lpb-slug"><i>/</i><input data-pg="slug" value="${esc(pg.slug)}" maxlength="40"></span></label>`}
            <label class="lpb-check"><input type="checkbox" data-pg="menu" ${pg.menu ? 'checked' : ''}> Show in the menu</label>
            <label>Google title<input data-pg="seo.title" value="${esc(pg.seo.title)}" maxlength="70" placeholder="Leave empty to make one automatically"></label>
            <label>Google description<textarea data-pg="seo.description" rows="3" maxlength="170" placeholder="Leave empty to use the hero text">${esc(pg.seo.description)}</textarea></label>
            ${pg.slug !== 'index' ? '<button type="button" class="btn btn--sm btn--ghost lpb-danger" data-page-del>Delete this page</button>' : ''}</details>
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
    ['input', 'change'].forEach(ev => pagePanel.addEventListener(ev, e => {
        const el = e.target, pg = page();
        if (el.dataset.linkTo || el.dataset.linkVal) {
            const l = { ...(pg.popup.link || { to: 'contact' }) };
            if (el.dataset.linkTo) { l.to = el.value; l.val = el.value === 'page' ? P.pages[0].slug : ''; } else l.val = el.value;
            pg.popup.link = l; commitSoon(); if (el.dataset.linkTo) drawPage(); return;
        }
        const k = el.dataset.pg; if (!k) return;
        let v = el.type === 'checkbox' ? el.checked : el.value;
        if (k === 'slug') { v = slugify(v) || pg.slug; if (P.pages.some(p => p !== pg && p.slug === v) || v === 'index') return; }
        setPath(pg, k, v); commitSoon();
        if (k === 'title' || k === 'menu') { refreshChrome(); drawPages(); }
        if (k === 'popup.trigger' && e.type === 'change') drawPage();
    }));

    /* =========================================================== site panel (global settings) */
    const syncGlobals = () => { const c = P.site.colors; B.GLOBAL.forEach(k => root.style.setProperty('--g-' + k, hex(c[k]))); };
    const drawSite = () => {
        const s = P.site, c = s.colors, fo = s.fonts;
        const keep = {}; $$('[data-site-panel] .lpb-acc').forEach(d => (keep[d.querySelector('summary').textContent] = d.open));
        $('[data-site-panel]').innerHTML = `
            <details class="lpb-acc" open><summary>Global colours</summary>
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
            <details class="lpb-acc"><summary>Header</summary>
            <label class="lpb-check"><input type="checkbox" data-site="sticky" ${s.sticky !== false ? 'checked' : ''}> Sticky header (stays on top when scrolling)</label>
            <label>Header button text<input data-site="navBtn" value="${esc(s.navBtn || '')}" placeholder="Leave empty to hide"></label>
            <label>Header button link${linkWidget('site|navLink', s.navLink)}</label>
            <label class="lpb-check"><input type="checkbox" data-site="announce.on" ${s.announce?.on ? 'checked' : ''}> Announcement bar above the header</label>
            ${s.announce?.on ? `<label>Bar text<input data-site="announce.text" value="${esc(s.announce.text || '')}"></label><label>Link text (optional)<input data-site="announce.linkText" value="${esc(s.announce.linkText || '')}"></label><label>Link${linkWidget('site|announce.link', s.announce.link)}</label>` : ''}</details>
            <details class="lpb-acc"><summary>Footer &amp; social</summary>
            <label>Footer text<textarea data-site="footer" rows="2">${esc(s.footer || '')}</textarea></label>
            ${Object.entries(B.SOCIAL).map(([k, v]) => `<label>${v[0]}<input data-site="social.${k}" value="${esc(s.social?.[k] || '')}" placeholder="https://…"></label>`).join('')}
            <label class="lpb-check"><input type="checkbox" data-site="backTop" ${s.backTop ? 'checked' : ''}> Back-to-top button</label></details>
            <details class="lpb-acc"><summary>Custom CSS</summary>
            <textarea data-site="css" rows="8" class="lpb-code" placeholder=".hero-copy h1 { letter-spacing: -2px; }">${esc(s.css || '')}</textarea><p class="lpb-hint">Added to every page. Scripts are not allowed.</p></details>
            <details class="lpb-acc"><summary>Start over</summary><p class="lpb-hint">Replace everything with an example (you can undo).</p>
            <div class="lpb-chips">${Object.entries(B.EXAMPLES).map(([k, v]) => `<button type="button" class="lpb-chip" data-example="${k}">${esc(v[0])}</button>`).join('')}</div></details>`;
        $$('[data-site-panel] .lpb-acc').forEach(d => { const k = d.querySelector('summary').textContent; if (k in keep) d.open = keep[k]; });
        syncGlobals();
    };
    const sitePanel = $('[data-tab-panel=site]');
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
        if ((k === 'announce.on') && e.type === 'change') drawSite();
    }));
    sitePanel.addEventListener('click', e => {
        const exb = e.target.closest('[data-example]');
        if (exb) { const keep = { formAction: P.site.formAction, publishSlug: P.site.publishSlug }; P = B.newProject(exb.dataset.example); Object.assign(P.site, keep); pageIdx = 0; sel = null; commit(); renderAll(); toast('Example loaded — press Undo to go back'); return; }
        const pick = e.target.closest('[data-img-pick]'); if (pick) { picking = pick.dataset.imgPick; return $('[data-pick-image]').click(); }
        if (e.target.closest('[data-img-clear]')) { P.site.logo = ''; commit(); refreshChrome(); drawSite(); }
    });

    /* =========================================================== tabs, pages, toolbar */
    const showTab = name => {
        $$('[data-tab]').forEach(b => b.setAttribute('aria-selected', b.dataset.tab === name ? 'true' : 'false'));
        $$('[data-tab-panel]').forEach(p => p.classList.toggle('is-active', p.dataset.tabPanel === name));
        if (name === 'page') drawPage();
        if (name === 'site') drawSite();
        if (name === 'edit') drawEdit();
    };
    $$('[data-tab]').forEach(b => b.addEventListener('click', () => showTab(b.dataset.tab)));
    const drawPages = () => { $('[data-pages]').innerHTML = P.pages.map((p, i) => `<option value="${i}" ${i === pageIdx ? 'selected' : ''}>${esc(p.title)}${p.slug === 'index' ? ' (home)' : ''}</option>`).join(''); };
    const switchPage = i => { if (i < 0 || !P.pages[i]) return; pageIdx = i; sel = null; drawPages(); renderCanvas(); drawEdit(); drawPage(); };
    $('[data-pages]').addEventListener('change', e => switchPage(+e.target.value));
    const addPage = async (preset, tpl) => {
        if (P.pages.length >= 10) return sweet({ icon: 'info', title: 'You have 10 pages', text: 'That is the most one site can have. Delete a page to add another.', cancel: false, confirm: 'Got it' });
        const title = await sweet({ icon: 'question', title: 'Add a new page', text: 'What should the page be called? It appears in your menu.', input: { value: preset || 'About', placeholder: 'About, Services, Contact…', max: 40 }, confirm: 'Add page' });
        if (!title) return;
        const pg = B.newPage(title.slice(0, 40), P.pages.length, tpl);
        while (P.pages.some(p => p.slug === pg.slug)) pg.slug += '-2';
        P.pages.push(pg); commit(); switchPage(P.pages.length - 1); showTab('page');
        track('builder_add_page', { template: tpl || '' });
    };
    $('[data-act=add-page]').addEventListener('click', () => addPage());

    const fit = () => { const w = { desktop: 1280, tablet: 820 }[device]; screen.style.setProperty('--s', w ? Math.min(1, screen.clientWidth / w).toFixed(4) : 1); screen.style.setProperty('--w', (w || 390) + 'px'); };
    const setDevice = d => {
        device = d; screen.dataset.device = d;
        $$('[data-device]').forEach(b => b.setAttribute('aria-pressed', b.dataset.device === d ? 'true' : 'false'));
        fit(); if ($('[data-tab-panel=edit]').classList.contains('is-active') && editTab === 'style') drawEdit();
    };
    $$('[data-device]').forEach(b => b.addEventListener('click', () => setDevice(b.dataset.device)));
    new ResizeObserver(fit).observe(screen);
    $('[data-act=undo]').addEventListener('click', doUndo);
    $('[data-act=redo]').addEventListener('click', doRedo);
    $('[data-act=preview]').addEventListener('click', () => {
        const w = window.open('', '_blank');
        if (w) { w.document.write(B.render(P, page(), 'download', { runtime: RUNTIME })); w.document.close(); }
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

    // Save / open project
    const save = (blob, name) => { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.append(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 3000); };
    const baseName = () => slugify(P.site.name) || 'my-website';
    $('[data-act=save]').addEventListener('click', () => { save(new Blob([JSON.stringify(P)], { type: 'application/json' }), baseName() + '.owlvix.json'); toast('Project saved — open it any time to keep editing'); track('builder_save'); });
    $('[data-act=open]').addEventListener('click', () => $('[data-open-file]').click());
    $('[data-open-file]').addEventListener('change', async e => {
        const f = e.target.files[0]; e.target.value = ''; if (!f) return;
        try {
            const data = B.migrate(JSON.parse(await f.text()));
            if (!Array.isArray(data.pages) || !data.pages.length || !data.site) throw new Error();
            undo.push(JSON.stringify(P)); P = data; last = JSON.stringify(P); pageIdx = 0; sel = null; persist(); renderAll(); syncHistory();
            toast('Project opened');
        } catch { toast('That file is not an Owlvix project.'); }
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
    const exportHtml = (pg, mode) => B.render(P, pg, mode, { runtime: RUNTIME, siteBase: SITE_BASE });
    $('[data-act=download]').addEventListener('click', () => {
        if (P.pages.length === 1) save(new Blob([exportHtml(P.pages[0], 'download')], { type: 'text/html' }), baseName() + '.html');
        else save(zip(P.pages.map(p => ({ name: (p.slug === 'index' ? 'index' : p.slug) + '.html', text: exportHtml(p, 'download') }))), baseName() + '.zip');
        const after = document.querySelector('[data-lpb-after]');
        after.querySelector('[data-after-zip]').hidden = P.pages.length === 1;
        after.hidden = false;
        if (root.classList.contains('is-full')) toast('Downloaded'); else after.scrollIntoView({ behavior: 'smooth', block: 'center' });
        track('builder_download', { pages: P.pages.length });
    });

    /* =========================================================== account, form, publish (server) */
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let token = ''; try { token = localStorage.getItem('owlvix-builder-token') || ''; } catch {}
    let account = null;
    const api = async (method, url, body) => {
        const res = await fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Builder-Token': token }, body: body ? JSON.stringify(body) : undefined });
        const json = await res.json().catch(() => ({}));
        if (res.status === 401) { token = ''; account = null; try { localStorage.removeItem('owlvix-builder-token'); } catch {} }
        if (!res.ok || json.ok === false) throw new Error(json.error || json.message || 'Something went wrong. Please try again.');
        return json;
    };
    const dlg = document.querySelector('[data-builder-dialog]'), dlgBody = dlg.querySelector('[data-dialog-body]');
    let afterSignIn = null;
    const openDialog = html => { dlgBody.innerHTML = html; if (!dlg.open) dlg.showModal(); dlgBody.querySelector('input:not([type=hidden])')?.focus(); };
    dlg.addEventListener('click', e => { if (e.target === dlg || e.target.closest('[data-dialog-close]')) dlg.close(); });
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
                await loadAccount(); track('builder_sign_in');
                afterSignIn?.();
            } else if (f.matches('[data-publish]')) await publish(f.slug.value);
        } catch (ex) { err.textContent = ex.message; err.hidden = false; }
        finally { btnEl.disabled = false; }
    });
    const formPanel = () => P.site.formAction
        ? `<div class="lpb-form-ok">✅ Contact form is active. Messages are emailed to ${esc(account?.email || 'your sign-in email')}.</div>`
        : `<div class="lpb-form-off"><b>📨 Working contact form</b><p>Visitors' messages are emailed to you — it works wherever you put the page. Free: sign in with your email to switch it on.</p><button type="button" class="btn btn--sm btn--glow" data-activate-form>Activate contact form</button></div>`;
    const activateForm = () => needAccount(async () => {
        try {
            const r = await api('POST', '/builder/forms', { site_name: P.site.name || 'My website' });
            P.site.formAction = r.form.action; commit(); if (dlg.open) dlg.close();
            refreshMain(); drawEdit(); toast('Contact form activated'); track('builder_form_activated');
        } catch (ex) { toast(ex.message); }
    });
    const publishDialog = () => {
        const mine = account?.sites || [], slug = P.site.publishSlug || slugify(P.site.name) || 'my-site';
        openDialog(`<h3>Publish your site</h3><p>Your ${P.pages.length > 1 ? P.pages.length + ' pages go' : 'page goes'} live at a free web address you can share. Signed in as <b>${esc(account?.email || '')}</b>.</p>
            <form data-publish><label>Web address<span class="lpb-slug lpb-slug--url"><i>${esc(SITE_HOST)}</i><input name="slug" value="${esc(slug)}" required minlength="3" maxlength="40"></span></label>
            <p class="lpb-hint">Lowercase letters, numbers and dashes. Published pages are hidden from Google search — for your own domain and Google ranking, download the page or let us set it up.</p><p class="lpb-err" hidden></p>
            <button class="btn btn--glow btn--block" type="submit">${mine.some(s => s.slug === slug) ? 'Update my published site' : 'Publish now'}</button></form>
            ${mine.length ? `<h4>Your published sites</h4><ul class="lpb-sites">${mine.map(s => `<li><a href="${esc(s.url)}" target="_blank" rel="noopener">${esc(s.url.replace(/^https?:\/\//, ''))}</a> <small>${s.status === 'live' ? 'Live' : 'Taken down'}</small>${s.status === 'live' ? ` <button type="button" class="lpb-link-btn" data-unpublish="${esc(s.slug)}">Unpublish</button>` : ''}</li>`).join('')}</ul>` : ''}`);
        dlgBody.querySelector('[name=slug]').addEventListener('input', e => (e.target.value = e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '')));
    };
    const publish = async slugIn => {
        const slug = slugify(slugIn);
        if (slug.length < 3) throw new Error('Please use at least 3 letters or numbers.');
        P.site.publishSlug = slug;
        const pages = P.pages.map(p => ({ slug: p.slug, title: p.title, html: exportHtml(p, 'publish') }));
        const r = await api('POST', '/builder/publish', { slug, name: P.site.name || 'My website', pages });
        commit(); await loadAccount().catch(() => {});
        openDialog(`<h3>🎉 Your site is live</h3><p><a class="lpb-live" href="${esc(r.url)}" target="_blank" rel="noopener">${esc(r.url.replace(/^https?:\/\//, ''))}</a></p>
            <p>Share the link anywhere. Changed something? Publish again to update it.</p><p>Want it on your own domain, found on Google, with analytics and speed tuning? We can set that up.</p>
            <div class="lpb-dialog-actions"><a class="btn btn--glow" href="${esc(r.url)}" target="_blank" rel="noopener">Open my site</a><button type="button" class="btn btn--ghost" data-lpb-quote-open>Put it on my domain</button></div>`);
        track('builder_publish', { pages: pages.length });
    };
    $('[data-act=publish]').addEventListener('click', () => needAccount(publishDialog));
    dlgBody.addEventListener('click', async e => {
        const un = e.target.closest('[data-unpublish]');
        if (un && await sweet({ icon: 'warning', title: 'Take this site offline?', text: `${SITE_HOST}${un.dataset.unpublish} will stop working. You can publish it again later.`, confirm: 'Yes, unpublish', danger: true })) {
            try { await api('POST', '/builder/unpublish', { slug: un.dataset.unpublish }); await loadAccount(); publishDialog(); toast('Site unpublished'); } catch (ex) { toast(ex.message); }
        }
        if (e.target.closest('[data-lpb-quote-open]')) { dlg.close(); setFull(false); document.querySelector('.speed-cta [data-lpb-quote]')?.click(); }
    });
    document.querySelectorAll('[data-lpb-quote]').forEach(btnEl => btnEl.addEventListener('click', () => {
        const modal = document.getElementById('quote-modal');
        const service = modal?.querySelector('select[name=service]'); if (service) service.value = 'Landing page';
        const ta = modal?.querySelector('textarea[name=message]');
        const text = `I built a ${P.pages.length > 1 ? P.pages.length + '-page website' : 'landing page'} for ${P.site.name || 'my business'} with your free builder${P.site.publishSlug ? ` (${SITE_BASE}${P.site.publishSlug})` : ''}. I'd like it live on my own domain.`;
        if (ta?.quill) ta.quill.setText(text); else if (ta) ta.value = text;
        track('builder_quote');
    }));

    /* =========================================================== start */
    const renderAll = () => { drawPages(); renderCanvas(); drawAdd(); drawEdit(); drawSite(); drawPage(); };
    renderAll(); syncHistory(); setDevice(window.innerWidth < 760 ? 'mobile' : 'desktop'); showTab('add');
    persist();
    window.__owlBuilder = { get P() { return P; }, select, find }; // for checking things from the browser console
})();
