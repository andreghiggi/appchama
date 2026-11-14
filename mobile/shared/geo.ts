export type Ponto = { latitude: number; longitude: number };

/** Distância aproximada em metros entre dois pontos. */
export function distanciaMetros(a: Ponto, b: Ponto): number {
  const R = 6371000;
  const rad = (g: number) => (g * Math.PI) / 180;
  const dLat = rad(b.latitude - a.latitude);
  const dLng = rad(b.longitude - a.longitude);
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.latitude)) * Math.cos(rad(b.latitude)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

/** Rumo em graus (0 = norte) de `de` para `para`; null se quase não saiu do lugar (evita o carro "tremer"). */
export function bearingBetween(de: Ponto, para: Ponto): number | null {
  if (distanciaMetros(de, para) < 3) return null;
  const rad = (g: number) => (g * Math.PI) / 180;
  const y = Math.sin(rad(para.longitude - de.longitude)) * Math.cos(rad(para.latitude));
  const x =
    Math.cos(rad(de.latitude)) * Math.sin(rad(para.latitude)) -
    Math.sin(rad(de.latitude)) * Math.cos(rad(para.latitude)) * Math.cos(rad(para.longitude - de.longitude));
  return ((Math.atan2(y, x) * 180) / Math.PI + 360) % 360;
}
