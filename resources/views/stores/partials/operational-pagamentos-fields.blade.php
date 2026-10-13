{{-- Variáveis: posGorjetaEnabled, onlineBookingPaymentRequired, idPrefix? --}}
@php $idPrefix = $idPrefix ?? ''; @endphp

<div class="uedit-section mb-0">
  <div class="uedit-section-title">Comportamento</div>
  @if (!empty($pagamentosNote))
    <p class="inherit-hint mb-3">{{ $pagamentosNote }}</p>
  @endif

  <div class="py-3 border-bottom">
    <div class="d-flex align-items-center justify-content-between gap-3">
      <label class="fw-semibold mb-0" for="{{ $idPrefix }}pos_gorjeta_enabled" style="cursor: pointer">Gorjeta na caixa</label>
      <div class="form-check form-switch m-0 flex-shrink-0">
        <input type="hidden" name="pos_gorjeta_enabled" value="0">
        <input class="form-check-input" type="checkbox" name="pos_gorjeta_enabled" value="1"
          id="{{ $idPrefix }}pos_gorjeta_enabled" @checked((bool) old('pos_gorjeta_enabled', $posGorjetaEnabled ?? false))>
      </div>
    </div>
    <p class="small text-muted mb-0 mt-2">Quando desligada, o campo de gorjeta deixa de aparecer no modal de pagamento da agenda.</p>
  </div>

  <div class="py-3">
    <div class="d-flex align-items-center justify-content-between gap-3">
      <label class="fw-semibold mb-0" for="{{ $idPrefix }}online_booking_payment_required" style="cursor: pointer">Pagamento nas marcações online</label>
      <div class="form-check form-switch m-0 flex-shrink-0">
        <input type="hidden" name="online_booking_payment_required" value="0">
        <input class="form-check-input" type="checkbox" name="online_booking_payment_required" value="1"
          id="{{ $idPrefix }}online_booking_payment_required" @checked((bool) old('online_booking_payment_required', $onlineBookingPaymentRequired ?? false))>
      </div>
    </div>
    <p class="small text-muted mb-0 mt-2">Exige pagamento no site público de marcações desta loja.</p>
  </div>
</div>
