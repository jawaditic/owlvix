/* Owlvix free invoice generator (/invoice-generator). Everything stays in the browser (localStorage): invoices, clients
   and bank details are never uploaded. The invoice is drawn as an A4 page in a preview frame and printed to PDF from a
   clean window, like the CV builder. One data model for every design:
   { id, design, color, font, logo, kind, number, ref, issued, due, currency, paid, from{}, to{}, items[], discount{}, tax{},
     shipping, deposit, payment, notes }. Earlier invoices are kept in "My invoices" (owlvix-invoices, newest first). */
(() => {
    const root = document.querySelector('[data-ib]');
    if (!root) return;
    const $ = (s, el = root) => el.querySelector(s), $$ = (s, el = root) => [...el.querySelectorAll(s)];
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const hex = (c, d) => (/^#[0-9a-f]{6}$/i.test(c || '') ? c : d);
    const uid = () => (Math.random().toString(36).slice(2) + '00000000').slice(0, 8);
    const track = (e, d = {}) => window.track?.(e, d);
    /** Styled dialog (window.sweet). If its script hasn't loaded after a short wait, questions answer "no" (nothing is
        deleted unasked) and the message shows in the status line instead. */
    const ask = async o => {
        for (let i = 0; i < 20 && !window.sweet; i++) await new Promise(r => setTimeout(r, 100));
        if (window.sweet) return window.sweet(o);
        $('[data-saved]').textContent = `${o.title}${o.cancel === false ? '' : ' — please reload the page and try again'}`;
        return o.cancel === false;
    };

    const STORE = 'owlvix-invoice', LIST = 'owlvix-invoices', MAX_LIST = 60, MAX_ITEMS = 40;
    const DESIGNS = { classic: 'Classic', modern: 'Modern (colour band)', bold: 'Bold', minimal: 'Minimal' };
    const FONTS = { "'Inter', Arial, sans-serif": 'Inter (clean)', "Georgia, 'Times New Roman', serif": 'Georgia (classic)', "'Lato', Arial, sans-serif": 'Lato (friendly)', "Arial, Helvetica, sans-serif": 'Arial (safe)' };
    // kind => [title on the page, label of the second date, show "Balance due"]
    const KINDS = {
        invoice: ['Invoice', 'Due date', true], tax: ['Tax invoice', 'Due date', true], proforma: ['Proforma invoice', 'Due date', true],
        quote: ['Quote', 'Valid until', false], estimate: ['Estimate', 'Valid until', false], receipt: ['Receipt', '', false],
    };
    const CURRENCIES = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'TRY', 'INR', 'PKR', 'BDT', 'LKR', 'NPR',
        'AED', 'SAR', 'QAR', 'KWD', 'BHD', 'OMR', 'EGP', 'MAD', 'NGN', 'GHS', 'KES', 'UGX', 'TZS', 'ZAR', 'SGD', 'MYR', 'IDR', 'PHP', 'THB', 'VND',
        'JPY', 'KRW', 'CNY', 'HKD', 'TWD', 'BRL', 'MXN', 'ARS', 'CLP', 'COP', 'PEN'];
    const LOCALE = (() => { try { return new Intl.NumberFormat(navigator.language).resolvedOptions().locale; } catch { return 'en-GB'; } })();
    /** A sensible first currency from the browser's language region (en-GB → GBP …). */
    const guessCurrency = () => {
        const region = (LOCALE.split('-')[1] || '').toUpperCase();
        const EURO = 'AT BE CY DE EE ES FI FR GR HR IE IT LT LU LV MT NL PT SI SK'.split(' ');
        const BY = { GB: 'GBP', US: 'USD', CA: 'CAD', AU: 'AUD', NZ: 'NZD', IN: 'INR', PK: 'PKR', BD: 'BDT', AE: 'AED', SA: 'SAR', ZA: 'ZAR', NG: 'NGN', KE: 'KES', SG: 'SGD', MY: 'MYR', PH: 'PHP', JP: 'JPY', CH: 'CHF', SE: 'SEK', NO: 'NOK', DK: 'DKK', PL: 'PLN', TR: 'TRY', BR: 'BRL', MX: 'MXN', EG: 'EGP', QA: 'QAR' };
        return EURO.includes(region) ? 'EUR' : BY[region] || 'USD';
    };

    const PREFIX = { invoice: 'INV', tax: 'INV', proforma: 'PRO', quote: 'QUO', estimate: 'EST', receipt: 'REC' };
    /** Switching type swaps a standard prefix (INV-0007 → QUO-0007); a number they made up stays as it is. */
    const setKind = k => { I.kind = k; I.number = String(I.number).replace(/^(INV|PRO|QUO|EST|REC)(?=-\d+$)/, PREFIX[k]); };

    /* ------------------------------------------------ dates (stored as yyyy-mm-dd, shown as "2 Nov 2026") */
    const iso = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const today = () => iso(new Date());
    const addDays = (s, n) => { const d = new Date((/^\d{4}-\d{2}-\d{2}$/.test(s) ? s : today()) + 'T12:00:00'); d.setDate(d.getDate() + n); return iso(d); };
    const showDate = s => { if (!/^\d{4}-\d{2}-\d{2}$/.test(s || '')) return ''; try { return new Date(s + 'T12:00:00').toLocaleDateString(LOCALE, { day: 'numeric', month: 'short', year: 'numeric' }); } catch { return s; } };
    const daysBetween = (a, b) => Math.round((new Date(b + 'T12:00:00') - new Date(a + 'T12:00:00')) / 864e5);

    /* ------------------------------------------------ money: worked out in whole minor units (cents) so totals add up */
    const fmtCache = {};
    const fmt = cur => fmtCache[cur] || (fmtCache[cur] = (() => { try { return new Intl.NumberFormat(LOCALE, { style: 'currency', currency: cur }); } catch { return new Intl.NumberFormat(LOCALE, { style: 'currency', currency: 'USD' }); } })());
    const digits = cur => fmt(cur).resolvedOptions().maximumFractionDigits;
    const num = v => { const n = parseFloat(String(v ?? '').replace(',', '.')); return Number.isFinite(n) ? n : 0; };
    const minor = (v, cur) => Math.round(num(v) * 10 ** digits(cur));
    const money = (m, cur) => fmt(cur).format(m / 10 ** digits(cur));
    const totals = I => {
        const c = I.currency, f = 10 ** digits(c);
        const lines = I.items.map(x => Math.round(num(x.qty) * num(x.price) * f));
        const sub = lines.reduce((a, b) => a + b, 0);
        const dv = Math.max(0, num(I.discount.value));
        const discount = Math.min(sub, I.discount.kind === 'percent' ? Math.round(sub * Math.min(dv, 100) / 100) : Math.round(dv * f));
        const rate = Math.min(100, Math.max(0, num(I.tax.rate)));
        // prices that already include tax: the tax is the part inside the total; otherwise it's added on top
        const taxable = sub - discount;
        const tax = I.tax.included ? Math.round(taxable - taxable / (1 + rate / 100)) : Math.round(taxable * rate / 100);
        const shipping = Math.max(0, minor(I.shipping, c));
        const total = taxable + (I.tax.included ? 0 : tax) + shipping;
        const deposit = KINDS[I.kind][2] ? Math.min(total, Math.max(0, minor(I.deposit, c))) : 0;
        return { lines, sub, discount, rate, tax, shipping, total, deposit, due: I.paid || I.kind === 'receipt' ? 0 : total - deposit };
    };

    /* ------------------------------------------------ data */
    const item = (o = {}) => ({ id: uid(), desc: '', qty: '1', price: '', ...o });
    const blank = (keep = {}) => ({
        id: uid(), design: 'classic', color: '#1e40af', font: "'Inter', Arial, sans-serif", logo: '', kind: 'invoice', number: 'INV-0001', ref: '',
        issued: today(), due: addDays(today(), 14), currency: guessCurrency(), paid: false,
        from: { name: '', email: '', phone: '', address: '', taxLabel: 'VAT no.', taxId: '' }, to: { name: '', email: '', address: '', taxId: '' },
        items: [item()], discount: { kind: 'percent', value: '' }, tax: { label: 'VAT', rate: '', included: false }, shipping: '', deposit: '',
        payment: '', notes: 'Thank you for your business!', ...keep,
    });
    const sample = () => blank({
        design: 'modern', color: '#1e40af', number: 'INV-0042', ref: 'PO-7781',
        from: { name: 'Brightside Studio', email: 'hello@brightside.studio', phone: '+44 20 7946 0958', address: '12 Market Street\nManchester M1 1AE\nUnited Kingdom', taxLabel: 'VAT no.', taxId: 'GB123 4567 89' },
        to: { name: 'Northwind Ltd', email: 'accounts@northwind.co.uk', address: 'Attn: Priya Shah\n48 Park Road\nLeeds LS1 2AB', taxId: '' },
        items: [item({ desc: 'Website design — 5 pages', qty: '1', price: '1800' }), item({ desc: 'Copywriting (hours)', qty: '6', price: '45' }), item({ desc: 'Hosting & care plan — 12 months', qty: '1', price: '240' })],
        discount: { kind: 'percent', value: '10' }, tax: { label: 'VAT', rate: '20', included: false },
        payment: 'Bank transfer to Brightside Studio\nSort code 00-00-00 · Account 12345678\nPlease use the invoice number as the reference.',
        notes: 'Thank you for your business! Payment is due within 14 days.',
    });

    /* saved data is checked before use (it goes into the page) */
    const str = (v, n) => (typeof v === 'string' ? v.slice(0, n) : typeof v === 'number' ? String(v).slice(0, n) : '');
    const dateOk = v => (/^\d{4}-\d{2}-\d{2}$/.test(v || '') ? v : '');
    const numStr = v => { const s = str(v, 20).trim(); return s === '' || Number.isFinite(+s.replace(',', '.')) ? s : ''; };
    const clean = d => {
        if (!d || typeof d !== 'object') return sample();
        const f = d.from || {}, t = d.to || {}, ds = d.discount || {}, tx = d.tax || {};
        const items = (Array.isArray(d.items) ? d.items : []).slice(0, MAX_ITEMS).filter(x => x && typeof x === 'object').map(x => item({ desc: str(x.desc, 500), qty: numStr(x.qty), price: numStr(x.price) }));
        return {
            id: /^[a-z0-9]{8}$/.test(d.id || '') ? d.id : uid(),
            design: Object.hasOwn(DESIGNS, d.design) ? d.design : 'classic', color: hex(d.color, '#1e40af'), font: Object.hasOwn(FONTS, d.font) ? d.font : "'Inter', Arial, sans-serif",
            logo: /^data:image\/(jpeg|png|webp);base64,[a-z0-9+/=]+$/i.test(d.logo || '') && d.logo.length < 300000 ? d.logo : '',
            kind: Object.hasOwn(KINDS, d.kind) ? d.kind : 'invoice', number: str(d.number, 40), ref: str(d.ref, 60),
            issued: dateOk(d.issued), due: dateOk(d.due), currency: CURRENCIES.includes(d.currency) ? d.currency : guessCurrency(), paid: !!d.paid,
            from: { name: str(f.name, 120), email: str(f.email, 120), phone: str(f.phone, 60), address: str(f.address, 400), taxLabel: str(f.taxLabel, 30), taxId: str(f.taxId, 60) },
            to: { name: str(t.name, 120), email: str(t.email, 120), address: str(t.address, 400), taxId: str(t.taxId, 60) },
            items: items.length ? items : [item()],
            discount: { kind: ds.kind === 'amount' ? 'amount' : 'percent', value: numStr(ds.value) },
            tax: { label: str(tx.label, 30), rate: numStr(tx.rate), included: !!tx.included },
            shipping: numStr(d.shipping), deposit: numStr(d.deposit), payment: str(d.payment, 1000), notes: str(d.notes, 1000),
        };
    };
    const read = (k, dflt) => { try { return JSON.parse(localStorage.getItem(k) || 'null') ?? dflt; } catch { return dflt; } };
    let I = clean(read(STORE, null));
    /** "My invoices": earlier documents (without the logo, which is the business's and kept on the current one). */
    let mine = (Array.isArray(read(LIST, [])) ? read(LIST, []) : []).slice(0, MAX_LIST).map(clean).map(x => ({ ...x, logo: '' }));
    const status = t => { $('[data-saved]').textContent = t; };
    const save = () => {
        try { localStorage.setItem(STORE, JSON.stringify(I)); status('Saved on this device'); }
        catch { status('Too big to save here — try a smaller logo'); }
    };
    const saveList = () => { try { localStorage.setItem(LIST, JSON.stringify(mine)); return true; } catch { return false; } };
    /** Put the open invoice in "My invoices" (replacing its older copy). Untouched blanks and the example aren't kept. */
    const keep = () => {
        if (isSample() || isBlank()) return;
        mine = [{ ...I, logo: '' }, ...mine.filter(x => x.id !== I.id)].slice(0, MAX_LIST);
        saveList();
    };
    const isBlank = () => !I.to.name.trim() && I.items.every(x => !x.desc.trim() && !num(x.price));
    const isSample = () => { const s = sample(); return I.from.name === s.from.name && I.to.name === s.to.name && JSON.stringify(I.items.map(x => [x.desc, x.qty, x.price])) === JSON.stringify(s.items.map(x => [x.desc, x.qty, x.price])); };
    /** INV-0042 → INV-0043 (keeps the zeros); a number with no digits gets "-2". */
    const nextNumber = n => {
        const m = String(n || '').match(/^(.*?)(\d+)(\D*)$/);
        if (!m) return (n || 'INV') + '-2';
        return m[1] + String(+m[2] + 1).padStart(m[2].length, '0') + m[3];
    };
    const highestNumber = () => {
        let best = I.number;
        for (const x of [I, ...mine]) { const a = +(String(x.number).match(/(\d+)\D*$/) || [0, -1])[1], b = +(String(best).match(/(\d+)\D*$/) || [0, -1])[1]; if (a > b) best = x.number; }
        return best;
    };

    /* ------------------------------------------------ the invoice page (A4, 210 × 297 mm) */
    const lines = t => String(t || '').split('\n').map(s => s.trim()).filter(Boolean);
    const linkFor = (k, v) => {
        const t = String(v || '').trim(); if (!t) return '';
        const href = k === 'email' ? 'mailto:' + t.replace(/[^\w.+@-]/g, '') : k === 'phone' ? 'tel:' + t.replace(/[^\d+]/g, '') : '';
        return href && !/["'<>\s]/.test(href) ? `<a href="${esc(href)}">${esc(t)}</a>` : esc(t);
    };
    const page = (forPrint = false) => {
        const c = I.color, d = I.design, K = KINDS[I.kind], T = totals(I), cur = I.currency, has = v => String(v || '').trim();
        const lum = [1, 3, 5].map(k => parseInt(c.substr(k, 2), 16) / 255).reduce((a, v, k) => a + v * [0.2126, 0.7152, 0.0722][k], 0), on = lum > 0.6 ? '#111827' : '#ffffff';
        const F = I.from, To = I.to;
        const fromLines = [...lines(F.address).map(esc), has(F.email) && linkFor('email', F.email), has(F.phone) && linkFor('phone', F.phone), has(F.taxId) && `${esc(F.taxLabel || 'Tax no.')} ${esc(F.taxId)}`].filter(Boolean);
        const toLines = [...lines(To.address).map(esc), has(To.email) && linkFor('email', To.email), has(To.taxId) && `${esc(F.taxLabel || 'Tax no.')} ${esc(To.taxId)}`].filter(Boolean);
        const meta = [
            [`${K[0]} no.`, I.number], ['Date', showDate(I.issued)], K[1] && [K[1], showDate(I.due)], has(I.ref) && ['Reference', I.ref],
        ].filter(x => x && has(x[1])).map(([k, v]) => `<tr><th>${esc(k)}</th><td>${esc(v)}</td></tr>`).join('');
        const rows = I.items.map((x, i) => (has(x.desc) || num(x.price) ? `<tr><td class="desc">${lines(x.desc).map(esc).join('<br>')}</td><td class="n">${esc(x.qty || '1')}</td><td class="n">${money(minor(x.price, cur), cur)}</td><td class="n">${money(T.lines[i], cur)}</td></tr>` : '')).join('');
        const sum = [
            ['Subtotal', money(T.sub, cur)],
            T.discount && [`Discount${I.discount.kind === 'percent' ? ` (${esc(I.discount.value)}%)` : ''}`, '−' + money(T.discount, cur)],
            T.rate && [`${esc(I.tax.label || 'Tax')} (${esc(I.tax.rate)}%)${I.tax.included ? ' included' : ''}`, money(T.tax, cur)],
            T.shipping && ['Shipping', money(T.shipping, cur)],
            ['Total', money(T.total, cur), 'total'],
            T.deposit && ['Paid', '−' + money(T.deposit, cur)],
            T.deposit && !I.paid && ['Balance due', money(T.due, cur), 'due'],
        ].filter(Boolean);
        if (!T.deposit || I.paid) sum.find(x => x[2] === 'total')[2] = 'total due'; // the last line stands out
        const sumHtml = sum.map(([k, v, cls]) => `<tr${cls ? ` class="${cls}"` : ''}><th>${k}</th><td>${v}</td></tr>`).join('');
        const stamp = I.paid || I.kind === 'receipt' ? '<div class="stamp">Paid</div>' : '';
        const logo = I.logo ? `<img class="logo" src="${I.logo}" alt="">` : '';
        const brand = `<div class="brand">${logo}<div><p class="biz">${esc(F.name || 'Your business')}</p>${fromLines.length ? `<p class="small">${fromLines.join('<br>')}</p>` : ''}</div></div>`;
        const title = `<div class="title"><h1>${esc(K[0])}</h1><table class="meta">${meta}</table></div>`;
        const head = d === 'modern' ? `<header class="band">${brand}${title}</header>` : `<header class="head">${brand}${title}</header>`;
        const bill = `<section class="bill"><div><h2>${I.kind === 'quote' || I.kind === 'estimate' ? 'Prepared for' : 'Bill to'}</h2><p class="who">${esc(To.name || 'Client name')}</p>${toLines.length ? `<p class="small">${toLines.join('<br>')}</p>` : ''}</div>
            ${K[2] && !I.paid ? `<div class="amount"><h2>${T.deposit ? 'Balance due' : 'Amount due'}</h2><p>${money(T.due, cur)}</p>${K[1] && has(showDate(I.due)) ? `<span>by ${esc(showDate(I.due))}</span>` : ''}</div>` : ''}</section>`;
        const table = `<table class="items"><thead><tr><th>Description</th><th class="n">Qty</th><th class="n">Price</th><th class="n">Amount</th></tr></thead><tbody>${rows || '<tr><td class="desc" colspan="4">Add what you’re charging for.</td></tr>'}</tbody></table>`;
        const foot = `<div class="end"><div class="notes">${has(I.payment) ? `<section><h2>How to pay</h2><p>${lines(I.payment).map(esc).join('<br>')}</p></section>` : ''}${has(I.notes) ? `<section><h2>Notes</h2><p>${lines(I.notes).map(esc).join('<br>')}</p></section>` : ''}</div><table class="sum">${sumHtml}</table></div>`;
        const css = `@page{size:A4;margin:12mm 0}@page:first{margin-top:0}*{box-sizing:border-box}html{-webkit-print-color-adjust:exact;print-color-adjust:exact}html,body{margin:0;background:${forPrint ? '#fff' : '#e2e8f0'}}
body{overflow-wrap:anywhere;font-family:${I.font};color:#1f2937;font-size:10pt;line-height:1.45}
.sheet{position:relative;width:210mm;min-height:${forPrint ? '0' : '296mm'};margin:${forPrint ? 0 : '0 auto'};background:#fff;padding:16mm 16mm 14mm;${forPrint ? '' : 'box-shadow:0 10px 30px rgba(15,23,42,.18);'}}
a{color:inherit;text-decoration:none}p{margin:0}h1{margin:0;font-size:24pt;line-height:1.1;letter-spacing:-.01em;color:${c}}
h2{margin:0 0 6px;font-size:8.5pt;letter-spacing:.12em;text-transform:uppercase;color:#6b7280;font-weight:600}
.head,.band{display:flex;justify-content:space-between;gap:12mm;align-items:flex-start;margin-bottom:10mm}
.brand{display:flex;gap:12px;align-items:flex-start;min-width:0}.logo{max-width:46mm;max-height:22mm;object-fit:contain;flex:none}
.biz{font-size:13pt;font-weight:700;color:#111827;margin-bottom:3px}.small{font-size:9pt;color:#4b5563}
.title{text-align:right;flex:none;max-width:85mm}.meta{margin:8px 0 0 auto;border-collapse:collapse;font-size:9pt}.meta th{font-weight:500;color:#6b7280;text-align:right;padding:1px 10px 1px 0;white-space:nowrap}.meta td{text-align:right;font-weight:600;color:#111827;padding:1px 0}
.bill{display:flex;justify-content:space-between;gap:10mm;margin-bottom:8mm}.who{font-size:11.5pt;font-weight:700;color:#111827;margin-bottom:2px}
.amount{text-align:right;flex:none}.amount p{font-size:18pt;font-weight:700;color:${c};line-height:1.15}.amount span{font-size:9pt;color:#6b7280}
table.items{width:100%;border-collapse:collapse;margin-bottom:6mm}table.items th{font-size:8.5pt;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;text-align:left;padding:7px 8px;border-bottom:1.5px solid ${c}}
table.items td{padding:8px;border-bottom:1px solid #e5e7eb;vertical-align:top}table.items tr{break-inside:avoid}.n{text-align:right!important;white-space:nowrap;width:1%}.desc{color:#111827}
.end{display:flex;justify-content:space-between;gap:10mm;align-items:flex-start;break-inside:avoid}.notes{flex:1;min-width:0;font-size:9pt;color:#374151}.notes section{margin-bottom:5mm}
table.sum{border-collapse:collapse;min-width:72mm;font-size:10pt}table.sum th{text-align:left;font-weight:500;color:#4b5563;padding:4px 14px 4px 0}table.sum td{text-align:right;padding:4px 0;white-space:nowrap;color:#111827}
table.sum tr.total th,table.sum tr.total td{border-top:1.5px solid #111827;padding-top:7px;font-weight:700;font-size:11pt;color:#111827}
table.sum tr.due th,table.sum tr.due td{background:${c};color:${on};font-weight:700;font-size:11pt;padding:8px 10px}
.stamp{position:absolute;top:58mm;right:22mm;transform:rotate(-12deg);border:3px solid #16a34a;color:#16a34a;border-radius:8px;padding:4px 16px;font:800 22pt/1 Arial,sans-serif;letter-spacing:.12em;text-transform:uppercase;opacity:.8}
/* modern */.d-modern .sheet{padding:0 0 14mm}.d-modern .band{background:${c};color:${on};padding:12mm 16mm;margin-bottom:9mm}.d-modern .band h1,.d-modern .band .biz{color:${on}}.d-modern .band .small,.d-modern .band .meta th{color:${on};opacity:.85}.d-modern .band .meta td{color:${on}}
.d-modern .bill,.d-modern table.items,.d-modern .end{margin-left:16mm;margin-right:16mm}.d-modern table.items{width:calc(100% - 32mm)}
/* bold */.d-bold .sheet{border-top:9mm solid ${c}}.d-bold h1{font-size:30pt;text-transform:uppercase;letter-spacing:.04em}.d-bold table.items thead th{background:${c};color:${on};border:0}.d-bold table.items tbody tr:nth-child(even) td{background:${c}0d}
/* minimal */.d-minimal h1{color:#111827;font-weight:600;font-size:20pt}.d-minimal table.items th{border-bottom-color:#111827}.d-minimal .amount p{color:#111827}.d-minimal table.sum tr.due th,.d-minimal table.sum tr.due td{background:none;color:#111827;border-top:1.5px solid #111827;padding:7px 0}
@media print{html,body{background:#fff}.sheet{box-shadow:none;margin:0}}`;
        return `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>${esc(`${K[0]} ${I.number || ''}${To.name ? ' — ' + To.name : ''}`.trim())}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lato:wght@400;700&display=swap" rel="stylesheet">
<style>${css}</style></head><body class="d-${d}"><div class="sheet">${stamp}${head}${bill}${table}${foot}</div>${forPrint ? '<script>addEventListener("load",()=>(document.fonts?document.fonts.ready:Promise.resolve()).then(()=>setTimeout(()=>print(),150)))<\/script>' : '<style>a{pointer-events:none}</style>'}</body></html>`;
    };

    /* ------------------------------------------------ "before you send" checks */
    const tips = () => {
        const out = [], T = totals(I);
        if (!I.from.name.trim()) out.push('Add your business name.');
        if (!I.from.email.trim() && !I.from.phone.trim()) out.push('Add your email or phone so your client can reach you.');
        if (!I.to.name.trim()) out.push('Add who the invoice is for.');
        if (!I.number.trim()) out.push('Give it a number — every invoice needs its own.');
        else if (mine.some(x => x.id !== I.id && x.number.trim() === I.number.trim() && x.kind === I.kind)) out.push(`You already have a ${KINDS[I.kind][0].toLowerCase()} numbered “${I.number}” in My invoices.`);
        if (!T.sub) out.push('Add what you’re charging for, with a price.');
        if (KINDS[I.kind][2] && !I.paid && !I.payment.trim()) out.push('Add how to pay (bank details, PayPal or a payment link) — it gets you paid faster.');
        if (KINDS[I.kind][1] && I.issued && I.due && daysBetween(I.issued, I.due) < 0) out.push('The due date is before the invoice date.');
        if (I.kind === 'tax' && !I.from.taxId.trim()) out.push('A tax invoice needs your tax or VAT number.');
        return out;
    };

    /* ------------------------------------------------ editor */
    const fld = (path, label, val, o = {}) => o.area
        ? `<label${o.wide ? ' class="rb-wide"' : ''}>${label}${o.hint ? ` <small>${o.hint}</small>` : ''}<textarea data-p="${path}" rows="${o.rows || 3}" maxlength="${o.max || 1000}" placeholder="${esc(o.ph || '')}">${esc(val)}</textarea></label>`
        : `<label${o.wide ? ' class="rb-wide"' : ''}>${label}<input data-p="${path}" maxlength="${o.max || 120}" value="${esc(val)}" placeholder="${esc(o.ph || '')}"${o.type ? ` type="${o.type}"` : ''}${o.type === 'number' ? ' step="any" min="0" inputmode="decimal"' : ''}></label>`;
    const sel = (path, label, val, opts) => `<label>${label}<select data-p="${path}">${opts.map(([v, t]) => `<option value="${esc(v)}" ${val === v ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select></label>`;
    const curName = c => { try { return `${c} — ${new Intl.DisplayNames([LOCALE], { type: 'currency' }).of(c)}`; } catch { return c; } };
    const openState = {};
    const draw = (openKey = '') => {
        $$('[data-form] details').forEach(d => (openState[d.dataset.sec] = d.open));
        if (openKey) openState[openKey] = true;
        const F = I.from, To = I.to, K = KINDS[I.kind], T = totals(I);
        $('[data-form]').innerHTML = `
        <details class="fb-settings" data-sec="doc" open><summary>${esc(K[0])} details</summary>
            <div class="fb-2">${sel('kind', 'Type', I.kind, Object.entries(KINDS).map(([k, v]) => [k, v[0]]))}${fld('number', `${esc(K[0])} number`, I.number, { max: 40 })}
            ${fld('issued', 'Date', I.issued, { type: 'date' })}${K[1] ? fld('due', esc(K[1]), I.due, { type: 'date' }) : '<span></span>'}</div>
            ${K[1] ? `<div class="ib-terms" role="group" aria-label="Quick due dates">${[[0, 'On receipt'], [7, '7 days'], [14, '14 days'], [30, '30 days'], [60, '60 days']].map(([n, t]) => `<button type="button" class="fb-chip${I.due && I.issued && daysBetween(I.issued, I.due) === n ? ' is-on' : ''}" data-terms="${n}">${t}</button>`).join('')}</div>` : ''}
            <div class="fb-2">${sel('currency', 'Currency', I.currency, CURRENCIES.map(c => [c, curName(c)]))}${fld('ref', 'PO / reference (optional)', I.ref, { max: 60 })}</div>
            ${K[2] ? `<label class="fb-check"><input type="checkbox" data-p="paid" ${I.paid ? 'checked' : ''}> Paid — show a “Paid” stamp</label>` : ''}
        </details>
        <details class="fb-settings" data-sec="from" open><summary>From (you)</summary>
            <div class="rb-photo ib-logo">${I.logo ? `<img src="${I.logo}" alt="Your logo">` : '<span aria-hidden="true">🏷️</span>'}<div><button type="button" class="btn btn--sm btn--ghost" data-logo>${I.logo ? 'Change logo' : 'Add your logo (optional)'}</button>${I.logo ? '<button type="button" class="rb-link" data-logo-clear>Remove</button>' : ''}</div></div>
            <div class="fb-2">${fld('from.name', 'Business or your name', F.name)}${fld('from.email', 'Email', F.email, { type: 'email' })}${fld('from.phone', 'Phone', F.phone, { max: 60 })}
            <div class="rb-dates">${fld('from.taxLabel', 'Tax ID name', F.taxLabel, { max: 30, ph: 'VAT no.' })}${fld('from.taxId', 'Number', F.taxId, { max: 60 })}</div>
            ${fld('from.address', 'Address', F.address, { area: true, rows: 3, max: 400, wide: true })}</div>
        </details>
        <details class="fb-settings" data-sec="to" open><summary>Bill to (your client)</summary>
            <div class="fb-2">${fld('to.name', 'Client name or company', To.name)}${fld('to.email', 'Email', To.email, { type: 'email' })}
            ${fld('to.address', 'Address', To.address, { area: true, rows: 3, max: 400, wide: true })}${fld('to.taxId', 'Their tax / VAT number (optional)', To.taxId, { max: 60 })}</div>
        </details>
        <details class="fb-settings" data-sec="items" open><summary>Items</summary>
            <div class="ib-items">${I.items.map((x, i) => `<div class="ib-item" data-list="items" data-i="${i}">
                <label class="ib-desc"><span class="sr-only">Item ${i + 1} description</span><textarea data-p="items.${i}.desc" rows="1" maxlength="500" placeholder="Description of item or service">${esc(x.desc)}</textarea></label>
                <label><span class="ib-cap">Qty</span><input data-p="items.${i}.qty" type="number" step="any" min="0" inputmode="decimal" value="${esc(x.qty)}" aria-label="Item ${i + 1} quantity"></label>
                <label><span class="ib-cap">Price</span><input data-p="items.${i}.price" type="number" step="any" min="0" inputmode="decimal" value="${esc(x.price)}" placeholder="0" aria-label="Item ${i + 1} price"></label>
                <output class="ib-line" data-line="${i}">${money(T.lines[i], I.currency)}</output>
                <span class="fb-q__tools"><button type="button" data-move="-1" ${i ? '' : 'disabled'} aria-label="Move item ${i + 1} up" title="Move up">↑</button><button type="button" data-move="1" ${i < I.items.length - 1 ? '' : 'disabled'} aria-label="Move item ${i + 1} down" title="Move down">↓</button><button type="button" data-del class="fb-del" aria-label="Delete item ${i + 1}" title="Delete">✕</button></span>
            </div>`).join('')}</div>
            ${I.items.length < MAX_ITEMS ? '<button type="button" class="btn btn--sm btn--ghost rb-add" data-add="items">＋ Add an item</button>' : ''}
        </details>
        <details class="fb-settings" data-sec="totals" open><summary>Discount, tax &amp; totals</summary>
            <div class="fb-2">
                <div class="rb-dates">${sel('discount.kind', 'Discount', I.discount.kind, [['percent', '% off'], ['amount', 'Amount off']])}${fld('discount.value', I.discount.kind === 'percent' ? 'Percent' : 'Amount', I.discount.value, { type: 'number', ph: '0' })}</div>
                <div class="rb-dates">${fld('tax.label', 'Tax name', I.tax.label, { max: 30, ph: 'VAT, GST, Sales tax' })}${fld('tax.rate', 'Rate %', I.tax.rate, { type: 'number', ph: '0' })}</div>
                <label class="fb-check rb-wide"><input type="checkbox" data-p="tax.included" ${I.tax.included ? 'checked' : ''}> My prices already include tax</label>
                ${fld('shipping', 'Shipping (optional)', I.shipping, { type: 'number', ph: '0' })}${K[2] ? fld('deposit', 'Already paid / deposit (optional)', I.deposit, { type: 'number', ph: '0' }) : ''}
            </div>
            <p class="ib-total" data-total>Total: <b>${money(T.total, I.currency)}</b>${K[2] && !I.paid && T.deposit ? ` · Balance due: <b>${money(T.due, I.currency)}</b>` : ''}</p>
        </details>
        <details class="fb-settings" data-sec="notes" open><summary>Payment details &amp; notes</summary>
            ${fld('payment', 'How to pay', I.payment, { area: true, rows: 3, max: 1000, wide: true, hint: 'bank details, PayPal or a payment link', ph: 'Bank: …  Account: …  or  paypal.me/you' })}
            ${fld('notes', 'Notes or terms', I.notes, { area: true, rows: 2, max: 1000, wide: true })}
        </details>`;
        $$('[data-form] details').forEach(d => { if (d.dataset.sec in openState) d.open = openState[d.dataset.sec]; });
        $$('.ib-desc textarea').forEach(grow);
    };
    const grow = t => { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight + 2, 200) + 'px'; };
    const drawLook = () => {
        $('[data-designs]').innerHTML = Object.entries(DESIGNS).map(([k, v]) => `<button type="button" class="fb-chip${I.design === k ? ' is-on' : ''}" data-design="${k}" aria-pressed="${I.design === k}">${v}</button>`).join('');
        $('[data-look=color]').value = I.color; $('[data-look=font]').value = I.font;
    };
    $('[data-look=font]').innerHTML = Object.entries(FONTS).map(([k, v]) => `<option value="${esc(k)}">${v}</option>`).join('');
    /** Totals shown in the editor, updated as they type (without redrawing the boxes they're typing in). */
    const drawSums = () => {
        const T = totals(I), K = KINDS[I.kind];
        $$('[data-line]').forEach(o => { o.textContent = money(T.lines[+o.dataset.line] || 0, I.currency); });
        const t = $('[data-total]'); if (t) t.innerHTML = `Total: <b>${money(T.total, I.currency)}</b>${K[2] && !I.paid && T.deposit ? ` · Balance due: <b>${money(T.due, I.currency)}</b>` : ''}`;
        $$('[data-terms]').forEach(b => b.classList.toggle('is-on', !!(I.due && I.issued && daysBetween(I.issued, I.due) === +b.dataset.terms)));
    };
    const drawMine = () => {
        const box = $('[data-mine]');
        box.hidden = !mine.length;
        $('[data-mine-count]').textContent = mine.length;
        $('[data-mine-list]').innerHTML = mine.map(x => `<li${x.id === I.id ? ' class="is-open"' : ''}><span><b>${esc(x.number || KINDS[x.kind][0])}</b> ${esc(x.to.name || 'No client')}<small>${esc([KINDS[x.kind][0], showDate(x.issued), money(totals(x).total, x.currency), x.paid ? 'paid' : ''].filter(Boolean).join(' · '))}</small></span>
            <span class="ib-mine__btns"><button type="button" class="btn btn--sm btn--ghost" data-open="${x.id}"${x.id === I.id ? ' disabled' : ''}>${x.id === I.id ? 'Open now' : 'Open'}</button><button type="button" class="fb-del" data-forget="${x.id}" aria-label="Delete ${esc(x.number || 'this invoice')} from this device" title="Delete">✕</button></span></li>`).join('');
    };

    /* ------------------------------------------------ preview: a real A4 page, scaled to fit */
    const frame = $('[data-preview]'), screen = $('.rb-screen');
    let timer = 0;
    const fit = () => { const k = Math.min(1, (screen.clientWidth - 24) / 794); frame.style.transform = `scale(${k})`; const h = frame.contentDocument?.body?.scrollHeight || 1123; frame.style.height = h + 'px'; screen.style.height = Math.round(h * k + 24) + 'px'; };
    const preview = () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const doc = frame.contentDocument, html = page();
            if (doc?.querySelector('.sheet')) { // swap styles and content in place: no reload, no font flash
                const next = new DOMParser().parseFromString(html, 'text/html');
                doc.querySelector('style').textContent = next.querySelector('style').textContent;
                doc.title = next.title; doc.body.className = next.body.className; doc.body.innerHTML = next.body.innerHTML;
                fit(); doc.fonts?.ready.then(fit);
            } else { frame.srcdoc = html; frame.onload = () => { fit(); frame.contentDocument.fonts?.ready.then(fit); }; }
        }, 120);
        const t = tips(); $('[data-tips]').innerHTML = t.map(m => `<li>${esc(m)}</li>`).join(''); $('[data-tips-box]').hidden = !t.length;
    };
    addEventListener('resize', fit);
    let saveTimer = 0;
    const changed = () => { clearTimeout(saveTimer); saveTimer = setTimeout(save, 300); drawSums(); preview(); };
    addEventListener('pagehide', () => { clearTimeout(saveTimer); save(); });

    root.addEventListener('input', e => {
        const el = e.target;
        if (el.dataset.look) { I[el.dataset.look] = el.value; return changed(); }
        const p = el.dataset.p; if (!p) return;
        const ks = p.split('.'), last = ks.pop(), obj = ks.reduce((o, k) => o[k], I);
        obj[last] = el.type === 'checkbox' ? el.checked : el.value;
        if (el.matches('.ib-desc textarea')) grow(el);
        // these change which boxes show: redraw, then put the focus back
        if (p === 'kind') setKind(el.value);
        if (['kind', 'paid', 'discount.kind', 'currency'].includes(p)) { draw(); $(`[data-p="${p}"]`)?.focus(); }
        changed();
    });
    root.addEventListener('click', async e => {
        const t = e.target.closest('button'); if (!t || !root.contains(t)) return;
        const card = t.closest('[data-list]'), i = card ? +card.dataset.i : -1;
        if (t.dataset.add) { I.items.push(item()); draw('items'); changed(); $$('[data-list="items"]').pop()?.querySelector('textarea')?.focus(); }
        else if (t.dataset.move) { const j = i + +t.dataset.move, L = I.items; [L[i], L[j]] = [L[j], L[i]]; draw(); changed(); const at = $(`[data-list="items"][data-i="${j}"]`); (at?.querySelector(`[data-move="${t.dataset.move}"]:not([disabled])`) || at?.querySelector('[data-move]:not([disabled])'))?.focus(); }
        else if ('del' in t.dataset) {
            const x = I.items[i];
            if ((x.desc.trim() || num(x.price)) && !(await ask({ icon: 'warning', title: 'Delete this item?', confirm: 'Delete', cancel: 'Keep it', danger: true }))) return;
            I.items.splice(i, 1); if (!I.items.length) I.items.push(item());
            draw(); changed(); ($(`[data-list="items"][data-i="${Math.min(i, I.items.length - 1)}"] textarea`) || $('[data-add="items"]'))?.focus();
        }
        else if (t.dataset.terms !== undefined) { I.issued = I.issued || today(); I.due = addDays(I.issued, +t.dataset.terms); const d = $('[data-p="due"]'); if (d) d.value = I.due; changed(); }
        else if (t.dataset.design) { I.design = t.dataset.design; drawLook(); changed(); track('invoice_design', { design: I.design }); }
        else if ('logo' in t.dataset) $('[data-logo-file]').click();
        else if ('logoClear' in t.dataset) { I.logo = ''; draw(); changed(); }
    });

    // Logo: shrunk in the browser (never uploaded); PNG keeps a see-through background
    $('[data-logo-file]').addEventListener('change', async e => {
        const f = e.target.files[0]; e.target.value = ''; if (!f) return;
        const bmp = await createImageBitmap(f).catch(() => null);
        if (!bmp) return ask({ icon: 'info', title: 'That image couldn’t be read', text: 'Please choose a JPG, PNG or WebP logo.', cancel: false, confirm: 'OK' });
        const k = Math.min(1, 480 / bmp.width, 240 / bmp.height), c = document.createElement('canvas');
        c.width = Math.max(1, Math.round(bmp.width * k)); c.height = Math.max(1, Math.round(bmp.height * k));
        c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
        let url = c.toDataURL('image/png');
        if (url.length > 280000) { const g = c.getContext('2d'); g.globalCompositeOperation = 'destination-over'; g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height); url = c.toDataURL('image/jpeg', 0.85); }
        I.logo = url; draw(); changed();
    });

    /* ------------------------------------------------ PDF, new invoice, my invoices, start over */
    $('[data-pdf]').addEventListener('click', () => {
        const w = open('', '_blank'); // opened right away so pop-up blockers allow it
        if (!w) return ask({ icon: 'info', title: 'Allow pop-ups to save your PDF', text: 'Your browser blocked the print window. Allow pop-ups for this site and press “Download PDF” again.', cancel: false, confirm: 'OK' });
        w.document.write(page(true)); w.document.close();
        keep(); drawMine();
        track('invoice_pdf', { design: I.design, kind: I.kind });
    });
    $('[data-new]').addEventListener('click', async () => {
        if (isSample()) {
            // the example's business details aren't theirs: start properly blank
            I = blank({ design: I.design, color: I.color, font: I.font, currency: I.currency });
        } else {
            keep();
            const days = I.issued && I.due ? Math.max(0, daysBetween(I.issued, I.due)) : 14;
            I = blank({ design: I.design, color: I.color, font: I.font, logo: I.logo, kind: I.kind, currency: I.currency, from: { ...I.from }, tax: { ...I.tax }, payment: I.payment, notes: I.notes, number: nextNumber(highestNumber()), due: addDays(today(), days) });
        }
        draw('to'); drawLook(); drawMine(); changed();
        status('New invoice — your business details are kept');
        $('[data-p="to.name"]')?.focus();
        track('invoice_new');
    });
    $('[data-mine]').addEventListener('click', async e => {
        const b = e.target.closest('button'); if (!b) return;
        const id = b.dataset.open || b.dataset.forget, x = mine.find(m => m.id === id); if (!x) return;
        if (b.dataset.forget) {
            if (!(await ask({ icon: 'warning', title: `Delete ${x.number || 'this invoice'}?`, text: 'It’s removed from My invoices on this device. This can’t be undone.', confirm: 'Delete', cancel: 'Keep it', danger: true }))) return;
            mine = mine.filter(m => m.id !== id); saveList(); drawMine(); preview();
            $('[data-mine-list] button:not([disabled])')?.focus();
            return;
        }
        keep();
        I = clean({ ...x, logo: I.logo }); draw(); drawLook(); drawMine(); changed();
        status(`Opened ${I.number || 'invoice'} — changes save on this device`);
        $('[data-mine]').open = false;
    });
    $('[data-reset]').addEventListener('click', async () => {
        if (!(await ask({ icon: 'warning', title: 'Clear this invoice?', text: 'Empty every box, your business details too? Invoices in “My invoices” stay.', confirm: 'Clear it', cancel: 'Cancel', danger: true }))) return;
        I = blank({ design: I.design, color: I.color, font: I.font, currency: I.currency, number: I.number }); draw(); drawLook(); changed();
    });

    /* ------------------------------------------------ quick setup wizard (public/js/builder-wizard.js) — everything stays on this device */
    const WZ = window.OwlWizard;
    if (WZ) {
        const EM = { classic: '🧾', modern: '🎨', bold: '⚡', minimal: '◻️' };
        let fresh = isSample(), wframe = null, wt = 0;
        /** The first time they type their own details, the example goes, so none of it ends up on their invoice. */
        const own = () => { if (!fresh) return; fresh = false; I = blank({ design: I.design, color: I.color, font: I.font, kind: I.kind, currency: I.currency, items: [item(), item(), item()].map(x => ({ ...x, qty: '' })) }); I.items[0].qty = '1'; setKind(I.kind); };
        const drawW = () => { if (wframe) wframe.srcdoc = page(); };
        /** The quick setup offers three item rows; the empty ones go when it closes. */
        const tidy = () => { I.items = I.items.filter((x, i) => !i || x.desc.trim() || num(x.price)); save(); };
        const upd = () => { save(); clearTimeout(wt); wt = setTimeout(drawW, 150); };
        const field = (k, label, ph, src, type = 'text', max = 120) => `<label class="wz-field">${label}<input data-wz="${k}" type="${type}" maxlength="${max}" value="${esc(fresh ? '' : src[k] || '')}" placeholder="${esc(ph)}"${type === 'number' ? ' step="any" min="0" inputmode="decimal"' : ''}></label>`;
        const area = (k, label, ph, src, rows = 3) => `<label class="wz-field">${label}<textarea data-wz="${k}" rows="${rows}" maxlength="400" placeholder="${esc(ph)}">${esc(fresh ? '' : src[k] || '')}</textarea></label>`;
        const row = i => { const x = I.items[i] || (I.items[i] = item({ qty: '' })); return `<div class="wz-ib-row">${`<label class="wz-field">${i ? '' : 'What for'}<input data-wz-item="${i}" data-k="desc" maxlength="500" value="${esc(fresh ? '' : x.desc)}" placeholder="${esc(['e.g. Logo design', 'e.g. Consulting (hours)', 'e.g. Printing'][i])}" aria-label="Item ${i + 1}"></label>`}<label class="wz-field">${i ? '' : 'Qty'}<input data-wz-item="${i}" data-k="qty" type="number" step="any" min="0" inputmode="decimal" value="${esc(fresh ? (i ? '' : '1') : x.qty)}" aria-label="Item ${i + 1} quantity"></label><label class="wz-field">${i ? '' : 'Price'}<input data-wz-item="${i}" data-k="price" type="number" step="any" min="0" inputmode="decimal" value="${esc(fresh ? '' : x.price)}" placeholder="0" aria-label="Item ${i + 1} price"></label></div>`; };
        const steps = [
            {
                title: 'What are you making?', sub: 'Pick the type and a design. You can change both any time.',
                draw: () => `<div class="wz-look"><b>Type</b><div class="wz-chips">${['invoice', 'quote', 'receipt', 'tax', 'estimate', 'proforma'].map(k => `<button type="button" data-wz-kind="${k}" aria-pressed="${I.kind === k}">${KINDS[k][0]}</button>`).join('')}</div></div>`
                    + WZ.cards(Object.entries(DESIGNS).map(([k, v]) => [k, EM[k] || '🧾', v]), I.design),
                click: (e, el, box) => {
                    if (el.dataset.wzKind) { setKind(el.dataset.wzKind); WZ.press(box, '[data-wz-kind]', el); }
                    else if (el.dataset.wzPick) { I.design = el.dataset.wzPick; WZ.press(box, '[data-wz-pick]', el); }
                    else return;
                    upd();
                },
            },
            {
                title: 'Your business', sub: 'Shown at the top. Saved on this device for your next invoice — nothing is uploaded.',
                draw: () => `<div class="wz-2">${field('name', 'Business or your name', 'e.g. Sara Khan Design', I.from)}${field('email', 'Email', 'you@business.com', I.from, 'email')}${field('phone', 'Phone (optional)', '+44 7700 900123', I.from, 'tel', 60)}${field('taxId', 'VAT / tax number (optional)', 'e.g. GB123456789', I.from, 'text', 60)}</div>${area('address', 'Address (optional)', '12 Market Street\nManchester M1 1AE', I.from)}`,
                input: (e, el) => { const k = el.dataset.wz; if (!k) return; own(); I.from[k] = el.value; upd(); },
                ok: () => (I.from.name.trim() && !fresh ? '' : 'Please type your business or your name.'),
            },
            {
                title: 'Who is it for?', sub: 'Your client’s name and where to send it.',
                draw: () => `<div class="wz-2">${field('name', 'Client name or company', 'e.g. Northwind Ltd', I.to)}${field('email', 'Their email (optional)', 'accounts@client.com', I.to, 'email')}</div>${area('address', 'Their address (optional)', 'Attn: Priya Shah\n48 Park Road, Leeds', I.to)}`,
                input: (e, el) => { const k = el.dataset.wz; if (!k) return; own(); I.to[k] = el.value; upd(); },
                ok: () => (I.to.name.trim() ? '' : 'Please type who it’s for.'),
            },
            {
                title: 'What are you charging for?', sub: 'Up to three lines here — add as many as you like in the editor.',
                draw: () => `<label class="wz-field wz-ib-cur">Currency <select data-wz-cur>${CURRENCIES.map(c => `<option value="${c}" ${I.currency === c ? 'selected' : ''}>${esc(curName(c))}</option>`).join('')}</select></label>${row(0)}${row(1)}${row(2)}`,
                input: (e, el) => { if (el.dataset.wzItem === undefined) return; own(); (I.items[+el.dataset.wzItem] || (I.items[+el.dataset.wzItem] = item({ qty: '' })))[el.dataset.k] = el.value; upd(); },
                change: (e, el) => { if (!el.matches('[data-wz-cur]')) return; I.currency = el.value; upd(); },
                ok: () => (I.items.some(x => x.desc.trim() && num(x.price)) ? '' : 'Please add at least one item with a price.'),
            },
            {
                title: 'Tax, due date and payment', sub: 'Skip tax if you don’t charge it. Bank details help you get paid faster.',
                draw: () => `<div class="wz-2"><label class="wz-field">Tax name<input data-wz-tax="label" maxlength="30" value="${esc(I.tax.label)}" placeholder="VAT, GST, Sales tax"></label><label class="wz-field">Rate %<input data-wz-tax="rate" type="number" step="any" min="0" max="100" inputmode="decimal" value="${esc(I.tax.rate)}" placeholder="0"></label></div>
                    ${KINDS[I.kind][1] ? `<div class="wz-look"><b>${esc(KINDS[I.kind][1])}</b><div class="wz-chips">${[[0, 'On receipt'], [7, '7 days'], [14, '14 days'], [30, '30 days']].map(([n, t]) => `<button type="button" data-wz-terms="${n}" aria-pressed="${!!(I.issued && I.due && daysBetween(I.issued, I.due) === n)}">${t}</button>`).join('')}</div></div>` : ''}
                    <label class="wz-field">How to pay <small>— bank details, PayPal or a payment link</small><textarea data-wz-pay rows="3" maxlength="1000" placeholder="Bank: …  Account: …">${esc(I.payment)}</textarea></label>`,
                input: (e, el) => {
                    if (el.dataset.wzTax) I.tax[el.dataset.wzTax] = el.value;
                    else if (el.matches('[data-wz-pay]')) I.payment = el.value;
                    else return;
                    upd();
                },
                click: (e, el, box) => { if (el.dataset.wzTerms === undefined) return; I.issued = I.issued || today(); I.due = addDays(I.issued, +el.dataset.wzTerms); WZ.press(box, '[data-wz-terms]', el); upd(); },
            },
            {
                title: 'Your colour', sub: 'Use your brand colour — it goes on the title, the table and the amount due.',
                draw: () => WZ.palette(I.color),
                click: (e, el, box) => { if (!el.dataset.pal) return; I.color = el.dataset.pal; WZ.press(box, '[data-pal]', el); box.querySelector('[data-wz-color]').value = el.dataset.pal; upd(); },
                input: (e, el, box) => { if (!el.matches('[data-wz-color]')) return; I.color = el.value; box.querySelectorAll('[data-pal]').forEach(x => x.setAttribute('aria-pressed', 'false')); upd(); },
            },
        ];
        WZ.mount({
            root, banner: document.querySelector('[data-wz-banner]'), offKey: 'owlvix-wz-invoice-off',
            say: ['Hoo! Need an invoice? I’ll make it with you — about 2 minutes.', 'Your details stay on this device. Nothing is uploaded.', 'Watch it fill in as you type. Ready? 🚀'],
            hasSaved: () => !isSample(),
            quickSlot: root.querySelector('.fb-top__actions'),
            onRestart: () => { fresh = isSample(); },
            steps,
            preview: {
                mount: side => { side.innerHTML = '<p class="wz-prev__label">Live preview</p><div class="wz-paper wz-prev"><iframe title="Your invoice" data-wz-frame></iframe></div>'; wframe = side.querySelector('[data-wz-frame]'); drawW(); },
                unmount: () => { wframe = null; tidy(); draw(); drawLook(); drawMine(); preview(); },
                refresh: drawW,
            },
            building: 'Making your invoice…',
            tasks: ['Adding your details', 'Working out the totals', 'Setting your design', 'Getting it ready to print'],
            done: {
                title: 'Your invoice is ready',
                sub: 'Download it as a PDF to send, or keep editing. Next time, “New invoice” keeps your business details and numbers it for you.',
                actions: [
                    { label: '📄 Download PDF', cls: 'btn btn--glow', then: 'pdf', act: async (close, o) => {
                        close();
                        // on arrival at the editor page the PDF window needs a fresh click, or the browser blocks it
                        if (o?.replay) { if (await window.sweet({ icon: 'success', title: 'Your invoice is ready', text: 'Save it as a PDF — or keep editing first.', confirm: '📄 Download PDF', cancel: 'Keep editing' })) root.querySelector('[data-pdf]')?.click(); return; }
                        setTimeout(() => root.querySelector('[data-pdf]')?.click(), 350);
                    } },
                    { label: '✏️ Keep editing', act: close => close() },
                ],
            },
            editorUrl: root.dataset.editorUrl || '', noun: 'invoice',
            hasWork: () => !isSample(),
            beforeLeave: () => { tidy(); try { localStorage.setItem(STORE, JSON.stringify(I)); return true; } catch { return false; } },
        });
    }

    draw(); drawLook(); drawMine(); preview();
})();
