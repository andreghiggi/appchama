import React from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { theme } from './theme';

type Props = {
  onPress: () => void;
  loading?: boolean;
  /** Rótulo exibido ao lado do ícone (modo inline). */
  label?: string;
  /** floating = canto do mapa; inline = botão na linha de embarque. */
  variant?: 'floating' | 'inline';
  disabled?: boolean;
};

function CrosshairIcon() {
  return (
    <View style={styles.crosshair}>
      <View style={styles.crosshairRing} />
      <View style={styles.crosshairDot} />
      <View style={styles.crosshairH} />
      <View style={styles.crosshairV} />
    </View>
  );
}

export function MapLocateButton({
  onPress,
  loading,
  label,
  variant = 'floating',
  disabled,
}: Props) {
  const isInline = variant === 'inline';

  return (
    <Pressable
      style={[
        isInline ? styles.inlineBtn : styles.floatingBtn,
        (loading || disabled) && styles.btnDisabled,
      ]}
      onPress={onPress}
      disabled={loading || disabled}
      accessibilityRole="button"
      accessibilityLabel={label ?? 'Usar minha localização'}
    >
      {loading ? (
        <ActivityIndicator size="small" color={isInline ? theme.mint : theme.ink} />
      ) : (
        <>
          <CrosshairIcon />
          {label ? (
            <Text style={styles.inlineLabel} numberOfLines={1}>
              {label}
            </Text>
          ) : null}
        </>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  floatingBtn: {
    position: 'absolute',
    right: 12,
    bottom: 24,
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: theme.mint,
    shadowColor: theme.mint,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 10,
    elevation: 6,
    zIndex: 600,
  },
  inlineBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    alignSelf: 'flex-start',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 10,
    backgroundColor: theme.mintBg,
    borderWidth: 1.5,
    borderColor: theme.mint,
    marginBottom: 10,
  },
  btnDisabled: {
    opacity: 0.65,
  },
  inlineLabel: {
    fontSize: 13,
    fontWeight: '700',
    color: '#0B6E62',
    flexShrink: 1,
  },
  crosshair: {
    width: 20,
    height: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  crosshairRing: {
    position: 'absolute',
    width: 16,
    height: 16,
    borderRadius: 8,
    borderWidth: 2,
    borderColor: theme.mint,
  },
  crosshairDot: {
    width: 4,
    height: 4,
    borderRadius: 2,
    backgroundColor: theme.mint,
  },
  crosshairH: {
    position: 'absolute',
    width: 6,
    height: 2,
    backgroundColor: theme.mint,
    left: -1,
  },
  crosshairV: {
    position: 'absolute',
    width: 2,
    height: 6,
    backgroundColor: theme.mint,
    top: -1,
  },
});
