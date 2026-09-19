@extends('partials.layouts.main')
@section('title', 'Resumo empresa — '.config('app.name'))
@section('content')

@php
    $fmtMoney = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
    $fmtPct = function (?float $pct): string {
        if ($pct === null) {
            return '—';
        }
        $sign = $pct > 0 ? '+' : '';

        return $sign.number_format($pct, 1, ',', '.').'%';
    };
    $deltaClass = function (?float $pct, float $delta): string {
        if ($pct === null && abs($delta) < 0.00001) {
            return 'text-muted';
        }
        if ($delta > 0.00001 || ($pct !== null && $pct > 0)) {
            return 'text-success';
        }
        if ($delta < -0.00001 || ($pct !== null && $pct < 0)) {
            return 'text-danger';
        }

        return 'text-muted';
    };
    $queryParams = request()->query();
@endphp

<div class="dash-welcome mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
        <div class="dash-welcome-content mb-0 flex-grow-1 min-w-0">
            <h2 class="dash-welcome-title mb-1">Resumo empresa</h2>
            <p class="text-muted small mb-0">
                Métricas agregadas das lojas. Não altera a loja activa da sessão.
                Comparação vs {{ \Carbon\Carbon::parse($metrics['prev_desde'])->format('d/m/Y') }}
                – {{ \Carbon\Carbon::parse($metrics['prev_ate'])->format('d/m/Y') }}.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 flex-shrink-0">
            <a href="{{ route('relatorios.organizacao.export', $queryParams) }}" class="btn btn-outline-primary btn-sm">
                <i class="ph ph-download-simple me-1"></i> Exportar CSV
            </a>
            <a href="{{ route('relatorios.organizacao.pdf', $queryParams) }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                <i class="ph ph-file-pdf me-1"></i> PDF
            </a>
        </div>
    </div>
</div>

<form method="GET" action="{{ route('relatorios.organizacao') }}" class="card shadow-sm mb-4">
    <div class="card-body row g-3 align-items-end">
        <div class="col-md-2">
            <label class="form-label small text-muted mb-0">Desde</label>
            <input type="date" name="desde" value="{{ $desde }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted mb-0">Até</label>
            <input type="date" name="ate" value="{{ $ate }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted mb-0">Lojas</label>
            <select name="lojas_scope" id="lojas_scope" class="form-select form-select-sm">
                <option value="todas" @selected($lojasScope === 'todas')>Todas as lojas</option>
                <option value="actual" @selected($lojasScope === 'actual')>Loja activa ({{ current_store()->get()->name }})</option>
                <option value="subset" @selected($lojasScope === 'subset')>Seleccionar…</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted mb-0">Evolução</label>
            <select name="evolucao" class="form-select form-select-sm">
                <option value="diaria" @selected(($evolucaoMode ?? 'diaria') === 'diaria')>Diária</option>
                <option value="semanal" @selected(($evolucaoMode ?? '') === 'semanal')>Semanal</option>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary btn-sm w-100">Actualizar</button>
        </div>
        <div class="col-12" id="store_ids_wrap" style="{{ $lojasScope === 'subset' ? '' : 'display:none' }}">
            <label class="form-label small text-muted mb-1">Seleccione lojas</label>
            <div class="d-flex flex-wrap gap-3">
                @foreach ($stores as $store)
                    <label class="form-check">
                        <input type="checkbox" class="form-check-input" name="store_ids[]" value="{{ $store->id }}"
                            @checked(in_array((int) $store->id, $selectedStoreIds, true))>
                        <span class="form-check-label">{{ $store->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>
</form>

{{-- Alertas operacionais --}}
<div class="card shadow-sm mb-4 border-0" style="background: color-mix(in srgb, var(--accent-color), transparent 94%);">
    <div class="card-body py-3">
        <div class="fw-semibold small mb-2 text-uppercase text-muted">Estado operacional</div>
        <div class="d-flex flex-wrap gap-3 small">
            <span>
                <i class="ph ph-cash-register me-1"></i>
                Caixa aberta:
                <strong>{{ $metrics['lojas_caixa_aberta'] }}/{{ $metrics['lojas_total'] }}</strong> lojas
            </span>
            <span>
                <i class="ph ph-note-blank me-1"></i>
                Rascunhos de venda: <strong>{{ $metrics['rascunhos_total'] }}</strong>
            </span>
            <span @class(['text-danger' => ($metrics['sms_falhados_total'] ?? 0) > 0])>
                <i class="ph ph-chat-circle-dots me-1"></i>
                SMS falhados no período: <strong>{{ $metrics['sms_falhados_total'] }}</strong>
            </span>
            <span>
                <i class="ph ph-chart-pie-slice me-1"></i>
                Ocupação: <strong>{{ number_format($metrics['taxa_ocupacao'], 1, ',', '.') }}%</strong>
                <span class="text-muted">({{ $metrics['horas_preenchidas'] }} / {{ $metrics['horas_uteis'] }})</span>
            </span>
            <span @class(['text-danger' => ($metrics['noshows_total'] ?? 0) > 0])>
                <i class="ph ph-user-minus me-1"></i>
                No-shows: <strong>{{ $metrics['noshows_total'] }}</strong>
                <span class="text-muted">({{ number_format($metrics['taxa_noshow'], 1, ',', '.') }}%)</span>
            </span>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">Pipeline de vendas</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="users-stat-card h-100">
                    <div class="users-stat-icon primary"><i class="ph-duotone ph-chart-line-up"></i></div>
                    <div class="users-stat-body">
                        <div class="users-stat-value">{{ $fmtMoney($metrics['previsto_total']) }}</div>
                        <div class="users-stat-label">Total previsto</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="users-stat-card h-100">
                    <div class="users-stat-icon success"><i class="ph-duotone ph-currency-eur"></i></div>
                    <div class="users-stat-body">
                        <div class="users-stat-value">{{ $fmtMoney($metrics['vendas_feitas_total']) }}</div>
                        <div class="users-stat-label">Vendas feitas</div>
                        <div class="small {{ $deltaClass($metrics['faturacao_delta_pct'], $metrics['faturacao_delta']) }} mt-1">
                            {{ $fmtMoney($metrics['faturacao_delta']) }}
                            ({{ $fmtPct($metrics['faturacao_delta_pct']) }}) vs período anterior
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="users-stat-card h-100">
                    <div class="users-stat-icon"><i class="ph-duotone ph-hourglass"></i></div>
                    <div class="users-stat-body">
                        <div class="users-stat-value">{{ $fmtMoney($metrics['por_fazer_total']) }}</div>
                        <div class="users-stat-label">Por fazer</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="users-stats mb-4">
    <div class="users-stat-card">
        <div class="users-stat-icon success"><i class="ph-duotone ph-receipt"></i></div>
        <div class="users-stat-body">
            <div class="users-stat-value">{{ $metrics['num_vendas'] }}</div>
            <div class="users-stat-label">Vendas pagas</div>
        </div>
    </div>
    <div class="users-stat-card">
        <div class="users-stat-icon primary"><i class="ph-duotone ph-tag"></i></div>
        <div class="users-stat-body">
            <div class="users-stat-value">{{ $fmtMoney($metrics['ticket_medio']) }}</div>
            <div class="users-stat-label">Ticket médio</div>
        </div>
    </div>
    <div class="users-stat-card">
        <div class="users-stat-icon"><i class="ph-duotone ph-check-circle"></i></div>
        <div class="users-stat-body">
            <div class="users-stat-value">{{ number_format($metrics['taxa_conclusao'], 1, ',', '.') }}%</div>
            <div class="users-stat-label">Taxa de conclusão</div>
            <div class="small text-muted">{{ $metrics['marcacoes_completas'] }} / {{ $metrics['marcacoes_total'] }} marcações</div>
        </div>
    </div>
    <div class="users-stat-card">
        <div class="users-stat-icon"><i class="ph-duotone ph-user-check"></i></div>
        <div class="users-stat-body">
            <div class="users-stat-value">{{ $metrics['clientes_unicos'] }}</div>
            <div class="users-stat-label">Clientes únicos atendidos</div>
            <div class="small text-muted">Período anterior: {{ $metrics['clientes_unicos_anterior'] }}</div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span class="fw-semibold">Evolução da receita ({{ ($metrics['evolucao']['mode'] ?? 'diaria') === 'semanal' ? 'semanal' : 'diária' }})</span>
        <span class="small text-muted">Empresa vs lojas</span>
    </div>
    <div class="card-body">
        <div id="orgEvolucaoChart" style="min-height: 320px;"></div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">Por loja</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Loja</th>
                        <th class="text-end">Previsto</th>
                        <th class="text-end">Feito</th>
                        <th class="text-end">Por fazer</th>
                        <th class="text-end">vs ant.</th>
                        <th class="text-end">Ocupação</th>
                        <th class="text-end">Faltou</th>
                        <th class="text-end">Cancel.</th>
                        <th class="text-end">Anul.</th>
                        <th class="text-end">No-show</th>
                        <th class="text-end">Caixa</th>
                        <th class="text-end">Rascunhos</th>
                        <th class="text-end">SMS fail</th>
                        <th class="text-end">Conclusão</th>
                        <th class="text-end">Clientes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($metrics['por_loja'] as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row->nome }}</td>
                            <td class="text-end">{{ $fmtMoney($row->previsto) }}</td>
                            <td class="text-end">{{ $fmtMoney($row->vendas_feitas) }}</td>
                            <td class="text-end">{{ $fmtMoney($row->por_fazer) }}</td>
                            <td class="text-end {{ $deltaClass($row->faturacao_delta_pct, $row->faturacao_delta) }}">
                                {{ $fmtMoney($row->faturacao_delta) }}
                                <span class="d-block small">{{ $fmtPct($row->faturacao_delta_pct) }}</span>
                            </td>
                            <td class="text-end">
                                {{ number_format($row->taxa_ocupacao, 1, ',', '.') }}%
                                <span class="d-block small text-muted">{{ $row->ocupacao_label }}</span>
                            </td>
                            <td class="text-end @if($row->faltou > 0) text-danger @endif">{{ $row->faltou }}</td>
                            <td class="text-end @if($row->cancelado > 0) text-warning @endif">{{ $row->cancelado }}</td>
                            <td class="text-end">{{ $row->anulado }}</td>
                            <td class="text-end">
                                {{ number_format($row->taxa_noshow, 1, ',', '.') }}%
                                <span class="d-block small text-muted">{{ $row->noshows }}</span>
                            </td>
                            <td class="text-end">
                                <span @class(['text-success' => $row->caixa_aberta, 'text-muted' => ! $row->caixa_aberta])>
                                    {{ $row->caixa_label }}
                                </span>
                            </td>
                            <td class="text-end">{{ $row->rascunhos }}</td>
                            <td class="text-end @if($row->sms_falhados > 0) text-danger fw-semibold @endif">{{ $row->sms_falhados }}</td>
                            <td class="text-end">
                                {{ number_format($row->taxa_conclusao, 1, ',', '.') }}%
                                <span class="d-block small text-muted">{{ $row->marcacoes_completas }}/{{ $row->marcacoes }}</span>
                            </td>
                            <td class="text-end">{{ $row->clientes_unicos }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="text-center text-muted py-4">Sem dados no período.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Top serviços</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Serviço</th>
                                <th class="text-end">Qtd</th>
                                <th class="text-end">Receita</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($metrics['top_servicos'] as $svc)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $svc->nome }}</div>
                                        @if ($svc->por_loja->count() > 1)
                                            <div class="small text-muted">
                                                @foreach ($svc->por_loja as $pl)
                                                    {{ $pl->loja }}: {{ $fmtMoney($pl->receita) }}@if(! $loop->last) · @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $svc->qtd }}</td>
                                    <td class="text-end">{{ $fmtMoney($svc->receita) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">Sem vendas no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Top categorias</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Categoria</th>
                                <th class="text-end">Qtd</th>
                                <th class="text-end">Receita</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($metrics['top_categorias'] as $cat)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $cat->nome }}</div>
                                        @if ($cat->por_loja->count() > 1)
                                            <div class="small text-muted">
                                                @foreach ($cat->por_loja as $pl)
                                                    {{ $pl->loja }}: {{ $fmtMoney($pl->receita) }}@if(! $loop->last) · @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $cat->qtd }}</td>
                                    <td class="text-end">{{ $fmtMoney($cat->receita) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">Sem vendas no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">Comissões por técnico</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Técnico</th>
                        <th>Lojas</th>
                        <th class="text-end">Comissão (c/ IVA)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($metrics['comissoes_tecnicos'] as $tec)
                        <tr>
                            <td class="fw-semibold">{{ $tec->nome }}</td>
                            <td class="small text-muted">
                                @foreach ($tec->por_loja as $pl)
                                    {{ $pl->loja }} ({{ $fmtMoney($pl->comissao) }})@if(! $loop->last), @endif
                                @endforeach
                            </td>
                            <td class="text-end">{{ $fmtMoney($tec->comissao) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">Sem comissões no período.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('lojas_scope')?.addEventListener('change', function () {
    document.getElementById('store_ids_wrap').style.display = this.value === 'subset' ? '' : 'none';
});

document.addEventListener('DOMContentLoaded', function () {
    if (typeof ApexCharts === 'undefined') return;
    var el = document.querySelector('#orgEvolucaoChart');
    if (!el) return;

    var accent = getComputedStyle(document.documentElement).getPropertyValue('--accent-color').trim() || '#6366f1';
    var muted = getComputedStyle(document.documentElement).getPropertyValue('--muted-color').trim() || '#94a3b8';
    var evolucao = @json($metrics['evolucao'] ?? ['labels' => [], 'series' => []]);
    var labels = evolucao.labels || [];
    var series = evolucao.series || [];

    if (!labels.length || !series.length) {
        el.innerHTML = '<p class="text-muted text-center py-5 mb-0">Sem dados de evolução no período.</p>';
        return;
    }

    new ApexCharts(el, {
        chart: {
            type: 'line',
            height: 320,
            toolbar: { show: false },
            fontFamily: 'inherit',
            zoom: { enabled: false }
        },
        series: series,
        stroke: {
            width: series.map(function (_, i) { return i === 0 ? 3 : 2; }),
            curve: 'smooth'
        },
        markers: { size: labels.length > 40 ? 0 : 3 },
        xaxis: {
            categories: labels,
            labels: { style: { colors: muted, fontSize: '11px' }, rotate: labels.length > 20 ? -45 : 0 }
        },
        yaxis: {
            labels: {
                formatter: function (v) { return Number(v).toFixed(0) + ' €'; },
                style: { colors: muted }
            }
        },
        colors: [accent, '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#f97316'],
        dataLabels: { enabled: false },
        legend: { position: 'top', horizontalAlign: 'left' },
        grid: { borderColor: 'rgba(0,0,0,0.06)', strokeDashArray: 4 },
        tooltip: {
            y: {
                formatter: function (v) {
                    return Number(v).toFixed(2).replace('.', ',') + ' €';
                }
            }
        }
    }).render();
});
</script>
@endpush
@endsection
