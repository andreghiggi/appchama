<?php

namespace App\Domains\Payments\Services;

use App\Models\RidePayment;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class RidePaymentReconcileService
{
    private const PAID_STATUSES = ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'];

    private const PAID_CHECKOUT_STATUSES = ['PAID', 'CONFIRMED', 'RECEIVED'];

    public function __construct(
        private AsaasClient $asaasClient,
        private RidePaymentService $ridePayments,
    ) {}

    /**
     * Consulta cobranças pendentes no Asaas e confirma pagamentos perdidos pelo webhook.
     *
     * @return array{checked: int, confirmed: int, skipped: int}
     */
    public function reconcilePending(int $olderThanMinutes = 2): array
    {
        $stats = ['checked' => 0, 'confirmed' => 0, 'skipped' => 0];

        $pending = RidePayment::query()
            ->where('status', 'pending')
            ->where(function ($query): void {
                $query->whereNotNull('asaas_payment_id')
                    ->orWhereNotNull('asaas_checkout_id');
            })
            ->where('created_at', '<=', now()->subMinutes($olderThanMinutes))
            ->with(['ride.driver', 'ride.passenger'])
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        foreach ($pending as $payment) {
            $stats['checked']++;

            $ride = $payment->ride;
            if (! $ride || $ride->payment_status === 'paid') {
                $stats['skipped']++;

                continue;
            }

            $tenant = Tenant::query()->find($payment->tenant_id);
            if (! $tenant || ! $this->asaasClient->isConfigured($tenant)) {
                $stats['skipped']++;

                continue;
            }

            if ($payment->asaas_checkout_id) {
                if ($this->reconcileCheckoutPayment($payment, $ride, $tenant)) {
                    $stats['confirmed']++;
                } else {
                    $stats['skipped']++;
                }

                continue;
            }

            try {
                $asaas = $this->asaasClient->get('/payments/'.$payment->asaas_payment_id, $tenant);
            } catch (\Throwable $e) {
                Log::warning('Ride payment reconcile failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
                $stats['skipped']++;

                continue;
            }

            $status = strtoupper((string) ($asaas['status'] ?? ''));
            if (! in_array($status, self::PAID_STATUSES, true)) {
                $stats['skipped']++;

                continue;
            }

            $method = strtolower($asaas['billingType'] ?? $payment->method ?? 'pix') === 'credit_card'
                ? 'credit_card'
                : ($payment->method ?? 'pix');

            $this->ridePayments->handleAsaasPaymentConfirmed(
                $ride,
                $payment->asaas_payment_id,
                $method,
            );
            $stats['confirmed']++;
        }

        return $stats;
    }

    private function reconcileCheckoutPayment(RidePayment $payment, $ride, Tenant $tenant): bool
    {
        try {
            $checkout = $this->asaasClient->get('/checkouts/'.$payment->asaas_checkout_id, $tenant);
        } catch (\Throwable $e) {
            Log::warning('Ride checkout reconcile failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            // Checkout Asaas expira em ~60min; se ainda falhar bem depois
            // disso, não há mais o que reconciliar — encerra em vez de
            // tentar para sempre a cada execução agendada.
            if ($payment->created_at->lt(now()->subHours(2))) {
                $this->ridePayments->cancelPendingPayment($ride);

                Log::warning('Ride checkout marked as expired after repeated reconcile failures', [
                    'payment_id' => $payment->id,
                ]);
            }

            return false;
        }

        $status = strtoupper((string) ($checkout['status'] ?? ''));
        if (! in_array($status, self::PAID_CHECKOUT_STATUSES, true)) {
            return false;
        }

        $this->ridePayments->handleAsaasPaymentConfirmed(
            $ride,
            $payment->asaas_checkout_id,
            'credit_card',
        );

        return true;
    }
}
