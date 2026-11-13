import React, { useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { LogoutButton } from '../components/LogoutButton';
import { api } from '../../../shared/api';
import { theme } from '../../../shared/theme';
import { useAuth } from '../context/AuthContext';

export default function RegistrationCodeScreen() {
  const { user, logout, refreshUser } = useAuth();
  const [code, setCode] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const generatedCode = user?.driver?.registration_code ?? '';

  const confirm = async () => {
    setLoading(true);
    setError(null);
    try {
      await api.confirmRegistrationCode(code.trim().toUpperCase());
      await refreshUser();
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Código inválido');
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.container}>
      <View style={styles.topBar}>
        <LogoutButton onPress={logout} />
      </View>
      <Text style={styles.title}>Código de cadastro</Text>
      <Text style={styles.text}>
        Olá, {user?.name?.split(' ')[0] ?? 'motorista'}. Guarde o código abaixo e informe à equipe
        Chama para liberar seu acesso.
      </Text>

      <View style={styles.codeBox}>
        <Text style={styles.codeLabel}>Seu código</Text>
        <Text style={styles.codeValue}>{generatedCode || '—'}</Text>
      </View>

      <Text style={styles.text}>
        Para confirmar que você anotou o código, digite-o abaixo:
      </Text>
      <TextInput
        style={styles.input}
        placeholder="CHM-XXXXXX"
        value={code}
        onChangeText={setCode}
        autoCapitalize="characters"
        maxLength={12}
      />
      <Pressable style={styles.button} onPress={confirm} disabled={loading || code.trim().length < 6}>
        {loading ? (
          <ActivityIndicator color={theme.ink} />
        ) : (
          <Text style={styles.buttonText}>Confirmar código</Text>
        )}
      </Pressable>

      {error ? <Text style={styles.error}>{error}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg, padding: 24, justifyContent: 'center' },
  topBar: { position: 'absolute', top: 16, right: 16, zIndex: 10 },
  title: { fontSize: 22, fontWeight: '700', color: theme.ink, marginBottom: 12 },
  text: { fontSize: 15, color: theme.textSecondary, lineHeight: 22, marginBottom: 16 },
  codeBox: {
    backgroundColor: theme.ink,
    borderRadius: 16,
    padding: 20,
    alignItems: 'center',
    marginBottom: 20,
  },
  codeLabel: { fontSize: 12, color: theme.mintBg, fontWeight: '600', marginBottom: 8 },
  codeValue: { fontSize: 28, fontWeight: '800', color: theme.amber, letterSpacing: 2 },
  input: {
    backgroundColor: theme.paper,
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
    fontSize: 18,
    letterSpacing: 1,
    textAlign: 'center',
  },
  button: { backgroundColor: theme.amber, borderRadius: 13, padding: 16, alignItems: 'center' },
  buttonText: { color: theme.ink, fontWeight: '700' },
  error: { color: '#DC2626', fontSize: 13, marginTop: 12, textAlign: 'center' },
});
