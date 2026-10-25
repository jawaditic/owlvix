import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { Platform, Pressable, View } from 'react-native';

import { Toggle } from '@/components/admin';
import { Button, Card, ChipPicker, Field, Loading, Notice, Screen, Section, T } from '@/components/ui';
import { Radius, useColors } from '@/constants/theme';
import { api, ApiError } from '@/lib/api';
import { confirm } from '@/lib/confirm';
import { useSite } from '@/lib/site';
import type { Project, ProjectList } from '@/lib/types';

type Form = { title: string; category: string; summary: string; url: string; ios_url: string; android_url: string; is_published: boolean };
type Picked = { uri: string; name: string; type: string };

const blank: Form = { title: '', category: 'website', summary: '', url: '', ios_url: '', android_url: '', is_published: true };

export default function ProjectForm() {
  const c = useColors();
  const site = useSite();
  const { id } = useLocalSearchParams<{ id: string }>();
  const isNew = id === 'new';
  const [form, setForm] = useState<Form>(blank);
  const [categories, setCategories] = useState<Record<string, string>>({});
  const [imageUrl, setImageUrl] = useState<string | null>(null);
  const [picked, setPicked] = useState<Picked | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  const [saving, setSaving] = useState(false);

  // The list endpoint carries the categories and every project, so one call fills the form
  useEffect(() => {
    api<ProjectList>('admin/projects')
      .then(res => {
        setCategories(res.categories);
        const p = res.data.find(x => String(x.id) === id);
        if (p) {
          setForm({
            title: p.title,
            category: p.category,
            summary: p.summary ?? '',
            url: p.url ?? '',
            ios_url: p.ios_url ?? '',
            android_url: p.android_url ?? '',
            is_published: p.is_published,
          });
          setImageUrl(p.image_url);
        } else if (!isNew) {
          setNotice('This project no longer exists.');
        }
      })
      .catch(e => setNotice(e instanceof ApiError ? e.message : 'Could not load.'))
      .finally(() => setReady(true));
  }, [id, isNew]);

  const set = <K extends keyof Form>(key: K) => (value: Form[K]) => {
    setForm(f => ({ ...f, [key]: value }));
    setErrors(e => ({ ...e, [key]: '' }));
  };

  const pick = async () => {
    const res = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.85, allowsEditing: true, aspect: [3, 2] });
    if (res.canceled || !res.assets?.[0]) return;
    const a = res.assets[0];
    const type = a.mimeType ?? 'image/jpeg';
    const ext = type.split('/')[1]?.replace('jpeg', 'jpg') ?? 'jpg';
    setPicked({ uri: a.uri, name: a.fileName ?? `project.${ext}`, type });
    setErrors(e => ({ ...e, image: '' }));
  };

  const save = async () => {
    if (!form.title.trim()) {
      setErrors({ title: 'Please enter a title.' });
      return;
    }
    if (isNew && !picked) {
      setErrors({ image: 'Please add a screenshot or mockup of the project.' });
      return;
    }
    setSaving(true);
    setNotice(null);
    try {
      const body = new FormData();
      Object.entries(form).forEach(([k, v]) => body.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : v.trim()));
      if (picked) {
        if (Platform.OS === 'web') {
          body.append('image', await (await fetch(picked.uri)).blob(), picked.name);
        } else {
          // React Native's FormData takes a file as { uri, name, type }
          body.append('image', { uri: picked.uri, name: picked.name, type: picked.type } as unknown as Blob);
        }
      }
      await api<Project>(isNew ? 'admin/projects' : `admin/projects/${id}`, { method: 'POST', form: body });
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
    confirm('Delete this project?', `“${form.title}” will be removed from the website and the app.`, 'Delete', async () => {
      await api(`admin/projects/${id}`, { method: 'DELETE' });
      site.refresh();
      router.back();
    });

  if (!ready) return <Loading />;

  const preview = picked?.uri ?? imageUrl;

  return (
    <Screen topInset={false}>
      <Stack.Screen options={{ title: isNew ? 'Add project' : 'Edit project' }} />
      {notice ? <Notice text={notice} /> : null}

      <Section>
        <View style={{ gap: 6 }}>
          <T v="label">Screenshot</T>
          <Pressable onPress={pick} accessibilityRole="button" accessibilityLabel={preview ? 'Change screenshot' : 'Add screenshot'}>
            <Card
              style={{
                padding: 0,
                overflow: 'hidden',
                aspectRatio: 3 / 2,
                alignItems: 'center',
                justifyContent: 'center',
                borderStyle: preview ? 'solid' : 'dashed',
                borderColor: errors.image ? c.red : c.borderStrong,
              }}>
              {preview ? (
                <Image source={preview} style={{ width: '100%', height: '100%' }} contentFit="cover" />
              ) : (
                <T v="label" color={c.green}>
                  + Choose an image
                </T>
              )}
            </Card>
          </Pressable>
          {preview ? <Button title="Change image" kind="ghost" small onPress={pick} style={{ alignSelf: 'flex-start', borderRadius: Radius.pill }} /> : null}
          {errors.image ? <T v="small" color={c.red}>{errors.image}</T> : <T v="small">JPG, PNG or WebP, up to 5 MB.</T>}
        </View>

        <Field label="Title" value={form.title} onChangeText={set('title')} error={errors.title} maxLength={120} />
        <ChipPicker
          label="Category"
          options={Object.values(categories)}
          value={categories[form.category] ?? ''}
          onChange={label => set('category')(Object.keys(categories).find(k => categories[k] === label) ?? 'website')}
        />
        <Field label="Short description" hint="(optional)" value={form.summary} onChangeText={set('summary')} error={errors.summary} multiline maxLength={300} />
        <Field label="Website link" hint="(optional)" value={form.url} onChangeText={set('url')} error={errors.url} autoCapitalize="none" keyboardType="url" placeholder="https://" />
        <Field label="App Store link" hint="(optional)" value={form.ios_url} onChangeText={set('ios_url')} error={errors.ios_url} autoCapitalize="none" keyboardType="url" placeholder="https://apps.apple.com/…" />
        <Field label="Google Play link" hint="(optional)" value={form.android_url} onChangeText={set('android_url')} error={errors.android_url} autoCapitalize="none" keyboardType="url" placeholder="https://play.google.com/…" />
        <Card>
          <Toggle label="Show on the website" value={form.is_published} onChange={set('is_published')} />
        </Card>

        <Button title={isNew ? 'Add project' : 'Save changes'} onPress={save} loading={saving} />
        {!isNew ? <Button title="Delete project" kind="danger" onPress={remove} /> : null}
      </Section>
    </Screen>
  );
}
