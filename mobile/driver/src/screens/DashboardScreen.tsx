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
import { formatBrl } from '../../../shared/fare';
import { SafeAreaView } from 'react-native-safe-area-context';
import { APP_LAYOUT_VERSION } from '../../../shared/appVersion';
import { useScreenLayout } from '../../../shared/responsive';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../shared/api';
import { LogoutButton } from '../components/LogoutButton';
import { useAuth } from '../context/AuthContext';
import { requestCurrentLocation, startLocationWatch } from '../../../shared/location';
import ExpandableMapLayout from '../../../shared/ExpandableMapLayout';
import { MapLocateButton } from '../../../shared/MapLocateButton';
import { AppMap, AppMarker } from '../../../shared/SimpleMap';
import { onlineLabel, subscriptionStatusLabel } from '../../../shared/labels';
import { alertNewOffer, primeOfferAlert } from '../../../shared/notify';
import { theme } from '../../../shared/theme';
import type { Ride } from '../../../shared/types';

type Props = {
  onOffer: (ride: Ride, expiresIn: number) => void;
  onOpenWallet: () => void;
  onOpenRide: (rideId: string) => void;
};

export default function DashboardScreen({ onOffer, onOpenWallet, onOpenRide }: Props) {
  const { logout } = useAuth();
  const layout = useScreenLayout();
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
  const [confirmingPayment, setConfirmingPayment] = useState(false);
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

  const awaitingPayment = dashboard?.awaiting_payment_ride ?? null;

  const confirmDirectFromDashboard = async () => {
    if (!awaitingPayment) return;

    if (awaitingPayment.payment_method !== 'direct_driver') {
      Alert.alert(
        'Pagamento pelo app',
        'O passageiro pagará via Pix ou cartão no app. Aguarde a confirmação automática.',
      );
      return;
    }

    setConfirmingPayment(true);
    try {
      const updated = await api.confirmDirectPayment(awaitingPayment.id);
      if (updated.payment_status === 'paid' || updated.status === 'completed') {
        Alert.alert('Confirmado', 'Pagamento registrado.');
      } else {
        Alert.alert('Aguardando', 'Pagamento ainda não confirmado. Tente novamente em instantes.');
      }
      await refetch();
    } catch (e: unknown) {
      Alert.alert('Erro', e instanceof Error ? e.message : 'Falha ao confirmar');
    } finally {
      setConfirmingPayment(false);
    }
  };

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
      } else {
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

  const Root = Platform.OS === 'web' ? View : SafeAreaView;
  const rootProps =
    Platform.OS === 'web' ? {} : ({ edges: ['top', 'bottom'] as const } satisfies { edges: ('top' | 'bottom')[] });

  return (
    <Root style={styles.container} {...rootProps}>
      <View style={styles.fill}>
        <ExpandableMapLayout
          defaultMapHeight={layout.mapHeight}
          renderMap={(mapHeight) => (
            <View style={[styles.mapWrap, { height: mapHeight }]}>
              <AppMap
                style={styles.map}
                region={region}
                recenterToken={Math.round(mapHeight)}
              >
                <AppMarker
                  coordinate={{ latitude: region.latitude, longitude: region.longitude }}
                  title="Você"
                  variant="driver"
                />
              </AppMap>
              <MapLocateButton onPress={centerOnUser} loading={locating} />
            </View>
          )}
        >
          <View style={styles.header}>
            <Text style={[styles.headerTitle, layout.isCompact && styles.headerTitleCompact]}>
              Painel do motorista
              {Platform.OS === 'web' ? ` · v${APP_LAYOUT_VERSION}` : ''}
            </Text>
            <View style={styles.toggleRow}>
              <Text style={styles.toggleLabel}>{onlineLabel(online)}</Text>
              <View style={styles.toggleActions}>
                <Switch value={online} onValueChange={toggleOnline} trackColor={{ true: theme.mint }} />
                <LogoutButton compact onPress={handleLogout} />
              </View>
            </View>
          </View>

          <View style={[styles.statsRow, layout.isCompact && styles.statsRowCompact]}>
            <View style={[styles.statCard, layout.isVeryCompact && styles.statCardWide]}>
              <Text style={styles.statLabel}>Ganhos hoje</Text>
              <Text style={[styles.statValue, layout.isCompact && styles.statValueCompact]}>
                R$ {Number(dashboard?.earnings_today ?? 0).toFixed(2).replace('.', ',')}
              </Text>
            </View>
            <View style={[styles.statCard, layout.isVeryCompact && styles.statCardWide]}>
              <Text style={styles.statLabel}>Saldo p/ saque</Text>
              <Text style={[styles.statValue, layout.isCompact && styles.statValueCompact]}>
                R$ {Number(dashboard?.balance_available ?? 0).toFixed(2).replace('.', ',')}
              </Text>
            </View>
            <View style={[styles.statCard, layout.isVeryCompact && styles.statCardWide]}>
              <Text style={styles.statLabel}>Corridas hoje</Text>
              <Text style={[styles.statValue, layout.isCompact && styles.statValueCompact]}>
                {dashboard?.rides_today ?? 0}
              </Text>
            </View>
          </View>

          <Pressable style={styles.walletBtn} onPress={onOpenWallet}>
            <Text style={styles.walletBtnText}>Carteira e saques</Text>
          </Pressable>

          {awaitingPayment ? (
            <View style={styles.paymentBanner}>
              <Text style={styles.paymentBannerTitle}>Corrida aguardando pagamento</Text>
              <Text style={styles.paymentBannerAmount}>
                Valor líquido:{' '}
                {formatBrl(Number(awaitingPayment.driver_net_amount || awaitingPayment.final_fare))}
              </Text>
              <Text style={styles.paymentBannerHint}>
                {awaitingPayment.payment_method === 'direct_driver'
                  ? 'Confirme quando receber o pagamento direto do passageiro.'
                  : 'O passageiro pagará via app (Pix/cartão). Esta tela atualiza automaticamente.'}
              </Text>
              {awaitingPayment.payment_method === 'direct_driver' ? (
                <Pressable
                  style={styles.paymentBannerBtn}
                  onPress={confirmDirectFromDashboard}
                  disabled={confirmingPayment}
                >
                  {confirmingPayment ? (
                    <ActivityIndicator color={theme.ink} />
                  ) : (
                    <Text style={styles.paymentBannerBtnText}>Confirmar recebimento</Text>
                  )}
                </Pressable>
              ) : null}
              <Pressable style={styles.paymentBannerLink} onPress={() => onOpenRide(awaitingPayment.id)}>
                <Text style={styles.paymentBannerLinkText}>Abrir tela da corrida</Text>
              </Pressable>
            </View>
          ) : null}

          {dashboard?.withdraw_hint &&
          (dashboard.balance_available ?? 0) < 1 &&
          (dashboard.total_earnings ?? 0) > 0 ? (
            <View style={styles.walletHintBanner}>
              <Text style={styles.walletHintTitle}>
                Total ganho: R$ {Number(dashboard.total_earnings).toFixed(2).replace('.', ',')} · Saldo p/ saque: R${' '}
                {Number(dashboard.balance_available ?? 0).toFixed(2).replace('.', ',')}
              </Text>
              <Text style={styles.walletHintText}>{dashboard.withdraw_hint}</Text>
            </View>
          ) : null}

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
        </ExpandableMapLayout>
      </View>
    </Root>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg, width: '100%', maxWidth: '100%', overflow: 'hidden' },
  fill: { flex: 1, width: '100%', maxWidth: '100%', overflow: 'hidden' },
  mapWrap: { position: 'relative', flex: 1, width: '100%', height: '100%', overflow: 'hidden' },
  map: { flex: 1, width: '100%', height: '100%' },
  header: {
    backgroundColor: theme.ink,
    borderRadius: 16,
    padding: 14,
    marginBottom: 12,
  },
  headerTitle: { color: '#fff', fontWeight: '700', marginBottom: 8, fontSize: 16 },
  headerTitleCompact: { fontSize: 15 },
  toggleRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  toggleLabel: { color: '#fff', fontWeight: '600', flex: 1, marginRight: 8 },
  toggleActions: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  statsRow: { flexDirection: 'row', gap: 8, marginBottom: 12 },
  statsRowCompact: { flexWrap: 'wrap' },
  statCard: { flex: 1, backgroundColor: theme.bg, borderRadius: 14, padding: 10, minWidth: 96 },
  statCardWide: { flexBasis: '47%', flexGrow: 1 },
  walletBtn: {
    backgroundColor: theme.bg,
    borderRadius: 12,
    padding: 12,
    marginBottom: 12,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: theme.line,
  },
  walletBtnText: { fontWeight: '700', color: theme.ink },
  walletHintBanner: {
    backgroundColor: 'rgba(37,99,235,0.08)',
    borderRadius: 12,
    padding: 12,
    marginBottom: 12,
  },
  walletHintTitle: { fontWeight: '700', color: theme.ink, fontSize: 12, marginBottom: 6 },
  walletHintText: { color: theme.textSecondary, fontSize: 12, lineHeight: 17 },
  paymentBanner: {
    backgroundColor: '#FEF3C7',
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
  },
  paymentBannerTitle: { fontWeight: '700', color: '#92400E', fontSize: 14, marginBottom: 4 },
  paymentBannerAmount: { fontWeight: '700', color: theme.ink, fontSize: 16, marginBottom: 6 },
  paymentBannerHint: { fontSize: 12, color: '#92400E', lineHeight: 17, marginBottom: 10 },
  paymentBannerBtn: {
    backgroundColor: theme.amber,
    borderRadius: 10,
    padding: 12,
    alignItems: 'center',
    marginBottom: 8,
  },
  paymentBannerBtnText: { fontWeight: '700', color: theme.ink },
  paymentBannerLink: { alignItems: 'center', paddingVertical: 4 },
  paymentBannerLinkText: { color: '#92400E', fontSize: 12, textDecorationLine: 'underline' },
  statLabel: { color: theme.textSecondary, fontSize: 12 },
  statValue: { fontSize: 18, fontWeight: '700', color: theme.ink, marginTop: 4 },
  statValueCompact: { fontSize: 16 },
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
});
