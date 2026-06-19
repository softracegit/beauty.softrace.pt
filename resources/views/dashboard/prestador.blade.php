@extends('partials.layouts.main')
@section('title', 'Dashboard | Beauty CRM')

@section('content')
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
            <p class="dash-welcome-text mb-0">Resumo das suas marcações — {{ $periodoMesLabel }}.</p>
        </div>
        <div class="dash-welcome-actions flex-shrink-0">
            <a href="{{ route('agenda.index') }}" class="btn btn-primary">
                <i class="ph ph-calendar-blank me-2"></i> Ver agenda
            </a>
        </div>
    </div>
</div>

<div class="dash-kpi-strip mb-4">
    <div class="dash-kpi">
        <div class="dash-kpi-icon primary">
            <i class="ph-duotone ph-calendar-dot"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $marcacoesHoje }}</div>
            <div class="dash-kpi-label">Marcações hoje</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon success">
            <i class="ph-duotone ph-calendar-check"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $marcacoesEsteMes }}</div>
            <div class="dash-kpi-label">Marcações este mês</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon warning">
            <i class="ph-duotone ph-hourglass-medium"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $marcacoesMesPorRealizar }}</div>
            <div class="dash-kpi-label">Por realizar este mês</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon info">
            <i class="ph-duotone ph-user-check"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $clientesAtendidosMes }}</div>
            <div class="dash-kpi-label">Clientes atendidos (mês)</div>
        </div>
    </div>
</div>

<div class="dash-kpi-strip mb-4">
    <div class="dash-kpi">
        <div class="dash-kpi-icon primary">
            <i class="ph-duotone ph-calendar-blank"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $marcacoesEstaSemana }}</div>
            <div class="dash-kpi-label">Marcações esta semana</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon success">
            <i class="ph-duotone ph-check-circle"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $marcacoesConcluidasHoje }}</div>
            <div class="dash-kpi-label">Concluídas hoje</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon info">
            <i class="ph-duotone ph-clock"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ number_format($horasAgendadasHoje, 1, ',', ' ') }} h</div>
            <div class="dash-kpi-label">Horas agendadas hoje</div>
        </div>
    </div>

    <div class="dash-kpi">
        <div class="dash-kpi-icon danger">
            <i class="ph-duotone ph-user-minus"></i>
        </div>
        <div class="dash-kpi-body">
            <div class="dash-kpi-value">{{ $faltasEsteMes }}</div>
            <div class="dash-kpi-label">Faltas este mês</div>
        </div>
    </div>
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
                                <td colspan="4" class="text-center text-muted py-4">Sem marcações por realizar hoje.</td>
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
