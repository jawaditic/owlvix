/** Small building blocks shared by every screen, styled like owlvix.com. */

import { forwardRef, type ReactNode } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
  type StyleProp,
  type TextInputInstance,
  type ViewProps,
  type TextInputProps,
  type TextProps,
  type TextStyle,
  type ViewStyle,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { MaxWidth, Radius, Space, useColors } from '@/constants/theme';

/* ---------- Text ---------- */

type Variant = 'h1' | 'h2' | 'h3' | 'body' | 'small' | 'label' | 'eyebrow';

export function T({ v = 'body', color, style, ...rest }: TextProps & { v?: Variant; color?: string }) {
  const c = useColors();
  const variants: Record<Variant, TextStyle> = {
    h1: { fontSize: 34, lineHeight: 40, fontWeight: '800', color: c.text, letterSpacing: -0.5 },
    h2: { fontSize: 24, lineHeight: 30, fontWeight: '800', color: c.text, letterSpacing: -0.3 },
    h3: { fontSize: 17, lineHeight: 23, fontWeight: '700', color: c.text },
    body: { fontSize: 16, lineHeight: 23, color: c.text2 },
    small: { fontSize: 13, lineHeight: 18, color: c.muted },
    label: { fontSize: 14, lineHeight: 19, fontWeight: '600', color: c.text },
    eyebrow: { fontSize: 12, lineHeight: 16, fontWeight: '700', color: c.green, letterSpacing: 1.4, textTransform: 'uppercase' },
  };
  return <Text style={[variants[v], color ? { color } : null, style]} {...rest} />;
}

/* ---------- Layout ---------- */

/** A scrolling page with safe-area padding, pull-to-refresh and a centred column on tablets. */
export function Screen({
  children,
  refreshing,
  onRefresh,
  topInset = true,
  contentStyle,
}: {
  children: ReactNode;
  refreshing?: boolean;
  onRefresh?: () => void;
  topInset?: boolean;
  contentStyle?: StyleProp<ViewStyle>;
}) {
  const c = useColors();
  const insets = useSafeAreaInsets();
  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: c.bg }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView
        style={{ flex: 1 }}
        keyboardShouldPersistTaps="handled"
        contentInsetAdjustmentBehavior="automatic"
        contentContainerStyle={[
          styles.screen,
          // On the web build the tab bar floats over the top of the page instead of sitting at the bottom
          { paddingTop: (topInset ? insets.top + (Platform.OS === 'web' ? 72 : 0) : 0) + Space.lg, paddingBottom: insets.bottom + 96 },
          contentStyle,
        ]}
        refreshControl={onRefresh ? <RefreshControl refreshing={!!refreshing} onRefresh={onRefresh} tintColor={c.green} /> : undefined}>
        {children}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

export function Card({ children, style, onPress }: { children: ReactNode; style?: StyleProp<ViewStyle>; onPress?: () => void }) {
  const c = useColors();
  const box = [styles.card, { backgroundColor: c.card, borderColor: c.border }, style];
  if (!onPress) return <View style={box}>{children}</View>;
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [box, pressed && { opacity: 0.75 }]} accessibilityRole="button">
      {children}
    </Pressable>
  );
}

export function Row({ children, gap = Space.sm, style, ...rest }: ViewProps & { gap?: number }) {
  return (
    <View style={[{ flexDirection: 'row', alignItems: 'center', gap }, style]} {...rest}>
      {children}
    </View>
  );
}

export function Section({ eyebrow, title, children, action }: { eyebrow?: string; title?: string; children: ReactNode; action?: ReactNode }) {
  return (
    <View style={{ gap: Space.md, marginTop: Space.xl }}>
      {(eyebrow || title || action) && (
        <Row style={{ justifyContent: 'space-between', alignItems: 'flex-end' }}>
          <View style={{ flex: 1, gap: 4 }}>
            {eyebrow ? <T v="eyebrow">{eyebrow}</T> : null}
            {title ? <T v="h2">{title}</T> : null}
          </View>
          {action}
        </Row>
      )}
      {children}
    </View>
  );
}

/* ---------- Buttons and chips ---------- */

export function Button({
  title,
  onPress,
  kind = 'primary',
  loading,
  disabled,
  small,
  style,
}: {
  title: string;
  onPress?: () => void;
  kind?: 'primary' | 'ghost' | 'danger';
  loading?: boolean;
  disabled?: boolean;
  small?: boolean;
  style?: StyleProp<ViewStyle>;
}) {
  const c = useColors();
  const bg = kind === 'primary' ? c.green : 'transparent';
  const fg = kind === 'primary' ? c.onGreen : kind === 'danger' ? c.red : c.text;
  const border = kind === 'primary' ? c.green : kind === 'danger' ? c.red : c.borderStrong;
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled || loading}
      accessibilityRole="button"
      accessibilityState={{ disabled: disabled || loading, busy: loading }}
      style={({ pressed }) => [
        styles.button,
        small && styles.buttonSmall,
        { backgroundColor: bg, borderColor: border, opacity: disabled ? 0.5 : pressed ? 0.8 : 1 },
        style,
      ]}>
      {loading ? <ActivityIndicator color={fg} /> : <Text style={[styles.buttonText, small && { fontSize: 14 }, { color: fg }]}>{title}</Text>}
    </Pressable>
  );
}

export function Chip({ label, active, onPress, count }: { label: string; active?: boolean; onPress?: () => void; count?: number }) {
  const c = useColors();
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityState={{ selected: !!active }}
      style={[styles.chip, { borderColor: active ? c.green : c.border, backgroundColor: active ? c.greenSoft : c.card }]}>
      <Text style={{ color: active ? c.greenInk : c.text2, fontWeight: '600', fontSize: 14 }}>
        {label}
        {count !== undefined ? <Text style={{ color: c.faint }}> {count}</Text> : null}
      </Text>
    </Pressable>
  );
}

/** Pick one option from a row of chips (used instead of dropdowns). */
export function ChipPicker({ label, options, value, onChange }: { label: string; options: string[]; value: string; onChange: (v: string) => void }) {
  return (
    <View style={{ gap: Space.sm }}>
      <T v="label">{label}</T>
      <View style={styles.wrap}>
        {options.map(o => (
          <Chip key={o} label={o} active={o === value} onPress={() => onChange(o)} />
        ))}
      </View>
    </View>
  );
}

export function Badge({ label, tone = 'green' }: { label: string; tone?: 'green' | 'blue' | 'grey' | 'red' }) {
  const c = useColors();
  const map = {
    green: [c.greenSoft, c.greenInk],
    blue: [c.blueSoft, c.blue],
    grey: [c.bgAlt, c.muted],
    red: [c.redSoft, c.red],
  } as const;
  const [bg, fg] = map[tone];
  return (
    <View style={[styles.badge, { backgroundColor: bg }]}>
      <Text style={{ color: fg, fontSize: 12, fontWeight: '700' }}>{label}</Text>
    </View>
  );
}

/* ---------- Forms ---------- */

type FieldProps = TextInputProps & { label: string; error?: string; hint?: string };

export const Field = forwardRef<TextInputInstance, FieldProps>(function Field({ label, error, hint, style, multiline, ...rest }, ref) {
  const c = useColors();
  return (
    <View style={{ gap: 6 }}>
      <T v="label">
        {label}
        {hint ? <T v="small"> {hint}</T> : null}
      </T>
      <TextInput
        ref={ref}
        placeholderTextColor={c.faint}
        multiline={multiline}
        accessibilityLabel={label}
        style={[
          styles.input,
          { backgroundColor: c.field, borderColor: error ? c.red : c.border, color: c.text },
          multiline && { minHeight: 120, textAlignVertical: 'top', paddingTop: 12 },
          style,
        ]}
        {...rest}
      />
      {error ? <T v="small" color={c.red}>{error}</T> : null}
    </View>
  );
});

export function Notice({ text, tone = 'red' }: { text: string; tone?: 'red' | 'green' }) {
  const c = useColors();
  return (
    <View style={[styles.notice, { backgroundColor: tone === 'red' ? c.redSoft : c.greenSoft }]} accessibilityRole="alert">
      <Text style={{ color: tone === 'red' ? c.red : c.greenInk, fontWeight: '600' }}>{text}</Text>
    </View>
  );
}

/* ---------- States ---------- */

export function Loading() {
  const c = useColors();
  return (
    <View style={{ paddingVertical: 60, alignItems: 'center' }}>
      <ActivityIndicator color={c.green} size="large" />
    </View>
  );
}

export function ErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <Card style={{ alignItems: 'center', gap: Space.md, paddingVertical: Space.xl }}>
      <T v="h3">Couldn’t load this</T>
      <T v="small" style={{ textAlign: 'center' }}>{message}</T>
      {onRetry ? <Button title="Try again" kind="ghost" small onPress={onRetry} /> : null}
    </Card>
  );
}

export function Empty({ title, text }: { title: string; text?: string }) {
  return (
    <Card style={{ alignItems: 'center', gap: Space.sm, paddingVertical: Space.xl }}>
      <T v="h3">{title}</T>
      {text ? <T v="small" style={{ textAlign: 'center' }}>{text}</T> : null}
    </Card>
  );
}

/* ---------- Numbers ---------- */

export function Stat({ value, label, note, tone }: { value: string | number; label: string; note?: string; tone?: 'up' | 'down' }) {
  const c = useColors();
  return (
    <Card style={{ flexGrow: 1, flexBasis: '45%', gap: 2 }}>
      <T v="small">{label}</T>
      <Text style={{ fontSize: 26, fontWeight: '800', color: c.text }}>{value}</Text>
      {note ? <T v="small" color={tone === 'up' ? c.green : tone === 'down' ? c.red : undefined}>{note}</T> : null}
    </Card>
  );
}

/** Stacked bar chart for daily numbers (e.g. enquiries from popup vs contact form). */
export function Bars({
  data,
  series,
  height = 120,
}: {
  data: { label: string; values: number[] }[];
  series: { name: string; color: string }[];
  height?: number;
}) {
  const c = useColors();
  const max = Math.max(1, ...data.map(d => d.values.reduce((a, b) => a + b, 0)));
  const total = series.map((_, i) => data.reduce((a, d) => a + d.values[i], 0));
  return (
    <View style={{ gap: Space.sm }}>
      <View
        style={{ height, flexDirection: 'row', alignItems: 'flex-end', gap: 2 }}
        accessible
        accessibilityLabel={series.map((s, i) => `${s.name}: ${total[i]}`).join(', ')}>
        {data.map(d => {
          const sum = d.values.reduce((a, b) => a + b, 0);
          return (
            <View key={d.label} style={{ flex: 1, height: '100%', justifyContent: 'flex-end' }}>
              {sum === 0 ? (
                <View style={{ height: 2, backgroundColor: c.border, borderRadius: 1 }} />
              ) : (
                d.values
                  .map((v, i) => (v ? <View key={i} style={{ height: (v / max) * height, backgroundColor: series[i].color }} /> : null))
                  .reverse()
              )}
            </View>
          );
        })}
      </View>
      <Row style={{ justifyContent: 'space-between' }}>
        <T v="small">{data[0]?.label}</T>
        <T v="small">{data[data.length - 1]?.label}</T>
      </Row>
      <Row gap={Space.lg}>
        {series.map((s, i) => (
          <Row key={s.name} gap={6}>
            <View style={{ width: 10, height: 10, borderRadius: 3, backgroundColor: s.color }} />
            <T v="small">
              {s.name} · {total[i]}
            </T>
          </Row>
        ))}
      </Row>
    </View>
  );
}

/** Horizontal bars for a ranked list (countries, sources, services). */
export function RankList({ items }: { items: { label: string; n: number }[] }) {
  const c = useColors();
  const max = Math.max(1, ...items.map(i => i.n));
  if (!items.length) return <T v="small">No data yet.</T>;
  return (
    <View style={{ gap: Space.sm }}>
      {items.map(i => (
        <View key={i.label} style={{ gap: 4 }}>
          <Row style={{ justifyContent: 'space-between' }}>
            <T v="label" style={{ flex: 1, fontWeight: '500' }} numberOfLines={1}>
              {i.label}
            </T>
            <T v="label">{i.n}</T>
          </Row>
          <View style={{ height: 6, borderRadius: 3, backgroundColor: c.bgAlt }}>
            <View style={{ width: `${(i.n / max) * 100}%`, height: 6, borderRadius: 3, backgroundColor: c.series1 }} />
          </View>
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { paddingHorizontal: Space.lg, width: '100%', maxWidth: MaxWidth, alignSelf: 'center' },
  card: { borderWidth: 1, borderRadius: Radius.lg, padding: Space.lg },
  button: { minHeight: 50, borderRadius: Radius.pill, borderWidth: 1.5, paddingHorizontal: 22, alignItems: 'center', justifyContent: 'center' },
  buttonSmall: { minHeight: 40, paddingHorizontal: 16 },
  buttonText: { fontSize: 16, fontWeight: '700' },
  chip: { borderWidth: 1, borderRadius: Radius.pill, paddingHorizontal: 14, paddingVertical: 8, minHeight: 38, justifyContent: 'center' },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: Space.sm },
  badge: { borderRadius: Radius.pill, paddingHorizontal: 10, paddingVertical: 3, alignSelf: 'flex-start' },
  input: { borderWidth: 1, borderRadius: Radius.md, paddingHorizontal: 14, minHeight: 50, fontSize: 16 },
  notice: { borderRadius: Radius.md, padding: Space.md },
});

export const wrapStyle = styles.wrap;
