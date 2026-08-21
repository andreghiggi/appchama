<?php

namespace App\Domains\Rides\Services;

use App\Events\RideStatusChanged;
use App\Jobs\MatchDriverJob;
use App\Models\City;
use App\Models\Ride;
use App\Models\User;
use App\Domains\Drivers\Services\DriverLocationService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RideService
{
    public function __construct(
        private FareCalculatorService $fareCalculator,
        private DriverLocationService $driverLocation,
    ) {}

    public function request(User $passenger, array $data): Ride
    {
        $city = City::query()
            ->where('id', $data['city_id'])
            ->where('tenant_id', $passenger->tenant_id)
            ->firstOrFail();

        $estimate = $this->fareCalculator->estimate(
            $data['origin_lat'],
            $data['origin_lng'],
            $data['destination_lat'],
            $data['destination_lng'],
            $city,
        );

        $ride = Ride::query()->create([
            'tenant_id' => $passenger->tenant_id,
            'city_id' => $city->id,
            'passenger_id' => $passenger->id,
            'status' => 'searching',
            'origin_lat' => $data['origin_lat'],
            'origin_lng' => $data['origin_lng'],
            'origin_address' => $data['origin_address'] ?? null,
            'destination_lat' => $data['destination_lat'],
            'destination_lng' => $data['destination_lng'],
            'destination_address' => $data['destination_address'] ?? null,
            'estimated_fare' => $estimate['estimated_fare'],
            'distance_km' => $estimate['distance_km'],
            'requested_at' => now(),
        ]);

        MatchDriverJob::dispatch($ride->id);

        event(new RideStatusChanged($ride));

        return $ride->fresh(['passenger', 'city']);
    }

    public function accept(Ride $ride, User $driver): Ride
    {
        abort_unless($driver->isDriver(), 403);
        abort_unless($ride->status === 'searching', 422, 'Corrida não está disponível para aceite.');

        $this->transition($ride, 'accepted', [
            'driver_id' => $driver->id,
            'accepted_at' => now(),
        ]);
        $this->driverLocation->setStatus($driver->id, 'busy');

        return $ride->fresh(['driver.user', 'passenger']);
    }

    public function decline(Ride $ride, User $driver): void
    {
        abort_unless($driver->isDriver(), 403);
        // Matching continua para o próximo candidato enquanto status = searching
    }

    public function arrive(Ride $ride, User $driver): Ride
    {
        $this->assertDriver($ride, $driver);
        $this->transition($ride, 'driver_arrived');

        return $ride->fresh();
    }

    public function start(Ride $ride, User $driver): Ride
    {
        $this->assertDriver($ride, $driver);
        $this->transition($ride, 'in_progress', ['started_at' => now()]);

        return $ride->fresh();
    }

    public function complete(Ride $ride, User $driver): Ride
    {
        $this->assertDriver($ride, $driver);

        $locations = $ride->locations()->orderBy('recorded_at')->get();
        $final = $this->fareCalculator->calculateFromLocations(
            $ride->city,
            $locations,
            $ride->started_at,
        );

        $this->transition($ride, 'completed', [
            'completed_at' => now(),
            'final_fare' => $final['final_fare'],
            'distance_km' => $final['distance_km'],
        ]);

        $this->driverLocation->setStatus($driver->id, 'available');

        return $ride->fresh();
    }

    public function cancel(Ride $ride, User $user, ?string $reason = null): Ride
    {
        $status = $user->isDriver() ? 'canceled_by_driver' : 'canceled_by_passenger';

        if ($ride->driver_id) {
            $this->driverLocation->setStatus($ride->driver_id, 'available');
        }

        $this->transition($ride, $status, ['canceled_reason' => $reason]);

        return $ride->fresh();
    }

    public function rate(Ride $ride, User $user, int $score, ?string $comment = null): void
    {
        if ($ride->status !== 'completed') {
            abort(422, 'Só é possível avaliar corridas concluídas.');
        }

        $ratedBy = $user->isDriver() ? 'driver' : 'passenger';

        $ride->ratings()->updateOrCreate(
            ['rated_by' => $ratedBy],
            ['score' => $score, 'comment' => $comment]
        );
    }

    private function transition(Ride $ride, string $status, array $extra = []): void
    {
        $allowed = [
            'requested' => ['searching', 'canceled_by_passenger'],
            'searching' => ['accepted', 'no_drivers_available', 'canceled_by_passenger'],
            'accepted' => ['driver_arrived', 'canceled_by_passenger', 'canceled_by_driver'],
            'driver_arrived' => ['in_progress', 'canceled_by_passenger', 'canceled_by_driver'],
            'in_progress' => ['completed', 'canceled_by_driver'],
        ];

        if (! in_array($status, $allowed[$ride->status] ?? [], true)) {
            throw new InvalidArgumentException("Transição inválida: {$ride->status} -> {$status}");
        }

        DB::transaction(function () use ($ride, $status, $extra): void {
            $ride->update(array_merge(['status' => $status], $extra));
            event(new RideStatusChanged($ride->fresh()));
        });
    }

    private function assertDriver(Ride $ride, User $driver): void
    {
        if ($ride->driver_id !== $driver->id) {
            abort(403, 'Corrida não pertence a este motorista.');
        }
    }
}
