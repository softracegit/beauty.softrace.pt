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
use App\Services\MarcacaoGlueSuggestionsService;
use App\Services\PrestadorDashboardService;
use App\Support\CrmPrivacyLock;
use App\Support\CurrentStore;
use App\Support\MarcacaoMoneyBatch;
use App\Support\StoreBusinessTime;
use App\Support\StoreContextPreference;
use App\Support\WeeklyScheduleWindow;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    /** @var list<int>|null */
    private ?array $dashboardStoreIds = null;

    public function __construct(
        private readonly FinancialDashboardService $financialDashboard,
    ) {}

    /**
     * Dashboard Resumo (página inicial do dashboard).
     */
    public function resumo(Request $request, PrestadorDashboardService $prestadorDashboard)
    {
        $user = auth()->user();
        $storeId = current_store_id();

        if ($user instanceof User && $user->isPrestador()) {
            return view('dashboard.prestador', $prestadorDashboard->build($user, $storeId));
        }

        if ($user instanceof User && $user->isRececao()) {
            return view('dashboard.prestador', $prestadorDashboard->buildForStore($storeId, $user));
        }

        if (app(CrmPrivacyLock::class)->isActive()) {
            return view('dashboard.prestador', $prestadorDashboard->buildForStore($storeId, $user));
        }

        $filter = $this->resolveDashboardStoreContext($request);
        $storeId = $this->primaryDashboardStoreId();

        $today = StoreBusinessTime::nowForStore($storeId)->startOfDay();
        $currentYear = $today->year;
        $previousYear = $currentYear - 1;

        $store = Store::query()->find($storeId) ?? current_store()->get();
        $prestadorAgents = $this->ocupacaoPrestadorAgents();
        $prestadorUserIds = $prestadorAgents->pluck('user_id')->filter()->map(fn ($id): int => (int) $id)->values();

        $kpiPorPeriodo = $this->resumoKpiPorPeriodo($today, $store, $prestadorAgents, $prestadorUserIds);

        $monthLabels = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthLabels[] = Carbon::create($currentYear, $month, 1)
                ->locale('pt_PT')
                ->translatedFormat('M');
        }

        [$vendasAnoAtual, $vendasAnoAnterior] = $this->resumoVendasAnuaisPorMes($currentYear, $previousYear, $today);
        [$atendidosAnoAtual, $atendidosAnoAnterior] = $this->resumoAtendidosAnuaisPorMes($currentYear, $previousYear, $today);

        $clientesContacto = $this->resumoClientesContactoStats();
        $vendasMesCorrente = $this->resumoVendasDiariasDoMes($today);
        $opsKpis = $this->resumoOpsKpisAcrossStores($prestadorDashboard, $user instanceof User ? $user : null);

        return view('dashboard.resumo', array_merge([
            'kpiPorPeriodo' => $kpiPorPeriodo,
            'monthLabels' => $monthLabels,
            'vendasAnoAtual' => $vendasAnoAtual,
            'vendasAnoAnterior' => $vendasAnoAnterior,
            'atendidosAnoAtual' => $atendidosAnoAtual,
            'atendidosAnoAnterior' => $atendidosAnoAnterior,
            'currentYear' => $currentYear,
            'previousYear' => $previousYear,
            'clientesContacto' => $clientesContacto,
            'vendasMesCorrente' => $vendasMesCorrente,
            'marcacoesHoje' => $opsKpis['marcacoesHoje'],
            'faltasHoje' => $opsKpis['faltasHoje'],
            'marcacoesEstaSemana' => $opsKpis['marcacoesEstaSemana'],
            'marcacoesEsteMes' => $opsKpis['marcacoesEsteMes'],
            'reportDateToday' => $opsKpis['reportDateToday'],
            'reportDateWeekStart' => $opsKpis['reportDateWeekStart'],
            'reportDateWeekEnd' => $opsKpis['reportDateWeekEnd'],
            'reportDateMonthStart' => $opsKpis['reportDateMonthStart'],
            'reportDateMonthEnd' => $opsKpis['reportDateMonthEnd'],
        ], $this->dashStoreViewData($filter)));
    }

    /**
     * Dashboard Financeiro (receitas, rankings e pré-visualização de comissões/despesas).
     */
    public function financeiro(Request $request)
    {
        if ($redirect = $this->redirectPrestadorFromAdminDashboard()) {
            return $redirect;
        }

        $filter = $this->resolveDashboardStoreContext($request);
        $storeIds = $filter['store_ids'];
        $storeId = $this->primaryDashboardStoreId();
        $today = StoreBusinessTime::nowForStore($storeId)->startOfDay();
        $year = (int) $request->input('year', $today->year);
        $monthRaw = $request->input('month', (string) $today->month);
        $month = ($monthRaw === '0' || $monthRaw === 0 || $monthRaw === 'all')
            ? FinancialDashboardService::MONTH_WHOLE_YEAR
            : max(1, min(12, (int) $monthRaw));

        if (count($storeIds) === 1) {
            $data = $this->financialDashboard->build($storeIds[0], $year, $month);
        } else {
            $data = $this->mergeFinancialDashboardBuilds($storeIds, $year, $month);
        }

        return view('dashboard.financeiro', array_merge($data, $this->dashStoreViewData($filter)));
    }

    /**
     * Dashboard Equipa — carga por técnica (marcações, horas, tempo pessoal).
     */
    public function equipa(Request $request)
    {
        if ($redirect = $this->redirectPrestadorFromAdminDashboard()) {
            return $redirect;
        }

        $filter = $this->resolveDashboardStoreContext($request);
        $storeId = $this->primaryDashboardStoreId();
        $store = Store::query()->find($storeId) ?? current_store()->get();
        $today = StoreBusinessTime::nowForStore($storeId)->startOfDay();
        $storeSchedule = $store->normalizedWeeklySchedule();

        $agents = Agent::query()
            ->whereIn('store_id', $filter['store_ids'])
            ->activeServiceProviders()
            ->with(['user', 'store'])
            ->orderBy('agenda_order')
            ->orderBy('name')
            ->get();

        $periodKeys = ['ontem', 'hoje', 'amanha', 'semana', 'mes'];
        /** @var array<string, array{0: Carbon, 1: Carbon}> $bounds */
        $bounds = [];
        foreach ($periodKeys as $period) {
            $bounds[$period] = $this->resumoPeriodBounds($period, $today);
        }

        $cardsByPeriod = $this->equipaBuildCardsByPeriod($agents, $bounds, $storeSchedule, $filter['store_ids']);

        $periodLabels = [
            'ontem' => 'Ontem',
            'hoje' => 'Hoje',
            'amanha' => 'Amanhã',
            'semana' => 'Semana',
            'mes' => 'Mês',
        ];

        return view('dashboard.equipa', array_merge(compact(
            'cardsByPeriod',
            'periodLabels',
            'periodKeys',
        ), $this->dashStoreViewData($filter)));
    }

    /**
     * @param  Collection<int, Agent>  $agents
     * @param  array<string, array{0: Carbon, 1: Carbon}>  $bounds
     * @param  array<string, array{enabled?: bool, start?: string|null, end?: string|null}>  $storeSchedule
     * @param  list<int>  $storeIds
     * @return array<string, list<array<string, mixed>>>
     */
    private function equipaBuildCardsByPeriod(
        Collection $agents,
        array $bounds,
        array $storeSchedule,
        array $storeIds,
    ): array {
        $periodKeys = array_keys($bounds);
        $emptyPeriods = array_fill_keys($periodKeys, []);

        if ($agents->isEmpty()) {
            return $emptyPeriods;
        }

        $coverStart = null;
        $coverEnd = null;
        foreach ($bounds as [$start, $end]) {
            if ($coverStart === null || $start->lt($coverStart)) {
                $coverStart = $start->copy();
            }
            if ($coverEnd === null || $end->gt($coverEnd)) {
                $coverEnd = $end->copy();
            }
        }

        [$coverStartUtc, $coverEndUtc] = $this->ocupacaoUtcQueryBounds($coverStart, $coverEnd);
        /** @var array<string, array{0: Carbon, 1: Carbon}> $boundsUtc */
        $boundsUtc = [];
        foreach ($bounds as $period => [$start, $end]) {
            $boundsUtc[$period] = $this->ocupacaoUtcQueryBounds($start, $end);
        }

        $userIds = $agents->pluck('user_id')->filter()->map(fn ($id): int => (int) $id)->values();
        if ($userIds->isEmpty()) {
            return $emptyPeriods;
        }

        $excluded = [
            CalendarEvent::STATUS_CANCELADO,
            CalendarEvent::STATUS_ANULADO,
            CalendarEvent::STATUS_FALTOU,
        ];

        $marcacoes = $this->calendarEventsQuery($storeIds)
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereNotIn('status', $excluded)
            ->whereIn('user_id', $userIds)
            ->whereBetween('start_at', [$coverStartUtc, $coverEndUtc])
            ->with([
                'eventServiceItems:id,calendar_event_id,duration',
                'eventServiceItems.extras:id,calendar_event_service_id,duration',
            ])
            ->get(['id', 'user_id', 'start_at', 'status']);

        $pessoal = $this->calendarEventsQuery($storeIds)
            ->where('event_type', CalendarEvent::TYPE_TEMPO_PESSOAL)
            ->where(function ($q): void {
                $q->whereNull('status')
                    ->orWhereNotIn('status', [
                        CalendarEvent::STATUS_CANCELADO,
                        CalendarEvent::STATUS_ANULADO,
                    ]);
            })
            ->whereIn('user_id', $userIds)
            ->whereBetween('start_at', [$coverStartUtc, $coverEndUtc])
            ->get(['id', 'user_id', 'start_at', 'end_at']);

        /** @var array<string, array<int, array{marcacoes: int, minutos_marcacao: int, minutos_pessoal: int}>> $agg */
        $agg = [];
        foreach ($periodKeys as $period) {
            foreach ($userIds as $uid) {
                $agg[$period][$uid] = [
                    'marcacoes' => 0,
                    'concluidas' => 0,
                    'minutos_marcacao' => 0,
                    'minutos_pessoal' => 0,
                ];
            }
        }

        $statusConcluidos = [
            CalendarEvent::STATUS_TERMINADO,
            CalendarEvent::STATUS_COMPLETO,
        ];

        foreach ($marcacoes as $event) {
            $startAt = $event->start_at;
            $uid = (int) ($event->user_id ?? 0);
            if ($startAt === null || $uid <= 0 || ! isset($agg[$periodKeys[0]][$uid])) {
                continue;
            }
            $minutes = 0;
            foreach ($event->eventServiceItems ?? [] as $item) {
                $minutes += (int) ($item->duration ?? 0);
                foreach ($item->extras ?? [] as $extra) {
                    $minutes += (int) ($extra->duration ?? 0);
                }
            }
            $status = (string) ($event->status ?? CalendarEvent::STATUS_AGENDADO);
            $isConcluida = in_array($status, $statusConcluidos, true);
            foreach ($boundsUtc as $period => [$pStart, $pEnd]) {
                if ($startAt->betweenIncluded($pStart, $pEnd)) {
                    $agg[$period][$uid]['marcacoes']++;
                    $agg[$period][$uid]['minutos_marcacao'] += $minutes;
                    if ($isConcluida) {
                        $agg[$period][$uid]['concluidas']++;
                    }
                }
            }
        }

        foreach ($pessoal as $event) {
            $startAt = $event->start_at;
            $endAt = $event->end_at;
            $uid = (int) ($event->user_id ?? 0);
            if ($startAt === null || $uid <= 0 || ! isset($agg[$periodKeys[0]][$uid])) {
                continue;
            }
            $minutes = 0;
            if ($endAt !== null) {
                $minutes = max(0, (int) $startAt->diffInMinutes($endAt));
            }
            foreach ($boundsUtc as $period => [$pStart, $pEnd]) {
                if ($startAt->betweenIncluded($pStart, $pEnd)) {
                    $agg[$period][$uid]['minutos_pessoal'] += $minutes;
                }
            }
        }

        $capacityByPeriodByAgent = [];
        foreach ($bounds as $period => [$start, $end]) {
            foreach ($agents as $agent) {
                $uid = (int) ($agent->user_id ?? 0);
                if ($uid <= 0) {
                    continue;
                }
                $agentSchedule = $agent->store?->normalizedWeeklySchedule() ?? $storeSchedule;
                // Capacidade = horário da técnica ∩ loja (sem −1 h almoço; isso só na taxa de ocupação).
                $capacityByPeriodByAgent[$period][$uid] = $this->ocupacaoCapacityMinutesForAgents(
                    $start,
                    $end,
                    collect([$agent]),
                    $agentSchedule,
                    false
                );
            }
        }

        $out = [];
        foreach ($periodKeys as $period) {
            $out[$period] = $agents->map(function (Agent $agent) use ($agg, $period, $capacityByPeriodByAgent): array {
                $uid = (int) ($agent->user_id ?? 0);
                $capacity = (int) ($capacityByPeriodByAgent[$period][$uid] ?? 0);
                $row = $agg[$period][$uid] ?? [
                    'marcacoes' => 0,
                    'concluidas' => 0,
                    'minutos_marcacao' => 0,
                    'minutos_pessoal' => 0,
                ];
                $minMarcacao = (int) $row['minutos_marcacao'];
                $minPessoal = (int) $row['minutos_pessoal'];
                $minVagas = max(0, $capacity - $minMarcacao - $minPessoal);
                $marcacoesCount = (int) $row['marcacoes'];
                $concluidas = (int) $row['concluidas'];
                $porConcluir = max(0, $marcacoesCount - $concluidas);
                $minMedio = $marcacoesCount > 0 ? (int) round($minMarcacao / $marcacoesCount) : 0;

                $pctMarcacao = $capacity > 0 ? (int) round(($minMarcacao / $capacity) * 100) : 0;
                $pctPessoal = $capacity > 0 ? (int) round(($minPessoal / $capacity) * 100) : 0;
                $pctVagas = $capacity > 0 ? (int) round(($minVagas / $capacity) * 100) : 0;
                $minExcesso = max(0, $minMarcacao + $minPessoal - $capacity);
                // Barra = fatias da capacidade (máx. 100%). Se excede, o excesso não entra na barra.
                $barPctMarcacao = min(100, $pctMarcacao);
                $barPctPessoal = min(max(0, 100 - $barPctMarcacao), $pctPessoal);
                $barPctVagas = max(0, 100 - $barPctMarcacao - $barPctPessoal);
                // Preenchimento = % de capacidade ocupada por marcações (alinhado ao segmento verde).
                $preenchimento = (float) $barPctMarcacao;

                $avatarUrl = $agent->avatar
                    ? asset('storage/'.$agent->avatar)
                    : asset('template/img/avatars/avatar-'.((($agent->id % 9) + 1)).'.webp');

                return [
                    'agent_id' => (int) $agent->id,
                    'user_id' => $uid,
                    'name' => $agent->name ?: ($agent->user?->name ?? '—'),
                    'color' => $agent->color ?: '#6c757d',
                    'avatar_url' => $avatarUrl,
                    'marcacoes' => $marcacoesCount,
                    'concluidas' => $concluidas,
                    'por_concluir' => $porConcluir,
                    'minutos_marcacao' => $minMarcacao,
                    'minutos_pessoal' => $minPessoal,
                    'minutos_vagas' => $minVagas,
                    'minutos_excesso' => $minExcesso,
                    'minutos_capacidade' => $capacity,
                    'minutos_medio' => $minMedio,
                    'horas_marcacao' => $this->equipaFormatMinutes($minMarcacao),
                    'horas_pessoal' => $this->equipaFormatMinutes($minPessoal),
                    'horas_vagas' => $this->equipaFormatMinutes($minVagas),
                    'horas_excesso' => $this->equipaFormatMinutes($minExcesso),
                    'horas_capacidade' => $this->equipaFormatMinutes($capacity),
                    'horas_medio' => $this->equipaFormatMinutes($minMedio),
                    'pct_marcacao' => $pctMarcacao,
                    'pct_pessoal' => $pctPessoal,
                    'pct_vagas' => $pctVagas,
                    'bar_pct_marcacao' => $barPctMarcacao,
                    'bar_pct_pessoal' => $barPctPessoal,
                    'bar_pct_vagas' => $barPctVagas,
                    'excede_capacidade' => $minExcesso > 0,
                    'preenchimento' => $preenchimento,
                ];
            })->values()->all();
        }

        return $out;
    }

    private function equipaFormatMinutes(int $minutes): string
    {
        $minutes = max(0, $minutes);
        if ($minutes < 60) {
            return $minutes.' min';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m > 0 ? $h.'h '.$m.'min' : $h.'h';
    }

    /** Formato compacto tipo 29h30min (subtítulo dos KPI de ocupação). */
    private function ocupacaoFormatDurationLabel(int $minutes): string
    {
        $minutes = max(0, $minutes);
        if ($minutes < 60) {
            return $minutes.'min';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m > 0 ? $h.'h'.$m.'min' : $h.'h';
    }

    /**
     * Dashboard Marcações.
     */
    public function marcacoes(Request $request)
    {
        if ($redirect = $this->redirectPrestadorFromAdminDashboard()) {
            return $redirect;
        }

        $filter = $this->resolveDashboardStoreContext($request);
        $storeIds = $filter['store_ids'];
        $storeId = $this->primaryDashboardStoreId();
        $today = StoreBusinessTime::nowForStore($storeId)->startOfDay();
        $startOfWeek = $today->copy()->startOfWeek();
        $endOfWeek = $today->copy()->endOfWeek()->endOfDay();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth()->endOfDay();
        $endOfToday = $today->copy()->endOfDay();
        $startOfTomorrow = $today->copy()->addDay()->startOfDay();
        $endOfTomorrow = $today->copy()->addDay()->endOfDay();

        $marcacoesBase = $this->calendarEventsQuery($storeIds)->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);

        [$hojeStartUtc, $hojeEndUtc] = $this->ocupacaoUtcQueryBounds($today, $endOfToday);
        [$semanaStartUtc, $semanaEndUtc] = $this->ocupacaoUtcQueryBounds($startOfWeek, $endOfWeek);
        [$mesStartUtc, $mesEndUtc] = $this->ocupacaoUtcQueryBounds($startOfMonth, $endOfMonth);

        $marcacoesHoje = (clone $marcacoesBase)->whereBetween('start_at', [$hojeStartUtc, $hojeEndUtc])->count();
        $marcacoesEstaSemana = (clone $marcacoesBase)
            ->whereBetween('start_at', [$semanaStartUtc, $semanaEndUtc])->count();
        $marcacoesEsteMes = (clone $marcacoesBase)
            ->whereBetween('start_at', [$mesStartUtc, $mesEndUtc])->count();
        $marcacoesMesAnterior = (clone $marcacoesBase)
            ->whereBetween('start_at', [
                StoreBusinessTime::toUtcInstant($startOfMonth->copy()->subMonth()->startOfMonth()),
                StoreBusinessTime::toUtcInstant($startOfMonth->copy()->subMonth()->endOfMonth()->endOfDay()),
            ])
            ->count();

        $variacaoMarcacoes = $marcacoesMesAnterior > 0
            ? round((($marcacoesEsteMes - $marcacoesMesAnterior) / $marcacoesMesAnterior) * 100, 1)
            : ($marcacoesEsteMes > 0 ? 100 : 0);

        $confirmacaoPorPeriodo = [
            'hoje' => $this->marcacoesConfirmacaoEntre($today, $endOfToday),
            'amanha' => $this->marcacoesConfirmacaoEntre($startOfTomorrow, $endOfTomorrow),
            'semana' => $this->marcacoesConfirmacaoEntre($startOfWeek, $endOfWeek),
            'mes' => $this->marcacoesConfirmacaoEntre($startOfMonth, $endOfMonth),
        ];

        $receitaHoje = $this->receitaMarcacoesEntre($today, $endOfToday);
        $receitaEstaSemana = $this->receitaMarcacoesEntre($startOfWeek, $endOfWeek);
        $receitaEsteMes = $this->receitaMarcacoesEntre($startOfMonth, $endOfMonth);
        $receitaMesAnterior = $this->receitaMarcacoesEntre(
            $startOfMonth->copy()->subMonth()->startOfMonth(),
            $startOfMonth->copy()->subMonth()->endOfMonth()->endOfDay()
        );
        $variacaoReceita = $receitaMesAnterior > 0
            ? round((($receitaEsteMes - $receitaMesAnterior) / $receitaMesAnterior) * 100, 1)
            : ($receitaEsteMes > 0 ? 100 : 0);

        $totalClientes = Client::forOrganization(current_organization_id())->count();
        $totalTecnicos = Agent::query()->whereIn('store_id', $storeIds)->where('status', Agent::STATUS_ACTIVE)->count();

        $proximasMarcacoes = $this->calendarEventsQuery($storeIds)->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->where('start_at', '>=', StoreBusinessTime::toUtcInstant($today))
            ->with(['client', 'user', 'eventServices'])
            ->orderBy('start_at')
            ->limit(8)
            ->get();

        $marcacoesRecentes = $this->calendarEventsQuery($storeIds)->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->with(['client', 'user', 'eventServices'])
            ->orderBy('start_at', 'desc')
            ->limit(5)
            ->get();

        // Marcações por serviço (independente de venda/faturação)
        $porServico = CalendarEventService::query()
            ->join('calendar_events', 'calendar_event_services.calendar_event_id', '=', 'calendar_events.id')
            ->join('services', 'calendar_event_services.service_id', '=', 'services.id')
            ->whereIn('calendar_events.store_id', $storeIds)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->groupBy('services.id', 'services.name')
            ->selectRaw('services.name as service_name, count(*) as total, sum(coalesce(calendar_event_services.price, services.price)) as receita')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $porTecnico = $this->calendarEventsQuery($storeIds)->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->load('user');

        // Receita por técnico: apenas vendas pagas de marcações concluídas
        $receitaPorTecnico = Sale::query()
            ->whereIn('sales.store_id', $storeIds)
            ->join('calendar_events', 'sales.calendar_event_id', '=', 'calendar_events.id')
            ->where('sales.status', Sale::STATUS_PAGO)
            ->whereIn('calendar_events.store_id', $storeIds)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', CalendarEvent::STATUS_COMPLETO)
            ->groupBy('calendar_events.user_id')
            ->selectRaw('calendar_events.user_id, sum(sales.total) as receita')
            ->get()
            ->keyBy('user_id');

        $porEstado = $this->calendarEventsQuery($storeIds)->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $mensalMarcacoes = [];
        $mensalReceita = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = $today->copy()->subMonths($i);
            $start = $date->copy()->startOfMonth()->startOfDay();
            $end = $date->copy()->endOfMonth()->endOfDay();
            $mensalMarcacoes[] = [
                'month' => $date->locale('pt_PT')->translatedFormat('M y'),
                'count' => (clone $marcacoesBase)->whereBetween('start_at', [
                    StoreBusinessTime::toUtcInstant($start),
                    StoreBusinessTime::toUtcInstant($end),
                ])->count(),
            ];
            $mensalReceita[] = [
                'month' => $date->locale('pt_PT')->translatedFormat('M y'),
                'revenue' => round($this->receitaMarcacoesEntre($start, $end), 2),
            ];
        }

        return view('dashboard.index', array_merge(compact(
            'marcacoesHoje',
            'marcacoesEstaSemana',
            'marcacoesEsteMes',
            'marcacoesMesAnterior',
            'variacaoMarcacoes',
            'confirmacaoPorPeriodo',
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
        ), $this->dashStoreViewData($filter)));
    }

    /**
     * Confirmadas vs não confirmadas no período (exclui cancelado/anulado/faltou).
     *
     * @return array{confirmadas: int, nao_confirmadas: int, total: int, pct_confirmadas: int, pct_nao_confirmadas: int}
     */
    private function marcacoesConfirmacaoEntre(Carbon $start, Carbon $end): array
    {
        if ($end->lt($start)) {
            return [
                'confirmadas' => 0,
                'nao_confirmadas' => 0,
                'total' => 0,
                'pct_confirmadas' => 0,
                'pct_nao_confirmadas' => 0,
            ];
        }

        [$startUtc, $endUtc] = $this->ocupacaoUtcQueryBounds($start, $end);
        $confirmados = [
            CalendarEvent::STATUS_CONFIRMADO,
            CalendarEvent::STATUS_CHEGOU,
            CalendarEvent::STATUS_INICIADO,
            CalendarEvent::STATUS_TERMINADO,
            CalendarEvent::STATUS_COMPLETO,
        ];
        $naoConfirmados = [
            CalendarEvent::STATUS_AGENDADO,
            CalendarEvent::STATUS_NOTIFICADO,
        ];

        $row = $this->calendarEventsQuery()
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where(function ($q) use ($confirmados, $naoConfirmados): void {
                $q->whereIn('status', array_merge($confirmados, $naoConfirmados))
                    ->orWhereNull('status');
            })
            ->whereBetween('start_at', [$startUtc, $endUtc])
            ->selectRaw(
                'SUM(CASE WHEN status IN (?,?,?,?,?) THEN 1 ELSE 0 END) as confirmadas,
                 SUM(CASE WHEN status IS NULL OR status IN (?,?) THEN 1 ELSE 0 END) as nao_confirmadas',
                [...$confirmados, ...$naoConfirmados]
            )
            ->first();

        $confirmadas = (int) ($row->confirmadas ?? 0);
        $naoConfirmadas = (int) ($row->nao_confirmadas ?? 0);
        $total = $confirmadas + $naoConfirmadas;
        $pctConfirmadas = $total > 0 ? (int) round(($confirmadas / $total) * 100) : 0;
        $pctNao = $total > 0 ? (100 - $pctConfirmadas) : 0;

        return [
            'confirmadas' => $confirmadas,
            'nao_confirmadas' => $naoConfirmadas,
            'total' => $total,
            'pct_confirmadas' => $pctConfirmadas,
            'pct_nao_confirmadas' => $pctNao,
        ];
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
            'amanha' => [
                $today->copy()->addDay()->startOfDay(),
                $today->copy()->addDay()->endOfDay(),
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
     * KPIs dos 5 períodos numa passagem (evita 5× pipeline / vendas / ocupação).
     *
     * @param  Collection<int, Agent>  $prestadorAgents
     * @param  Collection<int, int>  $prestadorUserIds
     * @return array<string, array{vendas_previsto: float, vendas_feitas: float, vendas_por_fazer: float, clientes_atendidos: int, taxa_ocupacao: float, horas_preenchidas: string, horas_uteis: string, ocupacao_label: string}>
     */
    private function resumoKpiPorPeriodo(
        Carbon $today,
        Store $store,
        Collection $prestadorAgents,
        Collection $prestadorUserIds,
    ): array {
        $periods = ['hoje', 'ontem', 'amanha', 'semana', 'mes'];
        /** @var array<string, array{0: Carbon, 1: Carbon}> $bounds */
        $bounds = [];
        foreach ($periods as $period) {
            $bounds[$period] = $this->resumoPeriodBounds($period, $today);
        }

        $coverStart = $bounds['hoje'][0]->copy();
        $coverEnd = $bounds['hoje'][1]->copy();
        foreach ($bounds as [$start, $end]) {
            if ($start->lt($coverStart)) {
                $coverStart = $start->copy();
            }
            if ($end->gt($coverEnd)) {
                $coverEnd = $end->copy();
            }
        }

        [$coverStartUtc, $coverEndUtc] = $this->ocupacaoUtcQueryBounds($coverStart, $coverEnd);
        /** @var array<string, array{0: Carbon, 1: Carbon}> $boundsUtc */
        $boundsUtc = [];
        foreach ($bounds as $period => [$start, $end]) {
            $boundsUtc[$period] = $this->ocupacaoUtcQueryBounds($start, $end);
        }

        $storeId = $this->primaryDashboardStoreId();
        $nowUtc = StoreBusinessTime::nowUtcForStore($storeId);

        $pipelineEvents = $this->calendarEventsQuery()
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereNotIn('status', [
                CalendarEvent::STATUS_CANCELADO,
                CalendarEvent::STATUS_ANULADO,
                CalendarEvent::STATUS_FALTOU,
            ])
            ->whereBetween('start_at', [$coverStartUtc, $coverEndUtc])
            ->with(['eventServiceItems.extras.extra'])
            ->get(['id', 'store_id', 'start_at', 'event_type', 'status']);

        $moneyBatch = new MarcacaoMoneyBatch(
            $pipelineEvents->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $storeId,
        );

        $pipelineByPeriod = array_fill_keys($periods, ['previsto' => 0.0, 'por_fazer' => 0.0]);
        foreach ($pipelineEvents as $event) {
            $startAt = $event->start_at;
            if ($startAt === null) {
                continue;
            }
            $serviceItems = $event->eventServiceItems ?? collect();
            $subtotal = $moneyBatch->chargeSubtotal($event, $serviceItems);
            $due = $moneyBatch->amountDue((int) $event->id, $subtotal);
            $previsto = max(0.0, $subtotal);
            $porFazer = max(0.0, $due);
            foreach ($boundsUtc as $period => [$pStart, $pEnd]) {
                if ($startAt->betweenIncluded($pStart, $pEnd)) {
                    $pipelineByPeriod[$period]['previsto'] += $previsto;
                    $pipelineByPeriod[$period]['por_fazer'] += $porFazer;
                }
            }
        }

        $vendasByPeriod = array_fill_keys($periods, 0.0);
        $sales = Sale::query()
            ->whereIn('store_id', $this->dashboardStoreIds())
            ->where('status', Sale::STATUS_PAGO)
            ->whereHas('calendarEvent', function ($cq) use ($coverStartUtc, $coverEndUtc): void {
                $cq->whereIn('store_id', $this->dashboardStoreIds())
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', CalendarEvent::STATUS_COMPLETO)
                    ->whereBetween('start_at', [$coverStartUtc, $coverEndUtc]);
            })
            ->with(['calendarEvent:id,start_at'])
            ->get(['id', 'total', 'calendar_event_id']);

        foreach ($sales as $sale) {
            $startAt = $sale->calendarEvent?->start_at;
            if ($startAt === null) {
                continue;
            }
            $total = (float) $sale->total;
            foreach ($boundsUtc as $period => [$pStart, $pEnd]) {
                if ($startAt->betweenIncluded($pStart, $pEnd)) {
                    $vendasByPeriod[$period] += $total;
                }
            }
        }

        $atendidosByPeriod = array_fill_keys($periods, 0);
        $atendidos = $this->calendarEventsQuery()
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('client_id')
            ->whereBetween('start_at', [$coverStartUtc, $coverEndUtc])
            ->alreadyPassed($nowUtc)
            ->get(['id', 'start_at']);

        foreach ($atendidos as $event) {
            $startAt = $event->start_at;
            if ($startAt === null) {
                continue;
            }
            foreach ($boundsUtc as $period => [$pStart, $pEnd]) {
                if ($startAt->betweenIncluded($pStart, $pEnd)) {
                    $atendidosByPeriod[$period]++;
                }
            }
        }

        $numTecnicos = $prestadorAgents->count();
        $ocupacaoByPeriod = array_fill_keys($periods, 0.0);
        $filledMinutesByPeriod = array_fill_keys($periods, 0);
        $capacityMinutesByPeriod = array_fill_keys($periods, 0);
        if ($numTecnicos > 0) {
            $ocupacaoEvents = $this->calendarEventsQuery()
                ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
                ->whereIn('user_id', $prestadorUserIds)
                ->whereBetween('start_at', [$coverStartUtc, $coverEndUtc])
                ->with(['eventServiceItems:id,calendar_event_id,duration', 'eventServiceItems.extras:id,calendar_event_service_id,duration'])
                ->get(['id', 'start_at']);

            foreach ($ocupacaoEvents as $event) {
                $startAt = $event->start_at;
                if ($startAt === null) {
                    continue;
                }
                $minutes = 0;
                foreach ($event->eventServiceItems ?? [] as $item) {
                    $minutes += (int) ($item->duration ?? 0);
                    foreach ($item->extras ?? [] as $extra) {
                        $minutes += (int) ($extra->duration ?? 0);
                    }
                }
                if ($minutes <= 0) {
                    continue;
                }
                foreach ($boundsUtc as $period => [$pStart, $pEnd]) {
                    if ($startAt->betweenIncluded($pStart, $pEnd)) {
                        $filledMinutesByPeriod[$period] += $minutes;
                    }
                }
            }

            foreach ($periods as $period) {
                [$start, $end] = $bounds[$period];
                $capacityMinutes = $this->ocupacaoCapacityAcrossStores(
                    $start,
                    $end,
                    $prestadorAgents,
                    true
                );
                $capacityMinutesByPeriod[$period] = $capacityMinutes;
                if ($capacityMinutes <= 0) {
                    continue;
                }
                $ocupacaoByPeriod[$period] = round(
                    min(100, ($filledMinutesByPeriod[$period] / $capacityMinutes) * 100),
                    1
                );
            }
        }

        $out = [];
        foreach ($periods as $period) {
            $horasPreenchidas = $this->ocupacaoFormatDurationLabel((int) round($filledMinutesByPeriod[$period]));
            $horasUteis = $this->ocupacaoFormatDurationLabel((int) $capacityMinutesByPeriod[$period]);
            $out[$period] = [
                'vendas_previsto' => round($pipelineByPeriod[$period]['previsto'], 2),
                'vendas_feitas' => round($vendasByPeriod[$period], 2),
                'vendas_por_fazer' => round($pipelineByPeriod[$period]['por_fazer'], 2),
                'clientes_atendidos' => $atendidosByPeriod[$period],
                'taxa_ocupacao' => $ocupacaoByPeriod[$period],
                'horas_preenchidas' => $horasPreenchidas,
                'horas_uteis' => $horasUteis,
                'ocupacao_label' => $horasPreenchidas.' / '.$horasUteis,
            ];
        }

        return $out;
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
        return $this->receitaMarcacoesEntre($start, $end);
    }

    /**
     * Pipeline de vendas do período (marcações activas, data da marcação).
     *
     * @return array{previsto: float, por_fazer: float}
     */
    private function resumoVendasPipelineEntre(Carbon $start, Carbon $end): array
    {
        if ($end->lt($start)) {
            return ['previsto' => 0.0, 'por_fazer' => 0.0];
        }

        $storeId = $this->primaryDashboardStoreId();
        [$startUtc, $endUtc] = $this->ocupacaoUtcQueryBounds($start, $end);

        $events = $this->calendarEventsQuery()
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->whereNotIn('status', [
                CalendarEvent::STATUS_CANCELADO,
                CalendarEvent::STATUS_ANULADO,
                CalendarEvent::STATUS_FALTOU,
            ])
            ->whereBetween('start_at', [$startUtc, $endUtc])
            ->with([
                'eventServiceItems.extras.extra',
            ])
            ->get();

        return MarcacaoMoneyBatch::sumPipelineTotals($events);
    }

    /**
     * Vendas pagas por dia no mês corrente (data da marcação).
     *
     * @return array{labels: array<int, string>, data: array<int, float>, month_label: string}
     */
    private function resumoVendasDiariasDoMes(Carbon $today): array
    {
        $start = $today->copy()->startOfMonth();
        $end = $today->copy()->endOfMonth()->endOfDay();
        $tz = $start->timezoneName;
        $storeIds = $this->dashboardStoreIds();
        $startUtc = StoreBusinessTime::toUtcInstant($start->copy()->startOfDay());
        $endUtc = StoreBusinessTime::toUtcInstant($end->copy()->endOfDay());

        $sales = Sale::query()
            ->whereIn('store_id', $storeIds)
            ->where('status', Sale::STATUS_PAGO)
            ->whereHas('calendarEvent', function ($cq) use ($storeIds, $startUtc, $endUtc): void {
                $cq->whereIn('store_id', $storeIds)
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', CalendarEvent::STATUS_COMPLETO)
                    ->whereBetween('start_at', [$startUtc, $endUtc]);
            })
            ->with('calendarEvent')
            ->get();

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
        $labels = [];
        $data = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $labels[] = (string) $day;
            $data[] = round($byDay[$day] ?? 0.0, 2);
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'month_label' => $start->copy()->locale('pt_PT')->translatedFormat('F Y'),
        ];
    }

    private function resumoClientesAtendidosEntre(Carbon $start, Carbon $end): int
    {
        if ($end->lt($start)) {
            return 0;
        }

        $storeId = $this->primaryDashboardStoreId();
        $nowUtc = StoreBusinessTime::nowUtcForStore($storeId);
        [$startUtc, $endUtc] = $this->ocupacaoUtcQueryBounds($start, $end);

        return (int) $this->calendarEventsQuery()
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('client_id')
            ->whereBetween('start_at', [$startUtc, $endUtc])
            ->alreadyPassed($nowUtc)
            ->count();
    }

    /**
     * @param  Collection<int, Agent>  $prestadorAgents
     * @param  Collection<int, int>  $prestadorUserIds
     */
    private function resumoTaxaOcupacaoEntre(
        Carbon $start,
        Carbon $end,
        Store $store,
        Collection $prestadorAgents,
        Collection $prestadorUserIds,
    ): float {
        if ($prestadorAgents->isEmpty()) {
            return 0;
        }

        $capacityMinutes = $this->ocupacaoCapacityAcrossStores(
            $start,
            $end,
            $prestadorAgents,
            true
        );
        if ($capacityMinutes <= 0) {
            return 0;
        }

        $filledMinutes = $this->ocupacaoFilledMinutesBetween($start, $end, $prestadorUserIds);

        return round(min(100, ($filledMinutes / $capacityMinutes) * 100), 1);
    }

    /**
     * @return array{total: int, com_telemovel: int, com_email: int, com_aniversario: int}
     */
    private function resumoClientesContactoStats(): array
    {
        $row = Client::forOrganization(current_organization_id())
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN phone IS NOT NULL AND phone != '' THEN 1 ELSE 0 END) as com_telemovel")
            ->selectRaw("SUM(CASE WHEN email IS NOT NULL AND email != '' THEN 1 ELSE 0 END) as com_email")
            ->selectRaw('SUM(CASE WHEN birth_date IS NOT NULL THEN 1 ELSE 0 END) as com_aniversario')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'com_telemovel' => (int) ($row->com_telemovel ?? 0),
            'com_email' => (int) ($row->com_email ?? 0),
            'com_aniversario' => (int) ($row->com_aniversario ?? 0),
        ];
    }

    /**
     * @return array{0: list<float>, 1: list<float>}
     */
    private function resumoVendasAnuaisPorMes(int $currentYear, int $previousYear, Carbon $today): array
    {
        return [
            $this->resumoVendasPorMesDoAno($currentYear, $today),
            $this->resumoVendasPorMesDoAno($previousYear, $today),
        ];
    }

    /**
     * @return list<float>
     */
    private function resumoVendasPorMesDoAno(int $year, Carbon $today): array
    {
        [$start, $end] = $this->resumoMonthBounds($year, 1, $today);
        $end = $this->resumoMonthBounds($year, 12, $today)[1];
        $tz = $today->timezoneName;

        $startUtc = StoreBusinessTime::toUtcInstant($start->copy()->startOfDay());
        $endUtc = StoreBusinessTime::toUtcInstant($end->copy()->endOfDay());

        $sales = Sale::query()
            ->whereIn('store_id', $this->dashboardStoreIds())
            ->where('status', Sale::STATUS_PAGO)
            ->whereHas('calendarEvent', function ($cq) use ($startUtc, $endUtc): void {
                $cq->whereIn('store_id', $this->dashboardStoreIds())
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', CalendarEvent::STATUS_COMPLETO)
                    ->whereBetween('start_at', [$startUtc, $endUtc]);
            })
            ->with(['calendarEvent:id,start_at'])
            ->get(['id', 'total', 'calendar_event_id']);

        $byMonth = array_fill(1, 12, 0.0);
        foreach ($sales as $sale) {
            $startAt = $sale->calendarEvent?->start_at;
            if ($startAt === null) {
                continue;
            }
            $month = (int) $startAt->timezone($tz)->month;
            if ($month >= 1 && $month <= 12) {
                $byMonth[$month] += (float) $sale->total;
            }
        }

        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $out[] = round($byMonth[$m], 2);
        }

        return $out;
    }

    /**
     * @return array{0: list<int>, 1: list<int>}
     */
    private function resumoAtendidosAnuaisPorMes(int $currentYear, int $previousYear, Carbon $today): array
    {
        return [
            $this->resumoAtendidosPorMesDoAno($currentYear, $today),
            $this->resumoAtendidosPorMesDoAno($previousYear, $today),
        ];
    }

    /**
     * @return list<int>
     */
    private function resumoAtendidosPorMesDoAno(int $year, Carbon $today): array
    {
        $storeId = $this->primaryDashboardStoreId();
        $nowUtc = StoreBusinessTime::nowUtcForStore($storeId);
        [$start] = $this->resumoMonthBounds($year, 1, $today);
        $end = $this->resumoMonthBounds($year, 12, $today)[1];
        [$startUtc, $endUtc] = $this->ocupacaoUtcQueryBounds($start, $end);
        $tz = $today->timezoneName;

        $events = $this->calendarEventsQuery()
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('client_id')
            ->whereBetween('start_at', [$startUtc, $endUtc])
            ->alreadyPassed($nowUtc)
            ->get(['id', 'start_at']);

        $byMonth = array_fill(1, 12, 0);
        foreach ($events as $event) {
            if ($event->start_at === null) {
                continue;
            }
            $month = (int) $event->start_at->timezone($tz)->month;
            if ($month >= 1 && $month <= 12) {
                $byMonth[$month]++;
            }
        }

        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $out[] = $byMonth[$m];
        }

        return $out;
    }

    /**
     * Receita total entre duas datas baseada em vendas pagas de marcações concluídas.
     */
    private function receitaMarcacoesEntre(Carbon $start, Carbon $end): float
    {
        if ($end->lt($start)) {
            return 0.0;
        }

        $storeIds = $this->dashboardStoreIds();
        $startUtc = StoreBusinessTime::toUtcInstant($start->copy()->startOfDay());
        $endUtc = StoreBusinessTime::toUtcInstant($end->copy()->endOfDay());

        return round((float) Sale::query()
            ->whereIn('store_id', $storeIds)
            ->where('status', Sale::STATUS_PAGO)
            ->whereHas('calendarEvent', function ($cq) use ($storeIds, $startUtc, $endUtc): void {
                $cq->whereIn('store_id', $storeIds)
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', CalendarEvent::STATUS_COMPLETO)
                    ->whereBetween('start_at', [$startUtc, $endUtc]);
            })
            ->sum('total'), 2);
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
    public function clientes(Request $request)
    {
        if ($redirect = $this->redirectPrestadorFromAdminDashboard()) {
            return $redirect;
        }

        $filter = $this->resolveDashboardStoreContext($request);
        $storeIds = $filter['store_ids'];
        $storeId = $this->primaryDashboardStoreId();

        $today = StoreBusinessTime::nowForStore($storeId)->startOfDay();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth()->endOfDay();

        $marcacoesBase = $this->calendarEventsQuery($storeIds)->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('client_id');

        $totalClientes = Client::forOrganization(current_organization_id())->count();
        $totalClientesComMarcacao = (clone $marcacoesBase)->distinct('client_id')->count('client_id');

        $clientesEsteMes = Client::forOrganization(current_organization_id())->whereMonth('created_at', $today->month)
            ->whereYear('created_at', $today->year)
            ->count();

        $marcacoesEsteMes = (clone $marcacoesBase)
            ->whereBetween('start_at', [
                StoreBusinessTime::toUtcInstant($startOfMonth),
                StoreBusinessTime::toUtcInstant($endOfMonth),
            ])
            ->get();

        $primeiraMarcacaoPorCliente = $this->calendarEventsQuery($storeIds)->where('event_type', CalendarEvent::TYPE_MARCACAO)
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
        $clientesComDuasOuMais = $this->calendarEventsQuery($storeIds)->where('event_type', CalendarEvent::TYPE_MARCACAO)
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
            ->forOrganization(current_organization_id())
            ->whereIn('id', $clientesComUmaOuMais)
            ->withCount(['calendarEvents as marcacoes_count' => function ($q) use ($storeIds) {
                $q->whereIn('store_id', $storeIds)
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
            ->forOrganization(current_organization_id())
            ->whereIn('id', $receitaPorCliente->keys())
            ->withCount(['calendarEvents as marcacoes_count' => function ($q) use ($storeIds) {
                $q->whereIn('store_id', $storeIds)
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
            $date = StoreBusinessTime::nowForStore($storeId)->subMonths($i);
            $monthlyGrowth[] = [
                'month' => $date->locale('pt_PT')->translatedFormat('M'),
                'count' => Client::forOrganization(current_organization_id())->whereMonth('created_at', $date->month)->whereYear('created_at', $date->year)->count(),
            ];
        }

        $recentClients = Client::forOrganization(current_organization_id())->orderBy('created_at', 'desc')->limit(10)->get();

        return view('dashboard.clientes', array_merge(compact(
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
        ), $this->dashStoreViewData($filter)));
    }

    /**
     * Receita total por client_id baseada em vendas concluídas (pagas) de marcações completas.
     */
    private function receitaPorCliente()
    {
        $storeIds = $this->dashboardStoreIds();

        return Sale::query()
            ->whereIn('store_id', $storeIds)
            ->where('status', Sale::STATUS_PAGO)
            ->whereNotNull('client_id')
            ->whereHas('calendarEvent', function ($q) use ($storeIds) {
                $q->whereIn('store_id', $storeIds)
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
        $clientIds = $this->calendarEventsQuery()->where('event_type', CalendarEvent::TYPE_MARCACAO)
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
            $datas = $this->calendarEventsQuery()->where('client_id', $clientId)
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

    /** Média de pausa de almoço por técnico/dia (horas úteis = horário da loja − isto). */
    private const OCUPACAO_LUNCH_BREAK_MINUTES = 60;

    /**
     * Dashboard de Ocupação (taxa de ocupação, picos, dias, duração média).
     */
    public function ocupacao(Request $request, MarcacaoGlueSuggestionsService $glueSuggestions)
    {
        if ($redirect = $this->redirectPrestadorFromAdminDashboard()) {
            return $redirect;
        }

        $filter = $this->resolveDashboardStoreContext($request);
        $storeIds = $filter['store_ids'];
        $storeId = $this->primaryDashboardStoreId();
        $gluePeriod = (string) $request->get('glue_period', 'hoje');
        // Cola de vagas: loja primária (agregação multi-loja não aplicável ao serviço actual).
        $glueSuggestionsData = $glueSuggestions->build($storeId, $gluePeriod);

        $store = Store::query()->find($storeId) ?? current_store()->get();
        $tz = $store->bookingTimezone();
        $today = StoreBusinessTime::nowForStore($storeId)->startOfDay();
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

        $prestadorAgents = $this->ocupacaoPrestadorAgents();
        $prestadorUserIds = $prestadorAgents->pluck('user_id')->filter()->map(fn ($id): int => (int) $id)->values();
        $numTecnicos = $prestadorAgents->count();
        $storeSchedule = $store->normalizedWeeklySchedule();

        $endOfToday = $today->copy()->endOfDay();
        $capacityMinutesHoje = $this->ocupacaoCapacityAcrossStores($today, $endOfToday, $prestadorAgents, true);
        $capacityMinutesMonth = $this->ocupacaoCapacityAcrossStores($startOfMonth, $endOfMonth, $prestadorAgents, true);
        $capacityMinutesWeek = $this->ocupacaoCapacityAcrossStores($startOfWeek, $endOfWeek, $prestadorAgents, true);
        $filledMinutesHoje = $this->ocupacaoFilledMinutesBetween($today, $endOfToday, $prestadorUserIds);
        $filledMinutesMonth = $this->ocupacaoFilledMinutesBetween($startOfMonth, $endOfMonth, $prestadorUserIds);
        $filledMinutesWeek = $this->ocupacaoFilledMinutesBetween($startOfWeek, $endOfWeek, $prestadorUserIds);

        $taxaOcupacaoHoje = $capacityMinutesHoje > 0
            ? round(min(100, ($filledMinutesHoje / $capacityMinutesHoje) * 100), 1)
            : 0;
        $taxaOcupacaoMes = $capacityMinutesMonth > 0
            ? round(min(100, ($filledMinutesMonth / $capacityMinutesMonth) * 100), 1)
            : 0;
        $taxaOcupacaoSemana = $capacityMinutesWeek > 0
            ? round(min(100, ($filledMinutesWeek / $capacityMinutesWeek) * 100), 1)
            : 0;

        $horasOcupacaoHoje = $this->ocupacaoFormatDurationLabel((int) round($filledMinutesHoje))
            .' / '.$this->ocupacaoFormatDurationLabel($capacityMinutesHoje);
        $horasOcupacaoSemana = $this->ocupacaoFormatDurationLabel((int) round($filledMinutesWeek))
            .' / '.$this->ocupacaoFormatDurationLabel($capacityMinutesWeek);
        $horasOcupacaoMes = $this->ocupacaoFormatDurationLabel((int) round($filledMinutesMonth))
            .' / '.$this->ocupacaoFormatDurationLabel($capacityMinutesMonth);

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
            ->whereHas('event', function ($q) use ($prestadorUserIds, $startOfMonthUtc, $endOfMonthUtc, $storeIds) {
                $q->whereIn('store_id', $storeIds)
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
            ->whereHas('event', function ($q) use ($prestadorUserIds, $startOfMonthUtc, $endOfMonthUtc, $storeIds) {
                $q->whereIn('store_id', $storeIds)
                    ->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
                    ->whereBetween('start_at', [$startOfMonthUtc, $endOfMonthUtc])
                    ->whereIn('user_id', $prestadorUserIds);
            })
            ->join('services', 'calendar_event_services.service_id', '=', 'services.id')
            ->selectRaw('services.id, services.name as service_name, count(*) as qtd, avg(calendar_event_services.duration) as media_min')
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('qtd')
            ->limit(10)
            ->get();

        $ocupacaoPorTecnico = $this->ocupacaoPorTecnico($startOfMonth, $endOfMonth, $store, $prestadorAgents, $prestadorUserIds);
        $slotsMaisVazios = $this->ocupacaoSlotsMaisVazios($startOfMonth, $endOfMonth, $store, $prestadorUserIds, $numTecnicos);

        $totalMarcacoesMes = (clone $marcacoesBase)->count();
        $horasTrabalhoMes = $totalMarcacoesMes > 0 && $duracaoMediaGeral
            ? round(($totalMarcacoesMes * $duracaoMediaGeral) / 60, 1)
            : 0;

        $avgUsefulHoursPerOpenDayPerTech = $this->ocupacaoAvgUsefulHoursPerOpenDay($prestadorAgents, $storeSchedule);

        $shortWeekdayLabels = [
            'mon' => 'Seg',
            'tue' => 'Ter',
            'wed' => 'Qua',
            'thu' => 'Qui',
            'fri' => 'Sex',
            'sat' => 'Sáb',
            'sun' => 'Dom',
        ];
        $storeOpenDaysLabel = collect($storeSchedule)
            ->filter(fn ($day) => (bool) ($day['enabled'] ?? false))
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

        return view('dashboard.ocupacao', array_merge(compact(
            'taxaOcupacaoHoje',
            'taxaOcupacaoMes',
            'taxaOcupacaoSemana',
            'horasOcupacaoHoje',
            'horasOcupacaoSemana',
            'horasOcupacaoMes',
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
            'avgUsefulHoursPerOpenDayPerTech',
            'ocupacaoTimezoneLabel',
            'glueSuggestionsData',
            'gluePeriod',
        ), $this->dashStoreViewData($filter)));
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
        $earliest = $this->calendarEventsQuery()
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->min('start_at');

        if ($earliest === null) {
            return (int) StoreBusinessTime::nowForStore($this->primaryDashboardStoreId())->year;
        }

        return max(2000, (int) Carbon::parse($earliest)->timezone($store->bookingTimezone())->year);
    }

    private function ocupacaoPrestadorUserIds(): Collection
    {
        return $this->ocupacaoPrestadorAgents()
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values();
    }

    /**
     * @return Collection<int, Agent>
     */
    private function ocupacaoPrestadorAgents(): Collection
    {
        return Agent::query()
            ->whereIn('store_id', $this->dashboardStoreIds())
            ->where('status', Agent::STATUS_ACTIVE)
            ->whereHas('user', fn ($q) => $q->whereIn('role', User::serviceProviderRoles()))
            ->with('store')
            ->get(['id', 'user_id', 'store_id', 'name', 'weekly_schedule']);
    }

    /**
     * Capacidade em minutos no intervalo: horário de cada técnica ∩ loja.
     * Com $subtractLunch, desconta a pausa média de almoço por técnica/dia aberto.
     *
     * @param  Collection<int, Agent>  $agents
     * @param  array<string, mixed>  $storeSchedule
     */
    private function ocupacaoCapacityMinutesForAgents(
        Carbon $start,
        Carbon $end,
        Collection $agents,
        array $storeSchedule,
        bool $subtractLunch = false,
    ): int {
        if ($agents->isEmpty()) {
            return 0;
        }

        $total = 0;
        $d = $start->copy()->startOfDay();
        while ($d->lte($end)) {
            $dayKey = WeeklyScheduleWindow::carbonIsoToWeekdayKey($d->dayOfWeekIso);
            foreach ($agents as $agent) {
                $window = WeeklyScheduleWindow::resolveMinutesWindow(
                    $agent->weekly_schedule,
                    $dayKey,
                    $storeSchedule
                );
                if ($window === null) {
                    continue;
                }
                $minutes = max(0, (int) $window[1] - (int) $window[0]);
                if ($subtractLunch && $minutes > 0) {
                    $minutes = max(0, $minutes - self::OCUPACAO_LUNCH_BREAK_MINUTES);
                }
                $total += $minutes;
            }
            $d->addDay();
        }

        return $total;
    }

    /**
     * Média de horas úteis/dia aberto (horário técnica ∩ loja − almoço).
     *
     * @param  Collection<int, Agent>  $agents
     * @param  array<string, mixed>  $storeSchedule
     */
    private function ocupacaoAvgUsefulHoursPerOpenDay(Collection $agents, array $storeSchedule): float
    {
        $samples = [];
        foreach ($agents as $agent) {
            foreach (Agent::WEEKDAY_KEYS as $dayKey) {
                $window = WeeklyScheduleWindow::resolveMinutesWindow(
                    $agent->weekly_schedule,
                    $dayKey,
                    $storeSchedule
                );
                if ($window === null) {
                    continue;
                }
                $minutes = max(0, (int) $window[1] - (int) $window[0]);
                if ($minutes <= 0) {
                    continue;
                }
                $samples[] = max(0, $minutes - self::OCUPACAO_LUNCH_BREAK_MINUTES);
            }
        }

        if ($samples === []) {
            return 0.0;
        }

        return round((array_sum($samples) / count($samples)) / 60, 1);
    }

    private function ocupacaoIsoWeekdayToScheduleKey(int $iso): string
    {
        return WeeklyScheduleWindow::carbonIsoToWeekdayKey($iso);
    }

    private function ocupacaoMarcacoesBase(Collection $prestadorUserIds)
    {
        $query = $this->calendarEventsQuery()
            ->where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);

        if ($prestadorUserIds->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('user_id', $prestadorUserIds);
    }

    private function ocupacaoFilledMinutesBetween(Carbon $startLocal, Carbon $endLocal, Collection $prestadorUserIds): float
    {
        if ($prestadorUserIds->isEmpty()) {
            return 0;
        }

        [$startUtc, $endUtc] = $this->ocupacaoUtcQueryBounds($startLocal, $endLocal);

        $eventIds = $this->calendarEventsQuery()->where('event_type', CalendarEvent::TYPE_MARCACAO)
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

        return (float) ((int) $totalMinutes + (int) $extraMinutes);
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

        $events = $this->calendarEventsQuery()
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
     * @param  Collection<int, Agent>  $prestadorAgents
     * @param  Collection<int, int>  $prestadorUserIds
     */
    private function ocupacaoPorTecnico(
        Carbon $startLocal,
        Carbon $endLocal,
        Store $store,
        Collection $prestadorAgents,
        Collection $prestadorUserIds,
    ): Collection {
        if ($prestadorAgents->isEmpty()) {
            return collect();
        }

        [$startUtc, $endUtc] = $this->ocupacaoUtcQueryBounds($startLocal, $endLocal);
        $storeSchedule = $store->normalizedWeeklySchedule();
        $agentsByUserId = $prestadorAgents->keyBy(fn (Agent $a): int => (int) $a->user_id);

        $filledByUser = CalendarEventService::query()
            ->join('calendar_events', 'calendar_event_services.calendar_event_id', '=', 'calendar_events.id')
            ->whereIn('calendar_events.store_id', $this->dashboardStoreIds())
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereBetween('calendar_events.start_at', [$startUtc, $endUtc])
            ->whereIn('calendar_events.user_id', $prestadorUserIds)
            ->selectRaw('calendar_events.user_id, sum(calendar_event_services.duration) as total_min')
            ->groupBy('calendar_events.user_id')
            ->get()
            ->keyBy('user_id');

        $users = User::whereIn('id', $prestadorUserIds)->get()->keyBy('id');

        return $prestadorUserIds->map(function ($userId) use ($filledByUser, $agentsByUserId, $storeSchedule, $startLocal, $endLocal, $users) {
            $userId = (int) $userId;
            $agent = $agentsByUserId->get($userId);
            $agentSchedule = $agent instanceof Agent
                ? ($agent->store?->normalizedWeeklySchedule() ?? $storeSchedule)
                : $storeSchedule;
            $capacityMinutes = $agent instanceof Agent
                ? $this->ocupacaoCapacityMinutesForAgents(
                    $startLocal,
                    $endLocal,
                    collect([$agent]),
                    $agentSchedule,
                    true
                )
                : 0;
            $row = $filledByUser->get($userId);
            $totalMin = $row ? (int) $row->total_min : 0;
            $taxa = $capacityMinutes > 0
                ? round(min(100, ($totalMin / $capacityMinutes) * 100), 1)
                : 0;

            return (object) [
                'user_id' => $userId,
                'name' => $agent?->name ?: ($users->get($userId)?->name ?? 'N/A'),
                'filled_hours' => $this->ocupacaoFormatDurationLabel($totalMin),
                'total_hours' => $this->ocupacaoFormatDurationLabel($capacityMinutes),
                'filled_minutes' => $totalMin,
                'total_minutes' => $capacityMinutes,
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

    /**
     * @return array{store_id: ?int, scope: string, store_ids: list<int>}
     */
    private function resolveDashboardStoreContext(Request $request): array
    {
        $user = auth()->user();
        $filter = StoreContextPreference::resolveFilter($user, $request, true);
        if ($filter['store_id'] !== null) {
            StoreContextPreference::persist($request, $filter['store_id']);
            $store = Store::query()->find($filter['store_id']);
            if ($store) {
                app(CurrentStore::class)->set($store);
            }
        } else {
            StoreContextPreference::persistAll($request);
        }
        $this->dashboardStoreIds = $filter['store_ids'];

        return $filter;
    }

    /**
     * @return list<int>
     */
    private function dashboardStoreIds(): array
    {
        return $this->dashboardStoreIds ?? [(int) current_store_id()];
    }

    private function primaryDashboardStoreId(): int
    {
        return (int) ($this->dashboardStoreIds()[0] ?? current_store_id());
    }

    /**
     * @param  list<int>|null  $storeIds
     */
    private function calendarEventsQuery(?array $storeIds = null): Builder
    {
        return CalendarEvent::query()->whereIn('store_id', $storeIds ?? $this->dashboardStoreIds());
    }

    /**
     * @param  array{store_id: ?int, scope: string, store_ids: list<int>}  $filter
     * @return array{dashStoreFilter: array, dashStoreSelected: int|string}
     */
    private function dashStoreViewData(array $filter): array
    {
        return [
            'dashStoreFilter' => $filter,
            'dashStoreSelected' => $filter['store_id'] ?? StoreContextPreference::SCOPE_ALL,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumoOpsKpisAcrossStores(PrestadorDashboardService $prestadorDashboard, ?User $user): array
    {
        $storeIds = $this->dashboardStoreIds();
        $sumKeys = ['marcacoesHoje', 'faltasHoje', 'marcacoesEstaSemana', 'marcacoesEsteMes'];
        $merged = null;

        foreach ($storeIds as $sid) {
            $part = $prestadorDashboard->buildForStore((int) $sid, $user);
            if ($merged === null) {
                $merged = $part;

                continue;
            }
            foreach ($sumKeys as $key) {
                $merged[$key] = (int) ($merged[$key] ?? 0) + (int) ($part[$key] ?? 0);
            }
        }

        return $merged ?? $prestadorDashboard->buildForStore($this->primaryDashboardStoreId(), $user);
    }

    /**
     * @param  Collection<int, Agent>  $agents
     */
    private function ocupacaoCapacityAcrossStores(
        Carbon $start,
        Carbon $end,
        Collection $agents,
        bool $subtractLunch = false,
    ): int {
        if ($agents->isEmpty()) {
            return 0;
        }

        $total = 0;
        $byStore = $agents->groupBy(fn (Agent $a): int => (int) $a->store_id);
        foreach ($byStore as $sid => $storeAgents) {
            $store = $storeAgents->first()?->store
                ?? Store::query()->find((int) $sid);
            if (! $store instanceof Store) {
                continue;
            }
            $total += $this->ocupacaoCapacityMinutesForAgents(
                $start,
                $end,
                $storeAgents,
                $store->normalizedWeeklySchedule(),
                $subtractLunch
            );
        }

        return $total;
    }

    /**
     * @param  list<int>  $storeIds
     * @return array<string, mixed>
     */
    private function mergeFinancialDashboardBuilds(array $storeIds, int $year, int $month): array
    {
        $builds = [];
        foreach ($storeIds as $sid) {
            $store = Store::query()->find((int) $sid);
            if ($store instanceof Store) {
                app(CurrentStore::class)->set($store);
            }
            $builds[] = $this->financialDashboard->build((int) $sid, $year, $month);
        }

        $primaryStore = Store::query()->find($this->primaryDashboardStoreId());
        if ($primaryStore instanceof Store) {
            app(CurrentStore::class)->set($primaryStore);
        }

        if ($builds === []) {
            return $this->financialDashboard->build($this->primaryDashboardStoreId(), $year, $month);
        }

        $merged = $builds[0];
        $sumKpiKeys = [
            'receita', 'receita_anterior', 'receita_semana', 'receita_semana_anterior',
            'num_faturas', 'clientes_unicos', 'dias_com_vendas', 'taxas', 'descontos',
            'comissoes_estimadas', 'margem_estimada',
        ];

        for ($i = 1; $i < count($builds); $i++) {
            $b = $builds[$i];
            foreach ($sumKpiKeys as $key) {
                $merged['kpis'][$key] = (float) ($merged['kpis'][$key] ?? 0) + (float) ($b['kpis'][$key] ?? 0);
            }

            $seriesA = $merged['receita_diaria'] ?? null;
            $seriesB = $b['receita_diaria'] ?? null;
            if (is_array($seriesA) && is_array($seriesB) && count($seriesA) === count($seriesB)) {
                foreach ($seriesA as $idx => $row) {
                    $merged['receita_diaria'][$idx]['receita'] = round(
                        (float) ($row['receita'] ?? 0) + (float) ($seriesB[$idx]['receita'] ?? 0),
                        2
                    );
                }
            }
        }

        $k = &$merged['kpis'];
        $k['receita'] = round((float) $k['receita'], 2);
        $k['receita_anterior'] = round((float) $k['receita_anterior'], 2);
        $k['receita_semana'] = round((float) $k['receita_semana'], 2);
        $k['receita_semana_anterior'] = round((float) $k['receita_semana_anterior'], 2);
        $k['taxas'] = round((float) $k['taxas'], 2);
        $k['descontos'] = round((float) $k['descontos'], 2);
        $k['comissoes_estimadas'] = round((float) $k['comissoes_estimadas'], 2);
        $k['margem_estimada'] = round((float) $k['receita'] - (float) $k['comissoes_estimadas'], 2);
        $k['ticket_medio'] = ((int) $k['num_faturas'] > 0)
            ? round((float) $k['receita'] / (int) $k['num_faturas'], 2)
            : null;
        $k['receita_media_dia'] = ((int) $k['dias_com_vendas'] > 0)
            ? round((float) $k['receita'] / (int) $k['dias_com_vendas'], 2)
            : null;
        $k['variacao_receita'] = ((float) $k['receita_anterior'] > 0)
            ? round((((float) $k['receita'] - (float) $k['receita_anterior']) / (float) $k['receita_anterior']) * 100, 1)
            : (((float) $k['receita'] > 0) ? 100.0 : 0.0);
        $k['variacao_receita_semana'] = ((float) $k['receita_semana_anterior'] > 0)
            ? round((((float) $k['receita_semana'] - (float) $k['receita_semana_anterior']) / (float) $k['receita_semana_anterior']) * 100, 1)
            : (((float) $k['receita_semana'] > 0) ? 100.0 : 0.0);

        // Rankings / destaques: loja primária (primeira) — agregação multi-loja não ordena bem.
        return $merged;
    }
}
