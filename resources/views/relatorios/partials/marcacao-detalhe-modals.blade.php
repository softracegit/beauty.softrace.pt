@if(($marcacoes ?? collect())->count() > 0)
  @php
    $marcacaoDetailMode = $marcacaoDetailMode ?? 'full';
  @endphp
  @foreach($marcacoes as $ev)
    <template id="marcacao-detail-{{ $ev->id }}">
      @include('relatorios._marcacao_modal_fragment', [
        'ev' => $ev,
        'marcacaoDetailMode' => $marcacaoDetailMode,
      ])
    </template>
  @endforeach

  <style>
    .marcacao-detalhe-offcanvas {
      --bs-offcanvas-width: 50vw !important;
      width: 50vw !important;
      max-width: 100vw;
    }
    .marcacao-detalhe-offcanvas.offcanvas-end {
      border-left: 1px solid var(--border-color, rgba(0, 0, 0, 0.1));
      border-radius: 20px 0 0 20px;
      overflow: hidden;
    }
    .marcacao-detalhe-offcanvas:focus,
    .marcacao-detalhe-offcanvas:focus-visible {
      outline: none;
      box-shadow: none;
    }
    .marcacao-detalhe-offcanvas .offcanvas-header {
      padding: 1rem 1.75rem;
      gap: 0.75rem;
    }
    .marcacao-detalhe-offcanvas .offcanvas-header .btn-close {
      margin: 0;
      order: -1;
    }
    .marcacao-detalhe-offcanvas .offcanvas-body {
      flex: 1 1 auto;
      padding: 1.25rem 1.75rem 1.75rem;
      overflow-y: auto;
    }
    .marcacao-detalhe-offcanvas__footer {
      flex-shrink: 0;
      padding: 1rem 1.75rem;
      box-sizing: border-box;
      background: var(--bs-body-bg, #fff);
    }
    .marcacao-detalhe-offcanvas__footer .btn {
      font-size: 0.875rem;
      font-weight: 500;
      line-height: 1.5;
      padding: var(--spacing-sm, 0.375rem) var(--spacing-lg, 1rem);
      box-sizing: border-box;
      height: calc(0.875rem * 1.5 + var(--spacing-sm, 0.375rem) * 2 + 2px);
      min-height: calc(0.875rem * 1.5 + var(--spacing-sm, 0.375rem) * 2 + 2px);
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    @media (max-width: 991.98px) {
      .marcacao-detalhe-offcanvas {
        --bs-offcanvas-width: 100% !important;
        width: 100% !important;
        border-radius: 0 !important;
      }
    }
  </style>

  <div
    class="offcanvas offcanvas-end marcacao-detalhe-offcanvas"
    tabindex="-1"
    id="marcacaoDetalheModal"
    aria-labelledby="marcacaoDetalheModalLabel"
    data-bs-scroll="true"
  >
    <div class="offcanvas-header border-bottom">
      <h5 class="offcanvas-title mb-0 fw-semibold" id="marcacaoDetalheModalLabel">Ficha técnica</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body csheet-modal-body" id="marcacaoDetalheModalBody"></div>
    <div class="border-top marcacao-detalhe-offcanvas__footer d-flex flex-wrap align-items-center justify-content-between gap-2" id="marcacaoDetalheModalFooter"></div>
  </div>

  @if(auth()->user()?->isAdmin())
    <div class="modal fade" id="clienteMarcacaoActivityLogModal" tabindex="-1" aria-labelledby="clienteMarcacaoActivityLogModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title fw-semibold" id="clienteMarcacaoActivityLogModalLabel">Histórico da marcação</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body py-3" id="clienteMarcacaoActivityLogBody">
            <p class="text-muted text-center py-3 mb-0">A carregar…</p>
          </div>
        </div>
      </div>
    </div>

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
@endif
