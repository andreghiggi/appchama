<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Ride $ride,
        public float $lat,
        public float $lng,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('ride.'.$this->ride->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'DriverLocationUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'ride_id' => $this->ride->id,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
