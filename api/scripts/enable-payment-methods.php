<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;

$slug = $argv[1] ?? 'chama-demo';
$tenant = Tenant::query()->where('slug', $slug)->first();

if (! $tenant) {
    echo "[FAIL] Tenant não encontrado: {$slug}\n";
    exit(1);
}

$methods = $tenant->enabledPaymentMethods();
$methods['pix'] = true;
$methods['credit_card'] = true;
$methods['direct_driver'] = true;

$tenant->update(['payment_methods_enabled' => $methods]);

echo "[OK] Métodos habilitados para {$slug}:\n";
echo json_encode($tenant->fresh()->enabledPaymentMethods(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
