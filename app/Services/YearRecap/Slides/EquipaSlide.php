<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;

/** Técnica com mais horas de serviço (só no âmbito loja). */
class EquipaSlide implements RecapSlide
{
    public function key(): string
    {
        return 'equipa';
    }

    public function allows(RecapContext $ctx): bool
    {
        return ! $ctx->isPersonal();
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $providers = array_flip($data->serviceProviderIds());
        $byUser = $data->events()
            ->filter(fn ($e) => $e->user_id !== null && isset($providers[$e->user_id]))
            ->groupBy('user_id')
            ->map(fn ($events) => ['minutes' => (int) $events->sum('minutes'), 'count' => $events->count()])
            ->sortByDesc('minutes');

        if ($byUser->count() < 2 || $byUser->first()['minutes'] < 600) {
            return null;
        }

        $top = $byUser->take(4);
        $names = $data->staffNames($top->keys()->map(fn ($id) => (int) $id)->all());
        $rows = $top->map(fn ($r, $id) => [
            'name' => $names[(int) $id] ?? '',
            'count' => F::int($r['minutes'] / 60).' h',
        ])->values();

        $first = $rows->shift();
        $firstStats = $top->first();

        return [
            'type' => 'stat',
            'kicker' => 'Equipa',
            'label' => 'Mais horas de serviço',
            'value' => $first['name'],
            'body' => F::int($firstStats['minutes'] / 60).' horas e '.F::int($firstStats['count']).' marcações. Um ano de mãos ocupadas.',
            'list' => $rows->all(),
        ];
    }
}
