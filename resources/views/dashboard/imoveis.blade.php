@extends('partials.layouts.main')
@section('title', 'Dashboard - Imóveis | Imobiliária')
@section('page-heading-title', 'Dashboard - Imóveis')
@section('page-heading-sub-title', 'Análise de Imóveis')
@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@php
    $totalImoveis = \App\Models\Property::where('status', '!=', \App\Models\Property::STATUS_INATIVO)->count();
    $disponiveis = \App\Models\Property::where('status', \App\Models\Property::STATUS_DISPONIVEL)->count();
    $reservados = \App\Models\Property::where('status', \App\Models\Property::STATUS_RESERVADO)->count();
    $emNegociacao = \App\Models\Property::where('status', \App\Models\Property::STATUS_EM_NEGOCIACAO)->count();
    $vendidos = \App\Models\Property::where('status', \App\Models\Property::STATUS_VENDIDO)->count();
    $arrendados = \App\Models\Property::where('status', \App\Models\Property::STATUS_ARRENDADO)->count();
    
    // Por tipo de transação
    $vendas = \App\Models\Property::where('status', '!=', \App\Models\Property::STATUS_INATIVO)
        ->whereHas('transactionType', function($q) {
            $q->where('name', 'like', '%Venda%');
        })
        ->count();
    $arrendamentos = \App\Models\Property::where('status', '!=', \App\Models\Property::STATUS_INATIVO)
        ->whereHas('transactionType', function($q) {
            $q->where('name', 'like', '%Arrendamento%');
        })
        ->count();
    
    // Valor total em stock
    $valorTotalStock = \App\Models\Property::where('status', \App\Models\Property::STATUS_DISPONIVEL)
        ->sum('price') ?? 0;
    
    // Imóveis recentes
    $recentProperties = \App\Models\Property::where('status', '!=', \App\Models\Property::STATUS_INATIVO)
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();
@endphp

<!-- Welcome Banner -->
<div class="dash-welcome mb-4">
    <div class="dash-welcome-content">
        <h2 class="dash-welcome-title">Dashboard de Imóveis</h2>
        <p class="dash-welcome-text">Visão geral do portfólio de imóveis.</p>
    </div>
    <div class="dash-welcome-actions">
        <a href="{{ route('properties.create') }}" class="btn btn-primary">
            <i class="ph ph-plus me-2"></i> Novo Imóvel
        </a>
    </div>
</div>

<!-- KPI Strip -->
<div class="dash-kpi-strip mb-4">
    <div class="dash-kpi">
        <div class="dash-kpi-icon primary">
            <i class="ph-duotone ph-house"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $totalImoveis }}</div>
            <div class="dash-kpi-label">Total Imóveis</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon success">
            <i class="ph-duotone ph-check-circle"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $disponiveis }}</div>
            <div class="dash-kpi-label">Disponíveis</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon warning">
            <i class="ph-duotone ph-clock"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $emNegociacao }}</div>
            <div class="dash-kpi-label">Em Negociação</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon info">
            <i class="ph-duotone ph-currency-eur"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ number_format($valorTotalStock / 1000, 0, ',', '.') }}K€</div>
            <div class="dash-kpi-label">Valor em Stock</div>
        </div>
    </div>
</div>

<!-- Main Grid: Charts Row -->
<div class="dash-grid dash-grid-charts mb-4">
    <!-- Status Distribution Chart -->
    <div class="card dash-chart-main">
        <div class="card-header">
            <h5 class="card-title">Distribuição por Estado</h5>
        </div>
        <div class="card-body">
            <div class="chart-container" id="statusChart"></div>
        </div>
    </div>

    <!-- Transaction Type Distribution -->
    <div class="card">
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
</div>

<!-- Properties Table -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Imóveis Recentes</h5>
        <div class="card-actions">
            <a href="{{ route('properties.index') }}" class="btn btn-sm btn-outline-primary">Ver Todos</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table dash-table">
                <thead>
                    <tr>
                        <th>Referência</th>
                        <th>Título</th>
                        <th>Tipo</th>
                        <th>Preço</th>
                        <th>Estado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentProperties as $property)
                        <tr>
                            <td><a href="{{ route('properties.show', $property) }}" class="fw-medium">{{ $property->reference }}</a></td>
                            <td>{{ $property->title }}</td>
                            <td>{{ $property->transactionType->name ?? '—' }}</td>
                            <td class="fw-semibold">{{ $property->formatted_price }}</td>
                            <td>
                                <span class="dash-status {{ $property->status === \App\Models\Property::STATUS_DISPONIVEL ? 'success' : ($property->status === \App\Models\Property::STATUS_VENDIDO ? 'danger' : 'warning') }}">
                                    {{ \App\Models\Property::statuses()[$property->status] ?? $property->status }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('properties.show', $property) }}" class="btn btn-sm btn-light" title="Ver">
                                    <i class="ph ph-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">Nenhum imóvel encontrado.</td>
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
    const warningColor = getComputedStyle(document.documentElement).getPropertyValue('--warning-color').trim() || '#f59e0b';
    const infoColor = getComputedStyle(document.documentElement).getPropertyValue('--info-color').trim() || '#3b82f6';
    const dangerColor = '#ef4444';
    const mutedColor = getComputedStyle(document.documentElement).getPropertyValue('--muted-color').trim() || '#6b7280';
    const borderColor = getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() || '#e5e7eb';

    // Status Distribution Chart
    const statusOptions = {
        series: [{{ $disponiveis }}, {{ $reservados }}, {{ $emNegociacao }}, {{ $vendidos }}, {{ $arrendados }}],
        chart: {
            type: 'donut',
            height: 320,
            fontFamily: 'inherit'
        },
        labels: ['Disponíveis', 'Reservados', 'Em Negociação', 'Vendidos', 'Arrendados'],
        colors: [successColor, warningColor, infoColor, dangerColor, accentColor],
        plotOptions: {
            pie: {
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        name: { show: true, fontSize: '13px', color: mutedColor },
                        value: { show: true, fontSize: '20px', fontWeight: 700 },
                        total: { show: true, label: 'Total', fontSize: '13px', color: mutedColor, formatter: () => '{{ $totalImoveis }}' }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        legend: { position: 'bottom', horizontalAlign: 'center' },
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
                        total: { show: true, label: 'Total', fontSize: '13px', color: mutedColor, formatter: () => '{{ $totalImoveis }}' }
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
