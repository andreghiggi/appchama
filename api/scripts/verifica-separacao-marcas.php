<?php
// Verifica que cada marca so recebe os proprios cadastros.
// Rodar: php artisan tinker scripts/verifica-separacao-marcas.php   (dentro de transacao, desfeito no fim)
use App\Models\{Tenant, TenantDomain, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, RateLimiter};

DB::beginTransaction();
$ok = 0; $falhas = [];
$check = function (string $nome, bool $cond) use (&$ok, &$falhas) {
    if ($cond) { $ok++; echo "  ok  $nome\n"; } else { $falhas[] = $nome; echo "  FALHOU  $nome\n"; }
};
$limpa = function () { RateLimiter::clear('tel:51911110009'); RateLimiter::clear('ip:10.9.8.5'); };
$limpa();
try {
    $vai = Tenant::query()->where('slug', 'vaide55')->firstOrFail();
    $demo = Tenant::query()->where('slug', 'chama-demo')->firstOrFail();
    TenantDomain::query()->firstOrCreate(['host' => 'api.vaide55.com.br'], ['tenant_id' => $vai->id, 'type' => 'api']);
    config(['services.sms.provider' => 'log']);
    $chama = function (string $host, string $slug, ?string $header = null) {
        foreach (['currentTenantId', 'tenantFromHost'] as $k) { app()->forgetInstance($k); }
        app('auth')->forgetGuards();
        $server = ['HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '10.9.8.5'];
        if ($header) { $server['HTTP_X_TENANT_SLUG'] = $header; }
        $res = app(\Illuminate\Contracts\Http\Kernel::class)->handle(Request::create("https://$host/api/v1/auth/otp/send", 'POST', ['phone' => '51911110009', 'tenant_slug' => $slug], [], [], $server));
        return $res->getStatusCode();
    };
    $check('vaide55 no endereco do vaide55', $chama('api.vaide55.com.br', 'vaide55', 'vaide55') === 200);
    $check('outra marca no endereco do vaide55 e recusada', $chama('api.vaide55.com.br', 'chama-demo') === 422);
    $check('cabecalho de outra marca e recusado', $chama('api.vaide55.com.br', 'vaide55', 'chama-demo') === 422);
    $check('appchama no endereco da plataforma', $chama('apichama.agilizeerp.com.br', 'chama-demo', 'chama-demo') === 200);
    $check('vaide55 pelo endereco do appchama e recusado', $chama('apichama.agilizeerp.com.br', 'vaide55') === 422);
    $check('nada do teste caiu na outra marca', ! DB::table('otp_codes')->where('phone', '51911110009')->whereNotIn('tenant_id', [$vai->id, $demo->id])->exists());
} finally {
    DB::rollBack();
    $limpa();
}
echo "\nSEPARACAO MARCAS: $ok ok, ".count($falhas)." falhas\n";
