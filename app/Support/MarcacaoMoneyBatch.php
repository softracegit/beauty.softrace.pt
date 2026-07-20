<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\Fee;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pré-carga vendas/bookings/taxas para N marcações — evita N+1 de ApplicableFees.
 */
final class MarcacaoMoneyBatch
{
    /** @var array<int, Collection<int, Sale>> */
    private array $salesByEvent = [];

    /** @var array<int, Booking|null> */
    private array $bookingByEvent = [];

    /** @var Collection<int, Fee> */
    private Collection $catalogFees;

    /** @var array<int, list<array{fee_id: int|null, name: string, price: float, formatted_price: string}>> */
    private array $chargedFeesByEvent = [];

    /**
     * @param  list<int>  $eventIds
     * @param  int|null  $storeId  Para catálogo de taxas (opcional).
     */
    public function __construct(array $eventIds, ?int $storeId = null)
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds))));
        $this->catalogFees = collect();

        if ($eventIds === []) {
            return;
        }

        $direct = Sale::query()
            ->whereIn('calendar_event_id', $eventIds)
            ->get([
                'id', 'calendar_event_id', 'status', 'scope', 'valor_pago', 'total', 'desconto', 'payment_method', 'invoice_status',
            ]);

        $pivotRows = DB::table('sale_calendar_events')
            ->whereIn('calendar_event_id', $eventIds)
            ->get(['sale_id', 'calendar_event_id']);

        $missingSaleIds = $pivotRows->pluck('sale_id')
            ->map(fn ($id): int => (int) $id)
            ->diff($direct->pluck('id')->map(fn ($id): int => (int) $id))
            ->values()
            ->all();

        $extraSales = $missingSaleIds === []
            ? collect()
            : Sale::query()
                ->whereIn('id', $missingSaleIds)
                ->get([
                    'id', 'calendar_event_id', 'status', 'scope', 'valor_pago', 'total', 'desconto', 'payment_method', 'invoice_status',
                ]);

        $salesById = $direct->concat($extraSales)->keyBy(fn (Sale $s): int => (int) $s->id);

        foreach ($eventIds as $eventId) {
            $this->salesByEvent[$eventId] = collect();
            $this->chargedFeesByEvent[$eventId] = [];
        }

        foreach ($direct as $sale) {
            $eid = (int) ($sale->calendar_event_id ?? 0);
            if ($eid > 0 && isset($this->salesByEvent[$eid])) {
                $this->salesByEvent[$eid]->put((int) $sale->id, $sale);
            }
        }

        foreach ($pivotRows as $row) {
            $eid = (int) $row->calendar_event_id;
            $sid = (int) $row->sale_id;
            $sale = $salesById->get($sid);
            if ($sale instanceof Sale && isset($this->salesByEvent[$eid])) {
                $this->salesByEvent[$eid]->put($sid, $sale);
            }
        }

        foreach ($this->salesByEvent as $eid => $sales) {
            $this->salesByEvent[$eid] = $sales->sortBy('id')->values();
        }

        $bookings = Booking::query()
            ->whereIn('calendar_event_id', $eventIds)
            ->where('payment_status', Booking::PAYMENT_PAID)
            ->orderByDesc('id')
            ->get(['id', 'calendar_event_id', 'paid_amount', 'wallet_applied_cents']);

        foreach ($eventIds as $eventId) {
            $this->bookingByEvent[$eventId] = null;
        }
        foreach ($bookings as $booking) {
            $eid = (int) $booking->calendar_event_id;
            if ($eid > 0 && ($this->bookingByEvent[$eid] ?? null) === null) {
                $this->bookingByEvent[$eid] = $booking;
            }
        }

        if ($storeId !== null && $storeId > 0) {
            $this->catalogFees = Fee::query()
                ->where('store_id', $storeId)
                ->with(['services:id'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        $activeSaleIds = $salesById
            ->filter(fn (Sale $s): bool => ($s->status ?? '') !== Sale::STATUS_ANULADO)
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($activeSaleIds !== []) {
            $saleIdToEventIds = [];
            foreach ($this->salesByEvent as $eid => $sales) {
                foreach ($sales as $sale) {
                    if (($sale->status ?? '') === Sale::STATUS_ANULADO) {
                        continue;
                    }
                    $sid = (int) $sale->id;
                    $saleIdToEventIds[$sid] ??= [];
                    $saleIdToEventIds[$sid][] = $eid;
                }
            }

            $taxItems = SaleItem::query()
                ->where('tipo', SaleItem::TIPO_TAXA)
                ->whereIn('sale_id', $activeSaleIds)
                ->with('fee')
                ->orderBy('id')
                ->get();

            foreach ($taxItems as $item) {
                $sid = (int) $item->sale_id;
                foreach ($saleIdToEventIds[$sid] ?? [] as $eid) {
                    $feeId = (int) ($item->fee_id ?? 0);
                    $key = $feeId > 0 ? 'fee_'.$feeId : 'line_'.$item->id;
                    if (isset($this->chargedFeesByEvent[$eid][$key])) {
                        continue;
                    }
                    $price = round((float) ($item->subtotal ?? $item->preco_unitario ?? 0), 2);
                    if ($price <= 0.00001) {
                        continue;
                    }
                    $name = trim((string) ($item->fee?->name ?? $item->descricao ?? 'Taxa'));
                    $this->chargedFeesByEvent[$eid][$key] = [
                        'fee_id' => $feeId > 0 ? $feeId : null,
                        'name' => $name !== '' ? $name : 'Taxa',
                        'price' => $price,
                        'formatted_price' => number_format($price, 2, ',', ' ').' €',
                    ];
                }
            }

            foreach ($this->chargedFeesByEvent as $eid => $fees) {
                $this->chargedFeesByEvent[$eid] = array_values($fees);
            }
        }
    }

    /**
     * @param  Collection<int, CalendarEvent>  $events
     * @return array{previsto: float, por_fazer: float}
     */
    public static function sumPipelineTotals(Collection $events): array
    {
        if ($events->isEmpty()) {
            return ['previsto' => 0.0, 'por_fazer' => 0.0];
        }

        $eventIds = $events->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $storeId = (int) ($events->first()?->store_id ?? current_store_id());
        $batch = new self($eventIds, $storeId);

        $previsto = 0.0;
        $porFazer = 0.0;
        foreach ($events as $event) {
            $serviceItems = $event->eventServiceItems ?? collect();
            $subtotal = $batch->chargeSubtotal($event, $serviceItems);
            $due = $batch->amountDue((int) $event->id, $subtotal);
            $previsto += max(0.0, $subtotal);
            $porFazer += max(0.0, $due);
        }

        return [
            'previsto' => round($previsto, 2),
            'por_fazer' => round($porFazer, 2),
        ];
    }

    /**
     * @return Collection<int, Sale>
     */
    public function salesForEvent(int $eventId): Collection
    {
        return $this->salesByEvent[$eventId] ?? collect();
    }

    /**
     * @return Collection<int, Sale>
     */
    public function activeSalesForEvent(int $eventId): Collection
    {
        return $this->salesForEvent($eventId)
            ->filter(fn (Sale $s): bool => ($s->status ?? Sale::STATUS_PAGO) !== Sale::STATUS_ANULADO)
            ->values();
    }

    public function moneyToward(int $eventId, bool $includeAnnulledCaixa = true): float
    {
        $sales = $this->salesForEvent($eventId);
        $fromSales = round((float) $sales
            ->filter(fn (Sale $s): bool => ($s->status ?? '') !== Sale::STATUS_ANULADO)
            ->sum(fn (Sale $s): float => (float) ($s->valor_pago ?? $s->total ?? 0)), 2);

        if ($includeAnnulledCaixa) {
            $annulledCaixa = round((float) $sales
                ->filter(fn (Sale $s): bool => ($s->status ?? '') === Sale::STATUS_ANULADO
                    && ($s->scope ?? '') === Sale::SCOPE_CAIXA_LIQUIDACAO)
                ->sum(fn (Sale $s): float => (float) ($s->valor_pago ?? $s->total ?? 0)), 2);
            $fromSales = round($fromSales + $annulledCaixa, 2);
        }

        $booking = $this->bookingByEvent[$eventId] ?? null;
        if (! $booking instanceof Booking) {
            return max(0.0, $fromSales);
        }

        $paidAmount = round(max(0.0, (float) $booking->paid_amount), 2);
        $walletEur = round(max(0, (int) $booking->wallet_applied_cents) / 100, 2);
        $fromBooking = max($paidAmount, round($fromSales + $walletEur, 2));

        return round(max($fromSales, $fromBooking), 2);
    }

    public function bookingPaidAmount(int $eventId): float
    {
        $fromReservaSales = round((float) $this->salesForEvent($eventId)
            ->filter(fn (Sale $s): bool => ($s->status ?? '') !== Sale::STATUS_ANULADO
                && ($s->scope ?? '') === Sale::SCOPE_BOOKING_RESERVA)
            ->sum(fn (Sale $s): float => (float) ($s->valor_pago ?? $s->total ?? 0)), 2);

        $booking = $this->bookingByEvent[$eventId] ?? null;
        if (! $booking instanceof Booking) {
            return max(0.0, $fromReservaSales);
        }

        $paidAmount = round(max(0.0, (float) $booking->paid_amount), 2);
        $walletEur = round(max(0, (int) $booking->wallet_applied_cents) / 100, 2);

        return round(max($paidAmount, $fromReservaSales, round($fromReservaSales + $walletEur, 2)), 2);
    }

    public function salesDiscount(int $eventId): float
    {
        return round((float) $this->salesForEvent($eventId)
            ->filter(fn (Sale $s): bool => ($s->status ?? '') !== Sale::STATUS_ANULADO)
            ->sum(fn (Sale $s): float => (float) ($s->desconto ?? 0)), 2);
    }

    public function hasActiveCaixa(int $eventId): bool
    {
        return $this->salesForEvent($eventId)->contains(
            fn (Sale $s): bool => ($s->status ?? '') !== Sale::STATUS_ANULADO
                && ($s->scope ?? '') === Sale::SCOPE_CAIXA_LIQUIDACAO
        );
    }

    public function amountDue(int $eventId, float $servicesExtrasSubtotal): float
    {
        $netSubtotal = max(0.0, round($servicesExtrasSubtotal - $this->salesDiscount($eventId), 2));

        return max(0.0, round($netSubtotal - $this->moneyToward($eventId), 2));
    }

    /**
     * @param  iterable<int, mixed>  $serviceItems
     */
    public function includeCatalogFees(CalendarEvent $event, iterable $serviceItems): bool
    {
        if (($event->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return false;
        }

        $servicesSubtotal = ApplicableFees::servicesExtrasSubtotalFromEventItems($serviceItems);
        if ($servicesSubtotal <= 0.00001) {
            return false;
        }

        if ($this->amountDue((int) $event->id, $servicesSubtotal) <= 0.00001) {
            return false;
        }

        return ! $this->hasActiveCaixa((int) $event->id);
    }

    /**
     * @param  iterable<int, mixed>  $serviceItems
     * @return list<array{fee_id: int, name: string, price: float, formatted_price: string}>
     */
    public function catalogFeesForServiceItems(iterable $serviceItems): array
    {
        $ids = collect($serviceItems)->map(function ($item): int {
            if ($item instanceof \App\Models\CalendarEventService) {
                return (int) $item->service_id;
            }
            if (is_array($item)) {
                return (int) ($item['service_id'] ?? $item['id'] ?? 0);
            }

            return 0;
        })->filter(fn (int $id): bool => $id > 0)->unique()->values();

        if ($ids->isEmpty() || $this->catalogFees->isEmpty()) {
            return [];
        }

        $idSet = $ids->flip()->all();

        return $this->catalogFees
            ->filter(function (Fee $fee) use ($idSet): bool {
                foreach ($fee->services as $service) {
                    if (isset($idSet[(int) $service->id])) {
                        return true;
                    }
                }

                return false;
            })
            ->unique('id')
            ->values()
            ->map(fn (Fee $fee): array => [
                'fee_id' => (int) $fee->id,
                'name' => $fee->name,
                'price' => round((float) $fee->price, 2),
                'formatted_price' => $fee->formatted_price,
            ])
            ->all();
    }

    /**
     * @param  iterable<int, mixed>  $serviceItems
     */
    public function chargeSubtotal(CalendarEvent $event, iterable $serviceItems): float
    {
        $servicesSubtotal = ApplicableFees::servicesExtrasSubtotalFromEventItems($serviceItems);
        if (! $this->includeCatalogFees($event, $serviceItems)) {
            return $servicesSubtotal;
        }

        return round($servicesSubtotal + ApplicableFees::sumPrices($this->catalogFeesForServiceItems($serviceItems)), 2);
    }

    /**
     * @return list<array{fee_id: int|null, name: string, price: float, formatted_price: string}>
     */
    public function chargedFees(int $eventId): array
    {
        return $this->chargedFeesByEvent[$eventId] ?? [];
    }
}
