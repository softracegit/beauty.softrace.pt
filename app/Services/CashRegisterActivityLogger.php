<?php

namespace App\Services;

use App\Models\CashRegisterSession;
use App\Models\User;

class CashRegisterActivityLogger
{
    public function logOpened(CashRegisterSession $session, User $causer, int $assignedBookingSalesCount = 0): void
    {
        $properties = [
            'session_id' => $session->id,
            'fundo_maneio' => $session->openingFloatEur(),
        ];

        if ($assignedBookingSalesCount > 0) {
            $properties['prepagamentos_atribuidos'] = $assignedBookingSalesCount;
        }

        $this->write($session, 'caixa_aberta', 'Caixa aberta', $properties, $causer);
    }

    public function logClosed(CashRegisterSession $session, User $causer): void
    {
        $summary = is_array($session->closing_summary) ? $session->closing_summary : [];

        $properties = [
            'session_id' => $session->id,
            'fundo_maneio' => $session->openingFloatEur(),
            'dinheiro_esperado' => round((float) ($summary['expected_cash_in_drawer'] ?? 0), 2),
            'dinheiro_contado' => round((float) ($summary['counted_cash'] ?? $session->closingCashCountedEur() ?? 0), 2),
            'diferenca' => round((float) ($summary['cash_difference'] ?? 0), 2),
            'vendas' => (int) ($summary['sales_count'] ?? 0),
        ];

        $notes = trim((string) ($session->notes ?? ''));
        if ($notes !== '') {
            $properties['notas'] = $notes;
        }

        $this->write($session, 'caixa_fechada', 'Caixa fechada', $properties, $causer);
    }

    private function write(
        CashRegisterSession $session,
        string $eventName,
        string $description,
        array $properties,
        User $causer,
    ): void {
        activity()
            ->performedOn($session)
            ->causedBy($causer)
            ->event($eventName)
            ->withProperties($properties)
            ->log($description);
    }
}
