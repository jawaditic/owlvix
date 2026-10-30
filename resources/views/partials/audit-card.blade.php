{{-- "Get a full SEO audit" panel (Claude SEO report by the Owlvix team). Used under the SEO checker results and on
     /seo-audit ($standalone = true shows a website field). Posts to seo-checker.audit and saves an "SEO audit request" lead. --}}
        <div class="audit-card" id="ai-audit">
            <div class="audit-card__glow" aria-hidden="true"></div>
            <div class="audit-card__info">
                <span class="badge badge--blue audit-card__badge">Free · by our team</span>
                <p class="eyebrow">Go deeper</p>
                <h2>Get a full <span class="accent">SEO audit</span></h2>
                <p>{{ ($standalone ?? false) ? 'Our free SEO checker looks at one page.' : 'This check looked at one page.' }} Our full audit reviews your <strong>whole website</strong> with professional SEO tools, the way an agency would — and we email you the report within 2 business days.</p>
                <ul class="audit-card__list">
                    <li><span class="svc-icon" aria-hidden="true">{!! \App\Support\Icons::svg('search') !!}</span><div><strong>Whole-site technical SEO</strong><small>Crawling, indexing, duplicates, sitemap</small></div></li>
                    <li><span class="svc-icon is-blue" aria-hidden="true">{!! \App\Support\Icons::svg('pen') !!}</span><div><strong>Content quality</strong><small>Expertise &amp; trust signals, thin pages</small></div></li>
                    <li><span class="svc-icon" aria-hidden="true">{!! \App\Support\Icons::svg('chart') !!}</span><div><strong>Search intent</strong><small>What ranks for your keywords</small></div></li>
                    <li><span class="svc-icon is-blue" aria-hidden="true">{!! \App\Support\Icons::svg('sparkle') !!}</span><div><strong>AI search readiness</strong><small>ChatGPT, Perplexity, AI Overviews</small></div></li>
                    <li><span class="svc-icon" aria-hidden="true">{!! \App\Support\Icons::svg('pulse') !!}</span><div><strong>Speed &amp; Core Web Vitals</strong><small>What slows you down and why</small></div></li>
                    <li><span class="svc-icon is-blue" aria-hidden="true">{!! \App\Support\Icons::svg('clipboard') !!}</span><div><strong>Prioritised action plan</strong><small>PDF report, most important first</small></div></li>
                </ul>
            </div>
            <form class="audit-card__form card" data-seo-audit data-live action="{{ route('seo-checker.audit') }}" method="POST" novalidate>
            <span class="perch-owl" aria-hidden="true">@include('partials.live-owl', ['width' => 96, 'variant' => 'dev', 'alt' => 'Owlvix owl preparing your audit'])</span>
                @csrf
                <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                @if ($standalone ?? false)
                    <input type="text" name="url" inputmode="url" autocomplete="url" placeholder="Your website, e.g. example.com" required maxlength="500" aria-label="Your website" value="{{ is_string(request('url')) ? request('url') : '' }}">
                @else
                    <input type="hidden" name="url">
                    <p class="audit-card__for">Audit for <strong data-audit-site>your site</strong></p>
                @endif
                <input type="text" name="name" placeholder="Your name" required maxlength="100" autocomplete="name" aria-label="Your name">
                <input type="email" name="email" placeholder="you@company.com" required maxlength="150" autocomplete="email" aria-label="Your email">
                <textarea name="notes" rows="3" maxlength="1000" placeholder="Anything we should focus on? (optional) — e.g. a keyword, a competitor, or a drop in traffic" aria-label="Notes"></textarea>
                <button type="submit" class="btn btn--glow btn--block">Request my full audit
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
                <p class="seo-email__msg" role="status" hidden data-audit-msg></p>
                <p class="form__privacy">Delivered by email within 2 business days. <a href="{{ route('privacy') }}">Privacy policy</a></p>
            </form>
        </div>

<script>
(() => {
    const af = document.querySelector('[data-seo-audit]');
    if (!af || af.dataset.ready) return;
    af.dataset.ready = '1';
    const amsg = af.querySelector('[data-audit-msg]');
    af.addEventListener('submit', async e => {
        e.preventDefault();
        for (const f of ['url', 'name', 'email']) {
            const el = af.elements[f];
            if (el.type !== 'hidden' && !el.value.trim()) { el.focus(); return; }
        }
        if (!af.elements.url.value) { amsg.textContent = 'Please run the free check above first.'; amsg.hidden = false; return; }
        const btn = af.querySelector('button'), label = btn.innerHTML; btn.disabled = true; btn.textContent = 'Sending…';
        try {
            const fd = new FormData(af);
            fd.append('g-recaptcha-response', await (window.owlvixCaptcha?.('seo_audit') ?? ''));
            const res = await fetch(af.action, { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.ok) throw new Error(json.error || Object.values(json.errors || {})[0]?.[0] || json.message || 'Could not send. Please try again.');
            amsg.textContent = json.already ? '✓ We already have your request — your audit is on its way.' : '✓ Requested! Check your inbox for a confirmation. Your full audit arrives within 2 business days.';
            amsg.dataset.ok = '1';
            af.querySelectorAll('input, textarea').forEach(i => i.disabled = true);
            btn.textContent = 'Audit requested ✓';
            window.track?.('generate_lead', { form: 'ai_audit' });
        } catch (err) { amsg.textContent = err.message; amsg.dataset.ok = ''; btn.disabled = false; btn.innerHTML = label; }
        finally { amsg.hidden = false; }
    });
})();
</script>
