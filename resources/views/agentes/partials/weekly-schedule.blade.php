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
<div class="card">
    <div class="card-body">
        <div class="uedit-section">
            <div class="uedit-section-title">Horário na agenda</div>
            <p class="text-muted small mb-3">
                Indica em que dias e horas este membro está disponível para marcações. Na agenda, este horário cruza-se com o da loja ({{ $storeHoursLabel ?? 'ver Definições → Negócio' }}): fora desta janela o tempo aparece com riscas a cinzento; pode marcar na mesma, com aviso.
            </p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle agent-weekly-schedule-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="min-width: 10rem;">Dia</th>
                            <th scope="col" class="text-center" style="width: 6rem;">Ativo</th>
                            <th scope="col" style="min-width: 8rem;">Início</th>
                            <th scope="col" style="min-width: 8rem;">Fim</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (\App\Models\Agent::WEEKDAY_KEYS as $dayKey)
                            @php $d = $resolveDay($dayKey); @endphp
                            <tr>
                                <td>{{ $dayLabels[$dayKey] ?? $dayKey }}</td>
                                <td class="text-center">
                                    <input type="hidden" name="weekly_schedule[{{ $dayKey }}][enabled]" value="0">
                                    <input type="checkbox" class="form-check-input" name="weekly_schedule[{{ $dayKey }}][enabled]" value="1" data-agent-weekday-enabled="{{ $dayKey }}" @checked($d['enabled'])>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" name="weekly_schedule[{{ $dayKey }}][start]" data-agent-weekday-field="{{ $dayKey }}" aria-label="Início {{ $dayLabels[$dayKey] ?? $dayKey }}">
                                        @foreach ($timeOptions as $opt)
                                            <option value="{{ $opt }}" @selected($d['start'] === $opt)>{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" name="weekly_schedule[{{ $dayKey }}][end]" data-agent-weekday-field="{{ $dayKey }}" aria-label="Fim {{ $dayLabels[$dayKey] ?? $dayKey }}">
                                        @foreach ($timeOptions as $opt)
                                            <option value="{{ $opt }}" @selected($d['end'] === $opt)>{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    function sync() {
        document.querySelectorAll('[data-agent-weekday-enabled]').forEach(function(cb) {
            var day = cb.getAttribute('data-agent-weekday-enabled');
            var on = cb.checked;
            document.querySelectorAll('[data-agent-weekday-field="' + day + '"]').forEach(function(sel) {
                sel.disabled = !on;
            });
        });
    }
    document.querySelectorAll('[data-agent-weekday-enabled]').forEach(function(cb) {
        cb.addEventListener('change', sync);
    });
    sync();
})();
</script>
