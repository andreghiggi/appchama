import { useEffect, useRef, useState } from 'react';
import type { LatLng } from './coords';

/**
 * Interpola suavemente entre posições GPS para o marcador do motorista
 * parecer em movimento no mapa (em vez de "pular" a cada poll).
 */
export function useAnimatedLocation(target: LatLng | null, durationMs = 800): LatLng | null {
  const [display, setDisplay] = useState<LatLng | null>(target);
  const fromRef = useRef<LatLng | null>(target);
  const animRef = useRef<number | null>(null);

  useEffect(() => {
    if (!target) {
      setDisplay(null);
      fromRef.current = null;
      return;
    }

    const from = fromRef.current ?? target;

    if (
      from.latitude === target.latitude &&
      from.longitude === target.longitude
    ) {
      setDisplay(target);
      return;
    }

    const start = typeof performance !== 'undefined' ? performance.now() : Date.now();

    const step = (now: number) => {
      const t = Math.min(1, (now - start) / durationMs);
      const eased = t * (2 - t);
      setDisplay({
        latitude: from.latitude + (target.latitude - from.latitude) * eased,
        longitude: from.longitude + (target.longitude - from.longitude) * eased,
      });

      if (t < 1) {
        animRef.current = requestAnimationFrame(step);
      } else {
        fromRef.current = target;
      }
    };

    if (animRef.current !== null) {
      cancelAnimationFrame(animRef.current);
    }
    animRef.current = requestAnimationFrame(step);

    return () => {
      if (animRef.current !== null) {
        cancelAnimationFrame(animRef.current);
        animRef.current = null;
      }
    };
  }, [target?.latitude, target?.longitude, durationMs]);

  return display;
}
