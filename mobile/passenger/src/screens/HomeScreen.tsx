import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import * as Location from 'expo-location';
import { api } from '../../../shared/api';
import { AppMap, AppMarker } from '../../../shared/SimpleMap';
import { theme } from '../../../shared/theme';
import type { Ride } from '../../../shared/types';

type Props = {
  onRideActive: (ride: Ride) => void;
};

export default function HomeScreen({ onRideActive }: Props) {
  const [region, setRegion] = useState({
    latitude: -23.5505,
    longitude: -46.6333,
    latitudeDelta: 0.04,
    longitudeDelta: 0.04,
  });
  const [destination, setDestination] = useState('');
  const [loading, setLoading] = useState(false);
  const [cityId, setCityId] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status === 'granted') {
        const loc = await Location.getCurrentPositionAsync({});
        setRegion((r) => ({
          ...r,
          latitude: loc.coords.latitude,
          longitude: loc.coords.longitude,
        }));
      }

      try {
        const cities = await api.listCities();
        if (cities[0]) setCityId(cities[0].id);
      } catch {
        /* cities loaded after auth in some flows */
      }
    })();
  }, []);

  const requestRide = async () => {
    if (!cityId) {
      Alert.alert('Cidade', 'Nenhuma cidade disponível.');
      return;
    }

    setLoading(true);
    try {
      const ride = await api.createRide({
        city_id: cityId,
        origin_lat: region.latitude,
        origin_lng: region.longitude,
        origin_address: 'Local atual',
        destination_lat: region.latitude + 0.02,
        destination_lng: region.longitude + 0.02,
        destination_address: destination || 'Destino informado',
      });
      onRideActive(ride);
    } catch (e: unknown) {
      Alert.alert('Erro', e instanceof Error ? e.message : 'Não foi possível solicitar corrida');
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.container}>
      <AppMap style={styles.map} region={region} onRegionChangeComplete={setRegion} title="Sua localização">
        <AppMarker coordinate={{ latitude: region.latitude, longitude: region.longitude }} title="Você" />
      </AppMap>

      <View style={styles.sheet}>
        <Text style={styles.greet}>Para onde vamos?</Text>
        <TextInput
          style={styles.input}
          placeholder="Digite o destino"
          value={destination}
          onChangeText={setDestination}
        />
        <Pressable style={styles.button} onPress={requestRide} disabled={loading}>
          {loading ? (
            <ActivityIndicator color={theme.ink} />
          ) : (
            <Text style={styles.buttonText}>Pedir corrida</Text>
          )}
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg },
  map: { flex: 1 },
  sheet: {
    backgroundColor: theme.paper,
    borderTopLeftRadius: 22,
    borderTopRightRadius: 22,
    padding: 18,
    marginTop: -20,
  },
  greet: { fontSize: 17, fontWeight: '600', color: theme.ink, marginBottom: 12 },
  input: {
    backgroundColor: theme.bg,
    borderRadius: 12,
    padding: 12,
    marginBottom: 12,
  },
  button: {
    backgroundColor: theme.amber,
    borderRadius: 13,
    padding: 14,
    alignItems: 'center',
  },
  buttonText: { color: theme.ink, fontWeight: '700', fontSize: 15 },
});
