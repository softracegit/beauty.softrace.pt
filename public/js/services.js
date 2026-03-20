/**
 * Serviços: lista por categoria, CRUD por AJAX.
 * Fluxo: 1) Selecionar categoria → GET /categories/{id}/services (lista).
 *        2) Criar/Editar/Eliminar serviço → POST/PUT/DELETE → depois: refresh lista (1 GET) + atualizar badges (1 GET /categories).
 */
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const container = document.getElementById('servicesListContainer');
    let selectedCategoryId = container?.getAttribute('data-selected-category-id') || document.querySelector('.contacts-group-item.active[data-category-id]')?.getAttribute('data-category-id') || 'all';
    if (selectedCategoryId === '') selectedCategoryId = 'all';
    let servicesDrake = null;       // vista de uma categoria (#servicesList)
    let servicesDrakesAll = [];     // vista "Todas" (vários .services-group-list)

    /** Após criar/editar/eliminar serviço: atualizar lista da categoria atual e badges. */
    function refreshAfterServiceChange() {
        if (selectedCategoryId === 'all') loadAllServices();
        else if (selectedCategoryId) loadServices(selectedCategoryId);
        updateCategoryBadges();
    }

    // --- Categorias: selects com cor (Choices.js) --- with custom templates so each option shows ri-circle-fill icon inside.
    // Templates must return DOM elements and set the same data-* / role / classes as default.
    function addClassesToElement(el, classes) {
        const arr = Array.isArray(classes) ? classes : [classes];
        arr.forEach(c => c && el.classList.add(c));
    }
    function categoryColorChoiceTemplate(templateOptions, data, itemSelectText, groupName) {
        const cn = templateOptions.classNames;
        const rawValue = typeof data.value === 'string' ? data.value : String(data.value || '');
        const div = document.createElement('div');
        div.id = data.elementId || '';
        addClassesToElement(div, cn.item);
        addClassesToElement(div, cn.itemChoice);
        div.innerHTML = '<span class="category-color-choice d-inline-flex align-items-center gap-2"><i class="ri-circle-fill" style="color:' + rawValue.replace(/"/g, '&quot;') + '"></i> ' + (data.label || '').replace(/</g, '&lt;').replace(/&/g, '&amp;') + '</span>';
        if (data.selected) addClassesToElement(div, cn.selectedState);
        if (data.placeholder) addClassesToElement(div, cn.placeholder);
        div.setAttribute('role', data.group ? 'treeitem' : 'option');
        div.dataset.choice = '';
        div.dataset.id = String(data.id ?? '');
        div.dataset.value = rawValue;
        if (itemSelectText) div.dataset.selectText = itemSelectText;
        if (data.group) div.dataset.groupId = String(data.group.id ?? '');
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
        const cn = templateOptions.classNames;
        const rawValue = typeof data.value === 'string' ? data.value : String(data.value || '');
        const div = document.createElement('div');
        addClassesToElement(div, cn.item);
        div.innerHTML = rawValue ? '<span class="category-color-choice d-inline-flex align-items-center gap-2"><i class="ri-circle-fill" style="color:' + rawValue.replace(/"/g, '&quot;') + '"></i> ' + (data.label || '').replace(/</g, '&lt;').replace(/&/g, '&amp;') + '</span>' : (templateOptions.placeholderValue || 'Selecionar cor...');
        div.dataset.item = '';
        div.dataset.id = String(data.id ?? '');
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
    let editCategoryColorChoices = null; // instância para atualizar a cor ao abrir o modal
    if (typeof Choices !== 'undefined') {
        const colorChoicesOptions = {
            searchEnabled: false,
            itemSelectText: '',
            shouldSort: false,
            allowHTML: true,
            callbackOnCreateTemplates: function(template, escape, classNames) {
                return {
                    choice: categoryColorChoiceTemplate,
                    item: categoryColorItemTemplate
                };
            }
        };
        const addColorEl = document.getElementById('addCategoryColorSelect');
        if (addColorEl && !addColorEl.closest('.choices')) {
            new Choices(addColorEl, colorChoicesOptions);
        }
        const editColorEl = document.getElementById('editCategoryColorSelect');
        if (editColorEl && !editColorEl.closest('.choices')) {
            editCategoryColorChoices = new Choices(editColorEl, colorChoicesOptions);
        }
    }

    // Ao alterar a cor no modal Editar Categoria: atualizar em tempo real a borda dos serviços e o ponto na sidebar
    const editCategoryColorSelect = document.getElementById('editCategoryColorSelect');
    if (editCategoryColorSelect) {
        editCategoryColorSelect.addEventListener('change', function() {
            const categoryId = document.getElementById('editCategoryId')?.value;
            if (!categoryId) return;
            const newColor = this.value || '#6c757d';
            const container = document.getElementById('servicesListContainer');
            if (container) {
                container.querySelectorAll('[data-category-id="' + categoryId + '"] .service-item').forEach(function(el) {
                    el.style.setProperty('--service-category-color', newColor);
                });
            }
            const sidebarItem = document.querySelector('#categoriesList .contacts-group-item[data-category-id="' + categoryId + '"]');
            if (sidebarItem) {
                const dot = sidebarItem.querySelector('.contacts-group-dot');
                if (dot) dot.style.background = newColor;
                sidebarItem.setAttribute('data-category-color', newColor);
            }
        });
    }

    // --- Modais: "Todos os membros" (Criar / Editar) ---
    document.getElementById('addServiceSelectAllAgents')?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('#addServiceModal .service-agent-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
    document.querySelectorAll('#addServiceModal .service-agent-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const allChecked = Array.from(document.querySelectorAll('#addServiceModal .service-agent-checkbox')).every(c => c.checked);
            const selectAll = document.getElementById('addServiceSelectAllAgents');
            if (selectAll) selectAll.checked = allChecked;
        });
    });

    // Selecionar/deselecionar todos os agentes - Modal Editar
    document.getElementById('editServiceSelectAllAgents')?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('#editServiceModal .service-agent-checkbox-edit');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
    document.querySelectorAll('#editServiceModal .service-agent-checkbox-edit').forEach(cb => {
        cb.addEventListener('change', function() {
            const allChecked = Array.from(document.querySelectorAll('#editServiceModal .service-agent-checkbox-edit')).every(c => c.checked);
            const selectAll = document.getElementById('editServiceSelectAllAgents');
            if (selectAll) selectAll.checked = allChecked;
        });
    });

    // --- Sidebar (mobile) e seleção de categoria ---
    const sidebarToggle = document.getElementById('servicesSidebarToggle');
    const sidebar = document.getElementById('servicesSidebar');
    const sidebarOverlay = document.getElementById('servicesSidebarOverlay');
    const sidebarClose = document.getElementById('servicesSidebarClose');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.add('active');
            sidebarOverlay.classList.add('active');
        });
    }

    if (sidebarClose) {
        sidebarClose.addEventListener('click', () => {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });
    }

    // Category selection (apenas itens da sidebar; não os blocos de extras nos modais)
    const categoriesList = document.getElementById('categoriesList');
    if (categoriesList) {
        categoriesList.querySelectorAll('.contacts-group-item[data-category-id]').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const categoryId = this.getAttribute('data-category-id');
                selectCategory(categoryId);
            });
        });
    }

    // Ações do cabeçalho da categoria (Editar / Adicionar serviço / Eliminar)
    const listContainer = document.getElementById('servicesListContainer');
    if (listContainer) {
        listContainer.addEventListener('click', function(e) {
            const header = e.target.closest('.services-category-header');
            if (!header) return;
            const categoryId = header.getAttribute('data-category-id');
            if (!categoryId) return;

            if (e.target.closest('.category-header-edit-btn')) {
                e.preventDefault();
                fetch(`/categories/${categoryId}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json())
                    .then(cat => {
                        document.getElementById('editCategoryId').value = cat.id;
                        document.getElementById('editCategoryName').value = cat.name || '';
                        document.getElementById('editCategoryDescription').value = cat.description || '';
                        const color = String(cat.color || '').trim();
                        const colorSelect = document.getElementById('editCategoryColorSelect');
                        if (colorSelect) colorSelect.value = color;
                        const modalEl = document.getElementById('editCategoryModal');
                        const doSetColor = function() {
                            if (editCategoryColorChoices && typeof editCategoryColorChoices.setChoiceByValue === 'function') {
                                editCategoryColorChoices.setChoiceByValue(color);
                            }
                        };
                        modalEl.addEventListener('shown.bs.modal', function once() {
                            modalEl.removeEventListener('shown.bs.modal', once);
                            requestAnimationFrame(function() { doSetColor(); });
                        }, { once: true });
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    })
                    .catch(err => { console.error(err); showToast('Erro ao carregar categoria', 'error'); });
                return;
            }
            if (e.target.closest('.category-header-add-service-btn')) {
                e.preventDefault();
                document.getElementById('addServiceCategoryId').value = categoryId;
                const addSelect = document.getElementById('addServiceCategorySelect');
                if (addSelect) addSelect.value = categoryId;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('addServiceModal')).show();
                return;
            }
            if (e.target.closest('.category-header-delete-btn')) {
                e.preventDefault();
                if (!confirm('Tem certeza que deseja eliminar esta categoria? Os serviços da categoria também serão afetados.')) return;
                fetch(`/categories/${categoryId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        location.reload();
                    } else {
                        showToast(data.message || 'Erro ao eliminar categoria', 'error');
                    }
                })
                .catch(err => { console.error(err); showToast('Erro ao eliminar categoria', 'error'); });
            }
        });
    }

    function selectCategory(categoryId) {
        selectedCategoryId = categoryId;
        
        // Update active state (apenas itens da sidebar)
        const list = document.getElementById('categoriesList');
        if (list) {
            list.querySelectorAll('.contacts-group-item[data-category-id]').forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('data-category-id') === categoryId) {
                    item.classList.add('active');
                }
            });
        }

        if (categoryId === 'all') loadAllServices();
        else loadServices(categoryId);
    }

    /** GET /categories/all/services → todas as categorias com serviços (vista "Todas as categorias"). */
    function loadAllServices() {
        fetch('/categories/all/services', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('servicesListContainer');
            const groups = data.groups || [];
            if (groups.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <i class="ph-duotone ph-package" style="font-size: 4rem; color: var(--muted-color);"></i>
                        <p class="text-muted mt-3">Nenhum serviço criado ainda.</p>
                    </div>
                `;
            } else {
                let html = '';
                groups.forEach(g => {
                    const cat = g.category || g;
                    const services = g.services || [];
                    html += `
                        <div class="mb-4" data-category-block="${cat.id}">
                            <div class="services-category-header mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2" data-category-id="${cat.id}">
                                <h5 class="mb-0 services-category-title">${cat.name}</h5>
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
                            <div class="services-group-list" data-sortable="services" data-category-id="${cat.id}">
                                ${renderServicesList(services, cat.color)}
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            }
            initServicesDragula();
            attachServiceEventListeners();
        })
        .catch(err => { console.error(err); showToast('Erro ao carregar serviços', 'error'); });
    }

    /** GET /categories/{id}/services → preenche a lista e reanexa listeners (editar/eliminar/dragula). */
    function loadServices(categoryId) {
        if (categoryId === 'all') { loadAllServices(); return; }
        fetch(`/categories/${categoryId}/services`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('servicesListContainer');
            const category = data.category;
            
            container.innerHTML = `
                <div class="services-category-header mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2" data-category-id="${category.id}">
                    <h5 class="mb-0 services-category-title">${category.name}</h5>
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
                <div id="servicesList" data-sortable="services" data-category-id="${category.id}">
                    ${renderServicesList(data.services, category.color)}
                </div>
            `;
            
            // Enable add service button
            const addServiceCategoryId = document.getElementById('addServiceCategoryId');
            if (addServiceCategoryId) {
                addServiceCategoryId.value = categoryId;
            }
            const addServiceCategorySelect = document.getElementById('addServiceCategorySelect');
            if (addServiceCategorySelect) {
                addServiceCategorySelect.value = categoryId;
            }
            
            // Initialize services dragula
            initServicesDragula();
            attachServiceEventListeners();
        })
        .catch(error => {
            console.error('Error loading services:', error);
            showToast('Erro ao carregar serviços', 'error');
        });
    }

    /** Atualizar badges das categorias na sidebar (1 único request: GET /categories com services_count) */
    function updateCategoryBadges() {
        fetch('/categories', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        })
        .then(data => {
            let categories = Array.isArray(data) ? data : (data?.data ?? data?.categories ?? Object.values(data || {}));
            if (!Array.isArray(categories)) return;
            categories.forEach(cat => {
                if (!cat?.id) return;
                const el = document.querySelector(`[data-category-id="${cat.id}"]`);
                const badge = el?.querySelector('.badge');
                if (badge) {
                    const count = cat.services_count ?? (Array.isArray(cat.services) ? cat.services.length : 0);
                    badge.textContent = count;
                }
            });
        })
        .catch(err => console.warn('updateCategoryBadges:', err));
    }

    // --- Render da lista de serviços (HTML) ---
    function renderServicesList(services, categoryColor) {
        const borderColor = categoryColor || '#6c757d';
        if (services.length === 0) {
            return `
                <div class="service-item-row service-empty-placeholder">
                    <div class="service-drag-handle" style="visibility: hidden;" aria-hidden="true"></div>
                    <div class="card service-item">
                        <div class="card-body">
                            <p class="text-muted small mb-0">Nenhum serviço nesta categoria.</p>
                        </div>
                    </div>
                </div>
            `;
        }
        
        return services.map(service => `
            <div class="service-item-row" data-service-id="${service.id}">
                <div class="service-drag-handle" aria-label="Arrastar para reordenar">
                    <span class="service-drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
                </div>
                <div class="card service-item" style="--service-category-color: ${borderColor};">
                    <div class="card-body d-flex justify-content-between align-items-center gap-3">
                        <div class="service-item-left">
                            <h6 class="mb-0 service-item-name">${service.name}</h6>
                            ${service.description ? `<p class="text-muted small mb-1">${service.description.substring(0, 100)}${service.description.length > 100 ? '...' : ''}</p>` : ''}
                            <div class="d-flex flex-wrap gap-3 text-muted small service-item-duration">
                                <span><i class="ph ph-clock me-1"></i>${formatDuration(service.duration)}</span>
                                ${service.online_price ? `<span class="text-success" title="Preço online"><i class="ph ph-globe me-1"></i>${formatPrice(service.online_price)}</span>` : ''}
                                ${(service.extras_count || (service.extras && service.extras.length) || 0) > 0 ? `<span><i class="ph ph-package me-1"></i>${service.extras_count || (service.extras && service.extras.length) || 0} extra(s)</span>` : ''}
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0 service-item-right">
                            <span class="service-item-price">${formatPrice(service.price)}</span>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Opções">
                                    <i class="ph ph-dots-three-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item edit-service-btn" href="#" data-service-id="${service.id}"><i class="ph ph-pencil-simple me-2"></i>Editar</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger delete-service-btn" href="#" data-service-id="${service.id}"><i class="ph ph-trash me-2"></i>Eliminar</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function formatDuration(minutes) {
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        if (hours > 0 && mins > 0) return `${hours}h ${mins}min`;
        if (hours > 0) return `${hours}h`;
        return `${mins}min`;
    }

    function formatPrice(price) {
        return new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(price);
    }

    // --- Dragula (reordenar serviços) --- vista de uma categoria (#servicesList) ou vista "Todas" (uma instância para todas as listas)
    function initServicesDragula() {
        if (typeof dragula === 'undefined') return;
        const destroyAll = function() {
            if (servicesDrake) { servicesDrake.destroy(); servicesDrake = null; }
            if (servicesDrakesAll.length) {
                servicesDrakesAll[0].destroy();
                servicesDrakesAll = [];
            }
        };
        const onDrop = function(listEl) {
            const categoryId = listEl.getAttribute('data-category-id');
            const order = Array.from(listEl.querySelectorAll('.service-item-row[data-service-id]'))
                .map(item => parseInt(item.getAttribute('data-service-id')));
            fetch(`/categories/${categoryId}/services/reorder`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ order })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) showToast('Ordem dos serviços atualizada', 'success');
            })
            .catch(error => { console.error('Error reordering services:', error); showToast('Erro ao reordenar serviços', 'error'); });
        };

        const servicesList = document.getElementById('servicesList');
        if (servicesList) {
            destroyAll();
            servicesDrake = dragula([servicesList], {
            moves: function(el, source, handle, sibling) {
                if (!el.classList.contains('service-item-row') || el.classList.contains('service-empty-placeholder')) return false;
                return handle && (handle.classList.contains('service-drag-handle') || handle.closest('.service-drag-handle'));
            },
                revertOnSpill: false,
                copy: false
            });
            servicesDrake.on('drop', function(el, target, source, sibling) {
                onDrop(servicesList);
            });
            return;
        }

        const groupLists = document.querySelectorAll('.services-group-list');
        if (groupLists.length === 0) {
            destroyAll();
            return;
        }
        destroyAll();
        // Uma única instância Dragula para todas as listas; accepts impede arrastar entre categorias
        const listArray = Array.from(groupLists);
        const d = dragula(listArray, {
            moves: function(el, source, handle, sibling) {
                if (!el.classList.contains('service-item-row') || el.classList.contains('service-empty-placeholder')) return false;
                return handle && (handle.classList.contains('service-drag-handle') || handle.closest('.service-drag-handle'));
            },
            accepts: function(el, target, source, sibling) {
                return target === source;
            },
            revertOnSpill: false,
            copy: false
        });
        d.on('drop', function(el, target, source, sibling) {
            onDrop(target);
        });
        servicesDrakesAll.push(d);
    }

    // --- Formulários: Categorias (criar / editar) ---
    document.getElementById('addCategoryForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        // Disable button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A criar...';
        
        const formData = new FormData(this);
        
        fetch('/categories', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('addCategoryModal')).hide();
                this.reset();
                location.reload(); // Reload to show new category
            } else {
                showToast(data.message || 'Erro ao criar categoria', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        })
        .catch(error => {
            console.error('Error creating category:', error);
            showToast('Erro ao criar categoria. Verifique os dados e tente novamente.', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });


    document.getElementById('editCategoryForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        // Disable button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A guardar...';
        
        const categoryId = document.getElementById('editCategoryId').value;
        const formData = new FormData(this);
        
        fetch(`/categories/${categoryId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-HTTP-Method-Override': 'PUT',
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('editCategoryModal')).hide();
                const cat = data.category;
                if (cat && cat.id) {
                    const sidebarItem = document.querySelector(`.contacts-group-item[data-category-id="${cat.id}"]`);
                    if (sidebarItem) {
                        const dot = sidebarItem.querySelector('.contacts-group-dot');
                        if (dot) dot.style.background = cat.color || '';
                        const spans = sidebarItem.querySelectorAll('span');
                        if (spans.length >= 2) spans[1].textContent = cat.name || '';
                        sidebarItem.setAttribute('data-category-name', cat.name || '');
                        sidebarItem.setAttribute('data-category-color', cat.color || '');
                    }
                    const header = document.querySelector(`.services-category-header[data-category-id="${cat.id}"]`);
                    if (header) {
                        const titleEl = header.querySelector('.services-category-title');
                        if (titleEl) titleEl.textContent = cat.name || titleEl.textContent;
                    }
                    document.querySelectorAll(`[data-category-block="${cat.id}"] .services-category-title`).forEach(el => {
                        el.textContent = cat.name || el.textContent;
                    });
                }
            } else {
                showToast(data.message || 'Erro ao atualizar categoria', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        })
        .catch(error => {
            console.error('Error updating category:', error);
            showToast('Erro ao atualizar categoria. Verifique os dados e tente novamente.', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });


    // --- Formulários: Serviços (criar / editar) ---
    document.getElementById('addServiceForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        // Disable button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A criar...';
        
        const formData = new FormData(this);
        
        // Se estiver em "Todas as categorias", não mudar a vista após criar (manter-se nesta vista)
        const categoryId = formData.get('category_id') || document.getElementById('addServiceCategoryId')?.value || document.getElementById('addServiceCategorySelect')?.value;
        if (categoryId && selectedCategoryId !== 'all') {
            selectedCategoryId = categoryId;
        }
        
        // Adicionar agent_ids ao FormData
        const agentIds = Array.from(document.querySelectorAll('#addServiceModal .service-agent-checkbox:checked')).map(cb => cb.value);
        agentIds.forEach(id => formData.append('agent_ids[]', id));
        const extraIds = Array.from(document.querySelectorAll('#addServiceModal input[name="extra_ids[]"]:checked')).map(cb => cb.value);
        extraIds.forEach(id => formData.append('extra_ids[]', id));
        
        fetch('/services', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('addServiceModal')).hide();
                this.reset();
                refreshAfterServiceChange();
            } else {
                showToast(data.message || 'Erro ao criar serviço', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        })
        .catch(error => {
            console.error('Error creating service:', error);
            showToast('Erro ao criar serviço. Verifique os dados e tente novamente.', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });

    // Edit Service Form (apenas uma vez; o formulário é estático)
    document.getElementById('editServiceForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A guardar...';
        
        const serviceId = document.getElementById('editServiceId').value;
        const formData = new FormData(this);
        
        const categoryId = document.getElementById('editServiceCategoryId').value;
        const name = document.getElementById('editServiceName').value;
        const duration = document.getElementById('editServiceDuration').value;
        const price = document.getElementById('editServicePrice').value;
        
        if (!formData.has('category_id') && categoryId) formData.set('category_id', categoryId);
        if (!formData.has('name') && name) formData.set('name', name);
        if (!formData.has('duration') && duration) formData.set('duration', duration);
        if (!formData.has('price') && price) formData.set('price', price);
        
        const agentIds = Array.from(document.querySelectorAll('#editServiceModal .service-agent-checkbox-edit:checked')).map(cb => cb.value);
        formData.delete('agent_ids[]');
        agentIds.forEach(id => formData.append('agent_ids[]', id));
        const extraIds = Array.from(document.querySelectorAll('#editServiceModal input[name="extra_ids[]"]:checked')).map(cb => cb.value);
        formData.delete('extra_ids[]');
        extraIds.forEach(id => formData.append('extra_ids[]', id));
        
        formData.set('_method', 'PUT');
        
        fetch(`/services/${serviceId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('editServiceModal')).hide();
                refreshAfterServiceChange();
            } else {
                showToast(data.message || 'Erro ao atualizar serviço', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        })
        .catch(error => {
            console.error('Error updating service:', error);
            showToast('Erro ao atualizar serviço. Verifique os dados e tente novamente.', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });

    // --- Botões dinâmicos da lista (editar / eliminar) — reanexados após cada loadServices ---
    function attachServiceEventListeners() {
        // Edit Service
        document.querySelectorAll('.edit-service-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const serviceId = this.getAttribute('data-service-id');
                
                fetch(`/services/${serviceId}`, {
                    headers: {
                        'Accept': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    const service = data.service;
                    document.getElementById('editServiceId').value = serviceId;
                    document.getElementById('editServiceCategoryId').value = service.category_id;
                    document.getElementById('editServiceName').value = service.name;
                    document.getElementById('editServiceDescription').value = service.description || '';
                    document.getElementById('editServiceDuration').value = service.duration;
                    document.getElementById('editServicePrice').value = service.price;
                    document.getElementById('editServiceOnlinePrice').value = service.online_price || '';
                    
                    // Preencher checkboxes de agentes
                    document.querySelectorAll('#editServiceModal .service-agent-checkbox-edit').forEach(cb => {
                        cb.checked = service.agents && service.agents.some(agent => agent.id === parseInt(cb.value));
                    });
                    // Atualizar checkbox "Todos os membros"
                    const allAgentsChecked = Array.from(document.querySelectorAll('#editServiceModal .service-agent-checkbox-edit')).every(c => c.checked);
                    document.getElementById('editServiceSelectAllAgents').checked = allAgentsChecked;
                    // Preencher checkboxes de extras
                    document.querySelectorAll('#editServiceModal input[name="extra_ids[]"]').forEach(cb => {
                        cb.checked = service.extras && service.extras.some(extra => extra.id === parseInt(cb.value));
                    });
                    
                    new bootstrap.Modal(document.getElementById('editServiceModal')).show();
                })
                .catch(error => {
                    console.error('Error loading service:', error);
                    showToast('Erro ao carregar serviço', 'error');
                });
            });
        });

        // Delete Service
        document.querySelectorAll('.delete-service-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                if (!confirm('Tem certeza que deseja eliminar este serviço?')) return;
                
                const serviceId = this.getAttribute('data-service-id');
                fetch(`/services/${serviceId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        refreshAfterServiceChange();
                    }
                })
                .catch(error => {
                    console.error('Error deleting service:', error);
                    showToast('Erro ao eliminar serviço', 'error');
                });
            });
        });
    }

    // --- Reset dos botões ao fechar modais + pesquisa + toast ---
    document.getElementById('addCategoryModal')?.addEventListener('hidden.bs.modal', function() {
        const submitBtn = document.querySelector('#addCategoryForm button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Criar';
        }
    });

    document.getElementById('editCategoryModal')?.addEventListener('hidden.bs.modal', function() {
        const submitBtn = document.querySelector('#editCategoryForm button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Guardar';
        }
    });

    document.getElementById('addServiceModal')?.addEventListener('hidden.bs.modal', function() {
        const submitBtn = document.querySelector('#addServiceForm button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Criar';
        }
    });

    document.getElementById('editServiceModal')?.addEventListener('hidden.bs.modal', function() {
        const submitBtn = document.querySelector('#editServiceForm button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Guardar';
        }
    });

    // Initialize on page load
    attachServiceEventListeners();
    initServicesDragula();

    // Search functionality
    document.getElementById('serviceSearch')?.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        document.querySelectorAll('.service-item-row').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });

    // Usa showToast global do layout (cores soft, bottom center)
});
