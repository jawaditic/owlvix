import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { Pressable, ScrollView, Text, View } from 'react-native';

import { Toggle } from '@/components/admin';
import { Button, Card, Chip, Field, Loading, Notice, Row, Screen, Section, T } from '@/components/ui';
import { Space, useColors } from '@/constants/theme';
import { api, ApiError } from '@/lib/api';
import { confirm } from '@/lib/confirm';
import { useSite } from '@/lib/site';
import type { ReviewList } from '@/lib/types';

type Form = { name: string; country_code: string; service: string; text: string; rating: number; is_published: boolean; in_email: boolean };

const blank: Form = { name: '', country_code: '', service: '', text: '', rating: 5, is_published: true, in_email: false };

const flag = (code: string) =>
  /^[a-z]{2}$/i.test(code) ? String.fromCodePoint(...code.toUpperCase().split('').map(ch => 0x1f1e6 + ch.charCodeAt(0) - 65)) : '';

export default function ReviewForm() {
  const c = useColors();
  const site = useSite();
  const { id } = useLocalSearchParams<{ id: string }>();
  const isNew = id === 'new';
  const [form, setForm] = useState<Form>(blank);
  const [countries, setCountries] = useState<Record<string, string>>({});
  const [countrySearch, setCountrySearch] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    api<ReviewList>('admin/reviews')
      .then(res => {
        setCountries(res.countries);
        const r = res.data.find(x => String(x.id) === id);
        if (r) {
          setForm({
            name: r.name,
            country_code: r.country_code ?? '',
            service: r.service ?? '',
            text: r.text,
            rating: r.rating,
            is_published: r.is_published,
            in_email: r.in_email,
          });
        } else if (!isNew) {
          setNotice('This review no longer exists.');
        }
      })
      .catch(e => setNotice(e instanceof ApiError ? e.message : 'Could not load.'))
      .finally(() => setReady(true));
  }, [id, isNew]);

  const set = <K extends keyof Form>(key: K) => (value: Form[K]) => {
    setForm(f => ({ ...f, [key]: value }));
    setErrors(e => ({ ...e, [key]: '' }));
  };

  const matches = useMemo(() => {
    const q = countrySearch.trim().toLowerCase();
    if (!q) return [];
    return Object.entries(countries)
      .filter(([code, name]) => name.toLowerCase().includes(q) || code === q)
      .slice(0, 12);
  }, [countries, countrySearch]);

  const save = async () => {
    setSaving(true);
    setNotice(null);
    try {
      const body = { ...form, name: form.name.trim(), text: form.text.trim(), service: form.service.trim() || null, country_code: form.country_code || null };
      await api(isNew ? 'admin/reviews' : `admin/reviews/${id}`, { method: isNew ? 'POST' : 'PUT', body });
      site.refresh();
      router.back();
    } catch (e) {
      if (e instanceof ApiError) {
        setErrors(e.fields);
        setNotice(Object.keys(e.fields).length ? 'Please check the highlighted fields.' : e.message);
      }
    } finally {
      setSaving(false);
    }
  };

  const remove = () =>
    confirm('Delete this review?', `The review from ${form.name} will be removed from the website and the app.`, 'Delete', async () => {
      await api(`admin/reviews/${id}`, { method: 'DELETE' });
      site.refresh();
      router.back();
    });

  if (!ready) return <Loading />;

  return (
    <Screen topInset={false}>
      <Stack.Screen options={{ title: isNew ? 'Add review' : 'Edit review' }} />
      {notice ? <Notice text={notice} /> : null}

      <Section>
        <Field label="Client name" value={form.name} onChangeText={set('name')} error={errors.name} maxLength={80} />

        <View style={{ gap: 6 }}>
          <T v="label">Country</T>
          {form.country_code ? (
            <Row>
              <Chip label={`${flag(form.country_code)} ${countries[form.country_code] ?? form.country_code}`} active />
              <Button title="Change" kind="ghost" small onPress={() => set('country_code')('')} />
            </Row>
          ) : (
            <>
              <Field label="Search countries" placeholder="e.g. United States" value={countrySearch} onChangeText={setCountrySearch} error={errors.country_code} />
              {matches.length ? (
                <ScrollView horizontal showsHorizontalScrollIndicator={false} keyboardShouldPersistTaps="handled">
                  <Row>
                    {matches.map(([code, name]) => (
                      <Chip key={code} label={`${flag(code)} ${name}`} onPress={() => { set('country_code')(code); setCountrySearch(''); }} />
                    ))}
                  </Row>
                </ScrollView>
              ) : null}
            </>
          )}
        </View>

        <Field label="Service" hint="(optional)" placeholder="e.g. Web app" value={form.service} onChangeText={set('service')} error={errors.service} maxLength={60} />

        <View style={{ gap: 6 }}>
          <T v="label">Rating</T>
          <Row gap={4} accessibilityRole="adjustable" accessibilityLabel={`Rating ${form.rating} of 5`}>
            {[1, 2, 3, 4, 5].map(n => (
              <Pressable key={n} onPress={() => set('rating')(n)} hitSlop={6} accessibilityRole="button" accessibilityLabel={`${n} star${n > 1 ? 's' : ''}`}>
                <Text style={{ fontSize: 34, color: n <= form.rating ? c.amber : c.border }}>★</Text>
              </Pressable>
            ))}
          </Row>
        </View>

        <Field label="Review" value={form.text} onChangeText={set('text')} error={errors.text} multiline maxLength={1000} />

        <Card style={{ gap: Space.sm }}>
          <Toggle label="Show on the website" value={form.is_published} onChange={set('is_published')} />
          <Toggle label="Show in the confirmation email" note="Up to 3 reviews are used." value={form.in_email} onChange={set('in_email')} />
        </Card>

        <Button title={isNew ? 'Add review' : 'Save changes'} onPress={save} loading={saving} />
        {!isNew ? <Button title="Delete review" kind="danger" onPress={remove} /> : null}
      </Section>
    </Screen>
  );
}
