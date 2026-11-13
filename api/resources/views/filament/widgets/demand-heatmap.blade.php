<x-filament-widgets::widget
    :attributes="
        (new \Filament\Support\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $this->getPollingInterval() => $this->getPollingInterval() ? true : null,
            ], escape: false)
    "
>
    <x-filament::section heading="Demanda (últimos 30 dias)">
        @if (empty($mapboxToken))
            <div class="rounded-xl bg-gray-50 p-6 text-center text-sm text-gray-500">
                Configure <code class="rounded bg-gray-200 px-1">MAPBOX_ACCESS_TOKEN</code> no .env para exibir o mapa.
            </div>
        @else
            <div
                id="admin-demand-heatmap-{{ $widgetId }}"
                wire:ignore
                style="height: 360px; border-radius: 12px; overflow: hidden; background: #eef1f4;"
            ></div>

            @if ($pointCount === 0)
                <p class="mt-2 text-sm" style="color: var(--fi-body-text-color, inherit); opacity: .75;">
                    Nenhum pedido de corrida nos últimos 30 dias.
                </p>
            @endif

            <div
                id="admin-demand-heatmap-data-{{ $widgetId }}"
                data-map-id="admin-demand-heatmap-{{ $widgetId }}"
                data-points='@json($points)'
                data-center-lng="{{ $centerLng }}"
                data-center-lat="{{ $centerLat }}"
                hidden
            ></div>
            <p class="mt-2 text-xs text-gray-500">
                {{ $pointCount }} pedido(s) de corrida mapeados
                @if ($cityName)
                    · {{ $cityName }}
                @endif
            </p>

            <script>
                (function () {
                    const dataElId = @json('admin-demand-heatmap-data-' . $widgetId);
                    const mapElId = @json('admin-demand-heatmap-' . $widgetId);
                    const token = @json($mapboxToken);

                    function readPayload() {
                        const dataEl = document.getElementById(dataElId);
                        if (!dataEl) return null;

                        return {
                            points: JSON.parse(dataEl.dataset.points || '[]'),
                            center: [
                                parseFloat(dataEl.dataset.centerLng),
                                parseFloat(dataEl.dataset.centerLat),
                            ],
                        };
                    }

                    function toGeoJson(points) {
                        return {
                            type: 'FeatureCollection',
                            features: points.map((p) => ({
                                type: 'Feature',
                                properties: {},
                                geometry: { type: 'Point', coordinates: p },
                            })),
                        };
                    }

                    function initMap() {
                        const payload = readPayload();
                        const mapEl = document.getElementById(mapElId);
                        if (!payload || !mapEl || mapEl.__chamaHeatmapReady) return;

                        mapboxgl.accessToken = token;
                        const map = new mapboxgl.Map({
                            container: mapElId,
                            style: 'mapbox://styles/mapbox/light-v11',
                            center: payload.center,
                            zoom: 10,
                        });
                        mapEl.__chamaHeatmapReady = true;

                        map.on('load', () => {
                            map.addSource('chama-demand-points', {
                                type: 'geojson',
                                data: toGeoJson(payload.points),
                            });
                            map.addLayer({
                                id: 'chama-demand-heat',
                                type: 'heatmap',
                                source: 'chama-demand-points',
                                paint: {
                                    'heatmap-weight': 1,
                                    'heatmap-intensity': 1,
                                    'heatmap-radius': 22,
                                    'heatmap-opacity': 0.75,
                                    'heatmap-color': [
                                        'interpolate', ['linear'], ['heatmap-density'],
                                        0, 'rgba(0,0,0,0)',
                                        0.2, '#fff4c2',
                                        0.4, '#ffd166',
                                        0.6, '#ff9f1c',
                                        0.8, '#e24b4a',
                                        1, '#9d174d',
                                    ],
                                },
                            });
                        });
                    }

                    function ensureMapboxLoaded(callback) {
                        if (window.mapboxgl) {
                            callback();
                            return;
                        }

                        let script = document.querySelector('script[data-chama-mapbox-gl]');
                        if (!script) {
                            script = document.createElement('script');
                            script.src = 'https://api.mapbox.com/mapbox-gl-js/v3.9.0/mapbox-gl.js';
                            script.setAttribute('data-chama-mapbox-gl', '1');
                            document.head.appendChild(script);

                            if (!document.querySelector('link[data-chama-mapbox-css]')) {
                                const link = document.createElement('link');
                                link.rel = 'stylesheet';
                                link.href = 'https://api.mapbox.com/mapbox-gl-js/v3.9.0/mapbox-gl.css';
                                link.setAttribute('data-chama-mapbox-css', '1');
                                document.head.appendChild(link);
                            }
                        }
                        script.addEventListener('load', callback, { once: true });
                        if (window.mapboxgl) callback();
                    }

                    function boot() {
                        ensureMapboxLoaded(initMap);
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', boot);
                    } else {
                        setTimeout(boot, 50);
                    }

                    document.addEventListener('livewire:navigated', boot);
                })();
            </script>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
