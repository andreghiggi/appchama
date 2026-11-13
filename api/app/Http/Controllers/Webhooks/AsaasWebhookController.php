<?php

namespace App\Http\Controllers\Webhooks;

use App\Domains\Payments\Services\AsaasClient;
use App\Domains\Payments\Services\DriverWithdrawalService;
use App\Domains\Payments\Services\RidePaymentService;
use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\RidePayment;
use App\Models\Tenant;
use App\Models\WalletRecharge;
use App\Domains\Payments\Services\DriverWalletRechargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
    public function __construct(
        private RidePaymentService $ridePayments,
        private DriverWithdrawalService $withdrawals,
        private AsaasClient $asaasClient,
        private DriverWalletRechargeService $recharges,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $token = $request->header('asaas-access-token')
            ?? $request->header('X-Asaas-Access-Token');

        $tenant = $this->resolveTenant($request, $token);

        if (! $tenant) {
            Log::warning('Asaas webhook tenant not resolved');

            return response()->json(['message' => 'Tenant not found'], 404);
        }

        $expected = $this->asaasClient->webhookToken($tenant);

        if (! $expected || $token !== $expected) {
            Log::warning('Asaas webhook token mismatch or not configured', ['tenant' => $tenant->slug]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $event = $request->input('event');
        $payment = $request->input('payment');
        $checkout = $request->input('checkout');
        $transfer = $request->input('transfer');

        Log::info('Asaas webhook', ['event' => $event, 'tenant' => $tenant->slug]);

        if (in_array($event, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true) && $payment) {
            if ($this->handleRechargePayment($payment)) return response()->json(['received' => true]);
            $this->handlePaymentEvent($payment);

            return response()->json(['received' => true]);
        }

        if ($event === 'CHECKOUT_PAID' && $checkout) {
            $this->handleCheckoutPaid($checkout);

            return response()->json(['received' => true]);
        }

        if (in_array($event, ['TRANSFER_DONE', 'TRANSFER_FAILED', 'TRANSFER_CANCELLED'], true) && $transfer) {
            $status = match ($event) {
                'TRANSFER_DONE' => 'DONE',
                'TRANSFER_FAILED' => 'FAILED',
                default => 'CANCELLED',
            };
            $this->withdrawals->handleTransferWebhook($transfer, $status);

            return response()->json(['received' => true]);
        }

        return response()->json(['received' => true]);
    }

    private function handleRechargePayment(array $payment): bool
    {
        $ref = (string) ($payment['externalReference'] ?? '');
        $id = str_starts_with($ref, 'wallet-recharge:') ? substr($ref, 16) : null;
        $recharge = $id ? WalletRecharge::query()->find($id) : WalletRecharge::query()->where('asaas_payment_id', $payment['id'] ?? null)->first();
        if (! $recharge) return false;
        $this->recharges->confirm($recharge); return true;
    }

    private function resolveTenant(Request $request, ?string $token): ?Tenant
    {
        $payment = $request->input('payment');
        $checkout = $request->input('checkout');

        if (is_array($payment)) {
            $ref = (string) ($payment['externalReference'] ?? '');
            if (str_starts_with($ref, 'wallet-recharge:')) {
                $recharge = WalletRecharge::query()->find(substr($ref, 16));
                if ($recharge?->tenant_id) return Tenant::query()->find($recharge->tenant_id);
            }
            $ride = $this->findRideFromPayment($payment);
            if ($ride?->tenant_id) {
                return Tenant::query()->find($ride->tenant_id);
            }
        }

        if (is_array($checkout)) {
            $rideId = $checkout['externalReference'] ?? null;
            if ($rideId) {
                $ride = Ride::query()->find($rideId);
                if ($ride?->tenant_id) {
                    return Tenant::query()->find($ride->tenant_id);
                }
            }

            $checkoutId = $checkout['id'] ?? null;
            if ($checkoutId) {
                $ridePayment = RidePayment::query()->where('asaas_checkout_id', $checkoutId)->first();
                if ($ridePayment?->tenant_id) {
                    return Tenant::query()->find($ridePayment->tenant_id);
                }
            }
        }

        if ($token) {
            foreach (Tenant::query()->get() as $tenant) {
                if ($this->asaasClient->webhookToken($tenant) === $token) {
                    return $tenant;
                }
            }
        }

        return null;
    }

    private function handlePaymentEvent(array $payment): void
    {
        $ride = $this->findRideFromPayment($payment);

        if (! $ride) {
            return;
        }

        $method = strtolower($payment['billingType'] ?? 'pix') === 'credit_card' ? 'credit_card' : 'pix';
        $this->ridePayments->handleAsaasPaymentConfirmed($ride, $payment['id'] ?? null, $method);
    }

    private function handleCheckoutPaid(array $checkout): void
    {
        $rideId = $checkout['externalReference'] ?? null;

        if ($rideId) {
            $ride = Ride::query()->find($rideId);
            if ($ride) {
                $this->ridePayments->handleAsaasPaymentConfirmed(
                    $ride,
                    $checkout['id'] ?? null,
                    'credit_card',
                );

                return;
            }
        }

        $checkoutId = $checkout['id'] ?? null;

        if ($checkoutId) {
            $ridePayment = RidePayment::query()->where('asaas_checkout_id', $checkoutId)->first();
            if ($ridePayment?->ride) {
                $this->ridePayments->handleAsaasPaymentConfirmed(
                    $ridePayment->ride,
                    $checkoutId,
                    'credit_card',
                );
            }
        }
    }

    private function findRideFromPayment(array $payment): ?Ride
    {
        $rideId = $payment['externalReference'] ?? null;

        if ($rideId) {
            return Ride::query()->find($rideId);
        }

        $paymentId = $payment['id'] ?? null;

        if ($paymentId) {
            $ridePayment = RidePayment::query()->where('asaas_payment_id', $paymentId)->first();

            return $ridePayment?->ride;
        }

        return null;
    }
}
