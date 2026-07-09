<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\SaleTechnicianAttribution;
use App\Support\StoreBusinessTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialDashboardService
{
    public function __construct(
        private readonly ComissoesReportService $comissoesReportService,
        private readonly VendasReportService $vendasReportService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $storeId, int $year, int $month): array
    {
        $today = StoreBusinessTime::nowForStore($storeId)->startOfDay();
        $year = max($this->minYear($storeId), min($today->year, $year));
        $month = max(1, min(12, $month));
        if ($year === $today->year && $month > $today->month) {
            $month = $today->month;
        }

        $start = Carbon::create($year, $month, 1, 0, 0, 0, $today->timezoneName)->startOfMonth();
        $end = $start->copy()->endOfMonth()->endOfDay();

        $prevStart = $start->copy()->subMonth()->startOfMonth();
        $prevEnd = $prevStart->copy()->endOfMonth()->endOfDay();

        $sales = $this->salesForPeriod($storeId, $start, $end);

        $receita = $this->receitaForPeriod($start, $end);
        $receitaAnterior = $this->receitaForPeriod($prevStart, $prevEnd);
        $numFaturas = $sales->count();
        $descontos = round((float) $sales->sum('desconto'), 2);
        $taxas = round($this->taxasForSales($storeId, $sales->pluck('id')), 2);
        $ticketMedio = $numFaturas > 0 ? round($receita / $numFaturas, 2) : null;
        $variacaoReceita = $receitaAnterior > 0
            ? round((($receita - $receitaAnterior) / $receitaAnterior) * 100, 1)
            : ($receita > 0 ? 100.0 : 0.0);

        $topServicos = $this->topServicos($storeId, $start, $end);
        $topTecnicas = $this->topTecnicas($storeId, $start, $end);
        $topClientes = $this->topClientes($storeId, $start, $end);

        $comissaoFilters = [
            'desde' => $start->toDateString(),
            'ate' => $end->toDateString(),
        ];
        $comissaoPorUserId = $this->comissoesReportService->comissaoPorUserId($comissaoFilters);
        $comissoesPorTecnica = $this->comissoesPorTecnica($storeId, $start, $end, $comissaoPorUserId);

        $salesForComissoes = $this->comissoesReportService->salesForReport($comissaoFilters);
        $comissaoLines = $this->comissoesReportService->linesCollection($salesForComissoes, null, null);
        $comissaoTotais = $this->comissoesReportService->totaisRodape($comissaoLines, $comissaoFilters);
        $comissoesEstimadas = (float) $comissaoTotais['total_comissao_com_iva'];
        $margemEstimada = round($receita - $comissoesEstimadas, 2);

        $receitaDiaria = $this->receitaDiaria($sales, $start);

        $weekBounds = $this->currentWeekBounds($today);
        $prevWeekBounds = $this->previousWeekBounds($today);
        $receitaSemana = $this->receitaForPeriod($weekBounds['start'], $weekBounds['end']);
        $receitaSemanaAnterior = $this->receitaForPeriod($prevWeekBounds['start'], $prevWeekBounds['end']);
        $variacaoReceitaSemana = $receitaSemanaAnterior > 0
            ? round((($receitaSemana - $receitaSemanaAnterior) / $receitaSemanaAnterior) * 100, 1)
            : ($receitaSemana > 0 ? 100.0 : 0.0);

        $clientesUnicos = $this->countUniqueClients($sales);
        $diasComVendas = $this->countDaysWithSales($sales, $start);
        $receitaMediaDia = $diasComVendas > 0 ? round($receita / $diasComVendas, 2) : null;

        $usesHistoricalComissoes = $this->comissoesReportService->footerUsesHistoricalOverride($comissaoFilters);

        $monthOptions = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];

        return [
            'year' => $year,
            'month' => $month,
            'availableYears' => range($this->minYear($storeId), $today->year),
            'monthOptions' => $monthOptions,
            'periodLabel' => $start->copy()->locale('pt')->translatedFormat('F Y'),
            'kpis' => [
                'receita' => $receita,
                'receita_anterior' => $receitaAnterior,
                'variacao_receita' => $variacaoReceita,
                'receita_semana' => $receitaSemana,
                'receita_semana_anterior' => $receitaSemanaAnterior,
                'variacao_receita_semana' => $variacaoReceitaSemana,
                'num_faturas' => $numFaturas,
                'ticket_medio' => $ticketMedio,
                'clientes_unicos' => $clientesUnicos,
                'dias_com_vendas' => $diasComVendas,
                'receita_media_dia' => $receitaMediaDia,
                'taxas' => $taxas,
                'descontos' => $descontos,
                'comissoes_estimadas' => $comissoesEstimadas,
                'margem_estimada' => $margemEstimada,
            ],
            'top_servicos' => $topServicos,
            'top_tecnicas' => $topTecnicas,
            'top_clientes' => $topClientes,
            'comissoes_por_tecnica' => $comissoesPorTecnica,
            'receita_diaria' => $receitaDiaria,
            'destaques' => [
                'servico' => $topServicos->first(),
                'tecnica' => $topTecnicas->first(),
                'cliente' => $topClientes->first(),
            ],
            'uses_historical_comissoes' => $usesHistoricalComissoes,
        ];
    }

    private function receitaForPeriod(Carbon $start, Carbon $end): float
    {
        return $this->vendasReportService->sumVendasPagasPorMarcacao($start, $end);
    }

    /**
     * Vendas pagas no período — critério data da marcação (alinhado ao Resumo e relatório de vendas).
     *
     * @return Collection<int, Sale>
     */
    private function salesForPeriod(int $storeId, Carbon $start, Carbon $end): Collection
    {
        return $this->vendasReportService->reportQuery([
            'desde' => $start->toDateString(),
            'ate' => $end->toDateString(),
            'data_criterio' => VendasReportService::DATE_CRITERION_MARCACAO,
        ])
            ->where('store_id', $storeId)
            ->with(['items.calendarEventService.event', 'items.service', 'calendarEvent', 'client', 'settledEvents'])
            ->get();
    }

    private function minYear(int $storeId): int
    {
        $firstSale = Sale::query()
            ->where('store_id', $storeId)
            ->where('status', Sale::STATUS_PAGO)
            ->min('data_emissao');

        if ($firstSale === null) {
            return (int) date('Y');
        }

        return (int) Carbon::parse($firstSale)->year;
    }

    private function taxasForSales(int $storeId, Collection $saleIds): float
    {
        if ($saleIds->isEmpty()) {
            return 0.0;
        }

        return (float) DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.store_id', $storeId)
            ->whereIn('sales.id', $saleIds)
            ->where('sale_items.tipo', SaleItem::TIPO_TAXA)
            ->sum('sale_items.subtotal');
    }

    /**
     * @return Collection<int, object{service_id: int, nome: string, receita: float, qtd: int}>
     */
    private function topServicos(int $storeId, Carbon $start, Carbon $end): Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('services', 'sale_items.service_id', '=', 'services.id')
            ->join('calendar_events', 'sales.calendar_event_id', '=', 'calendar_events.id')
            ->where('sales.store_id', $storeId)
            ->where('services.store_id', $storeId)
            ->where('calendar_events.store_id', $storeId)
            ->where('sales.status', Sale::STATUS_PAGO)
            ->where('sale_items.tipo', SaleItem::TIPO_SERVICO)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', CalendarEvent::STATUS_COMPLETO)
            ->whereDate('calendar_events.start_at', '>=', $start->toDateString())
            ->whereDate('calendar_events.start_at', '<=', $end->toDateString())
            ->groupBy('services.id', 'services.name')
            ->selectRaw('services.id as service_id, services.name as nome, sum(sale_items.subtotal) as receita, count(*) as qtd')
            ->orderByDesc('receita')
            ->limit(5)
            ->get()
            ->map(fn ($row) => (object) [
                'service_id' => (int) $row->service_id,
                'nome' => (string) $row->nome,
                'receita' => round((float) $row->receita, 2),
                'qtd' => (int) $row->qtd,
            ]);
    }

    /**
     * @return Collection<int, object{user_id: int, nome: string, receita: float, num_faturas: int}>
     */
    private function topTecnicas(int $storeId, Carbon $start, Carbon $end): Collection
    {
        $attributed = $this->receitaAtribuidaPorTecnica($storeId, $start, $end);

        return $attributed
            ->map(fn (object $row) => (object) [
                'user_id' => $row->user_id,
                'nome' => $row->nome,
                'receita' => $row->receita,
                'num_faturas' => $row->num_faturas,
            ])
            ->sortByDesc('receita')
            ->take(5)
            ->values();
    }

    /**
     * @return Collection<int, object{client_id: int, nome: string, receita: float, num_faturas: int}>
     */
    private function topClientes(int $storeId, Carbon $start, Carbon $end): Collection
    {
        return DB::table('sales')
            ->join('clients', 'sales.client_id', '=', 'clients.id')
            ->join('calendar_events', 'sales.calendar_event_id', '=', 'calendar_events.id')
            ->where('sales.store_id', $storeId)
            ->where('clients.store_id', $storeId)
            ->where('sales.status', Sale::STATUS_PAGO)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', CalendarEvent::STATUS_COMPLETO)
            ->whereDate('calendar_events.start_at', '>=', $start->toDateString())
            ->whereDate('calendar_events.start_at', '<=', $end->toDateString())
            ->groupBy('clients.id', 'clients.name')
            ->selectRaw('clients.id as client_id, clients.name as nome, sum(sales.total) as receita, count(*) as num_faturas')
            ->orderByDesc('receita')
            ->limit(5)
            ->get()
            ->map(fn ($row) => (object) [
                'client_id' => (int) $row->client_id,
                'nome' => (string) $row->nome,
                'receita' => round((float) $row->receita, 2),
                'num_faturas' => (int) $row->num_faturas,
            ]);
    }

    /**
     * Comissões por técnica alinhadas ao relatório (Zappy no histórico, CRM a partir de jun/2026).
     *
     * @param  array<int, float>  $comissaoPorUserId
     * @return Collection<int, object{user_id: int, nome: string, receita: float, comissao: float, taxa: ?string, num_faturas: int}>
     */
    private function comissoesPorTecnica(int $storeId, Carbon $start, Carbon $end, array $comissaoPorUserId): Collection
    {
        $attributed = $this->receitaAtribuidaPorTecnica($storeId, $start, $end)->keyBy('user_id');
        $agentRates = Agent::query()
            ->where('store_id', $storeId)
            ->whereIn('user_id', array_unique(array_merge(
                $attributed->keys()->all(),
                array_keys($comissaoPorUserId),
            )))
            ->get(['user_id', 'commission_rate', 'commission_unit'])
            ->keyBy('user_id');

        $userIds = array_unique(array_merge($attributed->keys()->all(), array_keys($comissaoPorUserId)));
        $userNames = User::query()
            ->whereIn('id', $userIds)
            ->pluck('name', 'id');

        return collect($userIds)
            ->map(function ($userId) use ($attributed, $comissaoPorUserId, $agentRates, $userNames): ?object {
                $userId = (int) $userId;
                $row = $attributed->get($userId);
                $comissao = round((float) ($comissaoPorUserId[$userId] ?? 0.0), 2);
                if ($comissao <= 0 && $row === null) {
                    return null;
                }

                $agent = $agentRates->get($userId);
                $rate = $agent?->commission_rate;
                $unit = $agent?->commission_unit;
                $taxa = null;
                if ($rate !== null) {
                    $taxa = (new Agent([
                        'commission_rate' => $rate,
                        'commission_unit' => $unit,
                    ]))->formatCommissionDisplay();
                }

                return (object) [
                    'user_id' => $userId,
                    'nome' => (string) ($row->nome ?? $userNames[$userId] ?? '—'),
                    'receita' => round((float) ($row->receita ?? 0.0), 2),
                    'comissao' => $comissao,
                    'taxa' => $taxa,
                    'num_faturas' => (int) ($row->num_faturas ?? 0),
                ];
            })
            ->filter()
            ->sortByDesc('comissao')
            ->values();
    }

    /**
     * Receita por técnica com repartição de vendas consolidadas (itens → marcação → user_id).
     *
     * @return Collection<int, object{user_id: int, nome: string, receita: float, num_faturas: int}>
     */
    private function receitaAtribuidaPorTecnica(int $storeId, Carbon $start, Carbon $end): Collection
    {
        $sales = $this->salesForPeriod($storeId, $start, $end);
        $byUser = [];

        foreach ($sales as $sale) {
            foreach (SaleTechnicianAttribution::slicesForSale($sale) as $slice) {
                $userId = (int) ($slice['user_id'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }

                $byUser[$userId] ??= [
                    'user_id' => $userId,
                    'nome' => (string) $slice['user_name'],
                    'receita' => 0.0,
                    'sale_ids' => [],
                ];
                $byUser[$userId]['receita'] += (float) $slice['valor'] + (float) $slice['taxas'];
                $byUser[$userId]['sale_ids'][(int) $sale->id] = true;
            }
        }

        return collect($byUser)
            ->map(fn (array $row) => (object) [
                'user_id' => $row['user_id'],
                'nome' => $row['nome'],
                'receita' => round($row['receita'], 2),
                'num_faturas' => count($row['sale_ids']),
            ])
            ->sortByDesc('receita')
            ->values();
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return array<int, array{day: int, label: string, receita: float}>
     */
    private function receitaDiaria(Collection $sales, Carbon $start): array
    {
        $tz = $start->timezoneName;
        $byDay = [];

        foreach ($sales as $sale) {
            $startAt = $sale->calendarEvent?->start_at;
            if ($startAt === null) {
                continue;
            }

            $day = (int) $startAt->timezone($tz)->day;
            $byDay[$day] = ($byDay[$day] ?? 0.0) + (float) $sale->total;
        }

        $daysInMonth = (int) $start->daysInMonth;
        $result = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $result[] = [
                'day' => $day,
                'label' => (string) $day,
                'receita' => round((float) ($byDay[$day] ?? 0), 2),
            ];
        }

        return $result;
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function currentWeekBounds(Carbon $today): array
    {
        return [
            'start' => $today->copy()->startOfWeek()->startOfDay(),
            'end' => $today->copy()->endOfWeek()->endOfDay(),
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function previousWeekBounds(Carbon $today): array
    {
        $start = $today->copy()->startOfWeek()->subWeek()->startOfDay();

        return [
            'start' => $start,
            'end' => $start->copy()->endOfWeek()->endOfDay(),
        ];
    }

    /**
     * @param  Collection<int, Sale>  $sales
     */
    private function countUniqueClients(Collection $sales): int
    {
        return $sales
            ->pluck('client_id')
            ->filter(fn ($id) => $id !== null && (int) $id > 0)
            ->unique()
            ->count();
    }

    /**
     * @param  Collection<int, Sale>  $sales
     */
    private function countDaysWithSales(Collection $sales, Carbon $start): int
    {
        $tz = $start->timezoneName;

        return $sales
            ->map(fn (Sale $sale) => $sale->calendarEvent?->start_at?->timezone($tz)->toDateString())
            ->filter()
            ->unique()
            ->count();
    }
}
