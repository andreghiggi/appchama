<?php

namespace App\Domains\Payments\Services;

use App\Models\Tenant;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasClient
{
    public function isConfigured(?Tenant $tenant = null): bool
    {
        return (bool) $this->apiKey($tenant);
    }

    public function apiKey(?Tenant $tenant = null): ?string
    {
        if ($tenant?->asaas_api_key) {
            return $tenant->asaas_api_key;
        }

        return config('services.asaas.api_key') ?: null;
    }

    public function baseUrl(?Tenant $tenant = null): string
    {
        $env = $tenant?->asaas_environment ?? config('services.asaas.environment', 'sandbox');

        return $env === 'production'
            ? 'https://api.asaas.com/v3'
            : 'https://api-sandbox.asaas.com/v3';
    }

    public function webhookToken(?Tenant $tenant = null): ?string
    {
        return $tenant?->asaas_webhook_token ?: config('services.asaas.webhook_token');
    }

    public function looksLikeApiKey(string $value): bool
    {
        return str_starts_with($value, '$aact_');
    }

    public function baseUrlForEnvironment(string $environment): string
    {
        return $environment === 'production'
            ? 'https://api.asaas.com/v3'
            : 'https://api-sandbox.asaas.com/v3';
    }

    /**
     * @return array{balance: mixed}
     */
    public function verifyCredentials(string $apiKey, string $environment): array
    {
        $apiKey = trim($apiKey);

        if ($apiKey === '') {
            throw new \InvalidArgumentException('Informe a API Key do Asaas.');
        }

        if ($environment === 'sandbox' && str_contains($apiKey, '_prod_')) {
            throw new \InvalidArgumentException('Esta API Key é de produção. Selecione ambiente Produção ou use a chave do sandbox.');
        }

        if ($environment === 'production' && (str_contains($apiKey, '_hmlg_') || str_contains($apiKey, '_sandbox_'))) {
            throw new \InvalidArgumentException('Esta API Key é de sandbox. Selecione ambiente Sandbox ou use a chave de produção.');
        }

        /** @var Response $response */
        $response = Http::withHeaders([
            'access_token' => $apiKey,
            'Accept' => 'application/json',
        ])->baseUrl($this->baseUrlForEnvironment($environment))
            ->get('/finance/balance');

        if (! $response->successful()) {
            $message = $response->json('errors.0.description')
                ?? $response->json('message')
                ?? 'API Key rejeitada pelo Asaas';

            Log::warning('Asaas credential verification failed', [
                'environment' => $environment,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new \InvalidArgumentException($message);
        }

        return $response->json() ?? [];
    }

    public function get(string $path, ?Tenant $tenant = null): array
    {
        return $this->request('GET', $path, [], $tenant);
    }

    public function post(string $path, array $body, ?Tenant $tenant = null): array
    {
        return $this->request('POST', $path, $body, $tenant);
    }

    public function put(string $path, array $body, ?Tenant $tenant = null): array
    {
        return $this->request('PUT', $path, $body, $tenant);
    }

    private function request(string $method, string $path, array $body, ?Tenant $tenant): array
    {
        $apiKey = $this->apiKey($tenant);

        if (! $apiKey) {
            abort(503, 'Gateway Asaas não configurado.');
        }

        $http = Http::withHeaders([
            'access_token' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->baseUrl($this->baseUrl($tenant));

        /** @var Response $response */
        $response = match ($method) {
            'GET' => $http->get($path),
            'PUT' => $http->put($path, $body),
            default => $http->post($path, $body),
        };

        if (! $response->successful()) {
            $message = $response->json('errors.0.description')
                ?? $response->json('message')
                ?? 'Erro na API Asaas';

            Log::warning('Asaas API error', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            abort(422, $message);
        }

        return $response->json() ?? [];
    }
}
