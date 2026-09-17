<?php

namespace App\Domains\Rides\Services;

use App\Models\Ride;
use Illuminate\Support\Facades\Cache;

class RideOfferService
{
    private function key(string $driverUserId): string
    {
        return "driver:{$driverUserId}:pending_offer";
    }

    public function setPending(string $driverUserId, Ride $ride, int $ttlSeconds = 45): void
    {
        Cache::put($this->key($driverUserId), [
            'ride_id' => $ride->id,
            'expires_at' => now()->timestamp + $ttlSeconds,
        ], $ttlSeconds);
    }

    public function getPending(string $driverUserId): ?Ride
    {
        return $this->getPendingOffer($driverUserId)['ride'] ?? null;
    }

    /**
     * @return array{ride: Ride, expires_in: int}|null
     */
    public function getPendingOffer(string $driverUserId): ?array
    {
        $payload = Cache::get($this->key($driverUserId));

        if (! $payload) {
            return null;
        }

        // Compatibilidade com ofertas gravadas no formato antigo (só o id).
        $rideId = is_array($payload) ? ($payload['ride_id'] ?? null) : $payload;
        $expiresAt = is_array($payload) ? ($payload['expires_at'] ?? null) : null;

        if (! $rideId) {
            return null;
        }

        $ride = Ride::query()
            ->with(['passenger:id,name,phone', 'city'])
            ->find($rideId);

        if (! $ride || $ride->status !== 'searching') {
            $this->clearPending($driverUserId);

            return null;
        }

        $expiresIn = $expiresAt ? max(0, $expiresAt - now()->timestamp) : 45;

        return ['ride' => $ride, 'expires_in' => (int) $expiresIn];
    }

    public function clearPending(string $driverUserId): void
    {
        Cache::forget($this->key($driverUserId));
    }

    private function declinedKey(string $rideId): string
    {
        return "ride:{$rideId}:declined";
    }

    /**
     * Quem recusou explicitamente não recebe a mesma corrida de novo, ao
     * contrário de quem apenas deixou a janela expirar sem responder.
     */
    public function markDeclined(string $rideId, string $driverUserId): void
    {
        $declined = (array) Cache::get($this->declinedKey($rideId), []);

        if (! in_array($driverUserId, $declined, true)) {
            $declined[] = $driverUserId;
            Cache::put($this->declinedKey($rideId), $declined, 600);
        }
    }

    /**
     * @return list<string>
     */
    public function declinedBy(string $rideId): array
    {
        return array_values((array) Cache::get($this->declinedKey($rideId), []));
    }

    public function clearDeclined(string $rideId): void
    {
        Cache::forget($this->declinedKey($rideId));
    }
}
