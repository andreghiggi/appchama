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

export function isValidCoord(value: number): boolean {
  return Number.isFinite(value) && Math.abs(value) <= 180;
}

export function toLatLng(value: unknown): LatLng | null {
  if (!value || typeof value !== 'object') return null;

  const { latitude, longitude } = value as { latitude?: unknown; longitude?: unknown };
  const lat = toNumber(latitude);
  const lng = toNumber(longitude);

  if (!isValidCoord(lat) || !isValidCoord(lng)) return null;

  return { latitude: lat, longitude: lng };
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
