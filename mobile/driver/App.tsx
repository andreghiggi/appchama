import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Platform, View } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { TelaSegura } from '../shared/TelaSegura';
import AvisoAtualizacao from '../shared/AvisoAtualizacao';
import GpsObrigatorio from '../shared/GpsObrigatorio';
import { AuthProvider, useAuth } from './src/context/AuthContext';
import LoginScreen from './src/screens/LoginScreen';
import DashboardScreen from './src/screens/DashboardScreen';
import WalletScreen from './src/screens/WalletScreen';
import HistoryScreen from './src/screens/HistoryScreen';
import SuporteScreen from '../shared/SuporteScreen';
import OfferScreen from './src/screens/OfferScreen';
import ActiveRideScreen from './src/screens/ActiveRideScreen';
import PendingApprovalScreen from './src/screens/PendingApprovalScreen';
import { api } from '../shared/api';
import type { NextRide, Ride, User } from '../shared/types';
import { theme } from '../shared/theme';
import { useActiveRideRecovery } from '../shared/useActiveRide';
import { registrarPush } from '../shared/push';

const Stack = createNativeStackNavigator();
const queryClient = new QueryClient();

function driverNeedsApproval(user: User): boolean {
  if (user.status === 'pending') return true;
  const check = user.driver?.background_check_status;
  return check === 'pending' || check === 'rejected' || !user.driver;
}

function MainApp() {
  const { user, loading } = useAuth();

  // Registra o aparelho para receber notificações (nova corrida, motorista chegou, cancelamento...).
  useEffect(() => {
    if (user) void registrarPush();
  }, [user?.id]);
  const [offer, setOffer] = useState<{ ride: Ride; expiresIn: number } | null>(null);
  const [activeRideId, setActiveRideId] = useState<string | null>(null);
  const [showWallet, setShowWallet] = useState(false);
  const [showHistory, setShowHistory] = useState(false);
  const [showSupport, setShowSupport] = useState(false);
  const [nextRide, setNextRide] = useState<NextRide | null>(null);

  // Com passageiro no carro: depois de 20% do trajeto o servidor pode oferecer a próxima corrida. Aqui só se escuta
  // a oferta (a regra de quem pode receber é do servidor) e se mostra a corrida já aceita que está na fila.
  useEffect(() => {
    if (!activeRideId) {
      setNextRide(null);
      return;
    }
    let vivo = true;
    const ver = async () => {
      try {
        const [o, a] = await Promise.all([api.getPendingOffer(), api.getActiveRide()]);
        if (!vivo) return;
        setNextRide(a.next_ride ?? null);
        if (o.ride && o.expires_in) setOffer((atual) => atual ?? { ride: o.ride as Ride, expiresIn: o.expires_in as number });
      } catch {
        /* sem rede: tenta no próximo ciclo */
      }
    };
    void ver();
    const t = setInterval(ver, 6000);
    return () => {
      vivo = false;
      clearInterval(t);
    };
  }, [activeRideId]);

  const canDrive = Boolean(user) && !driverNeedsApproval(user as User);
  const { restoredRide, restoring } = useActiveRideRecovery(canDrive);

  useEffect(() => {
    if (restoredRide) setActiveRideId((current) => current ?? restoredRide.id);
  }, [restoredRide]);

  useEffect(() => {
    if (Platform.OS !== 'web' || typeof document === 'undefined') return;
    document.documentElement.style.height = '100%';
    document.body.style.height = '100%';
    const root = document.getElementById('root');
    if (root) root.style.height = '100%';
  }, []);

  if (loading) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" color={theme.amber} />
      </View>
    );
  }

  if (!user) {
    return (
      <TelaSegura>
        <LoginScreen />
      </TelaSegura>
    );
  }

  if (driverNeedsApproval(user)) {
    return (
      <TelaSegura>
        <PendingApprovalScreen />
      </TelaSegura>
    );
  }

  if (restoring) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" color={theme.amber} />
      </View>
    );
  }

  if (showWallet) {
    return <WalletScreen onBack={() => setShowWallet(false)} />;
  }

  if (showHistory) {
    return <HistoryScreen onBack={() => setShowHistory(false)} />;
  }

  if (showSupport) {
    return <SuporteScreen papel="driver" onBack={() => setShowSupport(false)} />;
  }

  if (offer) {
    return (
      <TelaSegura>
        <OfferScreen
          ride={offer.ride}
          expiresIn={offer.expiresIn}
          onAccepted={() => {
            // com uma corrida já em andamento, a nova fica na fila e a atual continua na tela
            setActiveRideId((atual) => atual ?? offer.ride.id);
            setOffer(null);
          }}
          onDismiss={() => setOffer(null)}
        />
      </TelaSegura>
    );
  }

  if (activeRideId) {
    return (
      <TelaSegura>
        <ActiveRideScreen
          rideId={activeRideId}
          nextRide={nextRide}
          onDone={() => {
            // se há corrida na fila, segue direto para ela; senão volta ao painel
            api
              .getActiveRide()
              .then(({ ride }) => setActiveRideId(ride && ride.id !== activeRideId ? ride.id : null))
              .catch(() => setActiveRideId(null));
          }}
        />
      </TelaSegura>
    );
  }

  return (
    <TelaSegura>
      <DashboardScreen
        onOffer={(ride, expiresIn) => setOffer({ ride, expiresIn })}
        onOpenWallet={() => setShowWallet(true)}
        onOpenHistory={() => setShowHistory(true)}
        onOpenSupport={() => setShowSupport(true)}
      />
    </TelaSegura>
  );
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <SafeAreaProvider>
        <AuthProvider>
          <NavigationContainer>
            <StatusBar style="light" />
            <GpsObrigatorio>
              <MainApp />
            </GpsObrigatorio>
            <AvisoAtualizacao app="driver" />
          </NavigationContainer>
        </AuthProvider>
      </SafeAreaProvider>
    </QueryClientProvider>
  );
}
