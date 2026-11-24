@php
    $archived = (bool) ($archived ?? false);
    $servicesN = (int) ($extra->services_count ?? ($extra->relationLoaded('services') ? $extra->services->count() : 0));
    $color = $extra->extraCategory->color ?? ($categoryColor ?? '#6c757d');
@endphp
<div class="service-item-row extra-item-row {{ $archived ? 'extra-item-row--archived' : '' }}" data-extra-id="{{ $extra->id }}" @if($archived) data-archived="1" @endif>
    <div class="service-drag-handle" aria-label="Arrastar para reordenar">
        <span class="service-drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
    </div>
    <div class="card service-item {{ $archived ? '' : 'service-item-clickable extra-item-clickable' }}" style="--service-category-color: {{ $color }};">
        <div class="card-body service-item-body--simple py-3 pe-2">
            <div class="service-item-header">
                <div class="min-w-0">
                    <h6 class="mb-0 service-item-name">{{ $extra->name }}</h6>
                    @include('partials.catalog-item-copy', [
                        'shortDescription' => $extra->short_description,
                    ])
                    @if($archived && $extra->extraCategory)
                        <div class="text-muted small">{{ $extra->extraCategory->name }}</div>
                    @endif
                    <div class="service-item-badges">
                        @if($servicesN > 0)
                            <span class="badge text-bg-light">{{ $servicesN }} serviço(s)</span>
                        @endif
                        @include('extras.partials.extra-members-avatars', ['extra' => $extra])
                        @if($archived)
                            <button type="button" class="btn btn-sm btn-outline-primary extra-archive-restore-btn" data-restore-extra-id="{{ $extra->id }}">Restaurar</button>
                        @endif
                    </div>
                </div>
                <span class="service-item-desde-duration" title="Duração">{{ $extra->formatted_duration }}</span>
                <span class="service-item-price" title="Preço">{{ $extra->formatted_price }}</span>
            </div>
        </div>
    </div>
</div>
