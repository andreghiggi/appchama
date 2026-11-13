<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require_once dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domains\Maps\Services\CityDepartureCoordinateService;
use App\Models\City;

$tenant = \App\Models\Tenant::query()->where('slug', 'chama-demo')->firstOrFail();

$city = City::query()
    ->where('tenant_id', $tenant->id)
    ->where('name', 'Encantado')
    ->first();

if (! $city) {
    $city = City::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Encantado',
        'state' => 'RS',
        'base_fare' => 5,
        'price_per_km' => 0.9,
        'price_per_min' => 0.5,
        'min_fare' => 8,
        'active' => true,
        'departure_address' => 'Centro Encantado',
        'departure_lat' => -29.2356,
        'departure_lng' => -51.8711,
        'rides_enabled' => true,
    ]);
    echo "CREATED\n";
    exit(0);
}

$city->rides_enabled = true;
$result = app(CityDepartureCoordinateService::class)->normalizeCity($city, autoCorrect: true);
$city->save();

echo $result['corrected'] ? "NORMALIZED\n" : "OK\n";
