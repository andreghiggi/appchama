import type { Ride } from './types';
import { toNumber } from './coords';

/** Zoom do mapa conforme a distancia ate o proximo ponto: quanto mais perto, mais aproximado. */
export function zoomPorDistanciaKm(km: number | null | undefined): number {
  if (km == null || !Number.isFinite(km)) return 16;
  if (km < 0.15) return 18;
  if (km < 0.4) return 17;
  if (km < 1) return 16;
  if (km < 2.5) return 15;
  if (km < 6) return 14;
  if (km < 15) return 13;
  return 12;
}

/** "5 min", "1 h 05 min". */
export function formatEta(min: number | null | undefined): string | null {
  if (min == null || !Number.isFinite(Number(min))) return null;
  const m = Math.max(1, Math.round(Number(min)));
  if (m < 60) return `${m} min`;
  const h = Math.floor(m / 60);
  const r = m % 60;
  return r === 0 ? `${h} h` : `${h} h ${String(r).padStart(2, '0')} min`;
}

/** Horario previsto de chegada (HH:MM) somando os minutos a agora. */
export function horaChegada(min: number | null | undefined, agora: Date = new Date()): string | null {
  if (min == null || !Number.isFinite(Number(min))) return null;
  const d = new Date(agora.getTime() + Math.max(1, Math.round(Number(min))) * 60000);
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

/** Primeira parte do endereco ("Rua X, 123"), sem cidade/estado/CEP. */
export function enderecoCurto(label: string | null | undefined): string {
  const t = (label ?? '').trim();
  if (!t) return '';
  return t.split(',').slice(0, 2).join(',').trim();
}

export type MensagemCorrida = { titulo: string; subtitulo?: string };

type RideComTracking = Ride & {
  driver_eta_min?: number | string | null;
  driver_distance_km?: number | string | null;
  trip_duration_min?: number | string | null;
};

/** Distancia (km) ate o proximo ponto da rota informada pela API, se houver. */
export function distanciaAteAlvoKm(ride: Ride | null | undefined): number | null {
  if (!ride) return null;
  const v = toNumber((ride as RideComTracking).driver_distance_km);
  return Number.isFinite(v) ? v : null;
}

function etaMin(ride: RideComTracking): number | null {
  const v = toNumber(ride.driver_eta_min);
  return Number.isFinite(v) ? v : null;
}

/** Texto do aviso no topo do mapa do PASSAGEIRO, no estilo Uber. */
export function mensagemPassageiro(ride: Ride): MensagemCorrida | null {
  const r = ride as RideComTracking;
  const nome = (r.driver?.user?.name ?? 'Seu motorista').split(' ')[0];
  const eta = etaMin(r);
  const embarque = enderecoCurto(r.origin_address);
  switch (r.status) {
    case 'searching':
      return { titulo: 'Procurando um motorista perto de você…', subtitulo: 'Isso costuma levar poucos segundos' };
    case 'accepted':
      return {
        titulo: eta != null ? `${nome} chega em ${formatEta(eta)}` : `${nome} está a caminho`,
        subtitulo: embarque ? `Espere no local de partida, próximo de ${embarque}` : 'Espere no local de partida',
      };
    case 'driver_arrived':
      return {
        titulo: `${nome} chegou!`,
        subtitulo: 'Vá até o veículo. O motorista está esperando no local de partida',
      };
    case 'in_progress': {
      const destino = enderecoCurto(r.destination_address);
      const hora = horaChegada(eta);
      return {
        titulo: eta != null ? `Chegada em ${formatEta(eta)}${hora ? ` (${hora})` : ''}` : 'Em viagem',
        subtitulo: destino ? `Indo para ${destino}` : 'Boa viagem!',
      };
    }
    default:
      return null;
  }
}

/** Texto do aviso no topo do mapa do MOTORISTA. */
export function mensagemMotorista(ride: Ride): MensagemCorrida | null {
  const r = ride as RideComTracking;
  const eta = etaMin(r);
  const passageiro = (r.passenger?.name ?? 'o passageiro').split(' ')[0];
  switch (r.status) {
    case 'accepted':
      return {
        titulo: eta != null ? `Siga até o embarque · ${formatEta(eta)}` : 'Siga até o embarque',
        subtitulo: enderecoCurto(r.origin_address) || undefined,
      };
    case 'driver_arrived': {
      const viagem = toNumber(r.trip_duration_min);
      return {
        titulo: `Você chegou. Aguardando ${passageiro}`,
        subtitulo: Number.isFinite(viagem) ? `Viagem prevista: ${formatEta(viagem)}` : enderecoCurto(r.origin_address) || undefined,
      };
    }
    case 'in_progress': {
      const hora = horaChegada(eta);
      return {
        titulo: eta != null ? `Siga ao destino · ${formatEta(eta)}${hora ? ` (${hora})` : ''}` : 'Siga ao destino',
        subtitulo: enderecoCurto(r.destination_address) || undefined,
      };
    }
    default:
      return null;
  }
}
