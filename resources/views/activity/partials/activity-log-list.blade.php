@php
    use App\Support\ActivityLogDisplay;

    $hideSubjectLinks = $hideSubjectLinks ?? false;
    $calendarEventMorphClass = (new \App\Models\CalendarEvent())->getMorphClass();
    $clientMorphClass = (new \App\Models\Client())->getMorphClass();
    $agentMorphClass = (new \App\Models\Agent())->getMorphClass();
    $serviceMorphClass = (new \App\Models\Service())->getMorphClass();
    $extraMorphClass = (new \App\Models\Extra())->getMorphClass();
@endphp

@if(isset($activities) && $activities->count() > 0)
    <div class="activity-log">
        @foreach($activities as $activity)
            @php
                $activityStoreId = (int) ($activity->store_id ?? 0) ?: null;
                $eventIcon = match($activity->event ?? '') {
                    'created' => 'ph ph-plus-circle',
                    'updated' => 'ph ph-pencil-simple',
                    'deleted' => 'ph ph-trash',
                    'pre_pagamento', 'marcacao_paga' => 'ph ph-money',
                    'fatura_gerada' => 'ph ph-receipt',
                    'venda_anulada' => 'ph ph-x-circle',
                    'servicos_alterados' => 'ph ph-scissors',
                    'caixa_aberta' => 'ph ph-lock-open',
                    'caixa_fechada' => 'ph ph-lock-key',
                    'settings_updated' => 'ph ph-gear',
                    default => 'ph ph-info',
                };
                $eventClass = match($activity->event ?? '') {
                    'created' => 'bg-success-light text-success',
                    'updated' => 'bg-primary-light text-primary',
                    'deleted', 'venda_anulada' => 'bg-danger-light text-danger',
                    'pre_pagamento', 'marcacao_paga', 'fatura_gerada', 'servicos_alterados', 'caixa_aberta', 'caixa_fechada' => 'bg-success-light text-success',
                    'settings_updated' => 'bg-warning-light text-warning',
                    default => 'bg-secondary-light text-secondary',
                };

                $subjectLink = null;
                if (! $hideSubjectLinks && ! empty($activity->subject_id)) {
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

                $causerLabel = ActivityLogDisplay::causerLabel($activity);
                $activityTitle = ActivityLogDisplay::activityTitle($activity);
            @endphp
            <div class="activity-item">
                <div class="activity-icon {{ $eventClass }}">
                    <i class="{{ $eventIcon }}"></i>
                </div>
                <div class="activity-content">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="activity-title">
                            {{ $activityTitle }}
                        </div>
                        @if($subjectLink)
                            <a href="{{ $subjectLink }}" class="btn btn-sm btn-light" title="Ver">
                                <i class="ph ph-eye"></i>
                            </a>
                        @endif
                    </div>
                    @php
                        $contextLine = ActivityLogDisplay::contextLineForActivity($activity);
                    @endphp
                    @if($contextLine)
                        <div class="activity-context small text-muted">{{ $contextLine }}</div>
                    @endif
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
                                    @if(!ActivityLogDisplay::shouldShowChangeAttribute($attr)) @continue @endif
                                    @php
                                        $newVal = $attrs[$attr] ?? null;
                                        $oldVal = $old[$attr] ?? null;
                                    @endphp
                                    @if($oldVal != $newVal)
                                        <span class="d-block">{{ ActivityLogDisplay::attributeLabel($attr) }}: {{ ActivityLogDisplay::formatChange($attr, $oldVal, $newVal, $activityStoreId) }}</span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    @elseif($activity->properties)
                        @php
                            $props = $activity->properties;
                            $propsArr = is_object($props) && method_exists($props, 'toArray')
                                ? $props->toArray()
                                : (is_array($props) ? $props : []);
                            $customProps = array_diff_key($propsArr, array_flip(['attributes', 'old', 'contexto']));
                            $alteracoes = $customProps['alteracoes'] ?? null;
                            unset($customProps['alteracoes'], $customProps['secao']);
                        @endphp
                        @if(is_array($alteracoes) && $alteracoes !== [])
                            <div class="activity-description small text-muted">
                                @foreach($alteracoes as $line)
                                    <span class="d-block">{{ $line }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if(!empty($customProps))
                            <div class="activity-description small text-muted">
                                @foreach($customProps as $propKey => $propVal)
                                    @if(!ActivityLogDisplay::shouldShowChangeAttribute($propKey)) @continue @endif
                                    <span class="d-block">{{ ActivityLogDisplay::paymentPropertyLabel($propKey) }}: {{ ActivityLogDisplay::formatValue($propKey, $propVal, $activityStoreId) }}</span>
                                @endforeach
                            </div>
                        @endif
                    @endif
                    <div class="activity-time">
                        <i class="ph ph-clock"></i> {{ ActivityLogDisplay::formatLogTimestamp($activity->created_at, $activityStoreId) }}
                        @if($causerLabel)
                            por {{ $causerLabel }}
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <p class="text-muted text-center py-3">Nenhuma atividade registada.</p>
@endif
