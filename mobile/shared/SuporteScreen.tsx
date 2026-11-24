import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  BackHandler,
  FlatList,
  KeyboardAvoidingView,
  Linking,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { api } from './api';
import { useBrand, whatsappUrl } from './brand';
import { rideStatusLabel } from './labels';
import { theme } from './theme';
import type { Ride, SupportTicket } from './types';

type Props = {
  papel: 'passenger' | 'driver';
  /** Quando a tela abre por cima de tudo (motorista), mostra "Voltar". No passageiro ela é uma aba. */
  onBack?: () => void;
};

type Modo = { tipo: 'lista' } | { tipo: 'novo' } | { tipo: 'conversa'; id: string };

const ASSUNTOS: Record<'passenger' | 'driver', { id: string; rotulo: string }[]> = {
  passenger: [
    { id: 'ride', rotulo: 'Problema com uma corrida' },
    { id: 'payment', rotulo: 'Pagamento' },
    { id: 'report', rotulo: 'Denúncia / segurança' },
    { id: 'account', rotulo: 'Minha conta' },
    { id: 'app', rotulo: 'Erro ou dúvida no app' },
    { id: 'other', rotulo: 'Outro assunto' },
  ],
  driver: [
    { id: 'ride', rotulo: 'Problema com uma corrida' },
    { id: 'payment', rotulo: 'Crédito, recebimento ou saque' },
    { id: 'report', rotulo: 'Denúncia sobre passageiro' },
    { id: 'account', rotulo: 'Meu cadastro' },
    { id: 'app', rotulo: 'Erro ou dúvida no app' },
    { id: 'other', rotulo: 'Outro assunto' },
  ],
};

function dataHora(iso?: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const p = (n: number) => String(n).padStart(2, '0');
  return `${p(d.getDate())}/${p(d.getMonth() + 1)} ${p(d.getHours())}:${p(d.getMinutes())}`;
}

function corStatus(s: string): { fundo: string; texto: string } {
  if (s === 'closed') return { fundo: '#E5E7EB', texto: '#374151' };
  if (s === 'in_progress') return { fundo: '#DBEAFE', texto: '#1E40AF' };
  return { fundo: '#FEF3C7', texto: '#92400E' };
}

/** Ajuda e suporte: contatos da marca + chamados com conversa com a equipe. Serve para passageiro e motorista. */
export default function SuporteScreen({ papel, onBack }: Props) {
  const insets = useSafeAreaInsets();
  const [modo, setModo] = useState<Modo>({ tipo: 'lista' });
  const comTopo = Boolean(onBack);

  // Botão voltar do Android: conversa/novo voltam para a lista; a lista sai (quando há para onde voltar).
  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      if (modo.tipo !== 'lista') {
        setModo({ tipo: 'lista' });
        return true;
      }
      if (onBack) {
        onBack();
        return true;
      }
      return false;
    });
    return () => sub.remove();
  }, [modo.tipo, onBack]);

  return (
    <View style={[styles.container, comTopo && { paddingTop: 16 + insets.top, paddingBottom: 16 + insets.bottom }]}>
      {onBack && modo.tipo === 'lista' ? (
        <Pressable onPress={onBack} style={styles.back} hitSlop={16}>
          <Text style={styles.backText}>← Voltar</Text>
        </Pressable>
      ) : null}
      {modo.tipo === 'lista' ? (
        <Lista papel={papel} onNovo={() => setModo({ tipo: 'novo' })} onAbrir={(id) => setModo({ tipo: 'conversa', id })} />
      ) : null}
      {modo.tipo === 'novo' ? (
        <Novo papel={papel} onCancelar={() => setModo({ tipo: 'lista' })} onCriado={(id) => setModo({ tipo: 'conversa', id })} />
      ) : null}
      {modo.tipo === 'conversa' ? <Conversa id={modo.id} onVoltar={() => setModo({ tipo: 'lista' })} /> : null}
    </View>
  );
}

function Lista({ papel, onNovo, onAbrir }: { papel: 'passenger' | 'driver'; onNovo: () => void; onAbrir: (id: string) => void }) {
  const brand = useBrand();
  const [chamados, setChamados] = useState<SupportTicket[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [atualizando, setAtualizando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  const carregar = useCallback(async (puxou = false) => {
    if (puxou) setAtualizando(true);
    try {
      const r = await api.listSupportTickets();
      setChamados(r.data);
      setErro(null);
    } catch (e) {
      setErro(e instanceof Error ? e.message : 'Não foi possível carregar seus chamados');
    } finally {
      setCarregando(false);
      setAtualizando(false);
    }
  }, []);

  useEffect(() => {
    void carregar();
    const t = setInterval(() => void carregar(), 30000);
    return () => clearInterval(t);
  }, [carregar]);

  const zap = whatsappUrl(brand);
  const tel = (brand?.contacts?.phone ?? '').replace(/\D/g, '');
  const email = brand?.contacts?.email ?? '';

  const abrirLink = (url: string) => {
    Linking.openURL(url).catch(() => Alert.alert('Não foi possível abrir', 'Tente novamente em instantes.'));
  };

  return (
    <FlatList
      data={chamados}
      keyExtractor={(c) => c.id}
      refreshControl={<RefreshControl refreshing={atualizando} onRefresh={() => void carregar(true)} />}
      ListHeaderComponent={
        <View>
          <Text style={styles.title}>Ajuda e suporte</Text>
          <Text style={styles.sub}>
            {papel === 'driver'
              ? 'Fale com a equipe sobre corridas, crédito, saques ou qualquer problema.'
              : 'Fale com a equipe sobre uma corrida, pagamento ou qualquer problema.'}
          </Text>

          <Pressable style={styles.btnPrincipal} onPress={onNovo}>
            <Text style={styles.btnPrincipalTxt}>Abrir um chamado</Text>
          </Pressable>

          {zap || tel || email ? (
            <View style={styles.contatos}>
              <Text style={styles.contatosTitulo}>Prefere falar direto?</Text>
              <View style={styles.contatosLinha}>
                {zap ? (
                  <Pressable style={styles.contatoBtn} onPress={() => abrirLink(zap)}>
                    <Text style={styles.contatoTxt}>WhatsApp</Text>
                  </Pressable>
                ) : null}
                {tel ? (
                  <Pressable style={styles.contatoBtn} onPress={() => abrirLink(`tel:${tel}`)}>
                    <Text style={styles.contatoTxt}>Ligar</Text>
                  </Pressable>
                ) : null}
                {email ? (
                  <Pressable style={styles.contatoBtn} onPress={() => abrirLink(`mailto:${email}`)}>
                    <Text style={styles.contatoTxt}>E-mail</Text>
                  </Pressable>
                ) : null}
              </View>
            </View>
          ) : null}

          <Text style={styles.secao}>Meus chamados</Text>
          {carregando ? <ActivityIndicator color={theme.amber} style={{ marginVertical: 16 }} /> : null}
          {erro ? <Text style={styles.erro}>{erro}</Text> : null}
        </View>
      }
      ListEmptyComponent={
        !carregando && !erro ? <Text style={styles.vazio}>Você ainda não abriu nenhum chamado.</Text> : null
      }
      renderItem={({ item }) => {
        const cor = corStatus(item.status);
        return (
          <Pressable style={styles.card} onPress={() => onAbrir(item.id)}>
            <View style={styles.cardTopo}>
              <Text style={styles.cardCategoria}>{item.category_label}</Text>
              {item.unread ? <View style={styles.bolinha} /> : null}
              <View style={[styles.selo, { backgroundColor: cor.fundo }]}>
                <Text style={[styles.seloTxt, { color: cor.texto }]}>{item.status_label}</Text>
              </View>
            </View>
            <Text style={styles.cardAssunto} numberOfLines={2}>
              {item.subject}
            </Text>
            <Text style={styles.cardData}>{dataHora(item.last_message_at ?? item.created_at)}</Text>
          </Pressable>
        );
      }}
      contentContainerStyle={{ paddingBottom: 32 }}
    />
  );
}

function Novo({
  papel,
  onCancelar,
  onCriado,
}: {
  papel: 'passenger' | 'driver';
  onCancelar: () => void;
  onCriado: (id: string) => void;
}) {
  const [assunto, setAssunto] = useState<string>('');
  const [texto, setTexto] = useState('');
  const [corridas, setCorridas] = useState<Ride[]>([]);
  const [corridaId, setCorridaId] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  useEffect(() => {
    api
      .listRides(1)
      .then((r) => setCorridas((r.data ?? []).slice(0, 5)))
      .catch(() => undefined);
  }, []);

  const pedeCorrida = assunto === 'ride' || assunto === 'report' || assunto === 'payment';

  const enviar = async () => {
    setErro(null);
    if (!assunto) {
      setErro('Escolha o assunto.');
      return;
    }
    if (texto.trim().length < 5) {
      setErro('Conte o que aconteceu (mínimo 5 letras).');
      return;
    }
    setEnviando(true);
    try {
      const t = await api.openSupportTicket(assunto, texto.trim(), pedeCorrida ? corridaId : null);
      onCriado(t.id);
    } catch (e) {
      setErro(e instanceof Error ? e.message : 'Não foi possível enviar agora');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={{ paddingBottom: 32 }}>
        <Pressable onPress={onCancelar} style={styles.back} hitSlop={16}>
          <Text style={styles.backText}>← Cancelar</Text>
        </Pressable>
        <Text style={styles.title}>Novo chamado</Text>

        <Text style={styles.rotulo}>Qual o assunto?</Text>
        <View style={styles.chips}>
          {ASSUNTOS[papel].map((a) => (
            <Pressable key={a.id} onPress={() => setAssunto(a.id)} style={[styles.chip, assunto === a.id && styles.chipAtivo]}>
              <Text style={[styles.chipTxt, assunto === a.id && styles.chipTxtAtivo]}>{a.rotulo}</Text>
            </Pressable>
          ))}
        </View>

        {pedeCorrida && corridas.length > 0 ? (
          <View>
            <Text style={styles.rotulo}>É sobre qual corrida? (opcional)</Text>
            {corridas.map((c) => (
              <Pressable
                key={c.id}
                onPress={() => setCorridaId(corridaId === c.id ? null : c.id)}
                style={[styles.corrida, corridaId === c.id && styles.corridaAtiva]}
              >
                <Text style={styles.corridaTopo}>
                  {dataHora(c.requested_at ?? c.completed_at)} · {rideStatusLabel(c.status)}
                </Text>
                <Text style={styles.corridaEnd} numberOfLines={1}>
                  {c.destination_address || c.origin_address || 'Corrida'}
                </Text>
              </Pressable>
            ))}
          </View>
        ) : null}

        <Text style={styles.rotulo}>Conte o que aconteceu</Text>
        <TextInput
          style={styles.entrada}
          value={texto}
          onChangeText={setTexto}
          multiline
          maxLength={2000}
          placeholder="Escreva com calma. Quanto mais detalhes, mais rápido a equipe resolve."
          placeholderTextColor={theme.textMuted}
          textAlignVertical="top"
        />
        {erro ? <Text style={styles.erro}>{erro}</Text> : null}
        <Pressable style={[styles.btnPrincipal, enviando && { opacity: 0.6 }]} onPress={() => void enviar()} disabled={enviando}>
          {enviando ? <ActivityIndicator color={theme.ink} /> : <Text style={styles.btnPrincipalTxt}>Enviar para a equipe</Text>}
        </Pressable>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

function Conversa({ id, onVoltar }: { id: string; onVoltar: () => void }) {
  const [chamado, setChamado] = useState<SupportTicket | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [texto, setTexto] = useState('');
  const [enviando, setEnviando] = useState(false);
  const lista = useRef<FlatList>(null);

  const carregar = useCallback(async () => {
    try {
      setChamado(await api.getSupportTicket(id));
      setErro(null);
    } catch (e) {
      setErro(e instanceof Error ? e.message : 'Não foi possível carregar a conversa');
    }
  }, [id]);

  useEffect(() => {
    void carregar();
    const t = setInterval(() => void carregar(), 15000);
    return () => clearInterval(t);
  }, [carregar]);

  const enviar = async () => {
    const msg = texto.trim();
    if (!msg || enviando) return;
    setEnviando(true);
    try {
      setChamado(await api.replySupportTicket(id, msg));
      setTexto('');
      setErro(null);
    } catch (e) {
      setErro(e instanceof Error ? e.message : 'Não foi possível enviar');
    } finally {
      setEnviando(false);
    }
  };

  const encerrar = () => {
    Alert.alert('Encerrar chamado?', 'Se precisar, é só escrever de novo e ele reabre.', [
      { text: 'Não', style: 'cancel' },
      {
        text: 'Encerrar',
        onPress: () => {
          api
            .closeSupportTicket(id)
            .then(() => carregar())
            .catch((e) => setErro(e instanceof Error ? e.message : 'Não foi possível encerrar'));
        },
      },
    ]);
  };

  const msgs = chamado?.messages ?? [];

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <Pressable onPress={onVoltar} style={styles.back} hitSlop={16}>
        <Text style={styles.backText}>← Meus chamados</Text>
      </Pressable>
      {chamado ? (
        <View style={styles.conversaTopo}>
          <Text style={styles.conversaTitulo}>{chamado.category_label}</Text>
          <View style={[styles.selo, { backgroundColor: corStatus(chamado.status).fundo }]}>
            <Text style={[styles.seloTxt, { color: corStatus(chamado.status).texto }]}>{chamado.status_label}</Text>
          </View>
        </View>
      ) : (
        <ActivityIndicator color={theme.amber} style={{ marginVertical: 16 }} />
      )}
      {erro ? <Text style={styles.erro}>{erro}</Text> : null}
      <FlatList
        ref={lista}
        style={{ flex: 1 }}
        data={msgs}
        keyExtractor={(m) => m.id}
        onContentSizeChange={() => lista.current?.scrollToEnd({ animated: false })}
        renderItem={({ item }) => (
          <View style={[styles.balao, item.author === 'user' ? styles.balaoEu : styles.balaoEquipe]}>
            <Text style={styles.balaoAutor}>{item.author === 'user' ? 'Você' : 'Equipe'}</Text>
            <Text style={styles.balaoTxt}>{item.body}</Text>
            <Text style={styles.balaoHora}>{dataHora(item.created_at)}</Text>
          </View>
        )}
        contentContainerStyle={{ paddingVertical: 8 }}
      />
      {chamado?.status === 'closed' ? <Text style={styles.encerrado}>Chamado encerrado. Escreva abaixo para reabrir.</Text> : null}
      <View style={styles.caixa}>
        <TextInput
          style={styles.caixaEntrada}
          value={texto}
          onChangeText={setTexto}
          placeholder="Escreva sua mensagem"
          placeholderTextColor={theme.textMuted}
          multiline
          maxLength={2000}
        />
        <Pressable style={[styles.enviar, (!texto.trim() || enviando) && { opacity: 0.5 }]} onPress={() => void enviar()} disabled={!texto.trim() || enviando}>
          <Text style={styles.enviarTxt}>Enviar</Text>
        </Pressable>
      </View>
      {chamado && chamado.status !== 'closed' ? (
        <Pressable onPress={encerrar} style={{ alignSelf: 'center', padding: 8 }}>
          <Text style={styles.encerrarLink}>Já resolveu? Encerrar chamado</Text>
        </Pressable>
      ) : null}
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg, paddingHorizontal: 16 },
  back: { paddingVertical: 8, alignSelf: 'flex-start' },
  backText: { color: theme.ink, fontWeight: '600' },
  title: { fontSize: 24, fontWeight: '700', color: theme.ink, marginTop: 8, marginBottom: 4 },
  sub: { color: theme.textSecondary, marginBottom: 14, lineHeight: 20 },
  btnPrincipal: { backgroundColor: theme.amber, borderRadius: 12, paddingVertical: 14, alignItems: 'center', marginTop: 12 },
  btnPrincipalTxt: { color: theme.ink, fontWeight: '700', fontSize: 16 },
  contatos: { backgroundColor: theme.paper, borderRadius: 12, padding: 14, marginTop: 16, borderWidth: 1, borderColor: theme.line },
  contatosTitulo: { fontWeight: '700', color: theme.ink, marginBottom: 10 },
  contatosLinha: { flexDirection: 'row', gap: 8, flexWrap: 'wrap' },
  contatoBtn: { borderWidth: 1.5, borderColor: theme.ink, borderRadius: 10, paddingVertical: 8, paddingHorizontal: 16 },
  contatoTxt: { fontWeight: '700', color: theme.ink },
  secao: { fontSize: 16, fontWeight: '700', color: theme.ink, marginTop: 22, marginBottom: 8 },
  erro: { color: theme.danger, marginVertical: 8 },
  vazio: { color: theme.textMuted, textAlign: 'center', marginTop: 12 },
  card: { backgroundColor: theme.paper, borderRadius: 12, padding: 14, marginBottom: 10, borderWidth: 1, borderColor: theme.line },
  cardTopo: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  cardCategoria: { flex: 1, fontWeight: '700', color: theme.ink },
  bolinha: { width: 10, height: 10, borderRadius: 5, backgroundColor: theme.danger },
  selo: { borderRadius: 999, paddingVertical: 3, paddingHorizontal: 10 },
  seloTxt: { fontSize: 12, fontWeight: '700' },
  cardAssunto: { color: theme.textSecondary, marginTop: 6 },
  cardData: { color: theme.textMuted, fontSize: 12, marginTop: 6 },
  rotulo: { fontWeight: '700', color: theme.ink, marginTop: 18, marginBottom: 8 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { borderWidth: 1.5, borderColor: theme.line, backgroundColor: theme.paper, borderRadius: 999, paddingVertical: 8, paddingHorizontal: 14 },
  chipAtivo: { borderColor: theme.ink, backgroundColor: theme.amber },
  chipTxt: { color: theme.textSecondary, fontWeight: '600' },
  chipTxtAtivo: { color: theme.ink },
  corrida: { backgroundColor: theme.paper, borderRadius: 10, padding: 12, marginBottom: 8, borderWidth: 1.5, borderColor: theme.line },
  corridaAtiva: { borderColor: theme.ink, backgroundColor: '#FFF8DB' },
  corridaTopo: { color: theme.textSecondary, fontSize: 12 },
  corridaEnd: { color: theme.ink, fontWeight: '600', marginTop: 2 },
  entrada: {
    backgroundColor: theme.paper,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: theme.line,
    minHeight: 140,
    padding: 12,
    fontSize: 15,
    color: theme.text,
  },
  conversaTopo: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 6 },
  conversaTitulo: { fontSize: 18, fontWeight: '700', color: theme.ink, flex: 1, marginRight: 8 },
  balao: { maxWidth: '85%', borderRadius: 14, padding: 10, marginBottom: 8 },
  balaoEu: { alignSelf: 'flex-end', backgroundColor: '#FDE68A' },
  balaoEquipe: { alignSelf: 'flex-start', backgroundColor: theme.paper, borderWidth: 1, borderColor: theme.line },
  balaoAutor: { fontSize: 11, fontWeight: '700', color: theme.textSecondary, marginBottom: 2 },
  balaoTxt: { color: theme.text, fontSize: 15, lineHeight: 21 },
  balaoHora: { fontSize: 11, color: theme.textMuted, marginTop: 4, alignSelf: 'flex-end' },
  encerrado: { color: theme.textMuted, textAlign: 'center', marginVertical: 6 },
  caixa: { flexDirection: 'row', gap: 8, alignItems: 'flex-end', paddingTop: 8 },
  caixaEntrada: {
    flex: 1,
    backgroundColor: theme.paper,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: theme.line,
    paddingHorizontal: 12,
    paddingVertical: 10,
    maxHeight: 110,
    color: theme.text,
  },
  enviar: { backgroundColor: theme.amber, borderRadius: 12, paddingVertical: 12, paddingHorizontal: 16 },
  enviarTxt: { fontWeight: '700', color: theme.ink },
  encerrarLink: { color: theme.textSecondary, textDecorationLine: 'underline' },
});
