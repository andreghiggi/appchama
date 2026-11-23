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
  /** Campos que ainda faltam no cadastro; so eles aparecem. Vazio/ausente = todos. */
  missing?: string[];
  onSaved: (user: User) => void;
};

function onlyDigits(value: string, max?: number): string {
  const digits = value.replace(/\D/g, '');
  return max ? digits.slice(0, max) : digits;
}

export default function PaymentProfileForm({ user, missing, onSaved }: Props) {
  const falta = (campo: string) => !missing || missing.length === 0 || missing.includes(campo);
  const [name, setName] = useState(user.name ?? '');
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
      if (falta('name') && name.trim().split(/\s+/).filter(Boolean).length < 2) {
        setError('Informe nome e sobrenome.');
        setLoading(false);
        return;
      }
      const updated = await api.updateProfile({
        ...(falta('name') ? { name: name.trim() } : {}),
        ...(falta('cpf') ? { cpf: onlyDigits(cpf, 11) } : {}),
        ...(falta('email') ? { email: email.trim() } : {}),
        ...(falta('home_address') ? { home_address: address.trim() } : {}),
        ...(falta('home_address_number') ? { home_address_number: number.trim() } : {}),
        ...(falta('home_postal_code') ? { home_postal_code: onlyDigits(postalCode, 8) } : {}),
        ...(falta('home_city') ? { home_city: city.trim() } : {}),
        ...(falta('home_state') ? { home_state: state.trim().toUpperCase() } : {}),
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
        {missing && missing.length ? 'Falta só isto no seu cadastro para pagar:' : 'Informe nome completo, CPF, e-mail e endereço para pagar com Pix ou cartão.'}
      </Text>

{falta('name') ? (
      <TextInput
        style={styles.input}
        placeholder="Nome completo (nome e sobrenome)"
        value={name}
        onChangeText={setName}
        autoCapitalize="words"
      />
      ) : null}
{falta('cpf') ? (
      <TextInput
        style={styles.input}
        placeholder="CPF (somente números)"
        value={cpf}
        onChangeText={(v) => setCpf(onlyDigits(v, 11))}
        keyboardType="number-pad"
        maxLength={11}
      />
      ) : null}
{falta('email') ? (
      <TextInput
        style={styles.input}
        placeholder="E-mail"
        value={email}
        onChangeText={setEmail}
        keyboardType="email-address"
        autoCapitalize="none"
      />
      ) : null}
{falta('home_address') ? (
      <TextInput
        style={styles.input}
        placeholder="Endereço (rua/avenida)"
        value={address}
        onChangeText={setAddress}
      />
      ) : null}
{falta('home_address_number') ? (
      <TextInput
        style={styles.input}
        placeholder="Número"
        value={number}
        onChangeText={setNumber}
      />
      ) : null}
{falta('home_postal_code') ? (
      <TextInput
        style={styles.input}
        placeholder="CEP (somente números)"
        value={postalCode}
        onChangeText={(v) => setPostalCode(onlyDigits(v, 8))}
        keyboardType="number-pad"
        maxLength={8}
      />
      ) : null}
{falta('home_city') || falta('home_state') ? (
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
      ) : null}

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
