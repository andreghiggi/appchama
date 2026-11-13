<?php

namespace App\Domains\Payments\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Cpf;

class AsaasCustomerService
{
    public function __construct(private AsaasClient $client) {}

    public function ensureForUser(User $user, Tenant $tenant): string
    {
        $this->assertPaymentProfile($user);

        if ($user->asaas_customer_id) {
            $this->syncCustomer($user, $tenant);

            return $user->asaas_customer_id;
        }

        $customer = $this->client->post('/customers', $this->customerPayload($user), $tenant);
        $customerId = $customer['id'] ?? null;

        abort_unless($customerId, 422, 'Falha ao criar cliente no Asaas.');

        $user->update(['asaas_customer_id' => $customerId]);

        return $customerId;
    }

    public function syncCustomer(User $user, Tenant $tenant): void
    {
        if (! $user->asaas_customer_id || ! $user->hasPaymentProfile()) {
            return;
        }

        $this->client->post('/customers/'.$user->asaas_customer_id, $this->customerPayload($user), $tenant);
    }

    /**
     * @return array<string, mixed>
     */
    public function checkoutCustomerData(User $user): array
    {
        $this->assertPaymentProfile($user);

        return [
            'name' => $user->name,
            'email' => $user->email,
            'cpfCnpj' => $user->cpf,
            'phone' => $this->formatPhone($user->phone),
            'address' => $user->home_address,
            'addressNumber' => $user->home_address_number,
            'postalCode' => $user->home_postal_code,
            'province' => strtoupper($user->home_state ?? ''),
            'city' => $user->home_city,
        ];
    }

    public function assertPaymentProfile(User $user): void
    {
        abort_unless($user->hasPaymentProfile(), 422, 'Complete CPF, e-mail e endereço antes de pagar.');
    }

    /**
     * @return array<string, mixed>
     */
    private function customerPayload(User $user): array
    {
        $payload = [
            'name' => $user->name,
            'mobilePhone' => $this->formatPhone($user->phone),
            'cpfCnpj' => $user->cpf,
            'externalReference' => $user->id,
            'email' => $user->email,
        ];

        if ($user->home_address) {
            $payload['address'] = $user->home_address;
            $payload['addressNumber'] = $user->home_address_number;
            $payload['postalCode'] = $user->home_postal_code;
            $payload['province'] = strtoupper($user->home_state ?? '');
            $payload['cityName'] = $user->home_city;
        }

        return $payload;
    }

    private function formatPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? $phone;

        if (strlen($digits) > 11 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }
}
