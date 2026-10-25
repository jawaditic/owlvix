/** Portfolio, reviews and quote form options from /api/site, shared by the customer tabs. */

import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import { api, ApiError } from './api';
import type { SiteData } from './types';

type Site = { data: SiteData | null; error: string | null; refreshing: boolean; refresh: () => Promise<void> };

const SiteContext = createContext<Site | null>(null);

export function SiteProvider({ children }: { children: ReactNode }) {
  const [data, setData] = useState<SiteData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try {
      setData(await api<SiteData>('site'));
      setError(null);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Something went wrong.');
    }
  }, []);

  // First load when the app opens (state is only set once the request answers)
  useEffect(() => {
    api<SiteData>('site')
      .then(setData)
      .catch(e => setError(e instanceof ApiError ? e.message : 'Something went wrong.'));
  }, []);

  const refresh = useCallback(async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }, [load]);

  const value = useMemo(() => ({ data, error, refreshing, refresh }), [data, error, refreshing, refresh]);
  return <SiteContext.Provider value={value}>{children}</SiteContext.Provider>;
}

export function useSite(): Site {
  const ctx = useContext(SiteContext);
  if (!ctx) throw new Error('useSite must be used inside <SiteProvider>');
  return ctx;
}
