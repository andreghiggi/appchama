import React, { useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { api } from '../../../shared/api';
import { theme } from '../../../shared/theme';
import { useAuth } from '../context/AuthContext';

export default function LoginScreen() {
  const { login } = useAuth();
  const [phone, setPhone] = useState('5511999990002');
  const [name, setName] = useState('Marina Costa');
  const [code, setCode] = useState('');
  const [step, setStep] = useState<'phone' | 'code'>('phone');
  const [loading, setLoading] = useState(false);

  const sendOtp = async () => {
    setLoading(true);
    try {
      await api.sendOtp(phone);
      setStep('code');
      Alert.alert('OTP enviado', 'Em dev, veja storage/logs/laravel.log no backend.');
    } catch (e: unknown) {
      Alert.alert('Erro', e instanceof Error ? e.message : 'Falha ao enviar OTP');
    } finally {
      setLoading(false);
    }
  };

  const verify = async () => {
    setLoading(true);
    try {
      const result = await api.verifyOtp(phone, code, name, 'passenger');
      await login(result.token, result.user);
    } catch (e: unknown) {
      Alert.alert('Erro', e instanceof Error ? e.message : 'Código inválido');
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Chama</Text>
      <Text style={styles.subtitle}>App do passageiro</Text>

      {step === 'phone' ? (
        <>
          <TextInput
            style={styles.input}
            placeholder="Telefone"
            value={phone}
            onChangeText={setPhone}
            keyboardType="phone-pad"
          />
          <TextInput style={styles.input} placeholder="Nome" value={name} onChangeText={setName} />
          <Pressable style={styles.button} onPress={sendOtp} disabled={loading}>
            {loading ? <ActivityIndicator color={theme.ink} /> : <Text style={styles.buttonText}>Enviar código</Text>}
          </Pressable>
        </>
      ) : (
        <>
          <TextInput
            style={styles.input}
            placeholder="Código OTP"
            value={code}
            onChangeText={setCode}
            keyboardType="number-pad"
            maxLength={6}
          />
          <Pressable style={styles.button} onPress={verify} disabled={loading}>
            {loading ? <ActivityIndicator color={theme.ink} /> : <Text style={styles.buttonText}>Entrar</Text>}
          </Pressable>
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg, padding: 24, justifyContent: 'center' },
  title: { fontSize: 28, fontWeight: '700', color: theme.ink, marginBottom: 4 },
  subtitle: { fontSize: 14, color: theme.textSecondary, marginBottom: 24 },
  input: {
    backgroundColor: theme.paper,
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.line,
  },
  button: {
    backgroundColor: theme.amber,
    borderRadius: 13,
    padding: 16,
    alignItems: 'center',
  },
  buttonText: { color: theme.ink, fontWeight: '700', fontSize: 16 },
});
