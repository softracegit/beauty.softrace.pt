<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventService;
use App\Models\CalendarEventServiceExtra;
use App\Models\Service;
use App\Models\Sale;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Dashboard principal - Marcações e Serviços (estilo Fresha)
     */
    public function index()
    {
        $today = Carbon::today();
        $startOfWeek = $today->copy()->startOfWeek();
        $endOfWeek = $today->copy()->endOfWeek();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();

        $marcacoesBase = CalendarEvent::where('event_type', CalendarEvent::TYPE_MARCACAO)
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

        $totalClientes = Client::count();
        $totalTecnicos = Agent::where('status', Agent::STATUS_ACTIVE)->count();

        $proximasMarcacoes = CalendarEvent::where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->where('start_at', '>=', $today)
            ->with(['client', 'user', 'eventServices'])
            ->orderBy('start_at')
            ->limit(8)
            ->get();

        $marcacoesRecentes = CalendarEvent::where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->with(['client', 'user', 'eventServices'])
            ->orderBy('start_at', 'desc')
            ->limit(5)
            ->get();

        // Marcações por serviço (independente de venda/faturação)
        $porServico = CalendarEventService::query()
            ->join('calendar_events', 'calendar_event_services.calendar_event_id', '=', 'calendar_events.id')
            ->join('services', 'calendar_event_services.service_id', '=', 'services.id')
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->groupBy('services.id', 'services.name')
            ->selectRaw('services.name as service_name, count(*) as total, sum(coalesce(calendar_event_services.price, services.price)) as receita')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $porTecnico = CalendarEvent::where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->load('user');

        // Receita por técnico: apenas vendas pagas de marcações concluídas
        $receitaPorTecnico = Sale::query()
            ->join('calendar_events', 'sales.calendar_event_id', '=', 'calendar_events.id')
            ->where('sales.status', Sale::STATUS_PAGO)
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', CalendarEvent::STATUS_COMPLETO)
            ->groupBy('calendar_events.user_id')
            ->selectRaw('calendar_events.user_id, sum(sales.total) as receita')
            ->get()
            ->keyBy('user_id');

        $porEstado = CalendarEvent::where('event_type', CalendarEvent::TYPE_MARCACAO)
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
     * Receita total entre duas datas baseada em vendas pagas de marcações concluídas.
     */
    private function receitaMarcacoesEntre(Carbon $start, Carbon $end): float
    {
        return (float) Sale::query()
            ->where('status', Sale::STATUS_PAGO)
            ->whereHas('calendarEvent', function ($q) use ($start, $end) {
                $q->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', CalendarEvent::STATUS_COMPLETO)
                    ->whereBetween('start_at', [$start, $end]);
            })
            ->sum('total');
    }

    /**
     * Dashboard de Imóveis
     */
    public function imoveis()
    {
        return view('dashboard.imoveis');
    }

    /**
     * Dashboard de Negócios
     */
    public function negocios()
    {
        return view('dashboard.negocios');
    }

    /**
     * Dashboard de Clientes (métricas baseadas em marcações de serviços)
     */
    public function clientes()
    {
        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();

        $marcacoesBase = CalendarEvent::where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereNotNull('client_id');

        $totalClientes = Client::count();
        $totalClientesComMarcacao = (clone $marcacoesBase)->distinct('client_id')->count('client_id');

        $clientesEsteMes = Client::whereMonth('created_at', $today->month)
            ->whereYear('created_at', $today->year)
            ->count();

        $marcacoesEsteMes = (clone $marcacoesBase)
            ->whereBetween('start_at', [$startOfMonth, $endOfMonth])
            ->get();

        $primeiraMarcacaoPorCliente = CalendarEvent::where('event_type', CalendarEvent::TYPE_MARCACAO)
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
        $clientesComDuasOuMais = CalendarEvent::where('event_type', CalendarEvent::TYPE_MARCACAO)
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
            ->whereIn('id', $clientesComUmaOuMais)
            ->withCount(['calendarEvents as marcacoes_count' => function ($q) {
                $q->where('event_type', CalendarEvent::TYPE_MARCACAO)
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
            ->whereIn('id', $receitaPorCliente->keys())
            ->withCount(['calendarEvents as marcacoes_count' => function ($q) {
                $q->where('event_type', CalendarEvent::TYPE_MARCACAO)
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
                'count' => Client::whereMonth('created_at', $date->month)->whereYear('created_at', $date->year)->count(),
            ];
        }

        $recentClients = Client::orderBy('created_at', 'desc')->limit(10)->get();

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
            ->where('status', Sale::STATUS_PAGO)
            ->whereNotNull('client_id')
            ->whereHas('calendarEvent', function ($q) {
                $q->where('event_type', CalendarEvent::TYPE_MARCACAO)
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
        $clientIds = CalendarEvent::where('event_type', CalendarEvent::TYPE_MARCACAO)
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
            $datas = CalendarEvent::where('client_id', $clientId)
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
     * Regras para cálculo de slots (ocupação): duração do slot em minutos, hora início/fim, dias úteis (1=Seg a 6=Sáb).
     */
    private const SLOT_DURATION_MINUTES = 60;
    private const OCCUPANCY_HOUR_START = 9;
    private const OCCUPANCY_HOUR_END = 19;
    private const OCCUPANCY_WORK_DAYS = [1, 2, 3, 4, 5, 6]; // Seg a Sáb

    /**
     * Dashboard de Ocupação (taxa de ocupação, picos, dias, duração média).
     */
    public function ocupacao()
    {
        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();
        $startOfWeek = $today->copy()->startOfWeek();
        $endOfWeek = $today->copy()->endOfWeek();

        $numTecnicos = Agent::where('status', Agent::STATUS_ACTIVE)->count();
        $slotsPerDayPerTech = ((self::OCCUPANCY_HOUR_END - self::OCCUPANCY_HOUR_START) * 60) / self::SLOT_DURATION_MINUTES;

        $workDaysThisMonth = $this->countWorkDaysBetween($startOfMonth, $endOfMonth);
        $totalSlotsMonth = $workDaysThisMonth * $slotsPerDayPerTech * max(1, $numTecnicos);
        $workDaysThisWeek = $this->countWorkDaysBetween($startOfWeek, $endOfWeek);
        $totalSlotsWeek = $workDaysThisWeek * $slotsPerDayPerTech * max(1, $numTecnicos);

        $filledSlotsMonth = $this->filledSlotsBetween($startOfMonth, $endOfMonth);
        $filledSlotsWeek = $this->filledSlotsBetween($startOfWeek, $endOfWeek);

        $taxaOcupacaoMes = $totalSlotsMonth > 0 ? round(min(100, ($filledSlotsMonth / $totalSlotsMonth) * 100), 1) : 0;
        $taxaOcupacaoSemana = $totalSlotsWeek > 0 ? round(min(100, ($filledSlotsWeek / $totalSlotsWeek) * 100), 1) : 0;

        $marcacoesBase = CalendarEvent::where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);

        $porHora = [];
        for ($h = 0; $h < 24; $h++) {
            $porHora[$h] = (clone $marcacoesBase)->whereRaw('HOUR(start_at) = ?', [$h])->count();
        }

        $porDiaSemana = [];
        $diasNomes = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        foreach ([0, 1, 2, 3, 4, 5, 6] as $d) {
            $porDiaSemana[$d] = [
                'nome' => $diasNomes[$d],
                'total' => (clone $marcacoesBase)->whereRaw('DAYOFWEEK(start_at) = ?', [$d + 1])->count(),
            ];
        }

        $duracaoMediaGeral = CalendarEventService::query()
            ->whereHas('event', function ($q) {
                $q->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);
            })
            ->selectRaw('calendar_event_id, sum(duration) as total_min')
            ->groupBy('calendar_event_id')
            ->get()
            ->avg('total_min');

        $duracaoMediaPorServico = CalendarEventService::query()
            ->whereHas('event', function ($q) {
                $q->where('event_type', CalendarEvent::TYPE_MARCACAO)
                    ->where('status', '!=', CalendarEvent::STATUS_CANCELADO);
            })
            ->join('services', 'calendar_event_services.service_id', '=', 'services.id')
            ->selectRaw('services.id, services.name as service_name, count(*) as qtd, avg(calendar_event_services.duration) as media_min')
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('qtd')
            ->limit(10)
            ->get();

        $ocupacaoPorTecnico = $this->ocupacaoPorTecnico($startOfMonth, $endOfMonth, $slotsPerDayPerTech);

        $totalMarcacoesMes = (clone $marcacoesBase)->whereBetween('start_at', [$startOfMonth, $endOfMonth])->count();
        $horasTrabalhoMes = $totalMarcacoesMes > 0 && $duracaoMediaGeral
            ? round(($totalMarcacoesMes * $duracaoMediaGeral) / 60, 1)
            : 0;

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
            'totalMarcacoesMes',
            'horasTrabalhoMes'
        ));
    }

    private function countWorkDaysBetween(Carbon $start, Carbon $end): int
    {
        $n = 0;
        $d = $start->copy();
        while ($d->lte($end)) {
            $iso = $d->dayOfWeekIso;
            if (in_array($iso, self::OCCUPANCY_WORK_DAYS, true)) {
                $n++;
            }
            $d->addDay();
        }
        return $n;
    }

    private function filledSlotsBetween(Carbon $start, Carbon $end): float
    {
        $eventIds = CalendarEvent::where('event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereBetween('start_at', [$start, $end])
            ->pluck('id');

        $totalMinutes = CalendarEventService::whereIn('calendar_event_id', $eventIds)->sum('duration');
        $cesIds = CalendarEventService::whereIn('calendar_event_id', $eventIds)->pluck('id');
        $extraMinutes = CalendarEventServiceExtra::whereIn('calendar_event_service_id', $cesIds)->sum('duration');

        $total = (int) $totalMinutes + (int) $extraMinutes;
        return ceil($total / self::SLOT_DURATION_MINUTES);
    }

    private function ocupacaoPorTecnico(Carbon $start, Carbon $end, float $slotsPerDayPerTech): \Illuminate\Support\Collection
    {
        $workDays = $this->countWorkDaysBetween($start, $end);
        $totalSlotsPerTech = $workDays * $slotsPerDayPerTech;

        $filledByUser = CalendarEventService::query()
            ->join('calendar_events', 'calendar_event_services.calendar_event_id', '=', 'calendar_events.id')
            ->where('calendar_events.event_type', CalendarEvent::TYPE_MARCACAO)
            ->where('calendar_events.status', '!=', CalendarEvent::STATUS_CANCELADO)
            ->whereBetween('calendar_events.start_at', [$start, $end])
            ->whereNotNull('calendar_events.user_id')
            ->selectRaw('calendar_events.user_id, sum(calendar_event_services.duration) as total_min')
            ->groupBy('calendar_events.user_id')
            ->get()
            ->keyBy('user_id');

        $agentUserIds = Agent::where('status', Agent::STATUS_ACTIVE)->pluck('user_id')->filter();
        $users = \App\Models\User::whereIn('id', $agentUserIds)->get()->keyBy('id');

        return $agentUserIds->map(function ($userId) use ($filledByUser, $totalSlotsPerTech, $users) {
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
            'under-maintenance'
        ];

        if (in_array($page, $allowedPages) && view()->exists($page)) {
            return view($page);
        }

        abort(404);
    }
}
