{{-- Variáveis: depositPolicy, idPrefix? --}}
@php
  $idPrefix = $idPrefix ?? '';
  $policy = $depositPolicy ?? [
    'agenda_enabled' => true,
    'booking_enabled' => true,
    'tiers' => [['up_to' => null, 'mode' => 'percent', 'value' => 20]],
  ];
  $agendaOn = !empty($policy['agenda_enabled']) || (
    !array_key_exists('agenda_enabled', $policy) && !empty($policy['enabled'])
  );
  $bookingOn = !empty($policy['booking_enabled']) || (
    !array_key_exists('booking_enabled', $policy) && !empty($policy['enabled'])
  );
  $tiers = is_array($policy['tiers'] ?? null) ? $policy['tiers'] : [];
  if ($tiers === []) {
      $tiers = [['up_to' => null, 'mode' => 'percent', 'value' => 20]];
  }
@endphp

<div data-deposit-policy-root>
  <div class="pay-toggle-row border-bottom">
    <input type="hidden" name="deposit_agenda_enabled" value="0">
    <div class="form-check form-switch d-flex align-items-center justify-content-between gap-3 m-0 p-0">
      <label class="form-check-label fw-semibold mb-0" for="{{ $idPrefix }}deposit_agenda_enabled">Agenda</label>
      <input class="form-check-input m-0 flex-shrink-0" type="checkbox" role="switch" name="deposit_agenda_enabled" value="1"
        id="{{ $idPrefix }}deposit_agenda_enabled" @checked($agendaOn)>
    </div>
    <p class="small text-muted mb-0 mt-1">Mostra e permite cobrar o pré-pagamento na agenda.</p>
  </div>

  <div class="pay-toggle-row border-bottom">
    <input type="hidden" name="deposit_booking_enabled" value="0">
    <div class="form-check form-switch d-flex align-items-center justify-content-between gap-3 m-0 p-0">
      <label class="form-check-label fw-semibold mb-0" for="{{ $idPrefix }}deposit_booking_enabled">Booking</label>
      <input class="form-check-input m-0 flex-shrink-0" type="checkbox" role="switch" name="deposit_booking_enabled" value="1"
        id="{{ $idPrefix }}deposit_booking_enabled" @checked($bookingOn)>
    </div>
    <p class="small text-muted mb-0 mt-1">Exige o pré-pagamento no site público de marcações.</p>
  </div>

  <div class="pay-toggle-row deposit-tiers" data-deposit-tiers-wrap>
    <p class="fw-semibold mb-1">Regras</p>
    <p class="small text-muted mb-2">
      As mesmas regras aplicam-se à Agenda e ao Booking quando o respectivo toggle está ligado. Pode editar mesmo com ambos desligados.
    </p>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-2" data-deposit-tiers-table>
        <thead>
          <tr class="small text-muted">
            <th scope="col">Até (€)</th>
            <th scope="col">Tipo</th>
            <th scope="col">Valor</th>
            <th scope="col" class="text-end" style="width: 2.5rem;"></th>
          </tr>
        </thead>
        <tbody data-deposit-tiers-body>
          @foreach ($tiers as $i => $tier)
            @php
              $mode = ($tier['mode'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
              $upTo = $tier['up_to'] ?? null;
              $isCatchAll = $upTo === null || $upTo === '';
            @endphp
            <tr data-deposit-tier-row>
              <td>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                  name="deposit_tiers[{{ $i }}][up_to]"
                  value="{{ $isCatchAll ? '' : number_format((float) $upTo, 2, '.', '') }}"
                  placeholder="∞ / ou mais"
                  @if($isCatchAll) data-deposit-catch-all @endif>
              </td>
              <td>
                <select class="form-select form-select-sm" name="deposit_tiers[{{ $i }}][mode]">
                  <option value="percent" @selected($mode === 'percent')>%</option>
                  <option value="fixed" @selected($mode === 'fixed')>€</option>
                </select>
              </td>
              <td>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                  name="deposit_tiers[{{ $i }}][value]"
                  value="{{ number_format((float) ($tier['value'] ?? 0), 2, '.', '') }}" required>
              </td>
              <td class="text-end">
                <button type="button" class="btn btn-link btn-sm text-danger p-0" data-deposit-tier-remove title="Remover" aria-label="Remover intervalo">&times;</button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-deposit-tier-add>+ Intervalo</button>
    <p class="small text-muted mb-0 mt-2">
      Deixe «Até» vazio na última linha para «esse valor ou mais». Ex.: 24,99 → regra A; vazio → regra B para 25,00+.
    </p>
  </div>

  <template data-deposit-tier-template>
    <tr data-deposit-tier-row>
      <td>
        <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="deposit_tiers[__I__][up_to]" placeholder="∞ / ou mais">
      </td>
      <td>
        <select class="form-select form-select-sm" name="deposit_tiers[__I__][mode]">
          <option value="percent" selected>%</option>
          <option value="fixed">€</option>
        </select>
      </td>
      <td>
        <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="deposit_tiers[__I__][value]" value="20" required>
      </td>
      <td class="text-end">
        <button type="button" class="btn btn-link btn-sm text-danger p-0" data-deposit-tier-remove title="Remover" aria-label="Remover intervalo">&times;</button>
      </td>
    </tr>
  </template>
</div>

@once
<script>
(function () {
  function reindex(root) {
    var rows = root.querySelectorAll('[data-deposit-tiers-body] [data-deposit-tier-row]');
    rows.forEach(function (row, i) {
      row.querySelectorAll('[name]').forEach(function (el) {
        el.name = el.name.replace(/deposit_tiers\[\d+\]/, 'deposit_tiers[' + i + ']');
        el.name = el.name.replace(/deposit_tiers\[__I__\]/, 'deposit_tiers[' + i + ']');
      });
    });
  }

  function bindRoot(root) {
    if (!root || root.dataset.depositBound === '1') return;
    root.dataset.depositBound = '1';
    var body = root.querySelector('[data-deposit-tiers-body]');
    var tpl = root.querySelector('[data-deposit-tier-template]');

    root.addEventListener('click', function (e) {
      var addBtn = e.target.closest('[data-deposit-tier-add]');
      if (addBtn && root.contains(addBtn) && body && tpl) {
        e.preventDefault();
        var html = tpl.innerHTML.replace(/__I__/g, String(body.querySelectorAll('[data-deposit-tier-row]').length));
        body.insertAdjacentHTML('beforeend', html);
        reindex(root);
        return;
      }
      var rm = e.target.closest('[data-deposit-tier-remove]');
      if (rm && root.contains(rm)) {
        e.preventDefault();
        var row = rm.closest('[data-deposit-tier-row]');
        var rows = body ? body.querySelectorAll('[data-deposit-tier-row]') : [];
        if (row && rows.length > 1) {
          row.remove();
          reindex(root);
        }
      }
    });
  }

  function init() {
    document.querySelectorAll('[data-deposit-policy-root]').forEach(bindRoot);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
@endonce

