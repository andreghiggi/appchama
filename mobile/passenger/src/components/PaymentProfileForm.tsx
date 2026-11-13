import React, { useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { api } from '../../../shared/api';
import { theme } from '../../../shared/theme';
import type { User } from '../../../shared/types';

type Props = {
  user: User;
  onSaved: (user: User) => void;
};

function onlyDigits(value: string, max?: number): string {
  const digits = value.replace(/\D/g, '');
  return max ? digits.slice(0, max) : digits;
}

export default function PaymentProfileForm({ user, onSaved }: Props) {
  const [cpf, setCpf] = useState(user.cpf ?? '');
  const [email, setEmail] = useState(user.email ?? '');
  const [address, setAddress] = useState(user.home_address ?? '');
  const [number, setNumber] = useState(user.home_address_number ?? '');
  const [postalCode, setPostalCode] = useState(user.home_postal_code ?? '');
  const [city, setCity] = useState(user.home_city ?? 'Guaporé');
  const [state, setState] = useState(user.home_state ?? 'RS');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const save = async () => {
    setLoading(true);
    setError(null);
    try {
      const updated = await api.updateProfile({
        cpf: onlyDigits(cpf, 11),
        email: email.trim(),
        home_address: address.trim(),
        home_address_number: number.trim(),
        home_postal_code: onlyDigits(postalCode, 8),
        home_city: city.trim(),
        home_state: state.trim().toUpperCase(),
        home_lat: user.home_lat != null ? Number(user.home_lat) : undefined,
        home_lng: user.home_lng != null ? Number(user.home_lng) : undefined,
      });
      onSaved(updated);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Não foi possível salvar');
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.box}>
      <Text style={styles.title}>Dados para pagamento</Text>
      <Text style={styles.subtitle}>
        Informe CPF, e-mail e endereço completos para pagar com Pix ou cartão.
      </Text>

      <TextInput
        style={styles.input}
        placeholder="CPF (somente números)"
        value={cpf}
        onChangeText={(v) => setCpf(onlyDigits(v, 11))}
        keyboardType="number-pad"
        maxLength={11}
      />
      <TextInput
        style={styles.input}
        placeholder="E-mail"
        value={email}
        onChangeText={setEmail}
        keyboardType="email-address"
        autoCapitalize="none"
      />
      <TextInput
        style={styles.input}
        placeholder="Endereço (rua/avenida)"
        value={address}
        onChangeText={setAddress}
      />
      <TextInput
        style={styles.input}
        placeholder="Número"
        value={number}
        onChangeText={setNumber}
      />
      <TextInput
        style={styles.input}
        placeholder="CEP (somente números)"
        value={postalCode}
        onChangeText={(v) => setPostalCode(onlyDigits(v, 8))}
        keyboardType="number-pad"
        maxLength={8}
      />
      <View style={styles.row}>
        <TextInput
          style={[styles.input, styles.flex]}
          placeholder="Cidade"
          value={city}
          onChangeText={setCity}
        />
        <TextInput
          style={[styles.input, styles.uf]}
          placeholder="UF"
          value={state}
          onChangeText={(v) => setState(v.toUpperCase())}
          maxLength={2}
          autoCapitalize="characters"
        />
      </View>

      <Pressable style={styles.button} onPress={save} disabled={loading}>
        {loading ? (
          <ActivityIndicator color={theme.ink} />
        ) : (
          <Text style={styles.buttonText}>Salvar e continuar</Text>
        )}
      </Pressable>
      {error ? <Text style={styles.error}>{error}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  box: {
    backgroundColor: theme.paper,
    borderRadius: 16,
    padding: 16,
    marginBottom: 20,
  },
  title: { fontSize: 18, fontWeight: '700', color: theme.ink, marginBottom: 6 },
  subtitle: { fontSize: 13, color: theme.textSecondary, marginBottom: 12, lineHeight: 18 },
  input: {
    backgroundColor: theme.bg,
    borderRadius: 10,
    padding: 12,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: theme.line,
  },
  row: { flexDirection: 'row', gap: 8 },
  flex: { flex: 1 },
  uf: { width: 64 },
  button: {
    backgroundColor: theme.amber,
    borderRadius: 12,
    padding: 14,
    alignItems: 'center',
    marginTop: 4,
  },
  buttonText: { color: theme.ink, fontWeight: '700' },
  error: { color: theme.danger, marginTop: 10, textAlign: 'center', fontSize: 13 },
});
