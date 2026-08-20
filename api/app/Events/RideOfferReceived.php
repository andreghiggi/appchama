<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideOfferReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Ride $ride,
        public string $driverId,
        public int $timeoutSeconds = 15,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('driver.'.$this->driverId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'RideOfferReceived';
    }

    public function broadcastWith(): array
    {
        return [
            'ride' => $this->ride->load(['passenger:id,name,phone,rating_avg', 'city']),
            'timeout_seconds' => $this->timeoutSeconds,
        ];
    }
}
