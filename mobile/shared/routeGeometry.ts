import type { LatLng } from './coords';

const EARTH_RADIUS_M = 6371000;
const SNAP_MIN_ROUTE_POINTS = 3;
const SNAP_MIN_DISTANCE_M = 30;

function toRad(deg: number): number {
  return (deg * Math.PI) / 180;
}

function haversineM(a: LatLng, b: LatLng): number {
  const dLat = toRad(b.latitude - a.latitude);
  const dLng = toRad(b.longitude - a.longitude);
  const lat1 = toRad(a.latitude);
  const lat2 = toRad(b.latitude);
  const h =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
  return EARTH_RADIUS_M * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

export type NearestOnRoute = {
  point: LatLng;
  index: number;
  distanceM: number;
};

/** Projeta um ponto GPS no segmento mais próximo da polyline. */
export function nearestPointOnRoute(point: LatLng, route: LatLng[]): NearestOnRoute | null {
  if (route.length < 2) return null;

  let best: NearestOnRoute | null = null;

  for (let i = 0; i < route.length - 1; i++) {
    const a = route[i];
    const b = route[i + 1];
    const projected = projectOnSegment(point, a, b);
    const distanceM = haversineM(point, projected);

    if (!best || distanceM < best.distanceM) {
      best = { point: projected, index: i, distanceM };
    }
  }

  return best;
}

function projectOnSegment(p: LatLng, a: LatLng, b: LatLng): LatLng {
  const ax = a.longitude;
  const ay = a.latitude;
  const bx = b.longitude;
  const by = b.latitude;
  const px = p.longitude;
  const py = p.latitude;

  const abx = bx - ax;
  const aby = by - ay;
  const apx = px - ax;
  const apy = py - ay;
  const abLenSq = abx * abx + aby * aby;

  if (abLenSq === 0) return { latitude: ay, longitude: ax };

  let t = (apx * abx + apy * aby) / abLenSq;
  t = Math.max(0, Math.min(1, t));

  return {
    latitude: ay + aby * t,
    longitude: ax + abx * t,
  };
}

/** Alinha posição do motorista à rota quando há polyline suficiente. */
export function snapDriverToRoute(
  gps: LatLng | null,
  route: LatLng[],
): LatLng | null {
  if (!gps || route.length < SNAP_MIN_ROUTE_POINTS) return gps;

  const nearest = nearestPointOnRoute(gps, route);
  if (!nearest) return gps;

  if (nearest.distanceM <= SNAP_MIN_DISTANCE_M) {
    return gps;
  }

  return nearest.point;
}

/** Direção em graus (0 = norte) ao longo da rota no segmento informado. */
export function bearingOnRoute(route: LatLng[], segmentIndex: number): number {
  const a = route[Math.max(0, segmentIndex)];
  const b = route[Math.min(route.length - 1, segmentIndex + 1)];

  const lat1 = toRad(a.latitude);
  const lat2 = toRad(b.latitude);
  const dLng = toRad(b.longitude - a.longitude);

  const y = Math.sin(dLng) * Math.cos(lat2);
  const x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);

  return ((Math.atan2(y, x) * 180) / Math.PI + 360) % 360;
}
