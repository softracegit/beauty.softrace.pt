<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\SaleTechnicianAttribution;
use App\Support\StoreBusinessTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialDashboardService
{
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

        $sales = $this->paidSalesBetween($storeId, $start, $end);
        $prevSales = $this->paidSalesBetween($storeId, $prevStart, $prevEnd);

        $receita = round((float) $sales->sum('total'), 2);
        $receitaAnterior = round((float) $prevSales->sum('total'), 2);
        $numFaturas = $sales->count();
        $gorjetas = round((float) $sales->sum('gorjeta'), 2);
        $descontos = round((float) $sales->sum('desconto'), 2);
        $taxas = round($this->taxasForSales($storeId, $sales->pluck('id')), 2);
        $ticketMedio = $numFaturas > 0 ? round($receita / $numFaturas, 2) : null;
        $variacaoReceita = $receitaAnterior > 0
            ? round((($receita - $receitaAnterior) / $receitaAnterior) * 100, 1)
            : ($receita > 0 ? 100.0 : 0.0);
        $taxaGorjeta = $receita > 0 ? round(($gorjetas / $receita) * 100, 1) : 0.0;

        $topServicos = $this->topServicos($storeId, $start, $end);
        $topTecnicas = $this->topTecnicas($storeId, $start, $end);
        $topClientes = $this->topClientes($storeId, $start, $end);
        $comissoesPorTecnica = $this->comissoesEstimadasPorTecnica($storeId, $start, $end);
        $comissoesEstimadas = round((float) $comissoesPorTecnica->sum('comissao'), 2);
        $margemEstimada = round($receita - $comissoesEstimadas, 2);

        $receitaDiaria = $this->receitaDiaria($storeId, $start, $end);

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
                'num_faturas' => $numFaturas,
                'ticket_medio' => $ticketMedio,
                'gorjetas' => $gorjetas,
                'taxa_gorjeta' => $taxaGorjeta,
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
        ];
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

    private function paidSalesBetween(int $storeId, Carbon $start, Carbon $end): Collection
    {
        return Sale::query()
            ->where('store_id', $storeId)
            ->where('status', Sale::STATUS_PAGO)
            ->whereDate('data_emissao', '>=', $start->toDateString())
            ->whereDate('data_emissao', '<=', $end->toDateString())
            ->whereHas('calendarEvent', function ($q) use ($storeId) {
                $q->where('store_id', $storeId)
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);
            })
            ->with(['items.calendarEventService.event', 'calendarEvent'])
            ->get();
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
            ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereDate('sales.data_emissao', '>=', $start->toDateString())
            ->whereDate('sales.data_emissao', '<=', $end->toDateString())
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
            ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereDate('sales.data_emissao', '>=', $start->toDateString())
            ->whereDate('sales.data_emissao', '<=', $end->toDateString())
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
     * Estimativa com base na comissão configurada na ficha do membro (vendas pagas do período).
     *
     * @return Collection<int, object{user_id: int, nome: string, receita: float, comissao: float, taxa: ?string, num_faturas: int}>
     */
    private function comissoesEstimadasPorTecnica(int $storeId, Carbon $start, Carbon $end): Collection
    {
        $attributed = $this->receitaAtribuidaPorTecnica($storeId, $start, $end);
        $agentRates = Agent::query()
            ->where('store_id', $storeId)
            ->whereIn('user_id', $attributed->pluck('user_id')->all())
            ->get(['user_id', 'commission_rate', 'commission_unit'])
            ->keyBy('user_id');

        return $attributed->map(function (object $row) use ($agentRates) {
            $agent = $agentRates->get($row->user_id);
            $rate = $agent?->commission_rate;
            $unit = $agent?->commission_unit;
            $receita = round((float) $row->receita, 2);
            $comissao = $this->estimateCommission($receita, (int) $row->num_faturas, $rate, $unit);

            $taxa = null;
            if ($rate !== null) {
                $taxa = (new Agent([
                    'commission_rate' => $rate,
                    'commission_unit' => $unit,
                ]))->formatCommissionDisplay();
            }

            return (object) [
                'user_id' => (int) $row->user_id,
                'nome' => (string) $row->nome,
                'receita' => $receita,
                'comissao' => $comissao,
                'taxa' => $taxa,
                'num_faturas' => (int) $row->num_faturas,
            ];
        })->sortByDesc('comissao')->values();
    }

    /**
     * Receita por técnica com repartição de vendas consolidadas (itens → marcação → user_id).
     *
     * @return Collection<int, object{user_id: int, nome: string, receita: float, num_faturas: int}>
     */
    private function receitaAtribuidaPorTecnica(int $storeId, Carbon $start, Carbon $end): Collection
    {
        $sales = $this->paidSalesBetween($storeId, $start, $end);
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

    private function estimateCommission(float $receita, int $numFaturas, mixed $rate, mixed $unit): float
    {
        if ($rate === null || $rate === '') {
            return 0.0;
        }

        $rate = (float) $rate;
        $unit = (string) ($unit ?: Agent::COMMISSION_UNIT_PERCENT);

        if ($unit === Agent::COMMISSION_UNIT_EURO) {
            return round($rate * $numFaturas, 2);
        }

        return round($receita * ($rate / 100), 2);
    }

    /**
     * @return array<int, array{day: int, label: string, receita: float}>
     */
    private function receitaDiaria(int $storeId, Carbon $start, Carbon $end): array
    {
        $byDay = DB::table('sales')
            ->join('calendar_events', 'sales.calendar_event_id', '=', 'calendar_events.id')
            ->where('sales.store_id', $storeId)
            ->where('sales.status', Sale::STATUS_PAGO)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereDate('sales.data_emissao', '>=', $start->toDateString())
            ->whereDate('sales.data_emissao', '<=', $end->toDateString())
            ->selectRaw('DAY(sales.data_emissao) as dia, sum(sales.total) as receita')
            ->groupBy('dia')
            ->pluck('receita', 'dia');

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
}
