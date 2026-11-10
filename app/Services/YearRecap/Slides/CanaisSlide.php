<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;

/** Agenda vs booking online. */
class CanaisSlide implements RecapSlide
{
    public function key(): string
    {
        return 'canais';
    }

    public function allows(RecapContext $ctx): bool
    {
        return $ctx->audience === RecapContext::AUDIENCE_ADMIN;
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $events = $data->events();
        $total = $events->count();
        if ($total < 20) {
            return null;
        }

        $online = $events->where('online', true)->count();
        $agenda = $total - $online;
        $ratio = $online / $total;
        $agendaPct = (int) round(($agenda / $total) * 100);
        $onlinePct = 100 - $agendaPct;

        return [
            'type' => 'split',
            'kicker' => 'Canais',
            'label' => 'Como chegaram até vocês',
            'split' => [
                ['label' => 'Agenda / balcão · '.F::int($agenda), 'pct' => $agendaPct, 'value' => $agendaPct.'%'],
                ['label' => 'Booking online · '.F::int($online), 'pct' => $onlinePct, 'value' => $onlinePct.'%'],
            ],
            'body' => $online === 0
                ? 'Este ano foi tudo pela agenda. O booking online tem espaço para crescer.'
                : 'O booking online já traz '.($ratio < 0.1 ? 'quase 1' : F::inTen($ratio)).' em cada 10 marcações.',
        ];
    }
}
