import React from 'react';
import { Alert, Platform, Pressable, StyleSheet, Text } from 'react-native';
import { theme } from '../../../shared/theme';

type Props = {
  onPress: () => void | Promise<void>;
  compact?: boolean;
  inverted?: boolean;
};

export function confirmLogout(onConfirm: () => void | Promise<void>): void {
  const run = () => {
    void onConfirm();
  };

  if (Platform.OS === 'web' && typeof window !== 'undefined') {
    if (window.confirm('Deseja sair da conta neste dispositivo?')) {
      run();
    }

    return;
  }

  Alert.alert('Sair da conta', 'Deseja encerrar a sessão neste dispositivo?', [
    { text: 'Cancelar', style: 'cancel' },
    { text: 'Sair', style: 'destructive', onPress: run },
  ]);
}

export function LogoutButton({ onPress, compact = false, inverted = false }: Props) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel="Sair da conta"
      hitSlop={8}
      onPress={() => confirmLogout(onPress)}
      style={[
        styles.button,
        compact && styles.buttonCompact,
        inverted && styles.buttonInverted,
      ]}
    >
      <Text style={[styles.label, compact && styles.labelCompact, inverted && styles.labelInverted]}>
        Sair
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  button: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 999,
    backgroundColor: 'rgba(255, 255, 255, 0.14)',
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.25)',
  },
  buttonCompact: {
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  label: {
    color: '#fff',
    fontSize: 13,
    fontWeight: '600',
  },
  labelCompact: {
    fontSize: 12,
  },
  buttonInverted: {
    backgroundColor: theme.ink,
    borderColor: theme.ink,
  },
  labelInverted: {
    color: '#fff',
  },
});
