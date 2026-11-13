import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { api } from '../../../shared/api';
import { BRAND_NAME, theme, TENANT_SLUG } from '../../../shared/theme';
import type { City } from '../../../shared/types';
import { useAuth } from '../context/AuthContext';

type Props = {
  onBack: () => void;
};

type CnhFile = {
  name: string;
  type: string;
  file?: File;
  uri?: string;
};

export default function RegisterScreen({ onBack }: Props) {
  const { login } = useAuth();
  const [cities, setCities] = useState<City[]>([]);
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [cityId, setCityId] = useState('');
  const [cnhNumber, setCnhNumber] = useState('');
  const [cnhExpiry, setCnhExpiry] = useState('');
  const [vehiclePlate, setVehiclePlate] = useState('');
  const [vehicleModel, setVehicleModel] = useState('');
  const [vehicleColor, setVehicleColor] = useState('');
  const [vehicleYear, setVehicleYear] = useState(String(new Date().getFullYear()));
  const [cnhFile, setCnhFile] = useState<CnhFile | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadingCities, setLoadingCities] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .listCities()
      .then((list) => {
        setCities(list);
        if (list[0]) setCityId(list[0].id);
      })
      .finally(() => setLoadingCities(false));
  }, []);

  const pickCnh = () => {
    if (Platform.OS !== 'web' || typeof document === 'undefined') {
      setError('Envio de CNH disponível na versão web por enquanto.');
      return;
    }

    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/jpg,application/pdf';
    input.onchange = () => {
      const file = input.files?.[0];
      if (!file) return;
      setCnhFile({ name: file.name, type: file.type, file });
      setError(null);
    };
    input.click();
  };

  const submit = async () => {
    if (name.trim().length < 3) {
      setError('Informe seu nome completo.');
      return;
    }
    if (phone.trim().length < 10) {
      setError('Informe um telefone válido.');
      return;
    }
    if (password.length < 6) {
      setError('A senha deve ter pelo menos 6 caracteres.');
      return;
    }
    if (password !== passwordConfirmation) {
      setError('As senhas não conferem.');
      return;
    }
    if (!cityId) {
      setError('Selecione a cidade.');
      return;
    }
    if (!cnhNumber.trim()) {
      setError('Informe o número da CNH.');
      return;
    }
    if (!cnhExpiry.trim()) {
      setError('Informe a validade da CNH (AAAA-MM-DD).');
      return;
    }
    if (!vehiclePlate.trim()) {
      setError('Informe a placa do veículo.');
      return;
    }
    if (!cnhFile?.file) {
      setError('Envie a foto ou PDF da CNH.');
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const form = new FormData();
      form.append('tenant_slug', TENANT_SLUG);
      form.append('name', name.trim());
      form.append('phone', phone.trim());
      form.append('password', password);
      form.append('password_confirmation', passwordConfirmation);
      form.append('city_id', cityId);
      form.append('cnh_number', cnhNumber.trim());
      form.append('cnh_expiry', cnhExpiry.trim());
      form.append('vehicle_plate', vehiclePlate.trim().toUpperCase());
      form.append('vehicle_model', vehicleModel.trim());
      form.append('vehicle_color', vehicleColor.trim());
      form.append('vehicle_year', vehicleYear.trim());
      form.append('cnh_document', cnhFile.file, cnhFile.name);

      const result = await api.driverRegister(form);
      await login(result.token, result.user);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Não foi possível concluir o cadastro');
    } finally {
      setLoading(false);
    }
  };

  if (loadingCities) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={theme.amber} />
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <Pressable onPress={onBack} style={styles.back}>
        <Text style={styles.backText}>← Voltar</Text>
      </Pressable>
      <Text style={styles.title}>Criar conta motorista</Text>
      <Text style={styles.subtitle}>
        Preencha seus dados e envie a CNH. A equipe {BRAND_NAME} analisará e liberará seu acesso.
      </Text>

      <TextInput style={styles.input} placeholder="Nome completo" value={name} onChangeText={setName} />
      <TextInput
        style={styles.input}
        placeholder="Telefone (5511999990000)"
        value={phone}
        onChangeText={setPhone}
        keyboardType="phone-pad"
      />
      <TextInput
        style={styles.input}
        placeholder="Senha (mín. 6 caracteres)"
        value={password}
        onChangeText={setPassword}
        secureTextEntry
      />
      <TextInput
        style={styles.input}
        placeholder="Confirmar senha"
        value={passwordConfirmation}
        onChangeText={setPasswordConfirmation}
        secureTextEntry
      />

      <Text style={styles.label}>Cidade</Text>
      <View style={styles.cityRow}>
        {cities.map((city) => (
          <Pressable
            key={city.id}
            style={[styles.cityChip, cityId === city.id && styles.cityChipActive]}
            onPress={() => setCityId(city.id)}
          >
            <Text style={[styles.cityChipText, cityId === city.id && styles.cityChipTextActive]}>
              {city.name}
            </Text>
          </Pressable>
        ))}
      </View>

      <TextInput
        style={styles.input}
        placeholder="Número da CNH"
        value={cnhNumber}
        onChangeText={setCnhNumber}
      />
      <TextInput
        style={styles.input}
        placeholder="Validade CNH (AAAA-MM-DD)"
        value={cnhExpiry}
        onChangeText={setCnhExpiry}
      />

      <TextInput
        style={styles.input}
        placeholder="Placa (ABC1D23)"
        value={vehiclePlate}
        onChangeText={setVehiclePlate}
        autoCapitalize="characters"
      />
      <TextInput style={styles.input} placeholder="Modelo do veículo" value={vehicleModel} onChangeText={setVehicleModel} />
      <TextInput style={styles.input} placeholder="Cor" value={vehicleColor} onChangeText={setVehicleColor} />
      <TextInput
        style={styles.input}
        placeholder="Ano"
        value={vehicleYear}
        onChangeText={setVehicleYear}
        keyboardType="number-pad"
      />

      <Pressable style={styles.uploadBtn} onPress={pickCnh}>
        <Text style={styles.uploadBtnText}>
          {cnhFile ? `CNH: ${cnhFile.name}` : 'Enviar foto ou PDF da CNH'}
        </Text>
      </Pressable>

      <Pressable style={styles.button} onPress={submit} disabled={loading}>
        {loading ? (
          <ActivityIndicator color={theme.ink} />
        ) : (
          <Text style={styles.buttonText}>Enviar cadastro</Text>
        )}
      </Pressable>
      {error ? <Text style={styles.error}>{error}</Text> : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, backgroundColor: theme.ink, justifyContent: 'center', alignItems: 'center' },
  container: { flexGrow: 1, backgroundColor: theme.ink, padding: 24, paddingBottom: 40 },
  back: { marginBottom: 12 },
  backText: { color: theme.amber, fontWeight: '600' },
  title: { fontSize: 24, fontWeight: '700', color: '#fff', marginBottom: 8 },
  subtitle: { fontSize: 14, color: theme.mintBg, lineHeight: 20, marginBottom: 20 },
  label: { color: theme.mintBg, fontSize: 13, marginBottom: 8, fontWeight: '600' },
  input: { backgroundColor: theme.paper, borderRadius: 12, padding: 14, marginBottom: 12 },
  cityRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 12 },
  cityChip: {
    borderRadius: 999,
    borderWidth: 1,
    borderColor: theme.mintBg,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  cityChipActive: { backgroundColor: theme.amber, borderColor: theme.amber },
  cityChipText: { color: theme.mintBg, fontSize: 13 },
  cityChipTextActive: { color: theme.ink, fontWeight: '700' },
  uploadBtn: {
    borderWidth: 1,
    borderColor: theme.amber,
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
    alignItems: 'center',
  },
  uploadBtnText: { color: theme.amber, fontWeight: '600' },
  button: { backgroundColor: theme.amber, borderRadius: 13, padding: 16, alignItems: 'center' },
  buttonText: { color: theme.ink, fontWeight: '700' },
  error: { color: '#FCA5A5', fontSize: 13, marginTop: 12, textAlign: 'center' },
});
