<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Entidade responsável pelos documentos legais da plataforma
    |--------------------------------------------------------------------------
    |
    | Textos gerais do CRM / marcação online (não configuráveis por loja).
    |
    */

    'company_name' => env('LEGAL_COMPANY_NAME', env('APP_NAME', 'Laravel')),

    'company_nif' => env('LEGAL_COMPANY_NIF', ''),

    'company_address' => env('LEGAL_COMPANY_ADDRESS', ''),

    'contact_email' => env('LEGAL_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', 'privacidade@example.com')),

    /*
    |--------------------------------------------------------------------------
    | Versão da política de privacidade (para registo de aceitação)
    |--------------------------------------------------------------------------
    */

    'privacy_version' => env('LEGAL_PRIVACY_VERSION', '2026-06-10'),

];
