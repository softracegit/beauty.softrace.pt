{{-- Associação de serviços ao extra — mesmo aspeto que "Membros da Equipa" / extras nos serviços. --}}
@php
    $selectedIds = $selectedServiceIds ?? [];
    $serviceCategories = $serviceCategories ?? collect();
    $totalServices = $serviceCategories->sum(fn ($c) => $c->services->count());
    $inputIdPrefix = $inputIdPrefix ?? 'addExtra';
    $allSelected = $totalServices > 0 && collect($serviceCategories)->flatMap(fn ($c) => $c->services->pluck('id'))->every(fn ($id) => in_array($id, $selectedIds));
@endphp
<div class="mb-0 extra-services-block" data-extra-services-block>
    <label class="form-label">Serviços associados</label>
    @if($serviceCategories->isEmpty())
        <p class="text-muted small mb-0">Nenhuma categoria ou serviço criado ainda. Crie categorias e serviços em <a href="{{ route('services.index') }}">Serviços</a>.</p>
    @else
        <div class="border rounded p-3" style="max-height: 240px; overflow-y: auto;">
            <div class="form-check mb-2 pb-2 border-bottom">
                <input
                    type="checkbox"
                    class="form-check-input"
                    id="{{ $inputIdPrefix }}ServicesSelectAll"
                    data-extra-services-select-all
                    aria-label="Selecionar todos os serviços"
                    {{ $allSelected ? 'checked' : '' }}
                >
                <label class="form-check-label fw-semibold" for="{{ $inputIdPrefix }}ServicesSelectAll">
                    Todos os serviços
                    <span class="badge bg-secondary ms-2">{{ $totalServices }}</span>
                </label>
            </div>

            @foreach($serviceCategories as $category)
                @if($category->services->isEmpty())
                    @continue
                @endif
                <div class="extra-category-block mb-3" data-category-id="{{ $category->id }}">
                    <div class="form-check mb-2 mt-2">
                        <input
                            type="checkbox"
                            class="form-check-input extra-category-select-all"
                            id="{{ $inputIdPrefix }}ServicesCategory{{ $category->id }}"
                            data-category-id="{{ $category->id }}"
                            aria-label="Selecionar todos em {{ $category->name }}"
                            {{ $category->services->every(fn ($s) => in_array($s->id, $selectedIds)) ? 'checked' : '' }}
                        >
                        <label class="form-check-label fw-semibold" for="{{ $inputIdPrefix }}ServicesCategory{{ $category->id }}">
                            {{ $category->name }}
                            <span class="badge bg-secondary ms-2">{{ $category->services->count() }}</span>
                        </label>
                    </div>
                    @foreach($category->services as $service)
                        <div class="form-check ms-3 mb-2">
                            <input
                                class="form-check-input extra-service-cb"
                                type="checkbox"
                                name="service_ids[]"
                                value="{{ $service->id }}"
                                id="{{ $inputIdPrefix }}Service{{ $service->id }}"
                                data-category-id="{{ $category->id }}"
                                {{ in_array($service->id, $selectedIds) ? 'checked' : '' }}
                            >
                            <label class="form-check-label d-flex justify-content-between align-items-center gap-2 w-100" for="{{ $inputIdPrefix }}Service{{ $service->id }}">
                                <span>{{ $service->name }}</span>
                                <span class="text-muted small">{{ $service->formatted_price }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</div>

@if(!$serviceCategories->isEmpty())
<script>
(function() {
    function syncExtraServicesSelectState(block) {
        if (!block) return;
        var selectAll = block.querySelector('[data-extra-services-select-all]');
        var categoryCheckboxes = block.querySelectorAll('.extra-category-select-all');
        var serviceCheckboxes = block.querySelectorAll('.extra-service-cb');
        categoryCheckboxes.forEach(function(catCb) {
            var catId = catCb.getAttribute('data-category-id');
            var inCat = block.querySelectorAll('.extra-service-cb[data-category-id="' + catId + '"]');
            var checked = Array.from(inCat).filter(function(cb) { return cb.checked; }).length;
            catCb.checked = checked === inCat.length && inCat.length > 0;
            catCb.indeterminate = checked > 0 && checked < inCat.length;
        });
        if (selectAll) {
            var total = serviceCheckboxes.length;
            var checked = Array.from(serviceCheckboxes).filter(function(cb) { return cb.checked; }).length;
            selectAll.checked = total > 0 && checked === total;
            selectAll.indeterminate = checked > 0 && checked < total;
        }
    }

    window.syncExtraServicesSelectState = function(root) {
        (root || document).querySelectorAll('[data-extra-services-block]').forEach(syncExtraServicesSelectState);
    };

    function initExtraServicesBlock(block) {
        if (block.hasAttribute('data-extra-services-inited')) return;
        block.setAttribute('data-extra-services-inited', '1');
        var selectAll = block.querySelector('[data-extra-services-select-all]');
        var categoryCheckboxes = block.querySelectorAll('.extra-category-select-all');
        var serviceCheckboxes = block.querySelectorAll('.extra-service-cb');

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                serviceCheckboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
                selectAll.indeterminate = false;
                syncExtraServicesSelectState(block);
            });
        }
        categoryCheckboxes.forEach(function(catCb) {
            catCb.addEventListener('change', function() {
                var catId = this.getAttribute('data-category-id');
                block.querySelectorAll('.extra-service-cb[data-category-id="' + catId + '"]').forEach(function(cb) {
                    cb.checked = catCb.checked;
                });
                syncExtraServicesSelectState(block);
            });
        });
        serviceCheckboxes.forEach(function(cb) {
            cb.addEventListener('change', function() {
                syncExtraServicesSelectState(block);
            });
        });
        syncExtraServicesSelectState(block);
    }
    document.querySelectorAll('[data-extra-services-block]').forEach(initExtraServicesBlock);
})();
</script>
@endif
