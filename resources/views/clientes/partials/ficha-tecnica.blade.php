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
    $kpiStoreId = $cliente->store_id ? (int) $cliente->store_id : null;
    $formatVisitaParts = static function ($raw, ?int $storeId): ?array {
        if (! $raw) {
            return null;
        }
        $dt = $raw instanceof \Carbon\CarbonInterface
            ? $raw
            : \Carbon\Carbon::parse($raw);
        $date = \App\Support\DateTimeDisplay::marcacao($dt, $storeId, 'd/m/Y');
        $time = \App\Support\DateTimeDisplay::marcacao($dt, $storeId, 'H:i');

        return [$date, $time];
    };
    $ultimaMarcacao = $s->ultimaMarcacao ?? null;
    $proximaMarcacao = $s->proximaMarcacao ?? null;
    $ultimaParts = $ultimaMarcacao
        ? $formatVisitaParts($ultimaMarcacao->start_at, $ultimaMarcacao->store_id ?? $kpiStoreId)
        : null;
    $proximaParts = $proximaMarcacao
        ? $formatVisitaParts($proximaMarcacao->start_at, $proximaMarcacao->store_id ?? $kpiStoreId)
        : null;
@endphp

@if($s)
<div class="row g-3 mb-3 client-resumo-kpis">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="ph ph-clock-counter-clockwise fs-4 text-primary"></i>
                    <span class="fw-semibold">Última marcação</span>
                </div>
                @if($ultimaMarcacao && $ultimaParts)
                    <p class="mb-1 small text-muted">
                        {{ $ultimaParts[0] }}
                        <span class="mx-1" aria-hidden="true">·</span>
                        {{ $ultimaParts[1] }}
                    </p>
                    <p class="mb-0 small text-truncate" title="{{ $ultimaMarcacao->services }} · {{ ($ultimaMarcacao->duration_minutes ?? 0) > 0 ? $formatMins((int) $ultimaMarcacao->duration_minutes) : '—' }} · {{ $ultimaMarcacao->technician ?: '—' }}">
                        {{ $ultimaMarcacao->services }}
                        <span class="mx-1" aria-hidden="true">·</span>
                        {{ ($ultimaMarcacao->duration_minutes ?? 0) > 0 ? $formatMins((int) $ultimaMarcacao->duration_minutes) : '—' }}
                        <span class="mx-1" aria-hidden="true">·</span>
                        {{ $ultimaMarcacao->technician ?: '—' }}
                    </p>
                @else
                    <p class="mb-0 text-muted">—</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="ph ph-calendar-blank fs-4 text-primary"></i>
                    <span class="fw-semibold">Próxima marcação</span>
                </div>
                @if($proximaMarcacao && $proximaParts)
                    <p class="mb-1 small text-muted">
                        {{ $proximaParts[0] }}
                        <span class="mx-1" aria-hidden="true">·</span>
                        {{ $proximaParts[1] }}
                    </p>
                    <p class="mb-0 small text-truncate" title="{{ $proximaMarcacao->services }} · {{ ($proximaMarcacao->duration_minutes ?? 0) > 0 ? $formatMins((int) $proximaMarcacao->duration_minutes) : '—' }} · {{ $proximaMarcacao->technician ?: '—' }}">
                        {{ $proximaMarcacao->services }}
                        <span class="mx-1" aria-hidden="true">·</span>
                        {{ ($proximaMarcacao->duration_minutes ?? 0) > 0 ? $formatMins((int) $proximaMarcacao->duration_minutes) : '—' }}
                        <span class="mx-1" aria-hidden="true">·</span>
                        {{ $proximaMarcacao->technician ?: '—' }}
                    </p>
                @else
                    <p class="mb-0 text-muted">—</p>
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
        <span class="client-ficha-alert-card__title">Alertas do cliente</span>
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
    <h5 class="client-ficha-timeline-title mb-3">Últimas marcações</h5>
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
                        $label = $item->lineLabel();
                        if ($label !== '') {
                            $serviceLabels[] = $label;
                        }
                    }
                    $statusKey = (string) ($ev->status ?? \App\Models\CalendarEvent::STATUS_AGENDADO);
                    $statusLabel = \App\Models\CalendarEvent::statuses()[$statusKey] ?? ($statusKey !== '' ? $statusKey : '—');
                    $statusIcon = $ev->status_icon ?: 'ri-time-fill agenda-status-icon-agendado';
                    $visitNotes = trim((string) ($ev->description ?? ''));
                    $isTerminal = in_array($statusKey, [
                        \App\Models\CalendarEvent::STATUS_CANCELADO,
                        \App\Models\CalendarEvent::STATUS_FALTOU,
                        \App\Models\CalendarEvent::STATUS_ANULADO,
                    ], true);
                    $isCancelado = $statusKey === \App\Models\CalendarEvent::STATUS_CANCELADO;
                    $cancelReason = $isCancelado ? trim((string) ($ev->cancellation_reason ?? '')) : '';
                    $cancelNotes = $isCancelado ? trim((string) ($ev->cancellation_notes ?? '')) : '';
                    $hasCancelPreview = $cancelReason !== '' || $cancelNotes !== '';
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
                            <div class="client-ficha-timeline__actions">
                                <button
                                    type="button"
                                    class="client-ficha-timeline__action-btn client-ficha-timeline__action-btn--file js-marcacao-detail-trigger"
                                    data-bs-toggle="offcanvas"
                                    data-bs-target="#marcacaoDetalheModal"
                                    data-template-id="marcacao-detail-{{ $ev->id }}"
                                    title="Ficha técnica"
                                    aria-label="Ver ficha técnica da marcação"
                                >
                                    <i class="ph ph-file-text" aria-hidden="true"></i>
                                </button>
                                @if(auth()->user()?->isAdmin())
                                    <button
                                        type="button"
                                        class="client-ficha-timeline__action-btn client-ficha-timeline__action-btn--logs js-marcacao-activity-log-trigger"
                                        data-activity-log-url="{{ route('agenda.events.activity_log', $ev) }}"
                                        title="Ver logs"
                                        aria-label="Ver logs da marcação"
                                    >
                                        <i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i>
                                    </button>
                                @endif
                                <a
                                    href="{{ route('agenda.index', ['event' => $ev->id]) }}"
                                    class="client-ficha-timeline__action-btn client-ficha-timeline__action-btn--calendar"
                                    title="Ver na agenda"
                                    aria-label="Ver marcação na agenda"
                                >
                                    <i class="ph ph-calendar-blank" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                        @if($visitNotes !== '')
                            <div class="client-ficha-timeline__notes">
                                <span class="client-ficha-timeline__tech-label">Observações</span>
                                <span class="client-ficha-timeline__notes-text">{{ $visitNotes }}</span>
                            </div>
                        @endif
                        @if($hasCancelPreview)
                            <div class="client-ficha-timeline__tech">
                                @if($cancelReason !== '')
                                    <div><span class="client-ficha-timeline__tech-label">Razão</span> {{ $cancelReason }}</div>
                                @endif
                                @if($cancelNotes !== '')
                                    <div><span class="client-ficha-timeline__tech-label">Notas cancelamento</span> {{ $cancelNotes }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
