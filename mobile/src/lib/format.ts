/** Dates and numbers shown in the app. */

export function timeAgo(iso: string): string {
  const s = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
  if (s < 60) return 'just now';
  if (s < 3600) return `${Math.floor(s / 60)} min ago`;
  if (s < 86400) return `${Math.floor(s / 3600)} h ago`;
  if (s < 86400 * 7) return `${Math.floor(s / 86400)} d ago`;
  return shortDate(iso);
}

export function shortDate(iso: string): string {
  return new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

export function dateTime(iso: string): string {
  return new Date(iso).toLocaleString(undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

/** "2026-10-25" → "25 Oct" */
export function dayLabel(date: string): string {
  return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}

export function hours(h: number | null): string {
  if (h === null) return '—';
  if (h < 1) return `${Math.round(h * 60)} min`;
  if (h < 48) return `${h.toFixed(1)} h`;
  return `${Math.round(h / 24)} days`;
}
