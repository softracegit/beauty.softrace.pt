<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;

class ServicosSlide implements RecapSlide
{
    public function key(): string
    {
        return 'servicos';
    }

    public function allows(RecapContext $ctx): bool
    {
        return true;
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $services = $data->serviceCounts();
        if ($services->isEmpty()) {
            return null;
        }

        $top = $services->first();

        return [
            'type' => 'stat',
            'kicker' => 'Serviço estrela',
            'label' => $ctx->t('O serviço que mais fizeste', 'O mais pedido'),
            'value' => $top->service,
            'body' => F::int($top->qtd).' vezes este ano — '.$ctx->t('a tua especialidade, sem dúvida.', 'o ritual que a casa não larga.'),
            'list' => $services->slice(1, 3)
                ->map(fn ($s) => ['name' => $s->service, 'count' => F::int($s->qtd)])
                ->values()
                ->all(),
        ];
    }
}
