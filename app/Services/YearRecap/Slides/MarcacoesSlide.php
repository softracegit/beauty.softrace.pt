<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;

class MarcacoesSlide implements RecapSlide
{
    public function key(): string
    {
        return 'marcacoes';
    }

    public function allows(RecapContext $ctx): bool
    {
        return true;
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $events = $data->events();
        $total = $events->count();
        if ($total === 0) {
            return null;
        }

        $days = $events->map(fn ($e) => $e->start->toDateString())->unique()->count();
        $perDay = $days > 0 ? $total / $days : 0;

        $slide = [
            'type' => 'stat',
            'kicker' => 'Agenda',
            'label' => $ctx->t('Marcações que fizeste', 'Marcações realizadas'),
            'value' => F::int($total),
            'body' => $ctx->t(
                'Em média, '.F::decimal($perDay).' por dia de trabalho — o ritmo do teu ano.',
                'Em média, '.F::decimal($perDay).' por dia de portas abertas — o ritmo do vosso ano.',
            ),
            'highlight' => ['label' => 'Marcações', 'value' => F::int($total)],
        ];

        // Só mostramos a comparação quando é a subir e o ano anterior tem dados que cheguem.
        $previous = $data->previousYearCount();
        if ($previous >= 20 && $total > $previous) {
            $slide['footnote'] = F::signedPct(($total - $previous) / $previous).' vs '.($ctx->year - 1);
        }

        return $slide;
    }
}
