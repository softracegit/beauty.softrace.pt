<?php

return [

    'csv_directory' => base_path('SmartAdmin-pro/assets/files'),

    'default_store_id' => 1,

    /**
     * Horas nos CSV Zappy são hora local do negócio (não UTC).
     * Convertidas para APP_TIMEZONE ao gravar (ex.: Lisbon 14:00 → UTC 13:00 no verão).
     */
    'source_timezone' => env('ZAPPY_IMPORT_TIMEZONE', env('BOOKING_BUSINESS_TIMEZONE', 'Europe/Lisbon')),

    /**
     * Vendas importadas equivalem a pagamento final em loja (evita alerta «Falta faturar» na agenda).
     */
    'sales_scope' => 'caixa_liquidacao',

    /**
     * Quando o nome do serviço no CSV não existe no catálogo, usar este serviço local
     * (o preço da marcação continua a vir de price_base no CSV).
     */
    'default_service_name' => 'Manicure Tradicional',

    /**
     * Várias linhas seguidas no Zappy (mesmo cliente/técnica/dia) → um único evento com vários serviços.
     */
    'merge_consecutive_appointments' => true,

    /** Máximo de minutos entre o fim de um serviço e o início do seguinte para agrupar. */
    'merge_max_gap_minutes' => 60,

    /** Máximo de minutos entre o início da 1.ª e da 2.ª marcação (ex. 10:00 e 10:45 → 45 min). */
    'merge_max_start_span_minutes' => 120,

    /**
     * Fundir linhas com a mesma data/hora de pagamento no CSV (mesmo checkout Zappy).
     */
    'merge_same_payment_date' => true,

    /**
     * Marcações «Pagou» importadas sem venda: criar venda sintética a partir do CSV de marcações
     * (quando não existir linha em vendas.csv — ex. nome com encoding diferente).
     */
    'create_synthetic_sale_when_no_invoice' => true,

    /**
     * Estados Zappy em que linhas consecutivas podem ser fundidas no mesmo evento.
     */
    'merge_statuses' => ['Pagou', 'Chegou', 'Confirmada', 'Iniciado'],

    /**
     * Nome do técnico no CSV Zappy => user_id na agenda.
     */
    'agent_user_map' => [
        'Laissa Osto' => 2,
        'Sandy Hurtado' => 3,
        'Vanessa Pereira' => 4,
        'Andrea Velasquez' => 5,
    ],

    /**
     * Marcações e linhas de venda destes nomes são ignoradas.
     */
    'ignored_agent_names' => [
        'Daniel Simões',
        'Alejandra Silva',
    ],

    /**
     * Nome da categoria no CSV => nome na BD (igual por defeito).
     */
    'category_names' => [
        'Manicure',
        'Pedicure',
        'Estética Rosto',
    ],

    /**
     * Estado Zappy (marcacoes.csv) => [event_type, status].
     */
    'appointment_status_map' => [
        'Pagou' => ['marcacao', 'completo'],
        'Cancelada' => ['marcacao', 'cancelado'],
        'Tempo pessoal' => ['tempo_pessoal', 'completo'],
        'Confirmada' => ['marcacao', 'confirmado'],
        'Chegou' => ['marcacao', 'chegou'],
        'Faltou' => ['marcacao', 'faltou'],
    ],

    'files' => [
        'services' => 'services.csv',
        'clients' => 'clientes.csv',
        'appointments' => 'marcacoes.csv',
        'sales' => 'vendas.csv',
    ],

];
