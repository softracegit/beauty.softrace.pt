<?php

namespace App\Support;

use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Atribui linhas de venda à técnica da marcação (via calendar_event_service → evento).
 * Vendas consolidadas (várias marcações, uma fatura) repartem-se sem duplicar o documento fiscal.
 */
class SaleTechnicianAttribution
{
    /**
     * @return Collection<int, array{
     *     calendar_event_id: int,
     *     user_id: int|null,
     *     user_name: string,
     *     service_items: Collection<int, SaleItem>,
     *     item_subtotal: float,
     *     taxas: float,
     *     desconto: float,
     *     gorjeta: float,
     *     valor: float,
     *     is_anchor: bool,
     *     is_consolidated: bool
     * }>
     */
    public static function slicesForSale(Sale $sale, ?int $serviceIdFilter = null, ?int $technicianUserIdFilter = null): Collection
    {
        $anchorEventId = (int) ($sale->calendar_event_id ?? 0);
        if ($anchorEventId <= 0) {
            return collect();
        }

        $sale->loadMissing([
            'items.service',
            'items.extra',
            'items.calendarEventService.event.user',
            'calendarEvent.user',
            'settledEvents',
        ]);

        $grouped = [];
        foreach ($sale->items as $item) {
            if ($serviceIdFilter && $item->tipo === SaleItem::TIPO_SERVICO && (int) ($item->service_id ?? 0) !== $serviceIdFilter) {
                continue;
            }

            $eventId = self::eventIdForItem($item, $anchorEventId, $sale);
            if ($eventId <= 0) {
                continue;
            }

            $grouped[$eventId] ??= [
                'calendar_event_id' => $eventId,
                'service_items' => collect(),
                'item_subtotal' => 0.0,
                'taxas' => 0.0,
            ];

            if ($item->tipo === SaleItem::TIPO_TAXA) {
                $grouped[$eventId]['taxas'] += (float) $item->subtotal;
            } elseif (in_array($item->tipo, [SaleItem::TIPO_SERVICO, SaleItem::TIPO_EXTRA], true)) {
                $grouped[$eventId]['service_items']->push($item);
                $grouped[$eventId]['item_subtotal'] += (float) $item->subtotal;
            }
        }

        if ($grouped === []) {
            return collect();
        }

        if ($serviceIdFilter) {
            $grouped = array_filter(
                $grouped,
                fn (array $g): bool => $g['service_items']->isNotEmpty()
            );
            if ($grouped === []) {
                return collect();
            }
        }

        $isConsolidated = count($grouped) > 1;
        $totalServicesSubtotal = array_sum(array_map(
            fn (array $g): float => $g['item_subtotal'],
            $grouped
        ));
        $docDesconto = max(0, (float) ($sale->desconto ?? 0));
        $docGorjeta = max(0, (float) ($sale->gorjeta ?? 0));

        $slices = collect();
        $remainingDesconto = $docDesconto;
        $index = 0;
        $count = count($grouped);

        foreach ($grouped as $eventId => $group) {
            $index++;
            $isAnchor = $eventId === $anchorEventId;
            $event = $group['service_items']->first()?->calendarEventService?->event
                ?? ($isAnchor ? $sale->calendarEvent : null);

            if (! $event instanceof CalendarEvent) {
                $event = CalendarEvent::query()
                    ->with('user')
                    ->find($eventId);
            }

            $userId = $event?->user_id !== null ? (int) $event->user_id : null;

            $sliceDesconto = 0.0;
            if ($docDesconto > 0 && $totalServicesSubtotal > 0.00001) {
                if ($index === $count) {
                    $sliceDesconto = round($remainingDesconto, 2);
                } else {
                    $sliceDesconto = round($docDesconto * ($group['item_subtotal'] / $totalServicesSubtotal), 2);
                    $remainingDesconto = round($remainingDesconto - $sliceDesconto, 2);
                }
            }

            $valor = max(0, round($group['item_subtotal'] - $sliceDesconto, 2));

            $slices->push([
                'calendar_event_id' => (int) $eventId,
                'user_id' => $userId,
                'user_name' => (string) ($event?->user?->name ?? '—'),
                'service_items' => $group['service_items']->values(),
                'item_subtotal' => round($group['item_subtotal'], 2),
                'taxas' => round($group['taxas'], 2),
                'desconto' => $sliceDesconto,
                'gorjeta' => $isAnchor ? round($docGorjeta, 2) : 0.0,
                'valor' => $valor,
                'is_anchor' => $isAnchor,
                'is_consolidated' => $isConsolidated,
            ]);
        }

        if ($technicianUserIdFilter) {
            $slices = $slices->filter(
                fn (array $slice): bool => (int) ($slice['user_id'] ?? 0) === $technicianUserIdFilter
            );
        }

        return $slices->values();
    }

    public static function isConsolidatedSale(Sale $sale): bool
    {
        $anchorEventId = (int) ($sale->calendar_event_id ?? 0);
        if ($anchorEventId <= 0) {
            return false;
        }

        $sale->loadMissing('items.calendarEventService');

        $eventIds = [];
        foreach ($sale->items as $item) {
            if (! in_array($item->tipo, [SaleItem::TIPO_SERVICO, SaleItem::TIPO_EXTRA, SaleItem::TIPO_TAXA], true)) {
                continue;
            }
            $eventId = self::eventIdForItem($item, $anchorEventId, $sale);
            if ($eventId > 0) {
                $eventIds[$eventId] = true;
            }
        }

        return count($eventIds) > 1;
    }

    public static function eventIdForItem(SaleItem $item, int $anchorEventId, ?Sale $sale = null): int
    {
        $fromPivot = (int) ($item->calendarEventService?->calendar_event_id ?? 0);
        if ($fromPivot > 0) {
            return $fromPivot;
        }

        if ($sale instanceof Sale) {
            $resolved = self::resolveEventIdFromConsolidatedSale($item, $sale, $anchorEventId);
            if ($resolved > 0) {
                return $resolved;
            }
        }

        return $anchorEventId;
    }

    /**
     * Quando calendar_event_service_id falta no item, tenta casar service_id com marcações da venda.
     */
    public static function resolveEventIdFromConsolidatedSale(SaleItem $item, Sale $sale, int $anchorEventId): int
    {
        $serviceId = (int) ($item->service_id ?? 0);
        if ($serviceId <= 0) {
            return 0;
        }

        $linkedEventIds = self::linkedEventIdsForSale($sale, $anchorEventId);
        if ($linkedEventIds->count() <= 1) {
            return 0;
        }

        $matches = DB::table('calendar_event_services')
            ->whereIn('calendar_event_id', $linkedEventIds->all())
            ->where('service_id', $serviceId)
            ->get(['id', 'calendar_event_id']);

        if ($matches->isEmpty()) {
            return 0;
        }

        if ($matches->count() === 1) {
            return (int) $matches->first()->calendar_event_id;
        }

        $nonAnchor = $matches->first(fn ($row): bool => (int) $row->calendar_event_id !== $anchorEventId);
        if ($nonAnchor !== null) {
            return (int) $nonAnchor->calendar_event_id;
        }

        return (int) $matches->first()->calendar_event_id;
    }

    /**
     * calendar_event_service_id em falta: preenche a ligação pivot quando há match único na venda consolidada.
     */
    public static function resolveCalendarEventServiceIdForItem(SaleItem $item, Sale $sale, int $anchorEventId): ?int
    {
        if ((int) ($item->calendar_event_service_id ?? 0) > 0) {
            return (int) $item->calendar_event_service_id;
        }

        $serviceId = (int) ($item->service_id ?? 0);
        if ($serviceId <= 0) {
            return null;
        }

        $eventId = self::resolveEventIdFromConsolidatedSale($item, $sale, $anchorEventId);
        if ($eventId <= 0) {
            return null;
        }

        $cesId = DB::table('calendar_event_services')
            ->where('calendar_event_id', $eventId)
            ->where('service_id', $serviceId)
            ->value('id');

        return $cesId !== null ? (int) $cesId : null;
    }

    /**
     * @return Collection<int, int>
     */
    private static function linkedEventIdsForSale(Sale $sale, int $anchorEventId): Collection
    {
        $sale->loadMissing('settledEvents');
        $ids = $sale->settledEvents
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->contains($anchorEventId)) {
            return $ids;
        }

        return $ids->prepend($anchorEventId)->unique()->values();
    }
}
