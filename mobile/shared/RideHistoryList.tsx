import React, { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { api } from './api';
import { formatBrl } from './fare';
import { rideStatusLabel } from './labels';
import { theme } from './theme';
import type { Ride } from './types';

type Props = {
  /** 'driver' mostra o ganho líquido e o nome do passageiro; 'passenger' mostra o valor e o motorista. */
  papel: 'driver' | 'passenger';
  vazio?: string;
};

function dataBr(iso?: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const dd = String(d.getDate()).padStart(2, '0');
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const hh = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  return `${dd}/${mm}/${d.getFullYear()} ${hh}:${mi}`;
}

function corDoStatus(status: string): { fundo: string; texto: string } {
  if (status === 'completed') return { fundo: '#DCFCE7', texto: '#166534' };
  if (status.startsWith('canceled') || status === 'no_drivers_available') return { fundo: '#FEE2E2', texto: '#991B1B' };
  return { fundo: '#FEF3C7', texto: '#92400E' };
}

function valorDaCorrida(r: Ride, papel: 'driver' | 'passenger'): number {
  if (papel === 'driver') return Number(r.driver_net_amount ?? r.final_fare ?? r.estimated_fare ?? 0);
  return Number(r.final_fare ?? r.estimated_fare ?? 0);
}

/** Lista paginada das corridas do usuário (mais recentes primeiro), com puxar para atualizar e "carregar mais". */
export function RideHistoryList({ papel, vazio = 'Nenhuma corrida ainda' }: Props) {
  const [corridas, setCorridas] = useState<Ride[]>([]);
  const [pagina, setPagina] = useState(1);
  const [ultima, setUltima] = useState(1);
  const [carregando, setCarregando] = useState(true);
  const [atualizando, setAtualizando] = useState(false);
  const [maisCarregando, setMaisCarregando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  const carregar = useCallback(async (alvo: number, substituir: boolean) => {
    try {
      const res = await api.listRides(alvo);
      setCorridas((atual) => {
        const novas = res.data ?? [];
        if (substituir) return novas;
        const ids = new Set(atual.map((c) => c.id));
        return [...atual, ...novas.filter((c) => !ids.has(c.id))];
      });
      setPagina(res.current_page ?? alvo);
      setUltima(res.last_page ?? alvo);
      setErro(null);
    } catch (e: unknown) {
      setErro(e instanceof Error ? e.message : 'Não foi possível carregar as corridas.');
    }
  }, []);

  useEffect(() => {
    void carregar(1, true).finally(() => setCarregando(false));
  }, [carregar]);

  const atualizar = async () => {
    setAtualizando(true);
    await carregar(1, true);
    setAtualizando(false);
  };

  const carregarMais = async () => {
    if (maisCarregando || carregando || pagina >= ultima) return;
    setMaisCarregando(true);
    await carregar(pagina + 1, false);
    setMaisCarregando(false);
  };

  const concluidas = corridas.filter((c) => c.status === 'completed');
  const total = concluidas.reduce((soma, c) => soma + valorDaCorrida(c, papel), 0);

  if (carregando) {
    return <ActivityIndicator style={{ marginTop: 40 }} color={theme.amber} />;
  }

  return (
    <FlatList
      data={corridas}
      keyExtractor={(item) => item.id}
      onEndReached={carregarMais}
      onEndReachedThreshold={0.4}
      refreshControl={<RefreshControl refreshing={atualizando} onRefresh={atualizar} />}
      ListHeaderComponent={
        corridas.length > 0 ? (
          <View style={styles.resumo}>
            <Text style={styles.resumoTxt}>
              {concluidas.length} concluída{concluidas.length === 1 ? '' : 's'}
              {' · '}
              {papel === 'driver' ? 'Ganho' : 'Gasto'}: {formatBrl(total)}
            </Text>
            <Text style={styles.resumoSub}>Considera as corridas já carregadas na lista.</Text>
          </View>
        ) : null
      }
      ListEmptyComponent={
        <View>
          <Text style={styles.vazio}>{erro ?? vazio}</Text>
          {erro ? (
            <Pressable onPress={atualizar} hitSlop={12}>
              <Text style={styles.link}>Tentar de novo</Text>
            </Pressable>
          ) : null}
        </View>
      }
      ListFooterComponent={maisCarregando ? <ActivityIndicator style={{ margin: 12 }} color={theme.amber} /> : null}
      renderItem={({ item }) => {
        const cor = corDoStatus(item.status);
        const outro =
          papel === 'driver' ? item.passenger?.name : item.driver?.user?.name;
        return (
          <View style={styles.card}>
            <View style={styles.linhaTopo}>
              <Text style={styles.data}>{dataBr(item.requested_at)}</Text>
              <View style={[styles.badge, { backgroundColor: cor.fundo }]}>
                <Text style={[styles.badgeTxt, { color: cor.texto }]}>{rideStatusLabel(item.status)}</Text>
              </View>
            </View>
            {item.origin_address ? (
              <Text style={styles.endereco} numberOfLines={1}>
                De: {item.origin_address}
              </Text>
            ) : null}
            <Text style={styles.cardTitle} numberOfLines={2}>
              Para: {item.destination_address ?? 'Destino'}
            </Text>
            <View style={styles.linhaBase}>
              <Text style={styles.meta}>
                {outro ? `${papel === 'driver' ? 'Passageiro' : 'Motorista'}: ${outro}` : ' '}
              </Text>
              <Text style={styles.valor}>{formatBrl(valorDaCorrida(item, papel))}</Text>
            </View>
            {item.status.startsWith('canceled') && item.canceled_reason ? (
              <Text style={styles.motivo}>Motivo: {item.canceled_reason}</Text>
            ) : null}
          </View>
        );
      }}
    />
  );
}

const styles = StyleSheet.create({
  resumo: { backgroundColor: theme.mintBg, borderRadius: 12, padding: 12, marginBottom: 12 },
  resumoTxt: { color: theme.ink, fontWeight: '700' },
  resumoSub: { color: theme.textSecondary, fontSize: 11, marginTop: 2 },
  vazio: { color: theme.textMuted, textAlign: 'center', marginTop: 40 },
  link: { color: theme.ink, fontWeight: '700', textAlign: 'center', marginTop: 12 },
  card: {
    backgroundColor: theme.paper,
    borderRadius: 14,
    padding: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: theme.line,
  },
  linhaTopo: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 },
  data: { color: theme.textSecondary, fontSize: 12 },
  badge: { borderRadius: 8, paddingHorizontal: 8, paddingVertical: 3 },
  badgeTxt: { fontSize: 11, fontWeight: '700' },
  endereco: { color: theme.textSecondary, fontSize: 12, marginBottom: 2 },
  cardTitle: { fontWeight: '600', color: theme.ink },
  linhaBase: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 8 },
  meta: { color: theme.textSecondary, fontSize: 12, flex: 1, marginRight: 8 },
  valor: { fontWeight: '700', color: theme.ink },
  motivo: { color: '#991B1B', fontSize: 12, marginTop: 6 },
});
