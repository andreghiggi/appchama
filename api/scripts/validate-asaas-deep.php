<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domains\Payments\Services\AsaasCustomerService;
use App\Domains\Payments\Services\CommissionCalculator;
use App\Models\Ride;
use App\Models\Tenant;
use App\Models\User;

$tenant = Tenant::query()->where('slug', 'chama-demo')->firstOrFail();

echo "=== Comissão (20%) ===\n";
$split = app(CommissionCalculator::class)->split(50.00, $tenant);
echo "Bruto R$ 50,00 -> taxa R$ {$split['platform_fee']} -> líquido motorista R$ {$split['net']}\n";
echo ($split['platform_fee'] == 10.0 && $split['net'] == 40.0 ? '[OK]' : '[FAIL]')." cálculo comissão\n\n";

echo "=== Cliente Asaas (passageiro seed) ===\n";
$passenger = User::query()->where('phone', '5511999990002')->where('tenant_id', $tenant->id)->first();
if (! $passenger) {
    echo "[FAIL] Passageiro seed não encontrado\n";
    exit(1);
}

try {
    $before = $passenger->asaas_customer_id;
    $customerId = app(AsaasCustomerService::class)->ensureForUser($passenger->fresh(), $tenant);
    $passenger->refresh();
    echo "[OK] Cliente Asaas: {$customerId}\n";
    echo ($before ? '[OK] reutilizou existente' : '[OK] criou novo')."\n";
} catch (Throwable $e) {
    echo '[FAIL] '.$e->getMessage()."\n";
    exit(1);
}

echo "\n=== Corridas aguardando pagamento ===\n";
$awaiting = Ride::query()
    ->where('status', 'awaiting_payment')
    ->where('payment_status', 'pending')
    ->count();
echo "Total: {$awaiting}\n";

$ride = Ride::query()
    ->where('status', 'awaiting_payment')
    ->where('payment_status', 'pending')
    ->latest('updated_at')
    ->first();

if ($ride) {
    echo "Última corrida: {$ride->id} | valor R$ ".number_format((float) ($ride->final_fare ?? 0), 2, ',', '.')."\n";
    $methods = app(\App\Domains\Payments\Services\RidePaymentService::class)->enabledMethods($tenant);
    echo 'Pix habilitado: '.($methods['pix'] ? 'sim' : 'não')."\n";
    echo 'Cartão habilitado: '.($methods['credit_card'] ? 'sim' : 'não')."\n";
} else {
    echo "[INFO] Nenhuma corrida em awaiting_payment para teste Pix agora\n";
}

echo "\n=== APP_URL ===\n";
echo config('app.url')."\n";
$expectedWebhook = 'https://apichama.agilizeerp.com.br/api/v1/webhooks/asaas';
echo "Webhook recomendado: {$expectedWebhook}\n";
