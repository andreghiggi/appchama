<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require_once dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (\App\Models\City::query()->orderBy('name')->get() as $city) {
    echo sprintf(
        "%s | active=%s rides=%s lat=%s lng=%s accepts=%s\n",
        $city->name,
        $city->active ? '1' : '0',
        $city->rides_enabled ? '1' : '0',
        var_export($city->departure_lat, true),
        var_export($city->departure_lng, true),
        $city->acceptsRides() ? '1' : '0',
    );
}
