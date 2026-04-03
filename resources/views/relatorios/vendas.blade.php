@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Relatórios — Vendas').' — '.config('app.name'))

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
  <form method="GET" action="{{ route('relatorios.vendas') }}" class="uview-cliente-tab-filters mb-3">
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
        <option value="">Pago e Anulado</option>
        @foreach(\App\Models\Sale::statuses() as $key => $label)
          <option value="{{ $key }}" {{ ($vendasEstado ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-submit">
      <button type="submit" class="btn btn-primary">
        <i class="ph ph-magnifying-glass"></i>
      </button>
    </div>
  </form>

  @if($vendas->count() > 0)
    <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead>
          <tr>
            <th>Data</th>
            <th>N.º fatura</th>
            <th>Cliente</th>
            <th>NIF</th>
            <th>Técnico</th>
            <th>Serviço</th>
            <th class="text-center">Qtd</th>
            <th class="text-end">Valor</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          @foreach($vendas as $linha)
            <tr>
              <td>{{ $linha->data->format('d/m/Y') }}</td>
              <td>{{ $linha->numero_fatura }}</td>
              <td>{{ $linha->cliente }}</td>
              <td>{{ $linha->nif !== '' && $linha->nif !== null ? $linha->nif : '—' }}</td>
              <td>{{ $linha->tecnico }}</td>
              <td>
                {{ $linha->servico }}
                @if(($linha->tipo_item ?? '') === \App\Models\SaleItem::TIPO_EXTRA)
                  <span class="badge bg-info-light text-info ms-1">Extra</span>
                @endif
              </td>
              <td class="text-center">{{ $linha->quantidade }}</td>
              <td class="text-end">{{ number_format($linha->valor, 2, ',', ' ') }} €</td>
              <td class="text-end">
                <a href="{{ route('sales.pdf', $linha->sale) }}" class="btn btn-sm btn-light" target="_blank" rel="noopener" title="PDF da fatura">
                  <i class="ph ph-file-pdf"></i>
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="mt-3">
      {{ $vendas->links('pagination::bootstrap-5') }}
    </div>
  @else
    <p class="text-muted text-center py-3">Nenhuma linha de venda nos filtros selecionados.</p>
  @endif
@endsection
