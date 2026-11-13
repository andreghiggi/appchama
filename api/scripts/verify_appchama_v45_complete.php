<?php

/** Verificação completa v4.5 — rota visível + fit por fase + accepted sem driver. */

$tracking = app(App\Domains\Rides\Services\RideTrackingService::class);
$failures = [];
$checks = 0;

$pass = static function (string $name) use (&$checks): void {
    $checks++;
    echo "OK {$name}\n";
};

$fail = static function (string $name, string $detail = '') use (&$failures, &$checks): void {
    $checks++;
    $msg = "FAIL {$name}" . ($detail !== '' ? " — {$detail}" : '');
    echo "{$msg}\n";
    $failures[] = $msg;
};

$city = App\Models\City::query()
    ->withoutGlobalScope(App\Models\Scopes\TenantScope::class)
    ->whereNotNull('departure_lat')
    ->first();

$passenger = App\Models\User::query()->where('role', 'passenger')->first();
$driverUser = App\Models\User::query()->where('role', 'driver')->first();
$driver = $driverUser ? App\Models\Driver::query()->with('city:id,departure_lat,departure_lng')->where('user_id', $driverUser->id)->first() : null;

if (! $city || ! $passenger || ! $driver) {
    echo "SKIP missing fixtures\n";
    exit(1);
}

$cityLat = (float) ($driver->city?->departure_lat ?? $city->departure_lat);
$cityLng = (float) ($driver->city?->departure_lng ?? $city->departure_lng);
$oLat = $cityLat + 0.005;
$oLng = $cityLng + 0.005;
$dLat = $oLat + 0.02;
$dLng = $oLng + 0.02;

$near = static function (array $point, float $lat, float $lng, float $maxKm = 0.8): bool {
    $dlat = ((float) $point['latitude']) - $lat;
    $dlng = ((float) $point['longitude']) - $lng;

    return sqrt($dlat * $dlat + $dlng * $dlng) * 111 <= $maxKm;
};

$first = static fn (array $coords): ?array => $coords === [] ? null : $coords[0];
$last = static fn (array $coords): ?array => $coords === [] ? null : $coords[array_key_last($coords)];

// --- 1. accepted sem registro Driver: to_pickup (v4.5) ---
$ride0 = App\Models\Ride::create([
    'tenant_id' => $passenger->tenant_id,
    'city_id' => $city->id,
    'passenger_id' => $passenger->id,
    'driver_id' => $passenger->id,
    'status' => 'accepted',
    'origin_lat' => $oLat,
    'origin_lng' => $oLng,
    'origin_address' => 'Embarque',
    'destination_lat' => $dLat,
    'destination_lng' => $dLng,
    'destination_address' => 'Destino',
    'requested_at' => now(),
    'estimated_fare' => 10,
]);
$t0 = $tracking->forRide($ride0->fresh(['city']));
$end0 = $last($t0['route_coordinates'] ?? []);
if ($t0['route_phase'] !== 'to_pickup') {
    $fail('accepted_no_driver_phase', $t0['route_phase'] ?? 'null');
} elseif (count($t0['route_coordinates'] ?? []) < 2) {
    $fail('accepted_no_driver_route');
} elseif (! $end0 || ! $near($end0, $oLat, $oLng)) {
    $fail('accepted_no_driver_route_end');
} else {
    $pass('accepted_no_driver_to_pickup');
}
$ride0->delete();

// --- 2. in_progress sem GPS parte do embarque ---
$driver->update(['current_lat' => null, 'current_lng' => null]);
Illuminate\Support\Facades\Redis::zrem("geo:city:{$driver->city_id}", $driver->user_id);
$ride1 = App\Models\Ride::create([
    'tenant_id' => $passenger->tenant_id,
    'city_id' => $city->id,
    'passenger_id' => $passenger->id,
    'driver_id' => $driver->user_id,
    'status' => 'in_progress',
    'origin_lat' => $oLat,
    'origin_lng' => $oLng,
    'origin_address' => 'Embarque',
    'destination_lat' => $dLat,
    'destination_lng' => $dLng,
    'destination_address' => 'Destino',
    'requested_at' => now(),
    'estimated_fare' => 10,
]);
$t1 = $tracking->forRide($ride1->fresh(['driver.city']));
$route1 = $t1['route_coordinates'] ?? [];
$start1 = $first($route1);
$driverLoc1 = $t1['driver_location'] ?? null;

if ($t1['route_phase'] !== 'to_destination') {
    $fail('in_progress_no_gps_phase', $t1['route_phase'] ?? 'null');
} elseif (count($route1) < 2) {
    $fail('in_progress_no_gps_route');
} elseif (! $start1 || ! $near($start1, $oLat, $oLng, 0.5)) {
    $fail('in_progress_no_gps_route_start', 'route should start at pickup origin');
} elseif (! $driverLoc1 || ! $near($driverLoc1, $oLat, $oLng, 0.5)) {
    $fail('in_progress_no_gps_driver_loc', 'driver_location should be pickup origin');
} else {
    $pass('in_progress_no_gps_route_from_pickup');
}
$ride1->delete();

// --- 3. accepted com driver: to_pickup ---
$driver->update(['current_lat' => $oLat - 0.015, 'current_lng' => $oLng - 0.012]);
$ride5 = App\Models\Ride::create([
    'tenant_id' => $passenger->tenant_id,
    'city_id' => $city->id,
    'passenger_id' => $passenger->id,
    'driver_id' => $driver->user_id,
    'status' => 'accepted',
    'origin_lat' => $oLat,
    'origin_lng' => $oLng,
    'origin_address' => 'Embarque',
    'destination_lat' => $dLat,
    'destination_lng' => $dLng,
    'destination_address' => 'Destino',
    'requested_at' => now(),
    'estimated_fare' => 10,
]);
$t5 = $tracking->forRide($ride5->fresh(['driver.city']));
$end5 = $last($t5['route_coordinates'] ?? []);
if ($t5['route_phase'] !== 'to_pickup' || ! $end5 || ! $near($end5, $oLat, $oLng)) {
    $fail('accepted_to_pickup_end');
} elseif (count($t5['trip_route_coordinates'] ?? []) < 2) {
    $fail('accepted_trip_route');
} else {
    $pass('accepted_to_pickup_with_trip_route');
}
$ride5->delete();

// --- 4. in_progress com GPS: rota termina no destino ---
$driver->update(['current_lat' => $oLat - 0.008, 'current_lng' => $oLng - 0.006]);
$ride6 = App\Models\Ride::create([
    'tenant_id' => $passenger->tenant_id,
    'city_id' => $city->id,
    'passenger_id' => $passenger->id,
    'driver_id' => $driver->user_id,
    'status' => 'in_progress',
    'origin_lat' => $oLat,
    'origin_lng' => $oLng,
    'origin_address' => 'Embarque',
    'destination_lat' => $dLat,
    'destination_lng' => $dLng,
    'destination_address' => 'Destino',
    'requested_at' => now(),
    'estimated_fare' => 10,
]);
$t6 = $tracking->forRide($ride6->fresh(['driver.city']));
$end6 = $last($t6['route_coordinates'] ?? []);
if ($t6['route_phase'] !== 'to_destination' || count($t6['route_coordinates'] ?? []) < 2) {
    $fail('in_progress_gps_route');
} elseif (! $end6 || ! $near($end6, $dLat, $dLng, 2.0)) {
    $fail('in_progress_gps_route_end');
} else {
    $pass('in_progress_gps_to_destination');
}
$ride6->delete();

echo "\nSUMMARY checks={$checks} failures=" . count($failures) . "\n";
if ($failures !== []) {
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}

echo "V45_COMPLETE OK\n";
