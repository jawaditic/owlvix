// Motion, after the Aiiplan app: screens rise in, cards fade up one after another, things "pop", buttons squash a
// little when pressed, pictures drift slowly (Ken Burns), the main button breathes a ring, badges wiggle, and a
// bouncing-logo loader covers the first moment. All on the same ease, and all off when the phone asks for less
// motion (Android "Remove animations") or the owner turns motion off in the builder.
import { useEffect, useRef, useState } from 'react';
import { AccessibilityInfo, Animated, Easing, Image, Pressable, StyleSheet, View } from 'react-native';

const EASE = Easing.bezier(0.2, 0.8, 0.2, 1);
let reduced = false;
AccessibilityInfo.isReduceMotionEnabled?.().then(v => { reduced = !!v; }).catch(() => {});
AccessibilityInfo.addEventListener?.('reduceMotionChanged', v => { reduced = !!v; });
let ownerOn = true;
/** The owner's choice (content style.motion). */
export const setMotion = on => { ownerOn = on !== false; };
export const still = () => reduced || !ownerOn;

/** Fades up into place (18 px, 480 ms), after `index` × 60 ms so a list of cards arrives one after another. */
export function FadeUp({ index = 0, distance = 18, duration = 480, style, children }) {
    const v = useRef(new Animated.Value(still() ? 1 : 0)).current;
    useEffect(() => {
        if (still()) { v.setValue(1); return; }
        Animated.timing(v, { toValue: 1, duration, delay: Math.min(index, 8) * 60, easing: EASE, useNativeDriver: true }).start();
    }, []); // eslint-disable-line react-hooks/exhaustive-deps
    return (
        <Animated.View style={[style, { opacity: v, transform: [{ translateY: v.interpolate({ inputRange: [0, 1], outputRange: [distance, 0] }) }] }]}>
            {children}
        </Animated.View>
    );
}

/** Rises in whenever `trigger` changes (a tab becoming the one on screen): 12 px, 320 ms. */
export function Enter({ trigger, style, children }) {
    const v = useRef(new Animated.Value(1)).current;
    const first = useRef(true);
    useEffect(() => {
        if (first.current) { first.current = false; return; }
        if (still() || !trigger) return;
        v.setValue(0);
        Animated.timing(v, { toValue: 1, duration: 320, easing: EASE, useNativeDriver: true }).start();
    }, [trigger]); // eslint-disable-line react-hooks/exhaustive-deps
    return (
        <Animated.View style={[style, { opacity: v.interpolate({ inputRange: [0, 1], outputRange: [0.4, 1] }), transform: [{ translateY: v.interpolate({ inputRange: [0, 1], outputRange: [12, 0] }) }] }]}>
            {children}
        </Animated.View>
    );
}

/** A "pop" when `trigger` changes: 0.6 → 1.12 → 1 (a stamp added, a basket count going up). */
export function Pop({ trigger, style, children }) {
    const v = useRef(new Animated.Value(1)).current;
    const first = useRef(true);
    useEffect(() => {
        if (first.current) { first.current = false; if (still()) return; }
        if (still()) return;
        v.setValue(0.6);
        Animated.sequence([
            Animated.timing(v, { toValue: 1.12, duration: 250, easing: EASE, useNativeDriver: true }),
            Animated.timing(v, { toValue: 1, duration: 170, easing: EASE, useNativeDriver: true }),
        ]).start();
    }, [trigger]); // eslint-disable-line react-hooks/exhaustive-deps
    return <Animated.View style={[style, { transform: [{ scale: v }] }]}>{children}</Animated.View>;
}

/** A Pressable that squashes a little while pressed (0.96) and springs back. */
export function Press({ style, children, onPressIn, onPressOut, ...rest }) {
    const v = useRef(new Animated.Value(1)).current;
    const to = n => { if (!still()) Animated.spring(v, { toValue: n, useNativeDriver: true, speed: 40, bounciness: n === 1 ? 8 : 0 }).start(); };
    const flat = StyleSheet.flatten(style) || {};
    // the outer view keeps the layout (width, margins); the inner one scales
    const { margin, marginTop, marginBottom, marginLeft, marginRight, marginHorizontal, marginVertical, width, flex, alignSelf, ...inner } = flat;
    return (
        <Animated.View style={{ margin, marginTop, marginBottom, marginLeft, marginRight, marginHorizontal, marginVertical, width, flex, alignSelf, transform: [{ scale: v }] }}>
            <Pressable {...rest} onPressIn={e => { to(0.96); onPressIn?.(e); }} onPressOut={e => { to(1); onPressOut?.(e); }} style={inner}>
                {children}
            </Pressable>
        </Animated.View>
    );
}

/** A picture that drifts slowly (1 → 1.08, 7 s each way), like a Ken Burns pan; fills its parent. */
export function KenBurns({ uri }) {
    const v = useRef(new Animated.Value(0)).current;
    useEffect(() => {
        if (still()) return undefined;
        const loop = Animated.loop(Animated.sequence([
            Animated.timing(v, { toValue: 1, duration: 7000, easing: Easing.inOut(Easing.ease), useNativeDriver: true }),
            Animated.timing(v, { toValue: 0, duration: 7000, easing: Easing.inOut(Easing.ease), useNativeDriver: true }),
        ]));
        loop.start();
        return () => loop.stop();
    }, [v]);
    return (
        <Animated.Image source={{ uri }} resizeMode="cover" accessibilityIgnoresInvertColors
            style={[StyleSheet.absoluteFill, { transform: [{ scale: v.interpolate({ inputRange: [0, 1], outputRange: [1, 1.08] }) }, { translateX: v.interpolate({ inputRange: [0, 1], outputRange: [0, -4] }) }] }]} />
    );
}

/** A ring that breathes out from behind the main button (2 s, forever). Put it inside a relatively positioned box. */
export function Ring({ color, radius = 999 }) {
    const v = useRef(new Animated.Value(0)).current;
    useEffect(() => {
        if (still()) return undefined;
        const loop = Animated.loop(Animated.timing(v, { toValue: 1, duration: 2000, easing: Easing.out(Easing.ease), useNativeDriver: true }));
        loop.start();
        return () => loop.stop();
    }, [v]);
    if (still()) return null;
    return (
        <Animated.View pointerEvents="none" style={[StyleSheet.absoluteFill, { borderRadius: radius, borderWidth: 2, borderColor: color,
            opacity: v.interpolate({ inputRange: [0, 1], outputRange: [0.55, 0] }),
            transform: [{ scaleX: v.interpolate({ inputRange: [0, 1], outputRange: [1, 1.12] }) }, { scaleY: v.interpolate({ inputRange: [0, 1], outputRange: [1, 1.5] }) }] }]} />
    );
}

/** A little wiggle every few seconds (a badge asking to be noticed). */
export function Wiggle({ children, style }) {
    const v = useRef(new Animated.Value(0)).current;
    useEffect(() => {
        if (still()) return undefined;
        const step = (to, ms) => Animated.timing(v, { toValue: to, duration: ms, useNativeDriver: true });
        const loop = Animated.loop(Animated.sequence([step(-14, 190), step(12, 190), step(-8, 190), step(4, 190), step(0, 190), Animated.delay(2250)]));
        loop.start();
        return () => loop.stop();
    }, [v]);
    return <Animated.View style={[style, { transform: [{ rotate: v.interpolate({ inputRange: [-14, 14], outputRange: ['-14deg', '14deg'] }) }] }]}>{children}</Animated.View>;
}

/**
 * The opening loader: the app's icon bounces (with a squash) over a shadow that shrinks as it rises, above a
 * sliding bar; it fades out once the app is ready (at least 0.8 s, like Aiiplan).
 */
export function Loader({ icon, color, track, ready, dark }) {
    const [gone, setGone] = useState(false);
    const bounce = useRef(new Animated.Value(0)).current;
    const bar = useRef(new Animated.Value(0)).current;
    const fade = useRef(new Animated.Value(1)).current;
    const shown = useRef(Date.now());
    useEffect(() => {
        if (still()) return undefined;
        const b = Animated.loop(Animated.sequence([
            Animated.timing(bounce, { toValue: 1, duration: 500, easing: Easing.bezier(0.3, 0, 0.4, 1), useNativeDriver: true }),
            Animated.timing(bounce, { toValue: 0, duration: 500, easing: Easing.bezier(0.3, 0, 0.4, 1), useNativeDriver: true }),
        ]));
        const s = Animated.loop(Animated.timing(bar, { toValue: 1, duration: 900, easing: Easing.inOut(Easing.ease), useNativeDriver: true }));
        b.start(); s.start();
        return () => { b.stop(); s.stop(); };
    }, [bounce, bar]);
    useEffect(() => {
        if (!ready) return undefined;
        const t = setTimeout(() => {
            Animated.timing(fade, { toValue: 0, duration: 300, useNativeDriver: true }).start(() => setGone(true));
        }, still() ? 0 : Math.max(0, 800 - (Date.now() - shown.current)));
        return () => clearTimeout(t);
    }, [ready, fade]);
    if (gone) return null;
    return (
        <Animated.View pointerEvents="none" accessibilityElementsHidden importantForAccessibility="no-hide-descendants"
            style={[StyleSheet.absoluteFill, styles.loader, { opacity: fade, backgroundColor: dark ? 'rgba(11,17,32,0.96)' : 'rgba(255,255,255,0.94)' }]}>
            <Animated.View style={{ transform: [
                { translateY: bounce.interpolate({ inputRange: [0, 1], outputRange: [0, -22] }) },
                { scaleX: bounce.interpolate({ inputRange: [0, 0.15, 1], outputRange: [1.05, 1, 1] }) },
                { scaleY: bounce.interpolate({ inputRange: [0, 0.15, 1], outputRange: [0.95, 1, 1] }) }] }}>
                <View style={[styles.logoWrap, { shadowColor: color }]}>
                    {icon ? <Image source={icon} style={styles.logo} /> : <View style={[styles.logo, { backgroundColor: color }]} />}
                </View>
            </Animated.View>
            <Animated.View style={[styles.shadow, { backgroundColor: dark ? 'rgba(255,255,255,0.14)' : 'rgba(30,27,73,0.16)',
                opacity: bounce.interpolate({ inputRange: [0, 1], outputRange: [1, 0.45] }),
                transform: [{ scale: bounce.interpolate({ inputRange: [0, 1], outputRange: [1, 0.55] }) }] }]} />
            <View style={[styles.track, { backgroundColor: track }]}>
                <Animated.View style={[styles.bar, { backgroundColor: color, transform: [{ translateX: bar.interpolate({ inputRange: [0, 1], outputRange: [-37, 92] }) }] }]} />
            </View>
        </Animated.View>
    );
}

const styles = StyleSheet.create({
    loader: { zIndex: 50, elevation: 50, alignItems: 'center', justifyContent: 'center', gap: 12 },
    logoWrap: { borderRadius: 17, shadowOpacity: 0.28, shadowRadius: 22, shadowOffset: { width: 0, height: 10 }, elevation: 10, backgroundColor: 'transparent' },
    logo: { width: 62, height: 62, borderRadius: 17 },
    shadow: { width: 44, height: 8, borderRadius: 22 },
    track: { marginTop: 4, width: 92, height: 4, borderRadius: 999, overflow: 'hidden' },
    bar: { width: 37, height: 4, borderRadius: 999 },
});
