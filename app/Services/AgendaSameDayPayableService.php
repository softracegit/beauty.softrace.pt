<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Support\ApplicableFees;
use App\Support\StoreBusinessTime;
use Carbon\CarbonImmutable;
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
        $today = StoreBusinessTime::nowForStore($storeId)->toDateString();

        $events = CalendarEvent::query()
            ->where('store_id', $storeId)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereDate('start_at', $today)
            ->with([
                'client:id,name',
                'user:id,name',
                'eventServiceItems' => fn ($q) => $q->with(['service', 'extras.extra']),
                'sales' => fn ($q) => $q->where('status', '!=', Sale::STATUS_ANULADO),
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
            ->map(function (CalendarEvent $event): array {
                $checkoutSubtotal = ApplicableFees::chargeSubtotalForCalendarEvent($event, $event->eventServiceItems);
                $amountDue = ApplicableFees::amountDueCashFromEventId((int) $event->id, $checkoutSubtotal);
                $startUnix = $this->startAtUnixForEvent($event);

                return [
                    'id' => (int) $event->id,
                    'start_time' => $startUnix !== null
                        ? CarbonImmutable::createFromTimestamp($startUnix)->setTimezone(config('app.timezone'))->format('H:i')
                        : $this->localStartTimeForEvent($event),
                    'client_name' => trim((string) ($event->client?->name ?? '')) ?: '—',
                    'agent_name' => trim((string) ($event->user?->name ?? '')) ?: '—',
                    'services_label' => $this->servicesLabelForEvent($event),
                    'amount_due' => round(max(0.0, $amountDue), 2),
                    'pending_invoice' => $this->hasPendingFinalInvoice($event, $amountDue),
                ];
            })
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
            $startUnix = $this->startAtUnixForEvent($event);

            return [
                'id' => (int) $event->id,
                'start_time' => $startUnix !== null
                    ? CarbonImmutable::createFromTimestamp($startUnix)->setTimezone(config('app.timezone'))->format('H:i')
                    : $this->localStartTimeForEvent($event),
                'start_at_unix' => $startUnix,
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

        $day = CarbonImmutable::parse((string) $anchor->start_at, config('app.timezone'))->toDateString();

        $events = CalendarEvent::query()
            ->where('store_id', (int) $anchor->store_id)
            ->where('client_id', (int) $anchor->client_id)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereDate('start_at', $day)
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

        $sales = $event->relationLoaded('sales') ? $event->sales : $event->sales()->where('status', '!=', Sale::STATUS_ANULADO)->get();

        return ! $sales->contains(fn (Sale $sale): bool => $sale->scope === Sale::SCOPE_CAIXA_LIQUIDACAO);
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

    private function localStartTimeForEvent(CalendarEvent $event): string
    {
        $raw = trim((string) $event->getRawOriginal('start_at'));
        if ($raw !== '') {
            try {
                // start_at é persistido em UTC; converter para timezone da agenda ao apresentar.
                return CarbonImmutable::parse($raw, 'UTC')
                    ->setTimezone(config('app.timezone'))
                    ->format('H:i');
            } catch (\Throwable) {
                // fallback abaixo
            }
        }

        return $event->start_at ? $event->start_at->format('H:i') : '';
    }

    private function startAtUnixForEvent(CalendarEvent $event): ?int
    {
        $raw = trim((string) $event->getRawOriginal('start_at'));
        if ($raw === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($raw, 'UTC')->timestamp;
        } catch (\Throwable) {
            return null;
        }
    }
}
