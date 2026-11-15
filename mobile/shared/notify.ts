import { Platform, Vibration } from 'react-native';
import * as Notifications from 'expo-notifications';

const isWeb = Platform.OS === 'web';

let audioCtx: AudioContext | null = null;
let titleTimer: ReturnType<typeof setInterval> | null = null;
let originalTitle = '';

function getAudioContext(): AudioContext | null {
  if (!isWeb || typeof window === 'undefined') return null;
  try {
    const Ctor =
      (window as unknown as { AudioContext?: typeof AudioContext; webkitAudioContext?: typeof AudioContext })
        .AudioContext ??
      (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
    if (!Ctor) return null;
    audioCtx = audioCtx ?? new Ctor();
    return audioCtx;
  } catch {
    return null;
  }
}

/**
 * Navegadores só liberam áudio após um gesto do usuário; chamamos isto quando o
 * motorista fica online para que o alerta de oferta consiga tocar depois.
 */
export function primeOfferAlert(): void {
  if (!isWeb) return;
  const ctx = getAudioContext();
  if (ctx && ctx.state === 'suspended') {
    ctx.resume().catch(() => undefined);
  }
  try {
    if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
      Notification.requestPermission().catch(() => undefined);
    }
  } catch {
    /* ignore */
  }
}

function beep(): void {
  const ctx = getAudioContext();
  if (!ctx) return;
  if (ctx.state === 'suspended') ctx.resume().catch(() => undefined);

  const start = ctx.currentTime;
  [0, 0.3, 0.6].forEach((offset) => {
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.value = 880;
    gain.gain.setValueAtTime(0.0001, start + offset);
    gain.gain.exponentialRampToValueAtTime(0.4, start + offset + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + offset + 0.24);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start(start + offset);
    osc.stop(start + offset + 0.26);
  });
}

function flashTitle(text: string): void {
  if (!isWeb || typeof document === 'undefined') return;
  if (titleTimer) return;
  originalTitle = document.title;
  let on = false;
  titleTimer = setInterval(() => {
    document.title = on ? originalTitle : text;
    on = !on;
  }, 800);
}

let nativeTimer: ReturnType<typeof setInterval> | null = null;

/** App nativo: vibra e toca o som de notificacao em repeticao ate o motorista responder. */
function startNativeOfferAlert(subtitle?: string): void {
  if (nativeTimer) return;
  const pulse = () => {
    Vibration.vibrate([0, 500, 250, 500, 250, 500]);
    Notifications.scheduleNotificationAsync({
      content: {
        title: 'Nova corrida disponível',
        body: subtitle ?? 'Toque para abrir e aceitar.',
        sound: 'default',
      },
      trigger: Platform.OS === 'android' ? { channelId: 'ofertas' } : null,
    }).catch(() => undefined);
  };
  pulse();
  nativeTimer = setInterval(pulse, 4000);
}

export function stopOfferAlert(): void {
  if (nativeTimer) {
    clearInterval(nativeTimer);
    nativeTimer = null;
    Vibration.cancel();
    Notifications.dismissAllNotificationsAsync().catch(() => undefined);
  }
  if (titleTimer) {
    clearInterval(titleTimer);
    titleTimer = null;
    if (typeof document !== 'undefined' && originalTitle) document.title = originalTitle;
  }
}

export function alertNewOffer(subtitle?: string): void {
  if (!isWeb) {
    startNativeOfferAlert(subtitle);
    return;
  }

  beep();
  flashTitle('🚗 NOVA CORRIDA!');

  try {
    navigator.vibrate?.([300, 150, 300, 150, 300]);
  } catch {
    /* ignore */
  }

  try {
    if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
      const n = new Notification('Nova corrida disponível', {
        body: subtitle ?? 'Toque para abrir e aceitar.',
        tag: 'chama-offer',
        requireInteraction: true,
      });
      n.onclick = () => {
        window.focus();
        n.close();
      };
    }
  } catch {
    /* ignore */
  }
}
