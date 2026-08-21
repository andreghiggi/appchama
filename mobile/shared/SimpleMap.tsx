import React from 'react';
import { Platform, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

type Region = {
  latitude: number;
  longitude: number;
  latitudeDelta?: number;
  longitudeDelta?: number;
};

type Props = {
  style?: StyleProp<ViewStyle>;
  region: Region;
  onRegionChangeComplete?: (region: Region) => void;
  children?: React.ReactNode;
  title?: string;
};

function WebMap({ style, region, title }: Props) {
  return (
    <View style={[styles.webMap, style]}>
      <Text style={styles.webTitle}>{title ?? 'Mapa (web)'}</Text>
      <Text style={styles.webCoords}>
        {region.latitude.toFixed(5)}, {region.longitude.toFixed(5)}
      </Text>
      <Text style={styles.webHint}>No navegador usamos coordenadas fixas/GPS do browser.</Text>
    </View>
  );
}

let NativeMap: React.ComponentType<any> | null = null;
let NativeMarker: React.ComponentType<any> | null = null;

if (Platform.OS !== 'web') {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const maps = require('react-native-maps');
  NativeMap = maps.default;
  NativeMarker = maps.Marker;
}

export function AppMap(props: Props) {
  if (Platform.OS === 'web' || !NativeMap) {
    return <WebMap {...props} />;
  }
  return <NativeMap {...props} />;
}

export function AppMarker(props: {
  coordinate: { latitude: number; longitude: number };
  title?: string;
}) {
  if (Platform.OS === 'web' || !NativeMarker) {
    return null;
  }
  return <NativeMarker {...props} />;
}

const styles = StyleSheet.create({
  webMap: {
    flex: 1,
    backgroundColor: '#D9E2EC',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 16,
  },
  webTitle: { fontSize: 16, fontWeight: '700', color: '#14213D', marginBottom: 8 },
  webCoords: { fontSize: 14, color: '#243B53', fontFamily: 'monospace' },
  webHint: { marginTop: 8, fontSize: 12, color: '#627D98', textAlign: 'center' },
});
