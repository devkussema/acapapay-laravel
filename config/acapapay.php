<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AcapaPay Client ID
    |--------------------------------------------------------------------------
    | O Client ID fornecido no portal de Developer do SSO Acapadev.
    */
    'client_id' => env('ACAPAPAY_CLIENT_ID'),

    /*
    |--------------------------------------------------------------------------
    | AcapaPay Client Secret
    |--------------------------------------------------------------------------
    | A chave super secreta OAuth da tua aplicação.
    */
    'client_secret' => env('ACAPAPAY_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | AcapaPay Webhook Secret
    |--------------------------------------------------------------------------
    | Utilizado para validar a assinatura (HMAC-SHA256) nos pedidos de webhook
    | que a AcapaPay faz para o teu sistema, assegurando que não são forjados.
    */
    'webhook_secret' => env('ACAPAPAY_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | URLs Base da AcapaPay
    |--------------------------------------------------------------------------
    | Endereços do ambiente de identidade e API. Podes substituí-los se
    | estiveres a testar em ambiente local ou staging.
    */
    'host' => env('ACAPAPAY_HOST', 'https://id.acapadev.com'),
    'api_host' => env('ACAPAPAY_API_HOST', 'https://api.acapadev.com'),

    /*
    |--------------------------------------------------------------------------
    | Verificar SSL (Segurança)
    |--------------------------------------------------------------------------
    | Em ambiente de produção, manter como true.
    | Desativar (false) apenas em ambiente local com certificados self-signed.
    */
    'verify_ssl' => env('ACAPAPAY_VERIFY_SSL', true),
];
