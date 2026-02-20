@extends('partials.layouts.main')
@section('title', 'Dashboard - Clientes | Beauty CRM')
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
    $availableClientes = \App\Models\Client::where('status', \App\Models\Client::STATUS_AVAILABLE)->count();
    $unavailableClientes = \App\Models\Client::where('status', \App\Models\Client::STATUS_UNAVAILABLE)->count();
    
    // Clientes do mês atual
    $clientesEsteMes = \App\Models\Client::whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->count();
    
    // Clientes com negócios fechados
    $clientesComNegocios = \App\Models\Client::whereHas('deals', function($q) {
            $q->where('status', \App\Models\Deal::STATUS_FECHADO);
        })
        ->count();
    
    // Clientes recentes
    $recentClients = \App\Models\Client::orderBy('created_at', 'desc')
        ->limit(10)
        ->get();
    
    // Clientes por tipo
    $potenciais = \App\Models\Client::where('type', \App\Models\Client::TYPE_POTENCIAL_CLIENTE)->count();
    
    // Clientes com leads ativas (via oportunidades)
    $clientesComLeads = \App\Models\Client::whereHas('opportunities', function($q) {
            $q->whereHas('lead', function($q2) {
                $q2->where('status', '!=', 'arquivada');
            });
        })
        ->count();
    
    // Clientes com oportunidades ativas
    $clientesComOportunidades = \App\Models\Client::whereHas('opportunities', function($q) {
            $q->where('status', '!=', 'arquivada')
              ->where('status', '!=', 'ganha');
        })
        ->count();
    
    // Crescimento mensal (últimos 6 meses)
    $monthlyGrowth = [];
    for ($i = 5; $i >= 0; $i--) {
        $date = now()->subMonths($i);
        $monthlyGrowth[] = [
            'month' => $date->format('M'),
            'count' => \App\Models\Client::whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count()
        ];
    }
@endphp

<!-- Welcome Banner -->
<div class="dash-welcome mb-4">
    <div class="dash-welcome-content">
        <h2 class="dash-welcome-title">Dashboard de Clientes</h2>
        <p class="dash-welcome-text">Visão geral da base de clientes.</p>
    </div>
    <div class="dash-welcome-actions">
        <a href="{{ route('clientes.create') }}" class="btn btn-primary">
            <i class="ph ph-plus me-2"></i> Novo Cliente
        </a>
    </div>
</div>

<!-- KPI Strip -->
<div class="dash-kpi-strip mb-4">
    <div class="dash-kpi">
        <div class="dash-kpi-icon primary">
            <i class="ph-duotone ph-users"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $totalClientes }}</div>
            <div class="dash-kpi-label">Total Clientes</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon success">
            <i class="ph-duotone ph-check-circle"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $activeClientes }}</div>
            <div class="dash-kpi-label">Ativos</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon info">
            <i class="ph-duotone ph-trophy"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $clientesComNegocios }}</div>
            <div class="dash-kpi-label">Com Negócios</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon warning">
            <i class="ph-duotone ph-calendar-check"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $clientesEsteMes }}</div>
            <div class="dash-kpi-label">Este Mês</div>
        </div>
    </div>
</div>

<!-- Main Grid: Charts Row -->
<div class="dash-grid dash-grid-charts mb-4">
    <!-- Growth Chart -->
    <div class="card dash-chart-main">
        <div class="card-header">
            <h5 class="card-title">Crescimento Mensal (Últimos 6 Meses)</h5>
        </div>
        <div class="card-body">
            <div class="chart-container" id="growthChart"></div>
        </div>
    </div>

    <!-- Status Distribution -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Distribuição por Estado</h5>
        </div>
        <div class="card-body">
            <div class="chart-container" id="statusChart"></div>
            <div class="dash-traffic-legend mt-3">
                <div class="dash-traffic-item">
                    <span class="dash-traffic-dot" style="--dot-color: var(--success-color)"></span>
                    <span class="dash-traffic-name">Ativos</span>
                    <span class="dash-traffic-val">{{ $activeClientes }}</span>
                </div>
                <div class="dash-traffic-item">
                    <span class="dash-traffic-dot" style="--dot-color: var(--info-color)"></span>
                    <span class="dash-traffic-name">Disponíveis</span>
                    <span class="dash-traffic-val">{{ $availableClientes }}</span>
                </div>
                <div class="dash-traffic-item">
                    <span class="dash-traffic-dot" style="--dot-color: var(--muted-color)"></span>
                    <span class="dash-traffic-name">Indisponíveis</span>
                    <span class="dash-traffic-val">{{ $unavailableClientes }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Activity Cards -->
<div class="dash-grid mb-4">
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="dash-kpi-icon primary me-3">
                    <i class="ph-duotone ph-list-bullets"></i>
                </div>
                <div>
                    <div class="fw-semibold mb-1">Clientes com Leads</div>
                    <div class="h4 mb-0">{{ $clientesComLeads }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="dash-kpi-icon info me-3">
                    <i class="ph-duotone ph-briefcase"></i>
                </div>
                <div>
                    <div class="fw-semibold mb-1">Clientes com Oportunidades</div>
                    <div class="h4 mb-0">{{ $clientesComOportunidades }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Clients Table -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Clientes Recentes</h5>
        <div class="card-actions">
            <a href="{{ route('clientes.index') }}" class="btn btn-sm btn-outline-primary">Ver Todos</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table dash-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Telefone</th>
                        <th>Estado</th>
                        <th>Data Registo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentClients as $client)
                        <tr>
                            <td><a href="{{ route('clientes.show', $client) }}" class="fw-medium">{{ $client->client_id }}</a></td>
                            <td>{{ $client->name }}</td>
                            <td>{{ $client->email ?? '—' }}</td>
                            <td>{{ $client->phone ?? '—' }}</td>
                            <td>
                                <span class="dash-status {{ $client->status === \App\Models\Client::STATUS_ACTIVE ? 'success' : ($client->status === \App\Models\Client::STATUS_AVAILABLE ? 'info' : 'secondary') }}">
                                    {{ \App\Models\Client::statusLabels()[$client->status] ?? $client->status }}
                                </span>
                            </td>
                            <td>{{ $client->created_at->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ route('clientes.show', $client) }}" class="btn btn-sm btn-light" title="Ver">
                                    <i class="ph ph-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">Nenhum cliente encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent-color').trim() || '#6366f1';
    const successColor = getComputedStyle(document.documentElement).getPropertyValue('--success-color').trim() || '#10b981';
    const infoColor = getComputedStyle(document.documentElement).getPropertyValue('--info-color').trim() || '#3b82f6';
    const mutedColor = getComputedStyle(document.documentElement).getPropertyValue('--muted-color').trim() || '#6b7280';
    const borderColor = getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() || '#e5e7eb';

    // Growth Chart
    const growthOptions = {
        series: [{
            name: 'Novos Clientes',
            data: [@foreach($monthlyGrowth as $data){{ $data['count'] }},@endforeach]
        }],
        chart: {
            type: 'line',
            height: 320,
            fontFamily: 'inherit',
            toolbar: { show: false },
            sparkline: { enabled: false }
        },
        stroke: {
            curve: 'smooth',
            width: 3
        },
        colors: [accentColor],
        xaxis: {
            categories: [@foreach($monthlyGrowth as $data)'{{ $data['month'] }}',@endforeach],
            labels: { style: { colors: mutedColor } }
        },
        yaxis: {
            labels: {
                style: { colors: mutedColor }
            }
        },
        grid: {
            borderColor: borderColor,
            strokeDashArray: 4
        },
        markers: {
            size: 5,
            colors: [accentColor],
            strokeColors: accentColor,
            strokeWidth: 2
        },
        tooltip: {
            theme: 'light'
        }
    };
    const growthChart = new ApexCharts(document.querySelector('#growthChart'), growthOptions);
    growthChart.render();

    // Status Distribution Chart
    const statusOptions = {
        series: [{{ $activeClientes }}, {{ $availableClientes }}, {{ $unavailableClientes }}],
        chart: {
            type: 'donut',
            height: 240,
            fontFamily: 'inherit'
        },
        labels: ['Ativos', 'Disponíveis', 'Indisponíveis'],
        colors: [successColor, infoColor, mutedColor],
        plotOptions: {
            pie: {
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        name: { show: true, fontSize: '13px', color: mutedColor },
                        value: { show: true, fontSize: '20px', fontWeight: 700 },
                        total: { show: true, label: 'Total', fontSize: '13px', color: mutedColor, formatter: () => '{{ $totalClientes }}' }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        legend: { show: false },
        stroke: { width: 3, colors: ['var(--surface-color)'] }
    };
    const statusChart = new ApexCharts(document.querySelector('#statusChart'), statusOptions);
    statusChart.render();
});
</script>
@endsection
