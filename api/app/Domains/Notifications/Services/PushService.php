<?php

namespace App\Domains\Notifications\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envia notificação push pelo serviço do Expo (que repassa ao FCM/APNs).
 * Falha de envio nunca derruba a corrida: só entra no log.
 */
class PushService
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    /** @param  array<string, mixed>  $data */
    public function toUser(string $userId, string $title, string $body, array $data = [], string $canal = 'default', ?int $ttlSegundos = null): void
    {
        $tokens = DeviceToken::withoutGlobalScopes()->where('user_id', $userId)->pluck('token')->all();
        if ($tokens === []) {
            return;
        }

        $messages = array_map(fn (string $token): array => [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sound' => 'default',
            'priority' => 'high',
            'channelId' => $canal,
            ...($ttlSegundos ? ['ttl' => $ttlSegundos] : []),
        ], $tokens);

        try {
            $response = Http::timeout(5)->acceptJson()->post(self::ENDPOINT, $messages);
            $this->limparTokensInvalidos($tokens, (array) $response->json('data', []));
        } catch (\Throwable $e) {
            Log::warning('push.falhou', ['user_id' => $userId, 'erro' => $e->getMessage()]);
        }
    }

    /**
     * @param  list<string>  $tokens
     * @param  array<int, mixed>  $tickets
     */
    private function limparTokensInvalidos(array $tokens, array $tickets): void
    {
        foreach ($tickets as $i => $ticket) {
            if (is_array($ticket) && ($ticket['status'] ?? '') === 'error'
                && ($ticket['details']['error'] ?? '') === 'DeviceNotRegistered' && isset($tokens[$i])) {
                DeviceToken::withoutGlobalScopes()->where('token', $tokens[$i])->delete();
            }
        }
    }
}
