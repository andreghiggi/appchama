import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Linking,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { activateKeepAwakeAsync, deactivateKeepAwake } from 'expo-keep-awake';
import { api } from '../../../shared/api';
import { toNumber } from '../../../shared/coords';
import { formatBrl } from '../../../shared/fare';
import { bearingBetween, distanciaMetros } from '../../../shared/geo';
import { requestCurrentLocation, startLocationWatch } from '../../../shared/location';
import NavBanner from '../../../shared/NavBanner';
import { distanciaAteAlvoKm, formatEta, mensagemMotorista, zoomPorDistanciaKm } from '../../../shared/navegacao';
import { AppMap, AppMarker, AppRoute } from '../../../shared/SimpleMap';
import { rideStatusLabel } from '../../../shared/labels';
import { theme } from '../../../shared/theme';
import type { Ride } from '../../../shared/types';

type Props = {
  rideId: string;
  onDone: () => void;
};

type Ponto = { latitude: number; longitude: number };

export default function ActiveRideScreen({ rideId, onDone }: Props) {
  const [ride, setRide] = useState<Ride | null>(null);
  const [loading, setLoading] = useState(false);
  const [myLocation, setMyLocation] = useState<Ponto | null>(null);
  const [rumo, setRumo] = useState<number | null>(null);
  const [seguindo, setSeguindo] = useState(true);
  const [confirmingDirect, setConfirmingDirect] = useState(false);
  const [canceled, setCanceled] = useState(false);
  const stopWatchRef = useRef<(() => void) | null>(null);
  const ultimaPosicaoRef = useRef<Ponto | null>(null);

  useEffect(() => {
    let alive = true;
    let timer: ReturnType<typeof setInterval> | null = null;

    const load = async () => {
      try {
        const next = await api.getRide(rideId);
        if (!alive) return;
        setRide(next);
        if (next.status.startsWith('canceled') && timer) {
          setCanceled(true);
          clearInterval(timer);
        }
        if (next.status === 'completed' && timer) {
          clearInterval(timer);
        }
      } catch {
        /* ignore polling errors */
      }
    };

    load();
    timer = setInterval(load, 3000);

    return () => {
      alive = false;
      if (timer) clearInterval(timer);
    };
  }, [rideId]);

  useEffect(() => {
    requestCurrentLocation()
      .then((coords) => setMyLocation(coords))
      .catch(() => {});

    stopWatchRef.current = startLocationWatch(async (coords) => {
      // Rumo do carro (para girar o mapa como num navegador), pelo deslocamento entre as posicoes.
      const anterior = ultimaPosicaoRef.current;
      if (anterior && distanciaMetros(anterior, coords) >= 8) {
        const b = bearingBetween(anterior, coords);
        if (b != null) setRumo(b);
        ultimaPosicaoRef.current = coords;
      } else if (!anterior) {
        ultimaPosicaoRef.current = coords;
      }
      setMyLocation(coords);
      try {
        await api.updateLocation(coords.latitude, coords.longitude);
      } catch {
        /* ignore */
      }
    }, 8000);

    return () => {
      if (stopWatchRef.current) {
        stopWatchRef.current();
        stopWatchRef.current = null;
      }
    };
  }, []);

  // Do aceite ate o fim da corrida a tela do motorista nao apaga sozinha.
  const emAndamento = ['accepted', 'driver_arrived', 'in_progress', 'awaiting_payment'].includes(ride?.status ?? '');
  useEffect(() => {
    if (!emAndamento) return;
    activateKeepAwakeAsync('corrida').catch(() => undefined);
    return () => {
      deactivateKeepAwake('corrida').catch(() => undefined);
    };
  }, [emAndamento]);

  const pagamentoDireto = ride?.payment_method === 'direct_driver';
  const aguardandoPagamento = ride?.status === 'awaiting_payment';
  const corridaPaga = ride?.status === 'completed';
  const status = ride?.status;

  const lat = toNumber(ride?.origin_lat, -23.5505);
  const lng = toNumber(ride?.origin_lng, -46.6333);
  const destLat = toNumber(ride?.destination_lat, lat + 0.02);
  const destLng = toNumber(ride?.destination_lng, lng + 0.02);
  const embarque: Ponto = { latitude: lat, longitude: lng };
  const destino: Ponto = { latitude: destLat, longitude: destLng };

  // O valor mostrado ao motorista e o da corrida (o mesmo que o passageiro paga), nunca o liquido.
  const valorCorrida = Number(ride?.final_fare ?? ride?.estimated_fare ?? 0);
  const etaMin = toNumber(ride?.driver_eta_min);
  const etaTexto = formatEta(Number.isFinite(etaMin) ? etaMin : null);

  // Proximo ponto: embarque ate o passageiro entrar, depois o destino.
  const alvo = status === 'in_progress' || status === 'awaiting_payment' || status === 'completed' ? destino : embarque;
  const navegando = status === 'accepted' || status === 'in_progress';

  const distanciaKm = useMemo(() => {
    const api_ = distanciaAteAlvoKm(ride);
    if (api_ != null) return api_;
    if (myLocation) return distanciaMetros(myLocation, alvo) / 1000;
    return null;
  }, [ride, myLocation, alvo.latitude, alvo.longitude]);

  const follow = navegando && myLocation ? { center: myLocation, heading: rumo, zoom: zoomPorDistanciaKm(distanciaKm) } : null;

  // Fora da navegacao (chegou no embarque, fim) mostra o trajeto inteiro.
  const fitCoords = useMemo<Ponto[]>(() => {
    if (navegando) return [];
    return [embarque, destino];
  }, [navegando, lat, lng, destLat, destLng]);

  const routeCoords =
    ride?.route_coordinates && ride.route_coordinates.length >= 2
      ? ride.route_coordinates
      : myLocation
        ? [myLocation, alvo]
        : [embarque, destino];

  const cancelRide = async () => {
    setLoading(true);
    try {
      await api.cancelRide(rideId, 'Cancelado pelo motorista');
      onDone();
    } catch (e: unknown) {
      setLoading(false);
      Alert.alert('Não foi possível cancelar', e instanceof Error ? e.message : 'Tente novamente.');
    }
  };

  const nextAction = async () => {
    setLoading(true);
    try {
      if (ride?.status === 'accepted') {
        setRide(await api.arriveRide(rideId));
      } else if (ride?.status === 'driver_arrived') {
        // Ao iniciar, o servidor ja devolve o segundo trajeto (ate o destino) e o tempo estimado.
        setRide(await api.startRide(rideId));
        setSeguindo(true);
      } else if (ride?.status === 'in_progress') {
        setRide(await api.completeRide(rideId));
      }
    } finally {
      setLoading(false);
    }
  };

  const confirmDirect = async () => {
    setConfirmingDirect(true);
    try {
      await api.confirmDirectPayment(rideId);
      Alert.alert('Confirmado', 'Pagamento registrado.');
      onDone();
    } catch (e: unknown) {
      Alert.alert('Erro', e instanceof Error ? e.message : 'Falha ao confirmar');
    } finally {
      setConfirmingDirect(false);
    }
  };

  if (!ride) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={theme.amber} />
      </View>
    );
  }

  const navTarget =
    ride.status === 'in_progress'
      ? { latitude: destLat, longitude: destLng, label: 'Destino' }
      : { latitude: lat, longitude: lng, label: 'Embarque' };

  const label =
    ride.status === 'accepted'
      ? 'Cheguei no embarque'
      : ride.status === 'driver_arrived'
        ? 'Iniciar corrida'
        : ride.status === 'in_progress'
          ? 'Finalizar corrida'
          : 'Aguardando';

  const openExternalNav = () => {
    Linking.openURL(
      `https://www.google.com/maps/dir/?api=1&destination=${navTarget.latitude},${navTarget.longitude}`,
    );
  };

  const mostrarEmbarque = ride.status === 'accepted' || ride.status === 'driver_arrived';
  const mostrarDestino = ride.status !== 'accepted';

  return (
    <View style={styles.container}>
      <View style={styles.mapWrap}>
        <AppMap
          style={styles.map}
          region={{
            latitude: myLocation?.latitude ?? lat,
            longitude: myLocation?.longitude ?? lng,
            latitudeDelta: 0.01,
            longitudeDelta: 0.01,
          }}
          fitToCoordinates={fitCoords}
          follow={follow}
          followActive={seguindo}
          onUserMoved={() => setSeguindo(false)}
          pinMode="marker"
        >
          {mostrarEmbarque ? <AppMarker coordinate={embarque} title="Embarque" variant="origin" /> : null}
          {mostrarDestino ? (
            <AppMarker
              coordinate={destino}
              title="Destino"
              variant="destination"
              etaMin={ride.status === 'in_progress' && Number.isFinite(etaMin) ? etaMin : null}
              label={ride.status === 'in_progress' ? 'Chegada' : undefined}
            />
          ) : null}
          {myLocation ? <AppMarker coordinate={myLocation} title="Você" variant="driver" heading={rumo ?? undefined} /> : null}
          <AppRoute coordinates={routeCoords} />
        </AppMap>

        <NavBanner mensagem={mensagemMotorista(ride)} />

        {navegando && !seguindo ? (
          <Pressable style={styles.recentralizar} onPress={() => setSeguindo(true)}>
            <Text style={styles.recentralizarTexto}>Recentralizar</Text>
          </Pressable>
        ) : null}
      </View>

      <View style={styles.sheet}>
        <View style={styles.linhaTopo}>
          <View style={{ flex: 1 }}>
            <Text style={styles.passageiro} numberOfLines={1}>
              {corridaPaga ? 'Corrida concluída' : (ride.passenger?.name ?? 'Passageiro')}
            </Text>
            <Text style={styles.status}>{rideStatusLabel(ride.status)}</Text>
          </View>
          <View style={styles.valorBox}>
            <Text style={styles.valorLabel}>Valor</Text>
            <Text style={styles.valor}>{formatBrl(valorCorrida)}</Text>
          </View>
        </View>

        {navegando && etaTexto ? (
          <Text style={styles.eta}>
            {ride.status === 'in_progress' ? 'Chegada ao destino em ' : 'Chegada ao embarque em '}
            <Text style={styles.etaForte}>{etaTexto}</Text>
          </Text>
        ) : null}

        {aguardandoPagamento && (
          <Text style={styles.meta}>
            {pagamentoDireto
              ? 'O passageiro vai pagar direto a você (dinheiro ou Pix pessoal).'
              : 'Aguardando o passageiro pagar pelo app (Pix ou cartão). A corrida finaliza sozinha quando o pagamento cair.'}
          </Text>
        )}

        {['accepted', 'driver_arrived', 'in_progress'].includes(ride.status) && (
          <Pressable style={styles.button} onPress={nextAction} disabled={loading}>
            {loading ? <ActivityIndicator color={theme.ink} /> : <Text style={styles.buttonText}>{label}</Text>}
          </Pressable>
        )}

        {['accepted', 'driver_arrived', 'in_progress'].includes(ride.status) ? (
          <Pressable style={styles.navLink} onPress={openExternalNav}>
            <Text style={styles.navLinkText}>Abrir no GPS externo ({navTarget.label})</Text>
          </Pressable>
        ) : null}

        {['accepted', 'driver_arrived'].includes(ride.status) && (
          <Pressable style={styles.cancel} onPress={cancelRide} disabled={loading}>
            <Text style={styles.cancelText}>Cancelar corrida</Text>
          </Pressable>
        )}
      </View>

      <Modal visible={canceled} transparent animationType="fade">
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Corrida cancelada</Text>
            <Text style={styles.meta}>
              {ride.status === 'canceled_by_passenger'
                ? 'O passageiro cancelou esta corrida.'
                : 'Esta corrida foi cancelada.'}
            </Text>
            <Pressable style={styles.button} onPress={onDone}>
              <Text style={styles.buttonText}>Voltar ao painel</Text>
            </Pressable>
          </View>
        </View>
      </Modal>

      <Modal visible={aguardandoPagamento || corridaPaga} transparent animationType="fade">
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            {corridaPaga ? (
              <>
                <Text style={styles.modalCheck}>✓</Text>
                <Text style={styles.modalTitle}>Pagamento recebido</Text>
                <Text style={styles.modalFare}>Valor: {formatBrl(valorCorrida)}</Text>
                <Text style={styles.meta}>
                  {pagamentoDireto
                    ? 'Recebimento direto confirmado.'
                    : 'Corrida paga pelo app. Já está registrada na sua carteira.'}
                </Text>
                <Pressable style={styles.button} onPress={onDone}>
                  <Text style={styles.buttonText}>Voltar ao painel</Text>
                </Pressable>
              </>
            ) : (
              <>
                <Text style={styles.modalTitle}>Aguardando pagamento</Text>
                <Text style={styles.modalFare}>Valor: {formatBrl(valorCorrida)}</Text>
                <Text style={styles.meta}>
                  {pagamentoDireto
                    ? 'O passageiro escolheu pagar direto: confirme quando receber.'
                    : 'O passageiro está pagando pelo app (Pix ou cartão). Esta tela fecha sozinha quando o pagamento cair.'}
                </Text>
                {pagamentoDireto ? (
                  <Pressable style={styles.button} onPress={confirmDirect} disabled={confirmingDirect}>
                    {confirmingDirect ? (
                      <ActivityIndicator color={theme.ink} />
                    ) : (
                      <Text style={styles.buttonText}>Confirmar recebimento (direto)</Text>
                    )}
                  </Pressable>
                ) : null}
                <Pressable style={styles.navLink} onPress={onDone}>
                  <Text style={styles.navLinkText}>Voltar ao painel</Text>
                </Pressable>
              </>
            )}
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  container: { flex: 1, backgroundColor: theme.bg },
  mapWrap: { flex: 1, position: 'relative' },
  map: { flex: 1 },
  recentralizar: {
    position: 'absolute',
    right: 12,
    bottom: 16,
    backgroundColor: '#fff',
    borderRadius: 22,
    paddingHorizontal: 16,
    paddingVertical: 11,
    elevation: 6,
  },
  recentralizarTexto: { fontWeight: '700', color: theme.ink, fontSize: 13 },
  sheet: {
    backgroundColor: theme.paper,
    borderTopLeftRadius: 22,
    borderTopRightRadius: 22,
    paddingHorizontal: 20,
    paddingTop: 16,
    paddingBottom: 14,
    elevation: 12,
  },
  linhaTopo: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  passageiro: { fontSize: 18, fontWeight: '700', color: theme.ink },
  status: { fontSize: 13, color: theme.textSecondary, marginTop: 2 },
  valorBox: { alignItems: 'flex-end' },
  valorLabel: { fontSize: 11, color: theme.textSecondary, fontWeight: '600', textTransform: 'uppercase' },
  valor: { fontSize: 22, fontWeight: '800', color: theme.ink },
  eta: { marginTop: 8, color: theme.textSecondary, fontSize: 13 },
  etaForte: { color: theme.ink, fontWeight: '700' },
  meta: { marginTop: 8, color: theme.textSecondary, fontSize: 14 },
  button: {
    marginTop: 14,
    backgroundColor: theme.amber,
    borderRadius: 13,
    padding: 16,
    alignItems: 'center',
  },
  buttonText: { fontWeight: '700', color: theme.ink },
  cancel: { marginTop: 2, alignItems: 'center', paddingVertical: 6 },
  cancelText: { color: theme.danger, fontWeight: '600', fontSize: 14 },
  navLink: { marginTop: 8, alignItems: 'center', paddingVertical: 4 },
  navLinkText: { color: theme.textSecondary, fontSize: 13, textDecorationLine: 'underline' },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.45)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  modalCard: {
    backgroundColor: theme.paper,
    borderRadius: 16,
    padding: 24,
    width: '100%',
    maxWidth: 360,
  },
  modalCheck: { fontSize: 40, color: theme.mint, fontWeight: '700', textAlign: 'center', marginBottom: 4 },
  modalTitle: { fontSize: 20, fontWeight: '700', color: theme.ink, marginBottom: 12 },
  modalFare: { fontSize: 18, color: theme.ink, fontWeight: '700', marginBottom: 4 },
});
