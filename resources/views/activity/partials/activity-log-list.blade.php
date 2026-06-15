@php
    $calendarEventMorphClass = (new \App\Models\CalendarEvent())->getMorphClass();
    $clientMorphClass = (new \App\Models\Client())->getMorphClass();
    $agentMorphClass = (new \App\Models\Agent())->getMorphClass();
    $serviceMorphClass = (new \App\Models\Service())->getMorphClass();
    $extraMorphClass = (new \App\Models\Extra())->getMorphClass();
    $formatActivityValue = static function ($value) {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }
        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $json !== false ? (strlen($json) > 50 ? substr($json, 0, 50).'…' : $json) : '[valor complexo]';
        }

        $str = (string) $value;

        return strlen($str) > 50 ? substr($str, 0, 50).'…' : $str;
    };
@endphp

@if(isset($activities) && $activities->count() > 0)
    <div class="activity-log">
        @foreach($activities as $activity)
            @php
                $eventIcon = match($activity->event ?? '') {
                    'created' => 'ph ph-plus-circle',
                    'updated' => 'ph ph-pencil-simple',
                    'deleted' => 'ph ph-trash',
                    default => 'ph ph-info',
                };
                $eventClass = match($activity->event ?? '') {
                    'created' => 'bg-success-light text-success',
                    'updated' => 'bg-primary-light text-primary',
                    'deleted' => 'bg-danger-light text-danger',
                    default => 'bg-secondary-light text-secondary',
                };

                $subjectLink = null;
                if (!empty($activity->subject_id)) {
                    $subjectId = (int) $activity->subject_id;
                    $subjectType = $activity->subject_type ?? null;

                    if ($subjectType === $calendarEventMorphClass) {
                        $subjectLink = route('agenda.index', ['event' => (string) $subjectId]);
                    } elseif ($subjectType === $clientMorphClass) {
                        $subjectLink = route('clientes.show', $subjectId);
                    } elseif ($subjectType === $agentMorphClass) {
                        $subjectLink = route('equipa.show', ['agente' => $subjectId]);
                    } elseif ($subjectType === $serviceMorphClass) {
                        $subjectLink = route('services.show', $subjectId);
                    } elseif ($subjectType === $extraMorphClass) {
                        $subjectLink = route('extras.show', $subjectId);
                    }
                }
            @endphp
            <div class="activity-item">
                <div class="activity-icon {{ $eventClass }}">
                    <i class="{{ $eventIcon }}"></i>
                </div>
                <div class="activity-content">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="activity-title">
                            {{ $activity->description ?? 'Alteração' }}
                        </div>
                        @if($subjectLink)
                            <a href="{{ $subjectLink }}" class="btn btn-sm btn-light" title="Ver">
                                <i class="ph ph-eye"></i>
                            </a>
                        @endif
                    </div>
                    @if($activity->event === 'updated' && $activity->properties)
                        @php
                            $props = $activity->properties;
                            $attrs = is_object($props) ? $props->get('attributes', []) : ($props['attributes'] ?? []);
                            $old = is_object($props) ? $props->get('old', []) : ($props['old'] ?? []);
                            $attrs = is_array($attrs) ? $attrs : (method_exists($attrs, 'toArray') ? $attrs->toArray() : []);
                            $old = is_array($old) ? $old : (method_exists($old, 'toArray') ? $old->toArray() : []);
                        @endphp
                        @if(!empty($attrs) || !empty($old))
                            <div class="activity-description small text-muted">
                                @foreach(array_keys($attrs + $old) as $attr)
                                    @if(in_array($attr, ['password'], true)) @continue @endif
                                    @php
                                        $newVal = $attrs[$attr] ?? null;
                                        $oldVal = $old[$attr] ?? null;
                                    @endphp
                                    @if($oldVal != $newVal)
                                        <span class="d-block">{{ $attr }}: {{ $formatActivityValue($oldVal) }} → {{ $formatActivityValue($newVal) }}</span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    @endif
                    <div class="activity-time">
                        <i class="ph ph-clock"></i> {{ $activity->created_at->format('d/m/Y H:i') }}
                        @if($activity->causer)
                            por {{ $activity->causer->name }}
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <p class="text-muted text-center py-3">Nenhuma atividade registada.</p>
@endif

