<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;
use Carbon\Carbon;

class RecordesSlide implements RecapSlide
{
    public function key(): string
    {
        return 'recordes';
    }

    public function allows(RecapContext $ctx): bool
    {
        return true;
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $events = $data->events();
        if ($events->count() < 10) {
            return null;
        }

        $byDay = $events->countBy(fn ($e) => $e->start->toDateString())->sortDesc();
        $busiestDate = Carbon::parse((string) $byDay->keys()->first(), $ctx->timezone);
        $busiestCount = (int) $byDay->first();
        if ($busiestCount < 3) {
            return null;
        }

        $byMonth = $events->countBy(fn ($e) => $e->start->month)->sortDesc();

        return [
            'type' => 'stat',
            'kicker' => 'Recordes',
            'label' => $ctx->t('O teu dia mais cheio', 'O dia mais cheio'),
            'value' => F::dayMonth($busiestDate),
            'body' => F::int($busiestCount).' marcações num só dia. '.$ctx->t('Que maratona!', 'A agenda não teve descanso.'),
            'stat_secondary' => [
                'label' => 'Mês mais intenso',
                'value' => F::month((int) $byMonth->keys()->first()),
            ],
        ];
    }
}
