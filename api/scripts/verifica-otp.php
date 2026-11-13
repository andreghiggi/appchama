<?php
// Verifica o codigo de acesso: contas de teste, vazamento e limites.
// Rodar: php artisan tinker scripts/verifica-otp.php   (tudo dentro de transacao, desfeito no fim)
use App\Models\{Tenant, OtpCode};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, RateLimiter};

DB::beginTransaction();
$ok = 0; $falhas = [];
$check = function (string $nome, bool $cond) use (&$ok, &$falhas) {
    if ($cond) { $ok++; echo "  ok  $nome\n"; } else { $falhas[] = $nome; echo "  FALHOU  $nome\n"; }
};
$limites = function () {
    foreach (['51955554444', '51944443333', '51977776666', '51966665555', '51933332222', ''] as $t) { RateLimiter::clear('tel:'.$t); }
    RateLimiter::clear('ip:10.9.8.7');
};
$limites();
try {
    $vai = Tenant::query()->where('slug', 'vaide55')->firstOrFail();
    config(['services.otp.test_accounts' => '(51) 99999-0001:123456, 5199999-0002:654321, 51999990003:12ab56, 51999990004:12345']);
    config(['services.sms.expose_debug_code' => false, 'services.sms.provider' => 'log']);
    $svc = app(\App\Domains\Auth\Services\OtpService::class);

    echo "== 1. Contas de teste\n";
    $check('telefone de teste (com mascara) recebe o codigo fixo', $svc->testCodeFor('(51) 99999-0001') === '123456' && $svc->testCodeFor('51999990001') === '123456');
    $check('aceita com ou sem o 55 na frente', $svc->testCodeFor('5551999990001') === '123456');
    $check('segundo telefone de teste tem o proprio codigo', $svc->testCodeFor('51999990002') === '654321');
    $check('telefone fora da lista nao tem codigo fixo', $svc->testCodeFor('51988887777') === null);
    $check('codigo mal formado na lista e ignorado (nao vira porta aberta)', $svc->testCodeFor('51999990003') === null && $svc->testCodeFor('51999990004') === null);
    config(['services.otp.test_accounts' => '']);
    $vazia = $svc->testCodeFor('51999990001') === null;
    config(['services.otp.test_accounts' => '51999990001:123456']);
    $check('lista vazia nao libera ninguem', $vazia);

    $svc->send('51999990001', 'vaide55');
    $check('send grava o codigo fixo para o telefone de teste', OtpCode::query()->where('tenant_id', $vai->id)->where('phone', '51999990001')->value('code') === '123456');
    $svc->send('51988887777', 'vaide55');
    $real = OtpCode::query()->where('tenant_id', $vai->id)->where('phone', '51988887777')->value('code');
    $check('telefone comum recebe codigo aleatorio de 6 digitos', (bool) preg_match('/^\d{6}$/', (string) $real));

    $chama = function (string $uri, array $dados) {
        foreach (['currentTenantId', 'tenantFromHost'] as $k) { app()->forgetInstance($k); }
        $req = Request::create('https://api.vaide55.com.br'.$uri, 'POST', $dados, [], [], ['HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '10.9.8.7']);
        $res = app(\Illuminate\Contracts\Http\Kernel::class)->handle($req);
        return [$res->getStatusCode(), json_decode($res->getContent(), true), $res->getContent()];
    };

    echo "== 2. Vazamento do codigo\n";
    [$st, $j, $raw] = $chama('/api/v1/auth/otp/send', ['phone' => '51977776666', 'tenant_slug' => 'vaide55']);
    $gerado = (string) OtpCode::query()->where('phone', '51977776666')->latest()->value('code');
    $check('pedir codigo funciona (200)', $st === 200);
    $check('resposta NAO traz o codigo (debug_code) em producao', $st === 200 && ! array_key_exists('debug_code', $j) && $gerado !== '' && ! str_contains($raw, $gerado));
    config(['services.sms.expose_debug_code' => true]);
    [, $j2] = $chama('/api/v1/auth/otp/send', ['phone' => '51966665555', 'tenant_slug' => 'vaide55']);
    $check('so com OTP_EXPOSE_DEBUG_CODE=true o codigo aparece (desenvolvimento)', isset($j2['debug_code']));
    config(['services.sms.expose_debug_code' => false]);

    echo "== 3. Limites de pedidos e tentativas\n";
    $codigos = [];
    for ($i = 1; $i <= 7; $i++) { [$s] = $chama('/api/v1/auth/otp/send', ['phone' => '51955554444', 'tenant_slug' => 'vaide55']); $codigos[] = $s; }
    $check('apos 5 pedidos para o mesmo telefone, bloqueia (429)', array_slice($codigos, 0, 5) === [200, 200, 200, 200, 200] && $codigos[5] === 429 && $codigos[6] === 429);
    $st2 = [];
    for ($i = 1; $i <= 12; $i++) { [$s] = $chama('/api/v1/auth/otp/verify', ['phone' => '51944443333', 'code' => '000000', 'tenant_slug' => 'vaide55']); $st2[] = $s; }
    $check('apos 10 tentativas de codigo, bloqueia (429)', ! in_array(429, array_slice($st2, 0, 10), true) && $st2[10] === 429 && $st2[11] === 429);
    $check('outro telefone nao e afetado pelo bloqueio', $chama('/api/v1/auth/otp/send', ['phone' => '51933332222', 'tenant_slug' => 'vaide55'])[0] === 200);
} finally {
    DB::rollBack();
    $limites();
}
echo "\nOTP: $ok ok, ".count($falhas)." falhas\n";
foreach ($falhas as $f) { echo " - $f\n"; }
