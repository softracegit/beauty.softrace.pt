{{-- Associação de extras ao serviço – mesmo aspecto e lógica que "Serviços associados" nos extras. --}}
@php
    $extraCategories = $extraCategories ?? collect();
    $selectedExtraIds = $selectedExtraIds ?? [];
    $totalExtras = $extraCategories->sum(fn ($c) => $c->extras->count());
    $inputIdPrefix = $inputIdPrefix ?? 'service';
@endphp
<style>
    .service-extras-block .service-extras-all-row { border-bottom: 0px solid rgba(0,0,0,.12); padding-bottom: 0rem; margin-bottom: 0.75rem; }
    .service-extras-block .service-extra-category-block { border-top: 1px solid rgba(0,0,0,.2); padding-top: 1rem; margin-top: 1rem; }
    .service-extras-block .service-extra-category-block:first-child { border-top: none; padding-top: 0; margin-top: 0; }
    .service-extras-block .service-extra-category-header { font-weight: 700; font-size: 1.1rem; }
    .service-extras-block .service-extra-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.5rem 0; border-bottom: 1px solid rgba(0,0,0,.08); }
    .service-extras-block .service-extra-row:last-child { border-bottom: none; }
    .service-extras-block .service-extra-left { flex: 1; min-width: 0; }
    .service-extras-block .service-extra-name { font-weight: 400; font-size: 0.95rem; color: var(--heading-color, #1e293b); margin-bottom: 0.15rem; }
    .service-extras-block .service-extra-price { font-weight: 600; font-size: 0.95rem; color: var(--heading-color, #1e293b); flex-shrink: 0; }
    .service-extras-block .service-extra-label { cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 1rem; width: 100%; margin-bottom: 0; }
</style>
<div class="uedit-section service-extras-block" data-service-extras-block>
    <div class="uedit-section-title">Extras associados</div>
    <p class="text-muted small mb-3">Selecione os extras que podem ser adicionados a este serviço na marcação. Os extras estão agrupados por categoria.</p>

    @if($extraCategories->isEmpty())
        <p class="text-muted mb-0">Nenhuma categoria ou extra criado ainda. Crie extras em <a href="{{ route('extras.index') }}">Extras / Add-ons</a>.</p>
    @else
        <div class="service-extras-all-row d-flex align-items-center gap-2">
            <input type="checkbox" class="form-check-input" id="{{ $inputIdPrefix }}ExtrasSelectAll" data-service-extras-select-all aria-label="Selecionar todos os extras">
            <label class="form-check-label service-extra-category-header mb-0" for="{{ $inputIdPrefix }}ExtrasSelectAll">Todos os extras</label>
            <span class="badge bg-light text-dark ms-2">{{ $totalExtras }}</span>
        </div>

        @foreach($extraCategories as $category)
            <div class="service-extra-category-block" data-category-id="{{ $category->id }}">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <input type="checkbox" class="form-check-input service-extra-category-select-all" id="{{ $inputIdPrefix }}ExtrasCategory{{ $category->id }}" data-category-id="{{ $category->id }}" aria-label="Selecionar todos em {{ $category->name }}">
                    <label class="form-check-label service-extra-category-header mb-0 d-flex align-items-center gap-2" for="{{ $inputIdPrefix }}ExtrasCategory{{ $category->id }}">
                        {{ $category->name }}
                    </label>
                    <span class="badge bg-light text-dark ms-2">{{ $category->extras->count() }}</span>
                </div>
                <div class="ps-0">
                    @forelse($category->extras as $extra)
                        <div class="service-extra-row">
                            <div class="form-check flex-grow-1 mb-0">
                                <input class="form-check-input service-extra-cb" type="checkbox" name="extra_ids[]" value="{{ $extra->id }}" id="{{ $inputIdPrefix }}Extra{{ $extra->id }}" data-category-id="{{ $category->id }}" {{ in_array($extra->id, $selectedExtraIds) ? 'checked' : '' }}>
                                <label class="form-check-label service-extra-label" for="{{ $inputIdPrefix }}Extra{{ $extra->id }}">
                                    <div class="service-extra-left">
                                        <div class="service-extra-name">{{ $extra->name }}</div>
                                    </div>
                                    <span class="service-extra-price">{{ $extra->formatted_price }}</span>
                                </label>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0 py-2">Nenhum extra nesta categoria.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    @endif
</div>

@if(!$extraCategories->isEmpty())
<script>
(function() {
    function initServiceExtrasBlock(block) {
        if (block.hasAttribute('data-service-extras-inited')) return;
        block.setAttribute('data-service-extras-inited', '1');
        var selectAll = block.querySelector('[data-service-extras-select-all]');
        var categoryCheckboxes = block.querySelectorAll('.service-extra-category-select-all');
        var extraCheckboxes = block.querySelectorAll('.service-extra-cb');
        function updateCategoryCheckboxes() {
            categoryCheckboxes.forEach(function(catCb) {
                var catId = catCb.getAttribute('data-category-id');
                var inCat = block.querySelectorAll('.service-extra-cb[data-category-id="' + catId + '"]');
                var checked = Array.from(inCat).filter(function(cb) { return cb.checked; }).length;
                catCb.checked = checked === inCat.length && inCat.length > 0;
                catCb.indeterminate = checked > 0 && checked < inCat.length;
            });
        }
        function updateSelectAll() {
            var total = extraCheckboxes.length;
            var checked = Array.from(extraCheckboxes).filter(function(cb) { return cb.checked; }).length;
            if (selectAll) {
                selectAll.checked = total > 0 && checked === total;
                selectAll.indeterminate = checked > 0 && checked < total;
            }
            updateCategoryCheckboxes();
        }
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                extraCheckboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
                updateCategoryCheckboxes();
            });
        }
        categoryCheckboxes.forEach(function(catCb) {
            catCb.addEventListener('change', function() {
                var catId = this.getAttribute('data-category-id');
                block.querySelectorAll('.service-extra-cb[data-category-id="' + catId + '"]').forEach(function(cb) { cb.checked = catCb.checked; });
                updateSelectAll();
            });
        });
        extraCheckboxes.forEach(function(cb) {
            cb.addEventListener('change', updateSelectAll);
        });
        updateSelectAll();
    }
    document.querySelectorAll('[data-service-extras-block]').forEach(initServiceExtrasBlock);
})();
</script>
@endif

