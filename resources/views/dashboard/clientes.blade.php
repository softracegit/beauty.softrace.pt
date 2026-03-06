@extends('partials.layouts.main')
@section('title', 'Dashboard - Clientes | Beauty CRM')
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
        <h2 class="dash-welcome-title">Dashboard de Clientes</h2>
        <p class="dash-welcome-text">Métricas baseadas em marcações e retenção.</p>
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
            <div class="dash-kpi-value">{{ $totalClientes ?? 0 }}</div>
            <div class="dash-kpi-label">Total Clientes (ativos)</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon success">
            <i class="ph-duotone ph-calendar-check"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $totalClientesComMarcacao ?? 0 }}</div>
            <div class="dash-kpi-label">Com marcações</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon info">
            <i class="ph-duotone ph-user-plus"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $clientesEsteMes ?? 0 }}</div>
            <div class="dash-kpi-label">Registados este mês</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon warning">
            <i class="ph-duotone ph-repeat"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $taxaRetencao ?? 0 }}%</div>
            <div class="dash-kpi-label">Taxa de retenção</div>
        </div>
    </div>
</div>

<!-- Marcações este mês: Novos vs Recorrentes -->
<div class="dash-grid dash-grid-charts mb-4">
    <div class="card dash-chart-main">
        <div class="card-header">
            <h5 class="card-title">Marcações este mês: clientes novos vs recorrentes</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">Marcações de clientes que marcaram pela 1.ª vez este mês vs clientes que já tinham histórico.</p>
            <div class="d-flex gap-4 flex-wrap align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="dash-traffic-dot" style="--dot-color: var(--info-color)"></span>
                    <span><strong>{{ $marcacoesNovosClientes ?? 0 }}</strong> novas (1.ª vez no período)</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="dash-traffic-dot" style="--dot-color: var(--success-color)"></span>
                    <span><strong>{{ $marcacoesRecorrentes ?? 0 }}</strong> recorrentes</span>
                </div>
            </div>
            @php
                $totalMarcMes = ($marcacoesNovosClientes ?? 0) + ($marcacoesRecorrentes ?? 0);
                $pctNovos = $totalMarcMes > 0 ? round((($marcacoesNovosClientes ?? 0) / $totalMarcMes) * 100) : 0;
                $pctRec = $totalMarcMes > 0 ? round((($marcacoesRecorrentes ?? 0) / $totalMarcMes) * 100) : 0;
            @endphp
            <div class="progress mt-3" style="height: 1.5rem;">
                <div class="progress-bar bg-info" style="width: {{ $pctNovos }}%">{{ $pctNovos }}%</div>
                <div class="progress-bar bg-success" style="width: {{ $pctRec }}%">{{ $pctRec }}%</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Intervalo médio entre visitas</h5>
        </div>
        <div class="card-body d-flex align-items-center justify-content-center flex-column">
            @if(isset($intervaloMedioDias) && $intervaloMedioDias !== null)
                <div class="display-4 fw-bold text-primary">{{ number_format($intervaloMedioDias, 0) }}</div>
                <div class="text-muted">dias</div>
                <p class="small text-muted mt-2 mb-0">Média para clientes com 2+ marcações</p>
            @else
                <p class="text-muted mb-0">Dados insuficientes (é preciso pelo menos um cliente com 2+ marcações).</p>
            @endif
        </div>
    </div>
</div>

<!-- Gráfico crescimento + Distribuição -->
<div class="dash-grid dash-grid-charts mb-4">
    <div class="card dash-chart-main">
        <div class="card-header">
            <h5 class="card-title">Novos clientes (últimos 6 meses)</h5>
        </div>
        <div class="card-body">
            <div class="chart-container" id="growthChart"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Retenção</h5>
        </div>
        <div class="card-body">
            <div class="chart-container" id="retencaoDonutChart"></div>
            <div class="dash-traffic-legend mt-2">
                <div class="dash-traffic-item">
                    <span class="dash-traffic-dot" style="--dot-color: var(--success-color)"></span>
                    <span class="dash-traffic-name">Com 2+ marcações</span>
                    <span class="dash-traffic-val">{{ $taxaRetencao ?? 0 }}%</span>
                </div>
                <div class="dash-traffic-item">
                    <span class="dash-traffic-dot" style="--dot-color: var(--muted-color)"></span>
                    <span class="dash-traffic-name">Apenas 1 marcação</span>
                    <span class="dash-traffic-val">{{ 100 - ($taxaRetencao ?? 0) }}%</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Top clientes por marcações e por receita -->
<div class="dash-grid dash-grid-content mb-4">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Top clientes por marcações</h5>
            <div class="card-actions">
                <a href="{{ route('clientes.index') }}" class="btn btn-sm btn-outline-primary">Ver Todos</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table dash-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Marcações</th>
                            <th>Receita</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topClientesPorMarcacoes ?? [] as $c)
                            <tr>
                                <td>
                                    <a href="{{ route('clientes.show', $c) }}" class="fw-medium">{{ $c->name }}</a>
                                    @if($c->email)<br><span class="small text-muted">{{ $c->email }}</span>@endif
                                </td>
                                <td>{{ $c->marcacoes_count ?? 0 }}</td>
                                <td>{{ number_format($c->receita ?? 0, 0, ',', '.') }} €</td>
                                <td>
                                    <a href="{{ route('clientes.show', $c) }}" class="btn btn-sm btn-light" title="Ver"><i class="ph ph-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Nenhum cliente com marcações.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Top clientes por receita</h5>
            <div class="card-actions">
                <a href="{{ route('clientes.index') }}" class="btn btn-sm btn-outline-primary">Ver Todos</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table dash-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Receita</th>
                            <th>Marcações</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topClientesPorReceita ?? [] as $c)
                            <tr>
                                <td>
                                    <a href="{{ route('clientes.show', $c) }}" class="fw-medium">{{ $c->name }}</a>
                                    @if($c->email)<br><span class="small text-muted">{{ $c->email }}</span>@endif
                                </td>
                                <td class="fw-semibold">{{ number_format($c->receita ?? 0, 0, ',', '.') }} €</td>
                                <td>{{ $c->marcacoes_count ?? 0 }}</td>
                                <td>
                                    <a href="{{ route('clientes.show', $c) }}" class="btn btn-sm btn-light" title="Ver"><i class="ph ph-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Nenhum cliente com receita.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Clientes recentes -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Clientes recentes</h5>
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
                        <th>Data Registo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentClients ?? [] as $client)
                        <tr>
                            <td><a href="{{ route('clientes.show', $client) }}" class="fw-medium">{{ $client->client_id }}</a></td>
                            <td>{{ $client->name }}</td>
                            <td>{{ $client->email ?? '—' }}</td>
                            <td>{{ $client->phone ?? '—' }}</td>
                            <td>{{ $client->created_at->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ route('clientes.show', $client) }}" class="btn btn-sm btn-light" title="Ver"><i class="ph ph-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">Nenhum cliente encontrado.</td>
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
    const mutedColor = getComputedStyle(document.documentElement).getPropertyValue('--muted-color').trim() || '#6b7280';
    const borderColor = getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() || '#e5e7eb';

    var growthData = @json($monthlyGrowth ?? []);
    var categories = growthData.map(function(d) { return d.month; });
    var counts = growthData.map(function(d) { return d.count; });

    var growthOptions = {
        series: [{ name: 'Novos Clientes', data: counts }],
        chart: { type: 'line', height: 320, fontFamily: 'inherit', toolbar: { show: false } },
        stroke: { curve: 'smooth', width: 3 },
        colors: [accentColor],
        xaxis: { categories: categories, labels: { style: { colors: mutedColor } } },
        yaxis: { labels: { style: { colors: mutedColor } } },
        grid: { borderColor: borderColor, strokeDashArray: 4 },
        markers: { size: 5, colors: [accentColor], strokeColors: accentColor, strokeWidth: 2 }
    };
    var growthChart = new ApexCharts(document.querySelector('#growthChart'), growthOptions);
    growthChart.render();

    var taxaRetencao = {{ $taxaRetencao ?? 0 }};
    var retencaoOptions = {
        series: [taxaRetencao, 100 - taxaRetencao],
        chart: { type: 'donut', height: 240, fontFamily: 'inherit' },
        labels: ['Com 2+ marcações', 'Apenas 1 marcação'],
        colors: [successColor, mutedColor],
        plotOptions: {
            pie: {
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        total: { show: true, label: 'Retenção', formatter: function() { return taxaRetencao + '%'; } }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        legend: { show: false },
        stroke: { width: 3, colors: ['var(--surface-color)'] }
    };
    var retencaoChart = new ApexCharts(document.querySelector('#retencaoDonutChart'), retencaoOptions);
    retencaoChart.render();
});
</script>
@endsection
