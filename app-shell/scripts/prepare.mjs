// Gets an app ready to build: writes shell.json (the app's settings and its content at build time), the icon and
// splash images, and the signing key. Run by .github/workflows/build-android-app.yml before `expo prebuild`.
//
//   node scripts/prepare.mjs                       demo app (shell.example.json, plain icons)
//   node scripts/prepare.mjs job.json              settings from a file
//   OWLVIX_JOB_URL=… OWLVIX_JOB_TOKEN=… node scripts/prepare.mjs   settings from the Owlvix server
//
// Everything from the job is checked again here and only ever written to files — never put into a shell
// command — so a strange app name can't change what the build does.

import { spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { existsSync, mkdirSync, rmSync, readFileSync, writeFileSync, appendFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { deflateSync } from 'node:zlib';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const fail = msg => { console.error(`prepare: ${msg}`); process.exit(1); };

/* ---------- read the job ---------- */
let job;
if (process.env.OWLVIX_JOB_URL) {
    const res = await fetch(process.env.OWLVIX_JOB_URL, { headers: { Authorization: `Bearer ${process.env.OWLVIX_JOB_TOKEN}`, Accept: 'application/json' } });
    if (!res.ok) fail(`the server answered ${res.status} for the job`);
    job = (await res.json()).job;
} else {
    job = JSON.parse(readFileSync(process.argv[2] || join(root, 'shell.example.json'), 'utf8'));
}
if (!job || typeof job !== 'object') fail('no job');

/* ---------- check it (the server checks too; this is the second lock) ---------- */
const hex = v => (/^#[0-9a-f]{6}$/i.test(v) ? v.toLowerCase() : fail(`bad colour ${v}`));
const text = (v, max) => { const s = String(v ?? '').replace(/[\u0000-\u001f\u007f]/g, '').trim(); return s && s.length <= max ? s : fail(`bad text "${s}"`); };
const https = v => { let u; try { u = new URL(v); } catch { fail(`bad url ${v}`); } return u.protocol === 'https:' && !u.username && !u.password ? u.href : fail(`bad url ${v}`); };
if (!/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*){1,5}$/.test(job.package || '') || job.package.length > 100) fail('bad package name');
if (!/^\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(job.versionName || '')) fail('bad version name');
if (!Number.isInteger(job.versionCode) || job.versionCode < 1 || job.versionCode > 2_000_000_000) fail('bad version code');

// The content (tabs and blocks) is checked by the server and again by the app each time it's shown, since newer
// copies come from the server later; here it only has to be the right kind of document.
const content = job.content;
if (!content || content.v !== 1 || !Array.isArray(content.tabs) || content.tabs.length < 1 || content.tabs.length > 15) fail('bad content');
if (!/^https:\/\//.test(content.base || '')) fail('bad content base');
if (JSON.stringify(content).length > 2_000_000) fail('content too big');

const shell = {
    name: text(job.name, 30),
    package: job.package,
    versionName: job.versionName,
    versionCode: job.versionCode,
    contentUrl: https(job.contentUrl),
    colors: Object.fromEntries(['primary', 'bar', 'splash', 'icon'].map(k => [k, hex(job.colors?.[k])])),
    statusBar: job.statusBar === 'light' ? 'light' : 'dark',
    push: null, // set below when this build has push notifications
    statsUrl: job.statsUrl ? https(job.statsUrl) : null,
    loyaltyUrl: job.loyaltyUrl ? https(job.loyaltyUrl) : null,
    splashWidth: Number.isInteger(job.splashWidth) && job.splashWidth >= 80 && job.splashWidth <= 300 ? job.splashWidth : 180,
    content,
};
/* ---------- push notifications (the app's Firebase config and its white status-bar icon) ---------- */
rmSync(join(root, 'google-services.json'), { force: true });
rmSync(join(root, 'assets/notification.png'), { force: true });
if (job.googleServices && job.notificationIcon && /^app_[a-z0-9]{16}$/.test(job.pushTopic || '')) {
    const gs = Buffer.from(job.googleServices, 'base64').toString('utf8');
    let parsed; try { parsed = JSON.parse(gs); } catch { fail('bad google-services.json'); }
    if (!(parsed.client || []).some(c => c.client_info?.android_client_info?.package_name === job.package)) fail('google-services.json is for another app');
    const icon = Buffer.from(job.notificationIcon, 'base64');
    if (icon.subarray(0, 8).toString('hex') !== '89504e470d0a1a0a' || icon.readUInt32BE(16) !== 96 || icon.readUInt32BE(20) !== 96) fail('the notification icon must be a 96×96 PNG');
    mkdirSync(join(root, 'assets'), { recursive: true });
    writeFileSync(join(root, 'google-services.json'), gs);
    writeFileSync(join(root, 'assets/notification.png'), icon);
    shell.push = { topic: job.pushTopic };
}
writeFileSync(join(root, 'shell.json'), JSON.stringify(shell, null, 4));

/* ---------- images ---------- */
// PNGs from the builder (made in the browser, 1024×1024), or plain squares in the app colour for the demo.
const png = (b64, name) => {
    const buf = Buffer.from(b64, 'base64');
    if (buf.length > 3_000_000 || buf.subarray(0, 8).toString('hex') !== '89504e470d0a1a0a') fail(`${name} is not a PNG`);
    if (buf.readUInt32BE(16) !== 1024 || buf.readUInt32BE(20) !== 1024) fail(`${name} must be 1024×1024`);
    return buf;
};
const square = (color, size = 1024, transparent = false) => {
    const [r, g, b] = [1, 3, 5].map(i => parseInt(color.slice(i, i + 2), 16));
    const row = Buffer.alloc(1 + size * 4);
    for (let x = 0; x < size; x++) row.set(transparent ? [0, 0, 0, 0] : [r, g, b, 255], 1 + x * 4);
    const raw = Buffer.concat(Array.from({ length: size }, () => row));
    const crcTable = Array.from({ length: 256 }, (_, n) => { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; return c >>> 0; });
    const crc = buf => { let c = 0xffffffff; for (const byte of buf) c = crcTable[(c ^ byte) & 0xff] ^ (c >>> 8); return (c ^ 0xffffffff) >>> 0; };
    const chunk = (type, data) => { const len = Buffer.alloc(4); len.writeUInt32BE(data.length); const td = Buffer.concat([Buffer.from(type), data]); const c = Buffer.alloc(4); c.writeUInt32BE(crc(td)); return Buffer.concat([len, td, c]); };
    const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(size, 0); ihdr.writeUInt32BE(size, 4); ihdr.set([8, 6, 0, 0, 0], 8);
    return Buffer.concat([Buffer.from('89504e470d0a1a0a', 'hex'), chunk('IHDR', ihdr), chunk('IDAT', deflateSync(raw)), chunk('IEND', Buffer.alloc(0))]);
};
mkdirSync(join(root, 'assets'), { recursive: true });
writeFileSync(join(root, 'assets/icon.png'), job.icon ? png(job.icon, 'icon') : square(shell.colors.icon));
writeFileSync(join(root, 'assets/adaptive.png'), job.foreground ? png(job.foreground, 'foreground') : square(shell.colors.icon, 1024, true));
writeFileSync(join(root, 'assets/splash.png'), job.splash ? png(job.splash, 'splash') : job.foreground ? png(job.foreground, 'splash') : square(shell.colors.icon, 1024, true));

/* ---------- the app's pictures, packed into the build ---------- */
// Every picture the content uses (in every language) is downloaded and shipped inside the app, so even the first
// launch works offline. plugins/with-bundled-images.js puts them in the Android assets; content.js uses them when a
// picture's name is in shell.bundled, and downloads anything added later as before.
const BUNDLE_LIMIT = 30_000_000; // bytes, so a picture-heavy app stays a reasonable download
const bundleDir = join(root, 'bundled-img');
rmSync(bundleDir, { recursive: true, force: true });
const names = new Set();
JSON.stringify(content, (k, v) => { if (k === 'src' && typeof v === 'string' && /^[a-f0-9]{20}\.(jpg|png|webp)$/.test(v)) names.add(v); return v; });
shell.bundled = [];
let packed = 0;
for (const name of names) {
    try {
        const res = await fetch(content.base + name);
        if (!res.ok) continue;
        const buf = Buffer.from(await res.arrayBuffer());
        const magic = buf.subarray(0, 12).toString('hex');
        const ok = magic.startsWith('ffd8ff') || magic.startsWith('89504e47') || (magic.startsWith('52494646') && buf.subarray(8, 12).toString() === 'WEBP');
        if (!ok || packed + buf.length > BUNDLE_LIMIT) continue;
        mkdirSync(bundleDir, { recursive: true });
        writeFileSync(join(bundleDir, name), buf);
        packed += buf.length;
        shell.bundled.push(name);
    } catch {} // a picture that can't be fetched now is simply downloaded by the app later
}
writeFileSync(join(root, 'shell.json'), JSON.stringify(shell, null, 4));
console.log(`prepare: packed ${shell.bundled.length} of ${names.size} pictures (${Math.round(packed / 1024)} KB)`);

/* ---------- intro (full-screen picture or video after the splash) ---------- */
// Fetched on its own (too big for the job). intro.js tells the app which file to bundle, or that there's none.
let intro = null;
if (job.intro === 'image' || job.intro === 'video') {
    if (!process.env.OWLVIX_JOB_URL) fail('an intro needs the server');
    const res = await fetch(process.env.OWLVIX_JOB_URL.replace(/\/job$/, '/asset'), { headers: { Authorization: `Bearer ${process.env.OWLVIX_JOB_TOKEN}` } });
    if (!res.ok) fail(`the server answered ${res.status} for the intro`);
    const buf = Buffer.from(await res.arrayBuffer());
    const ok = job.intro === 'image' ? buf.subarray(0, 3).toString('hex') === 'ffd8ff' && buf.length <= 6_000_000 : buf.subarray(4, 8).toString() === 'ftyp' && buf.length <= 16_000_000;
    if (!ok) fail(`the intro ${job.intro} isn't what it should be`);
    intro = job.intro === 'image' ? 'intro.jpg' : 'intro.mp4';
    writeFileSync(join(root, 'assets', intro), buf);
}
writeFileSync(join(root, 'intro.js'), '// Written by scripts/prepare.mjs for this build.\n'
    + (intro ? `export default { type: '${job.intro}', source: require('./assets/${intro}') };\n` : 'export default null;\n'));

/* ---------- signing key ---------- */
// The same key must sign every version of an app, or Google Play refuses the update. The server keeps it and
// sends it with each job; the first build makes a new one (and the workflow sends it back to the server).
const dir = join(root, 'signing');
mkdirSync(dir, { recursive: true });
const store = join(dir, 'upload.keystore');
const password = job.keystorePassword || (process.env.OWLVIX_JOB_URL ? fail('no keystore password') : randomBytes(18).toString('base64url'));
if (!/^[A-Za-z0-9_-]{16,64}$/.test(password)) fail('bad keystore password');
let created = false;
if (job.keystore) {
    writeFileSync(store, Buffer.from(job.keystore, 'base64'));
} else {
    if (existsSync(store)) rmSync(store);
    const org = shell.name.replace(/[,=+<>#;"\\]/g, ' ');
    const r = spawnSync('keytool', ['-genkeypair', '-v', '-storetype', 'PKCS12', '-keystore', store, '-alias', 'upload', '-keyalg', 'RSA', '-keysize', '2048',
        '-validity', '10000', '-storepass', password, '-keypass', password, '-dname', `CN=${org}, O=${org}`], { stdio: 'inherit' });
    if (r.status !== 0) fail('keytool could not make a signing key');
    created = true;
}

// Tell the next workflow steps where the key is (GITHUB_ENV), or print it for a local build.
const env = { OWLVIX_UPLOAD_STORE_FILE: store, OWLVIX_UPLOAD_STORE_PASSWORD: password, OWLVIX_UPLOAD_KEY_ALIAS: 'upload', OWLVIX_KEYSTORE_CREATED: created ? '1' : '0' };
if (process.env.GITHUB_ENV) {
    console.log(`::add-mask::${password}`);
    appendFileSync(process.env.GITHUB_ENV, Object.entries(env).map(([k, v]) => `${k}=${v}\n`).join(''));
} else {
    writeFileSync(join(dir, 'env.sh'), Object.entries(env).map(([k, v]) => `export ${k}='${v}'\n`).join(''));
    console.log('Signing settings written to signing/env.sh (source it before building).');
}
console.log(`prepare: ${shell.name} (${shell.package}) ${shell.versionName} (${shell.versionCode}), ${content.tabs.length} tabs`);
