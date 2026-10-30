/* Owlvix free form builder (/form-builder). The form is designed here, saved in this browser, and published to the
   server (POST /builder/surveys) under the same email sign-in as the page builder. Published forms live at
   /form/{id} (BuilderPublicController::survey); answers are read back with /builder/surveys/{id}/responses. */
(() => {
    const root = document.querySelector('[data-fb]');
    if (!root) return;
    const $ = (s, el = root) => el.querySelector(s), $$ = (s, el = root) => [...el.querySelectorAll(s)];
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const uid = () => (Math.random().toString(36).slice(2) + '00000000').slice(0, 8); // always 8 characters (the server needs 3–12)
    const track = (e, d = {}) => window.track?.(e, d);
    const STORE = 'owlvix-form-builder';
    const TYPES = { text: ['Short answer', 'Aa'], textarea: ['Paragraph', '¶'], email: ['Email', '@'], phone: ['Phone', '☎'], number: ['Number', '#'], date: ['Date', '📅'],
        select: ['Dropdown', '▾'], radio: ['Multiple choice', '◉'], checkbox: ['Checkboxes', '☑'], rating: ['Rating 1–5', '★'], heading: ['Section title', 'H'] };
    const CHOICE = ['select', 'radio', 'checkbox'];
    const f = (type, label, o = {}) => ({ id: uid(), type, label, help: '', required: false, placeholder: '', ...(CHOICE.includes(type) ? { options: ['Option 1', 'Option 2'] } : {}), ...o });
    const TEMPLATES = {
        contact: ['Contact form', () => ({ title: 'Contact us', intro: 'Send us a message and we will reply within one working day.', button: 'Send message', thanks: 'Thanks! We will get back to you soon.',
            fields: [f('text', 'Your name', { required: true }), f('email', 'Email', { required: true }), f('phone', 'Phone'), f('textarea', 'Message', { required: true })] })],
        feedback: ['Customer feedback', () => ({ title: 'How did we do?', intro: 'Your feedback helps us get better. It takes one minute.', button: 'Send feedback', thanks: 'Thank you for your feedback! 💚',
            fields: [f('rating', 'How happy are you with us overall?', { required: true }), f('radio', 'How did you hear about us?', { options: ['Google', 'Social media', 'A friend', 'Other'] }), f('textarea', 'What could we do better?'), f('radio', 'Would you recommend us to a friend?', { options: ['Yes', 'Maybe', 'No'] }), f('email', 'Email (if you would like a reply)')] })],
        rsvp: ['Event RSVP', () => ({ title: 'You’re invited! Will you join us?', intro: 'Saturday 14 June · 7 pm\n12 Main Street, Your City', button: 'Send my RSVP', thanks: 'Thanks for letting us know — see you there! 🎉',
            fields: [f('text', 'Your name', { required: true }), f('radio', 'Will you come?', { required: true, options: ['Yes, I’ll be there', 'Sorry, I can’t make it'] }), f('number', 'How many guests (including you)?'), f('select', 'Meal choice', { options: ['Chicken', 'Fish', 'Vegetarian', 'Vegan'] }), f('textarea', 'Any allergies or a message for us?')] })],
        job: ['Job application', () => ({ title: 'Apply to join our team', intro: 'Tell us a little about you. We read every application.', button: 'Send application', thanks: 'Thanks for applying! We will be in touch within a week.',
            fields: [f('text', 'Full name', { required: true }), f('email', 'Email', { required: true }), f('phone', 'Phone', { required: true }), f('select', 'Which role are you applying for?', { required: true, options: ['Sales assistant', 'Customer support', 'Driver'] }), f('radio', 'When can you start?', { options: ['Right away', 'In 2 weeks', 'In a month or more'] }), f('text', 'Link to your CV or LinkedIn', { placeholder: 'https://…' }), f('textarea', 'Why do you want to work with us?')] })],
        quote: ['Quote request', () => ({ title: 'Get a free quote', intro: 'Tell us about your project and we will send a price within 24 hours.', button: 'Get my quote', thanks: 'Thanks! Your quote is on its way.',
            fields: [f('text', 'Your name', { required: true }), f('email', 'Email', { required: true }), f('phone', 'Phone'), f('checkbox', 'What do you need?', { required: true, options: ['Design', 'Development', 'Marketing', 'Not sure yet'] }), f('select', 'Budget', { options: ['Under $1,000', '$1,000 – $5,000', '$5,000 – $20,000', 'Over $20,000'] }), f('date', 'When do you need it by?'), f('textarea', 'Project details')] })],
        order: ['Order form', () => ({ title: 'Place your order', intro: 'Order before 2 pm for same-day delivery. We confirm every order by phone.', button: 'Place order', thanks: 'Thanks! We will call you to confirm your order.',
            fields: [f('text', 'Name', { required: true }), f('phone', 'Phone', { required: true }), f('text', 'Delivery address', { required: true }), f('checkbox', 'What would you like?', { required: true, options: ['Small bouquet — $25', 'Large bouquet — $45', 'Plant in a pot — $30'] }), f('date', 'Delivery date'), f('textarea', 'Card message (optional)')] })],
        blank: ['Blank form', () => ({ title: 'Untitled form', intro: '', button: 'Send', thanks: 'Thanks! Your answers have been sent.', fields: [f('text', 'Your name')] })],
    };
    const fresh = key => ({ id: null, schema: { color: '#16a34a', ...TEMPLATES[key][1]() } });

    let D;
    try { D = JSON.parse(localStorage.getItem(STORE) || 'null'); } catch {}
    if (!D?.schema?.fields) D = fresh('contact');
    let openId = null; // field being edited
    const save = () => { try { localStorage.setItem(STORE, JSON.stringify(D)); } catch {} };
    const S = () => D.schema;

    /* ------------------------------------------------ preview (looks like /form/{id}, not interactive) */
    const preview = () => {
        const s = S(), star = q => (q.required ? '<span class="fbp-req">*</span>' : '');
        const body = s.fields.map(q => {
            const help = q.help ? `<p class="fbp-help">${esc(q.help)}</p>` : '';
            if (q.type === 'heading') return `<h3 class="fbp-h">${esc(q.label)}</h3>${help}`;
            let input;
            if (q.type === 'textarea') input = `<div class="fbp-in fbp-ta">${esc(q.placeholder)}</div>`;
            else if (q.type === 'select') input = `<div class="fbp-in fbp-sel">${esc(q.placeholder || 'Choose…')}</div>`;
            else if (q.type === 'radio' || q.type === 'checkbox') input = (q.options || []).map(o => `<div class="fbp-opt"><i class="fbp-${q.type}"></i>${esc(o)}</div>`).join('');
            else if (q.type === 'rating') input = `<div class="fbp-rate">${[1, 2, 3, 4, 5].map(n => `<span>${n}</span>`).join('')}</div>`;
            else input = `<div class="fbp-in">${esc(q.placeholder || { date: 'dd / mm / yyyy' }[q.type] || '')}</div>`;
            return `<div class="fbp-q${q.id === openId ? ' is-on' : ''}" data-goto="${q.id}"><b>${esc(q.label)}${star(q)}</b>${help}${input}</div>`;
        }).join('');
        $('[data-preview]').style.setProperty('--fc', /^#[0-9a-f]{6}$/i.test(s.color) ? s.color : '#16a34a');
        $('[data-preview]').innerHTML = `<h2>${esc(s.title)}</h2>${s.intro ? `<p class="fbp-intro">${esc(s.intro)}</p>` : ''}${body}<span class="fbp-btn">${esc(s.button || 'Send')}</span>`;
    };

    /* ------------------------------------------------ editor */
    const fieldCard = (q, i) => {
        const open = q.id === openId, n = S().fields.length;
        const head = `<div class="fb-q__head" data-toggle="${q.id}"><span class="fb-q__icon" aria-hidden="true">${esc(TYPES[q.type][1])}</span><span class="fb-q__label">${esc(q.label) || '<i>No question yet</i>'}${q.required ? ' <em>*</em>' : ''}</span>
            <span class="fb-q__tools"><button type="button" data-move="-1" data-i="${i}" title="Move up" ${i ? '' : 'disabled'}>↑</button><button type="button" data-move="1" data-i="${i}" title="Move down" ${i < n - 1 ? '' : 'disabled'}>↓</button><button type="button" data-dup="${i}" title="Duplicate">⧉</button><button type="button" data-del="${i}" title="Delete" class="fb-del">✕</button></span></div>`;
        if (!open) return `<div class="fb-q">${head}</div>`;
        return `<div class="fb-q is-open">${head}<div class="fb-q__body">
            <div class="fb-2"><label>Question${q.type === 'heading' ? ' / title' : ''}<input data-q="label" data-i="${i}" maxlength="200" value="${esc(q.label)}"></label>
            <label>Type<select data-q="type" data-i="${i}">${Object.entries(TYPES).map(([k, v]) => `<option value="${k}" ${q.type === k ? 'selected' : ''}>${v[0]}</option>`).join('')}</select></label></div>
            <label>Help text (optional)<input data-q="help" data-i="${i}" maxlength="300" value="${esc(q.help)}"></label>
            ${CHOICE.includes(q.type) ? `<label>Choices (one per line)<textarea data-q="options" data-i="${i}" rows="${Math.min(8, (q.options || []).length + 1)}">${esc((q.options || []).join('\n'))}</textarea></label>` : ''}
            ${['text', 'textarea', 'email', 'phone', 'number', 'select'].includes(q.type) ? `<label>Placeholder (optional)<input data-q="placeholder" data-i="${i}" maxlength="100" value="${esc(q.placeholder)}"></label>` : ''}
            ${q.type !== 'heading' ? `<label class="fb-check"><input type="checkbox" data-q="required" data-i="${i}" ${q.required ? 'checked' : ''}> Required</label>` : ''}
        </div></div>`;
    };
    const drawFields = () => {
        $('[data-fields]').innerHTML = S().fields.map(fieldCard).join('') || '<p class="fb-hint">No questions yet — add one below.</p>';
        preview(); save();
    };
    const drawSettings = () => {
        const s = S();
        $$('[data-set]').forEach(el => (el.value = s[el.dataset.set] ?? ''));
    };
    $('[data-add-types]').innerHTML = Object.entries(TYPES).map(([k, v]) => `<button type="button" data-add="${k}"><span aria-hidden="true">${esc(v[1])}</span>${v[0]}</button>`).join('');
    $('[data-templates]').innerHTML = Object.entries(TEMPLATES).map(([k, v]) => `<button type="button" class="fb-chip" data-tpl="${k}">${esc(v[0])}</button>`).join('');

    root.addEventListener('input', e => {
        const el = e.target;
        if (el.dataset.set) { S()[el.dataset.set] = el.value; preview(); save(); markDirty(); return; }
        if (!el.dataset.q) return;
        const q = S().fields[+el.dataset.i], k = el.dataset.q;
        if (k === 'options') q.options = el.value.split('\n').map(s => s.trim()).filter(Boolean).slice(0, 20);
        else if (k === 'required') q.required = el.checked;
        else if (k === 'type') { q.type = el.value; if (CHOICE.includes(q.type) && !q.options?.length) q.options = ['Option 1', 'Option 2']; if (q.type === 'heading') q.required = false; return drawFields(), markDirty(); }
        else q[k] = el.value;
        if (k === 'label' || k === 'required') { const lbl = el.closest('.fb-q').querySelector('.fb-q__label'); lbl.innerHTML = (esc(q.label) || '<i>No question yet</i>') + (q.required ? ' <em>*</em>' : ''); }
        preview(); save(); markDirty();
    });
    root.addEventListener('click', async e => {
        const t = e.target.closest('button,[data-toggle],[data-goto]'); if (!t) return;
        const F = S().fields;
        if (t.dataset.add) {
            if (F.length >= 30) return note('A form can have up to 30 questions.');
            const q = f(t.dataset.add, t.dataset.add === 'heading' ? 'New section' : 'New question'); F.push(q); openId = q.id; drawFields(); markDirty();
            $(`[data-q="label"][data-i="${F.length - 1}"]`)?.select(); track('form_builder_add', { type: t.dataset.add });
        } else if (t.dataset.move) { const i = +t.dataset.i, j = i + +t.dataset.move; [F[i], F[j]] = [F[j], F[i]]; drawFields(); markDirty(); }
        else if (t.dataset.dup) { if (F.length >= 30) return; const c = { ...structuredClone(F[+t.dataset.dup]), id: uid() }; F.splice(+t.dataset.dup + 1, 0, c); openId = c.id; drawFields(); markDirty(); }
        else if (t.dataset.del) { F.splice(+t.dataset.del, 1); drawFields(); markDirty(); }
        else if (t.dataset.toggle) { if (e.target.closest('.fb-q__tools')) return; openId = openId === t.dataset.toggle ? null : t.dataset.toggle; drawFields(); }
        else if (t.dataset.goto) { openId = t.dataset.goto; drawFields(); $('[data-fields] .is-open')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
        else if (t.dataset.tpl) {
            if (!confirm('Replace your questions with this template?')) return;
            const id = D.id, color = S().color; D = fresh(t.dataset.tpl); D.id = id; D.schema.color = color; openId = null; drawSettings(); drawFields(); markDirty(); track('form_builder_template', { tpl: t.dataset.tpl });
        } else if (t.dataset.tab) showTab(t.dataset.tab);
    });

    /* ------------------------------------------------ tabs */
    const showTab = name => {
        $$('[data-tab]').forEach(b => b.setAttribute('aria-selected', b.dataset.tab === name ? 'true' : 'false'));
        $$('[data-panel]').forEach(p => (p.hidden = p.dataset.panel !== name));
        if (name === 'share') drawShare();
        if (name === 'responses') drawResponses();
    };

    /* ------------------------------------------------ account (same sign-in as the page builder) */
    let csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let token = ''; try { token = localStorage.getItem('owlvix-builder-token') || ''; } catch {}
    let forms = null; // your published forms
    const api = async (method, url, body, retried) => {
        const res = await fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Builder-Token': token }, body: body ? JSON.stringify(body) : undefined });
        if (res.status === 419 && !retried) { try { const h = await (await fetch(location.pathname)).text(); csrf = (h.match(/name="csrf-token" content="([^"]+)"/) || [])[1] || csrf; } catch {} return api(method, url, body, true); }
        const json = await res.json().catch(() => ({}));
        if (res.status === 401) { token = ''; forms = null; try { localStorage.removeItem('owlvix-builder-token'); } catch {} }
        if (!res.ok || json.ok === false) { const err = new Error(json.error || json.message || 'Something went wrong. Please try again.'); err.status = res.status; throw err; }
        return json;
    };
    const dlg = $('[data-dialog]'), dlgBody = $('[data-dialog-body]');
    let afterSignIn = null;
    const openDialog = html => { dlgBody.innerHTML = html; if (!dlg.open) dlg.showModal(); dlgBody.querySelector('input:not([type=hidden])')?.focus(); };
    dlg.addEventListener('click', e => { if (e.target === dlg || e.target.closest('[data-dialog-close]')) dlg.close(); });
    const signIn = then => {
        afterSignIn = then;
        openDialog(`<h3>Sign in with your email</h3><p>We'll email you a 6-digit code — no password needed. Answers to your forms are sent to this email.</p>
            <form data-signin><label>Email<input type="email" name="email" required autocomplete="email" placeholder="you@example.com"></label><p class="lpb-err" hidden></p><button class="btn btn--glow btn--block" type="submit">Send my code</button></form>`);
    };
    dlg.addEventListener('submit', async e => {
        e.preventDefault();
        const fm = e.target, err = fm.querySelector('.lpb-err'), b = fm.querySelector('button[type=submit]');
        err.hidden = true; b.disabled = true;
        try {
            if (fm.matches('[data-signin]')) {
                const email = fm.email.value.trim();
                await api('POST', '/builder/account/code', { email });
                openDialog(`<h3>Check your email</h3><p>We sent a 6-digit code to <b>${esc(email)}</b>. It's valid for 15 minutes.</p>
                    <form data-verify><input type="hidden" name="email" value="${esc(email)}"><label>Code<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required placeholder="123456"></label><p class="lpb-err" hidden></p><button class="btn btn--glow btn--block" type="submit">Sign in</button></form>`);
            } else {
                const r = await api('POST', '/builder/account/verify', { email: fm.email.value, code: fm.code.value.trim() });
                token = r.token; try { localStorage.setItem('owlvix-builder-token', token); } catch {}
                dlg.close(); track('builder_sign_in', { from: 'form_builder' }); await loadForms(); afterSignIn?.();
            }
        } catch (ex) { err.textContent = ex.message; err.hidden = false; }
        finally { b.disabled = false; }
    });
    const loadForms = async () => { forms = (await api('GET', '/builder/surveys')).forms; drawMine(); return forms; };
    const drawMine = () => {
        const sel = $('[data-mine]');
        sel.hidden = !forms?.length;
        sel.innerHTML = `<option value="">My forms (${forms?.length || 0})…</option>` + (forms || []).map(x => `<option value="${x.id}" ${x.id === D.id ? 'selected' : ''}>${esc(x.title)} · ${x.responses} answer${x.responses === 1 ? '' : 's'}</option>`).join('');
    };

    /* ------------------------------------------------ publish */
    const note = msg => { const n = $('[data-note]'); n.textContent = msg; n.hidden = false; clearTimeout(note.t); note.t = setTimeout(() => (n.hidden = true), 5000); };
    const markDirty = () => { D.dirty = true; save(); syncPublish(); };
    const syncPublish = () => {
        if (S().managed) { // an RSVP form made by an event page: its questions are changed on that page
            const b = $('[data-publish]'); b.textContent = 'Edited on your page'; b.disabled = true;
            $('[data-status]').textContent = 'This RSVP form belongs to your event page — change its questions there and publish the page. Answers are in the Responses tab.';
            return;
        }
        const b = $('[data-publish]'); b.textContent = D.id ? (D.dirty ? 'Save changes' : 'Saved ✓') : 'Publish form'; b.disabled = !!D.id && !D.dirty; $('[data-status]').textContent = D.id ? (D.dirty ? 'Unsaved changes — your live form still shows the last saved version.' : 'Live — changes are saved to your form.') : 'Not published yet — your work is kept in this browser.'; };
    const publish = async () => {
        if (!token) return signIn(publish);
        const b = $('[data-publish]'); b.disabled = true; b.textContent = 'Saving…';
        try {
            let r;
            try { r = await api('POST', '/builder/surveys', { id: D.id || undefined, schema: S() }); }
            catch (ex) { if (ex.status === 404 && D.id) { D.id = null; r = await api('POST', '/builder/surveys', { schema: S() }); } else throw ex; } // deleted, or another account's form
            const first = !D.id; D.id = r.form.id; D.dirty = false; save();
            await loadForms().catch(() => {});
            note(first ? '🎉 Your form is live! Share the link or embed it on your site.' : 'Changes saved.');
            track(first ? 'form_builder_publish' : 'form_builder_save', { fields: S().fields.length });
            if (first) showTab('share');
        } catch (ex) { note(ex.message); if (ex.status === 401) signIn(publish); }
        finally { syncPublish(); }
    };
    $('[data-publish]').addEventListener('click', publish);
    $('[data-new]').addEventListener('click', () => {
        if (D.dirty && !confirm('Start a new form? Unsaved changes to this one will be lost.')) return;
        D = fresh('blank'); D.dirty = false; openId = null; save(); drawSettings(); drawFields(); drawMine(); syncPublish(); showTab('build');
    });
    $('[data-mine]').addEventListener('change', e => {
        const x = forms?.find(y => y.id === e.target.value); if (!x) return;
        if (D.dirty && !confirm('Open this form? Unsaved changes to the current one will be lost.')) { e.target.value = D.id || ''; return; }
        D = { id: x.id, schema: structuredClone(x.schema) }; D.dirty = false; openId = null; save(); drawSettings(); drawFields(); syncPublish(); showTab('build');
    });

    /* ------------------------------------------------ share */
    const formUrl = () => forms?.find(x => x.id === D.id)?.url || `${location.origin}/form/${D.id}`;
    const drawShare = () => {
        const box = $('[data-share]');
        if (!D.id) { box.innerHTML = `<div class="fb-empty"><b>Publish your form to share it</b><p>You get a link to send to people, and a code to put the form on your own website.</p><button type="button" class="btn btn--glow" data-publish-now>Publish form</button></div>`; $('[data-publish-now]', box).addEventListener('click', publish); return; }
        const url = formUrl(), embed = `<iframe src="${url}?embed=1" width="100%" height="900" style="border:0;max-width:680px" title="${esc(S().title)}" loading="lazy"></iframe>`;
        box.innerHTML = `<label>Link to your form<div class="fb-copyrow"><input readonly value="${esc(url)}"><button type="button" class="btn btn--sm" data-copy="${esc(url)}">Copy</button><a class="btn btn--sm btn--ghost" href="${esc(url)}" target="_blank" rel="noopener">Open ↗</a></div></label>
            <p class="fb-hint">Send it by WhatsApp or email, or put it in your social bio.</p>
            <label>Embed on your website<div class="fb-copyrow"><textarea readonly rows="3">${esc(embed)}</textarea><button type="button" class="btn btn--sm" data-copy="${esc(embed)}">Copy</button></div></label>
            <p class="fb-hint">Paste this code into a page on WordPress (Custom HTML block), Wix (Embed code), Squarespace, Shopify or the Owlvix page builder.</p>
            <p class="fb-hint">Answers are emailed to you and saved in the Responses tab.</p>
            <button type="button" class="fb-danger" data-delete>Delete this form and all its answers</button>`;
    };
    $('[data-share]').addEventListener('click', async e => {
        const c = e.target.closest('[data-copy]');
        if (c) { try { await navigator.clipboard.writeText(c.dataset.copy); c.textContent = 'Copied ✓'; } catch { c.previousElementSibling?.select(); document.execCommand('copy'); } setTimeout(() => (c.textContent = 'Copy'), 2000); track('form_builder_copy'); }
        if (e.target.closest('[data-delete]')) {
            if (!confirm('Delete this form? Its link stops working and all answers are deleted. This cannot be undone.')) return;
            try { await api('POST', `/builder/surveys/${D.id}/delete`); D.id = null; D.dirty = true; save(); await loadForms().catch(() => {}); syncPublish(); drawShare(); note('Form deleted. Your questions are still here if you want to publish it again.'); }
            catch (ex) { note(ex.message); }
        }
    });

    /* ------------------------------------------------ responses */
    const drawResponses = async () => {
        const box = $('[data-responses]');
        if (!D.id) { box.innerHTML = '<div class="fb-empty"><b>No answers yet</b><p>Publish your form and share the link — every answer shows up here and is emailed to you.</p></div>'; return; }
        if (!token) { box.innerHTML = '<div class="fb-empty"><b>Sign in to see answers</b><p>Use the email you published this form with.</p><button type="button" class="btn btn--glow" data-signin-now>Sign in with email</button></div>'; $('[data-signin-now]', box).addEventListener('click', () => signIn(drawResponses)); return; }
        box.innerHTML = '<p class="fb-hint">Loading answers…</p>';
        try {
            const r = await api('GET', `/builder/surveys/${D.id}/responses`), saved = forms?.find(x => x.id === D.id)?.schema || S();
            const fields = [...saved.fields.filter(q => q.type !== 'heading'), ...(saved.removed || []).map(q => ({ ...q, label: q.label + ' (removed)' }))];
            if (!r.total) { box.innerHTML = '<div class="fb-empty"><b>No answers yet</b><p>Share your form link — answers appear here as soon as they arrive.</p></div>'; return; }
            const cell = v => esc(Array.isArray(v) ? v.join(', ') : v ?? '');
            box.innerHTML = `<div class="fb-resp-head"><b>${r.total} answer${r.total === 1 ? '' : 's'}</b><span><button type="button" class="btn btn--sm btn--ghost" data-refresh>Refresh</button> <button type="button" class="btn btn--sm" data-csv>Download CSV</button></span></div>
                <div class="fb-table"><table><thead><tr><th>Date</th>${fields.map(q => `<th>${esc(q.label)}</th>`).join('')}</tr></thead><tbody>
                ${r.responses.map(x => `<tr><td>${esc(new Date(x.created_at).toLocaleString())}</td>${fields.map(q => `<td>${cell(x.answers[q.id])}</td>`).join('')}</tr>`).join('')}</tbody></table></div>
                ${r.total > r.responses.length ? `<p class="fb-hint">Showing the newest ${r.responses.length}. Download the CSV for all ${r.total}.</p>` : ''}`;
            $('[data-refresh]', box).addEventListener('click', drawResponses);
            $('[data-csv]', box).addEventListener('click', async () => {
                const res = await fetch(`/builder/surveys/${D.id}/responses?format=csv`, { headers: { 'X-Builder-Token': token, Accept: 'text/csv' } });
                if (!res.ok) return note('Could not download the file. Please try again.');
                const a = document.createElement('a'); a.href = URL.createObjectURL(await res.blob()); a.download = `responses-${(S().title || 'form').toLowerCase().replace(/[^a-z0-9]+/g, '-').slice(0, 40)}.csv`; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 2000);
                track('form_builder_csv');
            });
        } catch (ex) { box.innerHTML = `<p class="fb-hint">${esc(ex.message)}</p>`; }
    };

    /* ------------------------------------------------ start */
    drawSettings(); drawFields(); syncPublish();
    if (token) loadForms().catch(() => {});
})();
