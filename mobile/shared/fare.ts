import { toNumber } from './coords';
import type { City } from './types';

function haversineKm(
  lat1: number,
  lng1: number,
  lat2: number,
  lng2: number,
): number {
  const R = 6371;
  const dLat = ((lat2 - lat1) * Math.PI) / 180;
  const dLng = ((lng2 - lng1) * Math.PI) / 180;
  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos((lat1 * Math.PI) / 180) *
      Math.cos((lat2 * Math.PI) / 180) *
      Math.sin(dLng / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export function estimateFare(
  city: Pick<City, 'base_fare' | 'price_per_km' | 'price_per_min' | 'min_fare'>,
  origin: { latitude: number; longitude: number },
  destination: { latitude: number; longitude: number },
): { distanceKm: number; durationMin: number; fare: number } {
  const distanceKm = haversineKm(
    origin.latitude,
    origin.longitude,
    destination.latitude,
    destination.longitude,
  );
  const durationMin = Math.max(1, Math.round(distanceKm / 0.5));
  const raw =
    Number(city.base_fare) +
    distanceKm * Number(city.price_per_km) +
    durationMin * Number(city.price_per_min);
  const fare = Math.max(raw, Number(city.min_fare));

  return {
    distanceKm: Math.round(distanceKm * 100) / 100,
    durationMin,
    fare: Math.round(fare * 100) / 100,
  };
}

export function formatBrl(value: number | string | null | undefined): string {
  const amount = toNumber(value, 0);
  return `R$ ${amount.toFixed(2).replace('.', ',')}`;
}
