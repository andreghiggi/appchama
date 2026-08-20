import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import MapView, { Marker } from 'react-native-maps';
import { api } from '../../../shared/api';
import { theme } from '../../../shared/theme';
import type { Ride } from '../../../shared/types';

type Props = {
  ride: Ride;
  onFinish: () => void;
  onRate: (ride: Ride) => void;
};

export default function RideScreen({ ride: initial, onFinish, onRate }: Props) {
  const [ride, setRide] = useState(initial);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const interval = setInterval(async () => {
      try {
        const updated = await api.getRide(initial.id);
        setRide(updated);
        if (updated.status === 'completed') {
          onRate(updated);
        }
        if (['canceled_by_passenger', 'canceled_by_driver', 'no_drivers_available'].includes(updated.status)) {
          onFinish();
        }
      } catch {
        /* ignore polling errors */
      }
    }, 3000);

    return () => clearInterval(interval);
  }, [initial.id, onFinish, onRate]);

  const cancel = async () => {
    setLoading(true);
    try {
      await api.cancelRide(ride.id, 'Cancelado pelo passageiro');
      onFinish();
    } finally {
      setLoading(false);
    }
  };

  const lat = ride.origin_lat ?? -23.5505;
  const lng = ride.origin_lng ?? -46.6333;

  return (
    <View style={styles.container}>
      <View style={styles.banner}>
        <Text style={styles.bannerText}>
          Status: <Text style={styles.bannerHighlight}>{ride.status}</Text>
        </Text>
      </View>

      <MapView
        style={styles.map}
        region={{
          latitude: lat,
          longitude: lng,
          latitudeDelta: 0.03,
          longitudeDelta: 0.03,
        }}
      >
        <Marker coordinate={{ latitude: lat, longitude: lng }} title="Origem" pinColor={theme.ink} />
      </MapView>

      <View style={styles.sheet}>
        <Text style={styles.driverName}>{ride.driver?.user?.name ?? 'Buscando motorista...'}</Text>
        <Text style={styles.fare}>
          Estimativa: R$ {Number(ride.estimated_fare ?? 0).toFixed(2).replace('.', ',')}
        </Text>
        <Pressable style={styles.cancel} onPress={cancel} disabled={loading}>
          {loading ? <ActivityIndicator /> : <Text style={styles.cancelText}>Cancelar corrida</Text>}
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg },
  banner: {
    position: 'absolute',
    top: 50,
    left: 16,
    right: 16,
    zIndex: 2,
    backgroundColor: theme.ink,
    borderRadius: 13,
    padding: 12,
  },
  bannerText: { color: '#fff', fontSize: 13 },
  bannerHighlight: { color: theme.amber, fontWeight: '700' },
  map: { flex: 1 },
  sheet: {
    backgroundColor: theme.paper,
    borderTopLeftRadius: 22,
    borderTopRightRadius: 22,
    padding: 18,
  },
  driverName: { fontSize: 16, fontWeight: '600', color: theme.ink },
  fare: { marginTop: 8, fontSize: 14, color: theme.textSecondary },
  cancel: { marginTop: 16, alignItems: 'center' },
  cancelText: { color: theme.danger, fontWeight: '600' },
});
