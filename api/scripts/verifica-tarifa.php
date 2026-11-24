<?php
// Verifica a tarifa por faixa de distancia (preco por km menor em viagem longa) e os valores da corrida encadeada por cidade.
// Nada e gravado (so calculos em memoria). Rodar: php artisan tinker scripts/verifica-tarifa.php
use App\Domains\Rides\Services\{FareCalculatorService, RideChainService};
use App\Models\City;

$ok = 0; $falhas = [];
$check = function (string $nome, bool $cond, $extra = '') use (&$ok, &$falhas) {
    if ($cond) { $ok++; echo "  ok  $nome\n"; } else { $falhas[] = $nome; echo "  FALHOU  $nome ".(is_scalar($extra) ? $extra : json_encode($extra))."\n"; }
};
$f = app(FareCalculatorService::class);
$cidade = fn (array $x = []) => new City(array_merge(['base_fare' => 0, 'price_per_km' => 4.00, 'price_per_min' => 0, 'min_fare' => 0, 'km_tiers' => null], $x));
$tarifa = fn (City $c, float $km, float $min = 0) => $f->calculateFinal($c, $km, $min);
try {
    // --- sem faixas: igual ao que sempre foi
    $c0 = $cidade();
    $check('sem faixas: 3 km a R$ 4,00 = R$ 12,00', abs($tarifa($c0, 3) - 12.0) < 0.001);
    $check('sem faixas: 12 km = R$ 48,00', abs($tarifa($c0, 12) - 48.0) < 0.001);
    $check('km_tiers vazio ([]) tambem vale como sem faixas', abs($tarifa($cidade(['km_tiers' => []]), 12) - 48.0) < 0.001);

    // --- com faixas (o exemplo do cliente: o km fica mais barato em viagem longa)
    $c = $cidade(['km_tiers' => [['from_km' => 5, 'price' => 3.5], ['from_km' => 10, 'price' => 3.0]]]);
    $check('ate 5 km continua R$ 4,00 por km (3 km = R$ 12,00)', abs($tarifa($c, 3) - 12.0) < 0.001);
    $check('exatamente 5 km = R$ 20,00 (sem degrau)', abs($tarifa($c, 5) - 20.0) < 0.001);
    $check('8 km = 5x4,00 + 3x3,50 = R$ 30,50', abs($tarifa($c, 8) - 30.5) < 0.001, $tarifa($c, 8));
    $check('12 km = 5x4,00 + 5x3,50 + 2x3,00 = R$ 43,50', abs($tarifa($c, 12) - 43.5) < 0.001, $tarifa($c, 12));
    $check('viagem longa tem km medio menor (12 km: R$ 3,625/km < R$ 4,00)', ($tarifa($c, 12) / 12) < 4.0 && ($tarifa($c, 12) / 12) > 3.0);

    // --- o preco total nunca diminui quando a distancia aumenta (mesmo com faixa mais barata)
    $sobe = true; $ant = -1;
    for ($km = 0.0; $km <= 40; $km += 0.1) { $v = $f->distanceCharge($c, $km); if ($v + 1e-9 < $ant) { $sobe = false; break; } $ant = $v; }
    $check('preco total so cresce com a distancia (sem degrau para baixo)', $sobe);

    // --- ordem e dados ruins
    $cb = $cidade(['km_tiers' => [['from_km' => 10, 'price' => 3.0], ['from_km' => 5, 'price' => 3.5], ['from_km' => 5, 'price' => 9], ['from_km' => 0, 'price' => 1], ['from_km' => -2, 'price' => 1], ['price' => 2], ['from_km' => 'x', 'price' => 2], ['from_km' => 7, 'price' => -1]]]);
    $check('faixas fora de ordem/invalidas: ignora o lixo e ordena', abs($tarifa($cb, 12) - 43.5) < 0.001, $tarifa($cb, 12));
    $check('lista de faixas validas fica em ordem crescente', array_column($f->tiers($c), 'from_km') === [5.0, 10.0]);

    // --- bandeirada, minutos e tarifa minima continuam valendo
    $cm = $cidade(['base_fare' => 6, 'price_per_min' => 0.5, 'min_fare' => 15, 'km_tiers' => [['from_km' => 5, 'price' => 3.5]]]);
    $check('bandeirada + minutos + faixas: 8 km, 20 min = 6 + 30,50 + 10 = R$ 46,50', abs($tarifa($cm, 8, 20) - 46.5) < 0.001, $tarifa($cm, 8, 20));
    $check('tarifa minima continua valendo (1 km, 2 min = min R$ 15,00)', abs($tarifa($cm, 1, 2) - 15.0) < 0.001, $tarifa($cm, 1, 2));
    $cz = $cidade(['km_tiers' => [['from_km' => 5, 'price' => 0]]]);
    $check('faixa com R$ 0,00 e aceita (km gratis depois do ponto)', abs($tarifa($cz, 9) - 20.0) < 0.001);

    // --- corrida encadeada: valores por cidade
    $chain = app(RideChainService::class);
    config(['rides.chain.enabled' => true, 'rides.chain.min_progress' => 0.20, 'rides.chain.max_pickup_km' => 5.0]);
    $padrao = $cidade();
    $check('cidade sem valores proprios usa o padrao (20% e 5 km)', abs($chain->minProgress(null) - 0.20) < 1e-9 && $chain->maxPickupKm(null) === 5.0);
    $cc = $cidade(['chain_rides_enabled' => true, 'chain_min_progress_pct' => 35, 'chain_max_pickup_km' => 8.5]);
    $check('cidade com valores proprios: 35% e 8,5 km', abs($chain->minProgress($cc) - 0.35) < 1e-9 && abs($chain->maxPickupKm($cc) - 8.5) < 1e-9);
    $check('cidade pode desligar a corrida encadeada', $chain->enabledFor($cidade(['chain_rides_enabled' => false])) === false && $chain->enabledFor($cc) === true);
    config(['rides.chain.enabled' => false]);
    $check('chave geral desligada vence a cidade', $chain->enabledFor($cc) === false);
    config(['rides.chain.enabled' => true]);
    $check('valores absurdos sao limitados (0% -> 1%, 99% -> 95%)', abs($chain->minProgress($cidade(['chain_min_progress_pct' => 0])) - 0.01) < 1e-9 && abs($chain->minProgress($cidade(['chain_min_progress_pct' => 99])) - 0.95) < 1e-9);

    // --- painel
    $check('painel: formulario da cidade carrega', class_exists(\App\Filament\Resources\Cities\Schemas\CityForm::class) && in_array('km_tiers', (new City)->getFillable(), true));
} catch (\Throwable $e) {
    $falhas[] = 'EXCECAO: '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine();
    echo '  EXCECAO '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine()."\n";
}
echo "\nTARIFA: $ok ok, ".count($falhas)." falhas\n";
