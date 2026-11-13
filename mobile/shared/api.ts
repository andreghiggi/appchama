import { API_URL, TENANT_SLUG } from './theme';
import type { Ride, User, City, GeocodeResult, RidePaymentInfo, DriverWalletInfo, DriverWithdrawal } from './types';

let authToken: string | null = null;

export function setToken(token: string | null) {
  authToken = token;
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Tenant-Slug': TENANT_SLUG,
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
    const body = await response.json().catch(() => ({})) as {
      message?: string;
      errors?: Record<string, string[]>;
    };
    const firstError = body.errors ? Object.values(body.errors).flat()[0] : undefined;
    throw new Error(firstError ?? body.message ?? `Erro ${response.status}`);
  }

  return response.json();
}

export const api = {
  sendOtp: (phone: string) =>
    request<{ message: string; debug_code?: string; user_exists?: boolean }>('/auth/otp/send', {
      method: 'POST',
      body: JSON.stringify({ phone, tenant_slug: TENANT_SLUG }),
    }),

  verifyOtp: (
    phone: string,
    code: string,
    options?: {
      name?: string;
      email?: string;
      cpf?: string;
      role?: string;
      home_address?: string;
      home_address_number?: string;
      home_postal_code?: string;
      home_city?: string;
      home_state?: string;
      home_lat?: number;
      home_lng?: number;
    },
  ) =>
    request<{ token: string; user: User }>('/auth/otp/verify', {
      method: 'POST',
      body: JSON.stringify({
        phone,
        code,
        tenant_slug: TENANT_SLUG,
        ...options,
      }),
    }),

  updateProfile: (payload: {
    cpf: string;
    email: string;
    home_address: string;
    home_address_number: string;
    home_postal_code: string;
    home_city: string;
    home_state: string;
    home_lat?: number;
    home_lng?: number;
  }) =>
    request<User>('/profile', {
      method: 'PUT',
      body: JSON.stringify(payload),
    }),

  reverseGeocodePublic: (lat: number, lng: number) => {
    const params = new URLSearchParams({
      lat: String(lat),
      lng: String(lng),
    });
    return request<{
      label: string;
      postal_code?: string | null;
      city?: string | null;
      state?: string | null;
      address?: string | null;
    }>(`/geocode/reverse?${params}`);
  },

  driverLogin: (phone: string, registrationCode: string) =>
    request<{ token: string; user: User }>('/auth/driver/login', {
      method: 'POST',
      body: JSON.stringify({
        phone,
        registration_code: registrationCode,
        tenant_slug: TENANT_SLUG,
      }),
    }),

  driverLoginWithPassword: (phone: string, password: string) =>
    request<{ token: string; user: User }>('/auth/driver/login', {
      method: 'POST',
      body: JSON.stringify({
        phone,
        password,
        tenant_slug: TENANT_SLUG,
      }),
    }),

  driverRegister: async (formData: FormData) => {
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (authToken) headers.Authorization = `Bearer ${authToken}`;
    const response = await fetch(`${API_URL}/auth/driver/register`, {
      method: 'POST',
      headers,
      body: formData,
    });
    if (!response.ok) {
      const body = await response.json().catch(() => ({})) as {
        message?: string;
        errors?: Record<string, string[]>;
      };
      const firstError = body.errors
        ? Object.values(body.errors).flat()[0]
        : undefined;
      throw new Error(firstError ?? body.message ?? `Erro ${response.status}`);
    }
    return response.json() as Promise<{ token: string; user: User; message?: string }>;
  },

  me: () => request<User>('/me'),

  createRide: (payload: Record<string, unknown>) =>
    request<Ride>('/rides', { method: 'POST', body: JSON.stringify(payload) }),

  getRide: (id: string) => request<Ride>(`/rides/${id}`),

  getActiveRide: () => request<{ ride: Ride | null }>('/rides/active'),

  listRides: () => request<{ data: Ride[] }>('/rides'),

  listCities: () => request<City[]>('/cities'),

  nearestCity: (lat: number, lng: number, cityId?: string, select = false) => {
    const params = new URLSearchParams({
      lat: String(lat),
      lng: String(lng),
    });
    if (cityId) params.set('city_id', cityId);
    if (select) params.set('select', '1');
    return request<{
      city: City;
      distance_km: number;
      max_radius_km: number;
      within_service_area?: boolean;
      departure_center: { latitude: number; longitude: number; label: string | null };
    }>(`/cities/nearest?${params}`);
  },

  searchGeocode: (q: string, lat: number, lng: number, cityId?: string) => {
    const params = new URLSearchParams({
      q,
      lat: String(lat),
      lng: String(lng),
    });
    if (cityId) params.set('city_id', cityId);
    return request<{
      results: GeocodeResult[];
      max_radius_km?: number;
      city_id?: string;
      city_name?: string;
    }>(`/geocode/search?${params}`);
  },

  reverseGeocode: (lat: number, lng: number) => {
    const params = new URLSearchParams({
      lat: String(lat),
      lng: String(lng),
    });
    return request<{ label: string }>(`/geocode/reverse?${params}`);
  },

  estimateRide: (payload: {
    city_id: string;
    origin_lat: number;
    origin_lng: number;
    destination_lat: number;
    destination_lng: number;
  }) =>
    request<{
      distance_km: number;
      duration_min: number;
      estimated_fare: number;
      polyline: string | null;
      coordinates: Array<{ latitude: number; longitude: number }>;
    }>('/rides/estimate?' + new URLSearchParams({
      city_id: payload.city_id,
      origin_lat: String(payload.origin_lat),
      origin_lng: String(payload.origin_lng),
      destination_lat: String(payload.destination_lat),
      destination_lng: String(payload.destination_lng),
    })),

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

  getRidePayment: (id: string) => request<RidePaymentInfo>(`/rides/${id}/payment`),

  payRide: (
    id: string,
    method: 'pix' | 'credit_card',
    options?: { success_url?: string; cancel_url?: string },
  ) =>
    request<RidePaymentInfo>(`/rides/${id}/pay`, {
      method: 'POST',
      body: JSON.stringify({
        method,
        success_url: options?.success_url,
        cancel_url: options?.cancel_url,
      }),
    }),

  confirmDirectPayment: (id: string) =>
    request<Ride>(`/rides/${id}/confirm-direct-payment`, { method: 'POST' }),

  getDriverWallet: () => request<DriverWalletInfo>('/drivers/wallet'),

  updatePayoutAccount: (pixKey: string, pixKeyType: string) =>
    request('/drivers/payout-account', {
      method: 'PUT',
      body: JSON.stringify({ pix_key: pixKey, pix_key_type: pixKeyType }),
    }),

  requestWithdrawal: (amount: number) =>
    request<DriverWithdrawal>('/drivers/withdrawals', {
      method: 'POST',
      body: JSON.stringify({ amount }),
    }),

  listWithdrawals: () => request<{ data: DriverWithdrawal[] }>('/drivers/withdrawals'),

  updateLocation: (lat: number, lng: number) =>
    request('/drivers/location', {
      method: 'POST',
      body: JSON.stringify({ lat, lng }),
    }),

  goOnline: (lat?: number, lng?: number) =>
    request('/drivers/online', {
      method: 'POST',
      body: JSON.stringify(lat != null && lng != null ? { lat, lng } : {}),
    }),
  goOffline: () => request('/drivers/offline', { method: 'POST' }),

  driverDashboard: () =>
    request<{
      online: boolean;
      subscription_status: string;
      rides_today: number;
      earnings_today: number;
      balance_available: number;
      total_earnings: number;
      rating_avg: number;
    }>('/drivers/dashboard'),

  getPendingOffer: () =>
    request<{ ride: Ride | null; expires_in: number | null }>('/drivers/offers'),

  confirmRegistrationCode: (code: string) =>
    request<{ message: string; driver: User['driver'] }>('/drivers/registration-code/confirm', {
      method: 'POST',
      body: JSON.stringify({ code }),
    }),
};
