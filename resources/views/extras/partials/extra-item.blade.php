<div class="card service-item mb-2 extra-item-row extra-item-clickable" data-extra-id="{{ $extra->id }}" style="--service-category-color: {{ $extra->extraCategory->color ?? '#6c757d' }};">
    <div class="card-body d-flex justify-content-between align-items-center gap-3">
        <div class="service-item-left">
            <h6 class="mb-0 service-item-name">{{ $extra->name }}</h6>
            @if($extra->description)
                <p class="text-muted small mb-1">{{ Str::limit($extra->description, 80) }}</p>
            @endif
            <div class="d-flex flex-wrap gap-3 text-muted small">
                <span><i class="ph ph-clock me-1"></i>{{ $extra->formatted_duration }}</span>
                <span>{{ $extra->formatted_price }}</span>
                @if($extra->services->count() > 0)
                    <span><i class="ph ph-package me-1"></i>{{ $extra->services->count() }} serviço(s)</span>
                @endif
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="service-item-price">{{ $extra->formatted_price }}</span>
        </div>
    </div>
</div>
