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
  <div class="pay-toggle-row">
    <input type="hidden" name="pos_gorjeta_enabled" value="0">
    <div class="form-check form-switch d-flex align-items-center justify-content-between gap-3 m-0 p-0">
      <label class="form-check-label fw-semibold mb-0" for="{{ $idPrefix }}pos_gorjeta_enabled">Gorjeta na caixa</label>
      <input class="form-check-input m-0 flex-shrink-0" type="checkbox" role="switch" name="pos_gorjeta_enabled" value="1"
        id="{{ $idPrefix }}pos_gorjeta_enabled" @checked((bool) old('pos_gorjeta_enabled', $posGorjetaEnabled ?? false))>
    </div>
    <p class="small text-muted mb-0 mt-1">Quando desligada, o campo de gorjeta deixa de aparecer no modal de pagamento da agenda.</p>
  </div>
</div>
