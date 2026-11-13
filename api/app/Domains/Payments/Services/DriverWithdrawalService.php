<?php

namespace App\Domains\Payments\Services;

use App\Models\Driver;
use App\Models\DriverWithdrawal;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class DriverWithdrawalService
{
    public function __construct(
        private DriverWalletService $walletService,
        private AsaasTransferService $transfers,
        private AsaasClient $asaasClient,
    ) {}

    public function request(Driver $driver, float $amount, Tenant $tenant): DriverWithdrawal
    {
        abort_unless($driver->payout_pix_key && $driver->payout_pix_key_type, 422, 'Cadastre sua chave Pix para saque.');

        $wallet = $this->walletService->ensureWallet($driver);

        if ((float) $wallet->balance_available < $amount) {
            abort(422, 'Saldo insuficiente.');
        }

        if ($amount < 1) {
            abort(422, 'Valor mínimo de saque: R$ 1,00.');
        }

        if (DriverWithdrawal::query()
            ->where('driver_id', $driver->user_id)
            ->where('status', 'processing')
            ->exists()) {
            abort(422, 'Você já tem um saque em processamento. Aguarde a conclusão.');
        }

        abort_unless($this->asaasClient->isConfigured($tenant), 503, 'Gateway de pagamento não configurado.');

        return DB::transaction(function () use ($driver, $amount, $tenant): DriverWithdrawal {
            $withdrawal = DriverWithdrawal::query()->create([
                'tenant_id' => $tenant->id,
                'driver_id' => $driver->user_id,
                'amount' => round($amount, 2),
                'status' => 'processing',
                'pix_key' => $driver->payout_pix_key,
                'pix_key_type' => $driver->payout_pix_key_type,
            ]);

            $this->walletService->debitForWithdrawal($driver->user_id, $amount, $withdrawal->id);

            try {
                $transfer = $this->transfers->transferWithdrawal($withdrawal, $tenant);
                $withdrawal->update([
                    'asaas_transfer_id' => $transfer['id'] ?? null,
                    'status' => in_array($transfer['status'] ?? '', ['DONE', 'CONFIRMED'], true) ? 'done' : 'processing',
                ]);
            } catch (\Throwable $e) {
                $this->walletService->refundFailedWithdrawal(
                    $driver->user_id,
                    $amount,
                    $withdrawal->id,
                    $e->getMessage(),
                );
                $withdrawal->update([
                    'status' => 'failed',
                    'fail_reason' => $e->getMessage(),
                ]);
                throw $e;
            }

            return $withdrawal->fresh();
        });
    }

    public function handleTransferWebhook(array $transfer, string $status): void
    {
        $withdrawalId = $transfer['externalReference'] ?? null;

        if (! $withdrawalId) {
            return;
        }

        $withdrawal = DriverWithdrawal::query()->find($withdrawalId);

        if (! $withdrawal) {
            return;
        }

        if ($status === 'DONE' || $status === 'CONFIRMED') {
            $withdrawal->update(['status' => 'done']);

            return;
        }

        if (in_array($status, ['FAILED', 'CANCELLED'], true) && $withdrawal->status !== 'failed') {
            $this->walletService->refundFailedWithdrawal(
                $withdrawal->driver_id,
                (float) $withdrawal->amount,
                $withdrawal->id,
                $transfer['failReason'] ?? 'Transferência falhou',
            );
            $withdrawal->update([
                'status' => 'failed',
                'fail_reason' => $transfer['failReason'] ?? 'Transferência falhou',
            ]);
        }
    }

    /**
     * Consulta transferências pendentes no Asaas e atualiza status local.
     *
     * @return array{checked: int, updated: int, failed: int}
     */
    public function reconcileStaleProcessing(int $olderThanMinutes = 5): array
    {
        $stats = ['checked' => 0, 'updated' => 0, 'failed' => 0];

        $pending = DriverWithdrawal::query()
            ->where('status', 'processing')
            ->where('created_at', '<=', now()->subMinutes($olderThanMinutes))
            ->get();

        foreach ($pending as $withdrawal) {
            $stats['checked']++;

            if (! $withdrawal->asaas_transfer_id) {
                if ($withdrawal->created_at <= now()->subMinutes(30)) {
                    $this->walletService->refundFailedWithdrawal(
                        $withdrawal->driver_id,
                        (float) $withdrawal->amount,
                        $withdrawal->id,
                        'Transferência não iniciada no gateway. Tente novamente.',
                    );
                    $withdrawal->update([
                        'status' => 'failed',
                        'fail_reason' => 'Transferência não iniciada no gateway. Tente novamente.',
                    ]);
                    $stats['failed']++;
                }

                continue;
            }

            $tenant = Tenant::query()->find($withdrawal->tenant_id);
            if (! $tenant || ! $this->asaasClient->isConfigured($tenant)) {
                continue;
            }

            try {
                $transfer = $this->transfers->getTransfer($withdrawal->asaas_transfer_id, $tenant);
            } catch (\Throwable) {
                continue;
            }

            $status = strtoupper((string) ($transfer['status'] ?? ''));

            if (in_array($status, ['DONE', 'CONFIRMED'], true)) {
                $withdrawal->update(['status' => 'done']);
                $stats['updated']++;

                continue;
            }

            if (in_array($status, ['FAILED', 'CANCELLED'], true) && $withdrawal->status !== 'failed') {
                $this->handleTransferWebhook($transfer, $status);
                $stats['failed']++;
            }
        }

        return $stats;
    }
}
