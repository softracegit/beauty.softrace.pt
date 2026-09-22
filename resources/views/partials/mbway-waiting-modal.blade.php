{{-- Modal pequenino: espera autorização MB Way (Stripe) --}}
<div
  class="modal fade"
  id="mbwayWaitingModal"
  tabindex="-1"
  aria-labelledby="mbwayWaitingModalLabel"
  aria-hidden="true"
  data-bs-backdrop="static"
  data-bs-keyboard="false"
  role="dialog"
  aria-modal="true"
>
  <div class="modal-dialog modal-dialog-centered mbway-waiting-modal-dialog">
    <div class="modal-content mbway-waiting-modal-content">
      <div class="modal-body text-center px-4 py-4">
        <div class="mbway-waiting-brand mb-3">
          @include('partials.mbway-logo', ['class' => 'mbway-waiting-brand__logo'])
        </div>
        <div class="mbway-waiting-spinner mb-3" role="status" aria-label="A aguardar">
          <span class="spinner-border text-primary" aria-hidden="true"></span>
        </div>
        <h5 class="mbway-waiting-title mb-2" id="mbwayWaitingModalLabel">A aguardar pagamento</h5>
        <p class="mbway-waiting-text text-muted small mb-3">
          O cliente tem <strong>4 minutos</strong> para autorizar no telemóvel.
        </p>
        <p class="mbway-waiting-countdown mb-4" id="mbwayWaitingCountdown" aria-live="polite">4:00</p>
        <button type="button" class="btn btn-outline-secondary btn-sm px-4" id="mbwayWaitingCancelBtn">
          Cancelar
        </button>
      </div>
    </div>
  </div>
</div>
