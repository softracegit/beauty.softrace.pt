{{-- Resumo: KPIs, alertas e cronologia de marcações --}}
@php
    $formatMins = static function (int $minutes): string {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        if ($hours > 0 && $mins > 0) {
            return $hours.'h '.$mins.'min';
        }
        if ($hours > 0) {
            return $hours.'h';
        }

        return $mins.'min';
    };

    $alertasPartes = collect([
        $cliente->allergies,
        $cliente->contraindications,
    ])->map(fn ($v) => trim((string) $v))->filter()->values();

    $s = $stats ?? null;
@endphp

@if($s)
<div class="row g-3 mb-3 client-resumo-kpis">
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="ph ph-calendar-check fs-4 text-primary"></i>
                    <span class="fw-semibold">Marcações</span>
                </div>
                <p class="mb-0">{{ $s->totalMarcacoes }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="ph ph-user fs-4 text-primary"></i>
                    <span class="fw-semibold">Técnico preferido</span>
                </div>
                <p class="mb-0">{{ $s->tecnicoPreferido ?? '—' }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="ph ph-scissors fs-4 text-primary"></i>
                    <span class="fw-semibold">Serviço mais usado</span>
                </div>
                @if($s->topServicos->isNotEmpty())
                    @php $sv = $s->topServicos->first(); @endphp
                    <p class="mb-0">{{ $sv->service_name }} <span class="text-muted">({{ $sv->total }})</span></p>
                @else
                    <p class="text-muted mb-0">Nenhum serviço registado.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

@if($alertasPartes->isNotEmpty())
<div class="client-ficha-alert-card">
    <div class="client-ficha-alert-card__head">
        <span class="client-ficha-alert-card__icon" aria-hidden="true"><i class="ph ph-warning-circle"></i></span>
        <span class="client-ficha-alert-card__title">Alertas</span>
        <a href="{{ route('clientes.edit', $cliente) }}#preferencias" class="client-ficha-alert-card__edit">Editar</a>
    </div>
    <div class="client-ficha-alert-card__body">
        @foreach($alertasPartes as $parte)
            <p class="client-ficha-alert-card__line mb-0">{{ $parte }}</p>
        @endforeach
    </div>
</div>
@endif

<div class="client-ficha-timeline-wrap{{ ($s || $alertasPartes->isNotEmpty()) ? ' client-ficha-timeline-wrap--spaced' : '' }}">
    @if(($marcacoes ?? collect())->isEmpty())
        <p class="text-muted small mb-0">Ainda não há marcações neste período.</p>
    @else
        <div class="client-ficha-timeline" role="list">
            @foreach($marcacoes as $ev)
                @php
                    $storeId = $ev->store_id ? (int) $ev->store_id : ($cliente->store_id ? (int) $cliente->store_id : null);
                    $items = $ev->eventServiceItems ?? collect();
                    $totalMins = 0;
                    $serviceLabels = [];
                    foreach ($items as $item) {
                        $totalMins += (int) ($item->duration ?? 0);
                        foreach ($item->extras ?? [] as $ex) {
                            $totalMins += (int) ($ex->duration ?? 0);
                        }
                        $label = trim((string) ($item->option_name ?: ($item->service?->name ?? '')));
                        if ($label !== '') {
                            $serviceLabels[] = $label;
                        }
                    }
                    $statusKey = (string) ($ev->status ?? \App\Models\CalendarEvent::STATUS_AGENDADO);
                    $statusLabel = \App\Models\CalendarEvent::statuses()[$statusKey] ?? ($statusKey !== '' ? $statusKey : '—');
                    $statusIcon = $ev->status_icon ?: 'ri-time-fill agenda-status-icon-agendado';
                    $techFormula = trim((string) ($ev->technical_formula ?? ''));
                    $techProducts = trim((string) ($ev->technical_products ?? ''));
                    $techNotes = trim((string) ($ev->technical_notes ?? ''));
                    $isTerminal = in_array($statusKey, [
                        \App\Models\CalendarEvent::STATUS_CANCELADO,
                        \App\Models\CalendarEvent::STATUS_FALTOU,
                        \App\Models\CalendarEvent::STATUS_ANULADO,
                    ], true);
                    $isCancelado = $statusKey === \App\Models\CalendarEvent::STATUS_CANCELADO;
                    $cancelReason = $isCancelado ? trim((string) ($ev->cancellation_reason ?? '')) : '';
                    $cancelNotes = $isCancelado ? trim((string) ($ev->cancellation_notes ?? '')) : '';
                    $hasTechnical = $techFormula !== ''
                        || $techProducts !== ''
                        || $techNotes !== ''
                        || $cancelReason !== ''
                        || $cancelNotes !== '';
                    $startBiz = \App\Support\DateTimeDisplay::inBusiness($ev->start_at, $storeId);
                    $dateLabel = $startBiz
                        ? $startBiz->format('j').' de '.ucfirst($startBiz->locale('pt')->translatedFormat('M')).' '.$startBiz->format('Y')
                        : '—';
                    $hourLabel = $startBiz ? $startBiz->format('H:i') : '—';
                    $origemLabel = $ev->marcacaoSourceLabel() ?? 'Agenda';
                    $marcadaEm = $ev->created_at
                        ? \App\Support\DateTimeDisplay::formatInstant($ev->created_at, $storeId, 'd/m/Y H:i')
                        : null;
                    $terminalAt = null;
                    if ($isTerminal) {
                        $terminalRaw = $ev->cancellation_evaluated_at ?: $ev->updated_at;
                        $terminalAt = $terminalRaw
                            ? \App\Support\DateTimeDisplay::formatInstant($terminalRaw, $storeId, 'd/m/Y H:i')
                            : null;
                        $terminalVerb = match ($statusKey) {
                            \App\Models\CalendarEvent::STATUS_FALTOU => 'Falta registada em',
                            \App\Models\CalendarEvent::STATUS_ANULADO => 'Anulada em',
                            default => 'Cancelada em',
                        };
                    }
                    $paymentMethods = \App\Models\Sale::paymentMethods($storeId);
                    $paymentLabels = ($ev->sales ?? collect())
                        ->filter(fn ($sale) => ! $sale->isAnulado())
                        ->map(function ($sale) use ($paymentMethods) {
                            $key = (string) ($sale->payment_method ?? '');

                            return $paymentMethods[$key] ?? ($key !== '' ? $key : null);
                        })
                        ->filter()
                        ->unique()
                        ->values();
                @endphp
                <article class="client-ficha-timeline__item" role="listitem">
                    <div class="client-ficha-timeline__rail" aria-hidden="true">
                        <span class="client-ficha-timeline__icon-wrap" title="{{ $statusLabel }}">
                            <i class="{{ $statusIcon }} client-ficha-timeline__status-icon"></i>
                        </span>
                    </div>
                    <div class="client-ficha-timeline__content">
                        <div class="client-ficha-timeline__top">
                            <div class="client-ficha-timeline__main">
                                <div class="client-ficha-timeline__headline">
                                    <span class="client-ficha-timeline__date">{{ $dateLabel }}</span>
                                    <span class="client-ficha-timeline__hour">{{ $hourLabel }}</span>
                                    <span class="client-ficha-timeline__services">{{ $serviceLabels !== [] ? implode(', ', $serviceLabels) : '—' }}</span>
                                </div>
                                <div class="client-ficha-timeline__status-line">
                                    <span class="client-ficha-timeline__status">{{ $statusLabel }}</span>
                                    @if($ev->user?->name)
                                        <span class="client-ficha-timeline__meta-sep" aria-hidden="true">·</span>
                                        <span class="client-ficha-timeline__meta">{{ $ev->user->name }}</span>
                                    @endif
                                    @if($totalMins > 0)
                                        <span class="client-ficha-timeline__meta-sep" aria-hidden="true">·</span>
                                        <span class="client-ficha-timeline__meta">{{ $formatMins($totalMins) }}</span>
                                    @endif
                                </div>
                                <div class="client-ficha-timeline__facts">
                                    <span class="client-ficha-timeline__fact">
                                        <span class="client-ficha-timeline__fact-label">Origem</span>
                                        {{ $origemLabel }}
                                    </span>
                                    @if($marcadaEm)
                                        <span class="client-ficha-timeline__fact">
                                            <span class="client-ficha-timeline__fact-label">Marcada em</span>
                                            {{ $marcadaEm }}
                                        </span>
                                    @endif
                                    @if($isTerminal && $terminalAt)
                                        <span class="client-ficha-timeline__fact">
                                            <span class="client-ficha-timeline__fact-label">{{ $terminalVerb }}</span>
                                            {{ $terminalAt }}
                                        </span>
                                    @endif
                                    @if($paymentLabels->isNotEmpty())
                                        <span class="client-ficha-timeline__fact">
                                            <span class="client-ficha-timeline__fact-label">Pago</span>
                                            {{ $paymentLabels->implode(', ') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <a href="{{ route('agenda.index', ['event' => $ev->id]) }}" class="client-ficha-timeline__agenda-btn" title="Ver na agenda" aria-label="Ver na agenda">
                                <i class="ph ph-calendar-blank" aria-hidden="true"></i>
                            </a>
                        </div>
                        @if($hasTechnical)
                            <div class="client-ficha-timeline__tech">
                                @if($cancelReason !== '')
                                    <div><span class="client-ficha-timeline__tech-label">Razão</span> {{ $cancelReason }}</div>
                                @endif
                                @if($cancelNotes !== '')
                                    <div><span class="client-ficha-timeline__tech-label">Notas</span> {{ $cancelNotes }}</div>
                                @endif
                                @if($techFormula !== '')
                                    <div><span class="client-ficha-timeline__tech-label">Fórmula</span> {{ $techFormula }}</div>
                                @endif
                                @if($techProducts !== '')
                                    <div><span class="client-ficha-timeline__tech-label">Produtos</span> {{ $techProducts }}</div>
                                @endif
                                @if($techNotes !== '')
                                    <div><span class="client-ficha-timeline__tech-label">Notas</span> {{ $techNotes }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
