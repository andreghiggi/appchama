<?php

namespace App\Domains\Rides\Services;

use App\Domains\Drivers\Services\DriverLocationService;
use App\Models\Driver;
use App\Models\Ride;

/**
 * Corrida encadeada: motorista com passageiro no carro que já fez pelo menos 20% do trajeto pode receber e aceitar
 * a próxima corrida (uma só). O embarque da nova precisa ser perto do destino da atual.
 */
class RideChainService
{
    public function enabled(): bool
    {
        return (bool) config('rides.chain.enabled', true);
    }

    /** Fração já percorrida da corrida (0 a 1), em linha reta, pela posição atual do motorista; null se não dá para saber. */
    public function progress(Ride $ride, Driver $driver): ?float
    {
        if ($ride->destination_lat === null || $driver->current_lat === null) {
            return null;
        }
        $total = $this->km((float) $ride->origin_lat, (float) $ride->origin_lng, (float) $ride->destination_lat, (float) $ride->destination_lng);
        if ($total < 0.2) {
            return null; // trajeto curto demais para medir
        }
        $falta = $this->km((float) $driver->current_lat, (float) $driver->current_lng, (float) $ride->destination_lat, (float) $ride->destination_lng);

        return max(0.0, min(1.0, 1 - ($falta / $total)));
    }

    /**
     * A corrida em andamento que o motorista pode "encadear": uma única corrida em andamento, nenhuma já na fila e
     * pelo menos o mínimo do trajeto concluído. Null quando ele não pode receber outra agora.
     */
    public function eligibleRide(Driver $driver): ?Ride
    {
        if (! $this->enabled()) {
            return null;
        }
        $rides = Ride::query()->where('driver_id', $driver->user_id)->whereIn('status', ['accepted', 'driver_arrived', 'in_progress'])->get();
        if ($rides->count() !== 1 || $rides->first()->status !== 'in_progress') {
            return null;
        }
        $a = $rides->first();
        $p = $this->progress($a, $driver);

        return $p !== null && $p >= (float) config('rides.chain.min_progress', 0.20) ? $a : null;
    }

    /**
     * Motoristas ocupados que podem receber esta corrida como a próxima, do mais perto ao mais longe.
     *
     * @return list<array{driver: Driver, distance_km: float, chained_after: string}>
     */
    public function candidatesFor(string $cityId, float $pickupLat, float $pickupLng): array
    {
        if (! $this->enabled()) {
            return [];
        }
        $max = (float) config('rides.chain.max_pickup_km', 5.0);
        $out = [];

        $drivers = Driver::query()->with('user')->where('city_id', $cityId)->where('online', true)
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))->get();

        foreach ($drivers as $driver) {
            if (! $driver->canReceiveRides()) {
                continue;
            }
            $a = $this->eligibleRide($driver);
            if (! $a) {
                continue;
            }
            $dist = $this->km((float) $a->destination_lat, (float) $a->destination_lng, $pickupLat, $pickupLng);
            if ($dist <= $max) {
                $out[] = ['driver' => $driver, 'distance_km' => round($dist, 2), 'chained_after' => $a->id];
            }
        }
        usort($out, fn ($x, $y) => $x['distance_km'] <=> $y['distance_km']);

        return $out;
    }

    /** Libera o motorista para novas corridas só se ele não tiver mais nenhuma ativa ou na fila. */
    public function releaseIfFree(string $driverUserId, DriverLocationService $location): void
    {
        $temAtiva = Ride::query()->where('driver_id', $driverUserId)->whereIn('status', ['accepted', 'driver_arrived', 'in_progress'])->exists();
        $location->setStatus($driverUserId, $temAtiva ? 'busy' : 'available');
    }

    private function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
