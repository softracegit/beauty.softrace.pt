@extends('partials.layouts.main')
@section('title', 'Dashboard - Negócios | Beauty CRM')
@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@php
    $totalNegocios = \App\Models\Deal::count();
    $fechados = \App\Models\Deal::where('status', \App\Models\Deal::STATUS_FECHADO)->count();
    $revertidos = \App\Models\Deal::where('status', \App\Models\Deal::STATUS_REVERTIDO)->count();
    
    // Receita total
    $totalReceita = \App\Models\Deal::where('status', \App\Models\Deal::STATUS_FECHADO)->sum('final_price') ?? 0;
    
    // Comissões totais
    $totalComissoes = \App\Models\Deal::where('status', \App\Models\Deal::STATUS_FECHADO)
        ->sum('property_commission_value') ?? 0;
    
    // Negócios do mês atual
    $negociosEsteMes = \App\Models\Deal::whereMonth('closed_at', now()->month)
        ->whereYear('closed_at', now()->year)
        ->where('status', \App\Models\Deal::STATUS_FECHADO)
        ->count();
    
    // Receita do mês atual
    $receitaEsteMes = \App\Models\Deal::whereMonth('closed_at', now()->month)
        ->whereYear('closed_at', now()->year)
        ->where('status', \App\Models\Deal::STATUS_FECHADO)
        ->sum('final_price') ?? 0;
    
    // Negócios recentes
    $recentDeals = \App\Models\Deal::with(['client', 'property'])
        ->orderBy('closed_at', 'desc')
        ->limit(10)
        ->get();
    
    // Receita mensal (últimos 6 meses)
    $monthlyRevenue = [];
    for ($i = 5; $i >= 0; $i--) {
        $date = now()->subMonths($i);
        $monthlyRevenue[] = [
            'month' => $date->format('M'),
            'revenue' => \App\Models\Deal::whereMonth('closed_at', $date->month)
                ->whereYear('closed_at', $date->year)
                ->where('status', \App\Models\Deal::STATUS_FECHADO)
                ->sum('final_price') ?? 0
        ];
    }
    
    // Por tipo de transação
    $vendas = \App\Models\Deal::where('status', \App\Models\Deal::STATUS_FECHADO)
        ->where('transaction_type', 'like', '%Venda%')
        ->count();
    $arrendamentos = \App\Models\Deal::where('status', \App\Models\Deal::STATUS_FECHADO)
        ->where('transaction_type', 'like', '%Arrendamento%')
        ->count();
@endphp

<!-- Welcome Banner -->
<div class="dash-welcome mb-4">
    <div class="dash-welcome-content">
        <h2 class="dash-welcome-title">Dashboard de Negócios</h2>
        <p class="dash-welcome-text">Análise de negócios fechados e receitas.</p>
    </div>
    <div class="dash-welcome-actions">
        <a href="{{ route('deals.index') }}" class="btn btn-primary">
            <i class="ph ph-list me-2"></i> Ver Todos os Negócios
        </a>
    </div>
</div>

<!-- KPI Strip -->
<div class="dash-kpi-strip mb-4">
    <div class="dash-kpi">
        <div class="dash-kpi-icon primary">
            <i class="ph-duotone ph-trophy"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $totalNegocios }}</div>
            <div class="dash-kpi-label">Total Negócios</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon success">
            <i class="ph-duotone ph-check-circle"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $fechados }}</div>
            <div class="dash-kpi-label">Fechados</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon info">
            <i class="ph-duotone ph-currency-eur"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ number_format($totalReceita / 1000, 0, ',', '.') }}K€</div>
            <div class="dash-kpi-label">Receita Total</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon warning">
            <i class="ph-duotone ph-calendar-check"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $negociosEsteMes }}</div>
            <div class="dash-kpi-label">Este Mês</div>
        </div>
    </div>
</div>

<!-- Main Grid: Charts Row -->
<div class="dash-grid dash-grid-charts mb-4">
    <!-- Revenue Chart -->
    <div class="card dash-chart-main">
        <div class="card-header">
            <h5 class="card-title">Receita Mensal (Últimos 6 Meses)</h5>
        </div>
        <div class="card-body">
            <div class="chart-container" id="revenueChart"></div>
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
                    <span class="dash-traffic-name">Fechados</span>
                    <span class="dash-traffic-val">{{ $fechados }}</span>
                </div>
                <div class="dash-traffic-item">
                    <span class="dash-traffic-dot" style="--dot-color: var(--danger-color)"></span>
                    <span class="dash-traffic-name">Revertidos</span>
                    <span class="dash-traffic-val">{{ $revertidos }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Type Distribution -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title">Por Tipo de Transação</h5>
    </div>
    <div class="card-body">
        <div class="chart-container" id="transactionChart"></div>
        <div class="dash-traffic-legend mt-3">
            <div class="dash-traffic-item">
                <span class="dash-traffic-dot" style="--dot-color: var(--primary-color)"></span>
                <span class="dash-traffic-name">Venda</span>
                <span class="dash-traffic-val">{{ $vendas }}</span>
            </div>
            <div class="dash-traffic-item">
                <span class="dash-traffic-dot" style="--dot-color: var(--success-color)"></span>
                <span class="dash-traffic-name">Arrendamento</span>
                <span class="dash-traffic-val">{{ $arrendamentos }}</span>
            </div>
        </div>
    </div>
</div>

<!-- Deals Table -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Negócios Recentes</h5>
        <div class="card-actions">
            <a href="{{ route('deals.index') }}" class="btn btn-sm btn-outline-primary">Ver Todos</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table dash-table">
                <thead>
                    <tr>
                        <th>Referência</th>
                        <th>Cliente</th>
                        <th>Imóvel</th>
                        <th>Valor</th>
                        <th>Estado</th>
                        <th>Data Fecho</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentDeals as $deal)
                        <tr>
                            <td><a href="{{ route('deals.show', $deal) }}" class="fw-medium">{{ $deal->reference }}</a></td>
                            <td>{{ $deal->client->name ?? '—' }}</td>
                            <td>{{ $deal->property_title ?? '—' }}</td>
                            <td class="fw-semibold">{{ $deal->formatted_final_price }}</td>
                            <td>
                                <span class="dash-status {{ $deal->status === \App\Models\Deal::STATUS_FECHADO ? 'success' : 'danger' }}">
                                    {{ \App\Models\Deal::statuses()[$deal->status] ?? $deal->status }}
                                </span>
                            </td>
                            <td>{{ $deal->closed_at ? $deal->closed_at->format('d/m/Y') : '—' }}</td>
                            <td>
                                <a href="{{ route('deals.show', $deal) }}" class="btn btn-sm btn-light" title="Ver">
                                    <i class="ph ph-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">Nenhum negócio encontrado.</td>
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
    const dangerColor = '#ef4444';
    const mutedColor = getComputedStyle(document.documentElement).getPropertyValue('--muted-color').trim() || '#6b7280';
    const borderColor = getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() || '#e5e7eb';

    // Revenue Chart
    const revenueOptions = {
        series: [{
            name: 'Receita',
            data: [@foreach($monthlyRevenue as $data){{ $data['revenue'] }},@endforeach]
        }],
        chart: {
            type: 'bar',
            height: 320,
            fontFamily: 'inherit',
            toolbar: { show: false }
        },
        plotOptions: {
            bar: {
                borderRadius: 8,
                columnWidth: '60%'
            }
        },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: {
            categories: [@foreach($monthlyRevenue as $data)'{{ $data['month'] }}',@endforeach],
            labels: { style: { colors: mutedColor } }
        },
        yaxis: {
            labels: {
                style: { colors: mutedColor },
                formatter: function(val) {
                    return (val / 1000).toFixed(0) + 'K€';
                }
            }
        },
        fill: {
            opacity: 1,
            colors: [accentColor]
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    return new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(val);
                }
            }
        },
        grid: {
            borderColor: borderColor,
            strokeDashArray: 4
        }
    };
    const revenueChart = new ApexCharts(document.querySelector('#revenueChart'), revenueOptions);
    revenueChart.render();

    // Status Distribution Chart
    const statusOptions = {
        series: [{{ $fechados }}, {{ $revertidos }}],
        chart: {
            type: 'donut',
            height: 240,
            fontFamily: 'inherit'
        },
        labels: ['Fechados', 'Revertidos'],
        colors: [successColor, dangerColor],
        plotOptions: {
            pie: {
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        name: { show: true, fontSize: '13px', color: mutedColor },
                        value: { show: true, fontSize: '20px', fontWeight: 700 },
                        total: { show: true, label: 'Total', fontSize: '13px', color: mutedColor, formatter: () => '{{ $totalNegocios }}' }
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

    // Transaction Type Chart
    const transactionOptions = {
        series: [{{ $vendas }}, {{ $arrendamentos }}],
        chart: {
            type: 'donut',
            height: 240,
            fontFamily: 'inherit'
        },
        labels: ['Venda', 'Arrendamento'],
        colors: [accentColor, successColor],
        plotOptions: {
            pie: {
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        name: { show: true, fontSize: '13px', color: mutedColor },
                        value: { show: true, fontSize: '20px', fontWeight: 700 },
                        total: { show: true, label: 'Total', fontSize: '13px', color: mutedColor, formatter: () => '{{ $fechados }}' }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        legend: { show: false },
        stroke: { width: 3, colors: ['var(--surface-color)'] }
    };
    const transactionChart = new ApexCharts(document.querySelector('#transactionChart'), transactionOptions);
    transactionChart.render();
});
</script>
@endsection
