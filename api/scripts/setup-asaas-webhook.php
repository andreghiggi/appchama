<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domains\Payments\Services\AsaasWebhookSetupService;
use App\Models\Tenant;

$slug = $argv[1] ?? config('app.default_tenant_slug', 'chama-demo');
$tenant = Tenant::query()->where('slug', $slug)->firstOrFail();

$setup = app(AsaasWebhookSetupService::class);

echo "Tenant: {$slug}\n";
echo 'URL: '.$setup->webhookUrl()."\n";

$token = $setup->ensureToken($tenant);
echo "Token: {$token}\n";

try {
    $hook = $setup->register($tenant->fresh());
    echo '[OK] Webhook registrado: '.($hook['id'] ?? 'ok')."\n";
    exit(0);
} catch (Throwable $e) {
    echo '[FAIL] '.$e->getMessage()."\n";
    exit(1);
}
