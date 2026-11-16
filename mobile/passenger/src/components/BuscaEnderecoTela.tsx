import React, { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  BackHandler,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { searchAddresses, type GeocodeResult } from '../../../shared/geocode';
import { useAlturaTeclado } from '../../../shared/TelaSegura';
import { theme } from '../../../shared/theme';

type Props = {
  titulo: string;
  placeholder: string;
  /** Ponto usado para dar preferencia aos resultados proximos. */
  near: { latitude: number; longitude: number };
  cityId?: string;
  onSelect: (item: GeocodeResult) => void;
  onClose: () => void;
  /** So no embarque: volta a usar o GPS. */
  onUseGps?: () => void;
};

/**
 * Tela cheia de busca de endereco ou local (hospital, mercado...).
 * Fica por cima da tela do mapa, que nao muda de tamanho quando o teclado sobe.
 */
export default function BuscaEnderecoTela({ titulo, placeholder, near, cityId, onSelect, onClose, onUseGps }: Props) {
  const [texto, setTexto] = useState('');
  const [resultados, setResultados] = useState<GeocodeResult[]>([]);
  const [buscando, setBuscando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const teclado = useAlturaTeclado();
  const nearRef = useRef(near);
  nearRef.current = near;

  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      onClose();
      return true;
    });
    return () => sub.remove();
  }, [onClose]);

  useEffect(() => {
    const q = texto.trim();
    if (q.length < 3) {
      setResultados([]);
      setErro(null);
      setBuscando(false);
      return;
    }
    let ativo = true;
    const timer = setTimeout(async () => {
      setBuscando(true);
      setErro(null);
      try {
        const r = await searchAddresses(q, nearRef.current, cityId);
        if (!ativo) return;
        setResultados(r.results);
        if (r.results.length === 0) setErro('Nenhum endereço ou local encontrado. Tente outro nome.');
      } catch (e: unknown) {
        if (!ativo) return;
        setResultados([]);
        setErro(e instanceof Error ? e.message : 'Falha na busca. Tente de novo.');
      } finally {
        if (ativo) setBuscando(false);
      }
    }, 400);
    return () => {
      ativo = false;
      clearTimeout(timer);
    };
  }, [texto, cityId]);

  return (
    <View style={estilos.tela}>
      <View style={estilos.topo}>
        <Pressable onPress={onClose} hitSlop={10} style={estilos.voltar}>
          <Text style={estilos.voltarTexto}>←</Text>
        </Pressable>
        <Text style={estilos.titulo}>{titulo}</Text>
      </View>

      <View style={estilos.campoWrap}>
        <TextInput
          style={estilos.campo}
          value={texto}
          onChangeText={setTexto}
          placeholder={placeholder}
          placeholderTextColor={theme.textMuted}
          autoFocus
          autoCorrect={false}
          returnKeyType="search"
        />
        {buscando ? <ActivityIndicator color={theme.ink} style={estilos.carregando} /> : null}
      </View>

      <ScrollView
        style={estilos.lista}
        contentContainerStyle={{ paddingBottom: teclado + 24 }}
        keyboardShouldPersistTaps="always"
      >
        {onUseGps ? (
          <Pressable style={estilos.item} onPress={onUseGps}>
            <Text style={estilos.itemTexto}>◎  Usar minha localização atual</Text>
          </Pressable>
        ) : null}
        {erro ? <Text style={estilos.erro}>{erro}</Text> : null}
        {resultados.map((item) => (
          <Pressable key={`${item.latitude}-${item.longitude}-${item.label}`} style={estilos.item} onPress={() => onSelect(item)}>
            <Text style={estilos.itemTexto} numberOfLines={3}>
              {item.label}
            </Text>
          </Pressable>
        ))}
        {texto.trim().length < 3 && resultados.length === 0 ? (
          <Text style={estilos.dica}>Digite o nome de um local (hospital, mercado, farmácia) ou uma rua.</Text>
        ) : null}
      </ScrollView>
    </View>
  );
}

const estilos = StyleSheet.create({
  tela: { ...StyleSheet.absoluteFillObject, backgroundColor: theme.bg, zIndex: 20 },
  topo: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingTop: 14, paddingBottom: 6, gap: 10 },
  voltar: { width: 36, height: 36, borderRadius: 18, backgroundColor: theme.paper, alignItems: 'center', justifyContent: 'center' },
  voltarTexto: { fontSize: 20, color: theme.ink, fontWeight: '700' },
  titulo: { fontSize: 18, fontWeight: '700', color: theme.ink },
  campoWrap: {
    marginHorizontal: 16,
    marginBottom: 8,
    backgroundColor: theme.paper,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: theme.line,
    flexDirection: 'row',
    alignItems: 'center',
  },
  campo: { flex: 1, fontSize: 15, color: theme.ink, paddingHorizontal: 14, paddingVertical: 12 },
  carregando: { marginRight: 12 },
  lista: { flex: 1 },
  item: { paddingHorizontal: 20, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: theme.line, backgroundColor: theme.paper },
  itemTexto: { fontSize: 14, color: theme.ink, lineHeight: 20 },
  erro: { paddingHorizontal: 20, paddingVertical: 14, color: theme.danger, fontSize: 13 },
  dica: { paddingHorizontal: 20, paddingVertical: 14, color: theme.textSecondary, fontSize: 13 },
});
