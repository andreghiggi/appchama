import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Platform, View } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { TelaSegura } from '../shared/TelaSegura';
import { AuthProvider, useAuth } from './src/context/AuthContext';
import LoginScreen from './src/screens/LoginScreen';
import HomeScreen from './src/screens/HomeScreen';
import RideScreen from './src/screens/RideScreen';
import HistoryScreen from './src/screens/HistoryScreen';
import TripSummaryScreen from './src/screens/TripSummaryScreen';
import PaymentScreen from './src/screens/PaymentScreen';
import RatingScreen from './src/screens/RatingScreen';
import type { Ride } from '../shared/types';
import { theme } from '../shared/theme';
import { useActiveRideRecovery } from '../shared/useActiveRide';
import { registrarPush } from '../shared/push';

const Tab = createBottomTabNavigator();
const queryClient = new QueryClient();

function MainApp() {
  const { user, loading, logout } = useAuth();

  // Registra o aparelho para receber notificações (nova corrida, motorista chegou, cancelamento...).
  useEffect(() => {
    if (user) void registrarPush();
  }, [user?.id]);
  const [activeRide, setActiveRide] = useState<Ride | null>(null);
  const [paymentRide, setPaymentRide] = useState<Ride | null>(null);
  const [summaryRide, setSummaryRide] = useState<Ride | null>(null);
  const [ratingRide, setRatingRide] = useState<Ride | null>(null);

  const { restoredRide, restoring } = useActiveRideRecovery(Boolean(user));

  useEffect(() => {
    if (!restoredRide) return;
    if (restoredRide.status === 'awaiting_payment') {
      setPaymentRide((current) => current ?? restoredRide);
      return;
    }
    setActiveRide((current) => current ?? restoredRide);
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

  if (restoring) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" color={theme.amber} />
      </View>
    );
  }

  if (ratingRide) {
    return (
      <TelaSegura>
        <RatingScreen ride={ratingRide} onDone={() => setRatingRide(null)} />
      </TelaSegura>
    );
  }

  if (paymentRide) {
    return (
      <TelaSegura>
        <PaymentScreen
          ride={paymentRide}
          onPaid={(ride) => {
            setPaymentRide(null);
            setSummaryRide(ride);
          }}
        />
      </TelaSegura>
    );
  }

  if (summaryRide) {
    return (
      <TelaSegura>
        <TripSummaryScreen
          ride={summaryRide}
          onConfirm={() => {
            setRatingRide(summaryRide);
            setSummaryRide(null);
          }}
        />
      </TelaSegura>
    );
  }

  if (activeRide) {
    return (
      <TelaSegura>
        <RideScreen
          ride={activeRide}
          onFinish={() => setActiveRide(null)}
          onCompleted={(ride) => {
            setActiveRide(null);
            if (ride.status === 'awaiting_payment') {
              setPaymentRide(ride);
            } else {
              setSummaryRide(ride);
            }
          }}
          onAwaitingPayment={(ride) => {
            setActiveRide(null);
            setPaymentRide(ride);
          }}
        />
      </TelaSegura>
    );
  }

  return (
    <Tab.Navigator
      screenOptions={{
        tabBarHideOnKeyboard: true,
        headerStyle: { backgroundColor: theme.ink },
        headerTintColor: '#fff',
        tabBarActiveTintColor: theme.ink,
      }}
    >
      <Tab.Screen name="Início" options={{ title: `Olá, ${user.name.split(' ')[0]}` }}>
        {() => <HomeScreen onRideActive={setActiveRide} />}
      </Tab.Screen>
      <Tab.Screen name="Corridas" component={HistoryScreen} />
      <Tab.Screen name="Sair" listeners={{ tabPress: (e) => { e.preventDefault(); logout(); } }}>
        {() => null}
      </Tab.Screen>
    </Tab.Navigator>
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
