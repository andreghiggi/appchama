import React, { useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StatusBar } from 'expo-status-bar';
import { AuthProvider, useAuth } from './src/context/AuthContext';
import LoginScreen from './src/screens/LoginScreen';
import HomeScreen from './src/screens/HomeScreen';
import RideScreen from './src/screens/RideScreen';
import HistoryScreen from './src/screens/HistoryScreen';
import RatingScreen from './src/screens/RatingScreen';
import type { Ride } from '../shared/types';
import { theme } from '../shared/theme';

const Tab = createBottomTabNavigator();
const queryClient = new QueryClient();

function MainApp() {
  const { user, loading, logout } = useAuth();
  const [activeRide, setActiveRide] = useState<Ride | null>(null);
  const [ratingRide, setRatingRide] = useState<Ride | null>(null);

  if (loading) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" color={theme.amber} />
      </View>
    );
  }

  if (!user) {
    return <LoginScreen />;
  }

  if (ratingRide) {
    return <RatingScreen ride={ratingRide} onDone={() => setRatingRide(null)} />;
  }

  if (activeRide) {
    return (
      <RideScreen
        ride={activeRide}
        onFinish={() => setActiveRide(null)}
        onRate={(ride) => {
          setActiveRide(null);
          setRatingRide(ride);
        }}
      />
    );
  }

  return (
    <Tab.Navigator
      screenOptions={{
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
      <AuthProvider>
        <NavigationContainer>
          <StatusBar style="light" />
          <MainApp />
        </NavigationContainer>
      </AuthProvider>
    </QueryClientProvider>
  );
}
