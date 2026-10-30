{{-- Free email signature generator — runs fully in the browser. Builds table-based HTML with inline styles (what
     Gmail, Outlook and Apple Mail accept) and copies it as rich text. Social icons are coloured cells, not images. --}}
@extends('layouts.page')

@php
    $faq = [
        ['Is the email signature generator free?', 'Yes, completely. No sign-up, no watermark and no limits. Everything happens in your browser — your details are not sent to us.'],
        ['How do I add it to Gmail?', 'Press “Copy signature”. In Gmail open Settings (gear) → See all settings → General → Signature → Create new, paste with Ctrl+V (Cmd+V on Mac) and press “Save changes” at the bottom of the page.'],
        ['How do I add it to Outlook?', 'Press “Copy signature”. In Outlook on the web or the new Outlook go to Settings → Accounts → Signatures; in classic Outlook go to File → Options → Mail → Signatures. Create a new one, paste and save.'],
        ['Why does my photo not show?', 'Email signatures can only show images that are online. Put your photo or logo on your website (or any public image host) and paste its address, starting with https://. Images from Google Drive or Dropbox sharing links do not work.'],
        ['Will it look the same on phones?', 'Yes. The signature is built with simple tables and inline styles — the way email apps expect — so it looks right in Gmail, Outlook, Apple Mail and on phones.'],
    ];
@endphp

@section('title', 'Free Email Signature Generator — Gmail, Outlook & Apple Mail')
@section('description', 'Create a professional email signature in a minute: 6 designs, your photo, logo, social icons and a call-to-action. Copy and paste into Gmail, Outlook or Apple Mail. Free.')
@section('canonical', route('email-signature'))
@section('no-auto-quote', '1')

@section('head')
@include('tools.partials.schema', ['name' => 'Owlvix Email Signature Generator', 'url' => route('email-signature'), 'category' => 'BusinessApplication', 'faq' => $faq])
@endsection

@section('content')
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">Email signature generator</span>
        </nav>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · Nothing is uploaded</span>
        <h1>A <span class="accent">professional email signature</span> in one minute</h1>
        <p class="lead">Pick a design, type your details, then copy and paste it into Gmail, Outlook or Apple Mail. Free, no sign-up.</p>
    </div>
</section>

<section class="calc-wrap">
    <div class="container">
        <div class="esig-designs" role="radiogroup" aria-label="Design" data-designs></div>
        <div class="meta-layout esig-layout">
            <form class="meta-edit card esig-form" data-form novalidate onsubmit="return false">
                <p class="eyebrow">Your details</p>
                <h2>Fill in what you need</h2>
                <p class="esig-hint">Leave a field empty to hide it.</p>
                <div class="esig-2">
                    <label>Full name<input data-k="name" maxlength="60" autocomplete="name"></label>
                    <label>Job title<input data-k="title" maxlength="60" autocomplete="organization-title"></label>
                </div>
                <div class="esig-2">
                    <label>Company<input data-k="company" maxlength="60" autocomplete="organization"></label>
                    <label>Department<input data-k="dept" maxlength="60"></label>
                </div>
                <div class="esig-2">
                    <label>Phone<input data-k="phone" maxlength="30" type="tel" autocomplete="tel"></label>
                    <label>Mobile<input data-k="mobile" maxlength="30" type="tel"></label>
                </div>
                <div class="esig-2">
                    <label>Email<input data-k="email" maxlength="100" type="email" autocomplete="email"></label>
                    <label>Website<input data-k="website" maxlength="100" placeholder="yourbusiness.com"></label>
                </div>
                <label>Address<input data-k="address" maxlength="120" autocomplete="street-address"></label>

                <details class="esig-acc" open><summary>Photo &amp; logo</summary>
                    <label>Photo address<input data-k="photo" maxlength="500" placeholder="https://yoursite.com/me.jpg"></label>
                    <label>Logo address<input data-k="logo" maxlength="500" placeholder="https://yoursite.com/logo.png"></label>
                    <p class="esig-hint">Images must be online — paste a link to the image on your website. Drive and Dropbox share links don’t work in email.</p>
                </details>
                <details class="esig-acc"><summary>Social links</summary>
                    <div class="esig-2" data-socials></div>
                </details>
                <details class="esig-acc"><summary>Colours &amp; font</summary>
                    <div class="esig-2">
                        <label>Main colour<input data-k="color" type="color"></label>
                        <label>Text colour<input data-k="ink" type="color"></label>
                    </div>
                    <div class="esig-2">
                        <label>Font<select data-k="font">
                            <option value="Arial, Helvetica, sans-serif">Arial</option>
                            <option value="Helvetica, Arial, sans-serif">Helvetica</option>
                            <option value="Verdana, Geneva, sans-serif">Verdana</option>
                            <option value="Tahoma, Geneva, sans-serif">Tahoma</option>
                            <option value="'Trebuchet MS', Helvetica, sans-serif">Trebuchet</option>
                            <option value="Georgia, 'Times New Roman', serif">Georgia (serif)</option>
                        </select></label>
                        <label>Size<select data-k="size"><option value="13">Small</option><option value="14">Medium</option><option value="15">Large</option></select></label>
                    </div>
                    <label>Photo shape<select data-k="shape"><option value="round">Round</option><option value="rounded">Rounded square</option><option value="square">Square</option></select></label>
                </details>
                <details class="esig-acc"><summary>Button &amp; message</summary>
                    <div class="esig-2">
                        <label>Button text<input data-k="cta" maxlength="40" placeholder="Book a call"></label>
                        <label>Button link<input data-k="ctaUrl" maxlength="300" placeholder="https://…"></label>
                    </div>
                    <label>Sign-off line<input data-k="signoff" maxlength="80" placeholder="Best regards,"></label>
                    <label>Small print / disclaimer<textarea data-k="legal" rows="2" maxlength="400" placeholder="This email and any attachments are confidential…"></textarea></label>
                </details>
                <button type="button" class="esig-reset" data-reset>Start over</button>
            </form>

            <div class="esig-out">
                <ul class="meta-issues" data-issues></ul>
                <div class="card esig-preview-card">
                    <div class="esig-mail" aria-hidden="true"><span></span><span></span><span></span><b>New message</b></div>
                    <div class="esig-preview" data-preview></div>
                </div>
                <div class="esig-actions">
                    <button type="button" class="btn btn--glow" data-copy>Copy signature</button>
                    <button type="button" class="btn btn--ghost" data-copy-html>Copy HTML code</button>
                    <button type="button" class="btn btn--ghost" data-download>Download .html</button>
                </div>
                <p class="esig-copied" data-copied role="status" hidden></p>
                <div class="card esig-how">
                    <div class="esig-tabs" role="tablist">
                        <button type="button" role="tab" aria-selected="true" data-how="gmail">Gmail</button>
                        <button type="button" role="tab" aria-selected="false" data-how="outlook">Outlook</button>
                        <button type="button" role="tab" aria-selected="false" data-how="apple">Apple Mail</button>
                        <button type="button" role="tab" aria-selected="false" data-how="phone">iPhone / Android</button>
                    </div>
                    <ol data-how-panel="gmail"><li>Press <b>Copy signature</b>.</li><li>In Gmail open <b>Settings ⚙ → See all settings</b>.</li><li>On the <b>General</b> tab scroll to <b>Signature</b> and press <b>Create new</b>.</li><li>Paste (Ctrl+V / Cmd+V), choose it as the default, and press <b>Save changes</b> at the bottom.</li></ol>
                    <ol data-how-panel="outlook" hidden><li>Press <b>Copy signature</b>.</li><li>Outlook on the web / new Outlook: <b>Settings → Accounts → Signatures</b>. Classic Outlook: <b>File → Options → Mail → Signatures</b>.</li><li>Create a new signature and paste (Ctrl+V).</li><li>Pick it for new messages and replies, then save.</li></ol>
                    <ol data-how-panel="apple" hidden><li>Press <b>Download .html</b> and open the file in Safari, then select all (Cmd+A) and copy (Cmd+C).</li><li>In Mail open <b>Settings → Signatures</b> and press <b>+</b>.</li><li>Untick <b>Always match my default message font</b>, then paste into the box on the right.</li></ol>
                    <ol data-how-panel="phone" hidden><li>Phone mail apps only keep plain text signatures.</li><li>Set the signature up in Gmail or Outlook on a computer — it is then used when you send from their phone apps too.</li></ol>
                </div>
                <div class="lp-cta__box card speed-cta schema-cta">
                    <div><h2>Want your whole brand to look this sharp?</h2><p>We design logos, websites and email templates that match — so every email you send builds trust.</p></div>
                    <a href="{{ route('service.landing-page-design') }}#contact" class="btn btn--glow" data-quote>Talk to us <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
                </div>
            </div>
        </div>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $faq, 'title' => 'Email signatures, explained'])

<script>
(() => {
    const $ = (s, el = document) => el.querySelector(s), $$ = (s, el = document) => [...el.querySelectorAll(s)];
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const hex = (c, d) => (/^#[0-9a-f]{6}$/i.test(c || '') ? c : d);
    const STORE = 'owlvix-email-signature';
    const DESIGNS = { classic: 'Classic', photo: 'Photo left', band: 'Colour bar', compact: 'Compact', centered: 'Centred', minimal: 'Minimal' };
    const SOCIAL = { linkedin: ['LinkedIn', 'in', '#0a66c2'], x: ['X', 'X', '#111111'], facebook: ['Facebook', 'f', '#1877f2'], instagram: ['Instagram', 'IG', '#e1306c'], youtube: ['YouTube', '▶', '#ff0000'], tiktok: ['TikTok', '♪', '#111111'], whatsapp: ['WhatsApp', 'WA', '#25d366'], calendly: ['Booking page', '📅', '#006bff'] };
    const DEFAULTS = { design: 'classic', name: 'Sarah Johnson', title: 'Marketing Manager', company: 'Northwind Consulting', dept: '', phone: '+1 555 123 4567', mobile: '', email: 'sarah@northwind.com', website: 'northwind.com', address: '', photo: '', logo: '',
        color: '#16a34a', ink: '#1f2937', font: 'Arial, Helvetica, sans-serif', size: '14', shape: 'round', cta: '', ctaUrl: '', signoff: '', legal: '', social: { linkedin: 'https://linkedin.com/in/', x: '', facebook: '', instagram: '', youtube: '', tiktok: '', whatsapp: '', calendly: '' } };
    let S;
    try { S = { ...DEFAULTS, ...JSON.parse(localStorage.getItem(STORE) || '{}') }; S.social = { ...DEFAULTS.social, ...(S.social || {}) }; } catch { S = structuredClone(DEFAULTS); }
    if (!DESIGNS[S.design]) S.design = 'classic';

    /* ------------------------------------------------ values → safe links */
    const http = u => { u = String(u || '').trim(); if (!u) return ''; if (!/^https?:\/\//i.test(u)) u = 'https://' + u; try { const x = new URL(u); return /^https?:$/.test(x.protocol) && x.hostname.includes('.') ? x.href : ''; } catch { return ''; } };
    const img = u => { u = String(u || '').trim(); return /^https:\/\/[^\s"'<>]+$/i.test(u) ? u : ''; };
    const tel = p => String(p || '').replace(/[^\d+]/g, '');
    const mail = e => (/^[^\s@<>"']+@[^\s@<>"']+\.[^\s@<>"']+$/.test(String(e || '').trim()) ? String(e).trim() : '');
    const site = u => String(u || '').trim().replace(/^https?:\/\//i, '').replace(/\/$/, '');

    /* ------------------------------------------------ signature HTML (tables + inline styles only) */
    const build = () => {
        const c = hex(S.color, '#16a34a'), ink = hex(S.ink, '#1f2937'), f = S.font, fs = +S.size || 14, muted = '#6b7280';
        const t = (txt, st = '') => `<span style="font-family:${f};font-size:${fs}px;color:${ink};${st}">${esc(txt)}</span>`;
        const a = (href, txt, st = '') => `<a href="${esc(href)}" style="font-family:${f};font-size:${fs}px;color:${ink};text-decoration:none;${st}">${esc(txt)}</a>`;
        const radius = { round: '50%', rounded: '12px', square: '0' }[S.shape] || '50%';
        const photo = (size = 96) => img(S.photo) ? `<img src="${esc(img(S.photo))}" width="${size}" height="${size}" alt="${esc(S.name)}" style="display:block;width:${size}px;height:${size}px;border-radius:${radius};object-fit:cover;border:0">` : '';
        const logo = (h = 40) => img(S.logo) ? `<img src="${esc(img(S.logo))}" height="${h}" alt="${esc(S.company || 'Logo')}" style="display:block;height:${h}px;width:auto;max-width:180px;border:0">` : '';
        const role = [S.title, S.dept].filter(s => String(s).trim()).join(' · ');
        const contacts = [
            tel(S.phone) && ['P', a('tel:' + tel(S.phone), S.phone)], tel(S.mobile) && ['M', a('tel:' + tel(S.mobile), S.mobile)],
            mail(S.email) && ['E', a('mailto:' + mail(S.email), mail(S.email))], http(S.website) && ['W', a(http(S.website), site(S.website))],
            String(S.address).trim() && ['A', t(S.address)],
        ].filter(Boolean);
        const line = ([k, v]) => `<tr><td style="padding:1px 0;font-family:${f};font-size:${fs}px;line-height:1.5"><span style="color:${c};font-weight:bold">${k}&nbsp;&nbsp;</span>${v}</td></tr>`;
        const inline = (sep = ' | ') => contacts.map(([, v]) => v).join(`<span style="color:${muted}">${sep}</span>`);
        const socials = Object.entries(S.social || {}).map(([k, u]) => [k, http(u)]).filter(([k, u]) => u && SOCIAL[k])
            .map(([k, u]) => `<td style="padding:0 4px 0 0"><a href="${esc(u)}" title="${SOCIAL[k][0]}" style="display:inline-block;width:24px;height:24px;line-height:24px;text-align:center;border-radius:${S.shape === 'square' ? '3px' : '50%'};background:${SOCIAL[k][2]};color:#ffffff;font-family:Arial,sans-serif;font-size:11px;font-weight:bold;text-decoration:none">${esc(SOCIAL[k][1])}</a></td>`).join('');
        const socialRow = socials ? `<table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;margin-top:8px"><tr>${socials}</tr></table>` : '';
        const cta = String(S.cta).trim() && http(S.ctaUrl) ? `<table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;margin-top:10px"><tr><td style="background:${c};border-radius:6px"><a href="${esc(http(S.ctaUrl))}" style="display:inline-block;padding:8px 16px;font-family:${f};font-size:${fs - 1}px;font-weight:bold;color:#ffffff;text-decoration:none">${esc(S.cta)}</a></td></tr></table>` : '';
        const name = String(S.name).trim() ? `<span style="font-family:${f};font-size:${fs + 4}px;font-weight:bold;color:${ink}">${esc(S.name)}</span>` : '';
        const sub = role || S.company ? `<br><span style="font-family:${f};font-size:${fs}px;color:${muted}">${esc(role)}${role && S.company ? ', ' : ''}${S.company ? `<b style="color:${c}">${esc(S.company)}</b>` : ''}</span>` : '';
        const signoff = String(S.signoff).trim() ? `<p style="margin:0 0 12px;font-family:${f};font-size:${fs}px;color:${ink}">${esc(S.signoff)}</p>` : '';
        const legal = String(S.legal).trim() ? `<p style="margin:12px 0 0;max-width:520px;font-family:${f};font-size:${Math.max(10, fs - 3)}px;line-height:1.4;color:#9ca3af">${esc(S.legal)}</p>` : '';
        const T = inner => `<table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse">${inner}</table>`;
        let body;
        switch (S.design) {
            case 'photo': body = T(`<tr>${photo() ? `<td valign="top" style="padding:0 16px 0 0">${photo()}</td>` : ''}<td valign="top" style="padding:0 0 0 16px;border-left:3px solid ${c}">${name}${sub}${T(`<tr><td style="height:8px"></td></tr>${contacts.map(line).join('')}`)}${socialRow}${cta}</td></tr>`); break;
            case 'band': body = T(`<tr><td style="padding:12px 16px;background:${c};border-radius:8px 8px 0 0"><span style="font-family:${f};font-size:${fs + 4}px;font-weight:bold;color:#ffffff">${esc(S.name)}</span>${role || S.company ? `<br><span style="font-family:${f};font-size:${fs}px;color:#ffffff">${esc([role, S.company].filter(Boolean).join(', '))}</span>` : ''}</td></tr>
                <tr><td style="padding:12px 16px;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 8px 8px">${T(`<tr>${photo(72) ? `<td valign="top" style="padding:0 14px 0 0">${photo(72)}</td>` : ''}<td valign="top">${T(contacts.map(line).join(''))}${socialRow}${cta}</td>${logo() ? `<td valign="middle" style="padding:0 0 0 18px">${logo()}</td>` : ''}</tr>`)}</td></tr>`); break;
            case 'compact': body = T(`<tr><td style="font-family:${f};font-size:${fs}px;line-height:1.6">${name ? `<b style="font-family:${f};font-size:${fs + 1}px;color:${ink}">${esc(S.name)}</b>` : ''}${role || S.company ? `<span style="color:${muted}"> · ${esc([role, S.company].filter(Boolean).join(', '))}</span>` : ''}<br>${inline()}</td></tr>${socials ? `<tr><td>${socialRow}</td></tr>` : ''}${cta ? `<tr><td>${cta}</td></tr>` : ''}`); break;
            case 'centered': body = T(`<tr><td align="center" style="text-align:center">${photo() ? `<table cellpadding="0" cellspacing="0" border="0" role="presentation" align="center"><tr><td style="padding-bottom:8px">${photo(80)}</td></tr></table>` : ''}${name}${sub}<br><span style="font-size:${fs}px;line-height:2">${inline()}</span>${socials ? `<table cellpadding="0" cellspacing="0" border="0" role="presentation" align="center" style="margin-top:6px"><tr>${socials}</tr></table>` : ''}${cta ? `<table cellpadding="0" cellspacing="0" border="0" role="presentation" align="center"><tr><td>${cta}</td></tr></table>` : ''}${logo() ? `<table cellpadding="0" cellspacing="0" border="0" role="presentation" align="center" style="margin-top:12px"><tr><td>${logo(32)}</td></tr></table>` : ''}</td></tr>`); break;
            case 'minimal': body = T(`<tr><td style="padding:0 0 6px;border-bottom:2px solid ${c}">${name}${sub}</td></tr><tr><td style="padding-top:6px;font-family:${f};font-size:${fs}px;line-height:1.6">${inline('&nbsp;&nbsp;·&nbsp;&nbsp;')}</td></tr>${socials ? `<tr><td>${socialRow}</td></tr>` : ''}${cta ? `<tr><td>${cta}</td></tr>` : ''}`); break;
            default: body = T(`<tr>${logo() || photo() ? `<td valign="top" style="padding:0 18px 0 0">${photo() || logo(56)}</td>` : ''}<td valign="top">${name}${sub}${T(`<tr><td style="height:4px;border-bottom:2px solid ${c}"></td></tr><tr><td style="height:6px"></td></tr>${contacts.map(line).join('')}`)}${socialRow}${cta}${photo() && logo() ? `<div style="margin-top:10px">${logo(32)}</div>` : ''}</td></tr>`);
        }
        return `<div style="font-family:${f};font-size:${fs}px;color:${ink}">${signoff}${body}${legal}</div>`;
    };

    /* ------------------------------------------------ checks */
    const issues = () => {
        const out = [];
        if (!String(S.name).trim()) out.push(['fail', 'Add your name.']);
        if (S.email && !mail(S.email)) out.push(['warn', 'The email address doesn’t look right.']);
        if (S.website && !http(S.website)) out.push(['warn', 'The website address doesn’t look right.']);
        ['photo', 'logo'].forEach(k => { if (String(S[k]).trim() && !img(S[k])) out.push(['warn', `The ${k} must be an online image link starting with https://`]); });
        ['photo', 'logo'].forEach(k => { if (/drive\.google|dropbox\.com|1drv\.ms|onedrive/i.test(S[k] || '')) out.push(['warn', `${k === 'photo' ? 'Photo' : 'Logo'}: Drive, Dropbox and OneDrive share links don’t show in emails — use a link to the image on your website.`]); });
        if (String(S.cta).trim() && !http(S.ctaUrl)) out.push(['warn', 'Add a link for your button, or it won’t be shown.']);
        Object.entries(S.social).forEach(([k, u]) => { if (String(u).trim() && !http(u)) out.push(['warn', `${SOCIAL[k][0]}: that link doesn’t look right.`]); });
        return out;
    };

    /* ------------------------------------------------ UI */
    $('[data-designs]').innerHTML = Object.entries(DESIGNS).map(([k, l]) => `<label class="calc-opt"><input type="radio" name="esig_design" value="${k}" ${S.design === k ? 'checked' : ''}><span class="calc-opt__box esig-design esig-design--${k}"><i aria-hidden="true"></i><strong>${l}</strong></span></label>`).join('');
    $('[data-socials]').innerHTML = Object.entries(SOCIAL).map(([k, v]) => `<label>${v[0]}<input data-s="${k}" maxlength="200" placeholder="https://…" value="${esc(S.social[k] || '')}"></label>`).join('');
    const fill = () => $$('[data-k]').forEach(el => (el.value = S[el.dataset.k] ?? ''));
    const save = () => { try { localStorage.setItem(STORE, JSON.stringify(S)); } catch {} };
    const draw = () => {
        $('[data-preview]').innerHTML = build();
        $('[data-issues]').innerHTML = issues().map(([s, m]) => `<li data-status="${s}">${esc(m)}</li>`).join('');
    };
    fill(); draw();
    $('[data-form]').addEventListener('input', e => {
        const el = e.target;
        if (el.dataset.k) S[el.dataset.k] = el.value; else if (el.dataset.s) S.social[el.dataset.s] = el.value; else return;
        save(); draw();
    });
    $('[data-form]').addEventListener('change', e => e.target.matches('select') && (S[e.target.dataset.k] = e.target.value, save(), draw()));
    $('[data-designs]').addEventListener('change', e => { S.design = e.target.value; save(); draw(); window.track?.('esig_design', { design: S.design }); });
    $('[data-reset]').addEventListener('click', () => { if (!confirm('Clear your details and start over?')) return; S = structuredClone(DEFAULTS); save(); fill(); $$('[data-s]').forEach(el => (el.value = S.social[el.dataset.s] || '')); $$('[name=esig_design]').forEach(r => (r.checked = r.value === S.design)); draw(); });

    const said = msg => { const p = $('[data-copied]'); p.textContent = msg; p.hidden = false; clearTimeout(said.t); said.t = setTimeout(() => (p.hidden = true), 4000); };
    const html = () => build();
    // Copy as rich text, so it pastes as a formatted signature (falls back to selecting the preview)
    $('[data-copy]').addEventListener('click', async () => {
        if (!String(S.name).trim()) return said('Add your name first.');
        try {
            await navigator.clipboard.write([new ClipboardItem({ 'text/html': new Blob([html()], { type: 'text/html' }), 'text/plain': new Blob([$('[data-preview]').innerText], { type: 'text/plain' }) })]);
        } catch {
            const r = document.createRange(); r.selectNodeContents($('[data-preview]'));
            const sel = getSelection(); sel.removeAllRanges(); sel.addRange(r); document.execCommand('copy'); sel.removeAllRanges();
        }
        said('✅ Copied! Now paste it into your email signature settings (Ctrl+V / Cmd+V).');
        window.track?.('esig_copy', { design: S.design });
    });
    $('[data-copy-html]').addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(html()); said('✅ HTML code copied.'); } catch { said('Your browser blocked copying — use Download instead.'); }
        window.track?.('esig_copy_html');
    });
    $('[data-download]').addEventListener('click', () => {
        const doc = `<!doctype html><html><head><meta charset="utf-8"><title>Email signature — ${esc(S.name)}</title></head><body style="margin:24px">${html()}</body></html>`;
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([doc], { type: 'text/html' }));
        a.download = 'email-signature.html'; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 2000);
        window.track?.('esig_download');
    });
    $$('[data-how]').forEach(b => b.addEventListener('click', () => {
        $$('[data-how]').forEach(x => x.setAttribute('aria-selected', x === b ? 'true' : 'false'));
        $$('[data-how-panel]').forEach(p => (p.hidden = p.dataset.howPanel !== b.dataset.how));
    }));
})();
</script>
@endsection
