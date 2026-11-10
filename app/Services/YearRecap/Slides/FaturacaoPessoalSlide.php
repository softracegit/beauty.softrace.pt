<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;

/** O que a própria prestadora faturou e a sua comissão. Só os números dela. */
class FaturacaoPessoalSlide implements RecapSlide
{
    public function key(): string
    {
        return 'faturacao_pessoal';
    }

    public function allows(RecapContext $ctx): bool
    {
        return $ctx->isPersonal() && $ctx->subjectUserId() === (int) $ctx->viewer->id;
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $userId = (int) $ctx->viewer->id;
        $row = collect($data->financial()['comissoes_por_tecnica'] ?? [])
            ->first(fn ($r) => (int) ($r->user_id ?? 0) === $userId);
        if ($row === null) {
            return null;
        }

        $receita = (float) ($row->receita ?? 0);
        $comissao = (float) ($row->comissao ?? 0);
        if ($receita <= 0) {
            return null;
        }

        return [
            'type' => 'stat',
            'kicker' => 'O teu resultado',
            'label' => 'O que faturaste',
            'value' => F::money($receita),
            'body' => 'Cada serviço conta — e o teu ano soma.',
            'stat_secondary' => $comissao > 0 ? [
                'label' => 'A tua comissão',
                'value' => F::money($comissao),
            ] : null,
            'highlight' => ['label' => 'Faturaste', 'value' => F::moneyShort($receita)],
        ];
    }
}
