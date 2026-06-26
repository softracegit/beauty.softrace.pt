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
     * Menos de {@see config('booking.min_lead_minutes')} minutos para o início — cancelamento online bloqueado.
     */
    public function isPastOnlineCancellationCutoff(): bool
    {
        $minLeadMinutes = max(0, (int) config('booking.min_lead_minutes', 30));
        if ($minLeadMinutes <= 0) {
            return false;
        }

        $cutoff = $this->appointmentStartAtLocal->copy()->subMinutes($minLeadMinutes);

        return $this->evaluatedAtLocal->greaterThan($cutoff);
    }

    /**
     * O cliente pode cancelar online (conta / SMS), respeitando corte de 30 min e política de pré-pagamento.
     */
    public function canCancelOnline(): bool
    {
        if (! $this->appointmentStartAtLocal->greaterThan($this->evaluatedAtLocal)) {
            return false;
        }

        if ($this->isPastOnlineCancellationCutoff()) {
            return false;
        }

        if ($this->hasPaidDeposit && ! $this->isWithinNoticePeriod) {
            return false;
        }

        return true;
    }
}
