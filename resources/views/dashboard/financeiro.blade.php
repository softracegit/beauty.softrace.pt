@extends('partials.layouts.main')
@section('title', 'Dashboard - Financeiro | Beauty CRM')

@section('css')
<style>
.dash-welcome--financeiro .dash-welcome-title { margin: 0; }
.dash-welcome--financeiro .dash-welcome-text { margin: 0; }
@media (min-width: 768px) {
    .dash-welcome--financeiro .dash-welcome-filters .form-select {
        padding-top: 6px !important;
        padding-bottom: 6px !important;
        font-size: 0.8125rem !important;
        min-height: 0;
    }
}
.dash-fin-destaque {
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 1rem 1.1rem;
    height: 100%;
    background: var(--surface-color);
}
.dash-fin-destaque-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--muted-color);
    margin-bottom: 0.35rem;
}
.dash-fin-destaque-name {
    font-weight: 700;
    font-size: 1.05rem;
    color: var(--heading-color);
    margin-bottom: 0.25rem;
}
.dash-fin-destaque-meta {
    font-size: 0.8125rem;
    color: var(--muted-color);
}
.dash-fin-rank-table td:last-child,
.dash-fin-rank-table th:last-child {
    text-align: right;
    white-space: nowrap;
}
.dash-fin-future {
    position: relative;
    border: 1px dashed color-mix(in srgb, var(--border-color), var(--accent-color) 25%);
    border-radius: var(--radius-lg);
    overflow: hidden;
}
.dash-fin-future-badge {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    z-index: 2;
}
.dash-fin-future-preview {
    opacity: 0.55;
    pointer-events: none;
    user-select: none;
    filter: grayscale(0.15);
}
.dash-fin-future-note {
    font-size: 0.8125rem;
    color: var(--muted-color);
    margin-top: 0.75rem;
}
.dash-kpi-trend-up { color: var(--success-color); font-size: 0.75rem; font-weight: 600; }
.dash-kpi-trend-down { color: var(--danger-color); font-size: 0.75rem; font-weight: 600; }
</style>
@endsection

@section('content')
@php
    $k = $kpis ?? [];
    $fmt = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €';
@endphp

<div class="dash-welcome mb-4 dash-welcome--financeiro">
    <div class="dash-welcome-header-row">
        <div class="dash-welcome-content flex-grow-1 min-w-0">
            <h2 class="dash-welcome-title">Financeiro</h2>
            <p class="dash-welcome-text mt-2 d-none d-md-block">Receitas, rentabilidade por serviço, técnica e cliente. Baseado em vendas pagas de {{ $periodLabel ?? '—' }}.</p>
        </div>
        <form method="GET" action="{{ route('dashboard.financeiro') }}" class="dash-welcome-filters">
            <select name="month" class="form-select form-select-sm dash-welcome-filter-month" aria-label="Mês">
                @foreach($monthOptions ?? [] as $monthValue => $monthLabel)
                    <option value="{{ $monthValue }}" {{ (int) ($month ?? now()->month) === (int) $monthValue ? 'selected' : '' }}>{{ $monthLabel }}</option>
                @endforeach
            </select>
            <select name="year" class="form-select form-select-sm dash-welcome-filter-year" aria-label="Ano">
                @foreach($availableYears ?? [now()->year] as $yearValue)
                    <option value="{{ $yearValue }}" {{ (int) ($year ?? now()->year) === (int) $yearValue ? 'selected' : '' }}>{{ $yearValue }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm dash-welcome-filter-btn text-nowrap">
                <i class="ph ph-funnel"></i><span class="dash-welcome-filter-btn-label">Filtrar</span>
            </button>
        </form>
    </div>
</div>

<div class="dash-kpi-strip mb-4">
    <div class="dash-kpi">
        <div class="dash-kpi-icon primary"><i class="ph-duotone ph-currency-eur"></i></div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $fmt($k['receita'] ?? 0) }}</div>
            <div class="dash-kpi-label">Este mês</div>
            @if(($k['variacao_receita'] ?? 0) != 0)
                <div class="{{ ($k['variacao_receita'] ?? 0) >= 0 ? 'dash-kpi-trend-up' : 'dash-kpi-trend-down' }}">
                    {{ ($k['variacao_receita'] ?? 0) >= 0 ? '+' : '' }}{{ number_format((float) ($k['variacao_receita'] ?? 0), 1, ',', ' ') }}% vs mês anterior
                </div>
            @endif
        </div>
    </div>
    <div class="dash-kpi">
        <div class="dash-kpi-icon primary"><i class="ph-duotone ph-calendar-dots"></i></div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $fmt($k['receita_semana'] ?? 0) }}</div>
            <div class="dash-kpi-label">Esta semana</div>
            @if(($k['variacao_receita_semana'] ?? 0) != 0)
                <div class="{{ ($k['variacao_receita_semana'] ?? 0) >= 0 ? 'dash-kpi-trend-up' : 'dash-kpi-trend-down' }}">
                    {{ ($k['variacao_receita_semana'] ?? 0) >= 0 ? '+' : '' }}{{ number_format((float) ($k['variacao_receita_semana'] ?? 0), 1, ',', ' ') }}% vs semana anterior
                </div>
            @endif
        </div>
    </div>
    <div class="dash-kpi">
        <div class="dash-kpi-icon success"><i class="ph-duotone ph-receipt"></i></div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ number_format((int) ($k['num_faturas'] ?? 0), 0, ',', '.') }}</div>
            <div class="dash-kpi-label">Faturas pagas</div>
        </div>
    </div>
    <div class="dash-kpi">
        <div class="dash-kpi-icon info"><i class="ph-duotone ph-scales"></i></div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ ($k['ticket_medio'] ?? null) !== null ? $fmt($k['ticket_medio']) : '—' }}</div>
            <div class="dash-kpi-label">Ticket médio</div>
        </div>
    </div>
    <div class="dash-kpi">
        <div class="dash-kpi-icon warning"><i class="ph-duotone ph-users-three"></i></div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ number_format((int) ($k['clientes_unicos'] ?? 0), 0, ',', '.') }}</div>
            <div class="dash-kpi-label">Clientes únicos</div>
            <div class="text-muted" style="font-size: 0.75rem; margin-top: 2px;">Compraram no período</div>
        </div>
    </div>
    <div class="dash-kpi">
        <div class="dash-kpi-icon info"><i class="ph-duotone ph-calendar-dots"></i></div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ ($k['receita_media_dia'] ?? null) !== null ? $fmt($k['receita_media_dia']) : '—' }}</div>
            <div class="dash-kpi-label">Média por dia</div>
            <div class="text-muted" style="font-size: 0.75rem; margin-top: 2px;">{{ (int) ($k['dias_com_vendas'] ?? 0) }} dias com vendas</div>
        </div>
    </div>
    <div class="dash-kpi">
        <div class="dash-kpi-icon primary"><i class="ph-duotone ph-percent"></i></div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ ($k['comissoes_estimadas'] ?? 0) > 0 ? $fmt($k['comissoes_estimadas']) : '—' }}</div>
            <div class="dash-kpi-label">Comissões (c/ IVA)</div>
        </div>
    </div>
    <div class="dash-kpi">
        <div class="dash-kpi-icon success"><i class="ph-duotone ph-chart-line-up"></i></div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $fmt($k['margem_estimada'] ?? 0) }}</div>
            <div class="dash-kpi-label">Margem estimada</div>
        </div>
    </div>
</div>

@if($uses_historical_comissoes ?? false)
    <p class="small text-muted mb-4">
        Comissões do período incluem totais históricos do Zappy (até 31/05/2026), alinhados ao relatório de comissões.
        Receita segue vendas por data da marcação, como no Resumo e no relatório de vendas.
    </p>
@endif

<div class="row g-3 mb-4">
    @foreach([
        ['key' => 'servico', 'label' => 'Serviço mais rentável', 'icon' => 'ph-scissors'],
        ['key' => 'tecnica', 'label' => 'Técnica mais rentável', 'icon' => 'ph-user-circle'],
        ['key' => 'cliente', 'label' => 'Cliente mais rentável', 'icon' => 'ph-users-three'],
    ] as $card)
        @php $item = ($destaques ?? [])[$card['key']] ?? null; @endphp
        <div class="col-md-4">
            <div class="dash-fin-destaque">
                <div class="d-flex align-items-start gap-2">
                    <i class="ph-duotone {{ $card['icon'] }} fs-4 text-primary"></i>
                    <div class="min-w-0">
                        <div class="dash-fin-destaque-label">{{ $card['label'] }}</div>
                        @if($item)
                            <div class="dash-fin-destaque-name text-truncate">{{ $item->nome }}</div>
                            <div class="dash-fin-destaque-meta">{{ $fmt($item->receita) }} · {{ $card['key'] === 'servico' ? ($item->qtd . ' linhas') : ($item->num_faturas . ' faturas') }}</div>
                        @else
                            <div class="dash-fin-destaque-name">—</div>
                            <div class="dash-fin-destaque-meta">Sem vendas no período</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="dash-grid dash-grid-charts mb-4">
    <div class="card dash-chart-main">
        <div class="card-header">
            <h5 class="card-title mb-0">Receita por dia</h5>
        </div>
        <div class="card-body">
            <div id="finReceitaDiariaChart" style="min-height: 280px;"></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h5 class="card-title mb-0">Outras métricas</h5></div>
        <div class="card-body">
            <ul class="list-unstyled mb-0 small">
                <li class="d-flex justify-content-between py-2 border-bottom"><span>Taxas cobradas</span><strong>{{ ($k['taxas'] ?? 0) > 0 ? $fmt($k['taxas']) : '—' }}</strong></li>
                <li class="d-flex justify-content-between py-2 border-bottom"><span>Descontos aplicados</span><strong>{{ ($k['descontos'] ?? 0) > 0 ? $fmt($k['descontos']) : '—' }}</strong></li>
                <li class="d-flex justify-content-between py-2 border-bottom"><span>Receita mês anterior</span><strong>{{ $fmt($k['receita_anterior'] ?? 0) }}</strong></li>
                <li class="d-flex justify-content-between py-2"><span class="text-muted">Sugestões futuras</span><span class="text-muted text-end">IVA estimado · receita/dia útil · extras vs serviços · cancelamentos</span></li>
            </ul>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="card-title mb-0">Top serviços</h5></div>
            <div class="card-body p-0">
                @if(($top_servicos ?? collect())->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 dash-fin-rank-table">
                        <thead><tr><th>Serviço</th><th>Receita</th></tr></thead>
                        <tbody>
                            @foreach($top_servicos as $row)
                            <tr>
                                <td>{{ $row->nome }} <span class="text-muted">({{ $row->qtd }})</span></td>
                                <td>{{ $fmt($row->receita) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted text-center py-4 mb-0">Sem dados no período.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="card-title mb-0">Top técnicas</h5></div>
            <div class="card-body p-0">
                @if(($top_tecnicas ?? collect())->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 dash-fin-rank-table">
                        <thead><tr><th>Técnica</th><th>Receita</th></tr></thead>
                        <tbody>
                            @foreach($top_tecnicas as $row)
                            <tr>
                                <td>{{ \Illuminate\Support\Str::before($row->nome, ' ') ?: $row->nome }}</td>
                                <td>{{ $fmt($row->receita) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted text-center py-4 mb-0">Sem dados no período.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="card-title mb-0">Top clientes</h5></div>
            <div class="card-body p-0">
                @if(($top_clientes ?? collect())->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 dash-fin-rank-table">
                        <thead><tr><th>Cliente</th><th>Receita</th></tr></thead>
                        <tbody>
                            @foreach($top_clientes as $row)
                            <tr>
                                <td><a href="{{ route('clientes.show', $row->client_id) }}">{{ $row->nome }}</a></td>
                                <td>{{ $fmt($row->receita) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted text-center py-4 mb-0">Sem dados no período.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<h5 class="mb-3 text-muted fw-semibold">Próximas funcionalidades <span class="badge bg-secondary-subtle text-secondary ms-1">preview UI</span></h5>
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card dash-fin-future h-100">
            <span class="badge bg-warning-subtle text-warning dash-fin-future-badge">Em breve</span>
            <div class="card-header border-0 pb-0"><h5 class="card-title mb-0">Comissões a pagar</h5></div>
            <div class="card-body dash-fin-future-preview">
                <p class="small text-muted mb-3">Cálculo automático com base na taxa de cada técnica e nas vendas do mês.</p>
                @forelse(($comissoes_por_tecnica ?? collect())->take(3) as $row)
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <div class="fw-semibold">{{ \Illuminate\Support\Str::before($row->nome, ' ') ?: $row->nome }}</div>
                        <small class="text-muted">{{ $row->taxa ?? 'Sem taxa definida' }} · {{ $fmt($row->receita) }} faturado</small>
                    </div>
                    <strong>{{ $fmt($row->comissao) }}</strong>
                </div>
                @empty
                <p class="text-muted small mb-0">Configure comissões na ficha de cada membro.</p>
                @endforelse
                <div class="mt-3 p-2 rounded bg-light small">Total do mês: <strong>{{ $fmt($k['comissoes_estimadas'] ?? 0) }}</strong></div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0">
                <p class="dash-fin-future-note mb-0">UI prevista: lista por técnica, detalhe por fatura, botão «Gerar pagamento» e exportação.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card dash-fin-future h-100">
            <span class="badge bg-warning-subtle text-warning dash-fin-future-badge">Em breve</span>
            <div class="card-header border-0 pb-0"><h5 class="card-title mb-0">Pagamentos a técnicas</h5></div>
            <div class="card-body dash-fin-future-preview">
                <div class="d-flex gap-2 mb-3">
                    <span class="badge bg-warning-subtle text-warning">Por pagar 1.240,00 €</span>
                    <span class="badge bg-success-subtle text-success">Pagos 860,00 €</span>
                </div>
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <div class="fw-semibold">Maria Silva</div>
                            <small class="text-muted">Março 2026 · transferência</small>
                        </div>
                        <div class="text-end">
                            <div>420,00 €</div>
                            <button class="btn btn-sm btn-outline-success mt-1" disabled>Marcar pago</button>
                        </div>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 opacity-75">
                        <div>
                            <div class="fw-semibold">Ana Costa</div>
                            <small class="text-muted">Fevereiro 2026 · pago em 05/03</small>
                        </div>
                        <span class="badge bg-success-subtle text-success">Pago</span>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0">
                <p class="dash-fin-future-note mb-0">UI prevista: tabs Por pagar / Pagos, registo de data e método, histórico e recibo interno.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card dash-fin-future h-100">
            <span class="badge bg-warning-subtle text-warning dash-fin-future-badge">Em breve</span>
            <div class="card-header border-0 pb-0"><h5 class="card-title mb-0">Despesas</h5></div>
            <div class="card-body dash-fin-future-preview">
                <div class="row g-2 mb-3">
                    <div class="col-6"><div class="p-2 rounded bg-light text-center"><div class="small text-muted">Produtos</div><strong>320 €</strong></div></div>
                    <div class="col-6"><div class="p-2 rounded bg-light text-center"><div class="small text-muted">Renda / fixos</div><strong>1.800 €</strong></div></div>
                </div>
                <table class="table table-sm mb-0">
                    <thead><tr><th>Despesa</th><th class="text-end">Valor</th></tr></thead>
                    <tbody>
                        <tr><td>Coloração L'Oréal</td><td class="text-end">89,00 €</td></tr>
                        <tr><td>Energia</td><td class="text-end">145,00 €</td></tr>
                    </tbody>
                </table>
                <button class="btn btn-sm btn-outline-primary w-100 mt-3" disabled><i class="ph ph-plus me-1"></i> Registar despesa</button>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0">
                <p class="dash-fin-future-note mb-0">UI prevista: categorias (produtos, serviços externos, fixos), anexo de fatura, margem líquida = receita − comissões − despesas.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof ApexCharts === 'undefined') return;
    var accent = getComputedStyle(document.documentElement).getPropertyValue('--accent-color').trim() || '#6366f1';
    var muted = getComputedStyle(document.documentElement).getPropertyValue('--muted-color').trim() || '#94a3b8';
    var data = @json($receita_diaria ?? []);
    new ApexCharts(document.querySelector('#finReceitaDiariaChart'), {
        chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [{ name: 'Receita', data: data.map(function (d) { return d.receita; }) }],
        xaxis: { categories: data.map(function (d) { return d.label; }), labels: { style: { colors: muted, fontSize: '11px' } } },
        yaxis: { labels: { formatter: function (v) { return v.toFixed(0) + ' €'; }, style: { colors: muted } } },
        colors: [accent],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(0,0,0,0.06)', strokeDashArray: 4 },
        tooltip: { y: { formatter: function (v) { return v.toFixed(2).replace('.', ',') + ' €'; } } }
    }).render();
});
</script>
@endsection
