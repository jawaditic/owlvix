/** Load data from the API for a screen, with pull-to-refresh and reload when the screen comes back into view. */

import { useFocusEffect } from 'expo-router';
import { useCallback, useRef, useState } from 'react';

import { api, ApiError } from './api';

export function useApi<T>(path: string | null) {
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const latest = useRef(0);

  const load = useCallback(
    async (mode: 'first' | 'refresh' | 'quiet' = 'quiet') => {
      if (!path) return;
      const call = ++latest.current;
      if (mode === 'refresh') setRefreshing(true);
      try {
        const res = await api<T>(path);
        if (call === latest.current) {
          setData(res);
          setError(null);
        }
      } catch (e) {
        if (call === latest.current) setError(e instanceof ApiError ? e.message : 'Something went wrong.');
      } finally {
        if (call === latest.current) {
          setLoading(false);
          setRefreshing(false);
        }
      }
    },
    [path],
  );

  // Load on open and quietly refresh whenever the screen is shown again (e.g. after editing)
  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  return { data, setData, error, loading: loading && !data, refreshing, refresh: () => load('refresh'), reload: () => load() };
}
