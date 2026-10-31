// An app made with the Owlvix mobile app builder (/mobile-app-builder). Its tabs are native screens built from
// blocks (blocks.js), or pages of the owner's website. What the tabs show comes from content.js: the copy built
// into the app, kept up to date from the server, so the owner's changes show without a new build. The name, icon,
// splash screen and colours of the system bars come from shell.json at build time (app.config.js).
import Constants from 'expo-constants';
import { useEventListener } from 'expo';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Animated, AppState, BackHandler, Image, Linking, Modal, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaProvider, useSafeAreaInsets } from 'react-native-safe-area-context';
import Svg, { Path } from 'react-native-svg';
import { WebView } from 'react-native-webview';
import { useVideoPlayer, VideoView } from 'expo-video';
import INTRO from './intro';
import { Block, Empty, Icon, isDark } from './blocks';
import { color, fetchContent, startContent, str } from './content';

const shell = Constants.expoConfig?.extra?.shell ?? {};
const CHECK_EVERY = 60 * 1000; // ask the server for changes at most once a minute when the app comes back

SplashScreen.preventAutoHideAsync().catch(() => {});

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
    const finish = useCallback(() => {
        if (done.current) return;
        done.current = true;
        Animated.timing(fade, { toValue: 0, duration: 350, useNativeDriver: true }).start(onDone);
    }, [fade, onDone]);
    useEffect(() => {
        const t = setTimeout(finish, INTRO.type === 'image' ? INTRO_PICTURE_MS : INTRO_MAX_MS);
        return () => clearTimeout(t);
    }, [finish]);
    return (
        <Animated.View style={[StyleSheet.absoluteFill, styles.intro, { opacity: fade }]}>
            {INTRO.type === 'video'
                ? <IntroVideo source={INTRO.source} onEnd={finish} />
                : <Image source={INTRO.source} style={StyleSheet.absoluteFill} resizeMode="cover" />}
            <Pressable onPress={finish} style={[styles.skip, { top: insets.top + 12 }]} accessibilityRole="button" accessibilityLabel="Skip">
                <Text style={styles.skipText}>Skip</Text>
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
            <Text style={styles.offlineTitle}>{str(ctx.offline.title) || 'You’re offline'}</Text>
            <Text style={styles.offlineText}>{str(ctx.offline.text) || 'Check your internet connection and try again.'}</Text>
            <Pressable onPress={onRetry} style={[styles.retry, { backgroundColor: ctx.primary }]} accessibilityRole="button">
                <Text style={[styles.retryText, { color: ctx.onPrimary }]}>{str(ctx.offline.button) || 'Try again'}</Text>
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
    const blocks = tab.blocks.filter(b => b && typeof b === 'object');
    return (
        <ScrollView ref={ref} style={styles.screen} contentContainerStyle={styles.screenInner}
            refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[ctx.primary]} />}>
            {blocks.length ? blocks.map((b, i) => <Block key={i} b={b} ctx={ctx} />) : <Empty />}
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

    const tabs = doc?.tabs ?? [];
    const C = {
        primary: color(doc?.colors?.primary, color(shell.colors?.primary, '#16a34a')),
        bar: color(doc?.colors?.bar, color(shell.colors?.bar, '#ffffff')),
    };
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
            openTab: i => { if (i >= 0 && i < tabs.length) { setOpened(o => (o.includes(i) ? o : [...o, i])); setActive(i); } },
            refresh,
        };
    }, [doc, C.primary, C.bar, refresh]); // eslint-disable-line react-hooks/exhaustive-deps

    // A native first screen is ready at once; a website tab hides the splash when its page loads (or after 5 s)
    useEffect(() => {
        if (tabs[0]?.type !== 'web' || INTRO?.source) hideSplash();
        const t = setTimeout(hideSplash, 5000);
        return () => clearTimeout(t);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // Check for changes on start, and when the app comes back to the front
    useEffect(() => {
        refresh();
        const sub = AppState.addEventListener('change', s => {
            if (s === 'active' && Date.now() - lastCheck.current > CHECK_EVERY) refresh();
        });
        return () => sub.remove();
    }, [refresh]);

    // Pages in the bottom bar (up to 5) and pages in the ☰ menu (a page can be in both); the ☰ shows when the
    // owner hasn't turned it off and at least one page is in it
    const bar = tabs.map((t, i) => i).filter(i => (tabs[i].bar === undefined ? i < 5 : !!tabs[i].bar)).slice(0, 5);
    const inMenu = tabs.map((t, i) => i).filter(i => (tabs[i].menu === undefined ? !bar.includes(i) : !!tabs[i].menu));
    const hasMenu = doc?.menu !== 'hide' && inMenu.length > 0;
    const [menu, setMenu] = useState(false);
    const [intro, setIntro] = useState(!!INTRO?.source);

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
                    <Pressable onPress={() => setMenu(true)} style={styles.burger} accessibilityRole="button" accessibilityLabel="Open menu" hitSlop={8}>
                        <Svg width={24} height={24} viewBox="0 0 24 24" fill="none" stroke={barInk} strokeWidth={2} strokeLinecap="round"><Path d="M4 7h16M4 12h16M4 17h16" /></Svg>
                    </Pressable>
                    <Text style={[styles.headerTitle, { color: barInk }]} numberOfLines={1} accessibilityRole="header">{str(tabs[active]?.label)}</Text>
                </View>
            )}
            <View style={styles.pages}>
                {!tabs.length && <Offline ctx={ctx} onRetry={refresh} />}
                {tabs.map((tab, i) => opened.includes(i) && (
                    <View key={`${i}-${tab.type}-${tab.url ?? ''}`} style={[StyleSheet.absoluteFill, { opacity: i === active ? 1 : 0 }]} pointerEvents={i === active ? 'auto' : 'none'}>
                        {tab.type === 'web'
                            ? <WebTab tab={tab} ctx={ctx} register={register} index={i} onFirstLoad={hideSplash} />
                            : <Screen tab={tab} ctx={ctx} register={register} index={i} />}
                    </View>
                ))}
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
                                        <Icon name={tab.icon} color={on ? C.primary : '#475569'} />
                                        <Text style={[styles.drawerText, on && { color: C.primary }]} numberOfLines={1}>{str(tab.label)}</Text>
                                    </Pressable>
                                );
                            })}
                        </ScrollView>
                        {/^https:\/\//.test(doc?.report || '') && (
                            <Pressable onPress={() => { setMenu(false); openOutside(doc.report); }} accessibilityRole="link" style={styles.drawerFoot}>
                                <Text style={styles.drawerFootText}>Made with Owlvix · Report this app</Text>
                            </Pressable>
                        )}
                    </View>
                    <Pressable style={styles.drawerShade} onPress={() => setMenu(false)} accessibilityRole="button" accessibilityLabel="Close menu" />
                </View>
            </Modal>
            {bar.length > 1 ? (
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
                <View style={{ height: insets.bottom, backgroundColor: C.bar }} />
            )}
            {intro && <Intro onDone={() => setIntro(false)} />}
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

const styles = StyleSheet.create({
    root: { flex: 1 },
    pages: { flex: 1, backgroundColor: '#f6f7f9' },
    screen: { flex: 1, backgroundColor: '#f6f7f9' },
    screenInner: { paddingBottom: 28 },
    web: { flex: 1, backgroundColor: '#ffffff' },
    progress: { position: 'absolute', top: 0, left: 0, height: 3 },
    intro: { backgroundColor: '#000', zIndex: 10, elevation: 10 },
    skip: { position: 'absolute', right: 16, paddingHorizontal: 16, paddingVertical: 8, borderRadius: 999, backgroundColor: 'rgba(0,0,0,0.45)' },
    skipText: { color: '#fff', fontSize: 14, fontWeight: '700' },
    header: { flexDirection: 'row', alignItems: 'center', height: 52, paddingHorizontal: 8, gap: 6, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: 'rgba(15,23,42,0.12)' },
    burger: { width: 44, height: 44, alignItems: 'center', justifyContent: 'center', borderRadius: 22 },
    headerTitle: { flex: 1, fontSize: 18, fontWeight: '700' },
    drawerWrap: { flex: 1, flexDirection: 'row' },
    drawer: { width: '80%', maxWidth: 320, backgroundColor: '#ffffff', paddingHorizontal: 12, elevation: 12 },
    drawerShade: { flex: 1, backgroundColor: 'rgba(15,23,42,0.45)' },
    drawerName: { fontSize: 20, fontWeight: '800', color: '#0f172a', marginHorizontal: 12, marginBottom: 14 },
    drawerItem: { flexDirection: 'row', alignItems: 'center', gap: 14, paddingHorizontal: 12, paddingVertical: 13, borderRadius: 12 },
    drawerText: { flex: 1, fontSize: 16, fontWeight: '600', color: '#0f172a' },
    drawerFoot: { paddingHorizontal: 12, paddingTop: 12 },
    drawerFootText: { fontSize: 12, color: '#94a3b8' },
    bar: { flexDirection: 'row', borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: 'rgba(15,23,42,0.12)', paddingTop: 6 },
    tab: { flex: 1, alignItems: 'center', gap: 2, paddingVertical: 2 },
    tabText: { fontSize: 11, fontWeight: '600' },
    offline: { ...StyleSheet.absoluteFillObject, backgroundColor: '#ffffff', alignItems: 'center', justifyContent: 'center', padding: 32, gap: 10 },
    offlineTitle: { fontSize: 20, fontWeight: '700', color: '#0f172a', textAlign: 'center', marginTop: 8 },
    offlineText: { fontSize: 15, color: '#64748b', textAlign: 'center', maxWidth: 320 },
    retry: { marginTop: 14, paddingHorizontal: 26, paddingVertical: 12, borderRadius: 999 },
    retryText: { fontSize: 15, fontWeight: '700' },
});
