import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { RideHistoryList } from '../../../shared/RideHistoryList';
import { theme } from '../../../shared/theme';

export default function HistoryScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Suas corridas</Text>
      <RideHistoryList papel="passenger" vazio="Você ainda não fez nenhuma corrida." />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg, padding: 16 },
  title: { fontSize: 20, fontWeight: '700', color: theme.ink, marginBottom: 12 },
});
