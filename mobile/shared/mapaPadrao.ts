import Constants from 'expo-constants';

/**
 * Onde o mapa abre enquanto o GPS ainda não respondeu: o centro da cidade da marca
 * (marcas/<marca>/marca.json -> centro_mapa). Sem isso, mostra o Brasil inteiro (nunca uma cidade que não é a da marca).
 */
export function regiaoPadrao(zoom = 0.04): {
  latitude: number;
  longitude: number;
  latitudeDelta: number;
  longitudeDelta: number;
} {
  const centro = (Constants.expoConfig?.extra as { marca?: { centroMapa?: { latitude?: number; longitude?: number } } } | undefined)
    ?.marca?.centroMapa;
  if (centro && Number.isFinite(centro.latitude) && Number.isFinite(centro.longitude)) {
    return {
      latitude: Number(centro.latitude),
      longitude: Number(centro.longitude),
      latitudeDelta: zoom,
      longitudeDelta: zoom,
    };
  }
  return { latitude: -14.235, longitude: -51.925, latitudeDelta: 30, longitudeDelta: 30 };
}
