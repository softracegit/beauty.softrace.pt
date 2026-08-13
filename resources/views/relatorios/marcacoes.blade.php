@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Relatórios — Marcações').' — '.config('app.name'))

@section('css')
  @include('relatorios._styles')
  @include('relatorios.partials.pdf-print-dropdown-styles')
  @include('relatorios.partials.vendas-table-styles', ['showClienteColumn' => false])
@endsection

@section('content')
  <div class="dash-welcome mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
      <div class="dash-welcome-content mb-0 flex-grow-1 min-w-0">
        <h2 class="dash-welcome-title mb-0">Marcações</h2>
      </div>
      <div class="d-flex flex-wrap gap-2 flex-shrink-0">
        <a href="{{ route('relatorios.marcacoes.export', request()->query()) }}" class="btn btn-outline-primary btn-sm">
          <i class="ph ph-download-simple me-1"></i> Exportar
        </a>
        @include('relatorios.partials.pdf-print-dropdown', [
          'pdfPrintUrl' => route('relatorios.marcacoes.pdf', request()->query()),
          'pdfColumnOptions' => $marcacoesPdfColumnOptions ?? [],
          'pdfPrintScope' => 'marcacoes',
          'pdfColsParam' => 'marcacoes_pdf_cols',
          'pdfOrientationParam' => 'marcacoes_pdf_orientation',
        ])
      </div>
    </div>
  </div>
  <form method="GET" action="{{ route('relatorios.marcacoes') }}" class="uview-cliente-tab-filters relatorio-tab-filters mb-3">
    <div class="uview-filter-field uview-filter-date">
      <label class="form-label small text-muted mb-0">Desde</label>
      <input type="text" name="marcacoes_desde" class="form-control form-control-sm" value="{{ $marcacoesDesde ?? '' }}">
    </div>
    <div class="uview-filter-field uview-filter-date">
      <label class="form-label small text-muted mb-0">Até</label>
      <input type="text" name="marcacoes_ate" class="form-control form-control-sm" value="{{ $marcacoesAte ?? '' }}">
    </div>
    <div class="uview-filter-field uview-filter-select">
      <label class="form-label small text-muted mb-0">Cliente</label>
      <select name="marcacoes_cliente" class="form-select form-select-sm">
        <option value="">Todos</option>
        @foreach($clientesOpts ?? [] as $c)
          <option value="{{ $c->id }}" {{ (string)($marcacoesCliente ?? '') === (string)$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-field uview-filter-select">
      <label class="form-label small text-muted mb-0">Serviço</label>
      <select name="marcacoes_servico" class="form-select form-select-sm">
        <option value="">Todos</option>
        @foreach($servicosOpts ?? [] as $svc)
          <option value="{{ $svc->id }}" {{ (string)($marcacoesServico ?? '') === (string)$svc->id ? 'selected' : '' }}>{{ $svc->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-field uview-filter-select">
      <label class="form-label small text-muted mb-0">Técnico</label>
      <select name="marcacoes_tecnico" class="form-select form-select-sm">
        <option value="">Todos</option>
        @foreach($tecnicosOpts ?? [] as $tec)
          <option value="{{ $tec->id }}" {{ (string)($marcacoesTecnico ?? '') === (string)$tec->id ? 'selected' : '' }}>{{ $tec->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-field uview-filter-estado">
      <label class="form-label small text-muted mb-0">Estado</label>
      <select name="marcacoes_estado" class="form-select form-select-sm">
        <option value="{{ \App\Support\MarcacoesReportEstadoFilter::TUDO }}" {{ ($marcacoesEstado ?? '') === \App\Support\MarcacoesReportEstadoFilter::TUDO ? 'selected' : '' }}>Ver tudo</option>
        <optgroup label="Marcações realizadas / por realizar">
          <option value="{{ \App\Support\MarcacoesReportEstadoFilter::ATIVAS }}" {{ ($marcacoesEstado ?? \App\Support\MarcacoesReportEstadoFilter::ATIVAS) === \App\Support\MarcacoesReportEstadoFilter::ATIVAS ? 'selected' : '' }}>Todas (realizadas / por realizar)</option>
          @foreach(\App\Support\MarcacoesReportEstadoFilter::ativasIndividualOptions() as $key => $label)
            <option value="{{ $key }}" {{ ($marcacoesEstado ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </optgroup>
        <optgroup label="Marcações não realizadas">
          <option value="{{ \App\Support\MarcacoesReportEstadoFilter::NAO_REALIZADAS }}" {{ ($marcacoesEstado ?? '') === \App\Support\MarcacoesReportEstadoFilter::NAO_REALIZADAS ? 'selected' : '' }}>Todas (não realizadas)</option>
          @foreach(\App\Support\MarcacoesReportEstadoFilter::naoRealizadasIndividualOptions() as $key => $label)
            <option value="{{ $key }}" {{ ($marcacoesEstado ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </optgroup>
        <option value="{{ \App\Support\MarcacoesReportEstadoFilter::TEMPO_PESSOAL }}" {{ ($marcacoesEstado ?? '') === \App\Support\MarcacoesReportEstadoFilter::TEMPO_PESSOAL ? 'selected' : '' }}>Tempos pessoais</option>
      </select>
    </div>
    <div class="uview-filter-submit">
      <button type="submit" class="btn btn-primary">
        <i class="ph ph-magnifying-glass"></i>
      </button>
    </div>
  </form>

  @include('relatorios.partials.marcacoes-table', [
    'marcacoes' => $marcacoes,
    'marcacoesTotais' => $marcacoesTotais ?? [],
  ])

  @include('relatorios.partials.marcacao-detalhe-modals', ['marcacoes' => $marcacoes])

  @if($marcacoes->count() > 0)
    @include('relatorios.partials.pagination', ['paginator' => $marcacoes])
  @endif
@endsection

@section('js')
  @include('relatorios.partials.marcacao-detalhe-modals-script')
  @include('relatorios.partials.pdf-print-dropdown-script')
@endsection
