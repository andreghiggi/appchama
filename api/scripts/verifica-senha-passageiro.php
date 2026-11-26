<?php
// Verifica o login do passageiro por senha e a mensagem quando um motorista tenta pedir corrida.
// Rodar: php artisan tinker scripts/verifica-senha-passageiro.php   (tudo dentro de transacao, desfeito no fim)
use App\Models\{Tenant, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, RateLimiter};

DB::beginTransaction();
$ok = 0; $falhas = [];
$check = function (string $nome, bool $cond) use (&$ok, &$falhas) {
    if ($cond) { $ok++; echo "  ok  $nome\n"; } else { $falhas[] = $nome; echo "  FALHOU  $nome\n"; }
};
$limpa = function () { foreach (['51911110001', '51911110002'] as $t) { RateLimiter::clear('tel:'.$t); } RateLimiter::clear('ip:10.9.8.6'); };
$limpa();
try {
    $vai = Tenant::query()->where('slug', 'vaide55')->firstOrFail();
    $p = User::query()->create(['tenant_id' => $vai->id, 'name' => 'Passageiro Teste', 'phone' => '51911110001', 'role' => 'passenger', 'status' => 'active']);
    $m = User::query()->create(['tenant_id' => $vai->id, 'name' => 'Motorista Teste', 'phone' => '51911110002', 'role' => 'driver', 'status' => 'active']);

    $chama = function (string $metodo, string $uri, array $dados, ?string $token = null) {
        foreach (['currentTenantId', 'tenantFromHost'] as $k) { app()->forgetInstance($k); }
        app('auth')->forgetGuards();
        $server = ['HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '10.9.8.6', 'HTTP_X_TENANT_SLUG' => 'vaide55'];
        if ($token) { $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token; }
        $res = app(\Illuminate\Contracts\Http\Kernel::class)->handle(Request::create('https://api.vaide55.com.br'.$uri, $metodo, $dados, [], [], $server));
        return [$res->getStatusCode(), json_decode($res->getContent(), true)];
    };
    $entra = fn (string $tel, string $senha) => $chama('POST', '/api/v1/auth/passenger/login', ['phone' => $tel, 'password' => $senha, 'tenant_slug' => 'vaide55']);

    [$s, $j] = $entra('51911110001', 'segredo1');
    $check('sem senha criada: orienta entrar com codigo', $s === 422 && str_contains($j['message'] ?? '', 'código'));

    $token = $p->createToken('t')->plainTextToken;
    [$s] = $chama('PUT', '/api/v1/auth/password', ['password' => '123', 'password_confirmation' => '123'], $token);
    $check('senha curta e recusada', $s === 422);
    [$s] = $chama('PUT', '/api/v1/auth/password', ['password' => 'segredo1', 'password_confirmation' => 'segredo1'], $token);
    $check('passageiro cria a senha', $s === 200);
    [$s, $j] = $entra('51911110001', 'segredo1');
    $check('entra com telefone e senha', $s === 200 && ! empty($j['token']) && ($j['user']['role'] ?? '') === 'passenger' && ! isset($j['user']['password']));
    [$s] = $entra('51911110001', 'errada99');
    $check('senha errada nao entra', $s === 422);
    [$s, $j] = $entra('51911110002', 'qualquer1');
    $check('motorista no app do passageiro recebe aviso claro', $s === 422 && str_contains($j['message'] ?? '', 'motorista'));

    $tm = $m->createToken('t')->plainTextToken;
    [$s, $j] = $chama('POST', '/api/v1/rides', [], $tm);
    $check('motorista pedindo corrida recebe mensagem (nao fica vazia)', $s === 403 && str_contains($j['message'] ?? '', 'motorista'));
    [$s] = $chama('PUT', '/api/v1/auth/password', ['password' => 'segredo1', 'password_confirmation' => 'segredo1'], $tm);
    $check('motorista nao troca senha por esta rota', $s === 403);
} finally {
    DB::rollBack();
    $limpa();
}
echo "\nSENHA PASSAGEIRO: $ok ok, ".count($falhas)." falhas\n";
