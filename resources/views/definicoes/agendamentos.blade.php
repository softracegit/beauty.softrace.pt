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

        <div class="mb-2">
          <label class="form-label fw-semibold d-block">Regra de atribuição</label>
          <div id="bookingAnyStaffRuleGroup" class="d-flex flex-column gap-2">
            @foreach(($bookingAnyStaffRules ?? []) as $value => $label)
              <label class="form-check">
                <input
                  class="form-check-input"
                  type="radio"
                  name="booking_any_staff_rule"
                  value="{{ $value }}"
                  @checked(old('booking_any_staff_rule', $bookingAnyStaffRule ?? '') === $value)
                >
                <span class="form-check-label">{{ $label }}</span>
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
