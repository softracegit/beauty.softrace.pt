@extends('definicoes.layout')

@section('definicoes_content')
  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">Marcações</h5>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-4">
        Define o tempo de bloqueio temporário do horário durante o checkout das marcações online.
      </p>

      <form method="post" action="{{ route('definicoes.marcacoes.update') }}">
        @csrf
        <input type="hidden" name="booking_any_staff_rule" value="{{ old('booking_any_staff_rule', $bookingAnyStaffRule) }}">
        <input type="hidden" name="booking_cancellation_notice_hours" value="{{ old('booking_cancellation_notice_hours', $bookingCancellationNoticeHours) }}">

        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-4 col-lg-3">
            <label for="booking_slot_hold_minutes" class="form-label fw-semibold mb-1">Reserva temporária do slot</label>
            <div class="input-group">
              <input
                id="booking_slot_hold_minutes"
                name="booking_slot_hold_minutes"
                type="number"
                min="1"
                max="240"
                step="1"
                class="form-control @error('booking_slot_hold_minutes') is-invalid @enderror"
                value="{{ old('booking_slot_hold_minutes', $bookingSlotHoldMinutes) }}"
                required
              >
              <span class="input-group-text">min</span>
              @error('booking_slot_hold_minutes')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="form-text">Este tempo é usado no checkout público da marcação para reservar o horário temporariamente.</div>
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

  <div class="card mt-3">
    <div class="card-header">
      <h5 class="card-title mb-0">Regras de Qualquer Staff</h5>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-3">
        Define como o Booking escolhe automaticamente o técnico quando o cliente seleciona “Qualquer staff”.
      </p>

      <form method="post" action="{{ route('definicoes.marcacoes.update') }}">
        @csrf
        <input type="hidden" name="booking_slot_hold_minutes" value="{{ old('booking_slot_hold_minutes', $bookingSlotHoldMinutes) }}">
        <input type="hidden" name="booking_cancellation_notice_hours" value="{{ old('booking_cancellation_notice_hours', $bookingCancellationNoticeHours) }}">

        <div class="mb-2">
          <label class="form-label fw-semibold d-block">Regra de atribuição</label>
          <div id="bookingAnyStaffRuleGroup" class="d-flex flex-column gap-3">
            @foreach(($bookingAnyStaffRules ?? []) as $value => $rule)
              <label class="form-check d-flex align-items-start gap-2 mb-0">
                <input
                  class="form-check-input mt-1 flex-shrink-0"
                  type="radio"
                  name="booking_any_staff_rule"
                  value="{{ $value }}"
                  @checked(old('booking_any_staff_rule', $bookingAnyStaffRule ?? '') === $value)
                >
                <span class="form-check-label">
                  <span class="d-block fw-semibold text-body">{{ $rule['title'] }}</span>
                  <span class="d-block small text-muted mt-1 lh-sm">{{ $rule['description'] }}</span>
                </span>
              </label>
            @endforeach
          </div>
          @error('booking_any_staff_rule')
            <div class="text-danger small mt-1">{{ $message }}</div>
          @enderror
        </div>

        <div class="uedit-form-actions pt-3 mt-2">
          <button type="submit" class="btn btn-primary">
            <i class="ph ph-check me-1"></i> Guardar alterações
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-header">
      <h5 class="card-title mb-0">Cancelamento</h5>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-4">
        Define o aviso mínimo para cancelamento sem perda do pré-pagamento. Fora deste prazo, o pré-pagamento online não é devolvido
        (nem em dinheiro nem em créditos na carteira).
      </p>

      <form method="post" action="{{ route('definicoes.marcacoes.update') }}">
        @csrf
        <input type="hidden" name="booking_slot_hold_minutes" value="{{ old('booking_slot_hold_minutes', $bookingSlotHoldMinutes) }}">
        <input type="hidden" name="booking_any_staff_rule" value="{{ old('booking_any_staff_rule', $bookingAnyStaffRule) }}">

        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-4 col-lg-3">
            <label for="booking_cancellation_notice_hours" class="form-label fw-semibold mb-1">Aviso mínimo para cancelamento sem penalização</label>
            <div class="input-group">
              <input
                id="booking_cancellation_notice_hours"
                name="booking_cancellation_notice_hours"
                type="number"
                min="{{ \App\Models\CrmSetting::BOOKING_CANCELLATION_NOTICE_HOURS_MIN }}"
                max="{{ \App\Models\CrmSetting::BOOKING_CANCELLATION_NOTICE_HOURS_MAX }}"
                step="1"
                class="form-control @error('booking_cancellation_notice_hours') is-invalid @enderror"
                value="{{ old('booking_cancellation_notice_hours', $bookingCancellationNoticeHours) }}"
                required
              >
              <span class="input-group-text">h</span>
              @error('booking_cancellation_notice_hours')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="form-text">
              O cliente pode cancelar sem perder o pré-pagamento se o pedido for feito pelo menos este número de horas antes da marcação
              (fuso horário da loja). Com 0 horas, o prazo é até ao início da marcação.
            </div>
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
