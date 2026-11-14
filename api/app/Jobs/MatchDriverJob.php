<?php

namespace App\Jobs;

use App\Domains\Drivers\Services\DriverLocationService;
use App\Domains\Rides\Services\RideOfferService;
use App\Events\RideOfferReceived;
use App\Events\RideStatusChanged;
use App\Models\Ride;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Oferta a corrida a um motorista por vez sem bloquear o worker: cada execução
 * entrega uma oferta e reagenda a si mesma para o fim da janela. O $offerSeq
 * descarta execuções agendadas que foram superadas por uma recusa imediata.
 */
class MatchDriverJob implements ShouldQueue
{
    use Queueable;

    public const OFFER_WINDOW_SECONDS = 45;

    private const MAX_SEARCH_SECONDS = 180;

    private const RETRY_SECONDS = 8;

    private const STATE_TTL = 600;

    public function __construct(public string $rideId, public int $offerSeq = 0)
    {
        $this->onQueue('appchama');
    }

    public function handle(DriverLocationService $locationService, RideOfferService $offerService): void
    {
        $ride = Ride::query()->find($this->rideId);

        if (! $ride) {
            return;
        }

        if ($this->offerSeq > 0 && (int) Cache::get($this->seqKey(), 0) !== $this->offerSeq) {
            return;
        }

        $this->releaseCurrentOffer($offerService);

        if ($ride->status !== 'searching') {
            $this->forgetState();
            $offerService->clearDeclined($this->rideId);

            return;
        }

        $startedAt = (int) Cache::remember($this->startedKey(), self::STATE_TTL, fn () => now()->timestamp);

        if (now()->timestamp - $startedAt >= self::MAX_SEARCH_SECONDS) {
            $this->giveUp($ride, $offerService);

            return;
        }

        $candidate = $this->nextCandidate($ride, $locationService, $offerService);

        if (! $candidate) {
            self::dispatch($this->rideId, $this->offerSeq)->delay(now()->addSeconds(self::RETRY_SECONDS));

            return;
        }

        $driver = $candidate['driver'];
        $seq = (int) Cache::get($this->seqKey(), 0) + 1;

        Cache::put($this->seqKey(), $seq, self::STATE_TTL);
        Cache::put($this->currentKey(), $driver->user_id, self::OFFER_WINDOW_SECONDS + 30);

        $offerService->setPending($driver->user_id, $ride, self::OFFER_WINDOW_SECONDS);
        event(new RideOfferReceived($ride, $driver->user_id, self::OFFER_WINDOW_SECONDS));

        self::dispatch($this->rideId, $seq)->delay(now()->addSeconds(self::OFFER_WINDOW_SECONDS));
    }

    /**
     * Escolhe o próximo motorista ainda não abordado nesta rodada. Ao esgotar a
     * lista, reinicia a rodada para dar nova chance a quem perdeu a janela por
     * estar com o app em segundo plano.
     */
    private function nextCandidate(
        Ride $ride,
        DriverLocationService $locationService,
        RideOfferService $offerService,
    ): ?array {
        $offered = (array) Cache::get($this->offeredKey(), []);
        $declined = $offerService->declinedBy($this->rideId);

        $pick = function () use ($ride, $locationService, $declined, &$offered): ?array {
            foreach ($locationService->findNearby($ride->city_id, (float) $ride->origin_lat, (float) $ride->origin_lng) as $candidate) {
                $driverId = $candidate['driver']->user_id;

                if (in_array($driverId, $declined, true)) {
                    continue;
                }

                if (! in_array($driverId, $offered, true)) {
                    return $candidate;
                }
            }

            return null;
        };

        $candidate = $pick();

        if (! $candidate && $offered !== []) {
            $offered = [];
            $candidate = $pick();
        }

        if (! $candidate) {
            return null;
        }

        $offered[] = $candidate['driver']->user_id;
        Cache::put($this->offeredKey(), $offered, self::STATE_TTL);

        return $candidate;
    }

    /** Zera a memória da busca (janela de 3 min, quem já foi abordado) para uma nova rodada da mesma corrida. */
    public static function resetState(string $rideId): void
    {
        foreach (['offered', 'search_started', 'offer_seq', 'current_offer'] as $parte) {
            Cache::forget("ride:{$rideId}:{$parte}");
        }
    }

    private function releaseCurrentOffer(RideOfferService $offerService): void
    {
        $current = Cache::get($this->currentKey());

        if ($current) {
            $offerService->clearPending($current);
            Cache::forget($this->currentKey());
        }
    }

    private function giveUp(Ride $ride, RideOfferService $offerService): void
    {
        $ride->refresh();

        if ($ride->status === 'searching') {
            $ride->update(['status' => 'no_drivers_available']);
            event(new RideStatusChanged($ride->fresh()));
        }

        $this->forgetState();
        $offerService->clearDeclined($this->rideId);
    }

    private function forgetState(): void
    {
        Cache::forget($this->offeredKey());
        Cache::forget($this->startedKey());
        Cache::forget($this->seqKey());
        Cache::forget($this->currentKey());
    }

    private function offeredKey(): string
    {
        return "ride:{$this->rideId}:offered";
    }

    private function startedKey(): string
    {
        return "ride:{$this->rideId}:search_started";
    }

    private function seqKey(): string
    {
        return "ride:{$this->rideId}:offer_seq";
    }

    private function currentKey(): string
    {
        return "ride:{$this->rideId}:current_offer";
    }
}
