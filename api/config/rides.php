<?php

return [
    /*
     | Corrida encadeada: o motorista com passageiro no carro pode aceitar a PRÓXIMA corrida depois de concluir
     | parte do trajeto atual. A nova corrida só começa quando ele finalizar a que está em andamento.
     */
    'chain' => [
        'enabled' => (bool) env('RIDE_CHAIN_ENABLED', true),
        // fração do trajeto (em linha reta, origem -> destino) que precisa estar concluída: 0.20 = 20%
        'min_progress' => (float) env('RIDE_CHAIN_MIN_PROGRESS', 0.20),
        // o embarque da nova corrida precisa ficar perto do destino da corrida atual (km)
        'max_pickup_km' => (float) env('RIDE_CHAIN_MAX_PICKUP_KM', 5.0),
    ],
];
