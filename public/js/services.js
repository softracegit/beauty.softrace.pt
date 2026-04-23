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

    function escapeHtml(str) {
        if (str == null) {
            return '';
        }
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function addModalHasOptions() {
        const cb = document.getElementById('addServiceHasOptions');
        return !!(cb && cb.checked);
    }

    function editModalHasOptions() {
        const cb = document.getElementById('editServiceHasOptions');
        return !!(cb && cb.checked);
    }

    function setSimplePricingInputsDisabled(prefix, disabled) {
        const id = prefix === 'add' ? 'addServiceSimplePricingWrap' : 'editServiceSimplePricingWrap';
        const wrap = document.getElementById(id);
        if (!wrap) {
            return;
        }
        const dur = wrap.querySelector('input[name="duration"]');
        const pri = wrap.querySelector('input[name="price"]');
        const onl = wrap.querySelector('input[name="online_price"]');
        [dur, pri, onl].forEach(function (el) {
            if (!el) {
                return;
            }
            el.disabled = !!disabled;
            if (disabled) {
                el.removeAttribute('required');
            }
        });
        if (dur && !disabled) {
            dur.setAttribute('required', 'required');
        }
        if (pri && !disabled) {
            pri.setAttribute('required', 'required');
        }
    }

    function buildOptionRowHtml(isBaseline, nameVal, durationVal, priceVal, onlineVal, sortIdx, optionId) {
        const idAttr = optionId ? ' data-option-id="' + String(optionId) + '"' : '';
        const removeBtn = isBaseline
            ? ''
            : '<button type="button" class="btn btn-link text-danger p-0 option-remove-btn" title="Remover"><i class="ph ph-x"></i></button>';
        const moveUpBtn = isBaseline
            ? '<button type="button" class="btn btn-link text-muted p-0 option-move-up-btn" title="Mover para cima" disabled><i class="ph ph-caret-up"></i></button>'
            : '<button type="button" class="btn btn-link text-muted p-0 option-move-up-btn" title="Mover para cima"><i class="ph ph-caret-up"></i></button>';
        const moveDownBtn = isBaseline
            ? '<button type="button" class="btn btn-link text-muted p-0 option-move-down-btn" title="Mover para baixo" disabled><i class="ph ph-caret-down"></i></button>'
            : '<button type="button" class="btn btn-link text-muted p-0 option-move-down-btn" title="Mover para baixo"><i class="ph ph-caret-down"></i></button>';
        const actionsCell = '<div class="service-options-actions">' + moveUpBtn + moveDownBtn + removeBtn + '</div>';
        const nameCell =
            '<input type="text" class="form-control form-control-sm" data-field="name" value="' +
            escapeHtml(nameVal) +
            '" required maxlength="255"' +
            (isBaseline ? ' placeholder="Nome da opção base"' : '') +
            '>';
        return (
            '<tr data-baseline="' + (isBaseline ? '1' : '0') + '" data-sort="' + sortIdx + '"' + idAttr + '>' +
            '<td>' + nameCell + '</td>' +
            '<td><input type="number" class="form-control form-control-sm" data-field="duration" min="1" step="1" required value="' + escapeHtml(String(durationVal)) + '"></td>' +
            '<td><input type="number" class="form-control form-control-sm" data-field="price" min="0" step="0.01" required value="' + escapeHtml(String(priceVal)) + '"></td>' +
            '<td><input type="number" class="form-control form-control-sm" data-field="online_price" min="0" step="0.01" required value="' + escapeHtml(String(onlineVal)) + '"></td>' +
            '<td class="text-end">' + actionsCell + '</td>' +
            '</tr>'
        );
    }

    function refreshOptionRowsMetadata(tbodyId) {
        const tbody = document.getElementById(tbodyId);
        if (!tbody) {
            return;
        }
        const rows = Array.from(tbody.querySelectorAll('tr[data-baseline]'));
        rows.forEach(function (tr, idx) {
            tr.setAttribute('data-sort', String(idx));
        });
        const movableRows = rows.filter(function (tr) {
            return tr.getAttribute('data-baseline') !== '1';
        });
        movableRows.forEach(function (tr, idx) {
            const upBtn = tr.querySelector('.option-move-up-btn');
            const downBtn = tr.querySelector('.option-move-down-btn');
            if (upBtn) {
                upBtn.disabled = idx === 0;
            }
            if (downBtn) {
                downBtn.disabled = idx === movableRows.length - 1;
            }
        });
    }

    function seedAddOptionsTableIfNeeded() {
        const tb = document.getElementById('addServiceOptionsTbody');
        if (!tb || tb.querySelector('tr')) {
            return;
        }
        const d = document.getElementById('addServiceDuration') ? document.getElementById('addServiceDuration').value : '60';
        const p = document.getElementById('addServicePrice') ? document.getElementById('addServicePrice').value : '';
        const o = document.getElementById('addServiceOnlinePrice') ? document.getElementById('addServiceOnlinePrice').value : '';
        const nm = document.getElementById('addServiceName') ? document.getElementById('addServiceName').value : '';
        tb.innerHTML =
            buildOptionRowHtml(true, nm, d, p, o, 0, null) +
            buildOptionRowHtml(false, '', d, p, o, 1, null);
        refreshOptionRowsMetadata('addServiceOptionsTbody');
    }

    function renderEditOptionsFromService(service) {
        const tb = document.getElementById('editServiceOptionsTbody');
        if (!tb) {
            return;
        }
        tb.innerHTML = '';
        const opts = Array.isArray(service.options) ? service.options.slice().sort(function (a, b) { return (a.sort_order || 0) - (b.sort_order || 0); }) : [];
        if (opts.length === 0) {
            return;
        }
        opts.forEach(function (opt, idx) {
            const isB = !!opt.is_baseline;
            const nm = opt.name || '';
            const rowHtml = buildOptionRowHtml(isB, nm, opt.duration, opt.price, opt.online_price, idx, opt.id);
            tb.insertAdjacentHTML('beforeend', rowHtml);
        });
        refreshOptionRowsMetadata('editServiceOptionsTbody');
    }

    function toggleAddServiceOptionsUi() {
        const on = addModalHasOptions();
        const opts = document.getElementById('addServiceOptionsWrap');
        const simple = document.getElementById('addServiceSimplePricingWrap');
        if (opts) {
            opts.classList.toggle('d-none', !on);
        }
        if (simple) {
            simple.classList.toggle('d-none', !!on);
        }
        setSimplePricingInputsDisabled('add', on);
        if (on) {
            seedAddOptionsTableIfNeeded();
        } else {
            const tb = document.getElementById('addServiceOptionsTbody');
            if (tb) {
                tb.innerHTML = '';
            }
        }
    }

    function toggleEditServiceOptionsUi() {
        const on = editModalHasOptions();
        const opts = document.getElementById('editServiceOptionsWrap');
        const simple = document.getElementById('editServiceSimplePricingWrap');
        if (opts) {
            opts.classList.toggle('d-none', !on);
        }
        if (simple) {
            simple.classList.toggle('d-none', !!on);
        }
        setSimplePricingInputsDisabled('edit', on);
        if (!on) {
            const tb = document.getElementById('editServiceOptionsTbody');
            if (tb) {
                tb.innerHTML = '';
            }
        } else {
            const tb = document.getElementById('editServiceOptionsTbody');
            if (tb && !tb.querySelector('tr')) {
                const d = document.getElementById('editServiceDuration') ? document.getElementById('editServiceDuration').value : '60';
                const p = document.getElementById('editServicePrice') ? document.getElementById('editServicePrice').value : '';
                const o = document.getElementById('editServiceOnlinePrice') ? document.getElementById('editServiceOnlinePrice').value : '';
                const nm = document.getElementById('editServiceName') ? document.getElementById('editServiceName').value : '';
                tb.innerHTML =
                    buildOptionRowHtml(true, nm, d, p, o, 0, null) + buildOptionRowHtml(false, '', d, p, o, 1, null);
                refreshOptionRowsMetadata('editServiceOptionsTbody');
            }
        }
    }

    function collectServiceOptionsPayload(tbodyId) {
        const tbody = document.getElementById(tbodyId);
        if (!tbody) {
            return [];
        }
        const rows = Array.from(tbody.querySelectorAll('tr[data-baseline]'));
        return rows.map(function (tr, idx) {
            const isB = tr.getAttribute('data-baseline') === '1';
            const nameEl = tr.querySelector('[data-field="name"]');
            const name = nameEl ? String(nameEl.value || '').trim() : '';
            const dur = parseInt(tr.querySelector('[data-field="duration"]').value, 10);
            const price = tr.querySelector('[data-field="price"]').value;
            const online = tr.querySelector('[data-field="online_price"]').value;
            const oid = tr.getAttribute('data-option-id');
            const row = {
                name: name,
                duration: dur,
                price: price,
                online_price: online,
                is_baseline: isB,
                sort_order: idx,
            };
            if (oid) {
                row.id = parseInt(oid, 10);
            }
            return row;
        });
    }

    function appendOptionsToFormData(formData, tbodyId) {
        const opts = collectServiceOptionsPayload(tbodyId);
        opts.forEach(function (o, i) {
            formData.append('options[' + i + '][name]', o.name);
            formData.append('options[' + i + '][duration]', String(o.duration));
            formData.append('options[' + i + '][price]', String(o.price));
            formData.append('options[' + i + '][online_price]', String(o.online_price));
            formData.append('options[' + i + '][sort_order]', String(o.sort_order));
            formData.append('options[' + i + '][is_baseline]', o.is_baseline ? '1' : '0');
            if (o.id) {
                formData.append('options[' + i + '][id]', String(o.id));
            }
        });
    }

    function showFirstValidationError(data) {
        if (!data || !data.errors) {
            return;
        }
        const first = Object.values(data.errors).flat()[0];
        if (first) {
            showToast(first, 'error');
        }
    }

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

    document.getElementById('addServiceHasOptions')?.addEventListener('change', function () {
        toggleAddServiceOptionsUi();
    });
    document.getElementById('editServiceHasOptions')?.addEventListener('change', function () {
        toggleEditServiceOptionsUi();
    });
    function addOptionRowToTbody(tbodyId) {
        const tb = document.getElementById(tbodyId);
        if (!tb) {
            return;
        }
        const first = tb.querySelector('tr [data-field="duration"]');
        const d = first && first.value ? first.value : '60';
        const prEl = tb.querySelector('tr [data-field="price"]');
        const onEl = tb.querySelector('tr [data-field="online_price"]');
        const p = prEl && prEl.value !== '' ? prEl.value : '';
        const o = onEl && onEl.value !== '' ? onEl.value : '';
        const n = tb.querySelectorAll('tr').length;
        tb.insertAdjacentHTML('beforeend', buildOptionRowHtml(false, '', d, p, o, n, null));
        refreshOptionRowsMetadata(tbodyId);
    }

    document.getElementById('addServiceAddOptionRow')?.addEventListener('click', function () {
        addOptionRowToTbody('addServiceOptionsTbody');
    });
    document.getElementById('editServiceAddOptionRow')?.addEventListener('click', function () {
        addOptionRowToTbody('editServiceOptionsTbody');
    });

    function syncSimplePricingFromOptions(prefix, tbodyId) {
        const tbody = document.getElementById(tbodyId);
        if (!tbody) {
            return;
        }
        const rows = Array.from(tbody.querySelectorAll('tr[data-baseline]'));
        if (!rows.length) {
            return;
        }
        const baseline = rows.find(function (tr) {
            return tr.getAttribute('data-baseline') === '1';
        }) || rows[0];
        const durationInputOption = baseline.querySelector('[data-field="duration"]');
        const priceInputOption = baseline.querySelector('[data-field="price"]');
        const onlineInputOption = baseline.querySelector('[data-field="online_price"]');
        const durationVal = durationInputOption ? durationInputOption.value : '';
        const priceVal = priceInputOption ? priceInputOption.value : '';
        const onlineVal = onlineInputOption ? onlineInputOption.value : '';
        const durationInput = document.getElementById(prefix + 'ServiceDuration');
        const priceInput = document.getElementById(prefix + 'ServicePrice');
        const onlineInput = document.getElementById(prefix + 'ServiceOnlinePrice');
        if (durationInput) {
            durationInput.value = durationVal;
        }
        if (priceInput) {
            priceInput.value = priceVal;
        }
        if (onlineInput) {
            onlineInput.value = onlineVal;
        }
    }

    function bindOptionTableActions(modalEl) {
        if (!modalEl || modalEl.dataset.optionActionsBound === '1') {
            return;
        }
        modalEl.dataset.optionActionsBound = '1';
        modalEl.addEventListener('click', function (e) {
            const btn = e.target.closest('.option-remove-btn');
            const moveUpBtn = e.target.closest('.option-move-up-btn');
            const moveDownBtn = e.target.closest('.option-move-down-btn');
            const tr = (btn || moveUpBtn || moveDownBtn) ? (btn || moveUpBtn || moveDownBtn).closest('tr') : null;
            const tbody = tr ? tr.parentElement : null;
            if (!tbody || !tr) {
                return;
            }
            if (btn) {
                if (tbody.querySelectorAll('tr').length <= 2) {
                    showToast('Use "Remover todas" para voltar ao serviço simples.', 'error');
                    return;
                }
                tr.remove();
                refreshOptionRowsMetadata(tbody.id);
                return;
            }
            const isBaseline = tr.getAttribute('data-baseline') === '1';
            if (isBaseline) {
                return;
            }
            if (moveUpBtn) {
                let prev = tr.previousElementSibling;
                while (prev && prev.getAttribute('data-baseline') === '1') {
                    prev = prev.previousElementSibling;
                }
                if (prev) {
                    tbody.insertBefore(tr, prev);
                    refreshOptionRowsMetadata(tbody.id);
                }
                return;
            }
            if (moveDownBtn) {
                let next = tr.nextElementSibling;
                while (next && next.getAttribute('data-baseline') === '1') {
                    next = next.nextElementSibling;
                }
                if (next) {
                    tbody.insertBefore(next, tr);
                    refreshOptionRowsMetadata(tbody.id);
                }
            }
        });
    }
    bindOptionTableActions(document.getElementById('addServiceModal'));
    bindOptionTableActions(document.getElementById('editServiceModal'));

    function bindClearOptionsButton(buttonId, checkboxId, tbodyId, prefix, toggleFn) {
        const btn = document.getElementById(buttonId);
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            const tbody = document.getElementById(tbodyId);
            if (!tbody || !tbody.querySelector('tr')) {
                const cb = document.getElementById(checkboxId);
                if (cb) {
                    cb.checked = false;
                }
                toggleFn();
                return;
            }
            if (!confirm('Remover todas as opções e voltar ao serviço simples?')) {
                return;
            }
            syncSimplePricingFromOptions(prefix, tbodyId);
            tbody.innerHTML = '';
            const cb = document.getElementById(checkboxId);
            if (cb) {
                cb.checked = false;
            }
            toggleFn();
        });
    }
    bindClearOptionsButton('addServiceClearOptionRows', 'addServiceHasOptions', 'addServiceOptionsTbody', 'add', toggleAddServiceOptionsUi);
    bindClearOptionsButton('editServiceClearOptionRows', 'editServiceHasOptions', 'editServiceOptionsTbody', 'edit', toggleEditServiceOptionsUi);

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
        
        return services.map(function (service) {
            const opts = service.options && service.options.length ? service.options : [];
            const hasOpts = opts.length > 0;
            let fromOnline = null;
            if (hasOpts) {
                fromOnline = Math.min.apply(
                    null,
                    opts.map(function (x) {
                        return parseFloat(x.online_price);
                    }),
                );
            }
            const extrasN = service.extras_count || (service.extras && service.extras.length) || 0;
            const metaLine = hasOpts
                ? `<span class="text-success" title="Menor preço online entre opções"><i class="ph ph-globe me-1"></i>Desde ${formatPrice(fromOnline)}</span>
                   <span><i class="ph ph-list-checks me-1"></i>${opts.length} opção(ões)</span>`
                : `<span><i class="ph ph-clock me-1"></i>${formatDuration(service.duration)}</span>
                   ${service.online_price ? `<span class="text-success" title="Preço online"><i class="ph ph-globe me-1"></i>${formatPrice(service.online_price)}</span>` : ''}`;
            const optionList = hasOpts
                ? `<ul class="list-unstyled small text-muted mb-0 mt-1 ps-0">${opts
                      .map(function (opt) {
                          return (
                              '<li class="mb-1">' +
                              escapeHtml(opt.name) +
                              ' · ' +
                              formatDuration(opt.duration) +
                              ' · ' +
                              formatPrice(opt.online_price) +
                              '</li>'
                          );
                      })
                      .join('')}</ul>`
                : '';
            const priceAside = hasOpts
                ? `<span class="service-item-price"><span class="small fw-normal">desde</span> ${formatPrice(fromOnline)}</span>`
                : `<span class="service-item-price">${formatPrice(service.price)}</span>`;
            const rowHasOptionsClass = hasOpts ? ' service-item-row--has-options' : '';
            const bodyAlignClass = hasOpts ? 'align-items-start' : 'align-items-center';
            const rightAlignClass = hasOpts ? 'align-items-start' : 'align-items-center';
            return `
            <div class="service-item-row${rowHasOptionsClass}" data-service-id="${service.id}">
                <div class="service-drag-handle" aria-label="Arrastar para reordenar">
                    <span class="service-drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
                </div>
                <div class="card service-item service-item-clickable" style="--service-category-color: ${borderColor};">
                    <div class="card-body d-flex justify-content-between ${bodyAlignClass} gap-3 py-2 pe-2">
                        <div class="service-item-left">
                            <h6 class="mb-0 service-item-name">${escapeHtml(service.name)}</h6>
                            ${service.description ? `<p class="text-muted small mb-1">${escapeHtml(service.description.substring(0, 100))}${service.description.length > 100 ? '...' : ''}</p>` : ''}
                            <div class="d-flex flex-wrap gap-3 text-muted small service-item-duration">
                                ${metaLine}
                                ${extrasN > 0 ? `<span><i class="ph ph-package me-1"></i>${extrasN} extra(s)</span>` : ''}
                            </div>
                            ${optionList}
                        </div>
                        <div class="d-flex ${rightAlignClass} gap-2 flex-shrink-0 service-item-right">${priceAside}</div>
                    </div>
                </div>
            </div>
        `;
        }).join('');
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

        const hasVar = addModalHasOptions();
        formData.set('has_options', hasVar ? '1' : '0');
        if (hasVar) {
            const baseline = document.querySelector('#addServiceOptionsTbody tr[data-baseline="1"]');
            if (baseline) {
                formData.set('duration', baseline.querySelector('[data-field="duration"]').value);
                formData.set('price', baseline.querySelector('[data-field="price"]').value);
                formData.set('online_price', baseline.querySelector('[data-field="online_price"]').value);
            }
            appendOptionsToFormData(formData, 'addServiceOptionsTbody');
        }

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
            body: formData,
        })
            .then(async function (response) {
                const data = await response.json().catch(function () {
                    return {};
                });
                if (!response.ok) {
                    showFirstValidationError(data);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                    return null;
                }
                return data;
            })
            .then(data => {
                if (!data) {
                    return;
                }
                if (data.success) {
                    showToast(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addServiceModal')).hide();
                    this.reset();
                    const cb = document.getElementById('addServiceHasOptions');
                    if (cb) {
                        cb.checked = false;
                    }
                    toggleAddServiceOptionsUi();
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
        const onlineEl = document.getElementById('editServiceOnlinePrice');
        if (onlineEl && !formData.has('online_price')) {
            formData.set('online_price', onlineEl.value || '');
        }

        const hasVar = editModalHasOptions();
        formData.set('has_options', hasVar ? '1' : '0');
        if (hasVar) {
            const baseline = document.querySelector('#editServiceOptionsTbody tr[data-baseline="1"]');
            if (baseline) {
                formData.set('duration', baseline.querySelector('[data-field="duration"]').value);
                formData.set('price', baseline.querySelector('[data-field="price"]').value);
                formData.set('online_price', baseline.querySelector('[data-field="online_price"]').value);
            }
            appendOptionsToFormData(formData, 'editServiceOptionsTbody');
        }

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
            body: formData,
        })
            .then(async function (response) {
                const data = await response.json().catch(function () {
                    return {};
                });
                if (!response.ok) {
                    showFirstValidationError(data);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                    return null;
                }
                return data;
            })
            .then(data => {
                if (!data) {
                    return;
                }
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

    function openEditServiceModal(serviceId) {
        fetch(`/services/${serviceId}`, {
            headers: {
                Accept: 'application/json',
            },
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

                const hasOpts = service.options && service.options.length > 0;
                const hc = document.getElementById('editServiceHasOptions');
                if (hc) {
                    hc.checked = !!hasOpts;
                }
                const tbEdit = document.getElementById('editServiceOptionsTbody');
                if (tbEdit) {
                    tbEdit.innerHTML = '';
                }
                if (hasOpts) {
                    renderEditOptionsFromService(service);
                }
                toggleEditServiceOptionsUi();

                document.querySelectorAll('#editServiceModal .service-agent-checkbox-edit').forEach(cb => {
                    cb.checked = service.agents && service.agents.some(agent => agent.id === parseInt(cb.value));
                });
                const allAgentsChecked = Array.from(
                    document.querySelectorAll('#editServiceModal .service-agent-checkbox-edit'),
                ).every(c => c.checked);
                document.getElementById('editServiceSelectAllAgents').checked = allAgentsChecked;
                document.querySelectorAll('#editServiceModal input[name="extra_ids[]"]').forEach(cb => {
                    cb.checked = service.extras && service.extras.some(extra => extra.id === parseInt(cb.value));
                });

                new bootstrap.Modal(document.getElementById('editServiceModal')).show();
            })
            .catch(error => {
                console.error('Error loading service:', error);
                showToast('Erro ao carregar serviço', 'error');
            });
    }

    function deleteServiceById(serviceId) {
        fetch(`/services/${serviceId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('editServiceModal')).hide();
                    refreshAfterServiceChange();
                } else {
                    showToast(data.message || 'Erro ao eliminar serviço', 'error');
                }
            })
            .catch(error => {
                console.error('Error deleting service:', error);
                showToast('Erro ao eliminar serviço', 'error');
            });
    }

    // --- Lista dinâmica: abrir editar ao clicar no card ---
    function attachServiceEventListeners() {
        document.querySelectorAll('.service-item-row[data-service-id] .service-item-clickable').forEach(card => {
            card.addEventListener('click', function (e) {
                if (e.target.closest('.service-drag-handle')) {
                    return;
                }
                const row = card.closest('.service-item-row[data-service-id]');
                const serviceId = row ? row.getAttribute('data-service-id') : null;
                if (!serviceId) {
                    return;
                }
                openEditServiceModal(serviceId);
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
        const cbAdd = document.getElementById('addServiceHasOptions');
        if (cbAdd) {
            cbAdd.checked = false;
        }
        const tbAdd = document.getElementById('addServiceOptionsTbody');
        if (tbAdd) {
            tbAdd.innerHTML = '';
        }
        toggleAddServiceOptionsUi();
    });

    document.getElementById('editServiceModal')?.addEventListener('hidden.bs.modal', function() {
        const submitBtn = document.querySelector('#editServiceForm button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Guardar';
        }
        const cbEd = document.getElementById('editServiceHasOptions');
        if (cbEd) {
            cbEd.checked = false;
        }
        const tbEd = document.getElementById('editServiceOptionsTbody');
        if (tbEd) {
            tbEd.innerHTML = '';
        }
        toggleEditServiceOptionsUi();
    });

    document.getElementById('editServiceDeleteBtn')?.addEventListener('click', function (e) {
        e.preventDefault();
        const serviceId = document.getElementById('editServiceId')?.value;
        if (!serviceId) {
            return;
        }
        if (!confirm('Tem certeza que deseja eliminar este serviço?')) {
            return;
        }
        deleteServiceById(serviceId);
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
