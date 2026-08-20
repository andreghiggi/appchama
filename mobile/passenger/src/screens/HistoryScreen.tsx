import React, { useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../../shared/api';
import { theme } from '../../../shared/theme';

export default function HistoryScreen() {
  const { data, isLoading, refetch } = useQuery({
    queryKey: ['rides'],
    queryFn: async () => {
      const res = await api.listRides();
      return res.data ?? [];
    },
  });

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Suas corridas</Text>
        <Pressable onPress={() => refetch()}>
          <Text style={styles.link}>Atualizar</Text>
        </Pressable>
      </View>
      {isLoading ? (
        <Text style={styles.empty}>Carregando...</Text>
      ) : (
        <FlatList
          data={data ?? []}
          keyExtractor={(item) => item.id}
          ListEmptyComponent={<Text style={styles.empty}>Nenhuma corrida ainda</Text>}
          renderItem={({ item }) => (
            <View style={styles.card}>
              <Text style={styles.cardTitle}>{item.destination_address ?? 'Destino'}</Text>
              <Text style={styles.cardMeta}>{item.status}</Text>
              <Text style={styles.cardMeta}>
                R$ {Number(item.final_fare ?? item.estimated_fare ?? 0).toFixed(2).replace('.', ',')}
              </Text>
            </View>
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg, padding: 16 },
  header: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 12 },
  title: { fontSize: 20, fontWeight: '700', color: theme.ink },
  link: { color: theme.mint, fontWeight: '600' },
  empty: { color: theme.textMuted, textAlign: 'center', marginTop: 40 },
  card: {
    backgroundColor: theme.paper,
    borderRadius: 14,
    padding: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: theme.line,
  },
  cardTitle: { fontWeight: '600', color: theme.ink },
  cardMeta: { color: theme.textSecondary, marginTop: 4, fontSize: 13 },
});
