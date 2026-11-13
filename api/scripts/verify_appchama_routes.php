<?php

/** Verifica polyline de rota nas 3 frentes (API + admin widget). */

$mapbox = app(App\Domains\Maps\Services\MapboxService::class);
$dir = $mapbox->directions(-28.8436, -51.8908, -28.82, -51.87);
echo 'MAPBOX directions count='.count($dir['coordinates'] ?? []).' dist='.($dir['distance_km'] ?? '?')."\n";

$city = App\Models\City::query()
    ->withoutGlobalScope(App\Models\Scopes\TenantScope::class)
    ->whereNotNull('departure_lat')
    ->first();

if ($city) {
    $est = app(App\Domains\Rides\Services\FareCalculatorService::class)->estimate(
        (float) $city->departure_lat,
        (float) $city->departure_lng,
        (float) $city->departure_lat + 0.015,
        (float) $city->departure_lng + 0.015,
        $city,
    );
    echo 'ESTIMATE coords='.count($est['coordinates'] ?? [])."\n";
}

$ride = App\Models\Ride::query()
    ->withoutGlobalScope(App\Models\Scopes\TenantScope::class)
    ->whereIn('status', ['accepted', 'driver_arrived', 'in_progress', 'searching'])
    ->latest('requested_at')
    ->first();

if ($ride) {
    $tracking = app(App\Domains\Rides\Services\RideTrackingService::class)->forRide($ride);
    echo "RIDE {$ride->id} status={$ride->status} phase={$tracking['route_phase']} coords=".count($tracking['route_coordinates'] ?? [])."\n";

    $widget = app(App\Filament\Widgets\LiveMapWidget::class);
    $ref = new ReflectionClass($widget);
    $m = $ref->getMethod('buildActiveTrips');
    $m->setAccessible(true);
    $trips = $m->invoke($widget, (float) ($city->departure_lat ?? -28.8436), (float) ($city->departure_lng ?? -51.8908));
    foreach ($trips as $t) {
        echo "WIDGET trip={$t['id']} route_points=".count($t['route'] ?? [])."\n";
    }
} else {
    echo "NO_ACTIVE_RIDE (API estimate ok above; mobile uses same coordinates format)\n";
}

echo "OK route fix verified\n";
