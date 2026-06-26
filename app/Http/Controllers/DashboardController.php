<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\CalendarEventServiceExtra;
use App\Models\Client;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\FinancialDashboardService;
use App\Services\PrestadorDashboardService;
use App\Services\VendasReportService;
use App\Support\StoreBusinessTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private readonly FinancialDashboardService $financialDashboard,
        private readonly VendasReportService $vendasReportService,
    ) {}

    /**
     * Dashboard Resumo (página inicial do dashboard).
     */
    public function resumo(PrestadorDashboardService $prestadorDashboard)
    {
        $user = auth()->user();
        $storeId = current_store_id();

        if ($user instanceof User && $user->isPrestador()) {
            return view('dashboard.prestador', $prestadorDashboard->build($user, $storeId));
        }

        if ($user instanceof User && $user->isRececao()) {
            return view('dashboard.prestador', $prestadorDashboard->buildForStore($storeId, $user));
        }

        $today = StoreBusinessTime::nowForStore($storeId)->startOfDay();
        $currentYear = $today->year;
        $previousYear = $currentYear - 1;

        $store = current_store()->get();
        $slotsByWeekdayKey = $this->ocupacaoSlotsByWeekdayKey($store);
        $prestadorUserIds = $this->ocupacaoPrestadorUserIds();

        $kpiPorPeriodo = [];
        foreach (['hoje', 'ontem', 'semana', 'mes'] as $period) {
            [$start, $end] = $this->resumoPeriodBounds($period, $today);
            $kpiPorPeriodo[$period] = [
                'vendas' => round($this->resumoVendasEntre($start, $end), 2),
                'clientes_atendidos' => $this->resumoClientesAtendidosEntre($start, $end),
                'clientes_novos' => $this->resumoClientesNovosEntre($start, $end),
                'taxa_ocupacao' => $this->resumoTaxaOcupacaoEntre($start, $end, $slotsByWeekdayKey, $prestadorUserIds),
            ];
        }

        $monthLabels = [];
        $vendasAnoAtual = [];
        $vendasAnoAnterior = [];
        $atendidosAnoAtual = [];
        $atendidosAnoAnterior = [];
        $novosAnoAtual = [];
        $novosAnoAnterior = [];

        for ($month = 1; $month <= 12; $month++) {
            $monthLabels[] = Carbon::create($currentYear, $month, 1)
                ->locale('pt_PT')
                ->translatedFormat('M');

            [$startAtual, $endAtual] = $this->resumoMonthBounds($currentYear, $month, $today);
            [$startAnterior, $endAnterior] = $this->resumoMonthBounds($previousYear, $month, $today);

            $vendasAnoAtual[] = round($this->resumoVendasEntre($startAtual, $endAtual), 2);
            $vendasAnoAnterior[] = round($this->resumoVendasEntre($startAnterior, $endAnterior), 2);
            $atendidosAnoAtual[] = $this->resumoClientesAtendidosEntre($startAtual, $endAtual);
            $atendidosAnoAnterior[] = $this->resumoClientesAtendidosEntre($startAnterior, $endAnterior);
            $novosAnoAtual[] = $this->resumoClientesNovosEntre($startAtual, $endAtual);
            $novosAnoAnterior[] = $this->resumoClientesNovosEntre($startAnterior, $endAnterior);
        }

        $clientesContacto = $this->resumoClientesContactoStats();

        return view('dashboard.resumo', compact(
            'kpiPorPeriodo',
            'monthLabels',
            'vendasAnoAtual',
            'vendasAnoAnterior',
            'atendidosAnoAtual',
            'atendidosAnoAnterior',
            'novosAnoAtual',
            'novosAnoAnterior',
            'currentYear',
            'previousYear',
            'clientesContacto',
        ));
    }

    /**
     * Dashboard Financeiro (receitas, rankings e pré-visualização de comissões/despesas).
     */
    public function financeiro(Request $request)
    {
        if ($redirect = $this->redirectPrestadorFromAdminDashboard()) {
            return $redirect;
        }

        $storeId = current_store_id();
        $today = StoreBusinessTime::nowForStore($storeId)->startOfDay();
        $year = (int) $request->input('year', $today->year);
        $month = (int) $request->input('month', $today->month);

        $data = $this->financialDashboard->build($storeId, $year, $month);

        return view('dashboard.financeiro', $data);
    }

    /**
     * Dashboard Marcações e Serviços (estilo Fresha)
     */
    public function marcacoes()
    {
        if ($redirect = $this->redirectPrestadorFromAdminDashboard()) {
            return $redirect;
        }

        $today = Carbon::today();
        $startOfWeek = $today->copy()->startOfWeek();
        $endOfWeek = $today->copy()->endOfWeek();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();

        $marcacoesBase = CalendarEvent::forStore(current_store_id())->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);

        $marcacoesHoje = (clone $marcacoesBase)->whereDate('start_at', $today)->count();
        $marcacoesEstaSemana = (clone $marcacoesBase)
            ->whereBetween('start_at', [$startOfWeek, $endOfWeek])->count();
        $marcacoesEsteMes = (clone $marcacoesBase)
            ->whereBetween('start_at', [$startOfMonth, $endOfMonth])->count();
        $marcacoesMesAnterior = (clone $marcacoesBase)
            ->whereBetween('start_at', [$startOfMonth->copy()->subMonth(), $endOfMonth->copy()->subMonth()])
            ->count();

        $variacaoMarcacoes = $marcacoesMesAnterior > 0
            ? round((($marcacoesEsteMes - $marcacoesMesAnterior) / $marcacoesMesAnterior) * 100, 1)
            : ($marcacoesEsteMes > 0 ? 100 : 0);

        $receitaHoje = $this->receitaMarcacoesEntre($today, $today->copy()->endOfDay());
        $receitaEstaSemana = $this->receitaMarcacoesEntre($startOfWeek, $endOfWeek);
        $receitaEsteMes = $this->receitaMarcacoesEntre($startOfMonth, $endOfMonth);
        $receitaMesAnterior = $this->receitaMarcacoesEntre(
            $startOfMonth->copy()->subMonth(),
            $endOfMonth->copy()->subMonth()
        );
        $variacaoReceita = $receitaMesAnterior > 0
            ? round((($receitaEsteMes - $receitaMesAnterior) / $receitaMesAnterior) * 100, 1)
            : ($receitaEsteMes > 0 ? 100 : 0);

        $totalClientes = Client::forStore(current_store_id())->count();
        $totalTecnicos = Agent::forStore(current_store_id())->where('status', Agent::STATUS_ACTIVE)->count();

        $proximasMarcacoes = CalendarEvent::forStore(current_store_id())->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->where('start_at', '>=', $today)
            ->with(['client', 'user', 'eventServices'])
            ->orderBy('start_at')
            ->limit(8)
            ->get();

        $marcacoesRecentes = CalendarEvent::forStore(current_store_id())->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->with(['client', 'user', 'eventServices'])
            ->orderBy('start_at', 'desc')
            ->limit(5)
            ->get();

        // Marcações por serviço (independente de venda/faturação)
        $porServico = CalendarEventService::query()
            ->join('calendar_events', 'calendar_event_services.calendar_event_id', '=', 'calendar_events.id')
            ->join('services', 'calendar_event_services.service_id', '=', 'services.id')
            ->where('calendar_events.store_id', current_store_id())
            ->where('services.store_id', current_store_id())
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->groupBy('services.id', 'services.name')
            ->selectRaw('services.name as service_name, count(*) as total, sum(coalesce(calendar_event_services.price, services.price)) as receita')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $porTecnico = CalendarEvent::forStore(current_store_id())->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->load('user');

        // Receita por técnico: apenas vendas pagas de marcações concluídas
        $receitaPorTecnico = Sale::query()
            ->where('sales.store_id', current_store_id())
            ->join('calendar_events', 'sales.calendar_event_id', '=', 'calendar_events.id')
            ->where('sales.status', Sale::STATUS_PAGO)
            ->where('calendar_events.store_id', current_store_id())
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', CalendarEvent::STATUS_COMPLETO)
            ->groupBy('calendar_events.user_id')
            ->selectRaw('calendar_events.user_id, sum(sales.total) as receita')
            ->get()
            ->keyBy('user_id');

        $porEstado = CalendarEvent::forStore(current_store_id())->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $mensalMarcacoes = [];
        $mensalReceita = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();
            $mensalMarcacoes[] = [
                'month' => $date->locale('pt_PT')->translatedFormat('M'),
                'count' => (clone $marcacoesBase)->whereBetween('start_at', [$start, $end])->count(),
            ];
            $mensalReceita[] = [
                'month' => $date->locale('pt_PT')->translatedFormat('M'),
                'revenue' => round($this->receitaMarcacoesEntre($start, $end), 2),
            ];
        }

        return view('dashboard.index', compact(
            'marcacoesHoje',
            'marcacoesEstaSemana',
            'marcacoesEsteMes',
            'marcacoesMesAnterior',
            'variacaoMarcacoes',
            'receitaHoje',
            'receitaEstaSemana',
            'receitaEsteMes',
            'receitaMesAnterior',
            'variacaoReceita',
            'totalClientes',
            'totalTecnicos',
            'proximasMarcacoes',
            'marcacoesRecentes',
            'porServico',
            'porTecnico',
            'receitaPorTecnico',
            'porEstado',
            'mensalMarcacoes',
            'mensalReceita'
        ));
    }

    /**
     * @return array{0: Carbon, 1: Carbon} Limites inclusivos no fuso da loja.
     */
    private function resumoPeriodBounds(string $period, Carbon $today): array
    {
        return match ($period) {
            'ontem' => [
                $today->copy()->subDay()->startOfDay(),
                $today->copy()->subDay()->endOfDay(),
            ],
            'semana' => [
                $today->copy()->startOfWeek(),
                $today->copy()->endOfWeek()->endOfDay(),
            ],
            'mes' => [
                $today->copy()->startOfMonth(),
                $today->copy()->endOfMonth()->endOfDay(),
            ],
            default => [
                $today->copy()->startOfDay(),
                $today->copy()->endOfDay(),
            ],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resumoMonthBounds(int $year, int $month, Carbon $today): array
    {
        $tz = $today->timezoneName;
        $start = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->startOfMonth();

        return [$start, $start->copy()->endOfMonth()->endOfDay()];
    }

    private function resumoVendasEntre(Carbon $start, Carbon $end): float
    {
        return $this->vendasReportService->sumVendasPagasPorEmissao($start, $end);
    }

    private function resumoClientesAtendidosEntre(Carbon $start, Carbon $end): int
    {
        if ($end->lt($start)) {
            return 0;
        }

        [$startUtc, $endUtc] = $this->ocupacaoUtcQueryBounds($start, $end);

        return (int) CalendarEvent::forStore(current_store_id())
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('client_id')
            ->whereBetween('start_at', [$startUtc, $endUtc])
            ->count();
    }

    /**
     * Clientes cuja 1.ª marcação (não cancelada) cai no período — alinhado com atendimentos reais.
     */
    private function resumoClientesNovosEntre(Carbon $start, Carbon $end): int
    {
        if ($end->lt($start)) {
            return 0;
        }

        [$startUtc, $endUtc] = $this->ocupacaoUtcQueryBounds($start, $end);

        $primeirasMarcacoes = CalendarEvent::forStore(current_store_id())
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('client_id')
            ->selectRaw('client_id, MIN(start_at) as primeira_marcacao')
            ->groupBy('client_id');

        return (int) DB::query()
            ->fromSub($primeirasMarcacoes, 'primeiras_marcacoes')
            ->whereBetween('primeira_marcacao', [$startUtc, $endUtc])
            ->count();
    }

    /**
     * @param  array<string, int>  $slotsByWeekdayKey
     */
    private function resumoTaxaOcupacaoEntre(
        Carbon $start,
        Carbon $end,
        array $slotsByWeekdayKey,
        Collection $prestadorUserIds,
    ): float {
        $numTecnicos = $prestadorUserIds->count();
        if ($numTecnicos <= 0) {
            return 0;
        }

        $totalSlots = $this->ocupacaoTotalSlotsBetween($start, $end, $slotsByWeekdayKey, $numTecnicos);
        if ($totalSlots <= 0) {
            return 0;
        }

        $filledSlots = $this->ocupacaoFilledSlotsBetween($start, $end, $prestadorUserIds);

        return round(min(100, ($filledSlots / $totalSlots) * 100), 1);
    }

    /**
     * @return array{total: int, com_telemovel: int, com_email: int, com_aniversario: int}
     */
    private function resumoClientesContactoStats(): array
    {
        $base = Client::forStore(current_store_id());
        $total = (clone $base)->count();

        return [
            'total' => $total,
            'com_telemovel' => (clone $base)->whereNotNull('phone')->where('phone', '!=', '')->count(),
            'com_email' => (clone $base)->whereNotNull('email')->where('email', '!=', '')->count(),
            'com_aniversario' => (clone $base)->whereNotNull('birth_date')->count(),
        ];
    }

    /**
     * Receita total entre duas datas baseada em vendas pagas de marcações concluídas.
     */
    private function receitaMarcacoesEntre(Carbon $start, Carbon $end): float
    {
        return $this->vendasReportService->sumVendasPagasPorMarcacao($start, $end);
    }

    /**
     * Dashboard de Imóveis
     */
    public function imoveis()
    {
        if ($redirect = $this->redirectPrestadorFromAdminDashboard()) {
            return $redirect;
        }

        return view('dashboard.imoveis');
    }

    /**
     * Dashboard de Negócios
     */
    public function negocios()
    {
        if ($redirect = $this->redirectPrestadorFromAdminDashboard()) {
            return $redirect;
        }

        return view('dashboard.negocios');
    }

    /**
     * Dashboard de Clientes (métricas baseadas em marcações de serviços)
     */
    public function clientes()
    {
        if ($redirect = $this->redirectPrestadorFromAdminDashboard()) {
            return $redirect;
        }

        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();

        $marcacoesBase = CalendarEvent::forStore(current_store_id())->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('client_id');

        $totalClientes = Client::forStore(current_store_id())->count();
        $totalClientesComMarcacao = (clone $marcacoesBase)->distinct('client_id')->count('client_id');

        $clientesEsteMes = Client::forStore(current_store_id())->whereMonth('created_at', $today->month)
            ->whereYear('created_at', $today->year)
            ->count();

        $marcacoesEsteMes = (clone $marcacoesBase)
            ->whereBetween('start_at', [$startOfMonth, $endOfMonth])
            ->get();

        $primeiraMarcacaoPorCliente = CalendarEvent::forStore(current_store_id())->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('client_id')
            ->selectRaw('client_id, min(start_at) as primeira')
            ->groupBy('client_id')
            ->pluck('primeira', 'client_id');

        $marcacoesNovosClientes = 0;
        $marcacoesRecorrentes = 0;
        foreach ($marcacoesEsteMes as $ev) {
            $primeira = $primeiraMarcacaoPorCliente[$ev->client_id] ?? null;
            if ($primeira === null) {
                $marcacoesRecorrentes++;

                continue;
            }
            $primeiraDt = $primeira instanceof \Carbon\Carbon ? $primeira : Carbon::parse($primeira);
            if ($primeiraDt->between($startOfMonth, $endOfMonth)) {
                $marcacoesNovosClientes++;
            } else {
                $marcacoesRecorrentes++;
            }
        }

        $clientesComUmaOuMais = (clone $marcacoesBase)->distinct('client_id')->pluck('client_id');
        $clientesComDuasOuMais = CalendarEvent::forStore(current_store_id())->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('client_id')
            ->selectRaw('client_id, count(*) as total')
            ->groupBy('client_id')
            ->havingRaw('count(*) >= 2')
            ->pluck('client_id');
        $taxaRetencao = $clientesComUmaOuMais->count() > 0
            ? round(($clientesComDuasOuMais->count() / $clientesComUmaOuMais->count()) * 100, 1)
            : 0;

        $receitaPorCliente = $this->receitaPorCliente();
        $topClientesPorMarcacoes = Client::query()
            ->forStore(current_store_id())
            ->whereIn('id', $clientesComUmaOuMais)
            ->withCount(['calendarEvents as marcacoes_count' => function ($q) {
                $q->where('store_id', current_store_id())
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);
            }])
            ->orderByDesc('marcacoes_count')
            ->limit(10)
            ->get()
            ->map(function ($c) use ($receitaPorCliente) {
                $c->receita = $receitaPorCliente->get($c->id, 0);

                return $c;
            });

        $topClientesPorReceita = Client::query()
            ->forStore(current_store_id())
            ->whereIn('id', $receitaPorCliente->keys())
            ->withCount(['calendarEvents as marcacoes_count' => function ($q) {
                $q->where('store_id', current_store_id())
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);
            }])
            ->get()
            ->sortByDesc(fn ($c) => $receitaPorCliente->get($c->id, 0))
            ->take(10)
            ->values()
            ->map(function ($c) use ($receitaPorCliente) {
                $c->receita = $receitaPorCliente->get($c->id, 0);

                return $c;
            });

        $intervaloMedioDias = $this->intervaloMedioEntreVisitas();

        $monthlyGrowth = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthlyGrowth[] = [
                'month' => $date->locale('pt_PT')->translatedFormat('M'),
                'count' => Client::forStore(current_store_id())->whereMonth('created_at', $date->month)->whereYear('created_at', $date->year)->count(),
            ];
        }

        $recentClients = Client::forStore(current_store_id())->orderBy('created_at', 'desc')->limit(10)->get();

        return view('dashboard.clientes', compact(
            'totalClientes',
            'totalClientesComMarcacao',
            'clientesEsteMes',
            'marcacoesNovosClientes',
            'marcacoesRecorrentes',
            'taxaRetencao',
            'topClientesPorMarcacoes',
            'topClientesPorReceita',
            'intervaloMedioDias',
            'monthlyGrowth',
            'recentClients'
        ));
    }

    /**
     * Receita total por client_id baseada em vendas concluídas (pagas) de marcações completas.
     */
    private function receitaPorCliente()
    {
        return Sale::query()
            ->where('store_id', current_store_id())
            ->where('status', Sale::STATUS_PAGO)
            ->whereNotNull('client_id')
            ->whereHas('calendarEvent', function ($q) {
                $q->where('store_id', current_store_id())
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', CalendarEvent::STATUS_COMPLETO);
            })
            ->groupBy('client_id')
            ->selectRaw('client_id, sum(total) as total')
            ->pluck('total', 'client_id')
            ->mapWithKeys(function ($total, $clientId) {
                return [(int) $clientId => (float) $total];
            });
    }

    /**
     * Intervalo médio em dias entre visitas consecutivas (clientes com 2+ marcações).
     */
    private function intervaloMedioEntreVisitas(): ?float
    {
        $clientIds = CalendarEvent::forStore(current_store_id())->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('client_id')
            ->selectRaw('client_id, count(*) as c')
            ->groupBy('client_id')
            ->havingRaw('count(*) >= 2')
            ->pluck('client_id');

        if ($clientIds->isEmpty()) {
            return null;
        }

        $somas = 0;
        $n = 0;
        foreach ($clientIds as $clientId) {
            $datas = CalendarEvent::forStore(current_store_id())->where('client_id', $clientId)
                ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
                ->orderBy('start_at')
                ->pluck('start_at')
                ->map(fn ($d) => $d->startOfDay()->timestamp);
            $datas = $datas->values()->all();
            for ($i = 1; $i < count($datas); $i++) {
                $somas += ($datas[$i] - $datas[$i - 1]) / 86400;
                $n++;
            }
        }

        return $n > 0 ? round($somas / $n, 1) : null;
    }

    /**
     * Regras para cálculo de slots (ocupação): duração do slot em minutos.
     */
    private const SLOT_DURATION_MINUTES = 90;

    /**
     * Dashboard de Ocupação (taxa de ocupação, picos, dias, duração média).
     */
    public function ocupacao(Request $request)
    {
        if ($redirect = $this->redirectPrestadorFromAdminDashboard()) {
            return $redirect;
        }
        $store = current_store()->get();
        $tz = $store->bookingTimezone();
        $today = StoreBusinessTime::nowForStore(current_store_id())->startOfDay();
        $year = (int) $request->input('year', $today->year);
        $month = (int) $request->input('month', $today->month);
        $year = max($this->ocupacaoMinYear($store), min($today->year, $year));
        $month = max(1, min(12, $month));
        if ($year === $today->year && $month > $today->month) {
            $month = $today->month;
        }

        [$startOfMonth, $endOfMonth] = $this->ocupacaoStoreMonthBounds($store, $year, $month);
        [$startOfMonthUtc, $endOfMonthUtc] = $this->ocupacaoUtcQueryBounds($startOfMonth, $endOfMonth);
        [$startOfWeek, $endOfWeek] = $this->ocupacaoWeekRangeInMonth($startOfMonth, $endOfMonth, $today);

        $slotsByWeekdayKey = $this->ocupacaoSlotsByWeekdayKey($store);
        $prestadorUserIds = $this->ocupacaoPrestadorUserIds();
        $numTecnicos = $prestadorUserIds->count();

        $totalSlotsMonth = $this->ocupacaoTotalSlotsBetween($startOfMonth, $endOfMonth, $slotsByWeekdayKey, $numTecnicos);
        $totalSlotsWeek = $this->ocupacaoTotalSlotsBetween($startOfWeek, $endOfWeek, $slotsByWeekdayKey, $numTecnicos);
        $filledSlotsMonth = $this->ocupacaoFilledSlotsBetween($startOfMonth, $endOfMonth, $prestadorUserIds);
        $filledSlotsWeek = $this->ocupacaoFilledSlotsBetween($startOfWeek, $endOfWeek, $prestadorUserIds);

        $taxaOcupacaoMes = $totalSlotsMonth > 0 ? round(min(100, ($filledSlotsMonth / $totalSlotsMonth) * 100), 1) : 0;
        $taxaOcupacaoSemana = $totalSlotsWeek > 0 ? round(min(100, ($filledSlotsWeek / $totalSlotsWeek) * 100), 1) : 0;

        $marcacoesBase = $this->ocupacaoMarcacoesBase($prestadorUserIds)
            ->whereBetween('start_at', [$startOfMonthUtc, $endOfMonthUtc]);

        $porHora = array_fill(0, 24, 0);
        $diasNomes = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $porDiaSemana = [];
        foreach (range(0, 6) as $d) {
            $porDiaSemana[$d] = [
                'nome' => $diasNomes[$d],
                'total' => 0,
            ];
        }
        foreach ((clone $marcacoesBase)->pluck('start_at') as $startAt) {
            if (! $startAt) {
                continue;
            }
            $localStart = $startAt->copy()->timezone($tz);
            $porHora[$localStart->hour]++;
            $porDiaSemana[$localStart->dayOfWeek]['total']++;
        }

        $duracaoMediaGeral = CalendarEventService::query()
            ->whereHas('event', function ($q) use ($prestadorUserIds, $startOfMonthUtc, $endOfMonthUtc) {
                $q->where('store_id', current_store_id())
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
                    ->whereBetween('start_at', [$startOfMonthUtc, $endOfMonthUtc])
                    ->whereIn('user_id', $prestadorUserIds);
            })
            ->selectRaw('calendar_event_id, sum(duration) as total_min')
            ->groupBy('calendar_event_id')
            ->get()
            ->avg('total_min');

        $duracaoMediaPorServico = CalendarEventService::query()
            ->whereHas('event', function ($q) use ($prestadorUserIds, $startOfMonthUtc, $endOfMonthUtc) {
                $q->where('store_id', current_store_id())
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
                    ->whereBetween('start_at', [$startOfMonthUtc, $endOfMonthUtc])
                    ->whereIn('user_id', $prestadorUserIds);
            })
            ->join('services', 'calendar_event_services.service_id', '=', 'services.id')
            ->where('services.store_id', current_store_id())
            ->selectRaw('services.id, services.name as service_name, count(*) as qtd, avg(calendar_event_services.duration) as media_min')
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('qtd')
            ->limit(10)
            ->get();

        $ocupacaoPorTecnico = $this->ocupacaoPorTecnico($startOfMonth, $endOfMonth, $slotsByWeekdayKey, $prestadorUserIds);
        $slotsMaisVazios = $this->ocupacaoSlotsMaisVazios($startOfMonth, $endOfMonth, $store, $prestadorUserIds, $numTecnicos);

        $totalMarcacoesMes = (clone $marcacoesBase)->count();
        $horasTrabalhoMes = $totalMarcacoesMes > 0 && $duracaoMediaGeral
            ? round(($totalMarcacoesMes * $duracaoMediaGeral) / 60, 1)
            : 0;

        $openDaySlots = array_values(array_filter($slotsByWeekdayKey, fn (int $slots) => $slots > 0));
        $avgSlotsPerOpenDayPerTech = $openDaySlots !== []
            ? round(array_sum($openDaySlots) / count($openDaySlots), 1)
            : 0.0;

        $shortWeekdayLabels = [
            'mon' => 'Seg',
            'tue' => 'Ter',
            'wed' => 'Qua',
            'thu' => 'Qui',
            'fri' => 'Sex',
            'sat' => 'Sáb',
            'sun' => 'Dom',
        ];
        $storeOpenDaysLabel = collect($slotsByWeekdayKey)
            ->filter(fn (int $slots) => $slots > 0)
            ->keys()
            ->map(fn (string $key) => $shortWeekdayLabels[$key] ?? $key)
            ->implode(', ');

        $periodLabel = $startOfMonth->copy()->locale('pt')->translatedFormat('F Y');
        $weekPeriodLabel = $startOfWeek->isSameDay($endOfWeek)
            ? $startOfWeek->format('d/m/Y')
            : $startOfWeek->format('d/m').'–'.$endOfWeek->format('d/m/Y');
        $availableYears = range($this->ocupacaoMinYear($store), $today->year);
        $monthOptions = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
        $storeHoursLabel = $store->hoursDisplayLabel();
        $ocupacaoTimezoneLabel = $tz;

        return view('dashboard.ocupacao', compact(
            'taxaOcupacaoMes',
            'taxaOcupacaoSemana',
            'totalSlotsMonth',
            'filledSlotsMonth',
            'totalSlotsWeek',
            'filledSlotsWeek',
            'numTecnicos',
            'porHora',
            'porDiaSemana',
            'duracaoMediaGeral',
            'duracaoMediaPorServico',
            'ocupacaoPorTecnico',
            'slotsMaisVazios',
            'totalMarcacoesMes',
            'horasTrabalhoMes',
            'year',
            'month',
            'availableYears',
            'monthOptions',
            'periodLabel',
            'weekPeriodLabel',
            'storeHoursLabel',
            'storeOpenDaysLabel',
            'avgSlotsPerOpenDayPerTech',
            'ocupacaoTimezoneLabel',
        ));
    }

    /**
     * @return array{0: Carbon, 1: Carbon} Limites inclusivos no fuso horário da loja.
     */
    private function ocupacaoStoreMonthBounds(Store $store, int $year, int $month): array
    {
        $tz = $store->bookingTimezone();
        $start = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->startOfMonth();
        $end = $start->copy()->endOfMonth()->endOfDay();

        return [$start, $end];
    }

    /**
     * @return array{0: Carbon, 1: Carbon} Instantes UTC para comparação com start_at na BD.
     */
    private function ocupacaoUtcQueryBounds(Carbon $startLocal, Carbon $endLocal): array
    {
        return [
            StoreBusinessTime::toUtcInstant($startLocal),
            StoreBusinessTime::toUtcInstant($endLocal),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function ocupacaoWeekRangeInMonth(Carbon $startOfMonth, Carbon $endOfMonth, Carbon $today): array
    {
        $anchor = $today->betweenIncluded($startOfMonth, $endOfMonth)
            ? $today
            : $startOfMonth->copy()->day(min(15, $startOfMonth->daysInMonth));

        $weekStart = $anchor->copy()->startOfWeek();
        $weekEnd = $anchor->copy()->endOfWeek();
        if ($weekStart->lt($startOfMonth)) {
            $weekStart = $startOfMonth->copy();
        }
        if ($weekEnd->gt($endOfMonth)) {
            $weekEnd = $endOfMonth->copy();
        }

        return [$weekStart->startOfDay(), $weekEnd->endOfDay()];
    }

    private function ocupacaoMinYear(Store $store): int
    {
        $earliest = CalendarEvent::forStore(current_store_id())
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->min('start_at');

        if ($earliest === null) {
            return (int) StoreBusinessTime::nowForStore(current_store_id())->year;
        }

        return max(2000, (int) Carbon::parse($earliest)->timezone($store->bookingTimezone())->year);
    }

    private function ocupacaoPrestadorUserIds(): Collection
    {
        return Agent::forStore(current_store_id())
            ->where('status', Agent::STATUS_ACTIVE)
            ->whereHas('user', fn ($q) => $q->whereIn('role', User::serviceProviderRoles()))
            ->pluck('user_id')
            ->filter()
            ->values();
    }

    /**
     * @return array<string, int>
     */
    private function ocupacaoSlotsByWeekdayKey(Store $store): array
    {
        $schedule = $store->normalizedWeeklySchedule();
        $out = [];
        foreach (Agent::WEEKDAY_KEYS as $dayKey) {
            $day = $schedule[$dayKey] ?? ['enabled' => false, 'start' => '09:00', 'end' => '20:00'];
            if (! ($day['enabled'] ?? false)) {
                $out[$dayKey] = 0;

                continue;
            }
            $start = Agent::timeStringToMinutes($day['start'] ?? '09:00');
            $end = Agent::timeStringToMinutes($day['end'] ?? '20:00');
            $minutes = max(0, $end - $start);
            $out[$dayKey] = $minutes > 0 ? (int) floor($minutes / self::SLOT_DURATION_MINUTES) : 0;
        }

        return $out;
    }

    private function ocupacaoIsoWeekdayToScheduleKey(int $iso): string
    {
        return match ($iso) {
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
            default => 'mon',
        };
    }

    /**
     * @param  array<string, int>  $slotsByWeekdayKey
     */
    private function ocupacaoTotalSlotsBetween(Carbon $start, Carbon $end, array $slotsByWeekdayKey, int $numTecnicos): int
    {
        if ($numTecnicos <= 0) {
            return 0;
        }

        $perTech = 0;
        $d = $start->copy()->startOfDay();
        while ($d->lte($end)) {
            $key = $this->ocupacaoIsoWeekdayToScheduleKey($d->dayOfWeekIso);
            $perTech += $slotsByWeekdayKey[$key] ?? 0;
            $d->addDay();
        }

        return $perTech * $numTecnicos;
    }

    private function ocupacaoMarcacoesBase(Collection $prestadorUserIds)
    {
        $query = CalendarEvent::forStore(current_store_id())
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);

        if ($prestadorUserIds->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('user_id', $prestadorUserIds);
    }

    private function ocupacaoFilledSlotsBetween(Carbon $startLocal, Carbon $endLocal, Collection $prestadorUserIds): float
    {
        if ($prestadorUserIds->isEmpty()) {
            return 0;
        }

        [$startUtc, $endUtc] = $this->ocupacaoUtcQueryBounds($startLocal, $endLocal);

        $eventIds = CalendarEvent::forStore(current_store_id())->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereIn('user_id', $prestadorUserIds)
            ->whereBetween('start_at', [$startUtc, $endUtc])
            ->pluck('id');

        if ($eventIds->isEmpty()) {
            return 0;
        }

        $totalMinutes = CalendarEventService::whereIn('calendar_event_id', $eventIds)->sum('duration');
        $cesIds = CalendarEventService::whereIn('calendar_event_id', $eventIds)->pluck('id');
        $extraMinutes = CalendarEventServiceExtra::whereIn('calendar_event_service_id', $cesIds)->sum('duration');

        $total = (int) $totalMinutes + (int) $extraMinutes;

        return ceil($total / self::SLOT_DURATION_MINUTES);
    }

    /**
     * Slots recorrentes (dia da semana + janela horária) com menor taxa de ocupação no período.
     *
     * @return \Illuminate\Support\Collection<int, object{
     *     day_label: string,
     *     time_label: string,
     *     slot_label: string,
     *     total_slots: int,
     *     filled_slots: int,
     *     empty_slots: int,
     *     taxa_ocupacao: float,
     *     taxa_vazio: float,
     *     sort: int
     * }>
     */
    private function ocupacaoSlotsMaisVazios(
        Carbon $startLocal,
        Carbon $endLocal,
        Store $store,
        Collection $prestadorUserIds,
        int $numTecnicos,
        int $limit = 12,
    ): Collection {
        $templates = $this->ocupacaoSlotTemplates($store);
        if ($templates === [] || $numTecnicos <= 0) {
            return collect();
        }

        $tz = $store->bookingTimezone();
        [$startUtc, $endUtc] = $this->ocupacaoUtcQueryBounds($startLocal, $endLocal);

        $agg = [];
        foreach ($templates as $key => $tpl) {
            $agg[$key] = array_merge($tpl, [
                'capacity_minutes' => 0,
                'filled_minutes' => 0,
            ]);
        }

        $periodStart = $startLocal->copy()->timezone($tz)->startOfDay();
        $periodEnd = $endLocal->copy()->timezone($tz)->endOfDay();
        $d = $periodStart->copy();
        while ($d->lte($periodEnd)) {
            $dayKey = $this->ocupacaoIsoWeekdayToScheduleKey($d->dayOfWeekIso);
            foreach ($templates as $key => $tpl) {
                if ($tpl['day_key'] !== $dayKey) {
                    continue;
                }
                $agg[$key]['capacity_minutes'] += self::SLOT_DURATION_MINUTES * $numTecnicos;
            }
            $d->addDay();
        }

        $events = CalendarEvent::forStore(current_store_id())
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereIn('user_id', $prestadorUserIds)
            ->whereBetween('start_at', [$startUtc, $endUtc])
            ->get(['start_at', 'end_at']);

        foreach ($events as $event) {
            if (! $event->start_at) {
                continue;
            }
            $eventStart = $event->start_at->copy()->timezone($tz);
            $eventEnd = $event->end_at?->copy()->timezone($tz) ?? $eventStart->copy()->addMinutes(self::SLOT_DURATION_MINUTES);

            $dayCursor = $eventStart->copy()->startOfDay();
            while ($dayCursor->lte($eventEnd) && $dayCursor->lte($periodEnd)) {
                if ($dayCursor->gte($periodStart)) {
                    $dayKey = $this->ocupacaoIsoWeekdayToScheduleKey($dayCursor->dayOfWeekIso);
                    foreach ($templates as $key => $tpl) {
                        if ($tpl['day_key'] !== $dayKey) {
                            continue;
                        }
                        $slotStart = $dayCursor->copy()->addMinutes($tpl['start_min']);
                        $slotEnd = $dayCursor->copy()->addMinutes($tpl['end_min']);
                        $overlap = $this->ocupacaoOverlapMinutes($eventStart, $eventEnd, $slotStart, $slotEnd);
                        if ($overlap > 0) {
                            $agg[$key]['filled_minutes'] += $overlap;
                        }
                    }
                }
                $dayCursor->addDay();
            }
        }

        return collect($agg)
            ->map(function (array $row) use ($numTecnicos) {
                $capacityMinutes = (int) ($row['capacity_minutes'] ?? 0);
                if ($capacityMinutes <= 0) {
                    return null;
                }

                $filledMinutes = min($capacityMinutes, (int) ($row['filled_minutes'] ?? 0));
                $totalSlots = (int) ceil($capacityMinutes / self::SLOT_DURATION_MINUTES);
                $filledSlots = (int) min($totalSlots, ceil($filledMinutes / self::SLOT_DURATION_MINUTES));
                $emptySlots = max(0, $totalSlots - $filledSlots);
                $taxaOcupacao = round(min(100, ($filledMinutes / $capacityMinutes) * 100), 1);

                return (object) [
                    'day_label' => $row['day_label'],
                    'time_label' => $row['time_label'],
                    'slot_label' => $row['day_label'].' '.$row['time_label'],
                    'total_slots' => $totalSlots,
                    'filled_slots' => $filledSlots,
                    'empty_slots' => $emptySlots,
                    'taxa_ocupacao' => $taxaOcupacao,
                    'taxa_vazio' => round(100 - $taxaOcupacao, 1),
                    'sort' => $row['sort'],
                ];
            })
            ->filter()
            ->sortBy([
                ['taxa_ocupacao', 'asc'],
                ['sort', 'asc'],
            ])
            ->take($limit)
            ->values();
    }

    /**
     * @return array<string, array{key: string, day_key: string, day_label: string, start_min: int, end_min: int, time_label: string, sort: int}>
     */
    private function ocupacaoSlotTemplates(Store $store): array
    {
        $schedule = $store->normalizedWeeklySchedule();
        $shortWeekdayLabels = [
            'mon' => 'Seg',
            'tue' => 'Ter',
            'wed' => 'Qua',
            'thu' => 'Qui',
            'fri' => 'Sex',
            'sat' => 'Sáb',
            'sun' => 'Dom',
        ];
        $templates = [];

        foreach (Agent::WEEKDAY_KEYS as $index => $dayKey) {
            $day = $schedule[$dayKey] ?? ['enabled' => false, 'start' => '09:00', 'end' => '20:00'];
            if (! ($day['enabled'] ?? false)) {
                continue;
            }

            $startMin = Agent::timeStringToMinutes($day['start'] ?? '09:00');
            $endMin = Agent::timeStringToMinutes($day['end'] ?? '20:00');
            $slotCount = (int) floor(max(0, $endMin - $startMin) / self::SLOT_DURATION_MINUTES);

            for ($i = 0; $i < $slotCount; $i++) {
                $slotStart = $startMin + ($i * self::SLOT_DURATION_MINUTES);
                $slotEnd = $slotStart + self::SLOT_DURATION_MINUTES;
                $key = $dayKey.'_'.$slotStart;
                $templates[$key] = [
                    'key' => $key,
                    'day_key' => $dayKey,
                    'day_label' => $shortWeekdayLabels[$dayKey] ?? $dayKey,
                    'start_min' => $slotStart,
                    'end_min' => $slotEnd,
                    'time_label' => $this->ocupacaoMinutesToTime($slotStart).'–'.$this->ocupacaoMinutesToTime($slotEnd),
                    'sort' => ($index * 10_000) + $slotStart,
                ];
            }
        }

        return $templates;
    }

    private function ocupacaoMinutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function ocupacaoOverlapMinutes(Carbon $aStart, Carbon $aEnd, Carbon $bStart, Carbon $bEnd): int
    {
        $start = max($aStart->getTimestamp(), $bStart->getTimestamp());
        $end = min($aEnd->getTimestamp(), $bEnd->getTimestamp());
        if ($end <= $start) {
            return 0;
        }

        return (int) floor(($end - $start) / 60);
    }

    /**
     * @param  array<string, int>  $slotsByWeekdayKey
     */
    private function ocupacaoPorTecnico(Carbon $startLocal, Carbon $endLocal, array $slotsByWeekdayKey, Collection $prestadorUserIds): Collection
    {
        if ($prestadorUserIds->isEmpty()) {
            return collect();
        }

        [$startUtc, $endUtc] = $this->ocupacaoUtcQueryBounds($startLocal, $endLocal);
        $totalSlotsPerTech = $this->ocupacaoTotalSlotsBetween($startLocal, $endLocal, $slotsByWeekdayKey, 1);

        $filledByUser = CalendarEventService::query()
            ->join('calendar_events', 'calendar_event_services.calendar_event_id', '=', 'calendar_events.id')
            ->where('calendar_events.store_id', current_store_id())
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereBetween('calendar_events.start_at', [$startUtc, $endUtc])
            ->whereIn('calendar_events.user_id', $prestadorUserIds)
            ->selectRaw('calendar_events.user_id, sum(calendar_event_services.duration) as total_min')
            ->groupBy('calendar_events.user_id')
            ->get()
            ->keyBy('user_id');

        $users = User::whereIn('id', $prestadorUserIds)->get()->keyBy('id');

        return $prestadorUserIds->map(function ($userId) use ($filledByUser, $totalSlotsPerTech, $users) {
            $row = $filledByUser->get($userId);
            $totalMin = $row ? (int) $row->total_min : 0;
            $filledSlots = ceil($totalMin / self::SLOT_DURATION_MINUTES);
            $taxa = $totalSlotsPerTech > 0 ? round(min(100, ($filledSlots / $totalSlotsPerTech) * 100), 1) : 0;

            return (object) [
                'user_id' => $userId,
                'name' => $users->get($userId)?->name ?? 'N/A',
                'filled_slots' => $filledSlots,
                'total_slots' => (int) $totalSlotsPerTech,
                'taxa' => $taxa,
            ];
        })->sortByDesc('taxa')->values();
    }

    /**
     * Método antigo para páginas do template (mantido para compatibilidade)
     */
    public function page($page)
    {
        $allowedPages = [
            'index',
            'apps-calendar',
            'apps-chat',
            'apps-email',
            'apps-hr-add-leave',
            'apps-hr-attendance',
            'apps-hr-employee-leave',
            'apps-hr-employee-list',
            'apps-hr-holidays',
            'apps-hr-leave',
            'apps-hr-main-attendance',
            'apps-hr-payroll-employee-salary',
            'apps-hr-payroll-payslip',
            'apps-hr-performance',
            'apps-kanban',
            'apps-prodcast-audience-analytics',
            'apps-prodcast-episode-manage',
            'apps-prodcast-list',
            'apps-real-estate-add-property',
            'apps-real-estate-agents',
            'apps-real-estate-clinets',
            'apps-real-estate-property-details',
            'apps-real-estate-property-list',
            'auth-email-verify',
            'auth-forgot-password',
            'auth-reset-password',
            'auth-signin',
            'auth-signout',
            'auth-signup',
            'auth-two-step-verify',
            'chart-apex-line',
            'chart-js-chart',
            'coming-soon',
            'dashboard-fitness',
            'dashboard-prodcast',
            'dashboard-real-estate',
            'echart-chart',
            'error',
            'google-maps',
            'icons-bootstrap',
            'icons-lucide',
            'icons-remix',
            'maps-leaflet',
            'maps-vector',
            'not-authorize',
            'pages-billing-subscription',
            'pages-blog-create',
            'pages-blog-details',
            'pages-blog-list',
            'pages-faqs',
            'pages-pricing',
            'pages-privacy-policy',
            'pages-profile',
            'pages-starter',
            'pages-terms-conditions',
            'pages-timeline',
            'ui-accordions',
            'ui-advance-swiper',
            'ui-alerts',
            'ui-avatars',
            'ui-badges',
            'ui-block',
            'ui-breadcrumbs',
            'ui-button-group',
            'ui-buttons',
            'ui-card',
            'ui-carousel',
            'ui-cookie',
            'ui-date-picker',
            'ui-draggable-cards',
            'ui-dropdowns',
            'ui-floating-labels',
            'ui-form-advanced',
            'ui-form-checkboxs-radios',
            'ui-form-editor',
            'ui-form-elements',
            'ui-form-file-uploads',
            'ui-form-input-group',
            'ui-form-input-masks',
            'ui-form-input-spin',
            'ui-form-layout',
            'ui-form-range',
            'ui-form-select',
            'ui-form-validation',
            'ui-form-wizards',
            'ui-images-figures',
            'ui-links',
            'ui-list',
            'ui-media-player',
            'ui-modal',
            'ui-offcanvas',
            'ui-pagination',
            'ui-placeholders',
            'ui-popover',
            'ui-progress',
            'ui-ratings',
            'ui-ribbons',
            'ui-scrollspy',
            'ui-separator',
            'ui-sortable-js',
            'ui-spinner',
            'ui-sweetalert2',
            'ui-tables-basic',
            'ui-tables-datatables',
            'ui-tables-gridjs',
            'ui-tables-listjs',
            'ui-tabs',
            'ui-tagify',
            'ui-toast',
            'ui-tooltips',
            'ui-tour',
            'ui-treeview',
            'ui-typography',
            'ui-utilities',
            'under-maintenance',
        ];

        if (in_array($page, $allowedPages) && view()->exists($page)) {
            return view($page);
        }

        abort(404);
    }

    private function redirectPrestadorFromAdminDashboard(): ?\Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();
        if ($user instanceof User && ($user->isPrestador() || $user->isRececao())) {
            return redirect()->route('dashboard');
        }

        return null;
    }
}
