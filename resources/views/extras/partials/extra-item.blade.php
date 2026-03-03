<div class="card service-item mb-2 extra-item-row" data-extra-id="{{ $extra->id }}" style="--service-category-color: {{ $extra->extraCategory->color ?? '#6c757d' }};">
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
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-icon btn-sm" type="button" data-bs-toggle="dropdown" aria-label="Opções"><i class="ph ph-dots-three-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item open-edit-extra-modal" href="#" data-extra-id="{{ $extra->id }}"><i class="ph ph-pencil-simple me-2"></i>Editar</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="{{ route('extras.destroy', $extra) }}" method="POST" class="d-inline form-destroy-extra" data-extra-name="{{ $extra->name }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="dropdown-item text-danger"><i class="ph ph-trash me-2"></i>Eliminar</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
