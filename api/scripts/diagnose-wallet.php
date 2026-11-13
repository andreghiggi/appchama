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

echo "=== DRIVER {$user->name} ({$user->id}) ===\n\n";

$wallet = App\Models\DriverWallet::query()->find($user->id);
echo "Wallet:\n";
echo '  balance_available: '.($wallet->balance_available ?? 'null')."\n";
echo '  total_earnings: '.($wallet->total_earnings ?? 'null')."\n\n";

echo "Withdrawals:\n";
$withdrawals = App\Models\DriverWithdrawal::query()
    ->where('driver_id', $user->id)
    ->orderByDesc('created_at')
    ->limit(10)
    ->get();
if ($withdrawals->isEmpty()) {
    echo "  (none)\n";
} else {
    foreach ($withdrawals as $w) {
        echo "  {$w->id} | {$w->status} | R$ {$w->amount} | asaas={$w->asaas_transfer_id} | {$w->fail_reason} | {$w->created_at}\n";
    }
}

echo "\nWallet transactions (last 15):\n";
$tx = App\Models\DriverWalletTransaction::query()
    ->where('driver_id', $user->id)
    ->orderByDesc('created_at')
    ->limit(15)
    ->get();
foreach ($tx as $t) {
    echo "  {$t->type} | R$ {$t->amount} | after={$t->balance_after} | {$t->description} | {$t->created_at}\n";
}

echo "\nRecent completed rides:\n";
$rides = App\Models\Ride::query()
    ->where('driver_id', $user->id)
    ->whereIn('status', ['completed', 'awaiting_payment'])
    ->orderByDesc('updated_at')
    ->limit(10)
    ->get(['id', 'status', 'payment_status', 'payment_method', 'final_fare', 'driver_net_amount', 'updated_at']);
foreach ($rides as $r) {
    $credited = App\Models\DriverWalletTransaction::query()
        ->where('ride_id', $r->id)
        ->where('type', 'ride_credit')
        ->exists();
    echo "  {$r->id} | {$r->status} | pay={$r->payment_status} | method={$r->payment_method} | net={$r->driver_net_amount} | credited=".($credited ? 'yes' : 'NO')." | {$r->updated_at}\n";
}

$driver = $user->driver;
if ($driver) {
    echo "\nPayout Pix: ".($driver->payout_pix_key ? 'set ('.$driver->payout_pix_key_type.')' : 'NOT SET')."\n";
}
