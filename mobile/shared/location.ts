import { Platform } from 'react-native';
import * as Location from 'expo-location';

export type Coords = { latitude: number; longitude: number };

// No app de celular (React Native) "window" e "navigator" também existem, mas sem geolocation:
// só é navegador de verdade quando a plataforma é 'web'.
function isWeb(): boolean {
  return Platform.OS === 'web' && typeof navigator !== 'undefined';
}

function webGeolocation(): Promise<Coords> {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('Geolocalização não suportada neste navegador.'));
      return;
    }

    const timeout = setTimeout(() => {
      reject(new Error('GPS demorou mais de 15s. Toque em ◎ para tentar de novo.'));
    }, 15000);

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        clearTimeout(timeout);
        resolve({
          latitude: pos.coords.latitude,
          longitude: pos.coords.longitude,
        });
      },
      (err) => {
        clearTimeout(timeout);
        reject(new Error(err.message || 'Não foi possível obter sua localização.'));
      },
      { enableHighAccuracy: true, timeout: 14000, maximumAge: 0 },
    );
  });
}

export async function requestCurrentLocation(): Promise<Coords> {
  if (isWeb()) {
    return webGeolocation();
  }

  const { status } = await Location.requestForegroundPermissionsAsync();
  if (status !== 'granted') {
    throw new Error('Permissão de localização negada. Ative nas configurações do dispositivo.');
  }

  const loc = await Location.getCurrentPositionAsync({
    accuracy: Location.Accuracy.High,
  });

  return {
    latitude: loc.coords.latitude,
    longitude: loc.coords.longitude,
  };
}

export function startLocationWatch(
  onUpdate: (coords: Coords) => void,
  intervalMs = 8000,
): () => void {
  if (isWeb() && navigator.geolocation) {
    let active = true;
    const watchId = navigator.geolocation.watchPosition(
      (pos) => {
        if (!active) return;
        onUpdate({
          latitude: pos.coords.latitude,
          longitude: pos.coords.longitude,
        });
      },
      () => {
        /* ignore transient GPS errors */
      },
      { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 },
    );

    return () => {
      active = false;
      navigator.geolocation.clearWatch(watchId);
    };
  }

  let active = true;

  const tick = async () => {
    if (!active) return;
    try {
      const coords = await requestCurrentLocation();
      if (active) onUpdate(coords);
    } catch {
      /* ignore transient GPS errors */
    }
  };

  tick();
  const id = setInterval(tick, intervalMs);

  return () => {
    active = false;
    clearInterval(id);
  };
}
