import React, { useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { api } from '../../../shared/api';
import { theme } from '../../../shared/theme';
import type { Ride } from '../../../shared/types';

type Props = {
  ride: Ride;
  onDone: () => void;
};

export default function RatingScreen({ ride, onDone }: Props) {
  const [score, setScore] = useState(5);
  const [comment, setComment] = useState('');

  const submit = async () => {
    try {
      await api.rateRide(ride.id, score, comment);
      Alert.alert('Obrigado!', 'Avaliação enviada.');
      onDone();
    } catch (e: unknown) {
      Alert.alert('Erro', e instanceof Error ? e.message : 'Falha ao avaliar');
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Como foi a corrida?</Text>
      <View style={styles.stars}>
        {[1, 2, 3, 4, 5].map((n) => (
          <Pressable key={n} onPress={() => setScore(n)}>
            <Text style={[styles.star, n <= score && styles.starOn]}>★</Text>
          </Pressable>
        ))}
      </View>
      <TextInput
        style={styles.input}
        placeholder="Comentário (opcional)"
        value={comment}
        onChangeText={setComment}
      />
      <Pressable style={styles.button} onPress={submit}>
        <Text style={styles.buttonText}>Enviar avaliação</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg, padding: 24, justifyContent: 'center' },
  title: { fontSize: 22, fontWeight: '700', color: theme.ink, marginBottom: 16 },
  stars: { flexDirection: 'row', gap: 8, marginBottom: 16 },
  star: { fontSize: 36, color: theme.line },
  starOn: { color: theme.amberDark },
  input: {
    backgroundColor: theme.paper,
    borderRadius: 12,
    padding: 12,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: theme.line,
  },
  button: {
    backgroundColor: theme.amber,
    borderRadius: 13,
    padding: 14,
    alignItems: 'center',
  },
  buttonText: { color: theme.ink, fontWeight: '700' },
});
