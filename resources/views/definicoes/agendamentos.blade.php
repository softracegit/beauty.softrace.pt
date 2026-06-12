@extends('definicoes.layout')

@section('definicoes_content')
  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">Aparência do Booking</h5>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-4">
        Escolhe o tema visual da página pública de marcações desta loja. O tema clássico mantém o aspeto atual; o tema elegante aplica um estilo minimalista inspirado em salões de beleza.
      </p>

      <form method="post" action="{{ route('definicoes.marcacoes.update') }}">
        @csrf
        <input type="hidden" name="booking_slot_hold_minutes" value="{{ old('booking_slot_hold_minutes', $bookingSlotHoldMinutes) }}">
        <input type="hidden" name="booking_any_staff_rule" value="{{ old('booking_any_staff_rule', $bookingAnyStaffRule) }}">
        <input type="hidden" name="booking_cancellation_notice_hours" value="{{ old('booking_cancellation_notice_hours', $bookingCancellationNoticeHours) }}">

        <div class="row g-3">
          @foreach(($bookingThemes ?? []) as $theme)
            @php
              $themeId = $theme['id'];
              [$previewBg, $previewAccent] = match ($themeId) {
                  \App\Support\BookingTheme::ELEGANT => ['#faf8f5', '#111111'],
                  \App\Support\BookingTheme::NOIR => ['#13111a', '#9d8cff'],
                  default => ['#f3f4f6', '#0d6efd'],
              };
            @endphp
            <div class="col-12 col-md-6 col-xl-4">
              <label class="d-block h-100 mb-0">
                <input
                  class="form-check-input visually-hidden booking-theme-radio"
                  type="radio"
                  name="booking_theme"
                  value="{{ $themeId }}"
                  @checked(old('booking_theme', $bookingTheme ?? \App\Support\BookingTheme::DEFAULT) === $themeId)
                >
                <span class="card h-100 booking-theme-card {{ old('booking_theme', $bookingTheme ?? \App\Support\BookingTheme::DEFAULT) === $themeId ? 'is-selected' : '' }}">
                  <span class="card-body d-flex flex-column gap-2">
                    <span
                      class="booking-theme-card__preview rounded-3 border"
                      style="background: {{ $previewBg }}; --preview-accent: {{ $previewAccent }};"
                      aria-hidden="true"
                    >
                      <span class="booking-theme-card__preview-bar"></span>
                      <span class="booking-theme-card__preview-chip booking-theme-card__preview-chip--active"></span>
                      <span class="booking-theme-card__preview-chip"></span>
                      <span class="booking-theme-card__preview-line"></span>
                      <span class="booking-theme-card__preview-line booking-theme-card__preview-line--short"></span>
                    </span>
                    <span class="fw-semibold text-body">{{ $theme['label'] }}</span>
                    <span class="small text-muted lh-sm">{{ $theme['description'] }}</span>
                  </span>
                </span>
              </label>
            </div>
          @endforeach
        </div>
        @error('booking_theme')
          <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror

        <div class="uedit-form-actions pt-4 mt-2">
          <button type="submit" class="btn btn-primary">
            <i class="ph ph-check me-1"></i> Guardar tema
          </button>
        </div>
      </form>
    </div>
  </div>

  <style>
    .booking-theme-card {
      cursor: pointer;
      transition: border-color 0.15s ease, box-shadow 0.15s ease;
      border: 2px solid transparent;
    }

    .booking-theme-card.is-selected,
    .booking-theme-radio:checked + .booking-theme-card {
      border-color: var(--accent-color, #0d6efd);
      box-shadow: 0 0 0 1px var(--accent-color, #0d6efd);
    }

    .booking-theme-card__preview {
      display: grid;
      gap: 0.45rem;
      min-height: 5.5rem;
      padding: 0.75rem;
    }

    .booking-theme-card__preview-bar {
      display: block;
      height: 0.45rem;
      width: 38%;
      border-radius: 999px;
      background: var(--preview-accent);
      opacity: 0.85;
    }

    .booking-theme-card__preview-chip {
      display: inline-block;
      width: 2.2rem;
      height: 0.65rem;
      border-radius: 999px;
      border: 1px solid #d8d8d8;
      background: #fff;
      margin-right: 0.25rem;
    }

    .booking-theme-card__preview-chip--active {
      background: var(--preview-accent);
      border-color: var(--preview-accent);
    }

    .booking-theme-card__preview-line {
      display: block;
      height: 0.35rem;
      width: 72%;
      border-radius: 999px;
      background: rgba(17, 17, 17, 0.08);
    }

    .booking-theme-card__preview-line--short {
      width: 48%;
    }
  </style>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('.booking-theme-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
          document.querySelectorAll('.booking-theme-card').forEach(function (card) {
            card.classList.remove('is-selected');
          });
          if (radio.checked) {
            radio.closest('label')?.querySelector('.booking-theme-card')?.classList.add('is-selected');
          }
        });
      });
    });
  </script>

  <div class="card mt-3">
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
