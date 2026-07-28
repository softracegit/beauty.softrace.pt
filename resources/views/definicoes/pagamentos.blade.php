@extends('definicoes.layout')

@section('definicoes_content')
  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">Pagamentos</h5>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-4">
        Opções de pagamento na loja (caixa) e no site público de marcações
        <span class="text-nowrap">(<code>/booking</code>)</span>.
      </p>

      <form method="post" action="{{ route('definicoes.pagamentos.update') }}">
        @csrf

        <div class="py-3 border-bottom">
          <div class="d-flex align-items-center justify-content-between gap-3">
            <label class="fw-semibold mb-0" for="pos_gorjeta_enabled" style="cursor: pointer">
              Gorjeta na caixa
            </label>
            <div class="form-check form-switch m-0 flex-shrink-0">
              <input type="hidden" name="pos_gorjeta_enabled" value="0">
              <input
                class="form-check-input"
                type="checkbox"
                name="pos_gorjeta_enabled"
                value="1"
                id="pos_gorjeta_enabled"
                @checked($posGorjetaEnabled)
              >
            </div>
          </div>
          <p class="small text-muted mb-0 mt-2 w-100">
            Quando está desligada, o campo de gorjeta deixa de aparecer no modal «Caixa — pagamento» da agenda e o valor enviado é sempre zero.
          </p>
        </div>

        <div class="py-3 border-bottom">
          <div class="d-flex align-items-center justify-content-between gap-3">
            <label class="fw-semibold mb-0" for="online_booking_payment_required" style="cursor: pointer">
              Pagamento nas marcações online
            </label>
            <div class="form-check form-switch m-0 flex-shrink-0">
              <input
                class="form-check-input"
                type="checkbox"
                name="online_booking_payment_required"
                value="1"
                id="online_booking_payment_required"
                @checked($onlineBookingPaymentRequired)
              >
            </div>
          </div>
          <p class="small text-muted mb-0 mt-2 w-100">
            Quando está ligado, o site público e a agenda usam Stripe (cartão e MB WAY automático).
            Quando está desligado, o passo de pagamento e as opções de fatura no site não aparecem; na agenda os pagamentos são manuais
            (dinheiro, transferência ou MB WAY registado apenas como controlo interno).
          </p>
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
