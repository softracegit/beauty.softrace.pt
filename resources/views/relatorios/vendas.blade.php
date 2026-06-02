@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Relatórios — Vendas').' — '.config('app.name'))

@section('css')
  @include('relatorios._styles')
  <link rel="stylesheet" href="{{ asset('template/css/payment-pos-modal.css') }}?v={{ file_exists(public_path('template/css/payment-pos-modal.css')) ? filemtime(public_path('template/css/payment-pos-modal.css')) : time() }}">
  <style>
    .table-striped > tbody > tr:nth-of-type(odd) > * {
      background-color:rgb(241, 247, 255) !important;
    }
    .vendas-report-table th,
    .vendas-report-table td {
      vertical-align: top;
    }
    .vendas-two-line { line-height: 1.15; }
    .vendas-two-line small { color: var(--bs-secondary-color); font-size: 0.75em; }
    .vendas-report-table thead th:nth-child(-n+5),
    .vendas-report-table tbody td:nth-child(-n+5) {
      font-size: 0.8125rem;
    }
  </style>
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

  @if($vendas->count() > 0)
    <div class="table-responsive">
      <table class="table table-sm table-hover table-striped vendas-report-table">
        <thead>
          <tr>
            <th>Data</th>
            <th>N.º fatura</th>
            <th>Cliente / NIF</th>
            <th>Técnico</th>
            <th>Serviço</th>
            <th class="text-end text-nowrap">Total</th>
            <th class="text-end text-nowrap">Taxas</th>
            <th class="text-end text-nowrap">Gorjeta</th>
            <th class="text-end text-nowrap">Em dívida</th>
            <th class="text-center text-nowrap">Estado</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          @foreach($vendas as $linha)
            <tr>
              <td class="vendas-two-line text-nowrap">
                <div>{{ \Carbon\Carbon::parse($linha->data)->locale('pt')->translatedFormat('j F') }}</div>
                <small>{{ \Carbon\Carbon::parse($linha->data)->format('Y') }}</small>
              </td>
              <td>{{ $linha->numero_fatura }}</td>
              <td class="vendas-two-line">
                <div>{{ $linha->cliente }}</div>
                <small>{{ $linha->nif !== '' && $linha->nif !== null ? $linha->nif : '-' }}</small>
              </td>
              <td>{{ \Illuminate\Support\Str::of((string) $linha->tecnico)->before(' ') ?: '-' }}</td>
              <td class="vendas-servico-cell">
                @php
                  $servicoNomes = (string) ($linha->servico_nomes ?? $linha->servico ?? '');
                  $servicoNomes = $servicoNomes !== '' && $servicoNomes !== '—' ? $servicoNomes : '';
                  $servicoNomesTrunc = $servicoNomes !== '' ? \Illuminate\Support\Str::limit($servicoNomes, 40) : '—';
                @endphp
                <span class="vendas-servico-nomes" @if($servicoNomes !== '' && $servicoNomes !== $servicoNomesTrunc) title="{{ $servicoNomes }}" @endif>{{ $servicoNomesTrunc }}</span>
                @if(!empty($linha->servico_subtitulo))
                  <small class="vendas-servico-sub text-muted">{{ $linha->servico_subtitulo }}</small>
                @endif
                @if(($linha->tipo_item ?? '') === \App\Models\SaleItem::TIPO_EXTRA)
                  <span class="badge bg-info-light text-info ms-1">Extra</span>
                @endif
              </td>
              <td class="text-end text-nowrap">{{ number_format((float) $linha->valor + (float) ($linha->gorjeta ?? 0), 2, ',', ' ') }}€</td>
              <td class="text-end text-nowrap">
                @if((float) ($linha->taxas ?? 0) > 0)
                  {{ number_format((float) $linha->taxas, 2, ',', ' ') }}€
                @else
                  -
                @endif
              </td>
              <td class="text-end text-nowrap">
                @if((float) ($linha->gorjeta ?? 0) > 0)
                  {{ number_format((float) $linha->gorjeta, 2, ',', ' ') }}€
                @else
                  -
                @endif
              </td>
              <td class="text-end text-nowrap">
                @if((float) ($linha->pendente ?? 0) > 0)
                  {{ number_format((float) $linha->pendente, 2, ',', ' ') }}€
                @else
                  -
                @endif
              </td>
              <td class="text-center text-nowrap">
                @if(($linha->invoice_status ?? \App\Models\Sale::INVOICE_STATUS_FATURADO) === \App\Models\Sale::INVOICE_STATUS_RASCUNHO)
                  <span class="badge bg-warning-subtle text-warning">Rascunho</span>
                @else
                  <span class="badge bg-success-subtle text-success">Faturado</span>
                @endif
              </td>
              <td class="text-end p-1">
                <div class="dropdown d-inline-block">
                  <button class="btn btn-sm btn-light dropdown-toggle js-vendas-dropdown" type="button" id="vendas-acoes-{{ $loop->index }}" data-bs-toggle="dropdown" aria-expanded="false" title="Ações da fatura">
                    <i class="ph ph-dots-three-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="vendas-acoes-{{ $loop->index }}">
                    @php
                      $isDraft = ($linha->invoice_status ?? \App\Models\Sale::INVOICE_STATUS_FATURADO) === \App\Models\Sale::INVOICE_STATUS_RASCUNHO;
                      $pdfUrl = (!$isDraft && $linha->sale && $linha->sale->vendus_document_id)
                        ? route('sales.vendus.pdf', $linha->sale)
                        : route('sales.pdf', $linha->sale);
                    @endphp
                    @if(!$isDraft)
                      <li>
                        <a class="dropdown-item" href="{{ $pdfUrl }}" target="_blank" rel="noopener">
                          <i class="ph ph-file-pdf me-2"></i>Ver PDF
                        </a>
                      </li>
                    @endif
                    @if($isDraft)
                      @php $saleRow = $linha->sale; @endphp
                      <li>
                        <button type="button" class="dropdown-item text-success js-sale-finalize"
                          data-sale-id="{{ $saleRow->id }}"
                          data-sale-amount="{{ (float) $saleRow->total }}"
                          data-client-name="{{ e($linha->cliente) }}"
                          data-client-email="{{ e($saleRow->client?->email ?? '') }}"
                          data-client-phone="{{ e($saleRow->client?->phone ?? '') }}"
                          data-client-nif="{{ e($linha->nif ?? '') }}"
                          data-modal-label="Faturar">
                          <i class="ph ph-check-circle me-2"></i>Faturar
                        </button>
                      </li>
                    @endif
                    @if($linha->calendar_event_id)
                      <li>
                        <a class="dropdown-item" href="{{ route('agenda.index', ['event' => $linha->calendar_event_id]) }}">
                          <i class="ph ph-calendar me-2"></i>Ver marcação
                        </a>
                      </li>
                    @else
                      <li><span class="dropdown-item-text text-muted small">Sem marcação associada</span></li>
                    @endif
                    @if(($linha->sale_status ?? '') === \App\Models\Sale::STATUS_PAGO && !$isDraft)
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <button type="button" class="dropdown-item text-danger js-sale-revert" data-revert-url="{{ route('sales.revert', $linha->sale) }}">
                          <i class="ph ph-arrow-counter-clockwise me-2"></i>Anular venda
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
            <td colspan="5" class="text-end"></td>
            <td class="text-end text-nowrap">{{ number_format($vendasTotais['total_valor_com_gorjeta'] ?? (($vendasTotais['total_valor'] ?? 0) + ($vendasTotais['total_gorjeta'] ?? 0)), 2, ',', ' ') }}€</td>
            <td class="text-end text-nowrap">
              @if((float) ($vendasTotais['total_taxas'] ?? 0) > 0)
                {{ number_format((float) ($vendasTotais['total_taxas'] ?? 0), 2, ',', ' ') }}€
              @else
                -
              @endif
            </td>
            <td class="text-end text-nowrap">{{ number_format($vendasTotais['total_gorjeta'] ?? 0, 2, ',', ' ') }}€</td>
            <td class="text-end text-nowrap">{{ number_format($vendasTotais['total_divida'] ?? 0, 2, ',', ' ') }}€</td>
            <td></td>
            <td></td>
          </tr>
          <tr class="fw-bold vendas-tfoot-total">
            <td colspan="5" class="text-end">Total</td>
            <td class="text-end text-nowrap">{{ number_format((float) ($vendasTotais['total_absoluto'] ?? 0), 2, ',', ' ') }}€</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
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

  @include('partials.payment-modal')
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
        var finalizeBtn = e.target.closest('.js-sale-finalize');
        if (finalizeBtn) {
          e.preventDefault();
          var saleId = finalizeBtn.getAttribute('data-sale-id');
          if (!saleId || typeof window.openSaleFinalizeInvoiceModal !== 'function') return;
          var dropdown = finalizeBtn.closest('.dropdown');
          if (dropdown && typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
            var toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
            if (toggle) bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
          }
          window.openSaleFinalizeInvoiceModal({
            id: parseInt(saleId, 10),
            amount: parseFloat(finalizeBtn.getAttribute('data-sale-amount')) || 0,
            client_name: finalizeBtn.getAttribute('data-client-name') || '',
            client_email: finalizeBtn.getAttribute('data-client-email') || '',
            client_phone: finalizeBtn.getAttribute('data-client-phone') || '',
            client_nif: finalizeBtn.getAttribute('data-client-nif') || '',
            modal_label: finalizeBtn.getAttribute('data-modal-label') || 'Faturar',
          });
          return;
        }

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
  <script>
    window.SALE_FINALIZE_CONFIG = {
      csrf: @json(csrf_token()),
      finalizeInvoiceBaseUrl: @json(url('sales')),
    };
  </script>
  <script src="{{ asset('template/js/sale-finalize-invoice-modal.js') }}?v={{ file_exists(public_path('template/js/sale-finalize-invoice-modal.js')) ? filemtime(public_path('template/js/sale-finalize-invoice-modal.js')) : time() }}"></script>
@endsection
