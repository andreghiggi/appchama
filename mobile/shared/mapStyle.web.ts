import type mapboxgl from 'mapbox-gl';
import type { MarkerVariant } from './mapStyle';
import { MAP_LAND_COLOR, MAP_ROUTE_COLOR, MAP_WATER_COLOR } from './mapStyle';

function markerHtml(variant: MarkerVariant): string {
  switch (variant) {
    case 'driver':
      return `
        <div style="position:relative;width:36px;height:36px;display:flex;align-items:center;justify-content:center">
          <div style="width:28px;height:16px;background:#2563EB;border-radius:4px 4px 3px 3px;box-shadow:0 3px 10px rgba(0,0,0,.28);position:relative">
            <div style="position:absolute;top:-5px;left:4px;width:10px;height:8px;background:#2563EB;border-radius:3px 3px 0 0"></div>
            <div style="position:absolute;top:3px;left:3px;width:5px;height:4px;background:rgba(255,255,255,.85);border-radius:1px"></div>
            <div style="position:absolute;top:3px;right:3px;width:5px;height:4px;background:rgba(255,255,255,.85);border-radius:1px"></div>
          </div>
        </div>`;
    case 'destination':
      return `
        <div style="display:flex;flex-direction:column;align-items:center;width:28px;height:38px">
          <div style="width:12px;height:12px;background:#E24B4A;border:3px solid #fff;border-radius:2px;box-shadow:0 2px 8px rgba(0,0,0,.35)"></div>
          <div style="width:2px;height:14px;background:#E24B4A;margin-top:-1px"></div>
          <div style="width:8px;height:3px;background:rgba(0,0,0,.25);border-radius:50%;margin-top:2px"></div>
        </div>`;
    case 'origin':
    default:
      return `
        <div style="display:flex;flex-direction:column;align-items:center;width:28px;height:38px">
          <div style="width:14px;height:14px;background:#111;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.35)"></div>
          <div style="width:2px;height:12px;background:#111;margin-top:-1px"></div>
          <div style="width:8px;height:3px;background:rgba(0,0,0,.25);border-radius:50%;margin-top:2px"></div>
        </div>`;
  }
}

export function createMarkerElement(variant: MarkerVariant = 'passenger'): HTMLDivElement {
  const el = document.createElement('div');
  el.className = 'chama-map-marker';
  el.innerHTML = markerHtml(variant === 'passenger' ? 'origin' : variant);
  return el;
}

/** Aplica paleta minimalista (cinza/azul) e esconde rótulos do Mapbox. */
export function applyChamaMapTheme(map: mapboxgl.Map): void {
  const style = map.getStyle();
  if (!style?.layers) return;

  for (const layer of style.layers) {
    if (layer.type === 'symbol') {
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

export { MAP_ROUTE_COLOR };
