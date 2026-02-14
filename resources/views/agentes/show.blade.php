@extends('partials.layouts.main')
@section('title', $agente->name . ' | Imobiliária')
@section('page-heading-title', 'Agente')
@section('page-heading-sub-title', 'Real Estate')
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
        <a href="{{ route('agentes.edit', $agente) }}" class="btn btn-primary btn-sm">
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
                <button class="uview-tab nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-activity">Atividade</button>
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
                            <div class="uview-detail-value"><a href="tel:{{ $agente->phone }}">{{ $agente->phone }}</a></div>
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

                    @if($agente->notes)
                    <div class="uview-detail-group">
                        <div class="uview-detail-title">Notas Gerais</div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-value">{{ $agente->notes }}</div>
                        </div>
                    </div>
                    @endif

                    <div class="uview-detail-group">
                        <div class="uview-detail-title">Informação da Conta</div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">ID Agente</div>
                            <div class="uview-detail-value">{{ $agente->agent_id }}</div>
                        </div>
                        @if($agente->user)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Tipo de Utilizador</div>
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

                <!-- Activity Tab -->
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
                        <span class="uview-status-label">Tipo de Utilizador</span>
                        <span class="uview-status-value">{{ \App\Models\User::roles()[$agente->user->role] ?? $agente->user->role }}</span>
                    </div>
                    @endif
                    <div class="uview-status-item">
                        <span class="uview-status-label">ID Agente</span>
                        <span class="uview-status-value">{{ $agente->agent_id }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Registado em</span>
                        <span class="uview-status-value">{{ $agente->created_at->format('d/m/Y') }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Total de Notas</span>
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
                <h5 class="modal-title text-danger"><i class="ph ph-warning me-2"></i>Eliminar Agente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem a certeza que deseja eliminar <strong>{{ $agente->name }}</strong>?</p>
                <p class="text-muted mb-0">Esta ação não pode ser desfeita. Todos os dados do agente serão permanentemente removidos do sistema.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="{{ route('agentes.destroy', $agente) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Eliminar Agente</button>
                </form>
            </div>
        </div>
    </div>
</div>

@include('partials.note-form-modal', ['route' => route('agentes.storeNote', $agente), 'modelName' => 'agente'])

@endsection
