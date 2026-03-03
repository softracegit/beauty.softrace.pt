{{-- Associação de serviços ao extra – mesmo aspecto e lógica que "Serviços associados" em agentes. --}}
@php
    $selectedIds = $selectedServiceIds ?? [];
    $serviceCategories = $serviceCategories ?? collect();
    $totalServices = $serviceCategories->sum(fn ($c) => $c->services->count());
    $inputIdPrefix = $inputIdPrefix ?? 'addExtra';
@endphp
<style>
    .extra-services-block .extra-services-all-row { border-bottom: 0px solid rgba(0,0,0,.12); padding-bottom: 0rem; margin-bottom: 0.75rem; }
    .extra-services-block .extra-category-block { border-top: 1px solid rgba(0,0,0,.2); padding-top: 1rem; margin-top: 1rem; }
    .extra-services-block .extra-category-block:first-child { border-top: none; padding-top: 0; margin-top: 0; }
    .extra-services-block .extra-category-header { font-weight: 700; font-size: 1.1rem; }
    .extra-services-block .extra-service-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.5rem 0; border-bottom: 1px solid rgba(0,0,0,.08); }
    .extra-services-block .extra-service-row:last-child { border-bottom: none; }
    .extra-services-block .extra-service-left { flex: 1; min-width: 0; }
    .extra-services-block .extra-service-name { font-weight: 400; font-size: 0.95rem; color: var(--heading-color, #1e293b); margin-bottom: 0.15rem; }
    .extra-services-block .extra-service-duration { font-size: 0.8125rem; color: var(--bs-secondary); }
    .extra-services-block .extra-service-price { font-weight: 600; font-size: 0.95rem; color: var(--heading-color, #1e293b); flex-shrink: 0; }
    .extra-services-block .extra-service-label { cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 1rem; width: 100%; margin-bottom: 0; }
</style>
<div class="uedit-section extra-services-block" data-extra-services-block>
    <div class="uedit-section-title">Serviços associados</div>
    <p class="text-muted small mb-3">Selecione os serviços aos quais este extra pode ser adicionado na marcação. Os serviços estão agrupados por categoria.</p>

    @if($serviceCategories->isEmpty())
        <p class="text-muted mb-0">Nenhuma categoria ou serviço criado ainda. Crie categorias e serviços em <a href="{{ route('services.index') }}">Serviços</a>.</p>
    @else
        <div class="extra-services-all-row d-flex align-items-center gap-2">
            <input type="checkbox" class="form-check-input" id="{{ $inputIdPrefix }}ServicesSelectAll" data-extra-services-select-all aria-label="Selecionar todos os serviços">
            <label class="form-check-label extra-category-header mb-0" for="{{ $inputIdPrefix }}ServicesSelectAll">Todos os serviços</label>
            <span class="badge bg-light text-dark ms-2">{{ $totalServices }}</span>
        </div>

        @foreach($serviceCategories as $category)
            <div class="extra-category-block" data-category-id="{{ $category->id }}">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <input type="checkbox" class="form-check-input extra-category-select-all" id="{{ $inputIdPrefix }}ServicesCategory{{ $category->id }}" data-category-id="{{ $category->id }}" aria-label="Selecionar todos em {{ $category->name }}">
                    <label class="form-check-label extra-category-header mb-0 d-flex align-items-center gap-2" for="{{ $inputIdPrefix }}ServicesCategory{{ $category->id }}">
                        {{ $category->name }}
                    </label>
                    <span class="badge bg-light text-dark ms-2">{{ $category->services->count() }}</span>
                </div>
                <div class="ps-0">
                    @forelse($category->services as $service)
                        <div class="extra-service-row">
                            <div class="form-check flex-grow-1 mb-0">
                                <input class="form-check-input extra-service-cb" type="checkbox" name="service_ids[]" value="{{ $service->id }}" id="{{ $inputIdPrefix }}Service{{ $service->id }}" data-category-id="{{ $category->id }}" {{ in_array($service->id, $selectedIds) ? 'checked' : '' }}>
                                <label class="form-check-label extra-service-label" for="{{ $inputIdPrefix }}Service{{ $service->id }}">
                                    <div class="extra-service-left">
                                        <div class="extra-service-name">{{ $service->name }}</div>
                                    </div>
                                    <span class="extra-service-price">{{ $service->formatted_price }}</span>
                                </label>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0 py-2">Nenhum serviço nesta categoria.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    @endif
</div>

@if(!$serviceCategories->isEmpty())
<script>
(function() {
    function initExtraServicesBlock(block) {
        if (block.hasAttribute('data-extra-services-inited')) return;
        block.setAttribute('data-extra-services-inited', '1');
        var selectAll = block.querySelector('[data-extra-services-select-all]');
        var categoryCheckboxes = block.querySelectorAll('.extra-category-select-all');
        var serviceCheckboxes = block.querySelectorAll('.extra-service-cb');
        function updateCategoryCheckboxes() {
            categoryCheckboxes.forEach(function(catCb) {
                var catId = catCb.getAttribute('data-category-id');
                var inCat = block.querySelectorAll('.extra-service-cb[data-category-id="' + catId + '"]');
                var checked = Array.from(inCat).filter(function(cb) { return cb.checked; }).length;
                catCb.checked = checked === inCat.length && inCat.length > 0;
                catCb.indeterminate = checked > 0 && checked < inCat.length;
            });
        }
        function updateSelectAll() {
            var total = serviceCheckboxes.length;
            var checked = Array.from(serviceCheckboxes).filter(function(cb) { return cb.checked; }).length;
            selectAll.checked = total > 0 && checked === total;
            selectAll.indeterminate = checked > 0 && checked < total;
            updateCategoryCheckboxes();
        }
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                serviceCheckboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
                updateCategoryCheckboxes();
            });
        }
        categoryCheckboxes.forEach(function(catCb) {
            catCb.addEventListener('change', function() {
                var catId = this.getAttribute('data-category-id');
                block.querySelectorAll('.extra-service-cb[data-category-id="' + catId + '"]').forEach(function(cb) { cb.checked = catCb.checked; });
                updateSelectAll();
            });
        });
        serviceCheckboxes.forEach(function(cb) {
            cb.addEventListener('change', updateSelectAll);
        });
        updateSelectAll();
    }
    document.querySelectorAll('[data-extra-services-block]').forEach(initExtraServicesBlock);
})();
</script>
@endif
