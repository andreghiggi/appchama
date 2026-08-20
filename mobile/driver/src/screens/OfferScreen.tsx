import React, { useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { api } from '../../../shared/api';
import { theme } from '../../../shared/theme';
import type { Ride } from '../../../shared/types';

type Props = {
  ride: Ride;
  onDone: () => void;
};

export default function OfferScreen({ ride, onDone }: Props) {
  const [seconds, setSeconds] = useState(15);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const timer = setInterval(() => {
      setSeconds((s) => {
        if (s <= 1) {
          clearInterval(timer);
          onDone();
          return 0;
        }
        return s - 1;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, [onDone]);

  const accept = async () => {
    setLoading(true);
    try {
      await api.acceptRide(ride.id);
      onDone();
    } catch (e: unknown) {
      Alert.alert('Erro', e instanceof Error ? e.message : 'Não foi possível aceitar');
    } finally {
      setLoading(false);
    }
  };

  const decline = async () => {
    await api.declineRide(ride.id);
    onDone();
  };

  return (
    <View style={styles.wrap}>
      <View style={styles.card}>
        <Text style={styles.tag}>Nova solicitação</Text>
        <View style={styles.timerTrack}>
          <View style={[styles.timerFill, { width: `${(seconds / 15) * 100}%` }]} />
        </View>
        <Text style={styles.passenger}>{ride.passenger?.name ?? 'Passageiro'}</Text>
        <Text style={styles.detail}>Destino: {ride.destination_address}</Text>
        <Text style={styles.detail}>
          Valor estimado: R$ {Number(ride.estimated_fare ?? 0).toFixed(2).replace('.', ',')}
        </Text>
        <View style={styles.actions}>
          <Pressable style={styles.decline} onPress={decline}>
            <Text>Recusar</Text>
          </Pressable>
          <Pressable style={styles.accept} onPress={accept} disabled={loading}>
            {loading ? <ActivityIndicator color={theme.ink} /> : <Text style={styles.acceptText}>Aceitar</Text>}
          </Pressable>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flex: 1,
    backgroundColor: theme.ink,
    justifyContent: 'flex-end',
    padding: 20,
  },
  card: {
    backgroundColor: theme.paper,
    borderRadius: 20,
    padding: 20,
  },
  tag: { color: theme.amberDark, fontWeight: '700', fontSize: 11, marginBottom: 8 },
  timerTrack: { height: 6, backgroundColor: theme.bg, borderRadius: 6, overflow: 'hidden', marginBottom: 16 },
  timerFill: { height: '100%', backgroundColor: theme.amber },
  passenger: { fontSize: 16, fontWeight: '700', color: theme.ink },
  detail: { color: theme.textSecondary, marginTop: 6, fontSize: 13 },
  actions: { flexDirection: 'row', gap: 10, marginTop: 16 },
  decline: {
    flex: 1,
    borderWidth: 1,
    borderColor: theme.line,
    borderRadius: 13,
    padding: 13,
    alignItems: 'center',
  },
  accept: {
    flex: 1.4,
    backgroundColor: theme.amber,
    borderRadius: 13,
    padding: 13,
    alignItems: 'center',
  },
  acceptText: { fontWeight: '700', color: theme.ink },
});
