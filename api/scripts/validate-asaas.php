<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domains\Payments\Services\AsaasClient;
use App\Domains\Payments\Services\AsaasPaymentService;
use App\Models\Tenant;

$slug = $argv[1] ?? config('app.default_tenant_slug', 'chama-demo');
$tenant = Tenant::query()->where('slug', $slug)->first();

if (! $tenant) {
    echo "[FAIL] Tenant não encontrado: {$slug}\n";
    exit(1);
}

$client = app(AsaasClient::class);
$payments = app(AsaasPaymentService::class);

$checks = [];

$checks['tenant_found'] = true;
$checks['api_key_configured'] = filled($tenant->asaas_api_key);
$checks['environment_sandbox'] = ($tenant->asaas_environment ?? 'sandbox') === 'sandbox';
$checks['webhook_token_set'] = filled($tenant->asaas_webhook_token);
$checks['commission_pct'] = $tenant->platform_commission_pct !== null;

$methods = $tenant->enabledPaymentMethods();
$checks['pix_enabled'] = (bool) ($methods['pix'] ?? false);
$checks['credit_card_enabled'] = (bool) ($methods['credit_card'] ?? false);
$checks['direct_driver_enabled'] = (bool) ($methods['direct_driver'] ?? false);

echo "=== Config tenant ({$slug}) ===\n";
echo 'Ambiente: '.($tenant->asaas_environment ?? 'sandbox')."\n";
echo 'API Key: '.($checks['api_key_configured'] ? 'configurada ('.strlen($tenant->asaas_api_key).' chars)' : 'AUSENTE')."\n";
echo 'Webhook token: '.($checks['webhook_token_set'] ? 'configurado' : 'AUSENTE')."\n";
echo 'Comissão: '.number_format((float) ($tenant->platform_commission_pct ?? 0), 2, ',', '.')."%\n";
echo 'Métodos: pix='.( $methods['pix'] ? 'sim' : 'não').', cartão='.( $methods['credit_card'] ? 'sim' : 'não').', direto='.( $methods['direct_driver'] ? 'sim' : 'não')."\n\n";

if (! $checks['api_key_configured']) {
    foreach ($checks as $name => $ok) {
        echo ($ok ? '[OK]' : '[FAIL]')." {$name}\n";
    }
    exit(1);
}

echo "=== Teste API Asaas ({$client->baseUrl($tenant)}) ===\n";

try {
    $balance = $payments->getBalance($tenant);
    $checks['api_balance'] = array_key_exists('balance', $balance);
    echo '[OK] Saldo consultado: R$ '.number_format((float) ($balance['balance'] ?? 0), 2, ',', '.')."\n";
} catch (Throwable $e) {
    $checks['api_balance'] = false;
    echo '[FAIL] Saldo: '.$e->getMessage()."\n";
}

try {
    $caps = app(\App\Domains\Payments\Services\AsaasAccountService::class)->availablePaymentMethods($tenant);
    $checks['asaas_account_ready'] = (bool) ($caps['asaas_ready'] ?? false);
    $checks['asaas_pix'] = (bool) ($caps['pix'] ?? false);
    $checks['asaas_credit_card'] = (bool) ($caps['credit_card'] ?? false);
    echo '[OK] Formas no Asaas: pix='.( $caps['pix'] ? 'sim' : 'não')
        .', cartão='.( $caps['credit_card'] ? 'sim' : 'não')
        .', general='.( $caps['general'] ?? '—')."\n";
} catch (Throwable $e) {
    $checks['asaas_account_ready'] = false;
    echo '[FAIL] Formas Asaas: '.$e->getMessage()."\n";
}

try {
    $customers = $client->get('/customers?limit=1', $tenant);
    $checks['api_customers'] = isset($customers['data']);
    $total = $customers['totalCount'] ?? count($customers['data'] ?? []);
    echo "[OK] Listagem clientes: {$total} cadastrado(s)\n";
} catch (Throwable $e) {
    $checks['api_customers'] = false;
    echo '[FAIL] Clientes: '.$e->getMessage()."\n";
}

try {
    $paymentsList = $client->get('/payments?limit=1', $tenant);
    $checks['api_payments'] = isset($paymentsList['data']);
    $total = $paymentsList['totalCount'] ?? count($paymentsList['data'] ?? []);
    echo "[OK] Listagem cobranças: {$total} registro(s)\n";
} catch (Throwable $e) {
    $checks['api_payments'] = false;
    echo '[FAIL] Cobranças: '.$e->getMessage()."\n";
}

echo "\n=== Webhook local ===\n";
$webhookUrl = rtrim(config('app.url') ?: 'https://apichama.agilizeerp.com.br', '/').'/api/v1/webhooks/asaas';
echo "URL: {$webhookUrl}\n";

$checks['webhook_route'] = Illuminate\Support\Facades\Route::has('generated::placeholder')
    || collect(Illuminate\Support\Facades\Route::getRoutes())->contains(
        fn ($r) => str_contains($r->uri(), 'webhooks/asaas')
    );

echo ($checks['webhook_route'] ? '[OK]' : '[FAIL]')." Rota webhook registrada\n";

echo "\n=== Resumo ===\n";
$failed = false;
foreach ($checks as $name => $ok) {
    echo ($ok ? '[OK]' : '[FAIL]')." {$name}\n";
    if (! $ok) {
        $failed = true;
    }
}

exit($failed ? 1 : 0);
