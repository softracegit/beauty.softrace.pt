@php
    $dayLabels = \App\Models\Agent::weekdayLabels();
    $timeOptions = [];
    for ($h = 0; $h < 24; $h++) {
        foreach ([0, 15, 30, 45] as $m) {
            $timeOptions[] = sprintf('%02d:%02d', $h, $m);
        }
    }
    $defaultDay = ['enabled' => true, 'start' => '09:00', 'end' => '20:00'];
    $resolveDay = function (string $key) use ($weeklySchedule, $defaultDay) {
        $v = is_array($weeklySchedule) ? ($weeklySchedule[$key] ?? null) : null;
        if (! is_array($v)) {
            return $defaultDay;
        }

        return [
            'enabled' => ! empty($v['enabled']),
            'start' => $v['start'] ?? $defaultDay['start'],
            'end' => $v['end'] ?? $defaultDay['end'],
        ];
    };
@endphp
  <p class="text-muted small mb-3">
    Define em que dias e horas a loja está aberta. Domingo costuma ficar desativado; pode ajustar por dia.
  </p>
  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle agent-weekly-schedule-table mb-0">
      <thead class="table-light">
        <tr>
          <th scope="col" style="min-width: 10rem;">Dia</th>
          <th scope="col" class="text-center" style="width: 6rem;">Aberto</th>
          <th scope="col" style="min-width: 8rem;">Abre</th>
          <th scope="col" style="min-width: 8rem;">Fecha</th>
        </tr>
      </thead>
      <tbody>
        @foreach (\App\Models\Agent::WEEKDAY_KEYS as $dayKey)
          @php $d = $resolveDay($dayKey); @endphp
          <tr>
            <td>{{ $dayLabels[$dayKey] ?? $dayKey }}</td>
            <td class="text-center">
              <input type="hidden" name="weekly_schedule[{{ $dayKey }}][enabled]" value="0">
              <input type="checkbox" class="form-check-input" name="weekly_schedule[{{ $dayKey }}][enabled]" value="1" data-store-weekday-enabled="{{ $dayKey }}" @checked($d['enabled'])>
            </td>
            <td>
              <select class="form-select form-select-sm @error("weekly_schedule.$dayKey") is-invalid @enderror" name="weekly_schedule[{{ $dayKey }}][start]" data-store-weekday-field="{{ $dayKey }}" aria-label="Abre {{ $dayLabels[$dayKey] ?? $dayKey }}">
                @foreach ($timeOptions as $opt)
                  <option value="{{ $opt }}" @selected($d['start'] === $opt)>{{ $opt }}</option>
                @endforeach
              </select>
            </td>
            <td>
              <select class="form-select form-select-sm @error("weekly_schedule.$dayKey") is-invalid @enderror" name="weekly_schedule[{{ $dayKey }}][end]" data-store-weekday-field="{{ $dayKey }}" aria-label="Fecha {{ $dayLabels[$dayKey] ?? $dayKey }}">
                @foreach ($timeOptions as $opt)
                  <option value="{{ $opt }}" @selected($d['end'] === $opt)>{{ $opt }}</option>
                @endforeach
              </select>
              @error("weekly_schedule.$dayKey")
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
<script>
(function() {
    function sync() {
        document.querySelectorAll('[data-store-weekday-enabled]').forEach(function(cb) {
            var day = cb.getAttribute('data-store-weekday-enabled');
            var on = cb.checked;
            document.querySelectorAll('[data-store-weekday-field="' + day + '"]').forEach(function(sel) {
                sel.disabled = !on;
            });
        });
    }
    document.querySelectorAll('[data-store-weekday-enabled]').forEach(function(cb) {
        cb.addEventListener('change', sync);
    });
    sync();
})();
</script>
