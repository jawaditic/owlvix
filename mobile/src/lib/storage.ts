/** Keeps the admin login on the phone: the device's secure keychain, or localStorage on the web build. */

import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

const web = Platform.OS === 'web';

export async function getItem(key: string): Promise<string | null> {
  if (web) {
    try {
      return localStorage.getItem(key);
    } catch {
      return null;
    }
  }
  return SecureStore.getItemAsync(key);
}

export async function setItem(key: string, value: string | null): Promise<void> {
  if (web) {
    try {
      if (value === null) localStorage.removeItem(key);
      else localStorage.setItem(key, value);
    } catch {}
    return;
  }
  if (value === null) await SecureStore.deleteItemAsync(key);
  else await SecureStore.setItemAsync(key, value);
}
