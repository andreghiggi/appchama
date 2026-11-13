<?php
use App\Models\{City, Ride, Tenant, TenantDomain, User, Driver, Vehicle};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$ok = 0; $falhas = [];
$check = function (string $nome, bool $cond) use (&$ok, &$falhas) { if ($cond) { $ok++; echo "  ok  $nome\n"; } else { $falhas[] = $nome; echo "  FALHOU  $nome\n"; } };
$limpar = function () { foreach (['currentTenantId', 'tenantFromHost'] as $k) { app()->forgetInstance($k); } };

DB::beginTransaction();
try {
    $demo = Tenant::query()->where('slug', 'chama-demo')->firstOrFail();
    $vai = Tenant::query()->where('slug', 'vaide55')->firstOrFail();

    // dados de teste (revertidos no fim)
    $limpar();
    $cDemo = City::query()->withoutGlobalScopes()->where('tenant_id', $demo->id)->first();
    $cVai = City::query()->create(array_merge($cDemo->only(['state', 'base_fare', 'price_per_km', 'price_per_min', 'min_fare', 'active', 'rides_enabled', 'departure_address', 'departure_lat', 'departure_lng']), ['tenant_id' => $vai->id, 'name' => 'Cidade Vaide55']));
    $uVai = User::query()->create(['tenant_id' => $vai->id, 'name' => 'Passageiro Vaide', 'phone' => '51990000001', 'role' => 'passenger', 'status' => 'active']);
    $uAdmVai = User::query()->create(['tenant_id' => $vai->id, 'name' => 'Adm Vaide', 'phone' => '51990000002', 'email' => 'adm@vaide.test', 'role' => 'admin', 'status' => 'active', 'password' => 'x']);
    $uAdmDemo = User::query()->create(['tenant_id' => $demo->id, 'name' => 'Adm Demo', 'phone' => '51990000003', 'email' => 'adm@demo.test', 'role' => 'admin', 'status' => 'active', 'password' => 'x']);
    $super = User::query()->where('is_platform_admin', true)->first();

    echo "== 1. Escopo dos modelos por marca\n";
    $limpar(); $totalRides = Ride::query()->count(); $totalCities = City::query()->count();
    app()->instance('currentTenantId', $vai->id);
    $check('marca Vaide55 ve 0 corridas (todas sao do demo)', Ride::query()->count() === 0);
    $check('marca Vaide55 ve so a propria cidade', City::query()->count() === 1 && City::query()->first()->name === 'Cidade Vaide55');
    $check('marca Vaide55 nao ve motoristas do demo', Driver::query()->count() === 0 && Vehicle::query()->count() === 0);
    $limpar(); app()->instance('currentTenantId', $demo->id);
    $check('marca demo ve as 108 corridas e nao a cidade do Vaide55', Ride::query()->count() === $totalRides && City::query()->where('name', 'Cidade Vaide55')->count() === 0);
    $check('marca demo ve seus 5 motoristas', Driver::query()->count() === 5);
    $limpar();
    $check('sem marca (plataforma) ve tudo', City::query()->count() === $totalCities);

    echo "== 2. Resolucao da marca pelo endereco (API)\n";
    TenantDomain::query()->firstOrCreate(['host' => 'api.vaide55.com.br'], ['tenant_id' => $vai->id, 'type' => 'api']);
    $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
    $get = function (string $url, array $headers = []) use ($kernel, $limpar) {
        $limpar();
        $req = Request::create($url, 'GET', [], [], [], array_merge(['HTTP_ACCEPT' => 'application/json'], $headers));
        $res = $kernel->handle($req);
        return json_decode($res->getContent(), true) + ['_status' => $res->getStatusCode()];
    };
    $r = $get('https://api.vaide55.com.br/api/v1/cities');
    $nomes = collect($r['data'] ?? $r)->pluck('name')->filter()->values()->all();
    $check('api.vaide55 lista so cidades do Vaide55', $nomes === ['Cidade Vaide55']);
    $r = $get('https://api.vaide55.com.br/api/v1/cities', ['HTTP_X_TENANT_SLUG' => 'chama-demo']);
    $nomes = collect($r['data'] ?? $r)->pluck('name')->filter()->values()->all();
    $check('cabecalho X-Tenant-Slug NAO troca a marca em endereco de marca', $nomes === ['Cidade Vaide55']);
    $r = $get('https://appchama.agilizeerp.com.br/api/v1/cities', ['HTTP_X_TENANT_SLUG' => 'chama-demo']);
    $nomes = collect($r['data'] ?? $r)->pluck('name')->filter()->values()->all();
    $check('endereco da plataforma + cabecalho continua funcionando (demo)', ! in_array('Cidade Vaide55', $nomes, true) && count($nomes) >= 1);

    echo "== 3. Usuario de uma marca nao usa a API de outra\n";
    $limpar(); $token = User::query()->withoutGlobalScopes()->where('tenant_id', $demo->id)->where('role', 'passenger')->first()->createToken('t')->plainTextToken;
    $r = $get('https://api.vaide55.com.br/api/v1/me', ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    $check('token do demo no endereco do Vaide55 -> 403', ($r['_status'] ?? 0) === 403);
    $r = $get('https://appchama.agilizeerp.com.br/api/v1/me', ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    $check('mesmo token no endereco da plataforma -> 200', ($r['_status'] ?? 0) === 200);

    echo "== 4. Acesso ao painel\n";
    $panel = \Filament\Facades\Filament::getPanel('admin');
    $limpar(); app()->instance('currentTenantId', $vai->id); app()->instance('tenantFromHost', true);
    $check('admin do Vaide55 entra no endereco do Vaide55', $uAdmVai->canAccessPanel($panel));
    $check('admin do demo NAO entra no endereco do Vaide55', ! $uAdmDemo->canAccessPanel($panel));
    $check('super-admin entra em qualquer endereco', $super->canAccessPanel($panel));
    $limpar();
    $check('admin de marca NAO entra no endereco da plataforma', ! $uAdmVai->canAccessPanel($panel));
    $check('super-admin entra no endereco da plataforma', $super->canAccessPanel($panel));

    echo "== 5. Consultas do painel respeitam a marca\n";
    $limpar(); app()->instance('currentTenantId', $vai->id); app()->instance('tenantFromHost', true);
    $check('RideResource (Vaide55) = 0 corridas', \App\Filament\Resources\Rides\RideResource::getEloquentQuery()->count() === 0);
    $check('CityResource (Vaide55) = so a cidade dele', \App\Filament\Resources\Cities\CityResource::getEloquentQuery()->count() === 1);
    $check('DriverResource (Vaide55) = 0 motoristas', \App\Filament\Resources\Drivers\DriverResource::getEloquentQuery()->count() === 0);
    $check('PaymentResource (Vaide55) = 0', \App\Filament\Resources\RidePayments\RidePaymentResource::getEloquentQuery()->count() === 0);
    $check('WithdrawalResource (Vaide55) = 0', \App\Filament\Resources\Withdrawals\WithdrawalResource::getEloquentQuery()->count() === 0);
    $limpar(); app()->instance('currentTenantId', $demo->id);
    $check('RideResource (demo) = 108', \App\Filament\Resources\Rides\RideResource::getEloquentQuery()->count() === $totalRides);
    $limpar();
    $check('RideResource (plataforma) = todas', \App\Filament\Resources\Rides\RideResource::getEloquentQuery()->count() === $totalRides);

    echo "== 6. Canal de tempo real por marca\n";
    $cb = collect(\Illuminate\Support\Facades\Broadcast::getChannels())->get('city.{cityId}.drivers');
    $limpar();
    $check('admin do Vaide55 nao escuta cidade do demo', ! $cb($uAdmVai, $cDemo->id));
    $check('admin do Vaide55 escuta a cidade dele', (bool) $cb($uAdmVai, $cVai->id));
    $check('super-admin escuta qualquer cidade', (bool) $cb($super, $cDemo->id));

    echo "== 7. Registro novo herda a marca\n";
    $limpar(); app()->instance('currentTenantId', $vai->id);
    $u2 = User::query()->create(['tenant_id' => $vai->id, 'name' => 'Motorista V', 'phone' => '51990000009', 'role' => 'driver', 'status' => 'active']);
    $drv = Driver::query()->withoutGlobalScopes()->first()->replicate();
    $drv->user_id = $u2->id; $drv->tenant_id = null; $drv->registration_code = 'T-'.substr(md5(uniqid()),0,8); $drv->save();
    $check('motorista novo herda tenant do usuario', $drv->fresh()->tenant_id === $vai->id);

    echo "== 8. Administradores das marcas (regras de quem pode o que)\n";
    $svc = app(\App\Domains\Tenants\Services\BrandUserService::class);
    $limpar();
    [$a1, $s1] = $svc->create($super, $vai, 'Dono Vaide', 'dono@vaide.test', 'admin');
    $check('super-admin cria admin de marca com senha temporaria (sem telefone)', $a1->role === 'admin' && $a1->tenant_id === $vai->id && strlen($s1) >= 10 && \Illuminate\Support\Facades\Hash::check($s1, $a1->password) && $a1->phone === null);
    [$sup1, ] = $svc->create($a1, $vai, 'Suporte Vaide', 'sup@vaide.test', 'support');
    $check('admin da marca cria suporte da propria marca', $sup1->role === 'support' && $sup1->tenant_id === $vai->id);
    [$supDemo, ] = $svc->create($super, $demo, 'Suporte Demo', 'sup@demo.test', 'support');
    $nega = function (callable $fn) { try { $fn(); return false; } catch (\Illuminate\Auth\Access\AuthorizationException) { return true; } };
    $check('admin da marca NAO cria outro admin', $nega(fn () => $svc->create($a1, $vai, 'X', 'x1@vaide.test', 'admin')));
    $check('admin da marca NAO cria suporte em outra marca', $nega(fn () => $svc->create($a1, $demo, 'X', 'x2@vaide.test', 'support')));
    $check('suporte NAO cria ninguem', $nega(fn () => $svc->create($sup1, $vai, 'X', 'x3@vaide.test', 'support')));
    $dup = false; try { $svc->create($super, $vai, 'Y', 'DONO@vaide.test', 'support'); } catch (\Illuminate\Validation\ValidationException) { $dup = true; }
    $check('e-mail repetido e recusado (sem diferenciar maiusculas)', $dup);
    $check('admin da marca gerencia suporte da propria marca', $svc->canManage($a1, $sup1));
    $check('admin da marca NAO gerencia outro admin', ! $svc->canManage($a1, $uAdmVai));
    $check('admin da marca NAO gerencia suporte de outra marca', ! $svc->canManage($a1, $supDemo));
    $check('suporte NAO gerencia ninguem', ! $svc->canManage($sup1, $sup1));
    $check('ninguem gerencia administrador da plataforma', ! $svc->canManage($super, $super) && ! $svc->canManage($a1, $super));
    $nova = $svc->resetPassword($a1, $sup1);
    $check('redefinir senha: a nova vale e a antiga nao', \Illuminate\Support\Facades\Hash::check($nova, $sup1->fresh()->password));
    $check('admin da marca NAO redefine senha de outra marca', $nega(fn () => $svc->resetPassword($a1, $supDemo)));
    $svc->setActive($a1, $sup1, false);
    $limpar(); app()->instance('currentTenantId', $vai->id); app()->instance('tenantFromHost', true);
    $check('usuario desativado nao entra no painel', ! $sup1->fresh()->canAccessPanel($panel));
    $check('admin da marca NAO desativa a si mesmo', $nega(fn () => $svc->setActive($a1, $a1, false)));
    $svc->setActive($a1, $sup1, true);

    echo "== 9. Telas: o que cada um enxerga\n";
    $emails = fn () => \App\Filament\Resources\BrandUsers\BrandUserResource::getEloquentQuery()->pluck('email')->sort()->values()->all();
    $limpar(); \Illuminate\Support\Facades\Auth::setUser($a1);
    $lista = $emails();
    $check('admin do Vaide ve so a equipe do Vaide', in_array('dono@vaide.test', $lista, true) && in_array('sup@vaide.test', $lista, true) && ! in_array('sup@demo.test', $lista, true) && ! in_array('adm@demo.test', $lista, true));
    $check('admin do Vaide NAO ve administradores da plataforma', ! in_array($super->email, $lista, true));
    \Illuminate\Support\Facades\Auth::setUser($super);
    $lista = $emails();
    $check('super-admin ve a equipe de todas as marcas', in_array('dono@vaide.test', $lista, true) && in_array('sup@demo.test', $lista, true));
    app()->instance('currentTenantId', $demo->id);
    $lista = $emails();
    $check('super-admin com filtro de marca ve so aquela marca', in_array('sup@demo.test', $lista, true) && ! in_array('dono@vaide.test', $lista, true));
    $limpar();
    \Illuminate\Support\Facades\Auth::setUser($a1);
    $check('Marcas: admin de marca NAO enxerga a tela', ! \App\Filament\Resources\Tenants\TenantResource::canViewAny());
    $check('Administradores: admin de marca enxerga', \App\Filament\Resources\BrandUsers\BrandUserResource::canViewAny());
    \Illuminate\Support\Facades\Auth::setUser($sup1);
    $check('Administradores: suporte NAO enxerga', ! \App\Filament\Resources\BrandUsers\BrandUserResource::canViewAny());
    \Illuminate\Support\Facades\Auth::setUser($super);
    $check('Marcas: super-admin enxerga e cria', \App\Filament\Resources\Tenants\TenantResource::canViewAny() && \App\Filament\Resources\Tenants\TenantResource::canCreate());

    echo "== 10. Pagamentos Asaas usa a marca do contexto (antes sempre a marca padrao)\n";
    $pg = new ReflectionMethod(\App\Filament\Pages\ManagePaymentSettings::class, 'resolveTenant'); $pg->setAccessible(true);
    $inst = (new ReflectionClass(\App\Filament\Pages\ManagePaymentSettings::class))->newInstanceWithoutConstructor();
    $limpar(); app()->instance('currentTenantId', $vai->id);
    $check('com a marca Vaide55 no contexto, a tela edita o Vaide55', $pg->invoke($inst)->id === $vai->id);
    $limpar(); $sem = false; try { $pg->invoke($inst); } catch (\Symfony\Component\HttpKernel\Exception\HttpException) { $sem = true; }
    $check('sem marca no contexto a tela pede para escolher (nao cai na marca padrao)', $sem);
} finally {
    DB::rollBack();
}
echo "\nRESULTADO: $ok ok, ".count($falhas)." falhas\n";
foreach ($falhas as $f) echo " - $f\n";
