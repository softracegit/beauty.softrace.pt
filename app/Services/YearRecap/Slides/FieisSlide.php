<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;

/** As clientes que mais voltaram. Só nome próprio + inicial. */
class FieisSlide implements RecapSlide
{
    public function key(): string
    {
        return 'fieis';
    }

    public function allows(RecapContext $ctx): bool
    {
        return true;
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $top = $data->visitsByClient()->filter(fn (int $n) => $n >= 3)->take(4);
        if ($top->isEmpty()) {
            return null;
        }

        $names = $data->clientNames($top->keys()->all());
        $rows = $top->map(fn (int $n, int $id) => ['name' => F::shortName($names[$id] ?? ''), 'count' => $n.'×'])
            ->filter(fn ($r) => $r['name'] !== '')
            ->values();
        if ($rows->isEmpty()) {
            return null;
        }

        $first = $rows->shift();
        $firstVisits = (int) $top->first();

        return [
            'type' => 'stat',
            'kicker' => 'Clientes fiéis',
            'label' => $ctx->t('Quem mais voltou a ti', 'Quem mais voltou'),
            'value' => $first['name'],
            'body' => $ctx->t(
                'Marcou contigo '.$firstVisits.' vezes este ano. Já faz parte da tua rotina.',
                'Marcou '.$firstVisits.' vezes este ano. Já faz parte da casa.',
            ),
            'list' => $rows->all(),
        ];
    }
}
