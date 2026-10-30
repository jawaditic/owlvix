import { Image } from 'expo-image';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Pressable, Text, View } from 'react-native';

import { ProjectCard, ReviewCard } from '@/components/site';
import { Badge, Button, Card, Row, Screen, Section, T, wrapStyle } from '@/components/ui';
import { Radius, Space, useColors } from '@/constants/theme';
import { Faq, Hero, Plans, Services, Steps, Strip } from '@/lib/content';
import { useSite } from '@/lib/site';

const quote = (service?: string) => router.navigate({ pathname: '/quote', params: service ? { service } : {} });

export default function Home() {
  const c = useColors();
  const { data, refreshing, refresh } = useSite();
  const [word, setWord] = useState(0);
  const [openFaq, setOpenFaq] = useState<number | null>(null);

  // Cycle the highlighted word like the website headline
  useEffect(() => {
    const t = setInterval(() => setWord(w => (w + 1) % Hero.words.length), 2800);
    return () => clearInterval(t);
  }, []);

  return (
    <Screen refreshing={refreshing} onRefresh={refresh}>
      {/* Brand */}
      <Row gap={10}>
        <Image source={require('@/assets/images/icon.png')} style={{ width: 34, height: 34, borderRadius: 9 }} />
        <Text style={{ fontSize: 22, fontWeight: '800', color: c.text }}>
          Owl<Text style={{ color: c.green }}>vix</Text>
        </Text>
      </Row>

      {/* Hero */}
      <View style={{ gap: Space.lg, marginTop: Space.xl }}>
        <Badge label={Hero.pill} />
        <T v="h1" accessibilityRole="header">
          Need {/^[aeiou]/i.test(Hero.words[word]) ? 'an' : 'a'} <Text style={{ color: c.green }}>{Hero.words[word]}</Text>
          {'\n'}We build it start to finish.
        </T>
        <T v="body">{Hero.lead}</T>

        <Card style={{ flexDirection: 'row', gap: Space.md, alignItems: 'center', backgroundColor: c.bgAlt }}>
          <Image source={require('@/assets/images/owl.png')} style={{ width: 64, height: 68 }} contentFit="contain" accessibilityLabel="Owlvix owl mascot" />
          <View style={{ flex: 1, gap: 4 }}>
            <Row gap={6}>
              <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: c.green }} />
              <T v="small">Online · replies within 24h</T>
            </Row>
            <T v="label" style={{ fontWeight: '500' }}>{Hero.owl}</T>
          </View>
        </Card>

        <Button title="Get a free quote  →" onPress={() => quote()} />
        <Button title="See what we build" kind="ghost" onPress={() => router.navigate('/work')} />
        <Row gap={Space.lg} style={{ flexWrap: 'wrap', justifyContent: 'center' }}>
          {Hero.trust.map(t => (
            <T key={t} v="small">
              <Text style={{ color: c.green }}>✓ </Text>
              {t}
            </T>
          ))}
        </Row>
      </View>

      {/* Trust strip */}
      <View style={[wrapStyle, { marginTop: Space.xl }]}>
        {Strip.map(s => (
          <Card key={s.label} style={{ flexGrow: 1, flexBasis: '45%', alignItems: 'center', paddingVertical: Space.md }}>
            <Text style={{ fontSize: 26, fontWeight: '800', color: c.green }}>{s.value}</Text>
            <T v="small" style={{ textAlign: 'center' }}>{s.label}</T>
          </Card>
        ))}
      </View>

      <Section eyebrow="What we build" title="Software that works for your business">
        {Services.map(s => (
          <Card key={s.title} style={{ flexDirection: 'row', gap: Space.md }}>
            <Text style={{ fontSize: 24 }}>{s.icon}</Text>
            <View style={{ flex: 1, gap: 4 }}>
              <T v="h3">{s.title}</T>
              <T v="small">{s.text}</T>
            </View>
          </Card>
        ))}
      </Section>

      {data?.projects.length ? (
        <Section
          eyebrow="Our work"
          title="Projects we've built"
          action={<Button title="All" kind="ghost" small onPress={() => router.navigate('/work')} />}>
          {data.projects.slice(0, 3).map(p => (
            <ProjectCard key={p.id} p={p} />
          ))}
        </Section>
      ) : null}

      <Section eyebrow="How it works" title="From idea to launch in 3 steps">
        {Steps.map((s, i) => (
          <Card key={s.title} style={{ flexDirection: 'row', gap: Space.md }}>
            <View style={{ width: 34, height: 34, borderRadius: Radius.pill, backgroundColor: c.greenSoft, alignItems: 'center', justifyContent: 'center' }}>
              <Text style={{ color: c.greenInk, fontWeight: '800' }}>{i + 1}</Text>
            </View>
            <View style={{ flex: 1, gap: 4 }}>
              <T v="h3">{s.title}</T>
              <T v="small">{s.text}</T>
            </View>
          </Card>
        ))}
      </Section>

      {data?.reviews.items.length ? (
        <Section
          eyebrow="Client reviews"
          title="What clients say"
          action={<Button title="All" kind="ghost" small onPress={() => router.navigate('/reviews')} />}>
          <ReviewCard r={data.reviews.items[0]} />
        </Section>
      ) : null}

      <Section eyebrow="Pricing" title="Start free. Pay as you approve.">
        {Plans.map(p => (
          <Card key={p.title} style={{ gap: Space.md, borderColor: p.featured ? c.green : c.border, borderWidth: p.featured ? 2 : 1 }}>
            {p.featured ? <Badge label="Start free" /> : null}
            <T v="h3">{p.title}</T>
            <Text style={{ fontSize: 22, fontWeight: '800', color: c.text }}>{p.price}</Text>
            <T v="small">{p.text}</T>
            <View style={{ gap: 6 }}>
              {p.points.map(pt => (
                <T key={pt} v="body" style={{ fontSize: 15 }}>
                  <Text style={{ color: c.green }}>✓ </Text>
                  {pt}
                </T>
              ))}
            </View>
            <Button title={p.cta} kind={p.featured ? 'primary' : 'ghost'} onPress={() => quote(p.service)} />
          </Card>
        ))}
      </Section>

      <Section eyebrow="FAQ" title="Questions people ask first">
        {Faq.map((f, i) => (
          <Card key={f.q} style={{ paddingVertical: Space.md }}>
            <Pressable
              onPress={() => setOpenFaq(openFaq === i ? null : i)}
              accessibilityRole="button"
              accessibilityState={{ expanded: openFaq === i }}
              style={{ flexDirection: 'row', alignItems: 'center', gap: Space.md, minHeight: 32 }}>
              <T v="h3" style={{ flex: 1, fontSize: 16 }}>{f.q}</T>
              <Text style={{ color: c.green, fontSize: 22, fontWeight: '700' }}>{openFaq === i ? '–' : '+'}</Text>
            </Pressable>
            {openFaq === i ? <T v="small" style={{ marginTop: Space.sm, fontSize: 15, lineHeight: 21 }}>{f.a}</T> : null}
          </Card>
        ))}
      </Section>

      <Card style={{ marginTop: Space.xl, gap: Space.md, alignItems: 'center', backgroundColor: c.greenSoft, borderColor: c.green }}>
        <T v="h2" style={{ textAlign: 'center' }}>Tell us about your project</T>
        <T v="small" style={{ textAlign: 'center' }}>We reply within one business day with questions or a quote.</T>
        <Button title="Get a free quote" onPress={() => quote()} style={{ alignSelf: 'stretch' }} />
      </Card>
    </Screen>
  );
}
