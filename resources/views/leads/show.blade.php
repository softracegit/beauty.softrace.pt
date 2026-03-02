@extends('partials.layouts.main')
@section('title', 'Lead ' . $lead->name . ' | Beauty CRM')
@section('content')

@php
    $currentStatusIndex = $lead->getStatusIndex();
    $totalStatuses = count($statuses);
    $progressPercentage = (($currentStatusIndex + 1) / $totalStatuses) * 100;
    $statusColor = $lead->status_color;
    $priorityColor = $lead->priority_color;
    $avatarNum = ($lead->agent ? $lead->agent->id : $lead->id) % 9 + 1;
    $avatarSrc = asset("template/img/avatars/avatar-{$avatarNum}.webp");
@endphp

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<style>
.deal-stages-pipeline {
    padding: 0 0;
    overflow: visible;
}

.pipeline-container {
    display: flex;
    align-items: stretch;
    width: 100%;
    gap: 0;
    position: relative;
}

.deal-stage {
    flex: 1;
    position: relative;
    background-color: #e9ecef;
    color: #6c757d;
    padding: 5px 0;
    text-align: center;
    font-weight: 500;
    font-size: 12px;
    transition: all 0.3s ease;
    min-height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 3px;
}

.deal-stage.last {
    margin-right: 0;
}

.deal-stage:not(.last)::after {
    content: '';
    position: absolute;
    right: -5px;
    top: 0;
    width: 0;
    height: 0;
    border-top: 15px solid transparent;
    border-bottom: 15px solid transparent;
    border-left: 6px solid #e9ecef;
    z-index: 5;
    transition: border-left-color 0.3s ease;
}

.deal-stage:not(:first-child)::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 0;
    height: 0;
    border-top: 15px solid transparent;
    border-bottom: 15px solid transparent;
    border-left: 6px solid #fff;
    z-index: 3;
}

.deal-stage.completed {
    background-color: #198754;
    color: #fff;
}

.deal-stage.completed:not(.last)::after {
    border-left-color: #198754;
}

.deal-stage.completed:not(:first-child)::before {
    border-left-color: #fff;
}

.deal-stage.active {
    background-color: #198754;
    color: #fff;
    font-weight: 600;
}

.deal-stage.active:not(.last)::after {
    border-left-color: #198754;
}

.deal-stage.active:not(:first-child)::before {
    border-left-color: #fff;
}

.deal-stage.last {
    border-top-right-radius: 6px;
    border-bottom-right-radius: 6px;
}

.deal-stage:first-child {
    border-top-left-radius: 6px;
    border-bottom-left-radius: 6px;
}

.stage-label {
    position: relative;
    z-index: 3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

@media (max-width: 768px) {
    .deal-stage {
        font-size: 12px;
        padding: 10px 15px;
    }
    
    .deal-stage:not(.last)::after {
        right: -15px;
        border-top-width: 20px;
        border-bottom-width: 20px;
        border-left-width: 15px;
    }
    
    .deal-stage:not(:first-child)::before {
        border-top-width: 20px;
        border-bottom-width: 20px;
        border-left-width: 15px;
    }
}
</style>

<div class="row">
    <!-- Dados da Lead -->
    <div class="col-12 col-lg-7">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Dados da Lead</h5>
                <div class="d-flex gap-2">
                    <a href="{{ route('leads.edit', $lead) }}" class="btn btn-primary btn-sm"><i class="ph ph-pencil-simple me-1"></i> Editar</a>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="leadOptionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="ph ph-dots-three me-1"></i> Opções
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="leadOptionsDropdown">
                            @if($lead->status !== \App\Models\Lead::STATUS_GANHO)
                            <li>
                                <a class="dropdown-item" href="#" onclick="convertToOpportunity({{ $lead->id }}); return false;">
                                    <i class="ph ph-arrows-left-right me-2"></i> Converter em Oportunidade
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            @endif
                            @if($lead->status !== \App\Models\Lead::STATUS_GANHO)
                            <li>
                                <a class="dropdown-item text-success" href="#" onclick="updateLeadStatus({{ $lead->id }}, '{{ \App\Models\Lead::STATUS_GANHO }}'); return false;">
                                    <i class="ph-duotone ph-check-circle me-2"></i> Marcar como Ganho
                                </a>
                            </li>
                            @endif
                            @if($lead->status !== \App\Models\Lead::STATUS_PERDIDO)
                            <li>
                                <a class="dropdown-item text-danger" href="#" onclick="updateLeadStatus({{ $lead->id }}, '{{ \App\Models\Lead::STATUS_PERDIDO }}'); return false;">
                                    <i class="ph-duotone ph-x-circle me-2"></i> Marcar como Perdida
                                </a>
                            </li>
                            @endif
                            @if(!$lead->isArchived())
                            <li>
                                <a class="dropdown-item text-muted" href="#" onclick="archiveLead({{ $lead->id }}); return false;">
                                    <i class="ph-duotone ph-archive me-2"></i> Arquivar
                                </a>
                            </li>
                            @else
                            <li>
                                <a class="dropdown-item text-success" href="#" onclick="unarchiveLead({{ $lead->id }}); return false;">
                                    <i class="ph-duotone ph-archive-box me-2"></i> Desarquivar
                                </a>
                            </li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('leads.destroy', $lead) }}" method="POST" class="d-inline" onsubmit="return confirm('Tem a certeza que deseja remover esta lead?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="ph ph-trash me-2"></i> Eliminar
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        @if($lead->status === \App\Models\Lead::STATUS_PERDIDO)
                            <div class="alert alert-danger d-flex align-items-center justify-content-between" role="alert">
                                <div>
                                    <i class="ph-duotone ph-warning me-2"></i>
                                    <strong>Lead Perdida</strong> - Esta lead está marcada como perdida.
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-light" onclick="restoreLead({{ $lead->id }}); return false;">
                                    <i class="ph ph-arrow-clockwise me-1"></i> Recuperar Lead
                                </button>
                            </div>
                        @elseif($lead->status === \App\Models\Lead::STATUS_GANHO && $lead->opportunity)
                            <div class="alert alert-success d-flex align-items-center justify-content-between" role="alert">
                                <div>
                                    <i class="ph-duotone ph-check-circle me-2"></i>
                                    <strong>Lead Ganha</strong> - Esta lead foi convertida em oportunidade.
                                    <a href="{{ route('opportunities.show', $lead->opportunity) }}" class="alert-link ms-2">
                                        <i class="ph ph-arrow-square-out me-1"></i> Ver Oportunidade {{ $lead->opportunity->reference }}
                                    </a>
                                </div>
                            </div>
                        @else
                            <div class="deal-stages-pipeline">
                                <div class="pipeline-container">
                                    @foreach($statuses as $index => $status)
                                    @php
                                    $isCompleted = $index < $currentStatusIndex;
                                    $isCurrent = $index == $currentStatusIndex;
                                    $statusLabel = $statusLabels[$status] ?? $status;
                                    $isLast = $index === $totalStatuses - 1;
                                    @endphp
                                    <div class="deal-stage {{ $isCompleted ? 'completed' : '' }} {{ $isCurrent ? 'active' : '' }} {{ $isLast ? 'last' : '' }}">
                                        <span class="stage-label">{{ $statusLabel }}</span>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">ID</label>
                        <p class="mb-0 fw-medium">{{ $lead->lead_id }}</p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">Nome</label>
                        <p class="mb-0 fw-medium">{{ $lead->name }}</p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">Tipo</label>
                        <p class="mb-0">
                            <span class="badge bg-info-subtle text-info">{{ \App\Models\Lead::types()[$lead->type] ?? $lead->type }}</span>
                        </p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">Estado</label>
                        <p class="mb-0">
                            <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }}">{{ \App\Models\Lead::statuses()[$lead->status] ?? $lead->status }}</span>
                        </p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">Prioridade</label>
                        <p class="mb-0">
                            <span class="badge bg-{{ $priorityColor }}-subtle text-{{ $priorityColor }}">{{ \App\Models\Lead::priorities()[$lead->priority] ?? $lead->priority }}</span>
                        </p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">Origem</label>
                        <p class="mb-0">{{ \App\Models\Lead::origins()[$lead->origin] ?? $lead->origin }}</p>
                    </div>
                    @if($lead->email)
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">Email</label>
                        <p class="mb-0"><a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a></p>
                    </div>
                    @endif
                    @if($lead->phone)
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">Telefone</label>
                        <p class="mb-0"><a href="tel:{{ $lead->phone }}">{{ $lead->phone }}</a></p>
                    </div>
                    @endif
                    @if($lead->property_reference)
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">Referência do Imóvel</label>
                        <p class="mb-0 fw-medium">{{ $lead->property_reference }}</p>
                    </div>
                    @endif
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">Responsável</label>
                        <p class="mb-0">
                            @if($lead->agent)
                                <div class="d-flex align-items-center">
                                    <img src="{{ $avatarSrc }}" alt="{{ $lead->agent->name }}" class="avatar-sm rounded-circle me-2">
                                    <span>{{ $lead->agent->name }}</span>
                                </div>
                            @else
                                <span class="text-muted">— Sem responsável —</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small text-uppercase">Última Atualização</label>
                        <p class="mb-0">
                            <small class="text-muted">{{ $lead->status_changed_at ? $lead->status_changed_at->format('d/m/Y H:i') : $lead->updated_at->format('d/m/Y H:i') }}</small>
                        </p>
                    </div>
                    @if($lead->notes)
                    <div class="col-12">
                        <label class="form-label text-muted small text-uppercase">Notas Gerais</label>
                        <p class="mb-0 text-muted">{{ $lead->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Preferências de Imóvel -->
        @if($lead->propertyPreferences->count() > 0)
        @php
            $preference = $lead->propertyPreferences->first();
        @endphp
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Preferências de Imóvel</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-3">
                    @if($preference->propertyType)
                    <div class="d-flex align-items-start gap-3">
                        <div>
                            <p class="mb-0 text-muted small">Tipo de Imóvel</p>
                            <p class="mb-0 text-body">{{ $preference->propertyType->name }}</p>
                        </div>
                    </div>
                    @endif

                    @if($preference->transactionType)
                    <div class="d-flex align-items-start gap-3">
                        <div>
                            <p class="mb-0 text-muted small">Tipo de Transação</p>
                            <p class="mb-0 text-body">{{ $preference->transactionType->name }}</p>
                        </div>
                    </div>
                    @endif

                    @if($preference->propertyCondition)
                    <div class="d-flex align-items-start gap-3">
                        <div>
                            <p class="mb-0 text-muted small">Condição</p>
                            <p class="mb-0 text-body">{{ $preference->propertyCondition->name }}</p>
                        </div>
                    </div>
                    @endif

                    @if($preference->min_price || $preference->max_price)
                    <div class="d-flex align-items-start gap-3">
                        <div>
                            <p class="mb-0 text-muted small">Preço</p>
                            <p class="mb-0 text-body">
                                @if($preference->min_price && $preference->max_price)
                                    {{ number_format($preference->min_price, 0, ',', '.') }}€ - {{ number_format($preference->max_price, 0, ',', '.') }}€
                                @elseif($preference->min_price)
                                    A partir de {{ number_format($preference->min_price, 0, ',', '.') }}€
                                @elseif($preference->max_price)
                                    Até {{ number_format($preference->max_price, 0, ',', '.') }}€
                                @endif
                            </p>
                        </div>
                    </div>
                    @endif

                    @if($preference->typologies->isNotEmpty())
                    <div class="d-flex align-items-start gap-3">
                        <div>
                            <p class="mb-0 text-muted small">Tipologias</p>
                            <p class="mb-0 text-body">
                                {{ $preference->typologies->pluck('name')->join(', ') }}
                            </p>
                        </div>
                    </div>
                    @endif

                    @if($preference->preferenceLocations->isNotEmpty())
                    <div class="d-flex align-items-start gap-3">
                        <div>
                            <p class="mb-0 text-muted small">Localização</p>
                            <div class="mb-0 text-body">
                                @foreach($preference->preferenceLocations as $location)
                                    @php
                                        $locationParts = [];
                                        if ($location->id_district) {
                                            $district = \App\Models\Local::where('id_district', $location->id_district)->first();
                                            if ($district) $locationParts[] = $district->district;
                                        }
                                        if ($location->id_city) {
                                            $city = \App\Models\Local::where('id_city', $location->id_city)->first();
                                            if ($city) $locationParts[] = $city->city;
                                        }
                                        if ($location->id_parish) {
                                            $parish = \App\Models\Local::where('id_parish', $location->id_parish)->first();
                                            if ($parish) $locationParts[] = $parish->parish;
                                        }
                                    @endphp
                                    <div>{{ implode(', ', array_filter($locationParts)) ?: '—' }}</div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($preference->features->isNotEmpty())
                    <div class="d-flex align-items-start gap-3">
                        <div>
                            <p class="mb-0 text-muted small">Características</p>
                            <p class="mb-0 text-body">
                                {{ $preference->features->pluck('name')->join(', ') }}
                            </p>
                        </div>
                    </div>
                    @endif

                    @if($preference->notes)
                    <div class="d-flex align-items-start gap-3">
                        <div>
                            <p class="mb-0 text-muted small">Notas sobre a Preferência</p>
                            <p class="mb-0 text-body">{{ $preference->notes }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Notas em Timeline -->
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Notas</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                    <i class="ph ph-plus me-1"></i> Adicionar Nota
                </button>
            </div>
            <div class="card-body">
                @php
                    // Garantir que usamos o relacionamento, não o atributo 'notes'
                    $leadNotes = $lead->getRelationValue('notes') ?: $lead->notes()->with('user')->get();
                @endphp
                @include('partials.notes-timeline', ['notes' => $leadNotes])
            </div>
        </div>
    </div>
</div>

@include('partials.note-form-modal', ['route' => route('leads.storeNote', $lead), 'modelName' => 'lead'])

<!-- Modal de Conversão em Oportunidade -->
<div class="modal fade" id="convertToOpportunityModal" tabindex="-1" aria-labelledby="convertToOpportunityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-height: 90vh;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="convertToOpportunityModalLabel">
                    <i class="ph ph-arrows-left-right me-2"></i> Converter Lead em Oportunidade
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="convertToOpportunityForm">
                <div class="modal-body" style="max-height: calc(90vh - 200px); overflow-y: auto;">
                    <!-- Dados do Cliente -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Dados do Cliente</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label for="client_name" class="form-label">Nome <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="client_name" name="client_name" 
                                           value="{{ $lead->name }}" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="client_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="client_email" name="client_email" 
                                           value="{{ $lead->email }}">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="client_phone" class="form-label">Telefone</label>
                                    <input type="text" class="form-control" id="client_phone" name="client_phone" 
                                           value="{{ $lead->phone }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dados da Oportunidade -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Dados da Oportunidade</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="priority" class="form-label">Prioridade <span class="text-danger">*</span></label>
                                    <select class="form-select" id="priority" name="priority" required>
                                        @foreach(\App\Models\Opportunity::priorities() as $value => $label)
                                            <option value="{{ $value }}" {{ $lead->priority === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="agent_id" class="form-label">Agente Responsável</label>
                                    <select class="form-select" id="agent_id" name="agent_id">
                                        <option value="">Selecione...</option>
                                        @foreach($agents as $agent)
                                            <option value="{{ $agent->id }}" {{ $lead->agent_id == $agent->id ? 'selected' : '' }}>
                                                {{ $agent->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="notes" class="form-label">Notas</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3">{{ $lead->notes }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Preferências de Imóvel -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Preferências de Imóvel</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <!-- Tipo de Imóvel -->
                                <div class="col-12 col-md-6">
                                    <label for="modal_preference_property_type_id" class="form-label">Tipo de Imóvel</label>
                                    <select name="preference_property_type_id" id="modal_preference_property_type_id" class="form-select">
                                        <option value="">Selecione...</option>
                                        @foreach($propertyTypes as $type)
                                            <option value="{{ $type->id }}" {{ $preference && $preference->property_type_id == $type->id ? 'selected' : '' }}>
                                                {{ $type->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Tipo de Transação -->
                                <div class="col-12 col-md-6">
                                    <label for="modal_preference_transaction_type_id" class="form-label">Tipo de Transação</label>
                                    <select name="preference_transaction_type_id" id="modal_preference_transaction_type_id" class="form-select">
                                        <option value="">Selecione...</option>
                                        @foreach($transactionTypes as $type)
                                            <option value="{{ $type->id }}" {{ $preference && $preference->transaction_type_id == $type->id ? 'selected' : '' }}>
                                                {{ $type->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Condição do Imóvel -->
                                <div class="col-12 col-md-6">
                                    <label for="modal_preference_property_condition_id" class="form-label">Condição</label>
                                    <select name="preference_property_condition_id" id="modal_preference_property_condition_id" class="form-select">
                                        <option value="">Selecione...</option>
                                        @foreach($conditions as $condition)
                                            <option value="{{ $condition->id }}" {{ $preference && $preference->property_condition_id == $condition->id ? 'selected' : '' }}>
                                                {{ $condition->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Preço Mínimo -->
                                <div class="col-12 col-md-3">
                                    <label for="modal_preference_min_price" class="form-label">Preço Mínimo (€)</label>
                                    <input type="number" name="preference_min_price" id="modal_preference_min_price" class="form-control" 
                                           value="{{ $preference ? $preference->min_price : '' }}" min="0" step="0.01">
                                </div>

                                <!-- Preço Máximo -->
                                <div class="col-12 col-md-3">
                                    <label for="modal_preference_max_price" class="form-label">Preço Máximo (€)</label>
                                    <input type="number" name="preference_max_price" id="modal_preference_max_price" class="form-control" 
                                           value="{{ $preference ? $preference->max_price : '' }}" min="0" step="0.01">
                                </div>

                                <!-- Tipologias -->
                                <div class="col-12">
                                    <label class="form-label">Tipologias</label>
                                    <div class="row g-2">
                                        @foreach($typologies as $typology)
                                            <div class="col-auto">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="preference_typologies[]" 
                                                           value="{{ $typology->id }}" id="modal_pref_typology_{{ $typology->id }}"
                                                           {{ $preference && $preference->typologies->contains($typology->id) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="modal_pref_typology_{{ $typology->id }}">
                                                        {{ $typology->name }}
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Localização -->
                                <div class="col-12">
                                    <label class="form-label">Localização</label>
                                    <div id="modal-preference-locations-container" class="mb-2">
                                        @if($preference && $preference->preferenceLocations->count() > 0)
                                            @foreach($preference->preferenceLocations as $index => $location)
                                                <div class="row g-2 mb-2 location-row">
                                                    <div class="col-md-4">
                                                        <select name="preference_locations[{{ $index }}][id_district]" class="form-select district-select">
                                                            <option value="">Distrito...</option>
                                                            @foreach($districts as $district)
                                                                <option value="{{ $district['id'] }}" {{ $location->id_district == $district['id'] ? 'selected' : '' }}>
                                                                    {{ $district['name'] }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <select name="preference_locations[{{ $index }}][id_city]" class="form-select city-select" data-row="{{ $index }}" data-selected-city="{{ $location->id_city }}">
                                                            <option value="">Concelho...</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <select name="preference_locations[{{ $index }}][id_parish]" class="form-select parish-select" data-row="{{ $index }}" data-selected-parish="{{ $location->id_parish }}">
                                                            <option value="">Freguesia...</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-1">
                                                        <button type="button" class="btn btn-sm btn-danger remove-location-row">
                                                            <i class="ph ph-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="modal-add-preference-location-row">
                                        <i class="ph ph-plus me-1"></i> Adicionar Localização
                                    </button>
                                </div>

                                <!-- Características -->
                                <div class="col-12">
                                    <label class="form-label">Características</label>
                                    <div class="row g-2">
                                        @foreach($features as $feature)
                                            <div class="col-auto">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="preference_features[]" 
                                                           value="{{ $feature->id }}" id="modal_pref_feature_{{ $feature->id }}"
                                                           {{ $preference && $preference->features->contains($feature->id) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="modal_pref_feature_{{ $feature->id }}">
                                                        {{ $feature->name }}
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Notas da Preferência -->
                                <div class="col-12">
                                    <label for="modal_preference_notes" class="form-label">Notas sobre a Preferência</label>
                                    <textarea name="preference_notes" id="modal_preference_notes" class="form-control" rows="3">{{ $preference ? $preference->notes : '' }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-check me-1"></i> Converter em Oportunidade
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
@section('js')
<script>

    function updateLeadStatus(leadId, status) {
        if (!confirm('Tem a certeza que deseja alterar o estado desta lead?')) {
            return;
        }

        fetch(`/leads/${leadId}/update-status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                status: status
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showToast('Erro ao atualizar estado: ' + (data.message || 'Erro desconhecido'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Erro ao atualizar estado', 'error');
        });
    }

    function convertToOpportunity(leadId) {
        const modal = new bootstrap.Modal(document.getElementById('convertToOpportunityModal'));
        modal.show();
    }

    // Carregar cidades e freguesias dinamicamente
    document.addEventListener('DOMContentLoaded', function() {
        let locationRowIndex = {{ $preference && $preference->preferenceLocations ? $preference->preferenceLocations->count() : 0 }};

        // Adicionar linha de localização
        document.getElementById('modal-add-preference-location-row')?.addEventListener('click', function() {
            const container = document.getElementById('modal-preference-locations-container');
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 location-row';
            row.innerHTML = `
                <div class="col-md-4">
                    <select name="preference_locations[${locationRowIndex}][id_district]" class="form-select district-select">
                        <option value="">Distrito...</option>
                        @foreach($districts as $district)
                            <option value="{{ $district['id'] }}">{{ $district['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="preference_locations[${locationRowIndex}][id_city]" class="form-select city-select" data-row="${locationRowIndex}">
                        <option value="">Concelho...</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="preference_locations[${locationRowIndex}][id_parish]" class="form-select parish-select" data-row="${locationRowIndex}">
                        <option value="">Freguesia...</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-danger remove-location-row">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(row);
            locationRowIndex++;
        });

        // Remover linha de localização
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-location-row')) {
                e.target.closest('.location-row').remove();
            }
        });

        // Carregar cidades quando selecionar distrito
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('district-select')) {
                const districtId = e.target.value;
                const row = e.target.closest('.location-row');
                const citySelect = row.querySelector('.city-select');
                const parishSelect = row.querySelector('.parish-select');
                
                citySelect.innerHTML = '<option value="">Concelho...</option>';
                parishSelect.innerHTML = '<option value="">Freguesia...</option>';

                if (districtId) {
                    fetch(`/properties/get-cities?district_id=${districtId}`)
                        .then(response => response.json())
                        .then(cities => {
                            cities.forEach(city => {
                                const option = document.createElement('option');
                                option.value = city.id;
                                option.textContent = city.name;
                                citySelect.appendChild(option);
                            });
                        });
                }
            }

            // Carregar freguesias quando selecionar concelho
            if (e.target.classList.contains('city-select')) {
                const cityId = e.target.value;
                const row = e.target.closest('.location-row');
                const parishSelect = row.querySelector('.parish-select');
                
                parishSelect.innerHTML = '<option value="">Freguesia...</option>';

                if (cityId) {
                    fetch(`/properties/get-parishes?city_id=${cityId}`)
                        .then(response => response.json())
                        .then(parishes => {
                            parishes.forEach(parish => {
                                const option = document.createElement('option');
                                option.value = parish.id;
                                option.textContent = parish.name;
                                parishSelect.appendChild(option);
                            });
                        });
                }
            }
        });

        // Submeter formulário de conversão
        document.getElementById('convertToOpportunityForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A converter...';

            fetch(`/leads/{{ $lead->id }}/convert-to-opportunity`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    showToast('Erro: ' + (data.message || 'Erro desconhecido'), 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erro ao converter lead', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });

        // Carregar cidades e freguesias para localizações existentes quando o modal abrir
        const modal = document.getElementById('convertToOpportunityModal');
        if (modal) {
            modal.addEventListener('show.bs.modal', function() {
                document.querySelectorAll('.location-row').forEach(row => {
                    const districtSelect = row.querySelector('.district-select');
                    const citySelect = row.querySelector('.city-select');
                    const parishSelect = row.querySelector('.parish-select');
                    const selectedCityId = citySelect.dataset.selectedCity;
                    const selectedParishId = parishSelect.dataset.selectedParish;
                    
                    if (districtSelect.value) {
                        fetch(`/properties/get-cities?district_id=${districtSelect.value}`)
                            .then(response => response.json())
                            .then(cities => {
                                citySelect.innerHTML = '<option value="">Concelho...</option>';
                                cities.forEach(city => {
                                    const option = document.createElement('option');
                                    option.value = city.id;
                                    option.textContent = city.name;
                                    if (selectedCityId && selectedCityId == city.id) {
                                        option.selected = true;
                                    }
                                    citySelect.appendChild(option);
                                });
                                
                                if (selectedCityId) {
                                    citySelect.value = selectedCityId;
                                    
                                    // Carregar freguesias
                                    if (selectedParishId) {
                                        fetch(`/properties/get-parishes?city_id=${selectedCityId}`)
                                            .then(response => response.json())
                                            .then(parishes => {
                                                parishSelect.innerHTML = '<option value="">Freguesia...</option>';
                                                parishes.forEach(parish => {
                                                    const option = document.createElement('option');
                                                    option.value = parish.id;
                                                    option.textContent = parish.name;
                                                    if (selectedParishId && selectedParishId == parish.id) {
                                                        option.selected = true;
                                                    }
                                                    parishSelect.appendChild(option);
                                                });
                                            });
                                    }
                                }
                            });
                    }
                });
            });
        }
    });

    function archiveLead(leadId) {
        if (!confirm('Tem a certeza que deseja arquivar esta lead?')) {
            return;
        }

        fetch(`/leads/${leadId}/archive`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showToast('Erro ao arquivar lead: ' + (data.message || 'Erro desconhecido'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Erro ao arquivar lead', 'error');
        });
    }

    function unarchiveLead(leadId) {
        if (!confirm('Tem a certeza que deseja desarquivar esta lead?')) {
            return;
        }

        // Para desarquivar, vamos fazer um update do archived_at para null
        // Mas como não temos rota específica, vamos usar uma abordagem diferente
        // Na verdade, precisamos criar uma rota para desarquivar também
        fetch(`/leads/${leadId}/restore`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                action: 'unarchive'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showToast('Erro ao desarquivar lead: ' + (data.message || 'Erro desconhecido'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Erro ao desarquivar lead', 'error');
        });
    }

    function restoreLead(leadId) {
        if (!confirm('Tem a certeza que deseja recuperar esta lead? Ela voltará ao estado "Por Tratar".')) {
            return;
        }

        fetch(`/leads/${leadId}/restore`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showToast('Erro ao recuperar lead: ' + (data.message || 'Erro desconhecido'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Erro ao recuperar lead', 'error');
        });
    }
</script>
@endsection
