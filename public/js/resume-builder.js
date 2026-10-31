/* Owlvix free resume / CV builder (/resume-builder). Everything stays in the browser (localStorage): personal details
   are never uploaded. The CV is drawn as an A4 page in a preview frame and printed to PDF from a clean window.
   Five designs (classic, modern, bold, minimal, compact) share one data model: { design, color, font, photo, person{}, summary, jobs[], schools[], skills,
   languages[], extras[] }. */
(() => {
    const root = document.querySelector('[data-rb]');
    if (!root) return;
    const $ = (s, el = root) => el.querySelector(s), $$ = (s, el = root) => [...el.querySelectorAll(s)];
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const hex = (c, d) => (/^#[0-9a-f]{6}$/i.test(c || '') ? c : d);
    const uid = () => (Math.random().toString(36).slice(2) + '00000000').slice(0, 8);
    const track = (e, d = {}) => window.track?.(e, d);
    /** Styled dialog (window.sweet). If its script hasn't loaded after a short wait, questions answer "no" (nothing is
        deleted unasked) and the message shows in the status line instead of disappearing. */
    const ask = async o => {
        for (let i = 0; i < 20 && !window.sweet; i++) await new Promise(r => setTimeout(r, 100));
        if (window.sweet) return window.sweet(o);
        $('[data-saved]').textContent = `${o.title}${o.cancel === false ? '' : ' — please reload the page and try again'}`;
        return o.cancel === false;
    };
    const STORE = 'owlvix-resume';
    const DESIGNS = { classic: 'Classic', modern: 'Modern (sidebar)', bold: 'Bold (timeline)', minimal: 'Minimal', compact: 'Compact' };
    const FONTS = { "'Inter', Arial, sans-serif": 'Inter (clean)', "Georgia, 'Times New Roman', serif": 'Georgia (classic)', "'Lato', Arial, sans-serif": 'Lato (friendly)', "Arial, Helvetica, sans-serif": 'Arial (safe)' };
    const LEVELS = ['Native', 'Fluent', 'Advanced', 'Intermediate', 'Basic'];
    const MAX = { jobs: 12, schools: 6, languages: 8, extras: 10 };

    const job = (o = {}) => ({ id: uid(), role: '', company: '', place: '', from: '', to: '', now: false, points: '', ...o });
    const school = (o = {}) => ({ id: uid(), degree: '', school: '', place: '', from: '', to: '', note: '', ...o });
    const lang = (o = {}) => ({ id: uid(), name: '', level: 'Fluent', ...o });
    const extra = (o = {}) => ({ id: uid(), title: '', detail: '', year: '', ...o });
    const sample = () => ({
        design: 'modern', color: '#2563eb', font: "'Inter', Arial, sans-serif", photo: '',
        person: { name: 'Alex Morgan', title: 'Marketing Manager', email: 'alex.morgan@email.com', phone: '+44 7700 900123', place: 'Manchester, UK', web: 'linkedin.com/in/alexmorgan' },
        summary: 'Marketing manager with 7 years of experience growing B2B brands. I plan campaigns that bring in qualified leads, lead small teams, and turn data into clear decisions.',
        jobs: [
            job({ role: 'Marketing Manager', company: 'Northwind Consulting', place: 'Manchester', from: '2021', now: true, points: 'Grew qualified leads by 62% in 18 months with a new content and email programme\nManaged a £400k budget and a team of 4\nLaunched the company’s first webinar series (3,000+ sign-ups)' }),
            job({ role: 'Digital Marketing Executive', company: 'Brightside Agency', place: 'Leeds', from: '2018', to: '2021', points: 'Ran paid search and social campaigns for 12 clients\nCut cost per lead by 35% through testing and better landing pages' }),
        ],
        schools: [school({ degree: 'BA (Hons) Business & Marketing', school: 'University of Leeds', from: '2014', to: '2017', note: 'First-class honours' })],
        skills: 'Campaign planning, Email marketing, Google Ads, SEO, Google Analytics 4, HubSpot, Copywriting, Team leadership',
        languages: [lang({ name: 'English', level: 'Native' }), lang({ name: 'Spanish', level: 'Intermediate' })],
        extras: [extra({ title: 'Google Ads Search Certification', detail: 'Google', year: '2023' })],
    });

    /* ------------------------------------------------ saved data: checked before use (it goes into the page) */
    const str = (v, n) => (typeof v === 'string' ? v.slice(0, n) : '');
    const clean = d => {
        if (!d || typeof d !== 'object') return sample();
        const pick = (arr, max, make, keys) => (Array.isArray(arr) ? arr : []).slice(0, max).filter(x => x && typeof x === 'object')
            .map(x => make(Object.fromEntries(keys.map(([k, n]) => [k, n === 'bool' ? !!x[k] : str(x[k], n)]))));
        const p = d.person || {};
        return {
            design: Object.hasOwn(DESIGNS, d.design) ? d.design : 'modern', color: hex(d.color, '#2563eb'), font: Object.hasOwn(FONTS, d.font) ? d.font : "'Inter', Arial, sans-serif",
            photo: /^data:image\/(jpeg|png|webp);base64,[a-z0-9+/=]+$/i.test(d.photo || '') && d.photo.length < 400000 ? d.photo : '',
            person: Object.fromEntries(['name', 'title', 'email', 'phone', 'place', 'web'].map(k => [k, str(p[k], 120)])),
            summary: str(d.summary, 1500), skills: str(d.skills, 1000),
            jobs: pick(d.jobs, MAX.jobs, job, [['role', 120], ['company', 120], ['place', 80], ['from', 20], ['to', 20], ['now', 'bool'], ['points', 3000]]),
            schools: pick(d.schools, MAX.schools, school, [['degree', 150], ['school', 150], ['place', 80], ['from', 20], ['to', 20], ['note', 300]]),
            languages: pick(d.languages, MAX.languages, lang, [['name', 40], ['level', 20]]).map(l => ({ ...l, level: LEVELS.includes(l.level) ? l.level : 'Fluent' })),
            extras: pick(d.extras, MAX.extras, extra, [['title', 150], ['detail', 150], ['year', 20]]),
        };
    };
    let R; try { R = clean(JSON.parse(localStorage.getItem(STORE) || 'null')); } catch { R = sample(); }
    const save = () => { try { localStorage.setItem(STORE, JSON.stringify(R)); $('[data-saved]').textContent = 'Saved on this device'; } catch { $('[data-saved]').textContent = 'Too big to save here — remove the photo or use “Save file”'; } };

    /* ------------------------------------------------ the CV page (A4, 210 × 297 mm) */
    const lines = t => String(t || '').split('\n').map(s => s.replace(/^[-•*]\s*/, '').trim()).filter(Boolean);
    const dates = x => [x.from, x.now ? 'Present' : x.to].filter(Boolean).join(' – ');
    const contactLink = (k, v) => {
        const t = String(v).trim(); if (!t) return '';
        const href = k === 'email' ? 'mailto:' + t.replace(/[^\w.+@-]/g, '') : k === 'phone' ? 'tel:' + t.replace(/[^\d+]/g, '') : k === 'web' ? (/^https?:\/\//i.test(t) ? t : 'https://' + t) : '';
        return href && !/["'<>\s]/.test(href) ? `<a href="${esc(href)}">${esc(t)}</a>` : esc(t);
    };
    const page = (forPrint = false) => {
        const c = R.color, P = R.person, d = R.design, has = v => String(v || '').trim();
        // Text on the accent colour (Modern sidebar, Bold band): dark on light colours, white on dark ones
        const lum = [1, 3, 5].map(k => parseInt(c.substr(k, 2), 16) / 255).reduce((a, v, k) => a + v * [0.2126, 0.7152, 0.0722][k], 0), light = lum > 0.6, on = light ? '#111827' : '#ffffff';
        const contacts = ['email', 'phone', 'place', 'web'].filter(k => has(P[k])).map(k => `<li class="ct ct-${k}">${contactLink(k, P[k])}</li>`).join('');
        const H = (t, cls = '') => `<h2 class="${cls}">${t}</h2>`;
        const jobsHtml = R.jobs.filter(j => has(j.role) || has(j.company)).map(j => `<article class="item"><div class="item-top"><div><h3>${esc(j.role)}</h3><p class="org">${esc([j.company, j.place].filter(has).join(' · '))}</p></div><span class="when">${esc(dates(j))}</span></div>${lines(j.points).length ? `<ul>${lines(j.points).map(l => `<li>${esc(l)}</li>`).join('')}</ul>` : ''}</article>`).join('');
        const schoolsHtml = R.schools.filter(s => has(s.degree) || has(s.school)).map(s => `<article class="item"><div class="item-top"><div><h3>${esc(s.degree)}</h3><p class="org">${esc([s.school, s.place].filter(has).join(' · '))}</p></div><span class="when">${esc(dates(s))}</span></div>${has(s.note) ? `<p class="note">${esc(s.note)}</p>` : ''}</article>`).join('');
        const skills = R.skills.split(/[,\n]/).map(s => s.trim()).filter(Boolean);
        const skillsHtml = skills.length ? `<ul class="tags">${skills.map(s => `<li>${esc(s)}</li>`).join('')}</ul>` : '';
        const langsHtml = R.languages.filter(l => has(l.name)).map(l => `<li><b>${esc(l.name)}</b><span>${esc(l.level)}</span></li>`).join('');
        const extrasHtml = R.extras.filter(x => has(x.title)).map(x => `<li><b>${esc(x.title)}</b>${has(x.detail) || has(x.year) ? `<span>${esc([x.detail, x.year].filter(has).join(' · '))}</span>` : ''}</li>`).join('');
        const photo = R.photo ? `<img class="photo" src="${R.photo}" alt="">` : '';
        const head = `<header class="head">${photo}<div><h1>${esc(P.name || 'Your name')}</h1>${has(P.title) ? `<p class="role">${esc(P.title)}</p>` : ''}${d !== 'modern' && contacts ? `<ul class="contacts">${contacts}</ul>` : ''}</div></header>`;
        const main = `${has(R.summary) ? `<section>${H('Profile')}<p class="summary">${esc(R.summary)}</p></section>` : ''}
            ${jobsHtml ? `<section>${H('Experience')}${jobsHtml}</section>` : ''}${schoolsHtml ? `<section>${H('Education')}${schoolsHtml}</section>` : ''}`;
        const side = `${skillsHtml ? `<section>${H('Skills')}${skillsHtml}</section>` : ''}${langsHtml ? `<section>${H('Languages')}<ul class="pairs">${langsHtml}</ul></section>` : ''}${extrasHtml ? `<section>${H('Certificates & more')}<ul class="pairs">${extrasHtml}</ul></section>` : ''}`;
        // Bold: a colour band with name and contacts, then the story as a timeline beside a slim column
        const bold = `<header class="band">${photo}<div><h1>${esc(P.name || 'Your name')}</h1>${has(P.title) ? `<p class="role">${esc(P.title)}</p>` : ''}${contacts ? `<ul class="contacts">${contacts}</ul>` : ''}</div></header><div class="grid2"><main>${main}</main><aside>${side}</aside></div>`;
        const body = d === 'bold' ? bold : d === 'modern'
            ? `<div class="grid"><aside class="side">${photo}<h1>${esc(P.name || 'Your name')}</h1>${has(P.title) ? `<p class="role">${esc(P.title)}</p>` : ''}${contacts ? `<section>${H('Contact')}<ul class="contacts">${contacts}</ul></section>` : ''}${side}</aside><main class="main">${main}</main></div>`
            : `${head}<div class="${d === 'compact' ? 'cols' : 'one'}"><main>${main}</main><aside>${side}</aside></div>`;
        const css = `@page{size:A4;margin:0}*{box-sizing:border-box}html{-webkit-print-color-adjust:exact;print-color-adjust:exact}html,body{margin:0;background:${forPrint ? '#fff' : '#e2e8f0'}}
body{font-family:${R.font};color:#1f2937;font-size:10.5pt;line-height:1.45;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.sheet{width:210mm;min-height:296mm;margin:${forPrint ? 0 : '0 auto'};background:#fff;-webkit-box-decoration-break:clone;box-decoration-break:clone;${forPrint ? '' : 'box-shadow:0 10px 30px rgba(15,23,42,.18);'}}
h1{margin:0;font-size:24pt;line-height:1.1;color:#111827;letter-spacing:-.01em}.role{margin:4px 0 0;font-size:12pt;color:${c};font-weight:600}
h2{margin:0 0 8px;font-size:10pt;letter-spacing:.12em;text-transform:uppercase;color:${c};border-bottom:1.5px solid ${c}33;padding-bottom:4px}
h2{break-after:avoid}h3{margin:0;font-size:11pt;color:#111827}section{margin:0 0 16px}.item{margin:0 0 12px;break-inside:avoid}
.item-top{display:flex;justify-content:space-between;gap:12px;align-items:baseline}.org{margin:1px 0 0;color:#4b5563;font-size:10pt}.when{flex:none;font-size:9.5pt;color:#6b7280;white-space:nowrap}
.item ul{margin:6px 0 0;padding-left:16px}.item li{margin:2px 0}.note{margin:4px 0 0;color:#4b5563;font-size:10pt}.summary{margin:0}
a{color:inherit;text-decoration:none}ul.contacts{list-style:none;margin:8px 0 0;padding:0;display:flex;flex-wrap:wrap;gap:4px 16px;font-size:9.5pt;color:#374151}
ul.tags{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:6px}ul.tags li{padding:3px 9px;border-radius:999px;background:${c}14;color:#1f2937;font-size:9.5pt}
ul.pairs{list-style:none;margin:0;padding:0;display:grid;gap:6px}ul.pairs li{display:flex;flex-direction:column}ul.pairs span{font-size:9.5pt;color:#6b7280}
.photo{width:88px;height:88px;border-radius:50%;object-fit:cover;flex:none}.head{display:flex;gap:18px;align-items:center}
/* classic */.d-classic .sheet{padding:18mm 18mm 14mm}.d-classic .head{padding-bottom:12px;margin-bottom:16px;border-bottom:3px solid ${c}}.d-classic .one aside section{margin-top:0}
/* minimal */.d-minimal .sheet{padding:20mm 20mm 16mm}.d-minimal h2{border:0;color:#111827;font-size:9pt}.d-minimal .role{color:#4b5563;font-weight:500}.d-minimal .head{margin-bottom:20px}.d-minimal ul.tags li{background:none;padding:0}.d-minimal ul.tags li:not(:last-child)::after{content:' ·';color:#9ca3af}
/* compact */.d-compact .sheet{padding:12mm 13mm;font-size:9.8pt}.d-compact .head{margin-bottom:12px;padding:12px 14px;border-radius:8px;background:${c}10}.d-compact h1{font-size:20pt}.d-compact .cols{display:grid;grid-template-columns:1fr 62mm;gap:8mm}.d-compact section{margin-bottom:12px}
/* modern */.d-modern .sheet{padding:0}.d-modern .grid{display:grid;grid-template-columns:68mm 1fr;min-height:296mm}
.d-modern .side,.d-modern .main{-webkit-box-decoration-break:clone;box-decoration-break:clone}
.d-modern .side{background:${c};color:${on};padding:14mm 8mm 10mm}.d-modern .side h1{color:${on};font-size:20pt}.d-modern .side .role{color:${on}d9}
.d-modern .side h2{color:${on};border-color:${on}55;margin-top:18px}.d-modern .side ul.contacts{display:grid;gap:5px;color:${on};margin:0;font-size:9.3pt;overflow-wrap:anywhere}
.d-modern .side ul.tags li{background:${on}26;color:${on}}.d-modern .side ul.pairs span{color:${on}bf}.d-modern .side .photo{width:110px;height:110px;margin:0 0 14px;border:3px solid ${on}66}
.d-modern .main{padding:14mm 12mm 10mm}
/* bold */.d-bold .sheet{padding:0}.d-bold .band{background:${c};color:${on};padding:11mm 14mm;display:flex;gap:18px;align-items:center}
.d-bold .band h1{color:${on};font-size:26pt}.d-bold .band .role{color:${on}e0}.d-bold .band ul.contacts{color:${on};margin-top:10px}.d-bold .band .photo{width:96px;height:96px;border:3px solid ${on}80}
.d-bold .grid2{display:grid;grid-template-columns:1fr 60mm;gap:9mm;padding:10mm 14mm 12mm;-webkit-box-decoration-break:clone;box-decoration-break:clone}
.d-bold h2{border:0;padding:0 0 0 9px;border-left:4px solid ${c};color:#111827;font-size:10.5pt;margin-bottom:10px}
.d-bold main .item{position:relative;padding:0 0 2px 16px;margin:0 0 12px;border-left:2px solid ${c}2e}
.d-bold main .item::before{content:'';box-sizing:border-box;position:absolute;left:-7px;top:4px;width:12px;height:12px;border-radius:50%;background:#fff;border:2.5px solid ${c}}
.d-bold aside{border-left:1.5px solid #e5e7eb;padding-left:7mm}.d-bold ul.tags li{background:${c}14;color:${light ? '#111827' : c};font-weight:600}
@media print{html,body{background:#fff}.sheet{box-shadow:none;margin:0}
/* every printed page: the sidebar colour runs top to bottom (the page background repeats on each page) */
html.d-modern-print{background:linear-gradient(90deg,${c} 68mm,#fff 68mm)!important}.d-modern-print body,.d-modern .sheet,.d-modern .side{background:transparent!important}}`; // one colour source in print: no seam where the sidebar box ends
        return `<!doctype html><html lang="en"${forPrint && d === 'modern' ? ' class="d-modern-print"' : ''}><head><meta charset="utf-8"><title>${esc((P.name || 'CV') + ' — CV')}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lato:wght@400;700&display=swap" rel="stylesheet">
<style>${css}</style></head><body class="d-${d}"><div class="sheet">${body}</div>${forPrint ? '<script>addEventListener("load",()=>(document.fonts?document.fonts.ready:Promise.resolve()).then(()=>setTimeout(()=>print(),150)))<\/script>' : '<style>a{pointer-events:none}</style>'}</body></html>`;
    };

    /* ------------------------------------------------ tips (what recruiters' software and people look for) */
    const tips = () => {
        const out = [], P = R.person;
        if (!P.name.trim()) out.push('Add your name.');
        if (!P.email.trim() && !P.phone.trim()) out.push('Add an email or phone number so employers can reach you.');
        if (P.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(P.email.trim())) out.push('Check your email address.');
        if (!R.summary.trim()) out.push('Add a 2–3 sentence profile: who you are, what you are best at, what you want next.');
        else if (R.summary.length > 600) out.push('Your profile is long — aim for 2–3 sentences.');
        if (!R.jobs.some(j => j.role.trim())) out.push('Add your work experience (or volunteering, internships and projects).');
        R.jobs.filter(j => j.role.trim() && lines(j.points).length && !lines(j.points).some(l => /\d/.test(l))).slice(0, 2).forEach(j => out.push(`“${j.role}”: add numbers to at least one point (%, £/$, people, time saved) — they make results believable.`));
        R.jobs.filter(j => j.role.trim() && !lines(j.points).length).slice(0, 2).forEach(j => out.push(`“${j.role}”: add 2–4 bullet points about what you achieved.`));
        if (R.skills.split(/[,\n]/).filter(s => s.trim()).length < 4) out.push('List at least 4–6 skills — copy the exact words from the job advert where they are true for you.');
        return out;
    };

    /* ------------------------------------------------ editor */
    const fld = (path, label, val, o = {}) => o.area
        ? `<label${o.wide ? ' class="rb-wide"' : ''}>${label}${o.hint ? ` <small>${o.hint}</small>` : ''}<textarea data-p="${path}" rows="${o.rows || 3}" maxlength="${o.max || 1000}" placeholder="${esc(o.ph || '')}">${esc(val)}</textarea></label>`
        : `<label${o.wide ? ' class="rb-wide"' : ''}>${label}<input data-p="${path}" maxlength="${o.max || 120}" value="${esc(val)}" placeholder="${esc(o.ph || '')}"${o.type ? ` type="${o.type}"` : ''}></label>`;
    const listHtml = (key, title, render, addLabel) => `<div class="rb-list">${R[key].map((x, i) => `<div class="fb-q is-open rb-item" data-list="${key}" data-i="${i}"><div class="fb-q__head"><span class="fb-q__label">${esc(title(x)) || '<i>New entry</i>'}</span>
        <span class="fb-q__tools"><button type="button" data-move="-1" ${i ? '' : 'disabled'} title="Move up">↑</button><button type="button" data-move="1" ${i < R[key].length - 1 ? '' : 'disabled'} title="Move down">↓</button><button type="button" data-del class="fb-del" title="Delete">✕</button></span></div>
        <div class="fb-q__body fb-2">${render(x, `${key}.${i}`)}</div></div>`).join('')}</div>
        ${R[key].length < MAX[key] ? `<button type="button" class="btn btn--sm btn--ghost rb-add" data-add="${key}">＋ ${addLabel}</button>` : ''}`;
    const openState = {};
    const draw = (openKey = '') => {
        $$('[data-form] details').forEach(d => (openState[d.dataset.sec] = d.open));
        if (openKey) openState[openKey] = true;
        const P = R.person;
        $('[data-form]').innerHTML = `
        <details class="fb-settings" data-sec="person" open><summary>Your details</summary>
            <div class="rb-photo">${R.photo ? `<img src="${R.photo}" alt="Your photo">` : '<span aria-hidden="true">👤</span>'}<div><button type="button" class="btn btn--sm btn--ghost" data-photo>${R.photo ? 'Change photo' : 'Add a photo (optional)'}</button>${R.photo ? '<button type="button" class="rb-link" data-photo-clear>Remove</button>' : ''}<p class="fb-hint">Skip the photo for jobs in the UK, US and Canada.</p></div></div>
            <div class="fb-2">${fld('person.name', 'Full name', P.name)}${fld('person.title', 'Job title', P.title, { ph: 'e.g. Marketing Manager' })}${fld('person.email', 'Email', P.email, { type: 'email' })}${fld('person.phone', 'Phone', P.phone, { type: 'tel' })}${fld('person.place', 'City, country', P.place)}${fld('person.web', 'LinkedIn or website', P.web, { ph: 'linkedin.com/in/you' })}</div>
        </details>
        <details class="fb-settings" data-sec="summary" open><summary>Profile</summary>${fld('summary', 'About you', R.summary, { area: true, rows: 4, max: 1500, hint: '2–3 sentences', wide: true })}</details>
        <details class="fb-settings" data-sec="jobs" open><summary>Work experience</summary>${listHtml('jobs', j => [j.role, j.company].filter(Boolean).join(' — '), (j, p) => `${fld(p + '.role', 'Job title', j.role)}${fld(p + '.company', 'Company', j.company)}${fld(p + '.place', 'City', j.place, { max: 80 })}<div class="rb-dates">${fld(p + '.from', 'From', j.from, { max: 20, ph: '2021' })}${j.now ? '' : fld(p + '.to', 'To', j.to, { max: 20, ph: '2024' })}</div><label class="fb-check rb-wide"><input type="checkbox" data-p="${p}.now" ${j.now ? 'checked' : ''}> I work here now</label>${fld(p + '.points', 'What you achieved', j.points, { area: true, rows: 4, max: 3000, hint: 'one per line, start with a verb, add numbers', wide: true })}`, 'Add a job')}</details>
        <details class="fb-settings" data-sec="schools" open><summary>Education</summary>${listHtml('schools', s => [s.degree, s.school].filter(Boolean).join(' — '), (s, p) => `${fld(p + '.degree', 'Qualification', s.degree, { max: 150 })}${fld(p + '.school', 'School / university', s.school, { max: 150 })}${fld(p + '.place', 'City', s.place, { max: 80 })}<div class="rb-dates">${fld(p + '.from', 'From', s.from, { max: 20 })}${fld(p + '.to', 'To', s.to, { max: 20 })}</div>${fld(p + '.note', 'Grade or note (optional)', s.note, { max: 300, wide: true })}`, 'Add education')}</details>
        <details class="fb-settings" data-sec="skills" open><summary>Skills</summary>${fld('skills', 'Skills', R.skills, { area: true, rows: 3, hint: 'separate with commas', wide: true })}</details>
        <details class="fb-settings" data-sec="languages"><summary>Languages</summary>${listHtml('languages', l => l.name, (l, p) => `${fld(p + '.name', 'Language', l.name, { max: 40 })}<label>Level<select data-p="${p}.level">${LEVELS.map(v => `<option ${l.level === v ? 'selected' : ''}>${v}</option>`).join('')}</select></label>`, 'Add a language')}</details>
        <details class="fb-settings" data-sec="extras"><summary>Certificates, courses &amp; projects</summary>${listHtml('extras', x => x.title, (x, p) => `${fld(p + '.title', 'Name', x.title, { max: 150, wide: true })}${fld(p + '.detail', 'From / details', x.detail, { max: 150 })}${fld(p + '.year', 'Year', x.year, { max: 20 })}`, 'Add one')}</details>`;
        restoreOpen();
    };
    const restoreOpen = () => $$('[data-form] details').forEach(d => { if (d.dataset.sec in openState) d.open = openState[d.dataset.sec]; });
    const drawLook = () => {
        $('[data-designs]').innerHTML = Object.entries(DESIGNS).map(([k, v]) => `<button type="button" class="fb-chip${R.design === k ? ' is-on' : ''}" data-design="${k}" aria-pressed="${R.design === k}">${v}</button>`).join('');
        $('[data-look=color]').value = R.color; $('[data-look=font]').value = R.font;
    };
    $('[data-look=font]').innerHTML = Object.entries(FONTS).map(([k, v]) => `<option value="${esc(k)}">${v}</option>`).join('');

    /* ------------------------------------------------ preview: a real A4 page, scaled to fit */
    const frame = $('[data-preview]'), screen = $('.rb-screen');
    let timer = 0;
    const fit = () => { const k = Math.min(1, (screen.clientWidth - 24) / 794); frame.style.transform = `scale(${k})`; frame.style.height = (frame.contentDocument?.body?.scrollHeight || 1123) + 'px'; screen.style.height = Math.round((frame.contentDocument?.body?.scrollHeight || 1123) * k + 24) + 'px'; };
    const preview = () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const doc = frame.contentDocument, html = page();
            if (doc?.querySelector('.sheet')) { // swap the styles and content in place: no reload, no font flash
                const next = new DOMParser().parseFromString(html, 'text/html');
                doc.documentElement.className = next.documentElement.className;
                doc.querySelector('style').textContent = next.querySelector('style').textContent;
                doc.body.className = next.body.className; doc.body.innerHTML = next.body.innerHTML;
                fit(); doc.fonts?.ready.then(fit);
            } else { frame.srcdoc = html; frame.onload = () => { fit(); frame.contentDocument.fonts?.ready.then(fit); }; }
        }, 150);
        const t = tips(); $('[data-tips]').innerHTML = t.map(m => `<li>${esc(m)}</li>`).join(''); $('[data-tips-box]').hidden = !t.length;
    };
    addEventListener('resize', fit);
    let saveTimer = 0;
    const changed = () => { clearTimeout(saveTimer); saveTimer = setTimeout(save, 300); preview(); };
    addEventListener('pagehide', () => { clearTimeout(saveTimer); save(); }); // a pending save still happens when leaving

    root.addEventListener('input', e => {
        const el = e.target;
        if (el.dataset.look) { R[el.dataset.look] = el.value; return changed(); }
        const p = el.dataset.p; if (!p) return;
        const ks = p.split('.'), last = ks.pop(), obj = ks.reduce((o, k) => o[k], R);
        obj[last] = el.type === 'checkbox' ? el.checked : el.value;
        if (last === 'now') { draw(); $(`[data-p="${p}"]`)?.focus(); } // show or hide the "To" box
        else { const card = el.closest('.rb-item'); if (card && ['role', 'company', 'degree', 'school', 'name', 'title'].includes(last)) { const x = obj; card.querySelector('.fb-q__label').textContent = [x.role || x.degree || x.name || x.title, x.company || x.school].filter(Boolean).join(' — ') || 'New entry'; } }
        changed();
    });
    root.addEventListener('click', async e => {
        const t = e.target.closest('button'); if (!t) return;
        const card = t.closest('[data-list]'), key = card?.dataset.list, i = card ? +card.dataset.i : -1;
        if (t.dataset.add) { const make = { jobs: job, schools: school, languages: lang, extras: extra }[t.dataset.add]; R[t.dataset.add].push(make()); draw(t.dataset.add); changed(); $$(`[data-list="${t.dataset.add}"]`).pop()?.querySelector('input')?.focus(); }
        else if (t.dataset.move) { const j = i + +t.dataset.move, L = R[key]; [L[i], L[j]] = [L[j], L[i]]; draw(); changed(); }
        else if ('del' in t.dataset) { if (await ask({ icon: 'warning', title: 'Delete this entry?', confirm: 'Delete', cancel: 'Keep it', danger: true })) { R[key].splice(i, 1); draw(); changed(); } }
        else if (t.dataset.design) { R.design = t.dataset.design; drawLook(); changed(); track('resume_design', { design: R.design }); }
        else if ('photo' in t.dataset) $('[data-photo-file]').click();
        else if ('photoClear' in t.dataset) { R.photo = ''; draw(); changed(); }
    });

    // Photo: resized in the browser to a small square JPEG (never uploaded)
    $('[data-photo-file]').addEventListener('change', async e => {
        const f = e.target.files[0]; e.target.value = ''; if (!f) return;
        const bmp = await createImageBitmap(f).catch(() => null);
        if (!bmp) return ask({ icon: 'info', title: 'That image couldn’t be read', text: 'Please choose a JPG, PNG or WebP photo.', cancel: false, confirm: 'OK' });
        const s = Math.min(bmp.width, bmp.height), c = document.createElement('canvas'); c.width = c.height = 320;
        const g = c.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, 320, 320); // transparent areas would turn black
        g.drawImage(bmp, (bmp.width - s) / 2, (bmp.height - s) / 2, s, s, 0, 0, 320, 320);
        R.photo = c.toDataURL('image/jpeg', 0.85); draw(); changed();
    });

    /* ------------------------------------------------ PDF, file save/open, start over */
    $('[data-pdf]').addEventListener('click', async () => {
        const w = open('', '_blank'); // opened right away so pop-up blockers allow it
        if (!w) return ask({ icon: 'info', title: 'Allow pop-ups to save your PDF', text: 'Your browser blocked the print window. Allow pop-ups for this site and press “Download PDF” again.', cancel: false, confirm: 'OK' });
        w.document.write(page(true)); w.document.close();
        track('resume_pdf', { design: R.design });
    });
    $('[data-save-file]').addEventListener('click', () => {
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([JSON.stringify(R)], { type: 'application/json' }));
        a.download = ((R.person.name || 'my').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'my') + '-cv.owlvix-cv.json';
        document.body.append(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 2000);
    });
    $('[data-open-file]').addEventListener('click', () => $('[data-open-input]').click());
    $('[data-open-input]').addEventListener('change', async e => {
        const f = e.target.files[0]; e.target.value = ''; if (!f) return;
        try {
            if (f.size > 2e6) throw new Error();
            const d = JSON.parse(await f.text()); if (!d.person) throw new Error();
            R = clean(d); draw(); drawLook(); changed();
        } catch { ask({ icon: 'info', title: 'That isn’t a saved CV', text: 'Choose a file saved with “Save file” in this builder.', cancel: false, confirm: 'OK' }); }
    });
    $('[data-reset]').addEventListener('click', async () => {
        const choice = await ask({ icon: 'warning', title: 'Start over?', text: 'Clear everything and start from a blank CV? Save a file first if you may need it again.', confirm: 'Clear my CV', cancel: 'Cancel', danger: true });
        if (!choice) return;
        R = clean({ design: R.design, color: R.color, font: R.font, person: {}, jobs: [job()], schools: [school()], languages: [], extras: [] }); draw(); drawLook(); changed();
    });

    draw(); drawLook(); preview();
})();
