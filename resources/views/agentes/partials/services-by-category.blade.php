{{-- Listagem de serviços por categoria com checkboxes para associar ao membro. --}}
@php
    $selectedIds = $selectedServiceIds ?? [];
    $totalServices = $categories->sum(fn ($c) => $c->services->count());
@endphp
<style>
    .member-services-block .member-services-all-row { border-bottom: 1px solid rgba(0,0,0,.12); padding-bottom: 0.75rem; margin-bottom: 0.75rem; }
    .member-services-block .member-category-block { border-top: 1px solid rgba(0,0,0,.2); padding-top: 1rem; margin-top: 1rem; }
    .member-services-block .member-category-block:first-child { border-top: none; padding-top: 0; margin-top: 0; }
    .member-services-block .member-category-header { font-weight: 700; font-size: 1.1rem; }
    .member-services-block .member-service-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.5rem 0; border-bottom: 1px solid rgba(0,0,0,.08); }
    .member-services-block .member-service-row:last-child { border-bottom: none; }
    .member-services-block .member-service-left { flex: 1; min-width: 0; }
    .member-services-block .member-service-name { font-weight: 400; font-size: 0.95rem; color: var(--heading-color, #1e293b); margin-bottom: 0.15rem; }
    .member-services-block .member-service-duration { font-size: 0.8125rem; color: var(--bs-secondary); }
    .member-services-block .member-service-price { font-weight: 600; font-size: 0.95rem; color: var(--heading-color, #1e293b); flex-shrink: 0; }
    .member-services-block .member-service-label { cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 1rem; width: 100%; margin-bottom: 0; }
</style>
<div class="uedit-section member-services-block" data-member-services-block>
    <div class="uedit-section-title">Serviços associados</div>
    <p class="text-muted small mb-3">Selecione os serviços que este membro pode prestar. Os serviços estão agrupados por categoria.</p>

    @if($categories->isEmpty())
        <p class="text-muted mb-0">Nenhuma categoria ou serviço criado ainda. Crie categorias e serviços em <a href="{{ route('services.index') }}">Serviços</a>.</p>
    @else
        {{-- Todos os serviços --}}
        <div class="member-services-all-row d-flex align-items-center gap-2">
            <input type="checkbox" class="form-check-input" id="memberServicesSelectAll" data-member-services-select-all aria-label="Selecionar todos os serviços">
            <label class="form-check-label member-category-header mb-0" for="memberServicesSelectAll">Todos os serviços</label>
            <span class="badge bg-light text-dark ms-2">{{ $totalServices }}</span>
        </div>

        @foreach($categories as $category)
            <div class="member-category-block" data-category-id="{{ $category->id }}">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <input type="checkbox" class="form-check-input member-category-select-all" id="memberServicesCategory{{ $category->id }}" data-category-id="{{ $category->id }}" aria-label="Selecionar todos em {{ $category->name }}">
                    <label class="form-check-label member-category-header mb-0 d-flex align-items-center gap-2" for="memberServicesCategory{{ $category->id }}">
                        {{ $category->name }}
                    </label>
                    <span class="badge bg-light text-dark ms-2">{{ $category->services->count() }}</span>
                </div>
                <div class="ps-0">
                    @forelse($category->services as $service)
                        <div class="member-service-row">
                            <div class="form-check flex-grow-1 mb-0">
                                <input class="form-check-input member-service-cb" type="checkbox" name="service_ids[]" value="{{ $service->id }}" id="memberService{{ $service->id }}" data-category-id="{{ $category->id }}" {{ in_array($service->id, $selectedIds) ? 'checked' : '' }}>
                                <label class="form-check-label member-service-label" for="memberService{{ $service->id }}">
                                    <div class="member-service-left">
                                        <div class="member-service-name">{{ $service->name }}</div>
                                    </div>
                                    <span class="member-service-price">{{ $service->formatted_price }}</span>
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

@if(!$categories->isEmpty())
<script>
(function() {
    var block = document.querySelector('[data-member-services-block]');
    if (!block) return;
    var selectAll = block.querySelector('[data-member-services-select-all]');
    var categoryCheckboxes = block.querySelectorAll('.member-category-select-all');
    var serviceCheckboxes = block.querySelectorAll('.member-service-cb');

    function updateCategoryCheckboxes() {
        categoryCheckboxes.forEach(function(catCb) {
            var catId = catCb.getAttribute('data-category-id');
            var inCat = block.querySelectorAll('.member-service-cb[data-category-id="' + catId + '"]');
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
            block.querySelectorAll('.member-service-cb[data-category-id="' + catId + '"]').forEach(function(cb) { cb.checked = catCb.checked; });
            updateSelectAll();
        });
    });
    serviceCheckboxes.forEach(function(cb) {
        cb.addEventListener('change', updateSelectAll);
    });
    updateSelectAll();
})();
</script>
@endif
