<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Ride $ride) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('ride.'.$this->ride->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'RideStatusChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'ride' => $this->ride->load(['passenger:id,name,phone', 'driver.user:id,name,phone', 'city']),
        ];
    }
}
