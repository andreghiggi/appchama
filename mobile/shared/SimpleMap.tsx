import React, { useEffect, useRef } from 'react';
import { Platform } from 'react-native';
import MapView, { Marker, Polyline, PROVIDER_GOOGLE, type Region } from 'react-native-maps';
import type { StyleProp, ViewStyle } from 'react-native';
import { DestinationMapPin, DriverMapPin, OriginMapPin } from './MapPinMarkers';
import { toLatLng, toLatLngList } from './coords';
import { GOOGLE_UBER_MAP_STYLE, MAP_ROUTE_COLOR, MAP_ROUTE_WIDTH, type MarkerVariant } from './mapStyle';

type AnyLatLng = { latitude: number | string; longitude: number | string };

type Props = {
  style?: StyleProp<ViewStyle>;
  region: Region;
  onRegionChangeComplete?: (region: Region) => void;
  children?: React.ReactNode;
  title?: string;
  pinMode?: 'center' | 'marker';
  fitToCoordinates?: AnyLatLng[];
  recenterToken?: number;
  onUserMoved?: () => void;
};

export function AppMap({
  pinMode = 'marker',
  fitToCoordinates,
  recenterToken = 0,
  onUserMoved,
  children,
  ...props
}: Props) {
  const useGoogleStyle = Platform.OS === 'android';
  const mapRef = useRef<MapView | null>(null);
  const userMovedRef = useRef(false);

  const fitPoints = toLatLngList(fitToCoordinates);
  const fitSignature = fitPoints
    .map((p) => `${p.latitude.toFixed(4)},${p.longitude.toFixed(4)}`)
    .join('|');

  useEffect(() => {
    if (recenterToken > 0) {
      userMovedRef.current = false;
    }
  }, [recenterToken]);

  useEffect(() => {
    if (fitPoints.length < 2 || userMovedRef.current) return;

    mapRef.current?.fitToCoordinates(fitPoints, {
      edgePadding: { top: 60, right: 60, bottom: 60, left: 60 },
      animated: true,
    });
  }, [fitSignature, recenterToken]);

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
        onUserMoved?.();
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
  bearing,
  etaMinutes,
  etaLabel,
}: {
  coordinate: AnyLatLng;
  title?: string;
  variant?: MarkerVariant;
  bearing?: number;
  skipAnimation?: boolean;
  etaMinutes?: number;
  etaLabel?: string;
}) {
  const point = toLatLng(coordinate);

  if (!point) return null;

  if (variant === 'driver') {
    return (
      <Marker coordinate={point} title={title} anchor={{ x: 0.5, y: 0.5 }}>
        <DriverMapPin />
      </Marker>
    );
  }

  if (variant === 'destination') {
    return (
      <Marker coordinate={point} title={title} anchor={{ x: 0.5, etaMinutes != null ? 1 : 0.5 }}>
        <DestinationMapPin etaMinutes={etaMinutes} label={etaLabel} />
      </Marker>
    );
  }

  return (
    <Marker coordinate={point} title={title} anchor={{ x: 0.5, y: 0.5 }}>
      <OriginMapPin />
    </Marker>
  );
}

export function AppRoute({ coordinates }: { coordinates: AnyLatLng[] }) {
  const points = toLatLngList(coordinates);

  if (points.length < 2) return null;

  return <Polyline coordinates={points} strokeColor={MAP_ROUTE_COLOR} strokeWidth={MAP_ROUTE_WIDTH} />;
}
