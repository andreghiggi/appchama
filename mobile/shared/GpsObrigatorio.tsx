import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, AppState, Linking, Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import * as Location from 'expo-location';
import { theme } from './theme';

type Estado = 'checando' | 'ok' | 'sem_permissao' | 'gps_desligado';

/**
 * O app só funciona com o GPS ligado e a permissão de localização dada (corrida, preço, motorista mais próximo).
 * Enquanto faltar uma das duas coisas, mostra uma tela explicando e levando direto para resolver.
 * Confere sozinho de novo quando a pessoa volta das configurações.
 */
export default function GpsObrigatorio({ children }: { children: React.ReactNode }) {
  const [estado, setEstado] = useState<Estado>(Platform.OS === 'web' ? 'ok' : 'checando');
  const [podePerguntar, setPodePerguntar] = useState(true);
  const ocupado = useRef(false);

  const conferir = useCallback(async () => {
    if (Platform.OS === 'web' || ocupado.current) return;
    ocupado.current = true;
    try {
      const perm = await Location.getForegroundPermissionsAsync();
      if (perm.status !== 'granted') {
        setPodePerguntar(perm.canAskAgain !== false);
        setEstado('sem_permissao');
        return;
      }
      const ligado = await Location.hasServicesEnabledAsync();
      setEstado(ligado ? 'ok' : 'gps_desligado');
    } catch {
      // Se nem a conferência funcionar, não prende a pessoa fora do app.
      setEstado('ok');
    } finally {
      ocupado.current = false;
    }
  }, []);

  useEffect(() => {
    void conferir();
    const sub = AppState.addEventListener('change', (s) => {
      if (s === 'active') void conferir();
    });
    // Se o GPS for desligado com o app aberto, volta a bloquear; enquanto bloqueado, confere a cada poucos segundos.
    const timer = setInterval(() => void conferir(), estado === 'ok' ? 15000 : 3000);
    return () => {
      sub.remove();
      clearInterval(timer);
    };
  }, [conferir, estado]);

  const pedirPermissao = async () => {
    if (!podePerguntar) {
      await Linking.openSettings().catch(() => undefined);
      return;
    }
    const r = await Location.requestForegroundPermissionsAsync();
    setPodePerguntar(r.canAskAgain !== false);
    void conferir();
  };

  const ligarGps = async () => {
    try {
      // No Android abre a janelinha "Ativar localização?" do próprio sistema.
      await Location.enableNetworkProviderAsync();
    } catch {
      await Linking.sendIntent('android.settings.LOCATION_SOURCE_SETTINGS').catch(() => Linking.openSettings().catch(() => undefined));
    }
    void conferir();
  };

  if (estado === 'ok') return <>{children}</>;

  if (estado === 'checando') {
    return (
      <View style={styles.centro}>
        <ActivityIndicator size="large" color={theme.amber} />
      </View>
    );
  }

  const semPermissao = estado === 'sem_permissao';

  return (
    <View style={styles.tela}>
      <Text style={styles.icone}>📍</Text>
      <Text style={styles.titulo}>{semPermissao ? 'Precisamos da sua localização' : 'Ligue o GPS do celular'}</Text>
      <Text style={styles.texto}>
        {semPermissao
          ? 'O app usa a sua localização para encontrar o motorista mais próximo, calcular o preço e acompanhar a corrida. Sem ela o app não funciona.'
          : 'O GPS está desligado. Ligue para continuar: sem ele não conseguimos saber onde você está.'}
      </Text>
      {semPermissao && !podePerguntar ? (
        <Text style={styles.dica}>
          A permissão foi negada. Toque no botão, abra "Permissões" e escolha "Localização" → "Permitir durante o uso do app".
        </Text>
      ) : null}
      <Pressable style={styles.botao} onPress={semPermissao ? pedirPermissao : ligarGps}>
        <Text style={styles.botaoTexto}>
          {semPermissao ? (podePerguntar ? 'Permitir localização' : 'Abrir configurações') : 'Ligar o GPS'}
        </Text>
      </Pressable>
      <Text style={styles.rodape}>Esta tela some sozinha quando a localização estiver ativa.</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  centro: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: theme.bg },
  tela: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 28, backgroundColor: theme.bg },
  icone: { fontSize: 56, marginBottom: 12 },
  titulo: { fontSize: 22, fontWeight: '800', color: theme.ink, textAlign: 'center', marginBottom: 10 },
  texto: { fontSize: 15, color: theme.textSecondary, textAlign: 'center', lineHeight: 22, marginBottom: 14 },
  dica: { fontSize: 13, color: '#92400E', backgroundColor: '#FEF3C7', padding: 10, borderRadius: 8, textAlign: 'center', marginBottom: 14 },
  botao: { backgroundColor: theme.amber, paddingVertical: 14, paddingHorizontal: 28, borderRadius: 12, marginTop: 6 },
  botaoTexto: { color: theme.ink, fontSize: 16, fontWeight: '800' },
  rodape: { fontSize: 12, color: theme.textMuted, marginTop: 18, textAlign: 'center' },
});
