<div class="border rounded p-3 mb-3 {{ $proposal->isCounterProposal() ? 'ms-4 border-start border-3 border-info' : '' }}" id="proposal-{{ $proposal->id }}">
    <div class="d-flex gap-3">
        <div class="flex-shrink-0" style="width: 60px;">
            @if($proposal->property->mainImage)
                <img src="{{ asset('storage/' . $proposal->property->mainImage->path) }}" alt="{{ $proposal->property->title }}" class="img-fluid rounded" style="height: 55px; width: 100%; object-fit: cover;">
            @else
                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 55px;">
                    <i class="ph-duotone ph-image text-muted"></i>
                </div>
            @endif
        </div>
        <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <div>
                    <a href="{{ route('properties.show', $proposal->property) }}" class="fw-semibold text-body">{{ $proposal->property->title }}</a>
                    @if($proposal->isCounterProposal())
                        <span class="badge bg-info-subtle text-info ms-1">Contraproposta</span>
                    @endif
                </div>
                <span class="badge bg-{{ $proposal->status_color }}-subtle text-{{ $proposal->status_color }}">
                    {{ \App\Models\Proposal::statuses()[$proposal->status] ?? $proposal->status }}
                </span>
            </div>
            <div class="mb-1">
                <strong class="text-primary">{{ $proposal->formatted_value }}</strong>
                <small class="text-muted ms-2">{{ $proposal->created_at->format('d/m/Y H:i') }}</small>
            </div>
            @if($proposal->conditions)
                <p class="mb-2 small text-muted">{{ \Illuminate\Support\Str::limit($proposal->conditions, 100) }}</p>
            @endif
            @if($proposal->status === \App\Models\Proposal::STATUS_REJEITADA && $proposal->rejection_reason)
                <p class="mb-2 small text-danger"><strong>Motivo da rejeição:</strong> {{ $proposal->rejection_reason }}</p>
            @endif
            @if(empty($readOnly))
            <div class="d-flex flex-wrap gap-1">
                @if(in_array($proposal->status, [\App\Models\Proposal::STATUS_RASCUNHO]))
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-proposal"
                        data-proposal-id="{{ $proposal->id }}"
                        data-proposal-value="{{ $proposal->proposed_value }}"
                        data-proposal-conditions="{{ e($proposal->conditions ?? '') }}">
                        <i class="ph ph-pencil-simple me-1"></i> Editar
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="sendProposal({{ $proposal->id }})">
                        <i class="ph-duotone ph-paper-plane-tilt me-1"></i> Enviar
                    </button>
                @endif
                @if($proposal->status === \App\Models\Proposal::STATUS_ENVIADA)
                    <button type="button" class="btn btn-sm btn-success" onclick="approveProposal({{ $proposal->id }})">
                        <i class="ph ph-check me-1"></i> Aprovar
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="openRejectProposalModal({{ $proposal->id }})">
                        <i class="ph ph-x me-1"></i> Rejeitar
                    </button>
                @endif
                @if($proposal->status === \App\Models\Proposal::STATUS_REJEITADA)
                    <button type="button" class="btn btn-sm btn-outline-info btn-counter-proposal"
                        data-proposal-id="{{ $proposal->id }}"
                        data-proposal-value="{{ $proposal->proposed_value }}"
                        data-proposal-conditions="{{ e($proposal->conditions ?? '') }}">
                        <i class="ph ph-arrow-clockwise me-1"></i> Contraproposta
                    </button>
                @endif
            </div>
            @endif
        </div>
    </div>
    @if($proposal->counterProposals->count() > 0)
        <div class="mt-3 pt-3 border-top">
            <small class="text-muted d-block mb-2">Contrapropostas:</small>
            @foreach($proposal->counterProposals as $counter)
                @include('opportunities.partials.proposal-item', ['proposal' => $counter, 'readOnly' => $readOnly ?? false])
            @endforeach
        </div>
    @endif
</div>
