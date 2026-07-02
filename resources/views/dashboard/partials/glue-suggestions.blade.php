@php
    $glue = $glueSuggestionsData ?? null;
    $glueSummary = $glue['summary'] ?? ['gap_count' => 0, 'recoverable_minutes' => 0, 'suggestion_count' => 0, 'days_with_gaps' => 0];
    $glueSuggestions = $glue['suggestions'] ?? [];
    $gluePeriodOptions = $glue['periodOptions'] ?? ['hoje' => 'Hoje', 'semana' => 'Esta semana', 'mes' => 'Este mês'];
    $activeGluePeriod = $gluePeriod ?? ($glue['period'] ?? 'hoje');
    $glueRouteName = $glueRouteName ?? 'dashboard.ocupacao';
    $glueRouteParams = $glueRouteParams ?? [];
@endphp

<style>
.glue-suggestions-card .card-header {
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem 1rem;
    padding: var(--spacing-md) var(--spacing-lg);
    border-bottom: none;
    overflow: visible;
}
.glue-suggestions-card__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    flex: 0 1 auto;
    min-width: 0;
}
.glue-suggestions-card__toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem 0.75rem;
    margin-left: auto;
    flex: 1 1 auto;
    min-width: 0;
}
.glue-mini-stats {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
}
.glue-mini-stat {
    display: inline-flex;
    align-items: baseline;
    gap: 0.3rem;
    padding: 0.15rem 0.5rem;
    border-radius: var(--radius-full);
    border: 1px solid var(--border-color);
    background: color-mix(in srgb, var(--surface-color), var(--accent-color) 3%);
    font-size: 0.6875rem;
    line-height: 1.25;
    white-space: nowrap;
}
.glue-mini-stat__value {
    font-weight: 700;
    color: var(--heading-color);
}
.glue-mini-stat__label {
    color: var(--muted-color);
}
.glue-mini-stat--warning .glue-mini-stat__value { color: var(--warning-color); }
.glue-mini-stat--danger .glue-mini-stat__value { color: var(--danger-color); }
.glue-mini-stat--info .glue-mini-stat__value { color: var(--info-color); }
.glue-suggestions-card__toolbar .dash-chart-tabs {
    flex-shrink: 0;
    flex-wrap: nowrap;
}
.glue-suggestions-card__toolbar .dash-chart-tab {
    display: inline-block;
    white-space: nowrap;
    text-decoration: none;
    line-height: 1.35;
}
.glue-suggestions-card .card-body {
    padding-top: 1rem;
}
.glue-suggestions-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.glue-item {
    display: grid;
    grid-template-columns: minmax(7rem, 9rem) minmax(0, 1fr) auto;
    gap: 1rem 1.25rem;
    align-items: start;
    padding: 1rem 1.125rem;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    background: var(--surface-color);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.glue-item:hover {
    border-color: color-mix(in srgb, var(--accent-color), var(--border-color) 55%);
    box-shadow: var(--card-shadow);
}
.glue-item__meta {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 0;
}
.glue-item__day {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--heading-color);
    line-height: 1.3;
}
.glue-item__tech {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: var(--muted-color);
}
.glue-item__tech i {
    font-size: 0.9rem;
    opacity: 0.85;
}
.glue-timeline {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 0;
}
.glue-step {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    font-size: 0.8125rem;
    line-height: 1.45;
}
.glue-step__marker {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    margin-top: 0.4rem;
    flex-shrink: 0;
    background: var(--border-color);
}
.glue-step--marcacao .glue-step__marker {
    background: var(--accent-color);
}
.glue-step--personal .glue-step__marker {
    background: var(--muted-color);
}
.glue-step--target .glue-step__marker {
    background: var(--warning-color);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--warning-color), transparent 80%);
}
.glue-step__body {
    min-width: 0;
}
.glue-step__time {
    display: inline-block;
    font-variant-numeric: tabular-nums;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--muted-color);
    margin-right: 0.35rem;
}
.glue-step__label {
    color: var(--heading-color);
}
.glue-step__label--muted {
    color: var(--muted-color);
    font-style: italic;
}
.glue-step__services {
    display: block;
    margin-top: 0.15rem;
    font-size: 0.75rem;
    color: var(--muted-color);
}
.glue-step__tag {
    display: inline-block;
    margin-left: 0.35rem;
    padding: 0.1rem 0.45rem;
    border-radius: var(--radius-full);
    font-size: 0.6875rem;
    font-weight: 600;
    vertical-align: middle;
    background: color-mix(in srgb, var(--muted-color), transparent 88%);
    color: var(--muted-color);
}
.glue-step__suggestion {
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px dashed color-mix(in srgb, var(--border-color), transparent 25%);
}
.glue-item__aside {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: flex-start;
    gap: 0.65rem;
    min-width: 7.5rem;
}
.glue-gap-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.55rem;
    border-radius: var(--radius-full);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--warning-color);
    background: var(--warning-color-light);
    white-space: nowrap;
}
.glue-move {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem;
    font-size: 0.8125rem;
    color: var(--default-color);
}
.glue-move__from {
    font-variant-numeric: tabular-nums;
    color: var(--muted-color);
    text-decoration: line-through;
    text-decoration-color: color-mix(in srgb, var(--muted-color), transparent 40%);
}
.glue-move__arrow {
    color: var(--accent-color);
    font-size: 0.95rem;
    flex-shrink: 0;
}
.glue-move__to {
    font-variant-numeric: tabular-nums;
    font-weight: 700;
    color: var(--accent-color);
}
.glue-move__range {
    display: block;
    margin-top: 0.2rem;
    font-size: 0.6875rem;
    color: var(--muted-color);
}
.glue-empty {
    text-align: center;
    padding: 2.5rem 1rem;
    color: var(--muted-color);
}
.glue-empty i {
    font-size: 2rem;
    opacity: 0.65;
    margin-bottom: 0.5rem;
}
.glue-empty__title {
    font-weight: 600;
    color: var(--heading-color);
    margin-bottom: 0.25rem;
}
@media (max-width: 767px) {
    .glue-suggestions-card .card-header {
        flex-direction: column;
        align-items: stretch;
    }
    .glue-suggestions-card__toolbar {
        margin-left: 0;
        justify-content: flex-start;
    }
}
@media (max-width: 991px) {
    .glue-item {
        grid-template-columns: 1fr;
        gap: 0.85rem;
    }
    .glue-item__aside {
        align-items: flex-start;
        min-width: 0;
        width: 100%;
        flex-direction: row;
        flex-wrap: wrap;
        justify-content: space-between;
    }
}
</style>

<div class="card mb-4 glue-suggestions-card">
    <div class="card-header">
        <h5 class="glue-suggestions-card__title">Otimizar agenda — colar marcações</h5>
        <div class="glue-suggestions-card__toolbar">
            <div class="glue-mini-stats" aria-label="Resumo do período">
                <div class="glue-mini-stat glue-mini-stat--warning">
                    <span class="glue-mini-stat__value">{{ $glueSummary['gap_count'] ?? 0 }}</span>
                    <span class="glue-mini-stat__label">buracos</span>
                </div>
                <div class="glue-mini-stat glue-mini-stat--danger">
                    <span class="glue-mini-stat__value">{{ $glueSummary['recoverable_minutes'] ?? 0 }} min</span>
                    <span class="glue-mini-stat__label">recuperáveis</span>
                </div>
                <div class="glue-mini-stat glue-mini-stat--info">
                    <span class="glue-mini-stat__value">{{ $glueSummary['days_with_gaps'] ?? 0 }}</span>
                    <span class="glue-mini-stat__label">dias</span>
                </div>
            </div>
            <div class="dash-chart-tabs" role="tablist" aria-label="Período das sugestões">
                @foreach($gluePeriodOptions as $periodKey => $periodLabel)
                    <a
                        href="{{ route($glueRouteName, array_merge($glueRouteParams, ['glue_period' => $periodKey])) }}"
                        class="dash-chart-tab {{ $activeGluePeriod === $periodKey ? 'active' : '' }}"
                        role="tab"
                        aria-selected="{{ $activeGluePeriod === $periodKey ? 'true' : 'false' }}"
                    >{{ $periodLabel }}</a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card-body">
        @if(count($glueSuggestions) > 0)
            <div class="glue-suggestions-list">
                @foreach($glueSuggestions as $item)
                    @php
                        $storeId = current_store_id();
                        $prevStart = \App\Support\DateTimeDisplay::marcacao($item['previous_start_at'], $storeId, 'H:i');
                        $prevEnd = \App\Support\DateTimeDisplay::marcacao($item['previous_end_at'], $storeId, 'H:i');
                        $nextStart = \App\Support\DateTimeDisplay::marcacao($item['next_start_at'], $storeId, 'H:i');
                        $nextEnd = \App\Support\DateTimeDisplay::marcacao($item['next_end_at'], $storeId, 'H:i');
                        $suggestedStart = \App\Support\DateTimeDisplay::marcacao($item['suggested_start_at'], $storeId, 'H:i');
                        $suggestedEnd = \App\Support\DateTimeDisplay::marcacao($item['suggested_end_at'], $storeId, 'H:i');
                    @endphp
                    <article class="glue-item">
                        <div class="glue-item__meta">
                            <div class="glue-item__day">{{ $item['day_label'] }}</div>
                            <div class="glue-item__tech">
                                <i class="ph ph-user-circle" aria-hidden="true"></i>
                                {{ $item['technician_name'] }}
                            </div>
                        </div>

                        <div class="glue-timeline">
                            @if(empty($item['between_sequence']))
                                <div class="glue-step glue-step--marcacao">
                                    <span class="glue-step__marker" aria-hidden="true"></span>
                                    <div class="glue-step__body">
                                        <span class="glue-step__time">{{ $prevStart }}–{{ $prevEnd }}</span>
                                        <span class="glue-step__label">{{ $item['previous_client_name'] }}</span>
                                    </div>
                                </div>
                            @endif
                            @foreach($item['between_sequence'] ?? [] as $between)
                                @php
                                    $betweenStart = \App\Support\DateTimeDisplay::marcacao($between['start_at'], $storeId, 'H:i');
                                    $betweenEnd = \App\Support\DateTimeDisplay::marcacao($between['end_at'], $storeId, 'H:i');
                                @endphp
                                <div class="glue-step glue-step--personal">
                                    <span class="glue-step__marker" aria-hidden="true"></span>
                                    <div class="glue-step__body">
                                        <span class="glue-step__time">{{ $betweenStart }}–{{ $betweenEnd }}</span>
                                        <span class="glue-step__label glue-step__label--muted">{{ $between['label'] }}</span>
                                        <span class="glue-step__tag">Tempo pessoal</span>
                                    </div>
                                </div>
                            @endforeach
                            <div class="glue-step glue-step--target">
                                <span class="glue-step__marker" aria-hidden="true"></span>
                                <div class="glue-step__body">
                                    <span class="glue-step__time">{{ $nextStart }}–{{ $nextEnd }}</span>
                                    <span class="glue-step__label">{{ $item['next_client_name'] }}</span>
                                    @if(($item['services_label'] ?? '—') !== '—')
                                        <span class="glue-step__services">{{ $item['services_label'] }}</span>
                                    @endif
                                    <div class="glue-step__suggestion">
                                        <div class="glue-move">
                                            <span class="glue-move__from">{{ $nextStart }}</span>
                                            <i class="ph ph-arrow-left glue-move__arrow" aria-hidden="true"></i>
                                            <span class="glue-move__to">{{ $suggestedStart }}</span>
                                        </div>
                                        <span class="glue-move__range">Novo horário: {{ $suggestedStart }}–{{ $suggestedEnd }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="glue-item__aside">
                            <span class="glue-gap-badge">
                                <i class="ph ph-hourglass-medium" aria-hidden="true"></i>
                                Recuperar {{ $item['gap_minutes'] }} min
                            </span>
                            <a
                                href="{{ route('agenda.index', ['date' => $item['day'], 'time' => $suggestedStart]) }}"
                                class="btn btn-sm btn-outline-primary"
                            >
                                <i class="ph ph-calendar-blank me-1" aria-hidden="true"></i>
                                Ver na agenda
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            @if(($glueSummary['suggestion_count'] ?? 0) > count($glueSuggestions))
                <p class="text-muted small mb-0 mt-3">
                    A mostrar {{ count($glueSuggestions) }} de {{ $glueSummary['suggestion_count'] }} sugestões.
                </p>
            @endif
        @else
            <div class="glue-empty">
                <i class="ph-duotone ph-check-circle d-block" aria-hidden="true"></i>
                <div class="glue-empty__title">Agenda compacta</div>
                <p class="mb-0 small">Sem buracos relevantes em {{ strtolower($glue['periodLabel'] ?? 'hoje') }}.</p>
            </div>
        @endif
    </div>
</div>
