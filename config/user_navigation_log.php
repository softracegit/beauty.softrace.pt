<?php

return [

    'enabled' => env('USER_NAVIGATION_LOG_ENABLED', true),

    /** Segundos sem repetir o mesmo user + rota + parâmetros. */
    'debounce_seconds' => (int) env('USER_NAVIGATION_LOG_DEBOUNCE', 60),

    'delete_records_older_than_days' => (int) env('USER_NAVIGATION_LOG_RETENTION_DAYS', 90),

    /**
     * Rotas GET excluídas (API, AJAX, exports, polling).
     */
    'excluded_route_names' => [
        'notifications.api',
        'agenda.resources',
        'agenda.members.services',
        'agenda.clients',
        'agenda.clients.wallet',
        'agenda.clients.saved_cards',
        'agenda.events',
        'agenda.events.show',
        'agenda.events.same_day_payable',
        'agenda.events.cancellation_preview',
        'agenda.checkout',
        'agenda.deposit.show',
        'categories.index',
        'clientes.export',
        'clientes.pdf',
        'properties.getCities',
        'properties.getParishes',
        'opportunities.getCrossedProperties',
        'sales.pdf',
        'sales.vendus.pdf',
        'sales.credit-note.pdf',
    ],

    /**
     * Prefixos de nome de rota a excluir (ex.: agenda.events.*).
     */
    'excluded_route_prefixes' => [
        'agenda.events.',
        'agenda.clients.',
        'agenda.checkout.',
        'agenda.deposit.',
    ],

];
