<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('ride.{rideId}', function (User $user, string $rideId) {
    return \App\Models\Ride::query()
        ->where('id', $rideId)
        ->where(function ($q) use ($user) {
            $q->where('passenger_id', $user->id)
                ->orWhere('driver_id', $user->id);
        })
        ->exists();
});

Broadcast::channel('driver.{driverId}', function (User $user, string $driverId) {
    return $user->id === $driverId && $user->isDriver();
});

Broadcast::channel('city.{cityId}.drivers', function (User $user) {
    return $user->isAdmin();
});
