<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;

class RetornoSlide implements RecapSlide
{
    public function key(): string
    {
        return 'retorno';
    }

    public function allows(RecapContext $ctx): bool
    {
        return true;
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $visits = $data->visitsByClient();
        if ($visits->count() < 10) {
            return null;
        }

        $returned = $visits->filter(fn (int $n) => $n >= 2)->count();
        $ratio = $returned / $visits->count();
        if ($returned === 0) {
            return null;
        }

        // Intervalo médio entre visitas consecutivas da mesma cliente.
        $gaps = [];
        foreach ($data->events()->whereNotNull('client_id')->groupBy('client_id') as $clientEvents) {
            $dates = $clientEvents->map(fn ($e) => $e->start->copy()->startOfDay())->unique()->values();
            for ($i = 1; $i < $dates->count(); $i++) {
                $gaps[] = $dates[$i - 1]->diffInDays($dates[$i], true);
            }
        }

        $x = F::inTen($ratio);

        return [
            'type' => 'stat',
            'kicker' => 'Fidelização',
            'label' => 'Voltaram pelo menos uma vez',
            'value' => F::pct($ratio),
            'body' => $ctx->t(
                $x.' em cada 10 clientes voltaram a marcar contigo. A confiança é o teu melhor marketing.',
                $x.' em cada 10 clientes marcaram de novo. A confiança é o vosso melhor marketing.',
            ),
            'stat_secondary' => $gaps !== [] ? [
                'label' => 'Intervalo médio entre visitas',
                'value' => F::int(array_sum($gaps) / count($gaps)).' dias',
            ] : null,
            'highlight' => ['label' => 'Retorno', 'value' => F::pct($ratio)],
        ];
    }
}
