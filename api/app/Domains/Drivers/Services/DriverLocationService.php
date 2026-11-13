<?php

namespace App\Domains\Drivers\Services;

use App\Events\DriverLocationUpdated;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\RideLocation;
use Illuminate\Support\Facades\Redis;

class DriverLocationService
{
    private const LAST_SEEN_TTL = 300;

    public function update(Driver $driver, float $lat, float $lng): void
    {
        $cityId = $driver->city_id;
        $driverId = $driver->user_id;

        Redis::geoadd("geo:city:{$cityId}", $lng, $lat, $driverId);
        Redis::setex("driver:{$driverId}:last_seen", self::LAST_SEEN_TTL, now()->timestamp);

        $driver->update([
            'current_lat' => $lat,
            'current_lng' => $lng,
            'last_location_at' => now(),
        ]);

        $activeRide = Ride::query()
            ->where('driver_id', $driverId)
            ->whereIn('status', ['accepted', 'driver_arrived', 'in_progress'])
            ->first();

        if ($activeRide) {
            $this->maybeSnapshot($driverId, $activeRide, $lat, $lng);
            event(new DriverLocationUpdated($activeRide, $lat, $lng));
        }
    }

    public function findNearby(string $cityId, float $lat, float $lng, float $radiusKm = 50, int $limit = 20): array
    {
        $results = Redis::georadius(
            "geo:city:{$cityId}",
            $lng,
            $lat,
            $radiusKm,
            'km',
            ['WITHDIST', 'ASC', 'COUNT' => $limit]
        );

        $drivers = [];
        $seen = [];

        foreach ($results as $result) {
            $driverId = is_array($result) ? $result[0] : $result;
            $distance = is_array($result) ? (float) $result[1] : 0;

            if (! $this->isDriverAvailable($driverId)) {
                continue;
            }

            $driver = Driver::query()->with('user')->find($driverId);

            if (! $driver || ! $driver->canReceiveRides()) {
                continue;
            }

            $seen[$driverId] = true;
            $drivers[] = [
                'driver' => $driver,
                'distance_km' => $distance,
            ];
        }

        if (count($drivers) < $limit) {
            $fallback = Driver::query()
                ->with('user')
                ->where('city_id', $cityId)
                ->where('online', true)
                ->where('background_check_status', 'approved')
                ->whereNotIn('subscription_status', ['suspended', 'overdue'])
                ->whereNotIn('user_id', array_keys($seen))
                ->limit($limit - count($drivers))
                ->get();

            foreach ($fallback as $driver) {
                if (! $driver->canReceiveRides()) {
                    continue;
                }

                $drivers[] = [
                    'driver' => $driver,
                    'distance_km' => $this->distanceKm(
                        $lat,
                        $lng,
                        (float) ($driver->current_lat ?? $lat),
                        (float) ($driver->current_lng ?? $lng),
                    ),
                ];
            }
        }

        return $drivers;
    }

    private function isDriverAvailable(string $driverId): bool
    {
        if (Redis::get("driver:{$driverId}:status") === 'busy') {
            return false;
        }

        if (Redis::exists("driver:{$driverId}:last_seen")) {
            return true;
        }

        $driver = Driver::query()->find($driverId);

        if ($driver?->online) {
            Redis::setex("driver:{$driverId}:last_seen", self::LAST_SEEN_TTL, now()->timestamp);

            return true;
        }

        return false;
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    public function setStatus(string $driverId, string $status): void
    {
        Redis::set("driver:{$driverId}:status", $status);
    }

    public function goOnline(Driver $driver, ?float $lat = null, ?float $lng = null): void
    {
        if ($driver->background_check_status !== 'approved') {
            abort(422, 'Motorista aguardando aprovação.');
        }

        if (in_array($driver->subscription_status, ['suspended', 'overdue'], true)) {
            abort(422, 'Mensalidade em atraso. Regularize para ficar online.');
        }

        $driver->update(['online' => true]);

        $hasActiveRide = Ride::query()
            ->where('driver_id', $driver->user_id)
            ->whereIn('status', ['accepted', 'driver_arrived', 'in_progress'])
            ->exists();

        // Evita dessincronizar o status "ocupado" no Redis com uma corrida
        // ainda aberta no banco quando o motorista reabre o app (ex: após queda de conexão).
        $this->setStatus($driver->user_id, $hasActiveRide ? 'busy' : 'available');

        [$lat, $lng] = $this->resolveCoordinates($driver, $lat, $lng);
        $this->update($driver, $lat, $lng);
    }

    /**
     * Garante coordenadas ao ficar online mesmo sem GPS no app (usa última posição ou partida da cidade).
     *
     * @return array{0: float, 1: float}
     */
    private function resolveCoordinates(Driver $driver, ?float $lat, ?float $lng): array
    {
        if ($lat !== null && $lng !== null) {
            return [$lat, $lng];
        }

        if ($driver->current_lat !== null && $driver->current_lng !== null) {
            return [(float) $driver->current_lat, (float) $driver->current_lng];
        }

        if ($driver->departure_lat !== null && $driver->departure_lng !== null) {
            return [(float) $driver->departure_lat, (float) $driver->departure_lng];
        }

        $driver->loadMissing('city');

        if ($driver->city?->departure_lat !== null && $driver->city?->departure_lng !== null) {
            return [(float) $driver->city->departure_lat, (float) $driver->city->departure_lng];
        }

        abort(422, 'Informe sua localização para ficar online.');
    }

    public function goOffline(Driver $driver): void
    {
        $driver->update(['online' => false]);
        Redis::del("driver:{$driver->user_id}:status");
        Redis::zrem("geo:city:{$driver->city_id}", $driver->user_id);
    }

    private function maybeSnapshot(string $driverId, Ride $ride, float $lat, float $lng): void
    {
        $key = "driver:{$driverId}:last_snapshot";
        $last = Redis::get($key);

        if ($last && (now()->timestamp - (int) $last) < 30) {
            return;
        }

        RideLocation::query()->create([
            'ride_id' => $ride->id,
            'lat' => $lat,
            'lng' => $lng,
            'recorded_at' => now(),
        ]);

        Redis::setex($key, 30, now()->timestamp);
    }
}
