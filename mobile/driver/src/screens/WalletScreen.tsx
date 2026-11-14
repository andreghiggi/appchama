import React, { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  BackHandler,
  Image,
  Share,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { api } from '../../../shared/api';
import { formatBrl } from '../../../shared/fare';
import { theme } from '../../../shared/theme';
import type { DriverWithdrawal } from '../../../shared/types';

type Props = {
  onBack: () => void;
};

const PIX_TYPES = ['CPF', 'CNPJ', 'EMAIL', 'PHONE', 'EVP'] as const;

export default function WalletScreen({ onBack }: Props) {
  const insets = useSafeAreaInsets();
  const [wallet, setWallet] = useState<{
    balance_available: number;
    prepaid_balance?: number;
    prepaid_recharge_minimum?: number;
    total_earnings: number;
    payout_pix_key?: string | null;
    payout_pix_key_type?: string | null;
  } | null>(null);
  const [pixKey, setPixKey] = useState('');
  const [pixType, setPixType] = useState<(typeof PIX_TYPES)[number]>('EVP');
  const [amount, setAmount] = useState('');
  const [withdrawals, setWithdrawals] = useState<DriverWithdrawal[]>([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [rechargeAmount, setRechargeAmount] = useState('20');
  const [pix, setPix] = useState<{ qr: string | null; code: string | null; valor: number } | null>(null);
  const saldoAntesRef = useRef(0);

  const load = async () => {
    setLoading(true);
    try {
      const [w, list] = await Promise.all([api.getDriverWallet(), api.listWithdrawals()]);
      setWallet(w);
      setPixKey(w.payout_pix_key ?? '');
      setPixType((w.payout_pix_key_type as (typeof PIX_TYPES)[number]) ?? 'EVP');
      setWithdrawals(list.data ?? []);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  // Botão voltar do Android também sai da carteira.
  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      onBack();
      return true;
    });
    return () => sub.remove();
  }, [onBack]);

  const gerarRecarga = async () => {
    const valor = parseFloat(rechargeAmount.replace(',', '.'));
    const minimo = wallet?.prepaid_recharge_minimum ?? 20;
    if (!valor || valor < minimo) {
      Alert.alert('Valor inválido', `A recarga mínima é ${formatBrl(minimo)}.`);
      return;
    }
    setSubmitting(true);
    try {
      saldoAntesRef.current = wallet?.prepaid_balance ?? 0;
      const r = await api.rechargeWallet(valor);
      setPix({ qr: r.pix_qr_code, code: r.pix_copy_paste, valor });
    } catch (e: unknown) {
      Alert.alert('Não foi possível gerar o Pix', e instanceof Error ? e.message : 'Tente novamente.');
    } finally {
      setSubmitting(false);
    }
  };

  // Com o Pix na tela, confere a cada 5 s se o crédito já entrou (o Asaas avisa o servidor quando paga).
  useEffect(() => {
    if (!pix) return;
    const timer = setInterval(async () => {
      try {
        const w = await api.getDriverWallet();
        if ((w.prepaid_balance ?? 0) > saldoAntesRef.current) {
          setWallet(w);
          setPix(null);
          Alert.alert('Pagamento recebido', `Crédito atualizado: ${formatBrl(w.prepaid_balance ?? 0)}.`);
        }
      } catch {
        /* tenta de novo no próximo ciclo */
      }
    }, 5000);
    return () => clearInterval(timer);
  }, [pix]);

  const savePix = async () => {
    setSubmitting(true);
    try {
      await api.updatePayoutAccount(pixKey.trim(), pixType);
      Alert.alert('Salvo', 'Chave Pix atualizada.');
      await load();
    } catch (e: unknown) {
      Alert.alert('Erro', e instanceof Error ? e.message : 'Falha ao salvar');
    } finally {
      setSubmitting(false);
    }
  };

  const withdraw = async () => {
    const value = parseFloat(amount.replace(',', '.'));
    if (!value || value <= 0) {
      Alert.alert('Valor inválido', 'Informe um valor para saque.');
      return;
    }

    setSubmitting(true);
    try {
      await api.requestWithdrawal(value);
      Alert.alert('Saque solicitado', 'A transferência foi enviada.');
      setAmount('');
      await load();
    } catch (e: unknown) {
      Alert.alert('Erro', e instanceof Error ? e.message : 'Falha no saque');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading && !wallet) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={theme.amber} />
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={[styles.container, { paddingTop: 20 + insets.top }]}>
      <Pressable onPress={onBack} style={styles.back} hitSlop={16}>
        <Text style={styles.backText}>← Voltar</Text>
      </Pressable>

      <Text style={styles.title}>Carteira</Text>

      <View style={styles.card}>
        <Text style={styles.label}>Crédito para aceitar corridas</Text>
        <Text style={styles.balance}>{formatBrl(wallet?.prepaid_balance ?? 0)}</Text>
        <Text style={styles.hint}>
          A comissão de cada corrida é descontada daqui. Recarga mínima: {formatBrl(wallet?.prepaid_recharge_minimum ?? 20)}.
        </Text>
      </View>

      <View style={styles.card}>
        <Text style={styles.label}>Saldo disponível para saque</Text>
        <Text style={styles.balance}>{formatBrl(wallet?.balance_available ?? 0)}</Text>
        <Text style={styles.hint}>
          Total ganho: {formatBrl(wallet?.total_earnings ?? 0)}
        </Text>
      </View>

      <Text style={styles.sectionTitle}>Recarregar crédito por Pix</Text>
      {pix ? (
        <View style={styles.card}>
          <Text style={styles.label}>Pague {formatBrl(pix.valor)} no Pix e aguarde a confirmação</Text>
          {pix.qr ? (
            <Image
              source={{ uri: `data:image/png;base64,${pix.qr}` }}
              style={{ width: 220, height: 220, alignSelf: 'center', marginVertical: 12 }}
            />
          ) : null}
          {pix.code ? (
            <>
              <Text style={styles.hint} selectable numberOfLines={3}>
                {pix.code}
              </Text>
              <Pressable style={styles.btnSecondary} onPress={() => void Share.share({ message: pix.code ?? '' })}>
                <Text style={styles.btnSecondaryText}>Copiar / compartilhar código Pix</Text>
              </Pressable>
            </>
          ) : null}
          <Pressable style={styles.btnSecondary} onPress={() => setPix(null)}>
            <Text style={styles.btnSecondaryText}>Fechar</Text>
          </Pressable>
        </View>
      ) : (
        <>
          <TextInput
            style={styles.input}
            placeholder="Valor da recarga (R$)"
            value={rechargeAmount}
            onChangeText={setRechargeAmount}
            keyboardType="decimal-pad"
          />
          <Pressable style={styles.btn} onPress={gerarRecarga} disabled={submitting}>
            {submitting ? <ActivityIndicator color={theme.ink} /> : <Text style={styles.btnText}>Gerar Pix de recarga</Text>}
          </Pressable>
        </>
      )}

      <Text style={styles.sectionTitle}>Chave Pix para saque</Text>
      <TextInput
        style={styles.input}
        placeholder="Chave Pix"
        value={pixKey}
        onChangeText={setPixKey}
      />
      <View style={styles.typeRow}>
        {PIX_TYPES.map((t) => (
          <Pressable
            key={t}
            style={[styles.typeChip, pixType === t && styles.typeChipActive]}
            onPress={() => setPixType(t)}
          >
            <Text style={[styles.typeChipText, pixType === t && styles.typeChipTextActive]}>{t}</Text>
          </Pressable>
        ))}
      </View>
      <Pressable style={styles.btnSecondary} onPress={savePix} disabled={submitting}>
        <Text style={styles.btnSecondaryText}>Salvar chave Pix</Text>
      </Pressable>

      <Text style={styles.sectionTitle}>Solicitar saque</Text>
      <TextInput
        style={styles.input}
        placeholder="Valor (R$)"
        value={amount}
        onChangeText={setAmount}
        keyboardType="decimal-pad"
      />
      <Pressable style={styles.btn} onPress={withdraw} disabled={submitting}>
        {submitting ? (
          <ActivityIndicator color={theme.ink} />
        ) : (
          <Text style={styles.btnText}>Sacar valor</Text>
        )}
      </Pressable>

      <Text style={styles.sectionTitle}>Histórico</Text>
      {withdrawals.length === 0 ? (
        <Text style={styles.empty}>Nenhum saque ainda.</Text>
      ) : (
        withdrawals.map((w) => (
          <View key={w.id} style={styles.row}>
            <Text style={styles.rowAmount}>{formatBrl(Number(w.amount))}</Text>
            <Text style={styles.rowStatus}>{w.status}</Text>
          </View>
        ))
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  container: { flexGrow: 1, backgroundColor: theme.bg, padding: 20, paddingBottom: 40 },
  back: { marginBottom: 8, paddingVertical: 8, alignSelf: 'flex-start' },
  backText: { color: theme.ink, fontWeight: '600' },
  title: { fontSize: 24, fontWeight: '700', color: theme.ink, marginBottom: 16 },
  card: {
    backgroundColor: theme.paper,
    borderRadius: 16,
    padding: 20,
    marginBottom: 20,
  },
  label: { fontSize: 13, color: theme.textSecondary },
  balance: { fontSize: 28, fontWeight: '800', color: theme.mint, marginTop: 6 },
  hint: { fontSize: 12, color: theme.textSecondary, marginTop: 8 },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: theme.ink, marginBottom: 10, marginTop: 8 },
  input: {
    backgroundColor: theme.paper,
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: theme.line,
  },
  typeRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 12 },
  typeChip: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: theme.line,
    backgroundColor: theme.paper,
  },
  typeChipActive: { backgroundColor: theme.ink, borderColor: theme.ink },
  typeChipText: { fontSize: 11, fontWeight: '600', color: theme.ink },
  typeChipTextActive: { color: '#fff' },
  btn: {
    backgroundColor: theme.amber,
    borderRadius: 13,
    padding: 16,
    alignItems: 'center',
    marginBottom: 20,
  },
  btnText: { fontWeight: '700', color: theme.ink },
  btnSecondary: {
    borderRadius: 13,
    padding: 14,
    alignItems: 'center',
    marginBottom: 20,
    borderWidth: 1,
    borderColor: theme.line,
    backgroundColor: theme.paper,
  },
  btnSecondaryText: { fontWeight: '600', color: theme.ink },
  empty: { color: theme.textSecondary, fontSize: 14 },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: theme.line,
  },
  rowAmount: { fontWeight: '600', color: theme.ink },
  rowStatus: { color: theme.textSecondary, fontSize: 13 },
});
