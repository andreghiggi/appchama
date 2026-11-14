import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { api } from '../../../shared/api';
import { toLatLng, toNumber } from '../../../shared/coords';
import DriverCard from '../../../shared/DriverCard';
import { formatBrl } from '../../../shared/fare';
import { AppMap, AppMarker, AppRoute } from '../../../shared/SimpleMap';
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
    timer = setInterval(poll, 2000);

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

  const lat = toNumber(ride.origin_lat, -23.5505);
  const lng = toNumber(ride.origin_lng, -46.6333);
  const destLat = toNumber(ride.destination_lat, lat + 0.02);
  const destLng = toNumber(ride.destination_lng, lng + 0.02);
  const driverLoc =
    toLatLng(ride.driver_location) ??
    (ride.driver?.current_lat != null && ride.driver?.current_lng != null
      ? toLatLng({ latitude: ride.driver.current_lat, longitude: ride.driver.current_lng })
      : null);

  const fitCoords = useMemo(() => {
    const coords = [
      { latitude: lat, longitude: lng },
      { latitude: destLat, longitude: destLng },
    ];
    if (driverLoc) {
      coords.push(driverLoc);
    }
    return coords;
  }, [lat, lng, destLat, destLng, driverLoc]);

  const routeCoords =
    ride.route_coordinates && ride.route_coordinates.length >= 2
      ? ride.route_coordinates
      : fitCoords;

  const isSearching = ride.status === 'searching';
  const noDrivers = ride.status === 'no_drivers_available';
  const canceled = ride.status.startsWith('canceled');
  const finished = noDrivers || canceled;
  const hasDriver = Boolean(ride.driver?.user?.name);
  const showDriverOnMap = hasDriver && driverLoc && ride.status !== 'searching';

  const driverSubtitle =
    ride.status === 'driver_arrived'
      ? 'Motorista no local de embarque'
      : ride.status === 'in_progress'
        ? 'A caminho do destino'
        : ride.status === 'accepted'
          ? 'A caminho do embarque'
          : undefined;

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
            etaMin={ride.status === 'in_progress' && ride.driver_eta_min != null ? Number(ride.driver_eta_min) : null}
            label={ride.status === 'in_progress' ? 'Chegada' : undefined}
          />
          {showDriverOnMap ? (
            <AppMarker coordinate={driverLoc!} title="Motorista" variant="driver" />
          ) : null}
          <AppRoute coordinates={routeCoords} />
        </AppMap>
      </View>

      <View style={styles.sheet}>
        <View style={styles.statusRow}>
          {isSearching ? <ActivityIndicator color={theme.mint} style={styles.statusSpinner} /> : null}
          <Text style={styles.statusLabel}>{rideStatusLabel(ride.status)}</Text>
        </View>

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
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.bg,
    minHeight: '100%' as unknown as number,
  },
  mapWrap: {
    flex: 1,
    minHeight: 320,
    position: 'relative',
  },
  map: { flex: 1, minHeight: 320 },
  sheet: {
    backgroundColor: theme.paper,
    borderTopLeftRadius: 22,
    borderTopRightRadius: 22,
    padding: 20,
    paddingBottom: 24,
    shadowColor: '#000',
    shadowOpacity: 0.08,
    shadowRadius: 12,
    elevation: 8,
  },
  statusRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 8 },
  statusSpinner: { marginRight: 8 },
  statusLabel: { fontSize: 13, fontWeight: '700', color: theme.mint, textTransform: 'uppercase' },
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
  meta: { fontSize: 13, color: theme.textSecondary, marginBottom: 6, lineHeight: 18 },
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
