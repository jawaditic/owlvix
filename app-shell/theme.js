// The app's look: light or dark colours, corner roundness and font, chosen in the builder (content "style") and
// applied to every native screen. Each file builds its StyleSheet from the current look (makeStyles(t, r, f)) and
// rebuilds it when the look changes (App.js calls applyLook before drawing).

export const LIGHT = {
    dark: false,
    bg: '#f6f7f9', card: '#ffffff', ink: '#0f172a', text: '#334155', muted: '#64748b',
    line: 'rgba(15,23,42,0.08)', border: 'rgba(15,23,42,0.12)', soft: 'rgba(15,23,42,0.06)', shade: 'rgba(15,23,42,0.45)', input: '#ffffff',
};

export const DARK = {
    dark: true,
    bg: '#0b1120', card: '#151e2e', ink: '#f1f5f9', text: '#cbd5e1', muted: '#94a3b8',
    line: 'rgba(148,163,184,0.16)', border: 'rgba(148,163,184,0.24)', soft: 'rgba(148,163,184,0.12)', shade: 'rgba(0,0,0,0.6)', input: '#0f1726',
};

const CORNERS = { rounded: 1, soft: 0.5, square: 0.12 };
const FONTS = { modern: undefined, classic: 'serif', condensed: 'sans-serif-condensed' };

/** The look for a content "style" and the phone's colour scheme ('light' | 'dark'). */
export function look(style, scheme) {
    const s = style && typeof style === 'object' ? style : {};
    const dark = s.theme === 'dark' || (s.theme === 'auto' && scheme === 'dark');
    const k = CORNERS[s.corners] ?? 1;
    return {
        key: `${dark ? 'd' : 'l'}-${s.corners || 'rounded'}-${s.font || 'modern'}`,
        t: dark ? DARK : LIGHT,
        r: n => (n >= 999 ? (k < 0.3 ? Math.round(14 * k) : 999) : Math.round(n * k)),
        f: FONTS[s.font],
    };
}

const listeners = [];
/** Each file registers how to rebuild its styles. */
export function onLook(fn) { listeners.push(fn); fn(look(null, 'light')); }
let current = '';
/** Rebuilds every file's styles when the look changed. */
export function applyLook(l) {
    if (l.key === current) return;
    current = l.key;
    listeners.forEach(fn => fn(l));
}
