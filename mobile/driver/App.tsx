import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Platform, View } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AuthProvider, useAuth } from './src/context/AuthContext';
import LoginScreen from './src/screens/LoginScreen';
import DashboardScreen from './src/screens/DashboardScreen';
import WalletScreen from './src/screens/WalletScreen';
import OfferScreen from './src/screens/OfferScreen';
import ActiveRideScreen from './src/screens/ActiveRideScreen';
import PendingApprovalScreen from './src/screens/PendingApprovalScreen';
import type { Ride, User } from '../shared/types';
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

  if (!user) return <LoginScreen />;

  if (driverNeedsApproval(user)) {
    return <PendingApprovalScreen />;
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

  if (offer) {
    return (
      <OfferScreen
        ride={offer.ride}
        expiresIn={offer.expiresIn}
        onAccepted={() => {
          setActiveRideId(offer.ride.id);
          setOffer(null);
        }}
        onDismiss={() => setOffer(null)}
      />
    );
  }

  if (activeRideId) {
    return <ActiveRideScreen rideId={activeRideId} onDone={() => setActiveRideId(null)} />;
  }

  return (
    <DashboardScreen
      onOffer={(ride, expiresIn) => setOffer({ ride, expiresIn })}
      onOpenWallet={() => setShowWallet(true)}
    />
  );
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <SafeAreaProvider>
        <AuthProvider>
          <NavigationContainer>
            <StatusBar style="light" />
            <MainApp />
          </NavigationContainer>
        </AuthProvider>
      </SafeAreaProvider>
    </QueryClientProvider>
  );
}
