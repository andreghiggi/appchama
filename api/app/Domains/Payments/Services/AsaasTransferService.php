<?php

namespace App\Domains\Payments\Services;

use App\Models\DriverWithdrawal;
use App\Models\Tenant;

class AsaasTransferService
{
    public function __construct(private AsaasClient $client) {}

    public function transferWithdrawal(DriverWithdrawal $withdrawal, Tenant $tenant): array
    {
        $payload = [
            'value' => (float) $withdrawal->amount,
            'operationType' => 'PIX',
            'pixAddressKey' => $withdrawal->pix_key,
            'pixAddressKeyType' => strtoupper($withdrawal->pix_key_type),
            'description' => 'Saque motorista Chama',
            'externalReference' => $withdrawal->id,
        ];

        return $this->client->post('/transfers', $payload, $tenant);
    }

    public function getTransfer(string $transferId, Tenant $tenant): array
    {
        return $this->client->get('/transfers/'.$transferId, $tenant);
    }
}
