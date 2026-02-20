@forelse($services as $service)
    <div class="service-item-row" data-service-id="{{ $service->id }}">
        <div class="service-drag-handle" aria-label="Arrastar para reordenar">
            <span class="service-drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        </div>
        <div class="card service-item" style="--service-category-color: {{ isset($category) ? $category->color : '#6c757d' }};">
        <div class="card-body d-flex justify-content-between align-items-center gap-3">
            <div class="service-item-left">
                <h6 class="mb-0 service-item-name">{{ $service->name }}</h6>
                @if($service->description)
                    <p class="text-muted small mb-1">{{ Str::limit($service->description, 100) }}</p>
                @endif
                <div class="d-flex flex-wrap gap-3 text-muted small service-item-duration">
                    <span><i class="ph ph-clock me-1"></i>{{ $service->formatted_duration }}</span>
                    @if($service->promo_price)
                        <span class="text-success"><i class="ph ph-tag me-1"></i>{{ $service->formatted_promo_price }}</span>
                    @endif
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0 service-item-right">
                <span class="service-item-price">{{ $service->formatted_price }}</span>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Opções">
                        <i class="ph ph-dots-three-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item edit-service-btn" href="#" data-service-id="{{ $service->id }}">
                                <i class="ph ph-pencil-simple me-2"></i>Editar
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger delete-service-btn" href="#" data-service-id="{{ $service->id }}">
                                <i class="ph ph-trash me-2"></i>Eliminar
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        </div>
    </div>
@empty
    <div class="service-item-row service-empty-placeholder">
        <div class="service-drag-handle" style="visibility: hidden;" aria-hidden="true"></div>
        <div class="card service-item">
            <div class="card-body">
                <p class="text-muted small mb-0">Nenhum serviço nesta categoria.</p>
            </div>
        </div>
    </div>
@endforelse
