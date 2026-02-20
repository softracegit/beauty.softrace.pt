@extends('partials.layouts.main')
@section('title', $cliente->name . ' | Beauty CRM')
@section('content')

@php
    $avatarNum = ($cliente->id % 9) + 1;
    $avatarSrc = asset("template/img/avatars/avatar-{$avatarNum}.webp");
    $statusClass = match($cliente->status) {
        'active' => 'active',
        'available' => 'pending',
        'unavailable' => 'inactive',
        default => 'inactive',
    };
    $statusLabel = \App\Models\Client::statusLabels()[$cliente->status] ?? $cliente->status;
    $clientNotes = $cliente->getRelationValue('notes') ?: $cliente->notes()->with('user')->get();
@endphp

<!-- User Profile Header -->
<div class="uview-header">
    <img src="{{ $avatarSrc }}" alt="{{ $cliente->name }}" class="uview-avatar">
    <div class="uview-info">
        <h2 class="uview-name">{{ $cliente->name }}</h2>
        <p class="uview-email">{{ $cliente->email }}</p>
        <div class="uview-badges">
            <span class="users-status {{ $statusClass }}"><span class="users-status-dot"></span> {{ $statusLabel }}</span>
        </div>
    </div>
    <div class="uview-header-actions">
        <a href="{{ route('clientes.edit', $cliente) }}" class="btn btn-primary btn-sm">
            <i class="ph ph-pencil-simple me-1"></i> Editar
        </a>
        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteClientModal">
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
                            <div class="uview-detail-value">{{ $cliente->name }}</div>
                        </div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Email</div>
                            <div class="uview-detail-value"><a href="mailto:{{ $cliente->email }}">{{ $cliente->email }}</a></div>
                        </div>
                        @if($cliente->phone)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Telefone</div>
                            <div class="uview-detail-value"><a href="tel:{{ $cliente->phone }}">{{ $cliente->phone }}</a></div>
                        </div>
                        @endif
                        @if($cliente->nif)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">NIF</div>
                            <div class="uview-detail-value">{{ $cliente->nif }}</div>
                        </div>
                        @endif
                        @if($cliente->birth_date)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Data de Nascimento</div>
                            <div class="uview-detail-value">{{ $cliente->birth_date->format('d/m/Y') }}</div>
                        </div>
                        @endif
                        @if($cliente->gender)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Género</div>
                            <div class="uview-detail-value">{{ \App\Models\Client::genders()[$cliente->gender] ?? $cliente->gender }}</div>
                        </div>
                        @endif
                        @if($cliente->nationality)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Nacionalidade</div>
                            <div class="uview-detail-value">{{ $cliente->nationality }}</div>
                        </div>
                        @endif
                        @if($cliente->marital_status)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Estado Civil</div>
                            <div class="uview-detail-value">{{ \App\Models\Client::maritalStatuses()[$cliente->marital_status] ?? $cliente->marital_status }}</div>
                        </div>
                        @endif
                    </div>

                    @if($cliente->address || $cliente->postal_code || $cliente->locality)
                    <div class="uview-detail-group">
                        <div class="uview-detail-title">Morada</div>
                        @if($cliente->address)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Morada</div>
                            <div class="uview-detail-value">
                                {{ $cliente->address }}
                                @if($cliente->door), Porta {{ $cliente->door }}@endif
                                @if($cliente->floor), {{ $cliente->floor }}º@endif
                                @if($cliente->side), {{ $cliente->side }}@endif
                            </div>
                        </div>
                        @endif
                        @if($cliente->postal_code)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Código Postal</div>
                            <div class="uview-detail-value">{{ $cliente->postal_code }}</div>
                        </div>
                        @endif
                        @if($cliente->locality)
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Localidade</div>
                            <div class="uview-detail-value">{{ $cliente->locality }}</div>
                        </div>
                        @endif
                    </div>
                    @endif

                    @if($cliente->notes)
                    <div class="uview-detail-group">
                        <div class="uview-detail-title">Notas Gerais</div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-value">{{ $cliente->notes }}</div>
                        </div>
                    </div>
                    @endif

                    <div class="uview-detail-group">
                        <div class="uview-detail-title">Informação da Conta</div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">ID Cliente</div>
                            <div class="uview-detail-value">{{ $cliente->client_id }}</div>
                        </div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Estado</div>
                            <div class="uview-detail-value">
                                <span class="users-status {{ $statusClass }}"><span class="users-status-dot"></span> {{ $statusLabel }}</span>
                            </div>
                        </div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Data de Registo</div>
                            <div class="uview-detail-value">{{ $cliente->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                        <div class="uview-detail-row">
                            <div class="uview-detail-label">Última Atualização</div>
                            <div class="uview-detail-value">{{ $cliente->updated_at->format('d/m/Y H:i') }}</div>
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
                    @if($clientNotes->count() > 0)
                        <div class="activity-log">
                            @foreach($clientNotes as $note)
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
                    <div class="uview-status-item">
                        <span class="uview-status-label">ID Cliente</span>
                        <span class="uview-status-value">{{ $cliente->client_id }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Registado em</span>
                        <span class="uview-status-value">{{ $cliente->created_at->format('d/m/Y') }}</span>
                    </div>
                    <div class="uview-status-item">
                        <span class="uview-status-label">Total de Notas</span>
                        <span class="uview-status-value">{{ $clientNotes->count() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Client Modal -->
<div class="modal fade" id="deleteClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger"><i class="ph ph-warning me-2"></i>Eliminar Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem a certeza que deseja eliminar <strong>{{ $cliente->name }}</strong>?</p>
                <p class="text-muted mb-0">Esta ação não pode ser desfeita. Todos os dados do cliente serão permanentemente removidos do sistema.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="{{ route('clientes.destroy', $cliente) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Eliminar Cliente</button>
                </form>
            </div>
        </div>
    </div>
</div>

@include('partials.note-form-modal', ['route' => route('clientes.storeNote', $cliente), 'modelName' => 'cliente'])

@endsection
