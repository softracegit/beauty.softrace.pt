<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe API (Payment Intents)
    |--------------------------------------------------------------------------
    |
    | Publishable key is safe for the browser (Stripe.js). Secret key must stay
    | on the server. Supports both STRIPE_API_* (this project) and the common
    | STRIPE_KEY / STRIPE_SECRET names.
    |
    */

    'key' => env('STRIPE_API_KEY', env('STRIPE_KEY')),

    'secret' => env('STRIPE_API_SECRET', env('STRIPE_SECRET')),

    /*
    |--------------------------------------------------------------------------
    | Webhook signing secret (whsec_...)
    |--------------------------------------------------------------------------
    */

    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | API version (opcional)
    |--------------------------------------------------------------------------
    |
    | Recomendado: deixa vazio — o stripe/stripe-php envia a versão compatível com a biblioteca.
    | Só define STRIPE_API_VERSION se copiares um valor exacto do painel Stripe (ex.: 2024-11-20.acacia).
    | Datas inventadas ou versões inválidas fazem falhar o PaymentIntent antes de aparecer o formulário de cartão.
    |
    */

    'api_version' => env('STRIPE_API_VERSION'),

    /*
    |--------------------------------------------------------------------------
    | Métodos no checkout público (/booking)
    |--------------------------------------------------------------------------
    |
    | Valor em STRIPE_BOOKING_PAYMENT_METHODS:
    | - `auto` — automatic_payment_methods: tudo o que tiveres activo no Dashboard
    |   (Amazon Pay, Klarna, MB WAY se existir, etc.).
    | - Lista separada por vírgulas — só esses tipos (ex.: card,multibanco).
    |   Isto remove da lista métodos que não incluas (ex.: Amazon Pay).
    |
    | Multibanco: Stripe Dashboard → Definições → Métodos de pagamento → Multibanco (ON).
    | Requer moeda compatível (EUR) e conta elegível. O pagamento pode ser assíncrono
    | (referência MB); confirma com webhook payment_intent.succeeded.
    |
    */

    'booking_payment_methods' => env('STRIPE_BOOKING_PAYMENT_METHODS', 'auto'),

];
