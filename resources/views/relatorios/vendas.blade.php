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
      <table class="table table-sm table-hover align-middle">
        <thead>
          <tr>
            <th>Data</th>
            <th>N.º fatura</th>
            <th>Cliente</th>
            <th>NIF</th>
            <th>Técnico</th>
            <th>Serviço</th>
            <th class="text-center">Qtd</th>
            <th class="text-end text-nowrap">Desconto</th>
            <th class="text-end text-nowrap">Valor</th>
            <th class="text-end text-nowrap">Em dívida</th>
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
              <td class="text-end text-nowrap">
                @if((float) ($linha->desconto ?? 0) > 0)
                  {{ number_format((float) $linha->desconto, 2, ',', ' ') }}€
                @else
                  —
                @endif
              </td>
              <td class="text-end text-nowrap">{{ number_format($linha->valor, 2, ',', ' ') }}€</td>
              <td class="text-end text-nowrap">
                @if((float) ($linha->pendente ?? 0) > 0)
                  {{ number_format((float) $linha->pendente, 2, ',', ' ') }}€
                @else
                  —
                @endif
              </td>
              <td class="text-end p-1">
                <div class="dropdown d-inline-block">
                  <button class="btn btn-sm btn-light dropdown-toggle js-vendas-dropdown" type="button" id="vendas-acoes-{{ $loop->index }}" data-bs-toggle="dropdown" aria-expanded="false" title="Ações da fatura">
                    <i class="ph ph-dots-three-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="vendas-acoes-{{ $loop->index }}">
                    <li>
                      <a class="dropdown-item" href="{{ route('sales.pdf', $linha->sale) }}" target="_blank" rel="noopener">
                        <i class="ph ph-printer me-2"></i>Ver / Imprimir
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" href="{{ route('sales.pdf', $linha->sale) }}?download=1">
                        <i class="ph ph-file-pdf me-2"></i>Ver PDF
                      </a>
                    </li>
                    @if($linha->calendar_event_id)
                      <li>
                        <a class="dropdown-item" href="{{ route('agenda.index', ['event' => $linha->calendar_event_id]) }}">
                          <i class="ph ph-calendar me-2"></i>Ver marcação
                        </a>
                      </li>
                    @else
                      <li><span class="dropdown-item-text text-muted small">Sem marcação associada</span></li>
                    @endif
                    @if(($linha->sale_status ?? '') === \App\Models\Sale::STATUS_PAGO)
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <button type="button" class="dropdown-item text-danger js-sale-revert" data-revert-url="{{ route('sales.revert', $linha->sale) }}">
                          <i class="ph ph-arrow-counter-clockwise me-2"></i>Cancelar (nota de crédito)
                        </button>
                      </li>
                    @endif
                  </ul>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
        <tfoot class="table-light">
          <tr class="fw-semibold">
            <td colspan="6" class="text-end">Totais (filtro)</td>
            <td class="text-center">{{ $vendasTotais['num_vendas'] ?? 0 }}</td>
            <td class="text-end text-nowrap">{{ number_format($vendasTotais['total_desconto'] ?? 0, 2, ',', ' ') }}€</td>
            <td class="text-end text-nowrap">{{ number_format($vendasTotais['total_valor'] ?? 0, 2, ',', ' ') }}€</td>
            <td class="text-end text-nowrap">{{ number_format($vendasTotais['total_divida'] ?? 0, 2, ',', ' ') }}€</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div class="mt-3">
      {{ $vendas->links('pagination::bootstrap-5') }}
    </div>
  @else
    <p class="text-muted text-center py-3">Nenhuma linha de venda nos filtros selecionados.</p>
  @endif
@endsection

@section('js')
  <script>
    (function () {
      function initVendasDropdowns() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Dropdown) return;
        document.querySelectorAll('.js-vendas-dropdown').forEach(function (btn) {
          bootstrap.Dropdown.getOrCreateInstance(btn, {
            boundary: 'viewport',
            popperConfig: function (defaultBsPopperConfig) {
              return Object.assign({}, defaultBsPopperConfig, { strategy: 'fixed' });
            },
          });
        });
      }
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVendasDropdowns);
      } else {
        initVendasDropdowns();
      }

      document.body.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-sale-revert');
        if (!btn) return;
        e.preventDefault();
        var url = btn.getAttribute('data-revert-url');
        if (!url) return;
        if (!window.confirm('Anular esta venda e registar nota de crédito? A marcação volta ao estado editável.')) return;
        var token = document.querySelector('meta[name="csrf-token"]');
        token = token ? token.getAttribute('content') : '';
        btn.disabled = true;
        fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: '{}'
        })
          .then(function (r) { return r.json().then(function (res) { return { ok: r.ok, res: res }; }); })
          .then(function (x) {
            btn.disabled = false;
            if (!x.ok || !x.res.success) {
              var msg = (x.res && (x.res.error || x.res.message)) || 'Erro ao anular.';
              if (window.showToast) window.showToast(msg, 'error'); else alert(msg);
              return;
            }
            if (window.showToast) window.showToast(x.res.message || 'Venda anulada.', 'success');
            window.location.reload();
          })
          .catch(function () {
            btn.disabled = false;
            if (window.showToast) window.showToast('Erro de ligação.', 'error'); else alert('Erro de ligação.');
          });
      });
    })();
  </script>
@endsection
