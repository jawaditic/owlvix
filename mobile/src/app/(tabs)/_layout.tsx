import { NativeTabs } from 'expo-router/native-tabs';

import { useColors } from '@/constants/theme';
import { useAuth } from '@/lib/auth';

export default function TabsLayout() {
  const c = useColors();
  const { token, unread } = useAuth();

  return (
    <NativeTabs backgroundColor={c.card} indicatorColor={c.greenSoft} tintColor={c.green} labelStyle={{ selected: { color: c.green } }}>
      <NativeTabs.Trigger name="index">
        <NativeTabs.Trigger.Label>Home</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf={{ default: 'house', selected: 'house.fill' }} md="home" />
      </NativeTabs.Trigger>

      <NativeTabs.Trigger name="work">
        <NativeTabs.Trigger.Label>Work</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf={{ default: 'square.grid.2x2', selected: 'square.grid.2x2.fill' }} md="grid_view" />
      </NativeTabs.Trigger>

      <NativeTabs.Trigger name="quote">
        <NativeTabs.Trigger.Label>Get a quote</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf={{ default: 'paperplane', selected: 'paperplane.fill' }} md="send" />
      </NativeTabs.Trigger>

      <NativeTabs.Trigger name="reviews">
        <NativeTabs.Trigger.Label>Reviews</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf={{ default: 'star', selected: 'star.fill' }} md="star" />
      </NativeTabs.Trigger>

      <NativeTabs.Trigger name="dashboard">
        <NativeTabs.Trigger.Label>Admin</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf={{ default: 'person.crop.circle', selected: 'person.crop.circle.fill' }} md="account_circle" />
        {token && unread > 0 ? <NativeTabs.Trigger.Badge>{unread > 9 ? '9+' : String(unread)}</NativeTabs.Trigger.Badge> : null}
      </NativeTabs.Trigger>
    </NativeTabs>
  );
}
