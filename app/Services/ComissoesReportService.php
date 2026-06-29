<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Support\SaleTechnicianAttribution;
use App\Support\TechnicianFilterUserId;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ComissoesReportService
{
    public function __construct(
        private readonly ZappyCommissionHistoricoService $zappyCommissionHistorico,
    ) {}

    /**
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed, estado?: ?string}  $filters
     */
    public function reportQuery(array $filters): Builder
    {
        $estado = (string) ($filters['estado'] ?? '');
        $eventSub = $this->eventQuery($filters)->select('calendar_events.id');

        $query = Sale::query()
            ->where('store_id', current_store_id())
            ->where('status', Sale::STATUS_PAGO)
            ->where(function (Builder $q) use ($eventSub): void {
                $q->whereIn('calendar_event_id', $eventSub)
                    ->orWhereHas('items.calendarEventService', fn (Builder $iq) => $iq->whereIn('calendar_event_id', $eventSub));
            });

        if (in_array($estado, [Sale::INVOICE_STATUS_FATURADO, Sale::INVOICE_STATUS_RASCUNHO], true)) {
            $query->where('invoice_status', $estado);
        } else {
            $query->whereIn('invoice_status', [Sale::INVOICE_STATUS_FATURADO, Sale::INVOICE_STATUS_RASCUNHO]);
        }

        return $query;
    }

    /**
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed}  $filters
     */
    public function eventQuery(array $filters): Builder
    {
        $desde = $filters['desde'] ?? null;
        $ate = $filters['ate'] ?? null;
        if (! $desde) {
            $desde = now()->copy()->startOfMonth()->toDateString();
        }
        if (! $ate) {
            $ate = now()->copy()->endOfMonth()->toDateString();
        }

        $servico = $filters['servico'] ?? null;
        $tecnico = TechnicianFilterUserId::resolve($filters['tecnico'] ?? null);
        $cliente = $filters['cliente'] ?? null;

        $query = CalendarEvent::query()
            ->forStore(current_store_id())
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereDate('start_at', '>=', $desde)
            ->whereDate('start_at', '<=', $ate);

        if ($servico) {
            $query->whereHas('eventServiceItems', fn (Builder $q) => $q->where('service_id', $servico));
        }
        if ($tecnico) {
            $query->where('user_id', $tecnico);
        }
        if ($cliente) {
            $query->where('client_id', $cliente);
        }

        return $query;
    }

    /**
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed, estado?: ?string}  $filters
     * @return Collection<int, Sale>
     */
    public function salesForReport(array $filters): Collection
    {
        return $this->reportQuery($filters)
            ->with([
                'client',
                'items.service',
                'items.extra',
                'items.calendarEventService.event.user',
                'calendarEvent.user',
                'settledEvents',
            ])
            ->get()
            ->sort(function (Sale $a, Sale $b): int {
                $aDate = $a->data_emissao?->format('Y-m-d') ?? '';
                $bDate = $b->data_emissao?->format('Y-m-d') ?? '';
                if ($aDate !== $bDate) {
                    return $bDate <=> $aDate;
                }

                return $b->id <=> $a->id;
            })
            ->values();
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return Collection<int, object{
     *     sale_id: int,
     *     sale_item_id: int,
     *     user_id: int,
     *     data_emissao: \Carbon\CarbonInterface|null,
     *     numero_fatura: ?string,
     *     tecnico: string,
     *     cliente: string,
     *     servico: string,
     *     valor_com_iva: float,
     *     valor_sem_iva: float,
     *     comissao_taxa: ?string,
     *     comissao_com_iva: float,
     *     comissao_sem_iva: float
     * }>
     */
    public function linesCollection(Collection $sales, ?int $servicoFilter = null, ?int $tecnicoFilter = null): Collection
    {
        $userIds = [];
        foreach ($sales as $sale) {
            $anchorEventId = (int) ($sale->calendar_event_id ?? 0);
            foreach ($sale->items as $item) {
                if (! in_array($item->tipo, [SaleItem::TIPO_SERVICO, SaleItem::TIPO_EXTRA], true)) {
                    continue;
                }
                $eventId = SaleTechnicianAttribution::eventIdForItem($item, $anchorEventId, $sale);
                if ($eventId <= 0) {
                    continue;
                }
                $event = $item->calendarEventService?->event
                    ?? ($eventId === $anchorEventId ? $sale->calendarEvent : null);
                if ($event?->user_id) {
                    $userIds[(int) $event->user_id] = true;
                }
            }
        }

        $agents = Agent::query()
            ->where('store_id', current_store_id())
            ->whereIn('user_id', array_keys($userIds))
            ->get(['user_id', 'commission_rate', 'commission_unit'])
            ->keyBy('user_id');

        $lines = collect();
        $euroCounted = [];

        foreach ($sales as $sale) {
            $lines = $lines->merge(
                $this->linesForSale($sale, $agents, $servicoFilter, $tecnicoFilter, $euroCounted)
            );
        }

        return $lines
            ->sort(function (object $a, object $b): int {
                $aTs = $a->data_emissao?->getTimestamp() ?? 0;
                $bTs = $b->data_emissao?->getTimestamp() ?? 0;
                if ($aTs !== $bTs) {
                    return $bTs <=> $aTs;
                }
                if ($a->sale_id !== $b->sale_id) {
                    return $b->sale_id <=> $a->sale_id;
                }

                return $a->sale_item_id <=> $b->sale_item_id;
            })
            ->values();
    }

    /**
     * @param  Collection<int, Agent>  $agents
     * @param  array<string, true>  $euroCounted
     * @return Collection<int, object>
     */
    private function linesForSale(
        Sale $sale,
        Collection $agents,
        ?int $servicoFilter,
        ?int $tecnicoFilter,
        array &$euroCounted,
    ): Collection {
        $anchorEventId = (int) ($sale->calendar_event_id ?? 0);
        if ($anchorEventId <= 0) {
            return collect();
        }

        $eligible = $sale->items
            ->filter(fn (SaleItem $item): bool => in_array($item->tipo, [SaleItem::TIPO_SERVICO, SaleItem::TIPO_EXTRA], true))
            ->values();

        if ($eligible->isEmpty()) {
            return collect();
        }

        $totalSub = (float) $eligible->sum(fn (SaleItem $item) => (float) $item->subtotal);
        $docDesconto = max(0, (float) ($sale->desconto ?? 0));
        $netFactor = $this->netFactorForSale($sale);
        $discountByItemId = $this->discountByItemId($eligible, $docDesconto, $totalSub);

        $out = collect();

        foreach ($eligible as $item) {
            if ($servicoFilter && $item->tipo === SaleItem::TIPO_SERVICO && (int) ($item->service_id ?? 0) !== $servicoFilter) {
                continue;
            }
            if ($servicoFilter && $item->tipo === SaleItem::TIPO_EXTRA) {
                continue;
            }

            $eventId = SaleTechnicianAttribution::eventIdForItem($item, $anchorEventId, $sale);
            if ($eventId <= 0) {
                continue;
            }

            $event = $item->calendarEventService?->event
                ?? ($eventId === $anchorEventId ? $sale->calendarEvent : null);
            if (! $event instanceof CalendarEvent) {
                $event = CalendarEvent::query()->with('user')->find($eventId);
            }

            $userId = $event?->user_id !== null ? (int) $event->user_id : 0;
            if ($tecnicoFilter && $userId !== $tecnicoFilter) {
                continue;
            }

            $lineDesconto = (float) ($discountByItemId[$item->id] ?? 0.0);

            $valorComIva = max(0, round((float) $item->subtotal - $lineDesconto, 2));
            $valorSemIva = round($valorComIva * $netFactor, 2);

            $agent = $agents->get($userId);
            $rate = $agent?->commission_rate;
            $unit = (string) ($agent?->commission_unit ?: Agent::COMMISSION_UNIT_PERCENT);
            $comissaoTaxa = $agent ? $agent->formatCommissionDisplay() : null;

            [$comissaoComIva, $comissaoSemIva] = $this->commissionAmounts(
                $valorComIva,
                $valorSemIva,
                $rate,
                $unit,
                (int) $sale->id,
                $userId,
                $euroCounted,
            );

            $out->push((object) [
                'sale_id' => (int) $sale->id,
                'sale_item_id' => (int) $item->id,
                'user_id' => $userId,
                'data_emissao' => $sale->data_emissao,
                'numero_fatura' => $sale->numero_fatura,
                'tecnico' => (string) ($event?->user?->name ?? '—'),
                'cliente' => (string) ($sale->client?->name ?? '—'),
                'servico' => $this->serviceLabelForSaleItem($item),
                'valor_com_iva' => $valorComIva,
                'valor_sem_iva' => $valorSemIva,
                'comissao_taxa' => $comissaoTaxa,
                'comissao_com_iva' => $comissaoComIva,
                'comissao_sem_iva' => $comissaoSemIva,
            ]);
        }

        return $out;
    }

    /**
     * @param  array<string, true>  $euroCounted
     * @return array{0: float, 1: float}
     */
    private function commissionAmounts(
        float $valorComIva,
        float $valorSemIva,
        mixed $rate,
        string $unit,
        int $saleId,
        int $userId,
        array &$euroCounted,
    ): array {
        if ($rate === null || $rate === '') {
            return [0.0, 0.0];
        }

        $rate = (float) $rate;

        if ($unit === Agent::COMMISSION_UNIT_EURO) {
            $key = $saleId.':'.$userId;
            if (isset($euroCounted[$key])) {
                return [0.0, 0.0];
            }
            $euroCounted[$key] = true;

            return [round($rate, 2), round($rate, 2)];
        }

        return [
            round($valorComIva * ($rate / 100), 2),
            round($valorSemIva * ($rate / 100), 2),
        ];
    }

    /**
     * @param  Collection<int, SaleItem>  $items
     * @return array<int, float>
     */
    private function discountByItemId(Collection $items, float $docDesconto, float $totalSub): array
    {
        if ($docDesconto <= 0 || $totalSub <= 0.00001 || $items->isEmpty()) {
            return [];
        }

        $discounts = [];
        $remaining = $docDesconto;
        $count = $items->count();

        foreach ($items->values() as $index => $item) {
            if ($index === $count - 1) {
                $discounts[(int) $item->id] = round($remaining, 2);
            } else {
                $share = round($docDesconto * ((float) $item->subtotal / $totalSub), 2);
                $discounts[(int) $item->id] = $share;
                $remaining = round($remaining - $share, 2);
            }
        }

        return $discounts;
    }

    private function netFactorForSale(Sale $sale): float
    {
        $total = (float) $sale->total;
        $iva = $sale->iva_total;

        if ($total > 0 && $iva !== null) {
            $net = $total - (float) $iva;
            if ($net >= 0 && $net <= $total) {
                return max(0.0, min(1.0, $net / $total));
            }
        }

        return round(100 / 123, 6);
    }

    private function serviceLabelForSaleItem(SaleItem $item): string
    {
        $optionName = trim((string) ($item->calendarEventService?->option_name ?? ''));
        if ($optionName !== '') {
            return $optionName;
        }

        $descricao = trim((string) $item->descricao);
        if ($descricao !== '') {
            return $descricao;
        }

        if ($item->service) {
            return (string) $item->service->name;
        }

        if ($item->extra) {
            return (string) $item->extra->name;
        }

        return (string) ($item->calendarEventService?->service?->name ?? '—');
    }

    /**
     * @param  Collection<int, object>  $lines
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed, estado?: ?string}  $filters
     * @return array{total_comissao_com_iva: float, total_comissao_sem_iva: float}
     */
    public function totaisRodape(Collection $lines, array $filters = []): array
    {
        $historical = $this->zappyCommissionHistorico->footerTotals($filters, $lines)
            ?? $this->historicalFooterFromConfigFile($filters, $lines);

        if ($historical !== null) {
            return $historical;
        }

        return [
            'total_comissao_com_iva' => round((float) $lines->sum(fn (object $line) => (float) $line->comissao_com_iva), 2),
            'total_comissao_sem_iva' => round((float) $lines->sum(fn (object $line) => (float) $line->comissao_sem_iva), 2),
        ];
    }

    /**
     * O rodapé usa totais Zappy (hardcoded) para meses até 31/05/2026, sem filtros por cliente/serviço.
     *
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed, estado?: ?string}  $filters
     */
    public function footerUsesHistoricalOverride(array $filters): bool
    {
        foreach (['cliente', 'servico'] as $key) {
            $value = $filters[$key] ?? null;
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        $desde = $this->normalizeRelatorioDate($filters['desde'] ?? '') ?? '';

        return $desde !== '' && $desde <= ZappyCommissionHistoricoService::historicalEndDate();
    }

    /**
     * Fallback: lê zappy_commission_totals.php directamente (ignora config:cache e opcache).
     *
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed}  $filters
     * @param  Collection<int, object>  $lines
     * @return array{total_comissao_com_iva: float, total_comissao_sem_iva: float}|null
     */
    private function historicalFooterFromConfigFile(array $filters, Collection $lines): ?array
    {
        foreach (['cliente', 'servico'] as $key) {
            $value = $filters[$key] ?? null;
            if ($value !== null && $value !== '') {
                return null;
            }
        }

        $path = config_path('zappy_commission_totals.php');
        if (! is_readable($path)) {
            return null;
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }

        $raw = require $path;
        if (! is_array($raw)) {
            return null;
        }

        $desde = $this->normalizeRelatorioDate($filters['desde'] ?? '') ?? '';
        $ate = $this->normalizeRelatorioDate($filters['ate'] ?? '') ?? '';
        if ($desde === '' || $ate === '' || $desde > '2026-05-31') {
            return null;
        }

        $totals = [];
        foreach ($raw as $key => $months) {
            if (! is_array($months) || ! is_numeric((string) $key)) {
                continue;
            }
            $totals[(int) $key] = $months;
        }

        $userId = TechnicianFilterUserId::resolve($filters['tecnico'] ?? null);
        if ($userId === null && ! empty($filters['tecnico'])) {
            $rawId = (int) $filters['tecnico'];
            $userId = isset($totals[$rawId]) ? $rawId : null;
        }

        $userIds = $userId !== null ? [$userId] : array_keys($totals);
        $historicalEnd = min($ate, '2026-05-31');

        $comIva = 0.0;
        $semIva = 0.0;
        $cursor = \Carbon\Carbon::parse($desde)->startOfMonth();
        $end = \Carbon\Carbon::parse($historicalEnd)->endOfMonth();

        while ($cursor->lte($end)) {
            $ym = $cursor->format('Y-m');
            $monthStart = $cursor->copy()->startOfMonth()->toDateString();
            $monthEnd = $cursor->copy()->endOfMonth()->toDateString();

            if ($monthEnd < $desde || $monthStart > $historicalEnd || $monthEnd > '2026-05-31') {
                $cursor->addMonth();

                continue;
            }

            foreach ($userIds as $id) {
                $month = $totals[$id][$ym] ?? null;
                if ($month === null) {
                    continue;
                }
                $comIva += (float) ($month['com_iva'] ?? 0);
                $semIva += (float) ($month['sem_iva'] ?? 0);
            }

            $cursor->addMonth();
        }

        if ($ate <= '2026-05-31') {
            return [
                'total_comissao_com_iva' => round($comIva, 2),
                'total_comissao_sem_iva' => round($semIva, 2),
            ];
        }

        $crmFrom = \Carbon\Carbon::parse('2026-05-31')->addDay()->toDateString();
        $crmCom = (float) $lines->filter(function (object $line) use ($crmFrom, $ate): bool {
            $date = $line->data_emissao?->format('Y-m-d');

            return $date !== null && $date >= $crmFrom && $date <= $ate;
        })->sum(fn (object $l) => (float) $l->comissao_com_iva);

        $crmSem = (float) $lines->filter(function (object $line) use ($crmFrom, $ate): bool {
            $date = $line->data_emissao?->format('Y-m-d');

            return $date !== null && $date >= $crmFrom && $date <= $ate;
        })->sum(fn (object $l) => (float) $l->comissao_sem_iva);

        return [
            'total_comissao_com_iva' => round($comIva + $crmCom, 2),
            'total_comissao_sem_iva' => round($semIva + $crmSem, 2),
        ];
    }

    private function normalizeRelatorioDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $value)) {
            return $value;
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $value, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function servicosOpts(): Collection
    {
        return Service::query()
            ->forStore(current_store_id())
            ->join('calendar_event_services', 'services.id', '=', 'calendar_event_services.service_id')
            ->join('calendar_events', 'calendar_events.id', '=', 'calendar_event_services.calendar_event_id')
            ->where('calendar_events.store_id', current_store_id())
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->select('services.id', 'services.name')
            ->distinct()
            ->orderBy('services.name')
            ->get();
    }

    /**
     * Comissão c/ IVA por técnica (users.id), alinhada ao rodapé do relatório.
     *
     * @param  array{desde?: ?string, ate?: ?string, cliente?: mixed, servico?: mixed, tecnico?: mixed, estado?: ?string}  $filters
     * @return array<int, float>
     */
    public function comissaoPorUserId(array $filters): array
    {
        $desde = $this->normalizeRelatorioDate($filters['desde'] ?? '') ?? '';
        $ate = $this->normalizeRelatorioDate($filters['ate'] ?? '') ?? '';
        if ($desde === '' || $ate === '') {
            return [];
        }

        $sales = $this->salesForReport($filters);
        $lines = $this->linesCollection($sales, null, null);

        $crmByUser = [];
        foreach ($lines as $line) {
            $userId = (int) ($line->user_id ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $crmByUser[$userId] = ($crmByUser[$userId] ?? 0.0) + (float) $line->comissao_com_iva;
        }

        if ($desde > ZappyCommissionHistoricoService::historicalEndDate()) {
            return $this->roundCommissionMap($crmByUser);
        }

        $historicalEnd = min($ate, ZappyCommissionHistoricoService::historicalEndDate());
        $zappyByUser = $this->zappyCommissionHistorico->technicianTotalsForRange($desde, $historicalEnd);

        if ($ate <= ZappyCommissionHistoricoService::historicalEndDate()) {
            return $this->roundCommissionMap(array_map(
                fn (array $amounts): float => (float) $amounts['com_iva'],
                $zappyByUser,
            ));
        }

        $crmFrom = Carbon::parse(ZappyCommissionHistoricoService::historicalEndDate())->addDay()->toDateString();
        $crmFromDate = [];
        foreach ($lines as $line) {
            $userId = (int) ($line->user_id ?? 0);
            $date = $line->data_emissao?->format('Y-m-d');
            if ($userId <= 0 || $date === null || $date < $crmFrom || $date > $ate) {
                continue;
            }
            $crmFromDate[$userId] = ($crmFromDate[$userId] ?? 0.0) + (float) $line->comissao_com_iva;
        }

        $userIds = array_unique(array_merge(array_keys($zappyByUser), array_keys($crmFromDate)));
        $blended = [];
        foreach ($userIds as $userId) {
            $blended[(int) $userId] = (float) ($zappyByUser[$userId]['com_iva'] ?? 0.0)
                + (float) ($crmFromDate[$userId] ?? 0.0);
        }

        return $this->roundCommissionMap($blended);
    }

    /**
     * @param  array<int, float>  $map
     * @return array<int, float>
     */
    private function roundCommissionMap(array $map): array
    {
        $out = [];
        foreach ($map as $userId => $amount) {
            $out[(int) $userId] = round((float) $amount, 2);
        }

        return $out;
    }

    public function clientesOpts(): Collection
    {
        return Client::query()
            ->forStore(current_store_id())
            ->join('calendar_events', 'calendar_events.client_id', '=', 'clients.id')
            ->where('calendar_events.store_id', current_store_id())
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->select('clients.id', 'clients.name')
            ->distinct()
            ->orderBy('clients.name')
            ->get();
    }
}
