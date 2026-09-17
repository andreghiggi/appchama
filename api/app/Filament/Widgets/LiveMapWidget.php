<?php

namespace App\Filament\Widgets;

use App\Domains\Rides\Services\RideTrackingService;
use App\Models\City;
use App\Models\Ride;
use App\Models\Scopes\TenantScope;
use App\Support\StatusLabels;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;

class LiveMapWidget extends Widget
{
    use CanPoll;

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.live-map';

    protected function getViewData(): array
    {
        $city = City::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('active', true)
            ->whereNotNull('departure_lat')
            ->orderBy('name')
            ->first();

        $centerLat = (float) ($city?->departure_lat ?? -28.8436);
        $centerLng = (float) ($city?->departure_lng ?? -51.8908);

        $trips = $this->buildActiveTrips($centerLat, $centerLng);

        return [
            'mapboxToken' => config('services.mapbox.token'),
            'centerLat' => $centerLat,
            'centerLng' => $centerLng,
            'cityName' => $city?->name,
            'trips' => $trips,
            'tripCount' => count($trips),
            'widgetId' => $this->getId(),
        ];
    }

    private function buildActiveTrips(float $centerLat, float $centerLng): array
    {
        $rides = Ride::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with(['passenger:id,name', 'driver.user:id,name', 'driver.city:id,name,departure_lat,departure_lng'])
            ->whereIn('status', ['accepted', 'driver_arrived', 'in_progress'])
            ->whereNotNull('driver_id')
            ->latest('requested_at')
            ->limit(20)
            ->get();

        $trips = [];

        foreach ($rides as $ride) {
            $driver = $ride->driver;
            if (! $driver) {
                continue;
            }

            $tracking = app(RideTrackingService::class)->forRide($ride);

            $originLat = (float) $ride->origin_lat;
            $originLng = (float) $ride->origin_lng;
            $destLat = (float) $ride->destination_lat;
            $destLng = (float) $ride->destination_lng;

            $driverLat = $tracking['driver_location']['latitude']
                ?? $driver->current_lat
                ?? $driver->city?->departure_lat
                ?? $originLat;
            $driverLng = $tracking['driver_location']['longitude']
                ?? $driver->current_lng
                ?? $driver->city?->departure_lng
                ?? $originLng;

            if (! $this->isFiniteCoordinate((float) $driverLat, (float) $driverLng)) {
                $driverLat = $originLat;
                $driverLng = $originLng;
            }

            $routePhase = $tracking['route_phase'] ?? 'full';

            $route = collect($tracking['route_coordinates'] ?? [])
                ->map(fn (array $point) => [(float) $point['longitude'], (float) $point['latitude']])
                ->filter(fn (array $coord) => $this->isFiniteCoordinate($coord[1], $coord[0]))
                ->values()
                ->all();

            if (count($route) < 2) {
                $target = $tracking['route_target'] ?? null;
                if ($target) {
                    $route = [
                        [(float) $driverLng, (float) $driverLat],
                        [(float) $target['longitude'], (float) $target['latitude']],
                    ];
                } else {
                    $route = [
                        [$originLng, $originLat],
                        [$destLng, $destLat],
                    ];
                }
            }

            $trips[] = [
                'id' => $ride->id,
                'status' => StatusLabels::rideStatus($ride->status),
                'route_phase' => $routePhase,
                'phase_label' => $this->phaseLabel($routePhase),
                'eta_min' => $tracking['driver_eta_min'],
                'distance_km' => $tracking['driver_distance_km'] !== null
                    ? round((float) $tracking['driver_distance_km'], 1)
                    : null,
                'driver' => [
                    'name' => $driver->user?->name ?? 'Motorista',
                    'lat' => (float) $driverLat,
                    'lng' => (float) $driverLng,
                ],
                'origin' => [
                    'lat' => $originLat,
                    'lng' => $originLng,
                    'address' => $ride->origin_address,
                ],
                'destination' => [
                    'lat' => $destLat,
                    'lng' => $destLng,
                    'address' => $ride->destination_address,
                ],
                'route' => $route,
                'bearing' => $this->bearingOnRoute($route),
            ];
        }

        return $trips;
    }

    private function phaseLabel(string $phase): string
    {
        return match ($phase) {
            'to_pickup' => 'Indo buscar passageiro',
            'to_destination' => 'Indo ao destino',
            default => 'Corrida em andamento',
        };
    }

    private function bearingOnRoute(array $route): ?float
    {
        if (count($route) < 2) {
            return null;
        }

        [$lng1, $lat1] = $route[0];
        [$lng2, $lat2] = $route[min(1, count($route) - 1)];

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $dLng = deg2rad($lng2 - $lng1);

        $y = sin($dLng) * cos($lat2Rad);
        $x = cos($lat1Rad) * sin($lat2Rad) - sin($lat1Rad) * cos($lat2Rad) * cos($dLng);

        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }

    private function isFiniteCoordinate(float $lat, float $lng): bool
    {
        if ($lat === 0.0 && $lng === 0.0) {
            return false;
        }

        return abs($lat) <= 90 && abs($lng) <= 180;
    }

    protected function getPollingInterval(): ?string
    {
        return '15s';
    }
}
