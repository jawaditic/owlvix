/** Admin login state: the token, logging in and out, and the unread count shown on the Admin tab. */

import * as Device from 'expo-device';
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import { api, setAuthToken, setUnauthorizedHandler } from './api';
import { registerForPush } from './push';
import { getItem, setItem } from './storage';

const KEY = 'owlvix-admin-token';

type Auth = {
  ready: boolean;
  token: string | null;
  unread: number;
  setUnread: (n: number) => void;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<Auth | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [ready, setReady] = useState(false);
  const [token, setToken] = useState<string | null>(null);
  const [unread, setUnread] = useState(0);

  const forget = useCallback(async () => {
    setAuthToken(null);
    setToken(null);
    setUnread(0);
    await setItem(KEY, null);
  }, []);

  // Restore the saved login when the app starts
  useEffect(() => {
    getItem(KEY)
      .then(saved => {
        setAuthToken(saved);
        setToken(saved);
      })
      .finally(() => setReady(true));
  }, []);

  // If the server rejects the token (logged out elsewhere), drop it here too
  useEffect(() => {
    setUnauthorizedHandler(() => void forget());
    return () => setUnauthorizedHandler(null);
  }, [forget]);

  // Once logged in, send this phone's push token so new enquiries show up as alerts
  useEffect(() => {
    if (!token) return;
    registerForPush()
      .then(pushToken => pushToken && api('admin/push-token', { method: 'POST', body: { push_token: pushToken } }))
      .catch(() => {});
  }, [token]);

  const login = useCallback(async (email: string, password: string) => {
    const device = [Device.manufacturer, Device.modelName].filter(Boolean).join(' ') || 'Phone';
    const res = await api<{ token: string }>('admin/login', { method: 'POST', body: { email, password, device } });
    setAuthToken(res.token);
    await setItem(KEY, res.token);
    setToken(res.token);
  }, []);

  const logout = useCallback(async () => {
    await api('admin/logout', { method: 'POST' }).catch(() => {});
    await forget();
  }, [forget]);

  const value = useMemo(() => ({ ready, token, unread, setUnread, login, logout }), [ready, token, unread, login, logout]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): Auth {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>');
  return ctx;
}
