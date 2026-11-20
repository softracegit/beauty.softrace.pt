@extends('definicoes.layout')

@section('definicoes_content')
  @php
    use App\Services\BookingReminderSettings;
    $r = $reminders;
  @endphp

  <form method="post" action="{{ route('definicoes.lembretes.update') }}" id="definicoes-lembretes-form">
    @csrf

    <div class="card mb-3">
      <div class="card-header">
        <h5 class="card-title mb-0">Canais activos</h5>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-4">Liga ou desliga o envio automático de lembretes ao cliente. O opt-in do cliente (SMS/email) continua a ser respeitado.</p>

        <div class="row g-3">
          <div class="col-12 col-md-6">
            <div class="border rounded-3 p-3 h-100">
              <div class="fw-semibold mb-2">Dia anterior</div>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="sms_day_before_enabled" value="1" id="sms_day_before_enabled" @checked(old('sms_day_before_enabled', $r['sms_day_before_enabled']))>
                <label class="form-check-label" for="sms_day_before_enabled">SMS</label>
              </div>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" name="email_day_before_enabled" value="1" id="email_day_before_enabled" @checked(old('email_day_before_enabled', $r['email_day_before_enabled']))>
                <label class="form-check-label" for="email_day_before_enabled">Email</label>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="border rounded-3 p-3 h-100">
              <div class="fw-semibold mb-2">Próprio dia</div>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="sms_same_day_enabled" value="1" id="sms_same_day_enabled" @checked(old('sms_same_day_enabled', $r['sms_same_day_enabled']))>
                <label class="form-check-label" for="sms_same_day_enabled">SMS</label>
              </div>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" name="email_same_day_enabled" value="1" id="email_same_day_enabled" @checked(old('email_same_day_enabled', $r['email_same_day_enabled']))>
                <label class="form-check-label" for="email_same_day_enabled">Email</label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        <h5 class="card-title mb-0">Timings — dia anterior</h5>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">Janela horária local em que o SMS/email do dia anterior pode ser despachado. Se a hora de fim for ≤ início, a janela é ignorada.</p>
        <div class="row g-3">
          <div class="col-6 col-md-3">
            <label for="day_before_send_start_hour" class="form-label fw-semibold">Hora início</label>
            <select name="day_before_send_start_hour" id="day_before_send_start_hour" class="form-select @error('day_before_send_start_hour') is-invalid @enderror">
              @for($h = 0; $h <= 23; $h++)
                <option value="{{ $h }}" @selected((int) old('day_before_send_start_hour', $r['day_before_send_start_hour']) === $h)>{{ sprintf('%02d:00', $h) }}</option>
              @endfor
            </select>
            @error('day_before_send_start_hour')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-6 col-md-3">
            <label for="day_before_send_end_hour" class="form-label fw-semibold">Hora fim</label>
            <select name="day_before_send_end_hour" id="day_before_send_end_hour" class="form-select @error('day_before_send_end_hour') is-invalid @enderror">
              @for($h = 1; $h <= 24; $h++)
                <option value="{{ $h }}" @selected((int) old('day_before_send_end_hour', $r['day_before_send_end_hour']) === $h)>{{ $h === 24 ? '24:00' : sprintf('%02d:00', $h) }}</option>
              @endfor
            </select>
            @error('day_before_send_end_hour')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12 col-md-6">
            <label for="min_minutes_after_created" class="form-label fw-semibold">Atraso mínimo após criar (min)</label>
            <input type="number" min="0" max="1440" name="min_minutes_after_created" id="min_minutes_after_created" class="form-control @error('min_minutes_after_created') is-invalid @enderror" value="{{ old('min_minutes_after_created', $r['min_minutes_after_created']) }}">
            <div class="form-text">Evita envio imediato quando a marcação é criada no dia anterior.</div>
            @error('min_minutes_after_created')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        <h5 class="card-title mb-0">Timings — próprio dia</h5>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">Envia quando faltam entre o mínimo e o máximo de horas para a marcação, e só entre a hora de início e a hora de fim (ex.: loja 09:00–20:00 → janela 08:00–18:00).</p>
        <div class="row g-3">
          <div class="col-6 col-md-3">
            <label for="same_day_send_start_hour" class="form-label fw-semibold">Hora início</label>
            <select name="same_day_send_start_hour" id="same_day_send_start_hour" class="form-select @error('same_day_send_start_hour') is-invalid @enderror">
              @for($h = 0; $h <= 23; $h++)
                <option value="{{ $h }}" @selected((int) old('same_day_send_start_hour', BookingReminderSettings::minutesToStartHour($r['same_day_send_start_minutes'])) === $h)>{{ sprintf('%02d:00', $h) }}</option>
              @endfor
            </select>
            @error('same_day_send_start_hour')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-6 col-md-3">
            <label for="same_day_send_end_hour" class="form-label fw-semibold">Hora fim</label>
            <select name="same_day_send_end_hour" id="same_day_send_end_hour" class="form-select @error('same_day_send_end_hour') is-invalid @enderror">
              @for($h = 1; $h <= 24; $h++)
                <option value="{{ $h }}" @selected((int) old('same_day_send_end_hour', BookingReminderSettings::minutesToEndHour($r['same_day_send_end_minutes'])) === $h)>{{ $h === 24 ? '24:00' : sprintf('%02d:00', $h) }}</option>
              @endfor
            </select>
            @error('same_day_send_end_hour')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-6 col-md-3">
            <label for="same_day_lead_min_hours" class="form-label fw-semibold">Antecedência mín. (horas)</label>
            <input type="number" min="0.5" max="24" step="0.5" name="same_day_lead_min_hours" id="same_day_lead_min_hours" class="form-control @error('same_day_lead_min_hours') is-invalid @enderror" value="{{ old('same_day_lead_min_hours', BookingReminderSettings::minutesToHours($r['same_day_lead_min_minutes'])) }}">
            @error('same_day_lead_min_hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-6 col-md-3">
            <label for="same_day_lead_max_hours" class="form-label fw-semibold">Antecedência máx. (horas)</label>
            <input type="number" min="0.5" max="24" step="0.5" name="same_day_lead_max_hours" id="same_day_lead_max_hours" class="form-control @error('same_day_lead_max_hours') is-invalid @enderror" value="{{ old('same_day_lead_max_hours', BookingReminderSettings::minutesToHours($r['same_day_lead_max_minutes'])) }}">
            @error('same_day_lead_max_hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
      </div>
    </div>

    <div class="uedit-form-actions">
      <button type="submit" class="btn btn-primary">
        <i class="ph ph-check me-1"></i> Guardar alterações
      </button>
    </div>
  </form>
@endsection
