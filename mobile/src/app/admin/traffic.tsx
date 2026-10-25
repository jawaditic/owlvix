import { useState } from 'react';
import { View } from 'react-native';

import { Bars, Card, Chip, ErrorState, Loading, RankList, Row, Screen, Section, Stat, T, wrapStyle } from '@/components/ui';
import { Space, useColors } from '@/constants/theme';
import { dayLabel } from '@/lib/format';
import type { Traffic } from '@/lib/types';
import { useApi } from '@/lib/use-api';

const label = (key: string | null, fallback = 'Unknown') => (key ? key.charAt(0).toUpperCase() + key.slice(1) : fallback);

export default function TrafficScreen() {
  const c = useColors();
  const [days, setDays] = useState(30);
  const { data, error, loading, refreshing, refresh, reload } = useApi<Traffic>(`admin/traffic?days=${days}`);
  const s = data?.stats;

  return (
    <Screen topInset={false} refreshing={refreshing} onRefresh={refresh}>
      <Row>
        {[7, 30, 90].map(d => (
          <Chip key={d} label={`${d} days`} active={days === d} onPress={() => setDays(d)} />
        ))}
      </Row>

      {loading ? <Loading /> : null}
      {error && !data ? <ErrorState message={error} onRetry={reload} /> : null}

      {data && s ? (
        <>
          <View style={[wrapStyle, { marginTop: Space.lg }]}>
            <Stat label="Visitors" value={s.visitors} note={`${s.newShare}% new`} />
            <Stat label="Page views" value={s.views} note={`${s.perVisitor} per visitor`} />
            <Stat label="Enquiries" value={s.enquiries} note={s.conversion === null ? undefined : `${s.conversion}% of visitors`} />
            <Stat label="Today" value={s.today} note={s.live ? `${s.live} on the site now` : 'nobody right now'} tone={s.live ? 'up' : undefined} />
          </View>

          <Section title="Visitors per day">
            <Card>
              <Bars
                data={data.daily.slice(-days).map(d => ({ label: dayLabel(d.date), values: [d.new, d.returning] }))}
                series={[
                  { name: 'New', color: c.series1 },
                  { name: 'Returning', color: c.series2 },
                ]}
              />
            </Card>
          </Section>

          <Section title="Countries">
            <Card>
              <RankList items={data.countries.map(x => ({ label: `${x.flag} ${x.name}`.trim(), n: x.n }))} />
            </Card>
          </Section>

          <Section title="Where they came from">
            <Card>
              <RankList items={data.sources.map(x => ({ label: label(x.key, 'Direct'), n: x.n }))} />
            </Card>
          </Section>

          <Section title="Devices and browsers">
            <Card style={{ gap: Space.lg }}>
              <RankList items={data.devices.map(x => ({ label: label(x.key), n: x.n }))} />
              <View style={{ height: 1, backgroundColor: c.border }} />
              <RankList items={data.browsers.map(x => ({ label: label(x.key), n: x.n }))} />
            </Card>
          </Section>

          <Section title="Top pages">
            <Card>
              <RankList items={data.pages.map(x => ({ label: x.path, n: x.n }))} />
            </Card>
          </Section>
          <T v="small" style={{ marginTop: Space.lg }}>
            A visitor is one browser on one day. No cookies or IP addresses are stored.
          </T>
        </>
      ) : null}
    </Screen>
  );
}
