<?php
// Verifica a corrida encadeada: motorista com passageiro no carro so recebe/aceita a proxima depois de 20% do trajeto,
// uma so na fila, a nova nao comeca antes de finalizar a atual, e motorista ocupado comum NAO recebe oferta. Tudo dentro de transacao.
// Rodar: php artisan tinker scripts/verifica-encadeada.php
use App\Domains\Drivers\Services\DriverLocationService;
use App\Domains\Payments\Services\DriverWalletService;
use App\Domains\Rides\Services\{RideChainService, RideOfferService, RideService};
use App\Events\{DriverLocationUpdated, RideOfferReceived, RideStatusChanged};
use App\Models\{City, Driver, Ride, User};
use Illuminate\Support\Facades\{DB, Event, Http, Redis};

DB::beginTransaction();
Event::fake([RideStatusChanged::class, DriverLocationUpdated::class, RideOfferReceived::class]);
Http::fake(['exp.host/*' => Http::response(['data' => []], 200)]);
$ok = 0; $falhas = [];
$check = function (string $nome, bool $cond, $extra = '') use (&$ok, &$falhas) {
    if ($cond) { $ok++; echo "  ok  $nome\n"; } else { $falhas[] = $nome; echo "  FALHOU  $nome ".(is_scalar($extra) ? $extra : json_encode($extra))."\n"; }
};
$http = function (callable $c) { try { return [200, $c()]; } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { return [$e->getStatusCode(), $e->getMessage()]; } };
$driverId = null;
try {
    $drv = Driver::withoutGlobalScopes()->whereHas('user')->firstOrFail();
    $driverId = (string) $drv->user_id;
    $user = User::withoutGlobalScopes()->findOrFail($driverId);
    $pass = User::withoutGlobalScopes()->where('role', 'passenger')->where('tenant_id', $user->tenant_id)->first() ?? User::withoutGlobalScopes()->where('role', 'passenger')->firstOrFail();
    $city = City::withoutGlobalScopes()->findOrFail($drv->city_id);
    app()->instance('currentTenantId', $user->tenant_id);
    $drv->forceFill(['online' => true, 'background_check_status' => 'approved', 'subscription_status' => 'active'])->save();
    $user->forceFill(['status' => 'active'])->save();
    app(DriverWalletService::class)->creditManual($driverId, 500, 'teste', 'verifica');
    // corridas antigas do banco de desenvolvimento nao podem atrapalhar (a transacao desfaz isto no fim)
    Ride::withoutGlobalScopes()->where('driver_id', $driverId)->whereIn('status', ['accepted', 'driver_arrived', 'in_progress'])->update(['status' => 'completed']);

    $loc = app(DriverLocationService::class);
    $svc = app(RideService::class);
    $chain = app(RideChainService::class);
    $offers = app(RideOfferService::class);
    config(['rides.chain.enabled' => true, 'rides.chain.min_progress' => 0.20, 'rides.chain.max_pickup_km' => 5.0]);

    $oA = [-29.2300, -51.8700]; $dA = [-29.2500, -51.9000];
    $mkRide = fn (string $status, array $o, array $d, ?string $driver = null) => Ride::query()->create([
        'tenant_id' => $user->tenant_id, 'city_id' => $city->id, 'passenger_id' => $pass->id, 'driver_id' => $driver, 'status' => $status,
        'origin_lat' => $o[0], 'origin_lng' => $o[1], 'destination_lat' => $d[0], 'destination_lng' => $d[1],
        'estimated_fare' => 20, 'requested_at' => now(), 'started_at' => $status === 'in_progress' ? now() : null]);
    $em = fn (float $f) => [$oA[0] + ($dA[0] - $oA[0]) * $f, $oA[1] + ($dA[1] - $oA[1]) * $f];
    $mover = function (float $f) use ($drv, $loc, $em, $user) { [$la, $ln] = $em($f); $drv->forceFill(['current_lat' => $la, 'current_lng' => $ln])->save(); $loc->update($drv->fresh(), $la, $ln); $user->unsetRelation('driver'); };
    $nasListas = fn (Ride $r) => collect($loc->findNearby($city->id, (float) $r->origin_lat, (float) $r->origin_lng))->first(fn ($c) => $c['driver']->user_id === $driverId);

    // A em andamento, motorista ocupado
    $A = $mkRide('in_progress', $oA, $dA, $driverId);
    $loc->setStatus($driverId, 'busy');
    $B = $mkRide('searching', [-29.2510, -51.9010], [-29.2400, -51.8800]);

    // --- antes de 20%
    $mover(0.10);
    $check('com 10% do trajeto: nao pode encadear', $chain->eligibleRide($drv->fresh()) === null);
    $check('com 10%: motorista ocupado NAO recebe oferta (nem pela lista de reserva)', $nasListas($B) === null);
    $offers->setPending($driverId, $B, 45);
    [$s] = $http(fn () => $svc->accept($B, $user));
    $check('com 10%: aceitar a proxima e recusado (422)', $s === 422, $s);
    $offers->clearPending($driverId);

    // --- depois de 20%
    $mover(0.30);
    $c = $nasListas($B);
    $check('com 30%: entra na lista de candidatos da proxima corrida', $c !== null && ($c['chained_after'] ?? null) === $A->id, $c ? array_keys($c) : null);
    $longe = $mkRide('searching', [-29.40, -52.10], [-29.41, -52.11]);
    $check('embarque longe do destino atual (>5 km): nao e oferecido', $nasListas($longe) === null);

    $offers->setPending($driverId, $B, 45);
    $r = $svc->accept($B, $user);
    $check('aceita a proxima: fica "accepted" ligada a corrida atual', $r->status === 'accepted' && $r->follows_ride_id === $A->id && $r->driver_id === $driverId, $r->only(['status', 'follows_ride_id']));
    $check('motorista continua ocupado (nao fica livre)', Redis::get("driver:{$driverId}:status") === 'busy');
    $check('a corrida atual segue em andamento', $A->fresh()->status === 'in_progress');

    // --- so uma na fila
    $C = $mkRide('searching', [-29.2505, -51.9005], [-29.2420, -51.8820]);
    $check('com uma ja na fila: nao e candidato a outra', $chain->eligibleRide($drv->fresh()) === null && $nasListas($C) === null);
    $offers->setPending($driverId, $C, 45);
    [$s] = $http(fn () => $svc->accept($C, $user));
    $check('segunda na fila e recusada (422)', $s === 422, $s);
    $offers->clearPending($driverId);

    // --- a nova so comeca depois de finalizar a atual
    [$s] = $http(fn () => $svc->arrive($B->fresh(), $user));
    $check('nao chega na proxima antes de finalizar a atual (422)', $s === 422, $s);
    $svc->complete($A->fresh(), $user);
    $check('finalizar a atual: motorista continua ocupado por causa da fila', Redis::get("driver:{$driverId}:status") === 'busy' && $A->fresh()->status === 'awaiting_payment');
    $ativa = Ride::query()->where('driver_id', $driverId)->whereIn('status', ['accepted', 'driver_arrived', 'in_progress'])->pluck('id')->all();
    $check('a proxima passa a ser a corrida ativa do motorista', $ativa === [$B->id], $ativa);
    $svc->arrive($B->fresh(), $user);
    $svc->start($B->fresh(), $user);
    $svc->complete($B->fresh(), $user);
    $check('sem mais nada na fila: motorista volta a ficar livre', Redis::get("driver:{$driverId}:status") === 'available');

    // --- regra desligavel
    $A2 = $mkRide('in_progress', $oA, $dA, $driverId);
    $loc->setStatus($driverId, 'busy'); $mover(0.40);
    $B2 = $mkRide('searching', [-29.2510, -51.9010], [-29.2400, -51.8800]);
    config(['rides.chain.enabled' => false]);
    $check('regra desligada: ninguem encadeia', $chain->eligibleRide($drv->fresh()) === null && $nasListas($B2) === null);
    config(['rides.chain.enabled' => true]);
    $check('regra ligada de novo: volta a valer', $chain->eligibleRide($drv->fresh())?->id === $A2->id);

    // --- cancelamento da proxima nao libera o motorista que ainda esta na atual
    $offers->setPending($driverId, $B2, 45);
    $svc->accept($B2, $user);
    $svc->cancel($B2->fresh(), $pass, 'desisti');
    $check('passageiro da fila cancela: motorista segue ocupado com a atual', Redis::get("driver:{$driverId}:status") === 'busy' && $A2->fresh()->status === 'in_progress');
} catch (\Throwable $e) {
    $falhas[] = 'EXCECAO: '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine();
    echo '  EXCECAO '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine()."\n";
} finally {
    DB::rollBack();
    // o estado do motorista no Redis nao e desfeito pela transacao
    if ($driverId) { Redis::set("driver:{$driverId}:status", 'available'); }
}
echo "\nENCADEADA: $ok ok, ".count($falhas)." falhas\n";
