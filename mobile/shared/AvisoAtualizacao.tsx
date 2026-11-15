import React, { useCallback, useEffect, useRef, useState } from 'react';
import { AppState, Linking, Modal, Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import Constants from 'expo-constants';
import { api } from './api';
import { theme } from './theme';

type Estado = { latest: number; minimum: number } | null;

const VERIFICAR_A_CADA_MS = 30 * 60 * 1000;

/** Codigo de versao instalado (o mesmo numero que vai para a Google Play). */
function versaoInstalada(): number {
  const v = Constants.expoConfig?.android?.versionCode;
  return typeof v === 'number' ? v : Number(v ?? 0) || 0;
}

function abrirLoja(): void {
  const pacote = Constants.expoConfig?.android?.package;
  if (!pacote) return;
  Linking.openURL(`market://details?id=${pacote}`).catch(() =>
    Linking.openURL(`https://play.google.com/store/apps/details?id=${pacote}`).catch(() => undefined),
  );
}

/**
 * Com o app aberto, avisa quando ha versao mais nova na loja.
 * "Agora nao" some ate o app ser aberto de novo; abaixo da versao minima so deixa atualizar.
 */
export default function AvisoAtualizacao({ app }: { app: 'passenger' | 'driver' }) {
  const [info, setInfo] = useState<Estado>(null);
  const [dispensado, setDispensado] = useState(false);
  const rodando = useRef(true);

  const verificar = useCallback(async () => {
    if (Platform.OS !== 'android') return;
    try {
      const r = await api.appVersion(app);
      if (rodando.current) setInfo(r);
    } catch {
      /* sem rede: tenta de novo depois */
    }
  }, [app]);

  useEffect(() => {
    rodando.current = true;
    void verificar();
    const timer = setInterval(verificar, VERIFICAR_A_CADA_MS);
    const sub = AppState.addEventListener('change', (estado: string) => {
      if (estado === 'active') void verificar();
    });
    return () => {
      rodando.current = false;
      clearInterval(timer);
      sub.remove();
    };
  }, [verificar]);

  const atual = versaoInstalada();
  if (!info || atual <= 0) return null;

  const obrigatoria = info.minimum > atual;
  const disponivel = info.latest > atual;
  if (!obrigatoria && (!disponivel || dispensado)) return null;

  return (
    <Modal visible transparent animationType="fade" onRequestClose={() => (obrigatoria ? undefined : setDispensado(true))}>
      <View style={estilos.fundo}>
        <View style={estilos.cartao}>
          <Text style={estilos.titulo}>{obrigatoria ? 'Atualização necessária' : 'Nova versão disponível'}</Text>
          <Text style={estilos.texto}>
            {obrigatoria
              ? 'Esta versão do app não funciona mais. Atualize na Play Store para continuar.'
              : 'Há uma versão mais nova do app com melhorias e correções. Atualize na Play Store.'}
          </Text>
          <Pressable style={estilos.botao} onPress={abrirLoja}>
            <Text style={estilos.botaoTexto}>Atualizar agora</Text>
          </Pressable>
          {obrigatoria ? null : (
            <Pressable style={estilos.depois} onPress={() => setDispensado(true)} hitSlop={8}>
              <Text style={estilos.depoisTexto}>Agora não</Text>
            </Pressable>
          )}
        </View>
      </View>
    </Modal>
  );
}

const estilos = StyleSheet.create({
  fundo: { flex: 1, backgroundColor: 'rgba(0,0,0,0.55)', justifyContent: 'center', padding: 24 },
  cartao: { backgroundColor: theme.paper, borderRadius: 18, padding: 22, gap: 10 },
  titulo: { fontSize: 20, fontWeight: '700', color: theme.ink },
  texto: { fontSize: 14, color: theme.textSecondary, lineHeight: 20 },
  botao: { backgroundColor: theme.amber, borderRadius: 13, padding: 14, alignItems: 'center', marginTop: 6 },
  botaoTexto: { color: theme.ink, fontWeight: '700', fontSize: 15 },
  depois: { alignItems: 'center', paddingVertical: 8 },
  depoisTexto: { color: theme.textSecondary, fontSize: 14, textDecorationLine: 'underline' },
});
