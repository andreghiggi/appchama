import React, { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  StyleSheet,
  Switch,
  Text,
  View,
} from 'react-native';
import * as Location from 'expo-location';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../../shared/api';
import { AppMap } from '../../../shared/SimpleMap';
import { theme } from '../../../shared/theme';
import type { Ride } from '../../../shared/types';

type Props = {
  onOffer: (ride: Ride) => void;
};

export default function DashboardScreen({ onOffer }: Props) {
  const [online, setOnline] = useState(false);
  const [region, setRegion] = useState({
    latitude: -23.5505,
    longitude: -46.6333,
    latitudeDelta: 0.05,
    longitudeDelta: 0.05,
  });
  const locationInterval = useRef<ReturnType<typeof setInterval> | null>(null);

  const { data: dashboard, refetch } = useQuery({
    queryKey: ['driver-dashboard'],
    queryFn: () => api.driverDashboard(),
  });

  useEffect(() => {
    if (dashboard) setOnline(dashboard.online);
  }, [dashboard]);

  const toggleOnline = async (value: boolean) => {
    try {
      if (value) {
        const { status } = await Location.requestForegroundPermissionsAsync();
        if (status !== 'granted') {
          Alert.alert('Permissão', 'Ative a localização para ficar online.');
          return;
        }
        await api.goOnline();
        setOnline(true);
        locationInterval.current = setInterval(async () => {
          const loc = await Location.getCurrentPositionAsync({});
          setRegion((r) => ({
            ...r,
            latitude: loc.coords.latitude,
            longitude: loc.coords.longitude,
          }));
          await api.updateLocation(loc.coords.latitude, loc.coords.longitude);
        }, 8000);
      } else {
        await api.goOffline();
        setOnline(false);
        if (locationInterval.current) clearInterval(locationInterval.current);
      }
      refetch();
    } catch (e: unknown) {
      Alert.alert('Erro', e instanceof Error ? e.message : 'Falha ao alterar status');
    }
  };

  useEffect(() => {
    const poll = setInterval(async () => {
      if (!online) return;
      try {
        const rides = await api.listRides();
        const offer = (rides.data ?? []).find((r) => r.status === 'searching');
        if (offer) onOffer(offer);
      } catch {
        /* ignore */
      }
    }, 4000);

    return () => clearInterval(poll);
  }, [online, onOffer]);

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Painel do motorista</Text>
        <View style={styles.toggleRow}>
          <Text style={styles.toggleLabel}>{online ? 'Online' : 'Offline'}</Text>
          <Switch value={online} onValueChange={toggleOnline} trackColor={{ true: theme.mint }} />
        </View>
      </View>

      <View style={styles.statsRow}>
        <View style={styles.statCard}>
          <Text style={styles.statLabel}>Ganhos hoje</Text>
          <Text style={styles.statValue}>
            R$ {Number(dashboard?.earnings_today ?? 0).toFixed(2).replace('.', ',')}
          </Text>
        </View>
        <View style={styles.statCard}>
          <Text style={styles.statLabel}>Corridas hoje</Text>
          <Text style={styles.statValue}>{dashboard?.rides_today ?? 0}</Text>
        </View>
      </View>

      <View style={styles.badge}>
        <Text style={styles.badgeText}>
          Mensalidade: {dashboard?.subscription_status ?? '—'}
        </Text>
      </View>

      <AppMap style={styles.map} region={region} title="Mapa do motorista" />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg, padding: 16 },
  header: {
    backgroundColor: theme.ink,
    borderRadius: 16,
    padding: 14,
    marginBottom: 12,
  },
  headerTitle: { color: '#fff', fontWeight: '700', marginBottom: 8 },
  toggleRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  toggleLabel: { color: '#fff', fontWeight: '600' },
  statsRow: { flexDirection: 'row', gap: 10, marginBottom: 12 },
  statCard: { flex: 1, backgroundColor: theme.paper, borderRadius: 14, padding: 12 },
  statLabel: { color: theme.textSecondary, fontSize: 12 },
  statValue: { fontSize: 18, fontWeight: '700', color: theme.ink, marginTop: 4 },
  badge: {
    backgroundColor: theme.mintBg,
    borderRadius: 12,
    padding: 10,
    marginBottom: 12,
  },
  badgeText: { color: '#0B6E62', fontWeight: '600', fontSize: 12 },
  map: { flex: 1, borderRadius: 16 },
});
