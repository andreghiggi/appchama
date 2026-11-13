<?php
use App\Models\{Tenant, User};
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

DB::beginTransaction();
$ok = 0; $falhas = [];
try {
    $super = User::query()->where('is_platform_admin', true)->first();
    $vai = Tenant::query()->where('slug', 'vaide55')->first();
    $limpar = function () { foreach (['currentTenantId', 'tenantFromHost'] as $k) { app()->forgetInstance($k); } };
    $limpar();
    \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

    $try = function (string $nome, callable $fn) use (&$ok, &$falhas) {
        try { $fn(); $ok++; echo "  ok  $nome\n"; }
        catch (\Throwable $e) { $falhas[] = $nome; echo "  FALHOU  $nome -> ".get_class($e).': '.substr($e->getMessage(), 0, 200)."\n"; }
    };
    $preencher = function ($c, array $dados) { foreach ($dados as $k => $v) { $c = $c->set("data.$k", $v); } return $c; };
    $exige = function (bool $cond, string $msg) { if (! $cond) throw new \RuntimeException($msg); };

    $try('lista de Marcas renderiza', function () use ($super, $exige) { $c = Livewire::actingAs($super)->test(\App\Filament\Resources\Tenants\Pages\ListTenants::class)->assertSuccessful(); $exige(str_contains($c->html(), 'Vaide55'), 'nao mostrou a marca'); });
    $try('formulario de nova Marca renderiza', fn () => Livewire::actingAs($super)->test(\App\Filament\Resources\Tenants\Pages\CreateTenant::class)->assertSuccessful());
    $try('edicao da marca Vaide55 renderiza com o nome', function () use ($super, $vai, $exige) { $c = Livewire::actingAs($super)->test(\App\Filament\Resources\Tenants\Pages\EditTenant::class, ['record' => $vai->getKey()])->assertSuccessful(); $exige(str_contains($c->html(), 'api.vaide55.com.br') && str_contains($c->html(), 'adm.vaide55.com.br'), 'nao mostrou os enderecos'); });
    $try('lista de Administradores renderiza', fn () => Livewire::actingAs($super)->test(\App\Filament\Resources\BrandUsers\Pages\ListBrandUsers::class)->assertSuccessful());

    $try('criar administrador pela tela (super-admin)', function () use ($super, $vai, $preencher, $exige) {
        $c = $preencher(Livewire::actingAs($super)->test(\App\Filament\Resources\BrandUsers\Pages\CreateBrandUser::class),
            ['tenant_id' => $vai->id, 'name' => 'Teste Tela', 'email' => 'tela@vaide.test', 'role' => 'admin'])->call('create');
        $exige(! $c->errors()->all(), 'erros: '.implode(' | ', $c->errors()->all()));
        $exige(User::query()->where('email', 'tela@vaide.test')->where('tenant_id', $vai->id)->where('role', 'admin')->exists(), 'usuario nao foi criado');
    });
    $try('e-mail repetido mostra erro na tela e nao duplica', function () use ($super, $vai, $preencher, $exige) {
        $c = $preencher(Livewire::actingAs($super)->test(\App\Filament\Resources\BrandUsers\Pages\CreateBrandUser::class),
            ['tenant_id' => $vai->id, 'name' => 'Outro', 'email' => 'tela@vaide.test', 'role' => 'support'])->call('create');
        $exige($c->errors()->has('data.email'), 'nao mostrou erro de e-mail');
        $exige(User::query()->where('email', 'tela@vaide.test')->count() === 1, 'duplicou');
    });

    $adm = User::query()->where('email', 'tela@vaide.test')->first();
    $limpar(); app()->instance('currentTenantId', $vai->id); app()->instance('tenantFromHost', true);

    $try('admin da marca cria suporte (marca e papel forcados pelo servidor)', function () use ($adm, $vai, $preencher, $exige) {
        $c = $preencher(Livewire::actingAs($adm)->test(\App\Filament\Resources\BrandUsers\Pages\CreateBrandUser::class),
            ['name' => 'Suporte Tela', 'email' => 'suptela@vaide.test', 'role' => 'support'])->call('create');
        $u = User::query()->where('email', 'suptela@vaide.test')->first();
        $exige($u && $u->tenant_id === $vai->id && $u->role === 'support', 'criou errado: '.json_encode($u?->only(['tenant_id', 'role'])).' erros: '.implode(' | ', $c->errors()->all()));
    });
    $try('admin da marca NAO consegue criar administrador (so suporte)', function () use ($adm, $preencher, $exige) {
        $c = $preencher(Livewire::actingAs($adm)->test(\App\Filament\Resources\BrandUsers\Pages\CreateBrandUser::class),
            ['name' => 'Tentativa', 'email' => 'tentativa@vaide.test', 'role' => 'admin'])->call('create');
        $exige(User::query()->where('email', 'tentativa@vaide.test')->count() === 0, 'criou um admin!');
    });
    $try('lista do admin da marca renderiza e mostra a equipe', function () use ($adm, $exige) {
        $c = Livewire::actingAs($adm)->test(\App\Filament\Resources\BrandUsers\Pages\ListBrandUsers::class)->assertSuccessful();
        $exige(str_contains($c->html(), 'suptela@vaide.test'), 'nao mostrou a equipe');
    });
    $try('admin da marca nao abre a tela de Marcas', function () use ($adm) {
        \Illuminate\Support\Facades\Auth::setUser($adm);
        if (\App\Filament\Resources\Tenants\TenantResource::canViewAny()) throw new \RuntimeException('abriu');
    });
} finally { DB::rollBack(); }
echo "\nTELAS: $ok ok, ".count($falhas)." falhas\n";
foreach ($falhas as $f) echo " - $f\n";
