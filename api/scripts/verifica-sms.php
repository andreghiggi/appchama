<?php
// Verifica o envio de SMS do codigo de acesso (sem enviar nada de verdade: as chamadas ao fornecedor sao simuladas).
// Rodar: php artisan tinker scripts/verifica-sms.php   (dentro de transacao, desfeito no fim)
use App\Domains\Auth\Services\{OtpService, SmsGateway};
use App\Models\{OtpCode, Tenant};
use Illuminate\Support\Facades\{DB, Http, RateLimiter};

DB::beginTransaction();
$ok = 0; $falhas = [];
$check = function (string $nome, bool $cond, $extra = '') use (&$ok, &$falhas) {
    if ($cond) { $ok++; echo "  ok  $nome\n"; } else { $falhas[] = $nome; echo "  FALHOU  $nome ".(is_scalar($extra) ? $extra : json_encode($extra))."\n"; }
};
try {
    $tenant = Tenant::query()->where('slug', 'vaide55')->firstOrFail();
    $svc = app(OtpService::class);
    config(['services.otp.test_accounts' => '51900000001:500001']);
    config(['services.sms.twilio' => ['account_sid' => 'ACteste', 'auth_token' => 'tok', 'from' => '+15005550006', 'messaging_service_sid' => null]]);

    $check('telefone vira formato internacional', SmsGateway::e164('(51) 99912-3456') === '+5551999123456' && SmsGateway::e164('5551999123456') === '+5551999123456');

    // provider log: nao chama o fornecedor
    config(['services.sms.provider' => 'log']);
    Http::fake();
    $svc->send('51988887777', 'vaide55');
    Http::assertNothingSent();
    $check('com provider "log" nada e enviado e o codigo e gravado', OtpCode::query()->where('tenant_id', $tenant->id)->where('phone', '51988887777')->exists());

    // provider twilio: envia 1 SMS com o codigo, para o numero certo
    config(['services.sms.provider' => 'twilio']);
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
    $svc->send('51988887777', 'vaide55');
    $codigo = OtpCode::query()->where('tenant_id', $tenant->id)->where('phone', '51988887777')->latest()->value('code');
    $enviado = null;
    Http::assertSent(function ($req) use (&$enviado) { $enviado = $req; return true; });
    $check('twilio: 1 chamada, destino +55, remetente e o codigo na mensagem', $enviado && str_contains($enviado->url(), '/Accounts/ACteste/Messages.json')
        && ($enviado['To'] ?? '') === '+5551988887777' && ($enviado['From'] ?? '') === '+15005550006' && str_contains((string) ($enviado['Body'] ?? ''), (string) $codigo), $enviado?->data());

    // conta de teste (codigo fixo): nao gasta SMS
    Http::fake(['api.twilio.com/*' => Http::response([], 201)]);
    $svc->send('51900000001', 'vaide55');
    Http::assertNothingSent();
    $check('conta de teste com codigo fixo nao envia SMS', OtpCode::query()->where('phone', '51900000001')->value('code') === '500001');

    // fornecedor fora do ar: o codigo e apagado e a pessoa recebe aviso simples (503)
    Http::fake(['api.twilio.com/*' => Http::response(['message' => 'erro'], 500)]);
    $codigoAntes = OtpCode::query()->where('phone', '51977776666')->count();
    $erro = null;
    try { $svc->send('51977776666', 'vaide55'); } catch (\Throwable $e) { $erro = $e; }
    $check('falha do fornecedor: aviso claro e nenhum codigo fica valendo', $erro instanceof \Symfony\Component\HttpKernel\Exception\HttpException && $erro->getStatusCode() === 503
        && str_contains($erro->getMessage(), 'Não foi possível enviar o SMS') && OtpCode::query()->where('phone', '51977776666')->count() === $codigoAntes, $erro?->getMessage());

    // configuracao incompleta
    config(['services.sms.twilio' => ['account_sid' => '', 'auth_token' => '', 'from' => '', 'messaging_service_sid' => '']]);
    $erro2 = null;
    try { $svc->send('51966665555', 'vaide55'); } catch (\Throwable $e) { $erro2 = $e; }
    $check('twilio sem configuracao: recusa em vez de fingir que enviou', $erro2 !== null);
} catch (\Throwable $e) {
    $check('execucao sem excecao', false, $e->getMessage());
} finally {
    DB::rollBack();
    foreach (['51988887777', '51977776666', '51966665555', '51900000001', ''] as $t) { RateLimiter::clear('tel:'.$t); }
}
echo "\nSMS: $ok ok, ".count($falhas)." falhas\n";
