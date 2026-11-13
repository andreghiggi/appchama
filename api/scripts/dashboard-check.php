<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$drivers = App\Models\Driver::query()
    ->with(['user:id,name', 'city:id,name,departure_lat,departure_lng'])
    ->where('online', true)
    ->get();

foreach ($drivers as $driver) {
    $lat = $driver->current_lat ?? $driver->departure_lat ?? $driver->city?->departure_lat;
    $lng = $driver->current_lng ?? $driver->departure_lng ?? $driver->city?->departure_lng;
    echo $driver->user?->name . '|online=' . ($driver->online ? '1' : '0') . '|lat=' . ($lat ?? 'null') . '|lng=' . ($lng ?? 'null') . PHP_EOL;
}

$rides = App\Models\Ride::query()
    ->withoutGlobalScopes()
    ->whereIn('status', ['searching', 'accepted', 'driver_arrived', 'in_progress'])
    ->get(['id', 'status', 'origin_lat', 'origin_lng']);

foreach ($rides as $ride) {
    echo 'ride=' . $ride->id . '|' . $ride->status . '|' . $ride->origin_lat . ',' . $ride->origin_lng . PHP_EOL;
}
