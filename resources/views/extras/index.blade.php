@extends('partials.layouts.main')
@section('title', 'Extras | Beauty CRM')

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
#extrasList,
.extras-group-list {
    margin-left: -15px !important;
}
.service-empty-placeholder .service-item::before {
    display: none;
}
.service-empty-placeholder .service-item:hover {
    box-shadow: none;
}
.service-empty-placeholder .service-item .card-body {
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
}
.category-color-choice .ri-circle-fill {
    font-size: 1rem;
}
.services-category-title-edit,
.contacts-group-item .services-category-sidebar-edit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    padding: 0;
    border: 0;
    border-radius: 0.35rem;
    background: transparent;
    color: var(--muted-color, #6b7280);
    text-decoration: none !important;
    box-shadow: none;
    line-height: 1;
    transition: color 0.15s ease, background 0.15s ease, opacity 0.15s ease;
}
.services-category-title-edit,
.services-category-options-btn {
    flex-shrink: 0;
    opacity: 0.7;
    transform: translateY(2px);
}
.services-category-title-edit {
    margin-left: 0.85rem;
}
.services-category-options-btn {
    display: inline-flex;
    align-items: center;
    height: 1.5rem;
    margin-left: 0.55rem;
    padding: 0 0.35rem;
    border: 0;
    border-radius: 0.35rem;
    background: transparent;
    color: var(--muted-color, #6b7280);
    font-size: 0.8125rem;
    font-weight: 500;
    line-height: 1;
    box-shadow: none;
    text-decoration: none !important;
    transition: color 0.15s ease, background 0.15s ease, opacity 0.15s ease;
}
.services-category-options-btn.dropdown-toggle::after {
    margin-left: 0.3rem;
    vertical-align: 0.15em;
}
.services-category-header .d-flex.align-items-center.min-w-0 {
    align-items: center;
}
.services-category-header .services-category-title {
    line-height: 1.35;
    margin-bottom: 0;
}
.services-category-title-edit:hover,
.services-category-title-edit:focus,
.services-category-options-btn:hover,
.services-category-options-btn:focus {
    opacity: 1;
    color: var(--accent-color, #5b8def);
    background: color-mix(in srgb, var(--accent-color, #5b8def) 12%, transparent);
    text-decoration: none !important;
}
[data-category-block] + [data-category-block] {
    margin-top: 3.25rem;
}
.contacts-group-item .services-category-sidebar-edit {
    margin-left: auto;
    opacity: 0;
    pointer-events: none;
}
.contacts-group-item:hover .services-category-sidebar-edit,
.contacts-group-item:focus-within .services-category-sidebar-edit {
    opacity: 1;
    pointer-events: auto;
}
.contacts-group-item .services-category-sidebar-edit:hover,
.contacts-group-item .services-category-sidebar-edit:focus {
    color: var(--accent-color, #5b8def);
    background: color-mix(in srgb, var(--accent-color, #5b8def) 12%, transparent);
}
.contacts-group-item .services-category-sidebar-edit + .badge {
    margin-left: 0;
}
.services-offcanvas {
    --bs-offcanvas-width: 50vw !important;
    width: 50vw !important;
    max-width: 100vw;
}
.services-offcanvas.offcanvas-end {
    border-left: 1px solid var(--border-color, rgba(0, 0, 0, 0.1));
    border-radius: 20px 0 0 20px;
    overflow: hidden;
}
.services-offcanvas:focus,
.services-offcanvas:focus-visible {
    outline: none;
    box-shadow: none;
}
.services-offcanvas__form {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}
.services-offcanvas .offcanvas-header {
    padding: 1rem 1.75rem;
}
.services-offcanvas .offcanvas-body {
    flex: 1 1 auto;
    padding: 1.75rem;
    overflow-y: auto;
}
.services-offcanvas .offcanvas-body .form-label:not(.small) {
    color: #9ca3af !important;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    margin-bottom: 0.25rem !important;
}
.services-offcanvas__footer {
    flex-shrink: 0;
    padding: 1rem 1.75rem;
    box-sizing: border-box;
    background: var(--bs-body-bg, #fff);
}
.services-offcanvas__footer .btn {
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.5;
    padding: var(--spacing-sm, 0.375rem) var(--spacing-lg, 1rem);
    box-sizing: border-box;
    height: calc(0.875rem * 1.5 + var(--spacing-sm, 0.375rem) * 2 + 2px);
    min-height: calc(0.875rem * 1.5 + var(--spacing-sm, 0.375rem) * 2 + 2px);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.services-offcanvas__danger-zone {
    margin-top: 2.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color-light, #eef0f3);
}
#deleteExtraConfirmModal,
#deleteExtraCategoryConfirmModal {
    --bs-modal-zindex: 1085;
}
.services-offcanvas__associated {
    margin-top: 2.25rem;
}
@media (max-width: 991.98px) {
    .services-offcanvas {
        --bs-offcanvas-width: 100% !important;
        width: 100% !important;
        border-radius: 0 !important;
    }
}
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
    cursor: pointer;
    transition: box-shadow 0.2s;
    position: relative;
    --service-category-color: var(--bs-secondary, #6c757d);
}
.service-item-row .service-item > .card-body {
    padding-top: 0.75rem !important;
    padding-bottom: 0.75rem !important;
}
.service-item-row .service-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    background: var(--service-category-color);
    border-radius: 4px 0 0 4px;
}
.service-item-row .service-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.gu-mirror {
    opacity: 0.8;
    z-index: 1100 !important;
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
.service-item-name,
.service-item-price {
    font-size: 1rem;
    font-weight: 600;
    color: var(--heading-color, #1e293b);
}
.service-item-price {
    white-space: nowrap;
    margin-right: 0.85rem;
}
.service-item-name {
    display: block;
    line-height: 1.35;
    margin-bottom: 0.55rem !important;
}
.service-item-header .service-item-name {
    margin-bottom: 0 !important;
    min-width: 0;
}
.service-item-badges {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.4rem;
}
.service-item-badges .badge {
    font-weight: 500;
    font-size: 0.75rem;
    color: var(--heading-color, #1e293b) !important;
    background-color: var(--background-color, #f1f5f9) !important;
    border: 1px solid var(--border-color, #e5e7eb);
}
.service-item-members-trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0;
    border: 0;
    background: transparent;
    cursor: pointer;
    max-width: 100%;
}
.service-item-members-trigger:hover .badge {
    border-color: color-mix(in srgb, var(--accent-color, #0d6efd), transparent 40%);
}
.service-item-members-trigger:focus-visible {
    outline: 2px solid color-mix(in srgb, var(--accent-color, #0d6efd), transparent 50%);
    outline-offset: 2px;
    border-radius: 0.35rem;
}
.service-item-members {
    margin-top: 0;
    flex-shrink: 0;
}
.service-item-members.avatar-group .avatar,
.service-item-members .avatar-more {
    width: 22px;
    height: 22px;
    margin-left: -6px;
}
.service-item-members.avatar-group .avatar:first-child {
    margin-left: 0;
}
.service-item-members .avatar {
    position: relative;
}
.service-item-members-trigger:hover .avatar {
    z-index: 1;
}
.service-item-member-initial {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--background-color, #f1f5f9);
    color: var(--heading-color, #1e293b);
    font-size: 0.625rem;
    font-weight: 600;
    line-height: 1;
    user-select: none;
}
.service-members-modal-store {
    margin-bottom: 1.15rem;
}
.service-members-modal-store:last-child {
    margin-bottom: 0;
}
.service-members-modal-store-title {
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--muted-color, #6b7280);
    margin-bottom: 0.55rem;
}
.service-members-modal-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}
.service-members-modal-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}
.service-members-modal-item .avatar,
.service-members-modal-item .service-item-member-initial {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    object-fit: cover;
    flex-shrink: 0;
}
.service-members-modal-item .service-item-member-initial {
    border: 1px solid var(--border-color, #e5e7eb);
}
.service-item-header {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto 8.5rem;
    column-gap: 1rem;
    align-items: center;
    width: 100%;
}
.service-item-header-meta:empty {
    width: 0;
}
.service-item-desde-duration {
    white-space: nowrap;
    text-align: right;
    font-variant-numeric: tabular-nums;
    font-size: 1rem;
    font-weight: 600;
    color: var(--heading-color, #1e293b);
    line-height: 1.35;
}
.service-item-header .service-item-price {
    white-space: nowrap;
    text-align: right;
    font-variant-numeric: tabular-nums;
    margin-right: 0.85rem;
}
@media (max-width: 575.98px) {
    .service-item-header {
        grid-template-columns: minmax(0, 1fr) auto;
        row-gap: 0.35rem;
    }
    .service-item-header .service-item-price {
        grid-column: 2;
        grid-row: 1;
    }
}
.contacts-groups-list {
    padding: var(--spacing-sm) var(--spacing-sm) !important;
}
.contacts-groups {
    padding: 0 !important;
    border-bottom: none !important;
}
.contacts-groups-footer {
    padding: 0.35rem var(--spacing-sm) var(--spacing-sm);
    border-top: 1px solid var(--border-color, #e5e7eb);
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.contacts-groups-footer .contacts-group-item {
    border-radius: var(--radius-md) !important;
}
.contacts-groups-footer .contacts-group-footer-icon {
    width: 0.75rem;
    font-size: 0.95rem;
    color: var(--muted-color, #6b7280);
    flex-shrink: 0;
}
.extra-item-row--archived .service-drag-handle {
    visibility: hidden;
    pointer-events: none;
}
.extra-item-row--archived .service-item {
    cursor: default;
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
                <a href="{{ route('extras.index') }}" class="contacts-group-item {{ !$selectedCategory && empty($viewArchive) ? 'active' : '' }}">
                    <span class="contacts-group-dot" style="background: var(--bs-secondary);"></span>
                    <span>Todas as categorias</span>
                    <span class="badge">{{ $categories->sum(fn($c) => $c->extras_count ?? 0) }}</span>
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('extras.index', ['category_id' => $cat->id]) }}" class="contacts-group-item {{ $selectedCategory && $selectedCategory->id === $cat->id ? 'active' : '' }}" data-category-id="{{ $cat->id }}">
                        <span class="contacts-group-dot" style="background: {{ $cat->color ?? '#6c757d' }};"></span>
                        <span>{{ $cat->name }}</span>
                        <button type="button" class="services-category-sidebar-edit extra-category-edit-btn" title="Editar categoria" aria-label="Editar categoria" data-category-id="{{ $cat->id }}">
                            <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                        </button>
                        <span class="badge">{{ $cat->extras_count ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
            <div class="contacts-groups-footer">
                <a href="#" class="contacts-group-item" id="sidebarCreateExtraCategoryBtn" data-action="create-category">
                    <i class="ph ph-plus-circle contacts-group-footer-icon" aria-hidden="true"></i>
                    <span>Nova categoria</span>
                </a>
                <a href="{{ route('extras.index', ['category_id' => 'archive']) }}" class="contacts-group-item {{ !empty($viewArchive) ? 'active' : '' }}" data-category-id="archive">
                    <i class="ph ph-archive contacts-group-footer-icon" aria-hidden="true"></i>
                    <span>Arquivo</span>
                    <span class="badge" id="archivedExtrasBadge">{{ (int) ($archivedExtrasCount ?? 0) }}</span>
                </a>
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
                    <li><a class="dropdown-item" href="#" id="openCreateExtraCategoryBtn"><i class="ph ph-folder me-2"></i>Nova categoria</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="offcanvas" data-bs-target="#addExtraModal" id="pageHeaderAddExtra"><i class="ph ph-package me-2"></i>Novo extra</a></li>
                </ul>
            </div>
        </div>
        <div class="services-list-container" id="extrasListContainer">
            @if(!empty($viewArchive))
                <div class="mb-4">
                    <div class="services-category-header mb-3 d-flex align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center min-w-0">
                            <h5 class="mb-0 services-category-title text-truncate">Arquivo</h5>
                        </div>
                    </div>
                    <div id="extrasList" class="extras-group-list" data-archived="1">
                        @forelse($archivedExtras as $extra)
                            @include('extras.partials.extra-item', ['extra' => $extra, 'archived' => true])
                        @empty
                            <div class="service-item-row service-empty-placeholder">
                                <div class="service-drag-handle" style="visibility: hidden;" aria-hidden="true"></div>
                                <div class="card service-item">
                                    <div class="card-body">
                                        <p class="text-muted small mb-0">Nenhum extra no Arquivo.</p>
                                    </div>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            @elseif($selectedCategory)
                <div class="mb-4" data-category-block="{{ $selectedCategory->id }}">
                    <div class="services-category-header mb-3 d-flex align-items-center flex-wrap gap-2" data-category-id="{{ $selectedCategory->id }}">
                        <div class="d-flex align-items-center min-w-0">
                            <h5 class="mb-0 services-category-title text-truncate">{{ $selectedCategory->name }}</h5>
                            <button type="button" class="services-category-title-edit extra-category-edit-btn" title="Editar categoria" aria-label="Editar categoria">
                                <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                            </button>
                            <div class="dropdown">
                                <button class="services-category-options-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Opções</button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item extra-category-edit-btn" href="#"><i class="ph ph-pencil-simple me-2"></i>Editar categoria</a></li>
                                    <li><a class="dropdown-item extra-category-add-extra-btn" href="#"><i class="ph ph-plus me-2"></i>Adicionar extra</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item extra-category-delete-btn text-danger" href="#"><i class="ph ph-trash me-2"></i>Eliminar</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div id="extrasList" class="extras-group-list" data-category-id="{{ $selectedCategory->id }}">
                        @forelse($extras as $extra)
                            @include('extras.partials.extra-item', ['extra' => $extra])
                        @empty
                            <div class="service-item-row service-empty-placeholder">
                                <div class="service-drag-handle" style="visibility: hidden;" aria-hidden="true"></div>
                                <div class="card service-item">
                                    <div class="card-body">
                                        <p class="text-muted small mb-0">Nenhum extra nesta categoria. <a href="#" class="extra-category-add-extra-btn">Criar extra</a></p>
                                    </div>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            @else
                @forelse($categories as $cat)
                    <div class="mb-4" data-category-block="{{ $cat->id }}">
                        <div class="services-category-header mb-3 d-flex align-items-center flex-wrap gap-2" data-category-id="{{ $cat->id }}">
                            <div class="d-flex align-items-center min-w-0">
                                <h5 class="mb-0 services-category-title text-truncate">{{ $cat->name }}</h5>
                                <button type="button" class="services-category-title-edit extra-category-edit-btn" title="Editar categoria" aria-label="Editar categoria">
                                    <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                                </button>
                                <div class="dropdown">
                                    <button class="services-category-options-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Opções</button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item extra-category-edit-btn" href="#"><i class="ph ph-pencil-simple me-2"></i>Editar categoria</a></li>
                                        <li><a class="dropdown-item extra-category-add-extra-btn" href="#"><i class="ph ph-plus me-2"></i>Adicionar extra</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item extra-category-delete-btn text-danger" href="#"><i class="ph ph-trash me-2"></i>Eliminar</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="extras-group-list" data-category-id="{{ $cat->id }}">
                            @forelse($cat->extras as $extra)
                                @include('extras.partials.extra-item', ['extra' => $extra])
                            @empty
                                <div class="service-item-row service-empty-placeholder">
                                    <div class="service-drag-handle" style="visibility: hidden;" aria-hidden="true"></div>
                                    <div class="card service-item">
                                        <div class="card-body">
                                            <p class="text-muted small mb-0">Nenhum extra nesta categoria. <a href="#" class="extra-category-add-extra-btn">Criar extra</a></p>
                                        </div>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="ph-duotone ph-package" style="font-size: 3rem;"></i>
                        <p class="mt-3 mb-0">Nenhuma categoria criada ainda.</p>
                        <a href="#" id="emptyStateCreateExtraCategoryBtn" class="btn btn-outline-primary btn-sm mt-3">Nova categoria</a>
                    </div>
                @endforelse
            @endif
        </div>
    </div>
</div>

<script src="{{ asset('template/vendor/dragula/dragula.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var pendingExtraCategoryDeleteId = null;
    function toast(msg, type) {
        if (typeof window.showToast === 'function') window.showToast(msg, type || 'error');
        else if (type === 'error') console.error(msg);
    }
    // --- Categorias: selects de cor com Choices.js (mesma lógica que Serviços) ---
    var extraCategoryColorChoices = null;
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
        var colorEl = document.getElementById('editExtraCategoryColorSelect');
        if (colorEl && !colorEl.closest('.choices')) {
            extraCategoryColorChoices = new Choices(colorEl, colorChoicesOptions);
        }
    }

    function setExtraCategoryOffcanvasMode(mode) {
        var panel = document.getElementById('editExtraCategoryModal');
        var title = document.getElementById('editExtraCategoryOffcanvasLabel');
        var methodInput = document.getElementById('editExtraCategoryMethod');
        var submitBtn = document.getElementById('editExtraCategorySubmitBtn');
        if (!panel) return;
        panel.setAttribute('data-mode', mode);
        if (mode === 'create') {
            if (title) title.textContent = 'Nova categoria de extras';
            if (methodInput) methodInput.value = 'POST';
            if (submitBtn) submitBtn.textContent = 'Criar';
        } else {
            if (title) title.textContent = 'Editar categoria de extras';
            if (methodInput) methodInput.value = 'PUT';
            if (submitBtn) submitBtn.textContent = 'Guardar';
        }
    }

    function resetExtraCategoryForm() {
        var form = document.getElementById('editExtraCategoryForm');
        if (form) form.reset();
        document.getElementById('editExtraCategoryModalId').value = '';
        var colorSelect = document.getElementById('editExtraCategoryColorSelect');
        if (colorSelect) colorSelect.value = '';
        if (extraCategoryColorChoices && typeof extraCategoryColorChoices.setChoiceByValue === 'function') {
            extraCategoryColorChoices.setChoiceByValue('');
        }
    }

    function openCreateExtraCategory() {
        setExtraCategoryOffcanvasMode('create');
        resetExtraCategoryForm();
        var panel = document.getElementById('editExtraCategoryModal');
        if (panel) bootstrap.Offcanvas.getOrCreateInstance(panel).show();
    }

    document.getElementById('openCreateExtraCategoryBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openCreateExtraCategory();
    });
    document.getElementById('emptyStateCreateExtraCategoryBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openCreateExtraCategory();
    });
    document.getElementById('sidebarCreateExtraCategoryBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openCreateExtraCategory();
    });

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

    function openEditExtraCategory(categoryId) {
        if (!categoryId) return;
        fetch('{{ url('extra-categories') }}/' + categoryId, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(cat) {
                setExtraCategoryOffcanvasMode('edit');
                document.getElementById('editExtraCategoryModalId').value = cat.id;
                document.getElementById('editExtraCategoryName').value = cat.name || '';
                document.getElementById('editExtraCategoryDescription').value = cat.description || '';
                var color = String(cat.color || '').trim() || '';
                var colorSelect = document.getElementById('editExtraCategoryColorSelect');
                if (colorSelect) colorSelect.value = color;
                var panelEl = document.getElementById('editExtraCategoryModal');
                var doSetColor = function() {
                    if (extraCategoryColorChoices && typeof extraCategoryColorChoices.setChoiceByValue === 'function') {
                        extraCategoryColorChoices.setChoiceByValue(color);
                    }
                };
                panelEl.addEventListener('shown.bs.offcanvas', function once() {
                    panelEl.removeEventListener('shown.bs.offcanvas', once);
                    requestAnimationFrame(doSetColor);
                }, { once: true });
                bootstrap.Offcanvas.getOrCreateInstance(panelEl).show();
            })
            .catch(function() { alert('Erro ao carregar categoria.'); });
    }

    function openAddExtraOffcanvas(categoryId) {
        var select = document.getElementById('addExtraCategoryId');
        if (select && categoryId) select.value = String(categoryId);
        var el = document.getElementById('addExtraModal');
        if (el) bootstrap.Offcanvas.getOrCreateInstance(el).show();
    }

    // Sidebar: pencil sem navegar
    document.getElementById('extrasSidebar')?.addEventListener('click', function(e) {
        var editBtn = e.target.closest('.extra-category-edit-btn');
        if (!editBtn) return;
        e.preventDefault();
        e.stopPropagation();
        openEditExtraCategory(editBtn.getAttribute('data-category-id') || editBtn.closest('[data-category-id]')?.getAttribute('data-category-id'));
    });

    // Headers / listas: editar categoria, adicionar extra, eliminar
    document.getElementById('extrasListContainer')?.addEventListener('click', function(e) {
        var addBtn = e.target.closest('.extra-category-add-extra-btn');
        if (addBtn) {
            e.preventDefault();
            var header = addBtn.closest('.services-category-header') || addBtn.closest('[data-category-block]');
            var categoryId = header?.getAttribute('data-category-id') || header?.getAttribute('data-category-block');
            if (!categoryId) {
                var block = addBtn.closest('[data-category-block]');
                categoryId = block?.getAttribute('data-category-block');
            }
            openAddExtraOffcanvas(categoryId);
            return;
        }

        var header = e.target.closest('.services-category-header');
        if (!header) return;
        var categoryId = header.getAttribute('data-category-id');
        if (!categoryId) return;
        if (e.target.closest('.extra-category-edit-btn')) {
            e.preventDefault();
            openEditExtraCategory(categoryId);
            return;
        }
        if (e.target.closest('.extra-category-delete-btn')) {
            e.preventDefault();
            pendingExtraCategoryDeleteId = categoryId;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteExtraCategoryConfirmModal')).show();
        }
    });

    document.getElementById('deleteExtraCategoryConfirmBtn')?.addEventListener('click', function () {
        var categoryId = pendingExtraCategoryDeleteId;
        if (!categoryId) return;
        pendingExtraCategoryDeleteId = null;
        bootstrap.Modal.getInstance(document.getElementById('deleteExtraCategoryConfirmModal'))?.hide();
        var csrf = document.querySelector('input[name="_token"]')?.value
            || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || '';
        fetch('{{ url('extra-categories') }}/' + categoryId, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) window.location.reload();
                else toast(data.message || 'Erro ao eliminar.', 'error');
            })
            .catch(function() { toast('Erro ao eliminar categoria.', 'error'); });
    });

    // Ao alterar a cor no modal Editar Categoria: atualizar em tempo real a borda dos extras e o ponto na sidebar
    document.getElementById('editExtraCategoryColorSelect')?.addEventListener('change', function() {
        var categoryId = document.getElementById('editExtraCategoryModalId')?.value;
        if (!categoryId) return;
        var newColor = this.value || '#6c757d';
        var container = document.getElementById('extrasListContainer');
        if (container) {
            var items = container.querySelectorAll(
                '[data-category-id="' + categoryId + '"] .service-item, [data-category-block="' + categoryId + '"] .service-item'
            );
            items.forEach(function(el) { el.style.setProperty('--service-category-color', newColor); });
        }
        var sidebarItem = document.querySelector('.contacts-group-item[href*="category_id=' + categoryId + '"]');
        if (sidebarItem) {
            var dot = sidebarItem.querySelector('.contacts-group-dot');
            if (dot) dot.style.background = newColor;
        }
    });

    // Submit criar / editar categoria
    document.getElementById('editExtraCategoryForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        var f = this;
        var btn = f.querySelector('button[type="submit"]');
        var panel = document.getElementById('editExtraCategoryModal');
        var mode = panel?.getAttribute('data-mode') || 'edit';
        var categoryId = document.getElementById('editExtraCategoryModalId').value;
        if (mode === 'edit' && !categoryId) return;
        var defaultLabel = mode === 'create' ? 'Criar' : 'Guardar';
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A guardar...';
        var fd = new FormData(f);
        var csrf = document.querySelector('input[name="_token"]')?.value || '';
        var url = mode === 'create'
            ? '{{ route('extras.categories.store') }}'
            : ('{{ url('extra-categories') }}/' + categoryId);
        if (mode === 'edit') fd.set('_method', 'PUT');
        else fd.delete('_method');
        fetch(url, {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = defaultLabel;
            if (data.success) {
                bootstrap.Offcanvas.getInstance(panel)?.hide();
                window.location.reload();
            } else {
                alert(data.message || 'Erro ao guardar.');
            }
        })
        .catch(function() { btn.disabled = false; btn.innerHTML = defaultLabel; });
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
                bootstrap.Offcanvas.getInstance(document.getElementById('addExtraModal'))?.hide();
                window.location.reload();
            } else {
                alert(data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Erro ao criar.'));
            }
        })
        .catch(function() { btn.disabled = false; btn.innerHTML = 'Criar'; });
    });

    function openEditExtraOffcanvas(extraId) {
        if (!extraId) return;
        var panel = document.getElementById('editExtraModal');
        var form = document.getElementById('editExtraModalForm');
        if (!panel || !form) return;
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
                if (typeof window.syncExtraServicesSelectState === 'function') {
                    window.syncExtraServicesSelectState(panel);
                }
                bootstrap.Offcanvas.getOrCreateInstance(panel).show();
            })
            .catch(function() { alert('Erro ao carregar extra.'); });
    }

    function escapeHtmlExtra(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function agentInitialsExtra(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (parts.length >= 2) {
            return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
        }
        if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
        return '?';
    }

    function renderExtraMemberAvatarHtml(agent) {
        var name = escapeHtmlExtra(agent.name || '');
        if (agent.avatar) {
            var src = escapeHtmlExtra('/storage/' + String(agent.avatar).replace(/^\/+/, ''));
            return '<img src="' + src + '" alt="' + name + '" class="avatar" loading="lazy">';
        }
        return '<span class="avatar service-item-member-initial" aria-hidden="true">' + escapeHtmlExtra(agentInitialsExtra(agent.name)) + '</span>';
    }

    function openExtraMembersModal(triggerEl) {
        var modalEl = document.getElementById('extraMembersModal');
        var bodyEl = document.getElementById('extraMembersModalBody');
        var nameEl = document.getElementById('extraMembersModalExtraName');
        if (!modalEl || !bodyEl || !nameEl || !triggerEl) return;
        var members = [];
        try {
            members = JSON.parse(triggerEl.getAttribute('data-members') || '[]');
        } catch (err) {
            members = [];
        }
        if (!Array.isArray(members)) members = [];
        nameEl.textContent = triggerEl.getAttribute('data-extra-name') || '';
        if (!members.length) {
            bodyEl.innerHTML =
                '<p class="text-muted mb-2">Nenhum membro associado a este extra.</p>' +
                '<p class="small mb-0">Associa técnicos em <a href="{{ route('services.extras-equipa') }}">Extras por equipa</a>.</p>';
        } else {
            var byStore = {};
            members.forEach(function (agent) {
                var key = String(agent.store_id || 0) + '::' + (agent.store_name || 'Sem loja');
                if (!byStore[key]) {
                    byStore[key] = { storeName: agent.store_name || 'Sem loja', agents: [] };
                }
                byStore[key].agents.push(agent);
            });
            bodyEl.innerHTML = Object.keys(byStore).map(function (key) {
                var group = byStore[key];
                var items = group.agents.map(function (agent) {
                    return '<li class="service-members-modal-item">' +
                        renderExtraMemberAvatarHtml(agent) +
                        '<span>' + escapeHtmlExtra(agent.name || '') + '</span>' +
                        '</li>';
                }).join('');
                return '<div class="service-members-modal-store">' +
                    '<div class="service-members-modal-store-title">' + escapeHtmlExtra(group.storeName) + '</div>' +
                    '<ul class="service-members-modal-list">' + items + '</ul>' +
                    '</div>';
            }).join('');
        }
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    // Abrir offcanvas Editar extra ao clicar no card (Arquivo: só restaurar)
    document.getElementById('extrasListContainer')?.addEventListener('click', function(e) {
        var membersBtn = e.target.closest('.service-item-members-trigger');
        if (membersBtn) {
            e.preventDefault();
            e.stopPropagation();
            openExtraMembersModal(membersBtn);
            return;
        }
        var restoreBtn = e.target.closest('.extra-archive-restore-btn');
        if (restoreBtn) {
            e.preventDefault();
            e.stopPropagation();
            var restoreId = restoreBtn.getAttribute('data-restore-extra-id');
            if (!restoreId) return;
            var csrfRestore = document.querySelector('input[name="_token"]')?.value || '';
            fetch('{{ url('extras') }}/' + restoreId + '/restore', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfRestore, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
            .then(function(res) {
                if (res.ok && res.data.success) window.location.reload();
                else alert(res.data.message || 'Erro ao restaurar.');
            })
            .catch(function() { alert('Erro ao restaurar extra.'); });
            return;
        }
        if (e.target.closest('.service-drag-handle')) return;
        var row = e.target.closest('.extra-item-row[data-extra-id]');
        if (!row) return;
        if (row.getAttribute('data-archived') === '1') return;
        if (e.target.closest('a, button, input, select, textarea, label, .dropdown, .dropdown-menu')) return;
        e.preventDefault();
        openEditExtraOffcanvas(row.getAttribute('data-extra-id'));
    });

    // Submit Editar extra (offcanvas)
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
                bootstrap.Offcanvas.getInstance(document.getElementById('editExtraModal'))?.hide();
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
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteExtraConfirmModal')).show();
    });

    document.getElementById('deleteExtraConfirmBtn')?.addEventListener('click', function() {
        var extraId = document.getElementById('editExtraDeleteBtn')?.getAttribute('data-extra-id')
            || document.getElementById('editExtraModalForm')?.getAttribute('data-extra-id');
        if (!extraId) return;
        bootstrap.Modal.getInstance(document.getElementById('deleteExtraConfirmModal'))?.hide();
        var csrf = document.querySelector('input[name="_token"]')?.value || '';
        fetch('{{ url('extras') }}/' + extraId, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('editExtraModal')).hide();
                window.location.reload();
            } else {
                toast(data.message || 'Erro ao arquivar.', 'error');
            }
        })
            .catch(function() { toast('Erro ao arquivar extra.', 'error'); });
    });

    // Dragula — reordenar extras por categoria
    function persistExtrasOrder(listEl) {
        if (!listEl) return;
        var categoryId = listEl.getAttribute('data-category-id');
        if (!categoryId) return;
        var order = Array.from(listEl.querySelectorAll('.extra-item-row[data-extra-id]'))
            .map(function(row) { return parseInt(row.getAttribute('data-extra-id'), 10); })
            .filter(function(id) { return !isNaN(id); });
        if (!order.length) return;
        var csrf = document.querySelector('input[name="_token"]')?.value || '';
        fetch('{{ url('extra-categories') }}/' + categoryId + '/extras/reorder', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ order: order })
        }).catch(function() { /* ignore */ });
    }

    function initExtrasDragula() {
        if (typeof dragula === 'undefined') return;
        var lists = Array.from(document.querySelectorAll('.extras-group-list[data-category-id]'));
        if (!lists.length) return;
        dragula(lists, {
            moves: function(el, source, handle) {
                if (!el.classList.contains('extra-item-row') || el.classList.contains('service-empty-placeholder')) return false;
                return !!(handle && (handle.classList.contains('service-drag-handle') || handle.closest('.service-drag-handle')));
            },
            accepts: function(el, target, source) {
                return target === source;
            },
            direction: 'vertical',
            mirrorContainer: document.body
        }).on('drop', function(el, target) {
            persistExtrasOrder(target);
        });
    }
    initExtrasDragula();
});
</script>

<!-- Criar / editar categoria de extras — offcanvas -->
<div class="offcanvas offcanvas-end services-offcanvas" tabindex="-1" id="editExtraCategoryModal" aria-labelledby="editExtraCategoryOffcanvasLabel" data-bs-scroll="true" data-mode="edit">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-semibold mb-0" id="editExtraCategoryOffcanvasLabel">Editar categoria de extras</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <form id="editExtraCategoryForm" class="services-offcanvas__form">
        @csrf
        <input type="hidden" name="_method" id="editExtraCategoryMethod" value="PUT">
        <input type="hidden" name="category_id" id="editExtraCategoryModalId" value="">
        <div class="offcanvas-body">
            <div class="row g-3 mb-3">
                <div class="col-md-7">
                    <label class="form-label" for="editExtraCategoryName">Nome <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" id="editExtraCategoryName" required>
                </div>
                <div class="col-md-5">
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
            <div class="mb-3">
                <label class="form-label" for="editExtraCategoryDescription">Descrição</label>
                <textarea class="form-control" name="description" id="editExtraCategoryDescription" rows="2"></textarea>
            </div>
        </div>
        <div class="border-top services-offcanvas__footer d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="editExtraCategorySubmitBtn">Guardar</button>
        </div>
    </form>
</div>

<!-- Novo extra — offcanvas -->
<div class="offcanvas offcanvas-end services-offcanvas" tabindex="-1" id="addExtraModal" aria-labelledby="addExtraOffcanvasLabel" data-bs-scroll="true">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-semibold mb-0" id="addExtraOffcanvasLabel">Novo extra</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <form id="addExtraForm" class="services-offcanvas__form" action="{{ route('extras.store') }}" method="POST">
        @csrf
        <div class="offcanvas-body">
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
            <div class="mb-3 services-offcanvas__associated">
                @include('extras.partials.extra-services-association', [
                    'serviceCategories' => $serviceCategories,
                    'selectedServiceIds' => [],
                ])
            </div>
            @endif
        </div>
        <div class="border-top services-offcanvas__footer d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Cancelar</button>
            <button type="submit" class="btn btn-primary">Criar</button>
        </div>
    </form>
</div>

<!-- Editar extra — offcanvas -->
<div class="offcanvas offcanvas-end services-offcanvas" tabindex="-1" id="editExtraModal" aria-labelledby="editExtraOffcanvasLabel" data-bs-scroll="true">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-semibold mb-0" id="editExtraOffcanvasLabel">Editar extra</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <form id="editExtraModalForm" class="services-offcanvas__form" data-update-url="{{ url('extras') }}" method="POST">
        @csrf
        @method('PUT')
        <div class="offcanvas-body">
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
            <div class="mb-3 services-offcanvas__associated">
                @include('extras.partials.extra-services-association', [
                    'serviceCategories' => $serviceCategories,
                    'selectedServiceIds' => [],
                    'inputIdPrefix' => 'editExtra',
                ])
            </div>
            @endif
            <div class="services-offcanvas__danger-zone">
                <button type="button" class="btn btn-outline-danger" id="editExtraDeleteBtn">
                    <i class="ph ph-archive me-1"></i>Arquivar extra
                </button>
            </div>
        </div>
        <div class="border-top services-offcanvas__footer d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</div>

<!-- Modal confirmar arquivo de extra -->
<div class="modal fade" id="deleteExtraConfirmModal" tabindex="-1" aria-labelledby="deleteExtraConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="deleteExtraConfirmModalLabel">Arquivar extra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">O extra passa para o Arquivo. Marcações e vendas antigas mantêm-se ligadas a este extra.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="deleteExtraConfirmBtn">Arquivar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal eliminar categoria de extra -->
<div class="modal fade" id="deleteExtraCategoryConfirmModal" tabindex="-1" aria-labelledby="deleteExtraCategoryConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="deleteExtraCategoryConfirmModalLabel">Eliminar categoria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Tem a certeza que deseja eliminar esta categoria? Só é possível se não tiver extras (incluindo no Arquivo).</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="deleteExtraCategoryConfirmBtn">Eliminar</button>
            </div>
        </div>
    </div>
</div>

{{-- Membros do extra (todas as lojas) --}}
<div class="modal fade" id="extraMembersModal" tabindex="-1" aria-labelledby="extraMembersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="extraMembersModalLabel">Membros — <span id="extraMembersModalExtraName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" id="extraMembersModalBody"></div>
            <div class="modal-footer justify-content-between">
                <a href="{{ route('services.extras-equipa') }}" class="btn btn-link px-0">Gerir em Extras por equipa</a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

@endsection
