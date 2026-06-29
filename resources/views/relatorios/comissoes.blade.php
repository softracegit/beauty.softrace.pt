@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Relatórios — Comissões').' — '.config('app.name'))

@section('css')
  @include('relatorios._styles')
@endsection

@section('content')
  <div class="dash-welcome mb-4">
    <div class="dash-welcome-content mb-0">
      <h2 class="dash-welcome-title mb-0">Comissões</h2>
    </div>
  </div>

  <form method="GET" action="{{ route('relatorios.comissoes') }}" class="uview-cliente-tab-filters relatorio-tab-filters mb-3">
    <div class="uview-filter-field uview-filter-date">
      <label class="form-label small text-muted mb-0">Desde</label>
      <input type="text" name="comissoes_desde" class="form-control form-control-sm" value="{{ $comissoesDesde ?? '' }}">
    </div>
    <div class="uview-filter-field uview-filter-date">
      <label class="form-label small text-muted mb-0">Até</label>
      <input type="text" name="comissoes_ate" class="form-control form-control-sm" value="{{ $comissoesAte ?? '' }}">
    </div>
    <div class="uview-filter-field uview-filter-select">
      <label class="form-label small text-muted mb-0">Cliente</label>
      <select name="comissoes_cliente" class="form-select form-select-sm">
        <option value="">Todos</option>
        @foreach($clientesOpts ?? [] as $c)
          <option value="{{ $c->id }}" {{ (string)($comissoesCliente ?? '') === (string)$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-field uview-filter-select">
      <label class="form-label small text-muted mb-0">Serviço</label>
      <select name="comissoes_servico" class="form-select form-select-sm">
        <option value="">Todos</option>
        @foreach($servicosOpts ?? [] as $svc)
          <option value="{{ $svc->id }}" {{ (string)($comissoesServico ?? '') === (string)$svc->id ? 'selected' : '' }}>{{ $svc->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-field uview-filter-select">
      <label class="form-label small text-muted mb-0">Técnico</label>
      <select name="comissoes_tecnico" class="form-select form-select-sm">
        <option value="">Todos</option>
        @foreach($tecnicosOpts ?? [] as $tec)
          <option value="{{ $tec->id }}" {{ (string)($comissoesTecnico ?? '') === (string)$tec->id ? 'selected' : '' }}>{{ $tec->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="uview-filter-field uview-filter-estado">
      <label class="form-label small text-muted mb-0">Estado</label>
      <select name="comissoes_estado" class="form-select form-select-sm">
        <option value="">Faturado e Rascunho</option>
        <option value="{{ \App\Models\Sale::INVOICE_STATUS_FATURADO }}" {{ ($comissoesEstado ?? '') === \App\Models\Sale::INVOICE_STATUS_FATURADO ? 'selected' : '' }}>Faturado</option>
        <option value="{{ \App\Models\Sale::INVOICE_STATUS_RASCUNHO }}" {{ ($comissoesEstado ?? '') === \App\Models\Sale::INVOICE_STATUS_RASCUNHO ? 'selected' : '' }}>Rascunho</option>
      </select>
    </div>
    <div class="uview-filter-field uview-filter-estado">
      <label class="form-label small text-muted mb-0">&nbsp;</label>
      <div class="form-check mb-0 d-flex align-items-center" style="min-height: 31px;">
        <input class="form-check-input" type="checkbox" id="comissoesComIva" checked>
        <label class="form-check-label small" for="comissoesComIva">c/ IVA</label>
      </div>
    </div>
    <div class="uview-filter-submit">
      <button type="submit" class="btn btn-primary">
        <i class="ph ph-magnifying-glass"></i>
      </button>
    </div>
  </form>

  @if($linhas->count() > 0)
    <div class="table-responsive">
      <table class="table table-sm table-hover" id="comissoesTable">
        <thead>
          <tr>
            <th>Data venda</th>
            <th>N.º fatura</th>
            <th>Colaborador</th>
            <th>Cliente</th>
            <th>Serviço</th>
            <th class="text-end text-nowrap">Valor serviço</th>
            <th class="text-end text-nowrap">Comissão</th>
            <th class="text-end text-nowrap">Valor comissão</th>
          </tr>
        </thead>
        <tbody>
          @foreach($linhas as $linha)
            <tr class="js-comissao-row"
              data-valor-com-iva="{{ $linha->valor_com_iva }}"
              data-valor-sem-iva="{{ $linha->valor_sem_iva }}"
              data-comissao-com-iva="{{ $linha->comissao_com_iva }}"
              data-comissao-sem-iva="{{ $linha->comissao_sem_iva }}">
              <td class="text-nowrap">{{ $linha->data_emissao ? \App\Support\DateTimeDisplay::business($linha->data_emissao) : '—' }}</td>
              <td class="text-nowrap">{{ $linha->numero_fatura ?: '—' }}</td>
              <td>{{ $linha->tecnico }}</td>
              <td>{{ $linha->cliente }}</td>
              <td>{{ $linha->servico }}</td>
              <td class="text-end text-nowrap js-comissao-valor-servico">{{ number_format($linha->valor_com_iva, 2, ',', ' ') }}€</td>
              <td class="text-end text-nowrap">{{ $linha->comissao_taxa ?? '—' }}</td>
              <td class="text-end text-nowrap js-comissao-valor-comissao">{{ number_format($linha->comissao_com_iva, 2, ',', ' ') }}€</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot class="table-light">
          <tr class="fw-bold" id="comissoesTotaisRow"
            data-total-com-iva="{{ $comissoesTotais['total_comissao_com_iva'] ?? 0 }}"
            data-total-sem-iva="{{ $comissoesTotais['total_comissao_sem_iva'] ?? 0 }}">
            <td colspan="7" class="text-end">Total comissões a pagar</td>
            <td class="text-end text-nowrap" id="comissoesTotalComissao">{{ number_format($comissoesTotais['total_comissao_com_iva'] ?? 0, 2, ',', ' ') }}€</td>
          </tr>
        </tfoot>
      </table>
    </div>

    @include('relatorios.partials.pagination', ['paginator' => $linhas])
  @else
    <p class="text-muted text-center py-3">Nenhuma comissão nos filtros selecionados.</p>
  @endif
@endsection

@section('js')
  <script>
    (function () {
      var cb = document.getElementById('comissoesComIva');
      var totalEl = document.getElementById('comissoesTotalComissao');
      if (!cb) return;

      function formatEuro(value) {
        var n = Number(value);
        if (!isFinite(n)) n = 0;
        return n.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '€';
      }

      function refresh() {
        var comIva = cb.checked;
        document.querySelectorAll('.js-comissao-row').forEach(function (row) {
          var valor = comIva
            ? parseFloat(row.getAttribute('data-valor-com-iva') || '0')
            : parseFloat(row.getAttribute('data-valor-sem-iva') || '0');
          var comissao = comIva
            ? parseFloat(row.getAttribute('data-comissao-com-iva') || '0')
            : parseFloat(row.getAttribute('data-comissao-sem-iva') || '0');
          var valorCell = row.querySelector('.js-comissao-valor-servico');
          var comissaoCell = row.querySelector('.js-comissao-valor-comissao');
          if (valorCell) valorCell.textContent = formatEuro(valor);
          if (comissaoCell) comissaoCell.textContent = formatEuro(comissao);
        });

        var totaisRow = document.getElementById('comissoesTotaisRow');
        if (totalEl && totaisRow) {
          var total = comIva
            ? parseFloat(totaisRow.getAttribute('data-total-com-iva') || '0')
            : parseFloat(totaisRow.getAttribute('data-total-sem-iva') || '0');
          totalEl.textContent = formatEuro(total);
        }
      }

      cb.addEventListener('change', refresh);
      refresh();
    })();
  </script>
@endsection
