@extends('partials.layouts.main')
@section('title', 'Dashboard | Beauty CRM')
@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Welcome Banner -->
<div class="dash-welcome mb-4">
    <div class="dash-welcome-content">
        <h2 class="dash-welcome-title">Bem-vindo de volta, {{ auth()->user()->name }}</h2>
        <p class="dash-welcome-text">Visão geral das marcações e dos seus serviços.</p>
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
        <a href="{{ route('agenda.index') }}" class="btn btn-primary ms-2">
            <i class="ph ph-calendar-blank me-2"></i> Ver Agenda
        </a>
    </div>
</div>

<!-- KPI Strip -->
<div class="dash-kpi-strip mb-4">
    <div class="dash-kpi">
        <div class="dash-kpi-icon primary">
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
            <i class="ph-duotone ph-users-three"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $totalClientes ?? 0 }}</div>
            <div class="dash-kpi-label">Clientes ativos</div>
        </div>
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

<!-- Grid: Próximas marcações + Top serviços -->
<div class="dash-grid dash-grid-content mb-4">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Próximas marcações</h5>
            <div class="card-actions">
                <a href="{{ route('agenda.index') }}" class="btn btn-sm btn-outline-primary">Ver Agenda</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table dash-table">
                    <thead>
                        <tr>
                            <th>Data / Hora</th>
                            <th>Cliente</th>
                            <th>Serviço</th>
                            <th>Técnico</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($proximasMarcacoes ?? [] as $ev)
                            <tr>
                                <td>
                                    <span class="fw-medium">{{ $ev->start_at->format('d/m') }}</span>
                                    <span class="text-muted small">{{ $ev->start_at->format('H:i') }}</span>
                                </td>
                                <td>{{ $ev->client?->name ?? '—' }}</td>
                                <td>{{ $ev->eventServices->pluck('name')->filter()->join(', ') ?: '—' }}</td>
                                <td>{{ $ev->user?->name ?? '—' }}</td>
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

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Marcações por serviço</h5>
            <div class="card-actions">
                <a href="{{ route('agenda.index') }}" class="btn-icon" title="Ver agenda"><i class="bi bi-arrow-up-right"></i></a>
            </div>
        </div>
        <div class="card-body">
            <div class="region-list">
                @php
                    $porServico = $porServico ?? collect();
                    $maxServico = $porServico->max('total') ?: 1;
                @endphp
                @forelse($porServico as $row)
                    @php $pct = $maxServico > 0 ? round(($row->total / $maxServico) * 100) : 0; @endphp
                    <div class="region-item">
                        <div class="region-info">
                            <span class="region-name">{{ $row->service_name }}</span>
                        </div>
                        <div class="region-stats">
                            <div class="progress region-progress">
                                <div class="progress-bar" style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="region-value">{{ $row->total }} marcações · {{ number_format((float)$row->receita, 0, ',', '.') }} €</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">Nenhum dado ainda.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Grid: Por técnico + Marcações recentes -->
<div class="dash-grid dash-grid-bottom mb-4">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Marcações por técnico</h5>
        </div>
        <div class="card-body">
            <div class="region-list">
                @php
                    $porTecnico = $porTecnico ?? collect();
                    $receitaPorTecnico = $receitaPorTecnico ?? collect();
                    $maxTec = $porTecnico->max('total') ?: 1;
                @endphp
                @forelse($porTecnico as $row)
                    @php
                        $pct = $maxTec > 0 ? round(($row->total / $maxTec) * 100) : 0;
                        $rec = $receitaPorTecnico->get($row->user_id);
                        $recVal = $rec ? (float)$rec->receita : 0;
                    @endphp
                    <div class="region-item">
                        <div class="region-info">
                            <span class="region-name">{{ $row->user?->name ?? 'N/A' }}</span>
                        </div>
                        <div class="region-stats">
                            <div class="progress region-progress">
                                <div class="progress-bar bg-success" style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="region-value">{{ $row->total }} marcações · {{ number_format($recVal, 0, ',', '.') }} €</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">Nenhum dado ainda.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Marcações recentes</h5>
            <div class="card-actions">
                <a href="{{ route('agenda.index') }}" class="btn-icon" title="Ver agenda"><i class="bi bi-arrow-up-right"></i></a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="transaction-list" style="padding: var(--spacing-md) var(--spacing-lg);">
                @forelse($marcacoesRecentes ?? [] as $ev)
                    <div class="transaction-item">
                        <div class="transaction-icon info">
                            <i class="ph-duotone ph-calendar-check"></i>
                        </div>
                        <div class="transaction-details">
                            <div class="transaction-title">{{ $ev->client?->name ?? '—' }}</div>
                            <div class="transaction-meta">{{ $ev->start_at->format('d/m/Y H:i') }} · {{ $ev->eventServices->pluck('name')->filter()->join(', ') ?: '—' }}</div>
                        </div>
                        <div class="transaction-amount">{{ \App\Models\CalendarEvent::statuses()[$ev->status ?? 'agendado'] ?? $ev->status }}</div>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">Nenhuma marcação recente.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="dash-grid dash-grid-2x2">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Ações rápidas</h5>
            <div class="card-actions">
                <a href="{{ route('agenda.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="ph ph-calendar-blank me-1"></i> Agenda
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="task-list">
                <div class="task-item">
                    <div class="task-info">
                        <div class="task-title">
                            <a href="{{ route('agenda.index') }}" class="text-body">
                                <i class="ph ph-calendar-blank me-2"></i> Ver Agenda
                            </a>
                        </div>
                    </div>
                </div>
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
    var dateEl = document.getElementById('dashDate');
    var timeEl = document.getElementById('dashTime');
    function updateDateTime() {
        var now = new Date();
        if (dateEl) dateEl.textContent = now.toLocaleDateString('pt-PT', { month: 'short', day: 'numeric', year: 'numeric' });
        if (timeEl) timeEl.textContent = now.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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

    document.querySelectorAll('.dash-chart-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.dash-chart-tab').forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            var period = this.getAttribute('data-period');
            var data = revenueData[period] || revenueData.revenue;
            barChart.updateOptions({ xaxis: { categories: data.categories } }, false, false);
            barChart.updateSeries(data.series);
            if (period === 'bookings') barChart.updateOptions({ tooltip: { y: { formatter: function(v) { return v; } } } });
            else barChart.updateOptions({ tooltip: { y: { formatter: function(v) { return v.toLocaleString('pt-PT') + ' €'; } } } });
        });
    });

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
