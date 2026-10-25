import { Image } from 'expo-image';
import { router, useLocalSearchParams } from 'expo-router';
import { Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Badge, Button, Card, T } from '@/components/ui';
import { Space, useColors } from '@/constants/theme';

/** Shown after a quote is sent, like the website's thank-you page. */
export default function Thanks() {
  const c = useColors();
  const insets = useSafeAreaInsets();
  const { name } = useLocalSearchParams<{ name?: string }>();

  return (
    <View style={{ flex: 1, backgroundColor: c.bg, padding: Space.xl, paddingBottom: insets.bottom + Space.xl, justifyContent: 'center', alignItems: 'center', gap: Space.lg }}>
      <Badge label="Request received" />
      <T v="h1" style={{ textAlign: 'center' }} accessibilityRole="header">
        Thanks{name ? ', ' : ''}
        <Text style={{ color: c.green }}>{name}</Text>!
      </T>
      <Card style={{ maxWidth: 400 }}>
        <T v="body" style={{ textAlign: 'center' }}>
          You’ll hear back from us within one business day. We’ve also sent a confirmation to your email. Talk soon!
        </T>
      </Card>
      <Image source={require('@/assets/images/owl.png')} style={{ width: 200, height: 210 }} contentFit="contain" accessibilityLabel="Owlvix owl" />
      <View style={{ alignSelf: 'stretch', gap: Space.md, maxWidth: 400, width: '100%', marginHorizontal: 'auto' }}>
        <Button
          title="See our work"
          onPress={() => {
            router.back();
            router.navigate('/work');
          }}
        />
        <Button title="Back to home" kind="ghost" onPress={() => { router.back(); router.navigate('/'); }} />
      </View>
    </View>
  );
}
