import { useMemo, useState } from 'react';
import { ScrollView } from 'react-native';

import { ProjectCard } from '@/components/site';
import { Chip, Empty, ErrorState, Loading, Row, Screen, Section, T } from '@/components/ui';
import { Space } from '@/constants/theme';
import { useSite } from '@/lib/site';

export default function Work() {
  const { data, error, refreshing, refresh } = useSite();
  const [cat, setCat] = useState('all');

  const projects = useMemo(() => data?.projects ?? [], [data]);
  const cats = useMemo(
    () => Object.entries(data?.categories ?? {}).filter(([key]) => projects.some(p => p.category === key)),
    [data, projects],
  );
  const shown = cat === 'all' ? projects : projects.filter(p => p.category === cat);

  return (
    <Screen refreshing={refreshing} onRefresh={refresh}>
      <T v="eyebrow">Our work</T>
      <T v="h1" accessibilityRole="header">Projects we’ve built</T>

      {!data && error ? <ErrorState message={error} onRetry={refresh} /> : null}
      {!data && !error ? <Loading /> : null}

      {data ? (
        <Section>
          {cats.length > 1 ? (
            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -Space.lg }} contentContainerStyle={{ paddingHorizontal: Space.lg }}>
              <Row>
                <Chip label="All" count={projects.length} active={cat === 'all'} onPress={() => setCat('all')} />
                {cats.map(([key, label]) => (
                  <Chip key={key} label={label} count={projects.filter(p => p.category === key).length} active={cat === key} onPress={() => setCat(key)} />
                ))}
              </Row>
            </ScrollView>
          ) : null}
          {shown.length ? shown.map(p => <ProjectCard key={p.id} p={p} />) : <Empty title="No projects here yet" />}
        </Section>
      ) : null}
    </Screen>
  );
}
