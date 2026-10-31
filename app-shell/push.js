// Push notifications from the app's owner (sent from the Owlvix builder through Firebase Cloud Messaging). Only used
// when the build has the app's Firebase config (shell.push): phones subscribe to the app's topic, so no device ID is
// sent to Owlvix. Notifications arriving while the app is closed are shown by Android itself; tapping one opens the
// app on the page the owner chose (data.tab).
import { PermissionsAndroid, Platform } from 'react-native';

const lib = () => require('@react-native-firebase/messaging'); // loaded only when push is on

/** Called once at the top level (index.js): Android needs a handler for messages that arrive in the background. */
export function registerBackground() {
    const { getMessaging, setBackgroundMessageHandler } = lib();
    setBackgroundMessageHandler(getMessaging(), async () => {});
}

/**
 * Asks for permission (Android 13+), subscribes to the app's topic and listens.
 * onOpen(data) runs when a notification is tapped; onShow(message) when one arrives while the app is open.
 * Returns a function that stops listening.
 */
export function startPush(topic, onOpen, onShow) {
    const { getMessaging, subscribeToTopic, getInitialNotification, onNotificationOpenedApp, onMessage } = lib();
    const m = getMessaging();
    if (Platform.OS === 'android' && Platform.Version >= 33) {
        PermissionsAndroid.request('android.permission.POST_NOTIFICATIONS').catch(() => {});
    }
    subscribeToTopic(m, topic).catch(() => {}); // tried again on the next start if offline
    getInitialNotification(m).then(msg => msg && onOpen(msg.data || {})).catch(() => {});
    const offOpen = onNotificationOpenedApp(m, msg => onOpen(msg.data || {}));
    const offMessage = onMessage(m, msg => onShow(msg));
    return () => { offOpen(); offMessage(); };
}
