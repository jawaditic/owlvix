import Constants from 'expo-constants';
import { registerRootComponent } from 'expo';
import App from './App';

// Push notifications: Android needs the background handler before the app starts (push.js)
if (Constants.expoConfig?.extra?.shell?.push) {
    try { require('./push').registerBackground(); } catch {}
}

registerRootComponent(App);
