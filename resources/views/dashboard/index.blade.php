@extends('partials.layouts.main')
@section('title', 'Dashboard - Marcações | Beauty CRM')

@section('css')
<style>
    .dash-marcacoes-tables .card-header {
        padding: 0.75rem 1rem;
    }
    .dash-marcacoes-tables .card-title {
        font-size: 0.9375rem;
        margin-bottom: 0;
    }
    .dash-marcacoes-tables .dash-compact-table {
        font-size: 0.8125rem;
        margin-bottom: 0;
    }
    .dash-marcacoes-tables .dash-compact-table th {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        color: var(--muted-color);
        white-space: nowrap;
        padding: 0.5rem 0.75rem;
        border-bottom-width: 1px;
    }
    .dash-marcacoes-tables .dash-compact-table td {
        padding: 0.5rem 0.75rem;
        vertical-align: middle;
    }
    .dash-marcacoes-tables .dash-compact-table .text-num {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .dash-confirmacao-panel[hidden] {
        display: none !important;
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
    <div class="d-flex align-items-center justify-content-between gap-3 w-100">
        <div class="dash-welcome-content">
            <h2 class="dash-welcome-title mb-0">Marcações</h2>
        </div>
        <div class="dash-welcome-actions flex-shrink-0">
            <a href="{{ route('agenda.index') }}" class="btn btn-primary">
                <i class="ph ph-calendar-blank me-2"></i> Ver Agenda
            </a>
        </div>
    </div>
</div>

<!-- KPI Strip -->
<div class="dash-kpi-strip mb-4">
    <div class="dash-kpi">
        <div class="dash-kpi-icon success">
            <i class="ph-duotone ph-calendar-check"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $marcacoesHoje ?? 0 }}</div>
            <div class="dash-kpi-label">Marcações hoje</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon primary">
            <i class="ph-duotone ph-currency-eur"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ number_format($receitaHoje ?? 0, 0, ',', '.') }} €</div>
            <div class="dash-kpi-label">Receita hoje</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon warning">
            <i class="ph-duotone ph-calendar-dots"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $marcacoesEsteMes ?? 0 }}</div>
            <div class="dash-kpi-label">Marcações este mês</div>
        </div>
        @if(isset($variacaoMarcacoes) && $variacaoMarcacoes != 0)
        <div class="dash-kpi-trend {{ $variacaoMarcacoes > 0 ? 'positive' : 'negative' }}">
            <i class="bi bi-trending-{{ $variacaoMarcacoes > 0 ? 'up' : 'down' }}"></i>
            <span>{{ $variacaoMarcacoes > 0 ? '+' : '' }}{{ $variacaoMarcacoes }}%</span>
        </div>
        @endif
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon info">
            <i class="ph-duotone ph-currency-eur"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ number_format($receitaEsteMes ?? 0, 0, ',', '.') }} €</div>
            <div class="dash-kpi-label">Receita este mês</div>
        </div>
        @if(isset($variacaoReceita) && $variacaoReceita != 0)
        <div class="dash-kpi-trend {{ $variacaoReceita > 0 ? 'positive' : 'negative' }}">
            <i class="bi bi-trending-{{ $variacaoReceita > 0 ? 'up' : 'down' }}"></i>
            <span>{{ $variacaoReceita > 0 ? '+' : '' }}{{ $variacaoReceita }}%</span>
        </div>
        @endif
    </div>
</div>

@php
    $confirmacaoPorPeriodo = $confirmacaoPorPeriodo ?? [
        'hoje' => ['confirmadas' => 0, 'nao_confirmadas' => 0, 'total' => 0, 'pct_confirmadas' => 0, 'pct_nao_confirmadas' => 0],
        'amanha' => ['confirmadas' => 0, 'nao_confirmadas' => 0, 'total' => 0, 'pct_confirmadas' => 0, 'pct_nao_confirmadas' => 0],
        'semana' => ['confirmadas' => 0, 'nao_confirmadas' => 0, 'total' => 0, 'pct_confirmadas' => 0, 'pct_nao_confirmadas' => 0],
        'mes' => ['confirmadas' => 0, 'nao_confirmadas' => 0, 'total' => 0, 'pct_confirmadas' => 0, 'pct_nao_confirmadas' => 0],
    ];
@endphp
<div class="card mb-4">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h5 class="card-title mb-0">Confirmadas vs não confirmadas</h5>
        <div class="dash-chart-tabs" id="dashConfirmacaoTabs" role="tablist">
            <button type="button" class="dash-chart-tab active" data-confirmacao-period="hoje" aria-selected="true">Hoje</button>
            <button type="button" class="dash-chart-tab" data-confirmacao-period="amanha" aria-selected="false">Amanhã</button>
            <button type="button" class="dash-chart-tab" data-confirmacao-period="semana" aria-selected="false">Semana</button>
            <button type="button" class="dash-chart-tab" data-confirmacao-period="mes" aria-selected="false">Mês</button>
        </div>
    </div>
    <div class="card-body">
        @foreach (['hoje' => 'Hoje', 'amanha' => 'Amanhã', 'semana' => 'Esta semana', 'mes' => 'Este mês'] as $periodKey => $periodLabel)
            @php $c = $confirmacaoPorPeriodo[$periodKey]; @endphp
            <div class="dash-confirmacao-panel" data-confirmacao-panel="{{ $periodKey }}" @if($periodKey !== 'hoje') hidden @endif>
                <div class="d-flex gap-4 flex-wrap align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="dash-traffic-dot" style="--dot-color: var(--success-color)"></span>
                        <span><strong>{{ $c['confirmadas'] }}</strong> confirmadas ({{ $c['pct_confirmadas'] }}%)</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="dash-traffic-dot" style="--dot-color: var(--warning-color)"></span>
                        <span><strong>{{ $c['nao_confirmadas'] }}</strong> não confirmadas ({{ $c['pct_nao_confirmadas'] }}%)</span>
                    </div>
                    <div class="text-muted small ms-md-auto">{{ $c['total'] }} marcações · {{ $periodLabel }}</div>
                </div>
                <div class="progress mt-3" style="height: 1.5rem;">
                    @if ($c['total'] > 0)
                        <div class="progress-bar bg-success" style="width: {{ $c['pct_confirmadas'] }}%">{{ $c['pct_confirmadas'] }}%</div>
                        <div class="progress-bar bg-warning text-dark" style="width: {{ $c['pct_nao_confirmadas'] }}%">{{ $c['pct_nao_confirmadas'] }}%</div>
                    @else
                        <div class="progress-bar bg-secondary" style="width: 100%">Sem marcações</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Main Grid: Charts Row -->
<div class="dash-grid dash-grid-charts mb-4">
    <div class="card dash-chart-main">
        <div class="card-header">
            <h5 class="card-title">Receita e Marcações (últimos 12 meses)</h5>
            <div class="card-actions">
                <div class="dash-chart-tabs">
                    <button class="dash-chart-tab active" data-period="revenue">Receita</button>
                    <button class="dash-chart-tab" data-period="bookings">Marcações</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-container" id="revenueBarChart"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Estado das marcações</h5>
        </div>
        <div class="card-body">
            @php
                $statusLabels = \App\Models\CalendarEvent::statuses();
                $porEstado = $porEstado ?? [];
                $totalEstado = array_sum($porEstado);
            @endphp
            <div class="chart-container" id="estadoDonutChart"></div>
            <div class="dash-traffic-legend mt-2">
                @foreach($statusLabels as $status => $label)
                    @if(isset($porEstado[$status]) && $porEstado[$status] > 0)
                        @php
                            $percent = $totalEstado > 0 ? round(($porEstado[$status] / $totalEstado) * 100) : 0;
                            $colors = ['primary' => 'primary', 'agendado' => 'info', 'confirmado' => 'success', 'chegou' => 'warning', 'iniciado' => 'primary', 'faltou' => 'warning', 'cancelado' => 'danger'];
                            $colorClass = $colors[$status] ?? 'secondary';
                        @endphp
                        <div class="dash-traffic-item">
                            <span class="dash-traffic-dot" style="--dot-color: var(--{{ $colorClass }}-color)"></span>
                            <span class="dash-traffic-name">{{ $label }}</span>
                            <span class="dash-traffic-val">{{ $porEstado[$status] }} ({{ $percent }}%)</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>

<!-- Tabelas: 2×2 -->
<div class="row g-3 mb-4 dash-marcacoes-tables">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title">Próximas marcações</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table dash-compact-table">
                        <thead>
                            <tr>
                                <th>Quando</th>
                                <th>Cliente</th>
                                <th>Serviço</th>
                                <th>Téc.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($proximasMarcacoes ?? [] as $ev)
                                <tr>
                                    <td class="text-nowrap">
                                        {{ \App\Support\DateTimeDisplay::marcacao($ev->start_at, $ev->store_id, 'd/m H:i') }}
                                    </td>
                                    <td>{{ $ev->client?->name ?? '—' }}</td>
                                    <td class="text-truncate" style="max-width: 10rem;">{{ $ev->eventServices->map(fn ($s) => trim((string) ($s->pivot->option_name ?? '')) !== '' ? $s->pivot->option_name : $s->name)->filter()->join(', ') ?: '—' }}</td>
                                    <td class="text-truncate" style="max-width: 7rem;">{{ $ev->user?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Nenhuma marcação próxima.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title">Marcações recentes</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table dash-compact-table">
                        <thead>
                            <tr>
                                <th>Quando</th>
                                <th>Cliente</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($marcacoesRecentes ?? [] as $ev)
                                <tr>
                                    <td class="text-nowrap">{{ \App\Support\DateTimeDisplay::marcacao($ev->start_at, $ev->store_id, 'd/m H:i') }}</td>
                                    <td>{{ $ev->client?->name ?? '—' }}</td>
                                    <td>{{ \App\Models\CalendarEvent::statuses()[$ev->status ?? 'agendado'] ?? $ev->status }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">Nenhuma marcação recente.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title">Por serviço</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table dash-compact-table">
                        <thead>
                            <tr>
                                <th>Serviço</th>
                                <th class="text-end">Marc.</th>
                                <th class="text-end">Receita</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($porServico ?? [] as $row)
                                <tr>
                                    <td class="text-truncate" style="max-width: 12rem;">{{ $row->service_name }}</td>
                                    <td class="text-end text-num">{{ $row->total }}</td>
                                    <td class="text-end text-num">{{ number_format((float) $row->receita, 0, ',', '.') }} €</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">Sem dados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title">Por técnico</h5>
            </div>
            <div class="card-body p-0">
                @php
                    $porTecnico = $porTecnico ?? collect();
                    $receitaPorTecnico = $receitaPorTecnico ?? collect();
                @endphp
                <div class="table-responsive">
                    <table class="table dash-compact-table">
                        <thead>
                            <tr>
                                <th>Técnico</th>
                                <th class="text-end">Marc.</th>
                                <th class="text-end">Receita</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($porTecnico as $row)
                                @php
                                    $rec = $receitaPorTecnico->get($row->user_id);
                                    $recVal = $rec ? (float) $rec->receita : 0;
                                @endphp
                                <tr>
                                    <td class="text-truncate" style="max-width: 12rem;">{{ $row->user?->name ?? '—' }}</td>
                                    <td class="text-end text-num">{{ $row->total }}</td>
                                    <td class="text-end text-num">{{ number_format($recVal, 0, ',', '.') }} €</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">Sem dados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="dash-grid dash-grid-2x2">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Ações rápidas</h5>
        </div>
        <div class="card-body p-0">
            <div class="task-list">
                <div class="task-item">
                    <div class="task-info">
                        <div class="task-title">
                            <a href="{{ route('clientes.create') }}" class="text-body">
                                <i class="ph ph-user-plus me-2"></i> Adicionar cliente
                            </a>
                        </div>
                    </div>
                </div>
                <div class="task-item">
                    <div class="task-info">
                        <div class="task-title">
                            <a href="{{ route('clientes.index') }}" class="text-body">
                                <i class="ph ph-users me-2"></i> Lista de clientes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Resumo do dia</h5>
        </div>
        <div class="card-body">
            <div class="dash-targets">
                <div class="dash-target">
                    <div class="dash-target-header">
                        <span class="dash-target-label">Marcações hoje</span>
                        <span class="dash-target-value">{{ $marcacoesHoje ?? 0 }}</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: {{ min(($marcacoesHoje ?? 0) * 10, 100) }}%"></div>
                    </div>
                </div>
                <div class="dash-target">
                    <div class="dash-target-header">
                        <span class="dash-target-label">Receita hoje</span>
                        <span class="dash-target-value">{{ number_format($receitaHoje ?? 0, 0, ',', '.') }} €</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" style="width: 50%"></div>
                    </div>
                </div>
                <div class="dash-target">
                    <div class="dash-target-header">
                        <span class="dash-target-label">Esta semana</span>
                        <span class="dash-target-value">{{ $marcacoesEstaSemana ?? 0 }} marcações</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-info" style="width: 50%"></div>
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
    const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent-color').trim() || '#6366f1';
    const successColor = getComputedStyle(document.documentElement).getPropertyValue('--success-color').trim() || '#10b981';
    const warningColor = getComputedStyle(document.documentElement).getPropertyValue('--warning-color').trim() || '#f59e0b';
    const infoColor = getComputedStyle(document.documentElement).getPropertyValue('--info-color').trim() || '#3b82f6';
    const dangerColor = getComputedStyle(document.documentElement).getPropertyValue('--danger-color').trim() || '#ef4444';
    const borderColor = getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() || '#e5e7eb';
    const mutedColor = getComputedStyle(document.documentElement).getPropertyValue('--muted-color').trim() || '#6b7280';

    var revenueData = {
        revenue: {
            series: [{ name: 'Receita (€)', data: [@foreach($mensalReceita ?? [] as $d){{ $d['revenue'] }},@endforeach] }],
            categories: [@foreach($mensalReceita ?? [] as $d)'{{ $d['month'] }}',@endforeach]
        },
        bookings: {
            series: [{ name: 'Marcações', data: [@foreach($mensalMarcacoes ?? [] as $d){{ $d['count'] }},@endforeach] }],
            categories: [@foreach($mensalMarcacoes ?? [] as $d)'{{ $d['month'] }}',@endforeach]
        }
    };

    var barOptions = {
        series: revenueData.revenue.series,
        chart: { type: 'bar', height: 320, fontFamily: 'inherit', toolbar: { show: false }, animations: { enabled: true, easing: 'easeinout', speed: 400 } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
        colors: [accentColor],
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: { categories: revenueData.revenue.categories, axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: mutedColor, fontSize: '12px' } } },
        yaxis: { labels: { style: { colors: mutedColor, fontSize: '12px' }, formatter: function(v) { return v.toLocaleString('pt-PT'); } } },
        grid: { borderColor: borderColor, strokeDashArray: 4, xaxis: { lines: { show: false } } },
        tooltip: { y: { formatter: function(v) { return v.toLocaleString('pt-PT') + ' €'; } } }
    };

    var barChart = new ApexCharts(document.querySelector('#revenueBarChart'), barOptions);
    barChart.render();

    document.querySelectorAll('.dash-chart-tab[data-period]').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.dash-chart-tab[data-period]').forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            var period = this.getAttribute('data-period');
            var data = revenueData[period] || revenueData.revenue;
            barChart.updateOptions({ xaxis: { categories: data.categories } }, false, false);
            barChart.updateSeries(data.series);
            if (period === 'bookings') barChart.updateOptions({ tooltip: { y: { formatter: function(v) { return v; } } } });
            else barChart.updateOptions({ tooltip: { y: { formatter: function(v) { return v.toLocaleString('pt-PT') + ' €'; } } } });
        });
    });

    var confirmacaoTabs = document.getElementById('dashConfirmacaoTabs');
    if (confirmacaoTabs) {
        confirmacaoTabs.querySelectorAll('[data-confirmacao-period]').forEach(function(tab) {
            tab.addEventListener('click', function() {
                var period = this.getAttribute('data-confirmacao-period');
                confirmacaoTabs.querySelectorAll('[data-confirmacao-period]').forEach(function(t) {
                    t.classList.toggle('active', t === tab);
                    t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
                });
                document.querySelectorAll('[data-confirmacao-panel]').forEach(function(panel) {
                    if (panel.getAttribute('data-confirmacao-panel') === period) {
                        panel.removeAttribute('hidden');
                    } else {
                        panel.setAttribute('hidden', '');
                    }
                });
            });
        });
    }

    @php
        $estadoLabels = [];
        $estadoSeries = [];
        foreach (\App\Models\CalendarEvent::statuses() as $status => $label) {
            if (isset($porEstado[$status]) && $porEstado[$status] > 0) {
                $estadoLabels[] = $label;
                $estadoSeries[] = $porEstado[$status];
            }
        }
        if (empty($estadoSeries)) { $estadoLabels = ['Sem dados']; $estadoSeries = [1]; }
    @endphp
    var donutOptions = {
        series: [@foreach($estadoSeries as $v){{ $v }},@endforeach],
        chart: { type: 'donut', height: 240, fontFamily: 'inherit' },
        labels: [@foreach($estadoLabels as $l)'{{ addslashes($l) }}',@endforeach],
        colors: [accentColor, successColor, infoColor, warningColor, dangerColor, '#8b5cf6'],
        plotOptions: {
            pie: {
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        total: { show: true, label: 'Total', formatter: function() { return '{{ $totalEstado ?? 0 }}'; } }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        legend: { show: false },
        stroke: { width: 3, colors: ['var(--surface-color)'] }
    };
    var donutChart = new ApexCharts(document.querySelector('#estadoDonutChart'), donutOptions);
    donutChart.render();

    document.addEventListener('themeChanged', function() {
        var newBorder = getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim();
        var newMuted = getComputedStyle(document.documentElement).getPropertyValue('--muted-color').trim();
        barChart.updateOptions({ grid: { borderColor: newBorder }, xaxis: { labels: { style: { colors: newMuted } } }, yaxis: { labels: { style: { colors: newMuted } } } });
    });
});
</script>
@endsection
