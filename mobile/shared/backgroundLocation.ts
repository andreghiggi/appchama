import { Platform } from 'react-native';
import * as Location from 'expo-location';
import * as TaskManager from 'expo-task-manager';
import { api, setToken } from './api';
import { storage } from './storage';

/**
 * Localização do motorista com o app em segundo plano ou com a tela bloqueada.
 * No Android roda como "serviço em primeiro plano" (aparece uma notificação fixa
 * "Você está online"), que é o que o sistema exige para não matar o processo.
 */
export const TAREFA_LOCALIZACAO = 'vaide-motorista-localizacao';

if (Platform.OS !== 'web') {
  // Precisa ser definida na carga do app (o Android pode acordar só esta tarefa, sem a tela).
  TaskManager.defineTask(TAREFA_LOCALIZACAO, async ({ data, error }: { data?: unknown; error?: unknown }) => {
    if (error) return;
    const locais = (data as { locations?: Location.LocationObject[] } | undefined)?.locations;
    const ultimo = locais?.[locais.length - 1];
    if (!ultimo) return;
    try {
      const token = await storage.getItem('token');
      if (!token) return;
      setToken(token);
      await api.updateLocation(ultimo.coords.latitude, ultimo.coords.longitude);
    } catch {
      /* sem rede: a próxima leitura tenta de novo */
    }
  });
}

/** Liga o envio contínuo. Se o motorista negar a permissão "o tempo todo", o app segue funcionando só com a tela aberta. */
export async function iniciarLocalizacaoSegundoPlano(): Promise<boolean> {
  if (Platform.OS === 'web') return false;
  try {
    const primeiro = await Location.requestForegroundPermissionsAsync();
    if (primeiro.status !== 'granted') return false;
    const segundo = await Location.requestBackgroundPermissionsAsync();
    if (segundo.status !== 'granted') return false;

    if (await Location.hasStartedLocationUpdatesAsync(TAREFA_LOCALIZACAO)) return true;

    await Location.startLocationUpdatesAsync(TAREFA_LOCALIZACAO, {
      accuracy: Location.Accuracy.Balanced,
      timeInterval: 8000,
      distanceInterval: 20,
      showsBackgroundLocationIndicator: true,
      foregroundService: {
        notificationTitle: 'Você está online',
        notificationBody: 'Recebendo corridas. Toque para abrir o app.',
        notificationColor: '#FCD200',
      },
    });
    return true;
  } catch {
    return false;
  }
}

export async function pararLocalizacaoSegundoPlano(): Promise<void> {
  if (Platform.OS === 'web') return;
  try {
    if (await Location.hasStartedLocationUpdatesAsync(TAREFA_LOCALIZACAO)) {
      await Location.stopLocationUpdatesAsync(TAREFA_LOCALIZACAO);
    }
  } catch {
    /* já parada */
  }
}
