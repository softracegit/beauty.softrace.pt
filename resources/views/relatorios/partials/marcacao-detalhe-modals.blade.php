@if(($marcacoes ?? collect())->count() > 0)
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
@endif
