<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;

class ClientesSlide implements RecapSlide
{
    public function key(): string
    {
        return 'clientes';
    }

    public function allows(RecapContext $ctx): bool
    {
        return true;
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $clients = $data->visitsByClient()->count();
        if ($clients === 0) {
            return null;
        }

        $new = max(0, $clients - $data->returningClientsCount());

        return [
            'type' => 'stat',
            'kicker' => 'Pessoas',
            'label' => $ctx->t('Clientes que atendeste', 'Clientes atendidos'),
            'value' => F::int($clients),
            'body' => $ctx->t(
                'Pessoas diferentes que passaram pelas tuas mãos este ano.',
                'Rostos diferentes que passaram pela casa este ano.',
            ),
            'stat_secondary' => $new > 0 ? [
                'label' => $ctx->t('Primeira vez contigo', 'Novas caras'),
                'value' => F::int($new),
            ] : null,
            'highlight' => ['label' => 'Clientes', 'value' => F::int($clients)],
        ];
    }
}
