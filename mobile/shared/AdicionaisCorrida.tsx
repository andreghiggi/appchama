import React, { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Modal, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { api } from './api';
import { formatBrl } from './fare';
import { theme } from './theme';
import type { RideExtra } from './types';

/**
 * Adicional "por fora": parada, espera ou distância a mais combinada durante a viagem.
 * O valor NÃO entra no preço da corrida nem no pagamento do app: o passageiro paga direto ao motorista.
 */

const MOTIVOS: { id: string; rotulo: string }[] = [
  { id: 'stop', rotulo: 'Parada no caminho' },
  { id: 'wait', rotulo: 'Tempo de espera' },
  { id: 'distance', rotulo: 'Distância a mais' },
  { id: 'other', rotulo: 'Outro motivo' },
];

function useAdicionais(rideId: string, ativo: boolean) {
  const [lista, setLista] = useState<RideExtra[]>([]);
  const carregar = useCallback(async () => {
    try {
      const r = await api.listRideExtras(rideId);
      setLista(r.data);
    } catch {
      /* sem rede: mantém o que já tinha */
    }
  }, [rideId]);

  useEffect(() => {
    void carregar();
    if (!ativo) return;
    const t = setInterval(() => void carregar(), 6000);
    return () => clearInterval(t);
  }, [carregar, ativo]);

  return { lista, carregar };
}

const nomeStatus: Record<string, string> = {
  pending: 'Aguardando o passageiro',
  accepted: 'Aceito',
  disputed: 'Passageiro não concordou',
  canceled: 'Desfeito',
};

/** Passageiro: responde ao adicional pedido pelo motorista. `resumo` só mostra o total combinado (tela de pagamento). */
export function AdicionaisPassageiro({ rideId, ativo, resumo = false }: { rideId: string; ativo: boolean; resumo?: boolean }) {
  const { lista, carregar } = useAdicionais(rideId, ativo);
  const [enviando, setEnviando] = useState<string | null>(null);

  const aceitos = lista.filter((e) => e.status === 'accepted');
  const totalAceito = aceitos.reduce((s, e) => s + e.amount, 0);
  const pendentes = lista.filter((e) => e.status === 'pending');

  const responder = async (e: RideExtra, acao: 'accept' | 'dispute') => {
    setEnviando(e.id);
    try {
      await (acao === 'accept' ? api.acceptRideExtra(rideId, e.id) : api.disputeRideExtra(rideId, e.id));
      await carregar();
    } catch (err) {
      Alert.alert('Não foi possível responder', err instanceof Error ? err.message : 'Tente de novo.');
    } finally {
      setEnviando(null);
    }
  };

  const discordar = (e: RideExtra) => {
    Alert.alert('Não concordar com o adicional?', 'O motorista será avisado e a equipe vai analisar o caso.', [
      { text: 'Voltar', style: 'cancel' },
      { text: 'Não concordo', style: 'destructive', onPress: () => void responder(e, 'dispute') },
    ]);
  };

  if (resumo) {
    if (totalAceito <= 0) return null;
    return (
      <View style={estilos.resumo}>
        <Text style={estilos.resumoTxt}>
          Adicionais combinados: {formatBrl(totalAceito)}. Pague direto ao motorista (fora do valor da corrida).
        </Text>
      </View>
    );
  }

  if (lista.length === 0) return null;

  return (
    <View>
      {pendentes.map((e) => (
        <View key={e.id} style={estilos.cardPedido}>
          <Text style={estilos.pedidoTitulo}>O motorista pediu um adicional</Text>
          <Text style={estilos.pedidoValor}>
            {e.reason_label} · {formatBrl(e.amount)}
          </Text>
          {e.note ? <Text style={estilos.pedidoNota}>“{e.note}”</Text> : null}
          <Text style={estilos.pedidoAviso}>Esse valor é pago direto ao motorista, fora do preço da corrida.</Text>
          <View style={estilos.linhaBotoes}>
            <Pressable style={[estilos.btnSim, enviando === e.id && { opacity: 0.6 }]} onPress={() => void responder(e, 'accept')} disabled={enviando === e.id}>
              {enviando === e.id ? <ActivityIndicator color={theme.ink} /> : <Text style={estilos.btnSimTxt}>Concordo</Text>}
            </Pressable>
            <Pressable style={estilos.btnNao} onPress={() => discordar(e)} disabled={enviando === e.id}>
              <Text style={estilos.btnNaoTxt}>Não concordo</Text>
            </Pressable>
          </View>
        </View>
      ))}
      {aceitos.length > 0 ? (
        <Text style={estilos.combinado}>
          Adicional combinado: {formatBrl(totalAceito)} — pague direto ao motorista.
        </Text>
      ) : null}
    </View>
  );
}

/** Motorista: cobra um adicional durante a viagem e acompanha a resposta do passageiro. */
export function AdicionaisMotorista({ rideId, status }: { rideId: string; status: string }) {
  const emViagem = status === 'in_progress';
  const { lista, carregar } = useAdicionais(rideId, emViagem || status === 'awaiting_payment');
  const [aberto, setAberto] = useState(false);
  const [motivo, setMotivo] = useState('');
  const [valor, setValor] = useState('');
  const [nota, setNota] = useState('');
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  const temPendente = lista.some((e) => e.status === 'pending');
  const aceitos = lista.filter((e) => e.status === 'accepted');
  const totalAceito = aceitos.reduce((s, e) => s + e.amount, 0);

  const fechar = () => {
    setAberto(false);
    setErro(null);
  };

  const enviar = async () => {
    const n = Number(valor.replace(/\./g, '').replace(',', '.'));
    if (!motivo) return setErro('Escolha o motivo.');
    if (!Number.isFinite(n) || n < 1) return setErro('Informe o valor (mínimo R$ 1,00).');
    setEnviando(true);
    setErro(null);
    try {
      await api.createRideExtra(rideId, n, motivo, nota.trim() || undefined);
      setMotivo('');
      setValor('');
      setNota('');
      setAberto(false);
      await carregar();
    } catch (e) {
      setErro(e instanceof Error ? e.message : 'Não foi possível enviar');
    } finally {
      setEnviando(false);
    }
  };

  const desfazer = (e: RideExtra) => {
    api
      .cancelRideExtra(rideId, e.id)
      .then(() => carregar())
      .catch((err) => Alert.alert('Não foi possível desfazer', err instanceof Error ? err.message : 'Tente de novo.'));
  };

  if (!emViagem && lista.length === 0) return null;

  return (
    <View style={{ marginTop: 8 }}>
      {emViagem ? (
        <Pressable style={[estilos.btnCobrar, temPendente && { opacity: 0.5 }]} onPress={() => setAberto(true)} disabled={temPendente}>
          <Text style={estilos.btnCobrarTxt}>{temPendente ? 'Aguardando o passageiro responder…' : 'Cobrar adicional (parada / espera)'}</Text>
        </Pressable>
      ) : null}

      {lista.map((e) => (
        <View key={e.id} style={estilos.itemLinha}>
          <View style={{ flex: 1 }}>
            <Text style={estilos.itemTxt}>
              {e.reason_label} · {formatBrl(e.amount)}
            </Text>
            <Text style={estilos.itemStatus}>{nomeStatus[e.status] ?? e.status}</Text>
          </View>
          {e.status === 'pending' ? (
            <Pressable onPress={() => desfazer(e)} hitSlop={10}>
              <Text style={estilos.desfazer}>Desfazer</Text>
            </Pressable>
          ) : null}
        </View>
      ))}
      {totalAceito > 0 ? (
        <Text style={estilos.receber}>Receber direto do passageiro: {formatBrl(totalAceito)} (fora do valor da corrida)</Text>
      ) : null}

      <Modal visible={aberto} transparent animationType="fade" onRequestClose={fechar}>
        <View style={estilos.fundo}>
          <View style={estilos.caixa}>
            <Text style={estilos.caixaTitulo}>Cobrar adicional</Text>
            <Text style={estilos.caixaSub}>
              Use quando o passageiro pedir uma parada, para esperar ou andar mais um pouco. Ele precisa concordar. O valor é recebido direto
              dele e não passa pelo app.
            </Text>
            <View style={estilos.chips}>
              {MOTIVOS.map((m) => (
                <Pressable key={m.id} onPress={() => setMotivo(m.id)} style={[estilos.chip, motivo === m.id && estilos.chipAtivo]}>
                  <Text style={[estilos.chipTxt, motivo === m.id && estilos.chipTxtAtivo]}>{m.rotulo}</Text>
                </Pressable>
              ))}
            </View>
            <TextInput
              style={estilos.campo}
              value={valor}
              onChangeText={setValor}
              placeholder="Valor (R$)  ex.: 8,50"
              placeholderTextColor={theme.textMuted}
              keyboardType="decimal-pad"
            />
            <TextInput
              style={estilos.campo}
              value={nota}
              onChangeText={setNota}
              placeholder="Observação (opcional)"
              placeholderTextColor={theme.textMuted}
              maxLength={120}
            />
            {erro ? <Text style={estilos.erro}>{erro}</Text> : null}
            <View style={estilos.linhaBotoes}>
              <Pressable style={estilos.btnNao} onPress={fechar}>
                <Text style={estilos.btnNaoTxt}>Cancelar</Text>
              </Pressable>
              <Pressable style={[estilos.btnSim, enviando && { opacity: 0.6 }]} onPress={() => void enviar()} disabled={enviando}>
                {enviando ? <ActivityIndicator color={theme.ink} /> : <Text style={estilos.btnSimTxt}>Enviar ao passageiro</Text>}
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const estilos = StyleSheet.create({
  cardPedido: { backgroundColor: '#FFF8DB', borderColor: theme.amber, borderWidth: 2, borderRadius: 14, padding: 14, marginBottom: 10 },
  pedidoTitulo: { fontWeight: '800', color: theme.ink, fontSize: 16 },
  pedidoValor: { fontSize: 20, fontWeight: '800', color: theme.ink, marginTop: 4 },
  pedidoNota: { color: theme.textSecondary, marginTop: 4, fontStyle: 'italic' },
  pedidoAviso: { color: theme.textSecondary, marginTop: 6, fontSize: 13 },
  linhaBotoes: { flexDirection: 'row', gap: 10, marginTop: 12 },
  btnSim: { flex: 1, backgroundColor: theme.amber, borderRadius: 10, paddingVertical: 12, alignItems: 'center' },
  btnSimTxt: { fontWeight: '800', color: theme.ink },
  btnNao: { flex: 1, borderWidth: 1.5, borderColor: theme.ink, borderRadius: 10, paddingVertical: 12, alignItems: 'center' },
  btnNaoTxt: { fontWeight: '700', color: theme.ink },
  combinado: { color: theme.textSecondary, marginBottom: 8, fontWeight: '600' },
  resumo: { backgroundColor: '#FEF3C7', borderRadius: 10, padding: 12, marginBottom: 14 },
  resumoTxt: { color: '#92400E', lineHeight: 18 },
  btnCobrar: { borderWidth: 1.5, borderColor: theme.ink, borderRadius: 12, paddingVertical: 12, alignItems: 'center' },
  btnCobrarTxt: { fontWeight: '700', color: theme.ink },
  itemLinha: { flexDirection: 'row', alignItems: 'center', backgroundColor: theme.paper, borderRadius: 10, borderWidth: 1, borderColor: theme.line, padding: 10, marginTop: 8 },
  itemTxt: { fontWeight: '700', color: theme.ink },
  itemStatus: { color: theme.textSecondary, fontSize: 12, marginTop: 2 },
  desfazer: { color: theme.danger, fontWeight: '700' },
  receber: { marginTop: 8, fontWeight: '700', color: theme.ink },
  fundo: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'center', padding: 20 },
  caixa: { backgroundColor: theme.paper, borderRadius: 16, padding: 18 },
  caixaTitulo: { fontSize: 20, fontWeight: '800', color: theme.ink },
  caixaSub: { color: theme.textSecondary, marginTop: 6, marginBottom: 12, lineHeight: 19 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 10 },
  chip: { borderWidth: 1.5, borderColor: theme.line, borderRadius: 999, paddingVertical: 7, paddingHorizontal: 12 },
  chipAtivo: { borderColor: theme.ink, backgroundColor: theme.amber },
  chipTxt: { color: theme.textSecondary, fontWeight: '600' },
  chipTxtAtivo: { color: theme.ink },
  campo: { borderWidth: 1, borderColor: theme.line, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 10, marginTop: 8, color: theme.text },
  erro: { color: theme.danger, marginTop: 8 },
});
