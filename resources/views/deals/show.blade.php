@extends('partials.layouts.main')
@section('title', 'Negócio ' . $deal->reference . ' | Beauty CRM')
@section('content')

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@php
    $statusColor = $deal->status_color;
@endphp

<div class="row">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">
                        <i class="ph-duotone ph-trophy me-2 text-success"></i>{{ $deal->reference }}
                        <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} ms-2">{{ \App\Models\Deal::statuses()[$deal->status] ?? $deal->status }}</span>
                    </h5>
                    <small class="text-muted">{{ $deal->transaction_type }} · {{ $deal->closed_at->format('d/m/Y H:i') }}</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('opportunities.show', $deal->opportunity) }}" class="btn btn-outline-primary btn-sm">
                        <i class="ph ph-arrow-left me-1"></i> Ver Oportunidade
                    </a>
                    @if($deal->canBeReverted())
                    <button type="button" class="btn btn-warning btn-sm" onclick="openReabrirDealModal()">
                        <i class="ph ph-arrow-clockwise me-1"></i> Reabrir
                    </button>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label text-muted small text-uppercase">Valor Final</label>
                        <p class="mb-0 fs-4 fw-bold text-success">{{ $deal->formatted_final_price }}</p>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label text-muted small text-uppercase">Imóvel</label>
                        <p class="mb-0">
                            <a href="{{ route('properties.show', $deal->property) }}" class="text-body fw-medium">{{ $deal->property_title }}</a>
                        </p>
                        <small class="text-muted">{{ $deal->property_reference }}</small>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label text-muted small text-uppercase">Cliente</label>
                        <p class="mb-0">
                            <a href="{{ route('clientes.show', $deal->client) }}" class="text-body fw-medium">{{ $deal->client->name }}</a>
                        </p>
                        <small class="text-muted">{{ $deal->client->email }}</small>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label text-muted small text-uppercase">Fechado por</label>
                        <p class="mb-0">{{ $deal->closedBy->name ?? 'N/A' }}</p>
                        <small class="text-muted">{{ $deal->closed_at->format('d/m/Y H:i') }}</small>
                    </div>
                    @if($deal->status === \App\Models\Deal::STATUS_REVERTIDO)
                    <div class="col-12 col-md-4">
                        <label class="form-label text-muted small text-uppercase">Revertido por</label>
                        <p class="mb-0">{{ $deal->revertedBy->name ?? 'N/A' }}</p>
                        <small class="text-muted">{{ $deal->reverted_at?->format('d/m/Y H:i') }}</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-muted small text-uppercase">Motivo da reversão</label>
                        <p class="mb-0 text-muted">{{ $deal->reversion_reason }}</p>
                    </div>
                    @endif
                </div>

                @if($deal->agentCommissions->count() > 0)
                <hr class="my-4">
                <h6 class="mb-3">Comissões dos Agentes</h6>
                <div class="row g-2">
                    @foreach($deal->agentCommissions as $commission)
                    <div class="col-md-6">
                        <div class="d-flex align-items-center gap-2 p-3 bg-light rounded">
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

                @if($deal->notes)
                <hr class="my-4">
                <label class="form-label text-muted small text-uppercase">Notas</label>
                <p class="mb-0">{{ $deal->notes }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

@if($deal->canBeReverted())
<!-- Modal para Reabrir negócio -->
<div class="modal fade" id="reabrirDealModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title text-warning"><i class="ph ph-arrow-clockwise me-2"></i>Reabrir Negócio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="reabrirDealForm">
                <div class="modal-body">
                    <p class="mb-3">Ao reabrir o negócio <strong>{{ $deal->reference }}</strong>, a oportunidade voltará ao estado "Proposta aceite" e o imóvel ficará novamente disponível. O histórico do negócio será mantido como "Revertido".</p>
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

@endsection

@section('js')
@if($deal->canBeReverted())
<script>
    function openReabrirDealModal() {
        new bootstrap.Modal(document.getElementById('reabrirDealModal')).show();
    }

    document.getElementById('reabrirDealForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const data = {
            reversion_reason: document.getElementById('reabrirReason').value
        };
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A processar...';
        fetch('{{ route('deals.revert', $deal) }}', {
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
                window.location.href = '{{ route('opportunities.show', $deal->opportunity) }}';
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
</script>
@endif
@endsection
