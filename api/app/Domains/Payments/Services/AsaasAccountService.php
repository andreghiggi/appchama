<?php

namespace App\Domains\Payments\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AsaasAccountService
{
    private const CACHE_SECONDS = 300;

    public function __construct(private AsaasClient $client) {}

    /**
     * @return array{
     *     asaas_ready: bool,
     *     pix: bool,
     *     credit_card: bool,
     *     general: ?string,
     *     commercial_info: ?string,
     *     bank_account_info: ?string,
     *     error: ?string
     * }
     */
    public function availablePaymentMethods(Tenant $tenant): array
    {
        if (! $this->client->isConfigured($tenant)) {
            return $this->emptyCapabilities('Gateway Asaas não configurado.');
        }

        return Cache::remember(
            'asaas_payment_methods:'.$tenant->id,
            self::CACHE_SECONDS,
            fn (): array => $this->fetchCapabilities($tenant),
        );
    }

    public function forgetCache(Tenant $tenant): void
    {
        Cache::forget('asaas_payment_methods:'.$tenant->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function accountStatus(Tenant $tenant): array
    {
        if (! $this->client->isConfigured($tenant)) {
            abort(503, 'Gateway Asaas não configurado.');
        }

        return $this->client->get('/myAccount/status/', $tenant);
    }

    /**
     * @return array{
     *     asaas_ready: bool,
     *     pix: bool,
     *     credit_card: bool,
     *     general: ?string,
     *     commercial_info: ?string,
     *     bank_account_info: ?string,
     *     error: ?string
     * }
     */
    private function fetchCapabilities(Tenant $tenant): array
    {
        try {
            $status = $this->client->get('/myAccount/status/', $tenant);
        } catch (\Throwable $e) {
            Log::warning('Asaas account status failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return $this->emptyCapabilities($e->getMessage());
        }

        $general = strtoupper((string) ($status['general'] ?? ''));
        $commercial = strtoupper((string) ($status['commercialInfo'] ?? ''));
        $bank = strtoupper((string) ($status['bankAccountInfo'] ?? ''));

        $ready = $general === 'APPROVED';
        $pix = $ready;
        $creditCard = $ready && $commercial === 'APPROVED';

        return [
            'asaas_ready' => $ready,
            'pix' => $pix,
            'credit_card' => $creditCard,
            'general' => $general ?: null,
            'commercial_info' => $commercial ?: null,
            'bank_account_info' => $bank ?: null,
            'error' => null,
        ];
    }

    /**
     * @return array{
     *     asaas_ready: bool,
     *     pix: bool,
     *     credit_card: bool,
     *     general: ?string,
     *     commercial_info: ?string,
     *     bank_account_info: ?string,
     *     error: ?string
     * }
     */
    private function emptyCapabilities(?string $error): array
    {
        return [
            'asaas_ready' => false,
            'pix' => false,
            'credit_card' => false,
            'general' => null,
            'commercial_info' => null,
            'bank_account_info' => null,
            'error' => $error,
        ];
    }
}
