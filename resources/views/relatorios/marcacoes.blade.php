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

  @if($marcacoes->count() > 0)
    @foreach($marcacoes as $ev)
      <template id="marcacao-detail-{{ $ev->id }}">
        @include('relatorios._marcacao_modal_fragment', ['ev' => $ev])
      </template>
    @endforeach
    <div class="modal fade" id="marcacaoDetalheModal" tabindex="-1" aria-labelledby="marcacaoDetalheModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="marcacaoDetalheModalLabel">Detalhes da marcação</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body" id="marcacaoDetalheModalBody"></div>
          <div class="modal-footer" id="marcacaoDetalheModalFooter"></div>
        </div>
      </div>
    </div>

    @if(auth()->user()?->isAdmin())
      <div class="modal fade" id="reativarMarcacaoModal" tabindex="-1" aria-labelledby="reativarMarcacaoModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header pb-3">
              <h4 class="modal-title mb-0 fw-semibold" id="reativarMarcacaoModalLabel">Reativar marcação</h4>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" id="reativarMarcacaoEventId" value="">
              <input type="hidden" id="reativarMarcacaoUrl" value="">
              <div id="reativarMarcacaoLoading" class="text-muted small mb-0">A verificar…</div>
              <div id="reativarMarcacaoBlocked" class="d-none">
                <div class="alert alert-warning mb-0">
                  <div class="fw-semibold mb-2">Não é possível reativar esta marcação</div>
                  <ul class="mb-0 ps-3" id="reativarMarcacaoBlockers"></ul>
                </div>
              </div>
              <div id="reativarMarcacaoForm" class="d-none">
                <div class="mb-3">
                  <div class="text-muted small mb-1">Estado atual</div>
                  <div class="fw-semibold" id="reativarMarcacaoStatusLabel">—</div>
                </div>
                <div class="mb-3">
                  <label for="reativarMarcacaoReason" class="form-label">Motivo da reativação</label>
                  <textarea class="form-control" id="reativarMarcacaoReason" rows="3" maxlength="1000" placeholder="Indique o motivo da reativação..."></textarea>
                </div>
                <div class="form-check mb-0">
                  <input class="form-check-input" type="checkbox" id="reativarMarcacaoNotifyClient">
                  <label class="form-check-label" for="reativarMarcacaoNotifyClient">Avisar cliente de que a marcação voltou a ficar ativa</label>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
              <button type="button" class="btn btn-primary d-none" id="reativarMarcacaoConfirmBtn">Reativar marcação</button>
            </div>
          </div>
        </div>
      </div>
    @endif

    @include('relatorios.partials.pagination', ['paginator' => $marcacoes])
  @endif
@endsection

@section('js')
  <script>
    (function () {
      var modalEl = document.getElementById('marcacaoDetalheModal');
      if (!modalEl) return;

      modalEl.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn || !btn.getAttribute('data-template-id')) return;
        var tid = btn.getAttribute('data-template-id');
        var tpl = document.getElementById(tid);
        var body = document.getElementById('marcacaoDetalheModalBody');
        var footer = document.getElementById('marcacaoDetalheModalFooter');
        if (!tpl || !body || !footer) return;
        body.innerHTML = '';
        footer.innerHTML = '';
        var frag = tpl.content.cloneNode(true);
        var bodyEl = frag.querySelector('.js-marcacao-modal-body');
        var footerEl = frag.querySelector('.js-marcacao-modal-footer');
        if (bodyEl) body.appendChild(bodyEl);
        if (footerEl) footer.appendChild(footerEl);
      });

      var reativarModalEl = document.getElementById('reativarMarcacaoModal');
      if (!reativarModalEl) return;

      var loadingEl = document.getElementById('reativarMarcacaoLoading');
      var blockedEl = document.getElementById('reativarMarcacaoBlocked');
      var blockersEl = document.getElementById('reativarMarcacaoBlockers');
      var formEl = document.getElementById('reativarMarcacaoForm');
      var statusLabelEl = document.getElementById('reativarMarcacaoStatusLabel');
      var reasonEl = document.getElementById('reativarMarcacaoReason');
      var notifyEl = document.getElementById('reativarMarcacaoNotifyClient');
      var confirmBtn = document.getElementById('reativarMarcacaoConfirmBtn');
      var eventIdEl = document.getElementById('reativarMarcacaoEventId');
      var urlEl = document.getElementById('reativarMarcacaoUrl');
      var detailModal = bootstrap.Modal.getOrCreateInstance(modalEl);
      var reativarModal = bootstrap.Modal.getOrCreateInstance(reativarModalEl);

      function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
      }

      function resetReativarModal() {
        if (loadingEl) loadingEl.classList.remove('d-none');
        if (blockedEl) blockedEl.classList.add('d-none');
        if (formEl) formEl.classList.add('d-none');
        if (confirmBtn) {
          confirmBtn.classList.add('d-none');
          confirmBtn.disabled = false;
        }
        if (blockersEl) blockersEl.innerHTML = '';
        if (reasonEl) reasonEl.value = '';
        if (notifyEl) notifyEl.checked = false;
      }

      function showBlocked(blockers) {
        if (loadingEl) loadingEl.classList.add('d-none');
        if (formEl) formEl.classList.add('d-none');
        if (confirmBtn) confirmBtn.classList.add('d-none');
        if (blockedEl) blockedEl.classList.remove('d-none');
        if (!blockersEl) return;
        blockersEl.innerHTML = '';
        (blockers || []).forEach(function (msg) {
          var li = document.createElement('li');
          li.textContent = msg;
          blockersEl.appendChild(li);
        });
      }

      function showForm(statusLabel) {
        if (loadingEl) loadingEl.classList.add('d-none');
        if (blockedEl) blockedEl.classList.add('d-none');
        if (formEl) formEl.classList.remove('d-none');
        if (confirmBtn) confirmBtn.classList.remove('d-none');
        if (statusLabelEl) statusLabelEl.textContent = statusLabel || '—';
      }

      modalEl.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-reativar-marcacao');
        if (!btn) return;
        e.preventDefault();

        var previewUrl = btn.getAttribute('data-preview-url');
        var reativarUrl = btn.getAttribute('data-reativar-url');
        var statusLabel = btn.getAttribute('data-status-label') || '';
        var eventId = btn.getAttribute('data-event-id') || '';
        if (!previewUrl || !reativarUrl) return;

        resetReativarModal();
        if (eventIdEl) eventIdEl.value = eventId;
        if (urlEl) urlEl.value = reativarUrl;
        if (statusLabelEl) statusLabelEl.textContent = statusLabel;

        detailModal.hide();
        reativarModal.show();

        fetch(previewUrl, {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
          .then(function (r) {
            return r.json().then(function (res) {
              return { ok: r.ok, res: res };
            });
          })
          .then(function (x) {
            if (!x.ok || !x.res.success) {
              showBlocked([(x.res && (x.res.message || x.res.error)) || 'Não foi possível verificar a reativação.']);
              return;
            }
            if (!x.res.can_reactivate) {
              showBlocked(x.res.blockers && x.res.blockers.length ? x.res.blockers : ['Não é possível reativar esta marcação.']);
              return;
            }
            showForm(x.res.status_label || statusLabel);
          })
          .catch(function () {
            showBlocked(['Erro de ligação ao verificar a reativação.']);
          });
      });

      if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
          var url = urlEl ? urlEl.value : '';
          if (!url) return;
          confirmBtn.disabled = true;
          fetch(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrfToken(),
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
              reactivation_reason: reasonEl ? reasonEl.value : '',
              notify_client: !!(notifyEl && notifyEl.checked)
            })
          })
            .then(function (r) {
              return r.json().then(function (res) {
                return { ok: r.ok, res: res };
              });
            })
            .then(function (x) {
              if (!x.ok || !x.res.success) {
                confirmBtn.disabled = false;
                if (x.res && x.res.blockers && x.res.blockers.length) {
                  showBlocked(x.res.blockers);
                  return;
                }
                var msg = (x.res && (x.res.message || x.res.error)) || 'Erro ao reativar.';
                if (window.showToast) window.showToast(msg, 'error'); else alert(msg);
                return;
              }
              if (window.showToast) window.showToast(x.res.message || 'Marcação reativada.', 'success');
              window.location.reload();
            })
            .catch(function () {
              confirmBtn.disabled = false;
              if (window.showToast) window.showToast('Erro de ligação.', 'error'); else alert('Erro de ligação.');
            });
        });
      }
    })();
  </script>
  @include('relatorios.partials.pdf-print-dropdown-script')
@endsection
