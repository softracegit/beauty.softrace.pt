{{-- Tabela de horário semanal (só leitura), mesma lógica que o formulário de edição. --}}
@php
    $dayLabels = \App\Models\Agent::weekdayLabels();
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
    Dias e horas em que este membro está disponível para marcações na agenda (cruzado com o horário da loja).
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
                        @if($d['enabled'])
                            <span class="badge bg-success-light text-success">Sim</span>
                        @else
                            <span class="badge bg-secondary-light text-secondary">Não</span>
                        @endif
                    </td>
                    <td>
                        @if($d['enabled'])
                            {{ $d['start'] }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($d['enabled'])
                            {{ $d['end'] }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
