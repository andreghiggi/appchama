export type MarkerVariant = 'passenger' | 'driver' | 'destination' | 'origin';

export const MAPBOX_STYLE = 'mapbox://styles/mapbox/streets-v12';
export const MAP_ROUTE_COLOR = '#1A73E8';
export const MAP_ROUTE_SHADOW = '#1558B0';
export const MAP_ROUTE_WIDTH = 6;
export const MAP_LAND_COLOR = '#F2EFE9';
export const MAP_WATER_COLOR = '#AAD3DF';

/** Estilo Google Maps minimalista — usar com PROVIDER_GOOGLE (Android). */
export const GOOGLE_UBER_MAP_STYLE = [
  { elementType: 'geometry', stylers: [{ color: MAP_LAND_COLOR }] },
  { elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
  { elementType: 'labels.text.fill', stylers: [{ visibility: 'off' }] },
  { elementType: 'labels.text.stroke', stylers: [{ visibility: 'off' }] },
  { featureType: 'administrative', stylers: [{ visibility: 'off' }] },
  { featureType: 'administrative.land_parcel', stylers: [{ visibility: 'off' }] },
  { featureType: 'poi', stylers: [{ visibility: 'off' }] },
  { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#D4DCE3' }] },
  { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#FFFFFF' }] },
  { featureType: 'road.arterial', elementType: 'labels', stylers: [{ visibility: 'off' }] },
  { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#E8ECF0' }] },
  { featureType: 'road.highway', elementType: 'labels', stylers: [{ visibility: 'off' }] },
  { featureType: 'road.local', elementType: 'labels', stylers: [{ visibility: 'off' }] },
  { featureType: 'transit', stylers: [{ visibility: 'off' }] },
  { featureType: 'water', elementType: 'geometry', stylers: [{ color: MAP_WATER_COLOR }] },
];
