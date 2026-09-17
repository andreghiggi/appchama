<?php

namespace App\Domains\Rides\Services;

use App\Domains\Maps\Services\MapboxService;
use App\Models\Driver;
use App\Models\Ride;
use Illuminate\Support\Facades\Redis;

class RideTrackingService
{
    public function __construct(private MapboxService $mapbox) {}

    /**
     * @return array{
     *     driver_location: ?array{latitude: float, longitude: float},
     *     driver_distance_km: ?float,
     *     driver_eta_min: ?int,
     *     route_coordinates: array<int, array{latitude: float, longitude: float}>,
     *     trip_route_coordinates: array<int, array{latitude: float, longitude: float}>,
     *     route_phase: string,
     *     route_target: ?array{latitude: float, longitude: float, label: string}
     * }
     */
    public function forRide(Ride $ride): array
    {
        $tracking = [
            'driver_location' => null,
            'driver_distance_km' => null,
            'driver_eta_min' => null,
            'route_coordinates' => [],
            'trip_route_coordinates' => [],
            'route_phase' => 'full',
            'route_target' => null,
        ];

        $originLat = (float) $ride->origin_lat;
        $originLng = (float) $ride->origin_lng;
        $destLat = (float) $ride->destination_lat;
        $destLng = (float) $ride->destination_lng;

        if (! $ride->driver_id || ! in_array($ride->status, ['accepted', 'driver_arrived', 'in_progress', 'awaiting_payment'], true)) {
            if (in_array($ride->status, ['searching', 'accepted', 'driver_arrived', 'in_progress', 'awaiting_payment'], true)) {
                $tripRoute = $this->ensureRouteCoordinates(
                    $this->originDestinationRoute($ride),
                    $originLat,
                    $originLng,
                    $destLat,
                    $destLng,
                );
                $tracking['route_coordinates'] = $tripRoute;
                $tracking['trip_route_coordinates'] = $tripRoute;
                $tracking['route_target'] = [
                    'latitude' => $destLat,
                    'longitude' => $destLng,
                    'label' => 'Destino',
                ];
            }

            return $tracking;
        }

        $driver = $ride->relationLoaded('driver')
            ? $ride->driver
            : Driver::query()->with('city:id,departure_lat,departure_lng')->find($ride->driver_id);

        if (! $driver) {
            $tracking['route_coordinates'] = $this->ensureRouteCoordinates(
                [],
                $originLat,
                $originLng,
                $destLat,
                $destLng,
            );

            return $tracking;
        }

        $driverLoc = $this->resolveDriverLocation($driver);
        if ($driverLoc) {
            $tracking['driver_location'] = $driverLoc;
        }

        if ($ride->status === 'in_progress') {
            $tracking['route_phase'] = 'to_destination';
            $tracking['route_target'] = [
                'latitude' => $destLat,
                'longitude' => $destLng,
                'label' => 'Destino',
            ];

            if ($driverLoc) {
                $route = $this->mapbox->directions(
                    $driverLoc['latitude'],
                    $driverLoc['longitude'],
                    $destLat,
                    $destLng,
                );
            } else {
                [$fromLat, $fromLng] = $this->resolveDriverFallbackPoint($driver, $originLat, $originLng);
                $route = $this->mapbox->directions($fromLat, $fromLng, $destLat, $destLng);
            }
        } elseif ($ride->status === 'driver_arrived') {
            $tracking['route_phase'] = 'full';
            $tracking['route_target'] = [
                'latitude' => $destLat,
                'longitude' => $destLng,
                'label' => 'Destino',
            ];
            $route = $this->mapbox->directions($originLat, $originLng, $destLat, $destLng);
        } elseif ($ride->status === 'awaiting_payment') {
            $tracking['route_phase'] = 'full';
            $tracking['route_target'] = [
                'latitude' => $destLat,
                'longitude' => $destLng,
                'label' => 'Destino',
            ];
            $route = $this->mapbox->directions($originLat, $originLng, $destLat, $destLng);
        } else {
            $tracking['route_phase'] = 'to_pickup';
            $tracking['route_target'] = [
                'latitude' => $originLat,
                'longitude' => $originLng,
                'label' => 'Embarque',
            ];

            if ($driverLoc) {
                $route = $this->mapbox->directions(
                    $driverLoc['latitude'],
                    $driverLoc['longitude'],
                    $originLat,
                    $originLng,
                );
            } else {
                [$fromLat, $fromLng] = $this->resolveDriverFallbackPoint($driver, $originLat, $originLng);
                $route = $this->mapbox->directions($fromLat, $fromLng, $originLat, $originLng);
            }
        }

        if (in_array($ride->status, ['driver_arrived', 'awaiting_payment'], true)) {
            $tracking['driver_distance_km'] = null;
            $tracking['driver_eta_min'] = null;
        } else {
            $tracking['driver_distance_km'] = $route['distance_km'] ?? null;
            $eta = $route['duration_min'] ?? null;
            if ($eta !== null && ($route['distance_km'] ?? 0) > 0.05) {
                $eta = max(1, (int) $eta);
            }
            $tracking['driver_eta_min'] = $eta;
        }

        if ($driverLoc) {
            $fallbackFrom = $driverLoc;
        } else {
            [$fbLat, $fbLng] = $this->resolveDriverFallbackPoint($driver, $originLat, $originLng);
            $fallbackFrom = ['latitude' => $fbLat, 'longitude' => $fbLng];
            // Sem GPS real: expor posição aproximada para o app desenhar o carro no mapa.
            $tracking['driver_location'] = $fallbackFrom;
        }
        $fallbackTo = $tracking['route_target'] ?? [
            'latitude' => $destLat,
            'longitude' => $destLng,
        ];

        $tracking['route_coordinates'] = $this->ensureRouteCoordinates(
            $route['coordinates'] ?? [],
            (float) $fallbackFrom['latitude'],
            (float) $fallbackFrom['longitude'],
            (float) $fallbackTo['latitude'],
            (float) $fallbackTo['longitude'],
        );

        $tracking['trip_route_coordinates'] = $this->ensureRouteCoordinates(
            $this->originDestinationRoute($ride),
            $originLat,
            $originLng,
            $destLat,
            $destLng,
        );

        return $tracking;
    }

    /**
     * ETA e rota até o embarque para oferta de corrida (status searching).
     *
     * @return array{
     *     driver_distance_km: ?float,
     *     driver_eta_min: ?int,
     *     route_coordinates: array<int, array{latitude: float, longitude: float}>,
     *     route_phase: string,
     *     route_target: ?array{latitude: float, longitude: float, label: string}
     * }
     */
    public function forOffer(Ride $ride, Driver $driver): array
    {
        $originLat = (float) $ride->origin_lat;
        $originLng = (float) $ride->origin_lng;

        $tracking = [
            'driver_distance_km' => null,
            'driver_eta_min' => null,
            'route_coordinates' => [],
            'route_phase' => 'to_pickup',
            'route_target' => [
                'latitude' => $originLat,
                'longitude' => $originLng,
                'label' => 'Embarque',
            ],
        ];

        $driverLoc = $this->resolveDriverLocation($driver);
        if ($driverLoc) {
            $fromLat = $driverLoc['latitude'];
            $fromLng = $driverLoc['longitude'];
        } else {
            [$fromLat, $fromLng] = $this->resolveDriverFallbackPoint($driver, $originLat, $originLng);
        }

        $route = $this->mapbox->directions($fromLat, $fromLng, $originLat, $originLng);
        $tracking['driver_distance_km'] = $route['distance_km'] ?? null;
        $eta = $route['duration_min'] ?? null;
        if ($eta !== null && ($route['distance_km'] ?? 0) > 0.05) {
            $eta = max(1, (int) $eta);
        }
        $tracking['driver_eta_min'] = $eta;
        $tracking['route_coordinates'] = $this->ensureRouteCoordinates(
            $route['coordinates'] ?? [],
            $fromLat,
            $fromLng,
            $originLat,
            $originLng,
        );
        $tracking['driver_location'] = ['latitude' => $fromLat, 'longitude' => $fromLng];
        $tracking['trip_route_coordinates'] = $this->ensureRouteCoordinates(
            $this->originDestinationRoute($ride),
            $originLat,
            $originLng,
            (float) $ride->destination_lat,
            (float) $ride->destination_lng,
        );

        return $tracking;
    }

    /** @return array<string, mixed> */
    public function mergeIntoOffer(Ride $ride, Driver $driver): array
    {
        $ride->loadMissing([
            'passenger:id,name,phone',
            'driver.user:id,name,phone',
            'driver.vehicles',
            'driver.city:id,departure_lat,departure_lng',
            'city',
            'ratings',
        ]);
        $driver->loadMissing('city:id,departure_lat,departure_lng');

        return array_merge($ride->toArray(), $this->forOffer($ride, $driver));
    }

    /** @return array<string, mixed> */
    public function mergeIntoRide(Ride $ride): array
    {
        $ride->loadMissing([
            'passenger:id,name,phone',
            'driver.user:id,name,phone',
            'driver.vehicles',
            'driver.city:id,departure_lat,departure_lng',
            'city',
            'ratings',
        ]);

        return array_merge($ride->toArray(), $this->forRide($ride));
    }

    /**
     * @return ?array{latitude: float, longitude: float}
     */
    private function resolveDriverLocation(Driver $driver): ?array
    {
        if ($driver->current_lat !== null && $driver->current_lng !== null) {
            return [
                'latitude' => (float) $driver->current_lat,
                'longitude' => (float) $driver->current_lng,
            ];
        }

        $pos = Redis::geopos("geo:city:{$driver->city_id}", $driver->user_id);

        if (! empty($pos[0])) {
            return [
                'latitude' => (float) $pos[0][1],
                'longitude' => (float) $pos[0][0],
            ];
        }

        return null;
    }

    /**
     * Ponto inicial quando o motorista ainda não tem GPS (centro da cidade ou origem).
     *
     * @return array{0: float, 1: float}
     */
    private function resolveDriverFallbackPoint(Driver $driver, float $originLat, float $originLng): array
    {
        $cityLat = $driver->city?->departure_lat;
        $cityLng = $driver->city?->departure_lng;

        if ($cityLat !== null && $cityLng !== null) {
            return [(float) $cityLat, (float) $cityLng];
        }

        return [$originLat, $originLng];
    }

    /**
     * @return array<int, array{latitude: float, longitude: float}>
     */
    private function originDestinationRoute(Ride $ride): array
    {
        $route = $this->mapbox->directions(
            (float) $ride->origin_lat,
            (float) $ride->origin_lng,
            (float) $ride->destination_lat,
            (float) $ride->destination_lng,
        );

        return $route['coordinates'] ?? [];
    }

    /**
     * Garante polyline com pelo menos 2 pontos válidos para o cliente desenhar a rota.
     *
     * @param  array<int, array{latitude: float, longitude: float}>  $coordinates
     * @return array<int, array{latitude: float, longitude: float}>
     */
    private function ensureRouteCoordinates(
        array $coordinates,
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng,
    ): array {
        $normalized = collect($coordinates)
            ->map(function ($point) {
                if (! is_array($point)) {
                    return null;
                }

                $lat = $point['latitude'] ?? $point['lat'] ?? null;
                $lng = $point['longitude'] ?? $point['lng'] ?? null;

                if ($lat === null || $lng === null) {
                    return null;
                }

                $lat = (float) $lat;
                $lng = (float) $lng;

                if (! $this->isFiniteCoordinate($lat, $lng)) {
                    return null;
                }

                return ['latitude' => $lat, 'longitude' => $lng];
            })
            ->filter()
            ->values()
            ->all();

        if (count($normalized) >= 2) {
            return $normalized;
        }

        $fallback = $this->mapbox->directions($fromLat, $fromLng, $toLat, $toLng);

        return $fallback['coordinates'] ?? [
            ['latitude' => $fromLat, 'longitude' => $fromLng],
            ['latitude' => $toLat, 'longitude' => $toLng],
        ];
    }

    private function isFiniteCoordinate(float $lat, float $lng): bool
    {
        if ($lat === 0.0 && $lng === 0.0) {
            return false;
        }

        return abs($lat) <= 90 && abs($lng) <= 180;
    }
}
