<?php

/**
 * Encerra corridas abertas e libera motoristas (uso operacional / testes).
 * Rodar na VPS: php scripts/cleanup-active-rides.php
 */

declare(strict_types=1);

use App\Models\Driver;
use App\Models\Ride;
use Illuminate\Support\Facades\Redis;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$activeStatuses = [
    'searching',
    'accepted',
    'driver_arrived',
    'in_progress',
    'awaiting_payment',
    'no_drivers_available',
];

$rides = Ride::query()->whereIn('status', $activeStatuses)->orderBy('requested_at')->get();

echo 'Corridas abertas encontradas: ' . $rides->count() . PHP_EOL;

foreach ($rides as $ride) {
    $from = $ride->status;

    if ($from === 'awaiting_payment') {
        $ride->update([
            'status' => 'completed',
            'payment_status' => 'paid',
            'completed_at' => now(),
            'canceled_reason' => null,
        ]);
        echo "  {$ride->id}: {$from} -> completed (paid)" . PHP_EOL;
        continue;
    }

    $to = $from === 'in_progress' ? 'canceled_by_driver' : 'canceled_by_passenger';

    $ride->update([
        'status' => $to,
        'canceled_reason' => 'Limpeza operacional para testes',
    ]);

    echo "  {$ride->id}: {$from} -> {$to}" . PHP_EOL;
}

$driverIds = $rides->pluck('driver_id')->filter()->unique();

foreach ($driverIds as $driverId) {
    $driver = Driver::query()->with('user')->find($driverId);
    if (! $driver?->user_id) {
        continue;
    }
    Redis::set("driver:{$driver->user_id}:status", 'available');
    Redis::del("driver:{$driver->user_id}:pending_offer");
}

$onlineBefore = Driver::query()->where('online', true)->count();
Driver::query()->where('online', true)->update(['online' => false]);

foreach (Driver::query()->with('user')->get() as $driver) {
    if ($driver->user_id) {
        Redis::del("driver:{$driver->user_id}:pending_offer");
    }
}

echo 'Motoristas online antes: ' . $onlineBefore . PHP_EOL;
echo 'Motoristas online agora: ' . Driver::query()->where('online', true)->count() . PHP_EOL;

$remaining = Ride::query()->whereIn('status', $activeStatuses)->count();
echo 'Corridas abertas restantes: ' . $remaining . PHP_EOL;

echo $remaining === 0 ? "CLEANUP OK\n" : "CLEANUP INCOMPLETE\n";
