import React, { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { api } from '../../../shared/api';
import { stopOfferAlert } from '../../../shared/notify';
import { theme } from '../../../shared/theme';
import type { Ride } from '../../../shared/types';

type Props = {
  ride: Ride;
  expiresIn: number;
  onAccepted: () => void;
  onDismiss: () => void;
};

export default function OfferScreen({ ride, expiresIn, onAccepted, onDismiss }: Props) {
  const total = Math.max(1, expiresIn);
  const [seconds, setSeconds] = useState(total);
  const [loading, setLoading] = useState(false);
  const dismissedRef = useRef(false);
  const deadlineRef = useRef(Date.now() + total * 1000);

  useEffect(() => () => stopOfferAlert(), []);

  // Conta pelo relógio, e não por ticks acumulados, para que uma aba congelada
  // em segundo plano não mostre um tempo restante maior do que o real.
  useEffect(() => {
    const tick = () => {
      const remaining = Math.ceil((deadlineRef.current - Date.now()) / 1000);

      if (remaining <= 0) {
        if (!dismissedRef.current) {
          dismissedRef.current = true;
          onDismiss();
        }
        setSeconds(0);
        return;
      }

      setSeconds(remaining);
    };

    tick();
    const timer = setInterval(tick, 500);

    return () => clearInterval(timer);
  }, [ride.id, onDismiss]);

  const accept = async () => {
    setLoading(true);
    dismissedRef.current = true;
    stopOfferAlert();
    try {
      await api.acceptRide(ride.id);
      onAccepted();
    } catch (e: unknown) {
      dismissedRef.current = false;
      Alert.alert('Erro', e instanceof Error ? e.message : 'Não foi possível aceitar');
      onDismiss();
    } finally {
      setLoading(false);
    }
  };

  const decline = async () => {
    dismissedRef.current = true;
    stopOfferAlert();
    await api.declineRide(ride.id).catch(() => undefined);
    onDismiss();
  };

  return (
    <View style={styles.wrap}>
      <View style={styles.card}>
        <Text style={styles.tag}>Nova solicitação · {seconds}s</Text>
        <View style={styles.timerTrack}>
          <View style={[styles.timerFill, { width: `${(seconds / total) * 100}%` }]} />
        </View>
        <Text style={styles.passenger}>{ride.passenger?.name ?? 'Passageiro'}</Text>
        <Text style={styles.detail}>Embarque: {ride.origin_address ?? 'Próximo a você'}</Text>
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
    minHeight: '100%' as unknown as number,
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
