<?php

return [
    // Endereços da PLATAFORMA (AppChama): sem marca fixa, o super-admin vê todas as marcas.
    'platform_hosts' => array_values(array_filter(array_map('trim', explode(
        ',',
        (string) env('TENANCY_PLATFORM_HOSTS', 'appchama.agilizeerp.com.br,chama.agilizeerp.com.br,apichama.agilizeerp.com.br')
    )))),
];
