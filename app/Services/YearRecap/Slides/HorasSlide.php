<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;

class HorasSlide implements RecapSlide
{
    public function key(): string
    {
        return 'horas';
    }

    public function allows(RecapContext $ctx): bool
    {
        return true;
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $timed = $data->events()->filter(fn ($e) => $e->minutes > 0);
        $minutes = (int) $timed->sum('minutes');
        if ($minutes < 600) {
            return null;
        }

        $hours = (int) round($minutes / 60);
        $workDays = (int) round($hours / 8);

        return [
            'type' => 'stat',
            'kicker' => 'Tempo',
            'label' => $ctx->t('Horas a trabalhar', 'Horas de serviço'),
            'value' => F::int($hours).' h',
            'body' => $ctx->t(
                'São '.F::int($workDays).' dias inteiros de 8 horas com as mãos ocupadas.',
                'São '.F::int($workDays).' dias inteiros de 8 horas de serviço na agenda.',
            ),
            'stat_secondary' => [
                'label' => 'Duração média por marcação',
                'value' => F::duration((int) round($minutes / max(1, $timed->count()))),
            ],
            'highlight' => ['label' => 'Horas', 'value' => F::int($hours).' h'],
        ];
    }
}
