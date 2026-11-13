<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require_once dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(\App\Domains\Maps\Services\ServiceAreaService::class);
$tenant = \App\Models\Tenant::query()->where('slug', 'chama-demo')->first();

$points = [
    'Guapore' => [-28.8436, -51.8908],
    'Encantado' => [-29.2356, -51.8711],
    'SP' => [-23.5505, -46.6333],
];

foreach ($points as $label => [$lat, $lng]) {
    $resolved = $service->resolveCityForCoordinates($lat, $lng, $tenant?->id);
    echo $label.': '.($resolved ? $resolved['city']->name : 'fora').PHP_EOL;
}

echo PHP_EOL.'Cidades habilitadas:'.PHP_EOL;
foreach (\App\Models\City::query()->where('rides_enabled', true)->get() as $city) {
    echo "- {$city->name} ({$city->departure_lat}, {$city->departure_lng})".PHP_EOL;
}
