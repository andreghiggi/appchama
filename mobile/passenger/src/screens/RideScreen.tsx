import React, { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useScreenLayout } from '../../../shared/responsive';
import { api } from '../../../shared/api';
import { resolveRideCoords } from '../../../shared/coords';
import { getPhaseSubtitle } from '../../../shared/rideMapHelpers';
import DriverCard from '../../../shared/DriverCard';
import ExpandableMapLayout from '../../../shared/ExpandableMapLayout';
import { formatBrl } from '../../../shared/fare';
import RideMapPanel from '../../../shared/RideMapPanel';
import { rideStatusLabel } from '../../../shared/labels';
import { theme } from '../../../shared/theme';
import type { Ride } from '../../../shared/types';

type Props = {
  ride: Ride;
  onFinish: () => void;
  onCompleted: (ride: Ride) => void;
  onAwaitingPayment: (ride: Ride) => void;
};

export default function RideScreen({ ride: initial, onFinish, onCompleted, onAwaitingPayment }: Props) {
  const layout = useScreenLayout();
  const [ride, setRide] = useState(initial);
  const [loading, setLoading] = useState(false);

  const onCompletedRef = useRef(onCompleted);
  onCompletedRef.current = onCompleted;
  const onAwaitingPaymentRef = useRef(onAwaitingPayment);
  onAwaitingPaymentRef.current = onAwaitingPayment;

  useEffect(() => {
    let alive = true;
    let timer: ReturnType<typeof setInterval> | null = null;

    const poll = async () => {
      try {
        const updated = await api.getRide(initial.id);
        if (!alive) return;
        setRide(updated);

        if (updated.status === 'awaiting_payment') {
          if (timer) clearInterval(timer);
          onAwaitingPaymentRef.current(updated);
          return;
        }

        if (updated.status === 'completed' && updated.payment_status === 'paid') {
          if (timer) clearInterval(timer);
          onCompletedRef.current(updated);
          return;
        }

        if (updated.status.startsWith('canceled') && timer) {
          clearInterval(timer);
        }
      } catch {
        /* ignore polling errors */
      }
    };

    poll();
    timer = setInterval(poll, 1000);

    return () => {
      alive = false;
      if (timer) clearInterval(timer);
    };
  }, [initial.id]);

  const cancel = async () => {
    setLoading(true);
    try {
      await api.cancelRide(ride.id, 'Cancelado pelo passageiro');
      onFinish();
    } finally {
      setLoading(false);
    }
  };

  const { usingFallback } = resolveRideCoords(ride);

  const isSearching = ride.status === 'searching';
  const noDrivers = ride.status === 'no_drivers_available';
  const canceled = ride.status.startsWith('canceled');
  const finished = noDrivers || canceled;
  const hasDriver = Boolean(ride.driver?.user?.name);
  const driverSubtitle = getPhaseSubtitle(ride, 'passenger');

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <ExpandableMapLayout
        defaultMapHeight={layout.activeRideMapHeight}
        renderMap={(mapHeight, expanded) => (
          <RideMapPanel
            ride={ride}
            mapHeight={mapHeight}
            perspective="passenger"
            style={styles.mapFill}
            fitTrigger={expanded}
          />
        )}
      >
        <View style={styles.statusRow}>
          {isSearching ? <ActivityIndicator color={theme.mint} style={styles.statusSpinner} /> : null}
          <Text style={styles.statusLabel}>{rideStatusLabel(ride.status)}</Text>
        </View>

        {usingFallback ? (
          <Text style={styles.coordsWarning}>
            Não foi possível carregar a localização exata desta corrida. O mapa pode estar impreciso.
          </Text>
        ) : null}

        {canceled ? (
          <Text style={styles.driverName}>
            {ride.status === 'canceled_by_driver'
              ? 'O motorista cancelou a corrida'
              : 'Corrida cancelada'}
          </Text>
        ) : hasDriver ? (
          <DriverCard ride={ride} subtitle={driverSubtitle} />
        ) : (
          <Text style={styles.driverName}>
            {isSearching ? 'Procurando motorista próximo...' : 'Nenhum motorista disponível'}
          </Text>
        )}

        {ride.origin_address ? (
          <View style={styles.addressRow}>
            <View style={styles.pinDotBlack} />
            <Text style={styles.addressText} numberOfLines={2}>
              {ride.origin_address}
            </Text>
          </View>
        ) : null}

        {ride.destination_address ? (
          <View style={styles.addressRow}>
            <View style={styles.pinDotRed} />
            <Text style={styles.addressText} numberOfLines={2}>
              {ride.destination_address}
            </Text>
          </View>
        ) : null}

        <Text style={styles.fare}>
          Estimativa: {formatBrl(Number(ride.estimated_fare ?? 0))}
        </Text>

        {finished ? (
          <Pressable style={styles.retryBtn} onPress={onFinish}>
            <Text style={styles.retryBtnText}>Voltar e pedir outra corrida</Text>
          </Pressable>
        ) : (
          <Pressable style={styles.cancel} onPress={cancel} disabled={loading || ride.status === 'completed'}>
            {loading ? (
              <ActivityIndicator color={theme.danger} />
            ) : (
              <Text style={styles.cancelText}>Cancelar corrida</Text>
            )}
          </Pressable>
        )}
      </ExpandableMapLayout>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.bg,
  },
  mapFill: { flex: 1, height: '100%' },
  statusRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 8 },
  statusSpinner: { marginRight: 8 },
  statusLabel: { fontSize: 13, fontWeight: '700', color: theme.mint, textTransform: 'uppercase' },
  coordsWarning: {
    fontSize: 12,
    color: theme.danger,
    backgroundColor: 'rgba(226,75,74,0.08)',
    padding: 10,
    borderRadius: 8,
    marginBottom: 10,
    lineHeight: 16,
  },
  driverName: { fontSize: 18, fontWeight: '700', color: theme.ink, marginBottom: 10 },
  addressRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    marginTop: 8,
  },
  pinDotBlack: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: '#111',
    borderWidth: 2,
    borderColor: '#fff',
    marginTop: 3,
    shadowColor: '#000',
    shadowOpacity: 0.2,
    shadowRadius: 2,
  },
  pinDotRed: {
    width: 10,
    height: 10,
    borderRadius: 2,
    backgroundColor: theme.danger,
    borderWidth: 2,
    borderColor: '#fff',
    marginTop: 3,
    shadowColor: '#000',
    shadowOpacity: 0.2,
    shadowRadius: 2,
  },
  addressText: { flex: 1, fontSize: 13, color: theme.textSecondary, lineHeight: 18 },
  fare: { fontSize: 15, color: theme.textSecondary, marginTop: 4 },
  cancel: { marginTop: 18, alignItems: 'center', paddingVertical: 8 },
  cancelText: { color: theme.danger, fontWeight: '600', fontSize: 15 },
  retryBtn: {
    marginTop: 16,
    backgroundColor: theme.amber,
    borderRadius: 13,
    padding: 14,
    alignItems: 'center',
  },
  retryBtnText: { color: theme.ink, fontWeight: '700' },
});
