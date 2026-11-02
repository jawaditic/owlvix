// Makes public/js/icons.js (window.OwlIcon) from resources/icons/icons.json — the one list of Owlvix's own icons,
// also read by App\Support\Icons in PHP. Run by `npm run minify`; edit the JSON, never icons.js.
import { readFileSync, writeFileSync } from 'node:fs';

export const inline = paths => paths.replace(/ class="d"/g, ' fill="currentColor" fill-opacity=".16"').replace(/ class="f"/g, ' fill="currentColor"');
export const makeIconsJs = () => {
    const icons = JSON.parse(readFileSync('resources/icons/icons.json', 'utf8'));
    const data = Object.fromEntries(Object.entries(icons).map(([k, v]) => [k, inline(v)]));
    writeFileSync('public/js/icons.js', `/* Owlvix icons — window.OwlIcon(name, { cls, label }) → an inline <svg> that takes the text colour and size (1em).
   GENERATED from resources/icons/icons.json by scripts/icons.mjs (npm run minify) — edit the JSON, not this file.
   Same drawings as App\\Support\\Icons::i() in PHP. */
(() => {
    const I = ${JSON.stringify(data, null, 0).replace(/","/g, '",\n        "')};
    const icon = (name, o = {}) => \`<svg class="oi\${o.cls ? ' ' + o.cls : ''}" viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" \${o.label ? \`role="img" aria-label="\${String(o.label).replace(/[&<>"]/g, '')}"\` : 'aria-hidden="true"'} focusable="false">\${I[name] || I.check}</svg>\`;
    icon.names = Object.keys(I);
    icon.paths = name => I[name] || '';
    window.OwlIcon = icon;
})();
`);
    return Object.keys(icons).length;
};
if (import.meta.url === `file://${process.argv[1]}`) console.log(`public/js/icons.js  ${makeIconsJs()} icons`);
