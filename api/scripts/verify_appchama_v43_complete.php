<?php

/** Verificação completa v4.3 — bugs corrigidos nos mapas. */

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
// Embarque ligeiramente afastado do centro da cidade para distinguir fallback vs origem.
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

// --- 1. in_progress sem GPS: rota parte do fallback (cidade), não origem ---
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
} elseif (! $start1 || $near($start1, $oLat, $oLng, 0.3)) {
    $fail('in_progress_no_gps_route_start', 'route should start at city fallback, not pickup origin');
} elseif (! $start1 || ! $near($start1, $cityLat, $cityLng, 1.0)) {
    $fail('in_progress_no_gps_route_start_city', 'route start not near city fallback');
} elseif (! $driverLoc1 || ! $near($driverLoc1, $cityLat, $cityLng, 1.0)) {
    $fail('in_progress_no_gps_driver_loc');
} else {
    $pass('in_progress_no_gps_route_from_fallback');
}
$ride1->delete();

// --- 2. driver_arrived: ETA/distância nulos ---
$driver->update(['current_lat' => $oLat, 'current_lng' => $oLng]);
$ride2 = App\Models\Ride::create([
    'tenant_id' => $passenger->tenant_id,
    'city_id' => $city->id,
    'passenger_id' => $passenger->id,
    'driver_id' => $driver->user_id,
    'status' => 'driver_arrived',
    'origin_lat' => $oLat,
    'origin_lng' => $oLng,
    'origin_address' => 'Embarque',
    'destination_lat' => $dLat,
    'destination_lng' => $dLng,
    'destination_address' => 'Destino',
    'requested_at' => now(),
    'estimated_fare' => 10,
]);
$t2 = $tracking->forRide($ride2->fresh(['driver.city']));
if ($t2['route_phase'] !== 'full') {
    $fail('driver_arrived_phase');
} elseif ($t2['driver_eta_min'] !== null || $t2['driver_distance_km'] !== null) {
    $fail('driver_arrived_eta_null', 'eta=' . json_encode($t2['driver_eta_min']));
} elseif (count($t2['route_coordinates'] ?? []) < 2) {
    $fail('driver_arrived_route');
} else {
    $pass('driver_arrived_null_eta_with_full_route');
}
$ride2->delete();

// --- 3. awaiting_payment: tracking com rota ---
$ride3 = App\Models\Ride::create([
    'tenant_id' => $passenger->tenant_id,
    'city_id' => $city->id,
    'passenger_id' => $passenger->id,
    'driver_id' => $driver->user_id,
    'status' => 'awaiting_payment',
    'origin_lat' => $oLat,
    'origin_lng' => $oLng,
    'origin_address' => 'Embarque',
    'destination_lat' => $dLat,
    'destination_lng' => $dLng,
    'destination_address' => 'Destino',
    'requested_at' => now(),
    'estimated_fare' => 10,
]);
$t3 = $tracking->forRide($ride3->fresh(['driver.city']));
if ($t3['route_phase'] !== 'full') {
    $fail('awaiting_payment_phase');
} elseif (count($t3['route_coordinates'] ?? []) < 2) {
    $fail('awaiting_payment_route');
} elseif ($t3['driver_eta_min'] !== null) {
    $fail('awaiting_payment_eta_null');
} else {
    $pass('awaiting_payment_tracking');
}
$ride3->delete();

// --- 4. mergeIntoOffer: driver_location + trip_route ---
$ride4 = App\Models\Ride::create([
    'tenant_id' => $passenger->tenant_id,
    'city_id' => $city->id,
    'passenger_id' => $passenger->id,
    'status' => 'searching',
    'origin_lat' => $oLat,
    'origin_lng' => $oLng,
    'origin_address' => 'Embarque',
    'destination_lat' => $dLat,
    'destination_lng' => $dLng,
    'destination_address' => 'Destino',
    'requested_at' => now(),
    'estimated_fare' => 10,
]);
$driver->update(['current_lat' => $oLat - 0.01, 'current_lng' => $oLng - 0.01]);
$offer = $tracking->mergeIntoOffer($ride4->fresh(), $driver->fresh(['city']));
if (! isset($offer['driver_location']['latitude'])) {
    $fail('mergeIntoOffer_driver_location');
} elseif (count($offer['route_coordinates'] ?? []) < 2) {
    $fail('mergeIntoOffer_route');
} elseif (count($offer['trip_route_coordinates'] ?? []) < 2) {
    $fail('mergeIntoOffer_trip_route');
} elseif ($offer['route_phase'] !== 'to_pickup') {
    $fail('mergeIntoOffer_phase');
} elseif (! isset($offer['passenger']['name'])) {
    $fail('mergeIntoOffer_relations');
} else {
    $pass('mergeIntoOffer_complete');
}
$ride4->delete();

// --- 5. accepted com GPS: rota termina no embarque ---
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

// --- 6. mergeIntoRide em cancel/show pattern ---
$ride6 = App\Models\Ride::create([
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
$merged = $tracking->mergeIntoRide($ride6->fresh());
if (! isset($merged['route_coordinates']) || ! isset($merged['driver_location'])) {
    $fail('mergeIntoRide_fields');
} else {
    $pass('mergeIntoRide_tracking_fields');
}
$ride6->delete();

// --- 7. Admin blade: botão Seguir deployado ---
$bladePath = resource_path('views/filament/widgets/live-map.blade.php');
$blade = is_readable($bladePath) ? file_get_contents($bladePath) : '';
if (! str_contains($blade, 'chama-admin-follow-btn') || ! str_contains($blade, 'fitMapToPoints')) {
    $fail('admin_follow_button_in_blade');
} else {
    $pass('admin_follow_button_in_blade');
}

echo "\nSUMMARY checks={$checks} failures=" . count($failures) . "\n";
if ($failures !== []) {
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}

echo "V43_COMPLETE OK\n";
