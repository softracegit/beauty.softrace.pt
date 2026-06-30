<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Support\ActivityLogContext;
use Illuminate\Support\Collection;

class MarcacaoPaymentActivityLogger
{
    public function logMarcacaoPaga(CalendarEvent $event, Sale $sale, ?User $causer = null): void
    {
        $this->write($event, 'marcacao_paga', 'Marcação paga', [
            'sale_id' => $sale->id,
            'numero_fatura' => $sale->numero_fatura,
            'total' => (float) $sale->total,
            'valor_pago' => (float) $sale->valor_pago,
            'payment_method' => $sale->payment_method,
            'scope' => $sale->scope,
        ], $causer);
    }

    public function logFaturaGerada(CalendarEvent $event, Sale $sale, ?User $causer = null): void
    {
        if ($sale->isInvoiceDraft()) {
            return;
        }

        $this->write($event, 'fatura_gerada', 'Fatura gerada', [
            'sale_id' => $sale->id,
            'numero_fatura' => $sale->numero_fatura,
            'total' => (float) $sale->total,
            'scope' => $sale->scope,
            'invoice_status' => $sale->invoice_status,
        ], $causer);
    }

    /**
     * @param  iterable<int, Sale>  $sales
     */
    public function logVendaAnulada(
        CalendarEvent $event,
        iterable $sales,
        string $reason,
        bool $finalInvoiceOnly,
        ?User $causer = null,
    ): void {
        $salesCollection = $sales instanceof Collection ? $sales : collect($sales);
        $activeSales = $salesCollection
            ->filter(fn (Sale $sale) => $sale->scope !== Sale::SCOPE_BOOKING_RESERVA)
            ->values();

        if ($activeSales->isEmpty()) {
            return;
        }

        $invoiceLabels = $activeSales
            ->map(fn (Sale $sale) => $sale->invoiceListLabel())
            ->filter()
            ->values()
            ->all();

        $description = $finalInvoiceOnly ? 'Fatura final anulada' : 'Venda anulada';

        $this->write($event, 'venda_anulada', $description, [
            'sale_ids' => $activeSales->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'numero_fatura' => implode(', ', $invoiceLabels),
            'motivo' => trim($reason) !== '' ? trim($reason) : null,
            'final_invoice_only' => $finalInvoiceOnly,
            'nota_credito' => $activeSales->contains(fn (Sale $sale) => $sale->vendus_credit_note_id !== null),
            'vendas_anuladas' => $activeSales->count(),
        ], $causer);
    }

    public function logPrePagamentoRecebido(
        CalendarEvent $event,
        float $amount,
        ?Sale $sale = null,
        ?User $causer = null,
    ): void {
        $properties = [
            'valor' => round($amount, 2),
        ];

        if ($sale instanceof Sale) {
            $properties['sale_id'] = $sale->id;
            $properties['numero_fatura'] = $sale->numero_fatura;
            $properties['payment_method'] = $sale->payment_method;
        }

        $this->write($event, 'pre_pagamento', 'Pré-pagamento recebido', $properties, $causer);

        if ($sale instanceof Sale) {
            $this->logFaturaGerada($event, $sale, $causer);
        }
    }

    /**
     * @param  Collection<int, CalendarEvent>  $events
     */
    public function logCheckoutCompleted(Collection $events, Sale $sale, ?User $causer = null): void
    {
        $causer = $this->resolveCauser(null, $causer);

        foreach ($events as $event) {
            $this->logMarcacaoPaga($event, $sale, $causer);
            $this->logFaturaGerada($event, $sale, $causer);
        }
    }

    public function logFaturaGeradaForSale(Sale $sale, ?User $causer = null): void
    {
        if ($sale->isInvoiceDraft()) {
            return;
        }

        $causer = $this->resolveCauser(null, $causer);

        foreach ($this->eventsForSale($sale) as $event) {
            $this->logFaturaGerada($event, $sale, $causer);
        }
    }

    /**
     * @return Collection<int, CalendarEvent>
     */
    public function eventsForSale(Sale $sale): Collection
    {
        $sale->loadMissing('settledEvents');

        if ($sale->settledEvents->isNotEmpty()) {
            return $sale->settledEvents->values();
        }

        $primary = $sale->calendarEvent;
        if ($primary instanceof CalendarEvent) {
            return collect([$primary]);
        }

        return collect();
    }

    public function resolveCauser(?int $userId = null, ?User $explicit = null): ?User
    {
        if ($explicit instanceof User) {
            return $explicit;
        }

        $authUser = auth()->user();
        if ($authUser instanceof User) {
            return $authUser;
        }

        if ($userId !== null && $userId > 0) {
            return User::query()->find($userId);
        }

        return null;
    }

    private function write(CalendarEvent $event, string $eventName, string $description, array $properties, ?User $causer = null): void
    {
        $line = ActivityLogContext::marcacaoLine($event);
        if ($line !== null) {
            $properties['contexto'] = $line;
        }

        $logger = activity()
            ->performedOn($event)
            ->event($eventName)
            ->withProperties($properties);

        $causer = $this->resolveCauser(null, $causer);
        if ($causer instanceof User) {
            $logger->causedBy($causer);
        }

        $logger->log($description);
    }
}
