import { useEffect, useState } from 'react';
import { api } from './api';
import type { Ride } from './types';

/**
 * Recupera do servidor a corrida em andamento ao abrir o app, para que um
 * recarregamento de página não faça o usuário perder o acompanhamento.
 */
export function useActiveRideRecovery(enabled: boolean): {
  restoredRide: Ride | null;
  restoring: boolean;
} {
  const [restoredRide, setRestoredRide] = useState<Ride | null>(null);
  const [restoring, setRestoring] = useState(enabled);

  useEffect(() => {
    if (!enabled) {
      setRestoring(false);
      return;
    }

    let alive = true;
    setRestoring(true);

    (async () => {
      try {
        const { ride } = await api.getActiveRide();
        if (alive && ride) setRestoredRide(ride);
      } catch {
        /* sem corrida ativa ou rede indisponível */
      } finally {
        if (alive) setRestoring(false);
      }
    })();

    return () => {
      alive = false;
    };
  }, [enabled]);

  return { restoredRide, restoring };
}
