@extends('partials.layouts.main')
@section('title', 'Dashboard - Ocupação | Beauty CRM')
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
        <h2 class="dash-welcome-title">Ocupação</h2>
        <p class="dash-welcome-text">Taxa de ocupação, horários de pico, dias mais ocupados e duração média. Regras: slots de 60 min, 9h–19h, Seg–Sáb.</p>
    </div>
    <div class="dash-welcome-actions">
        <a href="{{ route('agenda.index') }}" class="btn btn-primary">
            <i class="ph ph-calendar-blank me-2"></i> Ver Agenda
        </a>
    </div>
</div>

<!-- KPI Strip -->
<div class="dash-kpi-strip mb-4">
    <div class="dash-kpi">
        <div class="dash-kpi-icon primary">
            <i class="ph-duotone ph-chart-pie-slice"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $taxaOcupacaoMes ?? 0 }}%</div>
            <div class="dash-kpi-label">Ocupação este mês</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon success">
            <i class="ph-duotone ph-chart-bar"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $taxaOcupacaoSemana ?? 0 }}%</div>
            <div class="dash-kpi-label">Ocupação esta semana</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon info">
            <i class="ph-duotone ph-squares-four"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ number_format($filledSlotsMonth ?? 0, 0, ',', '.') }} / {{ number_format($totalSlotsMonth ?? 0, 0, ',', '.') }}</div>
            <div class="dash-kpi-label">Slots preenchidos / total (mês)</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon warning">
            <i class="ph-duotone ph-clock"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $duracaoMediaGeral ? round($duracaoMediaGeral) : '—' }}{{ $duracaoMediaGeral ? ' min' : '' }}</div>
            <div class="dash-kpi-label">Duração média (marcação)</div>
        </div>
    </div>
</div>

<!-- Horários de pico + Dias mais ocupados -->
<div class="dash-grid dash-grid-charts mb-4">
    <div class="card dash-chart-main">
        <div class="card-header">
            <h5 class="card-title">Horários de pico (marcações por hora do dia)</h5>
        </div>
        <div class="card-body">
            <div class="chart-container" id="picoHorasChart"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Dias mais ocupados (por dia da semana)</h5>
        </div>
        <div class="card-body">
            <div class="chart-container" id="diasOcupadosChart"></div>
        </div>
    </div>
</div>

<!-- Duração média por serviço + Ocupação por técnico -->
<div class="dash-grid dash-grid-content mb-4">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Duração média por serviço</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table dash-table">
                    <thead>
                        <tr>
                            <th>Serviço</th>
                            <th>Marcações</th>
                            <th>Duração média</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($duracaoMediaPorServico ?? [] as $row)
                            <tr>
                                <td>{{ $row->service_name }}</td>
                                <td>{{ $row->qtd }}</td>
                                <td>{{ round($row->media_min) }} min</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">Nenhum dado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Ocupação por técnico (este mês)</h5>
        </div>
        <div class="card-body">
            <div class="region-list">
                @forelse($ocupacaoPorTecnico ?? [] as $t)
                    <div class="region-item">
                        <div class="region-info">
                            <span class="region-name">{{ $t->name }}</span>
                        </div>
                        <div class="region-stats">
                            <div class="progress region-progress">
                                <div class="progress-bar" style="width: {{ min($t->taxa, 100) }}%"></div>
                            </div>
                            <span class="region-value">{{ $t->taxa }}% ({{ $t->filled_slots }}/{{ $t->total_slots }} slots)</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">Nenhum técnico ativo ou sem dados no período.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Resumo adicional -->
<div class="dash-grid dash-grid-2x2 mb-4">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Resumo do mês</h5>
        </div>
        <div class="card-body">
            <div class="dash-targets">
                <div class="dash-target">
                    <div class="dash-target-header">
                        <span class="dash-target-label">Total de marcações</span>
                        <span class="dash-target-value">{{ $totalMarcacoesMes ?? 0 }}</span>
                    </div>
                </div>
                <div class="dash-target">
                    <div class="dash-target-header">
                        <span class="dash-target-label">Horas de trabalho (estimada)</span>
                        <span class="dash-target-value">{{ $horasTrabalhoMes ?? 0 }} h</span>
                    </div>
                    <p class="small text-muted mb-0">Soma das durações de todas as marcações do mês.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Regras de slots</h5>
        </div>
        <div class="card-body">
            <ul class="list-unstyled mb-0 small">
                <li><strong>Slot:</strong> 60 min</li>
                <li><strong>Horário considerado:</strong> 9h–19h</li>
                <li><strong>Dias úteis:</strong> Segunda a Sábado</li>
                <li><strong>Slots por dia por técnico:</strong> 10</li>
            </ul>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent-color').trim() || '#6366f1';
    var successColor = getComputedStyle(document.documentElement).getPropertyValue('--success-color').trim() || '#10b981';
    var mutedColor = getComputedStyle(document.documentElement).getPropertyValue('--muted-color').trim() || '#6b7280';
    var borderColor = getComputedStyle(document.documentElement).getPropertyValue('--border-color').trim() || '#e5e7eb';

    var porHora = @json($porHora ?? array_fill(0, 24, 0));
    var categoriesHora = [];
    for (var h = 0; h < 24; h++) {
        categoriesHora.push(String(h).padStart(2, '0') + 'h');
    }
    var picoOptions = {
        series: [{ name: 'Marcações', data: Object.values(porHora) }],
        chart: { type: 'bar', height: 320, fontFamily: 'inherit', toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '85%' } },
        colors: [accentColor],
        dataLabels: { enabled: false },
        xaxis: { categories: categoriesHora, labels: { style: { colors: mutedColor } } },
        yaxis: { labels: { style: { colors: mutedColor } } },
        grid: { borderColor: borderColor, strokeDashArray: 4 }
    };
    new ApexCharts(document.querySelector('#picoHorasChart'), picoOptions).render();

    var porDiaSemana = @json($porDiaSemana ?? []);
    var orderDias = [1, 2, 3, 4, 5, 6, 0];
    var categoriesDia = [];
    var dataDia = [];
    orderDias.forEach(function(k) {
        if (porDiaSemana[k]) {
            categoriesDia.push(porDiaSemana[k].nome);
            dataDia.push(porDiaSemana[k].total);
        }
    });
    var diasOptions = {
        series: [{ name: 'Marcações', data: dataDia }],
        chart: { type: 'bar', height: 280, fontFamily: 'inherit', toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '65%', horizontal: false } },
        colors: [successColor],
        dataLabels: { enabled: false },
        xaxis: { categories: categoriesDia, labels: { style: { colors: mutedColor } } },
        yaxis: { labels: { style: { colors: mutedColor } } },
        grid: { borderColor: borderColor, strokeDashArray: 4 }
    };
    new ApexCharts(document.querySelector('#diasOcupadosChart'), diasOptions).render();
});
</script>
@endsection
