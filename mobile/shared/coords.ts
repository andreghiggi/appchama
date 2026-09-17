export type LatLng = { latitude: number; longitude: number };

/**
 * A API serializa colunas decimais como string (ex.: "-28.8436000"), então todo
 * valor numérico vindo dela precisa ser normalizado antes de virar coordenada.
 */
export function toNumber(value: unknown, fallback = Number.NaN): number {
  if (typeof value === 'number') return Number.isFinite(value) ? value : fallback;
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
  }
  return fallback;
}

export function isValidLatitude(value: number): boolean {
  return Number.isFinite(value) && Math.abs(value) <= 90;
}

export function isValidLongitude(value: number): boolean {
  return Number.isFinite(value) && Math.abs(value) <= 180;
}

/** @deprecated use isValidLatitude/isValidLongitude */
export function isValidCoord(value: number): boolean {
  return Number.isFinite(value) && Math.abs(value) <= 180;
}

export function toLatLng(value: unknown): LatLng | null {
  if (value == null) return null;

  if (Array.isArray(value) && value.length >= 2) {
    const lng = toNumber(value[0]);
    const lat = toNumber(value[1]);
    if (isValidLatitude(lat) && isValidLongitude(lng)) {
      return { latitude: lat, longitude: lng };
    }
    return null;
  }

  if (typeof value !== 'object') return null;

  const record = value as {
    latitude?: unknown;
    longitude?: unknown;
    lat?: unknown;
    lng?: unknown;
  };

  const lat = toNumber(record.latitude ?? record.lat);
  const lng = toNumber(record.longitude ?? record.lng);

  if (!isValidLatitude(lat) || !isValidLongitude(lng)) return null;

  return { latitude: lat, longitude: lng };
}

const SP_FALLBACK = { latitude: -23.5505, longitude: -46.6333 };

export type ResolvedCoords = {
  lat: number;
  lng: number;
  destLat: number;
  destLng: number;
  usingFallback: boolean;
};

/** Resolve coords da corrida; sinaliza quando a API não enviou coordenadas válidas. */
export function resolveRideCoords(ride: {
  origin_lat?: unknown;
  origin_lng?: unknown;
  destination_lat?: unknown;
  destination_lng?: unknown;
}): ResolvedCoords {
  const origin = toLatLng({ latitude: ride.origin_lat, longitude: ride.origin_lng });
  const usingFallback = origin === null;

  const lat = origin?.latitude ?? SP_FALLBACK.latitude;
  const lng = origin?.longitude ?? SP_FALLBACK.longitude;

  const dest = toLatLng({ latitude: ride.destination_lat, longitude: ride.destination_lng });
  const destLat = dest?.latitude ?? lat + 0.02;
  const destLng = dest?.longitude ?? lng + 0.02;

  return { lat, lng, destLat, destLng, usingFallback };
}

export function toLatLngList(values: unknown): LatLng[] {
  if (!Array.isArray(values)) return [];

  const result: LatLng[] = [];

  for (const item of values) {
    const point = toLatLng(item);
    if (point) result.push(point);
  }

  return result;
}
