@extends('partials.layouts.main')
@section('title', 'Materiais | Beauty CRM')

@section('css')
<link href="{{ asset('template/vendor/remixicon/remixicon.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('template/vendor/dragula/dragula.min.css') }}">
<style>
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
.services-top-bar .contacts-search { flex: 1; max-width: none; }
.services-list-container { padding: 1.5rem 1.25rem 1.25rem 2.25rem; }
#materialsList, .materials-group-list { margin-left: -15px !important; }
.service-empty-placeholder .service-item::before { display: none; }
.service-empty-placeholder .service-item:hover { box-shadow: none; }
.category-color-choice .ri-circle-fill { font-size: 1rem; }
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
.services-category-title-edit { margin-left: 0.85rem; }
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
.services-category-options-btn.dropdown-toggle::after { margin-left: 0.3rem; vertical-align: 0.15em; }
.services-category-header .services-category-title { line-height: 1.35; margin-bottom: 0; }
.services-category-title-edit:hover,
.services-category-title-edit:focus,
.services-category-options-btn:hover,
.services-category-options-btn:focus {
    opacity: 1;
    color: var(--accent-color, #5b8def);
    background: color-mix(in srgb, var(--accent-color, #5b8def) 12%, transparent);
    text-decoration: none !important;
}
[data-category-block] + [data-category-block] { margin-top: 3.25rem; }
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
.contacts-group-item .services-category-sidebar-edit + .badge { margin-left: 0; }
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
.services-offcanvas .offcanvas-body .form-label:not(.small) .text-danger {
    letter-spacing: 0;
    text-transform: none;
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
#deleteMaterialConfirmModal,
#deleteMaterialCategoryConfirmModal {
    --bs-modal-zindex: 1085;
}
.material-item-row--archived .service-drag-handle {
    visibility: hidden;
    pointer-events: none;
}
.material-item-row--archived .service-item {
    cursor: default;
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
.service-item-row:hover .service-drag-handle { opacity: 1; }
.service-drag-handle:active { cursor: grabbing; }
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
    flex: 1;
    margin-bottom: 0;
    cursor: pointer;
    position: relative;
    --service-category-color: #6c757d;
}
.service-item-row .service-item > .card-body {
    padding-top: 0.75rem !important;
    padding-bottom: 0.75rem !important;
}
.service-item-row .service-item::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 6px;
    background: var(--service-category-color);
    border-radius: 4px 0 0 4px;
}
.service-item-row .service-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
.service-item-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--heading-color, #1e293b);
}
.material-swatch {
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 0.5rem;
    border: 1px solid rgba(0,0,0,0.12);
    flex-shrink: 0;
}
.material-swatch--photo {
    object-fit: cover;
    display: block;
}
.contacts-groups-list { padding: var(--spacing-sm) var(--spacing-sm) !important; }
.contacts-groups { padding: 0 !important; border-bottom: none !important; }
.contacts-groups-footer {
    padding: 0.35rem var(--spacing-sm) var(--spacing-sm);
    border-top: 1px solid var(--border-color, #e5e7eb);
}
.contacts-groups-footer .contacts-group-item { border-radius: var(--radius-md) !important; }
.contacts-groups-footer .contacts-group-footer-icon {
    width: 0.75rem;
    font-size: 0.95rem;
    color: var(--muted-color, #6b7280);
    flex-shrink: 0;
}
.contacts-groups-list .contacts-group-item { border-radius: var(--radius-md) !important; }
.contacts-groups-list .contacts-group-item:hover {
    color: var(--accent-color) !important;
    background: transparent !important;
}
.contacts-groups-list .contacts-group-item.active:hover {
    background: color-mix(in srgb, var(--accent-color), transparent 90%) !important;
}
.gu-mirror { opacity: 0.9; }
.gu-mirror .service-drag-handle { opacity: 1; }
</style>
@endsection

@section('content')
<div class="contacts-container">
    <div class="contacts-sidebar-overlay" id="materialsSidebarOverlay"></div>
    <div class="contacts-sidebar" id="materialsSidebar">
        <button class="contacts-sidebar-close d-lg-none" id="materialsSidebarClose" aria-label="Fechar"><i class="ph ph-x"></i></button>
        <div class="contacts-groups">
            <div class="contacts-groups-list">
                <a href="{{ route('materials.index') }}" class="contacts-group-item {{ !$selectedCategory && empty($viewArchive) ? 'active' : '' }}">
                    <span class="contacts-group-dot" style="background: var(--bs-secondary);"></span>
                    <span>Todas as categorias</span>
                    <span class="badge">{{ $categories->sum(fn($c) => $c->materials_count ?? 0) }}</span>
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('materials.index', ['category_id' => $cat->id]) }}" class="contacts-group-item {{ $selectedCategory && $selectedCategory->id === $cat->id ? 'active' : '' }}" data-category-id="{{ $cat->id }}">
                        <span class="contacts-group-dot" style="background: {{ $cat->color ?? '#6c757d' }};"></span>
                        <span>{{ $cat->name }}</span>
                        <button type="button" class="services-category-sidebar-edit material-category-edit-btn" title="Editar categoria" aria-label="Editar categoria" data-category-id="{{ $cat->id }}">
                            <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                        </button>
                        <span class="badge">{{ $cat->materials_count ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
            <div class="contacts-groups-footer">
                <a href="#" class="contacts-group-item" id="sidebarCreateMaterialCategoryBtn">
                    <i class="ph ph-plus-circle contacts-group-footer-icon" aria-hidden="true"></i>
                    <span>Nova categoria</span>
                </a>
                <a href="{{ route('materials.index', ['category_id' => 'archive']) }}" class="contacts-group-item {{ !empty($viewArchive) ? 'active' : '' }}" data-category-id="archive">
                    <i class="ph ph-archive contacts-group-footer-icon" aria-hidden="true"></i>
                    <span>Arquivo</span>
                    <span class="badge" id="archivedMaterialsBadge">{{ (int) ($archivedMaterialsCount ?? 0) }}</span>
                </a>
            </div>
        </div>
    </div>
    <div class="contacts-main">
        <div class="services-top-bar contacts-header d-flex align-items-center gap-2 mb-3">
            <button class="contacts-sidebar-toggle d-lg-none" type="button" id="materialsSidebarToggle" aria-label="Abrir categorias"><i class="ph ph-list"></i></button>
            <div class="contacts-search flex-grow-1">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" class="form-control" placeholder="Pesquisar materiais..." id="materialSearch">
            </div>
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="ph ph-plus me-2"></i>Criar</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" id="openCreateMaterialCategoryBtn"><i class="ph ph-folder me-2"></i>Nova categoria</a></li>
                    <li><a class="dropdown-item" href="#" id="pageHeaderAddMaterial"><i class="ph ph-palette me-2"></i>Novo material</a></li>
                </ul>
            </div>
        </div>
        <div class="services-list-container" id="materialsListContainer">
            @if(!empty($viewArchive))
                <div class="mb-4">
                    <div class="services-category-header mb-3 d-flex align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center min-w-0">
                            <h5 class="mb-0 services-category-title text-truncate">Arquivo</h5>
                        </div>
                    </div>
                    <div id="materialsList" class="materials-group-list" data-archived="1">
                        @forelse($archivedMaterials as $material)
                            @include('materials.partials.material-item', ['material' => $material, 'archived' => true])
                        @empty
                            <div class="service-item-row service-empty-placeholder">
                                <div class="service-drag-handle" style="visibility: hidden;" aria-hidden="true"></div>
                                <div class="card service-item">
                                    <div class="card-body py-2">
                                        <p class="text-muted small mb-0">Nenhum material no Arquivo.</p>
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
                            <button type="button" class="services-category-title-edit material-category-edit-btn" title="Editar categoria" aria-label="Editar categoria">
                                <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                            </button>
                            <div class="dropdown">
                                <button class="services-category-options-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Opções</button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item material-category-edit-btn" href="#"><i class="ph ph-pencil-simple me-2"></i>Editar categoria</a></li>
                                    <li><a class="dropdown-item material-category-add-btn" href="#"><i class="ph ph-plus me-2"></i>Adicionar material</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item material-category-delete-btn text-danger" href="#"><i class="ph ph-trash me-2"></i>Eliminar</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div id="materialsList" class="materials-group-list" data-category-id="{{ $selectedCategory->id }}">
                        @forelse($materials as $material)
                            @include('materials.partials.material-item', ['material' => $material, 'categoryColor' => $selectedCategory->color])
                        @empty
                            <div class="service-item-row service-empty-placeholder">
                                <div class="service-drag-handle" style="visibility: hidden;" aria-hidden="true"></div>
                                <div class="card service-item">
                                    <div class="card-body py-2">
                                        <p class="text-muted small mb-0">Nenhum material nesta categoria. <a href="#" class="material-category-add-btn">Criar material</a></p>
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
                                <button type="button" class="services-category-title-edit material-category-edit-btn" title="Editar categoria" aria-label="Editar categoria">
                                    <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                                </button>
                                <div class="dropdown">
                                    <button class="services-category-options-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Opções</button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item material-category-edit-btn" href="#"><i class="ph ph-pencil-simple me-2"></i>Editar categoria</a></li>
                                        <li><a class="dropdown-item material-category-add-btn" href="#"><i class="ph ph-plus me-2"></i>Adicionar material</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item material-category-delete-btn text-danger" href="#"><i class="ph ph-trash me-2"></i>Eliminar</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="materials-group-list" data-category-id="{{ $cat->id }}">
                            @forelse($cat->materials as $material)
                                @include('materials.partials.material-item', ['material' => $material, 'categoryColor' => $cat->color])
                            @empty
                                <div class="service-item-row service-empty-placeholder">
                                    <div class="service-drag-handle" style="visibility: hidden;" aria-hidden="true"></div>
                                    <div class="card service-item">
                                        <div class="card-body py-2">
                                            <p class="text-muted small mb-0">Nenhum material nesta categoria. <a href="#" class="material-category-add-btn">Criar material</a></p>
                                        </div>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="ph-duotone ph-palette" style="font-size: 3rem;"></i>
                        <p class="mt-3 mb-0">Nenhuma categoria criada ainda.</p>
                        <a href="#" id="emptyStateCreateMaterialCategoryBtn" class="btn btn-outline-primary btn-sm mt-3">Nova categoria</a>
                    </div>
                @endforelse
            @endif
        </div>
    </div>
</div>

{{-- Categoria --}}
<div class="offcanvas offcanvas-end services-offcanvas" tabindex="-1" id="editMaterialCategoryModal" aria-labelledby="editMaterialCategoryOffcanvasLabel" data-bs-scroll="true" data-mode="edit">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-semibold mb-0" id="editMaterialCategoryOffcanvasLabel">Editar categoria de materiais</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <form id="editMaterialCategoryForm" class="services-offcanvas__form">
        @csrf
        <input type="hidden" name="_method" id="editMaterialCategoryMethod" value="PUT">
        <input type="hidden" id="editMaterialCategoryModalId" value="">
        <div class="offcanvas-body">
            <div class="row g-3 mb-3">
                <div class="col-md-7">
                    <label class="form-label" for="editMaterialCategoryName">Nome <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" id="editMaterialCategoryName" required>
                </div>
                <div class="col-md-5">
                    <label for="editMaterialCategoryColorSelect" class="form-label">Cor <span class="text-danger">*</span></label>
                    <select class="form-select" id="editMaterialCategoryColorSelect" name="color" required>
                        <option value="">Selecionar cor...</option>
                        @include('partials.category-color-options')
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="editMaterialCategoryDescription">Descrição</label>
                <textarea class="form-control" name="description" id="editMaterialCategoryDescription" rows="2"></textarea>
            </div>
        </div>
        <div class="border-top services-offcanvas__footer d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="editMaterialCategorySubmitBtn">Guardar</button>
        </div>
    </form>
</div>

{{-- Material --}}
<div class="offcanvas offcanvas-end services-offcanvas" tabindex="-1" id="materialOffcanvas" aria-labelledby="materialOffcanvasLabel" data-bs-scroll="true">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-semibold mb-0" id="materialOffcanvasLabel">Novo material</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <form id="materialForm" class="services-offcanvas__form" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="materialId" value="">
        <input type="hidden" id="materialRemovePhoto" value="0">
        <div class="offcanvas-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="materialName">Nome <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="materialName" name="name" required maxlength="255" placeholder="Ex: Ruby Red">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="materialCategoryId">Categoria <span class="text-danger">*</span></label>
                    <select class="form-select" id="materialCategoryId" name="material_category_id" required>
                        <option value="">— Selecionar categoria —</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ $selectedCategory && $selectedCategory->id === $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="materialBrand">Marca</label>
                    <input type="text" class="form-control" id="materialBrand" name="brand" maxlength="128" placeholder="Ex: Gelish">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="materialCode">Referência</label>
                    <input type="text" class="form-control" id="materialCode" name="code" maxlength="64" placeholder="Ex: RR-01">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="materialHex">Cor <span class="text-muted fw-normal">(opcional)</span></label>
                <div class="d-flex align-items-center gap-2">
                    <input type="color" class="form-control form-control-color" id="materialHexPicker" value="#bfdbfe" title="Escolher cor" aria-label="Escolher cor">
                    <input type="text" class="form-control" id="materialHex" name="hex" maxlength="7" placeholder="#RRGGBB" pattern="^#[0-9A-Fa-f]{6}$">
                    <button type="button" class="btn btn-light" id="materialHexClear" title="Limpar cor">Limpar</button>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="materialFinish">Acabamento</label>
                <select class="form-select" id="materialFinish" name="finish">
                    <option value="">— Sem acabamento —</option>
                    @foreach(\App\Models\Material::finishOptions() as $finishKey)
                        <option value="{{ $finishKey }}">{{ \App\Models\Material::finishLabel($finishKey) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="materialStock">Stock</label>
                <input type="number" class="form-control" id="materialStock" name="stock" min="0" max="999999" step="1" placeholder="Quantidade actual">
                <div class="form-text">Opcional. Inventário à mão — o consumo automático pela ficha fica para mais tarde.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="materialPhoto">Foto</label>
                <input type="file" class="form-control" id="materialPhoto" name="photo" accept="image/jpeg,image/png,image/webp">
                <div class="d-flex align-items-center gap-3 mt-2 d-none" id="materialPhotoPreviewWrap">
                    <img src="" alt="" id="materialPhotoPreview" class="rounded border" style="width: 64px; height: 64px; object-fit: cover;">
                    <button type="button" class="btn btn-sm btn-light" id="materialPhotoClear">Remover foto</button>
                </div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="materialActive" checked>
                <label class="form-check-label" for="materialActive">Activo</label>
            </div>
            <div class="mb-3 d-none" id="materialUsedInWrap">
                <div class="form-label mb-1">Usado em</div>
                <p class="small text-muted mb-0" id="materialUsedInEmpty">Não está associado a nenhum campo da ficha técnica.</p>
                <ul class="list-unstyled small mb-0 d-none" id="materialUsedInList"></ul>
            </div>
            <div class="services-offcanvas__danger-zone d-none" id="materialDangerZone">
                <button type="button" class="btn btn-outline-danger" id="materialDeleteBtn">
                    <i class="ph ph-archive me-1"></i>Arquivar material
                </button>
            </div>
        </div>
        <div class="border-top services-offcanvas__footer d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="materialSaveBtn">Guardar</button>
        </div>
    </form>
</div>

<div class="modal fade" id="deleteMaterialConfirmModal" tabindex="-1" aria-labelledby="deleteMaterialConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="deleteMaterialConfirmModalLabel">Arquivar material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">O material passa para o Arquivo e deixa de aparecer no catálogo activo. Pode restaurá-lo depois.</p>
                <p class="small text-muted mt-2 mb-0 d-none" id="deleteMaterialConfirmUsedIn"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="deleteMaterialConfirmBtn">Arquivar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteMaterialCategoryConfirmModal" tabindex="-1" aria-labelledby="deleteMaterialCategoryConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="deleteMaterialCategoryConfirmModalLabel">Eliminar categoria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Tem a certeza que deseja eliminar esta categoria? Só é possível se não tiver materiais.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="deleteMaterialCategoryConfirmBtn">Eliminar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="{{ asset('template/vendor/dragula/dragula.min.js') }}"></script>
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let pendingMaterialCategoryDeleteId = null;
    function toast(msg, type) {
        if (typeof window.showToast === 'function') window.showToast(msg, type || 'error');
        else if (type === 'error') console.error(msg);
    }

    // Sidebar mobile
    document.getElementById('materialsSidebarToggle')?.addEventListener('click', function () {
        document.getElementById('materialsSidebar')?.classList.add('show');
        document.getElementById('materialsSidebarOverlay')?.classList.add('show');
    });
    function closeSidebar() {
        document.getElementById('materialsSidebar')?.classList.remove('show');
        document.getElementById('materialsSidebarOverlay')?.classList.remove('show');
    }
    document.getElementById('materialsSidebarClose')?.addEventListener('click', closeSidebar);
    document.getElementById('materialsSidebarOverlay')?.addEventListener('click', closeSidebar);

    // Category color Choices
    var materialCategoryColorChoices = null;
    if (typeof Choices !== 'undefined') {
        function addClassesToElement(el, classes) {
            (Array.isArray(classes) ? classes : [classes]).forEach(function (c) { if (c) el.classList.add(c); });
        }
        function categoryColorChoiceTemplate(templateOptions, data) {
            var cn = templateOptions.classNames;
            var rawValue = typeof data.value === 'string' ? data.value : String(data.value || '');
            var div = document.createElement('div');
            div.id = data.elementId || '';
            addClassesToElement(div, cn.item);
            addClassesToElement(div, cn.itemChoice);
            div.innerHTML = '<span class="category-color-choice d-inline-flex align-items-center gap-2"><i class="ri-circle-fill" style="color:' + rawValue.replace(/"/g, '&quot;') + '"></i> ' + (data.label || '').replace(/</g, '&lt;').replace(/&/g, '&amp;') + '</span>';
            if (data.selected) addClassesToElement(div, cn.selectedState);
            if (data.placeholder) addClassesToElement(div, cn.placeholder);
            div.setAttribute('role', 'option');
            div.dataset.choice = '';
            div.dataset.id = String(data.id != null ? data.id : '');
            div.dataset.value = rawValue;
            if (data.disabled) {
                addClassesToElement(div, cn.itemDisabled);
                div.dataset.choiceDisabled = '';
            } else {
                addClassesToElement(div, cn.itemSelectable);
                div.dataset.choiceSelectable = '';
            }
            return div;
        }
        function categoryColorItemTemplate(templateOptions, data) {
            var cn = templateOptions.classNames;
            var rawValue = typeof data.value === 'string' ? data.value : String(data.value || '');
            var div = document.createElement('div');
            addClassesToElement(div, cn.item);
            div.innerHTML = rawValue
                ? '<span class="category-color-choice d-inline-flex align-items-center gap-2"><i class="ri-circle-fill" style="color:' + rawValue.replace(/"/g, '&quot;') + '"></i> ' + (data.label || '').replace(/</g, '&lt;').replace(/&/g, '&amp;') + '</span>'
                : (templateOptions.placeholderValue || 'Selecionar cor...');
            div.dataset.item = '';
            div.dataset.id = String(data.id != null ? data.id : '');
            div.dataset.value = rawValue;
            addClassesToElement(div, data.highlighted ? cn.highlightedState : cn.itemSelectable);
            return div;
        }
        var colorEl = document.getElementById('editMaterialCategoryColorSelect');
        if (colorEl && !colorEl.closest('.choices')) {
            materialCategoryColorChoices = new Choices(colorEl, {
                searchEnabled: false,
                itemSelectText: '',
                shouldSort: false,
                allowHTML: true,
                callbackOnCreateTemplates: function () {
                    return { choice: categoryColorChoiceTemplate, item: categoryColorItemTemplate };
                }
            });
        }
    }

    function setCategoryMode(mode) {
        var panel = document.getElementById('editMaterialCategoryModal');
        var title = document.getElementById('editMaterialCategoryOffcanvasLabel');
        var methodInput = document.getElementById('editMaterialCategoryMethod');
        var submitBtn = document.getElementById('editMaterialCategorySubmitBtn');
        panel?.setAttribute('data-mode', mode);
        if (mode === 'create') {
            if (title) title.textContent = 'Nova categoria de materiais';
            if (methodInput) methodInput.value = 'POST';
            if (submitBtn) submitBtn.textContent = 'Criar';
        } else {
            if (title) title.textContent = 'Editar categoria de materiais';
            if (methodInput) methodInput.value = 'PUT';
            if (submitBtn) submitBtn.textContent = 'Guardar';
        }
    }

    function resetCategoryForm() {
        document.getElementById('editMaterialCategoryForm')?.reset();
        document.getElementById('editMaterialCategoryModalId').value = '';
        var colorSelect = document.getElementById('editMaterialCategoryColorSelect');
        if (colorSelect) colorSelect.value = '';
        if (materialCategoryColorChoices?.setChoiceByValue) materialCategoryColorChoices.setChoiceByValue('');
    }

    function openCreateCategory() {
        setCategoryMode('create');
        resetCategoryForm();
        bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('editMaterialCategoryModal')).show();
    }

    function openEditCategory(categoryId) {
        fetch('/material-categories/' + encodeURIComponent(categoryId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (cat) {
                setCategoryMode('edit');
                document.getElementById('editMaterialCategoryModalId').value = cat.id;
                document.getElementById('editMaterialCategoryName').value = cat.name || '';
                document.getElementById('editMaterialCategoryDescription').value = cat.description || '';
                var colorSelect = document.getElementById('editMaterialCategoryColorSelect');
                if (colorSelect) colorSelect.value = cat.color || '';
                if (materialCategoryColorChoices?.setChoiceByValue) {
                    materialCategoryColorChoices.setChoiceByValue(cat.color || '');
                }
                bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('editMaterialCategoryModal')).show();
            })
            .catch(function () { alert('Erro ao carregar categoria.'); });
    }

    document.getElementById('openCreateMaterialCategoryBtn')?.addEventListener('click', function (e) {
        e.preventDefault();
        openCreateCategory();
    });
    document.getElementById('sidebarCreateMaterialCategoryBtn')?.addEventListener('click', function (e) {
        e.preventDefault();
        openCreateCategory();
    });
    document.getElementById('emptyStateCreateMaterialCategoryBtn')?.addEventListener('click', function (e) {
        e.preventDefault();
        openCreateCategory();
    });

    document.getElementById('materialsListContainer')?.addEventListener('click', function (e) {
        var editBtn = e.target.closest('.material-category-edit-btn');
        if (editBtn) {
            e.preventDefault();
            e.stopPropagation();
            var header = editBtn.closest('[data-category-id]') || editBtn.closest('.contacts-group-item');
            var categoryId = editBtn.getAttribute('data-category-id')
                || header?.getAttribute('data-category-id')
                || editBtn.closest('.services-category-header')?.getAttribute('data-category-id');
            if (categoryId) openEditCategory(categoryId);
            return;
        }
        var addBtn = e.target.closest('.material-category-add-btn');
        if (addBtn) {
            e.preventDefault();
            var block = addBtn.closest('[data-category-block]') || addBtn.closest('.services-category-header');
            var catId = block?.getAttribute('data-category-id') || block?.getAttribute('data-category-block');
            openCreateMaterial(catId || '');
            return;
        }
        var delBtn = e.target.closest('.material-category-delete-btn');
        if (delBtn) {
            e.preventDefault();
            var hdr = delBtn.closest('.services-category-header');
            var delId = hdr?.getAttribute('data-category-id');
            if (!delId) return;
            pendingMaterialCategoryDeleteId = delId;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteMaterialCategoryConfirmModal')).show();
        }
    });

    document.getElementById('deleteMaterialCategoryConfirmBtn')?.addEventListener('click', function () {
        var delId = pendingMaterialCategoryDeleteId;
        if (!delId) return;
        pendingMaterialCategoryDeleteId = null;
        bootstrap.Modal.getInstance(document.getElementById('deleteMaterialCategoryConfirmModal'))?.hide();
        fetch('/material-categories/' + encodeURIComponent(delId), {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json().then(function (res) { return { ok: r.ok, res: res }; }); })
            .then(function (x) {
                if (!x.ok) throw new Error((x.res && x.res.message) || 'Erro ao eliminar.');
                window.location.href = '{{ route('materials.index') }}';
            })
            .catch(function (err) { toast(err.message || 'Erro ao eliminar.', 'error'); });
    });

    document.querySelector('.contacts-groups-list')?.addEventListener('click', function (e) {
        var btn = e.target.closest('.material-category-edit-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var id = btn.getAttribute('data-category-id');
        if (id) openEditCategory(id);
    });

    document.getElementById('editMaterialCategoryForm')?.addEventListener('submit', function (e) {
        e.preventDefault();
        var mode = document.getElementById('editMaterialCategoryModal')?.getAttribute('data-mode');
        var categoryId = document.getElementById('editMaterialCategoryModalId').value;
        var payload = {
            name: document.getElementById('editMaterialCategoryName').value.trim(),
            description: document.getElementById('editMaterialCategoryDescription').value.trim() || null,
            color: document.getElementById('editMaterialCategoryColorSelect').value || null,
        };
        var url = mode === 'create' ? '/material-categories' : '/material-categories/' + encodeURIComponent(categoryId);
        var method = mode === 'create' ? 'POST' : 'PUT';
        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        })
            .then(function (r) { return r.json().then(function (res) { return { ok: r.ok, res: res }; }); })
            .then(function (x) {
                if (!x.ok) throw new Error((x.res && x.res.errors && Object.values(x.res.errors)[0][0]) || (x.res && x.res.message) || 'Erro ao guardar.');
                window.location.reload();
            })
            .catch(function (err) { alert(err.message || 'Erro ao guardar.'); });
    });

    // Material CRUD
    const panel = document.getElementById('materialOffcanvas');
    const form = document.getElementById('materialForm');
    const title = document.getElementById('materialOffcanvasLabel');
    const idEl = document.getElementById('materialId');
    const catEl = document.getElementById('materialCategoryId');
    const nameEl = document.getElementById('materialName');
    const codeEl = document.getElementById('materialCode');
    const brandEl = document.getElementById('materialBrand');
    const finishEl = document.getElementById('materialFinish');
    const stockEl = document.getElementById('materialStock');
    const photoEl = document.getElementById('materialPhoto');
    const removePhotoEl = document.getElementById('materialRemovePhoto');
    const photoPreviewWrap = document.getElementById('materialPhotoPreviewWrap');
    const photoPreview = document.getElementById('materialPhotoPreview');
    const hexEl = document.getElementById('materialHex');
    const hexPicker = document.getElementById('materialHexPicker');
    const activeEl = document.getElementById('materialActive');
    const danger = document.getElementById('materialDangerZone');
    const usedInWrap = document.getElementById('materialUsedInWrap');
    const usedInList = document.getElementById('materialUsedInList');
    const usedInEmpty = document.getElementById('materialUsedInEmpty');
    const deleteUsedInHint = document.getElementById('deleteMaterialConfirmUsedIn');
    let materialUsedInCache = [];

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatUsedInLabel(item) {
        const cat = item && item.category_name ? String(item.category_name) : 'Categoria';
        const field = item && item.field_label ? String(item.field_label) : (item && item.field_key ? String(item.field_key) : 'Campo');
        return cat + ' → ' + field;
    }

    function renderMaterialUsedIn(usages) {
        materialUsedInCache = Array.isArray(usages) ? usages : [];
        if (!usedInWrap) return;
        usedInWrap.classList.remove('d-none');
        if (!materialUsedInCache.length) {
            usedInEmpty?.classList.remove('d-none');
            usedInList?.classList.add('d-none');
            if (usedInList) usedInList.innerHTML = '';
            return;
        }
        usedInEmpty?.classList.add('d-none');
        usedInList?.classList.remove('d-none');
        if (usedInList) {
            usedInList.innerHTML = materialUsedInCache.map(function (item) {
                return '<li class="mb-1"><span class="text-body">' + escapeHtml(formatUsedInLabel(item)) + '</span></li>';
            }).join('');
        }
    }

    function clearMaterialUsedIn() {
        materialUsedInCache = [];
        usedInWrap?.classList.add('d-none');
        usedInEmpty?.classList.remove('d-none');
        usedInList?.classList.add('d-none');
        if (usedInList) usedInList.innerHTML = '';
        if (deleteUsedInHint) {
            deleteUsedInHint.textContent = '';
            deleteUsedInHint.classList.add('d-none');
        }
    }

    function resetPhotoUi() {
        if (photoEl) photoEl.value = '';
        if (removePhotoEl) removePhotoEl.value = '0';
        if (photoPreview) photoPreview.src = '';
        photoPreviewWrap?.classList.add('d-none');
    }
    function showPhotoPreview(url) {
        if (!url || !photoPreview || !photoPreviewWrap) {
            resetPhotoUi();
            return;
        }
        photoPreview.src = url;
        photoPreviewWrap.classList.remove('d-none');
        if (removePhotoEl) removePhotoEl.value = '0';
    }

    function normalizeHex(v) {
        v = String(v || '').trim();
        if (!v) return '';
        if (/^[0-9A-Fa-f]{6}$/.test(v)) v = '#' + v;
        if (/^#[0-9A-Fa-f]{6}$/.test(v)) return v.toLowerCase();
        return v;
    }
    function syncPickerFromText() {
        const hex = normalizeHex(hexEl.value);
        if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
            hexPicker.value = hex;
            hexEl.value = hex;
        }
    }

    function openCreateMaterial(categoryId) {
        idEl.value = '';
        form.reset();
        activeEl.checked = true;
        hexEl.value = '';
        hexPicker.value = '#bfdbfe';
        if (stockEl) stockEl.value = '';
        resetPhotoUi();
        clearMaterialUsedIn();
        danger.classList.add('d-none');
        title.textContent = 'Novo material';
        if (categoryId) {
            catEl.value = String(categoryId);
        } else if (!catEl.value) {
            @if($selectedCategory)
            catEl.value = '{{ $selectedCategory->id }}';
            @endif
        }
        bootstrap.Offcanvas.getOrCreateInstance(panel).show();
        requestAnimationFrame(function () { nameEl.focus(); });
    }

    function openEditMaterial(id) {
        fetch('/materials/' + encodeURIComponent(id), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                idEl.value = String(data.id);
                catEl.value = String(data.material_category_id || '');
                nameEl.value = data.name || '';
                codeEl.value = data.code || '';
                brandEl.value = data.brand || '';
                finishEl.value = data.finish || '';
                hexEl.value = data.hex || '';
                if (stockEl) stockEl.value = data.stock != null ? String(data.stock) : '';
                syncPickerFromText();
                if (!hexEl.value) hexPicker.value = '#bfdbfe';
                if (photoEl) photoEl.value = '';
                showPhotoPreview(data.photo_url || '');
                activeEl.checked = data.is_active !== false;
                renderMaterialUsedIn(data.used_in || []);
                danger.classList.remove('d-none');
                title.textContent = 'Editar material';
                bootstrap.Offcanvas.getOrCreateInstance(panel).show();
            })
            .catch(function () { alert('Erro ao carregar material.'); });
    }

    document.getElementById('pageHeaderAddMaterial')?.addEventListener('click', function (e) {
        e.preventDefault();
        openCreateMaterial(@json($selectedCategory?->id));
    });

    document.getElementById('materialsListContainer')?.addEventListener('click', function (e) {
        var restoreBtn = e.target.closest('.material-archive-restore-btn');
        if (restoreBtn) {
            e.preventDefault();
            e.stopPropagation();
            var restoreId = restoreBtn.getAttribute('data-restore-material-id');
            if (!restoreId) return;
            fetch('/materials/' + encodeURIComponent(restoreId) + '/restore', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (r) { return r.json().then(function (res) { return { ok: r.ok, res: res }; }); })
                .then(function (x) {
                    if (!x.ok) throw new Error((x.res && x.res.message) || 'Erro ao restaurar.');
                    window.location.reload();
                })
                .catch(function (err) { alert(err.message || 'Erro ao restaurar.'); });
            return;
        }
        if (e.target.closest('.service-drag-handle')) return;
        if (e.target.closest('.material-category-edit-btn, .material-category-add-btn, .material-category-delete-btn, .services-category-options-btn, .dropdown-menu')) return;
        var row = e.target.closest('.material-item-row[data-material-id]:not([data-archived])');
        if (row) openEditMaterial(row.getAttribute('data-material-id'));
    });

    hexPicker?.addEventListener('input', function () { hexEl.value = hexPicker.value; });
    hexEl?.addEventListener('change', syncPickerFromText);
    document.getElementById('materialHexClear')?.addEventListener('click', function () {
        hexEl.value = '';
        hexPicker.value = '#bfdbfe';
    });
    photoEl?.addEventListener('change', function () {
        var file = this.files && this.files[0];
        if (!file) return;
        if (removePhotoEl) removePhotoEl.value = '0';
        showPhotoPreview(URL.createObjectURL(file));
    });
    document.getElementById('materialPhotoClear')?.addEventListener('click', function () {
        if (photoEl) photoEl.value = '';
        if (removePhotoEl) removePhotoEl.value = '1';
        if (photoPreview) photoPreview.src = '';
        photoPreviewWrap?.classList.add('d-none');
    });

    document.getElementById('materialSearch')?.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        document.querySelectorAll('.material-item-row').forEach(function (row) {
            row.classList.toggle('d-none', q !== '' && row.textContent.toLowerCase().indexOf(q) === -1);
        });
    });

    form?.addEventListener('submit', function (e) {
        e.preventDefault();
        var id = idEl.value;
        var hex = normalizeHex(hexEl.value) || '';
        if (!catEl.value) {
            toast('Selecione uma categoria.', 'error');
            return;
        }
        if (hex && !/^#[0-9a-fA-F]{6}$/.test(hex)) {
            toast('Cor inválida. Use o formato #RRGGBB.', 'error');
            return;
        }
        var fd = new FormData();
        fd.append('material_category_id', String(catEl.value));
        fd.append('name', nameEl.value.trim());
        fd.append('code', codeEl.value.trim());
        fd.append('brand', (brandEl.value || '').trim());
        fd.append('finish', finishEl.value || '');
        fd.append('hex', hex);
        fd.append('stock', stockEl && stockEl.value !== '' ? String(stockEl.value) : '');
        fd.append('is_active', activeEl.checked ? '1' : '0');
        if (photoEl && photoEl.files && photoEl.files[0]) {
            fd.append('photo', photoEl.files[0]);
        }
        if (id && removePhotoEl && removePhotoEl.value === '1') {
            fd.append('remove_photo', '1');
        }
        var url = '/materials';
        var method = 'POST';
        if (id) {
            url = '/materials/' + encodeURIComponent(id);
            fd.append('_method', 'PUT');
        }
        fetch(url, {
            method: method,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: fd,
        })
            .then(function (r) { return r.json().then(function (res) { return { ok: r.ok, res: res }; }); })
            .then(function (x) {
                if (!x.ok) {
                    var msg = (x.res && x.res.message) || 'Não foi possível guardar.';
                    if (x.res && x.res.errors) msg = Object.values(x.res.errors)[0][0] || msg;
                    throw new Error(msg);
                }
                window.location.reload();
            })
            .catch(function (err) { toast(err.message || 'Erro ao guardar.', 'error'); });
    });

    document.getElementById('materialDeleteBtn')?.addEventListener('click', function () {
        if (!idEl.value) return;
        if (deleteUsedInHint) {
            if (materialUsedInCache.length) {
                var labels = materialUsedInCache.map(formatUsedInLabel);
                deleteUsedInHint.textContent = 'Usado em: ' + labels.join(' · ') + '. As fichas antigas mantêm o valor; deixa de aparecer para escolhas novas.';
                deleteUsedInHint.classList.remove('d-none');
            } else {
                deleteUsedInHint.textContent = '';
                deleteUsedInHint.classList.add('d-none');
            }
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteMaterialConfirmModal')).show();
    });
    document.getElementById('deleteMaterialConfirmBtn')?.addEventListener('click', function () {
        var id = idEl.value;
        if (!id) return;
        var btn = this;
        btn.disabled = true;
        fetch('/materials/' + encodeURIComponent(id), {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { return r.json().then(function (res) { return { ok: r.ok, res: res }; }); })
            .then(function (x) {
                if (!x.ok) throw new Error((x.res && x.res.message) || 'Erro ao arquivar.');
                window.location.reload();
            })
            .catch(function (err) {
                btn.disabled = false;
                toast(err.message || 'Erro ao arquivar.', 'error');
            });
    });

    // Drag reorder
    function persistMaterialsOrder(listEl) {
        if (!listEl) return;
        var categoryId = listEl.getAttribute('data-category-id');
        if (!categoryId) return;
        var order = Array.prototype.map.call(listEl.querySelectorAll('.material-item-row[data-material-id]'), function (row) {
            return Number(row.getAttribute('data-material-id'));
        }).filter(Boolean);
        if (!order.length) return;
        fetch('/material-categories/' + encodeURIComponent(categoryId) + '/materials/reorder', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ order: order }),
        }).catch(function () {});
    }

    function initMaterialsDragula() {
        if (typeof dragula === 'undefined') return;
        var lists = Array.prototype.slice.call(document.querySelectorAll('.materials-group-list[data-category-id]'));
        if (!lists.length) return;
        dragula(lists, {
            moves: function (el, source, handle) {
                return !!(handle && handle.closest('.service-drag-handle'));
            },
            accepts: function (el, target, source) {
                return target === source;
            },
        }).on('drop', function (el, target) {
            persistMaterialsOrder(target);
        });
    }
    initMaterialsDragula();
})();
</script>
@endsection
