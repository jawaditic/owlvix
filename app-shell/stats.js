// Anonymous usage statistics for the app's owner: how often the app is opened and which pages are viewed. Counted on
// the phone per day and sent once a day (for finished days only) to the Owlvix server, which keeps daily totals.
// Nothing that identifies the phone or the person is sent.
import { File, Paths } from 'expo-file-system';

const file = () => new File(Paths.document, 'owlvix-stats.json');
let state = null;
let lastOpen = 0;
let sending = false;

const today = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const load = () => {
    if (state) return state;
    try { state = JSON.parse(file().textSync()); } catch {}
    if (!state || typeof state !== 'object' || typeof state.days !== 'object') state = { reported: false, days: {} };
    return state;
};
const keep = () => { try { file().writeSync(JSON.stringify(state)); } catch {} };
const day = () => (load().days[today()] ??= { opens: 0, pages: {} });

/** The app was opened or came back to the front (counted at most once every 30 seconds). */
export function countOpen() {
    if (Date.now() - lastOpen < 30000) return;
    lastOpen = Date.now();
    day().opens += 1;
    keep();
}

/** A page was shown. */
export function countPage(i) {
    if (!Number.isInteger(i)) return;
    const p = day().pages;
    p[i] = (p[i] || 0) + 1;
    keep();
}

/** Sends the finished days (oldest first) and forgets them once the server has them. */
export async function sendStats(url) {
    if (!url || sending) return;
    const s = load(), now = today();
    const days = Object.keys(s.days).filter(d => d < now).sort().slice(-7);
    Object.keys(s.days).filter(d => d < now && !days.includes(d)).forEach(d => delete s.days[d]); // older than a week
    if (!days.length) return;
    sending = true;
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ new: !s.reported, days: days.map(d => ({ day: d, opens: s.days[d].opens, pages: s.days[d].pages })) }),
        });
        if (!res.ok && res.status !== 422) return; // try again next time
        days.forEach(d => delete s.days[d]);
        s.reported = true;
        keep();
    } catch {} finally { sending = false; }
}
