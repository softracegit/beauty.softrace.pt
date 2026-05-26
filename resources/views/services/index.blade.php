@extends('partials.layouts.main')
@section('title', 'Serviços | Beauty CRM')

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
    cursor: pointer;
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
    margin-right: 0.85rem;
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
.service-item-row--has-options .service-item-right {
    padding-top: 0.1rem;
}
.service-options-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.4rem;
}
.service-options-actions .btn-link {
    text-decoration: none;
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

<!-- Services Container -->
<div class="contacts-container">
    <!-- Mobile Sidebar Overlay -->
    <div class="contacts-sidebar-overlay" id="servicesSidebarOverlay"></div>

    <!-- Services Sidebar (Categories) -->
    <div class="contacts-sidebar" id="servicesSidebar">
        <!-- Mobile Close Button -->
        <button class="contacts-sidebar-close" id="servicesSidebarClose" aria-label="Close sidebar">
            <i class="ph ph-x"></i>
        </button>

        <div class="contacts-groups">
            <div class="contacts-groups-list" id="categoriesList" data-sortable="categories">
                <a href="#" class="contacts-group-item {{ !$selectedCategory ? 'active' : '' }}" data-category-id="all" data-category-name="Todas as categorias">
                    <span class="contacts-group-dot" style="background: var(--bs-secondary);"></span>
                    <span>Todas as categorias</span>
                </a>
                @forelse($categories as $category)
                    <a href="#" class="contacts-group-item {{ $selectedCategory && $selectedCategory->id === $category->id ? 'active' : '' }}" 
                       data-category-id="{{ $category->id }}" 
                       data-category-name="{{ $category->name }}"
                       data-category-color="{{ $category->color }}">
                        <span class="contacts-group-dot" style="background: {{ $category->color }};"></span>
                        <span>{{ $category->name }}</span>
                        <span class="badge">{{ $category->services_count ?? $category->services()->count() }}</span>
                    </a>
                @empty
                @endforelse
                @if($categories->isEmpty())
                    <div class="text-center text-muted py-3">
                        <p class="small mb-0">Nenhuma categoria criada ainda.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Services Main -->
    <div class="contacts-main">
        <!-- Services Top Bar: Pesquisa à esquerda, Botão Criar à direita -->
        <div class="services-top-bar contacts-header d-flex align-items-center gap-2 mb-3">
            <!-- Mobile Sidebar Toggle -->
            <button class="contacts-sidebar-toggle d-lg-none" type="button" id="servicesSidebarToggle" aria-label="Abrir lista de categorias">
                <i class="ph ph-list"></i>
            </button>
            
            <!-- Pesquisa à esquerda -->
            <div class="contacts-search flex-grow-1">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" class="form-control" placeholder="Pesquisar serviços..." id="serviceSearch">
            </div>
            
            <!-- Botão Criar à direita -->
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ph ph-plus me-2"></i>Criar
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addCategoryModal"><i class="ph ph-folder me-2"></i>Nova Categoria</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addServiceModal" id="pageHeaderAddService"><i class="ph ph-package me-2"></i>Novo Serviço</a></li>
                </ul>
            </div>
        </div>

        <!-- Services List -->
        <div class="services-list-container" id="servicesListContainer" data-selected-category-id="{{ $selectedCategory ? $selectedCategory->id : 'all' }}">
            @if($selectedCategory)
                <div class="services-category-header mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2" data-category-id="{{ $selectedCategory->id }}">
                    <h5 class="mb-0 services-category-title">{{ $selectedCategory->name }}</h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Opções
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item category-header-edit-btn" href="#"><i class="ph ph-pencil-simple me-2"></i>Editar</a></li>
                            <li><a class="dropdown-item category-header-add-service-btn" href="#"><i class="ph ph-plus me-2"></i>Adicionar serviço</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item category-header-delete-btn text-danger" href="#"><i class="ph ph-trash me-2"></i>Eliminar</a></li>
                        </ul>
                    </div>
                </div>
                <div id="servicesList" data-sortable="services" data-category-id="{{ $selectedCategory->id }}">
                    @include('services.partials.services-list', ['services' => $selectedCategory->services, 'category' => $selectedCategory])
                </div>
            @else
                @forelse($categories as $cat)
                    <div class="mb-4" data-category-block="{{ $cat->id }}">
                        <div class="services-category-header mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2" data-category-id="{{ $cat->id }}">
                            <h5 class="mb-0 services-category-title">{{ $cat->name }}</h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Opções</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item category-header-edit-btn" href="#"><i class="ph ph-pencil-simple me-2"></i>Editar</a></li>
                                    <li><a class="dropdown-item category-header-add-service-btn" href="#"><i class="ph ph-plus me-2"></i>Adicionar serviço</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item category-header-delete-btn text-danger" href="#"><i class="ph ph-trash me-2"></i>Eliminar</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="services-group-list" data-sortable="services" data-category-id="{{ $cat->id }}">
                            @include('services.partials.services-list', ['services' => $cat->services, 'category' => $cat])
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5">
                        <i class="ph-duotone ph-package" style="font-size: 4rem; color: var(--muted-color);"></i>
                        <p class="text-muted mt-3">Nenhum serviço criado ainda.</p>
                    </div>
                @endforelse
            @endif
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Categoria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCategoryForm">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="addCategoryColorSelect" class="form-label">Cor <span class="text-danger">*</span></label>
                        <select class="form-select" id="addCategoryColorSelect" name="color" required>
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

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Categoria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCategoryForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="category_id" id="editCategoryId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="editCategoryName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="description" id="editCategoryDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editCategoryColorSelect" class="form-label">Cor <span class="text-danger">*</span></label>
                        <select class="form-select" id="editCategoryColorSelect" name="color" required>
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

<!-- Add Service Modal -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Serviço</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addServiceForm">
                @csrf
                <input type="hidden" name="category_id" id="addServiceCategoryId" value="{{ $selectedCategory->id ?? '' }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Categoria <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" id="addServiceCategorySelect" required>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ $selectedCategory && $selectedCategory->id === $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="addServiceName" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="addServiceHasOptions" value="1" autocomplete="off">
                        <label class="form-check-label" for="addServiceHasOptions">Este serviço tem variantes (opções de preço e duração)</label>
                    </div>
                    <div id="addServiceSimplePricingWrap">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Duração (minutos) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="duration" id="addServiceDuration" value="60" min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Preço normal (€) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="price" id="addServicePrice" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Preço online (€)</label>
                            <input type="number" class="form-control" name="online_price" id="addServiceOnlinePrice" step="0.01" min="0" placeholder="Opcional, mais barato">
                            <div class="form-text">Preço para reservas online (deve ser ≤ preço normal).</div>
                        </div>
                    </div>
                    </div>
                    <div id="addServiceOptionsWrap" class="d-none mb-3">
                        <label class="form-label fw-semibold">Opções</label>
                        <p class="text-muted small mb-2">A primeira linha é a opção base: o nome pode ser livre (ex.: «Sem lavagem»); a duração e os preços desta linha definem os valores do serviço no catálogo. O preço online é obrigatório em todas as opções. «Desde» usa o menor preço online.</p>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm align-middle mb-0" id="addServiceOptionsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nome</th>
                                        <th style="width:7rem">Duração (min)</th>
                                        <th style="width:7rem">Preço (€)</th>
                                        <th style="width:7rem">Preço online (€)</th>
                                        <th style="width:7.5rem"></th>
                                    </tr>
                                </thead>
                                <tbody id="addServiceOptionsTbody"></tbody>
                            </table>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="addServiceAddOptionRow">Adicionar opção</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="addServiceClearOptionRows">Remover todas</button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Membros da Equipa</label>
                        <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                            <div class="form-check mb-2 pb-2 border-bottom">
                                <input class="form-check-input" type="checkbox" id="addServiceSelectAllAgents">
                                <label class="form-check-label fw-semibold" for="addServiceSelectAllAgents">
                                    Todos os membros
                                    <span class="badge bg-secondary ms-2">{{ count($agents) }}</span>
                                </label>
                            </div>
                            @forelse($agents as $agent)
                                <div class="form-check">
                                    <input class="form-check-input service-agent-checkbox" type="checkbox" name="agent_ids[]" value="{{ $agent->id }}" id="addServiceAgent{{ $agent->id }}">
                                    <label class="form-check-label" for="addServiceAgent{{ $agent->id }}">
                                        {{ $agent->name }}
                                    </label>
                                </div>
                            @empty
                                <p class="text-muted small mb-0">Nenhum agente disponível.</p>
                            @endforelse
                        </div>
                    </div>
                    @if(isset($extraCategories) && $extraCategories->isNotEmpty())
                    <div class="mb-3">
                        @include('services.partials.service-extras-association', [
                            'extraCategories' => $extraCategories,
                            'selectedExtraIds' => [],
                            'inputIdPrefix' => 'addService',
                        ])
                    </div>
                    @endif
                    @if(isset($fees) && $fees->isNotEmpty())
                    <div class="mb-3">
                        @include('services.partials.service-fees-association', [
                            'fees' => $fees,
                            'selectedFeeIds' => [],
                            'inputIdPrefix' => 'addService',
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

<!-- Edit Service Modal -->
<div class="modal fade" id="editServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Serviço</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editServiceForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="service_id" id="editServiceId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Categoria <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" id="editServiceCategoryId" required>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="editServiceName" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="description" id="editServiceDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="editServiceHasOptions" value="1" autocomplete="off">
                        <label class="form-check-label" for="editServiceHasOptions">Este serviço tem variantes (opções de preço e duração)</label>
                    </div>
                    <div id="editServiceSimplePricingWrap">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Duração (minutos) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="duration" id="editServiceDuration" min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Preço normal (€) <span class="text-danger">*</span> <span id="editServicePriceOriginal" class="text-muted text-decoration-line-through small ms-1" style="display:none;"></span></label>
                            <input type="number" class="form-control" name="price" id="editServicePrice" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Preço online (€)</label>
                            <input type="number" class="form-control" name="online_price" id="editServiceOnlinePrice" step="0.01" min="0" placeholder="Opcional">
                            <div class="form-text">Preço para reservas online (≤ preço normal).</div>
                        </div>
                    </div>
                    </div>
                    <div id="editServiceOptionsWrap" class="d-none mb-3">
                        <label class="form-label fw-semibold">Opções</label>
                        <p class="text-muted small mb-2">A opção base pode ter o nome que quiser; o nome do serviço acima é o título do catálogo. A base define duração e preços do serviço. Preço online obrigatório em todas as opções. «Desde» = menor preço online.</p>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm align-middle mb-0" id="editServiceOptionsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nome</th>
                                        <th style="width:7rem">Duração (min)</th>
                                        <th style="width:7rem">Preço (€)</th>
                                        <th style="width:7rem">Preço online (€)</th>
                                        <th style="width:7.5rem"></th>
                                    </tr>
                                </thead>
                                <tbody id="editServiceOptionsTbody"></tbody>
                            </table>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="editServiceAddOptionRow">Adicionar opção</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="editServiceClearOptionRows">Remover todas</button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Membros da Equipa</label>
                        <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                            <div class="form-check mb-2 pb-2 border-bottom">
                                <input class="form-check-input" type="checkbox" id="editServiceSelectAllAgents">
                                <label class="form-check-label fw-semibold" for="editServiceSelectAllAgents">
                                    Todos os membros
                                    <span class="badge bg-secondary ms-2">{{ count($agents) }}</span>
                                </label>
                            </div>
                            @forelse($agents as $agent)
                                <div class="form-check">
                                    <input class="form-check-input service-agent-checkbox-edit" type="checkbox" name="agent_ids[]" value="{{ $agent->id }}" id="editServiceAgent{{ $agent->id }}">
                                    <label class="form-check-label" for="editServiceAgent{{ $agent->id }}">
                                        {{ $agent->name }}
                                    </label>
                                </div>
                            @empty
                                <p class="text-muted small mb-0">Nenhum agente disponível.</p>
                            @endforelse
                        </div>
                    </div>
                    @if(isset($extraCategories) && $extraCategories->isNotEmpty())
                    <div class="mb-3" id="editServiceExtrasWrap">
                        @include('services.partials.service-extras-association', [
                            'extraCategories' => $extraCategories,
                            'selectedExtraIds' => [],
                            'inputIdPrefix' => 'editService',
                        ])
                    </div>
                    @endif
                    @if(isset($fees) && $fees->isNotEmpty())
                    <div class="mb-3" id="editServiceFeesWrap">
                        @include('services.partials.service-fees-association', [
                            'fees' => $fees,
                            'selectedFeeIds' => [],
                            'inputIdPrefix' => 'editService',
                        ])
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto" id="editServiceDeleteBtn">
                        <i class="ph ph-trash me-1"></i>Eliminar
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('js')
<script src="{{ asset('template/vendor/dragula/dragula.min.js') }}"></script>
<script src="{{ asset('js/services.js') }}"></script>
@endsection
