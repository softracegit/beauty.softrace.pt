@extends('partials.layouts.main')
@section('title', 'Dashboard - Resumo | Beauty CRM')
@section('css')
<style>
.dash-resumo-year-toggles {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.dash-resumo-year-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    background: transparent;
    color: var(--default-color);
    font-size: 0.8125rem;
    line-height: 1.4;
    cursor: pointer;
    transition: opacity 0.15s ease, border-color 0.15s ease;
}
.dash-resumo-year-toggle:hover {
    border-color: color-mix(in srgb, var(--accent-color), var(--border-color) 60%);
    background: transparent;
    color: var(--default-color);
}
.dash-resumo-year-toggle:not(.active) {
    opacity: 0.45;
}
.dash-resumo-year-toggle.active {
    opacity: 1;
    background: transparent;
    color: var(--default-color);
}
.dash-resumo-year-swatch {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 0.2rem;
    flex-shrink: 0;
}
#resumoTelemovelChart .apexcharts-datalabel-value,
#resumoEmailChart .apexcharts-datalabel-value,
#resumoAniversarioChart .apexcharts-datalabel-value {
    font-size: 1.5rem !important;
    font-weight: 700 !important;
    fill: var(--heading-color) !important;
}
#resumoPeriodTabs,
#resumoChartTabs {
    border-bottom: 1px solid var(--border-color);
}
#resumoPeriodTabs .nav-link,
#resumoChartTabs .nav-link {
    position: relative;
    border: none !important;
    margin-bottom: 0;
    padding-bottom: calc(0.625rem + 4px);
    font-weight: 500;
    background: transparent !important;
}
#resumoPeriodTabs .nav-link::after,
#resumoChartTabs .nav-link::after {
    content: '';
    position: absolute;
    left: 0.75rem;
    right: 0.75rem;
    bottom: -1px;
    height: 0;
    background: var(--accent-color);
    border-radius: 3px 3px 0 0;
    transition: height 0.15s ease, opacity 0.15s ease;
    z-index: 2;
}
#resumoPeriodTabs .nav-link:hover::after,
#resumoPeriodTabs .nav-link:focus::after,
#resumoChartTabs .nav-link:hover::after,
#resumoChartTabs .nav-link:focus::after {
    height: 2px;
    opacity: 0.45;
}
#resumoPeriodTabs .nav-link.active,
#resumoChartTabs .nav-link.active {
    color: var(--accent-color);
    font-weight: 600;
}
#resumoPeriodTabs .nav-link.active::after,
#resumoChartTabs .nav-link.active::after {
    height: 4px;
    opacity: 1;
}
.resumo-chart-tab-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}
@media (max-width: 767.98px) {
    #resumoChartTabContent .chart-container-lg {
        margin-left: -0.75rem;
        margin-right: -0.75rem;
    }
}
.dash-resumo-kpi-row {
    margin-bottom: var(--spacing-xl);
}
.dash-resumo-kpi-row > [class*="col-"] {
    display: flex;
}
.dash-resumo-kpi-row .dash-kpi {
    width: 100%;
}
.dash-resumo-vendas-card .dash-kpi-body {
    flex: 1;
    min-width: 0;
}
.dash-kpi-euro {
    font-size: 1.35rem;
    font-weight: 700;
    line-height: 1;
}
.dash-resumo-vendas-agregado {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
    width: 100%;
}
.dash-resumo-vendas-metric {
    min-width: 0;
    padding: 0 0.5rem;
}
.dash-resumo-vendas-metric:not(:first-child) {
    border-left: 1px solid var(--border-color);
    padding-left: 1rem;
}
.dash-resumo-vendas-metric-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--heading-color);
    line-height: 1.2;
    white-space: nowrap;
}
.dash-resumo-vendas-metric-label {
    font-size: 0.6875rem;
    color: var(--muted-color, var(--default-color));
    margin-top: 0.2rem;
    line-height: 1.3;
}
.dash-kpi-icon.muted {
    background: color-mix(in srgb, var(--default-color), transparent 92%);
    color: var(--default-color);
}
@media (max-width: 575.98px) {
    .dash-resumo-vendas-agregado {
        grid-template-columns: 1fr;
        gap: 0.65rem;
    }
    .dash-resumo-vendas-metric:not(:first-child) {
        border-left: none;
        border-top: 1px solid var(--border-color);
        padding-left: 0.5rem;
        padding-top: 0.65rem;
    }
}
</style>
@endsection
@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="dash-welcome mb-4">
    <div class="dash-welcome-content">
        <h2 class="dash-welcome-title">Olá de novo, {{ auth()->user()->name }}</h2>
        <p class="dash-welcome-text">Visão geral do seu negócio.</p>
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

@php
    $periodTabs = [
        'hoje' => 'Hoje',
        'ontem' => 'Ontem',
        'semana' => 'Semana',
        'mes' => 'Mês',
    ];
@endphp

<ul class="nav nav-tabs nav-tabs-bordered mb-4" id="resumoPeriodTabs" role="tablist">
    @foreach($periodTabs as $periodKey => $periodLabel)
        <li class="nav-item" role="presentation">
            <button
                class="nav-link {{ $loop->first ? 'active' : '' }}"
                id="resumo-tab-{{ $periodKey }}"
                data-bs-toggle="tab"
                data-bs-target="#resumo-pane-{{ $periodKey }}"
                type="button"
                role="tab"
                aria-controls="resumo-pane-{{ $periodKey }}"
                aria-selected="{{ $loop->first ? 'true' : 'false' }}"
            >{{ $periodLabel }}</button>
        </li>
    @endforeach
</ul>

<div class="tab-content mb-4" id="resumoPeriodTabContent">
    @foreach($periodTabs as $periodKey => $periodLabel)
        @php $kpi = $kpiPorPeriodo[$periodKey] ?? ['vendas_previsto' => 0, 'vendas_feitas' => 0, 'vendas_por_fazer' => 0, 'clientes_atendidos' => 0, 'taxa_ocupacao' => 0]; @endphp
        <div
            class="tab-pane fade {{ $loop->first ? 'show active' : '' }}"
            id="resumo-pane-{{ $periodKey }}"
            role="tabpanel"
            aria-labelledby="resumo-tab-{{ $periodKey }}"
        >
            <div class="row g-3 dash-resumo-kpi-row">
                <div class="col-lg-6">
                    <div class="dash-kpi dash-resumo-vendas-card h-100">
                        <div class="dash-kpi-icon primary">
                            <span class="dash-kpi-euro" aria-hidden="true">€</span>
                        </div>
                        <div class="dash-kpi-body">
                            <div class="dash-resumo-vendas-agregado">
                                <div class="dash-resumo-vendas-metric">
                                    <div class="dash-resumo-vendas-metric-value">{{ number_format($kpi['vendas_previsto'], 0, ',', '.') }} €</div>
                                    <div class="dash-resumo-vendas-metric-label">Total previsto</div>
                                </div>
                                <div class="dash-resumo-vendas-metric">
                                    <div class="dash-resumo-vendas-metric-value">{{ number_format($kpi['vendas_feitas'], 0, ',', '.') }} €</div>
                                    <div class="dash-resumo-vendas-metric-label">Vendas feitas</div>
                                </div>
                                <div class="dash-resumo-vendas-metric">
                                    <div class="dash-resumo-vendas-metric-value">{{ number_format($kpi['vendas_por_fazer'], 0, ',', '.') }} €</div>
                                    <div class="dash-resumo-vendas-metric-label">Por fazer</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-sm-6">
                    <div class="dash-kpi h-100">
                        <div class="dash-kpi-icon success">
                            <i class="ph-duotone ph-user-check"></i>
                        </div>
                        <div class="dash-kpi-body">
                            <div class="dash-kpi-value">{{ $kpi['clientes_atendidos'] }}</div>
                            <div class="dash-kpi-label">Clientes atendidos</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-sm-6">
                    <div class="dash-kpi h-100">
                        <div class="dash-kpi-icon muted">
                            <i class="ph-duotone ph-chart-pie-slice"></i>
                        </div>
                        <div class="dash-kpi-body">
                            <div class="dash-kpi-value">{{ number_format($kpi['taxa_ocupacao'], 1, ',', '.') }}%</div>
                            <div class="dash-kpi-label">Taxa de ocupação</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@php
    $chartTabs = [
        'vendas-ano' => 'Vendas este ano',
        'vendas-mes' => 'Vendas este mês',
        'atendidos' => 'Clientes atendidos',
        'novos' => 'Clientes novos',
    ];
    $vendasMesLabels = $vendasMesCorrente['labels'] ?? [];
    $vendasMesData = $vendasMesCorrente['data'] ?? [];
    $vendasMesLabel = $vendasMesCorrente['month_label'] ?? '';
@endphp

<div class="card mb-4">
    <div class="card-body pb-4 pt-3">
        <ul class="nav nav-tabs nav-tabs-bordered mb-0" id="resumoChartTabs" role="tablist">
            @foreach($chartTabs as $chartKey => $chartLabel)
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link {{ $loop->first ? 'active' : '' }}"
                        id="resumo-chart-tab-{{ $chartKey }}"
                        data-bs-toggle="tab"
                        data-bs-target="#resumo-chart-pane-{{ $chartKey }}"
                        type="button"
                        role="tab"
                        aria-controls="resumo-chart-pane-{{ $chartKey }}"
                        aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                    >{{ $chartLabel }}</button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content pt-4" id="resumoChartTabContent">
            <div
                class="tab-pane fade show active"
                id="resumo-chart-pane-vendas-ano"
                role="tabpanel"
                aria-labelledby="resumo-chart-tab-vendas-ano"
            >
                <div class="resumo-chart-tab-toolbar">
                    <div class="dash-resumo-year-toggles" role="group" aria-label="Anos no gráfico de vendas anuais" data-chart-target="resumoVendasAnoChart">
                        <button type="button" class="dash-resumo-year-toggle active" data-series="{{ $previousYear }}">
                            <span class="dash-resumo-year-swatch" style="background-color: #d4d4d8;"></span>{{ $previousYear }}
                        </button>
                        <button type="button" class="dash-resumo-year-toggle active" data-series="{{ $currentYear }}">
                            <span class="dash-resumo-year-swatch" style="background-color: var(--accent-color);"></span>{{ $currentYear }}
                        </button>
                    </div>
                </div>
                <div class="chart-container chart-container-lg" id="resumoVendasAnoChart"></div>
            </div>

            <div
                class="tab-pane fade"
                id="resumo-chart-pane-vendas-mes"
                role="tabpanel"
                aria-labelledby="resumo-chart-tab-vendas-mes"
            >
                <div class="resumo-chart-tab-toolbar">
                    <span class="text-muted small">{{ $vendasMesLabel }}</span>
                </div>
                <div class="chart-container chart-container-lg" id="resumoVendasMesChart"></div>
            </div>

            <div
                class="tab-pane fade"
                id="resumo-chart-pane-atendidos"
                role="tabpanel"
                aria-labelledby="resumo-chart-tab-atendidos"
            >
                <div class="resumo-chart-tab-toolbar">
                    <div class="dash-resumo-year-toggles" role="group" aria-label="Anos no gráfico de clientes atendidos" data-chart-target="resumoAtendidosChart">
                        <button type="button" class="dash-resumo-year-toggle active" data-series="{{ $previousYear }}">
                            <span class="dash-resumo-year-swatch" style="background-color: #d4d4d8;"></span>{{ $previousYear }}
                        </button>
                        <button type="button" class="dash-resumo-year-toggle active" data-series="{{ $currentYear }}">
                            <span class="dash-resumo-year-swatch" style="background-color: var(--accent-color);"></span>{{ $currentYear }}
                        </button>
                    </div>
                </div>
                <div class="chart-container chart-container-lg" id="resumoAtendidosChart"></div>
            </div>

            <div
                class="tab-pane fade"
                id="resumo-chart-pane-novos"
                role="tabpanel"
                aria-labelledby="resumo-chart-tab-novos"
            >
                <div class="resumo-chart-tab-toolbar">
                    <div class="dash-resumo-year-toggles" role="group" aria-label="Anos no gráfico de clientes novos" data-chart-target="resumoNovosChart">
                        <button type="button" class="dash-resumo-year-toggle active" data-series="{{ $previousYear }}">
                            <span class="dash-resumo-year-swatch" style="background-color: #d4d4d8;"></span>{{ $previousYear }}
                        </button>
                        <button type="button" class="dash-resumo-year-toggle active" data-series="{{ $currentYear }}">
                            <span class="dash-resumo-year-swatch" style="background-color: var(--accent-color);"></span>{{ $currentYear }}
                        </button>
                    </div>
                </div>
                <div class="chart-container chart-container-lg" id="resumoNovosChart"></div>
            </div>
        </div>
    </div>
</div>

@php
    $totalClientes = (int) ($clientesContacto['total'] ?? 0);
    $pctTelemovel = $totalClientes > 0 ? round((($clientesContacto['com_telemovel'] ?? 0) / $totalClientes) * 100, 1) : 0;
    $pctEmail = $totalClientes > 0 ? round((($clientesContacto['com_email'] ?? 0) / $totalClientes) * 100, 1) : 0;
    $pctAniversario = $totalClientes > 0 ? round((($clientesContacto['com_aniversario'] ?? 0) / $totalClientes) * 100, 1) : 0;
@endphp

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title">Clientes com telemóvel preenchido</h5>
            </div>
            <div class="card-body">
                <div class="chart-container" id="resumoTelemovelChart"></div>
                <div class="text-center mt-2">
                    <strong>{{ $clientesContacto['com_telemovel'] ?? 0 }}</strong>
                    <span class="text-muted">de {{ $clientesContacto['total'] ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title">Clientes com email preenchido</h5>
            </div>
            <div class="card-body">
                <div class="chart-container" id="resumoEmailChart"></div>
                <div class="text-center mt-2">
                    <strong>{{ $clientesContacto['com_email'] ?? 0 }}</strong>
                    <span class="text-muted">de {{ $clientesContacto['total'] ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title">Clientes com data de aniversário</h5>
            </div>
            <div class="card-body">
                <div class="chart-container" id="resumoAniversarioChart"></div>
                <div class="text-center mt-2">
                    <strong>{{ $clientesContacto['com_aniversario'] ?? 0 }}</strong>
                    <span class="text-muted">de {{ $clientesContacto['total'] ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var dateEl = document.getElementById('dashDate');
    var timeEl = document.getElementById('dashTime');
    function updateDateTime() {
        var now = new Date();
        if (dateEl) dateEl.textContent = now.toLocaleDateString('pt-PT', { month: 'short', day: 'numeric', year: 'numeric' });
        if (timeEl) timeEl.textContent = now.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    var accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent-color').trim() || '#6366f1';
    var infoColor = getComputedStyle(document.documentElement).getPropertyValue('--info-color').trim() || '#3b82f6';
    var successColor = getComputedStyle(document.documentElement).getPropertyValue('--success-color').trim() || '#10b981';
    var warningColor = getComputedStyle(document.documentElement).getPropertyValue('--warning-color').trim() || '#f59e0b';
    var mutedColor = getComputedStyle(document.documentElement).getPropertyValue('--muted-color').trim() || '#6b7280';
    var borderColor = getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() || '#e5e7eb';
    var yearOlderColor = '#d4d4d8';
    var yearOlderHoverColor = '#e0e0e4';
    var barChartHeight = 460;

    function olderYearBarPath(ctx, seriesIndex, dataPointIndex) {
        if (seriesIndex !== 0) {
            return null;
        }

        return ctx.w.globals.dom.baseEl.querySelector(
            '.apexcharts-bar-series .apexcharts-series[rel="1"] path.apexcharts-bar-area[j="' + dataPointIndex + '"]'
        );
    }

    function applyOlderYearBarHover(ctx, seriesIndex, dataPointIndex) {
        var path = olderYearBarPath(ctx, seriesIndex, dataPointIndex);
        if (!path) {
            return;
        }
        path.removeAttribute('filter');
        path.style.filter = 'none';
        path.setAttribute('fill', yearOlderHoverColor);
    }

    function resetOlderYearBarHover(ctx, seriesIndex, dataPointIndex) {
        var path = olderYearBarPath(ctx, seriesIndex, dataPointIndex);
        if (!path) {
            return;
        }
        path.removeAttribute('filter');
        path.style.filter = '';
        path.setAttribute('fill', yearOlderColor);
    }

    function comparativeBarChartEvents() {
        return {
            dataPointMouseEnter: function(event, ctx, config) {
                if (config.seriesIndex !== 0) {
                    return;
                }
                var snapshot = { seriesIndex: config.seriesIndex, dataPointIndex: config.dataPointIndex };
                requestAnimationFrame(function() {
                    applyOlderYearBarHover(ctx, snapshot.seriesIndex, snapshot.dataPointIndex);
                });
            },
            dataPointMouseLeave: function(event, ctx, config) {
                if (config.seriesIndex !== 0) {
                    return;
                }
                resetOlderYearBarHover(ctx, config.seriesIndex, config.dataPointIndex);
            }
        };
    }

    var monthLabels = @json($monthLabels ?? []);
    var currentYear = @json($currentYear ?? new Date().getFullYear());
    var previousYear = @json($previousYear ?? new Date().getFullYear() - 1);

    function comparativeBarOptions(series, categories, yFormatter) {
        return {
            series: series,
            chart: {
                type: 'bar',
                height: barChartHeight,
                fontFamily: 'inherit',
                toolbar: { show: false },
                events: comparativeBarChartEvents()
            },
            plotOptions: { bar: { borderRadius: 6, columnWidth: '70%' } },
            responsive: [{
                breakpoint: 768,
                options: {
                    plotOptions: { bar: { columnWidth: '92%' } },
                    grid: { padding: { top: 4, right: 0, left: 0 } },
                    xaxis: {
                        labels: { style: { fontSize: '10px' } }
                    }
                }
            }],
            colors: [yearOlderColor, accentColor],
            dataLabels: { enabled: false },
            stroke: { show: true, width: 2, colors: ['transparent'] },
            xaxis: {
                categories: categories,
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { colors: mutedColor, fontSize: '12px' } }
            },
            yaxis: {
                labels: {
                    style: { colors: mutedColor, fontSize: '12px' },
                    formatter: yFormatter || function(v) { return v.toLocaleString('pt-PT'); }
                }
            },
            grid: { borderColor: borderColor, strokeDashArray: 4, xaxis: { lines: { show: false } }, padding: { top: 4, right: 8 } },
            legend: { show: false },
            states: {
                active: { filter: { type: 'none' } },
                hover: { filter: { type: 'lighten', value: 0.04 } }
            },
            tooltip: { y: { formatter: yFormatter || function(v) { return v.toLocaleString('pt-PT'); } } }
        };
    }

    function singleBarOptions(seriesName, data, categories, yFormatter) {
        return {
            series: [{ name: seriesName, data: data }],
            chart: {
                type: 'bar',
                height: barChartHeight,
                fontFamily: 'inherit',
                toolbar: { show: false }
            },
            plotOptions: { bar: { borderRadius: 6, columnWidth: '75%' } },
            responsive: [{
                breakpoint: 768,
                options: {
                    plotOptions: { bar: { columnWidth: '88%' } },
                    grid: { padding: { top: 4, right: 0, left: 0 } },
                    xaxis: {
                        labels: {
                            style: { fontSize: '10px' },
                            rotate: -45,
                            rotateAlways: categories.length > 20
                        }
                    }
                }
            }],
            colors: [accentColor],
            dataLabels: { enabled: false },
            stroke: { show: true, width: 2, colors: ['transparent'] },
            xaxis: {
                categories: categories,
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { colors: mutedColor, fontSize: '11px' } }
            },
            yaxis: {
                labels: {
                    style: { colors: mutedColor, fontSize: '12px' },
                    formatter: yFormatter || function(v) { return v.toLocaleString('pt-PT'); }
                }
            },
            grid: { borderColor: borderColor, strokeDashArray: 4, xaxis: { lines: { show: false } }, padding: { top: 4, right: 8 } },
            legend: { show: false },
            tooltip: { y: { formatter: yFormatter || function(v) { return v.toLocaleString('pt-PT'); } } }
        };
    }

    function bindYearToggles(chart, chartElementId) {
        var toggleGroup = document.querySelector('[data-chart-target="' + chartElementId + '"]');
        if (!toggleGroup) {
            return;
        }

        toggleGroup.querySelectorAll('[data-series]').forEach(function(button) {
            button.addEventListener('click', function() {
                var seriesName = button.getAttribute('data-series');
                chart.toggleSeries(seriesName);
                button.classList.toggle('active');
            });
        });
    }

    var vendasAnoChart = new ApexCharts(document.querySelector('#resumoVendasAnoChart'), comparativeBarOptions(
        [
            { name: String(previousYear), data: @json($vendasAnoAnterior ?? []) },
            { name: String(currentYear), data: @json($vendasAnoAtual ?? []) }
        ],
        monthLabels,
        function(v) { return v.toLocaleString('pt-PT', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' €'; }
    ));
    vendasAnoChart.render();
    bindYearToggles(vendasAnoChart, 'resumoVendasAnoChart');

    var vendasMesChart = new ApexCharts(document.querySelector('#resumoVendasMesChart'), singleBarOptions(
        'Vendas',
        @json($vendasMesData ?? []),
        @json($vendasMesLabels ?? []),
        function(v) { return v.toLocaleString('pt-PT', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' €'; }
    ));
    vendasMesChart.render();

    var atendidosChart = new ApexCharts(document.querySelector('#resumoAtendidosChart'), comparativeBarOptions(
        [
            { name: String(previousYear), data: @json($atendidosAnoAnterior ?? []) },
            { name: String(currentYear), data: @json($atendidosAnoAtual ?? []) }
        ],
        monthLabels
    ));
    atendidosChart.render();
    bindYearToggles(atendidosChart, 'resumoAtendidosChart');

    var novosChart = new ApexCharts(document.querySelector('#resumoNovosChart'), comparativeBarOptions(
        [
            { name: String(previousYear), data: @json($novosAnoAnterior ?? []) },
            { name: String(currentYear), data: @json($novosAnoAtual ?? []) }
        ],
        monthLabels
    ));
    novosChart.render();
    bindYearToggles(novosChart, 'resumoNovosChart');

    var resumoBarCharts = {
        'resumo-chart-pane-vendas-ano': vendasAnoChart,
        'resumo-chart-pane-vendas-mes': vendasMesChart,
        'resumo-chart-pane-atendidos': atendidosChart,
        'resumo-chart-pane-novos': novosChart
    };
    document.querySelectorAll('#resumoChartTabs [data-bs-toggle="tab"]').forEach(function(tabEl) {
        tabEl.addEventListener('shown.bs.tab', function(event) {
            var paneId = event.target.getAttribute('data-bs-target');
            if (paneId && paneId.charAt(0) === '#') {
                paneId = paneId.slice(1);
            }
            var chart = resumoBarCharts[paneId];
            if (chart) {
                chart.updateOptions({}, false, true);
            }
        });
    });

    function contactDonutOptions(comCount, total, percent, colors) {
        var semCount = Math.max(0, total - comCount);
        var pctLabel = Number(percent).toLocaleString('pt-PT', { minimumFractionDigits: 0, maximumFractionDigits: 1 }) + '%';
        return {
            series: [comCount, semCount],
            chart: { type: 'donut', height: 240, fontFamily: 'inherit' },
            labels: ['Com dados', 'Sem dados'],
            colors: colors,
            plotOptions: {
                pie: {
                    donut: {
                        size: '72%',
                        labels: {
                            show: true,
                            name: { show: false },
                            value: {
                                show: true,
                                fontSize: '24px',
                                fontWeight: 700
                            },
                            total: {
                                show: true,
                                showAlways: true,
                                label: '\u00A0',
                                formatter: function() { return pctLabel; }
                            }
                        }
                    }
                }
            },
            dataLabels: { enabled: false },
            legend: { show: true, position: 'bottom', labels: { colors: mutedColor } },
            stroke: { width: 3, colors: ['var(--surface-color)'] }
        };
    }

    var totalClientes = @json((int) ($clientesContacto['total'] ?? 0));
    var pctTelemovel = @json($pctTelemovel);
    var pctEmail = @json($pctEmail);
    var pctAniversario = @json($pctAniversario);

    new ApexCharts(document.querySelector('#resumoTelemovelChart'), contactDonutOptions(
        @json((int) ($clientesContacto['com_telemovel'] ?? 0)),
        totalClientes,
        pctTelemovel,
        [successColor, borderColor]
    )).render();

    new ApexCharts(document.querySelector('#resumoEmailChart'), contactDonutOptions(
        @json((int) ($clientesContacto['com_email'] ?? 0)),
        totalClientes,
        pctEmail,
        [accentColor, borderColor]
    )).render();

    new ApexCharts(document.querySelector('#resumoAniversarioChart'), contactDonutOptions(
        @json((int) ($clientesContacto['com_aniversario'] ?? 0)),
        totalClientes,
        pctAniversario,
        [warningColor, borderColor]
    )).render();
});
</script>
@endsection
