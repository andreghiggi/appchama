export type MarkerVariant = 'passenger' | 'driver' | 'car' | 'destination' | 'origin';

export const MAP_ROUTE_COLOR = '#111111';
export const MAP_ROUTE_SHADOW = '#FFFFFF';
export const MAP_LAND_COLOR = '#EAEFF3';
export const MAP_WATER_COLOR = '#B8CDDB';

/** Estilo Google Maps minimalista — usar com PROVIDER_GOOGLE (Android). */
export const GOOGLE_UBER_MAP_STYLE = [
  { elementType: 'geometry', stylers: [{ color: MAP_LAND_COLOR }] },
  { elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
  { elementType: 'labels.text.fill', stylers: [{ color: '#5F6B7A' }] },
  { elementType: 'labels.text.stroke', stylers: [{ color: '#F4F6F8' }, { weight: 3 }] },
  { featureType: 'administrative.locality', elementType: 'labels.text.fill', stylers: [{ color: '#3B4654' }] },
  { featureType: 'administrative.neighborhood', elementType: 'labels.text.fill', stylers: [{ color: '#8A94A3' }] },
  { featureType: 'administrative.land_parcel', stylers: [{ visibility: 'off' }] },
  { featureType: 'poi', stylers: [{ visibility: 'off' }] },
  { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#D4DCE3' }] },
  { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#FFFFFF' }] },
  { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#E8ECF0' }] },
  { featureType: 'road.local', elementType: 'labels.text.fill', stylers: [{ color: '#7A8594' }] },
  { featureType: 'transit', stylers: [{ visibility: 'off' }] },
  { featureType: 'water', elementType: 'geometry', stylers: [{ color: MAP_WATER_COLOR }] },
];
