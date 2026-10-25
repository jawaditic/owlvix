/** New-enquiry alerts on the admin's phone (Expo push notifications). */

import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldPlaySound: true,
    shouldSetBadge: true,
    shouldShowBanner: true,
    shouldShowList: true,
  }),
});

/**
 * Ask for permission and return this phone's Expo push token, or null when alerts can't be used
 * (web, simulator without push, permission refused, or no EAS project id yet).
 */
export async function registerForPush(): Promise<string | null> {
  if (Platform.OS === 'web' || !Device.isDevice) return null;

  if (Platform.OS === 'android') {
    // Same channel id the server sends with (app/Support/Push.php)
    await Notifications.setNotificationChannelAsync('enquiries', {
      name: 'New enquiries',
      // eslint-disable-next-line import/namespace -- exported from a sub-module the lint rule doesn't follow
      importance: Notifications.AndroidImportance.MAX,
      vibrationPattern: [0, 250, 250, 250],
      lightColor: '#1ec773',
    });
  }

  let { status } = await Notifications.getPermissionsAsync();
  if (status !== 'granted') {
    status = (await Notifications.requestPermissionsAsync()).status;
  }
  if (status !== 'granted') return null;

  const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
  if (!projectId) {
    console.warn('Push alerts need an EAS project id: run `npx eas-cli@latest init` in mobile/.');
    return null;
  }

  try {
    return (await Notifications.getExpoPushTokenAsync({ projectId })).data;
  } catch (e) {
    console.warn('Could not get a push token', e);
    return null;
  }
}

/** Open the enquiry when the admin taps an alert. Returns a function that stops listening. */
export function onEnquiryTapped(open: (id: number) => void): () => void {
  const handle = (response: Notifications.NotificationResponse | null) => {
    const data = response?.notification.request.content.data as { type?: string; id?: number } | undefined;
    if (data?.type === 'enquiry' && data.id) open(Number(data.id));
  };
  // The alert that opened the app from closed
  Notifications.getLastNotificationResponseAsync().then(handle).catch(() => {});
  const sub = Notifications.addNotificationResponseReceivedListener(handle);
  return () => sub.remove();
}
