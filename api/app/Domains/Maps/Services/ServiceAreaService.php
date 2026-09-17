<?php

namespace App\Domains\Maps\Services;

use App\Models\City;
use App\Models\Driver;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Response;

class ServiceAreaService
{
    public function maxRadiusKm(): float
    {
        return (float) config('app.geocode_max_radius_km', 50);
    }

    /**
     * @return array{latitude: float, longitude: float, label: ?string}|null
     */
    public function resolveDepartureCenter(?City $city, ?Driver $driver = null, ?float $fallbackLat = null, ?float $fallbackLng = null): ?array
    {
        if ($driver?->departure_lat !== null && $driver->departure_lng !== null) {
            return [
                'latitude' => (float) $driver->departure_lat,
                'longitude' => (float) $driver->departure_lng,
                'label' => $driver->departure_address,
            ];
        }

        if ($city !== null) {
            $lat = City::normalizeCoordinate($city->departure_lat);
            $lng = City::normalizeCoordinate($city->departure_lng);

            if ($lat !== null && $lng !== null) {
                return [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'label' => $city->departure_address,
                ];
            }
        }

        if ($city && $fallbackLat === null && $fallbackLng === null) {
            $cityDriver = Driver::query()
                ->where('city_id', $city->id)
                ->whereNotNull('departure_lat')
                ->whereNotNull('departure_lng')
                ->first();

            if ($cityDriver) {
                return [
                    'latitude' => (float) $cityDriver->departure_lat,
                    'longitude' => (float) $cityDriver->departure_lng,
                    'label' => $cityDriver->departure_address,
                ];
            }
        }

        if ($fallbackLat !== null && $fallbackLng !== null) {
            return [
                'latitude' => $fallbackLat,
                'longitude' => $fallbackLng,
                'label' => null,
            ];
        }

        return null;
    }

    public function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function isWithinRadius(float $lat, float $lng, float $centerLat, float $centerLng, ?float $maxKm = null): bool
    {
        return $this->distanceKm($lat, $lng, $centerLat, $centerLng) <= ($maxKm ?? $this->maxRadiusKm());
    }

    public function assertWithinServiceArea(float $lat, float $lng, ?City $city, ?Driver $driver = null, string $field = 'localização'): void
    {
        if ($city && ! $city->acceptsRides()) {
            throw new HttpResponseException(
                Response::json([
                    'message' => "Corridas não estão habilitadas para {$city->name}. Ative em Admin → Cidades.",
                ], 422)
            );
        }

        $center = $this->resolveDepartureCenter($city, $driver);

        if (! $center) {
            return;
        }

        $cityLabel = $city?->name ?? 'da área de operação';

        if (! $this->isWithinRadius($lat, $lng, $center['latitude'], $center['longitude'])) {
            throw new HttpResponseException(
                Response::json([
                    'message' => "A {$field} deve estar a no máximo {$this->maxRadiusKm()} km do centro de {$cityLabel}.",
                ], 422)
            );
        }
    }

    /**
     * @return array{distance_km: float, center: array{latitude: float, longitude: float, label: ?string}, within_service_area: bool}
     */
    public function resolveSelectedCity(City $city, float $lat, float $lng): array
    {
        if (! $city->acceptsRides()) {
            throw new HttpResponseException(
                Response::json([
                    'message' => "Corridas não estão habilitadas para {$city->name}. Ative em Admin → Cidades.",
                ], 422)
            );
        }

        $center = $this->resolveDepartureCenter($city);

        if (! $center) {
            throw new HttpResponseException(
                Response::json([
                    'message' => "Configure o endereço de partida de {$city->name} no admin.",
                ], 422)
            );
        }

        $distance = round($this->distanceKm($lat, $lng, $center['latitude'], $center['longitude']), 2);

        return [
            'distance_km' => $distance,
            'center' => $center,
            'within_service_area' => $distance <= $this->maxRadiusKm(),
        ];
    }

    /**
     * Lista cidades operacionais cujo centro de partida está a no máximo maxRadiusKm do ponto informado.
     *
     * @return list<array{
     *     city: City,
     *     distance_km: float,
     *     within_service_area: bool,
     *     center: array{latitude: float, longitude: float, label: ?string}
     * }>
     */
    public function listCitiesWithinRadius(float $lat, float $lng, ?string $tenantId = null, ?float $maxKm = null): array
    {
        $maxKm ??= $this->maxRadiusKm();
        $matches = [];

        $cities = City::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('active', true)
            ->where('rides_enabled', true)
            ->orderBy('name')
            ->get();

        foreach ($cities as $city) {
            if (! $city->acceptsRides()) {
                continue;
            }

            $center = $this->resolveDepartureCenter($city);

            if (! $center) {
                continue;
            }

            $distance = round($this->distanceKm($lat, $lng, $center['latitude'], $center['longitude']), 2);

            if ($distance > $maxKm) {
                continue;
            }

            $matches[] = [
                'city' => $city,
                'distance_km' => $distance,
                'within_service_area' => true,
                'center' => $center,
            ];
        }

        usort($matches, fn (array $a, array $b) => $a['distance_km'] <=> $b['distance_km']);

        return $matches;
    }

    /**
     * @return array{city: City, distance_km: float, center: array{latitude: float, longitude: float, label: ?string}}|null
     */
    public function resolveCityForCoordinates(float $lat, float $lng, ?string $tenantId = null): ?array
    {
        $maxKm = $this->maxRadiusKm();
        $best = null;

        $cities = City::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('active', true)
            ->where('rides_enabled', true)
            ->get();

        foreach ($cities as $city) {
            $center = $this->resolveDepartureCenter($city);

            if (! $center) {
                continue;
            }

            $distance = $this->distanceKm($lat, $lng, $center['latitude'], $center['longitude']);

            if ($distance > $maxKm) {
                continue;
            }

            if ($best === null || $distance < $best['distance_km']) {
                $best = [
                    'city' => $city,
                    'distance_km' => round($distance, 2),
                    'center' => $center,
                ];
            }
        }

        return $best;
    }

    /**
     * @return array{city: City, distance_km: float, center: array{latitude: float, longitude: float, label: ?string}}
     */
    public function resolveCityOrFail(float $lat, float $lng, ?string $tenantId = null): array
    {
        $resolved = $this->resolveCityForCoordinates($lat, $lng, $tenantId);

        if ($resolved) {
            return $resolved;
        }

        $cityNames = City::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('active', true)
            ->where('rides_enabled', true)
            ->get()
            ->filter(fn (City $city) => $this->resolveDepartureCenter($city) !== null)
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $areas = $cityNames !== [] ? implode(', ', $cityNames) : 'nenhuma cidade configurada';

        throw new HttpResponseException(
            Response::json([
                'message' => "Você está fora das áreas de operação ({$this->maxRadiusKm()} km). Cidades atendidas: {$areas}.",
            ], 422)
        );
    }

    public function assertCityMatchesOrigin(City $city, float $lat, float $lng, ?string $tenantId = null): void
    {
        $resolved = $this->resolveCityOrFail($lat, $lng, $tenantId);

        if ($resolved['city']->id !== $city->id) {
            throw new HttpResponseException(
                Response::json([
                    'message' => "A origem informada pertence à área de {$resolved['city']->name}, não de {$city->name}.",
                ], 422)
            );
        }
    }
}
