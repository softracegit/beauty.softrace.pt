<div class="modal fade" id="crmCashRegisterOpenModal" tabindex="-1" aria-labelledby="crmCashRegisterOpenModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="crmCashRegisterOpenForm" method="POST" action="{{ route('caixa.open') }}" novalidate>
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="crmCashRegisterOpenModalLabel">Abrir caixa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">Indique o dinheiro inicial na gaveta (fundo de maneio).</p>
          <div id="crmCashRegisterOpenPendingWrap" class="d-none mb-3">
            <div class="alert alert-info py-2 small mb-0" id="crmCashRegisterOpenPendingAlert" role="status"></div>
          </div>
          <div id="crmCashRegisterOpenPendingLoading" class="text-muted small mb-3 d-none">
            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
            A verificar pré-pagamentos online…
          </div>
          <div class="mb-0">
            <label for="crmCashRegisterOpeningFloat" class="form-label">Dinheiro na caixa (€)</label>
            <input
              type="number"
              step="0.01"
              min="0"
              class="form-control"
              id="crmCashRegisterOpeningFloat"
              name="opening_float"
              value="0.00"
              required
            >
            <div class="invalid-feedback" id="crmCashRegisterOpenFloatError"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success" id="crmCashRegisterOpenSubmit">Abrir caixa</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="crmCashRegisterCloseModal" tabindex="-1" aria-labelledby="crmCashRegisterCloseModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="crmCashRegisterCloseForm" method="POST" action="{{ route('caixa.close.store') }}" novalidate>
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="crmCashRegisterCloseModalLabel">Fechar caixa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3" id="crmCashRegisterCloseSessionMeta"></p>
          <div id="crmCashRegisterCloseLoading" class="text-center py-4 text-muted d-none">
            <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
            A calcular movimentos…
          </div>
          <div id="crmCashRegisterCloseError" class="alert alert-danger d-none" role="alert"></div>
          <div id="crmCashRegisterCloseContent" class="d-none">
            <div class="table-responsive mb-4">
              <table class="table table-sm mb-0">
                <thead>
                  <tr>
                    <th>Método de pagamento</th>
                    <th class="text-end">Total CRM</th>
                  </tr>
                </thead>
                <tbody id="crmCashRegisterCloseMethodsBody"></tbody>
                <tfoot class="table-light">
                  <tr class="fw-semibold">
                    <td>Dinheiro esperado na gaveta</td>
                    <td class="text-end text-nowrap" id="crmCashRegisterCloseExpectedCash">—</td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <div class="row g-2 crm-cash-register-close-fields">
              <div class="col-sm-5 col-md-4">
                <label for="crmCashRegisterCountedCash" class="form-label small mb-1">Dinheiro contado na gaveta (€)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  class="form-control form-control-sm"
                  id="crmCashRegisterCountedCash"
                  name="counted_cash"
                  required
                >
                <div class="invalid-feedback" id="crmCashRegisterCloseCountedError"></div>
                <div class="form-text small text-nowrap" id="crmCashRegisterCloseCountedHint"></div>
              </div>
              <div class="col-12">
                <label for="crmCashRegisterCloseNotes" class="form-label small mb-1">Notas (opcional)</label>
                <textarea
                  class="form-control form-control-sm crm-cash-register-close-notes"
                  id="crmCashRegisterCloseNotes"
                  name="notes"
                  rows="1"
                  maxlength="2000"
                ></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning d-none" id="crmCashRegisterCloseSubmit">Confirmar fecho</button>
        </div>
      </form>
    </div>
  </div>
</div>
