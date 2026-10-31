// Native screens: each block made in the builder (banner, heading, text, picture, button, list, gallery, contact,
// opening hours, video, divider, slider, cards, reviews, FAQ, social links, notice, spacer) drawn with React Native
// components. Values are read through content.js, which
// checks them, and unknown block types are skipped, so an app keeps working when the builder gains new blocks.
import { useState } from 'react';
import { FlatList, Image, ImageBackground, LayoutAnimation, Modal, Pressable, ScrollView, StyleSheet, Text, useWindowDimensions, View } from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { action, image, str } from './content';
import ICONS from './icons.json';

const INK = '#0f172a';
const MUTED = '#64748b';
const LINE = 'rgba(15,23,42,0.08)';

/** Light text on dark colours, dark text on light ones. */
export const isDark = hex => {
    const [r, g, b] = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16));
    return r * 0.299 + g * 0.587 + b * 0.114 < 150;
};

export function Icon({ name, color, size = 24, width = 1.8 }) {
    return (
        <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={width} strokeLinecap="round" strokeLinejoin="round">
            <Path d={ICONS[name] ?? ICONS.home} />
        </Svg>
    );
}

const Chevron = () => (
    <Svg width={18} height={18} viewBox="0 0 24 24" fill="none" stroke={MUTED} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
        <Path d="M9 6l6 6-6 6" />
    </Svg>
);

/** Something that can be tapped: opens its link or tab, with a pressed look. */
function Tap({ act, ctx, style, children, label }) {
    const go = action(act);
    if (!go) return <View style={style}>{children}</View>;
    const onPress = () => (go.tab !== undefined ? ctx.openTab(go.tab) : ctx.openUrl(go.url));
    return (
        <Pressable onPress={onPress} style={({ pressed }) => [style, pressed && styles.pressed]} accessibilityRole={go.tab !== undefined ? 'button' : 'link'} accessibilityLabel={label}>
            {children}
        </Pressable>
    );
}

function Hero({ b, ctx }) {
    const img = image(b.image, ctx.base);
    const btn = b.button && str(b.button.label) ? b.button : null;
    const inner = (
        <View style={[styles.heroInner, img && styles.heroShade]}>
            {!!str(b.title) && <Text style={[styles.heroTitle, { color: img ? '#fff' : ctx.onPrimary }]} accessibilityRole="header">{b.title}</Text>}
            {!!str(b.text) && <Text style={[styles.heroText, { color: img ? 'rgba(255,255,255,0.92)' : ctx.onPrimary }]}>{b.text}</Text>}
            {btn && (
                <Tap act={btn.action} ctx={ctx} style={[styles.heroBtn, { backgroundColor: img ? ctx.primary : ctx.onPrimary }]} label={btn.label}>
                    <Text style={[styles.btnText, { color: img ? ctx.onPrimary : ctx.primary }]}>{btn.label}</Text>
                </Tap>
            )}
        </View>
    );
    return img
        ? <ImageBackground source={{ uri: img.uri }} style={styles.hero} imageStyle={styles.heroImg} resizeMode="cover">{inner}</ImageBackground>
        : <View style={[styles.hero, { backgroundColor: ctx.primary }]}>{inner}</View>;
}

function Picture({ b, ctx }) {
    const img = image(b.image, ctx.base);
    if (!img) return null;
    return (
        <View style={styles.pad}>
            <Tap act={b.action} ctx={ctx} label={str(b.caption) || 'Picture'}>
                <Image source={{ uri: img.uri }} style={[styles.picture, { aspectRatio: Math.max(0.5, Math.min(img.ratio, 3)) }, b.round && styles.round]} resizeMode="cover" accessibilityIgnoresInvertColors
                    accessible accessibilityRole="image" accessibilityLabel={str(b.alt) || str(b.caption) || 'Picture'} />
            </Tap>
            {!!str(b.caption) && <Text style={styles.caption}>{b.caption}</Text>}
        </View>
    );
}

function Button({ b, ctx }) {
    const outline = b.style === 'outline';
    return (
        <View style={styles.pad}>
            <Tap act={b.action} ctx={ctx} label={str(b.label)} style={[styles.btn, outline ? { borderWidth: 2, borderColor: ctx.primary } : { backgroundColor: ctx.primary }]}>
                <Text style={[styles.btnText, { color: outline ? ctx.primary : ctx.onPrimary }]}>{str(b.label)}</Text>
            </Tap>
        </View>
    );
}

function List({ b, ctx }) {
    const items = Array.isArray(b.items) ? b.items : [];
    return (
        <View style={styles.pad}>
            {!!str(b.title) && <Text style={styles.cardTitle} accessibilityRole="header">{b.title}</Text>}
            <View style={styles.card}>
                {items.map((it, i) => {
                    const img = image(it?.image, ctx.base);
                    const go = action(it?.action);
                    return (
                        <Tap key={i} act={it?.action} ctx={ctx} label={str(it?.title)} style={[styles.row, i > 0 && styles.rowLine]}>
                            {img && <Image source={{ uri: img.uri }} style={styles.thumb} />}
                            <View style={styles.rowBody}>
                                {!!str(it?.title) && <Text style={styles.rowTitle}>{it.title}</Text>}
                                {!!str(it?.text) && <Text style={styles.rowText}>{it.text}</Text>}
                            </View>
                            {!!str(it?.price) && <Text style={[styles.price, { color: ctx.primary }]}>{it.price}</Text>}
                            {go && <Chevron />}
                        </Tap>
                    );
                })}
            </View>
        </View>
    );
}

/** Pictures in a grid; tapping one opens them full screen, swipe to see the rest. */
function Gallery({ b, ctx }) {
    const { width } = useWindowDimensions();
    const [open, setOpen] = useState(-1);
    const imgs = (Array.isArray(b.images) ? b.images : []).map(i => image(i, ctx.base)).filter(Boolean);
    const cols = b.columns === 3 ? 3 : 2, gap = 8, size = (width - 32 - gap * (cols - 1)) / cols;
    return (
        <View style={[styles.pad, styles.grid, { gap }]}>
            {imgs.map((img, i) => (
                <Pressable key={i} onPress={() => setOpen(i)} accessibilityRole="imagebutton" accessibilityLabel={`Picture ${i + 1} of ${imgs.length}`}>
                    <Image source={{ uri: img.uri }} style={{ width: size, height: size, borderRadius: 10, backgroundColor: LINE }} />
                </Pressable>
            ))}
            <Modal visible={open >= 0} animationType="fade" onRequestClose={() => setOpen(-1)} statusBarTranslucent>
                <View style={styles.viewer}>
                    <FlatList data={imgs} horizontal pagingEnabled initialScrollIndex={Math.max(open, 0)} keyExtractor={(_, i) => String(i)}
                        getItemLayout={(_, i) => ({ length: width, offset: width * i, index: i })} showsHorizontalScrollIndicator={false}
                        renderItem={({ item }) => <Image source={{ uri: item.uri }} style={{ width, height: '100%' }} resizeMode="contain" />} />
                    <Pressable onPress={() => setOpen(-1)} style={styles.close} accessibilityRole="button" accessibilityLabel="Close">
                        <Text style={styles.closeText}>✕</Text>
                    </Pressable>
                </View>
            </Modal>
        </View>
    );
}

function Contact({ b, ctx }) {
    const rows = [
        b.phone && { icon: 'phone', label: 'Call', value: b.phone, act: { kind: 'tel', value: b.phone } },
        b.whatsapp && { icon: 'chat', label: 'WhatsApp', value: '+' + b.whatsapp, act: { kind: 'whatsapp', value: b.whatsapp } },
        b.email && { icon: 'mail', label: 'Email', value: b.email, act: { kind: 'email', value: b.email } },
        b.address && { icon: 'location', label: 'Directions', value: b.address, act: { kind: 'map', value: b.address } },
        b.website && { icon: 'globe', label: 'Website', value: str(b.website).replace(/^https:\/\/(www\.)?/, '').replace(/\/$/, ''), act: { kind: 'url', value: b.website } },
    ].filter(Boolean);
    return (
        <View style={styles.pad}>
            {!!str(b.title) && <Text style={styles.cardTitle} accessibilityRole="header">{b.title}</Text>}
            <View style={styles.card}>
                {rows.map((r, i) => (
                    <Tap key={r.icon} act={r.act} ctx={ctx} label={`${r.label}: ${str(r.value)}`} style={[styles.row, i > 0 && styles.rowLine]}>
                        <View style={[styles.badge, { backgroundColor: ctx.primary + '1f' }]}><Icon name={r.icon} color={ctx.primary} size={20} /></View>
                        <View style={styles.rowBody}>
                            <Text style={styles.rowLabel}>{r.label}</Text>
                            <Text style={styles.rowTitle}>{str(r.value)}</Text>
                        </View>
                        <Chevron />
                    </Tap>
                ))}
            </View>
        </View>
    );
}

function Hours({ b }) {
    const rows = Array.isArray(b.rows) ? b.rows : [];
    return (
        <View style={styles.pad}>
            {!!str(b.title) && <Text style={styles.cardTitle} accessibilityRole="header">{b.title}</Text>}
            <View style={styles.card}>
                {rows.map((r, i) => (
                    <View key={i} style={[styles.row, i > 0 && styles.rowLine]}>
                        <Text style={[styles.rowTitle, styles.rowBody]}>{str(r?.day)}</Text>
                        <Text style={styles.hoursTime}>{str(r?.time)}</Text>
                    </View>
                ))}
            </View>
        </View>
    );
}

/** A YouTube video: its picture with a play button; tapping opens it in YouTube. */
function Video({ b, ctx }) {
    if (!/^[A-Za-z0-9_-]{11}$/.test(b.id || '')) return null;
    return (
        <View style={styles.pad}>
            <Pressable onPress={() => ctx.openUrl('https://www.youtube.com/watch?v=' + b.id)} accessibilityRole="link" accessibilityLabel={str(b.title) || 'Play video'}
                style={({ pressed }) => pressed && styles.pressed}>
                <ImageBackground source={{ uri: `https://i.ytimg.com/vi/${b.id}/hqdefault.jpg` }} style={styles.video} imageStyle={styles.round} resizeMode="cover">
                    <View style={styles.play}><Svg width={30} height={30} viewBox="0 0 24 24"><Path d="M8 5v14l11-7z" fill="#fff" /></Svg></View>
                </ImageBackground>
            </Pressable>
            {!!str(b.title) && <Text style={styles.caption}>{b.title}</Text>}
        </View>
    );
}

/** Pictures to swipe through, with dots under them. */
function Slider({ b, ctx }) {
    const { width } = useWindowDimensions();
    const [at, setAt] = useState(0);
    const imgs = (Array.isArray(b.images) ? b.images : []).map(i => image(i, ctx.base)).filter(Boolean);
    if (!imgs.length) return null;
    const w = width - 32, h = w / Math.max(0.8, Math.min(imgs[0].ratio, 2.2));
    return (
        <View style={styles.pad}>
            <FlatList data={imgs} horizontal pagingEnabled showsHorizontalScrollIndicator={false} keyExtractor={(_, i) => String(i)}
                style={[styles.round, { width: w, height: h }]}
                onMomentumScrollEnd={e => setAt(Math.round(e.nativeEvent.contentOffset.x / w))}
                renderItem={({ item, index }) => <Image source={{ uri: item.uri }} style={{ width: w, height: h }} resizeMode="cover" accessible accessibilityRole="image" accessibilityLabel={`Picture ${index + 1} of ${imgs.length}`} />} />
            {imgs.length > 1 && (
                <View style={styles.dots}>{imgs.map((_, i) => <View key={i} style={[styles.dot, i === at && { backgroundColor: ctx.primary, width: 18 }]} />)}</View>
            )}
        </View>
    );
}

/** Feature cards: an icon, a title and a line, in one or two columns. */
function Cards({ b, ctx }) {
    const items = Array.isArray(b.items) ? b.items : [];
    const two = b.columns !== 1;
    return (
        <View style={styles.pad}>
            {!!str(b.title) && <Text style={styles.cardTitle} accessibilityRole="header">{b.title}</Text>}
            <View style={[styles.grid, { gap: 10 }]}>
                {items.map((it, i) => (
                    <Tap key={i} act={it?.action} ctx={ctx} label={str(it?.title)} style={[styles.feature, { width: two ? '48.4%' : '100%' }]}>
                        <View style={[styles.featureIc, { backgroundColor: ctx.primary + '1a' }]}><Icon name={it?.icon} color={ctx.primary} size={22} /></View>
                        {!!str(it?.title) && <Text style={styles.featureTitle}>{it.title}</Text>}
                        {!!str(it?.text) && <Text style={styles.rowText}>{it.text}</Text>}
                    </Tap>
                ))}
            </View>
        </View>
    );
}

const Stars = ({ n, color }) => (
    <View style={{ flexDirection: 'row', gap: 2 }} accessibilityLabel={`${n} out of 5 stars`}>
        {[1, 2, 3, 4, 5].map(i => (
            <Svg key={i} width={15} height={15} viewBox="0 0 24 24"><Path d="M12 3l2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z" fill={i <= n ? color : '#e2e8f0'} /></Svg>
        ))}
    </View>
);

/** Reviews: cards to swipe sideways. */
function Reviews({ b }) {
    const { width } = useWindowDimensions();
    const items = Array.isArray(b.items) ? b.items : [];
    const w = items.length > 1 ? width * 0.78 : width - 32;
    return (
        <View style={{ marginTop: 14 }}>
            {!!str(b.title) && <Text style={[styles.cardTitle, { marginHorizontal: 20 }]} accessibilityRole="header">{b.title}</Text>}
            <ScrollView horizontal showsHorizontalScrollIndicator={false} snapToInterval={w + 10} decelerationRate="fast" contentContainerStyle={{ paddingHorizontal: 16, gap: 10 }}>
                {items.map((r, i) => (
                    <View key={i} style={[styles.card, styles.review, { width: w }]}>
                        <Stars n={Number.isInteger(r?.stars) ? r.stars : 5} color="#f59e0b" />
                        <Text style={styles.reviewText}>“{str(r?.text)}”</Text>
                        {!!str(r?.name) && <Text style={styles.rowLabel}>— {r.name}</Text>}
                    </View>
                ))}
            </ScrollView>
        </View>
    );
}

/** Questions that open to show the answer. */
function Faq({ b, ctx }) {
    const [open, setOpen] = useState(-1);
    const items = Array.isArray(b.items) ? b.items : [];
    const toggle = i => { LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut); setOpen(open === i ? -1 : i); };
    return (
        <View style={styles.pad}>
            {!!str(b.title) && <Text style={styles.cardTitle} accessibilityRole="header">{b.title}</Text>}
            <View style={styles.card}>
                {items.map((it, i) => (
                    <View key={i} style={i > 0 && styles.rowLine}>
                        <Pressable onPress={() => toggle(i)} style={styles.row} accessibilityRole="button" accessibilityState={{ expanded: open === i }}>
                            <Text style={[styles.rowTitle, styles.rowBody]}>{str(it?.q)}</Text>
                            <Text style={[styles.faqSign, { color: ctx.primary }]}>{open === i ? '−' : '+'}</Text>
                        </Pressable>
                        {open === i && <Text style={[styles.rowText, styles.faqA]} selectable>{str(it?.a)}</Text>}
                    </View>
                ))}
            </View>
        </View>
    );
}

const SOCIAL = { instagram: ['Instagram', '#e1306c'], facebook: ['Facebook', '#1877f2'], tiktok: ['TikTok', '#111111'], youtube: ['YouTube', '#ff0000'],
    x: ['X', '#111111'], linkedin: ['LinkedIn', '#0a66c2'], pinterest: ['Pinterest', '#e60023'], snapchat: ['Snapchat', '#fffc00'] };

function Social({ b, ctx }) {
    const links = b.links && typeof b.links === 'object' ? Object.entries(b.links).filter(([k, v]) => SOCIAL[k] && /^https:\/\//.test(v || '')) : [];
    return (
        <View style={styles.pad}>
            {!!str(b.title) && <Text style={[styles.cardTitle, styles.center]} accessibilityRole="header">{b.title}</Text>}
            <View style={[styles.grid, { gap: 8, justifyContent: 'center' }]}>
                {links.map(([k, url]) => (
                    <Pressable key={k} onPress={() => ctx.openUrl(url)} accessibilityRole="link" accessibilityLabel={SOCIAL[k][0]}
                        style={({ pressed }) => [styles.chip, { backgroundColor: SOCIAL[k][1] }, pressed && styles.pressed]}>
                        <Text style={[styles.chipText, { color: k === 'snapchat' ? '#111' : '#fff' }]}>{SOCIAL[k][0]}</Text>
                    </Pressable>
                ))}
            </View>
        </View>
    );
}

const TONES = { info: ['#eff6ff', '#1d4ed8'], success: ['#ecfdf5', '#047857'], warning: ['#fffbeb', '#b45309'] };

export function Block({ b, ctx }) {
    if (!b || typeof b !== 'object') return null;
    switch (b.type) {
        case 'hero': return <Hero b={b} ctx={ctx} />;
        case 'heading': return <Text style={[styles.pad, styles.heading, b.align === 'center' && styles.center]} accessibilityRole="header">{str(b.text)}</Text>;
        case 'text': return <Text style={[styles.pad, styles.text, b.align === 'center' && styles.center]} selectable>{str(b.text)}</Text>;
        case 'image': return <Picture b={b} ctx={ctx} />;
        case 'button': return <Button b={b} ctx={ctx} />;
        case 'list': return <List b={b} ctx={ctx} />;
        case 'gallery': return <Gallery b={b} ctx={ctx} />;
        case 'contact': return <Contact b={b} ctx={ctx} />;
        case 'hours': return <Hours b={b} />;
        case 'video': return <Video b={b} ctx={ctx} />;
        case 'divider': return <View style={styles.divider} />;
        case 'slider': return <Slider b={b} ctx={ctx} />;
        case 'cards': return <Cards b={b} ctx={ctx} />;
        case 'reviews': return <Reviews b={b} />;
        case 'faq': return <Faq b={b} ctx={ctx} />;
        case 'social': return <Social b={b} ctx={ctx} />;
        case 'notice': { const [bg, fg] = TONES[b.tone] || TONES.info; return <View style={[styles.pad, styles.notice, { backgroundColor: bg, borderColor: fg + '33' }]}><Text style={[styles.noticeText, { color: fg }]}>{str(b.text)}</Text></View>; }
        case 'spacer': return <View style={{ height: { small: 8, large: 44 }[b.size] ?? 22 }} />;
        default: return null;
    }
}

/** Shown when a screen has no blocks yet. */
export const Empty = () => <Text style={[styles.pad, styles.rowText, styles.center, { marginTop: 40 }]}>Nothing here yet.</Text>;

const styles = StyleSheet.create({
    pad: { marginHorizontal: 16, marginTop: 14 },
    center: { textAlign: 'center' },
    pressed: { opacity: 0.7 },
    hero: { minHeight: 220, justifyContent: 'flex-end' },
    heroImg: {},
    heroInner: { padding: 22, paddingTop: 40, gap: 8 },
    heroShade: { backgroundColor: 'rgba(0,0,0,0.38)', flexGrow: 1, justifyContent: 'flex-end' },
    heroTitle: { fontSize: 28, fontWeight: '800', lineHeight: 34 },
    heroText: { fontSize: 16, lineHeight: 23 },
    heroBtn: { alignSelf: 'flex-start', marginTop: 8, paddingHorizontal: 22, paddingVertical: 12, borderRadius: 999 },
    heading: { fontSize: 22, fontWeight: '800', color: INK, lineHeight: 28, marginTop: 22 },
    text: { fontSize: 16, lineHeight: 24, color: '#334155' },
    picture: { width: '100%', borderRadius: 14, backgroundColor: LINE },
    round: { borderRadius: 14 },
    caption: { fontSize: 13, color: MUTED, marginTop: 6, textAlign: 'center' },
    btn: { borderRadius: 12, paddingVertical: 14, alignItems: 'center' },
    btnText: { fontSize: 16, fontWeight: '700' },
    cardTitle: { fontSize: 13, fontWeight: '700', color: MUTED, textTransform: 'uppercase', letterSpacing: 0.6, marginBottom: 8, marginLeft: 4 },
    card: { backgroundColor: '#fff', borderRadius: 14, borderWidth: StyleSheet.hairlineWidth, borderColor: 'rgba(15,23,42,0.12)', overflow: 'hidden' },
    row: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 14, paddingVertical: 12, minHeight: 52 },
    rowLine: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: 'rgba(15,23,42,0.12)' },
    rowBody: { flex: 1, gap: 2 },
    rowTitle: { fontSize: 16, fontWeight: '600', color: INK },
    rowText: { fontSize: 14, lineHeight: 20, color: MUTED },
    rowLabel: { fontSize: 12, color: MUTED },
    price: { fontSize: 16, fontWeight: '700' },
    thumb: { width: 56, height: 56, borderRadius: 10, backgroundColor: LINE },
    badge: { width: 38, height: 38, borderRadius: 19, alignItems: 'center', justifyContent: 'center' },
    hoursTime: { fontSize: 15, color: MUTED },
    grid: { flexDirection: 'row', flexWrap: 'wrap' },
    viewer: { flex: 1, backgroundColor: '#000' },
    close: { position: 'absolute', top: 44, right: 16, width: 44, height: 44, borderRadius: 22, backgroundColor: 'rgba(255,255,255,0.18)', alignItems: 'center', justifyContent: 'center' },
    closeText: { color: '#fff', fontSize: 20, fontWeight: '700' },
    video: { width: '100%', aspectRatio: 16 / 9, alignItems: 'center', justifyContent: 'center', backgroundColor: '#000', borderRadius: 14 },
    play: { width: 64, height: 64, borderRadius: 32, backgroundColor: 'rgba(0,0,0,0.6)', alignItems: 'center', justifyContent: 'center', paddingLeft: 4 },
    dots: { flexDirection: 'row', justifyContent: 'center', gap: 6, marginTop: 10 },
    dot: { width: 7, height: 7, borderRadius: 4, backgroundColor: '#cbd5e1' },
    feature: { backgroundColor: '#fff', borderRadius: 16, padding: 14, gap: 6, borderWidth: StyleSheet.hairlineWidth, borderColor: 'rgba(15,23,42,0.12)' },
    featureIc: { width: 40, height: 40, borderRadius: 12, alignItems: 'center', justifyContent: 'center', marginBottom: 2 },
    featureTitle: { fontSize: 15, fontWeight: '700', color: INK },
    review: { padding: 16, gap: 10 },
    reviewText: { fontSize: 15, lineHeight: 22, color: '#334155' },
    faqSign: { fontSize: 22, fontWeight: '600', width: 22, textAlign: 'center' },
    faqA: { paddingHorizontal: 14, paddingBottom: 14, marginTop: -4 },
    chip: { paddingHorizontal: 16, paddingVertical: 10, borderRadius: 999 },
    chipText: { fontSize: 14, fontWeight: '700' },
    notice: { borderRadius: 14, padding: 14, borderWidth: 1 },
    noticeText: { fontSize: 15, lineHeight: 22, fontWeight: '600' },
    divider: { height: StyleSheet.hairlineWidth, backgroundColor: 'rgba(15,23,42,0.15)', marginHorizontal: 16, marginTop: 20, marginBottom: 6 },
});
