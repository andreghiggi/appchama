<x-filament-widgets::widget
    :attributes="
        (new \Filament\Support\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $this->getPollingInterval() => $this->getPollingInterval() ? true : null,
            ], escape: false)
    "
>
    <x-filament::section heading="Mapa ao vivo">
        @if (empty($mapboxToken))
            <div class="rounded-xl bg-gray-50 p-6 text-center text-sm text-gray-500">
                Configure <code class="rounded bg-gray-200 px-1">MAPBOX_ACCESS_TOKEN</code> no .env para exibir o mapa.
            </div>
        @else
            <link href="https://api.mapbox.com/mapbox-gl-js/v3.9.0/mapbox-gl.css" rel="stylesheet" />
            <style>
                .chama-admin-driver-dot {
                    width: 14px;
                    height: 14px;
                    background: #1A73E8;
                    border: 3px solid #fff;
                    border-radius: 50%;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
                    cursor: pointer;
                }
                .chama-admin-pin-origin {
                    width: 14px;
                    height: 14px;
                    background: #111;
                    border: 3px solid #fff;
                    border-radius: 50%;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
                }
                .chama-admin-pin-destination {
                    width: 14px;
                    height: 14px;
                    background: #E24B4A;
                    border: 3px solid #fff;
                    border-radius: 2px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
                }
                .chama-admin-map-wrap {
                    position: relative;
                }
                .chama-admin-follow-btn {
                    position: absolute;
                    top: 12px;
                    right: 52px;
                    z-index: 2;
                    display: none;
                    align-items: center;
                    gap: 6px;
                    padding: 8px 12px;
                    border: none;
                    border-radius: 999px;
                    background: #111;
                    color: #fff;
                    font-size: 12px;
                    font-weight: 600;
                    cursor: pointer;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.25);
                }
                .chama-admin-follow-btn.is-visible {
                    display: inline-flex;
                }
            </style>
            <div class="chama-admin-map-wrap">
                <div
                    id="admin-live-map-{{ $widgetId }}"
                    wire:ignore
                    style="height: 420px; border-radius: 12px; overflow: hidden; background: #eef1f4; position: relative;"
                ></div>
                <button
                    type="button"
                    id="admin-live-map-follow-{{ $widgetId }}"
                    class="chama-admin-follow-btn"
                    aria-label="Seguir corridas no mapa"
                >
                    Seguir
                </button>
            </div>
            @if ($tripCount === 0)
                <p class="mt-2 text-sm" style="color: var(--fi-body-text-color, inherit); opacity: .75;">
                    Nenhuma corrida em andamento no mapa. Quando um motorista aceitar uma corrida, a rota por fase aparecerá aqui.
                </p>
            @endif
            <div
                id="admin-live-map-data-{{ $widgetId }}"
                data-map-id="admin-live-map-{{ $widgetId }}"
                data-trips='@json($trips)'
                data-center-lng="{{ $centerLng }}"
                data-center-lat="{{ $centerLat }}"
                hidden
            ></div>
            <p class="mt-2 text-xs text-gray-500">
                {{ $tripCount }} corrida(s) em andamento no mapa
                @if ($cityName)
                    · {{ $cityName }}
                @endif
                · rota por fase atualizada a cada 15s
            </p>

            @once
                <script src="https://api.mapbox.com/mapbox-gl-js/v3.9.0/mapbox-gl.js"></script>
            @endonce
            <script>
                (function () {
                    const dataElId = @json('admin-live-map-data-' . $widgetId);
                    const followBtnId = @json('admin-live-map-follow-' . $widgetId);
                    const token = @json($mapboxToken);
                    const stores = window.__chamaLiveMaps = window.__chamaLiveMaps || {};

                    function readPayload() {
                        const dataEl = document.getElementById(dataElId);
                        if (!dataEl) {
                            return null;
                        }

                        return {
                            mapId: dataEl.dataset.mapId,
                            trips: JSON.parse(dataEl.dataset.trips || '[]'),
                            center: [
                                parseFloat(dataEl.dataset.centerLng),
                                parseFloat(dataEl.dataset.centerLat),
                            ],
                        };
                    }

                    function applyChamaMapTheme(map) {
                        const style = map.getStyle();
                        if (!style?.layers) return;

                        for (const layer of style.layers) {
                            if (layer.type !== 'symbol') continue;
                            const id = layer.id.toLowerCase();
                            const hide = id.includes('poi')
                                || id.includes('transit')
                                || id.includes('airport');
                            if (hide) {
                                try {
                                    map.setLayoutProperty(layer.id, 'visibility', 'none');
                                } catch (_) {}
                            }
                        }
                    }

                    function driverDotHtml() {
                        return '<div class="chama-admin-driver-dot" aria-hidden="true"></div>';
                    }

                    function formatEta(trip) {
                        if (trip.eta_min == null || trip.distance_km == null) {
                            return '';
                        }
                        const km = Number(trip.distance_km).toFixed(1).replace('.', ',');
                        return `~${Math.round(trip.eta_min)} min · ${km} km`;
                    }

                    function tripPopupHtml(trip) {
                        const eta = formatEta(trip);
                        return `
                            <strong>${trip.driver.name}</strong><br>
                            <span style="color:#0F9E8D">${trip.status}</span><br>
                            <span style="color:#555;font-size:12px">${trip.phase_label || ''}</span>`
                            + (eta ? `<br><span style="font-weight:600">${eta}</span>` : '')
                            + (trip.origin?.address ? `<br><span style="font-size:11px;color:#666">Embarque: ${trip.origin.address}</span>` : '')
                            + (trip.destination?.address ? `<br><span style="font-size:11px;color:#666">Destino: ${trip.destination.address}</span>` : '');
                    }

                    function createCarMarker(trip) {
                        const root = document.createElement('div');
                        root.innerHTML = driverDotHtml();

                        return new mapboxgl.Marker({ element: root.firstElementChild, anchor: 'center' })
                            .setLngLat([trip.driver.lng, trip.driver.lat])
                            .setPopup(new mapboxgl.Popup({ offset: 16 }).setHTML(tripPopupHtml(trip)));
                    }

                    function createPinMarker(className, lngLat, popupHtml) {
                        const el = document.createElement('div');
                        el.className = className;

                        return new mapboxgl.Marker({ element: el, anchor: 'center' })
                            .setLngLat(lngLat)
                            .setPopup(new mapboxgl.Popup({ offset: 10 }).setHTML(popupHtml));
                    }

                    function clearLayers(store) {
                        store.markers.forEach((marker) => marker.remove());
                        store.markers = [];

                        store.routeLayerIds.forEach((layerId) => {
                            if (store.map.getLayer(`${layerId}-shadow`)) {
                                store.map.removeLayer(`${layerId}-shadow`);
                            }
                            if (store.map.getLayer(layerId)) {
                                store.map.removeLayer(layerId);
                            }
                        });
                        store.routeLayerIds = [];

                        store.routeSourceIds.forEach((sourceId) => {
                            if (store.map.getSource(sourceId)) {
                                store.map.removeSource(sourceId);
                            }
                        });
                        store.routeSourceIds = [];
                    }

                    function upsertRoute(store, trip, index) {
                        const sourceId = `chama-route-${index}`;
                        const layerId = `chama-route-line-${index}`;
                        const data = {
                            type: 'Feature',
                            geometry: {
                                type: 'LineString',
                                coordinates: trip.route,
                            },
                        };

                        if (store.map.getSource(sourceId)) {
                            store.map.getSource(sourceId).setData(data);
                        } else {
                            store.map.addSource(sourceId, { type: 'geojson', data });
                            store.map.addLayer({
                                id: `${layerId}-shadow`,
                                type: 'line',
                                source: sourceId,
                                layout: { 'line-join': 'round', 'line-cap': 'round' },
                                paint: {
                                    'line-color': '#1558B0',
                                    'line-width': 10,
                                    'line-opacity': 0.35,
                                },
                            });
                            store.map.addLayer({
                                id: layerId,
                                type: 'line',
                                source: sourceId,
                                layout: { 'line-join': 'round', 'line-cap': 'round' },
                                paint: {
                                    'line-color': '#1A73E8',
                                    'line-width': 6,
                                    'line-opacity': 1,
                                },
                            });
                            store.routeSourceIds.push(sourceId);
                            store.routeLayerIds.push(layerId);
                        }
                    }

                    function routeForTrip(trip) {
                        if (trip.route?.length >= 2) {
                            return trip.route;
                        }

                        if (trip.origin && trip.destination) {
                            return [
                                [trip.origin.lng, trip.origin.lat],
                                [trip.destination.lng, trip.destination.lat],
                            ];
                        }

                        return [];
                    }

                    function renderTrips(store, payload) {
                        clearLayers(store);

                        payload.trips.forEach((trip, index) => {
                            const route = routeForTrip(trip);
                            if (route.length >= 2) {
                                upsertRoute(store, { ...trip, route }, index);
                            }

                            store.markers.push(createCarMarker(trip).addTo(store.map));

                            const phase = trip.route_phase || 'full';
                            const showPickup = phase === 'to_pickup' || phase === 'full';
                            const showDestination = phase === 'to_destination' || phase === 'full';

                            if (showPickup && trip.origin) {
                                store.markers.push(
                                    createPinMarker(
                                        'chama-admin-pin-origin',
                                        [trip.origin.lng, trip.origin.lat],
                                        `<strong>Embarque</strong><br>${trip.origin.address || ''}`
                                    ).addTo(store.map)
                                );
                            }

                            if (showDestination && trip.destination) {
                                store.markers.push(
                                    createPinMarker(
                                        'chama-admin-pin-destination',
                                        [trip.destination.lng, trip.destination.lat],
                                        `<strong>Destino</strong><br>${trip.destination.address || ''}`
                                    ).addTo(store.map)
                                );
                            }
                        });

                        const points = payload.trips.flatMap((trip) => {
                            const phase = trip.route_phase || 'full';
                            const route = routeForTrip(trip);
                            if (route.length >= 2) {
                                return route;
                            }
                            const coords = [[trip.driver.lng, trip.driver.lat]];
                            if (trip.origin) {
                                coords.push([trip.origin.lng, trip.origin.lat]);
                            }
                            if (trip.destination) {
                                coords.push([trip.destination.lng, trip.destination.lat]);
                            }
                            return coords;
                        });

                        const tripIds = payload.trips.map((trip) => trip.id).join(',');
                        if (store.lastTripIds !== tripIds) {
                            store.lastTripIds = tripIds;
                            store.userMoved = false;
                        }

                        updateFollowButton(store);

                        if (!store.userMoved) {
                            fitMapToPoints(store, points, payload.center);
                        }
                    }

                    function fitMapToPoints(store, points, center) {
                        if (points.length > 1) {
                            const bounds = points.reduce(
                                (b, coord) => b.extend(coord),
                                new mapboxgl.LngLatBounds(points[0], points[0])
                            );
                            store.map.fitBounds(bounds, { padding: 40, maxZoom: 17, minZoom: 13, duration: 700 });
                        } else if (points.length === 1) {
                            store.map.flyTo({ center: points[0], zoom: 14, duration: 700 });
                        } else {
                            store.map.flyTo({ center, zoom: 13, duration: 700 });
                        }
                    }

                    function updateFollowButton(store) {
                        const followBtn = document.getElementById(followBtnId);
                        if (!followBtn) {
                            return;
                        }
                        followBtn.classList.toggle('is-visible', Boolean(store.userMoved));
                    }

                    function ensureMap(payload) {
                        if (stores[payload.mapId]) {
                            return stores[payload.mapId];
                        }

                        const el = document.getElementById(payload.mapId);
                        if (!el || !window.mapboxgl) {
                            return null;
                        }

                        mapboxgl.accessToken = token;
                        const map = new mapboxgl.Map({
                            container: el,
                            style: 'mapbox://styles/mapbox/streets-v12',
                            center: payload.center,
                            zoom: 13,
                        });

                        map.addControl(new mapboxgl.NavigationControl(), 'top-right');

                        const store = {
                            map,
                            markers: [],
                            routeSourceIds: [],
                            routeLayerIds: [],
                            ready: false,
                            latestPayload: payload,
                            userMoved: false,
                            lastTripIds: '',
                        };
                        stores[payload.mapId] = store;

                        const markUserMoved = (event) => {
                            if (event.originalEvent) {
                                store.userMoved = true;
                                updateFollowButton(store);
                            }
                        };
                        map.on('dragstart', markUserMoved);
                        map.on('zoomstart', markUserMoved);
                        map.on('rotatestart', markUserMoved);

                        const followBtn = document.getElementById(followBtnId);
                        if (followBtn && !followBtn.__chamaBound) {
                            followBtn.__chamaBound = true;
                            followBtn.addEventListener('click', () => {
                                store.userMoved = false;
                                updateFollowButton(store);
                                const payload = store.latestPayload;
                                if (!payload) {
                                    return;
                                }
                                const points = payload.trips.flatMap((trip) => {
                                    const route = routeForTrip(trip);
                                    if (route.length >= 2) {
                                        return route;
                                    }
                                    const coords = [[trip.driver.lng, trip.driver.lat]];
                                    if (trip.origin) {
                                        coords.push([trip.origin.lng, trip.origin.lat]);
                                    }
                                    if (trip.destination) {
                                        coords.push([trip.destination.lng, trip.destination.lat]);
                                    }
                                    return coords;
                                });
                                fitMapToPoints(store, points, payload.center);
                            });
                        }

                        map.on('load', () => {
                            applyChamaMapTheme(map);
                            store.ready = true;
                            renderTrips(store, store.latestPayload || payload);
                        });

                        return store;
                    }

                    function refreshMap() {
                        const payload = readPayload();
                        if (!payload) {
                            return;
                        }

                        const store = ensureMap(payload);
                        if (!store) {
                            return;
                        }

                        store.latestPayload = payload;

                        if (store.ready) {
                            renderTrips(store, payload);
                        }
                    }

                    function watchDataElement() {
                        const dataEl = document.getElementById(dataElId);
                        if (!dataEl || dataEl.__chamaObserver) {
                            return;
                        }

                        const observer = new MutationObserver(refreshMap);
                        observer.observe(dataEl, {
                            attributes: true,
                            attributeFilter: ['data-trips', 'data-center-lng', 'data-center-lat'],
                        });
                        dataEl.__chamaObserver = observer;
                    }

                    function boot() {
                        watchDataElement();
                        refreshMap();
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
