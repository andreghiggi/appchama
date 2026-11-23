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
        // Pix: basta nome completo + CPF (os demais dados, se existirem no cadastro, vao junto)
        abort_unless($user->hasFullName(), 422, 'Informe seu nome completo (nome e sobrenome) antes de pagar.');
        abort_unless($user->hasPixProfile(), 422, 'Informe seu CPF antes de pagar.');

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

    /** Cliente do Asaas para o MOTORISTA (recarga de credito): exige so nome completo e CPF, sem endereco. */
    public function ensureForDriver(User $user, Tenant $tenant): string
    {
        abort_unless($user->hasFullName(), 422, 'Informe seu nome completo (nome e sobrenome) no cadastro antes de recarregar.');
        abort_unless(filled($user->cpf) && Cpf::isValid((string) $user->cpf), 422, 'Informe seu CPF para gerar o Pix de recarga.');

        if ($user->asaas_customer_id) {
            $this->client->post('/customers/'.$user->asaas_customer_id, array_filter($this->customerPayload($user), fn ($v) => filled($v)), $tenant);

            return $user->asaas_customer_id;
        }

        $customer = $this->client->post('/customers', array_filter($this->customerPayload($user), fn ($v) => filled($v)), $tenant);
        $customerId = $customer['id'] ?? null;
        abort_unless($customerId, 422, 'Falha ao criar cliente no Asaas.');
        $user->update(['asaas_customer_id' => $customerId]);

        return $customerId;
    }

    public function syncCustomer(User $user, Tenant $tenant): void
    {
        if (! $user->asaas_customer_id || ! $user->hasPixProfile()) {
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
        abort_unless($user->hasFullName(), 422, 'Informe seu nome completo (nome e sobrenome) antes de pagar.');
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
