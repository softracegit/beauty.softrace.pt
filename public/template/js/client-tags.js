(function (window, document) {
    'use strict';

    var catalogCache = null;
    var catalogPromise = null;
    var openMenuRoot = null;
    var savingRoots = new WeakSet();

    function maxTagsPerClient() {
        var cfg = window.CLIENT_TAGS_CONFIG && window.CLIENT_TAGS_CONFIG.maxPerClient;
        return (typeof cfg === 'number' && cfg > 0) ? cfg : 5;
    }

    function isAtTagLimit(tagCount) {
        return tagCount >= maxTagsPerClient();
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function toast(msg, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, type || 'success');
        }
    }

    function apiError(data, fallback) {
        if (data && data.message) return data.message;
        if (data && data.errors) {
            var vals = Object.values(data.errors);
            if (vals.length && Array.isArray(vals[0])) return vals[0][0];
        }
        return fallback;
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function catalogUrl() {
        return (window.CLIENT_TAGS_CONFIG && window.CLIENT_TAGS_CONFIG.catalogUrl) || '/client-tags';
    }

    function fetchCatalog() {
        if (catalogCache) return Promise.resolve(catalogCache);
        if (catalogPromise) return catalogPromise;
        catalogPromise = fetch(catalogUrl(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) throw new Error('Não foi possível carregar etiquetas.');
                catalogCache = data.tags || [];
                return catalogCache;
            });
        }).finally(function () {
            catalogPromise = null;
        });
        return catalogPromise;
    }

    function invalidateCatalog() {
        catalogCache = null;
        catalogPromise = null;
    }

    function mergeIntoCatalog(tags) {
        if (!Array.isArray(tags) || tags.length === 0 || !catalogCache) return;

        var indexById = {};
        catalogCache.forEach(function (t, i) {
            indexById[String(t.id)] = i;
        });

        tags.forEach(function (t) {
            if (!t || t.id == null) return;
            var key = String(t.id);
            if (indexById[key] !== undefined) {
                if (t.name) catalogCache[indexById[key]].name = t.name;
                return;
            }
            catalogCache.push({ id: t.id, name: t.name });
            indexById[key] = catalogCache.length - 1;
        });
    }

    function afterSaveCatalogUpdate(saved, newNames) {
        var createdNew = Array.isArray(newNames) && newNames.length > 0;
        if (!createdNew) return;

        if (catalogCache) {
            mergeIntoCatalog(saved);
            return;
        }

        fetchCatalog().catch(function () {
            catalogCache = null;
        });
    }

    function preloadCatalog() {
        if (catalogCache || catalogPromise) return;
        fetchCatalog().catch(function () {
            catalogCache = null;
        });
    }

    function parseTags(root) {
        try {
            return JSON.parse(root.getAttribute('data-tags') || '[]');
        } catch (e) {
            return [];
        }
    }

    function chipHtml(tag, readonly) {
        var remove = readonly ? '' :
            '<button type="button" class="client-tag-chip__remove" data-tag-id="' + tag.id + '" aria-label="Remover"><i class="ph ph-x" aria-hidden="true"></i></button>';
        return '<span class="client-tag-chip" title="' + escapeHtml(tag.name) + '">' +
            '<span class="client-tag-chip__label">' + escapeHtml(tag.name) + '</span>' + remove + '</span>';
    }

    function addButtonHtml(readonly, tagCount) {
        if (readonly || isAtTagLimit(tagCount)) return '';
        var hasTags = tagCount > 0;
        var emptyClass = hasTags ? '' : ' client-tags-inline__add--empty';
        var label = hasTags ? '' : '<span class="client-tags-inline__add-label">Etiqueta</span>';
        return '<button type="button" class="client-tags-inline__add' + emptyClass + '" title="Adicionar etiqueta" aria-label="Adicionar etiqueta">' +
            label + '<i class="ph ph-plus" aria-hidden="true"></i></button>';
    }

    function render(root) {
        var tags = parseTags(root);
        var readonly = root.getAttribute('data-readonly') === '1';
        var variant = root.getAttribute('data-variant') || '';
        root.className = 'client-tags-inline' + (variant ? ' client-tags-inline--' + variant : '');

        var chips = tags.map(function (t) { return chipHtml(t, readonly); }).join('');
        var addBtn = addButtonHtml(readonly, tags.length);

        root.innerHTML =
            '<div class="client-tags-inline__row">' + chips + addBtn + '</div>' +
            '<div class="client-tags-inline__menu d-none" role="listbox" aria-label="Etiquetas">' +
            '<input type="text" class="client-tags-inline__input" maxlength="80" placeholder="Etiqueta…" autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list">' +
            '<div class="client-tags-inline__suggestions"></div>' +
            '<div class="client-tags-inline__hint">↑↓ para escolher · Enter para associar</div>' +
            '</div>';
    }

    function syncTags(root, tags) {
        root.setAttribute('data-tags', JSON.stringify(tags));
        render(root);
    }

    function saveTags(root, tagIds, newNames) {
        if (savingRoots.has(root)) {
            return Promise.resolve(parseTags(root));
        }

        var syncUrl = root.getAttribute('data-sync-url');
        if (!syncUrl) return Promise.reject(new Error('Cliente inválido.'));

        savingRoots.add(root);

        return fetch(syncUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ tag_ids: tagIds, new_tag_names: newNames || [] })
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) throw new Error(apiError(data, 'Não foi possível guardar.'));
                return data.tags || [];
            });
        }).then(function (saved) {
            afterSaveCatalogUpdate(saved, newNames);
            syncTags(root, saved);
            return saved;
        }).finally(function () {
            savingRoots.delete(root);
        });
    }

    function closeMenu() {
        if (openMenuRoot) {
            var menu = openMenuRoot.querySelector('.client-tags-inline__menu');
            var input = openMenuRoot.querySelector('.client-tags-inline__input');
            if (menu) {
                menu.classList.add('d-none');
                menu.classList.remove('client-tags-inline__menu--up', 'client-tags-inline__menu--fixed');
                menu.style.position = '';
                menu.style.left = '';
                menu.style.top = '';
                menu.style.width = '';
                menu.style.maxWidth = '';
            }
            if (input) input.setAttribute('aria-expanded', 'false');
            openMenuRoot._tagSuggestionIndex = -1;
            openMenuRoot = null;
        }
    }

    function getSuggestions(root) {
        return Array.prototype.slice.call(root.querySelectorAll('.client-tags-inline__suggestion'));
    }

    function setSuggestionHighlight(root, index) {
        var items = getSuggestions(root);
        if (!items.length) {
            root._tagSuggestionIndex = -1;
            return;
        }
        if (index < 0) index = items.length - 1;
        if (index >= items.length) index = 0;
        items.forEach(function (el, i) {
            el.classList.toggle('client-tags-inline__suggestion--active', i === index);
        });
        root._tagSuggestionIndex = index;
        var active = items[index];
        if (active && typeof active.scrollIntoView === 'function') {
            active.scrollIntoView({ block: 'nearest' });
        }
    }

    function resetSuggestionHighlight(root) {
        root._tagSuggestionIndex = -1;
        getSuggestions(root).forEach(function (el) {
            el.classList.remove('client-tags-inline__suggestion--active');
        });
    }

    function renderSuggestions(root, q) {
        var list = root.querySelector('.client-tags-inline__suggestions');
        if (!list) return;
        var current = parseTags(root);
        var ql = (q || '').trim().toLowerCase();

        return fetchCatalog().then(function (catalog) {
            var items = catalog.filter(function (tag) {
                if (current.some(function (t) { return String(t.id) === String(tag.id); })) return false;
                if (!ql) return true;
                return String(tag.name).toLowerCase().indexOf(ql) !== -1;
            }).slice(0, 8);

            list.innerHTML = items.map(function (tag) {
                return '<button type="button" class="client-tags-inline__suggestion" data-tag-id="' + tag.id + '" role="option">' +
                    '<span class="client-tag-chip">' + escapeHtml(tag.name) + '</span></button>';
            }).join('');

            if (!list.innerHTML && ql) {
                list.innerHTML = '<div class="client-tags-inline__hint px-1">Enter — criar «' + escapeHtml(q.trim()) + '»</div>';
            }

            resetSuggestionHighlight(root);
        });
    }

    function positionTagMenu(root) {
        var menu = root.querySelector('.client-tags-inline__menu');
        if (!menu || menu.classList.contains('d-none')) return;

        menu.classList.remove('client-tags-inline__menu--up', 'client-tags-inline__menu--fixed');
        menu.style.position = '';
        menu.style.left = '';
        menu.style.right = '';
        menu.style.top = '';
        menu.style.bottom = '';
        menu.style.width = '';
        menu.style.maxWidth = '';

        var offcanvas = root.closest('.agenda-marcacao-test-offcanvas');
        if (!offcanvas) {
            menu.style.left = '0';
            menu.style.right = 'auto';
            return;
        }

        var panel = root.closest('.offcanvas-body') || offcanvas;
        var row = root.querySelector('.client-tags-inline__row') || root;
        var anchorRect = row.getBoundingClientRect();
        var panelRect = panel.getBoundingClientRect();
        var menuWidth = Math.min(224, Math.max(168, panelRect.width - 16));

        menu.classList.add('client-tags-inline__menu--fixed');
        menu.style.position = 'fixed';
        menu.style.width = menuWidth + 'px';
        menu.style.maxWidth = menuWidth + 'px';

        var left = Math.min(
            Math.max(panelRect.left + 8, anchorRect.left),
            panelRect.right - menuWidth - 8
        );
        var top = anchorRect.bottom + 4;
        var menuHeight = menu.offsetHeight || 120;

        if (top + menuHeight > panelRect.bottom - 8) {
            top = anchorRect.top - menuHeight - 4;
            menu.classList.add('client-tags-inline__menu--up');
        }

        top = Math.min(Math.max(top, panelRect.top + 8), panelRect.bottom - menuHeight - 8);
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
    }

    function openMenu(root) {
        closeMenu();
        openMenuRoot = root;
        root._tagSuggestionIndex = -1;
        var menu = root.querySelector('.client-tags-inline__menu');
        var input = root.querySelector('.client-tags-inline__input');
        if (!menu || !input) return;
        menu.classList.remove('d-none');
        input.setAttribute('aria-expanded', 'true');
        input.value = '';
        renderSuggestions(root, '').then(function () {
            positionTagMenu(root);
        });
        setTimeout(function () { input.focus(); }, 0);
    }

    function attachExisting(root, tag) {
        var tags = parseTags(root);
        if (isAtTagLimit(tags.length)) {
            toast('Máximo de ' + maxTagsPerClient() + ' etiquetas por cliente.', 'error');
            closeMenu();
            return Promise.resolve();
        }
        var ids = tags.map(function (t) { return t.id; });
        if (!ids.some(function (id) { return String(id) === String(tag.id); })) {
            ids.push(tag.id);
        }
        return saveTags(root, ids, []).then(function () {
            closeMenu();
        });
    }

    function createAndAttach(root, name) {
        name = String(name || '').trim();
        if (!name) return Promise.resolve();
        var tags = parseTags(root);
        if (isAtTagLimit(tags.length)) {
            toast('Máximo de ' + maxTagsPerClient() + ' etiquetas por cliente.', 'error');
            closeMenu();
            return Promise.resolve();
        }
        return saveTags(root, tags.map(function (t) { return t.id; }), [name]).then(function () {
            closeMenu();
        });
    }

    function detach(root, tagId) {
        var tags = parseTags(root).filter(function (t) { return String(t.id) !== String(tagId); });
        return saveTags(root, tags.map(function (t) { return t.id; }), []);
    }

    function selectHighlightedOrSubmit(root) {
        var input = root.querySelector('.client-tags-inline__input');
        if (!input) return;
        var q = input.value.trim();
        var items = getSuggestions(root);
        var idx = root._tagSuggestionIndex;

        if (idx != null && idx >= 0 && items[idx]) {
            var tagId = items[idx].getAttribute('data-tag-id');
            return fetchCatalog().then(function (catalog) {
                var tag = catalog.find(function (t) { return String(t.id) === String(tagId); });
                if (tag) return attachExisting(root, tag);
            });
        }

        if (!q) return Promise.resolve();

        return fetchCatalog().then(function (catalog) {
            var current = parseTags(root);
            var exact = catalog.find(function (tag) {
                return String(tag.name).toLowerCase() === q.toLowerCase() &&
                    !current.some(function (t) { return String(t.id) === String(tag.id); });
            });
            if (exact) {
                return attachExisting(root, exact);
            }
            return createAndAttach(root, q);
        });
    }

    function bind(root) {
        if (root.getAttribute('data-bound') === '1') return;
        root.setAttribute('data-bound', '1');
        root._tagSuggestionIndex = -1;

        root.addEventListener('click', function (e) {
            if (root.getAttribute('data-readonly') === '1') return;

            var removeBtn = e.target.closest('.client-tag-chip__remove');
            if (removeBtn) {
                e.preventDefault();
                e.stopPropagation();
                detach(root, removeBtn.getAttribute('data-tag-id')).catch(function (err) {
                    toast(err.message || 'Erro.', 'error');
                });
                return;
            }

            var addBtn = e.target.closest('.client-tags-inline__add');
            if (addBtn) {
                e.preventDefault();
                e.stopPropagation();
                openMenu(root);
                return;
            }

            var suggestion = e.target.closest('.client-tags-inline__suggestion');
            if (suggestion) {
                e.preventDefault();
                var tagId = suggestion.getAttribute('data-tag-id');
                fetchCatalog().then(function (catalog) {
                    var tag = catalog.find(function (t) { return String(t.id) === String(tagId); });
                    if (tag) attachExisting(root, tag).catch(function (err) { toast(err.message, 'error'); });
                });
            }
        });

        root.addEventListener('keydown', function (e) {
            var input = root.querySelector('.client-tags-inline__input');
            if (!input || e.target !== input) return;

            if (e.key === 'Escape') {
                e.preventDefault();
                closeMenu();
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                var items = getSuggestions(root);
                if (!items.length) return;
                var next = (root._tagSuggestionIndex == null || root._tagSuggestionIndex < 0)
                    ? 0
                    : root._tagSuggestionIndex + 1;
                setSuggestionHighlight(root, next);
                return;
            }

            if (e.key === 'ArrowUp') {
                e.preventDefault();
                var list = getSuggestions(root);
                if (!list.length) return;
                var prev = (root._tagSuggestionIndex == null || root._tagSuggestionIndex < 0)
                    ? list.length - 1
                    : root._tagSuggestionIndex - 1;
                setSuggestionHighlight(root, prev);
                return;
            }

            if (e.key !== 'Enter') return;
            e.preventDefault();
            selectHighlightedOrSubmit(root).catch(function (err) {
                toast(err.message || 'Erro.', 'error');
            });
        });

        root.addEventListener('input', function (e) {
            if (!e.target.classList.contains('client-tags-inline__input')) return;
            renderSuggestions(root, e.target.value).then(function () {
                positionTagMenu(root);
            });
        });
    }

    function initRoot(root) {
        render(root);
        bind(root);
    }

    function initAll() {
        document.querySelectorAll('.client-tags-inline[data-client-id]').forEach(initRoot);
        preloadCatalog();
    }

    function mount(container, client, options) {
        if (!container) return;
        options = options || {};
        if (!client || !client.id) {
            container.innerHTML = '';
            return;
        }
        container.innerHTML = '';
        var root = document.createElement('div');
        root.className = 'client-tags-inline';
        root.setAttribute('data-client-id', String(client.id));
        root.setAttribute('data-sync-url', options.syncUrl || '');
        root.setAttribute('data-readonly', options.readonly ? '1' : '0');
        root.setAttribute('data-variant', options.variant || '');
        root.setAttribute('data-tags', JSON.stringify(client.tags || []));
        container.appendChild(root);
        initRoot(root);
    }

    document.addEventListener('click', function (e) {
        if (!openMenuRoot) return;
        if (openMenuRoot.contains(e.target)) return;
        closeMenu();
    });

    window.addEventListener('resize', function () {
        if (openMenuRoot) positionTagMenu(openMenuRoot);
    });

    window.ClientTags = {
        init: initRoot,
        initAll: initAll,
        mount: mount,
        invalidateCatalog: invalidateCatalog
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})(window, document);
