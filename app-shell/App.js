// An app made with the Owlvix mobile app builder (/mobile-app-builder). Its tabs are native screens built from
// blocks (blocks.js), or pages of the owner's website. What the tabs show comes from content.js: the copy built
// into the app, kept up to date from the server, so the owner's changes show without a new build. The name, icon,
// splash screen and colours of the system bars come from shell.json at build time (app.config.js).
import Constants from 'expo-constants';
import { useEventListener } from 'expo';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Animated, AppState, BackHandler, Image, Linking, Modal, Pressable, RefreshControl, ScrollView, StyleSheet, Text, useColorScheme, View } from 'react-native';
import { applyLook, look, mix, onLook } from './theme';
import { Enter, FadeUp, Loader, Press, setMotion } from './anim';
import { File, Paths } from 'expo-file-system';
import { LANGUAGES, setLanguage, t } from './i18n';
import { SafeAreaProvider, useSafeAreaInsets } from 'react-native-safe-area-context';
import Svg, { Path } from 'react-native-svg';
import { WebView } from 'react-native-webview';
import { useVideoPlayer, VideoView } from 'expo-video';
import INTRO from './intro';
import { Block, Empty, Icon, isDark } from './blocks';
import { color, fetchContent, setBundled, startContent, str, usableTabs } from './content';
import { countOpen, countPage, sendStats } from './stats';
import { OrderButton, OrderSheet, ProductSheet } from './order';
import { BookingSheet } from './booking';

const shell = Constants.expoConfig?.extra?.shell ?? {};
const CHECK_EVERY = 60 * 1000; // ask the server for changes at most once a minute when the app comes back

SplashScreen.preventAutoHideAsync().catch(() => {});
setBundled(shell.bundled); // pictures packed into this build

/** Opens a link outside the app: the browser, phone, email, WhatsApp, maps… */
const openOutside = url => {
    if (url.startsWith('intent:')) {
        // Android intent links: open the web fallback if the page gave one
        const fallback = /S\.browser_fallback_url=([^;]+)/.exec(url)?.[1];
        if (fallback) Linking.openURL(decodeURIComponent(fallback)).catch(() => {});
        return;
    }
    Linking.openURL(url).catch(() => {});
};

/* The language the app is shown in: the one picked in the ☰ menu (kept on the phone), else the phone's, else the
   app's main language — whichever the owner has translated the app into. */
const langFile = () => new File(Paths.document, 'owlvix-language.txt');
const savedLanguage = () => { try { const f = langFile(); return f.exists ? f.textSync().trim() : ''; } catch { return ''; } };
const phoneLanguage = () => { try { return (Intl.DateTimeFormat().resolvedOptions().locale || 'en').slice(0, 2).toLowerCase(); } catch { return 'en'; } };

// The app's icon for the opening loader (scripts/prepare.mjs writes it before each build)
const LOADER_ICON = (() => { try { return require('./assets/icon.png'); } catch { return null; } })();

const INTRO_PICTURE_MS = 2500;
const INTRO_MAX_MS = 15000; // a video longer than this is cut short

function IntroVideo({ source, onEnd }) {
    const player = useVideoPlayer(source, p => { p.muted = true; p.loop = false; p.play(); });
    useEventListener(player, 'playToEnd', onEnd);
    useEventListener(player, 'statusChange', ({ status }) => { if (status === 'error') onEnd(); });
    return <VideoView player={player} style={StyleSheet.absoluteFill} contentFit="cover" nativeControls={false} allowsFullscreen={false} allowsPictureInPicture={false} />;
}

/** The owner's full-screen picture or video, shown once when the app starts (after the splash). Tap Skip to go on. */
function Intro({ onDone }) {
    const fade = useRef(new Animated.Value(1)).current;
    const done = useRef(false);
    const insets = useSafeAreaInsets();
    const onDoneRef = useRef(onDone);
    onDoneRef.current = onDone;
    const finish = useCallback(() => {
        if (done.current) return;
        done.current = true;
        Animated.timing(fade, { toValue: 0, duration: 350, useNativeDriver: true }).start(() => onDoneRef.current());
    }, [fade]);
    useEffect(() => {
        const t = setTimeout(finish, INTRO.type === 'image' ? INTRO_PICTURE_MS : INTRO_MAX_MS);
        return () => clearTimeout(t);
    }, [finish]);
    return (
        <Animated.View style={[StyleSheet.absoluteFill, styles.intro, { opacity: fade }]}>
            {INTRO.type === 'video'
                ? <IntroVideo source={INTRO.source} onEnd={finish} />
                : <Image source={INTRO.source} style={StyleSheet.absoluteFill} resizeMode="cover" />}
            <Pressable onPress={finish} style={[styles.skip, { top: insets.top + 12 }]} accessibilityRole="button" accessibilityLabel={t('skip')}>
                <Text style={styles.skipText}>{t('skip')}</Text>
            </Pressable>
        </Animated.View>
    );
}

function Offline({ ctx, onRetry }) {
    return (
        <View style={styles.offline}>
            <Svg width={56} height={56} viewBox="0 0 24 24" fill="none" stroke={ctx.primary} strokeWidth={1.6} strokeLinecap="round" strokeLinejoin="round">
                <Path d="M2 8.5a15 15 0 0 1 20 0M5.5 12a10 10 0 0 1 13 0M9 15.5a5 5 0 0 1 6 0M12 19h.01M3 3l18 18" />
            </Svg>
            <Text style={styles.offlineTitle}>{(ctx.lang === ctx.mainLang && str(ctx.offline.title)) || t('offline_title')}</Text>
            <Text style={styles.offlineText}>{(ctx.lang === ctx.mainLang && str(ctx.offline.text)) || t('offline_text')}</Text>
            <Pressable onPress={onRetry} style={[styles.retry, { backgroundColor: ctx.primary }]} accessibilityRole="button">
                <Text style={[styles.retryText, { color: ctx.onPrimary }]}>{(ctx.lang === ctx.mainLang && str(ctx.offline.button)) || t('try_again')}</Text>
            </Pressable>
        </View>
    );
}

/** A native screen: its blocks in a scrolling list; pull down to check for changes. */
function Screen({ tab, ctx, register, index }) {
    const ref = useRef(null);
    const [refreshing, setRefreshing] = useState(false);
    useEffect(() => {
        register(index, { back: () => false, home: () => ref.current?.scrollTo({ y: 0, animated: true }) });
    }, [register, index]);
    const onRefresh = useCallback(async () => {
        setRefreshing(true);
        await ctx.refresh();
        setRefreshing(false);
    }, [ctx]);
    const blocks = (Array.isArray(tab.blocks) ? tab.blocks : []).filter(b => b && typeof b === 'object');
    return (
        <ScrollView ref={ref} style={styles.screen} contentContainerStyle={[styles.screenInner, { paddingBottom: ctx.navSpace + 28 }]}
            refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[ctx.primary]} />}>
            {blocks.length ? blocks.map((b, i) => <FadeUp key={i} index={i}><Block b={b} ctx={ctx} /></FadeUp>) : <Empty />}
        </ScrollView>
    );
}

/** A page of the owner's website. */
function WebTab({ tab, ctx, register, index, onFirstLoad }) {
    const ref = useRef(null);
    const [failed, setFailed] = useState(false);
    const [progress, setProgress] = useState(0);
    const canGoBack = useRef(false);

    useEffect(() => {
        register(index, {
            back: () => (canGoBack.current ? (ref.current?.goBack(), true) : false),
            home: () => ref.current?.injectJavaScript(`location.href = ${JSON.stringify(tab.url)}; true;`),
        });
    }, [register, index, tab.url]);

    const onShouldStart = useCallback(req => {
        const url = req.url;
        if (req.isTopFrame === false) return true; // maps, videos and other frames inside a page
        if (/^(about:|data:|blob:|javascript:)/i.test(url)) return true;
        if (/^https?:/i.test(url)) {
            if (ctx.ownSite(url) || ctx.externalLinks === 'app') return true;
            openOutside(url);
            return false;
        }
        openOutside(url); // tel:, mailto:, sms:, whatsapp:, geo:, intent:…
        return false;
    }, [ctx]);

    return (
        <View style={StyleSheet.absoluteFill}>
            <WebView
                ref={ref}
                source={{ uri: tab.url }}
                style={styles.web}
                applicationNameForUserAgent="OwlvixApp/2.0"
                onShouldStartLoadWithRequest={onShouldStart}
                onNavigationStateChange={s => { canGoBack.current = s.canGoBack; }}
                onLoadProgress={e => setProgress(e.nativeEvent.progress)}
                onLoadEnd={() => { setProgress(1); onFirstLoad(); }}
                onLoadStart={() => setFailed(false)}
                onError={() => { setFailed(true); onFirstLoad(); }}
                setSupportMultipleWindows={false}
                allowsBackForwardNavigationGestures
                pullToRefreshEnabled
                sharedCookiesEnabled
                thirdPartyCookiesEnabled
                textZoom={100}
                allowsInlineMediaPlayback
                allowFileAccess={false}
                mixedContentMode="never"
            />
            {progress < 1 && progress > 0 && !failed && (
                <View style={[styles.progress, { width: `${Math.round(progress * 100)}%`, backgroundColor: ctx.primary }]} />
            )}
            {failed && <Offline ctx={ctx} onRetry={() => { setFailed(false); ref.current?.reload(); }} />}
        </View>
    );
}

function Shell() {
    const insets = useSafeAreaInsets();
    const [state, setState] = useState(() => startContent(shell.content));
    const stateRef = useRef(state);
    stateRef.current = state;
    const doc = state.doc;
    const [active, setActive] = useState(0);
    const [menu, setMenu] = useState(false);
    const [intro, setIntro] = useState(!!INTRO?.source);
    const [banner, setBanner] = useState(null); // a notification that arrived while the app was open
    const [cart, setCart] = useState([]);       // the order basket: [{ name, price, qty }]
    const [product, setProduct] = useState(null);
    const [orderOpen, setOrderOpen] = useState(false);
    const [booking, setBooking] = useState(null); // the booking block being filled in
    const [sheetKey, setSheetKey] = useState(0);  // a new key each time a sheet opens, so it starts fresh
    const [opened, setOpened] = useState([0]); // tabs shown so far (kept, so each tab remembers its place)
    const pages = useRef({});
    const lastCheck = useRef(0);
    const register = useCallback((i, api) => { pages.current[i] = api; }, []);
    const hideSplash = useCallback(() => { SplashScreen.hideAsync().catch(() => {}); }, []);

    const refresh = useCallback(async () => {
        lastCheck.current = Date.now();
        const next = await fetchContent(shell.contentUrl, stateRef.current);
        if (next) setState(next);
    }, []);

    const [chosenLang, setChosenLang] = useState(savedLanguage);
    const mainLang = typeof doc?.lang === 'string' ? doc.lang : 'en';
    const translations = doc?.translations && typeof doc.translations === 'object' ? doc.translations : {};
    const languages = [mainLang, ...Object.keys(translations).filter(l => l !== mainLang && usableTabs(translations[l]?.tabs))];
    const lang = languages.includes(chosenLang) ? chosenLang : languages.includes(phoneLanguage()) ? phoneLanguage() : mainLang;
    setLanguage(lang); // the app's own words follow
    const pickLanguage = l => { setChosenLang(l); try { langFile().writeSync(l); } catch {} };
    const tabs = (lang === mainLang ? doc?.tabs : translations[lang]?.tabs) ?? [];
    // The owner's look (light/dark, corners, font): every file's styles are rebuilt before the screens draw
    const scheme = useColorScheme();
    const lk = look(doc?.style, scheme);
    applyLook(lk);
    setMotion(lk.motion);
    const C = {
        primary: color(doc?.colors?.primary, color(shell.colors?.primary, '#16a34a')),
        bar: color(doc?.colors?.bar, color(shell.colors?.bar, '#ffffff')),
    };
    if (lk.t.dark && !isDark(C.bar)) C.bar = '#111827'; // light bars would glare in dark mode
    // The floating bar (Aiiplan): the owner's bar colour when it's dark, else a deep navy shade of the main colour
    const floating = lk.nav === 'floating';
    const navBg = isDark(C.bar) ? C.bar : mix(C.primary, '#0b0a1f', 0.74);
    const navOn = (h => { const [r, g, b] = [1, 3, 5].map(i => parseInt(h.slice(i, i + 2), 16)); return r * 0.299 + g * 0.587 + b * 0.114; })(C.primary) < 60 ? '#ffffff' : C.primary; // a very dark main colour would vanish on the bar
    const ctx = useMemo(() => {
        const hosts = Array.isArray(doc?.hosts) ? doc.hosts.filter(h => typeof h === 'string') : [];
        return {
            ...C,
            onPrimary: isDark(C.primary) ? '#ffffff' : '#0f172a',
            base: doc?.base ?? '',
            offline: doc?.offline ?? {},
            externalLinks: doc?.externalLinks === 'app' ? 'app' : 'browser',
            ownSite: url => {
                try {
                    const host = new URL(url).hostname.toLowerCase();
                    return hosts.some(h => host === h || host.endsWith('.' + h));
                } catch {
                    return false;
                }
            },
            openUrl: openOutside,
            shop: doc?.shop && typeof doc.shop === 'object' ? doc.shop : null,
            openProduct: p => { setSheetKey(k => k + 1); setProduct(p); },
            openBooking: b => { setSheetKey(k => k + 1); setBooking(b); },
            navSpace: floating ? 68 + 14 + Math.max(insets.bottom, 8) : 0, // the floating bar covers the bottom of each screen
            lang,
            mainLang,
            insets,
            openTab: i => { if (i >= 0 && i < tabs.length) { setOpened(o => (o.includes(i) ? o : [...o, i])); setActive(i); } },
            refresh,
        };
    }, [doc, C.primary, C.bar, refresh, insets, lang, floating]); // eslint-disable-line react-hooks/exhaustive-deps

    // A native first screen is ready at once; a website tab hides the splash when its page loads (or after 5 s)
    useEffect(() => {
        if (tabs[0]?.type !== 'web' || INTRO?.source) hideSplash();
        const t = setTimeout(hideSplash, 5000);
        return () => clearTimeout(t);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // Push notifications: tapping one opens the page the owner chose; one arriving while the app is open shows a banner
    const openTabRef = useRef(null);
    openTabRef.current = ctx.openTab;
    useEffect(() => {
        if (!shell.push?.topic) return undefined;
        let stop = null;
        const t = setTimeout(() => {
            try {
                stop = require('./push').startPush(shell.push.topic,
                    data => { if (/^\d+$/.test(data.tab || '')) openTabRef.current(+data.tab); refresh(); },
                    msg => { setBanner({ title: str(msg.notification?.title), body: str(msg.notification?.body), tab: msg.data?.tab }); refresh(); });
            } catch {}
        }, INTRO?.source ? 3500 : 1500); // ask for permission once the app is showing, not over the splash
        return () => { clearTimeout(t); stop?.(); };
    }, [refresh]);
    useEffect(() => {
        if (!banner) return undefined;
        const t = setTimeout(() => setBanner(null), 7000);
        return () => clearTimeout(t);
    }, [banner]);

    // Check for changes on start, and when the app comes back to the front; count the open for the owner's statistics
    useEffect(() => {
        refresh();
        countOpen();
        sendStats(shell.statsUrl);
        const sub = AppState.addEventListener('change', s => {
            if (s !== 'active') return;
            countOpen();
            sendStats(shell.statsUrl);
            if (Date.now() - lastCheck.current > CHECK_EVERY) refresh();
        });
        return () => sub.remove();
    }, [refresh]);
    useEffect(() => { countPage(active); }, [active]);

    // Pages in the bottom bar (up to 5) and pages in the ☰ menu (a page can be in both); the ☰ shows when the
    // owner hasn't turned it off and at least one page is in it
    const bar = tabs.map((t, i) => i).filter(i => (tabs[i].bar === undefined ? i < 5 : !!tabs[i].bar)).slice(0, 5);
    const inMenu = tabs.map((t, i) => i).filter(i => (tabs[i].menu === undefined ? !bar.includes(i) : !!tabs[i].menu));
    const hasMenu = doc?.menu !== 'hide' && (inMenu.length > 0 || languages.length > 1); // (the language picker is in the menu)

    // New content may have fewer tabs
    useEffect(() => {
        if (active >= tabs.length) setActive(0);
    }, [tabs.length, active]);

    useEffect(() => {
        const sub = BackHandler.addEventListener('hardwareBackPress', () => {
            if (pages.current[active]?.back()) return true;
            if (active !== 0) { setActive(0); return true; }
            return false; // leave the app
        });
        return () => sub.remove();
    }, [active]);

    const pick = i => {
        setMenu(false);
        if (i === active) { pages.current[i]?.home(); return; } // tap the open tab again: back to its top
        if (!opened.includes(i)) setOpened([...opened, i]);
        setActive(i);
    };
    const barInk = isDark(C.bar) ? '#ffffff' : '#0f172a';

    const muted = isDark(C.bar) ? 'rgba(255,255,255,0.6)' : 'rgba(15,23,42,0.5)';
    return (
        <View style={[styles.root, { backgroundColor: C.bar }]}>
            <StatusBar style={isDark(C.bar) ? 'light' : 'dark'} />
            <View style={{ height: insets.top, backgroundColor: C.bar }} />
            {hasMenu && (
                <View style={[styles.header, { backgroundColor: C.bar }]}>
                    <Pressable onPress={() => setMenu(true)} style={styles.burger} accessibilityRole="button" accessibilityLabel={t('open_menu')} hitSlop={8}>
                        <Svg width={24} height={24} viewBox="0 0 24 24" fill="none" stroke={barInk} strokeWidth={2} strokeLinecap="round"><Path d="M4 7h16M4 12h16M4 17h16" /></Svg>
                    </Pressable>
                    <Text style={[styles.headerTitle, { color: barInk }]} numberOfLines={1} accessibilityRole="header">{str(tabs[active]?.label)}</Text>
                </View>
            )}
            <View style={styles.pages}>
                {!tabs.length && <Offline ctx={ctx} onRetry={refresh} />}
                {tabs.map((tab, i) => opened.includes(i) && (
                    <View key={`${i}-${tab.type}-${tab.url ?? ''}`} style={[StyleSheet.absoluteFill, { opacity: i === active ? 1 : 0 }]} pointerEvents={i === active ? 'auto' : 'none'}>
                        <Enter trigger={i === active ? i + 1 : 0} style={{ flex: 1 }}>
                            {tab.type === 'web'
                                ? <WebTab tab={tab} ctx={ctx} register={register} index={i} onFirstLoad={hideSplash} />
                                : <Screen tab={tab} ctx={ctx} register={register} index={i} />}
                        </Enter>
                    </View>
                ))}
                <OrderButton cart={cart} ctx={ctx} bottom={ctx.navSpace + 14} onPress={() => setOrderOpen(true)} />
            </View>
            <Modal visible={menu} transparent animationType="fade" onRequestClose={() => setMenu(false)} statusBarTranslucent>
                <View style={styles.drawerWrap}>
                    <View style={[styles.drawer, { paddingTop: insets.top + 16, paddingBottom: insets.bottom + 16 }]}>
                        <Text style={styles.drawerName} numberOfLines={2}>{str(shell.name)}</Text>
                        <ScrollView>
                            {inMenu.map(i => {
                                const tab = tabs[i], on = i === active;
                                return (
                                    <Pressable key={i} onPress={() => pick(i)} accessibilityRole="menuitem" accessibilityState={{ selected: on }}
                                        style={({ pressed }) => [styles.drawerItem, on && { backgroundColor: C.primary + '1a' }, pressed && { opacity: 0.7 }]}>
                                        <Icon name={tab.icon} color={on ? C.primary : T.text} />
                                        <Text style={[styles.drawerText, on && { color: C.primary }]} numberOfLines={1}>{str(tab.label)}</Text>
                                    </Pressable>
                                );
                            })}
                        </ScrollView>
                        {languages.length > 1 && (
                            <View style={styles.langs} accessibilityRole="radiogroup" accessibilityLabel={t('language')}>
                                <Text style={styles.langTitle}>🌐 {t('language')}</Text>
                                <View style={styles.langRow}>
                                    {languages.map(l => (
                                        <Pressable key={l} onPress={() => { pickLanguage(l); setMenu(false); }} accessibilityRole="radio" accessibilityState={{ selected: l === lang }}
                                            style={[styles.langChip, l === lang && { backgroundColor: C.primary, borderColor: C.primary }]}>
                                            <Text style={[styles.langText, l === lang && { color: ctx.onPrimary }]}>{LANGUAGES[l] || l}</Text>
                                        </Pressable>
                                    ))}
                                </View>
                            </View>
                        )}
                        {/^https:\/\//.test(doc?.report || '') && (
                            <Pressable onPress={() => { setMenu(false); openOutside(doc.report); }} accessibilityRole="link" style={styles.drawerFoot}>
                                <Text style={styles.drawerFootText}>{t('made_with')} · {t('report')}</Text>
                            </Pressable>
                        )}
                    </View>
                    <Pressable style={styles.drawerShade} onPress={() => setMenu(false)} accessibilityRole="button" accessibilityLabel={t('close')} />
                </View>
            </Modal>
            {bar.length > 1 && floating ? (
                <View style={[styles.float, { bottom: Math.max(insets.bottom, 8) + 6, backgroundColor: navBg }]} accessibilityRole="tablist">
                    {bar.map(i => {
                        const tab = tabs[i], on = i === active;
                        return (
                            <Press key={i} onPress={() => pick(i)} accessibilityRole="tab" accessibilityState={{ selected: on }} accessibilityLabel={str(tab.label)}
                                style={[styles.floatBtn, { backgroundColor: on ? navOn : 'rgba(255,255,255,0.07)' }]}>
                                <Icon name={tab.icon} color={on ? (navOn === '#ffffff' ? C.primary : ctx.onPrimary) : 'rgba(255,255,255,0.78)'} size={22} />
                            </Press>
                        );
                    })}
                </View>
            ) : bar.length > 1 ? (
                <View style={[styles.bar, { backgroundColor: C.bar, paddingBottom: Math.max(insets.bottom, 6) }]} accessibilityRole="tablist">
                    {bar.map(i => {
                        const tab = tabs[i], col = i === active ? C.primary : muted;
                        return (
                            <Pressable key={i} onPress={() => pick(i)} style={styles.tab} accessibilityRole="tab" accessibilityState={{ selected: i === active }} accessibilityLabel={str(tab.label)}>
                                <Icon name={tab.icon} color={col} />
                                <Text style={[styles.tabText, { color: col }]} numberOfLines={1}>{str(tab.label)}</Text>
                            </Pressable>
                        );
                    })}
                </View>
            ) : (
                <View style={{ height: floating ? 0 : insets.bottom, backgroundColor: C.bar }} />
            )}
            {floating && <View style={{ height: insets.bottom, backgroundColor: lk.t.bg }} />}
            <ProductSheet key={`p${sheetKey}`} product={product} ctx={ctx} insets={insets} onClose={() => setProduct(null)}
                onAdd={(p, qty) => {
                    setCart(c => { const i = c.findIndex(l => l.name === p.name && l.price === p.price); return i < 0 ? [...c, { name: p.name, price: p.price, qty }] : c.map((l, j) => (j === i ? { ...l, qty: Math.min(99, l.qty + qty) } : l)); });
                    setProduct(null);
                }} />
            <BookingSheet key={`b${sheetKey}`} block={booking} ctx={ctx} insets={insets} appName={str(shell.name)} onClose={() => setBooking(null)} />
            <OrderSheet visible={orderOpen} cart={cart} setCart={setCart} ctx={ctx} insets={insets} appName={str(shell.name)} onClose={() => setOrderOpen(false)} />
            {banner && (
                <Pressable onPress={() => { if (/^\d+$/.test(banner.tab || '')) ctx.openTab(+banner.tab); setBanner(null); }} accessibilityRole="button"
                    style={[styles.banner, { top: insets.top + 8 }]}>
                    <Icon name="bell" color={C.primary} size={22} />
                    <View style={{ flex: 1 }}>
                        {!!banner.title && <Text style={styles.bannerTitle} numberOfLines={1}>{banner.title}</Text>}
                        {!!banner.body && <Text style={styles.bannerBody} numberOfLines={3}>{banner.body}</Text>}
                    </View>
                </Pressable>
            )}
            {intro && <Intro onDone={() => setIntro(false)} />}
            <Loader icon={LOADER_ICON} color={C.primary} track={mix(C.primary, lk.t.dark ? '#0b1120' : '#ffffff', 0.85)} ready={!!doc} dark={lk.t.dark} />
        </View>
    );
}

export default function App() {
    return (
        <SafeAreaProvider>
            <Shell />
        </SafeAreaProvider>
    );
}

let styles;
let T;
const makeStyles = (t, r, f, h) => StyleSheet.create({
    root: { flex: 1 },
    pages: { flex: 1, backgroundColor: t.bg },
    screen: { flex: 1, backgroundColor: t.bg },
    screenInner: { paddingBottom: 28 },
    web: { flex: 1, backgroundColor: t.card },
    progress: { position: 'absolute', top: 0, left: 0, height: 3 },
    intro: { backgroundColor: '#000', zIndex: 10, elevation: 10 },
    banner: { position: 'absolute', left: 12, right: 12, flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderRadius: r(16), backgroundColor: t.card, elevation: 8, zIndex: 9,
        shadowColor: '#000', shadowOpacity: 0.18, shadowRadius: 12, shadowOffset: { width: 0, height: 4 } },
    bannerTitle: { fontFamily: f, fontSize: 15, fontWeight: '700', color: t.ink },
    bannerBody: { fontFamily: f, fontSize: 14, color: t.text, marginTop: 2 },
    skip: { position: 'absolute', right: 16, paddingHorizontal: 16, paddingVertical: 8, borderRadius: r(999), backgroundColor: 'rgba(0,0,0,0.45)' },
    skipText: { fontFamily: f, color: '#fff', fontSize: 14, fontWeight: '700' },
    float: { position: 'absolute', left: 14, right: 14, height: 68, borderRadius: r(34), flexDirection: 'row', alignItems: 'center', justifyContent: 'space-around', paddingHorizontal: 10, zIndex: 7,
        shadowColor: '#1E1B49', shadowOpacity: 0.28, shadowRadius: 22, shadowOffset: { width: 0, height: 10 }, elevation: 12 },
    floatBtn: { width: 48, height: 48, borderRadius: r(999), alignItems: 'center', justifyContent: 'center' },
    header: { flexDirection: 'row', alignItems: 'center', height: 52, paddingHorizontal: 8, gap: 6, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: t.border },
    burger: { width: 44, height: 44, alignItems: 'center', justifyContent: 'center', borderRadius: r(22) },
    headerTitle: { fontFamily: h, flex: 1, fontSize: 20, fontWeight: '600' },
    drawerWrap: { flex: 1, flexDirection: 'row' },
    drawer: { width: '80%', maxWidth: 320, backgroundColor: t.card, paddingHorizontal: 12, elevation: 12 },
    drawerShade: { flex: 1, backgroundColor: t.shade },
    drawerName: { fontFamily: h, fontSize: 22, fontWeight: '700', color: t.ink, marginHorizontal: 12, marginBottom: 14 },
    drawerItem: { flexDirection: 'row', alignItems: 'center', gap: 14, paddingHorizontal: 12, paddingVertical: 13, borderRadius: r(12) },
    drawerText: { fontFamily: f, flex: 1, fontSize: 16, fontWeight: '600', color: t.ink },
    langs: { paddingHorizontal: 12, paddingTop: 12, gap: 8, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: t.border },
    langTitle: { fontFamily: f, fontSize: 13, fontWeight: '700', color: t.muted },
    langRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
    langChip: { paddingHorizontal: 12, paddingVertical: 7, borderRadius: r(999), borderWidth: 1, borderColor: t.border },
    langText: { fontFamily: f, fontSize: 14, fontWeight: '600', color: t.ink },
    drawerFoot: { paddingHorizontal: 12, paddingTop: 12 },
    drawerFootText: { fontFamily: f, fontSize: 12, color: '#94a3b8' },
    bar: { flexDirection: 'row', borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: t.border, paddingTop: 6 },
    tab: { flex: 1, alignItems: 'center', gap: 2, paddingVertical: 2 },
    tabText: { fontFamily: f, fontSize: 11, fontWeight: '600' },
    offline: { ...StyleSheet.absoluteFillObject, backgroundColor: t.card, alignItems: 'center', justifyContent: 'center', padding: 32, gap: 10 },
    offlineTitle: { fontFamily: h, fontSize: 22, fontWeight: '700', color: t.ink, textAlign: 'center', marginTop: 8 },
    offlineText: { fontFamily: f, fontSize: 15, color: t.muted, textAlign: 'center', maxWidth: 320 },
    retry: { marginTop: 14, paddingHorizontal: 26, paddingVertical: 12, borderRadius: r(999) },
    retryText: { fontFamily: f, fontSize: 15, fontWeight: '700' },
});
onLook(({ t, r, f, h }) => { T = t; styles = makeStyles(t, r, f, h); });
