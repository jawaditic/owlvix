// Makes minified copies of the site's CSS and JS: public/css/site.css → public/css/site.min.css, etc.
// Edit the readable files, then run `npm run minify` and commit both. Pages load the .min copies
// (App\Support\Asset); tests/Feature/MinifiedAssetsTest.php fails if a copy is out of date.

import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import { transform } from 'esbuild';

export const FILES = [
    'css/theme.css',
    'css/site.css',
    'css/admin.css',
    'js/theme.js',
    'js/owl.js',
    'js/site.js',
    'js/sweet.js',
    'js/admin-dashboard.js',
    'js/builder-engine.js',
    'js/landing-builder.js',
    'js/builder-runtime.js',
    'js/form-builder.js',
    'js/qr.js',
    'js/email-builder.js',
    'js/resume-builder.js',
    'js/app-builder.js',
    'js/app-templates.js',
];

for (const file of FILES) {
    const source = readFileSync(`public/${file}`, 'utf8');
    const loader = file.endsWith('.css') ? 'css' : 'js';
    const { code } = await transform(source, { loader, minify: true, legalComments: 'none' });
    // First line records which version of the readable file this copy was made from
    const hash = createHash('sha1').update(source).digest('hex').slice(0, 12);
    const out = `public/${file.replace(/\.(css|js)$/, '.min.$1')}`;
    writeFileSync(out, `/*! from ${file} ${hash} */\n${code}`);
    console.log(`${out}  ${source.length} → ${code.length} bytes`);
}
