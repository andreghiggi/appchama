import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Platform, StyleSheet, Text, View } from 'react-native';
import MapView, { AnimatedRegion, Marker, Polyline, PROVIDER_GOOGLE, type Region } from 'react-native-maps';
import type { StyleProp, ViewStyle } from 'react-native';
import { toLatLng, toLatLngList } from './coords';
import { bearingBetween } from './geo';
import { GOOGLE_UBER_MAP_STYLE, MAP_ROUTE_COLOR, MAP_ROUTE_SHADOW, type MarkerVariant } from './mapStyle';

type AnyLatLng = { latitude: number | string; longitude: number | string };

type Props = {
  style?: StyleProp<ViewStyle>;
  region: Region;
  onRegionChangeComplete?: (region: Region) => void;
  children?: React.ReactNode;
  title?: string;
  pinMode?: 'center' | 'marker';
  fitToCoordinates?: AnyLatLng[];
};

export function AppMap({ pinMode = 'marker', fitToCoordinates, children, ...props }: Props) {
  const useGoogleStyle = Platform.OS === 'android';
  const mapRef = useRef<MapView | null>(null);
  const userMovedRef = useRef(false);

  const fitPoints = toLatLngList(fitToCoordinates);
  const fitSignature = fitPoints
    .map((p) => `${p.latitude.toFixed(4)},${p.longitude.toFixed(4)}`)
    .join('|');

  // Reenquadra apenas enquanto o usuário não tiver mexido no mapa, para não
  // desfazer o zoom dele a cada atualização de posição.
  useEffect(() => {
    if (fitPoints.length < 2 || userMovedRef.current) return;

    mapRef.current?.fitToCoordinates(fitPoints, {
      edgePadding: { top: 80, right: 60, bottom: 60, left: 60 },
      animated: true,
    });
  }, [fitSignature]);

  return (
    <MapView
      ref={mapRef}
      provider={useGoogleStyle ? PROVIDER_GOOGLE : undefined}
      customMapStyle={useGoogleStyle ? GOOGLE_UBER_MAP_STYLE : undefined}
      mapType="standard"
      showsUserLocation={pinMode === 'center'}
      showsMyLocationButton={false}
      showsCompass={false}
      toolbarEnabled={false}
      onPanDrag={() => {
        userMovedRef.current = true;
      }}
      {...props}
    >
      {children}
    </MapView>
  );
}

/**
 * No Android o marcador com visual próprio é desenhado como imagem: acompanha
 * mudanças por um instante e depois congela (poupa bateria e evita piscar).
 */
function useCongelarMarcador(...deps: unknown[]): boolean {
  const [acompanhar, setAcompanhar] = useState(true);
  useEffect(() => {
    setAcompanhar(true);
    const t = setTimeout(() => setAcompanhar(false), 600);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);
  return acompanhar;
}

function OrigemPin() {
  return (
    <View style={pin.anel}>
      <View style={pin.pontoInterno} />
    </View>
  );
}

function DestinoPin({ etaMin, label }: { etaMin?: number | null; label?: string }) {
  const temEta = etaMin != null && Number.isFinite(etaMin);
  return (
    <View style={pin.colunaDestino}>
      {temEta || label ? (
        <View style={pin.balao}>
          {label ? <Text style={pin.balaoLabel}>{label}</Text> : null}
          {temEta ? <Text style={pin.balaoEta}>{Math.max(1, Math.round(etaMin as number))} min</Text> : null}
        </View>
      ) : null}
      <View style={pin.quadrado}>
        <View style={pin.quadradoInterno} />
      </View>
    </View>
  );
}

function PassageiroPin() {
  return (
    <View style={pin.halo}>
      <View style={pin.pontoAzul} />
    </View>
  );
}

/** Carrinho visto de cima (frente para cima; o mapa gira pelo `rotation`). */
function CarroPin() {
  return (
    <View style={pin.carro}>
      <View style={pin.carroParabrisa} />
      <View style={pin.carroTeto} />
      <View style={pin.carroVidroTraseiro} />
    </View>
  );
}

type MarkerProps = {
  coordinate: AnyLatLng;
  title?: string;
  variant?: MarkerVariant;
  /** Tempo estimado mostrado em balão sobre o destino. */
  etaMin?: number | null;
  /** Texto do balão (ex.: "Chegada"). */
  label?: string;
  /** Direção do carro em graus (0 = norte). Sem valor, é calculada pelo deslocamento. */
  heading?: number;
};

/** Carro com movimento suave entre as posições recebidas (como no Uber). */
function CarroAnimado({
  coordinate,
  title,
  heading,
}: {
  coordinate: { latitude: number; longitude: number };
  title?: string;
  heading?: number;
}) {
  const regionRef = useRef<AnimatedRegion | null>(null);
  const ultimaRef = useRef(coordinate);
  const [rotacao, setRotacao] = useState(heading ?? 0);
  const acompanhar = useCongelarMarcador();

  if (!regionRef.current) {
    regionRef.current = new AnimatedRegion({
      latitude: coordinate.latitude,
      longitude: coordinate.longitude,
      latitudeDelta: 0,
      longitudeDelta: 0,
    });
  }

  useEffect(() => {
    const anterior = ultimaRef.current;
    if (anterior.latitude === coordinate.latitude && anterior.longitude === coordinate.longitude) return;
    const novoRumo = heading ?? bearingBetween(anterior, coordinate);
    if (novoRumo != null) setRotacao(novoRumo);
    ultimaRef.current = coordinate;
    regionRef.current
      ?.timing({
        latitude: coordinate.latitude,
        longitude: coordinate.longitude,
        latitudeDelta: 0,
        longitudeDelta: 0,
        duration: 2500,
        useNativeDriver: false,
      } as never)
      .start();
  }, [coordinate.latitude, coordinate.longitude, heading]);

  return (
    <Marker.Animated
      coordinate={regionRef.current as never}
      title={title}
      anchor={{ x: 0.5, y: 0.5 }}
      flat
      rotation={rotacao}
      tracksViewChanges={acompanhar}
      zIndex={5}
    >
      <CarroPin />
    </Marker.Animated>
  );
}

export function AppMarker({ coordinate, title, variant = 'passenger', etaMin, label, heading }: MarkerProps) {
  const point = toLatLng(coordinate);
  const acompanhar = useCongelarMarcador(variant, etaMin, label);

  if (!point) return null;

  if (variant === 'driver' || variant === 'car') {
    return <CarroAnimado coordinate={point} title={title} heading={heading} />;
  }

  const comBalao = variant === 'destination' && (etaMin != null || Boolean(label));

  return (
    <Marker
      coordinate={point}
      title={title}
      anchor={comBalao ? { x: 0.5, y: 1 } : { x: 0.5, y: 0.5 }}
      tracksViewChanges={acompanhar}
      zIndex={variant === 'passenger' ? 1 : 3}
    >
      {variant === 'origin' ? (
        <OrigemPin />
      ) : variant === 'destination' ? (
        <DestinoPin etaMin={etaMin} label={label} />
      ) : (
        <PassageiroPin />
      )}
    </Marker>
  );
}

export type NearbyCar = { id: string; latitude: number; longitude: number };

/** Carrinhos dos motoristas livres perto do passageiro (cada um anima sozinho entre as posições). */
export function AppNearbyCars({ cars }: { cars: NearbyCar[] }) {
  const lista = useMemo(() => cars.filter((c: NearbyCar) => toLatLng(c)), [cars]);
  return (
    <>
      {lista.map((c) => (
        <CarroAnimado key={c.id} coordinate={{ latitude: c.latitude, longitude: c.longitude }} />
      ))}
    </>
  );
}

export function AppRoute({ coordinates }: { coordinates: AnyLatLng[] }) {
  const points = toLatLngList(coordinates);

  if (points.length < 2) return null;

  return (
    <>
      <Polyline coordinates={points} strokeColor={MAP_ROUTE_SHADOW} strokeWidth={9} />
      <Polyline coordinates={points} strokeColor={MAP_ROUTE_COLOR} strokeWidth={5} />
    </>
  );
}

const pin = StyleSheet.create({
  anel: {
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: '#111',
    borderWidth: 3,
    borderColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
    elevation: 4,
  },
  pontoInterno: { width: 6, height: 6, borderRadius: 3, backgroundColor: '#fff' },
  colunaDestino: { alignItems: 'center' },
  quadrado: {
    width: 22,
    height: 22,
    backgroundColor: '#111',
    borderWidth: 3,
    borderColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
    elevation: 4,
  },
  quadradoInterno: { width: 6, height: 6, backgroundColor: '#fff' },
  balao: {
    backgroundColor: '#fff',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 6,
    marginBottom: 6,
    minWidth: 64,
    alignItems: 'center',
    elevation: 6,
  },
  balaoLabel: { fontSize: 11, fontWeight: '600', color: '#6B7280' },
  balaoEta: { fontSize: 16, fontWeight: '800', color: '#111' },
  halo: {
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: 'rgba(26,115,232,0.18)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  pontoAzul: {
    width: 16,
    height: 16,
    borderRadius: 8,
    backgroundColor: '#1A73E8',
    borderWidth: 3,
    borderColor: '#fff',
  },
  carro: {
    width: 22,
    height: 40,
    borderRadius: 8,
    backgroundColor: '#111',
    borderWidth: 2,
    borderColor: '#fff',
    alignItems: 'center',
    elevation: 5,
  },
  carroParabrisa: { width: 14, height: 8, borderRadius: 3, backgroundColor: '#9CA3AF', marginTop: 6 },
  carroTeto: { width: 14, height: 10, borderRadius: 3, backgroundColor: '#374151', marginTop: 3 },
  carroVidroTraseiro: { width: 14, height: 5, borderRadius: 2, backgroundColor: '#9CA3AF', marginTop: 3 },
});
