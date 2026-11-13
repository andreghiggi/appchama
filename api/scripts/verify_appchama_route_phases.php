<?php

/** Valida rotas por fase: motorista → embarque, depois motorista → destino. */

$tracking = app(App\Domains\Rides\Services\RideTrackingService::class);

$city = App\Models\City::query()
    ->withoutGlobalScope(App\Models\Scopes\TenantScope::class)
    ->whereNotNull('departure_lat')
    ->first();

if (! $city) {
    echo "SKIP: no city\n";
    exit;
}

$oLat = (float) $city->departure_lat;
$oLng = (float) $city->departure_lng;
$dLat = $oLat + 0.02;
$dLng = $oLng + 0.02;

$passenger = App\Models\User::query()->where('role', 'passenger')->first();
$driverUser = App\Models\User::query()->where('role', 'driver')->first();
$driver = $driverUser ? App\Models\Driver::query()->where('user_id', $driverUser->id)->first() : null;

if (! $passenger || ! $driver) {
    echo "SKIP: missing users\n";
    exit;
}

$driverLat = $oLat - 0.015;
$driverLng = $oLng - 0.012;
$driver->update(['current_lat' => $driverLat, 'current_lng' => $driverLng]);

$ride = App\Models\Ride::create([
    'tenant_id' => $passenger->tenant_id,
    'city_id' => $city->id,
    'passenger_id' => $passenger->id,
    'driver_id' => $driver->user_id,
    'status' => 'accepted',
    'origin_lat' => $oLat,
    'origin_lng' => $oLng,
    'origin_address' => 'Embarque teste',
    'destination_lat' => $dLat,
    'destination_lng' => $dLng,
    'destination_address' => 'Destino teste',
    'requested_at' => now(),
    'estimated_fare' => 10,
]);

$near = static function (array $point, float $lat, float $lng, float $maxKm = 0.5): bool {
    $dlat = ((float) $point['latitude']) - $lat;
    $dlng = ((float) $point['longitude']) - $lng;
    $km = sqrt($dlat * $dlat + $dlng * $dlng) * 111;

    return $km <= $maxKm;
};

$last = static function (array $coords): ?array {
    return $coords === [] ? null : $coords[array_key_last($coords)];
};

// Fase 1: accepted → indo ao embarque
$t1 = $tracking->forRide($ride->fresh(['driver.city']));
$route1 = $t1['route_coordinates'] ?? [];
echo 'PHASE1 status=accepted phase='.$t1['route_phase'].' points='.count($route1)."\n";

if ($t1['route_phase'] !== 'to_pickup' || count($route1) < 2) {
    echo "FAIL phase1 route\n";
    $ride->delete();
    exit(1);
}

$end1 = $last($route1);
if (! $end1 || ! $near($end1, $oLat, $oLng)) {
    echo "FAIL phase1 end not near origin\n";
    $ride->delete();
    exit(1);
}

$trip1 = $t1['trip_route_coordinates'] ?? [];
echo 'PHASE1 trip_route_points='.count($trip1)."\n";

// Fase 1b: motorista chegou → rota embarque → destino
$ride->update(['status' => 'driver_arrived']);
$t1b = $tracking->forRide($ride->fresh(['driver.city']));
echo 'PHASE1b status=driver_arrived phase='.$t1b['route_phase'].' points='.count($t1b['route_coordinates'] ?? [])."\n";
if ($t1b['route_phase'] !== 'full' || count($t1b['route_coordinates'] ?? []) < 2) {
    echo "FAIL phase1b driver_arrived should show full trip route\n";
    $ride->delete();
    exit(1);
}

// Fase 2: in_progress → indo ao destino
$ride->update(['status' => 'in_progress']);
$t2 = $tracking->forRide($ride->fresh(['driver.city']));
$route2 = $t2['route_coordinates'] ?? [];
echo 'PHASE2 status=in_progress phase='.$t2['route_phase'].' points='.count($route2)."\n";

if ($t2['route_phase'] !== 'to_destination' || count($route2) < 2) {
    echo "FAIL phase2 route\n";
    $ride->delete();
    exit(1);
}

$end2 = $last($route2);
if (! $end2 || ! $near($end2, $dLat, $dLng, 2.0)) {
    $elat = $end2['latitude'] ?? '?';
    $elng = $end2['longitude'] ?? '?';
    echo "FAIL phase2 end not near destination end=({$elat},{$elng}) dest=({$dLat},{$dLng})\n";
    $ride->delete();
    exit(1);
}

// Simula motorista se movendo: rota deve recalcular
$driver->update(['current_lat' => $oLat - 0.005, 'current_lng' => $oLng - 0.004]);
$t3 = $tracking->forRide($ride->fresh(['driver.city']));
$route3 = $t3['route_coordinates'] ?? [];
$start3 = $route3[0] ?? null;
echo 'PHASE2_MOVED points='.count($route3).' eta='.$t3['driver_eta_min']."\n";

if (! $start3 || ! $near($start3, (float) $driver->current_lat, (float) $driver->current_lng, 0.3)) {
    echo "FAIL moved route start not near driver\n";
    $ride->delete();
    exit(1);
}

$ride->delete();
echo "ROUTE_PHASES OK\n";
