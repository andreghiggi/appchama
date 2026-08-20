export type User = {
  id: string;
  name: string;
  phone: string;
  role: 'passenger' | 'driver' | 'admin' | 'support';
  status: string;
  driver?: DriverProfile;
};

export type DriverProfile = {
  user_id: string;
  city_id: string;
  online: boolean;
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

export type Ride = {
  id: string;
  status: string;
  origin_lat: number;
  origin_lng: number;
  origin_address?: string;
  destination_lat?: number;
  destination_lng?: number;
  destination_address?: string;
  estimated_fare?: number;
  final_fare?: number;
  passenger?: User;
  driver?: { user_id: string; user?: User };
  city?: { id: string; name: string };
};

export type City = {
  id: string;
  name: string;
  state: string;
};
