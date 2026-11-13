<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenant = App\Models\Tenant::query()->where('slug', 'chama-demo')->firstOrFail();
$client = app(App\Domains\Payments\Services\AsaasClient::class);

foreach ([
    '/myAccount/commercialInfo/',
    '/myAccount/status/',
    '/myAccount/paymentCheckoutConfig/',
    '/finance/payment/statistics',
] as $path) {
    echo "=== {$path} ===\n";
    try {
        echo json_encode($client->get($path, $tenant), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";
    } catch (Throwable $e) {
        echo 'ERR: '.$e->getMessage()."\n\n";
    }
}
