import React from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import { theme } from './theme';

type Props = {
  onPress: () => void;
  loading?: boolean;
};

export function MapLocateButton({ onPress, loading }: Props) {
  return (
    <Pressable style={styles.btn} onPress={onPress} disabled={loading}>
      {loading ? (
        <ActivityIndicator size="small" color={theme.ink} />
      ) : (
        <View style={styles.crosshair}>
          <View style={styles.crosshairRing} />
          <View style={styles.crosshairDot} />
          <View style={styles.crosshairH} />
          <View style={styles.crosshairV} />
        </View>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  btn: {
    position: 'absolute',
    right: 12,
    bottom: 24,
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.15,
    shadowRadius: 8,
    elevation: 4,
    zIndex: 600,
  },
  crosshair: {
    width: 22,
    height: 22,
    alignItems: 'center',
    justifyContent: 'center',
  },
  crosshairRing: {
    position: 'absolute',
    width: 18,
    height: 18,
    borderRadius: 9,
    borderWidth: 2,
    borderColor: theme.ink,
  },
  crosshairDot: {
    width: 4,
    height: 4,
    borderRadius: 2,
    backgroundColor: theme.ink,
  },
  crosshairH: {
    position: 'absolute',
    width: 6,
    height: 2,
    backgroundColor: theme.ink,
    left: -1,
  },
  crosshairV: {
    position: 'absolute',
    width: 2,
    height: 6,
    backgroundColor: theme.ink,
    top: -1,
  },
});
