/**
 * Serviços: lista por categoria, CRUD por AJAX.
 * Fluxo: 1) Selecionar categoria → GET /categories/{id}/services (lista).
 *        2) Criar/Editar/Eliminar serviço → POST/PUT/DELETE → depois: refresh lista (1 GET) + atualizar badges (1 GET /categories).
 */
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const container = document.getElementById('servicesListContainer');
    const canDeleteCatalog = container?.getAttribute('data-can-delete-catalog') === '1';
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

    function agentInitials(name) {
        const parts = String(name || '')
            .trim()
            .split(/\s+/)
            .filter(Boolean);
        if (parts.length >= 2) {
            return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
        }
        if (parts.length === 1) {
            return parts[0].charAt(0).toUpperCase();
        }
        return '?';
    }

    function normalizeServiceMembers(service) {
        const agents = Array.isArray(service.agents) ? service.agents : [];
        return agents.map(function (agent) {
            const store = agent.store || {};
            return {
                id: agent.id,
                name: agent.name || '',
                avatar: agent.avatar || null,
                store_id: agent.store_id || store.id || 0,
                store_name: store.name || agent.store_name || 'Sem loja',
            };
        });
    }

    function renderServiceMembersTrigger(service) {
        const members = normalizeServiceMembers(service);
        const agentsN = service.agents_count != null
            ? parseInt(service.agents_count, 10) || 0
            : members.length;
        const maxVisible = 5;
        const visible = members.slice(0, maxVisible);
        const overflow = members.length - visible.length;
        let avatarsHtml = '';
        if (visible.length) {
            const items = visible
                .map(function (agent) {
                    if (agent.avatar) {
                        const src = escapeHtml('/storage/' + String(agent.avatar).replace(/^\/+/, ''));
                        return `<img src="${src}" alt="" class="avatar" loading="lazy">`;
                    }
                    return `<span class="avatar service-item-member-initial">${escapeHtml(agentInitials(agent.name))}</span>`;
                })
                .join('');
            const more = overflow > 0
                ? `<span class="avatar-more">+${overflow}</span>`
                : '';
            avatarsHtml = `<span class="service-item-members avatar-group" aria-hidden="true">${items}${more}</span>`;
        }
        const payload = escapeHtml(JSON.stringify(members));
        const serviceName = escapeHtml(service.name || '');
        return (
            `<button type="button" class="service-item-members-trigger" data-service-name="${serviceName}" data-members="${payload}" title="Ver membros associados" aria-label="Ver ${agentsN} membro${agentsN === 1 ? '' : 's'} associados">` +
            `<span class="badge text-bg-light">${agentsN} membro${agentsN === 1 ? '' : 's'}</span>` +
            avatarsHtml +
            `</button>`
        );
    }

    function renderMemberAvatarHtml(agent, sizeClass) {
        const name = escapeHtml(agent.name || '');
        if (agent.avatar) {
            const src = escapeHtml('/storage/' + String(agent.avatar).replace(/^\/+/, ''));
            return `<img src="${src}" alt="${name}" class="avatar ${sizeClass || ''}" loading="lazy">`;
        }
        return `<span class="avatar service-item-member-initial ${sizeClass || ''}" aria-hidden="true">${escapeHtml(agentInitials(agent.name))}</span>`;
    }

    function openServiceMembersModal(triggerEl) {
        const modalEl = document.getElementById('serviceMembersModal');
        const bodyEl = document.getElementById('serviceMembersModalBody');
        const nameEl = document.getElementById('serviceMembersModalServiceName');
        if (!modalEl || !bodyEl || !nameEl || !triggerEl) {
            return;
        }
        let members = [];
        try {
            members = JSON.parse(triggerEl.getAttribute('data-members') || '[]');
        } catch (err) {
            members = [];
        }
        if (!Array.isArray(members)) {
            members = [];
        }
        nameEl.textContent = triggerEl.getAttribute('data-service-name') || '';
        if (!members.length) {
            bodyEl.innerHTML =
                '<p class="text-muted mb-2">Nenhum membro associado a este serviço.</p>' +
                '<p class="small mb-0">Associa técnicos em <a href="/servicos/equipa">Serviços por equipa</a>.</p>';
        } else {
            const byStore = {};
            members.forEach(function (agent) {
                const key = String(agent.store_id || 0) + '::' + (agent.store_name || 'Sem loja');
                if (!byStore[key]) {
                    byStore[key] = {
                        storeName: agent.store_name || 'Sem loja',
                        agents: [],
                    };
                }
                byStore[key].agents.push(agent);
            });
            bodyEl.innerHTML = Object.keys(byStore)
                .map(function (key) {
                    const group = byStore[key];
                    const items = group.agents
                        .map(function (agent) {
                            return (
                                `<li class="service-members-modal-item">` +
                                renderMemberAvatarHtml(agent) +
                                `<span>${escapeHtml(agent.name || '')}</span>` +
                                `</li>`
                            );
                        })
                        .join('');
                    return (
                        `<div class="service-members-modal-store">` +
                        `<div class="service-members-modal-store-title">${escapeHtml(group.storeName)}</div>` +
                        `<ul class="service-members-modal-list">${items}</ul>` +
                        `</div>`
                    );
                })
                .join('');
        }
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function categoryDeleteDropdownHtml() {
        if (!canDeleteCatalog) {
            return '';
        }

        return `
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item category-header-delete-btn text-danger" href="#"><i class="ph ph-trash me-2"></i>Eliminar</a></li>`;
    }

    function categoryHeaderHtml(categoryId, categoryName) {
        return `
                <div class="services-category-header mb-3 d-flex align-items-center flex-wrap gap-2" data-category-id="${escapeHtml(categoryId)}">
                    <div class="d-flex align-items-center min-w-0">
                        <h5 class="mb-0 services-category-title text-truncate">${escapeHtml(categoryName)}</h5>
                        <button type="button" class="services-category-title-edit category-header-edit-btn" title="Editar categoria" aria-label="Editar categoria">
                            <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                        </button>
                        <div class="dropdown">
                            <button class="services-category-options-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Opções
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item category-header-edit-btn" href="#"><i class="ph ph-pencil-simple me-2"></i>Editar categoria</a></li>
                                <li><a class="dropdown-item category-header-add-service-btn" href="#"><i class="ph ph-plus me-2"></i>Adicionar serviço</a></li>
                                ${categoryDeleteDropdownHtml()}
                            </ul>
                        </div>
                    </div>
                </div>`;
    }

    // --- Variantes de serviço (lista estilo Fresha + modal) ---
    const serviceVariantsState = {
        add: [],
        edit: [],
    };
    let serviceVariantKeySeq = 1;
    const serviceVariantDrakes = { add: null, edit: null };

    function nextVariantKey() {
        serviceVariantKeySeq += 1;
        return 'v' + serviceVariantKeySeq;
    }

    function pricingPrefixIds(prefix) {
        return {
            duration: prefix + 'ServiceDuration',
            price: prefix + 'ServicePrice',
            online: prefix + 'ServiceOnlinePrice',
            name: prefix + 'ServiceName',
            description: prefix + 'ServiceDescription',
            category: prefix === 'add' ? 'addServiceCategorySelect' : 'editServiceCategoryId',
            simpleWrap: prefix + 'ServiceSimplePricingWrap',
            list: prefix + 'ServiceVariantsList',
            hasOptions: prefix + 'ServiceHasOptions',
        };
    }

    function getCategoryColorForPrefix(prefix) {
        const ids = pricingPrefixIds(prefix);
        const select = document.getElementById(ids.category);
        if (!select) return '#6c757d';
        const opt = select.options[select.selectedIndex];
        return (opt && opt.getAttribute('data-color')) || '#6c757d';
    }

    function formatVariantDuration(minutes) {
        const m = parseInt(minutes, 10) || 0;
        const hours = Math.floor(m / 60);
        const mins = m % 60;
        if (hours > 0 && mins > 0) return hours + 'h ' + mins + 'min';
        if (hours > 0) return hours + 'h';
        return mins + 'min';
    }

    function formatVariantPrice(price) {
        const n = parseFloat(price);
        if (isNaN(n)) return '—';
        return new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(n);
    }

    function readSimplePricing(prefix) {
        const ids = pricingPrefixIds(prefix);
        return {
            duration: document.getElementById(ids.duration)?.value || '',
            price: document.getElementById(ids.price)?.value || '',
            online_price: document.getElementById(ids.online)?.value || '',
            name: (document.getElementById(ids.name)?.value || '').trim(),
            description: (document.getElementById(ids.description)?.value || '').trim(),
        };
    }

    function writeSimplePricing(prefix, data) {
        const ids = pricingPrefixIds(prefix);
        const dur = document.getElementById(ids.duration);
        const pri = document.getElementById(ids.price);
        const onl = document.getElementById(ids.online);
        if (dur) dur.value = data.duration != null ? data.duration : '';
        if (pri) pri.value = data.price != null ? data.price : '';
        if (onl) onl.value = data.online_price != null && data.online_price !== '' ? data.online_price : '';
    }

    function setSimplePricingEnabled(prefix, enabled) {
        const ids = pricingPrefixIds(prefix);
        const wrap = document.getElementById(ids.simpleWrap);
        if (!wrap) return;
        wrap.querySelectorAll('input').forEach(function (el) {
            el.disabled = !enabled;
            if (!enabled) {
                el.removeAttribute('required');
            }
        });
        if (enabled) {
            const dur = document.getElementById(ids.duration);
            const pri = document.getElementById(ids.price);
            if (dur) dur.setAttribute('required', 'required');
            if (pri) pri.setAttribute('required', 'required');
        }
    }

    function variantsMode(prefix) {
        return serviceVariantsState[prefix].length >= 2;
    }

    function syncHasOptionsFlag(prefix) {
        const el = document.getElementById(pricingPrefixIds(prefix).hasOptions);
        if (el) el.value = variantsMode(prefix) ? '1' : '0';
    }

    function syncBaselineToSimpleFields(prefix) {
        const items = serviceVariantsState[prefix];
        const baseline = items.find(function (i) { return i.is_baseline; }) || items[0];
        if (!baseline) return;
        writeSimplePricing(prefix, baseline);
    }

    function renderServiceVariants(prefix) {
        const ids = pricingPrefixIds(prefix);
        const list = document.getElementById(ids.list);
        const simple = document.getElementById(ids.simpleWrap);
        if (!list || !simple) return;

        const items = serviceVariantsState[prefix];
        const inVariants = items.length >= 2;
        syncHasOptionsFlag(prefix);

        simple.classList.toggle('d-none', inVariants);
        list.classList.toggle('d-none', !inVariants);
        setSimplePricingEnabled(prefix, !inVariants);

        if (!inVariants) {
            list.innerHTML = '';
            destroyVariantDrake(prefix);
            return;
        }

        const color = getCategoryColorForPrefix(prefix);
        list.innerHTML = items.map(function (item, idx) {
            const desc = item.description
                ? '<p class="text-muted small mb-1 text-truncate">' + escapeHtml(item.description) + '</p>'
                : '';
            const upDisabled = idx === 0 ? ' disabled' : '';
            const downDisabled = idx === items.length - 1 ? ' disabled' : '';
            const isAddon = item.type === 'addon' && !item.is_baseline;
            const addonBadge = isAddon ? ' <span class="badge text-bg-light fw-normal ms-1">Adicional</span>' : '';
            const plus = isAddon ? '+' : '';
            return (
                '<div class="service-variant-row" data-variant-key="' + escapeHtml(item.key) + '">' +
                    '<div class="service-drag-handle" aria-label="Arrastar para reordenar">' +
                        '<span class="service-drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>' +
                    '</div>' +
                    '<div class="card service-item service-variant-item" style="--service-category-color: ' + escapeHtml(color) + ';">' +
                        '<div class="card-body d-flex justify-content-between align-items-center gap-3">' +
                            '<div class="service-item-left min-w-0">' +
                                '<h6 class="mb-0 service-item-name text-truncate">' + escapeHtml(item.name || 'Sem nome') + addonBadge + '</h6>' +
                                desc +
                                '<div class="text-muted small"><i class="ph ph-clock me-1"></i>' + plus + escapeHtml(formatVariantDuration(item.duration)) + '</div>' +
                            '</div>' +
                            '<div class="d-flex align-items-center gap-2 flex-shrink-0">' +
                                '<span class="service-item-price">' + plus + escapeHtml(formatVariantPrice(item.price)) + '</span>' +
                                '<div class="dropdown">' +
                                    '<button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle service-variant-options-btn" data-bs-toggle="dropdown" aria-expanded="false">Opções</button>' +
                                    '<ul class="dropdown-menu dropdown-menu-end">' +
                                        '<li><button type="button" class="dropdown-item service-variant-action" data-action="edit">Editar</button></li>' +
                                        '<li><button type="button" class="dropdown-item service-variant-action" data-action="up"' + upDisabled + '>Mover para cima</button></li>' +
                                        '<li><button type="button" class="dropdown-item service-variant-action" data-action="down"' + downDisabled + '>Mover para baixo</button></li>' +
                                        '<li><button type="button" class="dropdown-item service-variant-action" data-action="duplicate">Duplicar</button></li>' +
                                        '<li><hr class="dropdown-divider"></li>' +
                                        '<li><button type="button" class="dropdown-item text-danger service-variant-action" data-action="delete">Eliminar</button></li>' +
                                    '</ul>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
        }).join('');

        initVariantDrake(prefix);
        syncBaselineToSimpleFields(prefix);
    }

    function destroyVariantDrake(prefix) {
        if (serviceVariantDrakes[prefix]) {
            serviceVariantDrakes[prefix].destroy();
            serviceVariantDrakes[prefix] = null;
        }
    }

    function refreshVariantMoveButtons(prefix) {
        const list = document.getElementById(pricingPrefixIds(prefix).list);
        if (!list) return;
        const rows = Array.from(list.querySelectorAll('.service-variant-row'));
        rows.forEach(function (row, idx) {
            const upBtn = row.querySelector('.service-variant-action[data-action="up"]');
            const downBtn = row.querySelector('.service-variant-action[data-action="down"]');
            if (upBtn) upBtn.disabled = idx === 0;
            if (downBtn) downBtn.disabled = idx === rows.length - 1;
        });
    }

    function syncVariantOrderFromDom(prefix) {
        const list = document.getElementById(pricingPrefixIds(prefix).list);
        if (!list) return;
        const keys = Array.from(list.querySelectorAll('.service-variant-row')).map(function (row) {
            return row.getAttribute('data-variant-key');
        });
        const byKey = {};
        serviceVariantsState[prefix].forEach(function (item) { byKey[item.key] = item; });
        serviceVariantsState[prefix] = keys.map(function (k) { return byKey[k]; }).filter(Boolean);
        refreshVariantMoveButtons(prefix);
        syncBaselineToSimpleFields(prefix);
    }

    function initVariantDrake(prefix) {
        if (typeof dragula === 'undefined') return;
        destroyVariantDrake(prefix);
        const list = document.getElementById(pricingPrefixIds(prefix).list);
        if (!list || list.classList.contains('d-none')) return;
        const d = dragula([list], {
            moves: function (el, source, handle) {
                if (!el.classList.contains('service-variant-row')) return false;
                return !!(handle && (handle.classList.contains('service-drag-handle') || handle.closest('.service-drag-handle')));
            },
            accepts: function (el, target) {
                return target === list;
            },
            revertOnSpill: true,
            mirrorContainer: document.body,
        });
        d.on('drop', function () {
            syncVariantOrderFromDom(prefix);
        });
        serviceVariantDrakes[prefix] = d;
    }

    function openVariantModal(prefix, itemOrNull) {
        const form = document.getElementById('serviceVariantForm');
        const title = document.getElementById('serviceVariantModalLabel');
        document.getElementById('serviceVariantPrefix').value = prefix;
        document.getElementById('serviceVariantEditKey').value = itemOrNull ? itemOrNull.key : '';
        if (title) title.textContent = itemOrNull ? 'Editar variante' : 'Adicionar variante';

        const simple = readSimplePricing(prefix);
        document.getElementById('serviceVariantName').value = itemOrNull ? (itemOrNull.name || '') : '';
        document.getElementById('serviceVariantDescription').value = itemOrNull ? (itemOrNull.description || '') : '';
        const typeSelect = document.getElementById('serviceVariantType');
        const typeHelp = document.getElementById('serviceVariantTypeHelp');
        if (typeSelect) {
            // A opção base define o preço do serviço: é sempre de escolha única.
            const isBase = !!(itemOrNull && itemOrNull.is_baseline);
            typeSelect.value = itemOrNull && itemOrNull.type === 'addon' && !isBase ? 'addon' : 'choice';
            typeSelect.disabled = isBase;
            if (typeHelp) {
                typeHelp.textContent = isBase
                    ? 'Esta é a opção base do serviço, por isso é sempre de escolha única.'
                    : 'Uma variante adicional (ex.: Reparação) aparece no Booking como interruptor, e o preço e a duração somam-se aos da variante escolhida.';
            }
        }
        document.getElementById('serviceVariantDuration').value = itemOrNull ? itemOrNull.duration : (simple.duration || '60');
        document.getElementById('serviceVariantPrice').value = itemOrNull ? itemOrNull.price : (simple.price || '');
        const onlineDefault = itemOrNull
            ? itemOrNull.online_price
            : (simple.online_price !== '' ? simple.online_price : (simple.price || ''));
        document.getElementById('serviceVariantOnlinePrice').value = onlineDefault || '';

        if (form) form.classList.remove('was-validated');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceVariantModal')).show();
        requestAnimationFrame(function () {
            document.getElementById('serviceVariantName')?.focus();
        });
    }

    function collectVariantFromModal() {
        const name = (document.getElementById('serviceVariantName')?.value || '').trim();
        const description = (document.getElementById('serviceVariantDescription')?.value || '').trim();
        const duration = parseInt(document.getElementById('serviceVariantDuration')?.value, 10);
        const price = document.getElementById('serviceVariantPrice')?.value;
        const online = document.getElementById('serviceVariantOnlinePrice')?.value;
        if (!name) {
            showToast('Indique o nome da variante.', 'error');
            return null;
        }
        if (!duration || duration < 1) {
            showToast('Indique a duração da variante.', 'error');
            return null;
        }
        if (price === '' || price == null || isNaN(parseFloat(price))) {
            showToast('Indique o preço normal da variante.', 'error');
            return null;
        }
        if (online === '' || online == null || isNaN(parseFloat(online))) {
            showToast('Indique o preço online da variante.', 'error');
            return null;
        }
        if (parseFloat(online) > parseFloat(price)) {
            showToast('O preço online não pode ser superior ao preço normal.', 'error');
            return null;
        }
        return {
            name: name,
            description: description,
            duration: duration,
            price: price,
            online_price: online,
            type: document.getElementById('serviceVariantType')?.value === 'addon' ? 'addon' : 'choice',
        };
    }

    function ensureBaselineExists(prefix) {
        const items = serviceVariantsState[prefix];
        if (!items.length) return;
        if (!items.some(function (i) { return i.is_baseline; })) {
            // A base tem de ser de escolha única; se só restarem adicionais, a primeira passa a base.
            const firstChoice = items.find(function (i) { return i.type !== 'addon'; }) || items[0];
            firstChoice.is_baseline = true;
        }
        items.forEach(function (i) {
            if (i.is_baseline) i.type = 'choice';
        });
    }

    function addOrUpdateVariantFromModal() {
        const prefix = document.getElementById('serviceVariantPrefix')?.value;
        if (prefix !== 'add' && prefix !== 'edit') return;
        const data = collectVariantFromModal();
        if (!data) return;

        const editKey = document.getElementById('serviceVariantEditKey')?.value;
        if (editKey) {
            const item = serviceVariantsState[prefix].find(function (i) { return i.key === editKey; });
            if (item) {
                Object.assign(item, data);
            }
            renderServiceVariants(prefix);
            bootstrap.Modal.getInstance(document.getElementById('serviceVariantModal'))?.hide();
            return;
        }

        // Primeira variante: converter campos simples → item base + nova variante
        if (!variantsMode(prefix)) {
            const simple = readSimplePricing(prefix);
            if (!simple.duration || simple.price === '') {
                showToast('Preencha duração e preço normal antes de adicionar variantes.', 'error');
                return;
            }
            const baseName = simple.name || 'Serviço';
            const baseOnline = simple.online_price !== '' ? simple.online_price : simple.price;
            serviceVariantsState[prefix] = [
                {
                    key: nextVariantKey(),
                    id: null,
                    name: baseName,
                    description: simple.description || '',
                    duration: parseInt(simple.duration, 10) || 60,
                    price: simple.price,
                    online_price: baseOnline,
                    is_baseline: true,
                    type: 'choice',
                },
                {
                    key: nextVariantKey(),
                    id: null,
                    name: data.name,
                    description: data.description,
                    duration: data.duration,
                    price: data.price,
                    online_price: data.online_price,
                    is_baseline: false,
                    type: data.type,
                },
            ];
        } else {
            serviceVariantsState[prefix].push({
                key: nextVariantKey(),
                id: null,
                name: data.name,
                description: data.description,
                duration: data.duration,
                price: data.price,
                online_price: data.online_price,
                is_baseline: false,
                type: data.type,
            });
        }
        renderServiceVariants(prefix);
        bootstrap.Modal.getInstance(document.getElementById('serviceVariantModal'))?.hide();
    }

    function moveVariant(prefix, key, direction) {
        const items = serviceVariantsState[prefix];
        const idx = items.findIndex(function (i) { return i.key === key; });
        if (idx < 0) return;
        const target = direction === 'up' ? idx - 1 : idx + 1;
        if (target < 0 || target >= items.length) return;
        const tmp = items[idx];
        items[idx] = items[target];
        items[target] = tmp;
        renderServiceVariants(prefix);
    }

    function duplicateVariant(prefix, key) {
        const items = serviceVariantsState[prefix];
        const idx = items.findIndex(function (i) { return i.key === key; });
        if (idx < 0) return;
        const src = items[idx];
        const copy = {
            key: nextVariantKey(),
            id: null,
            name: (src.name || 'Variante') + ' (cópia)',
            description: src.description || '',
            duration: src.duration,
            price: src.price,
            online_price: src.online_price,
            is_baseline: false,
            type: src.type === 'addon' ? 'addon' : 'choice',
        };
        items.splice(idx + 1, 0, copy);
        renderServiceVariants(prefix);
    }

    function deleteVariant(prefix, key) {
        const items = serviceVariantsState[prefix];
        const idx = items.findIndex(function (i) { return i.key === key; });
        if (idx < 0) return;
        if (items.length <= 2) {
            // Eliminar a última variante → voltar aos 3 campos (mantém valores do item restante ou do eliminado)
            const remaining = items.length === 2
                ? items[idx === 0 ? 1 : 0]
                : items[0];
            if (remaining) {
                writeSimplePricing(prefix, remaining);
            }
            serviceVariantsState[prefix] = [];
            renderServiceVariants(prefix);
            return;
        }
        const wasBaseline = !!items[idx].is_baseline;
        items.splice(idx, 1);
        if (wasBaseline) {
            ensureBaselineExists(prefix);
        }
        renderServiceVariants(prefix);
    }

    function loadVariantsFromService(prefix, service) {
        const opts = Array.isArray(service.options)
            ? service.options.slice().sort(function (a, b) { return (a.sort_order || 0) - (b.sort_order || 0); })
            : [];
        if (opts.length < 2) {
            serviceVariantsState[prefix] = [];
            renderServiceVariants(prefix);
            return;
        }
        serviceVariantsState[prefix] = opts.map(function (opt) {
            return {
                key: nextVariantKey(),
                id: opt.id || null,
                name: opt.name || '',
                description: opt.description || '',
                duration: opt.duration,
                price: opt.price,
                online_price: opt.online_price,
                is_baseline: !!opt.is_baseline,
                type: opt.type === 'addon' && !opt.is_baseline ? 'addon' : 'choice',
            };
        });
        ensureBaselineExists(prefix);
        renderServiceVariants(prefix);
    }

    function resetServiceVariants(prefix) {
        serviceVariantsState[prefix] = [];
        renderServiceVariants(prefix);
    }

    function appendVariantsToFormData(formData, prefix) {
        const items = serviceVariantsState[prefix];
        if (items.length < 2) return;
        items.forEach(function (o, i) {
            formData.append('options[' + i + '][name]', o.name);
            formData.append('options[' + i + '][description]', o.description || '');
            formData.append('options[' + i + '][duration]', String(o.duration));
            formData.append('options[' + i + '][price]', String(o.price));
            formData.append('options[' + i + '][online_price]', String(o.online_price));
            formData.append('options[' + i + '][sort_order]', String(i));
            formData.append('options[' + i + '][is_baseline]', o.is_baseline ? '1' : '0');
            formData.append('options[' + i + '][type]', o.type === 'addon' && !o.is_baseline ? 'addon' : 'choice');
            if (o.id) {
                formData.append('options[' + i + '][id]', String(o.id));
            }
        });
    }

    function prepareServiceFormDataWithVariants(formData, prefix) {
        const hasVar = variantsMode(prefix);
        formData.set('has_options', hasVar ? '1' : '0');
        if (hasVar) {
            syncBaselineToSimpleFields(prefix);
            const baseline = serviceVariantsState[prefix].find(function (i) { return i.is_baseline; }) || serviceVariantsState[prefix][0];
            if (baseline) {
                formData.set('duration', String(baseline.duration));
                formData.set('price', String(baseline.price));
                formData.set('online_price', String(baseline.online_price));
            }
            appendVariantsToFormData(formData, prefix);
        }
    }

    document.getElementById('addServiceAddVariantBtn')?.addEventListener('click', function () {
        openVariantModal('add', null);
    });
    document.getElementById('editServiceAddVariantBtn')?.addEventListener('click', function () {
        openVariantModal('edit', null);
    });
    document.getElementById('serviceVariantForm')?.addEventListener('submit', function (e) {
        e.preventDefault();
        addOrUpdateVariantFromModal();
    });

    function bindVariantsListEvents(prefix) {
        const list = document.getElementById(pricingPrefixIds(prefix).list);
        if (!list || list.dataset.variantsBound === '1') return;
        list.dataset.variantsBound = '1';
        list.addEventListener('click', function (e) {
            const actionBtn = e.target.closest('.service-variant-action');
            if (actionBtn) {
                e.preventDefault();
                e.stopPropagation();
                const row = actionBtn.closest('.service-variant-row');
                const key = row?.getAttribute('data-variant-key');
                if (!key) return;
                const action = actionBtn.getAttribute('data-action');
                if (action === 'edit') {
                    const item = serviceVariantsState[prefix].find(function (i) { return i.key === key; });
                    if (item) openVariantModal(prefix, item);
                } else if (action === 'up') {
                    moveVariant(prefix, key, 'up');
                } else if (action === 'down') {
                    moveVariant(prefix, key, 'down');
                } else if (action === 'duplicate') {
                    duplicateVariant(prefix, key);
                } else if (action === 'delete') {
                    if (!confirm('Eliminar esta variante?')) return;
                    deleteVariant(prefix, key);
                }
                return;
            }
            if (e.target.closest('.service-variant-options-btn') || e.target.closest('.dropdown-menu') || e.target.closest('.service-drag-handle')) {
                return;
            }
            const row = e.target.closest('.service-variant-row');
            if (!row) return;
            const key = row.getAttribute('data-variant-key');
            const item = serviceVariantsState[prefix].find(function (i) { return i.key === key; });
            if (item) openVariantModal(prefix, item);
        });
    }
    bindVariantsListEvents('add');
    bindVariantsListEvents('edit');

    document.getElementById('addServiceCategorySelect')?.addEventListener('change', function () {
        if (variantsMode('add')) renderServiceVariants('add');
    });
    document.getElementById('editServiceCategoryId')?.addEventListener('change', function () {
        if (variantsMode('edit')) renderServiceVariants('edit');
    });

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
        else if (selectedCategoryId === 'archive') loadArchivedServices();
        else if (selectedCategoryId) loadServices(selectedCategoryId);
        updateCategoryBadges();
    }

    function updateArchivedBadge(count) {
        const badge = document.getElementById('archivedServicesBadge');
        if (badge) {
            badge.textContent = String(count ?? 0);
        }
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
    let editCategoryLoadToken = 0;

    function parseCategoryHiddenFromBooking(value) {
        return value === true || value === 1 || value === '1';
    }

    function isPersistedClinicalFieldId(id) {
        return id != null && id !== '' && /^\d+$/.test(String(id));
    }

    function setCategoryOffcanvasMode(mode) {
        const panelEl = document.getElementById('editCategoryModal');
        const titleEl = document.getElementById('editCategoryOffcanvasLabel');
        const methodEl = document.getElementById('editCategoryMethod');
        const submitBtn = document.getElementById('editCategorySubmitBtn');
        const isCreate = mode === 'create';
        if (panelEl) panelEl.dataset.mode = isCreate ? 'create' : 'edit';
        if (titleEl) titleEl.textContent = isCreate ? 'Nova categoria' : 'Editar categoria';
        if (methodEl) methodEl.value = isCreate ? 'POST' : 'PUT';
        if (submitBtn) submitBtn.textContent = isCreate ? 'Criar' : 'Guardar';
    }

    function fillEditCategoryModal(cat) {
        if (!cat || !cat.id) return;
        setCategoryOffcanvasMode('edit');
        document.getElementById('editCategoryId').value = cat.id;
        document.getElementById('editCategoryName').value = cat.name || '';
        document.getElementById('editCategoryDescription').value = cat.description || '';
        const hiddenBookingEl = document.getElementById('editCategoryHiddenFromBooking');
        if (hiddenBookingEl) {
            hiddenBookingEl.checked = parseCategoryHiddenFromBooking(cat.hidden_from_booking);
        }
        const color = String(cat.color || '').trim();
        const colorSelect = document.getElementById('editCategoryColorSelect');
        if (colorSelect) colorSelect.value = color;
        if (editCategoryColorChoices && typeof editCategoryColorChoices.setChoiceByValue === 'function') {
            editCategoryColorChoices.setChoiceByValue(color);
        }
        renderClinicalFieldsList(Array.isArray(cat.clinical_fields) ? cat.clinical_fields : []);
        hideClinicalFieldEditor();
    }

    function openCreateCategoryModal() {
        editCategoryLoadToken++;
        setCategoryOffcanvasMode('create');
        document.getElementById('editCategoryId').value = '';
        document.getElementById('editCategoryName').value = '';
        document.getElementById('editCategoryDescription').value = '';
        const hiddenBookingEl = document.getElementById('editCategoryHiddenFromBooking');
        if (hiddenBookingEl) hiddenBookingEl.checked = false;
        const colorSelect = document.getElementById('editCategoryColorSelect');
        if (colorSelect) colorSelect.value = '';
        if (editCategoryColorChoices && typeof editCategoryColorChoices.setChoiceByValue === 'function') {
            editCategoryColorChoices.setChoiceByValue('');
        }
        renderClinicalFieldsList([]);
        hideClinicalFieldEditor();
        const panelEl = document.getElementById('editCategoryModal');
        bootstrap.Offcanvas.getOrCreateInstance(panelEl).show();
        requestAnimationFrame(function() {
            document.getElementById('editCategoryName')?.focus();
        });
    }

    function resetEditCategoryModal() {
        const hiddenBookingEl = document.getElementById('editCategoryHiddenFromBooking');
        if (hiddenBookingEl) hiddenBookingEl.checked = false;
        document.getElementById('editCategoryId').value = '';
        setCategoryOffcanvasMode('edit');
        renderClinicalFieldsList([]);
        hideClinicalFieldEditor();
    }

    function saveDraftClinicalField(payload, editId) {
        let fields = currentClinicalFields();
        if (editId && String(editId) !== 'new') {
            fields = fields.map(function(f) {
                if (String(f.id) !== String(editId)) return f;
                return Object.assign({}, f, payload, { id: f.id });
            });
        } else {
            fields.push(Object.assign({}, payload, { id: 'draft_' + Date.now() }));
        }
        renderClinicalFieldsList(fields);
        hideClinicalFieldEditor();
        showToast(editId ? 'Campo atualizado.' : 'Campo adicionado.', 'success');
    }

    function persistDraftClinicalFields(categoryId, fields) {
        const drafts = (fields || []).filter(function(f) {
            return f && !isPersistedClinicalFieldId(f.id);
        });
        if (!drafts.length) return Promise.resolve();
        return drafts.reduce(function(chain, field) {
            return chain.then(function() {
                const body = {
                    label: field.label,
                    key: field.key || null,
                    input_type: field.input_type,
                    options: field.options || null,
                    is_required: !!field.is_required,
                    is_active: field.is_active !== false,
                };
                return fetch(`/categories/${categoryId}/clinical-fields`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body)
                }).then(function(r) {
                    return r.json().then(function(d) {
                        if (!r.ok) {
                            const first = d.errors ? Object.values(d.errors)[0] : null;
                            throw new Error((Array.isArray(first) ? first[0] : null) || d.message || 'Erro ao guardar campo');
                        }
                        return d;
                    });
                });
            });
        }, Promise.resolve());
    }

    function clinicalFieldTypeLabel(type) {
        return ({
            text: 'Texto',
            textarea: 'Texto longo',
            select: 'Select',
            multiselect: 'Multi-select',
            number: 'Número',
            bool: 'Sim/Não'
        })[type] || type;
    }

    let clinicalFieldKeyTouched = false;

    function clinicalFieldSlugify(label) {
        let key = String(label || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/_+/g, '_')
            .replace(/^_+|_+$/g, '');
        if (key && !/^[a-z]/.test(key)) {
            key = 'f_' + key;
        }
        return key.slice(0, 64);
    }

    function syncClinicalFieldKeyFromLabel() {
        if (clinicalFieldKeyTouched) return;
        const keyEl = document.getElementById('clinicalFieldKey');
        const labelEl = document.getElementById('clinicalFieldLabel');
        if (!keyEl || !labelEl) return;
        keyEl.value = clinicalFieldSlugify(labelEl.value);
    }

    function renderClinicalFieldsList(fields) {
        const list = document.getElementById('clinicalFieldsList');
        const empty = document.getElementById('clinicalFieldsEmpty');
        if (!list) return;
        const active = (fields || []).filter(function(f) { return f && f.is_active !== false; });
        const inactive = (fields || []).filter(function(f) { return f && f.is_active === false; });
        const all = active.concat(inactive);
        list.innerHTML = '';
        if (empty) empty.classList.toggle('d-none', all.length > 0);
        all.forEach(function(f) {
            const row = document.createElement('div');
            const isActive = f.is_active !== false;
            row.className = 'clinical-field-card';
            row.dataset.fieldId = String(f.id);
            row.innerHTML =
                '<div class="clinical-field-card__summary">' +
                    '<div class="min-w-0">' +
                        '<div class="fw-semibold small text-truncate">' + escapeHtml(f.label || f.key) +
                            (f.is_required ? ' <span class="text-danger">*</span>' : '') +
                        '</div>' +
                        '<div class="text-muted" style="font-size:0.72rem;">' +
                            '<span class="clinical-field-status clinical-field-status--' + (isActive ? 'active' : 'inactive') + '">' +
                                (isActive ? 'Ativo' : 'Inativo') +
                            '</span>' +
                            ' · ' + escapeHtml(f.key) +
                            ' · ' + escapeHtml(clinicalFieldTypeLabel(f.input_type)) +
                        '</div>' +
                    '</div>' +
                    '<div class="d-flex gap-2 flex-shrink-0 clinical-field-card__actions">' +
                        '<button type="button" class="btn btn-sm btn-outline-secondary clinical-field-edit" title="Editar" aria-label="Editar">' +
                            '<i class="ph ph-pencil-simple" aria-hidden="true"></i>' +
                        '</button>' +
                    '</div>' +
                '</div>';
            list.appendChild(row);
        });
        list._fields = fields || [];
    }

    function hideClinicalFieldEditor() {
        const modalEl = document.getElementById('clinicalFieldModal');
        if (modalEl) {
            bootstrap.Modal.getInstance(modalEl)?.hide();
        }
        const idEl = document.getElementById('clinicalFieldEditId');
        if (idEl) idEl.value = '';
    }

    function showClinicalFieldEditor(field) {
        const title = document.getElementById('clinicalFieldModalLabel');
        const isPersisted = !!(field && isPersistedClinicalFieldId(field.id));
        const hasId = !!(field && field.id);
        if (title) title.textContent = hasId ? 'Editar campo' : 'Adicionar campo';

        document.getElementById('clinicalFieldEditId').value = hasId ? String(field.id) : '';
        document.getElementById('clinicalFieldLabel').value = field && field.label ? field.label : '';
        document.getElementById('clinicalFieldKey').value = field && field.key ? field.key : '';
        document.getElementById('clinicalFieldType').value = field && field.input_type ? field.input_type : 'text';
        document.getElementById('clinicalFieldOptions').value = field && Array.isArray(field.options) ? field.options.join('\n') : '';
        document.getElementById('clinicalFieldRequired').checked = !!(field && field.is_required);
        document.getElementById('clinicalFieldActive').checked = !field || field.is_active !== false;
        clinicalFieldKeyTouched = isPersisted || (hasId && !!field.key);
        const keyEl = document.getElementById('clinicalFieldKey');
        if (keyEl) {
            keyEl.readOnly = isPersisted;
            keyEl.classList.toggle('bg-body-secondary', isPersisted);
            keyEl.title = isPersisted ? 'A chave não pode ser alterada depois de criar o campo.' : '';
            keyEl.required = !isPersisted;
        }
        syncClinicalFieldOptionsVisibility();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('clinicalFieldModal')).show();
        requestAnimationFrame(function () {
            document.getElementById('clinicalFieldLabel')?.focus();
        });
    }

    function syncClinicalFieldOptionsVisibility() {
        const type = document.getElementById('clinicalFieldType')?.value;
        const wrap = document.getElementById('clinicalFieldOptionsWrap');
        if (!wrap) return;
        const needs = type === 'select' || type === 'multiselect';
        wrap.classList.toggle('d-none', !needs);
    }

    function currentClinicalFields() {
        const list = document.getElementById('clinicalFieldsList');
        return (list && Array.isArray(list._fields)) ? list._fields.slice() : [];
    }

    function openEditCategoryModal(categoryId) {
        const loadToken = ++editCategoryLoadToken;
        fetch(`/categories/${categoryId}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(cat => {
                if (loadToken !== editCategoryLoadToken) return;
                fillEditCategoryModal(cat);
                const panelEl = document.getElementById('editCategoryModal');
                panelEl.addEventListener('shown.bs.offcanvas', function once() {
                    panelEl.removeEventListener('shown.bs.offcanvas', once);
                    requestAnimationFrame(function() { fillEditCategoryModal(cat); });
                }, { once: true });
                bootstrap.Offcanvas.getOrCreateInstance(panelEl).show();
            })
            .catch(err => { console.error(err); showToast('Erro ao carregar categoria', 'error'); });
    }

    document.getElementById('clinicalFieldAddBtn')?.addEventListener('click', function() {
        showClinicalFieldEditor(null);
    });
    document.getElementById('clinicalFieldType')?.addEventListener('change', syncClinicalFieldOptionsVisibility);
    document.getElementById('clinicalFieldLabel')?.addEventListener('input', syncClinicalFieldKeyFromLabel);
    document.getElementById('clinicalFieldKey')?.addEventListener('input', function() {
        clinicalFieldKeyTouched = true;
    });
    document.getElementById('clinicalFieldsList')?.addEventListener('click', function(e) {
        const row = e.target.closest('.clinical-field-card[data-field-id]');
        if (!row) return;
        const fieldId = row.dataset.fieldId;
        const field = currentClinicalFields().find(function(f) { return String(f.id) === String(fieldId); });
        if (field) showClinicalFieldEditor(field);
    });

    function saveClinicalFieldFromModal() {
        const categoryId = document.getElementById('editCategoryId')?.value;
        const editId = document.getElementById('clinicalFieldEditId')?.value;
        const type = document.getElementById('clinicalFieldType')?.value;
        const optionsRaw = document.getElementById('clinicalFieldOptions')?.value || '';
        const options = optionsRaw.split(/\r?\n/).map(function(s) { return s.trim(); }).filter(Boolean);
        const payload = {
            label: (document.getElementById('clinicalFieldLabel')?.value || '').trim(),
            key: (document.getElementById('clinicalFieldKey')?.value || '').trim() || null,
            input_type: type,
            options: (type === 'select' || type === 'multiselect') ? options : null,
            is_required: !!document.getElementById('clinicalFieldRequired')?.checked,
            is_active: !!document.getElementById('clinicalFieldActive')?.checked,
        };
        if (!payload.label) {
            showToast('Indique o label do campo.', 'error');
            return;
        }
        if (!categoryId) {
            saveDraftClinicalField(payload, editId);
            return;
        }
        const url = isPersistedClinicalFieldId(editId)
            ? `/categories/${categoryId}/clinical-fields/${editId}`
            : `/categories/${categoryId}/clinical-fields`;
        const method = isPersistedClinicalFieldId(editId) ? 'PUT' : 'POST';
        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload)
        })
            .then(function(r) {
                return r.json().then(function(d) {
                    if (!r.ok) {
                        const first = d.errors ? Object.values(d.errors)[0] : null;
                        throw new Error((Array.isArray(first) ? first[0] : null) || d.message || 'Erro ao guardar campo');
                    }
                    return d;
                });
            })
            .then(function() {
                showToast(isPersistedClinicalFieldId(editId) ? 'Campo atualizado.' : 'Campo criado.', 'success');
                return fetch(`/categories/${categoryId}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            })
            .then(function(r) { return r.json(); })
            .then(function(cat) { renderClinicalFieldsList(cat.clinical_fields || []); hideClinicalFieldEditor(); })
            .catch(function(err) { showToast(err.message || 'Erro ao guardar campo', 'error'); });
    }

    document.getElementById('clinicalFieldForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        saveClinicalFieldFromModal();
    });
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
        const editColorEl = document.getElementById('editCategoryColorSelect');
        if (editColorEl && !editColorEl.closest('.choices')) {
            editCategoryColorChoices = new Choices(editColorEl, colorChoicesOptions);
        }
    }

    document.getElementById('openCreateCategoryBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        openCreateCategoryModal();
    });

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

    function updateCategoryBookingHiddenIndicator(sidebarItem, hidden) {
        if (!sidebarItem) return;
        sidebarItem.setAttribute('data-hidden-from-booking', hidden ? '1' : '0');
        let icon = sidebarItem.querySelector('.services-category-hidden-booking-icon');
        if (hidden) {
            if (!icon) {
                icon = document.createElement('i');
                icon.className = 'ph ph-eye-slash text-muted small services-category-hidden-booking-icon';
                icon.title = 'Oculta no booking';
                icon.setAttribute('aria-hidden', 'true');
                const editBtn = sidebarItem.querySelector('.services-category-sidebar-edit');
                const badge = sidebarItem.querySelector('.badge');
                const before = editBtn || badge;
                if (before) sidebarItem.insertBefore(icon, before);
                else sidebarItem.appendChild(icon);
            }
        } else if (icon) {
            icon.remove();
        }
    }

    // Category selection + editar (ícone no hover da sidebar) + Nova categoria / Arquivo
    const categoriesSidebar = document.getElementById('servicesSidebar');
    if (categoriesSidebar) {
        categoriesSidebar.addEventListener('click', function(e) {
            const createBtn = e.target.closest('[data-action="create-category"]');
            if (createBtn) {
                e.preventDefault();
                e.stopPropagation();
                openCreateCategoryModal();
                return;
            }
            const editBtn = e.target.closest('.category-header-edit-btn');
            if (editBtn) {
                e.preventDefault();
                e.stopPropagation();
                const item = editBtn.closest('.contacts-group-item[data-category-id]');
                const categoryId = item && item.getAttribute('data-category-id');
                if (categoryId && categoryId !== 'all' && categoryId !== 'archive') {
                    openEditCategoryModal(categoryId);
                }
                return;
            }
            const item = e.target.closest('.contacts-group-item[data-category-id]');
            if (!item || !categoriesSidebar.contains(item)) return;
            e.preventDefault();
            selectCategory(item.getAttribute('data-category-id'));
        });
    }

    // Ações do cabeçalho da categoria (Editar / Adicionar serviço / Eliminar)
    const listContainer = document.getElementById('servicesListContainer');
    if (listContainer) {
        listContainer.addEventListener('click', function (e) {
            const membersBtn = e.target.closest('.service-item-members-trigger');
            if (membersBtn && listContainer.contains(membersBtn)) {
                e.preventDefault();
                e.stopPropagation();
                openServiceMembersModal(membersBtn);
            }
        }, true);

        listContainer.addEventListener('click', function(e) {
            const header = e.target.closest('.services-category-header');
            if (!header) return;
            const categoryId = header.getAttribute('data-category-id');
            if (!categoryId) return;

            if (e.target.closest('.category-header-edit-btn')) {
                e.preventDefault();
                openEditCategoryModal(categoryId);
                return;
            }
            if (e.target.closest('.category-header-add-service-btn')) {
                e.preventDefault();
                document.getElementById('addServiceCategoryId').value = categoryId;
                const addSelect = document.getElementById('addServiceCategorySelect');
                if (addSelect) addSelect.value = categoryId;
                bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('addServiceModal')).show();
                return;
            }
            if (e.target.closest('.category-header-delete-btn')) {
                e.preventDefault();
                if (!canDeleteCatalog) {
                    showToast('Sem permissão para eliminar categorias.', 'error');
                    return;
                }
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
        
        // Update active state (categorias + Arquivo)
        const sidebar = document.getElementById('servicesSidebar');
        if (sidebar) {
            sidebar.querySelectorAll('.contacts-group-item[data-category-id]').forEach(item => {
                item.classList.toggle('active', item.getAttribute('data-category-id') === categoryId);
            });
        }

        if (categoryId === 'all') loadAllServices();
        else if (categoryId === 'archive') loadArchivedServices();
        else loadServices(categoryId);
    }

    /** GET /services/arquivo → serviços soft-deleted. */
    function loadArchivedServices() {
        fetch('/services/arquivo', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('servicesListContainer');
            const services = data.services || [];
            updateArchivedBadge(data.archived_services_count ?? services.length);
            container.innerHTML = `
                <div class="services-category-header mb-3 d-flex align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center min-w-0">
                        <h5 class="mb-0 services-category-title text-truncate">Arquivo</h5>
                    </div>
                </div>
                <div id="servicesList" data-archived="1">
                    ${renderServicesList(services, '#6c757d', { archived: true })}
                </div>
            `;
            if (servicesDrake) { servicesDrake.destroy(); servicesDrake = null; }
            if (servicesDrakesAll.length) {
                servicesDrakesAll[0].destroy();
                servicesDrakesAll = [];
            }
            attachServiceEventListeners();
            applyServiceSearch();
        })
        .catch(err => { console.error(err); showToast('Erro ao carregar o Arquivo', 'error'); });
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
                            ${categoryHeaderHtml(cat.id, cat.name)}
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
            applyServiceSearch();
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
                ${categoryHeaderHtml(category.id, category.name)}
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
            applyServiceSearch();
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
            let total = 0;
            categories.forEach(cat => {
                if (!cat?.id) return;
                const el = document.querySelector(`#categoriesList [data-category-id="${cat.id}"]`);
                const badge = el?.querySelector('.badge');
                const count = cat.services_count ?? (Array.isArray(cat.services) ? cat.services.length : 0);
                total += Number(count) || 0;
                if (badge) {
                    badge.textContent = count;
                }
            });
            const allBadge = document.querySelector('#categoriesList [data-category-id="all"] .badge');
            if (allBadge) {
                allBadge.textContent = total;
            }
            if (data && typeof data === 'object' && !Array.isArray(data) && data.archived_services_count != null) {
                updateArchivedBadge(data.archived_services_count);
            }
        })
        .catch(err => console.warn('updateCategoryBadges:', err));
    }

    // --- Render da lista de serviços (HTML) ---
    function renderServicesList(services, categoryColor, options) {
        const archived = !!(options && options.archived);
        const borderColor = categoryColor || '#6c757d';
        if (services.length === 0) {
            return `
                <div class="service-item-row service-empty-placeholder">
                    <div class="service-drag-handle" style="visibility: hidden;" aria-hidden="true"></div>
                    <div class="card service-item">
                        <div class="card-body">
                            <p class="text-muted small mb-0">${archived ? 'Nenhum serviço no Arquivo.' : 'Nenhum serviço nesta categoria.'}</p>
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
            const variantsN = opts.length;
            let fromDurationLabel = '';
            if (hasOpts) {
                const minDur = Math.min.apply(null, opts.map(function (x) { return parseInt(x.duration, 10) || 0; }));
                fromDurationLabel = formatDuration(minDur);
            }
            const membersTrigger = renderServiceMembersTrigger(service);
            const variantsBadges = hasOpts
                ? `<span class="badge text-bg-light">${variantsN} variante${variantsN === 1 ? '' : 's'}</span>` +
                  (extrasN > 0 ? `<span class="badge text-bg-light">${extrasN} extra(s)</span>` : '') +
                  membersTrigger
                : '';
            const simpleBadges = !hasOpts
                ? (extrasN > 0 ? `<span class="badge text-bg-light">${extrasN} extra(s)</span>` : '') + membersTrigger
                : '';
            const categoryMeta = archived && service.category && service.category.name
                ? `<div class="text-muted small">${escapeHtml(service.category.name)}</div>`
                : '';
            const hiddenBooking = !!service.hidden_from_booking;
            const hiddenIcon = hiddenBooking
                ? '<i class="ph ph-eye-slash text-muted small ms-1" title="Oculto no booking" aria-label="Oculto no booking"></i>'
                : '';
            const nameHtml = escapeHtml(service.name) + hiddenIcon;
            const restoreBtn = archived && canDeleteCatalog
                ? `<button type="button" class="btn btn-sm btn-outline-primary service-archive-restore-btn" data-restore-service-id="${service.id}">Restaurar</button>`
                : '';
            const rowHasOptionsClass = (hasOpts ? ' service-item-row--has-options' : '') + (archived ? ' service-item-row--archived' : '');
            const clickableClass = archived ? 'service-item' : 'service-item service-item-clickable';
            let bodyHtml;
            if (hasOpts) {
                const variantRows = opts
                    .map(function (opt) {
                        return (
                            '<div class="service-item-variant-row">' +
                            '<span class="service-item-variant-name">' +
                            escapeHtml(opt.name) +
                            '</span>' +
                            '<span class="service-item-variant-duration">' +
                            escapeHtml(formatDuration(opt.duration)) +
                            '</span>' +
                            '<span class="service-item-variant-price">' +
                            escapeHtml(formatPrice(opt.price)) +
                            '</span>' +
                            '</div>'
                        );
                    })
                    .join('');
                bodyHtml =
                    `<div class="card-body service-item-body--has-options py-3 pe-2">` +
                    `<div class="service-item-header">` +
                    `<div class="min-w-0"><h6 class="mb-0 service-item-name">${nameHtml}</h6>` +
                    `${categoryMeta}<div class="service-item-badges">${variantsBadges}${restoreBtn}</div></div>` +
                    `<span class="service-item-desde-duration" title="Menor duração"><span class="small fw-normal">Desde</span> ${escapeHtml(fromDurationLabel)}</span>` +
                    `<span class="service-item-price" title="Menor preço online"><span class="small fw-normal">Desde</span> ${formatPrice(fromOnline)}</span>` +
                    `</div>` +
                    `<div class="service-item-variants">${variantRows}</div>` +
                    `</div>`;
            } else {
                bodyHtml =
                    `<div class="card-body service-item-body--simple py-3 pe-2">` +
                    `<div class="service-item-header">` +
                    `<div class="min-w-0"><h6 class="mb-0 service-item-name">${nameHtml}</h6>` +
                    `${categoryMeta}<div class="service-item-badges">${simpleBadges}${restoreBtn}</div></div>` +
                    `<span class="service-item-desde-duration" title="Duração">${escapeHtml(formatDuration(service.duration))}</span>` +
                    `<span class="service-item-price" title="Preço normal">${formatPrice(service.price)}</span>` +
                    `</div></div>`;
            }
            return `
            <div class="service-item-row${rowHasOptionsClass}" data-service-id="${service.id}" data-hidden-from-booking="${hiddenBooking ? '1' : '0'}"${archived ? ' data-archived="1"' : ''}>
                <div class="service-drag-handle" aria-label="Arrastar para reordenar">
                    <span class="service-drag-dots"><span></span><span></span><span></span><span></span><span></span><span></span></span>
                </div>
                <div class="card ${clickableClass}" style="--service-category-color: ${borderColor};">
                    ${bodyHtml}
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
    document.getElementById('editCategoryForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        const panelEl = document.getElementById('editCategoryModal');
        const isCreate = panelEl?.dataset.mode === 'create';
        const categoryId = document.getElementById('editCategoryId').value;
        const draftFields = currentClinicalFields();

        submitBtn.disabled = true;
        submitBtn.innerHTML = isCreate
            ? '<span class="spinner-border spinner-border-sm me-1"></span> A criar...'
            : '<span class="spinner-border spinner-border-sm me-1"></span> A guardar...';

        const formData = new FormData(this);
        formData.delete('hidden_from_booking');
        formData.append('hidden_from_booking', document.getElementById('editCategoryHiddenFromBooking')?.checked ? '1' : '0');
        if (isCreate) {
            formData.delete('_method');
            formData.delete('category_id');
        }

        const url = isCreate ? '/categories' : `/categories/${categoryId}`;
        const headers = {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        };
        if (!isCreate) {
            headers['X-HTTP-Method-Override'] = 'PUT';
        }

        fetch(url, {
            method: 'POST',
            headers: headers,
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(function(d) {
                    const first = d.errors ? Object.values(d.errors)[0] : null;
                    throw new Error((Array.isArray(first) ? first[0] : null) || d.message || `HTTP error! status: ${response.status}`);
                }).catch(function(err) {
                    if (err instanceof Error && err.message && !err.message.startsWith('HTTP error')) throw err;
                    throw new Error(`HTTP error! status: ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || (isCreate ? 'Erro ao criar categoria' : 'Erro ao atualizar categoria'));
            }
            if (isCreate) {
                const newId = data.category && data.category.id;
                if (!newId) throw new Error('Categoria criada sem id.');
                return persistDraftClinicalFields(newId, draftFields).then(function() {
                    showToast(data.message, 'success');
                    bootstrap.Offcanvas.getInstance(panelEl)?.hide();
                    location.reload();
                });
            }
            showToast(data.message, 'success');
            bootstrap.Offcanvas.getInstance(panelEl)?.hide();
            const cat = data.category;
            if (cat && cat.id) {
                const sidebarItem = document.querySelector(`.contacts-group-item[data-category-id="${cat.id}"]`);
                if (sidebarItem) {
                    const dot = sidebarItem.querySelector('.contacts-group-dot');
                    if (dot) dot.style.background = cat.color || '';
                    const nameEl = sidebarItem.querySelector('.contacts-group-item__name');
                    if (nameEl) nameEl.textContent = cat.name || '';
                    else {
                        const spans = sidebarItem.querySelectorAll('span');
                        if (spans.length >= 2) spans[1].textContent = cat.name || '';
                    }
                    sidebarItem.setAttribute('data-category-name', cat.name || '');
                    sidebarItem.setAttribute('data-category-color', cat.color || '');
                    updateCategoryBookingHiddenIndicator(sidebarItem, !!cat.hidden_from_booking);
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
        })
        .catch(error => {
            console.error('Error saving category:', error);
            showToast(error.message || (isCreate ? 'Erro ao criar categoria.' : 'Erro ao atualizar categoria.'), 'error');
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
        prepareServiceFormDataWithVariants(formData, 'add');

        // Se estiver em "Todas as categorias", não mudar a vista após criar (manter-se nesta vista)
        const categoryId = formData.get('category_id') || document.getElementById('addServiceCategoryId')?.value || document.getElementById('addServiceCategorySelect')?.value;
        if (categoryId && selectedCategoryId !== 'all') {
            selectedCategoryId = categoryId;
        }

        formData.set('sync_extras', '1');
        const extraIds = Array.from(document.querySelectorAll('#addServiceModal .service-extra-cb:checked')).map(cb => cb.value);
        extraIds.forEach(id => formData.append('extra_ids[]', id));
        formData.set('sync_fees', '1');
        const feeIds = Array.from(document.querySelectorAll('#addServiceModal .service-fee-cb:checked')).map(cb => cb.value);
        feeIds.forEach(id => formData.append('fee_ids[]', id));
        formData.delete('hidden_from_booking');
        formData.append('hidden_from_booking', document.getElementById('addServiceHiddenFromBooking')?.checked ? '1' : '0');

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
                    bootstrap.Offcanvas.getInstance(document.getElementById('addServiceModal'))?.hide();
                    this.reset();
                    const hiddenCb = document.getElementById('addServiceHiddenFromBooking');
                    if (hiddenCb) {
                        hiddenCb.checked = false;
                    }
                    resetServiceVariants('add');
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

        prepareServiceFormDataWithVariants(formData, 'edit');

        formData.set('sync_extras', '1');
        const extraIds = Array.from(document.querySelectorAll('#editServiceModal .service-extra-cb:checked')).map(cb => cb.value);
        formData.delete('extra_ids[]');
        extraIds.forEach(id => formData.append('extra_ids[]', id));
        formData.set('sync_fees', '1');
        const feeIds = Array.from(document.querySelectorAll('#editServiceModal .service-fee-cb:checked')).map(cb => cb.value);
        formData.delete('fee_ids[]');
        feeIds.forEach(id => formData.append('fee_ids[]', id));
        formData.delete('hidden_from_booking');
        formData.append('hidden_from_booking', document.getElementById('editServiceHiddenFromBooking')?.checked ? '1' : '0');
        
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
                    bootstrap.Offcanvas.getInstance(document.getElementById('editServiceModal'))?.hide();
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
                const hiddenBookingEl = document.getElementById('editServiceHiddenFromBooking');
                if (hiddenBookingEl) {
                    hiddenBookingEl.checked = !!service.hidden_from_booking;
                }

                loadVariantsFromService('edit', service);

                document.querySelectorAll('#editServiceModal .service-extra-cb').forEach(cb => {
                    cb.checked =
                        service.extras &&
                        service.extras.some(extra => Number(extra.id) === Number(cb.value));
                });
                if (typeof window.syncServiceExtrasSelectState === 'function') {
                    window.syncServiceExtrasSelectState(document.getElementById('editServiceModal'));
                }
                document.querySelectorAll('#editServiceModal .service-fee-cb').forEach(cb => {
                    cb.checked =
                        service.fees &&
                        service.fees.some(fee => Number(fee.id) === Number(cb.value));
                });

                bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('editServiceModal')).show();
            })
            .catch(error => {
                console.error('Error loading service:', error);
                showToast('Erro ao carregar serviço', 'error');
            });
    }

    function deleteServiceById(serviceId) {
        if (!canDeleteCatalog) {
            showToast('Sem permissão para eliminar serviços.', 'error');
            return;
        }

        fetch(`/services/${serviceId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })
            .then(response => response.json().then(data => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (ok && data.success) {
                    showToast(data.message, 'success');
                    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('editServiceModal')).hide();
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

    function restoreServiceById(serviceId) {
        if (!canDeleteCatalog) {
            showToast('Sem permissão para restaurar serviços.', 'error');
            return;
        }

        fetch(`/services/${serviceId}/restore`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })
            .then(response => response.json().then(data => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (ok && data.success) {
                    showToast(data.message, 'success');
                    refreshAfterServiceChange();
                } else {
                    showToast(data.message || 'Erro ao restaurar serviço', 'error');
                }
            })
            .catch(error => {
                console.error('Error restoring service:', error);
                showToast('Erro ao restaurar serviço', 'error');
            });
    }

    // --- Lista dinâmica: abrir editar ao clicar no card ---
    function attachServiceEventListeners() {
        document.querySelectorAll('.service-archive-restore-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const serviceId = this.getAttribute('data-restore-service-id');
                if (serviceId) {
                    restoreServiceById(serviceId);
                }
            });
        });

        document.querySelectorAll('.service-item-row[data-service-id] .service-item-clickable').forEach(card => {
            card.addEventListener('click', function (e) {
                if (
                    e.target.closest('.service-drag-handle') ||
                    e.target.closest('.service-archive-restore-btn') ||
                    e.target.closest('.service-item-members-trigger')
                ) {
                    return;
                }
                const row = card.closest('.service-item-row[data-service-id]');
                if (row && row.getAttribute('data-archived') === '1') {
                    return;
                }
                const serviceId = row ? row.getAttribute('data-service-id') : null;
                if (!serviceId) {
                    return;
                }
                openEditServiceModal(serviceId);
            });
        });
    }

    // --- Reset dos botões ao fechar modais + pesquisa + toast ---
    document.getElementById('editCategoryModal')?.addEventListener('hidden.bs.offcanvas', function() {
        const submitBtn = document.getElementById('editCategorySubmitBtn');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = this.dataset.mode === 'create' ? 'Criar' : 'Guardar';
        }
        resetEditCategoryModal();
    });

    document.getElementById('addServiceModal')?.addEventListener('hidden.bs.offcanvas', function() {
        const submitBtn = document.getElementById('addServiceSubmitBtn');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Criar';
        }
        resetServiceVariants('add');
    });

    document.getElementById('editServiceModal')?.addEventListener('hidden.bs.offcanvas', function() {
        const submitBtn = document.getElementById('editServiceSubmitBtn');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Guardar';
        }
        resetServiceVariants('edit');
    });

    document.getElementById('editServiceDeleteBtn')?.addEventListener('click', function (e) {
        e.preventDefault();
        const serviceId = document.getElementById('editServiceId')?.value;
        if (!serviceId) {
            return;
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteServiceConfirmModal')).show();
    });

    document.getElementById('deleteServiceConfirmBtn')?.addEventListener('click', function () {
        const serviceId = document.getElementById('editServiceId')?.value;
        if (!serviceId) {
            return;
        }
        bootstrap.Modal.getInstance(document.getElementById('deleteServiceConfirmModal'))?.hide();
        deleteServiceById(serviceId);
    });

    // Initialize on page load
    attachServiceEventListeners();
    initServicesDragula();

    /** Filtra linhas pelo termo actual (mantém-se após editar/guardar / mudança de categoria). */
    function applyServiceSearch() {
        const input = document.getElementById('serviceSearch');
        const searchTerm = (input?.value || '').toLowerCase().trim();

        document.querySelectorAll('.service-item-row').forEach(function (row) {
            if (row.classList.contains('service-empty-placeholder')) {
                row.style.display = searchTerm ? 'none' : '';
                return;
            }
            const text = (row.textContent || '').toLowerCase();
            row.style.display = (!searchTerm || text.includes(searchTerm)) ? '' : 'none';
        });

        document.querySelectorAll('[data-category-block]').forEach(function (block) {
            if (!searchTerm) {
                block.style.display = '';
                return;
            }
            const hasVisible = Array.from(
                block.querySelectorAll('.service-item-row:not(.service-empty-placeholder)')
            ).some(function (row) {
                return row.style.display !== 'none';
            });
            block.style.display = hasVisible ? '' : 'none';
        });
    }

    document.getElementById('serviceSearch')?.addEventListener('input', applyServiceSearch);

    // Usa showToast global do layout (cores soft, bottom center)
});
