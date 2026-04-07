@extends('partials.layouts.main')
@section('title', $agente->name . ' | Beauty CRM')
@section('content')

@php
    $avatarNum = ($agente->id % 9) + 1;
    $avatarSrc = $agente->avatar 
        ? asset('storage/' . $agente->avatar)
        : asset("template/img/avatars/avatar-{$avatarNum}.webp");
    $statusClass = match($agente->status) {
        'active' => 'active',
        'inactive' => 'inactive',
        'on_leave' => 'pending',
        default => 'inactive',
    };
    $statusLabel = \App\Models\Agent::statusLabels()[$agente->status] ?? $agente->status;
    $agentNotes = $agente->getRelationValue('notes') ?: $agente->notes()->with('user')->get();
@endphp

<!-- User Profile Header -->
<div class="uview-header">
    <img src="{{ $avatarSrc }}" alt="{{ $agente->name }}" class="uview-avatar">
    <div class="uview-info">
        <h2 class="uview-name">{{ $agente->name }}</h2>
        <p class="uview-email">{{ $agente->user->email ?? '—' }}</p>
        <div class="uview-badges">
            @if($agente->user)
                <span class="users-role" style="background: color-mix(in srgb, var(--accent-color), transparent 88%); color: var(--accent-color);">
                    <i class="ph-fill ph-shield-chevron"></i> {{ \App\Models\User::roles()[$agente->user->role] ?? $agente->user->role }}
                </span>
            @endif
            <span class="users-status {{ $statusClass }}"><span class="users-status-dot"></span> {{ $statusLabel }}</span>
        </div>
    </div>
    <div class="uview-header-actions">
        <a href="{{ route('equipa.edit', $agente) }}" class="btn btn-primary btn-sm">
            <i class="ph ph-pencil-simple me-1"></i> Editar
        </a>
        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteAgentModal">
            <i class="ph ph-trash me-1"></i> Eliminar
        </button>
    </div>
</div>

<!-- Content Grid -->
<div class="uview-grid">
    <!-- Main Content -->
    <div>
        <div class="card">
            <div class="uview-tabs" role="tablist">
                <button class="uview-tab nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#tab-details">Detalhes</button>
                <button class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-marcacoes">Marcações</button>
                <button class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-vendas">Vendas</button>
                <button class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-log">Atividade</button>
                <button class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-activity">Notas</button>
            </div>
            <div class="card-body tab-content">

                <!-- Details Tab -->
                <div class="tab-pane fade show active" id="tab-details">
                    <div class="uview-detail-group">
                        <div class="uview-detail-title">Informação Pessoal</div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Nome Completo</div>
                            <div class="uview-detail-value">{{ $agente->name }}</div>
                        </div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Email</div>
                            <div class="uview-detail-value">
                                @if($agente->user && $agente->user->email)
                                    <a href="mailto:{{ $agente->user->email }}">{{ $agente->user->email }}</a>
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                        @if($agente->phone)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Telefone</div>
                            <div class="uview-detail-value"><a href="{{ \App\Support\PhoneDisplay::telHref($agente->phone) }}">{{ $agente->formatted_phone }}</a></div>
                        </div>
                        @endif
                        @if($agente->nif)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">NIF</div>
                            <div class="uview-detail-value">{{ $agente->nif }}</div>
                        </div>
                        @endif
                        @if($agente->birth_date)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Data de Nascimento</div>
                            <div class="uview-detail-value">{{ $agente->birth_date->format('d/m/Y') }}</div>
                        </div>
                        @endif
                        @if($agente->gender)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Género</div>
                            <div class="uview-detail-value">{{ \App\Models\Agent::genders()[$agente->gender] ?? $agente->gender }}</div>
                        </div>
                        @endif
                        @if($agente->nationality)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Nacionalidade</div>
                            <div class="uview-detail-value">{{ $agente->nationality }}</div>
                        </div>
                        @endif
                        @if($agente->marital_status)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Estado Civil</div>
                            <div class="uview-detail-value">{{ \App\Models\Agent::maritalStatuses()[$agente->marital_status] ?? $agente->marital_status }}</div>
                        </div>
                        @endif
                    </div>

                    @if($agente->address || $agente->postal_code || $agente->locality)
                    <div class="uview-detail-group">
                        <div class="uview-detail-title">Morada</div>
                        @if($agente->address)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Morada</div>
                            <div class="uview-detail-value">
                                {{ $agente->address }}
                                @if($agente->door), Porta {{ $agente->door }}@endif
                                @if($agente->floor), {{ $agente->floor }}º@endif
                                @if($agente->side), {{ $agente->side }}@endif
                            </div>
                        </div>
                        @endif
                        @if($agente->postal_code)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Código Postal</div>
                            <div class="uview-detail-value">{{ $agente->postal_code }}</div>
                        </div>
                        @endif
                        @if($agente->locality)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Localidade</div>
                            <div class="uview-detail-value">{{ $agente->locality }}</div>
                        </div>
                        @endif
                    </div>
                    @endif

                    @if($agente->specialization || $agente->commission_rate)
                    <div class="uview-detail-group">
                        <div class="uview-detail-title">Dados Profissionais</div>
                        @if($agente->specialization)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Especialização</div>
                            <div class="uview-detail-value">{{ $agente->specialization }}</div>
                        </div>
                        @endif
                        @if($agente->commission_rate)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Taxa de Comissão</div>
                            <div class="uview-detail-value">{{ number_format($agente->commission_rate, 2) }}%</div>
                        </div>
                        @endif
                    </div>
                    @endif

                    <div class="uview-detail-group">
                        <div class="uview-detail-title">Informação da Conta</div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">ID Membro</div>
                            <div class="uview-detail-value">{{ $agente->agent_id }}</div>
                        </div>
                        @if($agente->user)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Tipo de Membro</div>
                            <div class="uview-detail-value">{{ \App\Models\User::roles()[$agente->user->role] ?? $agente->user->role }}</div>
                        </div>
                        @endif
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Estado</div>
                            <div class="uview-detail-value">
                                <span class="users-status {{ $statusClass }}"><span class="users-status-dot"></span> {{ $statusLabel }}</span>
                            </div>
                        </div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Data de Registo</div>
                            <div class="uview-detail-value">{{ $agente->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Última Atualização</div>
                            <div class="uview-detail-value">{{ $agente->updated_at->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>
                </div>

                <!-- Marcações Tab -->
                <div class="tab-pane fade" id="tab-marcacoes">
                    @if($marcacoes->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Data/Hora</th>
                                        <th>Serviços</th>
                                        <th>Cliente</th>
                                        <th>Estado</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($marcacoes as $ev)
                                        @php
                                            $isFutura = $ev->start_at->isFuture();
                                            $badgeClass = $isFutura ? 'bg-success-light text-success' : 'bg-secondary-light text-secondary';
                                        @endphp
                                        <tr>
                                            <td>{{ $ev->start_at->format('d/m/Y H:i') }}</td>
                                            <td>
                                                @foreach($ev->eventServiceItems as $es)
                                                    <span class="badge {{ $isFutura ? 'bg-primary-light text-primary' : 'bg-secondary-light text-secondary' }} me-1">{{ $es->service?->name ?? '—' }}</span>
                                                @endforeach
                                            </td>
                                            <td>
                                                @if($ev->client)
                                                    <a href="{{ route('clientes.show', $ev->client) }}">{{ $ev->client->name }}</a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td><span class="badge {{ $badgeClass }}">{{ \App\Models\CalendarEvent::statuses()[$ev->status] ?? $ev->status }}</span></td>
                                            <td>
                                                <a href="{{ route('agenda.index') }}?event={{ $ev->id }}" class="btn btn-sm btn-light" title="Ver na agenda"><i class="ph ph-calendar"></i></a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted text-center py-3">Nenhuma marcação registada.</p>
                    @endif
                </div>

                <!-- Vendas Tab -->
                <div class="tab-pane fade" id="tab-vendas">
                    @if($vendas->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Cliente</th>
                                        <th>Serviço</th>
                                        <th class="text-center">Qtd</th>
                                        <th class="text-end">Preço</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $totalVendas = 0; @endphp
                                    @foreach($vendas as $linha)
                                        @php $totalVendas += $linha->preco * $linha->quantidade; @endphp
                                        <tr>
                                            <td>{{ $linha->data->format('d/m/Y H:i') }}</td>
                                            <td>{{ $linha->cliente }}</td>
                                            <td>
                                                {{ $linha->servico }}
                                                @if($linha->tipo === 'extra')
                                                    <span class="badge bg-info-light text-info ms-1">Extra</span>
                                                @endif
                                            </td>
                                            <td class="text-center">{{ $linha->quantidade }}</td>
                                            <td class="text-end">{{ number_format($linha->preco, 2, ',', ' ') }} €</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-light fw-semibold">
                                        <td colspan="4" class="text-end">Total</td>
                                        <td class="text-end">{{ number_format($totalVendas, 2, ',', ' ') }} €</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <p class="text-muted text-center py-3">Nenhuma venda registada.</p>
                    @endif
                </div>

                <!-- Atividade (logs) Tab -->
                <div class="tab-pane fade" id="tab-log">
                    <h6 class="mb-3">Histórico de alterações</h6>
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
                                @endphp
                                <div class="activity-item">
                                    <div class="activity-icon {{ $eventClass }}">
                                        <i class="{{ $eventIcon }}"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">{{ $activity->description ?? 'Alteração' }}</div>
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
                                                            $formatActivityValue = static function ($value) {
                                                                if (is_bool($value)) {
                                                                    return $value ? 'Sim' : 'Não';
                                                                }
                                                                if (is_array($value) || is_object($value)) {
                                                                    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                                                                    return $json !== false ? $json : '[valor complexo]';
                                                                }

                                                                $str = (string) $value;

                                                                return strlen($str) > 50 ? substr($str, 0, 50).'…' : $str;
                                                            };
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
                </div>

                <!-- Notas Tab -->
                <div class="tab-pane fade" id="tab-activity">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Notas</h6>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                            <i class="ph ph-plus me-1"></i> Adicionar Nota
                        </button>
                    </div>
                    @if($agentNotes->count() > 0)
                        <div class="activity-log">
                            @foreach($agentNotes as $note)
                                @php
                                    $color = \App\Models\Note::getColorForType($note->type);
                                    $typeLabel = \App\Models\Note::types()[$note->type] ?? $note->type;
                                    $iconClass = match($note->type) {
                                        \App\Models\Note::TYPE_EMAIL => 'ph-duotone ph-envelope',
                                        \App\Models\Note::TYPE_CHAMADA => 'ph-duotone ph-phone',
                                        \App\Models\Note::TYPE_REUNIAO => 'ph-duotone ph-calendar',
                                        default => 'ph-duotone ph-note',
                                    };
                                    $bgClass = match($color) {
                                        'text-success' => 'bg-success-light text-success',
                                        'text-info' => 'bg-info-light text-info',
                                        'text-warning' => 'bg-warning-light text-warning',
                                        'text-danger' => 'bg-danger-light text-danger',
                                        'text-primary' => 'bg-primary-light text-primary',
                                        default => 'bg-primary-light text-primary',
                                    };
                                @endphp
                                <div class="activity-item">
                                    <div class="activity-icon {{ $bgClass }}">
                                        <i class="{{ $iconClass }}"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">{{ $typeLabel }}</div>
                                        <div class="activity-description">{{ $note->note }}</div>
                                        <div class="activity-time">
                                            <i class="ph ph-clock"></i> {{ $note->created_at->format('d/m/Y H:i') }}
                                            @if($note->user)
                                                por {{ $note->user->name }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted text-center py-3">Nenhuma nota adicionada ainda.</p>
                    @endif
                </div>

            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- Account Status -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Estado da Conta</h5>
            </div>
            <div class="card-body">
                <div class="uview-status-list">
                    <div class="uview-status-item">
                        <span class="uview-status-label">Estado</span>
                        <span class="users-status {{ $statusClass }}"><span class="users-status-dot"></span> {{ $statusLabel }}</span>
                    </div>
                    @if($agente->user)
                    <div class="uview-status-item">
                        <span class="uview-status-label">Tipo de Membro</span>
                        <span class="uview-status-value">{{ \App\Models\User::roles()[$agente->user->role] ?? $agente->user->role }}</span>
                    </div>
                    @endif
                    <div class="uview-status-item">
                        <span class="uview-status-label">ID Membro</span>
                        <span class="uview-status-value">{{ $agente->agent_id }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Registado em</span>
                        <span class="uview-status-value">{{ $agente->created_at->format('d/m/Y') }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Marcações</span>
                        <span class="uview-status-value">{{ $marcacoes->count() }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Total Vendas</span>
                        <span class="uview-status-value">{{ number_format($vendas->sum(fn($l) => $l->preco * $l->quantidade), 2, ',', ' ') }} €</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Notas</span>
                        <span class="uview-status-value">{{ $agentNotes->count() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Agent Modal -->
<div class="modal fade" id="deleteAgentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger"><i class="ph ph-warning me-2"></i>Eliminar Membro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem a certeza que deseja eliminar <strong>{{ $agente->name }}</strong>?</p>
                <p class="text-muted mb-0">Esta ação não pode ser desfeita. Todos os dados do membro serão permanentemente removidos do sistema.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="{{ route('equipa.destroy', $agente) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Eliminar Membro</button>
                </form>
            </div>
        </div>
    </div>
</div>

@include('partials.note-form-modal', ['route' => route('equipa.storeNote', $agente), 'modelName' => 'agente'])

@endsection

@section('js')
<script>
(function() {
    const hash = window.location.hash;
    const validTabs = ['tab-details', 'tab-marcacoes', 'tab-vendas', 'tab-log', 'tab-activity'];
    const tabId = hash && validTabs.includes(hash.slice(1)) ? hash.slice(1) : null;

    document.addEventListener('DOMContentLoaded', function() {
        const tabList = document.querySelector('.uview-tabs');
        if (!tabList) return;

        if (tabId) {
            const trigger = document.querySelector('[data-bs-target="#' + tabId + '"]');
            if (trigger && typeof bootstrap !== 'undefined') {
                bootstrap.Tab.getOrCreateInstance(trigger).show();
            }
        }

        tabList.addEventListener('shown.bs.tab', function(e) {
            const target = e.target.getAttribute('data-bs-target');
            if (target && target.startsWith('#')) {
                history.replaceState(null, '', window.location.pathname + target);
            }
        });
    });
})();
</script>
@endsection
