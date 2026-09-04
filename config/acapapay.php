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
    | Ambiente (Modo)
    |--------------------------------------------------------------------------
    | 'production' para pagamentos reais.
    | 'sandbox' para simulação de pagamentos sem faturar de verdade.
    */
    'modo' => env('ACAPAPAY_MODO', 'production'),

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

    /*
    |--------------------------------------------------------------------------
    | Model de Planos de Faturação
    |--------------------------------------------------------------------------
    | Define qual é a Model (ex: \App\Models\Plan::class) na tua aplicação que
    | representa os planos. Utilizado pelo comando acapapay:sync-plans.
    */
    'plan_model' => null,

    /*
    |--------------------------------------------------------------------------
    | Moeda Preferida (RedotPay / Pagamentos Internacionais)
    |--------------------------------------------------------------------------
    | Se definida, será usada como moeda padrão nos checkouts.
    | Útil para apps que cobram exclusivamente em USD (ex: via RedotPay/cripto).
    | Valores suportados: 'AOA', 'USD'. Deixar null para usar a moeda do plano.
    */
    'preferred_currency' => env('ACAPAPAY_PREFERRED_CURRENCY', null),

    /*
    |--------------------------------------------------------------------------
    | Método de Pagamento Preferido
    |--------------------------------------------------------------------------
    | Se definido, será sugerido como método padrão na página de checkout.
    | Valores suportados: 'REF' (Multicaixa), 'GPO' (Multicaixa Express),
    | 'EKZ' (E-Kwanza), 'RDP' (RedotPay/Cripto). Deixar null para mostrar todos.
    */
    'preferred_method' => env('ACAPAPAY_PREFERRED_METHOD', null),
];
