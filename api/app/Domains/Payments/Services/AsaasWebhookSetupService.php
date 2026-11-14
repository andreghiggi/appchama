<?php

namespace App\Domains\Payments\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AsaasWebhookSetupService
{
    public function __construct(private AsaasClient $client) {}

    public function ensureToken(Tenant $tenant): string
    {
        if (filled($tenant->asaas_webhook_token)) {
            return $tenant->asaas_webhook_token;
        }

        $token = Str::random(40);
        $tenant->update(['asaas_webhook_token' => $token]);

        return $token;
    }

    public function register(Tenant $tenant): array
    {
        abort_unless($this->client->isConfigured($tenant), 422, 'Configure a API Key Asaas antes.');

        $token = $this->ensureToken($tenant);
        $url = $this->webhookUrl($tenant);
        $events = [
            'PAYMENT_RECEIVED',
            'PAYMENT_CONFIRMED',
            'CHECKOUT_PAID',
            'TRANSFER_DONE',
            'TRANSFER_FAILED',
            'TRANSFER_CANCELLED',
        ];

        $payload = [
            'name' => $tenant->name ?: 'AppChama',
            'url' => $url,
            'email' => config('mail.from.address', 'admin@chama.app'),
            'enabled' => true,
            'interrupted' => false,
            'apiVersion' => 3,
            'authToken' => $token,
            'sendType' => 'SEQUENTIALLY',
            'events' => $events,
        ];

        $existing = collect($this->client->get('/webhooks', $tenant)['data'] ?? [])
            ->first(fn (array $hook) => ($hook['url'] ?? '') === $url);

        if ($existing) {
            $updated = $this->client->put('/webhooks/'.($existing['id'] ?? ''), $payload, $tenant);

            Log::info('Asaas webhook updated', ['id' => $updated['id'] ?? null]);

            return $updated;
        }

        $created = $this->client->post('/webhooks', $payload, $tenant);

        Log::info('Asaas webhook created', ['id' => $created['id'] ?? null]);

        return $created;
    }

    /** Cada marca recebe o aviso do Asaas no próprio endereço de API (ex.: api.vaide55.com.br). */
    public function webhookUrl(?Tenant $tenant = null): string
    {
        $host = $tenant?->domains()->where('type', 'api')->value('host');
        if ($host) {
            return 'https://'.$host.'/api/v1/webhooks/asaas';
        }

        $base = rtrim(config('services.asaas.webhook_base_url', config('app.api_url', config('app.url'))), '/');

        return $base.'/api/v1/webhooks/asaas';
    }
}
