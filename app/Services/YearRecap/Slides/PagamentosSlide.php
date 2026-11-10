<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;
use App\Services\YearRecap\RecapFormat as F;
use App\Support\PaymentMethodCatalog;

/** Pagamentos recebidos e métodos preferidos. Só admin. */
class PagamentosSlide implements RecapSlide
{
    public function key(): string
    {
        return 'pagamentos';
    }

    public function allows(RecapContext $ctx): bool
    {
        return $ctx->audience === RecapContext::AUDIENCE_ADMIN && $ctx->viewer->canViewInvoices();
    }

    public function build(RecapContext $ctx, RecapData $data): ?array
    {
        $byMethod = $data->paymentsByMethod();
        $total = (int) $byMethod->sum('qtd');
        if ($total < 10) {
            return null;
        }

        $labels = $this->labels($ctx->storeId);
        $label = fn (string $code) => $labels[$code] ?? ($code !== '' ? ucfirst(str_replace('_', ' ', $code)) : 'Outro');
        $top = $byMethod->first();

        return [
            'type' => 'stat',
            'kicker' => 'Pagamentos',
            'label' => 'Pagamentos recebidos',
            'value' => F::int($total),
            'body' => $label($top->method).' foi o método preferido: '.F::pct($top->qtd / $total).' dos pagamentos.',
            'list' => $byMethod->slice(1, 3)
                ->map(fn ($m) => ['name' => $label($m->method), 'count' => F::pct($m->qtd / $total)])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, string> */
    private function labels(int $storeId): array
    {
        $labels = [];
        foreach (PaymentMethodCatalog::definitions() as $code => $def) {
            $labels[$code] = (string) ($def['label'] ?? $code);
        }
        try {
            $labels = array_merge($labels, PaymentMethodCatalog::labels($storeId));
        } catch (\Throwable) {
            // Sem configuração da organização: ficam os nomes base.
        }

        return $labels;
    }
}
