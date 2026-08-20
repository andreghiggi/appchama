<?php

namespace App\Domains\Drivers\Services;

use App\Events\DriverLocationUpdated;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\RideLocation;
use Illuminate\Support\Facades\Redis;

class DriverLocationService
{
    private const LAST_SEEN_TTL = 90;

    public function update(Driver $driver, float $lat, float $lng): void
    {
        $cityId = $driver->city_id;
        $driverId = $driver->user_id;

        Redis::geoadd("geo:city:{$cityId}", $lng, $lat, $driverId);
        Redis::setex("driver:{$driverId}:last_seen", self::LAST_SEEN_TTL, now()->timestamp);

        $activeRide = Ride::query()
            ->where('driver_id', $driverId)
            ->whereIn('status', ['accepted', 'driver_arrived', 'in_progress'])
            ->first();

        if ($activeRide) {
            $this->maybeSnapshot($driverId, $activeRide, $lat, $lng);
            event(new DriverLocationUpdated($activeRide, $lat, $lng));
        }

        $updateCount = (int) Redis::incr("driver:{$driverId}:loc_count");
        if ($updateCount % 10 === 0) {
            $driver->update([
                'current_lat' => $lat,
                'current_lng' => $lng,
                'last_location_at' => now(),
            ]);
        }
    }

    public function findNearby(string $cityId, float $lat, float $lng, float $radiusKm = 5, int $limit = 20): array
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

        foreach ($results as $result) {
            $driverId = is_array($result) ? $result[0] : $result;
            $distance = is_array($result) ? (float) $result[1] : 0;

            if (! Redis::exists("driver:{$driverId}:last_seen")) {
                continue;
            }

            if (Redis::get("driver:{$driverId}:status") === 'busy') {
                continue;
            }

            $driver = Driver::query()->with('user')->find($driverId);

            if (! $driver || ! $driver->canReceiveRides()) {
                continue;
            }

            $drivers[] = [
                'driver' => $driver,
                'distance_km' => $distance,
            ];
        }

        return $drivers;
    }

    public function setStatus(string $driverId, string $status): void
    {
        Redis::set("driver:{$driverId}:status", $status);
    }

    public function goOnline(Driver $driver): void
    {
        if (! $driver->canReceiveRides() && $driver->subscription_status === 'pending') {
            abort(422, 'Motorista aguardando aprovação ou mensalidade.');
        }

        if (in_array($driver->subscription_status, ['suspended', 'overdue'], true)) {
            abort(422, 'Mensalidade em atraso. Regularize para ficar online.');
        }

        $driver->update(['online' => true]);
        $this->setStatus($driver->user_id, 'available');
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
