<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Support\ApplicableFees;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AgendaSameDayPayableService
{
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
