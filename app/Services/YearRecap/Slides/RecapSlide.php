<?php

namespace App\Services\YearRecap\Slides;

use App\Services\YearRecap\RecapContext;
use App\Services\YearRecap\RecapData;

/**
 * Um slide do recap.
 *
 * build() devolve o array que a view renderiza, ou null para saltar o slide
 * (sem dados suficientes). Chaves usadas pela view: type (stat|split),
 * kicker, label, value, body, footnote, stat_secondary{label,value},
 * list[{name,count}], split[{label,pct,value}].
 * A chave "highlight" {label,value} alimenta os destaques do slide final.
 */
interface RecapSlide
{
    public function key(): string;

    /** Validação de permissões no servidor (independente da config). */
    public function allows(RecapContext $ctx): bool;

    public function build(RecapContext $ctx, RecapData $data): ?array;
}
