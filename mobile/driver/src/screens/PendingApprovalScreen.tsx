import React, { useEffect } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { LogoutButton } from '../components/LogoutButton';
import { useAuth } from '../context/AuthContext';
import { theme } from '../../../shared/theme';
import { subscriptionStatusLabel } from '../../../shared/labels';

export default function PendingApprovalScreen() {
  const { user, logout, refreshUser } = useAuth();
  const driver = user?.driver;
  const isAppRegistration = driver?.registration_source === 'app';
  const subscription = driver?.subscription_status
    ? subscriptionStatusLabel(driver.subscription_status)
    : 'Pendente';

  useEffect(() => {
    const interval = setInterval(() => {
      void refreshUser();
    }, 15000);

    return () => clearInterval(interval);
  }, [refreshUser]);

  return (
    <View style={styles.container}>
      <View style={styles.topBar}>
        <LogoutButton inverted onPress={logout} />
      </View>
      <Text style={styles.title}>Cadastro em análise</Text>
      <Text style={styles.text}>
        Olá, {user?.name?.split(' ')[0] ?? 'motorista'}.{' '}
        {isAppRegistration
          ? 'Recebemos seus dados e a CNH. A equipe Chama vai analisar e liberar seu acesso em breve.'
          : 'Seu login foi confirmado e o cadastro aguarda validação da equipe Chama no painel administrativo.'}
      </Text>
      <View style={styles.card}>
        {!isAppRegistration ? (
          <>
            <Text style={styles.label}>Código de cadastro</Text>
            <Text style={styles.code}>{driver?.registration_code ?? '—'}</Text>
          </>
        ) : (
          <>
            <Text style={styles.label}>Origem</Text>
            <Text style={styles.value}>Cadastro pelo app</Text>
            <Text style={[styles.hintInline, { marginTop: 8 }]}>
              A CNH enviada está disponível para análise no painel administrativo.
            </Text>
          </>
        )}
        <Text style={[styles.label, { marginTop: 12 }]}>Status da conta</Text>
        <Text style={styles.value}>{user?.status === 'pending' ? 'Pendente' : user?.status}</Text>
        <Text style={[styles.label, { marginTop: 12 }]}>Antecedentes</Text>
        <Text style={styles.value}>
          {driver?.background_check_status === 'approved'
            ? 'Aprovado'
            : driver?.background_check_status === 'rejected'
              ? 'Rejeitado'
              : 'Pendente'}
        </Text>
        <Text style={[styles.label, { marginTop: 12 }]}>Mensalidade</Text>
        <Text style={styles.value}>{subscription}</Text>
      </View>
      <View style={styles.pollingRow}>
        <ActivityIndicator size="small" color={theme.amber} />
        <Text style={styles.hint}>Aguardando validação… esta tela atualiza automaticamente.</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.bg, padding: 24, justifyContent: 'center' },
  topBar: { position: 'absolute', top: 16, right: 16, zIndex: 10 },
  title: { fontSize: 22, fontWeight: '700', color: theme.ink, marginBottom: 12 },
  text: { fontSize: 15, color: theme.textSecondary, lineHeight: 22, marginBottom: 20 },
  card: {
    backgroundColor: theme.paper,
    borderRadius: 16,
    padding: 16,
    marginBottom: 16,
  },
  label: { fontSize: 12, color: theme.textMuted, fontWeight: '600' },
  value: { fontSize: 16, color: theme.ink, fontWeight: '600', marginTop: 4 },
  code: { fontSize: 22, color: theme.ink, fontWeight: '800', marginTop: 4, letterSpacing: 1 },
  hintInline: { fontSize: 13, color: theme.textSecondary, lineHeight: 18 },
  pollingRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  hint: { fontSize: 13, color: theme.textSecondary, flex: 1 },
});
