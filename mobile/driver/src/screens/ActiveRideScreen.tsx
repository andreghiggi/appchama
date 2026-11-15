import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Linking,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { activateKeepAwakeAsync, deactivateKeepAwake } from 'expo-keep-awake';
import { api } from '../../../shared/api';
import { toNumber } from '../../../shared/coords';
import { formatBrl } from '../../../shared/fare';
import { requestCurrentLocation, startLocationWatch } from '../../../shared/location';
import { AppMap, AppMarker, AppRoute } from '../../../shared/SimpleMap';
import { rideStatusLabel } from '../../../shared/labels';
import { theme } from '../../../shared/theme';
import type { Ride } from '../../../shared/types';

type Props = {
  rideId: string;
  onDone: () => void;
};

export default function ActiveRideScreen({ rideId, onDone }: Props) {
  const [ride, setRide] = useState<Ride | null>(null);
  const [loading, setLoading] = useState(false);
  const [myLocation, setMyLocation] = useState<{ latitude: number; longitude: number } | null>(null);
  const [completedFare, setCompletedFare] = useState<number | null>(null);
  const [confirmingDirect, setConfirmingDirect] = useState(false);
  const [canceled, setCanceled] = useState(false);
  const stopWatchRef = useRef<(() => void) | null>(null);

  useEffect(() => {
    let alive = true;
    let timer: ReturnType<typeof setInterval> | null = null;

    const load = async () => {
      try {
        const next = await api.getRide(rideId);
        if (!alive) return;
        setRide(next);
        if (next.status === 'awaiting_payment') {
          setCompletedFare(Number(next.driver_net_amount ?? next.final_fare ?? 0));
        }
        if (next.status.startsWith('canceled') && timer) {
          setCanceled(true);
          clearInterval(timer);
        }
        if (next.status === 'completed') {
          setCompletedFare(Number(next.driver_net_amount ?? next.final_fare ?? 0));
          if (timer) clearInterval(timer);
        }
      } catch {
        /* ignore polling errors */
      }
    };

    load();
    timer = setInterval(load, 3000);

    return () => {
      alive = false;
      if (timer) clearInterval(timer);
    };
  }, [rideId]);

  useEffect(() => {
    requestCurrentLocation()
      .then((coords) => setMyLocation(coords))
      .catch(() => {});

    stopWatchRef.current = startLocationWatch(async (coords) => {
      setMyLocation(coords);
      try {
        await api.updateLocation(coords.latitude, coords.longitude);
      } catch {
        /* ignore */
      }
    }, 8000);

    return () => {
      if (stopWatchRef.current) {
        stopWatchRef.current();
        stopWatchRef.current = null;
      }
    };
  }, []);

  // Do aceite ate o fim da corrida a tela do motorista nao apaga sozinha.
  const emAndamento = ['accepted', 'driver_arrived', 'in_progress', 'awaiting_payment'].includes(ride?.status ?? '');
  useEffect(() => {
    if (!emAndamento) return;
    activateKeepAwakeAsync('corrida').catch(() => undefined);
    return () => {
      deactivateKeepAwake('corrida').catch(() => undefined);
    };
  }, [emAndamento]);

  const pagamentoDireto = ride?.payment_method === 'direct_driver';
  const aguardandoPagamento = ride?.status === 'awaiting_payment';
  const corridaPaga = ride?.status === 'completed';
  const lat = toNumber(ride?.origin_lat, -23.5505);
  const lng = toNumber(ride?.origin_lng, -46.6333);
  const destLat = toNumber(ride?.destination_lat, lat + 0.02);
  const destLng = toNumber(ride?.destination_lng, lng + 0.02);

  // Mapa aproximado: enquadra o carro e o proximo ponto (embarque, depois destino).
  const status = ride?.status;
  const fitCoords = useMemo(() => {
    const embarque = { latitude: lat, longitude: lng };
    const destino = { latitude: destLat, longitude: destLng };
    const alvo = status === 'in_progress' || status === 'awaiting_payment' || status === 'completed' ? destino : embarque;
    if (myLocation) return [myLocation, alvo];
    return [embarque, destino];
  }, [lat, lng, destLat, destLng, myLocation, status]);

  const routeCoords =
    ride?.route_coordinates && ride.route_coordinates.length >= 2
      ? ride.route_coordinates
      : fitCoords;

  const cancelRide = async () => {
    setLoading(true);
    try {
      await api.cancelRide(rideId, 'Cancelado pelo motorista');
      onDone();
    } catch (e: unknown) {
      // Antes o erro era engolido e a corrida ficava na tela sem explicação.
      setLoading(false);
      Alert.alert('Não foi possível cancelar', e instanceof Error ? e.message : 'Tente novamente.');
    }
  };

  const nextAction = async () => {
    setLoading(true);
    try {
      if (ride?.status === 'accepted') {
        setRide(await api.arriveRide(rideId));
      } else if (ride?.status === 'driver_arrived') {
        setRide(await api.startRide(rideId));
      } else if (ride?.status === 'in_progress') {
        const completed = await api.completeRide(rideId);
        setRide(completed);
        setCompletedFare(Number(completed.driver_net_amount ?? completed.final_fare ?? 0));
      }
    } finally {
      setLoading(false);
    }
  };

  const confirmDirect = async () => {
    setConfirmingDirect(true);
    try {
      await api.confirmDirectPayment(rideId);
      Alert.alert('Confirmado', 'Pagamento registrado.');
      onDone();
    } catch (e: unknown) {
      Alert.alert('Erro', e instanceof Error ? e.message : 'Falha ao confirmar');
    } finally {
      setConfirmingDirect(false);
    }
  };

  if (!ride) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={theme.amber} />
      </View>
    );
  }

  const navTarget =
    ride.status === 'in_progress'
      ? { latitude: destLat, longitude: destLng, label: 'Destino' }
      : { latitude: lat, longitude: lng, label: 'Embarque' };

  const label =
    ride.status === 'accepted'
      ? 'Cheguei no embarque'
      : ride.status === 'driver_arrived'
        ? 'Iniciar corrida (embarque)'
        : ride.status === 'in_progress'
          ? 'Finalizar corrida'
          : 'Aguardando';

  const openExternalNav = () => {
    Linking.openURL(
      `https://www.google.com/maps/dir/?api=1&destination=${navTarget.latitude},${navTarget.longitude}`,
    );
  };

  return (
    <View style={styles.container}>
      <View style={styles.mapWrap}>
        <AppMap
          style={styles.map}
          region={{
            latitude: (lat + destLat) / 2,
            longitude: (lng + destLng) / 2,
            latitudeDelta: 0.06,
            longitudeDelta: 0.06,
          }}
          fitToCoordinates={fitCoords}
          pinMode="marker"
        >
          <AppMarker coordinate={{ latitude: lat, longitude: lng }} title="Embarque" variant="origin" />
          <AppMarker
            coordinate={{ latitude: destLat, longitude: destLng }}
            title="Destino"
            variant="destination"
          />
          {myLocation ? (
            <AppMarker coordinate={myLocation} title="Você" variant="driver" />
          ) : null}
          <AppRoute coordinates={routeCoords} />
        </AppMap>
      </View>

      <View style={styles.sheet}>
        <Text style={styles.title}>{corridaPaga ? 'Corrida concluída' : 'Corrida ativa'}</Text>
        <Text style={styles.meta}>Status: {rideStatusLabel(ride.status)}</Text>
        <Text style={styles.meta}>Passageiro: {ride.passenger?.name ?? '—'}</Text>

        {aguardandoPagamento && (
          <>
            <Text style={styles.meta}>
              {pagamentoDireto
                ? 'O passageiro vai pagar direto a você (dinheiro ou Pix pessoal).'
                : 'Aguardando o passageiro pagar pelo app (Pix ou cartão). A corrida finaliza sozinha quando o pagamento cair.'}
            </Text>
            {pagamentoDireto ? (
              <Pressable style={styles.button} onPress={confirmDirect} disabled={confirmingDirect}>
                {confirmingDirect ? (
                  <ActivityIndicator color={theme.ink} />
                ) : (
                  <Text style={styles.buttonText}>Confirmar recebimento (direto)</Text>
                )}
              </Pressable>
            ) : null}
            <Pressable style={styles.navLink} onPress={onDone}>
              <Text style={styles.navLinkText}>Voltar ao painel</Text>
            </Pressable>
          </>
        )}

        {['accepted', 'driver_arrived', 'in_progress'].includes(ride.status) && (
          <Pressable style={styles.button} onPress={nextAction} disabled={loading}>
            {loading ? (
              <ActivityIndicator color={theme.ink} />
            ) : (
              <Text style={styles.buttonText}>{label}</Text>
            )}
          </Pressable>
        )}

        <Pressable style={styles.navLink} onPress={openExternalNav}>
          <Text style={styles.navLinkText}>Abrir no GPS externo ({navTarget.label})</Text>
        </Pressable>

        {['accepted', 'driver_arrived'].includes(ride.status) && (
          <Pressable style={styles.cancel} onPress={cancelRide} disabled={loading}>
            <Text style={styles.cancelText}>Cancelar corrida</Text>
          </Pressable>
        )}
      </View>

      <Modal visible={canceled} transparent animationType="fade">
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Corrida cancelada</Text>
            <Text style={styles.meta}>
              {ride.status === 'canceled_by_passenger'
                ? 'O passageiro cancelou esta corrida.'
                : 'Esta corrida foi cancelada.'}
            </Text>
            <Pressable style={styles.button} onPress={onDone}>
              <Text style={styles.buttonText}>Voltar ao painel</Text>
            </Pressable>
          </View>
        </View>
      </Modal>

      <Modal visible={(aguardandoPagamento || corridaPaga) && completedFare != null} transparent animationType="fade">
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            {corridaPaga ? (
              <>
                <Text style={styles.modalCheck}>✓</Text>
                <Text style={styles.modalTitle}>Pagamento recebido</Text>
                <Text style={styles.modalFare}>+ {formatBrl(completedFare ?? 0)}</Text>
                <Text style={styles.meta}>
                  {pagamentoDireto
                    ? 'Recebimento direto confirmado.'
                    : 'Já está na sua carteira, disponível para saque. A comissão foi descontada do seu crédito.'}
                </Text>
                <Pressable style={styles.button} onPress={onDone}>
                  <Text style={styles.buttonText}>Voltar ao painel</Text>
                </Pressable>
              </>
            ) : (
              <>
                <Text style={styles.modalTitle}>Aguardando pagamento</Text>
                <Text style={styles.modalFare}>Valor líquido: {formatBrl(completedFare ?? 0)}</Text>
                <Text style={styles.meta}>
                  {pagamentoDireto
                    ? 'O passageiro escolheu pagar direto: confirme quando receber.'
                    : 'O passageiro está pagando pelo app (Pix ou cartão). Esta tela fecha sozinha quando o pagamento cair.'}
                </Text>
                {pagamentoDireto ? (
                  <Pressable style={styles.button} onPress={confirmDirect} disabled={confirmingDirect}>
                    {confirmingDirect ? (
                      <ActivityIndicator color={theme.ink} />
                    ) : (
                      <Text style={styles.buttonText}>Confirmar recebimento (direto)</Text>
                    )}
                  </Pressable>
                ) : null}
                <Pressable style={styles.navLink} onPress={onDone}>
                  <Text style={styles.navLinkText}>Voltar ao painel</Text>
                </Pressable>
              </>
            )}
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  container: {
    flex: 1,
    backgroundColor: theme.bg,
    minHeight: '100%' as unknown as number,
  },
  mapWrap: { flex: 1, minHeight: 320 },
  map: { flex: 1, minHeight: 320 },
  sheet: {
    backgroundColor: theme.paper,
    borderTopLeftRadius: 22,
    borderTopRightRadius: 22,
    padding: 20,
    paddingBottom: 24,
  },
  title: { fontSize: 20, fontWeight: '700', color: theme.ink, marginBottom: 6 },
  meta: { marginTop: 4, color: theme.textSecondary, fontSize: 14 },
  button: {
    marginTop: 16,
    backgroundColor: theme.amber,
    borderRadius: 13,
    padding: 16,
    alignItems: 'center',
  },
  buttonText: { fontWeight: '700', color: theme.ink },
  cancel: { marginTop: 4, alignItems: 'center', paddingVertical: 8 },
  cancelText: { color: theme.danger, fontWeight: '600', fontSize: 14 },
  navLink: { marginTop: 12, alignItems: 'center', paddingVertical: 8 },
  navLinkText: { color: theme.textSecondary, fontSize: 13, textDecorationLine: 'underline' },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.45)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  modalCard: {
    backgroundColor: theme.paper,
    borderRadius: 16,
    padding: 24,
    width: '100%',
    maxWidth: 360,
  },
  modalCheck: { fontSize: 40, color: theme.mint, fontWeight: '700', textAlign: 'center', marginBottom: 4 },
  modalTitle: { fontSize: 20, fontWeight: '700', color: theme.ink, marginBottom: 12 },
  modalFare: { fontSize: 18, color: theme.mint, fontWeight: '700', marginBottom: 20 },
});
