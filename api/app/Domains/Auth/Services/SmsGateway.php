<?php

namespace App\Domains\Auth\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Envio de SMS. O fornecedor e escolhido por SMS_PROVIDER no .env, sem mexer no codigo:
 *  - log    : nao envia nada (desenvolvimento e contas de teste)
 *  - twilio : Twilio (Messages API). Exige TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN e TWILIO_FROM (numero) ou TWILIO_MESSAGING_SERVICE_SID.
 * Para um fornecedor novo basta acrescentar um metodo e um "case" abaixo.
 */
class SmsGateway
{
    public function provider(): string
    {
        return strtolower((string) config('services.sms.provider', 'log'));
    }

    /** Verdadeiro quando o codigo realmente sai por SMS (nao e so log). */
    public function realmenteEnvia(): bool
    {
        return $this->provider() !== 'log';
    }

    /** @throws RuntimeException quando o fornecedor recusa ou nao responde */
    public function send(string $phone, string $text): void
    {
        match ($this->provider()) {
            'log' => null,
            'twilio' => $this->twilio($phone, $text),
            default => throw new RuntimeException('Fornecedor de SMS desconhecido: '.$this->provider()),
        };
    }

    /** Telefone como o app manda (so digitos, com ou sem 55) para o formato internacional +55DDDNUMERO. */
    public static function e164(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($d) <= 11) {
            $d = '55'.$d;
        }

        return '+'.$d;
    }

    private function twilio(string $phone, string $text): void
    {
        $sid = (string) config('services.sms.twilio.account_sid');
        $token = (string) config('services.sms.twilio.auth_token');
        $from = (string) config('services.sms.twilio.from');
        $servico = (string) config('services.sms.twilio.messaging_service_sid');
        if ($sid === '' || $token === '' || ($from === '' && $servico === '')) {
            throw new RuntimeException('Twilio sem configuracao completa (conta, token e numero/servico de mensagens).');
        }

        $corpo = ['To' => self::e164($phone), 'Body' => $text] + ($servico !== '' ? ['MessagingServiceSid' => $servico] : ['From' => $from]);
        $r = Http::withBasicAuth($sid, $token)->asForm()->timeout(10)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $corpo);

        if (! $r->successful()) {
            // nao grava o telefone inteiro no log
            throw new RuntimeException('Twilio recusou o envio ('.$r->status().'): '.($r->json('message') ?? 'sem detalhe'));
        }
    }
}
