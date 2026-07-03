<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Relatórios de erro por email
    |--------------------------------------------------------------------------
    |
    | Em produção (APP_DEBUG=false), exceções não previstas enviam um email
    | técnico e mostram uma página amigável ao utilizador.
    |
    */

    'report_enabled' => (bool) env('ERROR_REPORT_ENABLED', env('APP_ENV') === 'production'),

    'report_recipients' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ERROR_REPORT_EMAIL', ''))
    ))),

    'report_throttle_minutes' => max(1, (int) env('ERROR_REPORT_THROTTLE_MINUTES', 15)),

    'user_message' => 'Ocorreu um erro inesperado. A nossa equipa foi notificada. Tente novamente dentro de momentos.',

];
