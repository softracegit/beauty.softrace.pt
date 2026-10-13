{{-- Variáveis: bookingThemes, bookingTheme, bookingSlotIntervalOptions, bookingSlotIntervalMinutes, bookingSlotHoldMinutes, bookingCartTtlHours, bookingAnyStaffRules, bookingAnyStaffRule, bookingCancellationNoticeHours --}}
@php
  $idPrefix = $idPrefix ?? '';
@endphp

<div class="uedit-section mb-4">
  <div class="uedit-section-title">Aparência do Booking</div>
  <p class="text-muted small mb-3">Tema visual da página pública de marcações.</p>
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
              <span class="booking-theme-card__preview rounded-3 border" style="background: {{ $previewBg }}; --preview-accent: {{ $previewAccent }};" aria-hidden="true">
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
</div>

<div class="uedit-section mb-4 mt-5">
  <div class="uedit-section-title">Marcações</div>

  <div class="row g-3 align-items-end">
    <div class="col-12 col-md-4 col-lg-3">
      <label for="{{ $idPrefix }}booking_slot_interval_minutes" class="form-label fw-semibold mb-1">Blocos de horários</label>
      <select id="{{ $idPrefix }}booking_slot_interval_minutes" name="booking_slot_interval_minutes" class="form-select @error('booking_slot_interval_minutes') is-invalid @enderror" required>
        @foreach(($bookingSlotIntervalOptions ?? \App\Models\CrmSetting::BOOKING_SLOT_INTERVAL_MINUTES_OPTIONS) as $minutes)
          <option value="{{ $minutes }}" @selected((int) old('booking_slot_interval_minutes', $bookingSlotIntervalMinutes ?? \App\Models\CrmSetting::BOOKING_SLOT_INTERVAL_MINUTES_DEFAULT) === (int) $minutes)>
            De {{ $minutes }} em {{ $minutes }} minutos
          </option>
        @endforeach
      </select>
      @error('booking_slot_interval_minutes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
  <p class="small text-muted mb-0 mt-2">
    Define os horários apresentados ao cliente no Booking (ex.: 10:00, 10:30…). A agenda interna e a duração dos serviços não são alteradas.
  </p>

  <div class="row g-3 align-items-end mt-3">
    <div class="col-12 col-md-4 col-lg-3">
      <label for="{{ $idPrefix }}booking_slot_hold_minutes" class="form-label fw-semibold mb-1">Reserva temporária do slot</label>
      <div class="input-group">
        <input id="{{ $idPrefix }}booking_slot_hold_minutes" name="booking_slot_hold_minutes" type="number" min="1" max="240" step="1"
          class="form-control @error('booking_slot_hold_minutes') is-invalid @enderror"
          value="{{ old('booking_slot_hold_minutes', $bookingSlotHoldMinutes) }}" required>
        <span class="input-group-text">min</span>
      </div>
      @error('booking_slot_hold_minutes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
  <p class="small text-muted mb-0 mt-2">
    Tempo usado no checkout para reservar o horário temporariamente.
  </p>

  <div class="row g-3 align-items-end mt-3">
    <div class="col-12 col-md-4 col-lg-3">
      <label for="{{ $idPrefix }}booking_cart_ttl_hours" class="form-label fw-semibold mb-1">Memória dos serviços escolhidos</label>
      <div class="input-group">
        <input id="{{ $idPrefix }}booking_cart_ttl_hours" name="booking_cart_ttl_hours" type="number"
          min="{{ \App\Models\CrmSetting::BOOKING_CART_TTL_HOURS_MIN }}"
          max="{{ \App\Models\CrmSetting::BOOKING_CART_TTL_HOURS_MAX }}"
          step="1"
          class="form-control @error('booking_cart_ttl_hours') is-invalid @enderror"
          value="{{ old('booking_cart_ttl_hours', $bookingCartTtlHours ?? \App\Models\CrmSetting::BOOKING_CART_TTL_HOURS_DEFAULT) }}"
          required>
        <span class="input-group-text">horas</span>
      </div>
      @error('booking_cart_ttl_hours')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
  <p class="small text-muted mb-0 mt-2">
    Após este tempo, limpa serviços (e o resto da seleção) guardados no browser do cliente. Predefinido: 12 horas.
  </p>
</div>

<div class="uedit-section mb-4 mt-5">
  <div class="uedit-section-title">Regras de Qualquer Staff</div>
  <div class="d-flex flex-column gap-3">
    @foreach(($bookingAnyStaffRules ?? []) as $value => $rule)
      <label class="form-check d-flex align-items-start gap-2 mb-0">
        <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="booking_any_staff_rule" value="{{ $value }}"
          @checked(old('booking_any_staff_rule', $bookingAnyStaffRule ?? '') === $value)>
        <span class="form-check-label">
          <span class="d-block fw-semibold text-body">{{ $rule['title'] }}</span>
          <span class="d-block small text-muted mt-1 lh-sm">{{ $rule['description'] }}</span>
        </span>
      </label>
    @endforeach
  </div>
  @error('booking_any_staff_rule')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>

<div class="uedit-section mb-0 mt-5">
  <div class="uedit-section-title">Cancelamento</div>
  <div class="row g-3 align-items-end">
    <div class="col-12 col-md-5">
      <label for="{{ $idPrefix }}booking_cancellation_notice_hours" class="form-label fw-semibold mb-1">Aviso mínimo (horas)</label>
      <div class="input-group">
        <input id="{{ $idPrefix }}booking_cancellation_notice_hours" name="booking_cancellation_notice_hours" type="number"
          min="{{ \App\Models\CrmSetting::BOOKING_CANCELLATION_NOTICE_HOURS_MIN }}"
          max="{{ \App\Models\CrmSetting::BOOKING_CANCELLATION_NOTICE_HOURS_MAX }}" step="1"
          class="form-control @error('booking_cancellation_notice_hours') is-invalid @enderror"
          value="{{ old('booking_cancellation_notice_hours', $bookingCancellationNoticeHours) }}" required>
        <span class="input-group-text">h</span>
      </div>
      @error('booking_cancellation_notice_hours')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
  </div>
  <p class="small text-muted mb-0 mt-2">Prazo mínimo antes da marcação para cancelar sem perder o pré-pagamento.</p>
</div>

<style>
  .booking-theme-card { cursor: pointer; transition: border-color 0.15s ease, box-shadow 0.15s ease; border: 2px solid transparent; }
  .booking-theme-card.is-selected, .booking-theme-radio:checked + .booking-theme-card { border-color: var(--accent-color, #0d6efd); box-shadow: 0 0 0 1px var(--accent-color, #0d6efd); }
  .booking-theme-card__preview { display: grid; gap: 0.45rem; min-height: 5.5rem; padding: 0.75rem; }
  .booking-theme-card__preview-bar { display: block; height: 0.45rem; width: 38%; border-radius: 999px; background: var(--preview-accent); opacity: 0.85; }
  .booking-theme-card__preview-chip { display: inline-block; width: 2.2rem; height: 0.65rem; border-radius: 999px; border: 1px solid #d8d8d8; background: #fff; margin-right: 0.25rem; }
  .booking-theme-card__preview-chip--active { background: var(--preview-accent); border-color: var(--preview-accent); }
  .booking-theme-card__preview-line { display: block; height: 0.35rem; width: 72%; border-radius: 999px; background: rgba(17, 17, 17, 0.08); }
  .booking-theme-card__preview-line--short { width: 48%; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.booking-theme-radio').forEach(function (radio) {
    radio.addEventListener('change', function () {
      document.querySelectorAll('.booking-theme-card').forEach(function (card) { card.classList.remove('is-selected'); });
      if (radio.checked) radio.closest('label')?.querySelector('.booking-theme-card')?.classList.add('is-selected');
    });
  });
});
</script>
