import { Text } from 'react-native';

import { open, ReviewCard, Stars } from '@/components/site';
import { Button, Card, Empty, ErrorState, Loading, Screen, Section, T } from '@/components/ui';
import { Space, useColors } from '@/constants/theme';
import { useSite } from '@/lib/site';

export default function Reviews() {
  const c = useColors();
  const { data, error, refreshing, refresh } = useSite();
  const r = data?.reviews;

  return (
    <Screen refreshing={refreshing} onRefresh={refresh}>
      <T v="eyebrow">Client reviews</T>
      <T v="h1" accessibilityRole="header">What clients say</T>

      {!data && error ? <ErrorState message={error} onRetry={refresh} /> : null}
      {!data && !error ? <Loading /> : null}

      {r ? (
        <Section>
          {r.count ? (
            <Card style={{ alignItems: 'center', gap: 6 }}>
              <Stars rating={5} size={20} />
              <Text style={{ fontSize: 30, fontWeight: '800', color: c.text }}>
                {r.five_star ? `${r.five_star} of ${r.count}` : r.count}
              </Text>
              <T v="small">{r.five_star ? 'reviews are 5 stars on Fiverr' : 'reviews on Fiverr'}</T>
            </Card>
          ) : null}
          {r.items.length ? r.items.map((item, i) => <ReviewCard key={`${item.name}-${i}`} r={item} />) : <Empty title="No reviews yet" />}
          {r.profile_url ? (
            <Button title={`Read all ${r.count || ''} reviews on Fiverr ↗`} kind="ghost" onPress={() => open(r.profile_url)} style={{ marginTop: Space.sm }} />
          ) : null}
        </Section>
      ) : null}
    </Screen>
  );
}
