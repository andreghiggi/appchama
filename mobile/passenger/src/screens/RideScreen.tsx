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
import { formatBrl } from '../../../shared/fare';
import { AppMap, AppMarker, AppRoute } from '../../../shared/SimpleMap';
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

  const vehicle = ride.driver?.vehicles?.[0];
  const primeiroNome = (ride.driver?.user?.name ?? 'Motorista').split(' ')[0];
  const etaNum = Number(ride.driver_eta_min);
  const etaOk = Number.isFinite(etaNum) && ride.driver_eta_min != null;
  const headline =
    ride.status === 'driver_arrived'
      ? `${primeiroNome} chegou`
      : ride.status === 'in_progress'
        ? 'A caminho do destino'
        : `${primeiroNome} está a caminho`;
  const headSub =
    ride.status === 'driver_arrived'
      ? 'Motorista no local de embarque'
      : ride.status === 'in_progress'
        ? 'Chegada prevista'
        : 'Indo até o seu embarque';
  const etaBox = ride.status === 'driver_arrived' ? null : etaOk ? Math.max(1, Math.round(etaNum)) : null;
  const ratingNum = Number(ride.driver?.rating_avg);
  const ratingTxt = Number.isFinite(ratingNum) && ratingNum > 0 ? ratingNum.toFixed(1).replace('.', ',') : null;

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
        <View style={styles.handle} />

        {canceled ? (
          <Text style={styles.headline}>
            {ride.status === 'canceled_by_driver' ? 'O motorista cancelou a corrida' : 'Corrida cancelada'}
          </Text>
        ) : hasDriver ? (
          <View style={styles.headRow}>
            <View style={{ flex: 1 }}>
              <Text style={styles.headline} numberOfLines={2}>{headline}</Text>
              <Text style={styles.headSub}>{headSub}</Text>
            </View>
            {etaBox != null ? (
              <View style={styles.etaBox}>
                <Text style={styles.etaNum}>{etaBox}</Text>
                <Text style={styles.etaUnit}>min</Text>
              </View>
            ) : null}
          </View>
        ) : (
          <View style={styles.headRow}>
            {isSearching ? <ActivityIndicator color={theme.ink} style={styles.statusSpinner} /> : null}
            <Text style={[styles.headline, { flex: 1 }]}>
              {isSearching
                ? ride.canceled_reason?.startsWith('Motorista anterior')
                  ? 'O motorista cancelou. Procurando outro para você...'
                  : 'Procurando motorista próximo...'
                : 'Nenhum motorista disponível'}
            </Text>
          </View>
        )}

        {hasDriver && !canceled ? (
          <View style={styles.driverRow}>
            <View style={styles.avatar}>
              <Text style={styles.avatarText}>{(ride.driver?.user?.name ?? 'M').charAt(0).toUpperCase()}</Text>
              {ratingTxt ? (
                <View style={styles.ratingPill}>
                  <Text style={styles.ratingTxt}>{ratingTxt} ★</Text>
                </View>
              ) : null}
            </View>
            <View style={styles.vehicleCol}>
              <Text style={styles.plate}>{vehicle?.plate ?? '—'}</Text>
              <Text style={styles.vehicleTxt} numberOfLines={1}>
                {[vehicle?.model, vehicle?.color].filter(Boolean).join(' · ') || ride.driver?.user?.name}
              </Text>
            </View>
          </View>
        ) : null}

        <View style={styles.divider} />

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
  handle: { alignSelf: 'center', width: 40, height: 4, borderRadius: 2, backgroundColor: '#D4D4D4', marginBottom: 12 },
  statusSpinner: { marginRight: 8 },
  headRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  headline: { fontSize: 20, fontWeight: '800', color: theme.ink },
  headSub: { fontSize: 13, color: theme.textSecondary, marginTop: 2 },
  etaBox: {
    backgroundColor: theme.amber,
    borderRadius: 10,
    minWidth: 64,
    paddingVertical: 8,
    paddingHorizontal: 12,
    alignItems: 'center',
  },
  etaNum: { fontSize: 26, fontWeight: '800', color: theme.ink, lineHeight: 28 },
  etaUnit: { fontSize: 12, fontWeight: '700', color: theme.ink },
  driverRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 14,
    paddingTop: 14,
    borderTopWidth: 1,
    borderTopColor: theme.line,
  },
  avatar: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: theme.ink,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: { color: theme.amber, fontWeight: '800', fontSize: 26 },
  ratingPill: {
    position: 'absolute',
    bottom: -8,
    backgroundColor: theme.paper,
    borderRadius: 10,
    paddingHorizontal: 6,
    paddingVertical: 1,
    borderWidth: 1,
    borderColor: theme.line,
  },
  ratingTxt: { fontSize: 11, fontWeight: '700', color: theme.ink },
  vehicleCol: { alignItems: 'flex-end', flex: 1, marginLeft: 12 },
  plate: { fontSize: 24, fontWeight: '800', color: theme.ink, letterSpacing: 1 },
  vehicleTxt: { fontSize: 14, color: theme.textSecondary, marginTop: 2 },
  divider: { height: 1, backgroundColor: theme.line, marginTop: 14 },
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
