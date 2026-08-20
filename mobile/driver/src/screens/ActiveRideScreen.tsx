import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Linking, Pressable, StyleSheet, Text, View } from 'react-native';
import { api } from '../../../shared/api';
import { theme } from '../../../shared/theme';
import type { Ride } from '../../../shared/types';

type Props = {
  rideId: string;
  onDone: () => void;
};

export default function ActiveRideScreen({ rideId, onDone }: Props) {
  const [ride, setRide] = useState<any>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const load = async () => {
      setRide(await api.getRide(rideId));
    };
    load();
    const interval = setInterval(load, 3000);
    return () => clearInterval(interval);
  }, [rideId]);

  if (!ride) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={theme.amber} />
      </View>
    );
  }

  const nextAction = async () => {
    setLoading(true);
    try {
      if (ride.status === 'accepted') {
        setRide(await api.arriveRide(rideId));
      } else if (ride.status === 'driver_arrived') {
        setRide(await api.startRide(rideId));
      } else if (ride.status === 'in_progress') {
        await api.completeRide(rideId);
        onDone();
      }
    } finally {
      setLoading(false);
    }
  };

  const label =
    ride.status === 'accepted'
      ? 'Cheguei no embarque'
      : ride.status === 'driver_arrived'
        ? 'Iniciar corrida (embarque)'
        : ride.status === 'in_progress'
          ? 'Finalizar corrida'
          : 'Aguardando';

  const openNav = () => {
    const lat = ride.origin_lat;
    const lng = ride.origin_lng;
    Linking.openURL(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`);
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Corrida ativa</Text>
      <Text style={styles.meta}>Status: {ride.status}</Text>
      <Text style={styles.meta}>Passageiro: {ride.passenger?.name}</Text>
      <Pressable style={styles.navButton} onPress={openNav}>
        <Text style={styles.navText}>Abrir navegação</Text>
      </Pressable>
      {['accepted', 'driver_arrived', 'in_progress'].includes(ride.status) && (
        <Pressable style={styles.button} onPress={nextAction} disabled={loading}>
          {loading ? <ActivityIndicator color={theme.ink} /> : <Text style={styles.buttonText}>{label}</Text>}
        </Pressable>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  container: { flex: 1, backgroundColor: theme.bg, padding: 20 },
  title: { fontSize: 22, fontWeight: '700', color: theme.ink },
  meta: { marginTop: 8, color: theme.textSecondary },
  navButton: {
    marginTop: 20,
    backgroundColor: theme.paper,
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: theme.line,
  },
  navText: { textAlign: 'center', fontWeight: '600', color: theme.ink },
  button: {
    marginTop: 16,
    backgroundColor: theme.amber,
    borderRadius: 13,
    padding: 16,
    alignItems: 'center',
  },
  buttonText: { fontWeight: '700', color: theme.ink },
});
