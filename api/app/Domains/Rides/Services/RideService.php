<?php

namespace App\Domains\Rides\Services;

use App\Events\RideStatusChanged;
use App\Jobs\MatchDriverJob;
use App\Models\City;
use App\Models\Driver;
use App\Models\Rating;
use App\Models\Ride;
use App\Models\User;
use App\Domains\Drivers\Services\DriverLocationService;
use App\Domains\Payments\Services\CommissionCalculator;
use App\Domains\Payments\Services\DriverWalletService;
use App\Domains\Rides\Services\RideOfferService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RideService
{
    private const RIDE_RELATIONS = ['driver.user', 'driver.vehicles', 'passenger', 'city'];

    public function __construct(
        private FareCalculatorService $fareCalculator,
        private DriverLocationService $driverLocation,
        private RideOfferService $offerService,
        private CommissionCalculator $commissionCalculator,
        private DriverWalletService $walletService,
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

        $this->startMatching($ride);

        event(new RideStatusChanged($ride));

        return $ride->fresh(['passenger', 'city']);
    }

    /** Primeira tentativa síncrona; retentativas agendadas vão para a fila appchama. */
    private function startMatching(Ride $ride): void
    {
        try {
            MatchDriverJob::dispatchSync($ride->id);
        } catch (\Throwable $e) {
            report($e);
            MatchDriverJob::dispatch($ride->id);
        }
    }

    public function accept(Ride $ride, User $driver): Ride
    {
        abort_unless($driver->isDriver(), 403);

        $offeredRide = $this->offerService->getPending($driver->id);
        $tenant = $ride->tenant ?? $ride->load('tenant')->tenant;
        $this->walletService->assertCanAcceptRide($driver->driver, (float) $ride->estimated_fare, (float) ($tenant->platform_commission_pct ?? 15));
        abort_unless($offeredRide && $offeredRide->id === $ride->id, 422, 'Esta corrida não foi oferecida a você.');

        DB::transaction(function () use ($ride, $driver): void {
            $locked = Ride::query()->whereKey($ride->id)->lockForUpdate()->firstOrFail();

            abort_unless($locked->status === 'searching', 422, 'Corrida não está disponível para aceite.');

            $this->transition($locked, 'accepted', [
                'driver_id' => $driver->id,
                'accepted_at' => now(),
            ]);
        });

        $this->driverLocation->setStatus($driver->id, 'busy');
        $this->offerService->clearPending($driver->id);

        $driverProfile = $driver->driver;
        if ($driverProfile?->current_lat !== null && $driverProfile?->current_lng !== null) {
            $this->driverLocation->update(
                $driverProfile,
                (float) $driverProfile->current_lat,
                (float) $driverProfile->current_lng,
            );
        }

        return $ride->fresh(self::RIDE_RELATIONS);
    }

    public function decline(Ride $ride, User $driver): void
    {
        abort_unless($driver->isDriver(), 403);
        $this->offerService->clearPending($driver->id);
        $this->offerService->markDeclined($ride->id, $driver->id);

        if ($ride->status === 'searching') {
            $this->startMatching($ride);
        }
    }

    public function arrive(Ride $ride, User $driver): Ride
    {
        $this->assertDriver($ride, $driver);
        $this->transition($ride, 'driver_arrived');

        return $ride->fresh(self::RIDE_RELATIONS);
    }

    public function start(Ride $ride, User $driver): Ride
    {
        $this->assertDriver($ride, $driver);
        $this->transition($ride, 'in_progress', ['started_at' => now()]);

        return $ride->fresh(self::RIDE_RELATIONS);
    }

    public function complete(Ride $ride, User $driver): Ride
    {
        $this->assertDriver($ride, $driver);

        $ride->load('city', 'tenant');
        $locations = $ride->locations()->orderBy('recorded_at')->get();
        $final = $this->fareCalculator->calculateFromLocations(
            $ride->city,
            $locations,
            $ride->started_at,
        );

        if ($locations->count() < 2 || $final['distance_km'] < 0.1) {
            $final = [
                'distance_km' => (float) ($ride->distance_km ?? 0),
                'duration_min' => $ride->started_at ? now()->diffInMinutes($ride->started_at) : 0,
                'final_fare' => (float) ($ride->estimated_fare ?? 0),
            ];
        }

        $split = $this->commissionCalculator->split($final['final_fare'], $ride->tenant);

        $this->transition($ride, 'awaiting_payment', [
            'final_fare' => $split['gross'],
            'platform_fee_amount' => $split['platform_fee'],
            'driver_net_amount' => $split['net'],
            'distance_km' => $final['distance_km'],
            'payment_status' => 'pending',
        ]);

        $this->driverLocation->setStatus($driver->id, 'available');

        return $ride->fresh(self::RIDE_RELATIONS);
    }

    public function cancel(Ride $ride, User $user, ?string $reason = null): Ride
    {
        $status = $user->isDriver() ? 'canceled_by_driver' : 'canceled_by_passenger';

        // Valida ANTES de mexer em qualquer estado: antes, um cancelamento recusado
        // liberava o motorista no Redis e devolvia erro 500, deixando a corrida "presa" na tela.
        try {
            $this->transition($ride, $status, ['canceled_reason' => $reason]);
        } catch (InvalidArgumentException) {
            $fresh = $ride->fresh();
            if ($fresh && str_starts_with((string) $fresh->status, 'canceled')) {
                return $fresh->load(self::RIDE_RELATIONS); // já estava cancelada: resposta idempotente
            }
            abort(422, $fresh?->status === 'awaiting_payment'
                ? 'A corrida já foi finalizada e está aguardando pagamento; não dá mais para cancelar.'
                : 'Esta corrida não pode mais ser cancelada.');
        }

        if ($ride->driver_id) {
            $this->driverLocation->setStatus($ride->driver_id, 'available');
        }

        return $ride->fresh(self::RIDE_RELATIONS);
    }

    public function rate(Ride $ride, User $user, int $score, ?string $comment = null): void
    {
        if ($ride->status !== 'completed' || $ride->payment_status !== 'paid') {
            abort(422, 'Só é possível avaliar corridas pagas e concluídas.');
        }

        $ratedBy = $user->isDriver() ? 'driver' : 'passenger';

        $ride->ratings()->updateOrCreate(
            ['rated_by' => $ratedBy],
            ['score' => $score, 'comment' => $comment]
        );

        if ($ratedBy === 'passenger' && $ride->driver_id) {
            $this->recalculateDriverRating($ride->driver_id);
        }
    }

    private function recalculateDriverRating(string $driverId): void
    {
        $avg = Rating::query()
            ->where('rated_by', 'passenger')
            ->whereHas('ride', fn ($q) => $q->where('driver_id', $driverId))
            ->avg('score');

        Driver::query()
            ->where('user_id', $driverId)
            ->update(['rating_avg' => $avg !== null ? round($avg, 2) : 5.0]);
    }

    private function transition(Ride $ride, string $status, array $extra = []): void
    {
        $allowed = [
            'requested' => ['searching', 'canceled_by_passenger'],
            'searching' => ['accepted', 'no_drivers_available', 'canceled_by_passenger'],
            'accepted' => ['driver_arrived', 'canceled_by_passenger', 'canceled_by_driver'],
            'driver_arrived' => ['in_progress', 'canceled_by_passenger', 'canceled_by_driver'],
            'in_progress' => ['awaiting_payment', 'canceled_by_driver'],
            'awaiting_payment' => ['completed'],
        ];

        DB::transaction(function () use ($ride, $status, $extra, $allowed): void {
            // Relê sob lock para revalidar a transição com o status mais recente:
            // evita duas requisições concorrentes (ex: passageiro cancela no instante
            // em que o motorista inicia a corrida) aplicando transições conflitantes.
            $locked = Ride::query()->whereKey($ride->id)->lockForUpdate()->firstOrFail();

            if (! in_array($status, $allowed[$locked->status] ?? [], true)) {
                throw new InvalidArgumentException("Transição inválida: {$locked->status} -> {$status}");
            }

            $locked->update(array_merge(['status' => $status], $extra));
            event(new RideStatusChanged($locked->fresh()));
        });
    }

    private function assertDriver(Ride $ride, User $driver): void
    {
        if ($ride->driver_id !== $driver->id) {
            abort(403, 'Corrida não pertence a este motorista.');
        }
    }
}
