<?php

namespace App\Jobs;

use App\Domains\Drivers\Services\DriverLocationService;
use App\Events\RideOfferReceived;
use App\Events\RideStatusChanged;
use App\Models\Ride;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class MatchDriverJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $rideId) {}

    public function handle(DriverLocationService $locationService): void
    {
        $ride = Ride::query()->find($this->rideId);

        if (! $ride || $ride->status !== 'searching') {
            return;
        }

        $candidates = $locationService->findNearby(
            $ride->city_id,
            (float) $ride->origin_lat,
            (float) $ride->origin_lng,
        );

        if (empty($candidates)) {
            $ride->update(['status' => 'no_drivers_available']);
            event(new RideStatusChanged($ride->fresh()));

            return;
        }

        foreach ($candidates as $candidate) {
            $driver = $candidate['driver'];
            $lockKey = "ride:{$ride->id}:offer:{$driver->user_id}";

            if (! Cache::add($lockKey, true, 20)) {
                continue;
            }

            event(new RideOfferReceived($ride, $driver->user_id));

            $accepted = $this->waitForResponse($ride, $driver->user_id, 15);

            if ($accepted) {
                return;
            }
        }

        $ride->refresh();
        if ($ride->status === 'searching') {
            $ride->update(['status' => 'no_drivers_available']);
            event(new RideStatusChanged($ride->fresh()));
        }
    }

    private function waitForResponse(Ride $ride, string $driverId, int $seconds): bool
    {
        $deadline = now()->addSeconds($seconds);

        while (now()->lt($deadline)) {
            $ride->refresh();

            if ($ride->status === 'accepted' && $ride->driver_id === $driverId) {
                return true;
            }

            if ($ride->status !== 'searching') {
                return false;
            }

            usleep(500000);
        }

        return false;
    }
}
