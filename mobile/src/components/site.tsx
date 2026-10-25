/** Cards for the customer side: a portfolio project and a client review. */

import { Image } from 'expo-image';
import * as WebBrowser from 'expo-web-browser';
import { Pressable, Text, View } from 'react-native';

import { Card, Row, T } from '@/components/ui';
import { Radius, Space, useColors } from '@/constants/theme';
import type { Project, PublicReview } from '@/lib/types';

export function open(url: string) {
  WebBrowser.openBrowserAsync(url).catch(() => {});
}

export function ProjectCard({ p }: { p: Project }) {
  const c = useColors();
  const links = [
    p.url && { label: 'View site ↗', url: p.url },
    p.ios_url && { label: 'App Store ↗', url: p.ios_url },
    p.android_url && { label: 'Google Play ↗', url: p.android_url },
  ].filter(Boolean) as { label: string; url: string }[];

  return (
    <Card style={{ padding: 0, overflow: 'hidden' }}>
      {p.image_url ? (
        <Image
          source={p.image_url}
          style={{ width: '100%', aspectRatio: 3 / 2, backgroundColor: c.bgAlt }}
          contentFit="cover"
          transition={200}
          accessibilityLabel={`${p.title} — ${p.category_label} project by Owlvix`}
        />
      ) : null}
      <View style={{ padding: Space.lg, gap: 6 }}>
        <T v="eyebrow">{p.category_label}</T>
        <T v="h3">{p.title}</T>
        {p.summary ? <T v="small">{p.summary}</T> : null}
        {links.length ? (
          <Row gap={Space.lg} style={{ marginTop: 4, flexWrap: 'wrap' }}>
            {links.map(l => (
              <Pressable key={l.url} onPress={() => open(l.url)} accessibilityRole="link" hitSlop={8}>
                <Text style={{ color: c.green, fontWeight: '700' }}>{l.label}</Text>
              </Pressable>
            ))}
          </Row>
        ) : null}
      </View>
    </Card>
  );
}

export function Stars({ rating, size = 15 }: { rating: number; size?: number }) {
  const c = useColors();
  return (
    <Text style={{ fontSize: size, letterSpacing: 1 }} accessibilityLabel={`${rating} out of 5 stars`}>
      <Text style={{ color: c.amber }}>{'★'.repeat(rating)}</Text>
      <Text style={{ color: c.border }}>{'★'.repeat(5 - rating)}</Text>
    </Text>
  );
}

export function ReviewCard({ r }: { r: PublicReview }) {
  const c = useColors();
  return (
    <Card style={{ gap: Space.md }}>
      <Stars rating={r.rating} />
      <T v="body">“{r.text}”</T>
      <Row gap={Space.md}>
        <View style={{ width: 38, height: 38, borderRadius: Radius.pill, backgroundColor: c.greenSoft, alignItems: 'center', justifyContent: 'center' }}>
          <Text style={{ color: c.greenInk, fontWeight: '800' }}>{r.name.charAt(0).toUpperCase()}</Text>
        </View>
        <View style={{ flex: 1 }}>
          <T v="label">{r.name}</T>
          <T v="small">{[r.flag, [r.country, r.service].filter(Boolean).join(' · ')].filter(Boolean).join(' ')}</T>
        </View>
      </Row>
    </Card>
  );
}
