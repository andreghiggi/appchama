import React, { useEffect, useRef } from 'react';
import { Platform } from 'react-native';
import MapView, { Marker, Polyline, PROVIDER_GOOGLE, type Region } from 'react-native-maps';
import type { StyleProp, ViewStyle } from 'react-native';
import { toLatLng, toLatLngList } from './coords';
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

const markerColors: Record<MarkerVariant, string> = {
  passenger: '#111111',
  driver: '#2563EB',
  destination: MAP_ROUTE_COLOR,
  origin: '#111111',
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
      edgePadding: { top: 60, right: 60, bottom: 60, left: 60 },
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
      onPanDrag={() => {
        userMovedRef.current = true;
      }}
      {...props}
    >
      {children}
    </MapView>
  );
}

export function AppMarker({
  coordinate,
  title,
  variant = 'passenger',
}: {
  coordinate: AnyLatLng;
  title?: string;
  variant?: MarkerVariant;
}) {
  const point = toLatLng(coordinate);

  if (!point) return null;

  return (
    <Marker
      coordinate={point}
      title={title}
      pinColor={markerColors[variant]}
    />
  );
}

export function AppRoute({ coordinates }: { coordinates: AnyLatLng[] }) {
  const points = toLatLngList(coordinates);

  if (points.length < 2) return null;

  return (
    <>
      <Polyline coordinates={points} strokeColor={MAP_ROUTE_SHADOW} strokeWidth={8} />
      <Polyline coordinates={points} strokeColor={MAP_ROUTE_COLOR} strokeWidth={5} />
    </>
  );
}
