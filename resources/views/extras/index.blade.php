@extends('partials.layouts.main')
@section('title', 'Extras / Add-ons | Beauty CRM')

@section('css')
<link href="{{ asset('template/vendor/remixicon/remixicon.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('template/vendor/dragula/dragula.min.css') }}">
<style>
/* Services Top Bar - seguindo template SmartAdmin apps-contacts */
.services-top-bar.contacts-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem 0.5rem 0.4rem 0.5rem;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 0 !important;
    background: var(--surface-color);
    border-radius: var(--bs-border-radius-lg) var(--bs-border-radius-lg) 0 0;
}
.services-top-bar .contacts-search {
    flex: 1;
    max-width: none;
}
.services-list-container {
    padding: 1.5rem 1.25rem 1.25rem 2.25rem;
}
#servicesList, .services-group-list {
    margin-left: -15px !important;
}
.service-empty-placeholder .service-item::before {
    display: none;
}
.service-empty-placeholder .service-item:hover {
    box-shadow: none;
}
.service-empty-placeholder .service-item .card-body {
    padding-top: 1rem;
    padding-bottom: 1rem;
}
.category-color-choice .ri-circle-fill {
    font-size: 1rem;
}
/* Handle de drag à esquerda: 6 pontos (3 lado a lado), visível só ao passar o rato */
.service-item-row {
    display: flex;
    align-items: stretch;
    margin-bottom: 1rem;
}
.service-drag-handle {
    display: flex;
    align-items: center;
    padding-right: 0.5rem;
    cursor: grab;
    color: var(--bs-secondary-color, #6c757d);
    user-select: none;
    opacity: 0;
    transition: opacity 0.2s ease;
}
.service-item-row:hover .service-drag-handle {
    opacity: 1;
}
.service-drag-handle:active {
    cursor: grabbing;
}
.service-drag-dots {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    grid-template-rows: repeat(3, 1fr);
    gap: 6px;
    width: 8px;
    height: 23px;
}
.service-drag-dots span {
    width: 2px;
    height: 2px;
    border-radius: 50%;
    background: currentColor;
}
.service-item-row .service-item {
    flex-grow: 1;
    margin-bottom: 0;
    cursor: default;
    transition: box-shadow 0.2s;
    position: relative;
    --service-category-color: var(--bs-secondary, #6c757d);
}
/* Borda esquerda com a cor da categoria; barra arredondada (não reta) */
.service-item-row .service-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0rem;
    bottom: 0rem;
    width: 6px;
    background: var(--service-category-color);
    border-radius: 4px 0 0 4px;
}
.service-item-row .service-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
/* Extras: borda esquerda com a cor da categoria (::before como nos serviços) */
.extra-item-row.service-item {
    position: relative;
    --service-category-color: var(--bs-secondary, #6c757d);
    transition: box-shadow 0.2s;
}
.extra-item-row.service-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0rem;
    bottom: 0rem;
    width: 6px;
    background: var(--service-category-color);
    border-radius: 4px 0 0 4px;
}
.extra-item-row.service-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.extra-item-row.extra-item-clickable {
    cursor: pointer;
}
.gu-mirror {
    opacity: 0.8;
}
.gu-mirror .service-drag-handle {
    opacity: 1;
}
.gu-transit {
    opacity: 0.2;
}
.services-category-title {
    font-weight: 600;
}
/* Preço alinhado à direita, mesma tipografia do nome do serviço */
.service-item-name,
.service-item-price {
    font-size: 1rem;
    font-weight: 600;
    color: var(--heading-color, #1e293b);
}
.service-item-price {
    white-space: nowrap;
}
/* Botão dos 3 pontinhos: ícone maior e mais escuro */
.service-item .btn-icon {
    color: var(--default-color, #334155);
}
.service-item .btn-icon i {
    font-size: 1.25rem;
}
/* Reduzir espaço vertical entre nome e duração */
.service-item-name {
    margin-bottom: 0.25rem !important;
}
.service-item-duration {
    margin-top: 0;
}
.contacts-groups-list {
    padding: var(--spacing-sm) var(--spacing-sm) !important;
}
.contacts-groups {
    padding: 0 !important;
}
.contacts-groups-list .contacts-group-item {
    border-radius: var(--radius-md) !important;
}
.contacts-groups-list .contacts-group-item:hover {
    color: var(--accent-color) !important;
    background: transparent !important;
}
.contacts-groups-list .contacts-group-item.active:hover {
    background: color-mix(in srgb, var(--accent-color), transparent 90%) !important;
}
.contacts-groups-header {
    padding: 0.3rem 1.25rem 1.3rem !important;
    font-size: 1.2rem !important;
    text-transform: none !important;
    letter-spacing: 0.05em !important;
    color: #333333 !important;
    font-weight: 600 !important;
    border-bottom: 1px solid var(--border-color) !important;
}
</style>
@endsection

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="contacts-container">
    <div class="contacts-sidebar-overlay" id="extrasSidebarOverlay"></div>
    <div class="contacts-sidebar" id="extrasSidebar">
        <button class="contacts-sidebar-close d-lg-none" id="extrasSidebarClose" aria-label="Fechar"><i class="ph ph-x"></i></button>
        <div class="contacts-groups">
            <div class="contacts-groups-list">
                <a href="{{ route('extras.index') }}" class="contacts-group-item {{ !$selectedCategory ? 'active' : '' }}">
                    <span class="contacts-group-dot" style="background: var(--bs-secondary);"></span>
                    <span>Todas as categorias</span>
                    <span class="badge">{{ $categories->sum(fn($c) => $c->extras_count ?? 0) }}</span>
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('extras.index', ['category_id' => $cat->id]) }}" class="contacts-group-item {{ $selectedCategory && $selectedCategory->id === $cat->id ? 'active' : '' }}">
                        <span class="contacts-group-dot" style="background: {{ $cat->color ?? '#6c757d' }};"></span>
                        <span>{{ $cat->name }}</span>
                        <span class="badge">{{ $cat->extras_count ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
    <div class="contacts-main">
        <div class="services-top-bar contacts-header d-flex align-items-center gap-2 mb-3">
            <button class="contacts-sidebar-toggle d-lg-none" type="button" id="extrasSidebarToggle" aria-label="Abrir categorias"><i class="ph ph-list"></i></button>
            <div class="contacts-search flex-grow-1">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" class="form-control" placeholder="Pesquisar extras..." id="extraSearch">
            </div>
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="ph ph-plus me-2"></i>Criar</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addExtraCategoryModal"><i class="ph ph-folder me-2"></i>Nova categoria</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addExtraModal"><i class="ph ph-package me-2"></i>Novo extra</a></li>
                </ul>
            </div>
        </div>
        <div class="services-list-container" id="extrasListContainer">
            @if($selectedCategory)
                <div class="mb-4" data-category-block="{{ $selectedCategory->id }}">
                    <div class="services-category-header mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2" data-category-id="{{ $selectedCategory->id }}">
                        <h5 class="mb-0 services-category-title">{{ $selectedCategory->name }}</h5>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Opções</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item extra-category-edit-btn" href="#"><i class="ph ph-pencil-simple me-2"></i>Editar</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item extra-category-delete-btn text-danger" href="#"><i class="ph ph-trash me-2"></i>Eliminar</a></li>
                            </ul>
                        </div>
                    </div>
                    <div id="extrasList" class="extras-group-list">
                        @forelse($extras as $extra)
                            @include('extras.partials.extra-item', ['extra' => $extra])
                        @empty
                            <div class="text-center py-5 text-muted">
                                <i class="ph-duotone ph-package" style="font-size: 3rem;"></i>
                                <p class="mt-3 mb-0">Nenhum extra nesta categoria.</p>
                                <a href="#" class="btn btn-outline-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#addExtraModal">Criar extra</a>
                            </div>
                        @endforelse
                    </div>
                </div>
            @else
                @forelse($categories as $cat)
                    <div class="mb-4" data-category-block="{{ $cat->id }}">
                        <div class="services-category-header mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2" data-category-id="{{ $cat->id }}">
                            <h5 class="mb-0 services-category-title">{{ $cat->name }}</h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Opções</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item extra-category-edit-btn" href="#"><i class="ph ph-pencil-simple me-2"></i>Editar</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item extra-category-delete-btn text-danger" href="#"><i class="ph ph-trash me-2"></i>Eliminar</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="extras-group-list" data-category-id="{{ $cat->id }}">
                            @forelse($cat->extras as $extra)
                                @include('extras.partials.extra-item', ['extra' => $extra])
                            @empty
                                <div class="text-center py-4 text-muted small">
                                    Nenhum extra nesta categoria. <a href="#" data-bs-toggle="modal" data-bs-target="#addExtraModal">Criar extra</a>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="ph-duotone ph-package" style="font-size: 3rem;"></i>
                        <p class="mt-3 mb-0">Nenhuma categoria criada ainda.</p>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#addExtraCategoryModal" class="btn btn-outline-primary btn-sm mt-3">Nova categoria</a>
                    </div>
                @endforelse
            @endif
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Categorias: selects de cor com Choices.js (mesma lógica que Serviços) ---
    var editExtraCategoryColorChoices = null;
    if (typeof Choices !== 'undefined') {
        function addClassesToElement(el, classes) {
            var arr = Array.isArray(classes) ? classes : [classes];
            arr.forEach(function(c) { if (c) el.classList.add(c); });
        }
        function categoryColorChoiceTemplate(templateOptions, data, itemSelectText, groupName) {
            var cn = templateOptions.classNames;
            var rawValue = typeof data.value === 'string' ? data.value : String(data.value || '');
            var div = document.createElement('div');
            div.id = data.elementId || '';
            addClassesToElement(div, cn.item);
            addClassesToElement(div, cn.itemChoice);
            div.innerHTML = '<span class="category-color-choice d-inline-flex align-items-center gap-2"><i class="ri-circle-fill" style="color:' + rawValue.replace(/"/g, '&quot;') + '"></i> ' + (data.label || '').replace(/</g, '&lt;').replace(/&/g, '&amp;') + '</span>';
            if (data.selected) addClassesToElement(div, cn.selectedState);
            if (data.placeholder) addClassesToElement(div, cn.placeholder);
            div.setAttribute('role', data.group ? 'treeitem' : 'option');
            div.dataset.choice = '';
            div.dataset.id = String(data.id != null ? data.id : '');
            div.dataset.value = rawValue;
            if (itemSelectText) div.dataset.selectText = itemSelectText;
            if (data.group) div.dataset.groupId = String(data.group.id != null ? data.group.id : '');
            if (data.disabled) {
                addClassesToElement(div, cn.itemDisabled);
                div.dataset.choiceDisabled = '';
                div.setAttribute('aria-disabled', 'true');
            } else {
                addClassesToElement(div, cn.itemSelectable);
                div.dataset.choiceSelectable = '';
                div.setAttribute('aria-selected', data.selected ? 'true' : 'false');
            }
            return div;
        }
        function categoryColorItemTemplate(templateOptions, data, removeItemButton) {
            var cn = templateOptions.classNames;
            var rawValue = typeof data.value === 'string' ? data.value : String(data.value || '');
            var div = document.createElement('div');
            addClassesToElement(div, cn.item);
            div.innerHTML = rawValue ? '<span class="category-color-choice d-inline-flex align-items-center gap-2"><i class="ri-circle-fill" style="color:' + rawValue.replace(/"/g, '&quot;') + '"></i> ' + (data.label || '').replace(/</g, '&lt;').replace(/&/g, '&amp;') + '</span>' : (templateOptions.placeholderValue || 'Selecionar cor...');
            div.dataset.item = '';
            div.dataset.id = String(data.id != null ? data.id : '');
            div.dataset.value = rawValue;
            if (this._isSelectElement) {
                div.setAttribute('aria-selected', 'true');
                div.setAttribute('role', 'option');
            }
            if (data.placeholder) {
                div.classList.add(cn.placeholder);
                div.dataset.placeholder = '';
            }
            addClassesToElement(div, data.highlighted ? cn.highlightedState : cn.itemSelectable);
            return div;
        }
        var colorChoicesOptions = {
            searchEnabled: false,
            itemSelectText: '',
            shouldSort: false,
            allowHTML: true,
            callbackOnCreateTemplates: function(template, escape, classNames) {
                return { choice: categoryColorChoiceTemplate, item: categoryColorItemTemplate };
            }
        };
        var addColorEl = document.getElementById('addExtraCategoryColorSelect');
        if (addColorEl && !addColorEl.closest('.choices')) {
            new Choices(addColorEl, colorChoicesOptions);
        }
        var editColorEl = document.getElementById('editExtraCategoryColorSelect');
        if (editColorEl && !editColorEl.closest('.choices')) {
            editExtraCategoryColorChoices = new Choices(editColorEl, colorChoicesOptions);
        }
    }

    var search = document.getElementById('extraSearch');
    if (search) {
        search.addEventListener('input', function() {
            var q = this.value.trim().toLowerCase();
            document.querySelectorAll('.extra-item-row').forEach(function(row) {
                var name = (row.querySelector('.service-item-name') || {}).textContent || '';
                var desc = (row.querySelector('.text-muted.small') || {}).textContent || '';
                var show = !q || name.toLowerCase().includes(q) || desc.toLowerCase().includes(q);
                row.style.display = show ? '' : 'none';
            });
        });
    }
    var toggle = document.getElementById('extrasSidebarToggle');
    var sidebar = document.getElementById('extrasSidebar');
    var overlay = document.getElementById('extrasSidebarOverlay');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function() { sidebar.classList.add('active'); });
        if (overlay) overlay.addEventListener('click', function() { sidebar.classList.remove('active'); });
    }
    var closeBtn = document.getElementById('extrasSidebarClose');
    if (closeBtn && sidebar) closeBtn.addEventListener('click', function() { sidebar.classList.remove('active'); });

    // Editar categoria: abrir modal com dados da categoria
    document.getElementById('extrasListContainer')?.addEventListener('click', function(e) {
        var header = e.target.closest('.services-category-header');
        if (!header) return;
        var categoryId = header.getAttribute('data-category-id');
        if (!categoryId) return;
        if (e.target.closest('.extra-category-edit-btn')) {
            e.preventDefault();
            fetch('{{ url('extra-categories') }}/' + categoryId, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(cat) {
                    document.getElementById('editExtraCategoryId').value = cat.id;
                    document.getElementById('editExtraCategoryName').value = cat.name || '';
                    document.getElementById('editExtraCategoryDescription').value = cat.description || '';
                    var color = String(cat.color || '').trim() || '';
                    var colorSelect = document.getElementById('editExtraCategoryColorSelect');
                    if (colorSelect) colorSelect.value = color;
                    var modalEl = document.getElementById('editExtraCategoryModal');
                    var doSetColor = function() {
                        if (editExtraCategoryColorChoices && typeof editExtraCategoryColorChoices.setChoiceByValue === 'function') {
                            editExtraCategoryColorChoices.setChoiceByValue(color);
                        }
                    };
                    modalEl.addEventListener('shown.bs.modal', function once() {
                        modalEl.removeEventListener('shown.bs.modal', once);
                        requestAnimationFrame(doSetColor);
                    }, { once: true });
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                })
                .catch(function() { alert('Erro ao carregar categoria.'); });
            return;
        }
        if (e.target.closest('.extra-category-delete-btn')) {
            e.preventDefault();
            if (!confirm('Eliminar esta categoria? Os extras da categoria também serão afetados.')) return;
            var csrf = document.querySelector('input[name="_token"]')?.value || '';
            fetch('{{ url('extra-categories') }}/' + categoryId, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) window.location.reload();
                else alert(data.message || 'Erro ao eliminar.');
            })
            .catch(function() { alert('Erro ao eliminar categoria.'); });
        }
    });

    // Ao alterar a cor no modal Editar Categoria: atualizar em tempo real a borda dos extras e o ponto na sidebar
    document.getElementById('editExtraCategoryColorSelect')?.addEventListener('change', function() {
        var categoryId = document.getElementById('editExtraCategoryId')?.value;
        if (!categoryId) return;
        var newColor = this.value || '#6c757d';
        var container = document.getElementById('extrasListContainer');
        if (container) {
            var items = container.querySelectorAll('[data-category-id="' + categoryId + '"] .extra-item-row, [data-category-block="' + categoryId + '"] .extra-item-row');
            items.forEach(function(el) { el.style.setProperty('--service-category-color', newColor); });
        }
        var sidebarItem = document.querySelector('.contacts-group-item[href*="category_id=' + categoryId + '"]');
        if (sidebarItem) {
            var dot = sidebarItem.querySelector('.contacts-group-dot');
            if (dot) dot.style.background = newColor;
        }
    });

    // Submit editar categoria
    document.getElementById('editExtraCategoryForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        var f = this;
        var btn = f.querySelector('button[type="submit"]');
        var categoryId = document.getElementById('editExtraCategoryId').value;
        if (!categoryId) return;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A guardar...';
        var fd = new FormData(f);
        fd.set('_method', 'PUT');
        var csrf = document.querySelector('input[name="_token"]')?.value || '';
        fetch('{{ url('extra-categories') }}/' + categoryId, {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = 'Guardar';
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('editExtraCategoryModal')).hide();
                var cat = data.category;
                if (cat && cat.id) {
                    var header = document.querySelector('.services-category-header[data-category-id="' + cat.id + '"]');
                    if (header) {
                        var titleEl = header.querySelector('.services-category-title');
                        if (titleEl) titleEl.textContent = cat.name || '';
                    }
                    var sidebarItem = document.querySelector('.contacts-group-item[href*="category_id=' + cat.id + '"]');
                    if (sidebarItem) {
                        var dot = sidebarItem.querySelector('.contacts-group-dot');
                        if (dot) dot.style.background = cat.color || '#6c757d';
                        var spans = sidebarItem.querySelectorAll('span');
                        if (spans.length >= 2) spans[1].textContent = cat.name || '';
                    }
                }
                window.location.reload();
            } else {
                alert(data.message || 'Erro ao guardar.');
            }
        })
        .catch(function() { btn.disabled = false; btn.innerHTML = 'Guardar'; });
    });

    // Novo extra (modal): submit via AJAX
    document.getElementById('addExtraForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        var f = this;
        var btn = f.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A criar...';
        var fd = new FormData(f);
        fd.delete('service_ids[]');
        f.querySelectorAll('.extra-service-cb:checked').forEach(function(cb) { fd.append('service_ids[]', cb.value); });
        fetch(f.action, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = 'Criar';
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('addExtraModal')).hide();
                window.location.reload();
            } else {
                alert(data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Erro ao criar.'));
            }
        })
        .catch(function() { btn.disabled = false; btn.innerHTML = 'Criar'; });
    });

    function openEditExtraModal(extraId) {
        if (!extraId) return;
        var modal = document.getElementById('editExtraModal');
        var form = document.getElementById('editExtraModalForm');
        if (!modal || !form) return;
        fetch('{{ url('extras') }}/' + extraId, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                form.action = form.dataset.updateUrl + '/' + data.id;
                form.setAttribute('data-extra-id', String(data.id));
                document.getElementById('editExtraDeleteBtn')?.setAttribute('data-extra-id', String(data.id));
                document.getElementById('editExtraCategoryId').value = data.extra_category_id || '';
                document.getElementById('editExtraName').value = data.name || '';
                document.getElementById('editExtraDescription').value = data.description || '';
                document.getElementById('editExtraPrice').value = data.price ?? 0;
                document.getElementById('editExtraDuration').value = data.duration ?? 0;
                var rawIds = data.service_ids;
                var serviceIds = Array.isArray(rawIds) ? rawIds : (rawIds && typeof rawIds === 'object' ? Object.values(rawIds) : []);
                var serviceIdSet = new Set(serviceIds.map(function(id) { return Number(id); }).filter(function(n) { return !isNaN(n); }));
                form.querySelectorAll('.extra-service-cb').forEach(function(cb) {
                    cb.checked = serviceIdSet.has(Number(cb.value));
                });
                var block = modal.querySelector('[data-extra-services-block]');
                if (block && block.hasAttribute('data-extra-services-inited')) {
                    var selectAll = block.querySelector('[data-extra-services-select-all]');
                    var catCbs = block.querySelectorAll('.extra-category-select-all');
                    var total = block.querySelectorAll('.extra-service-cb').length;
                    var checked = Array.from(block.querySelectorAll('.extra-service-cb')).filter(function(cb) { return cb.checked; }).length;
                    if (selectAll) {
                        selectAll.checked = total > 0 && checked === total;
                        selectAll.indeterminate = checked > 0 && checked < total;
                    }
                    catCbs.forEach(function(catCb) {
                        var catId = catCb.getAttribute('data-category-id');
                        var inCat = block.querySelectorAll('.extra-service-cb[data-category-id="' + catId + '"]');
                        var catChecked = Array.from(inCat).filter(function(cb) { return cb.checked; }).length;
                        catCb.checked = catChecked === inCat.length && inCat.length > 0;
                        catCb.indeterminate = catChecked > 0 && catChecked < inCat.length;
                    });
                }
                bootstrap.Modal.getOrCreateInstance(modal).show();
            })
            .catch(function() { alert('Erro ao carregar extra.'); });
    }

    // Abrir modal Editar extra ao clicar no card
    document.getElementById('extrasListContainer')?.addEventListener('click', function(e) {
        var row = e.target.closest('.extra-item-row.extra-item-clickable[data-extra-id]');
        if (!row) return;
        if (e.target.closest('a, button, input, select, textarea, label, .dropdown, .dropdown-menu')) return;
        e.preventDefault();
        openEditExtraModal(row.getAttribute('data-extra-id'));
    });

    // Submit Editar extra (modal)
    document.getElementById('editExtraModalForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        var f = this;
        var btn = f.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A guardar...';
        var fd = new FormData(f);
        fd.delete('service_ids[]');
        f.querySelectorAll('.extra-service-cb:checked').forEach(function(cb) { fd.append('service_ids[]', cb.value); });
        fd.set('_method', 'PUT');
        var csrf = document.querySelector('input[name="_token"]')?.value || '';
        fetch(f.action, {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = 'Guardar';
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('editExtraModal')).hide();
                window.location.reload();
            } else {
                alert(data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Erro ao guardar.'));
            }
        })
        .catch(function() { btn.disabled = false; btn.innerHTML = 'Guardar'; });
    });

    document.getElementById('editExtraDeleteBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        var extraId = this.getAttribute('data-extra-id') || document.getElementById('editExtraModalForm')?.getAttribute('data-extra-id');
        if (!extraId) return;
        if (!confirm('Eliminar este extra?')) return;
        var csrf = document.querySelector('input[name="_token"]')?.value || '';
        fetch('{{ url('extras') }}/' + extraId, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('editExtraModal')).hide();
                window.location.reload();
            } else {
                alert(data.message || 'Erro ao eliminar.');
            }
        })
        .catch(function() { alert('Erro ao eliminar extra.'); });
    });
});
</script>

<!-- Modal Nova Categoria -->
<div class="modal fade" id="addExtraCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova categoria de extras</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addExtraCategoryForm">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="addExtraCategoryColorSelect" class="form-label">Cor <span class="text-danger">*</span></label>
                        <select class="form-select" id="addExtraCategoryColorSelect" name="color" required>
                            <option value="">Selecionar cor...</option>
                            <option value="#bfdbfe">Azul Céu</option>
                            <option value="#93c5fd">Azul Claro</option>
                            <option value="#a5b4fc">Azul Índigo</option>
                            <option value="#c7d2fe">Azul Lavanda</option>
                            <option value="#ddd6fe">Lavanda</option>
                            <option value="#e9d5ff">Lilás</option>
                            <option value="#f3e8ff">Roxo Pastel</option>
                            <option value="#fbcfe8">Rosa Pastel</option>
                            <option value="#fecdd3">Rosa Claro</option>
                            <option value="#fda4af">Coral Suave</option>
                            <option value="#fed7aa">Laranja Pastel</option>
                            <option value="#fde68a">Âmbar Claro</option>
                            <option value="#fef9c3">Amarelo Pastel</option>
                            <option value="#d9f99d">Verde Lima</option>
                            <option value="#bbf7d0">Verde Menta</option>
                            <option value="#99f6e4">Verde Água</option>
                            <option value="#a5f3fc">Ciano Claro</option>
                            <option value="#bae6fd">Azul Gelo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Novo Extra -->
<div class="modal fade" id="addExtraModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo extra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addExtraForm" action="{{ route('extras.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Categoria <span class="text-danger">*</span></label>
                            <select class="form-select" name="extra_category_id" id="addExtraCategoryId" required>
                                <option value="">— Selecionar categoria —</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ $selectedCategory && $selectedCategory->id === $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required maxlength="255">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Preço (€) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="price" step="0.01" min="0" value="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duração (minutos) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="duration" min="0" value="0" required>
                        </div>
                    </div>
                    @if(isset($serviceCategories) && $serviceCategories->isNotEmpty())
                    <div class="mb-3">
                        @include('extras.partials.extra-services-association', [
                            'serviceCategories' => $serviceCategories,
                            'selectedServiceIds' => [],
                        ])
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Extra -->
<div class="modal fade" id="editExtraModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar extra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editExtraModalForm" data-update-url="{{ url('extras') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Categoria <span class="text-danger">*</span></label>
                            <select class="form-select" name="extra_category_id" id="editExtraCategoryId" required>
                                <option value="">— Selecionar categoria —</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="editExtraName" required maxlength="255">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="description" id="editExtraDescription" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Preço (€) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="price" id="editExtraPrice" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duração (minutos) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="duration" id="editExtraDuration" min="0" required>
                        </div>
                    </div>
                    @if(isset($serviceCategories) && $serviceCategories->isNotEmpty())
                    <div class="mb-3">
                        @include('extras.partials.extra-services-association', [
                            'serviceCategories' => $serviceCategories,
                            'selectedServiceIds' => [],
                            'inputIdPrefix' => 'editExtra',
                        ])
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto" id="editExtraDeleteBtn">
                        <i class="ph ph-trash me-1"></i>Eliminar
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Categoria -->
<div class="modal fade" id="editExtraCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar categoria de extras</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editExtraCategoryForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="category_id" id="editExtraCategoryId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="editExtraCategoryName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="description" id="editExtraCategoryDescription" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editExtraCategoryColorSelect" class="form-label">Cor <span class="text-danger">*</span></label>
                        <select class="form-select" id="editExtraCategoryColorSelect" name="color" required>
                            <option value="">Selecionar cor...</option>
                            <option value="#bfdbfe">Azul Céu</option>
                            <option value="#93c5fd">Azul Claro</option>
                            <option value="#a5b4fc">Azul Índigo</option>
                            <option value="#c7d2fe">Azul Lavanda</option>
                            <option value="#ddd6fe">Lavanda</option>
                            <option value="#e9d5ff">Lilás</option>
                            <option value="#f3e8ff">Roxo Pastel</option>
                            <option value="#fbcfe8">Rosa Pastel</option>
                            <option value="#fecdd3">Rosa Claro</option>
                            <option value="#fda4af">Coral Suave</option>
                            <option value="#fed7aa">Laranja Pastel</option>
                            <option value="#fde68a">Âmbar Claro</option>
                            <option value="#fef9c3">Amarelo Pastel</option>
                            <option value="#d9f99d">Verde Lima</option>
                            <option value="#bbf7d0">Verde Menta</option>
                            <option value="#99f6e4">Verde Água</option>
                            <option value="#a5f3fc">Ciano Claro</option>
                            <option value="#bae6fd">Azul Gelo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('addExtraCategoryForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    var f = this;
    var btn = f.querySelector('button[type="submit"]');
    btn.disabled = true;
    var fd = new FormData(f);
    fetch('{{ route('extras.categories.store') }}', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) window.location.reload();
        else { btn.disabled = false; alert(data.message || 'Erro.'); }
    }).catch(function() { btn.disabled = false; });
});
</script>
@endsection
