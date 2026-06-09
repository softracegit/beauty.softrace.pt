<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\Fee;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Collection;

class ApplicableFees
{
    /**
     * @return list<int>
     */
    private static function saleIdsLinkedToEvent(int $calendarEventId): array
    {
        $directIds = Sale::query()
            ->where('calendar_event_id', $calendarEventId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        $pivotIds = \Illuminate\Support\Facades\DB::table('sale_calendar_events')
            ->where('calendar_event_id', $calendarEventId)
            ->pluck('sale_id')
            ->map(fn ($id): int => (int) $id);

        return $directIds->merge($pivotIds)->unique()->values()->all();
    }

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

        $saleIds = self::saleIdsLinkedToEvent((int) $event->id);
        if ($saleIds === []) {
            return true;
        }

        return ! Sale::query()
            ->whereIn('id', $saleIds)
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

    /**
     * Valor já aplicado à marcação (vendas + pré-pagamento na receção, incluindo créditos da carteira).
     */
    public static function marcacaoMoneyTowardSubtotal(int $calendarEventId, bool $includeAnnulledCaixa = true): float
    {
        $saleIds = self::saleIdsLinkedToEvent($calendarEventId);
        if ($saleIds === []) {
            $saleIds = [-1];
        }

        $fromSales = round((float) Sale::query()
            ->whereIn('id', $saleIds)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(valor_pago, total)')), 2);
        // A fatura final anulada conta como «já pago» para o resumo/aviso amarelo (amount_due = 0),
        // mas NÃO ao re-emitir: aí precisamos do valor que estava nessa fatura para o novo documento.
        if ($includeAnnulledCaixa) {
            $annulledCaixa = round((float) Sale::query()
                ->whereIn('id', $saleIds)
                ->where('status', Sale::STATUS_ANULADO)
                ->where('scope', Sale::SCOPE_CAIXA_LIQUIDACAO)
                ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(valor_pago, total)')), 2);
            $fromSales = round($fromSales + $annulledCaixa, 2);
        }

        $booking = Booking::query()
            ->where('calendar_event_id', $calendarEventId)
            ->where('payment_status', Booking::PAYMENT_PAID)
            ->orderByDesc('id')
            ->first(['paid_amount', 'wallet_applied_cents']);

        if (! $booking instanceof Booking) {
            return max(0.0, $fromSales);
        }

        $paidAmount = round(max(0.0, (float) $booking->paid_amount), 2);
        $walletEur = round(max(0, (int) $booking->wallet_applied_cents) / 100, 2);
        // A venda de reserva só regista MB WAY/cartão; os créditos vivem no booking.
        $fromBooking = max($paidAmount, round($fromSales + $walletEur, 2));

        return round(max($fromSales, $fromBooking), 2);
    }

    /**
     * Pré-pagamento efectuado (para UI), incluindo parte em créditos sem fatura de reserva.
     */
    public static function marcacaoBookingPaidAmountForEvent(int $calendarEventId): float
    {
        $saleIds = self::saleIdsLinkedToEvent($calendarEventId);
        if ($saleIds === []) {
            $saleIds = [-1];
        }

        $fromReservaSales = round((float) Sale::query()
            ->whereIn('id', $saleIds)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->where('scope', Sale::SCOPE_BOOKING_RESERVA)
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(valor_pago, total)')), 2);

        $booking = Booking::query()
            ->where('calendar_event_id', $calendarEventId)
            ->where('payment_status', Booking::PAYMENT_PAID)
            ->orderByDesc('id')
            ->first(['paid_amount', 'wallet_applied_cents']);

        if (! $booking instanceof Booking) {
            return max(0.0, $fromReservaSales);
        }

        $paidAmount = round(max(0.0, (float) $booking->paid_amount), 2);
        $walletEur = round(max(0, (int) $booking->wallet_applied_cents) / 100, 2);

        return round(max($paidAmount, $fromReservaSales, round($fromReservaSales + $walletEur, 2)), 2);
    }

    public static function amountDueCashFromEventId(int $calendarEventId, float $servicesExtrasSubtotal): float
    {
        $saleIds = self::saleIdsLinkedToEvent($calendarEventId);
        if ($saleIds === []) {
            $saleIds = [-1];
        }

        $salesDiscount = (float) Sale::query()
            ->whereIn('id', $saleIds)
            ->where('status', '!=', Sale::STATUS_ANULADO)
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(desconto, 0)'));
        $netSubtotal = max(0.0, round($servicesExtrasSubtotal - $salesDiscount, 2));
        $moneyToward = self::marcacaoMoneyTowardSubtotal($calendarEventId);

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
     * Taxas efectivamente cobradas numa marcação (linhas tipo taxa nas vendas activas).
     *
     * @return list<array{fee_id: int|null, name: string, price: float, formatted_price: string}>
     */
    public static function chargedFeesForCalendarEvent(int $calendarEventId): array
    {
        $saleIds = self::saleIdsLinkedToEvent($calendarEventId);
        if ($saleIds === []) {
            return [];
        }

        $items = SaleItem::query()
            ->where('tipo', SaleItem::TIPO_TAXA)
            ->whereHas('sale', function ($q) use ($saleIds): void {
                $q->whereIn('id', $saleIds)
                    ->where('status', '!=', Sale::STATUS_ANULADO);
            })
            ->with('fee')
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            return [];
        }

        $byKey = [];
        foreach ($items as $item) {
            $feeId = (int) ($item->fee_id ?? 0);
            $key = $feeId > 0 ? 'fee_'.$feeId : 'line_'.$item->id;
            if (isset($byKey[$key])) {
                continue;
            }
            $price = round((float) ($item->subtotal ?? $item->preco_unitario ?? 0), 2);
            if ($price <= 0.00001) {
                continue;
            }
            $name = trim((string) ($item->fee?->name ?? $item->descricao ?? 'Taxa'));
            $byKey[$key] = [
                'fee_id' => $feeId > 0 ? $feeId : null,
                'name' => $name !== '' ? $name : 'Taxa',
                'price' => $price,
                'formatted_price' => number_format($price, 2, ',', ' ').' €',
            ];
        }

        return array_values($byKey);
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
