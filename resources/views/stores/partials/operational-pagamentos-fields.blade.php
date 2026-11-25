{{-- Variáveis: posGorjetaEnabled, depositPolicy, pagamentosNote?, idPrefix? --}}
@php $idPrefix = $idPrefix ?? ''; @endphp

<div class="uedit-section mb-0">
  <div class="uedit-section-title">Pré-pagamento</div>
  @if (!empty($pagamentosNote))
    <p class="inherit-hint mb-3">{{ $pagamentosNote }}</p>
  @endif

  @include('definicoes.partials.deposit-policy-fields', [
    'depositPolicy' => old('deposit_tiers')
      ? [
        'agenda_enabled' => (bool) old('deposit_agenda_enabled', $depositPolicy['agenda_enabled'] ?? true),
        'booking_enabled' => (bool) old('deposit_booking_enabled', $depositPolicy['booking_enabled'] ?? true),
        'tiers' => old('deposit_tiers'),
      ]
      : ($depositPolicy ?? null),
    'idPrefix' => $idPrefix,
  ])
</div>

<div class="uedit-section mb-0 mt-4">
  <div class="uedit-section-title">Outras definições de pagamentos</div>
  <div class="py-3">
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
</div>
