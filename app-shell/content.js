// The app's content: its tabs and the blocks on each screen (made in the Owlvix mobile app builder).
// The copy built into the app is shown first; a newer copy saved on the phone replaces it; and the app asks the
// server for changes on start and when it comes back to the front. The server's file is static and the request
// carries If-None-Match, so when nothing changed the answer is an empty "304 Not Modified".
import { File, Paths } from 'expo-file-system';

const HEX = /^#[0-9a-f]{6}$/i;
const IMAGE = /^[a-f0-9]{20}\.(jpg|png|webp)$/;

/** A content document we can show: anything else is ignored. */
export const usable = doc => !!doc && typeof doc === 'object' && doc.v === 1 && Number.isFinite(doc.rev)
    && typeof doc.base === 'string' && /^https:\/\//.test(doc.base)
    && Array.isArray(doc.tabs) && doc.tabs.length > 0 && doc.tabs.length <= 15
    && doc.tabs.every(t => t && typeof t.label === 'string' && (t.type === 'web' ? typeof t.url === 'string' : Array.isArray(t.blocks)));

const cacheFile = () => new File(Paths.document, 'owlvix-content.json');

/** The newest copy on the phone: the saved one, or the one built into the app. */
export function startContent(bundled) {
    try {
        const f = cacheFile();
        if (f.exists) {
            const saved = JSON.parse(f.textSync());
            if (usable(saved?.doc) && (!usable(bundled) || saved.doc.rev >= bundled.rev)) return saved;
        }
    } catch {}
    return { doc: usable(bundled) ? bundled : null, etag: null };
}

/** Asks the server for a newer copy. Returns the new state, or null when there's nothing new (or no internet). */
export async function fetchContent(url, current) {
    if (!url) return null;
    try {
        const headers = { Accept: 'application/json', 'Cache-Control': 'no-cache' };
        if (current?.etag) headers['If-None-Match'] = current.etag;
        const res = await fetch(url, { headers });
        if (res.status === 304 || !res.ok) return null;
        const doc = await res.json();
        if (!usable(doc) || (current?.doc && doc.rev < current.doc.rev)) return null;
        const next = { doc, etag: res.headers.get('etag') };
        try { Promise.resolve(cacheFile().write(JSON.stringify(next))).catch(() => {}); } catch {}
        return next;
    } catch {
        return null;
    }
}

/* ---------- safe readers: content comes from the network, so every value is checked before use ---------- */

export const str = v => (typeof v === 'string' ? v : '');
export const color = (v, fallback) => (HEX.test(v || '') ? v : fallback);

/** A picture's address and shape, or null. */
export const image = (img, base) => (img && IMAGE.test(img.src || '') ? { uri: base + img.src, ratio: img.w > 0 && img.h > 0 ? img.w / img.h : 1.5 } : null);

/** Where tapping goes: a URL to open, { tab } to switch tabs, or null. */
export function action(a) {
    if (!a || typeof a !== 'object') return null;
    const v = a.value;
    switch (a.kind) {
        case 'tab': return Number.isInteger(v) ? { tab: v } : null;
        case 'url': return typeof v === 'string' && /^https:\/\//i.test(v) ? { url: v } : null;
        case 'tel': return typeof v === 'string' ? { url: 'tel:' + v.replace(/[^0-9+]/g, '') } : null;
        case 'email': return typeof v === 'string' && /^[^\s@]+@[^\s@]+$/.test(v) ? { url: 'mailto:' + v } : null;
        case 'whatsapp': return typeof v === 'string' && /^\d{8,15}$/.test(v) ? { url: 'https://wa.me/' + v } : null;
        case 'map': return typeof v === 'string' && v ? { url: 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(v) } : null;
        default: return null;
    }
}
