import React, { createContext, useContext, useEffect, useRef, useState } from 'react';
import { StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';
import mapboxgl from 'mapbox-gl';
import 'mapbox-gl/dist/mapbox-gl.css';
import { isValidCoord, toLatLng, toLatLngList, toNumber, type LatLng } from './coords';
import { useAnimatedLocation } from './useAnimatedLocation';
import type { MarkerVariant } from './mapStyle';
import { MAPBOX_STYLE, MAP_ROUTE_COLOR, MAP_ROUTE_SHADOW, MAP_ROUTE_WIDTH } from './mapStyle';
import { createMarkerElement, MAPBOX_CSS, applyChamaMapTheme, setMarkerBearing } from './mapStyle.web';
import { MAPBOX_TOKEN } from './theme';

type AnyLatLng = { latitude: number | string; longitude: number | string };

type Region = AnyLatLng & {
  latitudeDelta?: number;
  longitudeDelta?: number;
};

type Props = {
  style?: StyleProp<ViewStyle>;
  region: Region;
  onRegionChangeComplete?: (region: { latitude: number; longitude: number; latitudeDelta?: number; longitudeDelta?: number }) => void;
  children?: React.ReactNode;
  title?: string;
  pinMode?: 'center' | 'marker';
  fitToCoordinates?: AnyLatLng[];
  recenterToken?: number;
  onUserMoved?: () => void;
};

const MapContext = createContext<mapboxgl.Map | null>(null);

function injectMapCss() {
  if (typeof document === 'undefined') return;
  const id = 'chama-mapbox-css';
  if (document.getElementById(id)) return;
  const style = document.createElement('style');
  style.id = id;
  style.textContent = MAPBOX_CSS;
  document.head.appendChild(style);
}

function CenterPin() {
  return (
    <View pointerEvents="none" style={styles.centerPinWrap}>
      <View style={styles.centerPulse} />
      <View style={styles.centerPinHead} />
      <View style={styles.centerPinStem} />
      <View style={styles.centerPinShadow} />
    </View>
  );
}

export function AppMap({
  style,
  region,
  onRegionChangeComplete,
  children,
  pinMode = 'marker',
  fitToCoordinates,
  recenterToken = 0,
  onUserMoved,
}: Props) {
  const regionLat = toNumber(region.latitude);
  const regionLng = toNumber(region.longitude);
  const fitPoints = toLatLngList(fitToCoordinates);

  const [containerEl, setContainerEl] = useState<HTMLDivElement | null>(null);
  const mapRef = useRef<mapboxgl.Map | null>(null);
  const syncingRef = useRef(false);
  const userMovedRef = useRef(false);
  const onRegionChangeRef = useRef(onRegionChangeComplete);
  const regionRef = useRef(region);
  const pinModeRef = useRef(pinMode);
  const [map, setMap] = useState<mapboxgl.Map | null>(null);

  onRegionChangeRef.current = onRegionChangeComplete;
  regionRef.current = region;
  pinModeRef.current = pinMode;

  useEffect(() => {
    if (!containerEl || !MAPBOX_TOKEN) return;

    injectMapCss();
    mapboxgl.accessToken = MAPBOX_TOKEN;

    const start = toLatLng(regionRef.current) ?? { latitude: 0, longitude: 0 };

    const instance = new mapboxgl.Map({
      container: containerEl,
      style: MAPBOX_STYLE,
      center: [start.longitude, start.latitude],
      zoom: 14,
      attributionControl: true,
    });

    instance.once('load', () => {
      applyChamaMapTheme(instance);
      requestAnimationFrame(() => instance.resize());
    });

    // Assim que o usuário mexe no mapa paramos de reenquadrar sozinhos, senão
    // o zoom dele seria desfeito a cada atualização de posição do motorista.
    const markUserMoved = (e: { originalEvent?: unknown }) => {
      if (e.originalEvent) {
        userMovedRef.current = true;
        onUserMoved?.();
      }
    };
    instance.on('dragstart', markUserMoved);
    instance.on('zoomstart', markUserMoved);
    instance.on('rotatestart', markUserMoved);

    instance.on('moveend', () => {
      if (syncingRef.current || pinModeRef.current !== 'center') return;
      const cb = onRegionChangeRef.current;
      if (!cb) return;
      const center = instance.getCenter();
      cb({
        latitude: center.lat,
        longitude: center.lng,
        latitudeDelta: regionRef.current.latitudeDelta,
        longitudeDelta: regionRef.current.longitudeDelta,
      });
    });

    mapRef.current = instance;
    setMap(instance);

    return () => {
      instance.remove();
      mapRef.current = null;
      setMap(null);
    };
  }, [containerEl]);

  useEffect(() => {
    if (!containerEl || !mapRef.current) return;

    const observer = new ResizeObserver(() => {
      mapRef.current?.resize();
    });
    observer.observe(containerEl);

    return () => observer.disconnect();
  }, [containerEl, map]);

  const fitSignature = fitPoints
    .map((p) => `${p.latitude.toFixed(4)},${p.longitude.toFixed(4)}`)
    .join('|');
  const fitSignatureRef = useRef(fitSignature);

  useEffect(() => {
    fitSignatureRef.current = fitSignature;
  }, [fitSignature]);

  useEffect(() => {
    const instance = mapRef.current;
    if (!instance) return;

    const apply = () => {
      if (fitPoints.length >= 2) {
        if (userMovedRef.current) return;

        syncingRef.current = true;
        try {
          const bounds = new mapboxgl.LngLatBounds();
          fitPoints.forEach((p) => bounds.extend([p.longitude, p.latitude]));
          instance.fitBounds(bounds, {
            padding: 40,
            animate: true,
            maxZoom: 17,
            minZoom: 13,
          });
        } catch {
          /* bounds inválidos */
        }
        syncingRef.current = false;
        return;
      }

      if (!isValidCoord(regionLat) || !isValidCoord(regionLng)) return;

      syncingRef.current = true;
      instance.jumpTo({
        center: [regionLng, regionLat],
        zoom: instance.getZoom(),
      });
      syncingRef.current = false;
    };

    if (instance.isStyleLoaded()) {
      apply();
    } else {
      instance.once('load', apply);
    }
  }, [map, regionLat, regionLng, region.latitudeDelta, fitSignature, recenterToken]);

  useEffect(() => {
    if (recenterToken > 0) {
      userMovedRef.current = false;
    }
  }, [recenterToken]);

  if (!MAPBOX_TOKEN) {
    return (
      <View style={[styles.container, style, styles.noToken]}>
        <Text style={styles.noTokenText}>Configure EXPO_PUBLIC_MAPBOX_TOKEN para exibir o mapa.</Text>
      </View>
    );
  }

  return (
    <MapContext.Provider value={map}>
      <View style={[styles.container, style]}>
        <div
          ref={setContainerEl}
          style={{
            width: '100%',
            maxWidth: '100%',
            height: '100%',
            minHeight: 180,
            flex: 1,
            overflow: 'hidden',
          }}
        />
        {pinMode === 'center' ? <CenterPin /> : null}
      </View>
      {children}
    </MapContext.Provider>
  );
}

export function AppMarker({
  coordinate,
  title,
  variant = 'passenger',
  bearing,
  skipAnimation = false,
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
  const map = useContext(MapContext);
  const markerRef = useRef<mapboxgl.Marker | null>(null);
  const elementRef = useRef<HTMLDivElement | null>(null);

  const point = toLatLng(coordinate);
  const animatedDriver = useAnimatedLocation(
    variant === 'driver' && !skipAnimation ? point : null,
  );
  const displayPoint = variant === 'driver' ? (animatedDriver ?? point) : point;

  const valid = point !== null;

  useEffect(() => {
    if (!map || !displayPoint) return;

    const el = createMarkerElement(variant, bearing, etaMinutes, etaLabel);
    if (title) el.title = title;
    elementRef.current = el;

    const anchor = variant === 'driver' ? 'center' : 'bottom';
    const marker = new mapboxgl.Marker({ element: el, anchor })
      .setLngLat([displayPoint.longitude, displayPoint.latitude])
      .addTo(map);

    markerRef.current = marker;

    return () => {
      marker.remove();
      markerRef.current = null;
      elementRef.current = null;
    };
  }, [map, title, variant, valid, etaMinutes, etaLabel]);

  useEffect(() => {
    if (!displayPoint) return;
    markerRef.current?.setLngLat([displayPoint.longitude, displayPoint.latitude]);
  }, [displayPoint?.latitude, displayPoint?.longitude]);

  useEffect(() => {
    if (variant !== 'driver' || bearing == null || !elementRef.current) return;
    setMarkerBearing(elementRef.current, bearing);
  }, [bearing, variant]);

  return null;
}

export function AppRoute({ coordinates }: { coordinates: AnyLatLng[] }) {
  const map = useContext(MapContext);

  const points = toLatLngList(coordinates);
  const pointsRef = useRef<LatLng[]>(points);
  pointsRef.current = points;

  const signature = points
    .map((p) => `${p.latitude.toFixed(5)},${p.longitude.toFixed(5)}`)
    .join('|');

  // Atualiza os dados da fonte existente; remover e readicionar as camadas a
  // cada atualização fazia a rota piscar no acompanhamento ao vivo.
  useEffect(() => {
    if (!map) return;

    const lineCoords = pointsRef.current.map(
      (p) => [p.longitude, p.latitude] as [number, number],
    );

    if (lineCoords.length < 2) return;

    const geojson: GeoJSON.Feature<GeoJSON.LineString> = {
      type: 'Feature',
      properties: {},
      geometry: { type: 'LineString', coordinates: lineCoords },
    };

    const apply = () => {
      const source = map.getSource('chama-route') as mapboxgl.GeoJSONSource | undefined;

      if (source) {
        source.setData(geojson);
        return;
      }

      map.addSource('chama-route', { type: 'geojson', data: geojson });
      map.addLayer({
        id: 'chama-route-shadow',
        type: 'line',
        source: 'chama-route',
        layout: { 'line-cap': 'round', 'line-join': 'round' },
        paint: {
          'line-color': MAP_ROUTE_SHADOW,
          'line-width': MAP_ROUTE_WIDTH + 4,
          'line-opacity': 0.35,
        },
      });
      map.addLayer({
        id: 'chama-route-line',
        type: 'line',
        source: 'chama-route',
        layout: { 'line-cap': 'round', 'line-join': 'round' },
        paint: { 'line-color': MAP_ROUTE_COLOR, 'line-width': MAP_ROUTE_WIDTH },
      });
    };

    const run = () => {
      if (!map.isStyleLoaded()) {
        map.once('load', apply);
        return;
      }
      apply();
    };

    run();
    map.on('idle', apply);

    return () => {
      map.off('idle', apply);
    };
  }, [map, signature]);

  useEffect(() => {
    if (!map) return;

    return () => {
      if (!map.getStyle()) return;
      if (map.getLayer('chama-route-line')) map.removeLayer('chama-route-line');
      if (map.getLayer('chama-route-shadow')) map.removeLayer('chama-route-shadow');
      if (map.getSource('chama-route')) map.removeSource('chama-route');
    };
  }, [map]);

  return null;
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    minHeight: 240,
    width: '100%',
    overflow: 'hidden',
    position: 'relative',
  },
  noToken: {
    backgroundColor: '#EEF1F4',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 16,
  },
  noTokenText: {
    color: '#5B6472',
    fontSize: 13,
    textAlign: 'center',
  },
  centerPinWrap: {
    position: 'absolute',
    top: '50%',
    left: '50%',
    width: 56,
    height: 56,
    marginLeft: -28,
    marginTop: -52,
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 500,
  },
  centerPulse: {
    position: 'absolute',
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: 'rgba(17,17,17,0.14)',
  },
  centerPinHead: {
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: '#111111',
    borderWidth: 3,
    borderColor: '#FFFFFF',
    zIndex: 2,
  },
  centerPinStem: {
    width: 3,
    height: 10,
    backgroundColor: '#111111',
    marginTop: -2,
    borderRadius: 2,
    zIndex: 2,
  },
  centerPinShadow: {
    width: 10,
    height: 4,
    borderRadius: 5,
    backgroundColor: 'rgba(0,0,0,0.25)',
    marginTop: 2,
    zIndex: 2,
  },
});
