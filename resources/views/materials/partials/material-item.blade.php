@php
    $archived = (bool) ($archived ?? false);
    $color = $material->materialCategory?->color
        ?? ($categoryColor ?? ($material->hex ?: '#6c757d'));
@endphp
<div class="service-item-row material-item-row {{ $archived ? 'material-item-row--archived' : '' }}" data-material-id="{{ $material->id }}" @if($archived) data-archived="1" @endif>
    <div class="service-drag-handle" aria-label="Arrastar para reordenar">
        <span class="service-drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
    </div>
    <div class="card service-item {{ $archived ? '' : 'material-item-clickable' }}" style="--service-category-color: {{ $color }};">
        <div class="card-body pe-2">
            <div class="d-flex align-items-center justify-content-between gap-3">
                <div class="min-w-0">
                    <h6 class="mb-0 service-item-name text-truncate">{{ $material->name }}</h6>
                    <div class="d-flex flex-wrap align-items-center gap-2 text-muted small">
                        @if($archived && $material->materialCategory)
                            <span>{{ $material->materialCategory->name }}</span>
                        @endif
                        @if($material->code)
                            <span>{{ $material->code }}</span>
                        @endif
                        @if($material->brand)
                            <span>{{ $material->brand }}</span>
                        @endif
                        @if($material->finish)
                            <span>{{ \App\Models\Material::finishLabel($material->finish) }}</span>
                        @endif
                        @if($material->stock !== null)
                            <span>Stock {{ $material->stock }}</span>
                        @endif
                        @unless($material->is_active)
                            <span class="text-warning">Inactivo</span>
                        @endunless
                        @if($archived)
                            <button type="button" class="btn btn-sm btn-outline-primary material-archive-restore-btn" data-restore-material-id="{{ $material->id }}">Restaurar</button>
                        @endif
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    @if($material->photoUrl())
                        <img src="{{ $material->photoUrl() }}" alt="" class="material-swatch material-swatch--photo" loading="lazy">
                    @else
                        <span class="material-swatch" style="background: {{ $material->hex ?: 'transparent' }}; {{ $material->hex ? '' : 'border-style: dashed;' }}" title="{{ $material->hex ?: 'Sem cor' }}" aria-hidden="true"></span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
