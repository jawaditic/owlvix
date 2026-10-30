{{-- Shared script for the website tools: posts the form, shows the loading owl with rotating steps, shows errors,
     keeps ?url= in the address bar (shareable) and hands the result to the page's own render function.
     Usage: OwlTool.init({ form, steps: [...], render: report => {...}, event: 'tech_check' }) --}}
<script>
window.OwlTool = window.OwlTool || {
    esc: s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]),
    icon: { pass: '<path d="M5 12.5l4.5 4.5L19 7.5"/>', warn: '<path d="M12 7v6M12 17h.01"/>', fail: '<path d="M7 7l10 10M17 7L7 17"/>' },
    word: { pass: 'Passed', warn: 'Improve', fail: 'Fix first' },
    /** Check cards grouped by category (same look as the SEO checker). */
    checks(list) {
        const groups = {};
        list.forEach(c => (groups[c.cat] ||= []).push(c));
        return Object.entries(groups).map(([cat, items]) => `
            <h3 class="speed-h">${this.esc(cat)} <small>${items.filter(c => c.status === 'pass').length}/${items.length} passed</small></h3>
            <div class="seo-checks">${items.map(c => `
                <div class="seo-check" data-status="${c.status}">
                    <span class="speed-metric__icon" aria-hidden="true"><svg viewBox="0 0 24 24">${this.icon[c.status]}</svg></span>
                    <div class="seo-check__body">
                        <div class="seo-check__head"><strong>${this.esc(c.title)}</strong><span class="speed-badge">${this.word[c.status]}</span></div>
                        <p class="seo-check__found">${this.esc(c.found)}</p>
                        ${c.tip ? `<p class="seo-check__tip"><b>Tip:</b> ${this.esc(c.tip)}</p>` : ''}
                    </div>
                </div>`).join('')}</div>`).join('');
    },
    init({ form, steps, render, event }) {
        const root = form.closest('main');
        const loading = root.querySelector('[data-tool-loading]'), report = root.querySelector('[data-tool-report]'), error = root.querySelector('[data-tool-error]');
        const step = loading.querySelector('[data-tool-step]');
        let timer = 0;
        const run = async () => {
            error.hidden = true; report.hidden = true; loading.hidden = false;
            const btn = form.querySelector('button[type=submit]'); btn.disabled = true;
            let i = 0; step.textContent = steps[0];
            timer = setInterval(() => { i = Math.min(i + 1, steps.length - 1); step.textContent = steps[i]; }, 2500);
            try {
                const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
                const json = await res.json().catch(() => ({}));
                if (!res.ok || !json.ok) throw new Error(json.error || json.message || 'Something went wrong. Please try again.');
                render(json.report);
                report.hidden = false;
                report.scrollIntoView({ behavior: 'smooth', block: 'start' });
                history.replaceState(null, '', '?url=' + encodeURIComponent(json.report.url));
                window.track?.(event, {});
            } catch (e) { error.textContent = e.message; error.hidden = false; }
            finally { clearInterval(timer); loading.hidden = true; btn.disabled = false; }
        };
        form.addEventListener('submit', e => { e.preventDefault(); run(); });
        if (form.url.value.trim()) run();
    },
};
</script>
