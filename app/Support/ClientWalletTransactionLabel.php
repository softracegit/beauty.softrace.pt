<?php

namespace App\Support;

use App\Models\ClientWalletTransaction;

class ClientWalletTransactionLabel
{
    public function forTransaction(ClientWalletTransaction $transaction): string
    {
        $stored = trim((string) ($transaction->description ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        return match ($transaction->type) {
            ClientWalletTransaction::TYPE_CREDIT_CANCELLATION_IN_POLICY => $this->cancellationCreditLabel($transaction),
            ClientWalletTransaction::TYPE_DEBIT_BOOKING_CHECKOUT => $this->bookingDebitLabel($transaction),
            ClientWalletTransaction::TYPE_DEBIT_POS_CHECKOUT => $this->posDebitLabel($transaction),
            ClientWalletTransaction::TYPE_CREDIT_MANUAL_TOPUP => 'Carregamento de saldo',
            ClientWalletTransaction::TYPE_CREDIT_CASHBACK => 'Cashback',
            ClientWalletTransaction::TYPE_CREDIT_ADMIN_ADJUSTMENT => 'Ajuste de crédito',
            ClientWalletTransaction::TYPE_DEBIT_ADMIN_ADJUSTMENT => 'Ajuste de débito',
            default => 'Movimento na carteira',
        };
    }

    private function cancellationCreditLabel(ClientWalletTransaction $transaction): string
    {
        $event = $transaction->calendarEvent;
        if (! $event?->start_at) {
            return 'Crédito por cancelamento de marcação';
        }

        $storeId = (int) ($event->store_id ?? 0) ?: null;
        $start = DateTimeDisplay::marcacao($event->start_at, $storeId, 'd/m/Y H:i');

        return 'Crédito por cancelamento da marcação de '.$start;
    }

    private function bookingDebitLabel(ClientWalletTransaction $transaction): string
    {
        $event = $transaction->calendarEvent;
        if ($event?->start_at) {
            $storeId = (int) ($event->store_id ?? 0) ?: null;
            $start = DateTimeDisplay::marcacao($event->start_at, $storeId, 'd/m/Y H:i');

            return 'Utilizado na marcação de '.$start;
        }

        return 'Utilizado na marcação online';
    }

    private function posDebitLabel(ClientWalletTransaction $transaction): string
    {
        $sale = $transaction->sale;
        if ($sale) {
            $ref = trim((string) ($sale->numero_fatura ?? ''));
            if ($ref !== '') {
                return 'Utilizado no pagamento em loja (fatura '.$ref.')';
            }
        }

        return 'Utilizado no pagamento em loja';
    }
}
