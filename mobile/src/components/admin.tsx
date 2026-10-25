/** Pieces used across the admin screens. */

import { router } from 'expo-router';
import { Pressable, Switch, Text, View } from 'react-native';

import { Badge, Row, T } from '@/components/ui';
import { Space, useColors } from '@/constants/theme';
import { timeAgo } from '@/lib/format';
import type { Message } from '@/lib/types';

/** One enquiry in a list: name, what they want, preview and when. Unread ones are bold with a dot. */
export function MessageRow({ m, first }: { m: Message; first?: boolean }) {
  const c = useColors();
  return (
    <Pressable
      onPress={() => router.push(`/admin/messages/${m.id}`)}
      accessibilityRole="button"
      accessibilityLabel={`${m.unread ? 'Unread. ' : ''}${m.name}, ${m.service ?? ''}. ${m.excerpt}`}
      style={({ pressed }) => [
        { padding: Space.lg, gap: 4, borderTopWidth: first ? 0 : 1, borderTopColor: c.border },
        pressed && { backgroundColor: c.bgAlt },
      ]}>
      <Row style={{ justifyContent: 'space-between' }}>
        <Row gap={8} style={{ flex: 1 }}>
          {m.unread ? <View style={{ width: 9, height: 9, borderRadius: 5, backgroundColor: c.green }} /> : null}
          <T v="label" style={{ fontWeight: m.unread ? '800' : '600', flexShrink: 1 }} numberOfLines={1}>
            {m.name}
          </T>
        </Row>
        <T v="small">{timeAgo(m.created_at)}</T>
      </Row>
      <Row gap={6} style={{ flexWrap: 'wrap' }}>
        {m.service ? <Badge label={m.service} tone="blue" /> : null}
        <Badge label={m.source_label} tone="grey" />
        {m.replies_count ? <Badge label={`Replied${m.replies_count > 1 ? ` ×${m.replies_count}` : ''}`} /> : null}
      </Row>
      <T v="small" numberOfLines={2} style={{ color: m.unread ? c.text2 : c.muted }}>
        {m.excerpt}
      </T>
    </Pressable>
  );
}

/** A labelled on/off switch row. */
export function Toggle({ label, note, value, onChange }: { label: string; note?: string; value: boolean; onChange: (v: boolean) => void }) {
  const c = useColors();
  return (
    <Row style={{ justifyContent: 'space-between', paddingVertical: 4 }}>
      <View style={{ flex: 1 }}>
        <T v="label">{label}</T>
        {note ? <T v="small">{note}</T> : null}
      </View>
      <Switch value={value} onValueChange={onChange} trackColor={{ true: c.green }} accessibilityLabel={label} />
    </Row>
  );
}

/** "+" button for a screen header (add a project or review). */
export function HeaderAdd({ onPress }: { onPress: () => void }) {
  const c = useColors();
  return (
    <Pressable onPress={onPress} hitSlop={12} accessibilityRole="button" accessibilityLabel="Add">
      <Text style={{ color: c.green, fontSize: 28, fontWeight: '500', paddingHorizontal: 6 }}>+</Text>
    </Pressable>
  );
}
