@extends('partials.layouts.main')
@section('title', 'Dashboard | Beauty CRM')

@section('content')
@php
    use App\Models\CalendarEvent;
    use App\Support\CrmPrivacyLock;
    use App\Support\MarcacoesReportEstadoFilter;

    $privacyLocked = app()->bound(CrmPrivacyLock::class) && app(CrmPrivacyLock::class)->isActive();
    $canLinkMarcacoesReport = ! $privacyLocked
        && (bool) (auth()->user()?->canAccessRoute('relatorios.marcacoes'));
    $reportDateToday = $reportDateToday ?? now()->toDateString();
    $reportDateWeekStart = $reportDateWeekStart ?? $reportDateToday;
    $reportDateWeekEnd = $reportDateWeekEnd ?? $reportDateToday;
    $reportDateMonthStart = $reportDateMonthStart ?? $reportDateToday;
    $reportDateMonthEnd = $reportDateMonthEnd ?? $reportDateToday;
    $marcacoesReportUrl = static function (string $desde, string $ate, string $estado): string {
        return route('relatorios.marcacoes', [
            'marcacoes_desde' => $desde,
            'marcacoes_ate' => $ate,
            'marcacoes_estado' => $estado,
        ]);
    };
@endphp
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="dash-welcome mb-4">
    <div class="d-flex align-items-center justify-content-between gap-3 w-100 flex-wrap">
        <div class="dash-welcome-content">
            <h2 class="dash-welcome-title mb-1">Olá, {{ $agentName }}</h2>
            <p class="dash-welcome-text mb-0">{{ $dashboardSubtitle }} — {{ $periodoMesLabel }}.</p>
        </div>
        <div class="dash-welcome-actions flex-shrink-0">
            <a href="{{ route('agenda.index') }}" class="btn btn-primary">
                <i class="ph ph-calendar-blank me-2"></i> Ver agenda
            </a>
        </div>
    </div>
</div>

<div class="dash-kpi-strip mb-4">
    @include('dashboard.partials.ops-kpi-card', [
        'href' => $canLinkMarcacoesReport ? $marcacoesReportUrl($reportDateToday, $reportDateToday, MarcacoesReportEstadoFilter::ATIVAS) : null,
        'iconClass' => 'primary',
        'icon' => 'ph-duotone ph-calendar-dot',
        'value' => $marcacoesHoje,
        'label' => 'Marcações hoje',
    ])
    @include('dashboard.partials.ops-kpi-card', [
        'href' => $canLinkMarcacoesReport ? $marcacoesReportUrl($reportDateToday, $reportDateToday, CalendarEvent::STATUS_FALTOU) : null,
        'iconClass' => 'danger',
        'icon' => 'ph-duotone ph-user-minus',
        'value' => $faltasHoje,
        'label' => 'Faltas hoje',
    ])
    @include('dashboard.partials.ops-kpi-card', [
        'iconClass' => 'success',
        'icon' => 'ph-duotone ph-check-circle',
        'value' => $marcacoesConcluidasHoje,
        'label' => 'Já decorridas hoje',
    ])
    @include('dashboard.partials.ops-kpi-card', [
        'iconClass' => 'info',
        'icon' => 'ph-duotone ph-clock',
        'valueHtml' => e(number_format($horasAgendadasHoje, 1, ',', ' ')).' h',
        'label' => 'Horas agendadas hoje',
    ])
</div>

<div class="dash-kpi-strip mb-4">
    @include('dashboard.partials.ops-kpi-card', [
        'href' => $canLinkMarcacoesReport ? $marcacoesReportUrl($reportDateWeekStart, $reportDateWeekEnd, MarcacoesReportEstadoFilter::ATIVAS) : null,
        'iconClass' => 'primary',
        'icon' => 'ph-duotone ph-calendar-blank',
        'value' => $marcacoesEstaSemana,
        'label' => 'Marcações esta semana',
    ])
    @include('dashboard.partials.ops-kpi-card', [
        'href' => $canLinkMarcacoesReport ? $marcacoesReportUrl($reportDateMonthStart, $reportDateMonthEnd, MarcacoesReportEstadoFilter::ATIVAS) : null,
        'iconClass' => 'success',
        'icon' => 'ph-duotone ph-calendar-check',
        'value' => $marcacoesEsteMes,
        'label' => 'Marcações este mês',
    ])
    @include('dashboard.partials.ops-kpi-card', [
        'href' => $canLinkMarcacoesReport ? $marcacoesReportUrl($reportDateMonthStart, $reportDateMonthEnd, CalendarEvent::STATUS_FALTOU) : null,
        'iconClass' => 'danger',
        'icon' => 'ph-duotone ph-user-minus',
        'value' => $faltasEsteMes,
        'label' => 'Faltas este mês',
    ])
    @include('dashboard.partials.ops-kpi-card', [
        'iconClass' => 'info',
        'icon' => 'ph-duotone ph-user-check',
        'value' => $clientesAtendidosMes,
        'label' => 'Clientes atendidos (mês)',
    ])
</div>

<div class="dash-grid dash-grid-content mb-4">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Resumo do dia</h5>
            <div class="card-actions">
                <span class="text-muted small">Próximas marcações por realizar</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table dash-table mb-0">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            @if($storeScope ?? false)
                            <th>Prestador</th>
                            @endif
                            <th>Cliente</th>
                            <th>Serviço</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($proximasMarcacoesHoje as $ev)
                            @php
                                $statusLabels = \App\Models\CalendarEvent::statuses();
                            @endphp
                            <tr>
                                <td>
                                    <span class="fw-medium">{{ \App\Support\DateTimeDisplay::marcacao($ev->start_at, $ev->store_id, 'H:i') }}</span>
                                    @if($ev->end_at)
                                        <span class="text-muted small">– {{ \App\Support\DateTimeDisplay::marcacao($ev->end_at, $ev->store_id, 'H:i') }}</span>
                                    @endif
                                </td>
                                @if($storeScope ?? false)
                                <td>{{ $ev->user?->agent?->name ?? $ev->user?->name ?? '—' }}</td>
                                @endif
                                <td>{{ $ev->client?->name ?? '—' }}</td>
                                <td>{{ $ev->eventServices->map(fn ($s) => trim((string) ($s->pivot->option_name ?? '')) !== '' ? $s->pivot->option_name : $s->name)->filter()->join(', ') ?: '—' }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        {{ $statusLabels[$ev->status] ?? $ev->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($storeScope ?? false) ? 5 : 4 }}" class="text-center text-muted py-4">Sem marcações por realizar hoje.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Serviços mais realizados</h5>
            <div class="card-actions">
                <span class="text-muted small">Este mês</span>
            </div>
        </div>
        <div class="card-body">
            <div class="region-list">
                @php
                    $maxServico = $servicosMaisRealizados->max('total') ?: 1;
                @endphp
                @forelse($servicosMaisRealizados as $row)
                    @php $pct = $maxServico > 0 ? round(($row->total / $maxServico) * 100) : 0; @endphp
                    <div class="region-item">
                        <div class="region-info">
                            <span class="region-name">{{ $row->service_name }}</span>
                            <span class="region-count">{{ (int) $row->total }}</span>
                        </div>
                        <div class="region-bar">
                            <div class="progress-bar" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">Ainda sem serviços registados este mês.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
