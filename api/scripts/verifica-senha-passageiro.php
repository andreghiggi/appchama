<?php
// Verifica o login do passageiro por senha, o mesmo telefone como passageiro e motorista, e o envio do codigo.
// Rodar: php artisan tinker scripts/verifica-senha-passageiro.php   (tudo dentro de transacao, desfeito no fim)
use App\Models\{Tenant, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, RateLimiter};

DB::beginTransaction();
$ok = 0; $falhas = [];
$check = function (string $nome, bool $cond) use (&$ok, &$falhas) {
    if ($cond) { $ok++; echo "  ok  $nome\n"; } else { $falhas[] = $nome; echo "  FALHOU  $nome\n"; }
};
$limpa = function () { foreach (['51911110001', '51911110002', '51911110003'] as $t) { RateLimiter::clear('tel:'.$t); } RateLimiter::clear('ip:10.9.8.6'); };
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
    $check('so com cadastro de motorista: orienta criar a conta de passageiro', $s === 422 && str_contains($j['message'] ?? '', 'Criar conta'));

    $tm = $m->createToken('t')->plainTextToken;
    [$s, $j] = $chama('POST', '/api/v1/rides', [], $tm);
    $check('motorista pedindo corrida recebe mensagem (nao fica vazia)', $s === 403 && str_contains($j['message'] ?? '', 'motorista'));
    [$s] = $chama('PUT', '/api/v1/auth/password', ['password' => 'segredo1', 'password_confirmation' => 'segredo1'], $tm);
    $check('motorista nao troca senha por esta rota', $s === 403);

    echo "== Mesmo telefone nos dois perfis\n";
    config(['services.sms.provider' => 'log', 'services.sms.expose_debug_code' => false, 'services.otp.test_accounts' => '']);
    $limpa();
    $codigo = fn (string $tel) => (string) \App\Models\OtpCode::query()->where('tenant_id', $vai->id)->where('phone', $tel)->latest()->value('code');
    $pede = fn (string $tel, array $extra = []) => $chama('POST', '/api/v1/auth/otp/send', ['phone' => $tel, 'tenant_slug' => 'vaide55'] + $extra);

    [$s, $j] = $pede('51911110002', ['intent' => 'register']);
    $check('telefone de motorista pode se cadastrar como passageiro', $s === 200 && $j['user_exists'] === false);
    $primeiro = $codigo('51911110002');
    $pede('51911110002');
    $check('pedir de novo repete o mesmo codigo (os dois SMS valem)', $primeiro !== '' && $codigo('51911110002') === $primeiro);
    [$s, $j] = $chama('POST', '/api/v1/auth/otp/verify', ['phone' => '51911110002', 'code' => $primeiro, 'tenant_slug' => 'vaide55', 'role' => 'passenger',
        'name' => 'Motorista Passageiro', 'email' => 'dois@exemplo.com', 'cpf' => '52998224725', 'home_address' => 'Rua A', 'home_address_number' => '1',
        'home_postal_code' => '95960000', 'home_city' => 'Encantado', 'home_state' => 'RS']);
    $check('cria a conta de passageiro separada da de motorista', $s === 200 && ($j['user']['role'] ?? '') === 'passenger' && ($j['user']['id'] ?? '') !== $m->id);
    $check('conta de motorista continua intacta', $m->fresh()->role === 'driver' && $m->fresh()->name === 'Motorista Teste');
    [$s] = $chama('PUT', '/api/v1/auth/password', ['password' => 'passag123', 'password_confirmation' => 'passag123'], $j['token'] ?? '');
    [$s2, $j2] = $entra('51911110002', 'passag123');
    $check('entra no app do passageiro com a senha de passageiro', $s === 200 && $s2 === 200 && ($j2['user']['role'] ?? '') === 'passenger');

    $antes = $codigo('51911110002');
    [$s, $j] = $pede('51911110002', ['intent' => 'register']);
    $check('quem ja tem conta de passageiro nao recebe SMS no cadastro', $s === 200 && $j['user_exists'] === true && $codigo('51911110002') === $antes);
    [$s, $j] = $pede('51911110003', ['intent' => 'login']);
    $check('quem nao tem conta nao recebe SMS na entrada', $s === 200 && $j['user_exists'] === false && $codigo('51911110003') === '');

    $cidade = \App\Models\City::query()->withoutGlobalScopes()->create(['tenant_id' => $vai->id, 'name' => 'Cidade do Teste', 'state' => 'RS', 'active' => true,
        'rides_enabled' => true, 'driver_signup_enabled' => true, 'departure_address' => 'Centro', 'departure_lat' => -29.2356, 'departure_lng' => -51.8711,
        'base_fare' => 12, 'price_per_km' => 0.9, 'price_per_min' => 0.5, 'min_fare' => 8]);

        \Illuminate\Support\Facades\Storage::fake('local');
        $dados = ['name' => 'Passageiro Motorista', 'phone' => '51911110001', 'password' => 'motor123', 'city_id' => $cidade->id,
            'cnh_number' => '12345678900', 'cnh_expiry' => now()->addYear()->toDateString(), 'vehicle_plate' => 'ABC1D23'];
        $reg = fn () => app(\App\Domains\Drivers\Services\DriverSelfRegistrationService::class)
            ->register($dados, \Illuminate\Http\UploadedFile::fake()->create('cnh.pdf', 10, 'application/pdf'), 'vaide55');
        $novo = $reg();
        $check('telefone de passageiro pode se cadastrar como motorista', $novo->role === 'driver' && $novo->id !== $p->id);
        [$s, $j] = $chama('POST', '/api/v1/auth/driver/login', ['phone' => '51911110001', 'password' => 'motor123', 'tenant_slug' => 'vaide55']);
        $check('entra no app do motorista com a senha de motorista', $s === 200 && ($j['user']['role'] ?? '') === 'driver');
        [$s] = $entra('51911110001', 'segredo1');
        $check('senha de passageiro segue valendo', $s === 200);
        $repetido = false;
        try { $reg(); } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $repetido = $e->getStatusCode() === 422; }
        $check('segundo cadastro de motorista com o mesmo telefone e recusado', $repetido);
} finally {
    DB::rollBack();
    $limpa();
}
echo "\nSENHA PASSAGEIRO: $ok ok, ".count($falhas)." falhas\n";
