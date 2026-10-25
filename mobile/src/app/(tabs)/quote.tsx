import * as Haptics from 'expo-haptics';
import { router, useLocalSearchParams } from 'expo-router';
import { useRef, useState } from 'react';
import { Platform, type TextInputInstance } from 'react-native';

import { Button, ChipPicker, Field, Notice, Screen, Section, T } from '@/components/ui';
import { Space } from '@/constants/theme';
import { api, ApiError } from '@/lib/api';
import { FormDefaults } from '@/lib/content';
import { useSite } from '@/lib/site';

type Form = { name: string; email: string; phone: string; service: string; budget: string; message: string };
type Errors = Partial<Record<keyof Form, string>>;

const empty: Form = { name: '', email: '', phone: '', service: 'Web app', budget: 'Not sure yet', message: '' };

/** The same checks as the website form (public/js/site.js), so most mistakes show before sending. */
function check(f: Form): Errors {
  const e: Errors = {};
  const name = f.name.trim();
  if (!name) e.name = 'Please enter your name.';
  else if (name.length < 2) e.name = 'Name must be at least 2 characters.';
  const email = f.email.trim();
  if (!email) e.email = 'Please enter your email.';
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) e.email = 'Please enter a valid email, like name@example.com.';
  const phone = f.phone.trim();
  if (phone) {
    const digits = phone.replace(/\D/g, '').length;
    if (!/^\+?[\d\s().-]+$/.test(phone)) e.phone = 'Use only digits, spaces, + ( ) . or -.';
    else if (digits < 7 || digits > 15) e.phone = 'Please enter a valid phone number (7–15 digits), with country code.';
  }
  const msg = f.message.trim();
  if (!msg) e.message = 'Please tell us a little about your project.';
  else if (msg.length < 10) e.message = `A few more words please (${10 - msg.length} more).`;
  else if (msg.length > 5000) e.message = 'Please keep it under 5000 characters.';
  return e;
}

export default function Quote() {
  const { data } = useSite();
  const params = useLocalSearchParams<{ service?: string }>();
  const [form, setForm] = useState<Form>(() => ({ ...empty, service: params.service ?? empty.service }));
  const [errors, setErrors] = useState<Errors>({});
  const [notice, setNotice] = useState<string | null>(null);
  const [sending, setSending] = useState(false);
  const nameRef = useRef<TextInputInstance>(null);
  const emailRef = useRef<TextInputInstance>(null);
  const phoneRef = useRef<TextInputInstance>(null);
  const messageRef = useRef<TextInputInstance>(null);

  const services = data?.form.services ?? FormDefaults.services;
  const budgets = data?.form.budgets ?? FormDefaults.budgets;

  // "Get a quote" on a pricing plan opens this tab with that plan picked
  const [planParam, setPlanParam] = useState(params.service);
  if (params.service !== planParam) {
    setPlanParam(params.service);
    if (params.service) setForm(f => ({ ...f, service: params.service! }));
  }

  const set = (key: keyof Form) => (value: string) => {
    setForm(f => ({ ...f, [key]: value }));
    if (errors[key]) setErrors(e => ({ ...e, [key]: undefined }));
  };

  const send = async () => {
    const found = check(form);
    setErrors(found);
    setNotice(null);
    const first = (['name', 'email', 'phone', 'message'] as const).find(k => found[k]);
    if (first) {
      ({ name: nameRef, email: emailRef, phone: phoneRef, message: messageRef })[first].current?.focus();
      return;
    }

    setSending(true);
    try {
      const res = await api<{ first_name: string }>('contact', {
        method: 'POST',
        body: { ...form, name: form.name.trim(), email: form.email.trim(), phone: form.phone.trim() || null },
      });
      if (Platform.OS !== 'web') Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
      setForm(empty);
      router.push({ pathname: '/thanks', params: { name: res.first_name } });
    } catch (e) {
      if (e instanceof ApiError) {
        setErrors(e.fields as Errors);
        setNotice(Object.keys(e.fields).length ? 'Please check the highlighted fields.' : e.message);
      } else {
        setNotice('Something went wrong. Please try again.');
      }
    } finally {
      setSending(false);
    }
  };

  return (
    <Screen>
      <T v="eyebrow">Free quote</T>
      <T v="h1" accessibilityRole="header">Get a free quote</T>
      <T v="body" style={{ marginTop: Space.sm }}>
        Takes 30 seconds. No obligation. We reply within one business day.
      </T>

      <Section>
        {notice ? <Notice text={notice} /> : null}
        <Field
          ref={nameRef}
          label="Name"
          value={form.name}
          onChangeText={set('name')}
          error={errors.name}
          autoComplete="name"
          textContentType="name"
          returnKeyType="next"
          onSubmitEditing={() => emailRef.current?.focus()}
          maxLength={100}
        />
        <Field
          ref={emailRef}
          label="Email"
          value={form.email}
          onChangeText={set('email')}
          error={errors.email}
          autoComplete="email"
          textContentType="emailAddress"
          keyboardType="email-address"
          autoCapitalize="none"
          returnKeyType="next"
          onSubmitEditing={() => phoneRef.current?.focus()}
          maxLength={150}
        />
        <Field
          ref={phoneRef}
          label="Phone"
          hint="(optional, with country code)"
          placeholder="+92 301 2345678"
          value={form.phone}
          onChangeText={set('phone')}
          error={errors.phone}
          autoComplete="tel"
          textContentType="telephoneNumber"
          keyboardType="phone-pad"
          maxLength={30}
        />
        <ChipPicker label="What do you need?" options={services} value={form.service} onChange={set('service')} />
        <ChipPicker label="Budget" options={budgets} value={form.budget} onChange={set('budget')} />
        <Field
          ref={messageRef}
          label="Your project"
          placeholder="A few lines about what you want built"
          value={form.message}
          onChangeText={set('message')}
          error={errors.message}
          multiline
          maxLength={5000}
        />
        <Button title="Get my free quote" onPress={send} loading={sending} style={{ marginTop: Space.sm }} />
        <T v="small" style={{ textAlign: 'center' }}>
          ✓ Free quote within 24h · ✓ You own 100% of the code
        </T>
      </Section>
    </Screen>
  );
}
