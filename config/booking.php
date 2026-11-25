<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Idiomas — fluxo público /booking
    |--------------------------------------------------------------------------
    */

    'default_locale' => env('BOOKING_DEFAULT_LOCALE', 'pt'),

    'supported_locales' => ['pt', 'en', 'es'],

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

    /**
     | Intervalo (minutos) do keep-alive no browser (/booking): pedido leve que renova
     | a sessão e o meta csrf-token enquanto o separador está visível. Deve ser menor
     | que SESSION_LIFETIME (ex.: keep-alive 10 min com sessão 120 min).
     */
    'session_keepalive_interval_minutes' => max(5, min(55, (int) env('BOOKING_SESSION_KEEPALIVE_MINUTES', 10))),

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
    | Lembretes SMS de marcação (dia anterior)
    |--------------------------------------------------------------------------
    |
    | O comando `booking:dispatch-sms-reminders` envia no dia civil anterior
    | ao início da marcação (na timezone de negócio). Evita lembretes à
    | madrugada por antecedência em horas e cobre marcações cedo ou tarde.
    |
    | Delay: só após N minutos desde created_at (anti-SMS imediato). Quem marca
    | à tarde para amanhã ainda pode receber no mesmo dia (janela até 20h).
    |
    | Só marcações em estado «Agendado» (exclui Notificado, Confirmado, etc.).
    |
    | Janela horária (hora local da loja): só despacha dentro desse intervalo
    | nesse dia anterior (ex.: 9–20 = entre 09:00 e 19:59). Use início 0 e
    | fim 24 para permitir qualquer hora.
    |
    | Máximo por execução: espalha envios quando há muitas marcações (cron
    | continua a correr cada minuto).
    |
    */

    /** Liga/desliga canais (fallback se ainda não houver valor em crm_settings). */
    'sms_reminder_enabled' => filter_var(env('BOOKING_SMS_REMINDER_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'sms_same_day_enabled' => filter_var(env('BOOKING_SMS_SAME_DAY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'email_reminder_enabled' => filter_var(env('BOOKING_EMAIL_REMINDER_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'email_same_day_enabled' => filter_var(env('BOOKING_EMAIL_SAME_DAY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /** @deprecated Mantido no .env por compatibilidade; o lembrete já não usa antecedência em minutos. */
    'sms_reminder_lead_minutes' => max(1, (int) env('BOOKING_SMS_REMINDER_LEAD_MINUTES', 120)),

    /** @deprecated Mantido no .env por compatibilidade; não aplicável ao modelo dia anterior. */
    'sms_reminder_grace_minutes' => max(0, (int) env('BOOKING_SMS_REMINDER_GRACE_MINUTES', 5)),

    /** Hora local (0–23) a partir da qual se pode enviar no «dia anterior». */
    'sms_reminder_day_before_send_start_hour' => max(0, min(23, (int) env('BOOKING_SMS_REMINDER_DAY_SEND_START_HOUR', 9))),

    /**
     * Hora local exclusiva de fim (1–24). Ex.: 20 = último minuto permitido 19:59.
     * Se fim <= início, a janela horária é ignorada (envio a qualquer hora do dia anterior).
     */
    'sms_reminder_day_before_send_end_hour' => max(1, min(24, (int) env('BOOKING_SMS_REMINDER_DAY_SEND_END_HOUR', 20))),

    /**
     * Atraso mínimo (minutos) após created_at antes de despachar o lembrete.
     * Evita SMS imediato quando a marcação é criada no dia anterior (ex.: à tarde para amanhã).
     */
    'sms_reminder_min_minutes_after_created' => max(0, (int) env('BOOKING_SMS_REMINDER_MIN_MINUTES_AFTER_CREATED', 45)),

    /**
     * Calling codes bloqueados para SMS com Alphanumeric Sender ID (Twilio).
     * Ex.: 1 (NANP), 34 (ES), 55 (BR).
     */
    'sms_blocked_calling_codes' => array_values(array_filter(array_map(
        static fn ($c): int => (int) $c,
        explode(',', (string) env('BOOKING_SMS_BLOCKED_CALLING_CODES', '1,34,55'))
    ), static fn (int $c): bool => $c > 0)),

    /** Máximo de SMS despachados por execução do comando (o resto fica para o minuto seguinte). */
    'sms_reminder_max_per_run' => max(1, (int) env('BOOKING_SMS_REMINDER_MAX_PER_RUN', 3)),

    /**
     * Link «Confirmar marcação» no SMS de lembrete (/r/{token}): válido até start_at da marcação
     * (instante UTC na BD). Ver SendBookingReminderSmsJob e BookingSmsActionController.
     */

    /** Tentativas do job de envio antes de marcar a marcação como falhada (sem reenvio futuro). */
    'sms_reminder_max_attempts' => max(1, (int) env('BOOKING_SMS_REMINDER_MAX_ATTEMPTS', 1)),

    /*
    |--------------------------------------------------------------------------
    | Lembretes SMS no próprio dia (sem link)
    |--------------------------------------------------------------------------
    |
    | Janela de antecedência: start_at entre lead_max e lead_min minutos no futuro.
    | Ex.: 120–180 = enviar quando faltam entre 2h e 3h. Cron + max_per_run espalha.
    | Só entre sms_same_day_send_start e sms_same_day_send_end (hora local).
    |
    */

    'sms_same_day_lead_min_minutes' => max(1, (int) env('BOOKING_SMS_SAME_DAY_LEAD_MIN_MINUTES', 120)),

    'sms_same_day_lead_max_minutes' => max(1, (int) env('BOOKING_SMS_SAME_DAY_LEAD_MAX_MINUTES', 180)),

    /** Minutos desde meia-noite (local) para início da janela de envio (08:30 = 510). */
    'sms_same_day_send_start_minutes' => max(0, min(24 * 60 - 1, (int) env('BOOKING_SMS_SAME_DAY_SEND_START_MINUTES', 8 * 60 + 30))),

    /** Minutos desde meia-noite (local), exclusivo, para fim da janela (18:00 = 1080). */
    'sms_same_day_send_end_minutes' => max(1, min(24 * 60, (int) env('BOOKING_SMS_SAME_DAY_SEND_END_MINUTES', 18 * 60))),

    'sms_same_day_max_per_run' => max(1, (int) env('BOOKING_SMS_SAME_DAY_MAX_PER_RUN', 3)),

    'sms_same_day_max_attempts' => max(1, (int) env('BOOKING_SMS_SAME_DAY_MAX_ATTEMPTS', 1)),

    /*
    |--------------------------------------------------------------------------
    | Lembretes email (dia anterior)
    |--------------------------------------------------------------------------
    */

    'email_reminder_max_per_run' => max(1, (int) env('BOOKING_EMAIL_REMINDER_MAX_PER_RUN', 10)),

    /*
    |--------------------------------------------------------------------------
    | Pagamento online na marcação (Stripe)
    |--------------------------------------------------------------------------
    |
    | Percentagem cobrada antecipadamente (ex.: 20). O restante é pago na loja.
    | Valores são arredondados a 2 casas decimais em EUR.
    |
    */

    // Fallback legado. Em runtime preferir App\Support\DepositPolicy (Definições → Pagamentos → Booking).
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
    | WhatsApp — botão flutuante no /booking (canto inferior esquerdo)
    |--------------------------------------------------------------------------
    */

    'booking_whatsapp' => env('BOOKING_WHATSAPP', '+351928116651'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp de apoio técnico (modal de login / autenticação)
    |--------------------------------------------------------------------------
    */

    'support_whatsapp' => env('BOOKING_SUPPORT_WHATSAPP', '+351963262852'),

    /*
    |--------------------------------------------------------------------------
    | Marcação pública — valores por defeito (fallback)
    |--------------------------------------------------------------------------
    |
    | Nome, morada, contactos, redes e logotipo são editados em Definições → Negócio
    | (por loja, na tabela `stores`). Estes valores só se aplicam se a loja ainda não
    | tiver dados guardados ou em ambientes sem registo na BD.
    |
    | `photo_fallback`: ícone quando a loja não tem logotipo carregado.
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
