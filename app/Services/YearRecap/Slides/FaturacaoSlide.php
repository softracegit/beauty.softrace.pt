<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;

/** Faturação da loja. Só admin. */
class FaturacaoSlide implements RecapSlide
{
    public function key(): string
    {
        return 'faturacao';
    }

    public function allows(RecapContext $ctx): bool
    {
        return $ctx->audience === RecapContext::AUDIENCE_ADMIN && $ctx->viewer->canViewInvoices();
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $kpis = $data->financial()['kpis'] ?? [];
        $receita = (float) ($kpis['receita'] ?? 0);
        if ($receita <= 0) {
            return null;
        }

        $anterior = (float) ($kpis['receita_anterior'] ?? 0);
        $ticket = $kpis['ticket_medio'] ?? null;

        return [
            'type' => 'stat',
            'kicker' => 'Resultado',
            'label' => 'Faturação do ano',
            'value' => F::money($receita),
            'body' => $ticket !== null
                ? 'Ticket médio de '.F::money((float) $ticket).' em '.F::int((int) ($kpis['num_faturas'] ?? 0)).' faturas. Cada visita conta — e o ano soma.'
                : 'Cada visita conta — e o ano soma.',
            'stat_secondary' => $anterior > 0 ? [
                'label' => 'vs ano anterior',
                'value' => F::signedPct(($receita - $anterior) / $anterior),
            ] : null,
            'highlight' => ['label' => 'Faturação', 'value' => F::moneyShort($receita)],
        ];
    }
}
