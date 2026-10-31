// Ordering from the products block: a product's details, the order basket and sending it. There's no payment: the
// order is written out and opened in WhatsApp or the email app, so the customer sees it and sends it themselves to
// the owner (shop settings in the content: currency, WhatsApp number, email, a note such as "Pay on collection").
import { useState } from 'react';
import { Image, KeyboardAvoidingView, Linking, Modal, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { Icon, money } from './blocks';
import { image, str } from './content';

const INK = '#0f172a';
const MUTED = '#64748b';

const Stepper = ({ value, onChange, color, label }) => (
    <View style={styles.stepper} accessibilityLabel={`${label}: ${value}`}>
        <Pressable onPress={() => onChange(value - 1)} style={[styles.stepBtn, { borderColor: color }]} accessibilityRole="button" accessibilityLabel="One less" hitSlop={6}>
            <Text style={[styles.stepText, { color }]}>−</Text>
        </Pressable>
        <Text style={styles.qty}>{value}</Text>
        <Pressable onPress={() => onChange(value + 1)} style={[styles.stepBtn, { borderColor: color }]} accessibilityRole="button" accessibilityLabel="One more" hitSlop={6}>
            <Text style={[styles.stepText, { color }]}>+</Text>
        </Pressable>
    </View>
);

/** A bottom sheet over the app. */
function Sheet({ visible, onClose, children, insets }) {
    return (
        <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose} statusBarTranslucent>
            <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={styles.sheetWrap}>
                <Pressable style={styles.shade} onPress={onClose} accessibilityRole="button" accessibilityLabel="Close" />
                <View style={[styles.sheet, { paddingBottom: Math.max(insets.bottom, 12) + 8 }]}>
                    <View style={styles.grip} />
                    {children}
                </View>
            </KeyboardAvoidingView>
        </Modal>
    );
}

/** One product: picture, price, description, how many, "Add to order". */
export function ProductSheet({ product, ctx, insets, onClose, onAdd }) {
    const [qty, setQty] = useState(1);
    const img = product ? image(product.image, ctx.base) : null;
    return (
        <Sheet visible={!!product} onClose={onClose} insets={insets}>
            {product && (
                <ScrollView style={{ maxHeight: 560 }} contentContainerStyle={{ gap: 10 }}>
                    {img && <Image source={{ uri: img.uri }} style={[styles.bigImg, { aspectRatio: Math.max(1, Math.min(img.ratio, 2)) }]} resizeMode="cover" />}
                    <Text style={styles.title}>{product.name}</Text>
                    <Text style={[styles.price, { color: ctx.primary }]}>{typeof product.price === 'number' ? money(product.price, ctx.shop) : 'Price on request'}</Text>
                    {!!str(product.text) && <Text style={styles.text}>{product.text}</Text>}
                    <View style={styles.row}>
                        <Stepper value={qty} onChange={n => setQty(Math.max(1, Math.min(99, n)))} color={ctx.primary} label="How many" />
                        <Pressable onPress={() => { onAdd(product, qty); setQty(1); }} style={[styles.btn, { backgroundColor: ctx.primary, flex: 1 }]} accessibilityRole="button">
                            <Text style={[styles.btnText, { color: ctx.onPrimary }]}>Add to order{typeof product.price === 'number' ? ` · ${money(product.price * qty, ctx.shop)}` : ''}</Text>
                        </Pressable>
                    </View>
                </ScrollView>
            )}
        </Sheet>
    );
}

/** The floating basket button, above the bottom bar. */
export function OrderButton({ cart, ctx, bottom, onPress }) {
    const count = cart.reduce((n, l) => n + l.qty, 0);
    if (!count) return null;
    const total = cart.reduce((n, l) => n + (typeof l.price === 'number' ? l.price * l.qty : 0), 0);
    return (
        <Pressable onPress={onPress} style={[styles.fab, { bottom, backgroundColor: ctx.primary }]} accessibilityRole="button" accessibilityLabel={`Your order: ${count} items`}>
            <Icon name="cart" color={ctx.onPrimary} size={20} />
            <Text style={[styles.fabText, { color: ctx.onPrimary }]}>{count} · {total ? money(total, ctx.shop) : 'View order'}</Text>
        </Pressable>
    );
}

/** The order basket: change amounts, add a name and note, send it on WhatsApp or by email. */
export function OrderSheet({ visible, cart, setCart, ctx, insets, appName, onClose }) {
    const [name, setName] = useState('');
    const [note, setNote] = useState('');
    const [sent, setSent] = useState(false);
    const shop = ctx.shop || {};
    const total = cart.reduce((n, l) => n + (typeof l.price === 'number' ? l.price * l.qty : 0), 0);
    const ask = cart.some(l => typeof l.price !== 'number');
    const setQty = (i, q) => setCart(c => (q < 1 ? c.filter((_, j) => j !== i) : c.map((l, j) => (j === i ? { ...l, qty: Math.min(99, q) } : l))));
    const message = () => [
        'Hello! I’d like to order:',
        ...cart.map(l => `${l.qty} × ${l.name}${typeof l.price === 'number' ? ' — ' + money(l.price * l.qty, shop) : ''}`),
        total ? `Total: ${money(total, shop)}${ask ? ' (+ items priced on request)' : ''}` : '',
        name.trim() ? `Name: ${name.trim()}` : '',
        note.trim() ? `Note: ${note.trim()}` : '',
        `(Sent from the ${appName} app)`,
    ].filter(Boolean).join('\n');
    const send = how => {
        const text = message();
        const url = how === 'whatsapp'
            ? `https://wa.me/${shop.whatsapp}?text=${encodeURIComponent(text)}`
            : `mailto:${shop.email}?subject=${encodeURIComponent(`Order from the ${appName} app`)}&body=${encodeURIComponent(text)}`;
        Linking.openURL(url).then(() => setSent(true)).catch(() => {});
    };
    const close = () => { setSent(false); onClose(); };
    return (
        <Sheet visible={visible} onClose={close} insets={insets}>
            <Text style={styles.title}>Your order</Text>
            {sent ? (
                <View style={{ gap: 12, paddingVertical: 8 }}>
                    <Text style={styles.text}>Your order is ready to send in {shop.whatsapp ? 'WhatsApp' : 'your email app'}. Once you’ve sent it, {appName} will get back to you.</Text>
                    <Pressable onPress={() => { setCart([]); setNote(''); close(); }} style={[styles.btn, { backgroundColor: ctx.primary }]} accessibilityRole="button">
                        <Text style={[styles.btnText, { color: ctx.onPrimary }]}>Done — start a new order</Text>
                    </Pressable>
                    <Pressable onPress={() => setSent(false)} style={styles.link} accessibilityRole="button"><Text style={[styles.linkText, { color: ctx.primary }]}>Back to my order</Text></Pressable>
                </View>
            ) : (
                <ScrollView style={{ maxHeight: 560 }} contentContainerStyle={{ gap: 12 }} keyboardShouldPersistTaps="handled">
                    {cart.map((l, i) => (
                        <View key={i} style={styles.line}>
                            <View style={{ flex: 1 }}>
                                <Text style={styles.lineName}>{l.name}</Text>
                                <Text style={styles.lineMuted}>{typeof l.price === 'number' ? money(l.price * l.qty, shop) : 'Price on request'}</Text>
                            </View>
                            <Stepper value={l.qty} onChange={q => setQty(i, q)} color={ctx.primary} label={l.name} />
                        </View>
                    ))}
                    {!!total && <Text style={styles.total}>Total {money(total, shop)}{ask ? ' + items priced on request' : ''}</Text>}
                    {!!str(shop.note) && <Text style={styles.lineMuted}>{shop.note}</Text>}
                    <TextInput value={name} onChangeText={setName} placeholder="Your name" style={styles.input} maxLength={60} placeholderTextColor="#94a3b8" />
                    <TextInput value={note} onChangeText={setNote} placeholder="Note (time, address, allergies…)" style={[styles.input, { minHeight: 70 }]} maxLength={400} multiline placeholderTextColor="#94a3b8" />
                    {!!shop.whatsapp && (
                        <Pressable onPress={() => send('whatsapp')} style={[styles.btn, { backgroundColor: '#25d366' }]} accessibilityRole="button">
                            <Text style={[styles.btnText, { color: '#05341b' }]}>Send order on WhatsApp</Text>
                        </Pressable>
                    )}
                    {!!shop.email && (
                        <Pressable onPress={() => send('email')} style={[styles.btn, shop.whatsapp ? styles.btnOutline : { backgroundColor: ctx.primary }, shop.whatsapp && { borderColor: ctx.primary }]} accessibilityRole="button">
                            <Text style={[styles.btnText, { color: shop.whatsapp ? ctx.primary : ctx.onPrimary }]}>Send order by email</Text>
                        </Pressable>
                    )}
                </ScrollView>
            )}
        </Sheet>
    );
}

const styles = StyleSheet.create({
    sheetWrap: { flex: 1, justifyContent: 'flex-end' },
    shade: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(15,23,42,0.45)' },
    sheet: { backgroundColor: '#fff', borderTopLeftRadius: 22, borderTopRightRadius: 22, paddingHorizontal: 18, paddingTop: 10, gap: 10, elevation: 16 },
    grip: { alignSelf: 'center', width: 40, height: 5, borderRadius: 3, backgroundColor: '#e2e8f0', marginBottom: 6 },
    bigImg: { width: '100%', borderRadius: 16, backgroundColor: 'rgba(15,23,42,0.06)' },
    title: { fontSize: 22, fontWeight: '800', color: INK },
    price: { fontSize: 18, fontWeight: '800' },
    text: { fontSize: 15, lineHeight: 22, color: '#334155' },
    row: { flexDirection: 'row', alignItems: 'center', gap: 12, marginTop: 6 },
    stepper: { flexDirection: 'row', alignItems: 'center', gap: 10 },
    stepBtn: { width: 36, height: 36, borderRadius: 18, borderWidth: 1.5, alignItems: 'center', justifyContent: 'center' },
    stepText: { fontSize: 20, fontWeight: '700', marginTop: -2 },
    qty: { minWidth: 22, textAlign: 'center', fontSize: 16, fontWeight: '700', color: INK },
    btn: { borderRadius: 14, paddingVertical: 15, alignItems: 'center', paddingHorizontal: 14 },
    btnOutline: { borderWidth: 2, backgroundColor: 'transparent' },
    btnText: { fontSize: 16, fontWeight: '700' },
    link: { alignItems: 'center', paddingVertical: 8 },
    linkText: { fontSize: 15, fontWeight: '600' },
    fab: { position: 'absolute', right: 16, flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: 18, paddingVertical: 13, borderRadius: 999, elevation: 8, zIndex: 8,
        shadowColor: '#000', shadowOpacity: 0.2, shadowRadius: 10, shadowOffset: { width: 0, height: 4 } },
    fabText: { fontSize: 15, fontWeight: '800' },
    line: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingBottom: 12, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: 'rgba(15,23,42,0.12)' },
    lineName: { fontSize: 16, fontWeight: '600', color: INK },
    lineMuted: { fontSize: 14, color: MUTED },
    total: { fontSize: 17, fontWeight: '800', color: INK, textAlign: 'right' },
    input: { borderWidth: 1, borderColor: 'rgba(15,23,42,0.15)', borderRadius: 12, paddingHorizontal: 14, paddingVertical: 12, fontSize: 15, color: INK, textAlignVertical: 'top' },
});
