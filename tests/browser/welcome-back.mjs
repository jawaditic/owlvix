// Browser test: the quick setup → editor page → "Welcome back" / Resume, on every builder (15), plus gallery designs,
// crafted #then= links and a real hand-off. Prints only failures, then a count.
//   php artisan serve --port=8001        (another terminal)
//   npm i --no-save puppeteer-core && node tests/browser/welcome-back.mjs [http://127.0.0.1:8001/]
// Needs Google Chrome (CHROME=/path/to/chrome to use another).
import puppeteer from 'puppeteer-core';
const BASE = process.argv[2] || 'http://127.0.0.1:8001/';
const wait = ms => new Promise(r => setTimeout(r, ms));
const b = await puppeteer.launch({ executablePath: process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: 'new' });
let pass = 0, fail = 0; const errs = [];
const check = (name, ok, info = '') => { ok ? pass++ : fail++; if (!ok) console.log('FAIL', name, info); };
const ROOT = '[data-lpb], [data-eb], [data-rb], [data-ib], [data-qb], [data-fb], [data-ab]';
const state = p => p.evaluate(root => {
    const ban = document.querySelector('[data-wz-banner]');
    return { path: location.pathname, banner: !!ban && !ban.hidden, welcome: !!ban && !ban.hidden && ban.classList.contains('wzb--back'),
        resume: !!document.querySelector('[data-wz-resume]'), builder: getComputedStyle(document.querySelector(root)).display !== 'none' };
}, ROOT);
const newPage = async ctx => { const p = await ctx.newPage(); await p.setViewport({ width: 1280, height: 900 }); p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.dismiss()); return p; };
const finishWizard = async p => {
    await p.evaluate(() => document.querySelector('[data-wz-start]').click()); await wait(700);
    for (let i = 0; i < 20 && !(await p.$('.wz-done')); i++) {
        await p.evaluate(() => document.querySelector('[data-wz-next]')?.click()); await wait(450);
        if (await p.$eval('[data-wz-err]', e => !e.hidden).catch(() => false)) {
            await p.evaluate(() => { const box = document.querySelector('.wz'); box.querySelectorAll('input[type=text], input:not([type]), input[type=email], input[type=url], textarea').forEach(i => { if (!i.value) { i.value = 'Test Co'; i.dispatchEvent(new Event('input', { bubbles: true })); } }); box.querySelectorAll('input[type=number]').forEach(i => { if (!i.value) { i.value = '10'; i.dispatchEvent(new Event('input', { bubbles: true })); } }); box.querySelector('[data-wz-pick]:not([aria-pressed="true"]), [data-kind]:not([aria-pressed="true"]), .wz-card')?.click(); });
            await wait(400); await p.evaluate(() => document.querySelector('[data-wz-next]')?.click()); await wait(500);
        }
    }
    await p.waitForSelector('.wz-done', { timeout: 8000 });
    await wait(500);
};
const paths = ['landing-page-builder', 'funnel-builder', 'website-builder', 'portfolio-builder', 'link-in-bio-builder', 'coming-soon-page-builder',
    'event-website-builder', 'restaurant-menu-builder', 'online-store-builder', 'form-builder', 'email-template-builder', 'resume-builder', 'invoice-generator', 'qr-code-generator', 'mobile-app-builder'];
for (const path of paths) {
    const ctx = await b.createBrowserContext(); const p = await newPage(ctx);
    // 1. brand-new visitor
    await p.goto(BASE + path, { waitUntil: 'networkidle2' }); await wait(600);
    let s = await state(p); check(`${path}: new visitor sees the invitation`, s.banner && !s.welcome && !s.resume, JSON.stringify(s));
    // 2. plain reload, nothing done
    await p.reload({ waitUntil: 'networkidle2' }); await wait(1800);
    s = await state(p); check(`${path}: reload without doing anything → still the invitation`, s.banner && !s.welcome, JSON.stringify(s));
    // 3. "no thanks", reload → builder on the page, no welcome back
    await p.evaluate(() => document.querySelector('[data-wzb-no]').click()); await wait(500);
    await p.reload({ waitUntil: 'networkidle2' }); await wait(1800);
    s = await state(p); check(`${path}: after "no thanks" → no welcome back`, !s.welcome && s.builder, JSON.stringify(s));
    await ctx.close();

    // 4. finish the quick setup → editor; back → welcome back; Resume → editor
    const ctx2 = await b.createBrowserContext(); const q = await newPage(ctx2);
    await q.goto(BASE + path, { waitUntil: 'networkidle2' }); await wait(500);
    await finishWizard(q);
    await Promise.all([q.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 }).catch(() => null),
        q.evaluate(() => (document.querySelector('[data-wz-edit]') || [...document.querySelectorAll('.wz-done button')].find(x => /Keep editing/.test(x.textContent))).click())]);
    check(`${path}: finishing opens the editor page`, q.url().endsWith('/' + path + '/editor'), q.url());
    await Promise.all([q.waitForNavigation({ waitUntil: 'networkidle2' }), q.click('.ebar__back')]); await wait(1800);
    s = await state(q); check(`${path}: back on the main page → welcome back with Resume, builder tucked away`, s.welcome && s.resume && !s.builder, JSON.stringify(s));
    await Promise.all([q.waitForNavigation({ waitUntil: 'networkidle2' }), q.click('[data-wz-resume]')]);
    check(`${path}: Resume opens the editor`, q.url().endsWith('/' + path + '/editor'), q.url());
    // 5. "edit it here" → builder shows; reload this visit → no banner
    await q.goto(BASE + path, { waitUntil: 'networkidle2' }); await wait(600);
    await q.evaluate(() => document.querySelector('[data-wzb-no]').click()); await wait(500);
    s = await state(q); check(`${path}: "edit it here" shows the builder`, s.builder && !s.banner, JSON.stringify(s));
    await q.reload({ waitUntil: 'networkidle2' }); await wait(1800);
    s = await state(q); check(`${path}: …and no banner again this visit`, s.builder && !s.banner, JSON.stringify(s));
    await ctx2.close();
}

// 6. a design from the gallery: straight to the builder, no banner flash
for (const [path, key] of [['landing-page-builder', 'dental'], ['online-store-builder', 'coffee']]) {
    const ctx = await b.createBrowserContext(); const p = await newPage(ctx);
    await p.goto(`${BASE}${path}?design=${key}`, { waitUntil: 'networkidle2' }); await wait(300);
    const s1 = await state(p); await wait(2000); const s2 = await state(p);
    check(`${path}?design=${key}: no banner, builder shown (also after 2s)`, !s1.banner && s1.builder && !s2.banner && s2.builder, JSON.stringify([s1, s2]));
    await ctx.close();
}
// 7. a crafted link can't publish/save someone's work
{
    const ctx = await b.createBrowserContext(); const p = await newPage(ctx);
    const posts = []; p.on('request', r => r.method() === 'POST' && /\/builder\//.test(r.url()) && posts.push(r.url()));
    await p.goto(BASE + 'form-builder', { waitUntil: 'networkidle2' });
    await p.evaluate(() => localStorage.setItem('owlvix-builder-token', 'fake'));
    for (const u of ['form-builder/editor#then=publish', 'website-builder/editor#then=save', 'resume-builder/editor#then=pdf', 'form-builder#then=publish']) {
        await p.goto(BASE + u, { waitUntil: 'networkidle2' }); await wait(1500);
        const dlg = await p.evaluate(() => !!document.querySelector('dialog[open], .sweet'));
        check(`crafted link ${u} does nothing`, !dlg && !posts.length, JSON.stringify({ dlg, posts }));
    }
    await ctx.close();
}
// 8. the real hand-off still works: "Save to my account" on the website builder → sign-in on the editor
{
    const ctx = await b.createBrowserContext(); const p = await newPage(ctx);
    await p.goto(BASE + 'website-builder', { waitUntil: 'networkidle2' }); await wait(500);
    await finishWizard(p);
    await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 }).catch(() => null),
        p.evaluate(() => [...document.querySelectorAll('.wz-done button')].find(x => /Save to my account/.test(x.textContent)).click())]);
    await wait(1500);
    const t = await p.evaluate(() => (document.querySelector('dialog[open]')?.innerText || '').replace(/\s+/g, ' ').slice(0, 40));
    check('real hand-off: "Save to my account" → sign-in on the editor', p.url().endsWith('/website-builder/editor') && /Sign in/.test(t), p.url() + ' ' + t);
    await ctx.close();
}
console.log(`\n${pass} passed, ${fail} failed`);
console.log('errors:', errs.length ? [...new Set(errs)] : 'none');
await b.close();
process.exit(fail || errs.length ? 1 : 0);
