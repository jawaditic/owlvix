import { Redirect, Stack } from 'expo-router';

import { useColors } from '@/constants/theme';
import { useAuth } from '@/lib/auth';

/** Admin screens. Logged-out visitors are sent to the Admin tab's login form. */
export default function AdminLayout() {
  const c = useColors();
  const { token } = useAuth();
  if (!token) return <Redirect href="/dashboard" />;

  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: c.card },
        headerTintColor: c.green,
        headerTitleStyle: { color: c.text, fontWeight: '700' },
        headerShadowVisible: false,
        headerBackButtonDisplayMode: 'minimal',
        contentStyle: { backgroundColor: c.bg },
      }}>
      <Stack.Screen name="messages/index" options={{ title: 'Messages' }} />
      <Stack.Screen name="messages/[id]" options={{ title: 'Message' }} />
      <Stack.Screen name="traffic" options={{ title: 'Traffic' }} />
      <Stack.Screen name="projects/index" options={{ title: 'Portfolio' }} />
      <Stack.Screen name="projects/[id]" options={{ title: 'Project' }} />
      <Stack.Screen name="reviews/index" options={{ title: 'Reviews' }} />
      <Stack.Screen name="reviews/[id]" options={{ title: 'Review' }} />
      <Stack.Screen name="reviews/settings" options={{ title: 'Rating summary', presentation: 'modal' }} />
    </Stack>
  );
}
