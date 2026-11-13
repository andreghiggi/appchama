<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domains\Payments\Services\AsaasClient;
use App\Models\Tenant;
use App\Models\User;

$tenant = Tenant::query()->where('slug', 'chama-demo')->firstOrFail();
$client = app(AsaasClient::class);

$passenger = User::query()
    ->where('tenant_id', $tenant->id)
    ->where('phone', '5511999990002')
    ->firstOrFail();

if (! $passenger->asaas_customer_id) {
    echo "[FAIL] Passageiro sem asaas_customer_id — rode validate-asaas-deep.php primeiro\n";
    exit(1);
}

$customerId = $passenger->asaas_customer_id;

// Asaas exige CPF/CNPJ para cobrança Pix — atualiza cliente de teste se necessário.
try {
    $client->post("/customers/{$customerId}", [
        'cpfCnpj' => '24971563792',
    ], $tenant);
    echo "[OK] CPF de teste aplicado ao cliente Asaas\n";
} catch (Throwable $e) {
    echo '[WARN] CPF cliente: '.$e->getMessage()."\n";
}

echo "=== Teste cobrança Pix sandbox (R$ 5,00) ===\n";

try {
    $payment = $client->post('/payments', [
        'customer' => $passenger->asaas_customer_id,
        'billingType' => 'PIX',
        'value' => 5.00,
        'dueDate' => now()->addDay()->format('Y-m-d'),
        'description' => 'Teste interno AppChama',
        'externalReference' => 'validation-'.now()->timestamp,
    ], $tenant);

    $paymentId = $payment['id'] ?? null;
    abort_unless($paymentId, 422, 'Sem ID de pagamento');

    $pix = $client->get("/payments/{$paymentId}/pixQrCode", $tenant);

    echo "[OK] Cobrança criada: {$paymentId}\n";
    echo '[OK] QR Pix gerado: '.(filled($pix['encodedImage'] ?? null) ? 'sim' : 'não')."\n";
    echo '[OK] Copia e cola: '.(filled($pix['payload'] ?? null) ? 'sim' : 'não')."\n";
    echo "[INFO] Cobrança de teste permanece no sandbox Asaas (R$ 5,00)\n";
} catch (Throwable $e) {
    echo '[FAIL] '.$e->getMessage()."\n";
    exit(1);
}

echo "\n=== Teste checkout cartão sandbox (R$ 5,00) ===\n";

try {
    $checkout = $client->post('/checkouts', [
        'billingTypes' => ['CREDIT_CARD'],
        'chargeTypes' => ['DETACHED'],
        'minutesToExpire' => 30,
        'externalReference' => 'validation-checkout-'.now()->timestamp,
        'callback' => [
            'successUrl' => 'https://passa.agilizeerp.com.br/',
            'cancelUrl' => 'https://passa.agilizeerp.com.br/',
            'expiredUrl' => 'https://passa.agilizeerp.com.br/',
        ],
        'items' => [
            [
                'name' => 'Teste AppChama',
                'description' => 'Validação checkout',
                'quantity' => 1,
                'value' => 5.00,
            ],
        ],
        'customerData' => [
            'name' => $passenger->name,
            'email' => $passenger->email ?: 'passageiro.teste@appchama.local',
            'cpfCnpj' => '24971563792',
            'phone' => preg_replace('/\D/', '', $passenger->phone),
            'address' => $passenger->home_address ?: 'Rua Teste',
            'addressNumber' => '100',
            'postalCode' => '99010000',
            'province' => 'RS',
            'city' => 'Guapore',
        ],
    ], $tenant);

    $checkoutId = $checkout['id'] ?? null;
    $link = $checkout['link'] ?? null;

    echo "[OK] Checkout criado: {$checkoutId}\n";
    echo '[OK] Link checkout: '.($link ? 'sim' : 'não')."\n";
} catch (Throwable $e) {
    echo '[FAIL] '.$e->getMessage()."\n";
    exit(1);
}

echo "\n[OK] Integração Asaas sandbox validada ponta a ponta\n";
