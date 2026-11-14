import React, { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Platform,
  Pressable,
  StyleSheet,
  Switch,
  Text,
  View,
} from 'react-native';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../shared/api';
import { LogoutButton } from '../components/LogoutButton';
import { useAuth } from '../context/AuthContext';
import { iniciarLocalizacaoSegundoPlano, pararLocalizacaoSegundoPlano } from '../../../shared/backgroundLocation';
import { requestCurrentLocation, startLocationWatch } from '../../../shared/location';
import { MapLocateButton } from '../../../shared/MapLocateButton';
import { AppMap, AppMarker } from '../../../shared/SimpleMap';
import { onlineLabel, subscriptionStatusLabel } from '../../../shared/labels';
import { alertNewOffer, primeOfferAlert } from '../../../shared/notify';
import { theme } from '../../../shared/theme';
import type { Ride } from '../../../shared/types';

type Props = {
  onOffer: (ride: Ride, expiresIn: number) => void;
  onOpenWallet: () => void;
};

export default function DashboardScreen({ onOffer, onOpenWallet }: Props) {
  const { logout } = useAuth();
  const queryClient = useQueryClient();
  const [online, setOnline] = useState(false);
  const [region, setRegion] = useState({
    latitude: -23.5505,
    longitude: -46.6333,
    latitudeDelta: 0.05,
    longitudeDelta: 0.05,
  });
  const [locating, setLocating] = useState(false);
  const [locationError, setLocationError] = useState<string | null>(null);
  const stopWatchRef = useRef<(() => void) | null>(null);
  const dashboardOnlineRef = useRef(false);
  const onOfferRef = useRef(onOffer);
  onOfferRef.current = onOffer;

  const { data: dashboard, refetch } = useQuery({
    queryKey: ['driver-dashboard'],
    queryFn: () => api.driverDashboard(),
  });

  useEffect(() => {
    if (dashboard) {
      setOnline(dashboard.online);
      dashboardOnlineRef.current = dashboard.online;
      // Reabriu o app já online: retoma o envio de localização em segundo plano.
      if (dashboard.online) void iniciarLocalizacaoSegundoPlano();
    }
  }, [dashboard]);

  const syncLocationToServer = async (lat: number, lng: number) => {
    setRegion((r) => ({
      ...r,
      latitude: lat,
      longitude: lng,
    }));
    if (dashboardOnlineRef.current || online) {
      await api.updateLocation(lat, lng);
    }
  };

  const centerOnUser = async () => {
    setLocating(true);
    setLocationError(null);
    try {
      const coords = await requestCurrentLocation();
      await syncLocationToServer(coords.latitude, coords.longitude);
    } catch (e: unknown) {
      setLocationError(e instanceof Error ? e.message : 'Não foi possível obter sua localização.');
    } finally {
      setLocating(false);
    }
  };

  useEffect(() => {
    centerOnUser();
  }, []);

  useEffect(() => {
    dashboardOnlineRef.current = online;

    if (stopWatchRef.current) {
      stopWatchRef.current();
      stopWatchRef.current = null;
    }

    if (!online) return;

    stopWatchRef.current = startLocationWatch(async (coords) => {
      try {
        await syncLocationToServer(coords.latitude, coords.longitude);
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
  }, [online]);

  const toggleOnline = async (value: boolean) => {
    try {
      if (value) {
        setLocationError(null);
        primeOfferAlert();
        let coords;
        try {
          coords = await requestCurrentLocation();
        } catch (e: unknown) {
          const msg = e instanceof Error ? e.message : 'Ative a localização para ficar online.';
          setLocationError(msg);
          try {
            await api.goOnline();
            setOnline(true);
            dashboardOnlineRef.current = true;
            refetch();
            Alert.alert(
              'Online sem GPS',
              'Ficou disponível usando o ponto de partida da cidade. Ative a localização para posição precisa.',
            );
            return;
          } catch (fallbackError: unknown) {
            Alert.alert(
              'Permissão',
              fallbackError instanceof Error ? fallbackError.message : msg,
            );
            return;
          }
        }

        await api.updateLocation(coords.latitude, coords.longitude);
        await api.goOnline(coords.latitude, coords.longitude);
        setRegion((r) => ({
          ...r,
          latitude: coords.latitude,
          longitude: coords.longitude,
        }));
        setOnline(true);
        dashboardOnlineRef.current = true;
        void iniciarLocalizacaoSegundoPlano();
      } else {
        await pararLocalizacaoSegundoPlano();
        await api.goOffline();
        setOnline(false);
        dashboardOnlineRef.current = false;
      }
      refetch();
    } catch (e: unknown) {
      Alert.alert('Erro', e instanceof Error ? e.message : 'Falha ao alterar status');
    }
  };

  useEffect(() => {
    let cancelled = false;

    const checkOffer = async () => {
      if (cancelled) return;
      if (!dashboardOnlineRef.current && !online) return;
      try {
        const { ride, expires_in: expiresIn } = await api.getPendingOffer();
        if (cancelled || !ride) return;
        alertNewOffer(ride.destination_address ?? undefined);
        onOfferRef.current(ride, expiresIn ?? 45);
      } catch {
        /* ignore */
      }
    };

    const poll = setInterval(checkOffer, 1500);
    checkOffer();

    // Navegadores congelam timers em abas de segundo plano; ao voltar o foco
    // consultamos na hora para não perder a janela da oferta.
    const isWeb = Platform.OS === 'web' && typeof document !== 'undefined';
    const onWake = () => {
      if (document.visibilityState === 'visible') checkOffer();
    };

    if (isWeb) {
      document.addEventListener('visibilitychange', onWake);
      window.addEventListener('focus', checkOffer);
    }

    return () => {
      cancelled = true;
      clearInterval(poll);
      if (isWeb) {
        document.removeEventListener('visibilitychange', onWake);
        window.removeEventListener('focus', checkOffer);
      }
    };
  }, [online]);

  useEffect(() => {
    if (!online) return;

    const heartbeat = setInterval(async () => {
      try {
        const coords = await requestCurrentLocation();
        await syncLocationToServer(coords.latitude, coords.longitude);
      } catch {
        /* ignore */
      }
    }, 30000);

    return () => clearInterval(heartbeat);
  }, [online]);

  const handleLogout = async () => {
    if (online) {
      try {
        await api.goOffline();
      } catch {
        /* ignore */
      }
      setOnline(false);
      dashboardOnlineRef.current = false;
    }

    await logout();
    queryClient.clear();
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Painel do motorista</Text>
        <View style={styles.toggleRow}>
          <Text style={styles.toggleLabel}>{onlineLabel(online)}</Text>
          <View style={styles.toggleActions}>
            <Switch value={online} onValueChange={toggleOnline} trackColor={{ true: theme.mint }} />
            <LogoutButton compact onPress={handleLogout} />
          </View>
        </View>
      </View>

      <Pressable style={styles.creditCard} onPress={onOpenWallet}>
        <Text style={styles.statLabel}>Crédito para aceitar corridas</Text>
        <Text style={styles.statValue}>
          R$ {Number((dashboard as { prepaid_balance?: number } | undefined)?.prepaid_balance ?? 0).toFixed(2).replace('.', ',')}
        </Text>
      </Pressable>

      <View style={styles.statsRow}>
        <View style={styles.statCard}>
          <Text style={styles.statLabel}>Ganhos hoje</Text>
          <Text style={styles.statValue}>
            R$ {Number(dashboard?.earnings_today ?? 0).toFixed(2).replace('.', ',')}
          </Text>
        </View>
        <View style={styles.statCard}>
          <Text style={styles.statLabel}>Saldo p/ saque</Text>
          <Text style={styles.statValue}>
            R$ {Number(dashboard?.balance_available ?? 0).toFixed(2).replace('.', ',')}
          </Text>
        </View>
        <View style={styles.statCard}>
          <Text style={styles.statLabel}>Corridas hoje</Text>
          <Text style={styles.statValue}>{dashboard?.rides_today ?? 0}</Text>
        </View>
      </View>

      <Pressable style={styles.walletBtn} onPress={onOpenWallet}>
        <Text style={styles.walletBtnText}>Carteira e saques</Text>
      </Pressable>

      <View style={styles.badge}>
        <Text style={styles.badgeText}>
          Mensalidade: {subscriptionStatusLabel(dashboard?.subscription_status ?? 'pending')}
        </Text>
      </View>

      {online ? (
        <View style={styles.hintBanner}>
          <Text style={styles.hintText}>
            Mantenha esta tela aberta e em primeiro plano para receber as chamadas.
          </Text>
        </View>
      ) : null}

      {locationError ? (
        <View style={styles.locationBanner}>
          <Text style={styles.locationBannerText}>{locationError}</Text>
        </View>
      ) : null}

      <View style={styles.mapWrap}>
        <AppMap style={styles.map} region={region}>
          <AppMarker
            coordinate={{ latitude: region.latitude, longitude: region.longitude }}
            title="Você"
            variant="driver"
          />
        </AppMap>
        <MapLocateButton onPress={centerOnUser} loading={locating} />
      </View>
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
  toggleLabel: { color: '#fff', fontWeight: '600', flex: 1, marginRight: 8 },
  toggleActions: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  statsRow: { flexDirection: 'row', gap: 8, marginBottom: 12 },
  statCard: { flex: 1, backgroundColor: theme.paper, borderRadius: 14, padding: 10 },
  creditCard: {
    backgroundColor: theme.paper,
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
  },
  walletBtn: {
    backgroundColor: theme.paper,
    borderRadius: 12,
    padding: 12,
    marginBottom: 12,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: theme.line,
  },
  walletBtnText: { fontWeight: '700', color: theme.ink },
  statLabel: { color: theme.textSecondary, fontSize: 12 },
  statValue: { fontSize: 18, fontWeight: '700', color: theme.ink, marginTop: 4 },
  badge: {
    backgroundColor: theme.mintBg,
    borderRadius: 12,
    padding: 10,
    marginBottom: 12,
  },
  badgeText: { color: '#0B6E62', fontWeight: '600', fontSize: 12 },
  locationBanner: {
    backgroundColor: '#FEF3C7',
    borderRadius: 12,
    padding: 10,
    marginBottom: 12,
  },
  locationBannerText: { color: '#92400E', fontSize: 12, fontWeight: '600' },
  hintBanner: {
    backgroundColor: '#E0F2FE',
    borderRadius: 12,
    padding: 10,
    marginBottom: 12,
  },
  hintText: { color: '#075985', fontSize: 12, fontWeight: '600' },
  mapWrap: { flex: 1, minHeight: 240, position: 'relative' },
  map: { flex: 1, minHeight: 240, borderRadius: 16 },
});
