<?php

/**
 * Versoes dos apps de celular (codigo de versao do Android) por marca.
 *  - latest:  ultima versao publicada na loja; apps mais antigos mostram "Atualizar" (pode dispensar).
 *  - minimum: abaixo disso o app so deixa atualizar (use para falhas graves).
 * Edite no .env:  APP_VERSIONS_JSON={"vaide55":{"passenger":{"latest":9,"minimum":0},"driver":{"latest":9,"minimum":0}}}
 */
return [
    'tenants' => json_decode((string) env('APP_VERSIONS_JSON', '{}'), true) ?: [],
];
