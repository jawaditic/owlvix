/** Talks to the Owlvix Laravel API (routes/api.php). */

export const API_URL = (process.env.EXPO_PUBLIC_API_URL ?? 'https://owlvix.com').replace(/\/$/, '');

/** An error from the API. `fields` holds validation messages, e.g. { email: 'Please enter a valid email.' }. */
export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public fields: Record<string, string> = {},
  ) {
    super(message);
  }
}

let authToken: string | null = null;
let onUnauthorized: (() => void) | null = null;

export function setAuthToken(token: string | null) {
  authToken = token;
}

/** Called when the server says the login is no longer valid (so the app can log out). */
export function setUnauthorizedHandler(fn: (() => void) | null) {
  onUnauthorized = fn;
}

type Options = { method?: string; body?: unknown; form?: FormData };

export async function api<T>(path: string, { method = 'GET', body, form }: Options = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (authToken) headers.Authorization = `Bearer ${authToken}`;
  if (body !== undefined) headers['Content-Type'] = 'application/json';

  let res: Response;
  try {
    res = await fetch(`${API_URL}/api/${path.replace(/^\//, '')}`, {
      method,
      headers,
      body: form ?? (body !== undefined ? JSON.stringify(body) : undefined),
    });
  } catch {
    throw new ApiError("Can't reach Owlvix. Check your internet connection and try again.", 0);
  }

  const data = await res.json().catch(() => ({}));
  if (res.ok) return data as T;

  if (res.status === 401 && authToken) onUnauthorized?.();
  const fields: Record<string, string> = {};
  for (const [key, msgs] of Object.entries((data?.errors ?? {}) as Record<string, string[]>)) {
    fields[key] = Array.isArray(msgs) ? msgs[0] : String(msgs);
  }
  const message =
    res.status === 429
      ? 'Too many tries. Please wait a minute and try again.'
      : (data?.message as string) || 'Something went wrong. Please try again.';
  throw new ApiError(message, res.status, fields);
}
