/* Styled dialogs in place of the browser's alert(), confirm() and prompt(), used across the site, the free builders
   and the admin (styles: .sweet in theme.css).
   window.sweet({ icon, title, text, confirm, cancel, danger, input, html }) → Promise:
     - a question resolves true (OK) or false (Cancel, Esc, click outside)
     - with input: { value, placeholder, max, readonly } it resolves the text, or null when cancelled
   Forms with data-confirm="Question?" ask first (data-confirm-text, data-confirm-ok for the details and button). */
(() => {
    if (window.sweet) return;
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const ICONS = {
        warning: '<path d="M12 8v5M12 16.5h.01"/>',
        question: '<path d="M9.5 9.2a2.6 2.6 0 0 1 5 .8c0 1.8-2.5 2.2-2.5 3.8M12 16.8h.01"/>',
        success: '<path d="M7 12.5l3.2 3.2L17 9"/>',
        info: '<path d="M12 11v5M12 8h.01"/>',
    };

    window.sweet = opts => new Promise(resolve => {
        const icon = ICONS[opts.icon] ? opts.icon : 'question';
        const inp = opts.input;
        const d = document.createElement('dialog');
        d.className = `sweet sweet--${icon}`;
        d.innerHTML = `<form method="dialog" class="sweet__box">
            <span class="sweet__icon" aria-hidden="true"><svg viewBox="0 0 24 24">${ICONS[icon]}</svg></span>
            <h3 class="sweet__title">${esc(opts.title)}</h3>${opts.text ? `<p class="sweet__text">${esc(opts.text)}</p>` : ''}
            ${opts.html || ''}
            ${inp ? `<input class="sweet__input" maxlength="${inp.max || 60}" value="${esc(inp.value || '')}" placeholder="${esc(inp.placeholder || '')}"${inp.readonly ? ' readonly' : ''}>` : ''}
            <div class="sweet__actions">${opts.cancel === false ? '' : `<button type="button" class="sweet__btn sweet__btn--cancel" data-no>${esc(opts.cancel || 'Cancel')}</button>`}
            ${opts.confirm === false ? '' : `<button type="submit" class="sweet__btn ${opts.danger ? 'sweet__btn--danger' : ''}" data-yes>${esc(opts.confirm || 'OK')}</button>`}</div></form>`;
        document.body.append(d);
        const input = d.querySelector('.sweet__input');
        let done = false;
        const finish = val => {
            if (done) return;
            done = true;
            d.classList.add('is-closing');
            setTimeout(() => { d.close(); d.remove(); resolve(val); }, 160);
        };
        const no = () => finish(input ? null : false);
        d.querySelector('form').addEventListener('submit', e => {
            e.preventDefault();
            if (input && !inp.readonly && !input.value.trim()) {
                input.classList.add('is-shake');
                setTimeout(() => input.classList.remove('is-shake'), 400);
                return input.focus();
            }
            finish(input ? input.value.trim() : true);
        });
        d.querySelector('[data-no]')?.addEventListener('click', no);
        d.addEventListener('cancel', e => { e.preventDefault(); no(); });
        d.addEventListener('click', e => {
            if (e.target === d) no();
            const pick = e.target.closest('[data-pick]');
            if (pick) finish(pick.dataset.pick);
        });
        d.showModal();
        (input || d.querySelector('[data-yes]'))?.focus();
        input?.select();
    });

    // <form data-confirm="Delete this message?"> asks before sending
    document.addEventListener('submit', e => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) return;
        e.preventDefault();
        e.stopPropagation();
        const danger = form.dataset.confirmDanger !== undefined;
        window.sweet({
            icon: 'warning', title: form.dataset.confirm, text: form.dataset.confirmText || '',
            confirm: form.dataset.confirmOk || 'Yes, continue', danger,
        }).then(yes => {
            if (!yes) return;
            form.dataset.confirmed = '1';
            form.requestSubmit ? form.requestSubmit(e.submitter || undefined) : form.submit();
        });
    }, true);
})();
