<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = $argv[1] ?? '5554999061836';
$user = App\Models\User::query()->where('phone', $phone)->first();

if (! $user) {
    echo "User not found: {$phone}\n";
    exit(1);
}

echo "Driver: {$user->name} ({$user->id})\n\n";

$rows = App\Models\DriverWithdrawal::query()
    ->where('driver_id', $user->id)
    ->orderByDesc('created_at')
    ->limit(10)
    ->get();

foreach ($rows as $w) {
    echo implode(' | ', [
        $w->id,
        $w->status,
        $w->amount,
        $w->asaas_transfer_id ?? 'no-asaas-id',
        $w->fail_reason ?? '-',
        $w->created_at,
    ])."\n";
}

$wallet = App\Models\DriverWallet::query()->find($user->id);
if ($wallet) {
    echo "\nWallet balance_available={$wallet->balance_available} total_earnings={$wallet->total_earnings}\n";
}
