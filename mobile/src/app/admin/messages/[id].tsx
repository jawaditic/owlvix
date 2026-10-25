import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Linking, Text, View } from 'react-native';

import { Badge, Button, Card, ErrorState, Field, Loading, Notice, Row, Screen, Section, T } from '@/components/ui';
import { Space, useColors } from '@/constants/theme';
import { api, ApiError } from '@/lib/api';
import { confirm } from '@/lib/confirm';
import { dateTime } from '@/lib/format';
import type { MessageFull } from '@/lib/types';
import { useApi } from '@/lib/use-api';

export default function MessageScreen() {
  const c = useColors();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { data: m, setData, error, loading, reload } = useApi<MessageFull>(`admin/messages/${id}`);
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [sending, setSending] = useState(false);
  const [replyError, setReplyError] = useState<string | null>(null);
  const [sent, setSent] = useState(false);
  const [busy, setBusy] = useState<'read' | 'delete' | null>(null);

  // Start the reply with a sensible subject and greeting once the message has loaded
  const [draftFor, setDraftFor] = useState<number | null>(null);
  if (m && draftFor !== m.id) {
    setDraftFor(m.id);
    setSubject(`Re: your ${m.service ? m.service.toLowerCase() + ' ' : ''}enquiry — Owlvix`);
    setBody(`Hi ${m.name.split(' ')[0]},\n\n`);
  }

  if (loading) return <Loading />;
  if (!m) return <Screen topInset={false}>{error ? <ErrorState message={error} onRetry={reload} /> : null}</Screen>;

  const sendReply = async () => {
    const greeting = `Hi ${m.name.split(' ')[0]},`;
    if (!subject.trim() || body.trim().length < 2 || body.trim() === greeting) {
      setReplyError('Please write a subject and your message.');
      return;
    }
    setSending(true);
    setReplyError(null);
    try {
      setData(await api<MessageFull>(`admin/messages/${m.id}/reply`, { method: 'POST', body: { subject: subject.trim(), body: body.trim() } }));
      setBody(`Hi ${m.name.split(' ')[0]},\n\n`);
      setSent(true);
    } catch (e) {
      setReplyError(e instanceof ApiError ? e.fields.body ?? e.fields.subject ?? e.message : 'Could not send.');
    } finally {
      setSending(false);
    }
  };

  const toggleRead = async () => {
    setBusy('read');
    try {
      const res = await api<{ unread: boolean }>(`admin/messages/${m.id}/read`, { method: 'PATCH' });
      if (res.unread) router.back();
      else setData({ ...m, unread: false });
    } finally {
      setBusy(null);
    }
  };

  const remove = () =>
    confirm('Delete this message?', `The enquiry from ${m.name} and its replies will be removed for good.`, 'Delete', async () => {
      setBusy('delete');
      try {
        await api(`admin/messages/${m.id}`, { method: 'DELETE' });
        router.back();
      } catch {
        setBusy(null);
      }
    });

  const digits = m.phone?.replace(/[^\d+]/g, '') ?? '';
  const details: [string, string | null][] = [
    ['Email', m.email],
    ['Phone', m.phone],
    ['Service', m.service],
    ['Budget', m.budget],
    ['Sent from', m.source_label],
    ['Received', dateTime(m.created_at)],
  ];

  return (
    <Screen topInset={false}>
      <Stack.Screen options={{ title: m.name }} />

      <Card style={{ gap: Space.md }}>
        <Row style={{ justifyContent: 'space-between' }}>
          <T v="h2" style={{ flex: 1 }}>{m.name}</T>
          {m.replies.length ? <Badge label="Replied" /> : <Badge label="Not replied" tone="grey" />}
        </Row>
        {details
          .filter(([, v]) => v)
          .map(([k, v]) => (
            <Row key={k} style={{ alignItems: 'flex-start' }}>
              <T v="small" style={{ width: 82 }}>{k}</T>
              <T v="label" style={{ flex: 1, fontWeight: '500' }} selectable>
                {v}
              </T>
            </Row>
          ))}
        <Row style={{ flexWrap: 'wrap' }}>
          <Button title="Email" kind="ghost" small onPress={() => Linking.openURL(`mailto:${m.email}`)} />
          {digits ? <Button title="Call" kind="ghost" small onPress={() => Linking.openURL(`tel:${digits}`)} /> : null}
          {digits ? <Button title="WhatsApp" kind="ghost" small onPress={() => Linking.openURL(`https://wa.me/${digits.replace('+', '')}`)} /> : null}
        </Row>
      </Card>

      <Section title="Their project">
        <Card>
          <T v="body" selectable>
            {m.message_text}
          </T>
        </Card>
      </Section>

      {m.replies.length ? (
        <Section title={`Your replies (${m.replies.length})`}>
          {m.replies.map(r => (
            <Card key={r.id} style={{ gap: 6, backgroundColor: c.greenSoft, borderColor: c.green }}>
              <T v="label">{r.subject}</T>
              <T v="small">{dateTime(r.created_at)}</T>
              <T v="body" selectable>
                {r.body}
              </T>
            </Card>
          ))}
        </Section>
      ) : null}

      <Section title="Reply by email">
        <T v="small">
          Sent from Owlvix to {m.email}. Their reply comes back to your inbox.
        </T>
        {sent ? <Notice tone="green" text={`Reply sent to ${m.email}.`} /> : null}
        {replyError ? <Notice text={replyError} /> : null}
        <Field label="Subject" value={subject} onChangeText={setSubject} maxLength={200} />
        <Field label="Message" value={body} onChangeText={t => { setBody(t); setSent(false); }} multiline maxLength={10000} />
        <Button title="Send reply" onPress={sendReply} loading={sending} />
      </Section>

      <View style={{ flexDirection: 'row', gap: Space.md, marginTop: Space.xl }}>
        <Button title="Mark unread" kind="ghost" small onPress={toggleRead} loading={busy === 'read'} style={{ flex: 1 }} />
        <Button title="Delete" kind="danger" small onPress={remove} loading={busy === 'delete'} style={{ flex: 1 }} />
      </View>
      <Text style={{ height: Space.xl }} />
    </Screen>
  );
}
