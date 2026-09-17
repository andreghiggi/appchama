import { api } from './api';
import type { GeocodeResult } from './types';

export type { GeocodeResult };

export async function searchAddresses(
  query: string,
  near: { latitude: number; longitude: number },
  cityId?: string,
): Promise<{
  results: GeocodeResult[];
  cityId?: string;
  cityName?: string;
  maxRadiusKm?: number;
  filteredCount?: number;
  mapboxCount?: number;
}> {
  const q = query.trim();
  if (q.length < 3) return { results: [] };

  try {
    const response = await api.searchGeocode(q, near.latitude, near.longitude, cityId);
    return {
      results: response.results,
      cityId: response.city_id,
      cityName: response.city_name,
      maxRadiusKm: response.max_radius_km,
      filteredCount: response.filtered_count,
      mapboxCount: response.mapbox_count,
    };
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : 'Falha ao buscar endereço';
    if (msg.includes('503') || msg.includes('indisponível') || msg.includes('Geocoding')) {
      throw new Error('Busca de endereços indisponível no momento. Tente novamente em instantes.');
    }
    if (
      msg.includes('partida') ||
      msg.includes('área') ||
      msg.includes('operacao') ||
      msg.includes('operação') ||
      msg.includes('Configure o endereço')
    ) {
      throw new Error(msg);
    }
    throw e instanceof Error ? e : new Error(msg);
  }
}

export async function geocodeAddress(
  query: string,
  near: { latitude: number; longitude: number },
  cityId?: string,
): Promise<GeocodeResult | null> {
  const { results } = await searchAddresses(query, near, cityId);
  return results[0] ?? null;
}

export async function reverseGeocode(lat: number, lng: number): Promise<string> {
  try {
    const { label } = await api.reverseGeocode(lat, lng);
    return label;
  } catch {
    return 'Local de embarque';
  }
}

/** Pontos intermediários para fallback quando a API não retorna rota. */
export function buildRoutePoints(
  origin: { latitude: number; longitude: number },
  destination: { latitude: number; longitude: number },
  steps = 24,
): Array<{ latitude: number; longitude: number }> {
  const points: Array<{ latitude: number; longitude: number }> = [];
  for (let i = 0; i <= steps; i++) {
    const t = i / steps;
    points.push({
      latitude: origin.latitude + (destination.latitude - origin.latitude) * t,
      longitude: origin.longitude + (destination.longitude - origin.longitude) * t,
    });
  }
  return points;
}

export function boundsForPoints(
  points: Array<{ latitude: number; longitude: number }>,
): { latitude: number; longitude: number; latitudeDelta: number; longitudeDelta: number } {
  const lats = points.map((p) => p.latitude);
  const lngs = points.map((p) => p.longitude);
  const minLat = Math.min(...lats);
  const maxLat = Math.max(...lats);
  const minLng = Math.min(...lngs);
  const maxLng = Math.max(...lngs);
  const latitudeDelta = Math.max((maxLat - minLat) * 1.6, 0.02);
  const longitudeDelta = Math.max((maxLng - minLng) * 1.6, 0.02);
  return {
    latitude: (minLat + maxLat) / 2,
    longitude: (minLng + maxLng) / 2,
    latitudeDelta,
    longitudeDelta,
  };
}
