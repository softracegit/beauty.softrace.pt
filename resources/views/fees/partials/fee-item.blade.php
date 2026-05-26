<div class="card service-item mb-2 fee-item-row fee-item-clickable" data-fee-id="{{ $fee->id }}" style="--service-category-color: #6c757d;">
    <div class="card-body d-flex justify-content-between align-items-center gap-3">
        <div class="service-item-left">
            <h6 class="mb-0 service-item-name">{{ $fee->name }}</h6>
            <div class="d-flex flex-wrap gap-3 text-muted small mt-1">
                @if(($fee->services_count ?? $fee->services->count()) > 0)
                    <span><i class="ph ph-scissors me-1"></i>{{ $fee->services_count ?? $fee->services->count() }} serviço(s)</span>
                @else
                    <span class="text-muted">Sem serviços associados</span>
                @endif
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="service-item-price">{{ $fee->formatted_price }}</span>
        </div>
    </div>
</div>
