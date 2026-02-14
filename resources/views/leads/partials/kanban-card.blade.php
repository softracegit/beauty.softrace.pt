@php
    $avatarNum = ($lead->agent ? $lead->agent->id : $lead->id) % 9 + 1;
    $avatarSrc = asset("template/img/avatars/avatar-{$avatarNum}.webp");
    $priorityColors = [
        'urgent' => 'danger',
        'high' => 'warning',
        'medium' => 'info',
        'low' => 'secondary',
    ];
    $priorityColor = $priorityColors[$lead->priority] ?? 'secondary';
    $timeAgo = $lead->status_changed_at ? $lead->status_changed_at->diffForHumans() : $lead->created_at->diffForHumans();
    // Garantir que usamos o relacionamento polimórfico, não o atributo 'notes' (texto)
    $notesCount = $lead->relationLoaded('notes') 
        ? $lead->getRelation('notes')->count() 
        : $lead->notes()->count();
@endphp
<div class="card border kanban-card mb-3" data-lead-id="{{ $lead->id }}">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="flex-grow-1">
                <h6 class="mb-1 fw-semibold">
                    <a href="{{ route('leads.show', $lead) }}" class="text-body">{{ $lead->name }}</a>
                </h6>
                <span class="badge bg-{{ $priorityColor }}-subtle text-{{ $priorityColor }} mb-1">
                    {{ \App\Models\Lead::priorities()[$lead->priority] ?? $lead->priority }}
                </span>
            </div>
            <div class="dropdown">
                <button class="btn btn-sm p-0 text-body" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('leads.show', $lead) }}"><i class="ph ph-eye me-2"></i> Ver</a></li>
                    <li><a class="dropdown-item" href="{{ route('leads.edit', $lead) }}"><i class="ph ph-pencil-simple me-2"></i> Editar</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="openNoteModal({{ $lead->id }})"><i class="ph ph-note me-2"></i> Adicionar Nota</a></li>
                </ul>
            </div>
        </div>
        
        <div class="mb-2">
            <small class="text-muted d-block mb-1">
                <i class="ph-duotone ph-buildings me-1"></i> {{ \App\Models\Lead::types()[$lead->type] ?? $lead->type }}
            </small>
            <small class="text-muted d-block mb-1">
                <i class="ph-duotone ph-map-pin me-1"></i> {{ \App\Models\Lead::origins()[$lead->origin] ?? $lead->origin }}
            </small>
            @if($lead->property_reference)
            <small class="text-muted d-block mb-1">
                <i class="ph-duotone ph-house me-1"></i> Ref: {{ $lead->property_reference }}
            </small>
            @endif
        </div>

        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex align-items-center">
                @if($lead->agent)
                    <img src="{{ $avatarSrc }}" alt="{{ $lead->agent->name }}" class="avatar-xs rounded-circle me-1" title="{{ $lead->agent->name }}">
                @else
                    <div class="avatar-xs rounded-circle bg-secondary-subtle d-flex align-items-center justify-content-center me-1" title="Sem responsável">
                        <i class="ph-duotone ph-user text-secondary"></i>
                    </div>
                @endif
                <small class="text-muted">{{ $timeAgo }}</small>
            </div>
            @if($notesCount > 0)
            <small class="text-muted">
                <i class="ph-duotone ph-note me-1"></i> {{ $notesCount }}
            </small>
            @endif
        </div>

        @if($lead->email || $lead->phone)
        <div class="border-top pt-2 mt-2">
            <div class="d-flex gap-2 flex-wrap">
                @if($lead->email)
                <a href="mailto:{{ $lead->email }}" class="text-muted" title="{{ $lead->email }}">
                    <i class="ph-duotone ph-envelope"></i>
                </a>
                @endif
                @if($lead->phone)
                <a href="tel:{{ $lead->phone }}" class="text-muted" title="{{ $lead->phone }}">
                    <i class="ph-duotone ph-phone"></i>
                </a>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
