<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Fee;
use App\Models\Sale;
use Illuminate\Support\Collection;

class ApplicableFees
{
    public static function servicesExtrasSubtotalFromEventItems(iterable $items): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            if ($item instanceof CalendarEventService) {
                $sum += (float) ($item->price ?? 0);
                foreach ($item->extras as $ex) {
                    $sum += (float) ($ex->price ?? $ex->extra?->price ?? 0);
                }
            } elseif (is_array($item)) {
                $sum += (float) ($item['price'] ?? 0);
                $sum += (float) collect($item['extras'] ?? [])->sum(fn (array $ex) => (float) ($ex['price'] ?? 0));
            }
        }

        return round(max(0.0, $sum), 2);
    }

    /**
     * Taxas de catálogo só para marcações ainda não liquidadas (serviços+extras) e sem venda de caixa activa.
     */
    public static function includeCatalogFeesForCalendarEvent(CalendarEvent $event): bool
    {
        if (($event->event_type ?? '') !== CalendarEvent::TYPE_MARCACAO) {
            return false;
        }

        $serviceItems = $event->relationLoaded('eventServiceItems')
            ? $event->eventServiceItems
            : $event->eventServiceItems()->with('extras.extra')->get();

        $servicesSubtotal = self::servicesExtrasSubtotalFromEventItems($serviceItems);
        if ($servicesSubtotal <= 0.00001) {
            return false;
        }

        if (self::amountDueCashFromEventId((int) $event->id, $servicesSubtotal) <= 0.00001) {
            return false;
        }

        return ! Sale::query()
            ->where('calendar_event_id', $event->id)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->where('scope', Sale::SCOPE_CAIXA_LIQUIDACAO)
            ->exists();
    }

    public static function chargeSubtotalForCalendarEvent(CalendarEvent $event, iterable $serviceItems): float
    {
        $servicesSubtotal = self::servicesExtrasSubtotalFromEventItems($serviceItems);
        if (! self::includeCatalogFeesForCalendarEvent($event)) {
            return $servicesSubtotal;
        }

        $serviceIds = collect($serviceItems)->map(function ($item) {
            if ($item instanceof CalendarEventService) {
                return (int) $item->service_id;
            }
            if (is_array($item)) {
                return (int) ($item['service_id'] ?? $item['id'] ?? 0);
            }

            return 0;
        })->filter(fn (int $id) => $id > 0);

        $feesTotal = self::sumPrices(self::forServiceIds($serviceIds, (int) $event->store_id));

        return round($servicesSubtotal + $feesTotal, 2);
    }

    public static function amountDueCashFromEventId(int $calendarEventId, float $servicesExtrasSubtotal): float
    {
        $salesActive = (float) Sale::query()
            ->where('calendar_event_id', $calendarEventId)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(valor_pago, total)'));
        $salesDiscount = (float) Sale::query()
            ->where('calendar_event_id', $calendarEventId)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(desconto, 0)'));
        $annulledCaixa = (float) Sale::query()
            ->where('calendar_event_id', $calendarEventId)
            ->where('status', Sale::STATUS_ANULADO)
            ->where('scope', Sale::SCOPE_CAIXA_LIQUIDACAO)
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(valor_pago, total)'));
        $bookingPaid = (float) Booking::query()
            ->where('calendar_event_id', $calendarEventId)
            ->where('payment_status', Booking::PAYMENT_PAID)
            ->orderByDesc('id')
            ->value('paid_amount');
        $netSubtotal = max(0.0, round($servicesExtrasSubtotal - $salesDiscount, 2));
        $moneyToward = round(max(round($salesActive + $annulledCaixa, 2), round(max($bookingPaid, 0.0), 2), 0.0), 2);

        return max(0.0, round($netSubtotal - $moneyToward, 2));
    }

    /**
     * @return list<array{tipo: string, fee_id: int, descricao: string, quantidade: int, preco_unitario: float, subtotal: float, sort_order: int}>
     */
    public static function checkoutFeeLineItems(CalendarEvent $event, int $sortOrderStart = 0): array
    {
        if (! self::includeCatalogFeesForCalendarEvent($event)) {
            return [];
        }

        $serviceIds = $event->eventServiceItems->pluck('service_id');
        $lines = [];
        $sort = $sortOrderStart;
        foreach (self::forServiceIds($serviceIds, (int) $event->store_id) as $fee) {
            $price = (float) $fee['price'];
            $lines[] = [
                'tipo' => \App\Models\SaleItem::TIPO_TAXA,
                'fee_id' => (int) $fee['fee_id'],
                'descricao' => $fee['name'],
                'quantidade' => 1,
                'preco_unitario' => $price,
                'subtotal' => $price,
                'sort_order' => $sort++,
            ];
        }

        return $lines;
    }

    /**
     * Taxas de catálogo aplicáveis a um conjunto de serviços (deduplicadas por fee_id).
     *
     * @param  iterable<int|string>  $serviceIds
     * @return list<array{fee_id: int, name: string, price: float, formatted_price: string}>
     */
    public static function forServiceIds(iterable $serviceIds, ?int $storeId = null): array
    {
        $ids = collect($serviceIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $storeId = $storeId ?? (int) current_store_id();

        $fees = Fee::query()
            ->where('store_id', $storeId)
            ->whereHas('services', fn ($q) => $q->whereIn('services.id', $ids))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();

        return $fees->map(fn (Fee $fee) => [
            'fee_id' => (int) $fee->id,
            'name' => $fee->name,
            'price' => round((float) $fee->price, 2),
            'formatted_price' => $fee->formatted_price,
        ])->all();
    }

    /**
     * @param  Collection<int, Service>|iterable<Service>  $services
     * @return list<array{fee_id: int, name: string, price: float, formatted_price: string}>
     */
    public static function forServices(iterable $services, ?int $storeId = null): array
    {
        $serviceIds = collect($services)->map(fn ($s) => (int) ($s->id ?? 0));

        return self::forServiceIds($serviceIds, $storeId);
    }

    /**
     * @param  array<int, array{service_id?: int|null, id?: int|null}>  $eventServicesPayload
     * @return list<array{fee_id: int, name: string, price: float, formatted_price: string}>
     */
    public static function forEventServicesPayload(array $eventServicesPayload, ?int $storeId = null): array
    {
        $serviceIds = collect($eventServicesPayload)
            ->map(fn (array $row) => (int) ($row['service_id'] ?? $row['id'] ?? 0))
            ->filter(fn (int $id) => $id > 0);

        return self::forServiceIds($serviceIds, $storeId);
    }

    /**
     * @param  list<array{fee_id: int, name: string, price: float, formatted_price?: string}>  $fees
     */
    public static function sumPrices(array $fees): float
    {
        return round(collect($fees)->sum(fn (array $f) => (float) ($f['price'] ?? 0)), 2);
    }
}
