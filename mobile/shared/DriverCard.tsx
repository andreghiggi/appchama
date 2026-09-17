import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { toNumber } from './coords';
import { theme } from './theme';
import type { Ride } from './types';

type Props = {
  ride: Ride;
  subtitle?: string;
};

export default function DriverCard({ ride, subtitle }: Props) {
  const name = ride.driver?.user?.name ?? 'Motorista';
  const vehicle = ride.driver?.vehicles?.[0];
  const vehicleLabel = vehicle
    ? `${vehicle.model} · ${vehicle.plate}${vehicle.color ? ` · ${vehicle.color}` : ''}`
    : null;

  const etaMin = toNumber(ride.driver_eta_min);
  const distanceKm = toNumber(ride.driver_distance_km);

  const eta =
    Number.isFinite(etaMin) && Number.isFinite(distanceKm)
      ? `~${Math.round(etaMin)} min · ${distanceKm.toFixed(1).replace('.', ',')} km`
      : null;

  return (
    <View style={styles.card}>
      <View style={styles.avatar}>
        <Text style={styles.avatarText}>{name.charAt(0).toUpperCase()}</Text>
      </View>
      <View style={styles.info}>
        <Text style={styles.name}>{name}</Text>
        {vehicleLabel ? <Text style={styles.vehicle}>{vehicleLabel}</Text> : null}
        {subtitle ? <Text style={styles.subtitle}>{subtitle}</Text> : null}
        {eta ? <Text style={styles.eta}>{eta}</Text> : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.bg,
    borderRadius: 14,
    padding: 12,
    marginBottom: 10,
    gap: 12,
  },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: theme.mint,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: { color: '#fff', fontWeight: '700', fontSize: 18 },
  info: { flex: 1 },
  name: { fontSize: 16, fontWeight: '700', color: theme.ink },
  vehicle: { fontSize: 13, color: theme.textSecondary, marginTop: 2 },
  subtitle: { fontSize: 13, color: theme.mint, fontWeight: '600', marginTop: 4 },
  eta: { fontSize: 13, color: theme.textSecondary, marginTop: 2 },
});
