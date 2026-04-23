@forelse($services as $service)
    @php
        $hasOpts = $service->relationLoaded('options') && $service->options->isNotEmpty();
        $fromOnline = $hasOpts ? $service->options->min('online_price') : null;
    @endphp
    <div class="service-item-row {{ $hasOpts ? 'service-item-row--has-options' : '' }}" data-service-id="{{ $service->id }}">
        <div class="service-drag-handle" aria-label="Arrastar para reordenar">
            <span class="service-drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        </div>
        <div class="card service-item service-item-clickable" style="--service-category-color: {{ isset($category) ? $category->color : '#6c757d' }};">
        <div class="card-body d-flex justify-content-between {{ $hasOpts ? 'align-items-start' : 'align-items-center' }} gap-3 py-3 pe-2">
            <div class="service-item-left">
                <h6 class="mb-0 service-item-name">{{ $service->name }}</h6>
                @if($service->description)
                    <p class="text-muted small mb-1">{{ Str::limit($service->description, 100) }}</p>
                @endif
                <div class="d-flex flex-wrap gap-3 text-muted small service-item-duration">
                    @if($hasOpts)
                        <span class="text-success" title="Menor preço online entre opções"><i class="ph ph-globe me-1"></i>Desde {{ number_format((float) $fromOnline, 2, ',', '.') }}&nbsp;€</span>
                        <span><i class="ph ph-list-checks me-1"></i>{{ $service->options->count() }} opção(ões)</span>
                    @else
                        <span><i class="ph ph-clock me-1"></i>{{ $service->formatted_duration }}</span>
                        @if($service->online_price)
                            <span class="text-success" title="Preço online"><i class="ph ph-globe me-1"></i>{{ $service->formatted_online_price }}</span>
                        @endif
                    @endif
                    @if(($service->extras_count ?? $service->extras->count() ?? 0) > 0)
                        <span><i class="ph ph-package me-1"></i>{{ $service->extras_count ?? $service->extras->count() }} extra(s)</span>
                    @endif
                </div>
                @if($hasOpts)
                    <ul class="list-unstyled small text-muted mb-0 mt-1 ps-0 service-option-chips">
                        @foreach($service->options as $opt)
                            <li class="mb-1">{{ $opt->name }} · {{ $opt->formatted_duration }} · {{ $opt->formatted_online_price }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="d-flex {{ $hasOpts ? 'align-items-start' : 'align-items-center' }} gap-2 flex-shrink-0 service-item-right">
                @if($hasOpts)
                    <span class="service-item-price" title="Menor preço online"><span class="small fw-normal">desde</span> {{ number_format((float) $fromOnline, 2, ',', '.') }}&nbsp;€</span>
                @else
                    <span class="service-item-price" title="Preço normal">{{ $service->formatted_price }}</span>
                @endif
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
