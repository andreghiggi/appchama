<?php
// Verifica a regra "cadastro de motorista so em algumas cidades; passageiro em todas".
// Rodar: php artisan tinker scripts/verifica-cidades.php   (tudo dentro de transacao, desfeito no fim)
use App\Models\{City, Tenant, User};
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};

DB::beginTransaction();
Storage::fake('local');
$ok = 0; $falhas = [];
$check = function (string $nome, bool $cond) use (&$ok, &$falhas) {
    if ($cond) { $ok++; echo "  ok  $nome\n"; } else { $falhas[] = $nome; echo "  FALHOU  $nome\n"; }
};
try {
    $vai = Tenant::query()->where('slug', 'vaide55')->firstOrFail();
    $demo = Tenant::query()->where('slug', 'chama-demo')->firstOrFail();
    $mk = fn (string $nome, bool $driver, float $lat, float $lng) => City::query()->withoutGlobalScopes()->create([
        'tenant_id' => $vai->id, 'name' => $nome, 'state' => 'RS', 'active' => true, 'rides_enabled' => true,
        'driver_signup_enabled' => $driver, 'departure_address' => "$nome, RS", 'departure_lat' => $lat, 'departure_lng' => $lng,
        'base_fare' => 12, 'price_per_km' => 0.9, 'price_per_min' => 0.5, 'min_fare' => 8,
    ]);
    $enc = $mk('Encantado', true, -29.2356, -51.8711);
    $roc = $mk('Roca Sales', true, -29.2894, -51.8708);
    $muc = $mk('Mucum', false, -29.1642, -51.8694);
    $nov = $mk('Nova Brescia', false, -29.2197, -52.0289);

    $chama = function (string $query) {
        foreach (['currentTenantId', 'tenantFromHost'] as $k) { app()->forgetInstance($k); }
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $req = Request::create('https://api.vaide55.com.br/api/v1/cities'.$query, 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $res = app(\Illuminate\Contracts\Http\Kernel::class)->handle($req);
        return [$res->getStatusCode(), collect(json_decode($res->getContent(), true) ?: [])->pluck('name')->sort()->values()->all()];
    };

    echo "== 1. Listas de cidades\n";
    [$s1, $todas] = $chama('');
    [$s2, $mot] = $chama('?for=driver');
    $check('passageiro ve as 4 cidades (corridas)', $s1 === 200 && $todas === ['Encantado', 'Mucum', 'Nova Brescia', 'Roca Sales']);
    $check('cadastro de motorista lista so Encantado e Roca Sales', $s2 === 200 && $mot === ['Encantado', 'Roca Sales']);
    $check('padrao de cidade nova/existente e aberto (nao quebra o que ja existia)', (bool) City::query()->withoutGlobalScopes()->where('tenant_id', $demo->id)->first()?->driver_signup_enabled);
    $check('Mucum continua aceitando corridas mesmo sem cadastro de motorista', $muc->acceptsRides() && ! $muc->acceptsDriverSignup());

    echo "== 2. Cadastro de motorista no servidor\n";
    $dados = fn (City $c, string $tel) => ['name' => 'Motorista Teste', 'phone' => $tel, 'password' => 'segredo123', 'city_id' => $c->id,
        'cnh_number' => '12345678900', 'cnh_expiry' => now()->addYear()->toDateString(), 'vehicle_plate' => 'ABC1D23'];
    $svc = app(\App\Domains\Drivers\Services\DriverSelfRegistrationService::class);
    $recusa = function (City $c, string $tel) use ($svc, $dados) {
        try { $svc->register($dados($c, $tel), UploadedFile::fake()->create("cnh.pdf", 20, "application/pdf"), 'vaide55'); return false; }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { return $e->getStatusCode() === 422 && str_contains($e->getMessage(), 'não está aberto'); }
    };
    $check('Mucum: servidor recusa o cadastro (422)', $recusa($muc, '51999000001'));
    $check('Nova Brescia: servidor recusa o cadastro (422)', $recusa($nov, '51999000002'));
    $u = $svc->register($dados($enc, '51999000003'), UploadedFile::fake()->create("cnh.pdf", 20, "application/pdf"), 'vaide55');
    $check('Encantado: cadastro de motorista funciona (fica pendente)', $u->role === 'driver' && $u->status === 'pending' && $u->driver?->city_id === $enc->id);
    $u2 = $svc->register($dados($roc, '51999000004'), UploadedFile::fake()->create("cnh.pdf", 20, "application/pdf"), 'vaide55');
    $check('Roca Sales: cadastro de motorista funciona', $u2->driver?->city_id === $roc->id);
    $enc->forceFill(['active' => false])->save();
    $check('cidade desativada fecha o cadastro mesmo com o interruptor ligado', $recusa($enc, '51999000005'));
} finally {
    DB::rollBack();
}
echo "\nCIDADES: $ok ok, ".count($falhas)." falhas\n";
foreach ($falhas as $f) { echo " - $f\n"; }
