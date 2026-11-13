import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { api } from '../../../shared/api';
import { storage } from '../../../shared/storage';
import { APP_NAME, theme } from '../../../shared/theme';
import { BrandFooter, BrandHeader } from '../../../shared/BrandHeader';
import { useAuth } from '../context/AuthContext';
import {
  DRIVER_STORAGE_KEYS,
  type DriverLoginMode,
} from '../lib/driverStorage';
import RegisterScreen from './RegisterScreen';

export default function LoginScreen() {
  const { login } = useAuth();
  const [screen, setScreen] = useState<'login' | 'register'>('login');
  const [loginMode, setLoginMode] = useState<DriverLoginMode>('password');
  const [phone, setPhone] = useState('');
  const [secret, setSecret] = useState('');
  const [remember, setRemember] = useState(true);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      const savedRemember = await storage.getItem(DRIVER_STORAGE_KEYS.remember);
      if (savedRemember !== '1') return;

      const savedPhone = await storage.getItem(DRIVER_STORAGE_KEYS.phone);
      const savedMode = await storage.getItem(DRIVER_STORAGE_KEYS.loginMode);
      const savedSecret = await storage.getItem(DRIVER_STORAGE_KEYS.secret);

      if (savedPhone) setPhone(savedPhone);
      if (savedMode === 'password' || savedMode === 'code') setLoginMode(savedMode);
      if (savedSecret) setSecret(savedSecret);
      setRemember(true);
    })();
  }, []);

  const persistCredentials = async () => {
    if (remember) {
      await storage.setItem(DRIVER_STORAGE_KEYS.remember, '1');
      await storage.setItem(DRIVER_STORAGE_KEYS.phone, phone.trim());
      await storage.setItem(DRIVER_STORAGE_KEYS.loginMode, loginMode);
      await storage.setItem(DRIVER_STORAGE_KEYS.secret, secret);
    } else {
      await storage.deleteItem(DRIVER_STORAGE_KEYS.remember);
      await storage.deleteItem(DRIVER_STORAGE_KEYS.phone);
      await storage.deleteItem(DRIVER_STORAGE_KEYS.loginMode);
      await storage.deleteItem(DRIVER_STORAGE_KEYS.secret);
    }
  };

  const submit = async () => {
    setLoading(true);
    setError(null);
    try {
      const result =
        loginMode === 'password'
          ? await api.driverLoginWithPassword(phone.trim(), secret)
          : await api.driverLogin(phone.trim(), secret.trim().toUpperCase());

      await persistCredentials();
      await login(result.token, result.user);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Não foi possível entrar');
    } finally {
      setLoading(false);
    }
  };

  if (screen === 'register') {
    return <RegisterScreen onBack={() => setScreen('login')} />;
  }

  const canSubmit =
    phone.trim().length >= 10 &&
    (loginMode === 'password' ? secret.length >= 6 : secret.trim().length >= 6);

  return (
    <View style={styles.container}>
      <BrandHeader title={APP_NAME} dark />
      <Text style={styles.subtitle}>
        {loginMode === 'password'
          ? 'Entre com telefone e senha definidos no seu cadastro pelo app.'
          : 'Entre com telefone e código CHM fornecido pelo administrador.'}
      </Text>

      <View style={styles.modeRow}>
        <Pressable
          style={[styles.modeBtn, loginMode === 'password' && styles.modeBtnActive]}
          onPress={() => {
            setLoginMode('password');
            setSecret('');
            setError(null);
          }}
        >
          <Text style={[styles.modeBtnText, loginMode === 'password' && styles.modeBtnTextActive]}>
            Cadastro app
          </Text>
        </Pressable>
        <Pressable
          style={[styles.modeBtn, loginMode === 'code' && styles.modeBtnActive]}
          onPress={() => {
            setLoginMode('code');
            setSecret('');
            setError(null);
          }}
        >
          <Text style={[styles.modeBtnText, loginMode === 'code' && styles.modeBtnTextActive]}>
            Cadastro admin
          </Text>
        </Pressable>
      </View>

      <TextInput
        style={styles.input}
        placeholder="Telefone (5511999990000)"
        value={phone}
        onChangeText={setPhone}
        keyboardType="phone-pad"
      />
      <TextInput
        style={styles.input}
        placeholder={loginMode === 'password' ? 'Senha' : 'Código (CHM-XXXXXX)'}
        value={secret}
        onChangeText={setSecret}
        secureTextEntry={loginMode === 'password'}
        autoCapitalize={loginMode === 'code' ? 'characters' : 'none'}
        maxLength={loginMode === 'code' ? 12 : undefined}
      />

      <Pressable style={styles.rememberRow} onPress={() => setRemember((v) => !v)}>
        <View style={[styles.checkbox, remember && styles.checkboxChecked]} />
        <Text style={styles.rememberText}>Lembrar meus dados</Text>
      </Pressable>

      <Pressable style={styles.button} onPress={submit} disabled={loading || !canSubmit}>
        {loading ? (
          <ActivityIndicator color={theme.ink} />
        ) : (
          <Text style={styles.buttonText}>Entrar</Text>
        )}
      </Pressable>

      <Pressable style={styles.linkBtn} onPress={() => setScreen('register')}>
        <Text style={styles.linkText}>Ainda não tenho conta — criar cadastro</Text>
      </Pressable>

      {error ? <Text style={styles.error}>{error}</Text> : null}
      <BrandFooter dark />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.ink, padding: 24, justifyContent: 'center' },
  title: { fontSize: 24, fontWeight: '700', color: '#fff', marginBottom: 8 },
  subtitle: { fontSize: 14, color: theme.mintBg, lineHeight: 20, marginBottom: 20 },
  modeRow: { flexDirection: 'row', gap: 8, marginBottom: 12 },
  modeBtn: {
    flex: 1,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: theme.mintBg,
    paddingVertical: 10,
    alignItems: 'center',
  },
  modeBtnActive: { backgroundColor: theme.amber, borderColor: theme.amber },
  modeBtnText: { color: theme.mintBg, fontSize: 13, fontWeight: '600' },
  modeBtnTextActive: { color: theme.ink },
  input: { backgroundColor: theme.paper, borderRadius: 12, padding: 14, marginBottom: 12 },
  rememberRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 16 },
  checkbox: {
    width: 20,
    height: 20,
    borderRadius: 4,
    borderWidth: 2,
    borderColor: theme.amber,
  },
  checkboxChecked: { backgroundColor: theme.amber },
  rememberText: { color: theme.mintBg, fontSize: 14 },
  button: { backgroundColor: theme.amber, borderRadius: 13, padding: 16, alignItems: 'center' },
  buttonText: { color: theme.ink, fontWeight: '700' },
  linkBtn: { marginTop: 16, alignItems: 'center' },
  linkText: { color: theme.amber, fontWeight: '600' },
  error: { color: '#FCA5A5', fontSize: 13, marginTop: 12, textAlign: 'center' },
});
