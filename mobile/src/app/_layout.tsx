import { DarkTheme, DefaultTheme, Stack, ThemeProvider, router } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { useColorScheme } from 'react-native';

import { Colors } from '@/constants/theme';
import { AuthProvider, useAuth } from '@/lib/auth';
import { onEnquiryTapped } from '@/lib/push';
import { SiteProvider } from '@/lib/site';

SplashScreen.preventAutoHideAsync();

function Navigator() {
  const scheme = useColorScheme() === 'dark' ? 'dark' : 'light';
  const c = Colors[scheme];
  const { ready, token } = useAuth();

  useEffect(() => {
    if (ready) SplashScreen.hideAsync();
  }, [ready]);

  // Tapping a "New enquiry" alert opens that enquiry
  useEffect(() => {
    if (!ready || !token) return;
    return onEnquiryTapped(id => router.push(`/admin/messages/${id}`));
  }, [ready, token]);

  const base = scheme === 'dark' ? DarkTheme : DefaultTheme;
  const theme = { ...base, colors: { ...base.colors, primary: c.green, background: c.bg, card: c.card, text: c.text, border: c.border } };

  if (!ready) return null;

  return (
    <ThemeProvider value={theme}>
      <StatusBar style={scheme === 'dark' ? 'light' : 'dark'} />
      <Stack
        screenOptions={{
          headerStyle: { backgroundColor: c.card },
          headerTintColor: c.green,
          headerTitleStyle: { color: c.text, fontWeight: '700' },
          headerShadowVisible: false,
          headerBackButtonDisplayMode: 'minimal',
          contentStyle: { backgroundColor: c.bg },
        }}>
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen name="thanks" options={{ presentation: 'modal', headerShown: false }} />
        <Stack.Screen name="admin" options={{ headerShown: false }} />
      </Stack>
    </ThemeProvider>
  );
}

export default function RootLayout() {
  return (
    <AuthProvider>
      <SiteProvider>
        <Navigator />
      </SiteProvider>
    </AuthProvider>
  );
}
