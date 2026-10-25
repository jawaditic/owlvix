import { router, type Href } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Pressable, Text, View, type TextInputInstance } from 'react-native';

import { MessageRow } from '@/components/admin';
import { Bars, Button, Card, ErrorState, Field, Loading, Notice, RankList, Row, Screen, Section, Stat, T, wrapStyle } from '@/components/ui';
import { Space, useColors } from '@/constants/theme';
import { ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { confirm } from '@/lib/confirm';
import { dayLabel, hours } from '@/lib/format';
import type { Dashboard } from '@/lib/types';
import { useApi } from '@/lib/use-api';

export default function AdminTab() {
  const { token } = useAuth();
  return token ? <AdminHome /> : <Login />;
}

function Login() {
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const passwordRef = useRef<TextInputInstance>(null);

  const submit = async () => {
    if (!email.trim() || !password) {
      setError('Please enter your email and password.');
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await login(email.trim(), password);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Could not log in.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen>
      <T v="eyebrow">Owlvix admin</T>
      <T v="h1" accessibilityRole="header">Log in</T>
      <T v="body" style={{ marginTop: Space.sm }}>
        For the Owlvix team. Use the same email and password as the website admin.
      </T>
      <Section>
        {error ? <Notice text={error} /> : null}
        <Field
          label="Email"
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          keyboardType="email-address"
          autoComplete="email"
          textContentType="username"
          returnKeyType="next"
          onSubmitEditing={() => passwordRef.current?.focus()}
        />
        <Field
          ref={passwordRef}
          label="Password"
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          autoComplete="current-password"
          textContentType="password"
          returnKeyType="go"
          onSubmitEditing={submit}
        />
        <Button title="Log in" onPress={submit} loading={busy} />
      </Section>
    </Screen>
  );
}

const LINKS: { label: string; note: string; href: Href; icon: string }[] = [
  { label: 'Messages', note: 'Enquiries and replies', href: '/admin/messages', icon: '✉️' },
  { label: 'Traffic', note: 'Visitors and sources', href: '/admin/traffic', icon: '📈' },
  { label: 'Portfolio', note: 'Projects on the site', href: '/admin/projects', icon: '🗂️' },
  { label: 'Reviews', note: 'Client reviews', href: '/admin/reviews', icon: '⭐' },
];

function AdminHome() {
  const c = useColors();
  const { logout, setUnread } = useAuth();
  const { data, error, loading, refreshing, refresh, reload } = useApi<Dashboard>('admin/dashboard');

  useEffect(() => {
    if (data) setUnread(data.stats.unread);
  }, [data, setUnread]);

  const confirmLogout = () => confirm('Log out?', 'You will stop getting new-enquiry alerts on this phone.', 'Log out', () => logout());

  const s = data?.stats;
  const change = s ? s.thisMonth - s.lastMonth : 0;
  const last30 = data?.daily.slice(-30) ?? [];

  return (
    <Screen refreshing={refreshing} onRefresh={refresh}>
      <Row style={{ justifyContent: 'space-between' }}>
        <View>
          <T v="eyebrow">Owlvix admin</T>
          <T v="h1" accessibilityRole="header">Dashboard</T>
        </View>
        <Button title="Log out" kind="ghost" small onPress={confirmLogout} />
      </Row>

      <View style={[wrapStyle, { marginTop: Space.lg }]}>
        {LINKS.map(l => (
          <Card key={l.label} onPress={() => router.push(l.href)} style={{ flexGrow: 1, flexBasis: '45%', gap: 4 }}>
            <Text style={{ fontSize: 22 }}>{l.icon}</Text>
            <Row gap={6}>
              <T v="h3">{l.label}</T>
              {l.label === 'Messages' && s?.unread ? (
                <View style={{ backgroundColor: c.green, borderRadius: 10, paddingHorizontal: 7, paddingVertical: 1 }}>
                  <Text style={{ color: c.onGreen, fontWeight: '800', fontSize: 12 }}>{s.unread}</Text>
                </View>
              ) : null}
            </Row>
            <T v="small">{l.note}</T>
          </Card>
        ))}
      </View>

      {loading ? <Loading /> : null}
      {error && !data ? <ErrorState message={error} onRetry={reload} /> : null}

      {data && s ? (
        <>
          <Section title="Enquiries">
            <View style={wrapStyle}>
              <Stat label="This month" value={s.thisMonth} note={`${change >= 0 ? '+' : ''}${change} vs last month`} tone={change > 0 ? 'up' : change < 0 ? 'down' : undefined} />
              <Stat label="Unread" value={s.unread} note={`${s.total} in total`} />
              <Stat label="Replied" value={s.replyRate === null ? '—' : `${s.replyRate}%`} note={`${s.replied} answered`} />
              <Stat label="First reply" value={hours(s.avgReplyHours)} note="average time" />
            </View>
            <Card>
              <T v="label" style={{ marginBottom: Space.md }}>Last 30 days</T>
              <Bars
                data={last30.map(d => ({ label: dayLabel(d.date), values: [d.popup, d.contact] }))}
                series={[
                  { name: 'Popup', color: c.series1 },
                  { name: 'Contact form & app', color: c.series2 },
                ]}
              />
            </Card>
          </Section>

          <Section title="Latest messages" action={<Button title="All" kind="ghost" small onPress={() => router.push('/admin/messages')} />}>
            {data.recent.length ? (
              <Card style={{ padding: 0 }}>
                {data.recent.map((m, i) => (
                  <MessageRow key={m.id} m={m} first={i === 0} />
                ))}
              </Card>
            ) : (
              <T v="small">No messages yet.</T>
            )}
          </Section>

          <Section title="What people ask for">
            <Card>
              <RankList items={Object.entries(data.services).map(([label, n]) => ({ label, n }))} />
            </Card>
            <Card>
              <T v="label" style={{ marginBottom: Space.md }}>Budgets</T>
              <RankList items={Object.entries(data.budgets).map(([label, n]) => ({ label, n }))} />
            </Card>
          </Section>

          <Section title="On the website">
            <Row>
              <Pressable style={{ flex: 1 }} onPress={() => router.push('/admin/projects')}>
                <Stat label="Projects shown" value={`${data.content.projects}/${data.content.projectsTotal}`} />
              </Pressable>
              <Pressable style={{ flex: 1 }} onPress={() => router.push('/admin/reviews')}>
                <Stat label="Reviews shown" value={`${data.content.reviews}/${data.content.reviewsTotal}`} />
              </Pressable>
            </Row>
          </Section>
        </>
      ) : null}
    </Screen>
  );
}
