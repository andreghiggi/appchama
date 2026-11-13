<?php
// Rodada de revisao do PAINEL: abre todas as telas como administrador de marca (Vai de 55) e como plataforma,
// confere que cada um so ve o que e seu e que os formularios novos salvam.
// Rodar: php artisan tinker scripts/verifica-paineis.php   (tudo dentro de transacao, desfeito no fim)
use App\Models\{City, Tenant, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Livewire\Livewire;

DB::beginTransaction();
Storage::fake('local');
$ok = 0; $falhas = [];
$try = function (string $nome, callable $fn) use (&$ok, &$falhas) {
    try { $fn(); $ok++; echo "  ok  $nome\n"; }
    catch (\Throwable $e) { $falhas[] = $nome; echo "  FALHOU  $nome -> ".get_class($e).': '.substr($e->getMessage(), 0, 220)."\n"; }
};
$exige = function (bool $c, string $m) { if (! $c) throw new \RuntimeException($m); };
try {
    $super = User::query()->where('is_platform_admin', true)->firstOrFail();
    $vai = Tenant::query()->where('slug', 'vaide55')->firstOrFail();
    $demo = Tenant::query()->where('slug', 'chama-demo')->firstOrFail();
    $vai->forceFill(['name' => 'Vai de 55', 'tagline' => 'Viu passar? So chamar!', 'primary_color' => '#FCD200',
        'theme' => ['ink' => '#111111', 'ink2' => '#1E1E1E', 'amber' => '#FCD200', 'amberDark' => '#C9A700', 'onAmber' => '#111111'],
        'logo_url' => '/brands/vaide55/logo.png', 'contacts' => ['site' => 'https://vaide55.com.br']])->save();
    \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    $limpar = function () { foreach (['currentTenantId', 'tenantFromHost'] as $k) { app()->forgetInstance($k); } };
    $comoMarca = function (Tenant $t) use ($limpar) { $limpar(); app()->instance('currentTenantId', $t->id); app()->instance('tenantFromHost', true); };

    // dados de teste: uma cidade, um motorista e um administrador em cada marca
    $mkCidade = fn (Tenant $t, string $nome) => City::query()->withoutGlobalScopes()->create([
        'tenant_id' => $t->id, 'name' => $nome, 'state' => 'RS', 'active' => true, 'rides_enabled' => false, 'driver_signup_enabled' => true,
        'base_fare' => 12, 'price_per_km' => 0.9, 'price_per_min' => 0.5, 'min_fare' => 8,
    ]);
    $cVai = $mkCidade($vai, 'ZZVai Cidade'); $cDemo = $mkCidade($demo, 'ZZDemo Cidade');
    $reg = app(\App\Domains\Drivers\Services\DriverSelfRegistrationService::class);
    $cnh = fn () => UploadedFile::fake()->create('cnh.pdf', 20, 'application/pdf');
    $mkMot = fn (Tenant $t, City $c, string $nome, string $tel) => $reg->register(['name' => $nome, 'phone' => $tel, 'password' => 'segredo123', 'city_id' => $c->id,
        'cnh_number' => '123', 'cnh_expiry' => now()->addYear()->toDateString(), 'vehicle_plate' => 'ZZV'.substr($tel, -4)], $cnh(), $t->slug);
    $mVai = $mkMot($vai, $cVai, 'ZZVai Motorista', '51988000001'); $mDemo = $mkMot($demo, $cDemo, 'ZZDemo Motorista', '51988000002');
    $adm = User::query()->create(['name' => 'Admin Smoke', 'email' => 'smoke@vaide.test', 'password' => 'segredo123', 'role' => 'admin', 'tenant_id' => $vai->id, 'status' => 'active']);
    $sup = User::query()->create(['name' => 'Suporte Smoke', 'email' => 'smokesup@vaide.test', 'password' => 'segredo123', 'role' => 'support', 'tenant_id' => $vai->id, 'status' => 'active']);

    $paginas = [
        'Cidades' => \App\Filament\Resources\Cities\Pages\ListCities::class,
        'Motoristas' => \App\Filament\Resources\Drivers\Pages\ListDrivers::class,
        'Veiculos' => \App\Filament\Resources\Vehicles\Pages\ListVehicles::class,
        'Corridas' => \App\Filament\Resources\Rides\Pages\ListRides::class,
        'Pagamentos de corridas' => \App\Filament\Resources\RidePayments\Pages\ListRidePayments::class,
        'Saques' => \App\Filament\Resources\Withdrawals\Pages\ListWithdrawals::class,
        'Chamados' => \App\Filament\Resources\SupportTickets\Pages\ListSupportTickets::class,
        'Administradores' => \App\Filament\Resources\BrandUsers\Pages\ListBrandUsers::class,
    ];

    echo "== 1. Administrador do Vai de 55: abre cada tela\n";
    $comoMarca($vai);
    foreach ($paginas as $nome => $classe) {
        $try("admin da marca abre: $nome", fn () => Livewire::actingAs($adm)->test($classe)->assertSuccessful());
    }
    $try('admin da marca abre o Painel inicial (Dashboard)', fn () => Livewire::actingAs($adm)->test(\Filament\Pages\Dashboard::class)->assertSuccessful());
    foreach (['StatsOverview', 'OperationsOverviewWidget', 'FinanceHealthWidget'] as $w) {
        $try("widget do painel: $w", fn () => Livewire::actingAs($adm)->test('App\\Filament\\Widgets\\'.$w)->assertSuccessful());
    }

    echo "== 2. Isolamento: o admin do Vai de 55 so ve dados do Vai de 55\n";
    $comoMarca($vai);
    $try('Cidades: ve a do Vai e NAO ve a do AppChama', function () use ($adm, $exige) {
        $h = Livewire::actingAs($adm)->test(\App\Filament\Resources\Cities\Pages\ListCities::class)->html();
        $exige(str_contains($h, 'ZZVai Cidade') && ! str_contains($h, 'ZZDemo Cidade'), 'vazou ou nao mostrou');
    });
    $try('Motoristas: ve o do Vai e NAO ve o do AppChama', function () use ($adm, $exige) {
        $h = Livewire::actingAs($adm)->test(\App\Filament\Resources\Drivers\Pages\ListDrivers::class)->html();
        $exige(str_contains($h, 'ZZVai Motorista') && ! str_contains($h, 'ZZDemo Motorista'), 'vazou ou nao mostrou');
    });
    $try('Veiculos: so do Vai de 55', function () use ($adm, $exige) {
        $h = Livewire::actingAs($adm)->test(\App\Filament\Resources\Vehicles\Pages\ListVehicles::class)->html();
        $exige(str_contains($h, 'ZZV0001') && ! str_contains($h, 'ZZV0002'), 'placa vazou ou nao mostrou');
    });
    $try('Administradores: ve a equipe do Vai e nao a de outra marca', function () use ($adm, $exige) {
        $h = Livewire::actingAs($adm)->test(\App\Filament\Resources\BrandUsers\Pages\ListBrandUsers::class)->html();
        $exige(str_contains($h, 'smokesup@vaide.test') && ! str_contains($h, 'admin@chama.app'), 'lista errada');
    });

    echo "== 3. Permissoes do admin e do suporte da marca\n";
    $try('admin da marca NAO acessa a tela Marcas', function () use ($adm, $exige) {
        \Illuminate\Support\Facades\Auth::setUser($adm);
        $exige(! \App\Filament\Resources\Tenants\TenantResource::canViewAny(), 'Marcas visivel');
    });
    $try('admin da marca abre Pagamentos Asaas (conta Asaas e da propria marca)', function () use ($adm, $comoMarca, $vai) {
        $comoMarca($vai);
        Livewire::actingAs($adm)->test(\App\Filament\Pages\ManagePaymentSettings::class)->assertSuccessful();
    });
    $try('admin da marca NAO consegue alterar a comissao da plataforma', function () use ($adm, $comoMarca, $vai, $exige) {
        $comoMarca($vai);
        $antes = (string) Tenant::query()->find($vai->id)->platform_commission_pct;
        $c = Livewire::actingAs($adm)->test(\App\Filament\Pages\ManagePaymentSettings::class);
        try { $c->set('data.platform_commission_pct', '99')->call('save'); } catch (\Throwable $e) { /* campo bloqueado: tambem vale */ }
        $exige((string) Tenant::query()->find($vai->id)->platform_commission_pct === $antes, 'comissao foi alterada pelo admin da marca');
    });
    $try('suporte da marca abre Corridas e Chamados e NAO ve Administradores', function () use ($sup, $exige, $comoMarca, $vai) {
        $comoMarca($vai);
        Livewire::actingAs($sup)->test(\App\Filament\Resources\Rides\Pages\ListRides::class)->assertSuccessful();
        Livewire::actingAs($sup)->test(\App\Filament\Resources\SupportTickets\Pages\ListSupportTickets::class)->assertSuccessful();
        \Illuminate\Support\Facades\Auth::setUser($sup);
        $exige(! \App\Filament\Resources\BrandUsers\BrandUserResource::canViewAny(), 'suporte ve Administradores');
    });

    echo "== 4. Formularios: o admin consegue alterar o que precisa\n";
    $comoMarca($vai);
    $try('criar cidade pela tela (fica na marca certa, cadastro de motorista ligado por padrao)', function () use ($adm, $vai, $exige) {
        $c = Livewire::actingAs($adm)->test(\App\Filament\Resources\Cities\Pages\CreateCity::class);
        foreach (['name' => 'ZZ Nova Cidade', 'state' => 'RS', 'active' => true, 'rides_enabled' => false, 'driver_signup_enabled' => true,
                  'base_fare' => '10', 'price_per_km' => '1.1', 'price_per_min' => '0.4', 'min_fare' => '7'] as $k => $v) { $c = $c->set("data.$k", $v); }
        $c->call('create');
        $exige(! $c->errors()->all(), 'erros: '.implode(' | ', $c->errors()->all()));
        $n = City::query()->withoutGlobalScopes()->where('name', 'ZZ Nova Cidade')->first();
        $exige($n && $n->tenant_id === $vai->id && $n->driver_signup_enabled === true, 'nao criou na marca certa');
    });
    $try('editar cidade: desligar cadastro de motorista e salvar', function () use ($adm, $cVai, $exige) {
        $c = Livewire::actingAs($adm)->test(\App\Filament\Resources\Cities\Pages\EditCity::class, ['record' => $cVai->getKey()])
            ->set('data.driver_signup_enabled', false)->set('data.price_per_km', '1.25')->call('save');
        $exige(! $c->errors()->all(), 'erros: '.implode(' | ', $c->errors()->all()));
        $f = City::query()->withoutGlobalScopes()->find($cVai->id);
        $exige($f->driver_signup_enabled === false && (float) $f->price_per_km === 1.25, 'nao salvou');
    });
    $try('admin da marca NAO consegue abrir a cidade de outra marca (edicao)', function () use ($adm, $cDemo, $exige) {
        try { Livewire::actingAs($adm)->test(\App\Filament\Resources\Cities\Pages\EditCity::class, ['record' => $cDemo->getKey()])->assertSuccessful(); }
        catch (\Throwable $e) { return; }   // erro/404 e o esperado
        $exige(false, 'abriu cidade de outra marca');
    });

    echo "== 5. Plataforma (AppChama) com a marca Vai de 55 selecionada\n";
    $limpar(); app()->instance('currentTenantId', $vai->id);
    foreach (['Cidades' => \App\Filament\Resources\Cities\Pages\ListCities::class, 'Motoristas' => \App\Filament\Resources\Drivers\Pages\ListDrivers::class,
              'Marcas' => \App\Filament\Resources\Tenants\Pages\ListTenants::class] as $nome => $classe) {
        $try("plataforma abre: $nome", fn () => Livewire::actingAs($super)->test($classe)->assertSuccessful());
    }
    $try('plataforma: Pagamentos Asaas do Vai de 55 abre', fn () => Livewire::actingAs($super)->test(\App\Filament\Pages\ManagePaymentSettings::class)->assertSuccessful());
    $try('plataforma editando a marca Vai de 55: campos de identidade aparecem preenchidos', function () use ($super, $vai, $exige) {
        $h = Livewire::actingAs($super)->test(\App\Filament\Resources\Tenants\Pages\EditTenant::class, ['record' => $vai->getKey()])->html();
        $exige(str_contains($h, 'Vai de 55') && (str_contains($h, '#FCD200') || str_contains($h, '#fcd200')), 'identidade nao apareceu no formulario');
    });
    $try('plataforma salva a identidade (lema, contato e link legal) sem perder o resto', function () use ($super, $vai, $exige) {
        $c = Livewire::actingAs($super)->test(\App\Filament\Resources\Tenants\Pages\EditTenant::class, ['record' => $vai->getKey()])
            ->set('data.contacts.email', 'suporte@vaide55.test')->set('data.legal.privacy_url', 'https://vaide55.com.br/privacidade')->call('save');
        $exige(! $c->errors()->all(), 'erros: '.implode(' | ', $c->errors()->all()));
        $f = Tenant::query()->find($vai->id);
        $exige(($f->contacts['email'] ?? null) === 'suporte@vaide55.test' && $f->name === 'Vai de 55' && ($f->brandConfig()['theme']['amber'] ?? null) === '#FCD200' && ($f->brandConfig()['theme']['ink'] ?? null) === '#111111', 'perdeu dados ao salvar: nome='.json_encode($f->name).' tema='.json_encode($f->theme).' contatos='.json_encode($f->contacts).' primaria='.$f->primary_color);
    });
} finally {
    DB::rollBack();
}
echo "\nPAINEIS: $ok ok, ".count($falhas)." falhas\n";
foreach ($falhas as $f) { echo " - $f\n"; }
