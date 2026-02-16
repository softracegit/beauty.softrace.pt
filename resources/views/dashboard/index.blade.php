@extends('partials.layouts.main')
@section('title', 'Dashboard | Imobiliária')
@section('page-heading-title', 'Resumo Geral')
@section('page-heading-sub-title', 'Dashboard')
@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@php
    $totalClientes = \App\Models\Client::count();
    $activeClientes = \App\Models\Client::where('status', \App\Models\Client::STATUS_ACTIVE)->count();
    $totalAgentes = \App\Models\Agent::where('status', \App\Models\Agent::STATUS_ACTIVE)->count();
    $totalLeads = \App\Models\Lead::count();
    $activeLeads = \App\Models\Lead::where('status', '!=', 'arquivada')->count();
    $totalOportunidades = \App\Models\Opportunity::count();
    $activeOportunidades = \App\Models\Opportunity::where('status', '!=', 'arquivada')->where('status', '!=', 'ganha')->count();
    $totalImoveis = \App\Models\Property::where('status', '!=', \App\Models\Property::STATUS_INATIVO)->count();
    $totalNegocios = \App\Models\Deal::count();
    
    // Calcular receita total dos negócios fechados
    $totalReceita = \App\Models\Deal::sum('final_price') ?? 0;
    
    // Leads do mês atual
    $leadsEsteMes = \App\Models\Lead::whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->count();
    $leadsMesAnterior = \App\Models\Lead::whereMonth('created_at', now()->subMonth()->month)
        ->whereYear('created_at', now()->subMonth()->year)
        ->count();
    $variacaoLeads = $leadsMesAnterior > 0 ? (($leadsEsteMes - $leadsMesAnterior) / $leadsMesAnterior) * 100 : 0;
    
    // Oportunidades do mês atual
    $oppEsteMes = \App\Models\Opportunity::whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->count();
    $oppMesAnterior = \App\Models\Opportunity::whereMonth('created_at', now()->subMonth()->month)
        ->whereYear('created_at', now()->subMonth()->year)
        ->count();
    $variacaoOpp = $oppMesAnterior > 0 ? (($oppEsteMes - $oppMesAnterior) / $oppMesAnterior) * 100 : 0;
    
    // Negócios do mês atual
    $negociosEsteMes = \App\Models\Deal::whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->count();
    $negociosMesAnterior = \App\Models\Deal::whereMonth('created_at', now()->subMonth()->month)
        ->whereYear('created_at', now()->subMonth()->year)
        ->count();
    $variacaoNegocios = $negociosMesAnterior > 0 ? (($negociosEsteMes - $negociosMesAnterior) / $negociosMesAnterior) * 100 : 0;
@endphp

<!-- Welcome Banner -->
<div class="dash-welcome mb-4">
    <div class="dash-welcome-content">
        <h2 class="dash-welcome-title">Bem-vindo de volta, {{ auth()->user()->name }}</h2>
        <p class="dash-welcome-text">Aqui está o que está a acontecer com o seu negócio hoje.</p>
    </div>
    <div class="dash-welcome-actions">
        <div class="dash-date">
            <i class="bi bi-calendar3"></i>
            <span id="dashDate"></span>
        </div>
        <div class="dash-date">
            <i class="bi bi-clock"></i>
            <span id="dashTime"></span>
        </div>
    </div>
</div>

<!-- KPI Strip -->
<div class="dash-kpi-strip mb-4">
    <div class="dash-kpi">
        <div class="dash-kpi-icon primary">
            <i class="ph-duotone ph-currency-eur"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ number_format($totalReceita, 0, ',', '.') }}€</div>
            <div class="dash-kpi-label">Receita Total</div>
        </div>
        @if($variacaoNegocios > 0)
        <div class="dash-kpi-trend positive">
            <i class="bi bi-trending-up"></i>
            <span>+{{ number_format($variacaoNegocios, 1) }}%</span>
        </div>
        @elseif($variacaoNegocios < 0)
        <div class="dash-kpi-trend negative">
            <i class="bi bi-trending-down"></i>
            <span>{{ number_format($variacaoNegocios, 1) }}%</span>
        </div>
        @endif
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon success">
            <i class="ph-duotone ph-tray"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $totalLeads }}</div>
            <div class="dash-kpi-label">Total Leads</div>
        </div>
        @if($variacaoLeads > 0)
        <div class="dash-kpi-trend positive">
            <i class="bi bi-trending-up"></i>
            <span>+{{ number_format($variacaoLeads, 1) }}%</span>
        </div>
        @elseif($variacaoLeads < 0)
        <div class="dash-kpi-trend negative">
            <i class="bi bi-trending-down"></i>
            <span>{{ number_format($variacaoLeads, 1) }}%</span>
        </div>
        @endif
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon warning">
            <i class="ph-duotone ph-users-three"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $totalClientes }}</div>
            <div class="dash-kpi-label">Clientes</div>
        </div>
        @if($activeClientes > 0)
        <div class="dash-kpi-trend positive">
            <i class="bi bi-trending-up"></i>
            <span>{{ $activeClientes }} ativos</span>
        </div>
        @endif
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon danger">
            <i class="ph-duotone ph-briefcase"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $totalOportunidades }}</div>
            <div class="dash-kpi-label">Oportunidades</div>
        </div>
        @if($variacaoOpp > 0)
        <div class="dash-kpi-trend positive">
            <i class="bi bi-trending-up"></i>
            <span>+{{ number_format($variacaoOpp, 1) }}%</span>
        </div>
        @elseif($variacaoOpp < 0)
        <div class="dash-kpi-trend negative">
            <i class="bi bi-trending-down"></i>
            <span>{{ number_format($variacaoOpp, 1) }}%</span>
        </div>
        @endif
    </div>
</div>

<!-- Main Grid: Charts Row -->
<div class="dash-grid dash-grid-charts mb-4">
    <!-- Revenue Chart (2/3 width) -->
    <div class="card dash-chart-main">
        <div class="card-header">
            <h5 class="card-title">Visão Geral de Negócios</h5>
            <div class="card-actions">
                <div class="dash-chart-tabs">
                    <button class="dash-chart-tab active" data-period="monthly">Mensal</button>
                    <button class="dash-chart-tab" data-period="weekly">Semanal</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-container" id="revenueBarChart"></div>
        </div>
    </div>

    <!-- Status Distribution (1/3 width) -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Distribuição de Leads</h5>
        </div>
        <div class="card-body">
            @php
                $leadsPorStatus = \App\Models\Lead::selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status')
                    ->toArray();
                $totalLeadsChart = array_sum($leadsPorStatus);
            @endphp
            <div class="chart-container" id="trafficDonutChart"></div>
            <div class="dash-traffic-legend">
                @foreach(\App\Models\Lead::statuses() as $status => $label)
                    @if(isset($leadsPorStatus[$status]) && $leadsPorStatus[$status] > 0)
                        @php
                            $percent = $totalLeadsChart > 0 ? round(($leadsPorStatus[$status] / $totalLeadsChart) * 100) : 0;
                            $colors = ['primary', 'success', 'warning', 'info', 'danger'];
                            $colorIndex = array_search($status, array_keys(\App\Models\Lead::statuses())) % count($colors);
                            $colorClass = $colors[$colorIndex] ?? 'primary';
                        @endphp
                        <div class="dash-traffic-item">
                            <span class="dash-traffic-dot" style="--dot-color: var(--{{ $colorClass }}-color)"></span>
                            <span class="dash-traffic-name">{{ $label }}</span>
                            <span class="dash-traffic-val">{{ $percent }}%</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>

<!-- Main Grid: Table + Activity Row -->
<div class="dash-grid dash-grid-content mb-4">
    <!-- Recent Leads Table (2/3) -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Leads Recentes</h5>
            <div class="card-actions">
                <a href="{{ route('leads.index') }}" class="btn btn-sm btn-outline-primary">Ver Todas</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table dash-table">
                    <thead>
                        <tr>
                            <th>Lead</th>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Prioridade</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $recentLeads = \App\Models\Lead::orderBy('created_at', 'desc')->limit(5)->get();
                        @endphp
                        @forelse($recentLeads as $lead)
                            <tr>
                                <td><a href="{{ route('leads.show', $lead) }}" class="fw-medium">{{ $lead->lead_id }}</a></td>
                                <td>
                                    <div class="dash-customer">
                                        <span>{{ $lead->name }}</span>
                                    </div>
                                </td>
                                <td>{{ \App\Models\Lead::types()[$lead->type] ?? $lead->type }}</td>
                                <td>
                                    <span class="badge bg-{{ $lead->priority_color }}-subtle text-{{ $lead->priority_color }}">
                                        {{ \App\Models\Lead::priorities()[$lead->priority] ?? $lead->priority }}
                                    </span>
                                </td>
                                <td><span class="dash-status {{ $lead->status_color }}">{{ \App\Models\Lead::statuses()[$lead->status] ?? $lead->status }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Nenhuma lead recente.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Activity Timeline (1/3) -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Atividade</h5>
            <div class="card-actions">
                <a href="{{ route('agenda.index') }}" class="btn-icon" title="Ver todas"><i class="bi bi-arrow-up-right"></i></a>
            </div>
        </div>
        <div class="card-body">
            <div class="dash-timeline">
                @php
                    $recentNotes = \App\Models\Note::orderBy('created_at', 'desc')->limit(6)->get();
                @endphp
                @forelse($recentNotes as $note)
                    @php
                        $colorMap = [
                            'geral' => 'info',
                            'telefonema' => 'primary',
                            'email' => 'success',
                            'reuniao' => 'warning',
                            'visita' => 'danger',
                        ];
                        $color = $colorMap[$note->type] ?? 'info';
                    @endphp
                    <div class="dash-timeline-item">
                        <div class="dash-timeline-marker {{ $color }}"></div>
                        <div class="dash-timeline-body">
                            <div class="dash-timeline-title">
                                {{ $note->user ? $note->user->name : 'Sistema' }} - 
                                {{ \App\Models\Note::types()[$note->type] ?? $note->type }}
                            </div>
                            <div class="dash-timeline-time">{{ $note->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">Nenhuma atividade recente.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Middle Row: Transactions + Region + Devices -->
<div class="dash-grid dash-grid-bottom mb-4">
    <!-- Recent Opportunities -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Oportunidades Recentes</h5>
            <div class="card-actions">
                <a href="{{ route('opportunities.index') }}" class="btn-icon" title="Ver todas"><i class="bi bi-arrow-up-right"></i></a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="transaction-list" style="padding: var(--spacing-md) var(--spacing-lg);">
                @php
                    $recentOpps = \App\Models\Opportunity::orderBy('created_at', 'desc')->limit(4)->get();
                @endphp
                @forelse($recentOpps as $opp)
                    <div class="transaction-item">
                        <div class="transaction-icon {{ $opp->status === 'ganha' ? 'success' : ($opp->status === 'perdida' ? 'danger' : 'info') }}">
                            <i class="ph-duotone ph-briefcase"></i>
                        </div>
                        <div class="transaction-details">
                            <div class="transaction-title">
                                <a href="{{ route('opportunities.show', $opp) }}" class="text-body">{{ $opp->title }}</a>
                            </div>
                            <div class="transaction-meta">{{ $opp->client->name }} - {{ $opp->created_at->format('d/m/Y') }}</div>
                        </div>
                        <div class="transaction-amount {{ $opp->status === 'ganha' ? 'positive' : '' }}">
                            {{ $opp->reference }}
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">Nenhuma oportunidade recente.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Top Agents -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Agentes com Mais Negócios</h5>
        </div>
        <div class="card-body">
            <div class="region-list">
                @php
                    $topAgents = \App\Models\Agent::withCount(['dealCommissions' => function($query) {
                        $query->whereHas('deal', function($q) {
                            $q->where('status', \App\Models\Deal::STATUS_FECHADO);
                        });
                    }])
                    ->orderBy('deal_commissions_count', 'desc')
                    ->limit(5)
                    ->get();
                    $maxDeals = $topAgents->max('deal_commissions_count') ?: 1;
                @endphp
                @forelse($topAgents as $agent)
                    @php
                        $percent = $maxDeals > 0 ? round(($agent->deal_commissions_count / $maxDeals) * 100) : 0;
                    @endphp
                    <div class="region-item">
                        <div class="region-info">
                            <span class="region-name">{{ $agent->name }}</span>
                        </div>
                        <div class="region-stats">
                            <div class="progress region-progress">
                                <div class="progress-bar" style="width: {{ $percent }}%"></div>
                            </div>
                            <span class="region-value">{{ $agent->deal_commissions_count }} negócios</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">Nenhum agente com negócios.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Properties by Type -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Imóveis por Tipo</h5>
        </div>
        <div class="card-body">
            <div class="device-list">
                @php
                    $propertiesByType = \App\Models\Property::selectRaw('transaction_type_id, count(*) as total')
                        ->where('status', '!=', \App\Models\Property::STATUS_INATIVO)
                        ->groupBy('transaction_type_id')
                        ->with('transactionType')
                        ->get();
                    $totalProps = $propertiesByType->sum('total');
                @endphp
                @forelse($propertiesByType as $propType)
                    @php
                        $percent = $totalProps > 0 ? round(($propType->total / $totalProps) * 100) : 0;
                        $colors = ['primary', 'success', 'warning', 'info', 'danger'];
                        $colorIndex = $loop->index % count($colors);
                        $colorClass = $colors[$colorIndex] ?? 'primary';
                    @endphp
                    <div class="device-item">
                        <div class="device-icon">
                            <i class="ph-duotone ph-house"></i>
                        </div>
                        <div class="device-info">
                            <div class="device-name">{{ $propType->transactionType->name ?? 'N/A' }}</div>
                            <div class="progress device-progress">
                                <div class="progress-bar bg-{{ $colorClass }}" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                        <div class="device-stats">
                            <div class="device-percent">{{ $percent }}%</div>
                            <div class="device-count">{{ $propType->total }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">Nenhum imóvel disponível.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Bottom Row: 2x2 Grid -->
<div class="dash-grid dash-grid-2x2">
    <!-- Quick Stats -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Estatísticas Rápidas</h5>
        </div>
        <div class="card-body">
            <div class="dash-targets">
                <div class="dash-target">
                    <div class="dash-target-header">
                        <span class="dash-target-label">Taxa de Conversão</span>
                        <span class="dash-target-value">
                            @php
                                $conversionRate = $totalLeads > 0 ? round(($totalOportunidades / $totalLeads) * 100) : 0;
                            @endphp
                            {{ $conversionRate }}%
                        </span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: {{ min($conversionRate, 100) }}%"></div>
                    </div>
                </div>
                <div class="dash-target">
                    <div class="dash-target-header">
                        <span class="dash-target-label">Taxa de Sucesso</span>
                        <span class="dash-target-value">
                            @php
                                $successRate = $totalOportunidades > 0 ? round(($totalNegocios / $totalOportunidades) * 100) : 0;
                            @endphp
                            {{ $successRate }}%
                        </span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" style="width: {{ min($successRate, 100) }}%"></div>
                    </div>
                </div>
                <div class="dash-target">
                    <div class="dash-target-header">
                        <span class="dash-target-label">Leads Ativas</span>
                        <span class="dash-target-value">
                            @php
                                $activeRate = $totalLeads > 0 ? round(($activeLeads / $totalLeads) * 100) : 0;
                            @endphp
                            {{ $activeRate }}%
                        </span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-warning" style="width: {{ min($activeRate, 100) }}%"></div>
                    </div>
                </div>
                <div class="dash-target">
                    <div class="dash-target-header">
                        <span class="dash-target-label">Oportunidades Ativas</span>
                        <span class="dash-target-value">
                            @php
                                $activeOppRate = $totalOportunidades > 0 ? round(($activeOportunidades / $totalOportunidades) * 100) : 0;
                            @endphp
                            {{ $activeOppRate }}%
                        </span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-info" style="width: {{ min($activeOppRate, 100) }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Properties -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Imóveis Mais Visualizados</h5>
        </div>
        <div class="card-body p-0">
            <div class="dash-products">
                @php
                    $topProperties = \App\Models\Property::where('status', '!=', \App\Models\Property::STATUS_INATIVO)
                        ->orderBy('created_at', 'desc')
                        ->limit(4)
                        ->get();
                @endphp
                @forelse($topProperties as $index => $property)
                    <div class="dash-product-item">
                        <div class="dash-product-rank">{{ $index + 1 }}</div>
                        <div class="dash-product-info">
                            <div class="dash-product-name">
                                <a href="{{ route('properties.show', $property) }}" class="text-body">{{ $property->title }}</a>
                            </div>
                            <div class="dash-product-meta">{{ $property->reference }}</div>
                        </div>
                        <div class="dash-product-revenue">{{ $property->formatted_price }}</div>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">Nenhum imóvel disponível.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Recent Clients -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Clientes Recentes</h5>
        </div>
        <div class="card-body p-0">
            <div class="dash-customers-list">
                @php
                    $recentClients = \App\Models\Client::orderBy('created_at', 'desc')->limit(4)->get();
                @endphp
                @forelse($recentClients as $client)
                    @php
                        $avatarNum = $client->id % 9 + 1;
                        $avatarSrc = asset("template/img/avatars/avatar-{$avatarNum}.webp");
                    @endphp
                    <div class="dash-customer-row">
                        <img src="{{ $avatarSrc }}" alt="" class="dash-customer-avatar">
                        <div class="dash-customer-info">
                            <div class="dash-customer-name">
                                <a href="{{ route('clientes.show', $client) }}" class="text-body">{{ $client->name }}</a>
                            </div>
                            <div class="dash-customer-email">{{ $client->email }}</div>
                        </div>
                        <span class="dash-status {{ $client->status === \App\Models\Client::STATUS_ACTIVE ? 'success' : 'warning' }}">
                            {{ \App\Models\Client::statusLabels()[$client->status] ?? $client->status }}
                        </span>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">Nenhum cliente recente.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Ações Rápidas</h5>
            <div class="card-actions">
                <a href="{{ route('agenda.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="ph ph-plus"></i> Ver Agenda
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="task-list">
                <div class="task-item">
                    <div class="task-info">
                        <div class="task-title">
                            <a href="{{ route('leads.create') }}" class="text-body">
                                <i class="ph ph-plus me-2"></i> Criar Nova Lead
                            </a>
                        </div>
                    </div>
                </div>
                <div class="task-item">
                    <div class="task-info">
                        <div class="task-title">
                            <a href="{{ route('opportunities.create') }}" class="text-body">
                                <i class="ph ph-plus me-2"></i> Criar Nova Oportunidade
                            </a>
                        </div>
                    </div>
                </div>
                <div class="task-item">
                    <div class="task-info">
                        <div class="task-title">
                            <a href="{{ route('clientes.create') }}" class="text-body">
                                <i class="ph ph-plus me-2"></i> Adicionar Novo Cliente
                            </a>
                        </div>
                    </div>
                </div>
                <div class="task-item">
                    <div class="task-info">
                        <div class="task-title">
                            <a href="{{ route('properties.create') }}" class="text-body">
                                <i class="ph ph-plus me-2"></i> Adicionar Novo Imóvel
                            </a>
                        </div>
                    </div>
                </div>
                <div class="task-item">
                    <div class="task-info">
                        <div class="task-title">
                            <a href="{{ route('agenda.index') }}" class="text-body">
                                <i class="ph-duotone ph-calendar me-2"></i> Ver Agenda Completa
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Live date & time
    var dateEl = document.getElementById('dashDate');
    var timeEl = document.getElementById('dashTime');

    function updateDateTime() {
        var now = new Date();
        dateEl.textContent = now.toLocaleDateString('pt-PT', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
        timeEl.textContent = now.toLocaleTimeString('pt-PT', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Get CSS variables
    const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent-color').trim() || '#6366f1';
    const successColor = getComputedStyle(document.documentElement).getPropertyValue('--success-color').trim() || '#10b981';
    const warningColor = getComputedStyle(document.documentElement).getPropertyValue('--warning-color').trim() || '#f59e0b';
    const infoColor = getComputedStyle(document.documentElement).getPropertyValue('--info-color').trim() || '#3b82f6';
    const borderColor = getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() || '#e5e7eb';
    const mutedColor = getComputedStyle(document.documentElement).getPropertyValue('--muted-color').trim() || '#6b7280';

    // Revenue Bar Chart - Monthly data
    @php
        // Dados mensais dos últimos 12 meses
        $monthlyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $deals = \App\Models\Deal::whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->sum('final_price') ?? 0;
            $opportunities = \App\Models\Opportunity::whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();
            $monthlyData[] = [
                'month' => $date->format('M'),
                'revenue' => round($deals),
                'opportunities' => $opportunities
            ];
        }
    @endphp

    var revenueData = {
        monthly: {
            series: [{
                name: 'Receita',
                data: [@foreach($monthlyData as $data){{ $data['revenue'] }},@endforeach]
            }, {
                name: 'Oportunidades',
                data: [@foreach($monthlyData as $data){{ $data['opportunities'] }},@endforeach]
            }],
            categories: [@foreach($monthlyData as $data)'{{ $data['month'] }}',@endforeach]
        },
        weekly: {
            series: [{
                name: 'Receita',
                data: [0, 0, 0, 0, 0, 0, 0]
            }, {
                name: 'Oportunidades',
                data: [0, 0, 0, 0, 0, 0, 0]
            }],
            categories: ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']
        }
    };

    var barOptions = {
        series: revenueData.monthly.series,
        chart: {
            type: 'bar',
            height: 320,
            fontFamily: 'inherit',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 400
            }
        },
        plotOptions: {
            bar: {
                borderRadius: 6,
                columnWidth: '55%',
                dataLabels: { position: 'top' }
            }
        },
        colors: [accentColor, successColor],
        dataLabels: { enabled: false },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        xaxis: {
            categories: revenueData.monthly.categories,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                style: {
                    colors: mutedColor,
                    fontSize: '12px'
                }
            }
        },
        yaxis: {
            labels: {
                style: {
                    colors: mutedColor,
                    fontSize: '12px'
                },
                formatter: function(value) {
                    return value.toLocaleString('pt-PT');
                }
            }
        },
        grid: {
            borderColor: borderColor,
            strokeDashArray: 4,
            xaxis: { lines: { show: false } }
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
            fontSize: '13px',
            markers: { width: 10, height: 10, radius: 4 },
            itemMargin: { horizontal: 12 }
        },
        tooltip: {
            y: {
                formatter: function(value) {
                    return value.toLocaleString('pt-PT') + '€';
                }
            }
        }
    };

    var barChart = new ApexCharts(document.querySelector('#revenueBarChart'), barOptions);
    barChart.render();

    // Tab switching
    var chartTabs = document.querySelectorAll('.dash-chart-tab');
    chartTabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            chartTabs.forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            var period = this.getAttribute('data-period');
            var data = revenueData[period];
            barChart.updateOptions({
                xaxis: { categories: data.categories }
            }, false, false);
            barChart.updateSeries(data.series);
        });
    });

    // Traffic Donut Chart (Lead Status Distribution)
    @php
        $leadStatusData = [];
        $leadStatusLabels = [];
        $leadStatusSeries = [];
        foreach(\App\Models\Lead::statuses() as $status => $label) {
            $count = $leadsPorStatus[$status] ?? 0;
            if($count > 0) {
                $leadStatusLabels[] = $label;
                $leadStatusSeries[] = $count;
            }
        }
    @endphp

    const donutOptions = {
        series: [@foreach($leadStatusSeries as $val){{ $val }},@endforeach],
        chart: {
            type: 'donut',
            height: 240,
            fontFamily: 'inherit'
        },
        labels: [@foreach($leadStatusLabels as $label)'{{ $label }}',@endforeach],
        colors: [accentColor, successColor, warningColor, infoColor, '#ef4444'],
        plotOptions: {
            pie: {
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '13px',
                            color: mutedColor
                        },
                        value: {
                            show: true,
                            fontSize: '20px',
                            fontWeight: 700,
                            formatter: function(val) {
                                return val;
                            }
                        },
                        total: {
                            show: true,
                            label: 'Total',
                            fontSize: '13px',
                            color: mutedColor,
                            formatter: function() {
                                return '{{ $totalLeads }}';
                            }
                        }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        legend: { show: false },
        stroke: {
            width: 3,
            colors: ['var(--surface-color)']
        }
    };

    const donutChart = new ApexCharts(document.querySelector('#trafficDonutChart'), donutOptions);
    donutChart.render();

    // Update charts on theme change
    document.addEventListener('themeChanged', function() {
        const newBorderColor = getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim();
        const newMutedColor = getComputedStyle(document.documentElement).getPropertyValue('--muted-color').trim();
        barChart.updateOptions({
            grid: { borderColor: newBorderColor },
            xaxis: { labels: { style: { colors: newMutedColor } } },
            yaxis: { labels: { style: { colors: newMutedColor } } }
        });
    });
});
</script>
@endsection
