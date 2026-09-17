export type User = {
  id: string;
  name: string;
  phone: string;
  email?: string | null;
  cpf?: string | null;
  role: 'passenger' | 'driver' | 'admin' | 'support';
  status: string;
  home_address?: string | null;
  home_address_number?: string | null;
  home_postal_code?: string | null;
  home_city?: string | null;
  home_state?: string | null;
  home_lat?: ApiNumber | null;
  home_lng?: ApiNumber | null;
  driver?: DriverProfile;
};

export type DriverProfile = {
  user_id: string;
  city_id: string;
  online: boolean;
  registration_code?: string;
  registration_source?: 'admin' | 'app';
  registration_code_confirmed_at?: string | null;
  registration_validated_at?: string | null;
  background_check_status?: string;
  subscription_status: string;
  rating_avg: number;
  vehicles?: Vehicle[];
};

export type Vehicle = {
  id: string;
  plate: string;
  model: string;
  color: string;
};

/** Colunas decimais chegam como string no JSON da API. */
export type ApiNumber = number | string;

export type Ride = {
  id: string;
  status: string;
  origin_lat: ApiNumber;
  origin_lng: ApiNumber;
  origin_address?: string;
  destination_lat?: ApiNumber;
  destination_lng?: ApiNumber;
  destination_address?: string;
  estimated_fare?: ApiNumber;
  final_fare?: ApiNumber;
  driver_net_amount?: ApiNumber;
  distance_km?: ApiNumber;
  payment_status?: 'pending' | 'paid' | 'failed';
  payment_method?: 'pix' | 'credit_card' | 'direct_driver' | null;
  paid_at?: string | null;
  passenger?: User;
  driver?: {
    user_id: string;
    user?: User;
    vehicles?: Vehicle[];
    rating_avg?: ApiNumber;
    current_lat?: ApiNumber;
    current_lng?: ApiNumber;
    city?: {
      id?: string;
      departure_lat?: ApiNumber;
      departure_lng?: ApiNumber;
    };
  };
  city?: { id: string; name: string };
  driver_location?: { latitude: ApiNumber; longitude: ApiNumber };
  driver_distance_km?: ApiNumber;
  driver_eta_min?: ApiNumber;
  route_coordinates?: Array<{ latitude: ApiNumber; longitude: ApiNumber }>;
  /** Rota completa embarque → destino (visão motorista). */
  trip_route_coordinates?: Array<{ latitude: ApiNumber; longitude: ApiNumber }>;
  route_phase?: 'to_pickup' | 'to_destination' | 'full';
  route_target?: {
    latitude: ApiNumber;
    longitude: ApiNumber;
    label?: string;
  };
};

export type City = {
  id: string;
  name: string;
  state: string;
  base_fare?: string | number;
  price_per_km?: string | number;
  price_per_min?: string | number;
  min_fare?: string | number;
  departure_address?: string;
  departure_lat?: ApiNumber;
  departure_lng?: ApiNumber;
  rides_enabled?: boolean;
};

export type PaymentMethodOption = {
  id: 'pix' | 'credit_card' | 'direct_driver';
  label: string;
  description: string;
  requires_profile: boolean;
};

export type RidePaymentInfo = {
  ride_id: string;
  status: string;
  payment_status: string;
  payment_method?: string | null;
  amount: number;
  driver_net_amount?: number | null;
  payment_profile_complete?: boolean;
  methods_enabled: {
    pix?: boolean;
    credit_card?: boolean;
    direct_driver?: boolean;
  };
  payment_methods?: PaymentMethodOption[];
  pix_qr_code?: string | null;
  pix_copy_paste?: string | null;
  checkout_url?: string | null;
};

export type DriverWalletInfo = {
  balance_available: number;
  total_earnings: number;
  direct_earnings?: number;
  app_balance_earnings?: number;
  pending_pix_rides?: number;
  has_processing_withdrawal?: boolean;
  withdraw_hint?: string | null;
  payout_pix_key?: string | null;
  payout_pix_key_type?: string | null;
};

export type DriverWithdrawal = {
  id: string;
  amount: number | string;
  status: string;
  pix_key: string;
  created_at: string;
  fail_reason?: string | null;
};

export type GeocodeResult = {
  latitude: number;
  longitude: number;
  label: string;
};
