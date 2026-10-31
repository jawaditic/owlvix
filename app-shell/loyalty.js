// Loyalty card: stamps kept on this phone (one card per loyalty block, by its id). Staff type their PIN on the phone
// to add a stamp, and again to give the reward when the card is full. The content only has a hash of the PIN
// (sha256 of "card:PIN"); five wrong PINs lock the card for 10 minutes.
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { File, Paths } from 'expo-file-system';
import { onLook } from './theme';
import { Icon } from './blocks';
import { str } from './content';
import { Sheet } from './order';

/* ---------- SHA-256 (small, for checking the staff PIN) ---------- */
const K = [0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5, 0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
    0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da, 0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
    0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85, 0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
    0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3, 0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2];
export function sha256(text) {
    const bytes = [];
    for (let i = 0; i < text.length; i++) bytes.push(text.charCodeAt(i) & 0xff); // the card id and digits: plain ASCII
    const bits = bytes.length * 8;
    bytes.push(0x80);
    while (bytes.length % 64 !== 56) bytes.push(0);
    for (let i = 7; i >= 0; i--) bytes.push(i > 3 ? 0 : (bits >>> (i * 8)) & 0xff);
    const h = [0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19];
    const rot = (x, n) => (x >>> n) | (x << (32 - n));
    for (let o = 0; o < bytes.length; o += 64) {
        const w = new Array(64);
        for (let i = 0; i < 16; i++) w[i] = (bytes[o + i * 4] << 24) | (bytes[o + i * 4 + 1] << 16) | (bytes[o + i * 4 + 2] << 8) | bytes[o + i * 4 + 3];
        for (let i = 16; i < 64; i++) {
            const s0 = rot(w[i - 15], 7) ^ rot(w[i - 15], 18) ^ (w[i - 15] >>> 3), s1 = rot(w[i - 2], 17) ^ rot(w[i - 2], 19) ^ (w[i - 2] >>> 10);
            w[i] = (w[i - 16] + s0 + w[i - 7] + s1) | 0;
        }
        let [a, b, c, d, e, f, g, hh] = h;
        for (let i = 0; i < 64; i++) {
            const t1 = (hh + (rot(e, 6) ^ rot(e, 11) ^ rot(e, 25)) + ((e & f) ^ (~e & g)) + K[i] + w[i]) | 0;
            const t2 = ((rot(a, 2) ^ rot(a, 13) ^ rot(a, 22)) + ((a & b) ^ (a & c) ^ (b & c))) | 0;
            hh = g; g = f; f = e; e = (d + t1) | 0; d = c; c = b; b = a; a = (t1 + t2) | 0;
        }
        [a, b, c, d, e, f, g, hh].forEach((v, i) => { h[i] = (h[i] + v) | 0; });
    }
    return h.map(v => (v >>> 0).toString(16).padStart(8, '0')).join('');
}

/* ---------- the cards on this phone ---------- */
const file = () => new File(Paths.document, 'owlvix-loyalty.json');
let saved = null;
const load = () => {
    if (saved) return saved;
    try { saved = JSON.parse(file().textSync()); } catch {}
    if (!saved || typeof saved !== 'object') saved = {};
    return saved;
};
const keep = () => { try { Promise.resolve(file().write(JSON.stringify(saved))).catch(() => {}); } catch {} };
const cardOf = id => (load()[id] ??= { stamps: 0, rewards: 0, wrong: 0, lockedUntil: 0 });

let styles;
let T;

export function LoyaltyBlock({ b, ctx }) {
    const [state, setState] = useState(() => ({ ...cardOf(b.card) }));
    const [asking, setAsking] = useState(false);
    const need = Math.max(4, Math.min(12, b.stamps || 10));
    const full = state.stamps >= need;
    useEffect(() => { setState({ ...cardOf(b.card) }); }, [b.card]);
    const done = () => {
        const c = cardOf(b.card);
        if (full) { c.stamps = 0; c.rewards += 1; } else c.stamps = Math.min(need, c.stamps + 1);
        keep(); setState({ ...c }); setAsking(false);
    };
    return (
        <View style={styles.card}>
            <View style={styles.head}>
                <View style={[styles.badge, { backgroundColor: ctx.primary + '1f' }]}><Icon name="star" color={ctx.primary} size={22} /></View>
                <View style={{ flex: 1 }}>
                    <Text style={styles.title}>{str(b.title) || 'Loyalty card'}</Text>
                    <Text style={styles.muted}>{need} stamps = {str(b.reward)}</Text>
                </View>
            </View>
            {!!str(b.text) && <Text style={styles.text}>{b.text}</Text>}
            <View style={styles.stamps} accessibilityLabel={`${state.stamps} of ${need} stamps`}>
                {Array.from({ length: need }, (_, i) => (
                    <View key={i} style={[styles.stamp, i < state.stamps && { backgroundColor: ctx.primary, borderColor: ctx.primary }]}>
                        {i < state.stamps ? <Icon name="star" color={ctx.onPrimary} size={18} /> : <Text style={styles.stampNum}>{i + 1}</Text>}
                    </View>
                ))}
            </View>
            {full && <Text style={[styles.ready, { color: ctx.primary }]}>🎉 Your reward is ready: {str(b.reward)}! Show this to staff.</Text>}
            <Pressable onPress={() => setAsking(true)} style={({ pressed }) => [styles.btn, { backgroundColor: ctx.primary }, pressed && { opacity: 0.8 }]} accessibilityRole="button">
                <Text style={[styles.btnText, { color: ctx.onPrimary }]}>{full ? 'Get my reward' : 'Add a stamp'}</Text>
            </Pressable>
            {state.rewards > 0 && <Text style={[styles.muted, { textAlign: 'center' }]}>Rewards so far: {state.rewards}</Text>}
            <PinSheet visible={asking} b={b} ctx={ctx} full={full} onClose={() => setAsking(false)} onDone={done} />
        </View>
    );
}

/** Staff enter their PIN on the customer's phone. */
function PinSheet({ visible, b, ctx, full, onClose, onDone }) {
    const [pin, setPin] = useState('');
    const [error, setError] = useState('');
    const close = () => { setPin(''); setError(''); onClose(); };
    const check = () => {
        const c = cardOf(b.card);
        if (Date.now() < (c.lockedUntil || 0)) { setError('Too many wrong PINs. Please try again in a few minutes.'); return; }
        if (sha256(`${b.card}:${pin}`) === b.pinHash) {
            c.wrong = 0; keep(); setPin(''); setError(''); onDone();
        } else {
            c.wrong = (c.wrong || 0) + 1;
            if (c.wrong >= 5) { c.wrong = 0; c.lockedUntil = Date.now() + 10 * 60 * 1000; }
            keep(); setPin(''); setError('That PIN isn’t right.');
        }
    };
    return (
        <Sheet visible={visible} onClose={close} insets={ctx.insets || { bottom: 0 }}>
            <Text style={styles.sheetTitle}>{full ? 'Give the reward' : 'Add a stamp'}</Text>
            <Text style={styles.text}>Please hand your phone to a member of staff.</Text>
            <TextInput value={pin} onChangeText={v => setPin(v.replace(/\D/g, '').slice(0, 8))} placeholder="Staff PIN" keyboardType="number-pad" secureTextEntry
                style={styles.input} autoFocus onSubmitEditing={check} placeholderTextColor="#94a3b8" accessibilityLabel="Staff PIN" />
            {!!error && <Text style={styles.error}>{error}</Text>}
            <Pressable disabled={pin.length < 4} onPress={check} style={[styles.btn, { backgroundColor: ctx.primary }, pin.length < 4 && { opacity: 0.45 }]} accessibilityRole="button">
                <Text style={[styles.btnText, { color: ctx.onPrimary }]}>{full ? 'Give reward' : 'Add stamp'}</Text>
            </Pressable>
        </Sheet>
    );
}

const makeStyles = (t, r, f) => StyleSheet.create({
    card: { marginHorizontal: 16, marginTop: 14, backgroundColor: t.card, borderRadius: r(18), padding: 18, gap: 12, borderWidth: StyleSheet.hairlineWidth, borderColor: t.border },
    head: { flexDirection: 'row', alignItems: 'center', gap: 12 },
    badge: { width: 44, height: 44, borderRadius: r(14), alignItems: 'center', justifyContent: 'center' },
    title: { fontFamily: f, fontSize: 18, fontWeight: '800', color: t.ink },
    sheetTitle: { fontFamily: f, fontSize: 22, fontWeight: '800', color: t.ink },
    text: { fontFamily: f, fontSize: 15, lineHeight: 22, color: t.text },
    muted: { fontFamily: f, fontSize: 14, color: t.muted },
    stamps: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, justifyContent: 'center' },
    stamp: { width: 46, height: 46, borderRadius: r(999), borderWidth: 2, borderStyle: 'dashed', borderColor: t.border, alignItems: 'center', justifyContent: 'center' },
    stampNum: { fontFamily: f, fontSize: 13, color: t.muted, fontWeight: '600' },
    ready: { fontFamily: f, fontSize: 15, fontWeight: '700', textAlign: 'center' },
    btn: { borderRadius: r(14), paddingVertical: 15, alignItems: 'center' },
    btnText: { fontFamily: f, fontSize: 16, fontWeight: '700' },
    input: { fontFamily: f, borderWidth: 1, borderColor: t.border, borderRadius: r(12), paddingHorizontal: 14, paddingVertical: 14, fontSize: 22, letterSpacing: 6, textAlign: 'center', color: t.ink, backgroundColor: t.input },
    error: { fontFamily: f, fontSize: 14, color: '#dc2626', textAlign: 'center' },
});
onLook(({ t, r, f }) => { T = t; styles = makeStyles(t, r, f); });
