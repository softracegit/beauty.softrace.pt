<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fuso horário — marcação pública (disponibilidade)
    |--------------------------------------------------------------------------
    |
    | Usado só no fluxo /booking (calendário do cliente, janela da loja e cruzamento
    | com eventos). Permite manter APP_TIMEZONE=UTC no resto do CRM sem alterar a
    | lógica existente; os instantes dos eventos continuam corretos via timestamps.
    |
    */

    'business_timezone' => env('BOOKING_BUSINESS_TIMEZONE', 'Europe/Lisbon'),

    /*
    |--------------------------------------------------------------------------
    | Magic link (marcação online)
    |--------------------------------------------------------------------------
    |
    | Validade do token enviado por email. A sessão após login usa "remember"
    | e SESSION_LIFETIME (ex.: 30 dias) — ver .env.example.
    |
    */

    'magic_link_ttl_minutes' => (int) env('BOOKING_MAGIC_LINK_TTL_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Pagamento online na marcação (Stripe)
    |--------------------------------------------------------------------------
    |
    | Percentagem cobrada antecipadamente (ex.: 20). O restante é pago na loja.
    | Valores são arredondados a 2 casas decimais em EUR.
    |
    */

    'deposit_percent' => max(0, min(100, (int) env('BOOKING_DEPOSIT_PERCENT', 20))),

    'currency' => strtolower((string) env('BOOKING_CURRENCY', 'eur')),

];
