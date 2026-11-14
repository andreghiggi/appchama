import { Platform } from 'react-native';
import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { api } from './api';

let tokenAtual: string | null = null;

// Notificação recebida com o app aberto também aparece (banner + som).
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: false,
  }),
});

/**
 * Pede permissão, obtém o token de push do aparelho e o registra no servidor.
 * Nunca lança erro: push é um reforço, o app funciona sem ele.
 */
export async function registrarPush(): Promise<void> {
  try {
    if (Platform.OS === 'web' || !Device.isDevice) return;

    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync('default', {
        name: 'Corridas',
        importance: Notifications.AndroidImportance.MAX,
        vibrationPattern: [0, 250, 250, 250],
        sound: 'default',
      });
    }

    // Ofertas de corrida: som e vibração fortes, aparecem por cima mesmo com o app fechado.
    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync('ofertas', {
        name: 'Ofertas de corrida',
        importance: Notifications.AndroidImportance.MAX,
        vibrationPattern: [0, 500, 250, 500, 250, 500],
        sound: 'default',
        lockscreenVisibility: Notifications.AndroidNotificationVisibility.PUBLIC,
        enableLights: true,
      });
    }

    let { status } = await Notifications.getPermissionsAsync();
    if (status !== 'granted') {
      status = (await Notifications.requestPermissionsAsync()).status;
    }
    if (status !== 'granted') return;

    const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
    const { data } = await Notifications.getExpoPushTokenAsync(projectId ? { projectId } : undefined);
    if (!data) return;

    tokenAtual = data;
    await api.registerDevice(data, Platform.OS);
  } catch {
    /* sem permissão, sem Firebase configurado ou sem rede: segue sem push */
  }
}

/** Ao sair da conta, este aparelho deixa de receber as notificações dela. */
export async function removerPush(): Promise<void> {
  const token = tokenAtual;
  tokenAtual = null;
  if (!token) return;
  try {
    await api.unregisterDevice(token);
  } catch {
    /* ignora */
  }
}
