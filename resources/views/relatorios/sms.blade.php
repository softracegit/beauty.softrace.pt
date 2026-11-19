@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Relatórios — SMS').' — '.config('app.name'))

@section('css')
  @include('relatorios._styles')
  @include('relatorios.partials.pdf-print-dropdown-styles')
@endsection

@section('content')
  <div class="dash-welcome mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
      <div class="dash-welcome-content mb-0 flex-grow-1 min-w-0">
        <h2 class="dash-welcome-title mb-0">SMS</h2>
      </div>
      <div class="relatorio-header-actions">
        @include('relatorios.partials.store-context')
        <a href="{{ route('relatorios.sms.export', request()->query()) }}" class="btn btn-outline-primary btn-sm">
          <i class="ph ph-download-simple me-1"></i> Exportar
        </a>
        @include('relatorios.partials.pdf-print-dropdown', [
          'pdfPrintUrl' => route('relatorios.sms.pdf', request()->query()),
          'pdfColumnOptions' => $smsPdfColumnOptions ?? [],
          'pdfPrintScope' => 'sms',
          'pdfColsParam' => 'sms_pdf_cols',
          'pdfOrientationParam' => 'sms_pdf_orientation',
        ])
      </div>
    </div>
  </div>

  <form method="GET" action="{{ route('relatorios.sms') }}" class="uview-cliente-tab-filters relatorio-tab-filters mb-3">
    @include('relatorios.partials.store-context-hidden')
    <div class="uview-filter-field uview-filter-date">
      <label class="form-label small text-muted mb-0">Desde</label>
      <input type="text" name="sms_desde" class="form-control form-control-sm" value="{{ $smsDesde ?? '' }}">
    </div>
    <div class="uview-filter-field uview-filter-date">
      <label class="form-label small text-muted mb-0">Até</label>
      <input type="text" name="sms_ate" class="form-control form-control-sm" value="{{ $smsAte ?? '' }}">
    </div>
    <div class="uview-filter-field uview-filter-select">
      <label class="form-label small text-muted mb-0">Cliente</label>
      <select name="sms_cliente" class="form-select form-select-sm">
        <option value="">Todos</option>
        @foreach($clientesOpts ?? [] as $c)
          <option value="{{ $c->id }}" {{ (string) ($smsCliente ?? '') === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-field uview-filter-select">
      <label class="form-label small text-muted mb-0">Tipo</label>
      <select name="sms_tipo" class="form-select form-select-sm">
        <option value="">Todos</option>
        @foreach($typeLabels ?? [] as $typeKey => $typeLabel)
          <option value="{{ $typeKey }}" {{ (string) ($smsTipo ?? '') === (string) $typeKey ? 'selected' : '' }}>{{ $typeLabel }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-field">
      <label class="form-label small text-muted mb-0">Telemóvel</label>
      <input type="text" name="sms_telemovel" class="form-control form-control-sm" value="{{ $smsTelemovel ?? '' }}" placeholder="Ex.: 912…" autocomplete="off">
    </div>
    <div class="uview-filter-submit">
      <button type="submit" class="btn btn-primary">
        <i class="ph ph-magnifying-glass"></i>
      </button>
    </div>
  </form>

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
              <td class="text-nowrap">{{ \App\Support\DateTimeDisplay::formatInstant($sms->sent_at, $displayStoreId ?? null, 'd/m/Y H:i') }}</td>
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
      Nenhum SMS encontrado com os filtros seleccionados.
    </div>
  @endif
@endsection

@section('js')
  @include('relatorios.partials.pdf-print-dropdown-script')
@endsection
