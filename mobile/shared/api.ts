import { API_URL, TENANT_SLUG } from './theme';
import type { Ride, User, City } from './types';

let authToken: string | null = null;

export function setToken(token: string | null) {
  authToken = token;
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string>),
  };

  if (authToken) {
    headers.Authorization = `Bearer ${authToken}`;
  }

  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers,
  });

  if (!response.ok) {
    const body = await response.json().catch(() => ({}));
    throw new Error(body.message ?? `Erro ${response.status}`);
  }

  return response.json();
}

export const api = {
  sendOtp: (phone: string) =>
    request('/auth/otp/send', {
      method: 'POST',
      body: JSON.stringify({ phone, tenant_slug: TENANT_SLUG }),
    }),

  verifyOtp: (phone: string, code: string, name?: string, role?: string) =>
    request<{ token: string; user: User }>('/auth/otp/verify', {
      method: 'POST',
      body: JSON.stringify({
        phone,
        code,
        tenant_slug: TENANT_SLUG,
        name,
        role,
      }),
    }),

  me: () => request<User>('/me'),

  createRide: (payload: Record<string, unknown>) =>
    request<Ride>('/rides', { method: 'POST', body: JSON.stringify(payload) }),

  getRide: (id: string) => request<Ride>(`/rides/${id}`),

  listRides: () => request<{ data: Ride[] }>('/rides'),

  listCities: () => request<City[]>('/cities'),

  cancelRide: (id: string, reason?: string) =>
    request(`/rides/${id}/cancel`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
    }),

  rateRide: (id: string, score: number, comment?: string) =>
    request(`/rides/${id}/rate`, {
      method: 'POST',
      body: JSON.stringify({ score, comment }),
    }),

  acceptRide: (id: string) => request<Ride>(`/rides/${id}/accept`, { method: 'POST' }),
  declineRide: (id: string) => request(`/rides/${id}/decline`, { method: 'POST' }),
  arriveRide: (id: string) => request<Ride>(`/rides/${id}/arrive`, { method: 'POST' }),
  startRide: (id: string) => request<Ride>(`/rides/${id}/start`, { method: 'POST' }),
  completeRide: (id: string) => request<Ride>(`/rides/${id}/complete`, { method: 'POST' }),

  updateLocation: (lat: number, lng: number) =>
    request('/drivers/location', {
      method: 'POST',
      body: JSON.stringify({ lat, lng }),
    }),

  goOnline: () => request('/drivers/online', { method: 'POST' }),
  goOffline: () => request('/drivers/offline', { method: 'POST' }),

  driverDashboard: () =>
    request<{
      online: boolean;
      subscription_status: string;
      rides_today: number;
      earnings_today: number;
      rating_avg: number;
    }>('/drivers/dashboard'),
};
