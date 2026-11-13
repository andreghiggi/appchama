<?php

namespace App\Domains\Payments\Services;

use App\Models\Ride;
use App\Models\RidePayment;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class RidePaymentService
{
    public function __construct(
        private AsaasPaymentService $asaasPayments,
        private DriverWalletService $walletService,
    ) {}

    public function tenantForRide(Ride $ride): Tenant
    {
        return $ride->tenant ?? Tenant::query()->findOrFail($ride->tenant_id);
    }

    public function enabledMethods(Tenant $tenant): array
    {
        $methods = $tenant->enabledPaymentMethods();
        $asaas = app(AsaasAccountService::class)->availablePaymentMethods($tenant);

        if (! $asaas['asaas_ready']) {
            $methods['pix'] = false;
            $methods['credit_card'] = false;
        } else {
            $methods['pix'] = (bool) ($methods['pix'] ?? false) && $asaas['pix'];
            $methods['credit_card'] = (bool) ($methods['credit_card'] ?? false) && $asaas['credit_card'];
        }

        return $methods;
    }

    /**
     * @return list<array{id: string, label: string, description: string, requires_profile: bool}>
     */
    public function listPaymentMethods(Tenant $tenant): array
    {
        $enabled = $this->enabledMethods($tenant);
        $methods = [];

        if ($enabled['pix'] ?? false) {
            $methods[] = [
                'id' => 'pix',
                'label' => 'Pix',
                'description' => 'Pague pelo app com QR Code ou copia e cola',
                'requires_profile' => true,
            ];
        }

        if ($enabled['credit_card'] ?? false) {
            $methods[] = [
                'id' => 'credit_card',
                'label' => 'Cartão de crédito',
                'description' => 'Checkout seguro Asaas',
                'requires_profile' => true,
            ];
        }

        if ($enabled['direct_driver'] ?? false) {
            $methods[] = [
                'id' => 'direct_driver',
                'label' => 'Direto ao motorista',
                'description' => 'Dinheiro ou Pix pessoal — o motorista confirma o recebimento',
                'requires_profile' => false,
            ];
        }

        return $methods;
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentStatus(Ride $ride): array
    {
        $tenant = $this->tenantForRide($ride);
        $ride->loadMissing('passenger');
        $latest = $ride->payments()->latest()->first();

        $asaasCapabilities = app(AsaasAccountService::class)->availablePaymentMethods($tenant);

        $response = [
            'ride_id' => $ride->id,
            'status' => $ride->status,
            'payment_status' => $ride->payment_status,
            'payment_method' => $ride->payment_method,
            'amount' => (float) ($ride->final_fare ?? $ride->estimated_fare ?? 0),
            'driver_net_amount' => $ride->driver_net_amount !== null ? (float) $ride->driver_net_amount : null,
            'methods_enabled' => $this->enabledMethods($tenant),
            'payment_methods' => $this->listPaymentMethods($tenant),
            'asaas_capabilities' => $asaasCapabilities,
            'payment_profile_complete' => (bool) $ride->passenger?->hasPaymentProfile(),
            'pix_qr_code' => null,
            'pix_copy_paste' => null,
            'checkout_url' => null,
        ];

        if ($latest && $latest->status === 'pending') {
            $payload = $latest->payload ?? [];
            if ($latest->method === 'pix') {
                $response['pix_qr_code'] = $payload['pix']['encodedImage'] ?? null;
                $response['pix_copy_paste'] = $payload['pix']['payload'] ?? null;
            }
            if ($latest->method === 'credit_card') {
                $response['checkout_url'] = $payload['checkout']['link'] ?? null;
            }
        }

        return $response;
    }

    public function initiatePayment(Ride $ride, string $method, ?string $successUrl = null, ?string $cancelUrl = null): array
    {
        abort_unless($ride->status === 'awaiting_payment', 422, 'Corrida não aguarda pagamento.');
        abort_unless($ride->payment_status === 'pending', 422, 'Pagamento já processado.');

        $existing = $ride->payments()
            ->where('method', $method)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($existing) {
            return $this->formatPendingPaymentResult($existing);
        }

        $ride->loadMissing('passenger');
        $tenant = $this->tenantForRide($ride);
        $enabled = $this->enabledMethods($tenant);

        if ($method === 'pix') {
            abort_unless($enabled['pix'] ?? false, 422, 'Pix não habilitado.');

            return $this->asaasPayments->createPixPayment($ride, $tenant);
        }

        if ($method === 'credit_card') {
            abort_unless($enabled['credit_card'] ?? false, 422, 'Cartão não habilitado.');
            abort_unless($successUrl && $cancelUrl, 422, 'URLs de retorno obrigatórias para cartão.');

            return $this->asaasPayments->createCheckoutPayment($ride, $tenant, $successUrl, $cancelUrl);
        }

        abort(422, 'Método de pagamento inválido.');
    }

    /**
     * @return array<string, mixed>
     */
    public function choosePaymentMethod(Ride $ride, string $method): array
    {
        abort_unless($ride->status === 'awaiting_payment', 422, 'Corrida não aguarda pagamento.');
        abort_unless($ride->payment_status === 'pending', 422, 'Pagamento já processado.');
        abort_unless($method === 'direct_driver', 422, 'Use o endpoint de pagamento para Pix ou cartão.');

        $tenant = $this->tenantForRide($ride);
        abort_unless($this->enabledMethods($tenant)['direct_driver'] ?? false, 422, 'Pagamento direto não habilitado.');

        $ride->update(['payment_method' => 'direct_driver']);

        return $this->paymentStatus($ride->fresh());
    }

    /**
     * @return array{payment: RidePayment, pix_qr_code?: string, pix_copy_paste?: string, checkout_url?: string}
     */
    private function formatPendingPaymentResult(RidePayment $payment): array
    {
        $payload = $payment->payload ?? [];
        $result = ['payment' => $payment];

        if ($payment->method === 'pix') {
            $result['pix_qr_code'] = $payload['pix']['encodedImage'] ?? null;
            $result['pix_copy_paste'] = $payload['pix']['payload'] ?? null;
        }

        if ($payment->method === 'credit_card') {
            $result['checkout_url'] = $payload['checkout']['link'] ?? null;
        }

        return $result;
    }

    public function confirmDirectPayment(Ride $ride): Ride
    {
        abort_unless($ride->status === 'awaiting_payment', 422, 'Corrida não aguarda pagamento.');
        abort_unless($ride->payment_method === 'direct_driver', 422, 'Passageiro não escolheu pagamento direto.');

        $tenant = $this->tenantForRide($ride);
        abort_unless($tenant->enabledPaymentMethods()['direct_driver'] ?? false, 422, 'Pagamento direto não habilitado.');

        return $this->markRidePaid($ride, 'direct_driver', null);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelPendingPayment(Ride $ride): array
    {
        abort_unless($ride->status === 'awaiting_payment', 422, 'Corrida não aguarda pagamento.');
        abort_unless($ride->payment_status === 'pending', 422, 'Pagamento já processado.');

        $ride->payments()
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        $ride->update(['payment_method' => null]);

        return $this->paymentStatus($ride->fresh());
    }

    public function handleAsaasPaymentConfirmed(Ride $ride, ?string $asaasPaymentId, ?string $method): Ride
    {
        if ($ride->payment_status === 'paid') {
            return $ride;
        }

        return $this->markRidePaid($ride, $method ?? $ride->payment_method ?? 'pix', $asaasPaymentId);
    }

    private function markRidePaid(Ride $ride, string $method, ?string $asaasPaymentId): Ride
    {
        return DB::transaction(function () use ($ride, $method, $asaasPaymentId): Ride {
            $ride = Ride::query()->lockForUpdate()->findOrFail($ride->id);

            if ($ride->payment_status === 'paid') {
                return $ride->load(['passenger', 'driver.user', 'city']);
            }

            if ($asaasPaymentId) {
                RidePayment::query()
                    ->where('ride_id', $ride->id)
                    ->where(function ($q) use ($asaasPaymentId): void {
                        $q->where('asaas_payment_id', $asaasPaymentId)
                            ->orWhere('asaas_checkout_id', $asaasPaymentId);
                    })
                    ->update(['status' => 'paid']);
            } elseif ($method === 'direct_driver') {
                RidePayment::query()->create([
                    'tenant_id' => $ride->tenant_id,
                    'ride_id' => $ride->id,
                    'method' => 'direct_driver',
                    'amount' => (float) $ride->final_fare,
                    'status' => 'paid',
                ]);
            }

            $ride->update([
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => $method,
                'paid_at' => now(),
                'completed_at' => $ride->completed_at ?? now(),
            ]);

            $addToBalance = in_array($method, ['pix', 'credit_card'], true);
            $freshRide = $ride->fresh(['passenger', 'driver.user', 'city']);
            $this->walletService->creditFromRide($freshRide, $addToBalance);
            $this->walletService->debitCommissionForRide($freshRide);

            return $ride->fresh(['passenger', 'driver.user', 'city']);
        });
    }
}
