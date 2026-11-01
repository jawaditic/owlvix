// Makes the 1200×628 share pictures in public/images/og/{route}.jpg (guides, the Guides page, the gallery and every
// builder), in the style of images/og.png. layouts/page.blade.php uses them for og:image when the file exists.
// Run again after adding a guide or builder:
//   php artisan share-images:data /tmp/og    (writes owl-dev.svg.html and og-pages.json there)
//   node scripts/share-images.mjs public/images/og /tmp/og
// Needs Chrome and puppeteer-core (npm i --no-save puppeteer-core).
import puppeteer from 'puppeteer-core';
import fs from 'node:fs';
const [, , outDir, dataDir] = process.argv;
const S = dataDir.replace(/\/?$/, '/');
const owl = fs.readFileSync(S + 'owl-dev.svg.html', 'utf8');
const data = JSON.parse(fs.readFileSync(S + 'og-pages.json', 'utf8'));
const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

const cards = [
    ...data.guides.map(g => ({ file: g.route, label: 'Free step-by-step guide', title: g.title, sub: 'Plain English · with a free builder', url: 'owlvix.com' + g.path })),
    { file: 'guides', label: 'Free guides', title: 'Step-by-step guides for small businesses', sub: 'Shops · Websites · Menus · CVs · Apps', url: 'owlvix.com/guides' },
    { file: 'examples', label: '56 free designs', title: 'Ready-made designs you can use free', sub: 'Shops · Websites · Menus · Weddings · Portfolios', url: 'owlvix.com/examples' },
    ...data.builders.map(b => ({ file: b.route, label: 'Free builder · No sign-up to start', title: 'Free ' + b.title.replace(/^(.)/, c => c.toLowerCase()).replace(/^free /i, ''), sub: b.desc, url: 'owlvix.com' + b.path })),
];

const page = c => `<!doctype html><html><head><meta charset="utf-8">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600&family=Poppins:wght@700;800&display=block" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0}
body{width:1200px;height:628px;overflow:hidden;font-family:Inter,sans-serif;color:#fff;
 background:radial-gradient(ellipse 60% 55% at 85% 70%,rgba(30,199,115,.22),transparent 70%),radial-gradient(ellipse 50% 50% at 15% 0%,rgba(37,99,235,.25),transparent 70%),linear-gradient(180deg,#0b1224,#060a14)}
.grid{position:absolute;left:-10%;right:-10%;bottom:-40px;height:250px;transform:perspective(420px) rotateX(58deg);transform-origin:bottom;
 background-image:linear-gradient(rgba(30,199,115,.45) 1px,transparent 1px),linear-gradient(90deg,rgba(30,199,115,.45) 1px,transparent 1px);background-size:56px 56px;
 -webkit-mask-image:linear-gradient(to top,#000 10%,transparent 95%)}
.wrap{position:relative;display:flex;height:100%;padding:62px 0 56px 74px}
.text{flex:1;display:flex;flex-direction:column;max-width:720px}
.label{align-self:flex-start;padding:9px 18px;border-radius:999px;background:rgba(30,199,115,.14);border:1.5px solid rgba(30,199,115,.55);color:#4ade80;font:600 21px Inter;letter-spacing:.06em;text-transform:uppercase}
h1{margin-top:30px;font:800 ${c.title.length > 46 ? 56 : c.title.length > 32 ? 64 : 72}px/1.08 Poppins;letter-spacing:-.01em;display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden}
p{margin-top:22px;color:#cbd5e1;font:500 26px/1.35 Inter;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.url{margin-top:auto;align-self:flex-start;padding:16px 30px;border-radius:999px;background:#1ec773;color:#04130b;font:700 25px Poppins}
.owl{width:390px;flex:none;display:flex;align-items:flex-end;justify-content:center;padding-right:30px}
.owl svg{width:330px;height:auto}
.o-lid{transform-box:fill-box;transform-origin:top;transform:scaleY(0)}
</style></head><body><div class="grid"></div><div class="wrap">
<div class="text"><span class="label">${esc(c.label)}</span><h1>${esc(c.title)}</h1><p>${esc(c.sub)}</p><span class="url">${esc(c.url)}</span></div>
<div class="owl">${owl}</div></div></body></html>`;

const b = await puppeteer.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: 'new' });
const p = await b.newPage(); await p.setViewport({ width: 1200, height: 628 });
fs.mkdirSync(outDir, { recursive: true });
for (const c of cards) {
    await p.setContent(page(c), { waitUntil: 'load', timeout: 60000 });
    await p.evaluate(() => document.fonts.ready); await new Promise(r => setTimeout(r, 150));
    await p.screenshot({ path: `${outDir}/${c.file}.jpg`, type: 'jpeg', quality: 86 });
}
console.log(cards.length, 'pictures');
await b.close();
