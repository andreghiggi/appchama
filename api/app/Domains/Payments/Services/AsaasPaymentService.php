<?php

namespace App\Domains\Payments\Services;

use App\Models\Ride;
use App\Models\RidePayment;
use App\Models\Tenant;

class AsaasPaymentService
{
    public function __construct(
        private AsaasClient $client,
        private AsaasCustomerService $customers,
    ) {}

    /**
     * @return array{payment: RidePayment, pix_qr_code?: string, pix_copy_paste?: string, checkout_url?: string}
     */
    public function createPixPayment(Ride $ride, Tenant $tenant): array
    {
        $ride->loadMissing('passenger');
        abort_unless($ride->passenger, 422, 'Passageiro não encontrado.');

        $customerId = $this->customers->ensureForUser($ride->passenger, $tenant);
        $amount = (float) $ride->final_fare;

        $dueDate = now()->addDay()->format('Y-m-d');

        $asaasPayment = $this->client->post('/payments', [
            'customer' => $customerId,
            'billingType' => 'PIX',
            'value' => $amount,
            'dueDate' => $dueDate,
            'description' => 'Corrida '.($tenant->name ?: 'plataforma').' #'.substr($ride->id, 0, 8),
            'externalReference' => $ride->id,
        ], $tenant);

        $paymentId = $asaasPayment['id'] ?? null;
        abort_unless($paymentId, 422, 'Falha ao criar cobrança Pix.');

        $pixData = $this->client->get("/payments/{$paymentId}/pixQrCode", $tenant);

        $ridePayment = RidePayment::query()->create([
            'tenant_id' => $tenant->id,
            'ride_id' => $ride->id,
            'method' => 'pix',
            'amount' => $amount,
            'status' => 'pending',
            'asaas_payment_id' => $paymentId,
            'payload' => ['payment' => $asaasPayment, 'pix' => $pixData],
        ]);

        $ride->update(['payment_method' => 'pix']);

        return [
            'payment' => $ridePayment,
            'pix_qr_code' => $pixData['encodedImage'] ?? null,
            'pix_copy_paste' => $pixData['payload'] ?? null,
        ];
    }

    /**
     * @return array{payment: RidePayment, checkout_url: string}
     */
    public function createCheckoutPayment(Ride $ride, Tenant $tenant, string $successUrl, string $cancelUrl): array
    {
        $ride->loadMissing('passenger');
        abort_unless($ride->passenger, 422, 'Passageiro não encontrado.');

        $this->customers->assertPaymentProfile($ride->passenger);

        $amount = (float) $ride->final_fare;

        $checkout = $this->client->post('/checkouts', [
            'billingTypes' => ['CREDIT_CARD'],
            'chargeTypes' => ['DETACHED'],
            'minutesToExpire' => 60,
            'externalReference' => $ride->id,
            'callback' => [
                'successUrl' => $successUrl,
                'cancelUrl' => $cancelUrl,
                'expiredUrl' => $cancelUrl,
            ],
            'items' => [
                [
                    'name' => 'Corrida '.($tenant->name ?: 'plataforma'),
                    'description' => 'Pagamento corrida #'.substr($ride->id, 0, 8),
                    'quantity' => 1,
                    'value' => $amount,
                ],
            ],
            'customerData' => $this->customers->checkoutCustomerData($ride->passenger),
        ], $tenant);

        $checkoutId = $checkout['id'] ?? null;
        $checkoutUrl = $checkout['link'] ?? null;

        abort_unless($checkoutId && $checkoutUrl, 422, 'Falha ao criar checkout.');

        $ridePayment = RidePayment::query()->create([
            'tenant_id' => $tenant->id,
            'ride_id' => $ride->id,
            'method' => 'credit_card',
            'amount' => $amount,
            'status' => 'pending',
            'asaas_checkout_id' => $checkoutId,
            'payload' => ['checkout' => $checkout],
        ]);

        $ride->update(['payment_method' => 'credit_card']);

        return [
            'payment' => $ridePayment,
            'checkout_url' => $checkoutUrl,
        ];
    }

    public function getBalance(Tenant $tenant): array
    {
        return $this->client->get('/finance/balance', $tenant);
    }
}
