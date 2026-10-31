// Appointment requests from the booking block: pick a service, a day and a time, add a name and phone number, and
// send it to the owner on WhatsApp or by email (they confirm it). Days, hours and slot length come from the block;
// there's no calendar on a server, so the owner keeps track of what's taken.
import { useMemo, useState } from 'react';
import { Alert, Linking, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { dateNames, t, tIn } from './i18n';
import { onLook } from './theme';
import { Icon } from './blocks';
import { str } from './content';
import { Sheet } from './order';

const mins = t => { const [h, m] = String(t).split(':').map(Number); return h * 60 + m; };
const hhmm = n => `${String(Math.floor(n / 60)).padStart(2, '0')}:${String(n % 60).padStart(2, '0')}`;
const label = (d, n) => `${n.days[d.getDay()]} ${d.getDate()} ${n.months[d.getMonth()]}`;

/** The booking block on a screen: what can be booked, and a button. */
export function BookingBlock({ b, ctx }) {
    const services = Array.isArray(b.services) ? b.services : [];
    return (
        <View style={styles.card}>
            <View style={[styles.badge, { backgroundColor: ctx.primary + '1f' }]}><Icon name="calendar" color={ctx.primary} size={22} /></View>
            <Text style={styles.title}>{str(b.title) || t('book_appointment')}</Text>
            {!!str(b.text) && <Text style={styles.text}>{b.text}</Text>}
            {services.length > 0 && (
                <View style={styles.services}>
                    {services.map((s, i) => (
                        <View key={i} style={styles.service}>
                            <Text style={styles.serviceName}>{str(s?.name)}</Text>
                            {!!str(s?.info) && <Text style={styles.muted}>{s.info}</Text>}
                        </View>
                    ))}
                </View>
            )}
            <Pressable onPress={() => ctx.openBooking(b)} style={({ pressed }) => [styles.btn, { backgroundColor: ctx.primary }, pressed && { opacity: 0.8 }]} accessibilityRole="button">
                <Text style={[styles.btnText, { color: ctx.onPrimary }]}>{t('book_appointment')}</Text>
            </Pressable>
        </View>
    );
}

const Chip = ({ on, onPress, children, color, wide }) => (
    <Pressable onPress={onPress} style={[styles.chip, wide && styles.chipWide, on && { backgroundColor: color, borderColor: color }]} accessibilityRole="button" accessibilityState={{ selected: on }}>
        {children(on)}
    </Pressable>
);

/** The request: service, day, time, name, phone, note → WhatsApp or email. */
export function BookingSheet({ block, ctx, insets, appName, onClose }) {
    const [service, setService] = useState(null);
    const [day, setDay] = useState(null);
    const [time, setTime] = useState(null);
    const [name, setName] = useState('');
    const [phone, setPhone] = useState('');
    const [note, setNote] = useState('');
    const [sent, setSent] = useState(false);
    const shop = ctx.shop || {};
    const ml = ctx.mainLang || 'en'; // the owner reads requests in the app's main language
    const mn = useMemo(() => dateNames(ml), [ml]);
    const dn = useMemo(() => dateNames(ctx.lang || 'en'), [ctx.lang]);
    const b = block || {};
    const services = Array.isArray(b.services) ? b.services : [];

    // Open days from today, as far ahead as the owner allows
    const days = useMemo(() => {
        const open = Array.isArray(b.days) ? b.days : [];
        const out = [];
        const d = new Date(); d.setHours(0, 0, 0, 0);
        for (let i = 0; i <= (b.ahead || 30); i++) {
            const x = new Date(d); x.setDate(d.getDate() + i);
            if (open.includes(x.getDay())) out.push(x);
        }
        return out;
    }, [block]); // eslint-disable-line react-hooks/exhaustive-deps

    // Start times on the chosen day; today, only those at least an hour away
    const times = useMemo(() => {
        if (!day || !b.from || !b.to) return [];
        const slot = b.slot || 30, out = [];
        const now = new Date(), isToday = day.toDateString() === now.toDateString(), soonest = now.getHours() * 60 + now.getMinutes() + 60;
        for (let t = mins(b.from); t + slot <= mins(b.to); t += slot) if (!isToday || t >= soonest) out.push(hhmm(t));
        return out;
    }, [day, block]); // eslint-disable-line react-hooks/exhaustive-deps

    const ready = (!services.length || service !== null) && day && time && name.trim();
    const close = () => { setSent(false); onClose(); };
    const send = how => {
        const text = [
            tIn(ml, 'm_book'),
            service !== null ? `${tIn(ml, 'm_service')}: ${str(services[service]?.name)}` : '',
            `${tIn(ml, 'm_when')}: ${label(day, mn)}, ${time}`,
            `${tIn(ml, 'm_name')}: ${name.trim()}`,
            phone.trim() ? `${tIn(ml, 'm_phone')}: ${phone.trim()}` : '',
            note.trim() ? `${tIn(ml, 'm_note')}: ${note.trim()}` : '',
            tIn(ml, 'm_sent_from', { app: appName }),
        ].filter(Boolean).join('\n');
        const url = how === 'whatsapp'
            ? `https://wa.me/${shop.whatsapp}?text=${encodeURIComponent(text)}`
            : `mailto:${shop.email}?subject=${encodeURIComponent(tIn(ml, 'm_book_subject', { app: appName }))}&body=${encodeURIComponent(text)}`;
        Linking.openURL(url).then(() => setSent(true)).catch(() => Alert.alert(t('no_app_title'), t('no_app', { via: how === 'whatsapp' ? t('whatsapp_app') : t('email_app'), to: how === 'whatsapp' ? '+' + shop.whatsapp : shop.email })));
    };

    return (
        <Sheet visible={!!block} onClose={close} insets={insets}>
            <Text style={styles.sheetTitle}>{str(b.title) || t('book_appointment')}</Text>
            {sent ? (
                <View style={{ gap: 12, paddingVertical: 8 }}>
                    <Text style={styles.text}>{t('request_ready', { via: shop.whatsapp ? t('whatsapp_app') : t('email_app'), app: appName })}</Text>
                    <Pressable onPress={() => { setTime(null); setNote(''); close(); }} style={[styles.btn, { backgroundColor: ctx.primary }]} accessibilityRole="button">
                        <Text style={[styles.btnText, { color: ctx.onPrimary }]}>{t('done')}</Text>
                    </Pressable>
                </View>
            ) : (
                <ScrollView style={{ flexShrink: 1 }} contentContainerStyle={{ gap: 12 }} keyboardShouldPersistTaps="handled">
                    {services.length > 0 && <>
                        <Text style={styles.step}>{t('service')}</Text>
                        <View style={styles.wrap}>
                            {services.map((s, i) => (
                                <Chip key={i} on={service === i} onPress={() => setService(i)} color={ctx.primary} wide>
                                    {on => <><Text style={[styles.chipText, on && { color: ctx.onPrimary }]}>{str(s?.name)}</Text>{!!str(s?.info) && <Text style={[styles.chipSub, on && { color: ctx.onPrimary }]}>{s.info}</Text>}</>}
                                </Chip>
                            ))}
                        </View>
                    </>}
                    <Text style={styles.step}>{t('day')}</Text>
                    {days.length ? (
                        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
                            {days.map(d => (
                                <Chip key={d.toDateString()} on={day?.toDateString() === d.toDateString()} onPress={() => { setDay(d); setTime(null); }} color={ctx.primary}>
                                    {on => <><Text style={[styles.chipSub, on && { color: ctx.onPrimary }]}>{dn.days[d.getDay()]}</Text><Text style={[styles.dayNum, on && { color: ctx.onPrimary }]}>{d.getDate()}</Text><Text style={[styles.chipSub, on && { color: ctx.onPrimary }]}>{dn.months[d.getMonth()]}</Text></>}
                                </Chip>
                            ))}
                        </ScrollView>
                    ) : <Text style={styles.muted}>{t('no_days')}</Text>}
                    {day && <>
                        <Text style={styles.step}>{t('time')}</Text>
                        {times.length ? (
                            <View style={styles.wrap}>
                                {times.map(t => (
                                    <Chip key={t} on={time === t} onPress={() => setTime(t)} color={ctx.primary}>
                                        {on => <Text style={[styles.chipText, on && { color: ctx.onPrimary }]}>{t}</Text>}
                                    </Chip>
                                ))}
                            </View>
                        ) : <Text style={styles.muted}>{t('no_times')}</Text>}
                    </>}
                    <Text style={styles.step}>{t('your_details')}</Text>
                    <TextInput value={name} onChangeText={setName} placeholder={t('your_name')} style={styles.input} maxLength={60} placeholderTextColor="#94a3b8" />
                    <TextInput value={phone} onChangeText={setPhone} placeholder={t('phone')} style={styles.input} maxLength={25} keyboardType="phone-pad" placeholderTextColor="#94a3b8" />
                    <TextInput value={note} onChangeText={setNote} placeholder={t('anything_else')} style={[styles.input, { minHeight: 64 }]} maxLength={300} multiline placeholderTextColor="#94a3b8" />
                    {!ready && <Text style={styles.muted}>{t('pick_to_send')}</Text>}
                    {!!shop.whatsapp && (
                        <Pressable disabled={!ready} onPress={() => send('whatsapp')} style={[styles.btn, { backgroundColor: '#25d366' }, !ready && styles.off]} accessibilityRole="button" accessibilityState={{ disabled: !ready }}>
                            <Text style={[styles.btnText, { color: '#05341b' }]}>{t('send_request_whatsapp')}</Text>
                        </Pressable>
                    )}
                    {!!shop.email && (
                        <Pressable disabled={!ready} onPress={() => send('email')} style={[styles.btn, shop.whatsapp ? { borderWidth: 2, borderColor: ctx.primary } : { backgroundColor: ctx.primary }, !ready && styles.off]} accessibilityRole="button" accessibilityState={{ disabled: !ready }}>
                            <Text style={[styles.btnText, { color: shop.whatsapp ? ctx.primary : ctx.onPrimary }]}>{t('send_request_email')}</Text>
                        </Pressable>
                    )}
                </ScrollView>
            )}
        </Sheet>
    );
}

let styles;
let T;
const makeStyles = (t, r, f) => StyleSheet.create({
    card: { marginHorizontal: 16, marginTop: 14, backgroundColor: t.card, borderRadius: r(18), padding: 18, gap: 10, borderWidth: StyleSheet.hairlineWidth, borderColor: t.border },
    badge: { width: 44, height: 44, borderRadius: r(14), alignItems: 'center', justifyContent: 'center' },
    title: { fontFamily: f, fontSize: 20, fontWeight: '800', color: t.ink },
    sheetTitle: { fontFamily: f, fontSize: 22, fontWeight: '800', color: t.ink },
    text: { fontFamily: f, fontSize: 15, lineHeight: 22, color: t.text },
    services: { gap: 6 },
    service: { flexDirection: 'row', justifyContent: 'space-between', gap: 10, paddingVertical: 6, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: t.line },
    serviceName: { fontFamily: f, fontSize: 15, fontWeight: '600', color: t.ink, flexShrink: 1 },
    muted: { fontFamily: f, fontSize: 14, color: t.muted },
    btn: { borderRadius: r(14), paddingVertical: 15, alignItems: 'center', marginTop: 4 },
    btnText: { fontFamily: f, fontSize: 16, fontWeight: '700' },
    off: { opacity: 0.45 },
    step: { fontFamily: f, fontSize: 13, fontWeight: '700', color: t.muted, textTransform: 'uppercase', letterSpacing: 0.6, marginTop: 4 },
    wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
    chip: { minWidth: 64, alignItems: 'center', paddingHorizontal: 12, paddingVertical: 10, borderRadius: r(12), borderWidth: 1.5, borderColor: t.border, backgroundColor: t.card },
    chipWide: { alignItems: 'flex-start' },
    chipText: { fontFamily: f, fontSize: 15, fontWeight: '700', color: t.ink },
    chipSub: { fontFamily: f, fontSize: 12, color: t.muted },
    dayNum: { fontFamily: f, fontSize: 20, fontWeight: '800', color: t.ink },
    input: { fontFamily: f, borderWidth: 1, borderColor: t.border, borderRadius: r(12), paddingHorizontal: 14, paddingVertical: 12, fontSize: 15, color: t.ink, textAlignVertical: 'top' },
});
onLook(({ t, r, f }) => { T = t; styles = makeStyles(t, r, f); });
