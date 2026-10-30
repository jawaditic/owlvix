/* Owlvix free email template builder (/email-template-builder). Builds a newsletter or marketing email from blocks,
   entirely in the browser (saved in localStorage). The output is email-safe HTML: tables, inline styles, a 600px
   column that stacks on phones, a hidden preview line, and an unsubscribe link in the format of the user's
   email platform (Mailchimp, Brevo, Klaviyo or their own link). */
(() => {
    const root = document.querySelector('[data-eb]');
    if (!root) return;
    const $ = (s, el = root) => el.querySelector(s), $$ = (s, el = root) => [...el.querySelectorAll(s)];
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const hex = (c, d) => (/^#[0-9a-f]{6}$/i.test(c || '') ? c : d);
    const uid = () => (Math.random().toString(36).slice(2) + '00000000').slice(0, 8);
    const track = (e, d = {}) => window.track?.(e, d);
    const STORE = 'owlvix-email-builder';

    /* ------------------------------------------------ safe values */
    const link = u => { u = String(u || '').trim(); if (!u) return ''; if (/^(mailto:|tel:)/i.test(u)) return u.replace(/["'<>\s]/g, ''); if (!/^https?:\/\//i.test(u)) u = 'https://' + u; try { const x = new URL(u); return /^https?:$/.test(x.protocol) && x.hostname.includes('.') ? x.href : ''; } catch { return ''; } };
    const img = u => { u = String(u || '').trim(); return /^https:\/\/[^\s"'<>]+$/i.test(u) ? u : ''; };
    /** Your own unsubscribe link: a web address, or your platform's tag such as {{ unsubscribe_link }} or %UNSUBSCRIBELINK%. */
    const ownUnsub = v => { v = String(v || '').trim(); return /^[{%*$[][^"'<>\s]?[^"'<>]{1,80}$/.test(v) ? v : link(v); };
    const align = a => (['left', 'center', 'right'].includes(a) ? a : 'left');
    const FONTS = { 'Arial, Helvetica, sans-serif': 'Arial', 'Helvetica, Arial, sans-serif': 'Helvetica', 'Verdana, Geneva, sans-serif': 'Verdana', "'Trebuchet MS', Helvetica, sans-serif": 'Trebuchet', "Georgia, 'Times New Roman', serif": 'Georgia (serif)' };
    const UNSUB = { mailchimp: ['Mailchimp', '*|UNSUB|*'], brevo: ['Brevo (Sendinblue)', '{{ unsubscribe }}'], klaviyo: ['Klaviyo', '{% unsubscribe_url %}'], mailerlite: ['MailerLite', '{$unsubscribe}'], custom: ['My own link', ''] };
    const SOCIAL = { facebook: ['Facebook', 'f', '#1877f2'], instagram: ['Instagram', 'IG', '#e1306c'], linkedin: ['LinkedIn', 'in', '#0a66c2'], x: ['X', 'X', '#111111'], youtube: ['YouTube', '▶', '#ff0000'], tiktok: ['TikTok', '♪', '#111111'], whatsapp: ['WhatsApp', 'WA', '#25d366'] };

    /** Simple formatting in text blocks: **bold**, and web addresses become links. */
    const rich = (t, color) => esc(t).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>')
        .replace(/(^|[\s(>])(https?:\/\/[^\s<]+?)([.,!?;:)]*)(?=$|[\s<])/g, (m, sp, u, end) => `${sp}<a href="${u}" style="color:${color};text-decoration:underline">${u.replace(/^https?:\/\//, '')}</a>${end}`)
        .replace(/\n/g, '<br>');

    /* ------------------------------------------------ blocks: [label, icon, defaults, fields] */
    // Field kinds: text, area, url, img, select (options), num (min, max), check
    const B = {
        header: ['Logo / header', '⌂', { logo: '', logoW: 160, name: 'Your brand', align: 'center' }, [['logo', 'Logo image address (https://…)', 'img'], ['logoW', 'Logo width (px)', 'num', 40, 300], ['name', 'Brand name (shown if no logo)', 'text'], ['align', 'Alignment', 'select', { left: 'Left', center: 'Centre' }]]],
        heading: ['Heading', 'H', { text: 'A big, clear headline', size: 28, align: 'left' }, [['text', 'Heading', 'text'], ['size', 'Size (px)', 'num', 18, 44], ['align', 'Alignment', 'select', { left: 'Left', center: 'Centre', right: 'Right' }]]],
        text: ['Text', '¶', { text: 'Write your message here. Keep paragraphs short — most people read email on their phone.\n\nUse **two stars** for bold. Web addresses become links.', align: 'left' }, [['text', 'Text', 'area'], ['align', 'Alignment', 'select', { left: 'Left', center: 'Centre' }]]],
        image: ['Image', '🖼', { src: '', alt: '', url: '', full: false }, [['src', 'Image address (https://…)', 'img'], ['alt', 'Describe the image (shown if images are off)', 'text'], ['url', 'Link (optional)', 'url'], ['full', 'Edge to edge (no side space)', 'check']]],
        button: ['Button', '⏺', { text: 'Shop now', url: '', align: 'center', full: false }, [['text', 'Button text', 'text'], ['url', 'Link', 'url'], ['align', 'Alignment', 'select', { left: 'Left', center: 'Centre' }], ['full', 'Full width', 'check']]],
        split: ['Image + text', '◧', { src: '', title: 'Feature title', text: 'A short description of this feature, product or story.', btn: 'Learn more', url: '', flip: false }, [['src', 'Image address (https://…)', 'img'], ['title', 'Title', 'text'], ['text', 'Text', 'area'], ['btn', 'Link text (optional)', 'text'], ['url', 'Link', 'url'], ['flip', 'Image on the right', 'check']]],
        products: ['Two products', '▦', { a: { src: '', name: 'Product one', price: '$29', url: '' }, b: { src: '', name: 'Product two', price: '$39', url: '' }, btn: 'Buy now' }, [['a', 'First product', 'product'], ['b', 'Second product', 'product'], ['btn', 'Button text', 'text']]],
        divider: ['Divider', '—', {}, []],
        spacer: ['Space', '↕', { h: 24 }, [['h', 'Height (px)', 'num', 4, 120]]],
        social: ['Social links', '＠', { facebook: '', instagram: 'https://instagram.com/', linkedin: '', x: '', youtube: '', tiktok: '', whatsapp: '' }, Object.entries(SOCIAL).map(([k, v]) => [k, v[0], 'url'])],
        footer: ['Footer & unsubscribe', '✉', { company: 'Your Company Ltd', address: '12 Main Street, Your City', why: 'You are getting this email because you signed up on our website.' }, [['company', 'Company name', 'text'], ['address', 'Postal address (required by law in many countries)', 'text'], ['why', 'Why they get this email', 'area']]],
    };
    const MAX = 60; // blocks per email
    const block = (type, o = {}) => ({ id: uid(), type, ...structuredClone(B[type][2]), ...o });

    /* ------------------------------------------------ templates */
    const T = {
        newsletter: ['Newsletter', () => ({ subject: 'Our news this month', preheader: 'Three things worth reading this month — plus a little surprise.', color: '#2563eb', blocks: [
            block('header'), block('image', { full: true, alt: 'This month’s story' }), block('heading', { text: 'Hello, this is our monthly news' }),
            block('text', { text: 'Thanks for reading! Here is what happened this month, what is coming next, and a little thank-you at the end.' }),
            block('split', { title: 'Story one: what we launched', text: 'Tell your readers about something new — a product, a service or a milestone.', btn: 'Read more' }),
            block('split', { title: 'Story two: a customer win', text: 'Share a short story about a happy customer and what you did for them.', btn: 'Read more', flip: true }),
            block('divider'), block('heading', { text: 'A thank-you for you', size: 22, align: 'center' }), block('text', { text: 'Use code **THANKS10** for 10% off your next order.', align: 'center' }),
            block('button', { text: 'Visit our website' }), block('social'), block('footer')] })],
        sale: ['Sale / promotion', () => ({ subject: '48-hour sale: 30% off everything', preheader: 'Ends Sunday at midnight — don’t miss it.', color: '#dc2626', blocks: [
            block('header'), block('heading', { text: '30% off everything — 48 hours only', size: 32, align: 'center' }), block('text', { text: 'Our biggest sale of the year starts now. Use code **SAVE30** at checkout.', align: 'center' }),
            block('button', { text: 'Shop the sale' }), block('image', { full: true, alt: 'Sale' }), block('products'), block('spacer'), block('text', { text: 'Free delivery on orders over $50. Sale ends Sunday at midnight.', align: 'center' }), block('social'), block('footer')] })],
        welcome: ['Welcome email', () => ({ subject: 'Welcome! Here’s how to get started', preheader: 'Thanks for joining — here are three quick tips.', color: '#16a34a', blocks: [
            block('header'), block('heading', { text: 'Welcome aboard! 👋', align: 'center' }), block('text', { text: 'Thanks for joining us. We are really glad you are here. Here are three quick ways to get the most out of it:', align: 'center' }),
            block('text', { text: '**1. Set up your profile** — it takes two minutes.\n**2. Explore our guides** — the best tips in one place.\n**3. Say hello** — just reply to this email, a real person reads it.' }),
            block('button', { text: 'Get started' }), block('divider'), block('text', { text: 'Questions? Just reply to this email — we answer every message.', align: 'center' }), block('footer')] })],
        event: ['Event invitation', () => ({ subject: 'You’re invited: our spring workshop', preheader: 'Thursday 6 pm · free · limited seats', color: '#7c3aed', blocks: [
            block('header'), block('image', { full: true, alt: 'Event' }), block('heading', { text: 'You’re invited to our spring workshop', align: 'center' }),
            block('text', { text: '**When:** Thursday, 6–8 pm\n**Where:** 12 Main Street, Your City (or online)\n**Price:** free — seats are limited', align: 'center' }),
            block('button', { text: 'Save my seat' }), block('text', { text: 'Can’t make it? Reply and we’ll send you the recording.', align: 'center' }), block('footer')] })],
        launch: ['Product launch', () => ({ subject: 'It’s here: meet our newest product', preheader: 'Built from your feedback — and on sale for launch week.', color: '#0f766e', blocks: [
            block('header'), block('heading', { text: 'Meet the new Product X', size: 32, align: 'center' }), block('text', { text: 'Built from hundreds of your ideas. Faster, simpler, and better looking.', align: 'center' }),
            block('image', { alt: 'Product X' }), block('button', { text: 'See it now' }),
            block('split', { title: 'Twice as fast', text: 'Explain the first big benefit in one or two sentences.', btn: '' }), block('split', { title: 'Easier than ever', text: 'Explain the second big benefit in one or two sentences.', btn: '', flip: true }),
            block('text', { text: 'Launch week only: **20% off** with code **LAUNCH20**.', align: 'center' }), block('button', { text: 'Get yours' }), block('social'), block('footer')] })],
    };
    const LOOK = { bg: '#f1f5f9', card: '#ffffff', ink: '#1f2937', font: 'Arial, Helvetica, sans-serif', platform: 'mailchimp', unsubUrl: '' };
    const fresh = key => { const t = T[key][1](); return { settings: { ...LOOK, subject: t.subject, preheader: t.preheader, color: t.color }, blocks: t.blocks }; };

    /* ------------------------------------------------ saved work (checked before use: it goes into the HTML) */
    const clean = d => {
        const f = fresh('newsletter'), s = d?.settings || {}, str = (v, n) => (typeof v === 'string' ? v.slice(0, n) : '');
        const settings = {
            subject: str(s.subject, 150), preheader: str(s.preheader, 200), color: hex(s.color, '#2563eb'), bg: hex(s.bg, '#f1f5f9'), card: hex(s.card, '#ffffff'), ink: hex(s.ink, '#1f2937'),
            font: Object.hasOwn(FONTS, s.font) ? s.font : f.settings.font, platform: Object.hasOwn(UNSUB, s.platform) ? s.platform : 'mailchimp', unsubUrl: str(s.unsubUrl, 500),
        };
        const blocks = (Array.isArray(d?.blocks) ? d.blocks : []).filter(b => b && Object.hasOwn(B, b.type)).slice(0, MAX).map(b => {
            const out = { id: /^[a-z0-9]{8}$/.test(b.id) ? b.id : uid(), type: b.type };
            Object.entries(B[b.type][2]).forEach(([k, v]) => {
                if (v && typeof v === 'object') out[k] = Object.fromEntries(Object.keys(v).map(kk => [kk, str(b[k]?.[kk], 500)]));
                else if (typeof v === 'number') { const fd = B[b.type][3].find(x => x[0] === k) || []; out[k] = Math.max(fd[3] ?? 0, Math.min(fd[4] ?? 200, +b[k] || v)); }
                else out[k] = typeof v === 'boolean' ? !!b[k] : str(b[k], 5000);
            });
            return out;
        });
        return d?.settings ? { settings, blocks } : f; // an emptied email keeps its settings
    };
    let E; try { E = clean(JSON.parse(localStorage.getItem(STORE) || 'null')); } catch { E = fresh('newsletter'); }
    const save = () => { try { localStorage.setItem(STORE, JSON.stringify(E)); } catch {} };
    let openId = null;

    /* ------------------------------------------------ the email HTML */
    const html = (opts = {}) => {
        const s = E.settings, c = s.color, ink = s.ink, f = s.font, muted = '#6b7280', P = 32;
        const pre = opts.preview ? (i, b) => ` data-b="${b.id}"` : () => ''; // preview only: click a block to edit it
        const row = (b, i, inner, style = '') => `<tr${pre(i, b)}><td class="px" style="padding:8px ${P}px;${style}">${inner}</td></tr>`;
        const btn = (text, url, a = 'center', full = false) => {
            const u = link(url); if (!String(text).trim()) return '';
            // Padding sits on the cell: Outlook ignores padding on links
            return `<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="${a === 'left' ? 'left' : 'center'}" style="border-collapse:separate${full ? ';width:100%' : ''}"><tr><td align="center" bgcolor="${c}" style="background:${c};border-radius:8px;padding:14px 28px"><a href="${esc(u || '#')}" style="display:inline-block;font-family:${f};font-size:16px;line-height:20px;font-weight:bold;color:#ffffff;text-decoration:none">${esc(text)}</a></td></tr></table>`;
        };
        const picture = (src, alt, w, url, radius = 8) => {
            const i = img(src); if (!i && !opts.preview) return ''; // empty images are left out of the real email
            const body = i ? `<img src="${esc(i)}" width="${w}" alt="${esc(alt)}" style="display:block;width:100%;max-width:${w}px;height:auto;border:0;border-radius:${radius}px">`
                : `<div style="background:#e5e7eb;border-radius:${radius}px;height:${Math.round(w * 0.5)}px;line-height:${Math.round(w * 0.5)}px;text-align:center;font-family:${f};font-size:14px;color:#6b7280">${opts.preview ? 'Image — add its address' : ''}</div>`;
            const u = link(url); return u && i ? `<a href="${esc(u)}">${body}</a>` : body;
        };
        const unsub = s.platform === 'custom' ? ownUnsub(s.unsubUrl) : UNSUB[s.platform][1];
        const rows = E.blocks.map((b, i) => {
            switch (b.type) {
                case 'header': {
                    const l = img(b.logo), a = align(b.align), w = Math.max(40, Math.min(300, +b.logoW || 160)); // Outlook needs a real width
                    return row(b, i, l ? `<img src="${esc(l)}" width="${w}" alt="${esc(b.name)}" style="display:${a === 'center' ? 'inline-block' : 'block'};width:${w}px;max-width:100%;height:auto;border:0">` : `<span style="font-family:${f};font-size:22px;font-weight:bold;color:${c}">${esc(b.name)}</span>`, `padding-top:28px;padding-bottom:20px;text-align:${a}`);
                }
                case 'heading': return row(b, i, `<h1 style="margin:0;font-family:${f};font-size:${Math.max(18, Math.min(44, +b.size || 28))}px;line-height:1.25;color:${ink};text-align:${align(b.align)}">${esc(b.text)}</h1>`, 'padding-top:16px');
                case 'text': return row(b, i, `<p style="margin:0;font-family:${f};font-size:16px;line-height:1.6;color:${ink};text-align:${align(b.align)}">${rich(b.text, c)}</p>`);
                case 'image': if (!img(b.src) && !opts.preview) return '';
                    return b.full ? `<tr${pre(i, b)}><td style="padding:8px 0">${picture(b.src, b.alt, 600, b.url, 0)}</td></tr>` : row(b, i, picture(b.src, b.alt, 536, b.url));
                case 'button': return row(b, i, btn(b.text, b.url, b.align, b.full), 'padding-top:16px;padding-bottom:16px');
                case 'divider': return row(b, i, `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-top:1px solid #e5e7eb;font-size:0;line-height:0">&nbsp;</td></tr></table>`, 'padding-top:16px;padding-bottom:16px');
                case 'spacer': return `<tr${pre(i, b)}><td style="height:${Math.max(4, Math.min(120, +b.h || 24))}px;font-size:0;line-height:0">&nbsp;</td></tr>`;
                case 'split': {
                    const hasPic = img(b.src) || opts.preview, half = hasPic ? '50%' : '100%'; // no image: the words take the whole row
                    const pic = hasPic ? `<td class="col" width="50%" valign="top" style="width:50%;padding:${b.flip ? '0 0 0 12px' : '0 12px 0 0'}">${picture(b.src, b.title, 256, b.url)}</td>` : '';
                    const u = link(b.url), more = String(b.btn).trim() && u ? `<p style="margin:10px 0 0"><a href="${esc(u)}" style="font-family:${f};font-size:15px;font-weight:bold;color:${c};text-decoration:none">${esc(b.btn)} →</a></p>` : '';
                    const words = `<td class="col" width="${half}" valign="middle" style="width:${half};padding:${!hasPic ? '0' : b.flip ? '0 12px 0 0' : '0 0 0 12px'}"><h2 style="margin:0 0 8px;font-family:${f};font-size:20px;line-height:1.3;color:${ink}">${esc(b.title)}</h2><p style="margin:0;font-family:${f};font-size:15px;line-height:1.6;color:${ink}">${rich(b.text, c)}</p>${more}</td>`;
                    return row(b, i, `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>${b.flip ? words + pic : pic + words}</tr></table>`, 'padding-top:16px;padding-bottom:16px');
                }
                case 'products': {
                    const card = (p, side) => `<td class="col" width="50%" valign="top" style="width:50%;padding:${side ? '0 0 0 8px' : '0 8px 0 0'}"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e5e7eb;border-radius:10px"><tr><td style="padding:12px">${picture(p.src, p.name, 234, p.url, 6)}
                        <p style="margin:12px 0 2px;font-family:${f};font-size:16px;font-weight:bold;color:${ink}">${esc(p.name)}</p><p style="margin:0 0 12px;font-family:${f};font-size:16px;color:${c};font-weight:bold">${esc(p.price)}</p>${btn(b.btn, p.url, 'left', true)}</td></tr></table></td>`;
                    return row(b, i, `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>${card(b.a, 0)}${card(b.b, 1)}</tr></table>`, 'padding-top:16px;padding-bottom:16px');
                }
                case 'social': {
                    // Only real profiles: a bare "instagram.com/" (the template's hint) is left out
                    const icons = Object.keys(SOCIAL).map(k => [k, link(b[k])]).filter(([, u]) => /^https?:/i.test(u) && new URL(u).pathname.length > 1);
                    if (!icons.length) return opts.preview ? row(b, i, `<p style="margin:0;font-family:${f};font-size:13px;color:#9ca3af;text-align:center">Social links — add your profile addresses</p>`) : '';
                    return row(b, i, `<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"><tr>${icons.map(([k, u]) => `<td width="32" height="32" align="center" bgcolor="${SOCIAL[k][2]}" style="width:32px;height:32px;background:${SOCIAL[k][2]};border-radius:50%"><a href="${esc(u)}" title="${SOCIAL[k][0]}" style="display:block;font-family:Arial,sans-serif;font-size:12px;font-weight:bold;line-height:32px;color:#ffffff;text-decoration:none">${esc(SOCIAL[k][1])}</a></td>`).join('<td width="8" style="width:8px">&nbsp;</td>')}</tr></table>`, 'padding-top:16px;padding-bottom:8px;text-align:center');
                }
                case 'footer': return row(b, i, `<p style="margin:0 0 6px;font-family:${f};font-size:12px;line-height:1.6;color:${muted};text-align:center"><b>${esc(b.company)}</b><br>${esc(b.address)}</p>
                    <p style="margin:0 0 6px;font-family:${f};font-size:12px;line-height:1.6;color:${muted};text-align:center">${esc(b.why)}</p>
                    <p style="margin:0;font-family:${f};font-size:12px;line-height:1.6;color:${muted};text-align:center">${unsub ? `<a href="${esc(unsub)}" style="color:${muted};text-decoration:underline">Unsubscribe</a>` : 'Add your unsubscribe link in Settings'}</p>`, 'padding-top:20px;padding-bottom:28px;border-top:1px solid #f1f5f9');
                default: return '';
            }
        }).join('\n');
        const preheader = s.preheader ? `<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:${s.bg}">${esc(s.preheader)}${'&nbsp;&zwnj;'.repeat(40)}</div>` : '';
        return `<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<title>${esc(s.subject || 'Email')}</title>
<style>
body{margin:0;padding:0;-webkit-text-size-adjust:100%}table{border-collapse:collapse}img{-ms-interpolation-mode:bicubic}a{color:${c}}
@media (max-width:620px){.wrap{width:100%!important}.px{padding-left:20px!important;padding-right:20px!important}.col{display:block!important;width:100%!important;padding:0 0 16px!important}}
${opts.preview ? '[data-b]{cursor:pointer}[data-b]:hover{outline:2px dashed rgba(37,99,235,.45);outline-offset:-2px}[data-b].is-on{outline:2px solid #2563eb;outline-offset:-2px}' : ''}
</style>
</head>
<body style="margin:0;padding:0;background:${s.bg}">
${preheader}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="${s.bg}" style="background:${s.bg}">
<tr><td align="center" style="padding:24px 12px">
<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
<table class="wrap" role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="${s.card}" style="width:600px;max-width:600px;background:${s.card};border-radius:12px">
${rows}
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr>
</table>
</body>
</html>`;
    };

    /* ------------------------------------------------ checks */
    const issues = () => {
        const out = [], s = E.settings;
        if (!s.subject.trim()) out.push('Add a subject line in Settings.');
        if (!E.blocks.some(b => b.type === 'footer')) out.push('Add a Footer block — emails need an unsubscribe link and your postal address.');
        if (s.platform === 'custom' && !ownUnsub(s.unsubUrl)) out.push('Add your unsubscribe link in Settings (a web address or your platform’s unsubscribe tag).');
        E.blocks.forEach(b => {
            const name = B[b.type][0];
            [b.src, b.logo, b.a?.src, b.b?.src].forEach(v => { if (v && !img(v)) out.push(`${name}: images must be online links starting with https://`); if (/drive\.google|dropbox\.com|1drv\.ms|onedrive/i.test(v || '')) out.push(`${name}: Drive, Dropbox and OneDrive links don’t show in emails — upload the image to your website or email platform.`); });
            if (b.type === 'button' && b.text.trim() && !link(b.url)) out.push(`Button “${b.text}” needs a link.`);
            if ((b.type === 'image' || b.type === 'split') && !img(b.src)) out.push(`${name}: add an image address — empty images are left out of the email.`);
            if (b.type === 'products' && (!img(b.a.src) || !img(b.b.src))) out.push('Two products: add both product images.');
            if (b.type === 'products') ['a', 'b'].forEach(k => { if (!link(b[k].url)) out.push(`${b[k].name || 'Product'}: add a link.`); });
        });
        return [...new Set(out)];
    };

    /* ------------------------------------------------ editor */
    const fieldHtml = (b, [k, label, kind, a1, a2]) => {
        const v = b[k];
        if (kind === 'area') return `<label>${label}<textarea data-k="${k}" rows="4" maxlength="5000">${esc(v)}</textarea></label>`;
        if (kind === 'select') return `<label>${label}<select data-k="${k}">${Object.entries(a1).map(([o, l]) => `<option value="${o}" ${v === o ? 'selected' : ''}>${l}</option>`).join('')}</select></label>`;
        if (kind === 'num') return `<label>${label}<input data-k="${k}" type="number" min="${a1}" max="${a2}" value="${esc(v)}"></label>`;
        if (kind === 'check') return `<label class="fb-check"><input type="checkbox" data-k="${k}" ${v ? 'checked' : ''}> ${label}</label>`;
        if (kind === 'product') return `<fieldset class="eb-product"><legend>${label}</legend>${[['src', 'Image address (https://…)'], ['name', 'Name'], ['price', 'Price'], ['url', 'Link']].map(([kk, l]) => `<label>${l}<input data-k="${k}.${kk}" maxlength="500" value="${esc(v[kk])}"></label>`).join('')}</fieldset>`;
        return `<label>${label}<input data-k="${k}" maxlength="${kind === 'text' ? 300 : 500}" value="${esc(v)}" ${kind === 'img' || kind === 'url' ? 'placeholder="https://…" inputmode="url"' : ''}></label>`;
    };
    const summary = b => esc(String(b.text || b.title || b.name || b.alt || b.a?.name || '').split('\n')[0].replace(/\*\*/g, '').slice(0, 60));
    const drawBlocks = () => {
        const n = E.blocks.length;
        $('[data-blocks]').innerHTML = E.blocks.map((b, i) => {
            const [label, icon, , fields] = B[b.type], open = b.id === openId;
            return `<div class="fb-q${open ? ' is-open' : ''}" data-i="${i}"><div class="fb-q__head" data-toggle="${b.id}"><span class="fb-q__icon" aria-hidden="true">${esc(icon)}</span><span class="fb-q__label">${label}${summary(b) ? ` <small>· ${summary(b)}</small>` : ''}</span>
                <span class="fb-q__tools"><button type="button" data-move="-1" title="Move up" ${i ? '' : 'disabled'}>↑</button><button type="button" data-move="1" title="Move down" ${i < n - 1 ? '' : 'disabled'}>↓</button><button type="button" data-dup title="Duplicate">⧉</button><button type="button" data-del title="Delete" class="fb-del">✕</button></span></div>
                ${open ? `<div class="fb-q__body">${fields.map(fl => fieldHtml(b, fl)).join('') || '<p class="fb-hint">Nothing to set.</p>'}</div>` : ''}</div>`;
        }).join('') || '<p class="fb-hint">Your email is empty — add a block below.</p>';
    };
    const drawSettings = () => $$('[data-set]').forEach(el => (el.value = E.settings[el.dataset.set] ?? ''));
    const syncUnsub = () => { $('[data-unsub-url]').hidden = E.settings.platform !== 'custom'; };

    let device = 'desktop', frameTimer = 0;
    const frame = $('[data-preview]');
    const drawPreview = () => {
        clearTimeout(frameTimer);
        frameTimer = setTimeout(() => {
            const y = frame.contentDocument?.scrollingElement?.scrollTop || 0;
            frame.srcdoc = html({ preview: true });
            frame.onload = () => {
                const d = frame.contentDocument; d.scrollingElement.scrollTop = y;
                d.querySelector(`[data-b="${openId}"]`)?.classList.add('is-on');
                d.addEventListener('click', e => { e.preventDefault(); const r = e.target.closest('[data-b]'); if (r) { openId = r.dataset.b; drawBlocks(); $('[data-blocks] .is-open')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); markPreview(); } });
            };
        }, 120);
        $('[data-issues]').innerHTML = issues().map(m => `<li>${esc(m)}</li>`).join('');
    };
    /** Desktop preview: draw the email at a real desktop width (680px) and shrink it to fit the panel. */
    const fit = () => {
        const sc = $('.eb-screen');
        if (device !== 'desktop') { frame.style.width = frame.style.height = frame.style.transform = ''; return; }
        const k = Math.min(1, sc.clientWidth / 680);
        Object.assign(frame.style, { width: '680px', height: sc.clientHeight / k + 'px', transform: `scale(${k})`, transformOrigin: 'top left' });
    };
    addEventListener('resize', fit);
    const markPreview = () => { const d = frame.contentDocument; if (!d) return; d.querySelectorAll('[data-b].is-on').forEach(el => el.classList.remove('is-on')); d.querySelector(`[data-b="${openId}"]`)?.classList.add('is-on'); };
    const changed = () => { save(); drawPreview(); };

    $('[data-add]').innerHTML = Object.entries(B).map(([k, v]) => `<button type="button" data-add-block="${k}"><span aria-hidden="true">${esc(v[1])}</span>${v[0]}</button>`).join('');
    $('[data-templates]').innerHTML = Object.entries(T).map(([k, v]) => `<button type="button" class="fb-chip" data-tpl="${k}">${esc(v[0])}</button>`).join('');
    $('[data-set="font"]').innerHTML = Object.entries(FONTS).map(([k, v]) => `<option value="${esc(k)}">${v}</option>`).join('');
    $('[data-set="platform"]').innerHTML = Object.entries(UNSUB).map(([k, v]) => `<option value="${k}">${v[0]}</option>`).join('');

    root.addEventListener('input', e => {
        const el = e.target;
        if (el.dataset.set) { E.settings[el.dataset.set] = el.value; if (el.dataset.set === 'platform') syncUnsub(); return changed(); }
        const k = el.dataset.k, card = el.closest('[data-i]'); if (!k || !card) return;
        const b = E.blocks[+card.dataset.i], [p, sub] = k.split('.');
        const v = el.type === 'checkbox' ? el.checked : el.type === 'number' ? +el.value : el.value;
        if (sub) b[p][sub] = v; else b[p] = v;
        const lbl = card.querySelector('.fb-q__label'); lbl.innerHTML = `${B[b.type][0]}${summary(b) ? ` <small>· ${summary(b)}</small>` : ''}`;
        changed();
    });
    root.addEventListener('click', async e => {
        const t = e.target.closest('button,[data-toggle]'); if (!t) return;
        const card = t.closest('[data-i]'), i = card ? +card.dataset.i : -1, L = E.blocks;
        if ((t.dataset.addBlock || 'dup' in t.dataset) && L.length >= MAX) return note(`An email can have up to ${MAX} blocks.`);
        if (t.dataset.addBlock) { const b = block(t.dataset.addBlock); const at = L.findIndex(x => x.id === openId); L.splice(at >= 0 ? at + 1 : L.length, 0, b); openId = b.id; drawBlocks(); changed(); track('email_builder_add', { type: b.type }); $('[data-blocks] .is-open')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
        else if (t.dataset.move) { const j = i + +t.dataset.move; [L[i], L[j]] = [L[j], L[i]]; drawBlocks(); changed(); }
        else if ('dup' in t.dataset) { const c = { ...structuredClone(L[i]), id: uid() }; L.splice(i + 1, 0, c); openId = c.id; drawBlocks(); changed(); }
        else if ('del' in t.dataset) { L.splice(i, 1); drawBlocks(); changed(); }
        else if (t.dataset.toggle) { if (e.target.closest('.fb-q__tools')) return; openId = openId === t.dataset.toggle ? null : t.dataset.toggle; drawBlocks(); markPreview(); }
        else if (t.dataset.tpl) {
            if (!(await window.sweet({ icon: 'warning', title: 'Use this template?', text: 'Your current email will be replaced. Your email platform and font stay as they are.', confirm: 'Replace my email', cancel: 'Keep mine' }))) return; const { platform, unsubUrl, font } = E.settings; E = fresh(t.dataset.tpl); Object.assign(E.settings, { platform, unsubUrl, font }); openId = null; drawSettings(); syncUnsub(); drawBlocks(); changed(); track('email_builder_template', { tpl: t.dataset.tpl }); }
        else if (t.dataset.device) { device = t.dataset.device; $$('.eb-devices [data-device]').forEach(x => x.setAttribute('aria-pressed', x === t ? 'true' : 'false')); $('.eb-screen').dataset.device = device; fit(); }
    });

    /* ------------------------------------------------ export */
    const note = msg => { const n = $('[data-note]'); n.textContent = msg; n.hidden = false; clearTimeout(note.t); note.t = setTimeout(() => (n.hidden = true), 4500); };
    /** Problems found? Show them (styled dialog) and let the user go back or export anyway. */
    const ready = async () => {
        const p = issues(); if (!p.length) return true;
        return window.sweet({ icon: 'warning', title: 'Check these before you send', html: `<ul class="eb-sweet-list">${p.map(m => `<li>${esc(m)}</li>`).join('')}</ul>`, confirm: 'Export anyway', cancel: 'Go back and fix' });
    };
    $('[data-copy]').addEventListener('click', async () => {
        if (!(await ready())) return;
        try { await navigator.clipboard.writeText(html()); note('✅ HTML copied — paste it into your email platform’s “code” or “HTML” editor.'); }
        catch { note('Your browser blocked copying — use Download instead.'); }
        track('email_builder_copy');
    });
    $('[data-download]').addEventListener('click', async () => {
        if (!(await ready())) return;
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([html()], { type: 'text/html' }));
        a.download = ((E.settings.subject || 'email').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 50) || 'email') + '.html';
        document.body.append(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 2000);
        track('email_builder_download');
    });

    // "Send it with your email platform" tabs (below the builder)
    const how = document.querySelector('.eb-how');
    how?.addEventListener('click', e => {
        const t = e.target.closest('[data-how]'); if (!t) return;
        how.querySelectorAll('[data-how]').forEach(x => x.setAttribute('aria-selected', x === t ? 'true' : 'false'));
        how.querySelectorAll('[data-how-panel]').forEach(p => (p.hidden = p.dataset.howPanel !== t.dataset.how));
    });

    drawSettings(); syncUnsub(); drawBlocks(); drawPreview(); fit();
})();
