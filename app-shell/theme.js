// The app's look: light or dark colours, corner roundness, fonts and the bottom bar style, chosen in the builder
// (content "style") and applied to every native screen. Each file builds its StyleSheet from the current look
// (makeStyles(t, r, f, h)) and rebuilds it when the look changes (App.js calls applyLook before drawing).
// The design follows the Aiiplan app: a warm off-white ground, white cards lifted by soft navy shadows (no
// borders), pill shapes, serif section headings over a clean sans body, and a floating navy bottom bar.

export const LIGHT = {
    dark: false,
    bg: '#F6F5F2', card: '#ffffff', ink: '#111114', text: '#4A4A55', muted: '#6E6E75',
    line: 'rgba(30,27,73,0.07)', border: 'rgba(30,27,73,0.10)', soft: '#EEEDF3', shade: 'rgba(17,17,20,0.45)', input: '#ffffff',
    // a card's lift: a soft shadow tinted with navy (Android draws "elevation" as its shadow)
    lift: { shadowColor: '#1E1B49', shadowOpacity: 0.08, shadowRadius: 20, shadowOffset: { width: 0, height: 6 }, elevation: 3 },
    liftSmall: { shadowColor: '#1E1B49', shadowOpacity: 0.10, shadowRadius: 10, shadowOffset: { width: 0, height: 3 }, elevation: 2 },
};

export const DARK = {
    dark: true,
    bg: '#0b1120', card: '#151e2e', ink: '#f1f5f9', text: '#cbd5e1', muted: '#94a3b8',
    line: 'rgba(148,163,184,0.16)', border: 'rgba(148,163,184,0.24)', soft: 'rgba(148,163,184,0.12)', shade: 'rgba(0,0,0,0.6)', input: '#0f1726',
    // shadows don't show on dark grounds: a hairline edge does the job instead
    lift: { borderWidth: 1, borderColor: 'rgba(148,163,184,0.14)' },
    liftSmall: { borderWidth: 1, borderColor: 'rgba(148,163,184,0.14)' },
};

const CORNERS = { rounded: 1, soft: 0.5, square: 0.12 };
// body font, heading font: "modern" pairs a serif for headings with the phone's sans for text (Aiiplan)
const FONTS = { modern: [undefined, 'serif'], classic: ['serif', 'serif'], condensed: ['sans-serif-condensed', 'sans-serif-condensed'], plain: [undefined, undefined] };

/** The look for a content "style" and the phone's colour scheme ('light' | 'dark'). */
export function look(style, scheme) {
    const s = style && typeof style === 'object' ? style : {};
    const dark = s.theme === 'dark' || (s.theme === 'auto' && scheme === 'dark');
    const k = CORNERS[s.corners] ?? 1;
    const [f, h] = FONTS[s.font] || FONTS.modern;
    return {
        key: `${dark ? 'd' : 'l'}-${s.corners || 'rounded'}-${s.font || 'modern'}`,
        t: dark ? DARK : LIGHT,
        r: n => (n >= 999 ? (k < 0.3 ? Math.round(14 * k) : 999) : Math.round(n * k)),
        f, h,
        nav: s.nav === 'classic' ? 'classic' : 'floating',
        motion: s.motion !== 'off',
    };
}

/** A colour mixed toward another (0 = a, 1 = b): used for the floating bar's deep shade of the main colour. */
export function mix(a, b, w) {
    const p = h => [1, 3, 5].map(i => parseInt(h.slice(i, i + 2), 16));
    const [x, y] = [p(a), p(b)];
    return '#' + x.map((v, i) => Math.round(v + (y[i] - v) * w).toString(16).padStart(2, '0')).join('');
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
