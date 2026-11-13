<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orphanId = '01a05fba-83a0-71fa-8388-ca7ea25578c3';
$approvedId = '01a05fbf-03a5-72ac-b396-134cbb2c6c90';
$correctPhone = '5554999061836';

$orphan = App\Models\User::find($orphanId);
$approved = App\Models\User::with('driver')->find($approvedId);

if (! $orphan || ! $approved || ! $approved->driver) {
    echo 'ERROR: users not found or approved has no driver' . PHP_EOL;
    exit(1);
}

echo 'Orphan tokens: ' . $orphan->tokens()->count() . PHP_EOL;
echo 'Approved tokens: ' . $approved->tokens()->count() . PHP_EOL;
echo 'Orphan phone: ' . $orphan->phone . PHP_EOL;
echo 'Approved phone: ' . $approved->phone . PHP_EOL;

if (($argv[1] ?? '') !== '--fix') {
    echo 'Run with --fix to merge accounts' . PHP_EOL;
    exit(0);
}

Illuminate\Support\Facades\DB::transaction(function () use ($orphan, $approved, $correctPhone): void {
    $orphan->tokens()->delete();
    $orphan->delete();

    $approved->update(['phone' => $correctPhone]);

    echo 'Fixed: approved driver now uses phone ' . $correctPhone . PHP_EOL;
});
