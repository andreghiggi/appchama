<?php
// Verifica o adicional "por fora" do motorista (parada/espera/distancia): pedir, passageiro aceitar/discordar, limites, isolamento e que NAO mexe no valor da corrida. Tudo dentro de transacao, desfeito no fim.
// Rodar: php artisan tinker scripts/verifica-adicionais.php
use App\Domains\Rides\Services\RideExtraService;
use App\Models\{City, Ride, RideExtra, SupportTicket, User};
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\{DB, Http};

DB::beginTransaction();
$ok = 0; $falhas = [];
$check = function (string $nome, bool $cond, $extra = '') use (&$ok, &$falhas) {
    if ($cond) { $ok++; echo "  ok  $nome\n"; } else { $falhas[] = $nome; echo "  FALHOU  $nome ".(is_scalar($extra) ? $extra : json_encode($extra))."\n"; }
};
$http = function (callable $c) { try { return [200, $c()]; } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { return [$e->getStatusCode(), $e->getMessage()]; } };
try {
    $pass = User::withoutGlobalScopes()->where('role', 'passenger')->firstOrFail();
    $drv = User::withoutGlobalScopes()->where('role', 'driver')->where('tenant_id', $pass->tenant_id)->first() ?? User::withoutGlobalScopes()->where('role', 'driver')->firstOrFail();
    $outro = User::withoutGlobalScopes()->where('role', 'driver')->where('id', '!=', $drv->id)->first();
    $city = City::withoutGlobalScopes()->firstOrFail();
    app()->instance('currentTenantId', $pass->tenant_id);
    Http::swap(new Factory()); Http::fake(['exp.host/*' => Http::response(['data' => []], 200)]);
    $svc = app(RideExtraService::class);

    $ride = Ride::query()->create(['tenant_id' => $pass->tenant_id, 'city_id' => $city->id, 'passenger_id' => $pass->id, 'driver_id' => $drv->id, 'status' => 'in_progress',
        'origin_lat' => -29.23, 'origin_lng' => -51.87, 'destination_lat' => -29.24, 'destination_lng' => -51.88, 'estimated_fare' => 20.00, 'final_fare' => 20.00, 'requested_at' => now()]);

    // --- pedir
    $e = $svc->create($ride, $drv, 8.50, 'stop', 'Parar na farmacia');
    $check('motorista registra adicional pendente', $e->status === 'pending' && (float) $e->amount === 8.5 && $e->reason === 'stop', $e->toArray());
    $check('NAO muda o valor da corrida nem o pagamento', (float) $ride->fresh()->final_fare === 20.0 && $ride->fresh()->payment_status === $ride->payment_status);
    [$s] = $http(fn () => $svc->create($ride, $drv, 5, 'wait', null));
    $check('nao abre outro enquanto o passageiro nao respondeu (422)', $s === 422);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'exp.host') && ($r[0]['data']['type'] ?? null) === 'ride_extra' && ($r[0]['data']['extra_id'] ?? null) === $e->id);

    // --- regras de quem pode / quando
    [$s] = $http(fn () => $svc->create($ride, $pass, 5, 'wait', null));
    $check('passageiro nao pode cobrar adicional (403)', $s === 403);
    if ($outro) { [$s] = $http(fn () => $svc->create($ride, $outro, 5, 'wait', null)); $check('outro motorista nao cobra na corrida alheia (403)', $s === 403); }
    foreach ([0.5, 250, -3] as $v) { [$s] = $http(fn () => $svc->create($ride, $drv, $v, 'wait', null)); $check("valor $v fora do limite e recusado (422)", $s === 422); }
    [$s] = $http(fn () => $svc->create($ride, $drv, 5, 'xx', null));
    $check('motivo invalido e recusado (422)', $s === 422);

    // --- passageiro concorda
    [$s] = $http(fn () => $svc->accept($ride, $drv, $e));
    $check('motorista nao responde pelo passageiro (404)', $s === 404);
    $a = $svc->accept($ride, $pass, $e);
    $check('passageiro aceita: fica registrado', $a->status === 'accepted' && $a->responded_at !== null);
    [$s] = $http(fn () => $svc->accept($ride, $pass, $e));
    $check('nao responde duas vezes (422)', $s === 422);

    // --- passageiro discorda -> chamado automatico
    $e2 = $svc->create($ride, $drv, 12, 'wait', null);
    $d = $svc->dispute($ride, $pass, $e2);
    $t = SupportTicket::query()->where('user_id', $pass->id)->where('ride_id', $ride->id)->latest('created_at')->first();
    $check('discordar marca "disputed" e abre chamado ligado a corrida', $d->status === 'disputed' && $t && $t->category === 'ride' && str_contains($t->messages()->first()->body, '12,00'), $t?->toArray());

    // --- motorista desfaz pendente
    $e3 = $svc->create($ride, $drv, 6, 'distance', 'Mais uma quadra');
    $c = $svc->cancel($ride, $drv, $e3);
    $check('motorista desfaz adicional ainda pendente', $c->status === 'canceled');
    [$s] = $http(fn () => $svc->cancel($ride, $drv, $a));
    $check('nao desfaz adicional ja respondido (422)', $s === 422);

    // --- limite por corrida e estado da corrida
    $n = RideExtra::query()->where('ride_id', $ride->id)->where('status', '!=', 'canceled')->count();
    for ($i = $n; $i < RideExtra::MAX_PER_RIDE; $i++) { $x = $svc->create($ride, $drv, 2, 'wait', null); $svc->accept($ride, $pass, $x); }
    [$s] = $http(fn () => $svc->create($ride, $drv, 2, 'wait', null));
    $check('limita a '.RideExtra::MAX_PER_RIDE.' adicionais por corrida (422)', $s === 422);
    $ride->update(['status' => 'awaiting_payment']);
    [$s] = $http(fn () => $svc->create($ride, $drv, 2, 'wait', null));
    $check('so com a viagem em andamento (422)', $s === 422);

    // --- listagem e isolamento
    $check('passageiro e motorista da corrida veem a lista', $svc->listFor($ride, $pass)->count() >= 4 && $svc->listFor($ride, $drv)->count() >= 4);
    if ($outro) { [$s] = $http(fn () => $svc->listFor($ride, $outro)); $check('estranho nao ve os adicionais (404)', $s === 404); }
    $check('rotas de adicional registradas', collect(app('router')->getRoutes())->map->uri()->filter(fn ($u) => str_contains($u, '/extras'))->count() === 5);
} catch (\Throwable $e) {
    $falhas[] = 'EXCECAO: '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine();
    echo '  EXCECAO '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine()."\n";
} finally {
    DB::rollBack();
}
echo "\nADICIONAIS: $ok ok, ".count($falhas)." falhas\n";
