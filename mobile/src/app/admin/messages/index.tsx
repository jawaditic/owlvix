import { useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, ScrollView, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { MessageRow } from '@/components/admin';
import { Button, Chip, Empty, ErrorState, Field, Loading, Row, T } from '@/components/ui';
import { MaxWidth, Space, useColors } from '@/constants/theme';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { Message, MessagePage } from '@/lib/types';

const FILTERS = [
  { key: '', label: 'All' },
  { key: 'status=unread', label: 'Unread' },
  { key: 'source=popup', label: 'Popup' },
  { key: 'source=contact', label: 'Contact form' },
  { key: 'source=app', label: 'Mobile app' },
  { key: 'source=landing', label: 'Landing page' },
  { key: 'source=seo-checker', label: 'SEO checker' },
];

export default function Messages() {
  const c = useColors();
  const insets = useSafeAreaInsets();
  const { setUnread } = useAuth();
  const [filter, setFilter] = useState('');
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');
  const [items, setItems] = useState<Message[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [state, setState] = useState<'loading' | 'ready' | 'more' | 'refreshing'>('loading');
  const [error, setError] = useState<string | null>(null);

  // Search after the admin stops typing
  useEffect(() => {
    const t = setTimeout(() => setQuery(search.trim()), 400);
    return () => clearTimeout(t);
  }, [search]);

  const load = useCallback(
    async (p: number, mode: 'loading' | 'more' | 'refreshing' | 'quiet') => {
      if (mode !== 'quiet') setState(mode);
      try {
        const qs = [filter, query && `q=${encodeURIComponent(query)}`, `page=${p}`].filter(Boolean).join('&');
        const res = await api<MessagePage>(`admin/messages?${qs}`);
        setItems(prev => (p === 1 ? res.data : [...prev, ...res.data.filter(m => !prev.some(x => x.id === m.id))]));
        setPage(res.page);
        setLastPage(res.last_page);
        setTotal(res.total);
        setUnread(res.unread);
        setError(null);
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Something went wrong.');
      } finally {
        setState('ready');
      }
    },
    [filter, query, setUnread],
  );

  // Load on open and when the filter or search changes (with a spinner); refresh quietly when
  // coming back from a message, so read/replied badges update
  const loadedKey = useRef<string | null>(null);
  useFocusEffect(
    useCallback(() => {
      const key = `${filter}|${query}`;
      load(1, loadedKey.current === key ? 'quiet' : 'loading');
      loadedKey.current = key;
    }, [load, filter, query]),
  );

  const header = (
    <View style={{ gap: Space.md, paddingTop: Space.lg, paddingBottom: Space.md }}>
      <Field label="Search" placeholder="Name, email, phone or words" value={search} onChangeText={setSearch} autoCapitalize="none" returnKeyType="search" clearButtonMode="while-editing" />
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -Space.lg }} contentContainerStyle={{ paddingHorizontal: Space.lg }}>
        <Row>
          {FILTERS.map(f => (
            <Chip key={f.key} label={f.label} active={filter === f.key} onPress={() => setFilter(f.key)} />
          ))}
        </Row>
      </ScrollView>
      {state !== 'loading' && !error ? <T v="small">{total} message{total === 1 ? '' : 's'}</T> : null}
    </View>
  );

  return (
    <FlatList
      style={{ flex: 1, backgroundColor: c.bg }}
      contentContainerStyle={{ paddingHorizontal: Space.lg, paddingBottom: insets.bottom + Space.xl, width: '100%', maxWidth: MaxWidth, alignSelf: 'center' }}
      data={state === 'loading' ? [] : items}
      keyExtractor={m => String(m.id)}
      keyboardShouldPersistTaps="handled"
      ListHeaderComponent={header}
      renderItem={({ item, index }) => (
        <View
          style={{
            backgroundColor: c.card,
            borderColor: c.border,
            borderLeftWidth: 1,
            borderRightWidth: 1,
            borderTopWidth: index === 0 ? 1 : 0,
            borderBottomWidth: index === items.length - 1 ? 1 : 0,
            borderTopLeftRadius: index === 0 ? 20 : 0,
            borderTopRightRadius: index === 0 ? 20 : 0,
            borderBottomLeftRadius: index === items.length - 1 ? 20 : 0,
            borderBottomRightRadius: index === items.length - 1 ? 20 : 0,
            overflow: 'hidden',
          }}>
          <MessageRow m={item} first={index === 0} />
        </View>
      )}
      ListEmptyComponent={
        state === 'loading' ? (
          <Loading />
        ) : error ? (
          <ErrorState message={error} onRetry={() => load(1, 'loading')} />
        ) : (
          <Empty title={filter || query ? 'No messages match' : 'No messages yet'} text={filter || query ? 'Try another filter or search.' : 'New enquiries will show up here.'} />
        )
      }
      ListFooterComponent={
        state === 'more' ? (
          <ActivityIndicator color={c.green} style={{ marginTop: Space.lg }} />
        ) : page < lastPage && items.length ? (
          <Button title="Load more" kind="ghost" small onPress={() => load(page + 1, 'more')} style={{ marginTop: Space.lg, alignSelf: 'center' }} />
        ) : undefined
      }
      onEndReached={() => state === 'ready' && page < lastPage && load(page + 1, 'more')}
      onEndReachedThreshold={0.4}
      refreshControl={<RefreshControl refreshing={state === 'refreshing'} onRefresh={() => load(1, 'refreshing')} tintColor={c.green} />}
    />
  );
}
