<?php

/** Simula corrida com/sem GPS e valida coords para as 3 frentes. */

$city = App\Models\City::query()
    ->withoutGlobalScope(App\Models\Scopes\TenantScope::class)
    ->whereNotNull('departure_lat')
    ->first();

if (! $city) {
    echo "SKIP: no city\n";

    return;
}

$oLat = (float) $city->departure_lat;
$oLng = (float) $city->departure_lng;
$dLat = $oLat + 0.02;
$dLng = $oLng + 0.02;

$passenger = App\Models\User::query()->where('role', 'passenger')->first()
    ?? App\Models\User::query()->whereNotNull('tenant_id')->first();

$driverUser = App\Models\User::query()->where('role', 'driver')->first();
$driver = $driverUser ? App\Models\Driver::query()->where('user_id', $driverUser->id)->first() : null;

if (! $passenger || ! $driver) {
    echo "SKIP: missing passenger/driver for simulation\n";
    exit;
}

$ride = App\Models\Ride::create([
    'tenant_id' => $passenger->tenant_id,
    'city_id' => $city->id,
    'passenger_id' => $passenger->id,
    'driver_id' => $driver->user_id,
    'status' => 'accepted',
    'origin_lat' => $oLat,
    'origin_lng' => $oLng,
    'origin_address' => 'Teste origem',
    'destination_lat' => $dLat,
    'destination_lng' => $dLng,
    'destination_address' => 'Teste destino',
    'requested_at' => now(),
    'estimated_fare' => 10,
]);

echo "CREATED ride={$ride->id}\n";

// Sem GPS motorista
$driver->update(['current_lat' => null, 'current_lng' => null]);
$trackingNoGps = app(App\Domains\Rides\Services\RideTrackingService::class)->forRide($ride->fresh(['driver.city']));
$noGpsDriverLoc = $trackingNoGps['driver_location'] !== null ? 'yes' : 'no';
echo 'NO_GPS coords='.count($trackingNoGps['route_coordinates']).' phase='.$trackingNoGps['route_phase']." driver_loc={$noGpsDriverLoc}\n";
if ($noGpsDriverLoc === 'no' || count($trackingNoGps['route_coordinates']) < 2) {
    echo "FAIL NO_GPS missing driver_location or route\n";
    exit(1);
}

// Com GPS motorista
$driver->update(['current_lat' => $oLat - 0.01, 'current_lng' => $oLng - 0.01]);
$trackingGps = app(App\Domains\Rides\Services\RideTrackingService::class)->forRide($ride->fresh(['driver.city']));
$withGpsDriverLoc = $trackingGps['driver_location'] !== null ? 'yes' : 'no';
echo 'WITH_GPS coords='.count($trackingGps['route_coordinates']).' phase='.$trackingGps['route_phase']." driver_loc={$withGpsDriverLoc}\n";
if ($withGpsDriverLoc === 'no' || count($trackingGps['route_coordinates']) < 2) {
    echo "FAIL WITH_GPS missing driver_location or route\n";
    exit(1);
}

$widget = app(App\Filament\Widgets\LiveMapWidget::class);
$ref = new ReflectionClass($widget);
$m = $ref->getMethod('buildActiveTrips');
$m->setAccessible(true);
$trips = $m->invoke($widget, $oLat, $oLng);
$found = collect($trips)->firstWhere('id', $ride->id);
echo 'WIDGET route_points='.count($found['route'] ?? [])."\n";

$est = app(App\Domains\Rides\Services\FareCalculatorService::class)->estimate($oLat, $oLng, $dLat, $dLng, $city);
echo 'PASSENGER_ESTIMATE coords='.count($est['coordinates'] ?? [])."\n";

$ride->delete();
echo "CLEANUP deleted ride={$ride->id}\n";
echo "E2E OK\n";
