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
    | Código OTP (login booking + verificação de contacto)
    |--------------------------------------------------------------------------
    |
    | Validade do código em minutos. Limite de reenvios: intervalo mínimo entre
    | envios bem-sucedidos (cooldown), contagem na janela e bloqueio após exceder.
    |
    */

    'auth_code_ttl_minutes' => max(3, (int) env('BOOKING_AUTH_CODE_TTL_MINUTES', 10)),

    /** Segundos entre um envio bem-sucedido e o próximo pedido (anti-abuso). */
    'otp_send_cooldown_seconds' => max(0, (int) env('BOOKING_OTP_SEND_COOLDOWN_SECONDS', 30)),

    /** Máximo de códigos enviados com sucesso na janela (inclui o 1.º envio; ex.: 6 = 1 + 5 reenvios). */
    'otp_send_max_per_window' => max(0, (int) env('BOOKING_OTP_SEND_MAX_PER_WINDOW', 6)),

    /** Janela em horas para contar os envios (0 no max desativa só o teto — use 1+). */
    'otp_send_count_window_hours' => max(1, (int) env('BOOKING_OTP_SEND_COUNT_WINDOW_HOURS', 1)),

    /** Horas de bloqueio após atingir o máximo de envios na janela. */
    'otp_send_lockout_hours' => max(1, (int) env('BOOKING_OTP_SEND_LOCKOUT_HOURS', 2)),

    /*
    |--------------------------------------------------------------------------
    | Lembretes SMS de marcação
    |--------------------------------------------------------------------------
    |
    | O comando agendado corre a cada minuto e procura marcações com início
    | dentro desta antecedência. Ex.: 120 = enviar ~2 horas antes.
    |
    */

    'sms_reminder_lead_minutes' => max(1, (int) env('BOOKING_SMS_REMINDER_LEAD_MINUTES', 120)),

    /**
     * Tolerância (em minutos) para trás no disparo do lembrete.
     * Evita perder SMS por pequenos atrasos do scheduler/worker.
     */
    'sms_reminder_grace_minutes' => max(0, (int) env('BOOKING_SMS_REMINDER_GRACE_MINUTES', 5)),

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

    /*
    |--------------------------------------------------------------------------
    | Antecedência mínima da marcação (minutos)
    |--------------------------------------------------------------------------
    |
    | Não permite reservar para "agora"; ex.: 30 = só horários a partir de
    | daqui a 30 minutos.
    |
    */

    'min_lead_minutes' => max(0, (int) env('BOOKING_MIN_LEAD_MINUTES', 30)),

    /*
    |--------------------------------------------------------------------------
    | Dados da loja (marcação pública — resumo e menu)
    |--------------------------------------------------------------------------
    |
    | Usados no resumo lateral e no offcanvas. `photo`: URL absoluta ou caminho
    | relativo a public/ (ex.: booking-assets/img/icone.png). Vazio = usa `photo_fallback`.
    |
    */

    'public_store' => [
        'name' => env('BOOKING_PUBLIC_STORE_NAME', 'Fada Studio | Beauty Bar Aveiro'),
        'address' => env('BOOKING_PUBLIC_STORE_ADDRESS', 'R. Gustavo Ferreira Pinto Basto 15 A, 3810-119 Aveiro'),
        'photo' => env('BOOKING_PUBLIC_STORE_PHOTO', ''),
        'photo_fallback' => 'booking-assets/img/icone.png',
        'maps_url' => env('BOOKING_PUBLIC_STORE_MAPS_URL', 'https://maps.google.com/?q=R.%20Gustavo%20Ferreira%20Pinto%20Basto%2015%20A%2C%203810-119%20Aveiro'),
        'phone' => env('BOOKING_PUBLIC_STORE_PHONE', '300 505 149'),
        'phone_tel_href' => env('BOOKING_PUBLIC_STORE_PHONE_TEL', 'tel:+351300505149'),
        'hours_summary' => env('BOOKING_PUBLIC_STORE_HOURS_SUMMARY', 'Seg–Sáb 09:00–20:00 · Domingo encerrado'),
        'website_url' => env('BOOKING_PUBLIC_STORE_WEBSITE', 'https://www.fadastudio.pt/'),
        'instagram_url' => env('BOOKING_PUBLIC_STORE_INSTAGRAM', 'https://www.instagram.com/fadastudio.pt/'),
        'weekday_open' => env('BOOKING_PUBLIC_STORE_WEEKDAY_OPEN', '09:00'),
        'weekday_close' => env('BOOKING_PUBLIC_STORE_WEEKDAY_CLOSE', '20:00'),
    ],

];
