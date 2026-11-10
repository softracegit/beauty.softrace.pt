<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;

/** Marcações que a própria pessoa criou na agenda (receção). */
class MarcadasPorMimSlide implements RecapSlide
{
    public function key(): string
    {
        return 'marcadas_por_mim';
    }

    public function allows(RecapContext $ctx): bool
    {
        return ! $ctx->isPersonal();
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $mine = $data->marcacoesCreatedByViewer();
        if ($mine < 5) {
            return null;
        }

        $all = $data->marcacoesCreatedByStaff();
        $name = $ctx->viewerFirstName();

        return [
            'type' => 'stat',
            'kicker' => 'Ao balcão',
            'label' => 'Marcações que agendaste',
            'value' => F::int($mine),
            'body' => 'Cada uma foi uma chamada, uma mensagem ou um "até já" ao balcão'.($name !== '' ? ', '.$name : '').'.',
            'stat_secondary' => $all > 0 ? [
                'label' => 'Das marcações feitas na agenda',
                'value' => F::pct($mine / $all),
            ] : null,
            'highlight' => ['label' => 'Agendadas por ti', 'value' => F::int($mine)],
        ];
    }
}
