<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Support\ApplicableFees;
use App\Support\DateTimeDisplay;
use App\Support\StoreBusinessTime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AgendaSameDayPayableService
{
    /**
     * Marcações de hoje (fuso da loja) com saldo por pagar ou fatura final em falta.
     *
     * @return array{
     *   count: int,
     *   total_due: float,
     *   rows: list<array{
     *     id: int,
     *     start_time: string,
     *     client_name: string,
     *     agent_name: string,
     *     services_label: string,
     *     amount_due: float,
     *     pending_invoice: bool
     *   }>
     * }
     */
    public function unpaidMarcacoesTodayForStore(int $storeId): array
    {
        $events = $this->marcacoesForStoreDay($storeId)
            ->with([
                'client:id,name',
                'user:id,name',
                'eventServiceItems' => fn ($q) => $q->with(['service', 'extras.extra']),
            ])
            ->orderBy('start_at')
            ->orderBy('id')
            ->get();

        $rows = $events
            ->filter(function (CalendarEvent $event): bool {
                if ($event->isMarcacaoStatusLocked()) {
                    return false;
                }

                return $this->isUnpaidOrPendingInvoice($event);
            })
            ->map(fn (CalendarEvent $event): array => $this->mapUnpaidRow($event))
            ->values();

        return [
            'count' => $rows->count(),
            'total_due' => round((float) $rows->sum('amount_due'), 2),
            'rows' => $rows->all(),
        ];
    }

    /**
     * @return array{count:int,total_due:float,rows:list<array{id:int,start_time:string,start_at_unix:int|null,services_label:string,amount_due:float,booking_paid_amount:float}>}
     */
    public function summaryForEvent(CalendarEvent $anchor): array
    {
        $rows = $this->siblingsForEvent($anchor)->map(function (CalendarEvent $event): array {
            $checkoutSubtotal = ApplicableFees::chargeSubtotalForCalendarEvent($event, $event->eventServiceItems);
            $amountDue = ApplicableFees::amountDueCashFromEventId((int) $event->id, $checkoutSubtotal);

            return [
                'id' => (int) $event->id,
                'start_time' => DateTimeDisplay::marcacao($event->start_at, (int) $event->store_id, 'H:i'),
                'start_at_unix' => $event->start_at?->timestamp,
                'services_label' => $this->servicesLabelForEvent($event),
                'amount_due' => round(max(0.0, $amountDue), 2),
                'booking_paid_amount' => round(max(0.0, ApplicableFees::marcacaoBookingPaidAmountForEvent((int) $event->id)), 2),
            ];
        })->values();

        return [
            'count' => $rows->count(),
            'total_due' => round((float) $rows->sum('amount_due'), 2),
            'rows' => $rows->all(),
        ];
    }

    /**
     * @return Collection<int, CalendarEvent>
     */
    public function siblingsForEvent(CalendarEvent $anchor): Collection
    {
        if (! $anchor->client_id || ($anchor->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return collect();
        }

        $anchorDay = DateTimeDisplay::inBusiness($anchor->start_at, (int) $anchor->store_id);
        if ($anchorDay === null) {
            return collect();
        }

        $events = $this->marcacoesForStoreDay((int) $anchor->store_id, $anchorDay)
            ->where('client_id', (int) $anchor->client_id)
            ->with(['eventServiceItems' => fn ($q) => $q->with(['service', 'extras.extra'])])
            ->orderBy('start_at')
            ->orderBy('id')
            ->get();

        return $events
            ->filter(function (CalendarEvent $event): bool {
                if ($event->isMarcacaoStatusLocked()) {
                    return false;
                }

                $checkoutSubtotal = ApplicableFees::chargeSubtotalForCalendarEvent($event, $event->eventServiceItems);
                $amountDue = ApplicableFees::amountDueCashFromEventId((int) $event->id, $checkoutSubtotal);

                return $amountDue > 0.00001;
            })
            ->values();
    }

    /**
     * @return Builder<CalendarEvent>
     */
    private function marcacoesForStoreDay(int $storeId, ?CarbonInterface $dayInStoreTimezone = null): Builder
    {
        $day = $dayInStoreTimezone ?? StoreBusinessTime::nowForStore($storeId);
        [$startOfDay, $endOfDay] = $this->dayRangeForStore($day);

        return CalendarEvent::query()
            ->where('store_id', $storeId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereBetween('start_at', [$startOfDay, $endOfDay]);
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function dayRangeForStore(CarbonInterface $dayInStoreTimezone): array
    {
        return [
            $dayInStoreTimezone->copy()->startOfDay(),
            $dayInStoreTimezone->copy()->endOfDay(),
        ];
    }

    /**
     * @return array{
     *   id: int,
     *   start_time: string,
     *   client_name: string,
     *   agent_name: string,
     *   services_label: string,
     *   amount_due: float,
     *   pending_invoice: bool
     * }
     */
    private function mapUnpaidRow(CalendarEvent $event): array
    {
        $checkoutSubtotal = ApplicableFees::chargeSubtotalForCalendarEvent($event, $event->eventServiceItems);
        $amountDue = ApplicableFees::amountDueCashFromEventId((int) $event->id, $checkoutSubtotal);

        return [
            'id' => (int) $event->id,
            'start_time' => DateTimeDisplay::marcacao($event->start_at, (int) $event->store_id, 'H:i'),
            'client_name' => trim((string) ($event->client?->name ?? '')) ?: '—',
            'agent_name' => trim((string) ($event->user?->name ?? '')) ?: '—',
            'services_label' => $this->servicesLabelForEvent($event),
            'amount_due' => round(max(0.0, $amountDue), 2),
            'pending_invoice' => $this->hasPendingFinalInvoice($event, $amountDue),
        ];
    }

    private function isUnpaidOrPendingInvoice(CalendarEvent $event): bool
    {
        $checkoutSubtotal = ApplicableFees::chargeSubtotalForCalendarEvent($event, $event->eventServiceItems);
        $amountDue = ApplicableFees::amountDueCashFromEventId((int) $event->id, $checkoutSubtotal);

        if ($amountDue > 0.00001) {
            return true;
        }

        return $this->hasPendingFinalInvoice($event, $amountDue);
    }

    private function hasPendingFinalInvoice(CalendarEvent $event, float $amountDue): bool
    {
        if (($event->status ?? '') !== CalendarEvent::STATUS_COMPLETO) {
            return false;
        }

        $servicesSubtotal = ApplicableFees::servicesExtrasSubtotalFromEventItems($event->eventServiceItems);
        if ($servicesSubtotal <= 0.00001) {
            return false;
        }

        if ($amountDue > 0.00001) {
            return false;
        }

        return ! ApplicableFees::hasActiveCaixaLiquidacaoSaleForEvent((int) $event->id);
    }

    private function servicesLabelForEvent(CalendarEvent $event): string
    {
        $labels = $event->eventServiceItems
            ->map(fn ($item): string => trim((string) ($item->service?->name ?? 'Serviço')))
            ->filter(fn (string $name): bool => $name !== '')
            ->values();

        if ($labels->isEmpty()) {
            return trim((string) ($event->title ?? 'Marcação'));
        }

        return $labels->join(', ');
    }
}
