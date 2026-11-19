{{-- Associação de extras ao serviço — mesmo aspeto que "Membros da Equipa". --}}
@php
    $extraCategories = $extraCategories ?? collect();
    $selectedExtraIds = $selectedExtraIds ?? [];
    $totalExtras = $extraCategories->sum(fn ($c) => $c->extras->count());
    $inputIdPrefix = $inputIdPrefix ?? 'service';
    $allSelected = $totalExtras > 0 && collect($extraCategories)->flatMap(fn ($c) => $c->extras->pluck('id'))->every(fn ($id) => in_array($id, $selectedExtraIds));
@endphp
<div class="mb-0 service-extras-block" data-service-extras-block>
    <label class="form-label">Extras associados</label>
    @if($extraCategories->isEmpty())
        <p class="text-muted small mb-0">Nenhuma categoria ou extra criado ainda. Crie extras em <a href="{{ route('extras.index') }}">Extras</a>.</p>
    @else
        <div class="border rounded p-3" style="max-height: 240px; overflow-y: auto;">
            <div class="form-check mb-2 pb-2 border-bottom">
                <input
                    type="checkbox"
                    class="form-check-input"
                    id="{{ $inputIdPrefix }}ExtrasSelectAll"
                    data-service-extras-select-all
                    aria-label="Selecionar todos os extras"
                    {{ $allSelected ? 'checked' : '' }}
                >
                <label class="form-check-label fw-semibold" for="{{ $inputIdPrefix }}ExtrasSelectAll">
                    Todos os extras
                    <span class="badge bg-secondary ms-2">{{ $totalExtras }}</span>
                </label>
            </div>

            @foreach($extraCategories as $category)
                @if($category->extras->isEmpty())
                    @continue
                @endif
                <div class="service-extra-category-block mb-3" data-category-id="{{ $category->id }}">
                    <div class="form-check mb-2 mt-2">
                        <input
                            type="checkbox"
                            class="form-check-input service-extra-category-select-all"
                            id="{{ $inputIdPrefix }}ExtrasCategory{{ $category->id }}"
                            data-category-id="{{ $category->id }}"
                            aria-label="Selecionar todos em {{ $category->name }}"
                            {{ $category->extras->every(fn ($e) => in_array($e->id, $selectedExtraIds)) ? 'checked' : '' }}
                        >
                        <label class="form-check-label fw-semibold" for="{{ $inputIdPrefix }}ExtrasCategory{{ $category->id }}">
                            {{ $category->name }}
                            <span class="badge bg-secondary ms-2">{{ $category->extras->count() }}</span>
                        </label>
                    </div>
                    @foreach($category->extras as $extra)
                        <div class="form-check ms-3 mb-2">
                            <input
                                class="form-check-input service-extra-cb"
                                type="checkbox"
                                name="extra_ids[]"
                                value="{{ $extra->id }}"
                                id="{{ $inputIdPrefix }}Extra{{ $extra->id }}"
                                data-category-id="{{ $category->id }}"
                                {{ in_array($extra->id, $selectedExtraIds) ? 'checked' : '' }}
                            >
                            <label class="form-check-label d-flex justify-content-between align-items-center gap-2 w-100" for="{{ $inputIdPrefix }}Extra{{ $extra->id }}">
                                <span>{{ $extra->name }}</span>
                                <span class="text-muted small">{{ $extra->formatted_price }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</div>

@if(!$extraCategories->isEmpty())
<script>
(function() {
    function syncServiceExtrasSelectState(block) {
        if (!block) return;
        var selectAll = block.querySelector('[data-service-extras-select-all]');
        var categoryCheckboxes = block.querySelectorAll('.service-extra-category-select-all');
        var extraCheckboxes = block.querySelectorAll('.service-extra-cb');
        categoryCheckboxes.forEach(function(catCb) {
            var catId = catCb.getAttribute('data-category-id');
            var inCat = block.querySelectorAll('.service-extra-cb[data-category-id="' + catId + '"]');
            var checked = Array.from(inCat).filter(function(cb) { return cb.checked; }).length;
            catCb.checked = checked === inCat.length && inCat.length > 0;
            catCb.indeterminate = checked > 0 && checked < inCat.length;
        });
        if (selectAll) {
            var total = extraCheckboxes.length;
            var checked = Array.from(extraCheckboxes).filter(function(cb) { return cb.checked; }).length;
            selectAll.checked = total > 0 && checked === total;
            selectAll.indeterminate = checked > 0 && checked < total;
        }
    }

    window.syncServiceExtrasSelectState = function(root) {
        (root || document).querySelectorAll('[data-service-extras-block]').forEach(syncServiceExtrasSelectState);
    };

    function initServiceExtrasBlock(block) {
        if (block.hasAttribute('data-service-extras-inited')) return;
        block.setAttribute('data-service-extras-inited', '1');
        var selectAll = block.querySelector('[data-service-extras-select-all]');
        var categoryCheckboxes = block.querySelectorAll('.service-extra-category-select-all');
        var extraCheckboxes = block.querySelectorAll('.service-extra-cb');

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                extraCheckboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
                selectAll.indeterminate = false;
                syncServiceExtrasSelectState(block);
            });
        }
        categoryCheckboxes.forEach(function(catCb) {
            catCb.addEventListener('change', function() {
                var catId = this.getAttribute('data-category-id');
                block.querySelectorAll('.service-extra-cb[data-category-id="' + catId + '"]').forEach(function(cb) {
                    cb.checked = catCb.checked;
                });
                syncServiceExtrasSelectState(block);
            });
        });
        extraCheckboxes.forEach(function(cb) {
            cb.addEventListener('change', function() {
                syncServiceExtrasSelectState(block);
            });
        });
        syncServiceExtrasSelectState(block);
    }
    document.querySelectorAll('[data-service-extras-block]').forEach(initServiceExtrasBlock);
})();
</script>
@endif
