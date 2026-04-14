@extends('definicoes.layout')

@section('definicoes_content')
  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">Pagamentos</h5>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-4">
        Controla se o cliente paga um depósito online (Stripe) antes de confirmar a marcação no site público
        <span class="text-nowrap">(<code>/booking</code>)</span>.
      </p>

      <form method="post" action="{{ route('definicoes.pagamentos.update') }}">
        @csrf

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 py-3 border-top border-bottom">
          <div class="flex-grow-1" style="max-width: 36rem;">
            <div class="fw-semibold mb-1">Pagamento nas marcações online</div>
            <p class="small text-muted mb-0">
              Quando está desligado, o passo de pagamento não aparece e a marcação fica confirmada após os dados de contacto
              (o cliente paga na loja, se aplicável).
            </p>
          </div>
          <div class="form-check form-switch m-0">
            <input
              class="form-check-input"
              type="checkbox"
              name="online_booking_payment_required"
              value="1"
              id="online_booking_payment_required"
              @checked($onlineBookingPaymentRequired)
            >
            <label class="form-check-label visually-hidden" for="online_booking_payment_required">
              Exigir pagamento online na marcação
            </label>
          </div>
        </div>

        <div class="uedit-form-actions pt-4 mt-2">
          <button type="submit" class="btn btn-primary">
            <i class="ph ph-check me-1"></i> Guardar alterações
          </button>
        </div>
      </form>
    </div>
  </div>
  @if (session('status'))
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.showToast === 'function') {
          window.showToast(@json(session('status')), 'success');
        }
      });
    </script>
  @endif
@endsection
