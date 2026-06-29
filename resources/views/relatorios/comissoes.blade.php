@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Relatórios — Comissões').' — '.config('app.name'))

@section('css')
  @include('relatorios._styles')
  <style>
    .comissoes-print-dropdown { min-width: 15rem; }
    .comissoes-print-dropdown .form-check-label { cursor: pointer; }
    .comissoes-pdf-orientation-group {
      display: flex;
      gap: 0.5rem;
      margin-top: 0.85rem;
    }
    .comissoes-pdf-orientation-option {
      flex: 1 1 0;
      min-width: 0;
      min-height: 3.25rem;
      margin: 0;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md, 0.375rem);
      padding: 0.35rem 0.25rem;
      text-align: center;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      transition: border-color 0.15s ease, background-color 0.15s ease;
    }
    .comissoes-pdf-orientation-option:hover {
      border-color: color-mix(in srgb, var(--border-color), var(--accent-color) 35%);
    }
    .comissoes-pdf-orientation-option:has(.js-comissoes-pdf-orientation:checked) {
      border-color: var(--accent-color, #0d6efd);
      background: color-mix(in srgb, var(--accent-color, #0d6efd) 8%, transparent);
    }
    .comissoes-pdf-orientation-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.35rem;
      line-height: 1;
      color: var(--heading-color, #333);
    }
    .comissoes-pdf-orientation-icon--landscape {
      transform: rotate(90deg);
    }
    .comissoes-pdf-orientation-label {
      display: block;
      margin-top: 0.2rem;
      font-size: 0.625rem;
      line-height: 1.2;
      color: var(--muted-color, #666);
    }
  </style>
@endsection

@section('content')
  <div class="dash-welcome mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
      <div class="dash-welcome-content mb-0 flex-grow-1 min-w-0">
        <h2 class="dash-welcome-title mb-0">Comissões</h2>
      </div>
      <div class="d-flex flex-wrap gap-2 flex-shrink-0">
        <a href="{{ route('relatorios.comissoes.export', request()->query()) }}" class="btn btn-outline-primary btn-sm js-comissoes-export-link">
          <i class="ph ph-download-simple me-1"></i> Exportar
        </a>
        <div class="dropdown">
          <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
            <i class="ph ph-printer me-1"></i> Imprimir
          </button>
          <div class="dropdown-menu dropdown-menu-end p-3 shadow-sm comissoes-print-dropdown">
            <div class="small fw-semibold mb-2">Colunas do PDF</div>
            @foreach($comissoesPdfColumnOptions ?? [] as $colKey => $colLabel)
              <div class="form-check mb-1">
                <input class="form-check-input js-comissoes-pdf-col" type="checkbox" value="{{ $colKey }}" id="comissoesPdfCol_{{ $colKey }}" checked>
                <label class="form-check-label small" for="comissoesPdfCol_{{ $colKey }}">{{ $colLabel }}</label>
              </div>
            @endforeach
            <div class="comissoes-pdf-orientation-group" role="radiogroup" aria-label="Orientação do PDF">
              <label class="comissoes-pdf-orientation-option">
                <input type="radio" name="comissoes_pdf_orientation_ui" value="portrait" class="visually-hidden js-comissoes-pdf-orientation">
                <span class="comissoes-pdf-orientation-icon" aria-hidden="true"><i class="ph ph-file"></i></span>
                <span class="comissoes-pdf-orientation-label">Vertical</span>
              </label>
              <label class="comissoes-pdf-orientation-option">
                <input type="radio" name="comissoes_pdf_orientation_ui" value="landscape" class="visually-hidden js-comissoes-pdf-orientation" checked>
                <span class="comissoes-pdf-orientation-icon comissoes-pdf-orientation-icon--landscape" aria-hidden="true"><i class="ph ph-file"></i></span>
                <span class="comissoes-pdf-orientation-label">Horizontal</span>
              </label>
            </div>
            <a href="{{ route('relatorios.comissoes.pdf', request()->query()) }}"
              class="btn btn-primary btn-sm w-100 mt-3 js-comissoes-print-link"
              data-base-href="{{ route('relatorios.comissoes.pdf', request()->query()) }}"
              target="_blank"
              rel="noopener">
              Gerar PDF
            </a>
            <p class="small text-muted mb-0 mt-2 js-comissoes-pdf-cols-warning d-none">Seleccione pelo menos uma coluna.</p>
          </div>
        </div>
      </div>
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
      <label class="form-label small text-muted mb-0">Colaborador(a)</label>
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
    @if(!empty($comissoesTotalHistorico))
      <p class="small text-muted mb-2">
        O total de comissões inclui valores históricos do Zappy (até 31/05/2026). A partir de junho/2026, linhas e total coincidem.
      </p>
    @endif
    <div class="table-responsive">
      <table class="table table-sm table-hover" id="comissoesTable">
        <thead>
          <tr>
            <th>Data venda</th>
            <th>N.º fatura</th>
            <th>Colaborador(a)</th>
            <th>Cliente</th>
            <th>Serviço</th>
            <th class="text-end text-nowrap">Valor serviço</th>
            <th class="text-end text-nowrap">Comissão (%)</th>
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
              <td class="text-nowrap">{{ $linha->data_emissao ? \App\Support\DateTimeDisplay::businessDate($linha->data_emissao) : '—' }}</td>
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
      var STORAGE_KEY_IVA = 'comissoes_com_iva';
      var STORAGE_KEY_COLS = 'comissoes_pdf_cols';
      var STORAGE_KEY_ORIENTATION = 'comissoes_pdf_orientation';
      var COLUMN_ORDER = @json(array_keys($comissoesPdfColumnOptions ?? []));
      var cb = document.getElementById('comissoesComIva');
      var totalEl = document.getElementById('comissoesTotalComissao');
      var printLink = document.querySelector('.js-comissoes-print-link');
      var colsWarning = document.querySelector('.js-comissoes-pdf-cols-warning');
      var colCheckboxes = document.querySelectorAll('.js-comissoes-pdf-col');
      var orientationInputs = document.querySelectorAll('.js-comissoes-pdf-orientation');

      function readComIvaPreference() {
        try {
          var stored = localStorage.getItem(STORAGE_KEY_IVA);
          if (stored === '0') return false;
          if (stored === '1') return true;
        } catch (e) {}
        return true;
      }

      function readPdfColsPreference() {
        try {
          var stored = localStorage.getItem(STORAGE_KEY_COLS);
          if (!stored) return null;
          var parsed = stored.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
          return parsed.length ? parsed : null;
        } catch (e) {}
        return null;
      }

      function saveComIvaPreference(comIva) {
        try {
          localStorage.setItem(STORAGE_KEY_IVA, comIva ? '1' : '0');
        } catch (e) {}
        syncExportPrintLinks(comIva);
      }

      function savePdfColsPreference(cols) {
        try {
          localStorage.setItem(STORAGE_KEY_COLS, cols.join(','));
        } catch (e) {}
      }

      function readPdfOrientationPreference() {
        try {
          var stored = localStorage.getItem(STORAGE_KEY_ORIENTATION);
          if (stored === 'portrait' || stored === 'landscape') return stored;
        } catch (e) {}
        return 'landscape';
      }

      function savePdfOrientationPreference(orientation) {
        try {
          localStorage.setItem(STORAGE_KEY_ORIENTATION, orientation);
        } catch (e) {}
      }

      function getSelectedPdfOrientation() {
        var selected = 'landscape';
        orientationInputs.forEach(function (input) {
          if (input.checked) selected = input.value;
        });
        return selected === 'portrait' ? 'portrait' : 'landscape';
      }

      function applyPdfOrientationPreference() {
        var preferred = readPdfOrientationPreference();
        orientationInputs.forEach(function (input) {
          input.checked = input.value === preferred;
        });
      }

      function getSelectedPdfCols() {
        var selected = [];
        colCheckboxes.forEach(function (input) {
          if (input.checked) selected.push(input.value);
        });
        return COLUMN_ORDER.filter(function (key) {
          return selected.indexOf(key) !== -1;
        });
      }

      function applyPdfColsPreference() {
        var preferred = readPdfColsPreference();
        if (!preferred) return;
        colCheckboxes.forEach(function (input) {
          input.checked = preferred.indexOf(input.value) !== -1;
        });
      }

      function syncExportPrintLinks(comIva) {
        document.querySelectorAll('.js-comissoes-export-link').forEach(function (link) {
          try {
            var url = new URL(link.getAttribute('href'), window.location.origin);
            url.searchParams.set('comissoes_com_iva', comIva ? '1' : '0');
            link.setAttribute('href', url.pathname + url.search);
          } catch (e) {}
        });
        syncPrintLink(comIva);
      }

      function syncPrintLink(comIva) {
        if (!printLink) return;
        var baseHref = printLink.getAttribute('data-base-href') || printLink.getAttribute('href');
        try {
          var url = new URL(baseHref, window.location.origin);
          url.searchParams.set('comissoes_com_iva', comIva ? '1' : '0');
          var cols = getSelectedPdfCols();
          if (cols.length) {
            url.searchParams.set('comissoes_pdf_cols', cols.join(','));
          } else {
            url.searchParams.delete('comissoes_pdf_cols');
          }
          url.searchParams.set('comissoes_pdf_orientation', getSelectedPdfOrientation());
          printLink.setAttribute('href', url.pathname + url.search);
          printLink.classList.toggle('disabled', cols.length === 0);
          printLink.setAttribute('aria-disabled', cols.length === 0 ? 'true' : 'false');
          if (colsWarning) colsWarning.classList.toggle('d-none', cols.length > 0);
        } catch (e) {}
      }

      function formatEuro(value) {
        var n = Number(value);
        if (!isFinite(n)) n = 0;
        return n.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '€';
      }

      function refresh() {
        if (!cb) return;
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

      applyPdfColsPreference();
      applyPdfOrientationPreference();
      if (cb) {
        cb.checked = readComIvaPreference();
        syncExportPrintLinks(cb.checked);
        refresh();
        cb.addEventListener('change', function () {
          saveComIvaPreference(cb.checked);
          refresh();
        });
      } else {
        syncPrintLink(true);
      }

      colCheckboxes.forEach(function (input) {
        input.addEventListener('change', function () {
          var cols = getSelectedPdfCols();
          savePdfColsPreference(cols);
          syncPrintLink(cb ? cb.checked : readComIvaPreference());
        });
      });

      orientationInputs.forEach(function (input) {
        input.addEventListener('change', function () {
          savePdfOrientationPreference(getSelectedPdfOrientation());
          syncPrintLink(cb ? cb.checked : readComIvaPreference());
        });
      });

      if (printLink) {
        printLink.addEventListener('click', function (event) {
          if (getSelectedPdfCols().length === 0) {
            event.preventDefault();
          }
        });
      }
    })();
  </script>
@endsection
