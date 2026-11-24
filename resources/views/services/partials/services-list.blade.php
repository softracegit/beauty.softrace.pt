@forelse($services as $service)
    @php
        $hasOpts = $service->relationLoaded('options') && $service->options->isNotEmpty();
        // "Desde" só considera as variantes de escolha única (as adicionais somam-se a essas)
        $choiceOpts = $hasOpts ? $service->options->reject(fn ($o) => $o->isAddon()) : collect();
        if ($hasOpts && $choiceOpts->isEmpty()) {
            $choiceOpts = $service->options;
        }
        $fromOnline = $hasOpts ? $choiceOpts->min('online_price') : null;
        $fromDurationLabel = $hasOpts
            ? optional($choiceOpts->sortBy('duration')->first())->formatted_duration
            : null;
        $extrasN = (int) ($service->extras_count ?? ($service->relationLoaded('extras') ? $service->extras->count() : 0));
        $futureN = (int) ($service->future_marcacoes_count ?? 0);
    @endphp
    <div class="service-item-row {{ $hasOpts ? 'service-item-row--has-options' : '' }}" data-service-id="{{ $service->id }}" data-hidden-from-booking="{{ $service->hidden_from_booking ? '1' : '0' }}">
        <div class="service-drag-handle" aria-label="Arrastar para reordenar">
            <span class="service-drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        </div>
        <div class="card service-item service-item-clickable" style="--service-category-color: {{ isset($category) ? $category->color : '#6c757d' }};">
        @if($hasOpts)
        @php $variantsN = $service->options->count(); @endphp
        <div class="card-body service-item-body--has-options py-3 pe-2">
            <div class="service-item-header">
                <div class="min-w-0">
                    <h6 class="mb-0 service-item-name">{{ $service->name }}@if($service->hidden_from_booking)<i class="ph ph-eye-slash text-muted small ms-1" title="Oculto no booking" aria-label="Oculto no booking"></i>@endif</h6>
                    @include('partials.catalog-item-copy', [
                        'shortDescription' => $service->short_description,
                    ])
                    <div class="service-item-badges">
                        <span class="badge text-bg-light">{{ $variantsN }} variante{{ $variantsN === 1 ? '' : 's' }}</span>
                        @if($extrasN > 0)
                            <span class="badge text-bg-light">{{ $extrasN }} extra(s)</span>
                        @endif
                        @include('services.partials.service-future-marcacoes-badge', ['count' => $futureN])
                        @include('services.partials.service-members-avatars', ['service' => $service])
                    </div>
                </div>
                <span class="service-item-desde-duration" title="Menor duração">
                    <span class="small fw-normal">Desde</span> {{ $fromDurationLabel }}
                </span>
                <span class="service-item-price" title="Menor preço online"><span class="small fw-normal">Desde</span> {{ number_format((float) $fromOnline, 2, ',', '.') }}&nbsp;€</span>
            </div>
            <div class="service-item-variants">
                @foreach($service->options as $opt)
                    <div class="service-item-variant-row">
                        <span class="service-item-variant-name">{{ $opt->name }}@if($opt->isAddon()) <span class="badge text-bg-light fw-normal ms-1">Adicional</span>@endif</span>
                        <span class="service-item-variant-duration">{{ $opt->isAddon() ? '+' : '' }}{{ $opt->formatted_duration }}</span>
                        <span class="service-item-variant-price">{{ $opt->isAddon() ? '+' : '' }}{{ $opt->formatted_price }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        @else
        <div class="card-body service-item-body--simple py-3 pe-2">
            <div class="service-item-header">
                <div class="min-w-0">
                    <h6 class="mb-0 service-item-name">{{ $service->name }}@if($service->hidden_from_booking)<i class="ph ph-eye-slash text-muted small ms-1" title="Oculto no booking" aria-label="Oculto no booking"></i>@endif</h6>
                    @include('partials.catalog-item-copy', [
                        'shortDescription' => $service->short_description,
                    ])
                    <div class="service-item-badges">
                        @if($extrasN > 0)
                            <span class="badge text-bg-light">{{ $extrasN }} extra(s)</span>
                        @endif
                        @include('services.partials.service-future-marcacoes-badge', ['count' => $futureN])
                        @include('services.partials.service-members-avatars', ['service' => $service])
                    </div>
                </div>
                <span class="service-item-desde-duration" title="Duração">{{ $service->formatted_duration }}</span>
                <span class="service-item-price" title="Preço normal">{{ $service->formatted_price }}</span>
            </div>
        </div>
        @endif
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
