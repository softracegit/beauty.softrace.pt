<?php

/*
|--------------------------------------------------------------------------
| Year Recap
|--------------------------------------------------------------------------
|
| Que slides vê cada perfil e por que ordem. A segurança não depende só
| desta lista: cada slide valida também, no servidor, se o utilizador o pode
| ver (ver App\Services\YearRecap\Slides\*::allows()).
|
*/

return [

    // Minutos em cache para o ano corrente. Anos fechados ficam 30 dias.
    'cache_minutes' => (int) env('YEAR_RECAP_CACHE_MINUTES', 60),

    // Abaixo disto não há história para contar: mostra o ecrã "ainda é cedo".
    'min_marcacoes' => 10,

    // Serviços que contam para "unhas feitas" (procura no nome do serviço e da categoria).
    'nail_keywords' => [
        'unha', 'manicure', 'pedicure', 'verniz', 'gel', 'gelinho', 'acrílico', 'acrilico',
        'fibra', 'nail', 'esmalte', 'francesinha', 'molde', 'tips',
    ],

    // Unhas por serviço (mãos ou pés = 10). É uma estimativa assumida no texto.
    'nails_per_service' => 10,

    'slides' => [
        'admin' => [
            'marcacoes',
            'clientes',
            'retorno',
            'servicos',
            'horario',
            'equipa',
            'canais',
            'faturacao',
            'pagamentos',
            'sms',
        ],
        'rececao' => [
            'marcacoes',
            'marcadas_por_mim',
            'clientes',
            'servicos',
            'horario',
            'equipa',
        ],
        'prestador' => [
            'marcacoes',
            'clientes',
            'retorno',
            'fieis',
            'servicos',
            'unhas',
            'horas',
            'recordes',
            'horario',
            'faturacao_pessoal',
        ],
    ],

    // Destaques do último slide (os primeiros 4 disponíveis, por esta ordem).
    'finale_priority' => [
        'marcacoes', 'clientes', 'unhas', 'horas', 'retorno', 'faturacao', 'faturacao_pessoal', 'marcadas_por_mim',
    ],

];
