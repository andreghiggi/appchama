import React from 'react';
import { Linking, Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import { toNumber } from './coords';
import { getPhaseSubtitle, getRoutePhase, getRouteTarget, openNavigationUrl } from './rideMapHelpers';
import { theme } from './theme';
import type { Ride } from './types';

type Props = {
  ride: Ride;
  perspective?: 'passenger' | 'driver';
  showNavigation?: boolean;
};

export default function TripDetailsCard({
  ride,
  perspective = 'driver',
  showNavigation = true,
}: Props) {
  const phase = getRoutePhase(ride);
  const phaseLabel = getPhaseSubtitle(ride, perspective);
  const target = getRouteTarget(ride);

  const etaMin = toNumber(ride.driver_eta_min);
  const distanceKm = toNumber(ride.driver_distance_km);
  const eta =
    Number.isFinite(etaMin) && Number.isFinite(distanceKm)
      ? `~${Math.round(etaMin)} min · ${distanceKm.toFixed(1).replace('.', ',')} km`
      : null;

  const openNav = () => {
    const url = openNavigationUrl(target.latitude, target.longitude, target.label);
    if (Platform.OS === 'web') {
      window.open(url, '_blank');
    } else {
      void Linking.openURL(url);
    }
  };

  return (
    <View style={styles.card}>
      {phaseLabel ? <Text style={styles.phase}>{phaseLabel}</Text> : null}
      {eta ? <Text style={styles.eta}>{eta}</Text> : null}

      {ride.origin_address ? (
        <View style={styles.row}>
          <View style={styles.dotBlack} />
          <View style={styles.rowText}>
            <Text style={styles.label}>Início</Text>
            <Text style={styles.address} numberOfLines={2}>
              {ride.origin_address}
            </Text>
          </View>
        </View>
      ) : null}

      {ride.destination_address ? (
        <View style={styles.row}>
          <View style={styles.dotRed} />
          <View style={styles.rowText}>
            <Text style={styles.label}>Destino</Text>
            <Text style={styles.address} numberOfLines={2}>
              {ride.destination_address}
            </Text>
          </View>
        </View>
      ) : null}

      {ride.distance_km != null ? (
        <Text style={styles.meta}>
          Distância total: {toNumber(ride.distance_km).toFixed(1).replace('.', ',')} km
        </Text>
      ) : null}

      {showNavigation && ['accepted', 'driver_arrived', 'in_progress'].includes(ride.status) ? (
        <Pressable style={styles.navBtn} onPress={openNav}>
          <Text style={styles.navBtnText}>
            {phase === 'to_destination' ? 'Navegar até o destino' : 'Navegar até o embarque'}
          </Text>
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: theme.bg,
    borderRadius: 14,
    padding: 14,
    marginTop: 10,
    gap: 4,
  },
  phase: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.mint,
  },
  eta: {
    fontSize: 13,
    color: theme.textSecondary,
    marginBottom: 6,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    marginTop: 8,
  },
  dotBlack: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: '#111',
    borderWidth: 2,
    borderColor: '#fff',
    marginTop: 4,
  },
  dotRed: {
    width: 10,
    height: 10,
    borderRadius: 2,
    backgroundColor: theme.danger,
    borderWidth: 2,
    borderColor: '#fff',
    marginTop: 4,
  },
  rowText: { flex: 1 },
  label: { fontSize: 11, fontWeight: '700', color: theme.textSecondary, textTransform: 'uppercase' },
  address: { fontSize: 13, color: theme.ink, lineHeight: 18, marginTop: 2 },
  meta: { fontSize: 12, color: theme.textSecondary, marginTop: 6 },
  navBtn: {
    marginTop: 12,
    backgroundColor: theme.paper,
    borderRadius: 10,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: theme.line,
    alignItems: 'center',
  },
  navBtnText: { fontSize: 13, fontWeight: '700', color: theme.ink },
});
