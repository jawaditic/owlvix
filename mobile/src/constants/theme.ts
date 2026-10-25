/** Owlvix colours, matching the website's light and dark themes (public/css/theme.css). */

import { useColorScheme } from 'react-native';

export const Colors = {
  light: {
    bg: '#f5f7fb',
    bgAlt: '#eef2f7',
    card: '#ffffff',
    border: '#dce3ec',
    borderStrong: '#c5cfdc',
    text: '#0f172a',
    text2: '#334155',
    muted: '#556479',
    faint: '#7b889c',
    green: '#15803d',
    greenSoft: '#dcfce7',
    greenInk: '#166534',
    onGreen: '#ffffff',
    blue: '#2563eb',
    blueSoft: '#dbeafe',
    red: '#dc2626',
    redSoft: '#fee2e2',
    amber: '#d97706',
    field: '#ffffff',
    series1: '#15803d',
    series2: '#2563eb',
  },
  dark: {
    bg: '#070b14',
    bgAlt: '#0a1120',
    card: '#121a2e',
    border: '#243049',
    borderStrong: '#33415e',
    text: '#f1f5f9',
    text2: '#cbd5e1',
    muted: '#94a3b8',
    faint: '#64748b',
    green: '#1ec773',
    greenSoft: '#0f2a1f',
    greenInk: '#86efac',
    onGreen: '#04130b',
    blue: '#4f8cff',
    blueSoft: '#13213d',
    red: '#f87171',
    redSoft: '#3a1414',
    amber: '#fbbf24',
    field: '#0b1222',
    series1: '#1aae68',
    series2: '#4f8cff',
  },
} as const;

export type Palette = { [K in keyof typeof Colors.light]: string };

export function useColors(): Palette {
  return Colors[useColorScheme() === 'dark' ? 'dark' : 'light'];
}

export const Space = { xs: 4, sm: 8, md: 12, lg: 16, xl: 24, xxl: 32 } as const;
export const Radius = { sm: 10, md: 14, lg: 20, pill: 999 } as const;
export const MaxWidth = 720;
