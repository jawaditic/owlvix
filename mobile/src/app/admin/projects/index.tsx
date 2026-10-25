import { Image } from 'expo-image';
import { router, Stack } from 'expo-router';
import { useState } from 'react';
import { Pressable, View } from 'react-native';

import { HeaderAdd } from '@/components/admin';
import { Badge, Button, Card, Empty, ErrorState, Loading, Row, Screen, Section, T } from '@/components/ui';
import { Space, useColors } from '@/constants/theme';
import { api } from '@/lib/api';
import { useSite } from '@/lib/site';
import type { Project, ProjectList } from '@/lib/types';
import { useApi } from '@/lib/use-api';

export default function Projects() {
  const c = useColors();
  const site = useSite();
  const { data, setData, error, loading, refreshing, refresh, reload } = useApi<ProjectList>('admin/projects');
  const [busy, setBusy] = useState<number | null>(null);

  const run = async (p: Project, fn: () => Promise<void>) => {
    setBusy(p.id);
    try {
      await fn();
      site.refresh(); // keep the customer tabs in step
    } finally {
      setBusy(null);
    }
  };

  const toggle = (p: Project) =>
    run(p, async () => {
      const updated = await api<Project>(`admin/projects/${p.id}/toggle`, { method: 'POST' });
      setData(d => d && { ...d, data: d.data.map(x => (x.id === p.id ? updated : x)) });
    });

  const move = (p: Project, dir: 'up' | 'down') => run(p, async () => setData(await api<ProjectList>(`admin/projects/${p.id}/move/${dir}`, { method: 'POST' })));

  const list = data?.data ?? [];

  return (
    <Screen topInset={false} refreshing={refreshing} onRefresh={refresh}>
      <Stack.Screen options={{ headerRight: () => <HeaderAdd onPress={() => router.push('/admin/projects/new')} /> }} />
      <T v="small">Projects shown in “Our work” on the website and in the app. The first ones show first.</T>

      {loading ? <Loading /> : null}
      {error && !data ? <ErrorState message={error} onRetry={reload} /> : null}

      {data ? (
        <Section>
          {list.length ? null : <Empty title="No projects yet" text="Add your first project with the + button." />}
          {list.map((p, i) => (
            <Card key={p.id} style={{ gap: Space.md, opacity: busy === p.id ? 0.6 : 1 }}>
              <Pressable onPress={() => router.push(`/admin/projects/${p.id}`)} style={{ flexDirection: 'row', gap: Space.md }} accessibilityRole="button" accessibilityLabel={`Edit ${p.title}`}>
                {p.image_url ? (
                  <Image source={p.image_url} style={{ width: 84, height: 60, borderRadius: 8, backgroundColor: c.bgAlt }} contentFit="cover" />
                ) : (
                  <View style={{ width: 84, height: 60, borderRadius: 8, backgroundColor: c.bgAlt }} />
                )}
                <View style={{ flex: 1, gap: 4 }}>
                  <T v="label" numberOfLines={1}>{p.title}</T>
                  <Row gap={6}>
                    <Badge label={p.category_label} tone="blue" />
                    {p.is_published ? <Badge label="On site" /> : <Badge label="Hidden" tone="grey" />}
                  </Row>
                </View>
              </Pressable>
              <Row>
                <Button title={p.is_published ? 'Hide' : 'Show'} kind="ghost" small onPress={() => toggle(p)} style={{ flex: 1 }} />
                <Button title="↑" kind="ghost" small disabled={i === 0} onPress={() => move(p, 'up')} />
                <Button title="↓" kind="ghost" small disabled={i === list.length - 1} onPress={() => move(p, 'down')} />
              </Row>
            </Card>
          ))}
          <Button title="Add a project" onPress={() => router.push('/admin/projects/new')} />
        </Section>
      ) : null}
    </Screen>
  );
}
