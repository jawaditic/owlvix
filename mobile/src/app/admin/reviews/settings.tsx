import { router } from 'expo-router';
import { useEffect, useState } from 'react';

import { Button, Field, Loading, Notice, Screen, Section, T } from '@/components/ui';
import { api, ApiError } from '@/lib/api';
import { useSite } from '@/lib/site';
import type { ReviewList, ReviewSettings } from '@/lib/types';

type Form = Record<keyof ReviewSettings, string>;

/** The Fiverr rating, review counts and profile link shown next to the reviews. */
export default function RatingSettings() {
  const site = useSite();
  const [form, setForm] = useState<Form | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    api<ReviewList>('admin/reviews')
      .then(res =>
        setForm({
          reviews_profile_url: res.settings.reviews_profile_url ?? '',
          reviews_rating: res.settings.reviews_rating ?? '',
          reviews_count: res.settings.reviews_count ?? '',
          reviews_five_star: res.settings.reviews_five_star ?? '',
        }),
      )
      .catch(e => setNotice(e instanceof ApiError ? e.message : 'Could not load.'));
  }, []);

  if (!form) return notice ? <Screen topInset={false}><Notice text={notice} /></Screen> : <Loading />;

  const set = (key: keyof Form) => (value: string) => {
    setForm({ ...form, [key]: value });
    setErrors(e => ({ ...e, [key]: '' }));
  };

  const save = async () => {
    setSaving(true);
    setNotice(null);
    try {
      await api('admin/reviews/settings', { method: 'PUT', body: form });
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

  return (
    <Screen topInset={false}>
      <T v="small">Shown above the reviews on the website and in the app. Copy these from your Fiverr profile.</T>
      <Section>
        {notice ? <Notice text={notice} /> : null}
        <Field label="Fiverr profile link" value={form.reviews_profile_url} onChangeText={set('reviews_profile_url')} error={errors.reviews_profile_url} autoCapitalize="none" keyboardType="url" />
        <Field label="Rating" hint="(0 to 5)" value={form.reviews_rating} onChangeText={set('reviews_rating')} error={errors.reviews_rating} keyboardType="decimal-pad" />
        <Field label="Number of reviews" value={form.reviews_count} onChangeText={set('reviews_count')} error={errors.reviews_count} keyboardType="number-pad" />
        <Field label="Five-star reviews" value={form.reviews_five_star} onChangeText={set('reviews_five_star')} error={errors.reviews_five_star} keyboardType="number-pad" />
        <Button title="Save" onPress={save} loading={saving} />
      </Section>
    </Screen>
  );
}
