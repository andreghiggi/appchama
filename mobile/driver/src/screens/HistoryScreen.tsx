import React, { useEffect } from 'react';
import { BackHandler, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { RideHistoryList } from '../../../shared/RideHistoryList';
import { theme } from '../../../shared/theme';

type Props = { onBack: () => void };

export default function HistoryScreen({ onBack }: Props) {
  const insets = useSafeAreaInsets();

  // Botão voltar do Android também sai do histórico.
  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      onBack();
      return true;
    });
    return () => sub.remove();
  }, [onBack]);

  return (
    <View style={[styles.container, { paddingTop: 16 + insets.top, paddingBottom: 16 + insets.bottom }]}>
      <Pressable onPress={onBack} style={styles.back} hitSlop={16}>
        <Text style={styles.backText}>← Voltar</Text>
      </Pressable>
      <Text style={styles.title}>Minhas corridas</Text>
      <RideHistoryList papel="driver" vazio="Você ainda não fez nenhuma corrida." />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg, paddingHorizontal: 16 },
  back: { paddingVertical: 8, alignSelf: 'flex-start' },
  backText: { color: theme.ink, fontWeight: '600' },
  title: { fontSize: 24, fontWeight: '700', color: theme.ink, marginVertical: 12 },
});
