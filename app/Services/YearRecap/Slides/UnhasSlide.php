<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;

/**
 * "Unhas feitas": serviços de unhas × 10. É uma estimativa e o texto di-lo.
 * Os serviços contam pelo nome do serviço ou da categoria (config year-recap.nail_keywords).
 */
class UnhasSlide implements RecapSlide
{
    public function key(): string
    {
        return 'unhas';
    }

    public function allows(RecapContext $ctx): bool
    {
        return true;
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $keywords = array_map('mb_strtolower', (array) config('year-recap.nail_keywords', []));
        $isNail = function (object $s) use ($keywords): bool {
            $haystack = mb_strtolower($s->service.' '.($s->category ?? ''));
            foreach ($keywords as $kw) {
                if ($kw !== '' && str_contains($haystack, $kw)) {
                    return true;
                }
            }

            return false;
        };

        $services = $data->serviceCounts()->filter($isNail)->sum('qtd');
        if ($services < 20) {
            return null;
        }

        $perService = max(1, (int) config('year-recap.nails_per_service', 10));
        $nails = $services * $perService;
        $days = $data->events()->map(fn ($e) => $e->start->toDateString())->unique()->count();

        return [
            'type' => 'stat',
            'kicker' => 'Unhas',
            'label' => $ctx->t('Unhas que fizeste', 'Unhas feitas'),
            'value' => '≈ '.F::int($nails),
            'body' => F::int($services).' serviços de unhas, a '.$perService.' unhas cada. Feitas as contas por alto.',
            'stat_secondary' => $days > 0 ? [
                'label' => 'Por dia de trabalho',
                'value' => '≈ '.F::int($nails / $days),
            ] : null,
            'highlight' => ['label' => 'Unhas', 'value' => F::int($nails)],
        ];
    }
}
