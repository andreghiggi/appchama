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
import { requestCurrentLocation } from '../../../shared/location';
import { DEFAULT_CITY, DEFAULT_STATE, theme } from '../../../shared/theme';
import { BrandFooter, BrandHeader } from '../../../shared/BrandHeader';
import { useAuth } from '../context/AuthContext';

type Mode = 'register' | 'login';
type Step = 'form' | 'code';

function onlyDigits(value: string, max?: number): string {
  const digits = value.replace(/\D/g, '');
  return max ? digits.slice(0, max) : digits;
}

export default function LoginScreen() {
  const { login } = useAuth();
  const [mode, setMode] = useState<Mode>('register');
  const [step, setStep] = useState<Step>('form');
  const [name, setName] = useState('');
  const [cpf, setCpf] = useState('');
  const [email, setEmail] = useState('');
  const [address, setAddress] = useState('');
  const [addressNumber, setAddressNumber] = useState('');
  const [postalCode, setPostalCode] = useState('');
  const [city, setCity] = useState(DEFAULT_CITY);
  const [state, setState] = useState(DEFAULT_STATE);
  const [addressLat, setAddressLat] = useState<number | null>(null);
  const [addressLng, setAddressLng] = useState<number | null>(null);
  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const [loading, setLoading] = useState(false);
  const [locating, setLocating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);

  const fillAddressFromLocation = async () => {
    setLocating(true);
    setError(null);
    try {
      const coords = await requestCurrentLocation();
      const geo = await api.reverseGeocodePublic(coords.latitude, coords.longitude);
      setAddress(geo.address ?? geo.label);
      setAddressLat(coords.latitude);
      setAddressLng(coords.longitude);
      if (geo.postal_code) setPostalCode(geo.postal_code);
      if (geo.city) setCity(geo.city);
      if (geo.state) setState(geo.state);
      setInfo('Endereço preenchido pela sua localização.');
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Não foi possível obter o endereço.');
    } finally {
      setLocating(false);
    }
  };

  const validateForm = (): boolean => {
    if (mode === 'login') {
      if (phone.trim().length < 10) {
        setError('Informe um telefone válido.');
        return false;
      }
      return true;
    }

    if (name.trim().length < 3) {
      setError('Informe seu nome completo.');
      return false;
    }
    if (onlyDigits(cpf).length !== 11) {
      setError('Informe um CPF válido (11 dígitos).');
      return false;
    }
    if (!email.trim().includes('@')) {
      setError('Informe um e-mail válido.');
      return false;
    }
    if (address.trim().length < 5) {
      setError('Informe seu endereço completo ou use a localização.');
      return false;
    }
    if (!addressNumber.trim()) {
      setError('Informe o número do endereço.');
      return false;
    }
    if (onlyDigits(postalCode).length !== 8) {
      setError('Informe o CEP (8 dígitos).');
      return false;
    }
    if (!city.trim() || state.trim().length !== 2) {
      setError('Informe cidade e UF.');
      return false;
    }
    if (phone.trim().length < 10) {
      setError('Informe um telefone válido.');
      return false;
    }
    return true;
  };

  const sendOtp = async () => {
    if (!validateForm()) return;

    setLoading(true);
    setError(null);
    setInfo(null);
    try {
      const res = await api.sendOtp(phone.trim());
      if (mode === 'register' && res.user_exists) {
        setError('Este telefone já está cadastrado. Use "Já tenho conta" para entrar.');
        return;
      }
      if (mode === 'login' && res.user_exists === false) {
        setError('Telefone não encontrado. Crie sua conta primeiro.');
        return;
      }
      setStep('code');
      setInfo(
        res.debug_code
          ? `Código de teste: ${res.debug_code}`
          : 'Código enviado por SMS. Digite abaixo para continuar.',
      );
      if (res.debug_code) setCode(res.debug_code);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Falha ao enviar código');
    } finally {
      setLoading(false);
    }
  };

  const verify = async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await api.verifyOtp(
        phone.trim(),
        code.trim(),
        mode === 'register'
          ? {
              name: name.trim(),
              email: email.trim(),
              cpf: onlyDigits(cpf, 11),
              role: 'passenger',
              home_address: address.trim(),
              home_address_number: addressNumber.trim(),
              home_postal_code: onlyDigits(postalCode, 8),
              home_city: city.trim(),
              home_state: state.trim().toUpperCase(),
              home_lat: addressLat ?? undefined,
              home_lng: addressLng ?? undefined,
            }
          : undefined,
      );
      await login(result.token, result.user);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Código inválido');
    } finally {
      setLoading(false);
    }
  };

  const switchMode = (next: Mode) => {
    setMode(next);
    setStep('form');
    setError(null);
    setInfo(null);
    setCode('');
  };

  return (
    <View style={styles.container}>
      <BrandHeader />
      <Text style={styles.subtitle}>
        {mode === 'register' ? 'Crie sua conta para pedir corridas' : 'Entre na sua conta'}
      </Text>

      <View style={styles.modeRow}>
        <Pressable
          style={[styles.modeBtn, mode === 'register' && styles.modeBtnActive]}
          onPress={() => switchMode('register')}
        >
          <Text style={[styles.modeBtnText, mode === 'register' && styles.modeBtnTextActive]}>
            Criar conta
          </Text>
        </Pressable>
        <Pressable
          style={[styles.modeBtn, mode === 'login' && styles.modeBtnActive]}
          onPress={() => switchMode('login')}
        >
          <Text style={[styles.modeBtnText, mode === 'login' && styles.modeBtnTextActive]}>
            Já tenho conta
          </Text>
        </Pressable>
      </View>

      {step === 'form' ? (
        <>
          {mode === 'register' ? (
            <>
              <TextInput
                style={styles.input}
                placeholder="Nome completo"
                value={name}
                onChangeText={setName}
                autoCapitalize="words"
              />
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
                style={[styles.input, styles.addressInput]}
                placeholder="Endereço (rua/avenida)"
                value={address}
                onChangeText={(text) => {
                  setAddress(text);
                  setAddressLat(null);
                  setAddressLng(null);
                }}
              />
              <View style={styles.row}>
                <TextInput
                  style={[styles.input, styles.flex, { marginBottom: 12 }]}
                  placeholder="Número"
                  value={addressNumber}
                  onChangeText={setAddressNumber}
                />
                <TextInput
                  style={[styles.input, styles.cep, { marginBottom: 12 }]}
                  placeholder="CEP"
                  value={postalCode}
                  onChangeText={(v) => setPostalCode(onlyDigits(v, 8))}
                  keyboardType="number-pad"
                  maxLength={8}
                />
              </View>
              <View style={styles.row}>
                <TextInput
                  style={[styles.input, styles.flex, { marginBottom: 12 }]}
                  placeholder="Cidade"
                  value={city}
                  onChangeText={setCity}
                />
                <TextInput
                  style={[styles.input, styles.uf, { marginBottom: 12 }]}
                  placeholder="UF"
                  value={state}
                  onChangeText={(v) => setState(v.toUpperCase())}
                  maxLength={2}
                  autoCapitalize="characters"
                />
              </View>
              <Pressable style={styles.secondaryBtn} onPress={fillAddressFromLocation} disabled={locating}>
                {locating ? (
                  <ActivityIndicator color={theme.ink} />
                ) : (
                  <Text style={styles.secondaryBtnText}>📍 Usar minha localização</Text>
                )}
              </Pressable>
            </>
          ) : null}

          <TextInput
            style={styles.input}
            placeholder="Telefone (5511999990002)"
            value={phone}
            onChangeText={setPhone}
            keyboardType="phone-pad"
          />

          <Pressable style={styles.button} onPress={sendOtp} disabled={loading}>
            {loading ? (
              <ActivityIndicator color={theme.ink} />
            ) : (
              <Text style={styles.buttonText}>Enviar código</Text>
            )}
          </Pressable>
        </>
      ) : (
        <>
          <Text style={styles.codeHint}>Código enviado para {phone}</Text>
          <TextInput
            style={styles.input}
            placeholder="Código de 6 dígitos"
            value={code}
            onChangeText={setCode}
            keyboardType="number-pad"
            maxLength={6}
          />
          <Pressable style={styles.button} onPress={verify} disabled={loading || code.length < 6}>
            {loading ? (
              <ActivityIndicator color={theme.ink} />
            ) : (
              <Text style={styles.buttonText}>
                {mode === 'register' ? 'Concluir cadastro' : 'Entrar'}
              </Text>
            )}
          </Pressable>
          <Pressable style={styles.linkBtn} onPress={() => setStep('form')}>
            <Text style={styles.linkBtnText}>Voltar</Text>
          </Pressable>
        </>
      )}

      {info ? <Text style={styles.info}>{info}</Text> : null}
      {error ? <Text style={styles.error}>{error}</Text> : null}
      <BrandFooter />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg, padding: 24, justifyContent: 'center' },
  title: { fontSize: 28, fontWeight: '700', color: theme.ink, marginBottom: 4 },
  subtitle: { fontSize: 14, color: theme.textSecondary, marginBottom: 20 },
  modeRow: { flexDirection: 'row', gap: 8, marginBottom: 16 },
  modeBtn: {
    flex: 1,
    paddingVertical: 10,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: theme.line,
    alignItems: 'center',
    backgroundColor: theme.paper,
  },
  modeBtnActive: { backgroundColor: theme.ink, borderColor: theme.ink },
  modeBtnText: { color: theme.ink, fontWeight: '600', fontSize: 13 },
  modeBtnTextActive: { color: '#fff' },
  input: {
    backgroundColor: theme.paper,
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.line,
  },
  addressInput: { minHeight: 56, textAlignVertical: 'top' },
  row: { flexDirection: 'row', gap: 8 },
  flex: { flex: 1 },
  cep: { width: 110 },
  uf: { width: 64 },
  secondaryBtn: {
    borderRadius: 12,
    padding: 12,
    marginBottom: 12,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: theme.line,
    backgroundColor: theme.paper,
  },
  secondaryBtnText: { color: theme.ink, fontWeight: '600' },
  button: {
    backgroundColor: theme.amber,
    borderRadius: 13,
    padding: 16,
    alignItems: 'center',
    marginTop: 4,
  },
  buttonText: { color: theme.ink, fontWeight: '700', fontSize: 16 },
  codeHint: { fontSize: 13, color: theme.textSecondary, marginBottom: 10 },
  linkBtn: { marginTop: 12, alignItems: 'center' },
  linkBtnText: { color: theme.ink, fontWeight: '600' },
  info: { color: '#0B6E62', fontSize: 13, marginTop: 12, textAlign: 'center' },
  error: { color: theme.danger, fontSize: 13, marginTop: 12, textAlign: 'center' },
});
