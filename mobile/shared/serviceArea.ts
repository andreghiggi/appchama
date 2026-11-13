import type { City } from './types';

const EARTH_RADIUS_KM = 6371;
const MAX_RADIUS_KM = 50;

export function distanceKm(
  lat1: number,
  lng1: number,
  lat2: number,
  lng2: number,
): number {
  const dLat = ((lat2 - lat1) * Math.PI) / 180;
  const dLng = ((lng2 - lng1) * Math.PI) / 180;
  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos((lat1 * Math.PI) / 180) *
      Math.cos((lat2 * Math.PI) / 180) *
      Math.sin(dLng / 2) ** 2;
  return EARTH_RADIUS_KM * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export function parseCoordinate(value: string | number | null | undefined): number | null {
  if (value == null || value === '') return null;
  const normalized = String(value).trim().replace(',', '.');
  const num = Number(normalized);
  return Number.isFinite(num) ? num : null;
}

export function cityDepartureCenter(city: City): { latitude: number; longitude: number } | null {
  const lat = parseCoordinate(city.departure_lat);
  const lng = parseCoordinate(city.departure_lng);
  if (lat == null || lng == null) return null;
  if (lat === 0 && lng === 0) return null;
  return { latitude: lat, longitude: lng };
}

export function isWithinServiceArea(
  lat: number,
  lng: number,
  city: City,
  maxKm = MAX_RADIUS_KM,
): boolean {
  const center = cityDepartureCenter(city);
  if (!center) return true;
  return distanceKm(lat, lng, center.latitude, center.longitude) <= maxKm;
}

export function resolvePickupPoint(
  gps: { latitude: number; longitude: number },
  city: City,
): {
  pickup: { latitude: number; longitude: number };
  usedCityCenter: boolean;
} {
  const center = cityDepartureCenter(city);
  if (!center || isWithinServiceArea(gps.latitude, gps.longitude, city)) {
    return { pickup: gps, usedCityCenter: false };
  }
  return { pickup: center, usedCityCenter: true };
}
