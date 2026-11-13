import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  Linking,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { api } from '../../../shared/api';
import { formatBrl } from '../../../shared/fare';
import { theme } from '../../../shared/theme';
import type { Ride, RidePaymentInfo } from '../../../shared/types';
import PaymentProfileForm from '../components/PaymentProfileForm';
import { useAuth } from '../context/AuthContext';

type Props = {
  ride: Ride;
  onPaid: (ride: Ride) => void;
};

export default function PaymentScreen({ ride: initial, onPaid }: Props) {
  const { user, updateUser } = useAuth();
  const [ride, setRide] = useState(initial);
  const [info, setInfo] = useState<RidePaymentInfo | null>(null);
  const [loading, setLoading] = useState(false);
  const [paying, setPaying] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const amount = Number(ride.final_fare ?? ride.estimated_fare ?? 0);

  const loadPayment = async () => {
    try {
      const data = await api.getRidePayment(ride.id);
      setInfo(data);
      if (data.payment_status === 'paid' || data.status === 'completed') {
        const updated = await api.getRide(ride.id);
        setRide(updated);
        onPaid(updated);
      }
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Erro ao carregar pagamento');
    }
  };

  useEffect(() => {
    loadPayment();
    const timer = setInterval(loadPayment, 3000);
    return () => clearInterval(timer);
  }, [ride.id]);

  const pay = async (method: 'pix' | 'credit_card') => {
    setPaying(true);
    setError(null);
    try {
      const baseUrl =
        Platform.OS === 'web' && typeof window !== 'undefined'
          ? window.location.origin + window.location.pathname
          : undefined;

      const result = await api.payRide(ride.id, method, {
        success_url: baseUrl,
        cancel_url: baseUrl,
      });
      setInfo(result);

      if (method === 'credit_card' && result.checkout_url) {
        if (Platform.OS === 'web') {
          window.open(result.checkout_url, '_blank');
        } else {
          await Linking.openURL(result.checkout_url);
        }
      }
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Falha ao iniciar pagamento');
    } finally {
      setPaying(false);
    }
  };

  const copyPix = async () => {
    const code = info?.pix_copy_paste;
    if (!code) return;

    if (Platform.OS === 'web' && typeof navigator !== 'undefined' && navigator.clipboard) {
      await navigator.clipboard.writeText(code);
      Alert.alert('Copiado', 'Código Pix copiado.');
    } else {
      Alert.alert('Pix', code);
    }
  };

  const methods = info?.methods_enabled ?? {};
  const profileComplete = info?.payment_profile_complete ?? user?.cpf != null;

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <Text style={styles.title}>Pagamento da corrida</Text>
      <Text style={styles.subtitle}>Finalize o pagamento para concluir a viagem</Text>

      {!profileComplete && user ? (
        <PaymentProfileForm
          user={user}
          onSaved={(updated) => {
            updateUser(updated);
            void loadPayment();
          }}
        />
      ) : null}

      <View style={styles.amountBox}>
        <Text style={styles.amountLabel}>Valor</Text>
        <Text style={styles.amount}>{formatBrl(amount)}</Text>
      </View>

      {methods.direct_driver ? (
        <Text style={styles.directHint}>
          Pagamento direto ao motorista: confirme com o motorista após pagar em dinheiro ou Pix
          pessoal.
        </Text>
      ) : null}

      <View style={styles.methods}>
        {methods.pix ? (
          <Pressable
            style={[styles.methodBtn, !profileComplete && styles.methodBtnDisabled]}
            onPress={() => pay('pix')}
            disabled={paying || !profileComplete}
          >
            <Text style={styles.methodBtnText}>Pagar com Pix</Text>
          </Pressable>
        ) : null}

        {methods.credit_card ? (
          <Pressable
            style={[styles.methodBtn, !profileComplete && styles.methodBtnDisabled]}
            onPress={() => pay('credit_card')}
            disabled={paying || !profileComplete}
          >
            <Text style={styles.methodBtnText}>Pagar com cartão</Text>
          </Pressable>
        ) : null}
      </View>

      {paying || loading ? <ActivityIndicator color={theme.amber} style={{ marginTop: 16 }} /> : null}

      {info?.pix_qr_code ? (
        <View style={styles.pixBox}>
          <Text style={styles.pixTitle}>Escaneie o QR Code Pix</Text>
          <Image
            source={{ uri: `data:image/png;base64,${info.pix_qr_code}` }}
            style={styles.qr}
            resizeMode="contain"
          />
          {info.pix_copy_paste ? (
            <Pressable style={styles.copyBtn} onPress={copyPix}>
              <Text style={styles.copyBtnText}>Copiar código Pix</Text>
            </Pressable>
          ) : null}
          <Text style={styles.waitHint}>Aguardando confirmação do pagamento...</Text>
        </View>
      ) : null}

      {info?.checkout_url && !info.pix_qr_code ? (
        <Text style={styles.waitHint}>
          Complete o pagamento na janela do cartão. Esta tela atualiza automaticamente.
        </Text>
      ) : null}

      {error ? <Text style={styles.error}>{error}</Text> : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flexGrow: 1, backgroundColor: theme.bg, padding: 24, justifyContent: 'center' },
  title: { fontSize: 24, fontWeight: '700', color: theme.ink, marginBottom: 4 },
  subtitle: { fontSize: 14, color: theme.textSecondary, marginBottom: 20 },
  amountBox: {
    backgroundColor: theme.paper,
    borderRadius: 16,
    padding: 20,
    alignItems: 'center',
    marginBottom: 20,
  },
  amountLabel: { fontSize: 13, color: theme.textSecondary },
  amount: { fontSize: 32, fontWeight: '800', color: theme.mint, marginTop: 6 },
  directHint: {
    fontSize: 13,
    color: theme.textSecondary,
    marginBottom: 16,
    lineHeight: 18,
    textAlign: 'center',
  },
  methods: { gap: 10 },
  methodBtn: {
    backgroundColor: theme.amber,
    borderRadius: 13,
    padding: 16,
    alignItems: 'center',
  },
  methodBtnText: { fontWeight: '700', color: theme.ink, fontSize: 16 },
  methodBtnDisabled: { opacity: 0.5 },
  pixBox: { marginTop: 24, alignItems: 'center' },
  pixTitle: { fontWeight: '600', color: theme.ink, marginBottom: 12 },
  qr: { width: 220, height: 220, backgroundColor: '#fff', borderRadius: 12 },
  copyBtn: { marginTop: 12, padding: 10 },
  copyBtnText: { color: theme.ink, fontWeight: '600' },
  waitHint: { marginTop: 16, fontSize: 13, color: theme.textSecondary, textAlign: 'center' },
  error: { color: theme.danger, marginTop: 16, textAlign: 'center' },
});
