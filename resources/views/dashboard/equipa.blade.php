@extends('partials.layouts.main')
@section('title', 'Dashboard - Equipa | Beauty CRM')

@section('css')
<style>
    .dash-equipa-panel[hidden] { display: none !important; }
    .dash-equipa-card {
        border-top: 3px solid var(--equipa-accent, #6c757d);
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .dash-equipa-card > .card-body {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
    }
    .dash-equipa-card .dash-equipa-metrics {
        margin-top: auto;
    }
    .dash-equipa-card .dash-equipa-head {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .dash-equipa-card .dash-equipa-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        border: 2px solid rgba(0,0,0,.06);
    }
    .dash-equipa-card .dash-equipa-name {
        font-weight: 600;
        font-size: 1rem;
        margin: 0;
        line-height: 1.25;
        word-break: break-word;
    }
    .dash-equipa-card .dash-equipa-marcacoes {
        font-size: 1.75rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        line-height: 1.1;
    }
    .dash-equipa-card .dash-equipa-marcacoes-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: var(--muted-color);
    }
    .dash-equipa-bar {
        height: 1.5rem;
        overflow: hidden;
    }
    .dash-equipa-bar .progress-bar {
        font-size: 0.75rem;
        font-weight: 600;
    }
    .dash-equipa-status {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem 1.25rem;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid var(--border-color, rgba(0,0,0,.06));
        font-size: 0.875rem;
    }
    .dash-equipa-status-item {
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    .dash-equipa-status-item strong {
        font-variant-numeric: tabular-nums;
    }
    .dash-equipa-dot--concluidas {
        background-color: var(--bs-success, #198754) !important;
    }
    .dash-equipa-dot--por-concluir {
        background-color: #ffc107 !important;
    }
    .dash-equipa-metric {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        font-size: 0.875rem;
        padding: 0.35rem 0;
    }
    .dash-equipa-metric + .dash-equipa-metric {
        border-top: 1px solid var(--border-color, rgba(0,0,0,.06));
    }
    .dash-equipa-metric-label {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        color: var(--muted-color);
    }
    .dash-equipa-metric-value {
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .dash-equipa-dot {
        width: 0.55rem;
        height: 0.55rem;
        border-radius: 50%;
        flex-shrink: 0;
        display: inline-block;
    }
    /* Cinza médio-claro: distingue do verde e do branco das vagas */
    .dash-equipa-dot--pessoal {
        background-color: #b8c0cc !important;
    }
    .dash-equipa-dot--vagas {
        background-color: #f8f9fa !important;
        border: 1px solid #dee2e6;
    }
    .dash-equipa-bar .progress-bar.dash-equipa-seg-pessoal {
        background-color: #b8c0cc !important;
        color: #212529 !important;
    }
    .dash-equipa-bar .progress-bar.dash-equipa-seg-vagas {
        background-color: #f8f9fa !important;
        border-left: 1px solid #dee2e6;
    }
</style>
@endsection

@section('content')

@php
    $periodKeys = $periodKeys ?? ['ontem', 'hoje', 'amanha', 'semana', 'mes'];
    $periodLabels = $periodLabels ?? [
        'ontem' => 'Ontem',
        'hoje' => 'Hoje',
        'amanha' => 'Amanhã',
        'semana' => 'Semana',
        'mes' => 'Mês',
    ];
    $cardsByPeriod = $cardsByPeriod ?? [];
@endphp

<div class="dash-welcome mb-4">
    <div class="d-flex align-items-center justify-content-between gap-3 w-100 flex-wrap">
        <div class="dash-welcome-content">
            <h2 class="dash-welcome-title mb-0">Equipa</h2>
        </div>
        <div class="dash-chart-tabs" id="dashEquipaTabs" role="tablist">
            @foreach ($periodKeys as $periodKey)
                <button
                    type="button"
                    class="dash-chart-tab {{ $periodKey === 'hoje' ? 'active' : '' }}"
                    data-equipa-period="{{ $periodKey }}"
                    aria-selected="{{ $periodKey === 'hoje' ? 'true' : 'false' }}"
                >{{ $periodLabels[$periodKey] ?? $periodKey }}</button>
            @endforeach
        </div>
    </div>
</div>

@foreach ($periodKeys as $periodKey)
    @php $cards = $cardsByPeriod[$periodKey] ?? []; @endphp
    <div class="dash-equipa-panel" data-equipa-panel="{{ $periodKey }}" @if($periodKey !== 'hoje') hidden @endif>
        @if (count($cards) === 0)
            <div class="card">
                <div class="card-body text-muted">Não há técnicas activas nesta loja.</div>
            </div>
        @else
            <div class="row g-3">
                @foreach ($cards as $card)
                    <div class="col-12 col-sm-6 col-lg-3 d-flex">
                        <div class="card dash-equipa-card w-100" style="--equipa-accent: {{ $card['color'] }}">
                            <div class="card-body">
                                <div class="dash-equipa-head mb-3">
                                    <img src="{{ $card['avatar_url'] }}" alt="" class="dash-equipa-avatar">
                                    <div class="min-w-0">
                                        <h3 class="dash-equipa-name">{{ $card['name'] }}</h3>
                                        <div class="small text-muted">Preenchimento {{ number_format($card['preenchimento'], 0, ',', ' ') }}%</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="dash-equipa-marcacoes">{{ $card['marcacoes'] }}</div>
                                    <div class="dash-equipa-marcacoes-label">Marcações</div>
                                </div>

                                <div class="progress dash-equipa-bar mb-3" style="height: 1.5rem;">
                                    @if ($card['minutos_capacidade'] > 0)
                                        @if ($card['pct_marcacao'] > 0)
                                            <div class="progress-bar bg-success" style="width: {{ $card['pct_marcacao'] }}%" title="Horas previstas {{ $card['pct_marcacao'] }}%">{{ $card['pct_marcacao'] >= 12 ? $card['pct_marcacao'].'%' : '' }}</div>
                                        @endif
                                        @if ($card['pct_pessoal'] > 0)
                                            <div class="progress-bar dash-equipa-seg-pessoal" style="width: {{ $card['pct_pessoal'] }}%" title="Tempo pessoal {{ $card['pct_pessoal'] }}%">{{ $card['pct_pessoal'] >= 12 ? $card['pct_pessoal'].'%' : '' }}</div>
                                        @endif
                                        @if ($card['pct_vagas'] > 0)
                                            <div class="progress-bar dash-equipa-seg-vagas" style="width: {{ $card['pct_vagas'] }}%" title="Vagas {{ $card['pct_vagas'] }}%"></div>
                                        @endif
                                    @else
                                        <div class="progress-bar bg-secondary" style="width: 100%">Loja fechada</div>
                                    @endif
                                </div>

                                <div class="dash-equipa-metrics">
                                    <div class="dash-equipa-metric">
                                        <span class="dash-equipa-metric-label">
                                            <span class="dash-equipa-dot bg-success"></span>
                                            Horas previstas
                                        </span>
                                        <span class="dash-equipa-metric-value">{{ $card['horas_marcacao'] }}</span>
                                    </div>
                                    <div class="dash-equipa-metric">
                                        <span class="dash-equipa-metric-label">
                                            <span class="dash-equipa-dot dash-equipa-dot--pessoal"></span>
                                            Tempo pessoal
                                        </span>
                                        <span class="dash-equipa-metric-value">{{ $card['horas_pessoal'] }}</span>
                                    </div>
                                    <div class="dash-equipa-metric">
                                        <span class="dash-equipa-metric-label">
                                            <span class="dash-equipa-dot dash-equipa-dot--vagas"></span>
                                            Horas vagas
                                        </span>
                                        <span class="dash-equipa-metric-value">{{ $card['horas_vagas'] }}</span>
                                    </div>
                                    <div class="dash-equipa-metric">
                                        <span class="dash-equipa-metric-label">Tempo médio</span>
                                        <span class="dash-equipa-metric-value">{{ $card['horas_medio'] }}</span>
                                    </div>
                                    <div class="dash-equipa-metric">
                                        <span class="dash-equipa-metric-label text-muted">Capacidade</span>
                                        <span class="dash-equipa-metric-value text-muted">{{ $card['horas_capacidade'] }}</span>
                                    </div>
                                </div>

                                <div class="dash-equipa-status">
                                    <div class="dash-equipa-status-item">
                                        <span class="dash-equipa-dot dash-equipa-dot--concluidas"></span>
                                        <span><strong>{{ $card['concluidas'] }}</strong> concluídas</span>
                                    </div>
                                    <div class="dash-equipa-status-item">
                                        <span class="dash-equipa-dot dash-equipa-dot--por-concluir"></span>
                                        <span><strong>{{ $card['por_concluir'] }}</strong> por concluir</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endforeach

@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tabs = document.getElementById('dashEquipaTabs');
    if (!tabs) return;
    tabs.querySelectorAll('[data-equipa-period]').forEach(function(tab) {
        tab.addEventListener('click', function() {
            var period = this.getAttribute('data-equipa-period');
            tabs.querySelectorAll('[data-equipa-period]').forEach(function(t) {
                t.classList.toggle('active', t === tab);
                t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
            });
            document.querySelectorAll('[data-equipa-panel]').forEach(function(panel) {
                if (panel.getAttribute('data-equipa-panel') === period) {
                    panel.removeAttribute('hidden');
                } else {
                    panel.setAttribute('hidden', '');
                }
            });
        });
    });
});
</script>
@endsection
