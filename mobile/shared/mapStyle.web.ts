import type mapboxgl from 'mapbox-gl';
import { destinationMarkerHtml, driverMarkerHtml, originMarkerHtml } from './mapMarkerHtml';
import type { MarkerVariant } from './mapStyle';
import { MAP_LAND_COLOR, MAP_WATER_COLOR } from './mapStyle';

function markerHtml(
  variant: MarkerVariant,
  bearing?: number,
  etaMinutes?: number,
  etaLabel?: string,
): string {
  switch (variant) {
    case 'driver':
      return driverMarkerHtml();
    case 'destination':
      return destinationMarkerHtml(etaMinutes, etaLabel);
    case 'origin':
    default:
      return originMarkerHtml();
  }
}

export function createMarkerElement(
  variant: MarkerVariant = 'passenger',
  bearing?: number,
  etaMinutes?: number,
  etaLabel?: string,
): HTMLDivElement {
  const el = document.createElement('div');
  el.className = 'chama-map-marker';
  el.innerHTML = markerHtml(variant === 'passenger' ? 'origin' : variant, bearing, etaMinutes, etaLabel);
  return el;
}

export function setMarkerBearing(element: HTMLElement, bearing: number): void {
  const rotateEl = element.querySelector('[data-car-rotate]') as HTMLElement | null;
  if (rotateEl) {
    rotateEl.style.transform = `rotate(${bearing}deg)`;
  }
}

/** Visual próximo ao Google Maps: ruas legíveis, menos poluição de POI. */
export function applyChamaMapTheme(map: mapboxgl.Map): void {
  const style = map.getStyle();
  if (!style?.layers) return;

  for (const layer of style.layers) {
    if (layer.type !== 'symbol') continue;

    const id = layer.id.toLowerCase();
    const hide =
      id.includes('poi') ||
      id.includes('transit') ||
      id.includes('airport');

    if (hide) {
      try {
        map.setLayoutProperty(layer.id, 'visibility', 'none');
      } catch {
        /* camada pode não existir neste estilo */
      }
    }
  }

  const paintOverrides: Array<[string, string, unknown]> = [
    ['background', 'background-color', MAP_LAND_COLOR],
    ['water', 'fill-color', MAP_WATER_COLOR],
    ['waterway', 'line-color', MAP_WATER_COLOR],
  ];

  for (const [id, prop, value] of paintOverrides) {
    if (map.getLayer(id)) {
      try {
        map.setPaintProperty(id, prop, value);
      } catch {
        /* ignore */
      }
    }
  }
}

export const MAPBOX_CSS = `
@keyframes chama-pulse {
  0% { transform: scale(0.75); opacity: 0.55; }
  70% { transform: scale(1.55); opacity: 0; }
  100% { transform: scale(1.55); opacity: 0; }
}
.chama-map-marker {
  cursor: default;
  z-index: 10;
}
.chama-car-marker {
  pointer-events: none;
  filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.35));
}
.chama-car-marker-shell {
  background: #fff;
  border-radius: 50%;
  padding: 4px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.28);
  display: flex;
  align-items: center;
  justify-content: center;
}
.mapboxgl-ctrl-bottom-right,
.mapboxgl-ctrl-bottom-left {
  opacity: 0.45;
}
.mapboxgl-ctrl-attrib {
  font-size: 9px !important;
}
`;

/** @deprecated Leaflet — mantido para referência durante transição */
export const MAP_TILE_URL =
  'https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png';

export const MAP_ATTRIBUTION =
  '&copy; OpenStreetMap &copy; Mapbox';

export const LEAFLET_UBER_CSS = MAPBOX_CSS;
