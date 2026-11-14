<?php
// Corrida completa, falando com a API de verdade pelo endereco do Vai de 55:
// cadastro do motorista -> aprovacao -> login -> online; passageiro entra por codigo -> estima -> pede -> motorista aceita,
// chega, inicia e finaliza. Tudo dentro de transacao (desfeito no fim).
// Rodar: php artisan tinker scripts/verifica-corrida.php
use App\Models\{City, Driver, Ride, Tenant, User};
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth, DB, Queue, RateLimiter, Storage};

DB::beginTransaction();
Storage::fake('local');
// Fila falsa: no desenvolvimento ela e sincrona e executaria na hora o reenvio que na producao espera 45 s.
// A busca de motorista e rodada UMA vez, manualmente, como o trabalhador (worker) faria.
Queue::fake();
$ok = 0; $falhas = [];
$check = function (string $nome, bool $cond, string $extra = '') use (&$ok, &$falhas) {
    if ($cond) { $ok++; echo "  ok  $nome\n"; } else { $falhas[] = $nome; echo "  FALHOU  $nome $extra\n"; }
};
$ipsUsados = ['10.20.30.40', '10.20.30.41'];
$limpaLimites = function () use ($ipsUsados) {
    foreach ($ipsUsados as $ip) { RateLimiter::clear('ip:'.$ip); }
    foreach (['51999990001', '51977000001', '51977000099'] as $t) { RateLimiter::clear('tel:'.$t); }
};
$limpaLimites();
try {
    $vai = Tenant::query()->where('slug', 'vaide55')->firstOrFail();
    config(['services.otp.test_accounts' => '51999990001:111111', 'services.sms.expose_debug_code' => false]);

    // cidades do Vai de 55 (Encantado aceita tudo; Mucum so passageiro)
    $mk = fn (string $n, bool $mot, float $lat, float $lng) => City::query()->withoutGlobalScopes()->create([
        'tenant_id' => $vai->id, 'name' => $n, 'state' => 'RS', 'active' => true, 'rides_enabled' => true, 'driver_signup_enabled' => $mot,
        'departure_address' => "Centro, $n, RS", 'departure_lat' => $lat, 'departure_lng' => $lng,
        'base_fare' => 12, 'price_per_km' => 0.9, 'price_per_min' => 0.5, 'min_fare' => 8]);
    $enc = $mk('Encantado', true, -29.2356, -51.8711); $muc = $mk('Mucum', false, -29.1642, -51.8694);

    $api = function (string $metodo, string $uri, array $dados = [], ?string $token = null, array $arquivos = [], string $host = 'api.vaide55.com.br', array $cab = []) {
        foreach (['currentTenantId', 'tenantFromHost'] as $k) { app()->forgetInstance($k); }
        Auth::forgetGuards();
        $srv = array_merge(['HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '10.20.30.40'], $cab);
        if ($token) { $srv['HTTP_AUTHORIZATION'] = 'Bearer '.$token; }
        $req = Request::create("https://$host/api/v1$uri", $metodo, $dados, [], $arquivos, $srv);
        $res = app(\Illuminate\Contracts\Http\Kernel::class)->handle($req);
        return [$res->getStatusCode(), json_decode($res->getContent(), true) ?? [], $res->getContent()];
    };
    $cnh = fn () => UploadedFile::fake()->create('cnh.pdf', 20, 'application/pdf');

    echo "== 1. Cadastro do motorista pelo app\n";
    $reg = fn (City $c, string $tel) => $api('POST', '/auth/driver/register', [
        'tenant_slug' => 'vaide55', 'name' => 'Motorista Teste', 'phone' => $tel, 'password' => 'segredo123', 'password_confirmation' => 'segredo123',
        'city_id' => $c->id, 'cnh_number' => '12345678900', 'cnh_expiry' => now()->addYear()->format('Y-m-d'), 'vehicle_plate' => 'ABC1D23',
        'vehicle_model' => 'Gol', 'vehicle_color' => 'Branco', 'vehicle_year' => '2020',
    ], null, ['cnh_document' => $cnh()]);
    [$s, $j] = $reg($muc, '51977000099');
    $check('Mucum: API recusa cadastro de motorista (422)', $s === 422, "status=$s");
    [$s, $j, $raw] = $reg($enc, '51977000001');
    $check('Encantado: cadastro de motorista aceito', in_array($s, [200, 201], true) && ! empty($j['token']), "status=$s ".substr($raw, 0, 160));
    $drvUser = User::query()->withoutGlobalScopes()->where('phone', '51977000001')->first();
    $check('motorista nasce pendente e na marca Vai de 55', $drvUser && $drvUser->status === 'pending' && $drvUser->tenant_id === $vai->id);

    // aprovacao pelo painel (equivalente) + saldo da carteira (comissao pre-paga)
    $drv = Driver::query()->withoutGlobalScopes()->where('user_id', $drvUser->id)->first();
    app(\App\Domains\Drivers\Services\DriverRegistrationCodeService::class)->approveAppDriver($drv);
    $w = app(\App\Domains\Payments\Services\DriverWalletService::class)->ensureWallet($drv->fresh());
    $w->forceFill(['prepaid_balance' => 50])->save();
    [$s, $j] = $api('POST', '/auth/driver/login', ['phone' => '51977000001', 'tenant_slug' => 'vaide55', 'password' => 'segredo123']);
    $check('motorista aprovado entra com telefone e senha', $s === 200 && ! empty($j['token']), "status=$s");
    $tokM = $j['token'] ?? null;
    [$s] = $api('POST', '/drivers/location', ['lat' => -29.2360, 'lng' => -51.8715], $tokM);
    $check('motorista envia a localizacao', in_array($s, [200, 204], true), "status=$s");
    [$s, $j] = $api('POST', '/drivers/online', ['lat' => -29.2360, 'lng' => -51.8715], $tokM);
    $check('motorista fica online', $s === 200 && ($j['online'] ?? false) === true, "status=$s");

    echo "== 2. Passageiro entra por codigo (conta de teste) e se cadastra\n";
    [$s] = $api('POST', '/auth/otp/send', ['phone' => '51999990001', 'tenant_slug' => 'vaide55']);
    $check('pedir codigo funciona', $s === 200, "status=$s");
    [$s, $j, $raw] = $api('POST', '/auth/otp/verify', [
        'phone' => '51999990001', 'code' => '111111', 'tenant_slug' => 'vaide55', 'name' => 'Passageiro Teste', 'email' => 'passageiro@vaide.test',
        'cpf' => '11144477735', 'role' => 'passenger', 'home_address' => 'Rua Julio de Castilhos', 'home_address_number' => '100',
        'home_postal_code' => '95960000', 'home_city' => 'Encantado', 'home_state' => 'RS', 'home_lat' => -29.2356, 'home_lng' => -51.8711]);
    $check('codigo de teste confirma e cria o passageiro', $s === 200 && ! empty($j['token']), "status=$s ".substr($raw, 0, 200));
    $tokP = $j['token'] ?? null;
    [$s, $j] = $api('GET', '/me', [], $tokP);
    $check('/me devolve o passageiro da marca certa', $s === 200 && ($j['role'] ?? null) === 'passenger' && ($j['tenant_id'] ?? null) === $vai->id, "status=$s");
    [$s, $j] = $api('GET', '/cities', [], $tokP);
    $check('passageiro ve as cidades da marca', $s === 200 && collect($j)->pluck('name')->contains('Encantado') && collect($j)->pluck('name')->contains('Mucum'));
    [$s, $j] = $api('GET', '/brand-config', [], $tokP);
    $check('app le a identidade da marca', $s === 200 && ($j['data']['slug'] ?? null) === 'vaide55');

    echo "== 3. Corrida: estimar, pedir, aceitar, chegar, iniciar, finalizar\n";
    $trajeto = ['city_id' => $enc->id, 'origin_lat' => -29.2356, 'origin_lng' => -51.8711, 'destination_lat' => -29.2400, 'destination_lng' => -51.8760,
        'origin_address' => 'Origem teste', 'destination_address' => 'Destino teste'];
    [$s, $j, $raw] = $api('GET', '/rides/estimate', $trajeto, $tokP);
    $check('estimativa de preco devolve valor', $s === 200 && (float) ($j['estimated_fare'] ?? $j['fare'] ?? $j['data']['estimated_fare'] ?? 0) >= 8, "status=$s ".substr($raw, 0, 220));
    [$s, $j, $raw] = $api('POST', '/rides', $trajeto, $tokP);
    $rideId = $j['id'] ?? $j['ride']['id'] ?? null;
    $check('pedido de corrida criado (202)', $s === 202 && $rideId, "status=$s ".substr($raw, 0, 220));
    if ($rideId) {
        foreach (['currentTenantId', 'tenantFromHost'] as $k) { app()->forgetInstance($k); }   // o worker nao tem marca no contexto
        app()->call([new \App\Jobs\MatchDriverJob($rideId), 'handle']);
    }
    $ride = $rideId ? Ride::query()->withoutGlobalScopes()->find($rideId) : null;
    $check('corrida nasce na marca Vai de 55 e procurando motorista', $ride && $ride->tenant_id === $vai->id && $ride->status === 'searching', 'status='.($ride->status ?? '?'));
    if ($ride) {
        [$s, $j, $raw] = $api('GET', '/drivers/offers', [], $tokM);
        $check('motorista recebe a oferta da corrida', $s === 200 && (($j['ride']['id'] ?? null) === $ride->id), "status=$s ".substr($raw, 0, 200));
        foreach (['accept' => 'accepted', 'arrive' => 'driver_arrived', 'start' => 'in_progress', 'complete' => null] as $acao => $esperado) {
            [$s, $j, $raw] = $api('POST', "/rides/{$ride->id}/$acao", [], $tokM);
            $st = Ride::query()->withoutGlobalScopes()->find($ride->id)->status;
            $check("motorista: $acao (corrida fica '$st')", $s === 200 && ($esperado === null ? in_array($st, ['completed', 'awaiting_payment'], true) : $st === $esperado), "status=$s ".substr($raw, 0, 200));
            if ($s !== 200) { break; }
        }
        [$s, $j] = $api('GET', '/rides', [], $tokP);
        $check('passageiro ve a corrida no historico', $s === 200 && collect($j['data'] ?? $j)->pluck('id')->contains($ride->id));
        [$s] = $api('GET', '/rides/'.$ride->id, [], $tokP);
        $check('passageiro abre os detalhes da corrida', $s === 200, "status=$s");
    }

    echo "== 3b. Motorista cancela: a corrida volta a procurar outro; carrinhos e push\n";
    [$s, $j, $raw] = $api('POST', '/rides', $trajeto, $tokP);
    $rid2 = $j['id'] ?? $j['ride']['id'] ?? null;
    $check('segunda corrida criada', $s === 202 && $rid2, "status=$s ".substr($raw, 0, 160));
    if ($rid2) {
        foreach (['currentTenantId', 'tenantFromHost'] as $k) { app()->forgetInstance($k); }
        app()->call([new \App\Jobs\MatchDriverJob($rid2), 'handle']);
        [$s] = $api('POST', "/rides/$rid2/accept", [], $tokM);
        $check('motorista aceita a segunda corrida', $s === 200, "status=$s");
        [$s, $j, $raw] = $api('POST', "/rides/$rid2/cancel", ['reason' => 'teste'], $tokM);
        $r2 = Ride::query()->withoutGlobalScopes()->find($rid2);
        $check('cancelamento do motorista responde 200', $s === 200, "status=$s ".substr($raw, 0, 200));
        $check('corrida volta a procurar motorista (nao morre) e fica sem motorista', $r2->status === 'searching' && $r2->driver_id === null, 'status='.$r2->status);
        $declinados = app(\App\Domains\Rides\Services\RideOfferService::class)->declinedBy($rid2);
        $check('quem cancelou nao recebe a mesma corrida de novo', in_array($drvUser->id, $declinados, true));
        [$s, $j] = $api('GET', '/rides/active', [], $tokP);
        $check('passageiro continua com a corrida ativa (procurando)', $s === 200 && ($j['ride']['id'] ?? null) === $rid2);
        // cancelar de novo uma corrida que nao e mais dele: 422 claro, nunca 500
        [$s] = $api('POST', "/rides/{$ride->id}/cancel", [], $tokM);
        $check('cancelar corrida ja finalizada devolve 422 (nao 500)', $s === 422, "status=$s");
    }
    [$s, $j, $raw] = $api('GET', '/drivers/nearby?lat=-29.2356&lng=-51.8711&radius_km=5', [], $tokP);
    $check('passageiro consulta carrinhos por perto', $s === 200 && isset($j['drivers']), "status=$s ".substr($raw, 0, 160));
    $check('carrinhos nao expoem id real, nome nem telefone', ! str_contains($raw, $drvUser->id) && ! str_contains($raw, '51977000001') && ! str_contains($raw, 'Motorista Teste'));
    [$s] = $api('POST', '/devices', ['token' => 'ExponentPushToken[teste-verificacao]', 'platform' => 'android'], $tokP);
    $check('aparelho registra o token de push', $s === 200, "status=$s");
    [$s] = $api('POST', '/devices', ['token' => 'qualquer-coisa'], $tokP);
    $check('token de push invalido e recusado (422)', $s === 422, "status=$s");
    [$s] = $api('DELETE', '/devices', ['token' => 'ExponentPushToken[teste-verificacao]'], $tokP);
    $check('aparelho remove o token ao sair', $s === 200, "status=$s");

    echo "== 4. Isolamento entre marcas
";
    $pDemo = User::query()->withoutGlobalScopes()->where('role', 'passenger')->where('tenant_id', Tenant::query()->where('slug', 'chama-demo')->value('id'))->first();
    if ($pDemo) {
        $tokDemo = $pDemo->createToken('teste')->plainTextToken;
        [$s] = $api('GET', '/me', [], $tokDemo, [], 'api.vaide55.com.br');
        $check('passageiro do AppChama NAO entra pelo endereco do Vai de 55 (403)', $s === 403, "status=$s");
    }
    [$s, $j] = $api('GET', '/me', [], $tokP, [], 'apichama.agilizeerp.com.br', ['HTTP_X_TENANT_SLUG' => 'chama-demo']);
    $check('pelo endereco da plataforma, o passageiro do Vai de 55 so enxerga a propria conta (nunca dados do AppChama)', $s === 200 && ($j['tenant_id'] ?? null) === $vai->id, "status=$s");
    if ($ride) {
        [$s, $j] = $api('GET', '/rides', [], $tokP, [], 'apichama.agilizeerp.com.br', ['HTTP_X_TENANT_SLUG' => 'chama-demo']);
        $ids = collect($j['data'] ?? $j)->pluck('tenant_id')->unique()->all();
        $check('lista de corridas pela plataforma traz so corridas do Vai de 55', $s === 200 && ($ids === [] || $ids === [$vai->id]));
    }
} finally {
    DB::rollBack();
    $limpaLimites();
}
echo "\nCORRIDA: $ok ok, ".count($falhas)." falhas\n";
foreach ($falhas as $f) { echo " - $f\n"; }
