<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Resultado da avaliação da política de cancelamento (fuso de negócio da loja).
 */
final class CancellationPolicyResult
{
    public function __construct(
        public readonly bool $isWithinNoticePeriod,
        public readonly int $noticeHoursApplied,
        public readonly string $businessTimezone,
        public readonly CarbonInterface $appointmentStartAtLocal,
        public readonly CarbonInterface $cancellationDeadlineLocal,
        public readonly CarbonInterface $evaluatedAtLocal,
        public readonly bool $hasPaidDeposit,
        public readonly int $eligibleDepositCreditCents,
    ) {}

    /**
     * Prazo limite para cancelar sem perder o pré-pagamento (inclusive).
     */
    public function deadlineFormatted(string $format = 'd/m/Y \à\s H:i'): string
    {
        return $this->cancellationDeadlineLocal->format($format);
    }

    /**
     * Fora do prazo de aviso mínimo (definições CRM) — cancelamento online bloqueado.
     */
    public function isPastOnlineCancellationCutoff(): bool
    {
        if (! $this->appointmentStartAtLocal->greaterThan($this->evaluatedAtLocal)) {
            return true;
        }

        return ! $this->isWithinNoticePeriod;
    }

    /**
     * O cliente pode cancelar online (conta / SMS), dentro do aviso mínimo da loja.
     */
    public function canCancelOnline(): bool
    {
        return $this->appointmentStartAtLocal->greaterThan($this->evaluatedAtLocal)
            && $this->isWithinNoticePeriod;
    }
}
