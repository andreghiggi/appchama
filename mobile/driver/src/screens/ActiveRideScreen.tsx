import React, { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Modal,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useScreenLayout } from '../../../shared/responsive';
import { api } from '../../../shared/api';
import { resolveRideCoords } from '../../../shared/coords';
import { formatBrl } from '../../../shared/fare';
import { requestCurrentLocation, startLocationWatch } from '../../../shared/location';
import ExpandableMapLayout from '../../../shared/ExpandableMapLayout';
import RideMapPanel from '../../../shared/RideMapPanel';
import TripDetailsCard from '../../../shared/TripDetailsCard';
import { rideStatusLabel } from '../../../shared/labels';
import { theme } from '../../../shared/theme';
import { dismissRideSession } from '../../../shared/rideSession';
import type { Ride } from '../../../shared/types';

type Props = {
  rideId: string;
  onDone: () => void;
};

export default function ActiveRideScreen({ rideId, onDone }: Props) {
  const layout = useScreenLayout();
  const [ride, setRide] = useState<Ride | null>(null);
  const [loading, setLoading] = useState(false);
  const [myLocation, setMyLocation] = useState<{ latitude: number; longitude: number } | null>(null);
  const [completedFare, setCompletedFare] = useState<number | null>(null);
  const [awaitingPayment, setAwaitingPayment] = useState(false);
  const [confirmingDirect, setConfirmingDirect] = useState(false);
  const [canceled, setCanceled] = useState(false);
  const stopWatchRef = useRef<(() => void) | null>(null);
  const paymentModalDismissedRef = useRef(false);
  const confirmingPaidRef = useRef(false);
  const onDoneRef = useRef(onDone);
  onDoneRef.current = onDone;

  useEffect(() => {
    let alive = true;
    let timer: ReturnType<typeof setInterval> | null = null;

    const load = async () => {
      try {
        const next = await api.getRide(rideId);
        if (!alive) return;
        setRide(next);

        if (next.status === 'completed' && next.payment_status === 'paid') {
          if (timer) clearInterval(timer);
          onDoneRef.current();
          return;
        }

        if (next.status === 'awaiting_payment') {
          setCompletedFare(Number(next.driver_net_amount ?? next.final_fare ?? 0));
          if (!paymentModalDismissedRef.current && !confirmingPaidRef.current) {
            setAwaitingPayment(true);
          }
        }

        if (next.status.startsWith('canceled') && timer) {
          setCanceled(true);
          clearInterval(timer);
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

  const { usingFallback } = resolveRideCoords(ride ?? {});

  const cancelRide = async () => {
    setLoading(true);
    try {
      await api.cancelRide(rideId, 'Cancelado pelo motorista');
      onDone();
    } catch {
      setLoading(false);
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
        paymentModalDismissedRef.current = false;
        setAwaitingPayment(true);
        setCompletedFare(Number(completed.driver_net_amount ?? completed.final_fare ?? 0));
      }
    } finally {
      setLoading(false);
    }
  };

  const confirmDirect = async () => {
    if (ride?.payment_method !== 'direct_driver') {
      Alert.alert(
        'Pagamento pelo app',
        'O passageiro pagará via Pix ou cartão no app. Aguarde a confirmação automática.',
      );
      return;
    }

    confirmingPaidRef.current = true;
    setConfirmingDirect(true);
    try {
      const updated = await api.confirmDirectPayment(rideId);
      setRide(updated);

      if (updated.payment_status === 'paid' || updated.status === 'completed') {
        setAwaitingPayment(false);
        onDone();
        return;
      }

      const refreshed = await api.getRide(rideId);
      setRide(refreshed);
      if (refreshed.payment_status === 'paid' || refreshed.status === 'completed') {
        setAwaitingPayment(false);
        onDone();
      } else {
        Alert.alert('Aguardando', 'Pagamento ainda não confirmado. Tente novamente em instantes.');
        confirmingPaidRef.current = false;
      }
    } catch (e: unknown) {
      confirmingPaidRef.current = false;
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

  const label =
    ride.status === 'accepted'
      ? 'Cheguei no embarque'
      : ride.status === 'driver_arrived'
        ? 'Iniciar corrida (embarque)'
        : ride.status === 'in_progress'
          ? 'Finalizar corrida'
          : 'Aguardando';

  const showDirectConfirm = ride.payment_method === 'direct_driver';
  const waitingAppPayment =
    ride.status === 'awaiting_payment' && ride.payment_method !== 'direct_driver';

  const Root = Platform.OS === 'web' ? View : SafeAreaView;
  const rootProps =
    Platform.OS === 'web' ? {} : ({ edges: ['top'] as const } satisfies { edges: ('top')[] });

  return (
    <Root style={styles.container} {...rootProps}>
      <View style={styles.fill}>
        <ExpandableMapLayout
          defaultMapHeight={layout.activeRideMapHeight}
          renderMap={(mapHeight, expanded) => (
            <RideMapPanel
              ride={ride}
              driverLocation={myLocation}
              mapHeight={mapHeight}
              perspective="driver"
              style={styles.mapFill}
              fitTrigger={expanded}
            />
          )}
        >
          <Text style={styles.title}>Corrida ativa</Text>
          <Text style={styles.meta}>Status: {rideStatusLabel(ride.status)}</Text>
          <Text style={styles.meta}>Passageiro: {ride.passenger?.name ?? '—'}</Text>

          {usingFallback ? (
            <Text style={styles.coordsWarning}>
              Localização da corrida indisponível. O mapa pode estar impreciso.
            </Text>
          ) : null}

          {['accepted', 'driver_arrived', 'in_progress'].includes(ride.status) ? (
            <TripDetailsCard ride={ride} perspective="driver" />
          ) : null}

          {ride.status === 'awaiting_payment' && (
            <>
              {waitingAppPayment ? (
                <Text style={styles.meta}>
                  Aguardando pagamento do passageiro via app (Pix/cartão).
                </Text>
              ) : (
                <Text style={styles.meta}>Aguardando confirmação do pagamento direto.</Text>
              )}

              {showDirectConfirm ? (
                <Pressable style={styles.button} onPress={confirmDirect} disabled={confirmingDirect}>
                  {confirmingDirect ? (
                    <ActivityIndicator color={theme.ink} />
                  ) : (
                    <Text style={styles.buttonText}>Confirmar recebimento</Text>
                  )}
                </Pressable>
              ) : null}

              <Pressable
                style={styles.navLink}
                onPress={() => {
                  dismissRideSession(rideId);
                  paymentModalDismissedRef.current = true;
                  onDone();
                }}
              >
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

          {['accepted', 'driver_arrived'].includes(ride.status) && (
            <Pressable style={styles.cancel} onPress={cancelRide} disabled={loading}>
              <Text style={styles.cancelText}>Cancelar corrida</Text>
            </Pressable>
          )}
        </ExpandableMapLayout>
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

      <Modal visible={awaitingPayment && completedFare != null && !paymentModalDismissedRef.current} transparent animationType="fade">
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Aguardando pagamento</Text>
            <Text style={styles.modalFare}>Valor líquido: {formatBrl(completedFare ?? 0)}</Text>
            <Text style={styles.meta}>
              {showDirectConfirm
                ? 'Confirme quando receber o pagamento direto do passageiro.'
                : 'O passageiro pagará via app (Pix/cartão). Esta tela atualiza automaticamente.'}
            </Text>
            {showDirectConfirm ? (
              <Pressable style={styles.button} onPress={confirmDirect} disabled={confirmingDirect}>
                {confirmingDirect ? (
                  <ActivityIndicator color={theme.ink} />
                ) : (
                  <Text style={styles.buttonText}>Confirmar recebimento</Text>
                )}
              </Pressable>
            ) : null}
            <Pressable
              style={styles.navLink}
              onPress={() => {
                paymentModalDismissedRef.current = true;
                setAwaitingPayment(false);
                dismissRideSession(rideId);
                onDone();
              }}
            >
              <Text style={styles.navLinkText}>Voltar ao painel</Text>
            </Pressable>
          </View>
        </View>
      </Modal>
    </Root>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  container: {
    flex: 1,
    backgroundColor: theme.bg,
    width: '100%',
    maxWidth: '100%',
    overflow: 'hidden',
  },
  fill: { flex: 1, width: '100%', maxWidth: '100%', overflow: 'hidden' },
  mapFill: { flex: 1, height: '100%' },
  title: { fontSize: 20, fontWeight: '700', color: theme.ink, marginBottom: 6 },
  meta: { marginTop: 4, color: theme.textSecondary, fontSize: 14 },
  coordsWarning: {
    marginTop: 8,
    fontSize: 12,
    color: theme.danger,
    backgroundColor: 'rgba(226,75,74,0.08)',
    padding: 10,
    borderRadius: 8,
    lineHeight: 16,
  },
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
  modalTitle: { fontSize: 20, fontWeight: '700', color: theme.ink, marginBottom: 12 },
  modalFare: { fontSize: 18, color: theme.mint, fontWeight: '700', marginBottom: 20 },
});
