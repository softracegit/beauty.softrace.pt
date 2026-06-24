@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Relatórios — SMS').' — '.config('app.name'))

@section('css')
  @include('relatorios._styles')
@endsection

@section('content')
  <div class="dash-welcome mb-4">
    <div class="d-flex align-items-center justify-content-between gap-3 w-100 flex-wrap">
      <div class="dash-welcome-content flex-grow-1 min-w-0">
        <h2 class="dash-welcome-title mb-0">SMS</h2>
        <p class="text-muted small mb-0 mt-1">Histórico de envios (OTP, lembretes de marcação e campanhas). Período da tabela: {{ $periodLabel ?? '—' }}.</p>
      </div>
      <form method="GET" action="{{ route('relatorios.sms') }}" class="d-flex align-items-center gap-2 flex-shrink-0">
        <select name="month" class="form-select form-select-sm" style="min-width: 10rem;" aria-label="Mês">
          @foreach($monthOptions ?? [] as $monthValue => $monthLabel)
            <option value="{{ $monthValue }}" {{ (int) ($month ?? now()->month) === (int) $monthValue ? 'selected' : '' }}>{{ $monthLabel }}</option>
          @endforeach
        </select>
        <select name="year" class="form-select form-select-sm" style="min-width: 6rem;" aria-label="Ano">
          @foreach($availableYears ?? [now()->year] as $yearValue)
            <option value="{{ $yearValue }}" {{ (int) ($year ?? now()->year) === (int) $yearValue ? 'selected' : '' }}>{{ $yearValue }}</option>
          @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm text-nowrap">
          <i class="ph ph-funnel me-1"></i> Filtrar
        </button>
      </form>
    </div>
  </div>

  @php $counts = $summaryCounts ?? ['today' => 0, 'week' => 0, 'month' => 0]; @endphp
  <div class="dash-kpi-strip mb-4">
    <div class="dash-kpi">
      <div class="dash-kpi-icon primary"><i class="ph-duotone ph-chat-circle-text"></i></div>
      <div class="dash-kpi-body">
        <div class="dash-kpi-value">{{ number_format((int) ($counts['today'] ?? 0), 0, ',', '.') }}</div>
        <div class="dash-kpi-label">Enviadas hoje</div>
      </div>
    </div>
    <div class="dash-kpi">
      <div class="dash-kpi-icon success"><i class="ph-duotone ph-calendar-check"></i></div>
      <div class="dash-kpi-body">
        <div class="dash-kpi-value">{{ number_format((int) ($counts['week'] ?? 0), 0, ',', '.') }}</div>
        <div class="dash-kpi-label">Enviadas esta semana</div>
      </div>
    </div>
    <div class="dash-kpi">
      <div class="dash-kpi-icon info"><i class="ph-duotone ph-calendar-blank"></i></div>
      <div class="dash-kpi-body">
        <div class="dash-kpi-value">{{ number_format((int) ($counts['month'] ?? 0), 0, ',', '.') }}</div>
        <div class="dash-kpi-label">Enviadas este mês</div>
      </div>
    </div>
  </div>

  @if($messages->count() > 0)
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead>
          <tr>
            <th>Data</th>
            <th>Tipo</th>
            <th>Remetente</th>
            <th>Cliente</th>
            <th>Telemóvel</th>
            <th>Mensagem</th>
          </tr>
        </thead>
        <tbody>
          @foreach($messages as $sms)
            @php
              $clientName = $sms->client_name ?: ($sms->client?->name ?? '—');
              $clientUrl = $sms->client_id ? route('clientes.show', $sms->client_id) : null;
            @endphp
            <tr>
              <td class="text-nowrap">{{ $sms->sent_at?->timezone($storeTimezone ?? config('app.timezone'))->format('d/m/Y H:i') }}</td>
              <td class="text-nowrap">
                <span class="badge text-bg-light border">{{ $typeLabels[$sms->type] ?? $sms->type }}</span>
              </td>
              <td class="text-nowrap">{{ $sms->from_phone }}</td>
              <td>
                @if($clientUrl)
                  <a href="{{ $clientUrl }}" class="text-decoration-none">{{ $clientName }}</a>
                @else
                  {{ $clientName }}
                @endif
              </td>
              <td class="text-nowrap">{{ \App\Support\PhoneDisplay::formatInternational($sms->to_phone) ?? $sms->to_phone }}</td>
              <td class="small" style="max-width: 22rem; white-space: pre-wrap;">{{ $sms->body }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @include('relatorios.partials.pagination', ['paginator' => $messages])
  @else
    <div class="alert alert-light border mb-0" role="status">
      <i class="ph ph-chat-centered-text me-1"></i>
      Nenhum SMS enviado em {{ $periodLabel ?? 'este período' }}.
    </div>
  @endif
@endsection
