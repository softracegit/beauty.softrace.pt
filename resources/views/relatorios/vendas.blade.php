@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Relatórios — Vendas').' — '.config('app.name'))

@section('css')
  @include('relatorios._styles')
  <link rel="stylesheet" href="{{ asset('template/css/payment-pos-modal.css') }}?v={{ file_exists(public_path('template/css/payment-pos-modal.css')) ? filemtime(public_path('template/css/payment-pos-modal.css')) : time() }}">
  @include('relatorios.partials.vendas-table-styles', ['showClienteColumn' => true])
@endsection

@section('content')
  <div class="dash-welcome mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
      <div class="dash-welcome-content mb-0 flex-grow-1 min-w-0">
        <h2 class="dash-welcome-title mb-0">Vendas</h2>
      </div>
      <div class="d-flex flex-wrap gap-2 flex-shrink-0">
        <a href="{{ route('relatorios.vendas.export', request()->query()) }}" class="btn btn-outline-primary btn-sm">
          <i class="ph ph-download-simple me-1"></i> Exportar
        </a>
        <a href="{{ route('relatorios.vendas.pdf', request()->query()) }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
          <i class="ph ph-printer me-1"></i> Imprimir
        </a>
      </div>
    </div>
  </div>
  <form method="GET" action="{{ route('relatorios.vendas') }}" class="uview-cliente-tab-filters relatorio-tab-filters mb-3">
    <div class="uview-filter-field uview-filter-date">
      <label class="form-label small text-muted mb-0">Desde</label>
      <input type="text" name="vendas_desde" class="form-control form-control-sm" value="{{ $vendasDesde ?? '' }}">
    </div>
    <div class="uview-filter-field uview-filter-date">
      <label class="form-label small text-muted mb-0">Até</label>
      <input type="text" name="vendas_ate" class="form-control form-control-sm" value="{{ $vendasAte ?? '' }}">
    </div>
    <div class="uview-filter-field uview-filter-select">
      <label class="form-label small text-muted mb-0">Cliente</label>
      <select name="vendas_cliente" class="form-select form-select-sm">
        <option value="">Todos</option>
        @foreach($clientesOpts ?? [] as $c)
          <option value="{{ $c->id }}" {{ (string)($vendasCliente ?? '') === (string)$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-field uview-filter-select">
      <label class="form-label small text-muted mb-0">Serviço</label>
      <select name="vendas_servico" class="form-select form-select-sm">
        <option value="">Todos</option>
        @foreach($servicosOpts ?? [] as $svc)
          <option value="{{ $svc->id }}" {{ (string)($vendasServico ?? '') === (string)$svc->id ? 'selected' : '' }}>{{ $svc->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-field uview-filter-select">
      <label class="form-label small text-muted mb-0">Técnico</label>
      <select name="vendas_tecnico" class="form-select form-select-sm">
        <option value="">Todos</option>
        @foreach($tecnicosOpts ?? [] as $tec)
          <option value="{{ $tec->id }}" {{ (string)($vendasTecnico ?? '') === (string)$tec->id ? 'selected' : '' }}>{{ $tec->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-field uview-filter-estado">
      <label class="form-label small text-muted mb-0">Estado</label>
      <select name="vendas_estado" class="form-select form-select-sm">
        <option value="">Faturado e Rascunho</option>
        <option value="{{ \App\Models\Sale::INVOICE_STATUS_FATURADO }}" {{ ($vendasEstado ?? '') === \App\Models\Sale::INVOICE_STATUS_FATURADO ? 'selected' : '' }}>Faturado</option>
        <option value="{{ \App\Models\Sale::INVOICE_STATUS_RASCUNHO }}" {{ ($vendasEstado ?? '') === \App\Models\Sale::INVOICE_STATUS_RASCUNHO ? 'selected' : '' }}>Rascunho</option>
      </select>
    </div>
    <div class="uview-filter-submit">
      <button type="submit" class="btn btn-primary">
        <i class="ph ph-magnifying-glass"></i>
      </button>
    </div>
  </form>

  @include('relatorios.partials.vendas-table', [
    'vendas' => $vendas,
    'vendasTotais' => $vendasTotais,
    'showClienteColumn' => true,
  ])

  @include('partials.payment-modal')
@endsection

@section('js')
  @include('relatorios.partials.vendas-table-scripts')
@endsection
