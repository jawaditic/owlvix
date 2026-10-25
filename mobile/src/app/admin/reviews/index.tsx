import { router, Stack } from 'expo-router';
import { useState } from 'react';
import { Pressable } from 'react-native';

import { HeaderAdd } from '@/components/admin';
import { Stars } from '@/components/site';
import { Badge, Button, Card, Empty, ErrorState, Loading, Row, Screen, Section, T } from '@/components/ui';
import { Space } from '@/constants/theme';
import { api } from '@/lib/api';
import { useSite } from '@/lib/site';
import type { Review, ReviewList } from '@/lib/types';
import { useApi } from '@/lib/use-api';

export default function Reviews() {
  const site = useSite();
  const { data, setData, error, loading, refreshing, refresh, reload } = useApi<ReviewList>('admin/reviews');
  const [busy, setBusy] = useState<number | null>(null);

  const run = async (r: Review, fn: () => Promise<void>) => {
    setBusy(r.id);
    try {
      await fn();
      site.refresh();
    } finally {
      setBusy(null);
    }
  };

  const toggle = (r: Review, field: 'is_published' | 'in_email') =>
    run(r, async () => {
      const updated = await api<Review>(`admin/reviews/${r.id}/toggle/${field}`, { method: 'POST' });
      setData(d => d && { ...d, data: d.data.map(x => (x.id === r.id ? updated : x)) });
    });

  const move = (r: Review, dir: 'up' | 'down') => run(r, async () => setData(await api<ReviewList>(`admin/reviews/${r.id}/move/${dir}`, { method: 'POST' })));

  const list = data?.data ?? [];
  const s = data?.settings;

  return (
    <Screen topInset={false} refreshing={refreshing} onRefresh={refresh}>
      <Stack.Screen options={{ headerRight: () => <HeaderAdd onPress={() => router.push('/admin/reviews/new')} /> }} />

      {loading ? <Loading /> : null}
      {error && !data ? <ErrorState message={error} onRetry={reload} /> : null}

      {data && s ? (
        <>
          <Card onPress={() => router.push('/admin/reviews/settings')} style={{ gap: 4 }}>
            <Row style={{ justifyContent: 'space-between' }}>
              <T v="label">Fiverr rating summary</T>
              <T v="label" style={{ color: undefined }}>Edit ›</T>
            </Row>
            <T v="small">
              ★ {s.reviews_rating || '—'} · {s.reviews_count || 0} reviews · {s.reviews_five_star || 0} five-star
            </T>
          </Card>

          <Section title="Reviews">
            <T v="small">“In email” reviews (up to 3) appear in the confirmation email new clients get.</T>
            {list.length ? null : <Empty title="No reviews yet" text="Add one with the + button." />}
            {list.map((r, i) => (
              <Card key={r.id} style={{ gap: Space.sm, opacity: busy === r.id ? 0.6 : 1 }}>
                <Pressable onPress={() => router.push(`/admin/reviews/${r.id}`)} style={{ gap: 6 }} accessibilityRole="button" accessibilityLabel={`Edit review by ${r.name}`}>
                  <Row style={{ justifyContent: 'space-between' }}>
                    <T v="label" style={{ flex: 1 }} numberOfLines={1}>
                      {r.flag} {r.name}
                    </T>
                    <Stars rating={r.rating} size={13} />
                  </Row>
                  <T v="small" numberOfLines={3}>“{r.text}”</T>
                  <Row gap={6}>
                    {r.is_published ? <Badge label="On site" /> : <Badge label="Hidden" tone="grey" />}
                    {r.in_email ? <Badge label="In email" tone="blue" /> : null}
                  </Row>
                </Pressable>
                <Row style={{ flexWrap: 'wrap' }}>
                  <Button title={r.is_published ? 'Hide' : 'Show'} kind="ghost" small onPress={() => toggle(r, 'is_published')} />
                  <Button title={r.in_email ? 'Not in email' : 'In email'} kind="ghost" small onPress={() => toggle(r, 'in_email')} />
                  <Button title="↑" kind="ghost" small disabled={i === 0} onPress={() => move(r, 'up')} />
                  <Button title="↓" kind="ghost" small disabled={i === list.length - 1} onPress={() => move(r, 'down')} />
                </Row>
              </Card>
            ))}
            <Button title="Add a review" onPress={() => router.push('/admin/reviews/new')} />
          </Section>
        </>
      ) : null}
    </Screen>
  );
}
