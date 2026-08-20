import React, { useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StatusBar } from 'expo-status-bar';
import { AuthProvider, useAuth } from './src/context/AuthContext';
import LoginScreen from './src/screens/LoginScreen';
import DashboardScreen from './src/screens/DashboardScreen';
import OfferScreen from './src/screens/OfferScreen';
import ActiveRideScreen from './src/screens/ActiveRideScreen';
import type { Ride } from '../shared/types';
import { theme } from '../shared/theme';

const Stack = createNativeStackNavigator();
const queryClient = new QueryClient();

function MainApp() {
  const { user, loading, logout } = useAuth();
  const [offer, setOffer] = useState<Ride | null>(null);
  const [activeRideId, setActiveRideId] = useState<string | null>(null);

  if (loading) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" color={theme.amber} />
      </View>
    );
  }

  if (!user) return <LoginScreen />;

  if (offer) {
    return (
      <OfferScreen
        ride={offer}
        onDone={() => {
          setOffer(null);
          setActiveRideId(offer.id);
        }}
      />
    );
  }

  if (activeRideId) {
    return <ActiveRideScreen rideId={activeRideId} onDone={() => setActiveRideId(null)} />;
  }

  return <DashboardScreen onOffer={setOffer} />;
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <NavigationContainer>
          <StatusBar style="light" />
          <MainApp />
        </NavigationContainer>
      </AuthProvider>
    </QueryClientProvider>
  );
}
