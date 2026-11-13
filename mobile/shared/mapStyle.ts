export type MarkerVariant = 'passenger' | 'driver' | 'destination' | 'origin';

export const MAP_ROUTE_COLOR = '#E24B4A';
export const MAP_ROUTE_SHADOW = '#FFFFFF';
export const MAP_LAND_COLOR = '#DDE4EA';
export const MAP_WATER_COLOR = '#B8CDDB';

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
