<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sms' => [
        'provider' => env('SMS_PROVIDER', 'log'),
        // Devolver o codigo na resposta da API so em desenvolvimento. Em producao fica DESLIGADO.
        'expose_debug_code' => (bool) env('OTP_EXPOSE_DEBUG_CODE', false),
        // Twilio (so usado com SMS_PROVIDER=twilio). Os valores ficam no .env do servidor, nunca no Git.
        // smsmais.com (so usado com SMS_PROVIDER=smsmais). O token fica no .env do servidor.
        'smsmais' => [
            'token' => env('SMSMAIS_TOKEN'),
            'url' => env('SMSMAIS_URL', 'https://smsmais.com'),
        ],
        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),
            'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        ],
    ],

    // Contas de teste (revisao das lojas, testes internos): "telefone:codigo,telefone:codigo".
    // So esses telefones recebem codigo fixo. Nunca coloque telefone real aqui.
    'otp' => [
        'test_accounts' => env('OTP_TEST_ACCOUNTS', ''),
    ],

    'mapbox' => [
        'token' => env('MAPBOX_ACCESS_TOKEN'),
    ],

    'google' => [
        // Chave de SERVIDOR (Places API New), restrita ao IP da VPS. Sem ela a busca por locais fica desligada.
        'places_key' => env('GOOGLE_PLACES_API_KEY'),
    ],

    'asaas' => [
        'api_key' => env('ASAAS_API_KEY'),
        'environment' => env('ASAAS_ENVIRONMENT', 'sandbox'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
        'webhook_base_url' => env('API_URL', 'https://apichama.agilizeerp.com.br'),
    ],

];
