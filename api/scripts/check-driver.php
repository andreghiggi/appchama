<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phoneFilter = $argv[1] ?? '';

$query = App\Models\User::query()->where('role', 'driver')->with('driver');

if ($phoneFilter !== '') {
    $query->where('phone', 'like', '%' . $phoneFilter . '%');
}

foreach ($query->get() as $user) {
    $driver = $user->driver;
    echo implode('|', [
        $user->name,
        $user->phone,
        'code=' . ($driver?->registration_code ?? '-'),
        'confirmed=' . ($driver?->registration_code_confirmed_at ? 'yes' : 'no'),
        'validated=' . ($driver?->registration_validated_at ? 'yes' : 'no'),
        'bg=' . ($driver?->background_check_status ?? 'no-driver'),
    ]) . PHP_EOL;
}

if (($argv[2] ?? '') === '--delete' && $phoneFilter !== '') {
    $user = App\Models\User::query()->where('role', 'driver')->where('phone', 'like', '%' . $phoneFilter . '%')->first();
    if ($user) {
        Illuminate\Support\Facades\DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });
        echo 'Deleted: ' . $user->name . ' (' . $user->phone . ')' . PHP_EOL;
    }
}
