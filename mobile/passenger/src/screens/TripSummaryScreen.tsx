import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import DriverCard from '../../../shared/DriverCard';
import { formatBrl } from '../../../shared/fare';
import { theme } from '../../../shared/theme';
import type { Ride } from '../../../shared/types';

type Props = {
  ride: Ride;
  onConfirm: () => void;
};

export default function TripSummaryScreen({ ride, onConfirm }: Props) {
  const fare = Number(ride.final_fare ?? ride.estimated_fare ?? 0);
  const distance = ride.distance_km != null ? `${Number(ride.distance_km).toFixed(1).replace('.', ',')} km` : '—';

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Corrida concluída</Text>
      <Text style={styles.subtitle}>Confira o resumo antes de avaliar</Text>

      <View style={styles.amountBox}>
        <Text style={styles.amountLabel}>Valor da corrida</Text>
        <Text style={styles.amount}>{formatBrl(fare)}</Text>
        <Text style={styles.paymentHint}>Pagamento confirmado no app</Text>
      </View>

      <View style={styles.row}>
        <Text style={styles.rowLabel}>Distância</Text>
        <Text style={styles.rowValue}>{distance}</Text>
      </View>

      {ride.destination_address ? (
        <View style={styles.row}>
          <Text style={styles.rowLabel}>Destino</Text>
          <Text style={styles.rowValue} numberOfLines={2}>
            {ride.destination_address}
          </Text>
        </View>
      ) : null}

      {ride.driver?.user ? <DriverCard ride={ride} /> : null}

      <Pressable style={styles.button} onPress={onConfirm}>
        <Text style={styles.buttonText}>Confirmar e avaliar</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg, padding: 24, justifyContent: 'center' },
  title: { fontSize: 24, fontWeight: '700', color: theme.ink, marginBottom: 4 },
  subtitle: { fontSize: 14, color: theme.textSecondary, marginBottom: 24 },
  amountBox: {
    backgroundColor: theme.paper,
    borderRadius: 16,
    padding: 20,
    marginBottom: 20,
    alignItems: 'center',
  },
  amountLabel: { fontSize: 13, color: theme.textSecondary, marginBottom: 6 },
  amount: { fontSize: 32, fontWeight: '800', color: theme.mint },
  paymentHint: { fontSize: 12, color: theme.textSecondary, marginTop: 8, textAlign: 'center' },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 10,
    gap: 12,
  },
  rowLabel: { fontSize: 14, color: theme.textSecondary, flex: 1 },
  rowValue: { fontSize: 14, fontWeight: '600', color: theme.ink, flex: 2, textAlign: 'right' },
  button: {
    marginTop: 24,
    backgroundColor: theme.amber,
    borderRadius: 13,
    padding: 16,
    alignItems: 'center',
  },
  buttonText: { color: theme.ink, fontWeight: '700', fontSize: 16 },
});
