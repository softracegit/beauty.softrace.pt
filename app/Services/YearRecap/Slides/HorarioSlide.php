<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;

class HorarioSlide implements RecapSlide
{
    public function key(): string
    {
        return 'horario';
    }

    public function allows(RecapContext $ctx): bool
    {
        return true;
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $events = $data->events();
        if ($events->count() < 20) {
            return null;
        }

        $slots = $events->countBy(fn ($e) => $e->start->dayOfWeek.'|'.$e->start->hour)->sortDesc();
        $label = function (string $slot, bool $withArticle = false): string {
            [$dow, $hour] = array_map('intval', explode('|', $slot));
            $day = $withArticle ? F::weekdayWithArticle($dow) : F::weekdayPlural($dow);

            return $day.' · '.$hour.'h';
        };

        $first = (string) $slots->keys()->first();
        $second = $slots->keys()->get(1);

        $slide = [
            'type' => 'stat',
            'kicker' => 'Ritmo',
            'label' => $ctx->t('O teu horário de ouro', 'O vosso horário de ouro'),
            'value' => $label($first),
            'body' => 'É quando a agenda enche mais.'.($second !== null ? ' A seguir vêm '.$label((string) $second, true).'.' : ''),
        ];

        // Na loja (sem slide de recordes) mostramos aqui o mês mais intenso.
        if (! $ctx->isPersonal()) {
            $byMonth = $events->countBy(fn ($e) => $e->start->month)->sortDesc();
            $slide['stat_secondary'] = ['label' => 'Mês mais intenso', 'value' => F::month((int) $byMonth->keys()->first())];
        }

        return $slide;
    }
}
