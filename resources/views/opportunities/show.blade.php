@extends('partials.layouts.main')
@section('title', 'Oportunidade ' . $opportunity->reference . ' | Imobiliária')
@section('page-heading-title', 'Oportunidade ' . $opportunity->reference)
@section('page-heading-sub-title', 'Oportunidades')
@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@php
    $isGanha = $opportunity->status === \App\Models\Opportunity::STATUS_GANHA;
    $statusColor = $opportunity->status_color;
    $priorityColor = $opportunity->priority_color;
    $types = \App\Models\Opportunity::types();
    $origins = \App\Models\Lead::origins();
    $agentAvatarSrc = null;
    if ($opportunity->agent) {
        $avatarNum = ($opportunity->agent->id % 9) + 1;
        $agentAvatarSrc = asset("template/img/avatars/avatar-{$avatarNum}.webp");
    }
@endphp

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-start">
        <div class="d-flex align-items-center gap-3">
            <div class="position-relative d-inline-block">
                <div class="h-50px w-50px rounded-pill bg-primary d-flex justify-content-center align-items-center text-white fs-5 fw-semibold">
                    {{ strtoupper(substr($opportunity->client->name, 0, 2)) }}
                </div>
            </div>
            <div>
                <h5 class="card-title mb-0">
                    <a href="{{ route('clientes.show', $opportunity->client) }}" class="text-body">{{ $opportunity->client->name }}</a>
                    <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} ms-2">{{ $statuses[$opportunity->status] ?? $opportunity->status }}</span>
                    <span class="badge bg-{{ $priorityColor }}-subtle text-{{ $priorityColor }} ms-1">{{ \App\Models\Opportunity::priorities()[$opportunity->priority] ?? $opportunity->priority }}</span>
                    <span class="badge bg-info-subtle text-info ms-1">{{ $types[$opportunity->type] ?? $opportunity->type }}</span>
                </h5>
                <p class="text-muted mb-0 small">{{ $opportunity->reference }}</p>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-start">
            @if($opportunity->canBeFinalized())
                <button type="button" class="btn btn-success btn-sm" onclick="openFinalizeDealModal()">
                    <i class="ph-duotone ph-check-circle me-1"></i> Fechar Negócio
                </button>
            @endif
            @if($opportunity->status !== \App\Models\Opportunity::STATUS_GANHA)
                <a href="{{ route('opportunities.edit', $opportunity) }}" class="btn btn-primary btn-sm"><i class="ph ph-pencil-simple me-1"></i> Editar</a>
            @endif
            @if($isGanha && $opportunity->deal)
                <button type="button" class="btn btn-warning btn-sm" onclick="openReabrirDealModal({{ $opportunity->deal->id }}, '{{ addslashes($opportunity->deal->reference) }}')">
                    <i class="ph ph-arrow-clockwise me-1"></i> Reabrir
                </button>
            @endif
            @if(!$isGanha)
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="opportunityOptionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ph ph-dots-three me-1"></i> Opções
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="opportunityOptionsDropdown">
                    @if(!$opportunity->isArchived())
                    <li>
                        <a class="dropdown-item text-muted" href="#" onclick="archiveOpportunity({{ $opportunity->id }}); return false;">
                            <i class="ph-duotone ph-archive me-2"></i> Arquivar
                        </a>
                    </li>
                    @else
                    <li>
                        <a class="dropdown-item text-success" href="#" onclick="unarchiveOpportunity({{ $opportunity->id }}); return false;">
                            <i class="ph-duotone ph-archive-box me-2"></i> Desarquivar
                        </a>
                    </li>
                    @endif
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="{{ route('opportunities.destroy', $opportunity) }}" method="POST" class="d-inline" onsubmit="return confirm('Tem a certeza que deseja remover esta oportunidade?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="ph ph-trash me-2"></i> Eliminar
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
            @endif
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6 col-lg-3">
                <div>
                    <p class="mb-0 text-muted small">Email</p>
                    <a href="mailto:{{ $opportunity->client->email }}" class="mb-0 text-body">{{ $opportunity->client->email }}</a>
                </div>
            </div>
            @if($opportunity->client->phone)
            <div class="col-12 col-md-6 col-lg-3">
                <div>
                    <p class="mb-0 text-muted small">Telefone</p>
                    <a href="tel:{{ $opportunity->client->phone }}" class="mb-0 text-body">{{ $opportunity->client->phone }}</a>
                </div>
            </div>
            @endif
            @if($opportunity->lead)
            <div class="col-12 col-md-6 col-lg-3">
                <div>
                    <p class="mb-0 text-muted small">Lead</p>
                    <a href="{{ route('leads.show', $opportunity->lead) }}" class="mb-0 text-body">{{ $opportunity->lead->lead_id }}</a>
                </div>
            </div>
            @endif
            @if($opportunity->lead && $opportunity->lead->origin)
            <div class="col-12 col-md-6 col-lg-3">
                <div>
                    <p class="mb-0 text-muted small">Origem</p>
                    <p class="mb-0 text-body">{{ $origins[$opportunity->lead->origin] ?? $opportunity->lead->origin }}</p>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@if($opportunity->status === \App\Models\Opportunity::STATUS_GANHA && $opportunity->deal)
<div class="card mb-3 border-success">
    <div class="card-header bg-success-subtle d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0 text-success"><i class="ph-duotone ph-trophy me-2"></i>Negócio Fechado</h5>
            <small class="text-muted">{{ $opportunity->deal->reference }}</small>
        </div>
        <a href="{{ route('deals.show', $opportunity->deal) }}" class="btn btn-sm btn-outline-success">
            <i class="ph ph-arrow-square-out me-1"></i> Ver Detalhes
        </a>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <p class="mb-0 text-muted small">Valor Final</p>
                <p class="mb-0 fs-4 fw-semibold text-success">{{ $opportunity->deal->formatted_final_price }}</p>
            </div>
            <div class="col-md-4">
                <p class="mb-0 text-muted small">Imóvel</p>
                <p class="mb-0">
                    <a href="{{ route('properties.show', $opportunity->deal->property_id) }}" class="text-body">
                        {{ $opportunity->deal->property_title }}
                    </a>
                </p>
                <small class="text-muted">{{ $opportunity->deal->property_reference }}</small>
            </div>
            <div class="col-md-4">
                <p class="mb-0 text-muted small">Data de Fecho</p>
                <p class="mb-0">{{ $opportunity->deal->closed_at->format('d/m/Y H:i') }}</p>
                <small class="text-muted">por {{ $opportunity->deal->closedBy->name ?? 'N/A' }}</small>
            </div>
        </div>
        
        @if($opportunity->deal->agentCommissions->count() > 0)
        <hr class="my-3">
        <h6 class="mb-3">Comissões dos Agentes</h6>
        <div class="row g-2">
            @foreach($opportunity->deal->agentCommissions as $commission)
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-2 p-2 bg-light rounded">
                    <div class="flex-grow-1">
                        <p class="mb-0 fw-medium">{{ $commission->agent_name }}</p>
                        <small class="text-muted">{{ $commission->role_label }}</small>
                    </div>
                    <div class="text-end">
                        @if($commission->commission_value)
                            <p class="mb-0 fw-semibold text-primary">{{ $commission->formatted_commission_value }}</p>
                        @endif
                        @if($commission->commission_percentage)
                            <small class="text-muted">({{ $commission->formatted_commission_percentage }})</small>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endif

@php
    $preference = $opportunity->propertyPreferences->first();
@endphp

<div class="row">
    <!-- Lado Esquerdo: Preferências de Imóvel -->
    <div class="col-xl-3">
        <div class="card">
            <div class="card-body">
                @if($preference)
                <div class="pb-5 border-bottom border-dashed d-flex flex-column gap-4">
                    <h5 class="card-title">Preferências de Imóvel</h5>
                    
                    @if($preference->transactionType)
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <p class="mb-0 text-muted small">Negócio</p>
                            <p class="mb-0 text-body">{{ $preference->transactionType->name }}</p>
                        </div>
                    </div>
                    @endif

                    @if($preference->min_price || $preference->max_price)
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <p class="mb-0 text-muted small">Faixa de Preço</p>
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

                    @if($preference->propertyType)
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <p class="mb-0 text-muted small">Tipo de Imóvel</p>
                            <p class="mb-0 text-body">{{ $preference->propertyType->name }}</p>
                        </div>
                    </div>
                    @endif

                    @if($preference->propertyCondition)
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <p class="mb-0 text-muted small">Estado do Imóvel</p>
                            <p class="mb-0 text-body">{{ $preference->propertyCondition->name }}</p>
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
                            <p class="mb-0 text-muted small">Localizações</p>
                            <div class="mb-0 text-body">
                                @foreach($preference->preferenceLocations as $location)
                                    @php
                                        $locationParts = [];
                                        if ($location->id_parish) {
                                            $locationParts[] = \App\Models\Local::getParishNameById($location->id_parish);
                                        }
                                        if ($location->id_city) {
                                            $locationParts[] = \App\Models\Local::getCityNameById($location->id_city);
                                        }
                                        if ($location->id_district) {
                                            $locationParts[] = \App\Models\Local::getDistrictNameById($location->id_district);
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
                @else
                <div class="pb-5 border-bottom border-dashed">
                    <h5 class="card-title">Preferências de Imóvel</h5>
                    <p class="text-muted mb-0">Nenhuma preferência definida.</p>
                </div>
                @endif

                @if($opportunity->notes)
                <div class="pt-5 d-flex flex-column gap-4">
                    <h5 class="card-title">Notas Gerais</h5>
                    <p class="text-muted mb-0">{{ $opportunity->notes }}</p>
                </div>
                @endif

                <!-- Informações da Oportunidade -->
                <div class="pt-5 d-flex flex-column gap-3">
                    <h5 class="card-title">Informações</h5>
                    @if($opportunity->agent)
                    <div>
                        <p class="mb-1 text-muted small">Agente Responsável</p>
                        <div class="d-flex align-items-center gap-2">
                            @if($agentAvatarSrc)
                                <img src="{{ $agentAvatarSrc }}" alt="{{ $opportunity->agent->name }}" class="avatar-sm rounded-circle">
                            @endif
                            <p class="mb-0 text-body">{{ $opportunity->agent->name }}</p>
                        </div>
                    </div>
                    @endif
                    <div>
                        <p class="mb-1 text-muted small">Estado</p>
                        <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} fs-6">{{ $statuses[$opportunity->status] ?? $opportunity->status }}</span>
                    </div>
                    @if($opportunity->status_changed_at)
                    <div>
                        <p class="mb-1 text-muted small">Última Alteração</p>
                        <p class="mb-0 text-body">{{ $opportunity->status_changed_at->format('d/m/Y H:i') }}</p>
                    </div>
                    @endif
                    <div>
                        <p class="mb-1 text-muted small">Criado em</p>
                        <p class="mb-0 text-body">{{ $opportunity->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Meio: Imóveis Cruzados e Associados -->
    <div class="col-xl-5 order-last order-xl-2">
        <!-- Imóveis Cruzados -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Imóveis Cruzados</h5>
            </div>
            <div class="card-body">
                <div id="crossedPropertiesContainer">
                    @if($crossedProperties->count() > 0)
                        <div class="d-flex flex-column">
                            @foreach($crossedProperties as $property)
                                <div class="d-flex gap-3 py-3 {{ !$loop->last ? 'border-bottom' : '' }}" id="crossed-property-{{ $property->id }}">
                                    <div class="flex-shrink-0" style="width: 120px;">
                                        @if($property->mainImage)
                                            <img src="{{ asset('storage/' . $property->mainImage->path) }}" alt="{{ $property->title }}" class="img-fluid rounded" style="height: 100px; width: 100%; object-fit: cover;">
                                        @else
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 100px;">
                                                <i class="ph-duotone ph-image fs-3 text-muted"></i>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1">
                                                    <a href="{{ route('properties.show', $property) }}" class="text-body">{{ $property->title }}</a>
                                                </h6>
                                                <small class="text-muted">{{ $property->reference }}</small>
                                            </div>
                                            @if(!$isGanha)
                                            <button type="button" class="btn btn-sm btn-primary" onclick="attachProperty({{ $property->id }})">
                                                <i class="ph ph-plus"></i>
                                            </button>
                                            @endif
                                        </div>
                                        <div class="mb-2">
                                            <span class="badge bg-primary-subtle text-primary">{{ $property->formatted_price }}</span>
                                            @if($property->propertyTypology)
                                                <span class="badge bg-info-subtle text-info ms-1">{{ $property->propertyTypology->name }}</span>
                                            @endif
                                            @if($property->status)
                                                <span class="badge bg-{{ $property->status_color }}-subtle text-{{ $property->status_color }} ms-1">{{ $property->statuses()[$property->status] ?? $property->status }}</span>
                                            @endif
                                        </div>
                                        <small class="text-muted d-block">{{ $property->full_address }}</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted text-center py-3">Nenhum imóvel sugerido com base nos critérios definidos.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Imóveis Associados -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Imóveis Associados</h5>
                <span class="badge bg-primary">{{ $associatedProperties->count() }} imóvel(is)</span>
            </div>
            <div class="card-body">
                @if($associatedProperties->count() > 0)
                    <div class="d-flex flex-column">
                        @foreach($associatedProperties as $property)
                            <div class="d-flex gap-3 py-3 {{ !$loop->last ? 'border-bottom' : '' }}" id="associated-property-{{ $property->id }}">
                                <div class="flex-shrink-0" style="width: 120px;">
                                    @if($property->mainImage)
                                        <img src="{{ asset('storage/' . $property->mainImage->path) }}" alt="{{ $property->title }}" class="img-fluid rounded" style="height: 100px; width: 100%; object-fit: cover;">
                                    @else
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 100px;">
                                            <i class="ph-duotone ph-image fs-3 text-muted"></i>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1">
                                                <a href="{{ route('properties.show', $property) }}" class="text-body">{{ $property->title }}</a>
                                            </h6>
                                            <small class="text-muted">{{ $property->reference }}</small>
                                        </div>
                                        @if(!$isGanha)
                                        <button type="button" class="btn btn-sm btn-danger" onclick="detachProperty({{ $property->id }})">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                        @endif
                                    </div>
                                    <div class="mb-2">
                                        <span class="badge bg-primary-subtle text-primary">{{ $property->formatted_price }}</span>
                                        @if($property->propertyTypology)
                                            <span class="badge bg-info-subtle text-info ms-1">{{ $property->propertyTypology->name }}</span>
                                        @endif
                                        @if($property->status)
                                            <span class="badge bg-{{ $property->status_color }}-subtle text-{{ $property->status_color }} ms-1">{{ $property->statuses()[$property->status] ?? $property->status }}</span>
                                        @endif
                                    </div>
                                    <small class="text-muted d-block mb-2">{{ $property->full_address }}</small>
                                    @if($property->pivot->notes)
                                        <div class="mb-2 p-2 bg-light rounded">
                                            <small class="text-muted"><strong>Nota:</strong> {{ $property->pivot->notes }}</small>
                                        </div>
                                    @endif
                                    <small class="text-muted d-block mb-2">
                                        <i class="ph-duotone ph-clock me-1"></i>Associado em {{ $property->pivot->attached_at ? \Carbon\Carbon::parse($property->pivot->attached_at)->format('d/m/Y H:i') : 'N/A' }}
                                    </small>
                                    @if(!$isGanha)
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="openScheduleVisitModal({{ $property->id }}, '{{ addslashes($property->title) }}')">
                                            <i class="ph-duotone ph-calendar-check me-1"></i> Marcar Visita
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="openCreateProposalModal({{ $property->id }}, '{{ addslashes($property->title) }}', {{ $property->price ?? 0 }})">
                                            <i class="ph-duotone ph-file-text me-1"></i> Criar Proposta
                                        </button>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted text-center py-3">Nenhum imóvel associado ainda. Use os imóveis cruzados acima para associar.</p>
                @endif
            </div>
        </div>

        <!-- Visitas -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Visitas</h5>
                <span class="badge bg-primary">{{ $opportunity->visits->count() }} visita(s)</span>
            </div>
            <div class="card-body">
                @if($opportunity->visits->count() > 0)
                    <div class="d-flex flex-column">
                        @foreach($opportunity->visits->sortByDesc('scheduled_at') as $visit)
                            <div class="d-flex gap-3 py-3 {{ !$loop->last ? 'border-bottom' : '' }}" id="visit-{{ $visit->id }}">
                                <div class="flex-shrink-0" style="width: 80px;">
                                    @if($visit->property->mainImage)
                                        <img src="{{ asset('storage/' . $visit->property->mainImage->path) }}" alt="{{ $visit->property->title }}" class="img-fluid rounded" style="height: 70px; width: 100%; object-fit: cover;">
                                    @else
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 70px;">
                                            <i class="ph-duotone ph-image fs-4 text-muted"></i>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0">
                                            <a href="{{ route('properties.show', $visit->property) }}" class="text-body">{{ $visit->property->title }}</a>
                                        </h6>
                                        <span class="badge bg-{{ $visit->status_color }}-subtle text-{{ $visit->status_color }}">
                                            {{ \App\Models\Visit::statuses()[$visit->status] ?? $visit->status }}
                                        </span>
                                    </div>
                                    <small class="text-muted d-block mb-2">
                                        <i class="ph-duotone ph-calendar-check me-1"></i>{{ $visit->scheduled_at->format('d/m/Y H:i') }}
                                    </small>
                                    @if($visit->status === \App\Models\Visit::STATUS_REALIZADA && ($visit->client_feedback_strengths || $visit->client_feedback_weaknesses))
                                        <div class="small">
                                            @if($visit->client_feedback_strengths)
                                                <p class="mb-1"><strong class="text-success">Pontos fortes:</strong> {{ $visit->client_feedback_strengths }}</p>
                                            @endif
                                            @if($visit->client_feedback_weaknesses)
                                                <p class="mb-0"><strong class="text-warning">Pontos fracos:</strong> {{ $visit->client_feedback_weaknesses }}</p>
                                            @endif
                                        </div>
                                    @endif
                                    @if(!$isGanha)
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-1 btn-edit-visit"
                                        data-visit-id="{{ $visit->id }}"
                                        data-visit-status="{{ $visit->status }}"
                                        data-visit-scheduled="{{ $visit->scheduled_at->format('Y-m-d\TH:i') }}"
                                        data-visit-strengths="{{ e($visit->client_feedback_strengths ?? '') }}"
                                        data-visit-weaknesses="{{ e($visit->client_feedback_weaknesses ?? '') }}"
                                        data-visit-notes="{{ e($visit->notes ?? '') }}">
                                        <i class="ph ph-pencil-simple me-1"></i> Editar
                                    </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted text-center py-3">Nenhuma visita agendada. Associe um imóvel e clique em "Marcar Visita".</p>
                @endif
            </div>
        </div>

        <!-- Propostas -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Propostas</h5>
                <span class="badge bg-primary">{{ $opportunity->proposals->count() }} proposta(s)</span>
            </div>
            <div class="card-body">
                @php
                    $rootProposals = $opportunity->proposals->whereNull('parent_proposal_id')->sortByDesc('created_at');
                @endphp
                @if($rootProposals->count() > 0)
                    <div class="d-flex flex-column">
                        @foreach($rootProposals as $proposal)
                            @include('opportunities.partials.proposal-item', ['proposal' => $proposal, 'readOnly' => $isGanha])
                        @endforeach
                    </div>
                @else
                    <p class="text-muted text-center py-3">Nenhuma proposta criada. Associe um imóvel e clique em "Criar Proposta".</p>
                @endif
            </div>
        </div>

        <!-- Histórico de Estados -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Histórico de Alterações</h5>
            </div>
            <div class="card-body">
                @if($opportunity->statusLogs->count() > 0)
                <div class="timeline2 icon-timeline">
                        <ul>
                            @foreach($opportunity->statusLogs as $log)
                                <li class="box">
                                    <span class="bg-{{ $opportunity->getStatusColorAttribute() }} text-white">
                                        <i class="ph ph-arrow-right"></i>
                                    </span>
                                    <p class="text-muted float-end fs-13 mb-0">{{ $log->created_at->format('d/m/Y H:i') }}</p>
                                    <h6 class="title">{{ $log->changedBy ? $log->changedBy->name : 'Sistema' }}</h6>
                                    <div class="info">
                                        @if($log->old_status)
                                            <span class="badge bg-secondary-subtle text-secondary me-2">{{ $statuses[$log->old_status] ?? $log->old_status }}</span>
                                            <i class="ph ph-arrow-right"></i>
                                        @endif
                                        <span class="badge bg-{{ $opportunity->getStatusColorAttribute() }}-subtle text-{{ $opportunity->getStatusColorAttribute() }} ms-2">{{ $statuses[$log->new_status] ?? $log->new_status }}</span>
                                        @if($log->notes)
                                            <p class="mb-0 mt-2 text-muted">{{ $log->notes }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <p class="text-muted text-center py-3">Nenhuma alteração registada ainda.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Lado Direito: Histórico de Estados e Notas -->
    <div class="col-xl-4 order-2 order-xl-last">
        <!-- Notas em Timeline -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Notas</h5>
                @if(!$isGanha)
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                    <i class="ph ph-plus me-1"></i> Adicionar
                </button>
                @endif
            </div>
            <div class="card-body">
                @php
                    // Garantir que usamos o relacionamento, não o atributo 'notes'
                    // getRelationValue() sempre retorna o relacionamento se existir, senão busca
                    $opportunityNotes = $opportunity->getRelationValue('notes') ?: $opportunity->notes()->with('user')->get();
                @endphp
                @include('partials.notes-timeline', ['notes' => $opportunityNotes])
            </div>
        </div>
    </div>
</div>

@if(!$isGanha)
@include('partials.note-form-modal', ['route' => route('opportunities.storeNote', $opportunity), 'modelName' => 'oportunidade'])
@endif

<!-- Modal para Reabrir negócio -->
@if($isGanha && $opportunity->deal)
<div class="modal fade" id="reabrirDealModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title text-warning"><i class="ph ph-arrow-clockwise me-2"></i>Reabrir Negócio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="reabrirDealForm">
                <input type="hidden" id="reabrirDealId" name="deal_id">
                <div class="modal-body">
                    <p class="mb-3">Ao reabrir o negócio <strong id="reabrirDealRef"></strong>, a oportunidade voltará ao estado "Proposta aceite" e o imóvel ficará novamente disponível. O histórico do negócio será mantido como "Revertido".</p>
                    <div class="mb-0">
                        <label for="reabrirReason" class="form-label">Motivo da reabertura <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reabrirReason" name="reversion_reason" rows="3" required placeholder="Indique o motivo da reabertura..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="ph ph-arrow-clockwise me-1"></i> Reabrir Negócio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if(!$isGanha)
<!-- Modal para associar imóvel com nota -->
<div class="modal fade" id="attachPropertyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Associar Imóvel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="attachPropertyForm">
                <div class="modal-body">
                    <input type="hidden" id="attachPropertyId" name="property_id">
                    <div class="mb-3">
                        <label class="form-label">Nota (opcional)</label>
                        <textarea name="notes" id="attachPropertyNotes" class="form-control" rows="3" placeholder="Adicione uma nota sobre esta associação..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Associar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para desassociar imóvel -->
<div class="modal fade" id="detachPropertyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Desassociar Imóvel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="detachPropertyForm">
                <div class="modal-body">
                    <input type="hidden" id="detachPropertyId" name="property_id">
                    <p class="mb-0">Tem a certeza que deseja desassociar este imóvel da oportunidade?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Desassociar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<!-- Modal para arquivar oportunidade -->
<div class="modal fade" id="archiveOpportunityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Arquivar Oportunidade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="archiveOpportunityForm">
                <div class="modal-body">
                    <input type="hidden" id="archiveOpportunityId" name="opportunity_id">
                    <p class="mb-0">Tem a certeza que deseja arquivar esta oportunidade?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Arquivar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para marcar visita -->
<div class="modal fade" id="scheduleVisitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Marcar Visita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="scheduleVisitForm">
                <div class="modal-body">
                    <input type="hidden" id="visitPropertyId" name="property_id">
                    <p class="text-muted mb-3" id="visitPropertyName"></p>
                    <div class="mb-3">
                        <label for="visitScheduledAt" class="form-label">Data e Hora <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="visitScheduledAt" name="scheduled_at" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Agendar Visita</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para editar visita -->
<div class="modal fade" id="editVisitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Visita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editVisitForm">
                <input type="hidden" id="editVisitId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editVisitStatus" class="form-label">Estado</label>
                        <select class="form-select" id="editVisitStatus" name="status">
                            @foreach(\App\Models\Visit::statuses() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editVisitScheduledAt" class="form-label">Data e Hora</label>
                        <input type="datetime-local" class="form-control" id="editVisitScheduledAt" name="scheduled_at">
                    </div>
                    <div class="mb-3" id="editVisitFeedbackSection">
                        <label class="form-label">Opinião do Cliente</label>
                        <div class="mb-2">
                            <label for="editVisitStrengths" class="form-label small text-success">Pontos fortes</label>
                            <textarea class="form-control" id="editVisitStrengths" name="client_feedback_strengths" rows="2" placeholder="O que o cliente gostou..."></textarea>
                        </div>
                        <div>
                            <label for="editVisitWeaknesses" class="form-label small text-warning">Pontos fracos</label>
                            <textarea class="form-control" id="editVisitWeaknesses" name="client_feedback_weaknesses" rows="2" placeholder="O que o cliente não gostou..."></textarea>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label for="editVisitNotes" class="form-label">Notas</label>
                        <textarea class="form-control" id="editVisitNotes" name="notes" rows="2" placeholder="Outras observações..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal criar proposta -->
<div class="modal fade" id="createProposalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Proposta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createProposalForm">
                <div class="modal-body">
                    <input type="hidden" id="proposalPropertyId" name="property_id">
                    <p class="text-muted mb-3" id="proposalPropertyName"></p>
                    <div class="mb-3">
                        <label for="proposalValue" class="form-label">Valor Proposto (€) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="proposalValue" name="proposed_value" step="0.01" min="0" required>
                    </div>
                    <div class="mb-0">
                        <label for="proposalConditions" class="form-label">Condições</label>
                        <textarea class="form-control" id="proposalConditions" name="conditions" rows="3" placeholder="Condições da proposta..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Proposta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal editar proposta -->
<div class="modal fade" id="editProposalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Proposta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editProposalForm">
                <input type="hidden" id="editProposalId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editProposalValue" class="form-label">Valor Proposto (€) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="editProposalValue" name="proposed_value" step="0.01" min="0" required>
                    </div>
                    <div class="mb-0">
                        <label for="editProposalConditions" class="form-label">Condições</label>
                        <textarea class="form-control" id="editProposalConditions" name="conditions" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal rejeitar proposta -->
<div class="modal fade" id="rejectProposalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rejeitar Proposta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectProposalForm">
                <input type="hidden" id="rejectProposalId">
                <div class="modal-body">
                    <div class="mb-0">
                        <label for="rejectionReason" class="form-label">Motivo da rejeição (opcional)</label>
                        <textarea class="form-control" id="rejectionReason" name="rejection_reason" rows="3" placeholder="Indique o motivo da rejeição..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Rejeitar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal contraproposta -->
<div class="modal fade" id="counterProposalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Criar Contraproposta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="counterProposalForm">
                <input type="hidden" id="counterProposalParentId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="counterProposalValue" class="form-label">Valor Proposto (€) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="counterProposalValue" name="proposed_value" step="0.01" min="0" required>
                    </div>
                    <div class="mb-0">
                        <label for="counterProposalConditions" class="form-label">Condições</label>
                        <textarea class="form-control" id="counterProposalConditions" name="conditions" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Contraproposta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para desarquivar oportunidade -->
<div class="modal fade" id="unarchiveOpportunityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Desarquivar Oportunidade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="unarchiveOpportunityForm">
                <div class="modal-body">
                    <input type="hidden" id="unarchiveOpportunityId" name="opportunity_id">
                    <p class="mb-0">Tem a certeza que deseja desarquivar esta oportunidade?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Desarquivar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para fechar negócio -->
@if($opportunity->canBeFinalized())
@php
    $approvedProposal = $opportunity->approved_proposal;
    $approvedProperty = $approvedProposal?->property?->load('agent');
    $saleValue = (float) ($approvedProposal?->proposed_value ?? 0);
    $vendedor = $opportunity->agent;
    $angariador = $approvedProperty?->agent ?? $vendedor;
    $isPleno = $vendedor && $angariador && $vendedor->id === $angariador->id;
    $propCommPct = $approvedProperty?->commission_percentage ? (float) $approvedProperty->commission_percentage : 0;
    $propCommVal = $approvedProperty?->commission_value ? (float) $approvedProperty->commission_value : ($saleValue > 0 && $propCommPct > 0 ? round($saleValue * ($propCommPct / 100), 2) : 0);
    if ($propCommVal == 0 && $propCommPct > 0) {
        $propCommVal = round($saleValue * ($propCommPct / 100), 2);
    }
    $consultantSharePct = 50;
    $defaultAgentCommission = $propCommVal > 0 ? round($propCommVal * ($consultantSharePct / 100), 2) : 0;
    if ($isPleno && $vendedor) {
        $defaultVendedorVal = $defaultAgentCommission;
        $defaultAngariadorVal = 0;
    } else {
        $defaultVendedorVal = $propCommVal > 0 ? round($defaultAgentCommission / 2, 2) : 0;
        $defaultAngariadorVal = $propCommVal > 0 ? round($defaultAgentCommission / 2, 2) : 0;
    }
@endphp
<div class="modal fade" id="finalizeDealModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" style="max-height: 90vh;">
        <div class="modal-content">
            <div class="modal-header bg-success-subtle">
                <h5 class="modal-title text-success"><i class="ph-duotone ph-check-circle me-2"></i>Fechar Negócio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="finalizeDealForm">
                <div class="modal-body" style="max-height: calc(90vh - 200px); overflow-y: auto;">
                    <!-- Resumo da Proposta Aceite -->
                    <div class="alert alert-success mb-4">
                        <h6 class="alert-heading mb-2"><i class="ph-duotone ph-file-text me-1"></i> Proposta Aceite</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Imóvel:</strong> {{ $approvedProperty?->title ?? 'N/A' }}</p>
                                <p class="mb-1"><strong>Referência:</strong> {{ $approvedProperty?->reference ?? 'N/A' }}</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Valor Acordado:</strong></p>
                                <p class="mb-0 fs-4 fw-bold" id="dealSaleValueDisplay">{{ number_format($saleValue, 2, ',', '.') }} €</p>
                            </div>
                        </div>
                        @if($approvedProposal?->conditions)
                            <hr class="my-2">
                            <p class="mb-0 small"><strong>Condições:</strong> {{ $approvedProposal->conditions }}</p>
                        @endif
                    </div>

                    <!-- Comissão do Imóvel (editável) -->
                    <h6 class="mb-3"><i class="ph-duotone ph-currency-eur me-1"></i> Comissão do Imóvel</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="propertyCommissionValue" class="form-label">Valor (€)</label>
                            <input type="number" class="form-control" id="propertyCommissionValue" name="property_commission_value" step="0.01" min="0" value="{{ $propCommVal }}" placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label for="propertyCommissionPercentage" class="form-label">Percentagem (%)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="propertyCommissionPercentage" name="property_commission_percentage" step="0.01" min="0" max="100" value="{{ $propCommPct }}" placeholder="0">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Máx. comissão dos consultores: <span id="maxConsultantCommissionDisplay">{{ number_format($propCommVal, 2, ',', '.') }}</span> €</small>
                        </div>
                    </div>

                    <!-- Agentes e Comissões (auto-preenchidos) -->
                    <h6 class="mb-3"><i class="ph-duotone ph-user-star me-1"></i> Agentes Envolvidos e Comissões</h6>
                    @if($isPleno && $vendedor)
                        <p class="text-info small mb-2"><i class="ph-duotone ph-info me-1"></i> Negócio Pleno – {{ $vendedor->name }} (Vendedor e Angariador)</p>
                    @endif
                    <div id="agentCommissionsContainer">
                        @if($vendedor)
                        <div class="agent-commission-row card card-body bg-light mb-3">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Agente</label>
                                    <input type="text" class="form-control bg-white" value="{{ $vendedor->name }} (Vendedor)" readonly>
                                    <input type="hidden" name="agent_commissions[0][agent_id]" value="{{ $vendedor->id }}">
                                    <input type="hidden" name="agent_commissions[0][role]" value="{{ \App\Models\Deal::ROLE_VENDEDOR }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Valor (€)</label>
                                    <input type="number" class="form-control agent-commission-value" name="agent_commissions[0][commission_value]" step="0.01" min="0" value="{{ $defaultVendedorVal }}" placeholder="0.00" data-max="{{ $propCommVal }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">% da comissão do imóvel</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control agent-commission-percentage" name="agent_commissions[0][commission_percentage]" step="0.01" min="0" max="100" placeholder="0">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                        @if(!$isPleno && $angariador)
                        <div class="agent-commission-row card card-body bg-light mb-3">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Agente</label>
                                    <input type="text" class="form-control bg-white" value="{{ $angariador->name }} (Angariador)" readonly>
                                    <input type="hidden" name="agent_commissions[1][agent_id]" value="{{ $angariador->id }}">
                                    <input type="hidden" name="agent_commissions[1][role]" value="{{ \App\Models\Deal::ROLE_ANGARIADOR }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Valor (€)</label>
                                    <input type="number" class="form-control agent-commission-value" name="agent_commissions[1][commission_value]" step="0.01" min="0" value="{{ $defaultAngariadorVal }}" placeholder="0.00" data-max="{{ $propCommVal }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">% da comissão do imóvel</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control agent-commission-percentage" name="agent_commissions[1][commission_percentage]" step="0.01" min="0" max="100" placeholder="0">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                        @if(!$vendedor && !$angariador)
                        <div class="alert alert-warning mb-3">Não foi encontrado agente responsável pela oportunidade nem angariador do imóvel. Por favor, associe um agente à oportunidade ou ao imóvel.</div>
                        @endif
                    </div>

                    <!-- Totais -->
                    <div class="card bg-primary-subtle mb-3">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-medium">Total Consultores:</span>
                                <span class="fs-5 fw-bold text-primary" id="totalCommissionsDisplay">0,00 €</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center text-muted small">
                                <span>Limite (não pode ultrapassar comissão do imóvel):</span>
                                <span id="limitCommissionsDisplay">{{ number_format($propCommVal, 2, ',', '.') }} €</span>
                            </div>
                            <div id="commissionExceedAlert" class="alert alert-danger py-2 mt-2 mb-0 d-none">
                                <i class="ph-duotone ph-warning me-1"></i> O total das comissões dos consultores não pode ultrapassar a comissão do imóvel.
                            </div>
                        </div>
                    </div>

                    <!-- Notas -->
                    <div class="mb-0">
                        <label for="dealNotes" class="form-label">Notas (opcional)</label>
                        <textarea class="form-control" id="dealNotes" name="notes" rows="2" placeholder="Observações sobre o negócio..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="finalizeDealSubmitBtn" @if(!$vendedor && !$angariador) disabled @endif>
                        <i class="ph-duotone ph-check-circle me-1"></i> Confirmar e Fechar Negócio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@section('js')
<script>
    // ============ Fechar Negócio ============
    @if($opportunity->canBeFinalized())
    const dealSaleValue = {{ $saleValue ?? 0 }};

    function openFinalizeDealModal() {
        new bootstrap.Modal(document.getElementById('finalizeDealModal')).show();
        updateDealCommissions();
    }

    function getMaxCommission() {
        const valInput = document.getElementById('propertyCommissionValue');
        const pctInput = document.getElementById('propertyCommissionPercentage');
        if (!valInput || !pctInput) return 0;
        const val = parseFloat(valInput.value) || 0;
        const pct = parseFloat(pctInput.value) || 0;
        if (val > 0) return val;
        if (pct > 0 && dealSaleValue > 0) return Math.round(dealSaleValue * (pct / 100) * 100) / 100;
        return 0;
    }

    function updateDealCommissions() {
        const maxCommission = getMaxCommission();
        document.getElementById('maxConsultantCommissionDisplay').textContent = maxCommission.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('limitCommissionsDisplay').textContent = maxCommission.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
        
        let total = 0;
        document.querySelectorAll('.agent-commission-row .agent-commission-value').forEach(input => {
            const value = parseFloat(input.value) || 0;
            total += value;
            const pctInput = input.closest('.row').querySelector('.agent-commission-percentage');
            if (pctInput && maxCommission > 0) {
                pctInput.value = (value / maxCommission * 100).toFixed(2);
            }
        });
        
        document.getElementById('totalCommissionsDisplay').textContent = total.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
        
        const exceedAlert = document.getElementById('commissionExceedAlert');
        const submitBtn = document.getElementById('finalizeDealSubmitBtn');
        if (total > maxCommission) {
            exceedAlert?.classList.remove('d-none');
            submitBtn?.setAttribute('disabled', 'disabled');
        } else {
            exceedAlert?.classList.add('d-none');
            submitBtn?.removeAttribute('disabled');
        }
    }

    function syncPropertyCommission(byValue) {
        const valInput = document.getElementById('propertyCommissionValue');
        const pctInput = document.getElementById('propertyCommissionPercentage');
        if (!valInput || !pctInput) return;
        if (byValue) {
            const val = parseFloat(valInput.value) || 0;
            if (dealSaleValue > 0 && val > 0) {
                pctInput.value = (val / dealSaleValue * 100).toFixed(2);
            }
        } else {
            const pct = parseFloat(pctInput.value) || 0;
            if (dealSaleValue > 0 && pct > 0) {
                valInput.value = (dealSaleValue * (pct / 100)).toFixed(2);
            }
        }
        updateDealCommissions();
    }

    document.getElementById('propertyCommissionValue')?.addEventListener('input', () => syncPropertyCommission(true));
    document.getElementById('propertyCommissionPercentage')?.addEventListener('input', () => syncPropertyCommission(false));

    let isSyncingAgentCommission = false;
    function syncAgentCommissionFromValue(valueInput) {
        if (isSyncingAgentCommission) return;
        const maxCommission = getMaxCommission();
        if (maxCommission <= 0) return;
        const row = valueInput.closest('.row');
        const pctInput = row?.querySelector('.agent-commission-percentage');
        if (pctInput) {
            isSyncingAgentCommission = true;
            const val = parseFloat(valueInput.value) || 0;
            pctInput.value = (val / maxCommission * 100).toFixed(2);
            isSyncingAgentCommission = false;
        }
        updateDealCommissions();
    }

    function syncAgentCommissionFromPercentage(pctInput) {
        if (isSyncingAgentCommission) return;
        const maxCommission = getMaxCommission();
        if (maxCommission <= 0) return;
        const row = pctInput.closest('.row');
        const valueInput = row?.querySelector('.agent-commission-value');
        if (valueInput) {
            isSyncingAgentCommission = true;
            const pct = parseFloat(pctInput.value) || 0;
            const val = Math.round(maxCommission * (pct / 100) * 100) / 100;
            valueInput.value = val.toFixed(2);
            isSyncingAgentCommission = false;
        }
        updateDealCommissions();
    }

    document.querySelectorAll('.agent-commission-value').forEach(input => {
        input.addEventListener('input', () => syncAgentCommissionFromValue(input));
    });

    document.querySelectorAll('.agent-commission-percentage').forEach(input => {
        input.addEventListener('input', () => syncAgentCommissionFromPercentage(input));
    });

    document.getElementById('finalizeDealForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const maxCommission = getMaxCommission();
        let total = 0;
        const agentCommissions = [];
        document.querySelectorAll('.agent-commission-row').forEach((row) => {
            const agentId = row.querySelector('[name*="[agent_id]"]')?.value;
            const role = row.querySelector('[name*="[role]"]')?.value;
            const commissionValue = row.querySelector('[name*="[commission_value]"]')?.value;
            const commissionPercentage = row.querySelector('[name*="[commission_percentage]"]')?.value;
            if (agentId && role) {
                const val = parseFloat(commissionValue) || 0;
                total += val;
                agentCommissions.push({
                    agent_id: agentId,
                    role: role,
                    commission_value: commissionValue || null,
                    commission_percentage: commissionPercentage || null
                });
            }
        });

        if (agentCommissions.length === 0) {
            alert('É necessário pelo menos um agente.');
            return;
        }

        if (total > maxCommission) {
            alert('O total das comissões dos consultores (' + total.toFixed(2).replace('.', ',') + ' €) não pode ultrapassar a comissão do imóvel (' + maxCommission.toFixed(2).replace('.', ',') + ' €).');
            return;
        }

        const data = {
            property_commission_value: document.getElementById('propertyCommissionValue')?.value || null,
            property_commission_percentage: document.getElementById('propertyCommissionPercentage')?.value || null,
            agent_commissions: agentCommissions,
            notes: document.getElementById('dealNotes')?.value || ''
        };

        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A processar...';

        fetch('{{ route('opportunities.finalize', $opportunity) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('finalizeDealModal')).hide();
                alert('Negócio fechado com sucesso!');
                location.reload();
            } else {
                alert('Erro: ' + (data.message || 'Erro desconhecido'));
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="ph-duotone ph-check-circle me-1"></i> Confirmar e Fechar Negócio';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erro ao fechar negócio');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ph-duotone ph-check-circle me-1"></i> Confirmar e Fechar Negócio';
        });
    });
    @endif

    @if($isGanha && $opportunity->deal)
    function openReabrirDealModal(dealId, dealRef) {
        document.getElementById('reabrirDealId').value = dealId;
        document.getElementById('reabrirDealRef').textContent = dealRef;
        document.getElementById('reabrirReason').value = '';
        new bootstrap.Modal(document.getElementById('reabrirDealModal')).show();
    }

    document.getElementById('reabrirDealForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const dealId = document.getElementById('reabrirDealId').value;
        const data = {
            reversion_reason: document.getElementById('reabrirReason').value
        };
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A processar...';
        fetch('{{ url("deals") }}/' + dealId + '/revert', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('reabrirDealModal')).hide();
                alert('Negócio reaberto com sucesso.');
                location.reload();
            } else {
                alert('Erro: ' + (data.message || 'Erro desconhecido'));
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="ph ph-arrow-clockwise me-1"></i> Reabrir Negócio';
            }
        })
        .catch(() => {
            alert('Erro ao reabrir negócio');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ph ph-arrow-clockwise me-1"></i> Reabrir Negócio';
        });
    });
    @endif

    // ============ Visitas ============
    function openScheduleVisitModal(propertyId, propertyTitle) {
        document.getElementById('visitPropertyId').value = propertyId;
        document.getElementById('visitPropertyName').textContent = 'Imóvel: ' + propertyTitle;
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('visitScheduledAt').value = now.toISOString().slice(0, 16);
        new bootstrap.Modal(document.getElementById('scheduleVisitModal')).show();
    }

    function openEditVisitModal(data) {
        document.getElementById('editVisitId').value = data.id;
        document.getElementById('editVisitStatus').value = data.status;
        document.getElementById('editVisitScheduledAt').value = data.scheduled_at || '';
        document.getElementById('editVisitStrengths').value = data.client_feedback_strengths || '';
        document.getElementById('editVisitWeaknesses').value = data.client_feedback_weaknesses || '';
        document.getElementById('editVisitNotes').value = data.notes || '';
        new bootstrap.Modal(document.getElementById('editVisitModal')).show();
    }

    document.querySelectorAll('.btn-edit-visit').forEach(btn => {
        btn.addEventListener('click', function() {
            openEditVisitModal({
                id: this.dataset.visitId,
                status: this.dataset.visitStatus,
                scheduled_at: this.dataset.visitScheduled,
                client_feedback_strengths: this.dataset.visitStrengths,
                client_feedback_weaknesses: this.dataset.visitWeaknesses,
                notes: this.dataset.visitNotes
            });
        });
    });

    document.getElementById('scheduleVisitForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        data.scheduled_at = document.getElementById('visitScheduledAt').value;

        fetch('{{ route('opportunities.visits.store', $opportunity) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('scheduleVisitModal')).hide();
                location.reload();
            } else {
                alert('Erro ao agendar visita: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erro ao agendar visita');
        });
    });

    document.getElementById('editVisitForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const visitId = document.getElementById('editVisitId').value;
        const formData = {
            status: document.getElementById('editVisitStatus').value,
            scheduled_at: document.getElementById('editVisitScheduledAt').value,
            client_feedback_strengths: document.getElementById('editVisitStrengths').value,
            client_feedback_weaknesses: document.getElementById('editVisitWeaknesses').value,
            notes: document.getElementById('editVisitNotes').value
        };

        fetch('{{ url("visits") }}/' + visitId, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('editVisitModal')).hide();
                location.reload();
            } else {
                alert('Erro ao atualizar visita: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erro ao atualizar visita');
        });
    });

    function openCreateProposalModal(propertyId, propertyTitle, defaultPrice) {
        document.getElementById('proposalPropertyId').value = propertyId;
        document.getElementById('proposalPropertyName').textContent = 'Imóvel: ' + propertyTitle;
        document.getElementById('proposalValue').value = defaultPrice || '';
        document.getElementById('proposalConditions').value = '';
        new bootstrap.Modal(document.getElementById('createProposalModal')).show();
    }

    document.querySelectorAll('.btn-edit-proposal').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('editProposalId').value = this.dataset.proposalId;
            document.getElementById('editProposalValue').value = this.dataset.proposalValue;
            document.getElementById('editProposalConditions').value = this.dataset.proposalConditions || '';
            new bootstrap.Modal(document.getElementById('editProposalModal')).show();
        });
    });

    document.querySelectorAll('.btn-counter-proposal').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('counterProposalParentId').value = this.dataset.proposalId;
            document.getElementById('counterProposalValue').value = this.dataset.proposalValue || '';
            document.getElementById('counterProposalConditions').value = this.dataset.proposalConditions || '';
            new bootstrap.Modal(document.getElementById('counterProposalModal')).show();
        });
    });

    function openRejectProposalModal(proposalId) {
        document.getElementById('rejectProposalId').value = proposalId;
        document.getElementById('rejectionReason').value = '';
        new bootstrap.Modal(document.getElementById('rejectProposalModal')).show();
    }

    function sendProposal(proposalId) {
        fetch('{{ url("proposals") }}/' + proposalId, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ status: 'enviada' })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
            else alert('Erro: ' + (data.message || 'Erro desconhecido'));
        })
        .catch(() => alert('Erro ao enviar proposta'));
    }

    function approveProposal(proposalId) {
        fetch('{{ url("proposals") }}/' + proposalId + '/approve', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
            else alert('Erro: ' + (data.message || 'Erro desconhecido'));
        })
        .catch(() => alert('Erro ao aprovar proposta'));
    }

    document.getElementById('createProposalForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const data = {
            property_id: document.getElementById('proposalPropertyId').value,
            proposed_value: document.getElementById('proposalValue').value,
            conditions: document.getElementById('proposalConditions').value
        };
        fetch('{{ route('opportunities.proposals.store', $opportunity) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('createProposalModal')).hide();
                location.reload();
            } else alert('Erro: ' + (data.message || 'Erro desconhecido'));
        })
        .catch(() => alert('Erro ao criar proposta'));
    });

    document.getElementById('editProposalForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const proposalId = document.getElementById('editProposalId').value;
        const data = {
            proposed_value: document.getElementById('editProposalValue').value,
            conditions: document.getElementById('editProposalConditions').value
        };
        fetch('{{ url("proposals") }}/' + proposalId, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('editProposalModal')).hide();
                location.reload();
            } else alert('Erro: ' + (data.message || 'Erro desconhecido'));
        })
        .catch(() => alert('Erro ao atualizar proposta'));
    });

    document.getElementById('rejectProposalForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const proposalId = document.getElementById('rejectProposalId').value;
        const data = {
            rejection_reason: document.getElementById('rejectionReason').value
        };
        fetch('{{ url("proposals") }}/' + proposalId + '/reject', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('rejectProposalModal')).hide();
                location.reload();
            } else alert('Erro: ' + (data.message || 'Erro desconhecido'));
        })
        .catch(() => alert('Erro ao rejeitar proposta'));
    });

    document.getElementById('counterProposalForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const parentId = document.getElementById('counterProposalParentId').value;
        const data = {
            proposed_value: document.getElementById('counterProposalValue').value,
            conditions: document.getElementById('counterProposalConditions').value
        };
        fetch('{{ url("proposals") }}/' + parentId + '/counter-proposal', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('counterProposalModal')).hide();
                location.reload();
            } else alert('Erro: ' + (data.message || 'Erro desconhecido'));
        })
        .catch(() => alert('Erro ao criar contraproposta'));
    });

    function attachProperty(propertyId) {
        document.getElementById('attachPropertyId').value = propertyId;
        document.getElementById('attachPropertyNotes').value = '';
        new bootstrap.Modal(document.getElementById('attachPropertyModal')).show();
    }

    document.getElementById('attachPropertyForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const propertyId = document.getElementById('attachPropertyId').value;
        const notes = document.getElementById('attachPropertyNotes').value;

        fetch('{{ route('opportunities.attachProperty', $opportunity) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                property_id: propertyId,
                notes: notes
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('attachPropertyModal')).hide();
                location.reload();
            } else {
                alert('Erro ao associar imóvel: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erro ao associar imóvel');
        });
    });

    function detachProperty(propertyId) {
        document.getElementById('detachPropertyId').value = propertyId;
        new bootstrap.Modal(document.getElementById('detachPropertyModal')).show();
    }

    document.getElementById('detachPropertyForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const propertyId = document.getElementById('detachPropertyId').value;

        fetch('{{ route('opportunities.detachProperty', $opportunity) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                property_id: propertyId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('detachPropertyModal')).hide();
                location.reload();
            } else {
                alert('Erro ao desassociar imóvel: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erro ao desassociar imóvel');
        });
    });

    function archiveOpportunity(opportunityId) {
        document.getElementById('archiveOpportunityId').value = opportunityId;
        new bootstrap.Modal(document.getElementById('archiveOpportunityModal')).show();
    }

    document.getElementById('archiveOpportunityForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const opportunityId = document.getElementById('archiveOpportunityId').value;

        fetch(`/opportunities/${opportunityId}/archive`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('archiveOpportunityModal')).hide();
                location.reload();
            } else {
                alert('Erro ao arquivar oportunidade: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erro ao arquivar oportunidade');
        });
    });

    function unarchiveOpportunity(opportunityId) {
        document.getElementById('unarchiveOpportunityId').value = opportunityId;
        new bootstrap.Modal(document.getElementById('unarchiveOpportunityModal')).show();
    }

    document.getElementById('unarchiveOpportunityForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const opportunityId = document.getElementById('unarchiveOpportunityId').value;

        fetch(`/opportunities/${opportunityId}/restore`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('unarchiveOpportunityModal')).hide();
                location.reload();
            } else {
                alert('Erro ao desarquivar oportunidade: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erro ao desarquivar oportunidade');
        });
    });
</script>
@endsection
