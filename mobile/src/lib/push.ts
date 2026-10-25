/** New-enquiry alerts on the admin's phone (Expo push notifications). */

import Constants, { ExecutionEnvironment } from 'expo-constants';
import * as Device from 'expo-device';
import { Platform } from 'react-native';

type NotificationsModule = typeof import('expo-notifications');

/**
 * expo-notifications is loaded only where push works. Expo Go on Android doesn't include it any more
 * and crashes as soon as it's imported, so it must not be imported at the top of this file.
 */
let cached: NotificationsModule | null | undefined;
function notifications(): NotificationsModule | null {
  if (cached !== undefined) return cached;
  const expoGoAndroid = Platform.OS === 'android' && Constants.executionEnvironment === ExecutionEnvironment.StoreClient;
  if (Platform.OS === 'web' || expoGoAndroid) return (cached = null);
  try {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const N: NotificationsModule = require('expo-notifications');
    N.setNotificationHandler({
      handleNotification: async () => ({
        shouldPlaySound: true,
        shouldSetBadge: true,
        shouldShowBanner: true,
        shouldShowList: true,
      }),
    });
    return (cached = N);
  } catch {
    return (cached = null);
  }
}

/**
 * Ask for permission and return this phone's Expo push token, or null when alerts can't be used
 * (web, Expo Go on Android, simulator without push, permission refused, or no EAS project id yet).
 */
export async function registerForPush(): Promise<string | null> {
  const N = notifications();
  if (!N || !Device.isDevice) return null;

  if (Platform.OS === 'android') {
    // Same channel id the server sends with (app/Support/Push.php)
    await N.setNotificationChannelAsync('enquiries', {
      name: 'New enquiries',
      importance: N.AndroidImportance.MAX,
      vibrationPattern: [0, 250, 250, 250],
      lightColor: '#1ec773',
    });
  }

  let { status } = await N.getPermissionsAsync();
  if (status !== 'granted') {
    status = (await N.requestPermissionsAsync()).status;
  }
  if (status !== 'granted') return null;

  const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
  if (!projectId) {
    console.warn('Push alerts need an EAS project id: run `npx eas-cli@latest init` in mobile/.');
    return null;
  }

  try {
    return (await N.getExpoPushTokenAsync({ projectId })).data;
  } catch (e) {
    console.warn('Could not get a push token', e);
    return null;
  }
}

/** Open the enquiry when the admin taps an alert. Returns a function that stops listening. */
export function onEnquiryTapped(open: (id: number) => void): () => void {
  const N = notifications();
  if (!N) return () => {};
  const handle = (response: import('expo-notifications').NotificationResponse | null) => {
    const data = response?.notification.request.content.data as { type?: string; id?: number } | undefined;
    if (data?.type === 'enquiry' && data.id) open(Number(data.id));
  };
  // The alert that opened the app from closed
  N.getLastNotificationResponseAsync().then(handle).catch(() => {});
  const sub = N.addNotificationResponseReceivedListener(handle);
  return () => sub.remove();
}
