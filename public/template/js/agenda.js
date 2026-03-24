var C = window.AGENDA_CONFIG || {};

document.addEventListener('DOMContentLoaded', function() {
    const $id = function(id) { return document.getElementById(id); };
    const $ = function(sel) { return document.querySelector(sel); };
    const $$ = function(sel) { return document.querySelectorAll(sel); };

    const calendarEl = $id('calendar');
    const eventsUrl = C.eventsUrl || '';
    const resourcesUrl = C.resourcesUrl || '';
    const clientesBaseUrl = C.clientesBaseUrl || '';
    const csrf = (C.csrf || "");
    const currentUserIsAdmin = !!C.currentUserIsAdmin;

    let allResources = [];
    let consultantFilterIds = [];
    let currentViewMode = 'consultant';
    let selectedConsultantId = '';
    let eventDetailModalLoading = false;
    /** Tipo da vista atual; usado em callbacks do FullCalendar que correm antes de `calendar` existir (evita TDZ). */
    let agendaCurrentViewType = 'resourceTimeGridDay';

    const AGENDA_SLOT_STORAGE_KEY = 'agendaSlot24h';
    function readAgendaSlot24hPreference() {
        try {
            return localStorage.getItem(AGENDA_SLOT_STORAGE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }
    function getAgendaSlotRange(is24h) {
        return is24h
            ? { min: '00:00:00', max: '24:00:00' }
            : { min: '09:00:00', max: '19:00:00' };
    }
    let agendaSlot24hEnabled = readAgendaSlot24hPreference();
    var initialAgendaSlots = getAgendaSlotRange(agendaSlot24hEnabled);

    function viewSupportsConsultantFilter(viewType) {
        return viewType === 'resourceTimeGridDay' || viewType === 'timeGridWeek' || viewType === 'timeGridThreeDay';
    }
    function isResourceTimeGridDayView(viewType) {
        return viewType === 'resourceTimeGridDay';
    }

    const DAYS_SHORT = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    const DAYS_LONG = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    const MONTHS_SHORT = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    const MONTHS_LONG = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

    const STATUS_LABELS = {
        agendado: 'Agendado',
        confirmado: 'Confirmado',
        chegou: 'Chegou',
        iniciado: 'Iniciado',
        faltou: 'Faltou',
        cancelado: 'Cancelado',
        completo: 'Concluído'
    };
    const STATUS_ICONS = {
        agendado: 'ph-clock',
        confirmado: 'ph-check',
        chegou: 'ph-map-pin',
        iniciado: 'ph-play',
        faltou: 'ph-prohibit',
        cancelado: 'ph-x-circle',
        completo: 'ph-check-circle'
    };

    // Formatar data para prev/next: "Qui 5 Fev"
    function formatDateShort(date) {
        const d = new Date(date);
        return DAYS_SHORT[d.getDay()] + ' ' + d.getDate() + ' ' + MONTHS_SHORT[d.getMonth()];
    }
    
    // Formatar data do botão currentDate conforme a vista
    function formatCurrentDateButton(viewType, startDate, endDate) {
        const start = new Date(startDate);
        const end = endDate ? new Date(endDate) : null;
        
        if (viewType === 'dayGridMonth') {
            return MONTHS_LONG[start.getMonth()] + ' ' + start.getFullYear();
        }
        
        if (viewType === 'resourceTimeGridDay') {
            return DAYS_LONG[start.getDay()] + ', ' + start.getDate() + ' ' + MONTHS_LONG[start.getMonth()] + ' ' + start.getFullYear();
        }
        
        if (viewType === 'timeGridWeek') {
            if (end && start.getTime() !== end.getTime()) {
                const lastDay = new Date(end);
                lastDay.setDate(lastDay.getDate() - 1);
                const startDay = start.getDate();
                const startMonth = MONTHS_SHORT[start.getMonth()];
                const endDay = lastDay.getDate();
                const endMonth = MONTHS_SHORT[lastDay.getMonth()];
                const year = start.getFullYear();
                
                return startDay + ' ' + startMonth + ' - ' + endDay + ' ' + endMonth + ', ' + year;
            } else {
                // Vista de dia único
                return start.getDate() + ' ' + MONTHS_SHORT[start.getMonth()] + ', ' + start.getFullYear();
            }
        }
        
        if (viewType === 'timeGridThreeDay') {
            if (end && start.getTime() !== end.getTime()) {
                const lastDay = new Date(end);
                lastDay.setDate(lastDay.getDate() - 1);
                const startDay = start.getDate();
                const startMonth = MONTHS_SHORT[start.getMonth()];
                const endDay = lastDay.getDate();
                const endMonth = MONTHS_SHORT[lastDay.getMonth()];
                const year = start.getFullYear();
                return startDay + ' ' + startMonth + ' - ' + endDay + ' ' + endMonth + ', ' + year;
            }
            return start.getDate() + ' ' + MONTHS_SHORT[start.getMonth()] + ', ' + start.getFullYear();
        }
        
        // Fallback: usar formato curto
        return formatDateShort(start);
    }

    var _agendaHighlight = { wrapper: null };
    var _agendaHoverHighlight = null;

    function clearAgendaHoverHighlight() {
        if (_agendaHoverHighlight) {
            _agendaHoverHighlight.remove();
            _agendaHoverHighlight = null;
        }
    }

    function clearAgendaCellHighlight() {
        var w = _agendaHighlight.wrapper;
        if (!w) return;
        if (w._isDayGrid && w._parent) {
            w._parent.classList.remove('agenda-cell-highlighted');
        } else if (w._isFullRow) {
            w.remove();
        } else if (w.remove) {
            w.remove();
        }
        _agendaHighlight.wrapper = null;
    }

    function createCellHighlightForColumn(slotTd, resourceId, timeLabel, clickClientX) {
        if (!slotTd) return null;
        var colEl = $('.fc-timegrid-col[data-resource-id="' + resourceId + '"]') ||
            $('[data-resource-id="' + resourceId + '"]');
        if (!colEl && clickClientX != null) {
            var cols = $$('.fc-timegrid-col');
            for (var i = 0; i < cols.length; i++) {
                var r = cols[i].getBoundingClientRect();
                if (clickClientX >= r.left && clickClientX <= r.right) { colEl = cols[i]; break; }
            }
        }
        if (!colEl) return null;
        var slotRect = slotTd.getBoundingClientRect();
        var colRect = colEl.getBoundingClientRect();
        if (colRect.width <= 0 || slotRect.height <= 0) return null;
        var wrapper = document.createElement('div');
        wrapper.className = 'agenda-cell-highlight agenda-cell-highlight-active';
        wrapper.style.position = 'fixed';
        wrapper.style.top = slotRect.top + 'px';
        wrapper.style.left = colRect.left + 'px';
        wrapper.style.width = colRect.width + 'px';
        wrapper.style.height = slotRect.height + 'px';
        wrapper.style.zIndex = '999';
        wrapper.style.pointerEvents = 'none';
        var timeSpan = document.createElement('span');
        timeSpan.className = 'agenda-cell-time-overlay';
        timeSpan.textContent = timeLabel;
        wrapper.appendChild(timeSpan);
        document.body.appendChild(wrapper);
        return wrapper;
    }

    /**
     * Mostra o menu rápido (popup) na posição do rato com as opções dadas.
     * @param {number} clientX - posição X do rato (ex: info.jsEvent.clientX)
     * @param {number} clientY - posição Y do rato (ex: info.jsEvent.clientY)
     * @param {string} [headingText] - texto do primeiro li (data/hora); bold, fundo cinza; opcional
     * @param {Array<{label: string, action: function}>} options - lista de { label, action }
     */
    function showQuickMenu(clientX, clientY, headingText, options) {
        if (typeof headingText === 'object' && !Array.isArray(headingText) && headingText !== null) {
            options = headingText;
            headingText = null;
        }
        hideEventQuickview();
        var menu = $id('agendaQuickMenu');
        if (!menu || !options || options.length === 0) return;

        function hideQuickMenu() {
            menu.classList.remove('is-open');
            document.removeEventListener('click', closeHandler);
            window.removeEventListener('scroll', scrollHandler, true);
            document.removeEventListener('keydown', escHandler);
            clearAgendaCellHighlight();
        }

        function escHandler(e) {
            if (e.key === 'Escape') hideQuickMenu();
        }

        function scrollHandler() {
            hideQuickMenu();
        }

        function closeHandler(e) {
            if (menu.contains(e.target)) return;
            hideQuickMenu();
        }

        menu.innerHTML = '';
        var header = document.createElement('div');
        header.className = 'quickaccess-header';
        var h6 = document.createElement('h6');
        h6.textContent = headingText || '';
        header.appendChild(h6);
        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'quickaccess-close';
        closeBtn.setAttribute('aria-label', 'Fechar');
        closeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            hideQuickMenu();
        });
        header.appendChild(closeBtn);
        menu.appendChild(header);
        var grid = document.createElement('div');
        grid.className = 'quickaccess-grid';
        options.forEach(function(opt) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'quickaccess-item';
            btn.setAttribute('role', 'menuitem');
            var iconSpan = document.createElement('span');
            iconSpan.className = 'quickaccess-icon';
            var qaColor = opt.iconColor || 'var(--accent-color, #0d6efd)';
            iconSpan.style.setProperty('--qa-color', qaColor);
            var iconClass = opt.icon || 'bi bi-plus-circle';
            var icon = document.createElement('i');
            icon.className = iconClass;
            iconSpan.appendChild(icon);
            var labelSpan = document.createElement('span');
            labelSpan.className = 'quickaccess-label';
            labelSpan.textContent = opt.label;
            btn.appendChild(iconSpan);
            btn.appendChild(labelSpan);
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                hideQuickMenu();
                if (typeof opt.action === 'function') opt.action();
            });
            grid.appendChild(btn);
        });
        menu.appendChild(grid);

        var offset = 8;
        menu.style.left = (clientX + offset) + 'px';
        menu.style.top = (clientY + offset) + 'px';

        // Manter dentro da viewport
        requestAnimationFrame(function() {
            var rect = menu.getBoundingClientRect();
            var vw = window.innerWidth;
            var vh = window.innerHeight;
            var left = parseFloat(menu.style.left);
            var top = parseFloat(menu.style.top);
            if (left + rect.width > vw) menu.style.left = (vw - rect.width - 8) + 'px';
            if (top + rect.height > vh) menu.style.top = (vh - rect.height - 8) + 'px';
            if (top < 8) menu.style.top = '8px';
            if (left < 8) menu.style.left = '8px';
        });

        menu.classList.add('is-open');
        setTimeout(function() {
            document.addEventListener('click', closeHandler);
            window.addEventListener('scroll', scrollHandler, true);
            document.addEventListener('keydown', escHandler);
        }, 0);
    }

    /**
     * Mostra menu dropdown (lista) para alterar estado do evento ao clicar no ícone de estado no fc-event.
     */
    function showEventStatusQuickMenu(event, clientX, clientY) {
        hideEventQuickview();
        var menu = $id('agendaQuickMenu');
        if (!menu) return;
        var ext = event.extendedProps || {};
        var currentStatus = ext.status || 'agendado';
        var statusOpts = [
            { status: 'agendado', label: 'Agendado', icon: 'ph ph-clock' },
            { status: 'confirmado', label: 'Confirmado', icon: 'ph ph-check' },
            { status: 'chegou', label: 'Chegou', icon: 'ph ph-map-pin' },
            { status: 'iniciado', label: 'Iniciado', icon: 'ph ph-play' },
            { status: 'cancelar', label: 'Cancelar', icon: 'ph ph-x-circle', isCancelAction: true }
        ];
        function hideMenu() {
            menu.classList.remove('is-open');
            menu.classList.remove('agenda-status-dropdown');
            menu.innerHTML = '';
            document.removeEventListener('click', closeHandler);
            document.removeEventListener('keydown', escHandler);
        }
        function closeHandler(e) {
            if (menu.contains(e.target)) return;
            hideMenu();
        }
        function escHandler(e) {
            if (e.key === 'Escape') hideMenu();
        }
        menu.innerHTML = '';
        menu.classList.add('agenda-status-dropdown');
        var list = document.createElement('div');
        list.className = 'agenda-status-dropdown-list';
        statusOpts.forEach(function(o) {
            if (o.isCancelAction && (currentStatus === 'faltou' || currentStatus === 'cancelado')) return;
            if (!o.isCancelAction && o.status === currentStatus) return;
            var a = document.createElement('a');
            a.href = '#';
            a.className = 'agenda-status-dropdown-item dropdown-item d-flex align-items-center gap-2' + (o.isCancelAction ? ' text-danger' : '');
            a.innerHTML = '<i class="' + o.icon + '"></i><span>' + (o.label || o.status) + '</span>';
            a.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                hideMenu();
                if (o.isCancelAction) {
                    window._cancelMarcacaoConfirmed = false;
                    window._cancelMarcacaoPreviousStatus = currentStatus;
                    window._cancelMarcacaoContext = 'quick';
                    $id('cancelMarcacaoEventId').value = event.id;
                    var totalQuick = 0;
                    (ext.event_services || []).forEach(function(s) { totalQuick += parseFloat(s.price) || 0; });
                    $id('cancelMarcacaoTotalPrice').textContent = totalQuick > 0 ? (totalQuick.toFixed(2).replace('.', ',') + ' €') : '0,00 €';
                    $id('cancelMarcacaoQueAconteceu').value = 'faltou';
                    $id('cancelMarcacaoReason').value = '';
                    $id('cancelMarcacaoOutraTexto').value = '';
                    $id('cancelMarcacaoOutraWrap').classList.add('d-none');
                    $id('cancelMarcacaoRefund').value = '';
                    $id('cancelMarcacaoAvisouPrazo').value = '';
                    $id('cancelMarcacaoAvisouWrap').classList.add('d-none');
                    bootstrap.Modal.getOrCreateInstance($id('cancelMarcacaoModal')).show();
                    return;
                }
                fetch((C.urlEvents || '') + '/' + event.id + '/status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ status: o.status })
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        var ev = typeof calendar !== 'undefined' ? calendar.getEventById(event.id) : null;
                        if (ev) {
                            ev.setExtendedProp('status', res.status);
                            ev.setExtendedProp('status_icon', res.status_icon);
                            ev.setExtendedProp('status_label', res.status_label);
                        }
                        var iconBtn = $('.fc-event[data-event-id="' + event.id + '"] .agenda-event-status-icon-btn i');
                        if (iconBtn) iconBtn.className = (res.status_icon || 'ph ph-clock') + ' fc-event-status-icon';
                        showToast('Estado atualizado.', 'success');
                    } else {
                        showToast(res.message || 'Erro ao atualizar estado.', 'error');
                    }
                })
                .catch(function() {
                    showToast('Erro de ligação.', 'error');
                });
            });
            list.appendChild(a);
        });
        menu.appendChild(list);
        var offset = 4;
        menu.style.left = (clientX + offset) + 'px';
        menu.style.top = (clientY + offset) + 'px';
        requestAnimationFrame(function() {
            var rect = menu.getBoundingClientRect();
            var vw = window.innerWidth;
            var vh = window.innerHeight;
            var left = parseFloat(menu.style.left);
            var top = parseFloat(menu.style.top);
            if (left + rect.width > vw) menu.style.left = (vw - rect.width - 8) + 'px';
            if (top + rect.height > vh) menu.style.top = (vh - rect.height - 8) + 'px';
            if (top < 8) menu.style.top = '8px';
            if (left < 8) menu.style.left = '8px';
        });
        menu.classList.add('is-open');
        setTimeout(function() {
            document.addEventListener('click', closeHandler);
            document.addEventListener('keydown', escHandler);
        }, 0);
    }

    var agendaEventQuickviewShowTimeout = null;
    var agendaEventQuickviewHideTimeout = null;
    var agendaEventQuickviewHoverId = null;

    function hideEventQuickview() {
        if (agendaEventQuickviewHideTimeout) {
            clearTimeout(agendaEventQuickviewHideTimeout);
            agendaEventQuickviewHideTimeout = null;
        }
        var qv = $id('agendaEventQuickview');
        if (qv) {
            qv.classList.remove('is-open');
            qv.innerHTML = '';
        }
        agendaEventQuickviewHoverId = null;
    }

    /**
     * Mostra o quickview do evento ao lado do elemento do evento (posição automática: direita, esquerda, baixo, cima).
     * @param {Object} info - info do FullCalendar (info.event, info.el)
     */
    function showEventQuickview(info) {
        var event = info.event;
        var el = info.el;
        var eventBlock = el.querySelector && el.querySelector('.fc-event-main');
        var rectEl = eventBlock || el;
        var ext = event.extendedProps || {};
        var statusLabels = STATUS_LABELS;
        var statusIcons = {
            agendado: 'ph ph-clock',
            confirmado: 'ph ph-check',
            chegou: 'ph ph-map-pin',
            iniciado: 'ph ph-play',
            faltou: 'ph ph-prohibit',
            cancelado: 'ph ph-x-circle',
            completo: 'ph ph-check-circle'
        };
        var isTempoPessoal = (ext.event_type || '') === 'tempo_pessoal';
        var personalTimeType = ext.personal_time_type || {};
        var status = ext.status || 'agendado';
        var statusLabel = isTempoPessoal ? 'Tempo pessoal' : (statusLabels[status] || status);
        var statusIcon = isTempoPessoal ? null : (statusIcons[status] || 'ph ph-clock');
        var start = event.start;
        var end = event.end;
        var fmt = function(d) { return d ? (String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')) : ''; };
        var startStr = fmt(start);
        var endStr = fmt(end);
        var timeStr = startStr && endStr ? (startStr + ' - ' + endStr) : (startStr || '—');
        var clientName = (ext.client_name || event.title || '—').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        var clientAvatarUrl = ext.client_avatar_url || '';
        var userName = (ext.user_name || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        var eventServices = ext.event_services || [];
        var totalPrice = 0;
        var totalDuration = 0;
        eventServices.forEach(function(s) {
            var basePrice = parseFloat(s.price) || 0;
            var baseDuration = parseInt(s.duration || 0, 10) || 0;
            var extras = Array.isArray(s.extras) ? s.extras : [];
            var extrasTotal = 0;
            var extrasDurationTotal = 0;
            extras.forEach(function(ex) {
                var exPrice = parseFloat(ex.price) || 0;
                var exDuration = parseInt(ex.duration || 0, 10) || 0;
                extrasTotal += exPrice;
                extrasDurationTotal += exDuration;
            });
            totalPrice += basePrice + extrasTotal;
            totalDuration += baseDuration + extrasDurationTotal;
        });
        var totalPriceStr = totalPrice > 0 ? (totalPrice.toFixed(2).replace('.', ',') + ' €') : '';

        var qv = $id('agendaEventQuickview');
        if (!qv) return;
        qv.innerHTML = '';

        var header = document.createElement('div');
        header.className = 'agenda-quickview-header';
        var timeSpan = document.createElement('span');
        timeSpan.className = 'agenda-quickview-time';
        timeSpan.textContent = timeStr;
        header.appendChild(timeSpan);
        var statusSpan = document.createElement('span');
        statusSpan.className = 'agenda-quickview-status';
        statusSpan.innerHTML = statusIcon ? ('<i class="' + statusIcon + '"></i><span>' + statusLabel + '</span>') : ('<span>' + statusLabel + '</span>');
        header.appendChild(statusSpan);
        qv.appendChild(header);

        var body = document.createElement('div');
        body.className = 'agenda-quickview-body';
        if (isTempoPessoal && (personalTimeType.name || event.title)) {
            var typeRow = document.createElement('div');
            typeRow.className = 'agenda-quickview-service-row';
            var typeLeft = document.createElement('div');
            typeLeft.className = 'agenda-quickview-service-left';
            var typeIcon = personalTimeType.icon ? ('ph ' + personalTimeType.icon) : 'ph ph-dots-three';
            typeLeft.innerHTML = '<i class="' + typeIcon + ' me-2"></i><span class="agenda-quickview-service-name">' + (personalTimeType.name || event.title || '—').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>';
            typeRow.appendChild(typeLeft);
            body.appendChild(typeRow);
        }
        if (!isTempoPessoal) {
        var clientRow = document.createElement('div');
        clientRow.className = 'agenda-quickview-client';
        if (clientAvatarUrl) {
            var img = document.createElement('img');
            img.className = 'agenda-quickview-avatar';
            img.src = clientAvatarUrl;
            img.alt = '';
            clientRow.appendChild(img);
        } else {
            var initials = (clientName || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
            var fallback = document.createElement('div');
            fallback.className = 'agenda-quickview-avatar-fallback';
            fallback.textContent = initials;
            clientRow.appendChild(fallback);
        }
        var nameSpan = document.createElement('span');
        nameSpan.className = 'agenda-quickview-client-name';
        nameSpan.textContent = clientName;
        clientRow.appendChild(nameSpan);
        body.appendChild(clientRow);
        }

        if (eventServices.length > 0) {
            eventServices.forEach(function(s) {
                // Linha principal do serviço
                var row = document.createElement('div');
                row.className = 'agenda-quickview-service-row';
                var left = document.createElement('div');
                left.className = 'agenda-quickview-service-left';
                var nameEl = document.createElement('div');
                nameEl.className = 'agenda-quickview-service-name';
                nameEl.textContent = (s.name || '—').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                left.appendChild(nameEl);
                // Duração deste serviço (como nos extras, apenas duração)
                var meta = document.createElement('div');
                meta.className = 'agenda-quickview-service-meta';
                var metaParts = [];
                if (s.formatted_duration) {
                    metaParts.push(s.formatted_duration);
                } else if (s.duration) {
                    metaParts.push((s.duration || 0) + ' min');
                }
                meta.textContent = metaParts.join(' · ');
                if (meta.textContent) {
                    left.appendChild(meta);
                }
                row.appendChild(left);
                var priceEl = document.createElement('div');
                priceEl.className = 'agenda-quickview-service-price';
                priceEl.textContent = s.formatted_price || (parseFloat(s.price) || 0).toFixed(2).replace('.', ',') + ' €';
                row.appendChild(priceEl);
                body.appendChild(row);

                // Extras associados ao serviço, cada um como uma linha própria
                var extras = Array.isArray(s.extras) ? s.extras : [];
                extras.forEach(function(ex) {
                    var extraRow = document.createElement('div');
                    extraRow.className = 'agenda-quickview-service-row';

                    var extraLeft = document.createElement('div');
                    extraLeft.className = 'agenda-quickview-service-left';
                    var extraNameEl = document.createElement('div');
                    extraNameEl.className = 'agenda-quickview-service-name';
                    var extraName = (ex.name || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    extraNameEl.textContent = '+ ' + (extraName || 'Extra');
                    extraLeft.appendChild(extraNameEl);

                    var extraMeta = document.createElement('div');
                    extraMeta.className = 'agenda-quickview-service-meta';
                    var extraMetaParts = [];
                    if (ex.formatted_duration) {
                        extraMetaParts.push(ex.formatted_duration);
                    } else if (ex.duration) {
                        extraMetaParts.push((ex.duration || 0) + ' min');
                    }
                    extraMeta.textContent = extraMetaParts.join(' · ');
                    if (extraMeta.textContent) {
                        extraLeft.appendChild(extraMeta);
                    }

                    extraRow.appendChild(extraLeft);

                    var extraPriceEl = document.createElement('div');
                    extraPriceEl.className = 'agenda-quickview-service-price';
                    if (typeof ex.formatted_price === 'string' && ex.formatted_price.trim() !== '') {
                        extraPriceEl.textContent = ex.formatted_price;
                    } else if (ex.price != null) {
                        extraPriceEl.textContent = (parseFloat(ex.price) || 0).toFixed(2).replace('.', ',') + ' €';
                    } else {
                        extraPriceEl.textContent = '';
                    }
                    extraRow.appendChild(extraPriceEl);

                    body.appendChild(extraRow);
                });
            });

            // Linha única com duração total (serviços + extras) e preço total
            if (totalDuration > 0 || totalPriceStr) {
                var totalRow = document.createElement('div');
                totalRow.className = 'agenda-quickview-service-row';
                totalRow.style.marginTop = '0.5rem';
                totalRow.style.paddingTop = '0.5rem';
                totalRow.style.borderTop = '1px solid var(--border-color, rgba(0,0,0,0.1))';

                var totalLeft = document.createElement('div');
                totalLeft.className = 'agenda-quickview-service-left';

                var durationText = '';
                if (totalDuration > 0) {
                    var hours = Math.floor(totalDuration / 60);
                    var mins = totalDuration % 60;
                    if (hours > 0 && mins > 0) {
                        durationText = hours + 'h ' + mins + 'min';
                    } else if (hours > 0) {
                        durationText = hours + 'h';
                    } else {
                        durationText = mins + 'min';
                    }
                }

                var durationEl = document.createElement('div');
                durationEl.className = 'agenda-quickview-service-name';
                durationEl.textContent = durationText || '';
                totalLeft.appendChild(durationEl);

                totalRow.appendChild(totalLeft);

                var totalPriceEl = document.createElement('div');
                totalPriceEl.className = 'agenda-quickview-service-price';
                totalPriceEl.textContent = totalPriceStr;
                totalRow.appendChild(totalPriceEl);

                body.appendChild(totalRow);
            }
            if (eventServices.length > 1 && totalPriceStr) {
                var totalRow = document.createElement('div');
                totalRow.className = 'agenda-quickview-service-row';
                totalRow.style.marginTop = '0.5rem';
                totalRow.style.paddingTop = '0.5rem';
                totalRow.style.borderTop = '1px solid var(--border-color, rgba(0,0,0,0.1))';
                var totalLeft = document.createElement('div');
                totalLeft.className = 'agenda-quickview-service-left';
                totalLeft.textContent = 'Total';
                totalRow.appendChild(totalLeft);
                var totalPriceEl = document.createElement('div');
                totalPriceEl.className = 'agenda-quickview-service-price';
                totalPriceEl.textContent = totalPriceStr;
                totalRow.appendChild(totalPriceEl);
                body.appendChild(totalRow);
            }
        } else {
            var serviceName = (ext.service_name || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            if (serviceName || userName) {
                var row = document.createElement('div');
                row.className = 'agenda-quickview-service-row';
                var left = document.createElement('div');
                left.className = 'agenda-quickview-service-left';
                if (serviceName) {
                    var nameEl = document.createElement('div');
                    nameEl.className = 'agenda-quickview-service-name';
                    nameEl.textContent = serviceName;
                    left.appendChild(nameEl);
                }
                if (userName) {
                    var meta = document.createElement('div');
                    meta.className = 'agenda-quickview-service-meta';
                    var metaText = userName;
                    if (isTempoPessoal && personalTimeType.formatted_duration) metaText += ' · ' + personalTimeType.formatted_duration;
                    meta.textContent = metaText;
                    left.appendChild(meta);
                }
                row.appendChild(left);
                if (totalPriceStr) {
                    var priceEl = document.createElement('div');
                    priceEl.className = 'agenda-quickview-service-price';
                    priceEl.textContent = totalPriceStr;
                    row.appendChild(priceEl);
                }
                body.appendChild(row);
            }
        }
        qv.appendChild(body);

        qv.classList.add('is-open');
        // Distância do quickview ao evento (horizontal um pouco mais próxima)
        var gapX = 3;
        var gapY = 8;
        var rect = rectEl.getBoundingClientRect();
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var qvRect = qv.getBoundingClientRect();
        var qvW = qvRect.width;
        var qvH = qvRect.height;
        var left = 0;
        var top = 0;
        var spaceRight = vw - rect.right;
        var spaceLeft = rect.left;
        var spaceBottom = vh - rect.bottom;
        var spaceTop = rect.top;
        if (spaceLeft >= qvW + gapX) {
            left = rect.left - qvW - gapX;
            top = rect.top + (rect.height / 2) - (qvH / 2);
        } else if (spaceRight >= qvW + gapX) {
            left = rect.right + gapX;
            top = rect.top + (rect.height / 2) - (qvH / 2);
        } else if (spaceBottom >= qvH + gapY) {
            left = rect.left + (rect.width / 2) - (qvW / 2);
            top = rect.bottom + gapY;
        } else if (spaceTop >= qvH + gapY) {
            left = rect.left + (rect.width / 2) - (qvW / 2);
            top = rect.top - qvH - gapY;
        } else {
            left = rect.right + gapX;
            top = rect.top;
        }
        if (left + qvW > vw - 8) left = vw - qvW - 8;
        if (left < 8) left = 8;
        if (top + qvH > vh - 8) top = vh - qvH - 8;
        if (top < 8) top = 8;
        qv.style.left = left + 'px';
        qv.style.top = top + 'px';
        agendaEventQuickviewHoverId = event.id;
    }

    const TempoPessoal = {
        populateTimeOptions: function(containerSelector, selectedTime, onSelect) {
            var container = $(containerSelector);
            if (!container) return;
            container.innerHTML = '';
            for (var h = 0; h < 24; h++) {
                for (var m = 0; m < 60; m += 15) {
                    var ts = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
                    var a = document.createElement('a');
                    a.href = '#';
                    a.className = 'dropdown-item tempo-pessoal-time-opt' + (ts === selectedTime ? ' active' : '');
                    a.dataset.time = ts;
                    a.textContent = ts;
                    a.addEventListener('click', function(e) { e.preventDefault(); onSelect(this.dataset.time); });
                    container.appendChild(a);
                }
            }
        },
        applyNewStartTime: function(timeStr) {
            var startStr = $id('tempoPessoalStart').value;
            var endStr = $id('tempoPessoalEnd').value;
            if (!startStr) return;
            var start = new Date(startStr);
            var parts = (timeStr || '').match(/^(\d{1,2}):(\d{2})/);
            if (!parts) return;
            start.setHours(parseInt(parts[1], 10), parseInt(parts[2], 10), 0, 0);
            var end = endStr ? new Date(endStr) : new Date(start.getTime() + 60 * 60 * 1000);
            var durMs = end.getTime() - (new Date(startStr)).getTime();
            if (durMs < 15 * 60 * 1000) durMs = 60 * 60 * 1000;
            end = new Date(start.getTime() + durMs);
            var pad = function(n) { return String(n).padStart(2, '0'); };
            $id('tempoPessoalStart').value = start.getFullYear() + '-' + pad(start.getMonth() + 1) + '-' + pad(start.getDate()) + 'T' + pad(start.getHours()) + ':' + pad(start.getMinutes());
            $id('tempoPessoalEnd').value = end.getFullYear() + '-' + pad(end.getMonth() + 1) + '-' + pad(end.getDate()) + 'T' + pad(end.getHours()) + ':' + pad(end.getMinutes());
            $id('tempoPessoalStartTimeToggle').textContent = timeStr;
            $id('tempoPessoalEndTimeToggle').textContent = pad(end.getHours()) + ':' + pad(Math.floor(end.getMinutes() / 15) * 15);
            TempoPessoal.populateTimeOptions('.tempo-pessoal-time-options', timeStr, TempoPessoal.applyNewStartTime);
            TempoPessoal.populateTimeOptions('.tempo-pessoal-end-time-options', $id('tempoPessoalEndTimeToggle').textContent, TempoPessoal.applyNewEndTime);
            var toggle = $id('tempoPessoalStartTimeToggle');
            if (toggle && bootstrap.Dropdown) bootstrap.Dropdown.getInstance(toggle)?.hide();
        },
        applyTypeDuration: function() {
            var startStr = $id('tempoPessoalStart')?.value;
            if (!startStr) return;
            var activeCard = $('.tempo-pessoal-type-card.active');
            var dur = activeCard ? (parseInt(activeCard.dataset.duration, 10) || 60) : 60;
            var startD = new Date(startStr);
            var endD = new Date(startD.getTime() + dur * 60 * 1000);
            var pad = function(n) { return String(n).padStart(2, '0'); };
            var et = pad(endD.getHours()) + ':' + pad(Math.floor(endD.getMinutes() / 15) * 15);
            $id('tempoPessoalEnd').value = endD.getFullYear() + '-' + pad(endD.getMonth() + 1) + '-' + pad(endD.getDate()) + 'T' + pad(endD.getHours()) + ':' + pad(endD.getMinutes());
            $id('tempoPessoalEndTimeToggle').textContent = et;
            TempoPessoal.populateTimeOptions('.tempo-pessoal-end-time-options', et, TempoPessoal.applyNewEndTime);
        },
        applyNewEndTime: function(timeStr) {
            var startStr = $id('tempoPessoalStart').value;
            if (!startStr) return;
            var start = new Date(startStr);
            var end = new Date(startStr);
            var parts = (timeStr || '').match(/^(\d{1,2}):(\d{2})/);
            if (!parts) return;
            end.setHours(parseInt(parts[1], 10), parseInt(parts[2], 10), 0, 0);
            if (end.getTime() <= start.getTime()) end = new Date(start.getTime() + 60 * 60 * 1000);
            var pad = function(n) { return String(n).padStart(2, '0'); };
            $id('tempoPessoalEnd').value = end.getFullYear() + '-' + pad(end.getMonth() + 1) + '-' + pad(end.getDate()) + 'T' + pad(end.getHours()) + ':' + pad(end.getMinutes());
            $id('tempoPessoalEndTimeToggle').textContent = timeStr;
            TempoPessoal.populateTimeOptions('.tempo-pessoal-end-time-options', timeStr, TempoPessoal.applyNewEndTime);
            var toggle = $id('tempoPessoalEndTimeToggle');
            if (toggle && bootstrap.Dropdown) bootstrap.Dropdown.getInstance(toggle)?.hide();
        },
        syncHiddenFromInputs: function() {
            var dateStr = $id('tempoPessoalDateInput')?.value || '';
            var startTimeStr = ($id('tempoPessoalStartTimeToggle')?.textContent || '').trim();
            var endTimeStr = ($id('tempoPessoalEndTimeToggle')?.textContent || '').trim();
            if (!dateStr || !startTimeStr || startTimeStr === '—') return;
            if (!endTimeStr || endTimeStr === '—') endTimeStr = startTimeStr;
            var datePart = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})/);
            var startPart = startTimeStr.match(/^(\d{1,2}):(\d{2})/);
            var endPart = endTimeStr.match(/^(\d{1,2}):(\d{2})/);
            if (!datePart || !startPart || !endPart) return;
            var startIso = datePart[1] + '-' + datePart[2] + '-' + datePart[3] + 'T' + startPart[1].padStart(2, '0') + ':' + startPart[2];
            var endIso = datePart[1] + '-' + datePart[2] + '-' + datePart[3] + 'T' + endPart[1].padStart(2, '0') + ':' + endPart[2];
            var startD = new Date(startIso);
            var endD = new Date(endIso);
            if (endD.getTime() <= startD.getTime()) {
                endD = new Date(startD.getTime() + 60 * 60 * 1000);
                $id('tempoPessoalEndTimeToggle').textContent = String(endD.getHours()).padStart(2, '0') + ':' + String(endD.getMinutes()).padStart(2, '0');
                TempoPessoal.populateTimeOptions('.tempo-pessoal-end-time-options', $id('tempoPessoalEndTimeToggle').textContent, TempoPessoal.applyNewEndTime);
            }
            $id('tempoPessoalStart').value = startIso;
            $id('tempoPessoalEnd').value = endD.getFullYear() + '-' + String(endD.getMonth() + 1).padStart(2, '0') + '-' + String(endD.getDate()).padStart(2, '0') + 'T' + String(endD.getHours()).padStart(2, '0') + ':' + String(endD.getMinutes()).padStart(2, '0');
        }
    };

    function openTempoPessoalModal(startStr, endStr, resourceId) {
        $id('tempoPessoalEventId').value = '';
        $id('tempoPessoalStart').value = startStr || '';
        $id('tempoPessoalEnd').value = endStr || '';
        $id('tempoPessoalMembro').value = resourceId ? String(resourceId) : (currentUserIsAdmin ? '' : String(C.authId || ''));
        var firstCard = $('.tempo-pessoal-type-card');
        if (firstCard) {
            $$('.tempo-pessoal-type-card').forEach(function(c) { c.classList.remove('active'); });
            firstCard.classList.add('active');
            $id('tempoPessoalTipo').value = firstCard.dataset.id || '';
            TempoPessoal.applyTypeDuration();
        } else {
            $id('tempoPessoalTipo').value = '';
        }
        $id('tempoPessoalDescricao').value = '';
        $id('tempoPessoalDeleteBtn').style.display = 'none';
        var startD = startStr ? new Date(startStr) : new Date();
        var endD = endStr ? new Date(endStr) : new Date(startD.getTime() + 60 * 60 * 1000);
        var pad = function(n) { return String(n).padStart(2, '0'); };
        var daysPt = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        var monthsPtLong = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        var ymd = startD.getFullYear() + '-' + pad(startD.getMonth() + 1) + '-' + pad(startD.getDate());
        var dateLabel = daysPt[startD.getDay()] + ', ' + startD.getDate() + ' ' + monthsPtLong[startD.getMonth()];
        var st = pad(startD.getHours()) + ':' + pad(Math.floor(startD.getMinutes() / 15) * 15);
        var et = firstCard ? ($id('tempoPessoalEndTimeToggle').textContent || pad(endD.getHours()) + ':' + pad(Math.floor(endD.getMinutes() / 15) * 15)) : (pad(endD.getHours()) + ':' + pad(Math.floor(endD.getMinutes() / 15) * 15));
        $id('tempoPessoalDateInput').value = ymd;
        $id('tempoPessoalDateToggle').textContent = dateLabel;
        if (window.tempoPessoalDateFlatpickr) window.tempoPessoalDateFlatpickr.setDate(ymd, false);
        $id('tempoPessoalStartTimeToggle').textContent = st;
        if (!firstCard) $id('tempoPessoalEndTimeToggle').textContent = et;
        TempoPessoal.populateTimeOptions('.tempo-pessoal-time-options', st, TempoPessoal.applyNewStartTime);
        TempoPessoal.populateTimeOptions('.tempo-pessoal-end-time-options', et, TempoPessoal.applyNewEndTime);
        TempoPessoal.syncHiddenFromInputs();
        bootstrap.Modal.getOrCreateInstance($id('tempoPessoalModal')).show();
    }

    function populateTempoPessoalModal(data) {
        $id('tempoPessoalEventId').value = data.id || '';
        $id('tempoPessoalStart').value = data.start_at || '';
        $id('tempoPessoalEnd').value = data.end_at || '';
        $id('tempoPessoalMembro').value = data.user_id ? String(data.user_id) : '';
        var typeId = data.personal_time_type_id ? String(data.personal_time_type_id) : null;
        var cards = $$('.tempo-pessoal-type-card');
        var selectedCard = typeId ? $('.tempo-pessoal-type-card[data-id="' + typeId + '"]') : null;
        if (!selectedCard && data.title) {
            selectedCard = Array.prototype.find.call(cards, function(c) { return (c.dataset.name || '').trim() === (data.title || '').trim(); }) || cards[0];
        }
        if (!selectedCard) selectedCard = cards[0];
        cards.forEach(function(c) { c.classList.remove('active'); });
        if (selectedCard) {
            selectedCard.classList.add('active');
            $id('tempoPessoalTipo').value = selectedCard.dataset.id || '';
        } else {
            $id('tempoPessoalTipo').value = typeId || (cards[0] ? cards[0].dataset.id : '') || '';
        }
        $id('tempoPessoalDescricao').value = data.description || '';
        $id('tempoPessoalDeleteBtn').style.display = data.id ? 'inline-block' : 'none';
        var startDate = data.start_at ? new Date(data.start_at) : null;
        var endDate = data.end_at ? new Date(data.end_at) : null;
        if (startDate) {
            var pad = function(n) { return String(n).padStart(2, '0'); };
            var ymd = startDate.getFullYear() + '-' + pad(startDate.getMonth() + 1) + '-' + pad(startDate.getDate());
            var dateLabel = DAYS_LONG[startDate.getDay()] + ', ' + startDate.getDate() + ' ' + MONTHS_LONG[startDate.getMonth()];
            var st = pad(startDate.getHours()) + ':' + pad(Math.floor(startDate.getMinutes() / 15) * 15);
            var et = endDate ? (pad(endDate.getHours()) + ':' + pad(Math.floor(endDate.getMinutes() / 15) * 15)) : st;
            $id('tempoPessoalDateInput').value = ymd;
            $id('tempoPessoalDateToggle').textContent = dateLabel;
            if (window.tempoPessoalDateFlatpickr) window.tempoPessoalDateFlatpickr.setDate(ymd, false);
            $id('tempoPessoalStartTimeToggle').textContent = st;
            $id('tempoPessoalEndTimeToggle').textContent = et;
            TempoPessoal.populateTimeOptions('.tempo-pessoal-time-options', st, TempoPessoal.applyNewStartTime);
            TempoPessoal.populateTimeOptions('.tempo-pessoal-end-time-options', et, TempoPessoal.applyNewEndTime);
            TempoPessoal.syncHiddenFromInputs();
        }
    }

    var agendaMembersServicesUrl = (C.agendaMembersServicesUrl || '');
    var agendaClientsUrl = (C.agendaClientsUrl || '');
    var agendaEquipaBaseUrl = (C.agendaEquipaBaseUrl || '');
    var novaMarcacaoServicesData = null;
    var novaMarcacaoSelectedClient = null;
    var novaMarcacaoSelectedServices = [];
    var novaMarcacaoEditServiceIndex = -1;
    var eventDetailSelectedServices = [];
    var eventDetailCurrentData = null;

    const NovaMarcacao = {};

    NovaMarcacao.renderSelectedServices = function() {
        var container = $id('novaMarcacaoSelectedServicesList');
        var totalRow = $id('novaMarcacaoTotalPrice') ? $id('novaMarcacaoTotalPrice').closest('.nova-marcacao-total-row') : null;
        var titleEl = $('#novaMarcacaoServiceSelected .nova-marcacao-services-selected-title');
        if (!container) return;
        if (novaMarcacaoSelectedServices.length === 0) {
            container.innerHTML = '';
            if (titleEl) titleEl.textContent = 'Serviços selecionados';
            // Sem serviços selecionados, escondemos a linha do total
            if (totalRow) totalRow.classList.add('d-none');
            return;
        }
        if (titleEl) titleEl.textContent = novaMarcacaoSelectedServices.length === 1 ? 'Serviço selecionado' : 'Serviços selecionados';
        var html = novaMarcacaoSelectedServices.map(function(item, idx) {
            var serviceRow =
                '<div class="d-flex justify-content-between align-items-center w-100">' +
                    '<div class="nova-marcacao-service-item-left">' +
                        '<div class="nova-marcacao-service-item-name">' + (item.name || '') + '</div>' +
                        '<div class="nova-marcacao-service-item-duration">' + (item.formatted_duration || item.duration + ' min') + '</div>' +
                    '</div>' +
                    '<div class="d-flex align-items-center gap-2 justify-content-end">' +
                        '<div class="d-flex gap-1">' +
                            ( (item.available_extras && item.available_extras.length > 0)
                                ? '<button type="button" class="btn btn-outline-secondary btn-icon btn-sm novaMarcacaoAddExtrasBtn" data-idx="' + idx + '" title="Adicionar extras" aria-label="Adicionar extras"><i class="ph ph-plus-circle"></i></button>'
                                : '' ) +
                            '<button type="button" class="btn btn-outline-secondary btn-icon btn-sm novaMarcacaoEditServiceBtn" data-idx="' + idx + '" title="Alterar opções" aria-label="Alterar opções"><i class="ph ph-pencil-simple"></i></button>' +
                            '<button type="button" class="btn btn-outline-danger btn-icon btn-sm novaMarcacaoDeleteServiceBtn" data-idx="' + idx + '" title="Eliminar" aria-label="Eliminar"><i class="ph ph-trash"></i></button>' +
                        '</div>' +
                        '<span class="nova-marcacao-service-item-price">' + (item.formatted_price || '') + '</span>' +
                    '</div>' +
                '</div>';

            var extrasLine = (item.extras && item.extras.length)
                ? item.extras.map(function(e, eIdx) {
                    var priceText = e.formatted_price || ((parseFloat(e.price) || 0).toFixed(2).replace('.', ',') + ' €');
                    var durText = e.formatted_duration || ((e.duration || 0) + ' min');
                    return '' +
                        '<div class="nova-marcacao-extra-row d-flex justify-content-between align-items-start mt-1 w-100" data-idx="' + idx + '" data-extra-index="' + eIdx + '">' +
                            '<div class="nova-marcacao-service-item-left d-flex flex-column">' +
                                '<div class="d-flex align-items-center">' +
                                    '<div class="nova-marcacao-service-item-name">+ ' + (e.name || '') + '</div>' +
                                    '<button type="button" class="btn btn-link btn-sm p-0 ms-1 mt-1 novaMarcacaoRemoveExtraBtn" data-idx="' + idx + '" data-extra-index="' + eIdx + '" aria-label="Remover extra">' +
                                        '<i class="ph ph-x"></i>' +
                                    '</button>' +
                                '</div>' +
                                '<div class="nova-marcacao-service-item-duration">' + durText + '</div>' +
                            '</div>' +
                            '<div class="nova-marcacao-service-item-price">' + priceText + '</div>' +
                        '</div>';
                }).join('')
                : '';
            var origP = item.original_price != null ? parseFloat(item.original_price) : NaN;
            var currP = item.price != null ? parseFloat(item.price) : NaN;
            var showStrikethrough = !isNaN(origP) && currP !== origP;
            var priceBlock = showStrikethrough
                ? '<span class="text-danger text-decoration-line-through small me-1">' + (origP.toFixed(2).replace('.', ',') + ' €') + '</span><span class="nova-marcacao-service-item-price">' + (item.formatted_price || '') + '</span>'
                : '<span class="nova-marcacao-service-item-price">' + (item.formatted_price || '') + '</span>';
            return '<div class="nova-marcacao-service-item nova-marcacao-service-selected-card mb-2" data-idx="' + idx + '" style="border-left-color:' + (item.color || '#6c757d') + ';display:block;">' +
                serviceRow +
                extrasLine +
                '</div>';
        }).join('');
        container.innerHTML = html;
        if (totalRow) totalRow.classList.remove('d-none');
        container.querySelectorAll('.novaMarcacaoEditServiceBtn').forEach(function(btn) {
            btn.addEventListener('click', function(e) { e.stopPropagation(); NovaMarcacao.openEditQuickMenu(e, parseInt(this.dataset.idx, 10)); });
        });
        container.querySelectorAll('.novaMarcacaoDeleteServiceBtn').forEach(function(btn) {
            btn.addEventListener('click', function(e) { e.stopPropagation(); NovaMarcacao.deleteService(parseInt(this.dataset.idx, 10)); });
        });
        container.querySelectorAll('.novaMarcacaoAddExtrasBtn').forEach(function(btn) {
            btn.addEventListener('click', function(e) { e.stopPropagation(); NovaMarcacao.openAddExtrasQuickMenu(e, parseInt(this.dataset.idx, 10)); });
        });
        container.querySelectorAll('.novaMarcacaoRemoveExtraBtn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var sIdx = parseInt(this.dataset.idx, 10);
                var exIdx = parseInt(this.dataset.extraIndex, 10);
                if (!isNaN(sIdx) && !isNaN(exIdx) && novaMarcacaoSelectedServices[sIdx] && Array.isArray(novaMarcacaoSelectedServices[sIdx].extras)) {
                    novaMarcacaoSelectedServices[sIdx].extras.splice(exIdx, 1);
                    NovaMarcacao.renderSelectedServices();
                    NovaMarcacao.updateEndTimeAndTotal();
                }
            });
        });
    }

    NovaMarcacao.openAddExtrasQuickMenu = function(evt, idx) {
        var item = novaMarcacaoSelectedServices[idx];
        if (!item || !item.available_extras || !item.available_extras.length) return;
        var addedIds = (item.extras || []).map(function(e) { return e.id; });
        var toShow = item.available_extras.filter(function(e) { return addedIds.indexOf(e.id) === -1; });
        if (!toShow.length) {
            showToast('Não há mais extras disponíveis para este serviço.', 'info');
            return;
        }
        var popup = $id('novaMarcacaoAddExtrasQuickMenu');
        if (!popup) return;
        var modalContent = $id('novaMarcacaoModal')?.querySelector('.modal-content');
        if (modalContent) modalContent.appendChild(popup);
        function hide() {
            popup.classList.remove('is-open');
            popup.innerHTML = '';
            document.removeEventListener('click', ch);
            document.removeEventListener('keydown', eh);
        }
        function ch(e) { if (popup.contains(e.target)) return; hide(); }
        function eh(e) { if (e.key === 'Escape') { e.stopPropagation(); hide(); } }
        popup.innerHTML = '<div class="edit-service-header"><h6>Adicionar extra</h6><button type="button" class="edit-service-close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button></div><div class="edit-service-body"><div class="list-group list-group-flush" id="novaMarcacaoAddExtrasList"></div></div>';
        popup.querySelector('.edit-service-close').addEventListener('click', hide);
        var list = $id('novaMarcacaoAddExtrasList');
        toShow.forEach(function(ex) {
            var a = document.createElement('a');
            a.href = '#';
            a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            a.innerHTML = '<span>' + (ex.name || '').replace(/</g, '&lt;') + '</span><span class="small text-muted">' + (ex.formatted_price || ex.price + ' €') + ' · ' + (ex.formatted_duration || ex.duration + ' min') + '</span>';
            a.addEventListener('click', function(e) {
                e.preventDefault();
                if (!novaMarcacaoSelectedServices[idx].extras) novaMarcacaoSelectedServices[idx].extras = [];
                novaMarcacaoSelectedServices[idx].extras.push({ id: ex.id, name: ex.name, duration: ex.duration || 0, price: ex.price || 0, formatted_duration: ex.formatted_duration || (ex.duration || 0) + ' min', formatted_price: ex.formatted_price || (ex.price || 0).toFixed(2).replace('.', ',') + ' €' });
                hide();
                NovaMarcacao.renderSelectedServices();
                NovaMarcacao.updateEndTimeAndTotal();
            });
            list.appendChild(a);
        });
        popup.classList.add('is-open');
        var rect = evt.target.closest('button')?.getBoundingClientRect() || { left: evt.clientX, bottom: evt.clientY };
        var offset = 8;
        var container = modalContent && modalContent.parentElement ? modalContent.parentElement.getBoundingClientRect() : null;
        var left = container ? (rect.left - container.left) : (rect.left || 0);
        var top = container ? (rect.bottom + offset - container.top) : ((rect.bottom || 0) + offset);
        popup.style.left = (left || 0) + 'px';
        popup.style.top = (top || 0) + 'px';
        setTimeout(function() { document.addEventListener('click', ch); document.addEventListener('keydown', eh); }, 0);
    }

    NovaMarcacao.applyNewDate = function(ymd) {
        var startStr = $id('novaMarcacaoStart').value;
        var endStr = $id('novaMarcacaoEnd').value;
        if (!startStr || !ymd) return;
        var start = new Date(startStr);
        var parts = (ymd || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!parts) return;
        start.setFullYear(parseInt(parts[1], 10), parseInt(parts[2], 10) - 1, parseInt(parts[3], 10));
        var totalDur = novaMarcacaoSelectedServices.reduce(function(sum, s) {
            var d = (s.duration || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (e.duration || 0); }, 0);
            return sum + d;
        }, 0);
        if (totalDur === 0 && endStr) {
            var oldStart = new Date(startStr);
            var oldEnd = new Date(endStr);
            totalDur = (oldEnd.getTime() - oldStart.getTime()) / 60000;
        }
        if (totalDur < 1) totalDur = 60;
        var end = new Date(start.getTime() + totalDur * 60 * 1000);
        var startIso = start.getFullYear() + '-' + String(start.getMonth() + 1).padStart(2, '0') + '-' + String(start.getDate()).padStart(2, '0') + 'T' + String(start.getHours()).padStart(2, '0') + ':' + String(start.getMinutes()).padStart(2, '0');
        var endIso = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0') + 'T' + String(end.getHours()).padStart(2, '0') + ':' + String(end.getMinutes()).padStart(2, '0');
        $id('novaMarcacaoStart').value = startIso;
        $id('novaMarcacaoEnd').value = endIso;
        $id('novaMarcacaoEditTitleDay').textContent = DAYS_LONG[start.getDay()] + ', ' + start.getDate() + ' ' + MONTHS_LONG[start.getMonth()];
        $id('novaMarcacaoTimeToggle').textContent = String(start.getHours()).padStart(2, '0') + ':' + String(start.getMinutes()).padStart(2, '0');
        NovaMarcacao.updateEndTimeAndTotal();
        var dateToggle = $id('novaMarcacaoDateToggle');
        if (dateToggle && bootstrap.Dropdown) {
            var inst = bootstrap.Dropdown.getInstance(dateToggle);
            if (inst) inst.hide();
        }
    }

    NovaMarcacao.applyNewStartTime = function(newTimeStr) {
        var startStr = $id('novaMarcacaoStart').value;
        if (!startStr) return;
        var start = new Date(startStr);
        var parts = (newTimeStr || '').match(/^(\d{1,2}):(\d{2})/);
        if (!parts) return;
        start.setHours(parseInt(parts[1], 10), parseInt(parts[2], 10), 0, 0);
        var totalDur = novaMarcacaoSelectedServices.reduce(function(sum, s) { return sum + (s.duration || 0); }, 0);
        if (totalDur < 1) totalDur = 60;
        var end = new Date(start.getTime() + totalDur * 60 * 1000);
        var startIso = start.getFullYear() + '-' + String(start.getMonth() + 1).padStart(2, '0') + '-' + String(start.getDate()).padStart(2, '0') + 'T' + String(start.getHours()).padStart(2, '0') + ':' + String(start.getMinutes()).padStart(2, '0');
        var endIso = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0') + 'T' + String(end.getHours()).padStart(2, '0') + ':' + String(end.getMinutes()).padStart(2, '0');
        $id('novaMarcacaoStart').value = startIso;
        $id('novaMarcacaoEnd').value = endIso;
        $id('novaMarcacaoEditTitleDay').textContent = DAYS_LONG[start.getDay()] + ', ' + start.getDate() + ' ' + MONTHS_LONG[start.getMonth()];
        $id('novaMarcacaoTimeToggle').textContent = String(start.getHours()).padStart(2, '0') + ':' + String(start.getMinutes()).padStart(2, '0');
        NovaMarcacao.updateEndTimeAndTotal();
        var dd = $id('novaMarcacaoTimeToggle');
        if (dd && bootstrap.Dropdown) {
            var inst = bootstrap.Dropdown.getInstance(dd);
            if (inst) inst.hide();
        }
    }

    NovaMarcacao.populateTimeOptions = function(selectedTime) {
        var container = $('.nova-marcacao-time-options');
        if (!container) return;
        container.innerHTML = '';
        for (var h = 0; h < 24; h++) {
            for (var m = 0; m < 60; m += 15) {
                var ts = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
                var a = document.createElement('a');
                a.href = '#';
                a.className = 'dropdown-item nova-marcacao-time-opt' + (ts === selectedTime ? ' active' : '');
                a.dataset.time = ts;
                a.textContent = ts;
                a.addEventListener('click', function(e) { e.preventDefault(); NovaMarcacao.applyNewStartTime(this.dataset.time); });
                container.appendChild(a);
            }
        }
    }

    NovaMarcacao.updateEndTimeAndTotal = function() {
        var startStr = $id('novaMarcacaoStart').value;
        if (!startStr) return;
        var totalDur = novaMarcacaoSelectedServices.reduce(function(sum, s) {
            var d = (s.duration || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (e.duration || 0); }, 0);
            return sum + d;
        }, 0);
        var start = new Date(startStr);
        var end = new Date(start.getTime() + totalDur * 60 * 1000);
        var endStr = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0') + 'T' + String(end.getHours()).padStart(2, '0') + ':' + String(end.getMinutes()).padStart(2, '0');
        $id('novaMarcacaoEnd').value = endStr;
        var totalPrice = novaMarcacaoSelectedServices.reduce(function(sum, s) {
            var p = (parseFloat(s.price) || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (parseFloat(e.price) || 0); }, 0);
            return sum + p;
        }, 0);
        $id('novaMarcacaoTotalPrice').textContent = totalPrice.toFixed(2).replace('.', ',') + ' €';
    }

    NovaMarcacao.openEditQuickMenu = function(evt, idx) {
        novaMarcacaoEditServiceIndex = idx;
        var item = novaMarcacaoSelectedServices[idx];
        if (!item) return;
        var popup = $id('novaMarcacaoEditServiceQuickMenu');
        if (!popup) return;
        var modalContent = $id('novaMarcacaoModal')?.querySelector('.modal-content');
        if (modalContent) modalContent.appendChild(popup);

        function hideEditQuickMenu() {
            popup.classList.remove('is-open');
            popup.innerHTML = '';
            document.removeEventListener('click', closeHandler);
            document.removeEventListener('keydown', escHandler);
            window._hideEditServiceQuickMenu = null;
        }
        window._hideEditServiceQuickMenu = hideEditQuickMenu;
        function closeHandler(e) {
            if (popup.contains(e.target)) return;
            hideEditQuickMenu();
        }
        function escHandler(e) {
            if (e.key === 'Escape') { e.stopPropagation(); hideEditQuickMenu(); }
        }

        popup.innerHTML = '<div class="edit-service-header">' +
            '<h6>Alterar opções do serviço</h6>' +
            '<button type="button" class="edit-service-close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button>' +
            '</div>' +
            '<div class="edit-service-body">' +
            '<div class="mb-2"><label class="form-label small">Duração (minutos)</label>' +
            '<input type="number" class="form-control form-control-sm novaMarcacaoEditDuration" min="1" step="1" placeholder="Ex: 60" value="' + (item.duration || '') + '"></div>' +
            '<div class="mb-0"><label class="form-label small">Preço (€)</label>' +
            '<input type="number" class="form-control form-control-sm novaMarcacaoEditPrice" min="0" step="0.01" placeholder="Ex: 25" value="' + (item.price != null && item.price !== '' ? item.price : '') + '"></div>' +
            '</div>' +
            '<div class="edit-service-footer">' +
            '<button type="button" class="btn btn-light btn-sm novaMarcacaoEditCancel">Cancelar</button>' +
            '<button type="button" class="btn btn-primary btn-sm novaMarcacaoEditSave">Guardar</button>' +
            '</div>';

        var header = popup.querySelector('.edit-service-header');
        header.querySelector('.edit-service-close').addEventListener('click', hideEditQuickMenu);

        popup.querySelector('.novaMarcacaoEditCancel').addEventListener('click', hideEditQuickMenu);

        popup.querySelector('.novaMarcacaoEditSave').addEventListener('click', function() {
            var durInput = popup.querySelector('.novaMarcacaoEditDuration');
            var priceInput = popup.querySelector('.novaMarcacaoEditPrice');
            var dur = parseInt(durInput.value, 10);
            var price = parseFloat(priceInput.value);
            var i = novaMarcacaoEditServiceIndex;
            if (i >= 0 && novaMarcacaoSelectedServices[i]) {
                if (!isNaN(dur) && dur > 0) novaMarcacaoSelectedServices[i].duration = dur;
                if (!isNaN(price) && price >= 0) {
                    novaMarcacaoSelectedServices[i].price = price;
                    novaMarcacaoSelectedServices[i].formatted_price = price.toFixed(2).replace('.', ',') + ' €';
                }
                novaMarcacaoSelectedServices[i].formatted_duration = (novaMarcacaoSelectedServices[i].duration || 0) + ' min';
                NovaMarcacao.renderSelectedServices();
                NovaMarcacao.updateEndTimeAndTotal();
            }
            hideEditQuickMenu();
        });

        var rect = evt.target.closest('button')?.getBoundingClientRect() || { left: evt.clientX, bottom: evt.clientY };
        var offset = 8;
        requestAnimationFrame(function() {
            popup.classList.add('is-open');
            var popupRect = popup.getBoundingClientRect();
            var container = modalContent && modalContent.parentElement ? modalContent.parentElement.getBoundingClientRect() : null;
            var left, top;
            if (container) {
                left = (rect.left || evt.clientX) - container.left;
                top = (rect.bottom !== undefined ? rect.bottom : evt.clientY) + offset - container.top;
            } else {
                left = rect.left || evt.clientX;
                top = (rect.bottom !== undefined ? rect.bottom : evt.clientY) + offset;
            }
            var vw = container ? container.width : window.innerWidth;
            var vh = container ? container.height : window.innerHeight;
            var maxLeft = vw - popupRect.width - offset;
            var maxTop = vh - popupRect.height - offset;
            left = Math.max(offset, Math.min(left, maxLeft));
            top = Math.max(offset, Math.min(top, maxTop));
            popup.style.left = left + 'px';
            popup.style.top = top + 'px';
        });
        setTimeout(function() {
            document.addEventListener('click', closeHandler);
            document.addEventListener('keydown', escHandler);
        }, 0);
    }

    NovaMarcacao.deleteService = function(idx) {
        if (typeof window._hideEditServiceQuickMenu === 'function') { window._hideEditServiceQuickMenu(); window._hideEditServiceQuickMenu = null; }
        novaMarcacaoSelectedServices.splice(idx, 1);
        NovaMarcacao.renderSelectedServices();
        NovaMarcacao.updateEndTimeAndTotal();
        if (novaMarcacaoSelectedServices.length === 0) {
            $id('novaMarcacaoServiceSelected').classList.add('d-none');
            $id('novaMarcacaoServicesList').classList.remove('d-none');
        }
    };

    var eventDetailOriginalStartAt = null;
    var eventDetailOriginalEndAt = null;
    var eventDetailWasSaved = false;
    var eventDetailExistingSale = null;

    function setEventDetailPaymentAndReadOnly(existingSale, eventType, servicesCount) {
        var payBtn = $id('eventDetailPaymentBtn');
        var verFatura = $id('eventDetailVerFaturaLink');
        var revertBtn = $id('eventDetailReverterFaturaBtn');
        var saveBtn = $id('eventDetailSaveBtn');
        var readonly = !!existingSale;
        if (payBtn) payBtn.classList.toggle('d-none', readonly || eventType !== 'marcacao' || servicesCount === 0);
        if (verFatura) {
            if (existingSale) {
                verFatura.href = existingSale.pdf_url || '#';
                verFatura.classList.remove('d-none');
            } else {
                verFatura.classList.add('d-none');
            }
        }
        if (revertBtn) {
            revertBtn.classList.toggle('d-none', !existingSale);
            if (existingSale) revertBtn.dataset.saleId = String(existingSale.id);
        }
        if (saveBtn) saveBtn.disabled = readonly;
        var dateToggle = $id('eventDetailDateToggle');
        var timeToggle = $id('eventDetailTimeToggle');
        var statusWrap = $id('eventDetailStatusDropdownWrap');
        var observacoes = $id('eventDetailObservacoes');
        var addMoreBtn = $id('eventDetailAddMoreServicesBtn');
        var clientCancelBtn = $id('eventDetailClientCancelBtn');
        [dateToggle, timeToggle, statusWrap].forEach(function(el) {
            if (el) el.style.pointerEvents = readonly ? 'none' : '';
        });
        if (observacoes) observacoes.disabled = readonly;
        if (addMoreBtn) { addMoreBtn.disabled = readonly; addMoreBtn.style.display = readonly ? 'none' : ''; }
        if (clientCancelBtn) clientCancelBtn.style.display = readonly ? 'none' : '';
        var clientSearchWrap = $id('eventDetailClientSearchWrap');
        var clientResults = $id('eventDetailClientResults');
        var clientClear = $id('eventDetailClientClear');
        var clientAddWrap = $id('eventDetailClientAddWrap');
        if (clientSearchWrap) clientSearchWrap.style.display = readonly ? 'none' : '';
        if (clientResults) clientResults.style.display = readonly ? 'none' : '';
        if (clientClear) clientClear.style.display = readonly ? 'none' : '';
        if (clientAddWrap) clientAddWrap.style.display = readonly ? 'none' : '';
        var selectedList = $id('eventDetailSelectedServicesList');
        if (selectedList) selectedList.style.pointerEvents = readonly ? 'none' : '';
        var editModal = $id('eventDetailEditModal');
        if (editModal) editModal.classList.toggle('event-detail-readonly', readonly);
    }

    function populateEventDetailEditModal(data) {
        eventDetailCurrentData = data;
        eventDetailExistingSale = data.existing_sale || null;
        eventDetailOriginalStartAt = data.start_at || null;
        eventDetailOriginalEndAt = data.end_at || null;
        eventDetailSelectedServices = [];
        var id = data.id;
        $id('eventDetailEditId').value = id;
        $id('eventDetailEditUserId').value = data.user_id || '';
        $id('eventDetailEditStart').value = data.start_at || '';
        $id('eventDetailEditEnd').value = data.end_at || '';
        var startDate = data.start_at ? new Date(data.start_at) : null;
        var endDate = data.end_at ? new Date(data.end_at) : null;
        if (startDate) {
            var dayStr = DAYS_LONG[startDate.getDay()] + ', ' + startDate.getDate() + ' ' + MONTHS_LONG[startDate.getMonth()];
            var timeStr = String(startDate.getHours()).padStart(2, '0') + ':' + String(startDate.getMinutes()).padStart(2, '0');
            var min = startDate.getMinutes();
            var m = Math.round(min / 15) * 15;
            if (m === 60) { m = 0; }
            var timeSlotForDropdown = String(startDate.getHours()).padStart(2, '0') + ':' + String(m).padStart(2, '0');
            $id('eventDetailEditTitleDay').textContent = dayStr;
            $id('eventDetailTimeToggle').textContent = timeStr;
            eventDetailPopulateTimeOptions(timeSlotForDropdown);
            if (window.eventDetailDateFlatpickr) {
                var ymdStr = startDate.getFullYear() + '-' + String(startDate.getMonth() + 1).padStart(2, '0') + '-' + String(startDate.getDate()).padStart(2, '0');
                window.eventDetailDateFlatpickr.setDate(ymdStr, false);
            }
        } else {
            $id('eventDetailEditTitleDay').textContent = '—';
            $id('eventDetailTimeToggle').textContent = '—';
            eventDetailPopulateTimeOptions('');
        }
        var agentInfo = agendaAgentInfo[String(data.user_id)] || { name: data.user_name || '—', email: '', avatarUrl: data.user_avatar_url || '' };
        $id('eventDetailAgentName').textContent = agentInfo.name || '—';
        $id('eventDetailAgentLink').href = (function() { var info = agendaAgentInfo[String(data.user_id || '')]; return (info && info.agentId) ? (agendaEquipaBaseUrl + '/' + info.agentId) : '#'; })();
        if (agentInfo.avatarUrl) {
            $id('eventDetailAgentAvatar').src = agentInfo.avatarUrl;
            $id('eventDetailAgentAvatar').style.display = 'block';
        } else {
            $id('eventDetailAgentAvatar').style.display = 'none';
        }
        var statusVal = data.status || 'agendado';
        $id('eventDetailStatus').value = statusVal;
        $id('eventDetailStatusLabel').textContent = STATUS_LABELS[statusVal] || statusVal;
        var iconEl = $id('eventDetailStatusIcon');
        if (iconEl) {
            var ic = iconEl.querySelector('i');
            if (ic) ic.className = 'me-2 ph ' + (STATUS_ICONS[statusVal] || 'ph-clock');
        }
        // Mostrar estado como texto verde fixo quando concluído (sem dropdown)
        var statusDropdownWrap = $id('eventDetailStatusDropdownWrap');
        var statusStatic = $id('eventDetailStatusStatic');
        var statusStaticIcon = $id('eventDetailStatusStaticIcon');
        var statusStaticLabel = $id('eventDetailStatusStaticLabel');
        if (statusVal === 'completo') {
            if (statusDropdownWrap) statusDropdownWrap.classList.add('d-none');
            if (statusStatic) {
                statusStatic.classList.remove('d-none');
                if (statusStaticLabel) statusStaticLabel.textContent = STATUS_LABELS[statusVal] || 'Concluído';
                if (statusStaticIcon) {
                    var si = statusStaticIcon.querySelector('i');
                    if (si) si.className = 'me-1 ph ' + (STATUS_ICONS[statusVal] || 'ph-check-circle');
                }
            }
        } else {
            if (statusDropdownWrap) statusDropdownWrap.classList.remove('d-none');
            if (statusStatic) statusStatic.classList.add('d-none');
        }
        var cancelOpt = $id('eventDetailStatusMenu')?.querySelector('[data-status="cancelar"]');
        if (cancelOpt) cancelOpt.style.display = (statusVal === 'faltou' || statusVal === 'cancelado') ? 'none' : '';
        $id('eventDetailObservacoes').value = data.description || '';
        var hasVisitLead = !!(data.visit || data.lead);
        var hasClient = data.client_id && data.client_name;
        $id('eventDetailClientAddWrap').classList.toggle('d-none', hasVisitLead || hasClient);
        $id('eventDetailClientSearchWrap').classList.add('d-none');
        $id('eventDetailClientResults').classList.add('d-none');
        $id('eventDetailClientSelected').classList.add('d-none');
        $id('eventDetailVisitLeadBlock').classList.add('d-none');
        $id('eventDetailCreateClientBtn').classList.add('d-none');
        if (hasVisitLead) {
            var block = $id('eventDetailVisitLeadBlock');
            block.classList.remove('d-none');
            block.innerHTML = '';
            if (data.visit) {
                block.innerHTML = '<h6 class="nova-marcacao-section-title">Cliente (Visita)</h6><div class="nova-marcacao-person"><div><strong>' + (data.visit.client_name || '—') + '</strong></div></div>' +
                    '<div class="mt-2"><a href="' + (data.visit.opportunity_id ? (C.urlOpportunities || '') + '/' + data.visit.opportunity_id : '#') + '" class="btn btn-sm btn-outline-primary"><i class="ph ph-briefcase me-1"></i>Ficha da Oportunidade</a></div>';
            } else if (data.lead) {
                block.innerHTML = '<h6 class="nova-marcacao-section-title">Lead</h6><div class="nova-marcacao-person"><div><strong>' + (data.lead.name || '—') + '</strong><span class="d-block small text-muted">' + [data.lead.email, data.lead.phone].filter(Boolean).join(' · ') + '</span></div></div>' +
                    '<div class="mt-2"><a href="' + (C.urlLeads || '') + '/' + data.lead.id + '" class="btn btn-sm btn-outline-primary"><i class="ph ph-file-text me-1"></i>Ficha da Lead</a></div>';
            }
        } else if (data.client_id && data.client_name) {
            eventDetailSelectedClient = { id: data.client_id, name: data.client_name, phone: data.client_phone || '', avatar_url: data.client_avatar_url || '' };
            $id('eventDetailClientSelectedName').textContent = data.client_name;
            $id('eventDetailClientSelectedEmail').textContent = data.client_phone || '—';
            if (data.client_avatar_url) {
                $id('eventDetailClientAvatar').src = data.client_avatar_url;
                $id('eventDetailClientAvatar').classList.remove('d-none');
                $id('eventDetailClientAvatarFallback').classList.add('d-none');
            } else {
                var initials = (data.client_name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
                $id('eventDetailClientAvatarFallback').textContent = initials;
                $id('eventDetailClientAvatarFallback').classList.remove('d-none');
                $id('eventDetailClientAvatar').classList.add('d-none');
            }
            $id('eventDetailClientSelected').classList.remove('d-none');
            $id('eventDetailCreateClientBtn').classList.add('d-none');
            $id('eventDetailClientAddWrap').classList.add('d-none');
            $id('eventDetailClientSearchWrap').classList.add('d-none');
            var link = $id('eventDetailClientProfileLink');
            if (link) link.href = clientesBaseUrl + '/' + data.client_id;
        } else {
            eventDetailSelectedClient = null;
        }
        $id('eventDetailServicesCol').classList.toggle('d-none', data.event_type !== 'marcacao');
        if (data.event_type === 'marcacao') {
            (data.event_services || []).forEach(function(s) {
                var dur = s.duration || 60;
                var pr = parseFloat(s.price) || 0;
                var origPr = s.original_price != null ? parseFloat(s.original_price) : pr;
                var extras = (s.extras || []).map(function(e) {
                    return { id: e.extra_id, name: e.name, duration: e.duration || 0, price: e.price || 0, formatted_duration: e.formatted_duration || (e.duration || 0) + ' min', formatted_price: e.formatted_price || (e.price || 0).toFixed(2).replace('.', ',') + ' €' };
                });
                eventDetailSelectedServices.push({
                    service_id: s.id,
                    name: s.name,
                    duration: dur,
                    price: pr,
                    original_price: origPr,
                    formatted_duration: (s.formatted_duration || dur + ' min'),
                    formatted_price: s.formatted_price || (pr.toFixed(2).replace('.', ',') + ' €'),
                    color: s.color || '#6c757d',
                    available_extras: [],
                    extras: extras
                });
            });
            EventDetail.renderSelectedServices();
            EventDetail.updateTotal();
            EventDetail.updateEndTime();
            setEventDetailPaymentAndReadOnly(eventDetailExistingSale, 'marcacao', eventDetailSelectedServices.length);
            $id('eventDetailServicesList').innerHTML = '<div class="text-muted small">A carregar...</div>';
            fetch(agendaMembersServicesUrl + '/' + (data.user_id || C.authId) + '/services', { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(svcData) {
                    eventDetailServicesData = svcData;
                    eventDetailSelectedServices.forEach(function(item) {
                        var availableExtras = [];
                        (svcData.categories || []).forEach(function(cat) {
                            (cat.services || []).forEach(function(svc) {
                                if (String(svc.id) === String(item.service_id)) availableExtras = svc.extras || [];
                            });
                        });
                        item.available_extras = availableExtras;
                    });
                    EventDetail.renderSelectedServices();
                    var html = '';
                    (svcData.categories || []).forEach(function(cat) {
                        var count = (cat.services || []).length;
                        html += '<div class="nova-marcacao-services-category"><span>' + (cat.name || 'Outros') + '</span><span class="badge rounded-pill nova-marcacao-category-count ms-2">' + count + '</span></div>';
                        var color = cat.color || '#6c757d';
                        (cat.services || []).forEach(function(s) {
                            var sFormattedDur = s.formatted_duration || (s.duration || 60) + ' min';
                            var sFormattedPrice = s.formatted_price || '';
                            var sPrice = (s.price != null && s.price !== '') ? parseFloat(s.price) : 0;
                            html += '<div class="nova-marcacao-service-item event-detail-service-item" data-service-id="' + s.id + '" data-duration="' + (s.duration || 60) + '" data-name="' + (s.name || '').replace(/"/g, '&quot;') + '" data-price="' + sPrice + '" data-formatted-duration="' + (sFormattedDur || '').replace(/"/g, '&quot;') + '" data-formatted-price="' + (sFormattedPrice || '').replace(/"/g, '&quot;') + '" data-color="' + (color || '#6c757d').replace(/"/g, '&quot;') + '" style="border-left-color:' + color + '">';
                            html += '<div class="nova-marcacao-service-item-left"><div class="nova-marcacao-service-item-name">' + (s.name || '') + '</div><div class="nova-marcacao-service-item-duration">' + sFormattedDur + '</div></div>';
                            html += '<div class="nova-marcacao-service-item-price">' + sFormattedPrice + '</div></div>';
                        });
                    });
                    $id('eventDetailServicesList').innerHTML = html || '<div class="text-muted small">Nenhum serviço disponível.</div>';
                    $id('eventDetailServicesList').querySelectorAll('.event-detail-service-item').forEach(function(el) {
                        el.addEventListener('click', function() {
                            var sid = this.dataset.serviceId;
                            if (eventDetailSelectedServices.some(function(s) { return String(s.service_id) === sid; })) return;
                            var dur = parseInt(this.dataset.duration, 10) || 60;
                            var priceNum = parseFloat(this.dataset.price) || 0;
                            var availableExtras = [];
                            (eventDetailServicesData.categories || []).forEach(function(cat) {
                                (cat.services || []).forEach(function(svc) {
                                    if (String(svc.id) === sid) availableExtras = svc.extras || [];
                                });
                            });
                            eventDetailSelectedServices.push({
                                service_id: sid, name: this.dataset.name || '', duration: dur, price: priceNum,
                                original_price: priceNum,
                                formatted_duration: this.dataset.formattedDuration || dur + ' min',
                                formatted_price: this.dataset.formattedPrice || (priceNum.toFixed(2).replace('.', ',') + ' €'),
                                color: this.dataset.color || '#6c757d',
                                available_extras: availableExtras,
                                extras: []
                            });
                    EventDetail.renderSelectedServices();
                    EventDetail.updateTotal();
                    EventDetail.updateEndTime();
                            $id('eventDetailServicesList').classList.add('d-none');
                            $id('eventDetailCancelAddServicesBtn').classList.add('d-none');
                            $id('eventDetailServiceSelected').classList.remove('d-none');
                        });
                    });
                    if (eventDetailSelectedServices.length > 0) {
                        $id('eventDetailServicesList').classList.add('d-none');
                        $id('eventDetailCancelAddServicesBtn').classList.add('d-none');
                        $id('eventDetailServiceSelected').classList.remove('d-none');
                    }
                    setEventDetailPaymentAndReadOnly(eventDetailExistingSale, 'marcacao', eventDetailSelectedServices.length);
                })
                .catch(function() {
                    $id('eventDetailServicesList').innerHTML = '<div class="text-danger small">Erro ao carregar serviços.</div>';
                });
        } else {
            setEventDetailPaymentAndReadOnly(null, data.event_type || '', 0);
        }
    }

    var eventDetailSelectedClient = null;
    var eventDetailServicesData = null;

    const EventDetail = {};

    EventDetail.renderSelectedServices = function() {
        var container = $id('eventDetailSelectedServicesList');
        if (!container) return;
        var titleEl = $('#eventDetailServiceSelected .nova-marcacao-services-selected-title');
        var totalRow = $id('eventDetailTotalPrice') ? $id('eventDetailTotalPrice').closest('.nova-marcacao-total-row') : null;
        if (eventDetailSelectedServices.length === 0) {
            container.innerHTML = '';
            if (titleEl) titleEl.textContent = 'Serviços selecionados';
            if (totalRow) totalRow.classList.remove('d-none');
            return;
        }
        if (titleEl) titleEl.textContent = eventDetailSelectedServices.length === 1 ? 'Serviço selecionado' : 'Serviços selecionados';
        var isCompleted = (eventDetailCurrentData?.status === 'completo');
        var html = eventDetailSelectedServices.map(function(item, idx) {
            var origP = item.original_price != null ? parseFloat(item.original_price) : NaN;
            var currP = item.price != null ? parseFloat(item.price) : NaN;
            var showStrikethrough = !isNaN(origP) && currP !== origP;
            var priceBlock = showStrikethrough
                ? '<span class="text-danger text-decoration-line-through small me-1">' + (origP.toFixed(2).replace('.', ',') + ' €') + '</span><span class="nova-marcacao-service-item-price">' + (item.formatted_price || '') + '</span>'
                : '<span class="nova-marcacao-service-item-price">' + (item.formatted_price || '') + '</span>';

            var serviceRow =
                '<div class="d-flex justify-content-between align-items-center w-100">' +
                    '<div class="nova-marcacao-service-item-left">' +
                        '<div class="nova-marcacao-service-item-name">' + (item.name || '') + '</div>' +
                        '<div class="nova-marcacao-service-item-duration">' + (item.formatted_duration || item.duration + ' min') + '</div>' +
                    '</div>' +
                    '<div class="d-flex align-items-center gap-2 justify-content-end">' +
                        (isCompleted
                            ? priceBlock
                            : (
                                '<div class="d-flex gap-1">' +
                                    (item.available_extras && item.available_extras.length > 0
                                        ? '<button type="button" class="btn btn-outline-secondary btn-icon btn-sm eventDetailAddExtrasBtn" data-idx="' + idx + '" title="Adicionar extras"><i class="ph ph-plus-circle"></i></button>'
                                        : ''
                                    ) +
                                    '<button type="button" class="btn btn-outline-secondary btn-icon btn-sm eventDetailEditServiceBtn" data-idx="' + idx + '" title="Alterar opções"><i class="ph ph-pencil-simple"></i></button>' +
                                    '<button type="button" class="btn btn-outline-danger btn-icon btn-sm eventDetailDeleteServiceBtn" data-idx="' + idx + '" title="Eliminar"><i class="ph ph-trash"></i></button>' +
                                '</div>' +
                                priceBlock
                            )
                        ) +
                    '</div>' +
                '</div>';

            var extrasLine = (item.extras && item.extras.length)
                ? item.extras.map(function(e, eIdx) {
                    var priceText = e.formatted_price || ((parseFloat(e.price) || 0).toFixed(2).replace('.', ',') + ' €');
                    var durText = e.formatted_duration || ((e.duration || 0) + ' min');
                    var removeBtn = '';
                    if (!isCompleted) {
                        removeBtn =
                            '<button type="button" class="btn btn-link btn-sm p-0 ms-1 mt-1 eventDetailRemoveExtraBtn" data-idx="' + idx + '" data-extra-index="' + eIdx + '" aria-label="Remover extra">' +
                                '<i class="ph ph-x"></i>' +
                            '</button>';
                    }
                    return '' +
                        '<div class="event-detail-extra-row d-flex justify-content-between align-items-start mt-1 w-100" data-idx="' + idx + '" data-extra-index="' + eIdx + '">' +
                            '<div class="nova-marcacao-service-item-left d-flex flex-column">' +
                                '<div class="d-flex align-items-center">' +
                                    '<div class="nova-marcacao-service-item-name">+ ' + (e.name || '') + '</div>' +
                                    removeBtn +
                                '</div>' +
                                '<div class="nova-marcacao-service-item-duration">' + durText + '</div>' +
                            '</div>' +
                            '<div class="nova-marcacao-service-item-price">' + priceText + '</div>' +
                        '</div>';
                }).join('')
                : '';

            return '<div class="nova-marcacao-service-item nova-marcacao-service-selected-card mb-2" data-idx="' + idx + '" style="border-left-color:' + (item.color || '#6c757d') + ';display:block;">' +
                serviceRow +
                extrasLine +
                '</div>';
        }).join('');
        container.innerHTML = html;
        if (totalRow) totalRow.classList.remove('d-none');
        if (!isCompleted) {
            container.querySelectorAll('.eventDetailEditServiceBtn').forEach(function(btn) {
                btn.addEventListener('click', function(e) { e.stopPropagation(); EventDetail.openEditQuickMenu(e, parseInt(this.dataset.idx, 10)); });
            });
            container.querySelectorAll('.eventDetailDeleteServiceBtn').forEach(function(btn) {
                btn.addEventListener('click', function(e) { e.stopPropagation(); EventDetail.deleteService(parseInt(this.dataset.idx, 10)); });
            });
            container.querySelectorAll('.eventDetailAddExtrasBtn').forEach(function(btn) {
                btn.addEventListener('click', function(e) { e.stopPropagation(); EventDetail.openAddExtrasQuickMenu(e, parseInt(this.dataset.idx, 10)); });
            });
            container.querySelectorAll('.eventDetailRemoveExtraBtn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var sIdx = parseInt(this.dataset.idx, 10);
                    var exIdx = parseInt(this.dataset.extraIndex, 10);
                    if (!isNaN(sIdx) && !isNaN(exIdx) && eventDetailSelectedServices[sIdx] && Array.isArray(eventDetailSelectedServices[sIdx].extras)) {
                        eventDetailSelectedServices[sIdx].extras.splice(exIdx, 1);
                        EventDetail.renderSelectedServices();
                        EventDetail.updateTotal();
                        EventDetail.updateEndTime();
                    }
                });
            });
        }
    }

    EventDetail.openAddExtrasQuickMenu = function(evt, idx) {
        var item = eventDetailSelectedServices[idx];
        if (!item || !item.available_extras || !item.available_extras.length) return;
        var addedIds = (item.extras || []).map(function(e) { return e.id; });
        var toShow = item.available_extras.filter(function(e) { return addedIds.indexOf(e.id) === -1; });
        if (!toShow.length) {
            showToast('Não há mais extras disponíveis para este serviço.', 'info');
            return;
        }
        var popup = $id('eventDetailAddExtrasQuickMenu');
        if (!popup) return;
        var modalContent = $id('eventDetailEditModal')?.querySelector('.modal-content');
        if (modalContent) modalContent.appendChild(popup);
        function hide() {
            popup.classList.remove('is-open');
            popup.innerHTML = '';
            document.removeEventListener('click', ch);
            document.removeEventListener('keydown', eh);
        }
        function ch(e) { if (popup.contains(e.target)) return; hide(); }
        function eh(e) { if (e.key === 'Escape') { e.stopPropagation(); hide(); } }
        popup.innerHTML = '<div class="edit-service-header"><h6>Adicionar extra</h6><button type="button" class="edit-service-close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button></div><div class="edit-service-body"><div class="list-group list-group-flush" id="eventDetailAddExtrasList"></div></div>';
        popup.querySelector('.edit-service-close').addEventListener('click', hide);
        var list = $id('eventDetailAddExtrasList');
        toShow.forEach(function(ex) {
            var a = document.createElement('a');
            a.href = '#';
            a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            a.innerHTML = '<span>' + (ex.name || '').replace(/</g, '&lt;') + '</span><span class="small text-muted">' + (ex.formatted_price || ex.price + ' €') + ' · ' + (ex.formatted_duration || ex.duration + ' min') + '</span>';
            a.addEventListener('click', function(e) {
                e.preventDefault();
                if (!eventDetailSelectedServices[idx].extras) eventDetailSelectedServices[idx].extras = [];
                eventDetailSelectedServices[idx].extras.push({ id: ex.id, name: ex.name, duration: ex.duration || 0, price: ex.price || 0, formatted_duration: ex.formatted_duration || (ex.duration || 0) + ' min', formatted_price: ex.formatted_price || (ex.price || 0).toFixed(2).replace('.', ',') + ' €' });
                hide();
                EventDetail.renderSelectedServices();
                EventDetail.updateTotal();
                EventDetail.updateEndTime();
            });
            list.appendChild(a);
        });
        popup.classList.add('is-open');
        var rect = evt.target.closest('button')?.getBoundingClientRect() || { left: evt.clientX, bottom: evt.clientY };
        var offset = 8;
        var container = modalContent && modalContent.parentElement ? modalContent.parentElement.getBoundingClientRect() : null;
        var left = container ? (rect.left - container.left) : (rect.left || 0);
        var top = container ? (rect.bottom + offset - container.top) : ((rect.bottom || 0) + offset);
        popup.style.left = (left || 0) + 'px';
        popup.style.top = (top || 0) + 'px';
        setTimeout(function() { document.addEventListener('click', ch); document.addEventListener('keydown', eh); }, 0);
    }

    EventDetail.updateTotal = function() {
        var total = eventDetailSelectedServices.reduce(function(sum, s) {
            var p = (parseFloat(s.price) || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (parseFloat(e.price) || 0); }, 0);
            return sum + p;
        }, 0);
        $id('eventDetailTotalPrice').textContent = total.toFixed(2).replace('.', ',') + ' €';
    }

    EventDetail.updateEndTime = function() {
        var startStr = $id('eventDetailEditStart').value;
        if (!startStr) return;
        var totalDur = eventDetailSelectedServices.reduce(function(sum, s) {
            var d = (s.duration || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (e.duration || 0); }, 0);
            return sum + d;
        }, 0);
        var start = new Date(startStr);
        var end = new Date(start.getTime() + totalDur * 60 * 1000);
        var endStr = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0') + 'T' + String(end.getHours()).padStart(2, '0') + ':' + String(end.getMinutes()).padStart(2, '0');
        $id('eventDetailEditEnd').value = endStr;
    }

    EventDetail.applyNewStartTime = function(newTimeStr) {
        var startStr = $id('eventDetailEditStart').value;
        var endStr = $id('eventDetailEditEnd').value;
        if (!startStr) return;
        var start = new Date(startStr);
        var parts = (newTimeStr || '').match(/^(\d{1,2}):(\d{2})/);
        if (!parts) return;
        start.setHours(parseInt(parts[1], 10), parseInt(parts[2], 10), 0, 0);
        var totalDur = eventDetailSelectedServices.reduce(function(sum, s) {
            var d = (s.duration || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (e.duration || 0); }, 0);
            return sum + d;
        }, 0);
        if (totalDur === 0 && endStr) {
            var oldStart = new Date(startStr);
            var oldEnd = new Date(endStr);
            totalDur = (oldEnd.getTime() - oldStart.getTime()) / 60000;
        }
        if (totalDur < 1) totalDur = 60;
        var end = new Date(start.getTime() + totalDur * 60 * 1000);
        var startIso = start.getFullYear() + '-' + String(start.getMonth() + 1).padStart(2, '0') + '-' + String(start.getDate()).padStart(2, '0') + 'T' + String(start.getHours()).padStart(2, '0') + ':' + String(start.getMinutes()).padStart(2, '0');
        var endIso = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0') + 'T' + String(end.getHours()).padStart(2, '0') + ':' + String(end.getMinutes()).padStart(2, '0');
        $id('eventDetailEditStart').value = startIso;
        $id('eventDetailEditEnd').value = endIso;
        $id('eventDetailEditTitleDay').textContent = DAYS_LONG[start.getDay()] + ', ' + start.getDate() + ' ' + MONTHS_LONG[start.getMonth()];
        $id('eventDetailTimeToggle').textContent = String(start.getHours()).padStart(2, '0') + ':' + String(start.getMinutes()).padStart(2, '0');
        if (eventDetailCurrentData) {
            eventDetailCurrentData.start_at = startIso;
            eventDetailCurrentData.end_at = endIso;
        }
        var evId = $id('eventDetailEditId').value;
        if (evId && typeof calendar !== 'undefined') {
            var ev = calendar.getEventById(evId);
            if (ev) {
                ev.setStart(start);
                ev.setEnd(end);
            }
        }
        var dd = $id('eventDetailTimeToggle');
        if (dd && bootstrap.Dropdown) {
            var inst = bootstrap.Dropdown.getInstance(dd);
            if (inst) inst.hide();
        }
    }

    EventDetail.applyNewDate = function(ymd) {
        var startStr = $id('eventDetailEditStart').value;
        var endStr = $id('eventDetailEditEnd').value;
        if (!startStr || !ymd) return;
        var start = new Date(startStr);
        var parts = (ymd || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!parts) return;
        start.setFullYear(parseInt(parts[1], 10), parseInt(parts[2], 10) - 1, parseInt(parts[3], 10));
        var totalDur = eventDetailSelectedServices.reduce(function(sum, s) {
            var d = (s.duration || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (e.duration || 0); }, 0);
            return sum + d;
        }, 0);
        if (totalDur === 0 && endStr) {
            var oldStart = new Date(startStr);
            var oldEnd = new Date(endStr);
            totalDur = (oldEnd.getTime() - oldStart.getTime()) / 60000;
        }
        if (totalDur < 1) totalDur = 60;
        var end = new Date(start.getTime() + totalDur * 60 * 1000);
        var startIso = start.getFullYear() + '-' + String(start.getMonth() + 1).padStart(2, '0') + '-' + String(start.getDate()).padStart(2, '0') + 'T' + String(start.getHours()).padStart(2, '0') + ':' + String(start.getMinutes()).padStart(2, '0');
        var endIso = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0') + 'T' + String(end.getHours()).padStart(2, '0') + ':' + String(end.getMinutes()).padStart(2, '0');
        $id('eventDetailEditStart').value = startIso;
        $id('eventDetailEditEnd').value = endIso;
        $id('eventDetailEditTitleDay').textContent = DAYS_LONG[start.getDay()] + ', ' + start.getDate() + ' ' + MONTHS_LONG[start.getMonth()];
        $id('eventDetailTimeToggle').textContent = String(start.getHours()).padStart(2, '0') + ':' + String(start.getMinutes()).padStart(2, '0');
        if (eventDetailCurrentData) {
            eventDetailCurrentData.start_at = startIso;
            eventDetailCurrentData.end_at = endIso;
        }
        var evId = $id('eventDetailEditId').value;
        if (evId && typeof calendar !== 'undefined') {
            var ev = calendar.getEventById(evId);
            if (ev) {
                ev.setStart(start);
                ev.setEnd(end);
            }
        }
        var dateToggle = $id('eventDetailDateToggle');
        if (dateToggle && bootstrap.Dropdown) {
            var inst = bootstrap.Dropdown.getInstance(dateToggle);
            if (inst) inst.hide();
        }
    }

    var eventDetailEditServiceIndex = -1;
    EventDetail.openEditQuickMenu = function(evt, idx) {
        eventDetailEditServiceIndex = idx;
        var item = eventDetailSelectedServices[idx];
        if (!item) return;
        var popup = $id('novaMarcacaoEditServiceQuickMenu');
        if (!popup) return;
        function hide() { popup.classList.remove('is-open'); popup.innerHTML = ''; document.removeEventListener('click', ch); document.removeEventListener('keydown', eh); window._hideEditServiceQuickMenu = null; }
        window._hideEditServiceQuickMenu = hide;
        function ch(e) { if (popup.contains(e.target)) return; hide(); }
        function eh(e) { if (e.key === 'Escape') { e.stopPropagation(); hide(); } }
        var modalContent = $id('eventDetailEditModal')?.querySelector('.modal-content');
        if (modalContent) modalContent.appendChild(popup);
        popup.innerHTML = '<div class="edit-service-header"><h6>Alterar opções do serviço</h6><button type="button" class="edit-service-close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button></div>' +
            '<div class="edit-service-body"><div class="mb-2"><label class="form-label small">Duração (minutos)</label><input type="number" class="form-control form-control-sm edDur" min="1" value="' + (item.duration || '') + '"></div>' +
            '<div class="mb-0"><label class="form-label small">Preço (€)</label><input type="number" class="form-control form-control-sm edPrice" min="0" step="0.01" value="' + (item.price != null ? item.price : '') + '"></div></div>' +
            '<div class="edit-service-footer"><button type="button" class="btn btn-light btn-sm edCancel">Cancelar</button><button type="button" class="btn btn-primary btn-sm edSave">Guardar</button></div>';
        popup.querySelector('.edit-service-close').addEventListener('click', hide);
        popup.querySelector('.edCancel').addEventListener('click', hide);
        popup.querySelector('.edSave').addEventListener('click', function() {
            var d = parseInt(popup.querySelector('.edDur').value, 10);
            var p = parseFloat(popup.querySelector('.edPrice').value);
            if (eventDetailSelectedServices[idx]) {
                if (!isNaN(d) && d > 0) eventDetailSelectedServices[idx].duration = d;
                if (!isNaN(p) && p >= 0) { eventDetailSelectedServices[idx].price = p; eventDetailSelectedServices[idx].formatted_price = p.toFixed(2).replace('.', ',') + ' €'; }
                eventDetailSelectedServices[idx].formatted_duration = (eventDetailSelectedServices[idx].duration || 0) + ' min';
                EventDetail.renderSelectedServices();
                EventDetail.updateTotal();
                EventDetail.updateEndTime();
            }
            hide();
        });
        var rect = evt.target.closest('button')?.getBoundingClientRect() || { left: evt.clientX, bottom: evt.clientY };
        var offset = 8;
        requestAnimationFrame(function() {
            popup.classList.add('is-open');
            var popupRect = popup.getBoundingClientRect();
            var container = modalContent && modalContent.parentElement ? modalContent.parentElement.getBoundingClientRect() : null;
            var left, top;
            if (container) {
                left = (rect.left || evt.clientX) - container.left;
                top = (rect.bottom !== undefined ? rect.bottom : evt.clientY) + offset - container.top;
            } else {
                left = rect.left || evt.clientX;
                top = (rect.bottom !== undefined ? rect.bottom : evt.clientY) + offset;
            }
            var vw = container ? container.width : window.innerWidth;
            var vh = container ? container.height : window.innerHeight;
            var maxLeft = vw - popupRect.width - offset;
            var maxTop = vh - popupRect.height - offset;
            left = Math.max(offset, Math.min(left, maxLeft));
            top = Math.max(offset, Math.min(top, maxTop));
            popup.style.left = left + 'px';
            popup.style.top = top + 'px';
        });
        setTimeout(function() { document.addEventListener('click', ch); document.addEventListener('keydown', eh); }, 0);
    }

    EventDetail.deleteService = function(idx) {
        if (typeof window._hideEditServiceQuickMenu === 'function') { window._hideEditServiceQuickMenu(); window._hideEditServiceQuickMenu = null; }
        eventDetailSelectedServices.splice(idx, 1);
        EventDetail.renderSelectedServices();
        EventDetail.updateTotal();
        EventDetail.updateEndTime();
        if (eventDetailSelectedServices.length === 0) {
            $id('eventDetailServiceSelected').classList.add('d-none');
            $id('eventDetailServicesList').classList.remove('d-none');
        }
    }
    var agendaAgentInfo = C.agendaAgentInfo || {};

    function openNovaMarcacaoModal(startStr, endStr, resourceId, preSelectedClientId) {
        var agentId = resourceId || String(C.authId || '');
        $id('novaMarcacaoAgentId').value = agentId;
        $id('novaMarcacaoStart').value = startStr;
        $id('novaMarcacaoEnd').value = endStr;
        $id('novaMarcacaoObservacoes').value = '';
        novaMarcacaoSelectedClient = null;
        $id('novaMarcacaoClientAddWrap').classList.remove('d-none');
        $id('novaMarcacaoClientSearchWrap').classList.remove('d-none');
        $id('novaMarcacaoClientSelected').classList.add('d-none');
        $id('novaMarcacaoClientSearch').value = '';
        $id('novaMarcacaoClientResults').innerHTML = '';
        var agentInfo = agendaAgentInfo[String(agentId)] || { name: '—', email: '', avatarUrl: '' };
        if (!agentInfo.name || agentInfo.name === '—') {
            var resources = calendar.getResources();
            for (var i = 0; i < resources.length; i++) {
                if (String(resources[i].id) === String(agentId)) {
                    agentInfo.name = resources[i].title || '—';
                    agentInfo.avatarUrl = resources[i].extendedProps?.avatarUrl || agentInfo.avatarUrl;
                    break;
                }
            }
        }
        if (agentInfo.name === '—' && agentId === String(C.authId || '')) {
            agentInfo = { name: (C.authName || 'Eu'), email: (C.authEmail || ''), avatarUrl: agentInfo.avatarUrl || '' };
        }
        var agentName = agentInfo.name || '—';
        var agentNameEl = $id('novaMarcacaoAgentName');
        if (agentNameEl) agentNameEl.textContent = agentName;
        var inlineEmailEl = $id('novaMarcacaoAgentEmail');
        if (inlineEmailEl) inlineEmailEl.textContent = agentInfo.email || '—';
        var agentLinkEl = $id('novaMarcacaoAgentLink');
        if (agentLinkEl) {
            var infoMap = agendaAgentInfo[String(agentId)];
            var href = (infoMap && infoMap.agentId) ? (agendaEquipaBaseUrl + '/' + infoMap.agentId) : '#';
            agentLinkEl.setAttribute('href', href);
        }
        var agentAvatarEl = $id('novaMarcacaoAgentAvatar');
        if (agentAvatarEl) {
            if (agentInfo.avatarUrl) {
                agentAvatarEl.src = agentInfo.avatarUrl;
                agentAvatarEl.style.display = 'block';
            } else {
                agentAvatarEl.style.display = 'none';
            }
        }
        var startD = new Date(startStr);
        var endD = new Date(endStr);
        if (window.novaMarcacaoDateFlatpickr) {
            var ymdStr = startD.getFullYear() + '-' + String(startD.getMonth() + 1).padStart(2, '0') + '-' + String(startD.getDate()).padStart(2, '0');
            window.novaMarcacaoDateFlatpickr.setDate(ymdStr, false);
        }
        var timeStr = String(startD.getHours()).padStart(2, '0') + ':' + String(startD.getMinutes()).padStart(2, '0');
        var min = startD.getMinutes();
        var m = Math.round(min / 15) * 15;
        if (m === 60) { m = 0; }
        var timeSlotForDropdown = String(startD.getHours()).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        $id('novaMarcacaoEditTitleDay').textContent = DAYS_LONG[startD.getDay()] + ', ' + startD.getDate() + ' ' + MONTHS_LONG[startD.getMonth()];
        $id('novaMarcacaoTimeToggle').textContent = timeStr;
        NovaMarcacao.populateTimeOptions(timeSlotForDropdown);
        $id('novaMarcacaoServicesList').innerHTML = '<div class="text-muted small">A carregar serviços...</div>';
        $id('novaMarcacaoServicesList').classList.remove('d-none');
        $id('novaMarcacaoServiceSelected').classList.add('d-none');
        novaMarcacaoServicesData = null;
        novaMarcacaoSelectedServices = [];
        fetch(agendaMembersServicesUrl + '/' + agentId + '/services', { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                novaMarcacaoServicesData = data;
                var html = '';
                (data.categories || []).forEach(function(cat) {
                    var count = (cat.services || []).length;
                    html += '<div class="nova-marcacao-services-category"><span>' + (cat.name || 'Outros') + '</span><span class="badge rounded-pill nova-marcacao-category-count ms-2">' + count + '</span></div>';
                    var color = cat.color || '#6c757d';
                    (cat.services || []).forEach(function(s) {
                        var sFormattedDur = s.formatted_duration || (s.duration || 60) + ' min';
                        var sFormattedPrice = s.formatted_price || '';
                        var sPrice = (s.price != null && s.price !== '') ? parseFloat(s.price) : 0;
                        html += '<div class="nova-marcacao-service-item" data-service-id="' + s.id + '" data-duration="' + (s.duration || 60) + '" data-name="' + (s.name || '').replace(/"/g, '&quot;') + '" data-price="' + sPrice + '" data-formatted-duration="' + (sFormattedDur || '').replace(/"/g, '&quot;') + '" data-formatted-price="' + (sFormattedPrice || '').replace(/"/g, '&quot;') + '" data-color="' + (color || '#6c757d').replace(/"/g, '&quot;') + '" style="border-left-color:' + color + '">';
                        html += '<div class="nova-marcacao-service-item-left"><div class="nova-marcacao-service-item-name">' + (s.name || '') + '</div><div class="nova-marcacao-service-item-duration">' + sFormattedDur + '</div></div>';
                        html += '<div class="nova-marcacao-service-item-price">' + sFormattedPrice + '</div></div>';
                    });
                });
                $id('novaMarcacaoServicesList').innerHTML = html || '<div class="text-muted small">Nenhum serviço disponível.</div>';
                $id('novaMarcacaoServicesList').querySelectorAll('.nova-marcacao-service-item').forEach(function(el) {
                    el.addEventListener('click', function() {
                        var sid = this.dataset.serviceId;
                        if (novaMarcacaoSelectedServices.some(function(s) { return String(s.service_id) === sid; })) return;
                        var dur = parseInt(this.dataset.duration, 10) || 60;
                        var priceNum = parseFloat(this.dataset.price) || 0;
                        var availableExtras = [];
                        (novaMarcacaoServicesData.categories || []).forEach(function(cat) {
                            (cat.services || []).forEach(function(svc) {
                                if (String(svc.id) === sid) availableExtras = svc.extras || [];
                            });
                        });
                        novaMarcacaoSelectedServices.push({
                            service_id: sid,
                            name: this.dataset.name || '',
                            duration: dur,
                            price: priceNum,
                            original_price: priceNum,
                            formatted_duration: this.dataset.formattedDuration || dur + ' min',
                            formatted_price: this.dataset.formattedPrice || (priceNum.toFixed(2).replace('.', ',') + ' €'),
                            color: this.dataset.color || '#6c757d',
                            available_extras: availableExtras,
                            extras: []
                        });
                        NovaMarcacao.renderSelectedServices();
                        NovaMarcacao.updateEndTimeAndTotal();
                        $id('novaMarcacaoServicesList').classList.add('d-none');
                        $id('novaMarcacaoCancelAddServicesBtn').classList.add('d-none');
                        $id('novaMarcacaoServiceSelected').classList.remove('d-none');
                    });
                });
            })
            .catch(function() {
                $id('novaMarcacaoServicesList').innerHTML = '<div class="text-danger small">Erro ao carregar serviços.</div>';
            });
        var modal = bootstrap.Modal.getOrCreateInstance($id('novaMarcacaoModal'));
        modal.show();

        // Ao abrir a nova marcação, esconder a linha de total enquanto não houver serviços selecionados
        var totalRow = $id('novaMarcacaoTotalPrice') ? $id('novaMarcacaoTotalPrice').closest('.nova-marcacao-total-row') : null;
        if (totalRow) {
            if (!novaMarcacaoSelectedServices || novaMarcacaoSelectedServices.length === 0) {
                totalRow.classList.add('d-none');
            } else {
                totalRow.classList.remove('d-none');
            }
        }
        if (preSelectedClientId) {
            fetch(agendaClientsUrl + '?client_id=' + encodeURIComponent(preSelectedClientId), { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(clients) {
                    if (clients && clients[0]) {
                        var c = clients[0];
                        novaMarcacaoSelectedClient = { id: String(c.id), name: c.name || '', phone: c.phone || '', avatar_url: c.avatar_url || '' };
                        $id('novaMarcacaoClientSelectedName').textContent = c.name || '';
                        $id('novaMarcacaoClientSelectedEmail').textContent = c.phone || '—';
                        var link = $id('novaMarcacaoClientProfileLink');
                        if (link) link.href = clientesBaseUrl + '/' + c.id;
                        if (c.avatar_url) {
                            $id('novaMarcacaoClientAvatar').src = c.avatar_url;
                            $id('novaMarcacaoClientAvatar').classList.remove('d-none');
                            $id('novaMarcacaoClientAvatarFallback').classList.add('d-none');
                        } else {
                            $id('novaMarcacaoClientAvatar').classList.add('d-none');
                            var initials = (c.name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
                            $id('novaMarcacaoClientAvatarFallback').textContent = initials;
                            $id('novaMarcacaoClientAvatarFallback').classList.remove('d-none');
                        }
                        $id('novaMarcacaoClientAddWrap').classList.add('d-none');
                        $id('novaMarcacaoClientSelected').classList.remove('d-none');
                        $id('novaMarcacaoClientSearchWrap').classList.add('d-none');
                    }
                });
        }
    }

    $id('novaMarcacaoClientSearch').addEventListener('input', (function() {
        var t;
        return function() {
            clearTimeout(t);
            var q = this.value.trim();
            if (q.length < 1) {
                $id('novaMarcacaoClientResults').innerHTML = '';
                return;
            }
            t = setTimeout(function() {
                $id('novaMarcacaoClientResults').innerHTML = '<div class="text-muted small">A pesquisar...</div>';
                fetch(agendaClientsUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(clients) {
                        if (!clients.length) {
                            $id('novaMarcacaoClientResults').innerHTML = '<div class="text-muted small">Nenhum cliente encontrado.</div>';
                            return;
                        }
                        var html = clients.map(function(c) {
                            var phone = c.phone || '';
                            var label = (c.name || '');
                            if (phone) {
                                label += ' <small class="text-muted">(' + phone + ')</small>';
                            }
                            var dataAttrs = 'data-id="' + c.id + '" data-name="' + (c.name || '').replace(/"/g, '&quot;') + '" data-phone="' + (phone || '').replace(/"/g, '&quot;') + '" data-avatar="' + (c.avatar_url || '').replace(/"/g, '&quot;') + '"';
                            return '<div class="nova-marcacao-client-item" ' + dataAttrs + '>' + label + '</div>';
                        }).join('');
                        $id('novaMarcacaoClientResults').innerHTML = html;
                        $id('novaMarcacaoClientResults').querySelectorAll('.nova-marcacao-client-item').forEach(function(el) {
                            el.addEventListener('click', function() {
                                var name = this.dataset.name || '';
                                var phone = this.dataset.phone || '';
                                var avatarUrl = this.dataset.avatar || '';
                                novaMarcacaoSelectedClient = { id: this.dataset.id, name: name, phone: phone, avatar_url: avatarUrl };
                                $id('novaMarcacaoClientSelectedName').textContent = name;
                                $id('novaMarcacaoClientSelectedEmail').textContent = phone || '—';
                                var link = $id('novaMarcacaoClientProfileLink');
                                if (link) link.href = clientesBaseUrl + '/' + this.dataset.id;
                                if (avatarUrl) {
                                    $id('novaMarcacaoClientAvatar').src = avatarUrl;
                                    $id('novaMarcacaoClientAvatar').classList.remove('d-none');
                                    $id('novaMarcacaoClientAvatarFallback').classList.add('d-none');
                                } else {
                                    $id('novaMarcacaoClientAvatar').classList.add('d-none');
                                    var initials = (name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
                                    $id('novaMarcacaoClientAvatarFallback').textContent = initials;
                                    $id('novaMarcacaoClientAvatarFallback').classList.remove('d-none');
                                }
                                $id('novaMarcacaoClientAddWrap').classList.add('d-none');
                                $id('novaMarcacaoClientSelected').classList.remove('d-none');
                                $id('novaMarcacaoClientSearchWrap').classList.add('d-none');
                                $id('novaMarcacaoClientResults').innerHTML = '';
                                $id('novaMarcacaoClientSearch').value = '';
                                window._novaMarcacaoPreviousClient = null;
                            });
                        });
                    })
                    .catch(function() {
                        $id('novaMarcacaoClientResults').innerHTML = '<div class="text-danger small">Erro ao pesquisar.</div>';
                    });
            }, 300);
        };
    })());

    $id('novaMarcacaoClientClear').addEventListener('click', function() {
        var prev = novaMarcacaoSelectedClient ? { id: novaMarcacaoSelectedClient.id, name: novaMarcacaoSelectedClient.name, phone: novaMarcacaoSelectedClient.phone || '', avatar_url: novaMarcacaoSelectedClient.avatar_url || '' } : null;
        novaMarcacaoSelectedClient = null;
        $id('novaMarcacaoClientSelected').classList.add('d-none');
        $id('novaMarcacaoClientAddWrap').classList.remove('d-none');
        $id('novaMarcacaoClientSearchWrap').classList.remove('d-none');
        $id('novaMarcacaoClientResults').innerHTML = '';
        $id('novaMarcacaoCreateClientBtn').classList.remove('d-none');
        if (prev) {
            window._novaMarcacaoPreviousClient = prev;
            $id('novaMarcacaoClientCancelBtn').classList.remove('d-none');
        }
    });
    function openCreateClientQuickMenu(context, evt) {
        var popup = $id('agendaCreateClientQuickMenu');
        if (!popup) return;
        var modalEl = context === 'novaMarcacao' ? $id('novaMarcacaoModal') : $id('eventDetailEditModal');
        var modalContent = modalEl?.querySelector('.modal-content');
        if (modalContent) modalContent.appendChild(popup);
        function hide() {
            popup.classList.remove('is-open');
            popup.innerHTML = '';
            document.removeEventListener('click', ch);
            document.removeEventListener('keydown', eh);
        }
        function ch(e) { if (popup.contains(e.target)) return; hide(); }
        function eh(e) { if (e.key === 'Escape') { e.stopPropagation(); hide(); } }
        popup.innerHTML = '<div class="create-client-header">' +
            '<h6>Novo cliente</h6>' +
            '<button type="button" class="create-client-close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button>' +
            '</div>' +
            '<div class="create-client-body">' +
            '<div class="mb-2"><label class="form-label small">Nome <span class="text-danger">*</span></label>' +
            '<input type="text" class="form-control form-control-sm create-client-name" placeholder="Nome do cliente" required></div>' +
            '<div class="mb-2"><label class="form-label small">Email <span class="text-danger">*</span></label>' +
            '<input type="email" class="form-control form-control-sm create-client-email" placeholder="email@exemplo.pt" required></div>' +
            '<div class="mb-0"><label class="form-label small">Telefone</label>' +
            '<input type="tel" class="form-control form-control-sm create-client-phone" placeholder="+351 912 345 678"></div>' +
            '</div>' +
            '<div class="create-client-footer">' +
            '<button type="button" class="btn btn-light btn-sm create-client-cancel">Cancelar</button>' +
            '<button type="button" class="btn btn-primary btn-sm create-client-submit">Criar</button>' +
            '</div>';
        popup.classList.add('is-open');
        // Posicionar logo abaixo do link "criar novo cliente", centrado em relação ao link
        try {
            var triggerEl = evt.currentTarget || evt.target;
            if (triggerEl && modalContent) {
                var triggerRect = triggerEl.getBoundingClientRect();
                var containerRect = modalContent.getBoundingClientRect();
                var popupRect = popup.getBoundingClientRect();
                var gapY = 4;
                // centro do link dentro do conteúdo do modal
                var triggerCenter = (triggerRect.left + triggerRect.right) / 2 - containerRect.left;
                var left = triggerCenter - popupRect.width / 2;
                var top = triggerRect.bottom - containerRect.top + gapY;
                // clamp dentro do conteúdo do modal
                var minLeft = 8;
                var maxLeft = containerRect.width - popupRect.width - 8;
                if (!isNaN(maxLeft)) {
                    if (left < minLeft) left = minLeft;
                    if (left > maxLeft) left = maxLeft;
                }
                popup.style.left = left + 'px';
                popup.style.top = top + 'px';
            }
        } catch (e) {
            // fallback silencioso se algo correr mal no cálculo
        }
        popup.querySelector('.create-client-close').addEventListener('click', hide);
        popup.querySelector('.create-client-cancel').addEventListener('click', hide);
        popup.querySelector('.create-client-submit').addEventListener('click', function() {
            var name = popup.querySelector('.create-client-name').value.trim();
            var email = popup.querySelector('.create-client-email').value.trim();
            var phone = popup.querySelector('.create-client-phone').value.trim();
            if (!name || !email) {
                showToast('Preencha nome e email.', 'error');
                return;
            }
            var btn = popup.querySelector('.create-client-submit');
            btn.disabled = true;
            btn.textContent = 'A criar...';
            fetch(agendaClientsUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ name: name, email: email, phone: phone || null })
            })
            .then(function(r) { return r.json().then(function(data) {
                if (!r.ok) {
                    var msg = 'Erro ao criar cliente.';
                    if (data.errors && data.errors.email) msg = 'O email inserido já existe associado a um cliente.';
                    else if (data.message) msg = data.message;
                    throw new Error(msg);
                }
                return data;
            }); })
            .then(function(client) {
                hide();
                var c = { id: String(client.id), name: client.name || name, phone: client.phone || '', avatar_url: client.avatar_url || '' };
                if (context === 'novaMarcacao') {
                    novaMarcacaoSelectedClient = c;
                    $id('novaMarcacaoClientSelectedName').textContent = c.name;
                    $id('novaMarcacaoClientSelectedEmail').textContent = c.phone || '—';
                    var link = $id('novaMarcacaoClientProfileLink');
                    if (link) link.href = clientesBaseUrl + '/' + c.id;
                    $id('novaMarcacaoClientAvatar').classList.add('d-none');
                    var initials = (c.name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
                    $id('novaMarcacaoClientAvatarFallback').textContent = initials;
                    $id('novaMarcacaoClientAvatarFallback').classList.remove('d-none');
                    $id('novaMarcacaoClientAddWrap').classList.add('d-none');
                    $id('novaMarcacaoClientSelected').classList.remove('d-none');
                    $id('novaMarcacaoClientSearchWrap').classList.add('d-none');
                    $id('novaMarcacaoClientResults').innerHTML = '';
                    $id('novaMarcacaoClientSearch').value = '';
                    $id('novaMarcacaoClientCancelBtn').classList.add('d-none');
                    $id('novaMarcacaoCreateClientBtn').classList.add('d-none');
                    window._novaMarcacaoPreviousClient = null;
                } else {
                    eventDetailSelectedClient = c;
                    $id('eventDetailClientSelectedName').textContent = c.name;
                    $id('eventDetailClientSelectedEmail').textContent = c.phone || '—';
                    var link = $id('eventDetailClientProfileLink');
                    if (link) link.href = clientesBaseUrl + '/' + c.id;
                    if (c.avatar_url) {
                        $id('eventDetailClientAvatar').src = c.avatar_url;
                        $id('eventDetailClientAvatar').classList.remove('d-none');
                        $id('eventDetailClientAvatarFallback').classList.add('d-none');
                    } else {
                        $id('eventDetailClientAvatar').classList.add('d-none');
                        var inits = (c.name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
                        $id('eventDetailClientAvatarFallback').textContent = inits;
                        $id('eventDetailClientAvatarFallback').classList.remove('d-none');
                    }
                    $id('eventDetailClientAddWrap').classList.add('d-none');
                    $id('eventDetailClientSelected').classList.remove('d-none');
                    $id('eventDetailClientSearchWrap').classList.add('d-none');
                    $id('eventDetailClientResults').innerHTML = '';
                    $id('eventDetailClientSearch').value = '';
                    $id('eventDetailClientCancelBtn').classList.add('d-none');
                    $id('eventDetailCreateClientBtn').classList.add('d-none');
                    window._eventDetailPreviousClient = null;
                }
                showToast('Cliente criado com sucesso.', 'success');
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.textContent = 'Criar';
                showToast(err.message || 'Erro ao criar cliente.', 'error');
            });
        });
        requestAnimationFrame(function() {
            popup.classList.add('is-open');
            var btnEl = (evt && evt.target) ? evt.target.closest('button') : null;
            var rect = btnEl ? btnEl.getBoundingClientRect() : { left: 200, bottom: 200 };
            var offset = 8;
            var container = modalContent && modalContent.parentElement ? modalContent.parentElement.getBoundingClientRect() : null;
            var left, top;
            if (container) {
                left = (rect.left || 200) - container.left;
                top = (rect.bottom !== undefined ? rect.bottom : 200) + offset - container.top;
            } else {
                left = rect.left || 200;
                top = (rect.bottom !== undefined ? rect.bottom : 200) + offset;
            }
            var popupRect = popup.getBoundingClientRect();
            var vw = container ? container.width : window.innerWidth;
            var vh = container ? container.height : window.innerHeight;
            var maxLeft = vw - popupRect.width - offset;
            var maxTop = vh - popupRect.height - offset;
            left = Math.max(offset, Math.min(left, maxLeft));
            top = Math.max(offset, Math.min(top, maxTop));
            popup.style.left = left + 'px';
            popup.style.top = top + 'px';
            document.addEventListener('click', ch);
            document.addEventListener('keydown', eh);
            popup.querySelector('.create-client-name').focus();
        });
    }
    $id('novaMarcacaoCreateClientBtn').addEventListener('click', function(e) {
        e.preventDefault();
        openCreateClientQuickMenu('novaMarcacao', e);
    });
    $id('eventDetailCreateClientBtn').addEventListener('click', function(e) {
        e.preventDefault();
        openCreateClientQuickMenu('eventDetail', e);
    });

    $id('novaMarcacaoClientCancelBtn').addEventListener('click', function() {
        var prev = window._novaMarcacaoPreviousClient;
        if (prev) {
            novaMarcacaoSelectedClient = prev;
            $id('novaMarcacaoClientSelectedName').textContent = prev.name;
            $id('novaMarcacaoClientSelectedEmail').textContent = prev.phone || '—';
            var link = $id('novaMarcacaoClientProfileLink');
            if (link) link.href = clientesBaseUrl + '/' + prev.id;
            if (prev.avatar_url) {
                $id('novaMarcacaoClientAvatar').src = prev.avatar_url;
                $id('novaMarcacaoClientAvatar').classList.remove('d-none');
                $id('novaMarcacaoClientAvatarFallback').classList.add('d-none');
            } else {
                $id('novaMarcacaoClientAvatar').classList.add('d-none');
                var initials = (prev.name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
                $id('novaMarcacaoClientAvatarFallback').textContent = initials;
                $id('novaMarcacaoClientAvatarFallback').classList.remove('d-none');
            }
            $id('novaMarcacaoClientAddWrap').classList.add('d-none');
            $id('novaMarcacaoClientSelected').classList.remove('d-none');
            $id('novaMarcacaoClientSearchWrap').classList.add('d-none');
            $id('novaMarcacaoClientResults').innerHTML = '';
            $id('novaMarcacaoClientSearch').value = '';
            $id('novaMarcacaoCreateClientBtn').classList.add('d-none');
            this.classList.add('d-none');
            window._novaMarcacaoPreviousClient = null;
        }
    });

    $id('novaMarcacaoAddMoreServicesBtn').addEventListener('click', function() {
        $id('novaMarcacaoServiceSelected').classList.add('d-none');
        $id('novaMarcacaoCancelAddServicesBtn').classList.remove('d-none');
        $id('novaMarcacaoServicesList').classList.remove('d-none');
        var totalRow = $id('novaMarcacaoTotalPrice') ? $id('novaMarcacaoTotalPrice').closest('.nova-marcacao-total-row') : null;
        if (totalRow) totalRow.classList.add('d-none');
    });

    $id('novaMarcacaoCancelAddServicesBtn').addEventListener('click', function() {
        $id('novaMarcacaoServicesList').classList.add('d-none');
        $id('novaMarcacaoCancelAddServicesBtn').classList.add('d-none');
        $id('novaMarcacaoServiceSelected').classList.remove('d-none');
        var totalRow = $id('novaMarcacaoTotalPrice') ? $id('novaMarcacaoTotalPrice').closest('.nova-marcacao-total-row') : null;
        if (totalRow) totalRow.classList.remove('d-none');
    });

    $id('eventDetailAddMoreServicesBtn').addEventListener('click', function() {
        $id('eventDetailServiceSelected').classList.add('d-none');
        $id('eventDetailCancelAddServicesBtn').classList.remove('d-none');
        $id('eventDetailServicesList').classList.remove('d-none');
        var totalRow = $id('eventDetailTotalPrice') ? $id('eventDetailTotalPrice').closest('.nova-marcacao-total-row') : null;
        if (totalRow) totalRow.classList.add('d-none');
    });

    $id('eventDetailCancelAddServicesBtn').addEventListener('click', function() {
        $id('eventDetailServicesList').classList.add('d-none');
        $id('eventDetailCancelAddServicesBtn').classList.add('d-none');
        $id('eventDetailServiceSelected').classList.remove('d-none');
        var totalRow = $id('eventDetailTotalPrice') ? $id('eventDetailTotalPrice').closest('.nova-marcacao-total-row') : null;
        if (totalRow) totalRow.classList.remove('d-none');
    });

    $id('eventDetailClientClear').addEventListener('click', function() {
        // Guardar o cliente atual para poder reverter se o utilizador cancelar
        var prev = eventDetailSelectedClient ? {
            id: eventDetailSelectedClient.id,
            name: eventDetailSelectedClient.name,
            phone: eventDetailSelectedClient.phone || '',
            avatar_url: eventDetailSelectedClient.avatar_url || ''
        } : null;

        // Esconder o cartão atual e voltar ao estado "Cliente" + pesquisa
        eventDetailSelectedClient = null;
        $id('eventDetailClientSelected').classList.add('d-none');
        $id('eventDetailClientAddWrap').classList.remove('d-none');
        $id('eventDetailClientSearchWrap').classList.remove('d-none');
        $id('eventDetailClientResults').classList.remove('d-none');
        $id('eventDetailClientSearch').value = '';
        $id('eventDetailClientSearch').focus();
        $id('eventDetailClientResults').innerHTML = '';
        $id('eventDetailCreateClientBtn').classList.remove('d-none');

        if (prev) {
            window._eventDetailPreviousClient = prev;
            $id('eventDetailClientCancelBtn').classList.remove('d-none');
        }
    });
    $id('eventDetailClientCancelBtn').addEventListener('click', function() {
        var prev = window._eventDetailPreviousClient;
        if (prev) {
            eventDetailSelectedClient = prev;
            $id('eventDetailClientSelectedName').textContent = prev.name;
            $id('eventDetailClientSelectedEmail').textContent = prev.phone || '—';
            var link = $id('eventDetailClientProfileLink');
            if (link) link.href = clientesBaseUrl + '/' + prev.id;
            if (prev.avatar_url) {
                $id('eventDetailClientAvatar').src = prev.avatar_url;
                $id('eventDetailClientAvatar').classList.remove('d-none');
                $id('eventDetailClientAvatarFallback').classList.add('d-none');
            } else {
                $id('eventDetailClientAvatar').classList.add('d-none');
                var initials = (prev.name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
                $id('eventDetailClientAvatarFallback').textContent = initials;
                $id('eventDetailClientAvatarFallback').classList.remove('d-none');
            }
            $id('eventDetailClientAddWrap').classList.add('d-none');
            $id('eventDetailClientSelected').classList.remove('d-none');
            $id('eventDetailClientSearchWrap').classList.add('d-none');
            $id('eventDetailClientResults').classList.add('d-none');
            $id('eventDetailClientSearch').value = '';
            $id('eventDetailClientResults').innerHTML = '';
            $id('eventDetailCreateClientBtn').classList.add('d-none');
            this.classList.add('d-none');
            window._eventDetailPreviousClient = null;
        }
    });

    $id('eventDetailClientSearch').addEventListener('input', (function() {
        var t;
        return function() {
            clearTimeout(t);
            var q = this.value.trim();
            if (q.length < 1) {
                $id('eventDetailClientResults').innerHTML = '';
                return;
            }
            t = setTimeout(function() {
                $id('eventDetailClientResults').innerHTML = '<div class="text-muted small">A pesquisar...</div>';
                fetch(agendaClientsUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(clients) {
                        if (!clients.length) {
                            $id('eventDetailClientResults').innerHTML = '<div class="text-muted small">Nenhum cliente encontrado.</div>';
                            return;
                        }
                        var html = clients.map(function(c) {
                            var phone = c.phone || '';
                            var label = (c.name || '');
                            if (phone) {
                                label += ' <small class="text-muted">(' + phone + ')</small>';
                            }
                            var dataAttrs = 'data-id="' + c.id + '" data-name="' + (c.name || '').replace(/"/g, '&quot;') + '" data-phone="' + (phone || '').replace(/"/g, '&quot;') + '" data-avatar="' + (c.avatar_url || '').replace(/"/g, '&quot;') + '"';
                            return '<div class="nova-marcacao-client-item event-detail-client-item" ' + dataAttrs + '>' + label + '</div>';
                        }).join('');
                        $id('eventDetailClientResults').innerHTML = html;
                        $id('eventDetailClientResults').querySelectorAll('.event-detail-client-item').forEach(function(el) {
                            el.addEventListener('click', function() {
                                var name = this.dataset.name || '';
                                var phone = this.dataset.phone || '';
                                var avatarUrl = this.dataset.avatar || '';
                                eventDetailSelectedClient = { id: this.dataset.id, name: name, phone: phone, avatar_url: avatarUrl };
                                $id('eventDetailClientSelectedName').textContent = name;
                                $id('eventDetailClientSelectedEmail').textContent = phone || '—';
                                var link = $id('eventDetailClientProfileLink');
                                if (link) link.href = clientesBaseUrl + '/' + this.dataset.id;
                                if (avatarUrl) {
                                    $id('eventDetailClientAvatar').src = avatarUrl;
                                    $id('eventDetailClientAvatar').classList.remove('d-none');
                                    $id('eventDetailClientAvatarFallback').classList.add('d-none');
                                } else {
                                    $id('eventDetailClientAvatar').classList.add('d-none');
                                    var initials = (name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
                                    $id('eventDetailClientAvatarFallback').textContent = initials;
                                    $id('eventDetailClientAvatarFallback').classList.remove('d-none');
                                }
                                $id('eventDetailClientAddWrap').classList.add('d-none');
                                $id('eventDetailClientSelected').classList.remove('d-none');
                                $id('eventDetailClientSearchWrap').classList.add('d-none');
                                $id('eventDetailClientResults').innerHTML = '';
                                $id('eventDetailClientSearch').value = '';
                                $id('eventDetailClientCancelBtn').classList.add('d-none');
                                $id('eventDetailCreateClientBtn').classList.add('d-none');
                                window._eventDetailPreviousClient = null;
                            });
                        });
                    });
            }, 300);
        };
    })());

    $id('eventDetailEditForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var id = $id('eventDetailEditId').value;
        var title = eventDetailCurrentData?.title || '';
        if (eventDetailCurrentData?.event_type === 'marcacao' && eventDetailSelectedServices.length > 0) {
            var clientName = (eventDetailSelectedClient && eventDetailSelectedClient.name) || eventDetailCurrentData.client_name || '';
            var serviceNames = eventDetailSelectedServices.map(function(s) { return s.name; }).join(', ');
            title = (clientName || 'Cliente') + ' - ' + serviceNames;
        }
        var totalDur = eventDetailSelectedServices.reduce(function(sum, s) {
            var d = (s.duration || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (e.duration || 0); }, 0);
            return sum + d;
        }, 0);
        var startStr = $id('eventDetailEditStart').value;
        var endStr = startStr;
        if (totalDur > 0 && startStr) {
            var start = new Date(startStr);
            var end = new Date(start.getTime() + totalDur * 60 * 1000);
            endStr = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0') + 'T' + String(end.getHours()).padStart(2, '0') + ':' + String(end.getMinutes()).padStart(2, '0');
        }
        var payload = {
            title: title,
            start_at: startStr,
            end_at: endStr,
            description: $id('eventDetailObservacoes').value,
            status: $id('eventDetailStatus').value
        };
        if (eventDetailCurrentData?.event_type === 'marcacao') {
            payload.client_id = eventDetailSelectedClient ? eventDetailSelectedClient.id : null;
            payload.services = eventDetailSelectedServices.map(function(s) {
                return {
                    service_id: s.service_id,
                    duration: s.duration,
                    price: s.price,
                    original_price: s.original_price != null ? s.original_price : s.price,
                    extras: (s.extras || []).map(function(e) { return { extra_id: e.id, duration: e.duration || 0, price: e.price || 0 }; })
                };
            });
        }
        var btn = $id('eventDetailSaveBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>A guardar...';
        fetch((C.urlEvents || '') + '/' + id, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        })
        .then(function(r) {
            return r.json().then(function(res) {
                if (!r.ok) {
                    var msg = res.message || (res.errors ? Object.values(res.errors).flat().join(' ') : null) || 'Erro ao guardar.';
                    throw new Error(msg);
                }
                return res;
            });
        })
        .then(function(res) {
            btn.disabled = false;
            btn.innerHTML = 'Guardar';
            if (res.success && res.event) {
                eventDetailWasSaved = true;
                var ev = calendar.getEventById(id);
                if (ev) {
                    ev.setProp('title', res.event.title);
                    ev.setStart(res.event.start);
                    ev.setEnd(res.event.end);
                    var ep = res.event.extendedProps || {};
                    Object.keys(ep).forEach(function(k) { ev.setExtendedProp(k, ep[k]); });
                }
                bootstrap.Modal.getInstance($id('eventDetailEditModal')).hide();
            } else {
                showToast(res.message || 'Erro ao guardar.', 'error');
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.innerHTML = 'Guardar';
            var msg = (err && err.message && err.message.indexOf('Unexpected') === -1) ? err.message : 'Erro de ligação. Verifique os logs do servidor se o problema persistir.';
            showToast(msg, 'error');
        });
    });

    function paymentModalSubtotal() {
        return eventDetailSelectedServices.reduce(function(sum, s) {
            var p = (parseFloat(s.price) || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (parseFloat(e.price) || 0); }, 0);
            return sum + p;
        }, 0);
    }

    function paymentModalUpdateTotals() {
        var sub = paymentModalSubtotal();
        var gorjeta = parseFloat($id('paymentGorjeta').value) || 0;
        if (gorjeta < 0) gorjeta = 0;
        $id('paymentSubtotalDisplay').textContent = sub.toFixed(2).replace('.', ',') + ' €';
        $id('paymentGorjetaDisplay').textContent = gorjeta.toFixed(2).replace('.', ',') + ' €';
        var gorjetaLine = $id('paymentGorjetaLine');
        if (gorjetaLine) gorjetaLine.classList.toggle('d-none', gorjeta <= 0);
        $id('paymentTotalDisplay').textContent = (sub + gorjeta).toFixed(2).replace('.', ',') + ' €';
    }

    $id('eventDetailPaymentBtn').addEventListener('click', function() {
        var sub = paymentModalSubtotal();
        $id('paymentSubtotalDisplay').textContent = sub.toFixed(2).replace('.', ',') + ' €';
        $id('paymentGorjeta').value = '0';
        paymentModalUpdateTotals();
        var cards = $$('#paymentMethodToggleGroup .payment-method-card');
        cards.forEach(function(card, i) {
            card.classList.toggle('active', i === 0);
        });
        var firstCard = cards[0];
        $id('paymentMethodValue').value = firstCard ? (firstCard.dataset.method || 'dinheiro') : 'dinheiro';
        bootstrap.Modal.getOrCreateInstance($id('paymentModal')).show();
    });

    $('#paymentMethodToggleGroup').addEventListener('click', function(e) {
        var card = e.target.closest('.payment-method-card');
        if (!card) return;
        $$('#paymentMethodToggleGroup .payment-method-card').forEach(function(c) { c.classList.remove('active'); });
        card.classList.add('active');
        $id('paymentMethodValue').value = card.dataset.method || '';
    });

    $id('paymentGorjeta').addEventListener('input', paymentModalUpdateTotals);

    $id('paymentConfirmBtn').addEventListener('click', function() {
        var method = $id('paymentMethodValue').value;
        if (!method) {
            showToast('Selecione um meio de pagamento.', 'error');
            return;
        }
        var gorjeta = parseFloat($id('paymentGorjeta').value) || 0;
        if (gorjeta < 0) {
            showToast('Gorjeta inválida.', 'error');
            return;
        }
        var eventId = $id('eventDetailEditId').value;
        if (!eventId) {
            showToast('Evento não encontrado.', 'error');
            return;
        }
        var items = [];
        eventDetailSelectedServices.forEach(function(s) {
            items.push({ tipo: 'servico', descricao: s.name || 'Serviço', quantidade: 1, preco_unitario: parseFloat(s.price) || 0, service_id: s.service_id });
            (s.extras || []).forEach(function(e) {
                items.push({ tipo: 'extra', descricao: '+ ' + (e.name || 'Extra'), quantidade: 1, preco_unitario: parseFloat(e.price) || 0, extra_id: e.id });
            });
        });
        if (items.length === 0) {
            showToast('Nenhum serviço para faturar.', 'error');
            return;
        }
        var btn = $id('paymentConfirmBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>A faturar...';
        fetch(C.agendaCheckoutStoreUrl || '', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ event_id: eventId, payment_method: method, gorjeta: gorjeta, items: items })
        })
        .then(function(r) { return r.json().then(function(res) { return { ok: r.ok, res: res }; }); })
        .then(function(_) {
            var ok = _.ok;
            var res = _.res;
            btn.disabled = false;
            btn.innerHTML = 'Confirmar e faturar';
            if (!ok) {
                showToast(res.error || res.message || 'Erro ao faturar.', 'error');
                return;
            }
            bootstrap.Modal.getInstance($id('paymentModal')).hide();
            showToast('Venda registada com sucesso.', 'success');
            if (typeof calendar !== 'undefined') {
                calendar.refetchEvents();
            }
            eventDetailModalLoading = true;
            fetch((C.urlEvents || '') + '/' + eventId, { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                populateEventDetailEditModal(data);
                setEventDetailPaymentAndReadOnly(data.existing_sale || null, 'marcacao', eventDetailSelectedServices.length);
            })
            .finally(function() { eventDetailModalLoading = false; });
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = 'Confirmar e faturar';
            showToast('Erro de ligação.', 'error');
        });
    });

    $id('paymentCancelBtn').addEventListener('click', function() { bootstrap.Modal.getInstance($id('paymentModal'))?.hide(); });
    $id('paymentModalCloseBtn').addEventListener('click', function() { bootstrap.Modal.getInstance($id('paymentModal'))?.hide(); });

    $id('paymentModal').addEventListener('show.bs.modal', function() {
        var pm = $id('paymentModal');
        if (pm) pm.style.zIndex = '1065';
        function raiseBackdrop() {
            var backdrops = document.querySelectorAll('.modal-backdrop');
            if (backdrops.length >= 2) {
                var last = backdrops[backdrops.length - 1];
                last.style.zIndex = '1060';
                last.style.transition = 'none';
                return;
            }
            requestAnimationFrame(raiseBackdrop);
        }
        requestAnimationFrame(raiseBackdrop);
    });
    $id('paymentModal').addEventListener('shown.bs.modal', function() {
        var backdrops = document.querySelectorAll('.modal-backdrop');
        if (backdrops.length > 0) {
            backdrops[backdrops.length - 1].style.zIndex = '1060';
        }
        var pm = $id('paymentModal');
        if (pm) pm.style.zIndex = '1065';
    });

    $id('eventDetailReverterFaturaBtn').addEventListener('click', function() {
        var saleId = this.dataset.saleId;
        if (!saleId) return;
        var revertUrl = (C.salesRevertUrl || '').replace(/\/$/, '') + '/' + saleId + '/revert';
        this.disabled = true;
        fetch(revertUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            $id('eventDetailReverterFaturaBtn').disabled = false;
            if (!res.success) {
                showToast(res.error || res.message || 'Erro ao reverter.', 'error');
                return;
            }
            showToast(res.message || 'Venda anulada.', 'success');
            if (typeof calendar !== 'undefined') {
                calendar.refetchEvents();
            }
            var eventId = $id('eventDetailEditId').value;
            if (!eventId) return;
            eventDetailModalLoading = true;
            fetch((C.urlEvents || '') + '/' + eventId, { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                populateEventDetailEditModal(data);
            })
            .finally(function() { eventDetailModalLoading = false; });
        })
        .catch(function() {
            $id('eventDetailReverterFaturaBtn').disabled = false;
            showToast('Erro de ligação.', 'error');
        });
    });

    $id('novaMarcacaoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!novaMarcacaoSelectedServices.length) {
            showToast('Selecione pelo menos um serviço.', 'error');
            return;
        }
        if (!novaMarcacaoSelectedClient || !novaMarcacaoSelectedClient.name) {
            showToast('Selecione um cliente.', 'error');
            return;
        }
        var serviceNames = novaMarcacaoSelectedServices.map(function(s) { return s.name; }).join(', ');
        var title = novaMarcacaoSelectedClient.name + ' - ' + serviceNames;
        var btn = $id('novaMarcacaoSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>A guardar...';
        var servicesPayload = novaMarcacaoSelectedServices.map(function(s) {
            return {
                service_id: s.service_id,
                duration: s.duration,
                price: s.price,
                original_price: s.original_price != null ? s.original_price : s.price,
                extras: (s.extras || []).map(function(e) { return { extra_id: e.id, duration: e.duration || 0, price: e.price || 0 }; })
            };
        });
        var payload = {
            title: title,
            start_at: $id('novaMarcacaoStart').value,
            end_at: $id('novaMarcacaoEnd').value,
            description: $id('novaMarcacaoObservacoes').value,
            event_type: 'marcacao',
            user_id: $id('novaMarcacaoAgentId').value,
            client_id: novaMarcacaoSelectedClient ? novaMarcacaoSelectedClient.id : null,
            services: servicesPayload
        };
        var csrf = C.csrf || '';
        fetch((C.urlEvents || ''), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        })
        .then(function(r) {
            return r.json().then(function(res) {
                if (!r.ok) {
                    var msg = res.message || (res.errors ? Object.values(res.errors).flat().join(' ') : null) || 'Erro ao criar marcação.';
                    throw new Error(msg);
                }
                return res;
            });
        })
        .then(function(res) {
            btn.disabled = false;
            btn.textContent = 'Criar marcação';
            if (res.success && res.event) {
                /* refetch em vez de addEvent: evita duplicar com o mesmo evento vindo do fetch (ex.: mudar filtro de equipa) */
                calendar.refetchEvents();
                bootstrap.Modal.getInstance($id('novaMarcacaoModal')).hide();
            } else {
                showToast(res.message || 'Erro ao criar marcação.', 'error');
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.textContent = 'Criar marcação';
            var msg = (err && err.message && err.message.indexOf('Unexpected') === -1) ? err.message : 'Erro de ligação. Verifique os logs do servidor se o problema persistir.';
            showToast(msg, 'error');
        });
    });

    $id('novaMarcacaoModal').addEventListener('hidden.bs.modal', function() {
        novaMarcacaoSelectedClient = null;
        novaMarcacaoServicesData = null;
        novaMarcacaoSelectedServices = [];
        $id('novaMarcacaoTotalPrice').textContent = '0,00 €';
        window._novaMarcacaoPreviousClient = null;
        $id('novaMarcacaoClientCancelBtn').classList.add('d-none');
        $id('novaMarcacaoClientAddWrap').classList.remove('d-none');
        $id('novaMarcacaoClientSearchWrap').classList.add('d-none');
        $id('novaMarcacaoClientSelected').classList.add('d-none');
    });

    function ensureAgendaSlot24hToggle() {
        var leftChunk = calendarEl.querySelector('.fc-header-toolbar .fc-toolbar-chunk');
        if (!leftChunk) return;
        var existing = leftChunk.querySelector('#agendaSlot24hToggle');
        if (existing) {
            if (existing.checked !== agendaSlot24hEnabled) {
                existing.checked = agendaSlot24hEnabled;
            }
            return;
        }
        var wrap = document.createElement('div');
        wrap.className = 'form-check form-switch agenda-slot-24h-toggle d-inline-flex align-items-center ms-2 ms-md-3';
        wrap.innerHTML = '<input class="form-check-input flex-shrink-0" type="checkbox" id="agendaSlot24hToggle" role="switch" aria-label="Mostrar grelha 24 horas">' +
            '<label class="form-check-label small text-nowrap ms-2 mb-0" for="agendaSlot24hToggle">24&nbsp;h</label>';
        leftChunk.appendChild(wrap);
        var input = wrap.querySelector('#agendaSlot24hToggle');
        input.checked = agendaSlot24hEnabled;
        input.addEventListener('change', function() {
            agendaSlot24hEnabled = input.checked;
            var r = getAgendaSlotRange(agendaSlot24hEnabled);
            calendar.setOption('slotMinTime', r.min);
            calendar.setOption('slotMaxTime', r.max);
            try {
                localStorage.setItem(AGENDA_SLOT_STORAGE_KEY, agendaSlot24hEnabled ? '1' : '0');
            } catch (e) {}
            var vt = calendar.view.type;
            if (vt.indexOf('timeGrid') !== -1 || vt.indexOf('resourceTimeGrid') !== -1) {
                setTimeout(function() {
                    var now = new Date();
                    var currentTime = now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0') + ':00';
                    calendar.scrollToTime(currentTime);
                }, 50);
            }
        });
    }

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'resourceTimeGridDay',
        locale: 'pt',
        editable: true,
        customButtons: {
            currentDate: {
                text: '',
                click: function() {
                    // Botão apenas informativo (título), não faz nada
                }
            },
            viewSelector: {
                text: 'Dia',
                click: function() {
                    // Dropdown será inicializado após render
                }
            },
            consultantFilter: {
                text: 'Toda a equipa',
                click: function() {
                    // Dropdown será inicializado após render (só visível na vista Dia)
                }
            },
            refreshAgenda: {
                text: '',
                click: function() {
                    if (typeof calendar !== 'undefined') {
                        calendar.refetchEvents();
                    }
                }
            },
            adicionarDropdown: {
                text: 'Adicionar',
                click: function() {
                    // Dropdown será inicializado após render
                }
            }
        },
        headerToolbar: {
            left: 'today prev currentDate next refreshAgenda',
            center: '',
            right: 'consultantFilter viewSelector adicionarDropdown'
        },
        buttonText: {
            today: 'Hoje',
            month: 'Mês',
            week: 'Semana',
            resourceTimeGridDay: 'Dia',
            timeGridThreeDay: '3 dias',
            prev: '',
            next: ''
        },
        views: {
            timeGridThreeDay: {
                type: 'timeGridWeek',
                duration: { days: 3 }
            }
        },
        // Horário da grelha: 9h–19h ou 24h conforme toggle (futuro: config. da loja)
        slotMinTime: initialAgendaSlots.min,
        slotMaxTime: initialAgendaSlots.max,
        slotDuration: '00:15:00',
        slotLabelInterval: '01:00',
        allDaySlot: false,
        nowIndicator: true,
        scrollTime: new Date().toTimeString().slice(0, 5) + ':00',
        scrollTimeReset: false,
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        /* Horário/título já vão no eventContent; sem isto o TimeGrid mostra .fc-event-time por cima e parece duplicado */
        displayEventTime: false,
        slotLaneDidMount: function(arg) {
            if (arg.el && arg.date) arg.el.setAttribute('data-slot-date', arg.date.toISOString());
        },
        dayMaxEvents: 2,
        dayMaxEventRows: 2,
        eventContent: function(arg) {
            const extProps = arg.event.extendedProps || {};
            const isTempoPessoal = (extProps.event_type || '') === 'tempo_pessoal';
            const statusIcon = isTempoPessoal
                ? (extProps.personal_time_type?.icon ? 'ph ' + extProps.personal_time_type.icon : 'ph ph-dots-three')
                : (extProps.status_icon || null);
            const clientName = (extProps.client_name || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const serviceName = (extProps.service_name || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const fallbackTitle = (arg.event.title || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');

            // Extras (nomes) a partir de event_services
            var extrasParts = [];
            (extProps.event_services || []).forEach(function(s) {
                (s.extras || []).forEach(function(e) {
                    var n = (e.name || '').trim();
                    if (n) extrasParts.push(n.replace(/</g, '&lt;').replace(/>/g, '&gt;'));
                });
            });
            const extrasStr = extrasParts.length ? extrasParts.join(', ') : '';

            // Ícone de estado (clicável para marcações sem fatura; concluídas = só ícone a verde)
            var hasInvoice = !!(extProps.has_invoice);
            var iconHtml = '';
            if (statusIcon) {
                if (isTempoPessoal) {
                    iconHtml = '<i class="' + statusIcon + ' fc-event-status-icon"></i>';
                } else if (hasInvoice) {
                    iconHtml = '<span class="fc-event-status-icon-completo"><i class="' + statusIcon + ' fc-event-status-icon"></i></span>';
                } else {
                    iconHtml = '<span class="agenda-event-status-icon-btn" role="button" tabindex="-1" title="Alterar estado"><i class="' + statusIcon + ' fc-event-status-icon"></i></span>';
                }
            }

            // Linha 1: ícone + cliente (ou título)
            const line1 = iconHtml + '<strong class="fc-event-client">' + (clientName || fallbackTitle || '—') + '</strong>';

            // Linha 2: serviço + extras
            let line2 = serviceName || '';
            if (extrasStr) line2 += (line2 ? ' · ' : '') + extrasStr;
            const line2Html = line2 ? '<span class="fc-event-service-line">' + line2 + '</span>' : '';

            // Linha 3: hora início - fim
            const start = arg.event.start;
            const end = arg.event.end;
            const fmt = function(d) { return d ? (String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')) : ''; };
            const startStr = fmt(start);
            const endStr = fmt(end);
            let line3 = '';
            if (startStr && endStr) {
                line3 = startStr + ' - ' + endStr;
            } else if (startStr) {
                line3 = startStr;
            }
            const line3Html = line3 ? '<span class="fc-event-time-range">' + line3 + '</span>' : '';

            let contentHtml = '<div class="fc-event-line fc-event-line-1">' + line1 + '</div>';
            if (line2Html) {
                contentHtml += '<div class="fc-event-line fc-event-line-2">' + line2Html + '</div>';
            }
            if (line3Html) {
                contentHtml += '<div class="fc-event-line fc-event-line-3">' + line3Html + '</div>';
            }

            return { html: '<div class="fc-event-content-wrapper">' + contentHtml + '</div>' };
        },
        dayHeaderFormat: function(arg) {
            const days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
            const daysLong = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
            let d = null;
            
            // FullCalendar v6 passa um objeto Date Formatter customizado
            // O objeto date tem propriedades: marker (ISO string), year, month, day, etc.
            if (arg && arg.date) {
                const dateObj = arg.date;
                
                // Tentar usar marker (ISO string) primeiro
                if (dateObj.marker) {
                    d = new Date(dateObj.marker);
                }
                // Se não tem marker, construir a data a partir das propriedades
                else if (dateObj.year !== undefined && dateObj.month !== undefined && dateObj.day !== undefined) {
                    // month é 0-indexed no FullCalendar, mas new Date espera 0-indexed também
                    d = new Date(dateObj.year, dateObj.month, dateObj.day);
                }
                // Fallback: tentar usar como Date se for
                else if (dateObj instanceof Date) {
                    d = dateObj;
                }
            }
            
            // Se não conseguiu obter a data válida
            if (!d || isNaN(d.getTime())) {
                return '';
            }
            
            const dayIndex = d.getDay();
            
            const currentView = agendaCurrentViewType;
            
            // Na vista de mês, mostrar apenas o nome do dia
            if (currentView === 'dayGridMonth') {
                if (dayIndex >= 0 && dayIndex <= 6) {
                    return days[dayIndex];
                }
                return '';
            }
            
            // Semana, 3 dias e Dia: nome completo do dia + número (ex: "Quinta 5")
            const dayNumber = d.getDate();
            if (dayIndex >= 0 && dayIndex <= 6 && dayNumber >= 1 && dayNumber <= 31) {
                return daysLong[dayIndex] + ' ' + dayNumber;
            }
            
            return '';
        },
        /* === 2) Clique numa célula: mostrar menu rápido; "Criar evento" abre o modal === */
        dateClick: function(info) {
            function toLocalDateTimeStr(d) {
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                const h = String(d.getHours()).padStart(2, '0');
                const min = String(d.getMinutes()).padStart(2, '0');
                return y + '-' + m + '-' + day + 'T' + h + ':' + min;
            }
            var startDate = info.date;
            var endDate;
            if (info.allDay) {
                endDate = new Date(startDate);
                endDate.setHours(endDate.getHours() + 1);
            } else {
                endDate = new Date(startDate.getTime() + 60 * 60 * 1000);
            }
            var resourceId = info.resource ? info.resource.id : null;
            var startStr = toLocalDateTimeStr(startDate);
            var endStr = toLocalDateTimeStr(endDate);

            var d = startDate;
            var headingLabel = DAYS_LONG[d.getDay()] + ', ' + d.getDate() + ' ' + MONTHS_LONG[d.getMonth()] + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
            var timeLabel = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');

            clearAgendaCellHighlight();
            var target = info.jsEvent.target;
            var slotTd = target.closest('td');
            if (slotTd && (slotTd.closest('.fc-timegrid-axis') || slotTd.classList.contains('fc-timegrid-slot-label'))) slotTd = null;
            if (slotTd && (resourceId || info.jsEvent.clientX != null)) {
                var wrapper = createCellHighlightForColumn(slotTd, resourceId, timeLabel, info.jsEvent.clientX);
                if (wrapper) _agendaHighlight.wrapper = wrapper;
            }
            if (!_agendaHighlight.wrapper && slotTd) {
                var dayCell = target.closest('.fc-daygrid-day');
                if (dayCell) {
                    dayCell.classList.add('agenda-cell-highlighted');
                    _agendaHighlight.wrapper = { remove: function() {}, _isDayGrid: true };
                    _agendaHighlight.wrapper._parent = dayCell;
                } else {
                    var slotRect = slotTd.getBoundingClientRect();
                    var wrapper = document.createElement('div');
                    wrapper.className = 'agenda-cell-highlight agenda-cell-highlight-active';
                    wrapper.style.position = 'fixed';
                    wrapper.style.top = slotRect.top + 'px';
                    wrapper.style.left = slotRect.left + 'px';
                    wrapper.style.width = slotRect.width + 'px';
                    wrapper.style.height = slotRect.height + 'px';
                    wrapper.style.zIndex = '9998';
                    wrapper.style.pointerEvents = 'none';
                    var span = document.createElement('span');
                    span.className = 'agenda-cell-time-overlay';
                    span.textContent = timeLabel;
                    wrapper.appendChild(span);
                    document.body.appendChild(wrapper);
                    _agendaHighlight.wrapper = wrapper;
                    _agendaHighlight.wrapper._isFullRow = true;
                }
            }

            var options = [
                {
                    label: 'Nova marcação',
                    icon: 'bi bi-calendar-check',
                    iconColor: 'var(--accent-color, #0d6efd)',
                    action: function() {
                        openNovaMarcacaoModal(startStr, endStr, resourceId);
                    }
                },
                {
                    label: 'Novo tempo pessoal',
                    icon: 'bi bi-person',
                    iconColor: 'var(--bs-secondary, #6c757d)',
                    action: function() {
                        openTempoPessoalModal(startStr, endStr, resourceId);
                    }
                }
            ];
            clearAgendaHoverHighlight();
            showQuickMenu(info.jsEvent.clientX, info.jsEvent.clientY, headingLabel, options);
        },
        resources: function(fetchInfo, successCallback, failureCallback) {
            fetch(resourcesUrl, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(function(res) {
                allResources = res;
                let out = res;
                if (consultantFilterIds.length > 0) {
                    out = res.filter(function(r) { return consultantFilterIds.indexOf(r.id) !== -1; });
                }
                successCallback(out);
                
                if (viewSupportsConsultantFilter(agendaCurrentViewType)) {
                    setTimeout(function() {
                        initConsultantDropdown();
                        updateConsultantFilterButton();
                    }, 50);
                }
            })
            .catch(failureCallback);
        },
        resourceLabelContent: function(arg) {
            const res = arg.resource;
            const title = (res.title || '').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            const ext = res.extendedProps || {};
            const avatarUrl = ext.avatarUrl || '';
            if (!avatarUrl) {
                return { html: '<span class="fc-resource-consultant-name">' + title + '</span>' };
            }
            return {
                html: '<span class="fc-resource-consultant-label">' +
                    '<img class="fc-resource-consultant-avatar" src="' + String(avatarUrl).replace(/"/g, '&quot;') + '" alt="" />' +
                    '<span class="fc-resource-consultant-name">' + title + '</span></span>'
            };
        },
        events: function(info, successCallback, failureCallback) {
            const params = new URLSearchParams({
                start: info.startStr,
                end: info.endStr
            });
            var vtEvents = agendaCurrentViewType;
            if (vtEvents === 'resourceTimeGridDay') {
                params.set('for_resources', '1');
            } else if ((vtEvents === 'timeGridWeek' || vtEvents === 'timeGridThreeDay') && consultantFilterIds.length > 0) {
                params.set('filter_user_ids', consultantFilterIds.join(','));
            }
            fetch(eventsUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(function(events) {
                if (Array.isArray(events)) {
                    const seen = new Set();
                    events = events.filter(function(ev) {
                        var id = String(ev.id || '');
                        if (!id) return true;
                        if (seen.has(id)) return false;
                        seen.add(id);
                        return true;
                    });
                }
                successCallback(events);
            })
            .catch(failureCallback);
        },
        eventDidMount: function(info) {
            info.el.dataset.eventId = info.event.id;
            info.el.style.setProperty('color', '#000', 'important');
            //info.el.style.setProperty('box-shadow', 'none', 'important');
            var bgColor = info.event.backgroundColor;
            if (info.event.extendedProps?.event_type === 'tempo_pessoal') bgColor = bgColor || '#dee2e6';
            if (bgColor) {
                info.el.style.setProperty('background-color', bgColor, 'important');
            }
            var hoverDelay = 200;
            info.el.addEventListener('mouseenter', function() {
                if ($id('agendaQuickMenu').classList.contains('is-open')) return;
                if (agendaEventQuickviewHideTimeout) {
                    clearTimeout(agendaEventQuickviewHideTimeout);
                    agendaEventQuickviewHideTimeout = null;
                }
                if (agendaEventQuickviewShowTimeout) clearTimeout(agendaEventQuickviewShowTimeout);
                agendaEventQuickviewShowTimeout = setTimeout(function() {
                    agendaEventQuickviewShowTimeout = null;
                    var ev = calendar.getEventById(info.event.id);
                    var showInfo = ev ? { event: ev, el: info.el } : info;
                    showEventQuickview(showInfo);
                }, hoverDelay);
            });
            info.el.addEventListener('mouseleave', function() {
                if (agendaEventQuickviewShowTimeout) {
                    clearTimeout(agendaEventQuickviewShowTimeout);
                    agendaEventQuickviewShowTimeout = null;
                }
                if (agendaEventQuickviewHideTimeout) clearTimeout(agendaEventQuickviewHideTimeout);
                agendaEventQuickviewHideTimeout = setTimeout(function() {
                    agendaEventQuickviewHideTimeout = null;
                    hideEventQuickview();
                }, 80);
            });
        },
        eventClick: function(info) {
            hideEventQuickview();
            info.jsEvent.preventDefault();
            const id = info.event.id;
            if (eventDetailModalLoading) {
                return;
            }
            eventDetailModalLoading = true;
            fetch((C.urlEvents || '') + '/' + id, {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(function(data) {
                if (data.event_type === 'tempo_pessoal') {
                    populateTempoPessoalModal(data);
                    bootstrap.Modal.getOrCreateInstance($id('tempoPessoalModal')).show();
                } else {
                    populateEventDetailEditModal(data);
                    bootstrap.Modal.getOrCreateInstance($id('eventDetailEditModal')).show();
                }
                eventDetailModalLoading = false;
            })
            .catch(function(error) {
                console.error('Erro ao carregar detalhes do evento:', error);
                showToast('Erro ao carregar detalhes do evento.', 'error');
                eventDetailModalLoading = false;
            });
        },
        eventDrop: function(info) {
            if (info.event.extendedProps.has_invoice) { info.revert(); return; }
            const timeEditable = info.event.extendedProps.is_time_editable !== false;
            const reassignOnly = info.newResource && isResourceTimeGridDayView(calendar.view.type);
            if (!timeEditable && !reassignOnly) {
                info.revert();
                return;
            }
            const id = info.event.id;
            const start = info.event.start.toISOString();
            const end = info.event.end ? info.event.end.toISOString() : start;
            const payload = { start_at: start, end_at: end };
            if (info.newResource && isResourceTimeGridDayView(calendar.view.type)) {
                payload.user_id = info.newResource.id || null;
            }
            const url = (C.urlEvents || '') + '/' + id + '/update';
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify(payload)
            })
            .then(function(r) {
                if (!r.ok) throw new Error(r.statusText);
                return r.json();
            })
            .then(function(res) {
                if (!res.success) {
                    showToast(res.message || 'Erro ao atualizar.', 'error');
                    info.revert();
                    return;
                }
                if (res.event && payload.user_id !== undefined) {
                    info.event.setExtendedProp('user_id', payload.user_id);
                    if (res.event.extendedProps && res.event.extendedProps.user_name) info.event.setExtendedProp('user_name', res.event.extendedProps.user_name);
                    var newColor = res.event.backgroundColor;
                    if (newColor == null && info.newResource && allResources && allResources.length) {
                        var resObj = allResources.find(function(r) { return String(r.id) === String(info.newResource.id); });
                        newColor = resObj?.extendedProps?.color || null;
                    }
                    info.event.setProp('backgroundColor', newColor || null);
                    if (newColor) {
                        info.el.style.setProperty('background-color', newColor, 'important');
                        setTimeout(function() {
                            var el = $('[data-event-id="' + info.event.id + '"]');
                            if (el) el.style.setProperty('background-color', newColor, 'important');
                        }, 0);
                    } else {
                        info.el.style.removeProperty('background-color');
                        setTimeout(function() {
                            var el = $('[data-event-id="' + info.event.id + '"]');
                            if (el) el.style.removeProperty('background-color');
                        }, 0);
                    }
                }
            })
            .catch(function(err) {
                console.error('eventDrop error', err);
                info.revert();
            });
        },
        eventResize: function(info) {
            if (info.event.extendedProps.has_invoice) { info.revert(); return; }
            if (info.event.extendedProps.is_time_editable === false) { info.revert(); return; }
            const id = info.event.id;
            const start = info.event.start.toISOString();
            const end = info.event.end ? info.event.end.toISOString() : start;
            const url = (C.urlEvents || '') + '/' + id + '/update';
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({ start_at: start, end_at: end })
            })
            .then(function(r) {
                if (!r.ok) throw new Error(r.statusText);
                return r.json();
            })
            .then(function(res) {
                if (!res.success) {
                    showToast(res.message || 'Erro ao atualizar.', 'error');
                    info.revert();
                }
            })
            .catch(function(err) {
                console.error('eventResize error', err);
                info.revert();
            });
        },
        datesSet: function(info) {
            agendaCurrentViewType = info.view.type;
            if ((info.view.type.includes('timeGrid') || info.view.type.includes('resourceTimeGrid')) &&
                calendar.view.activeStart <= new Date() && calendar.view.activeEnd >= new Date()) {
                setTimeout(function() {
                    const now = new Date();
                    const currentTime = now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0') + ':00';
                    calendar.scrollToTime(currentTime);
                }, 50);
            }
            requestAnimationFrame(function() {
                var viewType = calendar.view.type;
                const viewSelectorBtn = calendarEl.querySelector('.fc-viewSelector-button');
                const consultantBtn = calendarEl.querySelector('.fc-consultantFilter-button');
                const currentDateBtn = calendarEl.querySelector('.fc-currentDate-button');
                const prevBtn = calendarEl.querySelector('.fc-prev-button');
                const nextBtn = calendarEl.querySelector('.fc-next-button');
                if (viewSelectorBtn && (!viewSelectorBtn.closest('.dropdown') || !calendarEl.querySelector('#viewSelectorDropdown'))) {
                    viewSelectorBtn.dataset.initialized = '';
                    initViewSelectorDropdown();
                }
                if (consultantBtn && viewSupportsConsultantFilter(viewType) && (!consultantBtn.closest('.dropdown') || !calendarEl.querySelector('#consultantDropdown'))) {
                    consultantBtn.dataset.initialized = '';
                    initConsultantDropdown();
                }
                initAdicionarDropdown();
                var startDate = viewType === 'dayGridMonth' ? calendar.view.currentStart : info.start;
                if (currentDateBtn) {
                    currentDateBtn.textContent = formatCurrentDateButton(viewType, startDate, info.end);
                    currentDateBtn.style.pointerEvents = 'none';
                    currentDateBtn.style.cursor = 'default';
                    currentDateBtn.style.fontWeight = '500';
                    currentDateBtn.style.color = '#212529';
                    currentDateBtn.style.opacity = '1';
                }
                if (viewSelectorBtn && viewSelectorBtn.dataset.initialized === '1') {
                    var viewLabels = {
                        timeGridWeek: 'Semana',
                        timeGridThreeDay: '3 dias',
                        resourceTimeGridDay: 'Dia'
                    };
                    viewSelectorBtn.textContent = viewLabels[viewType] || 'Dia';
                }
                if (consultantBtn && consultantBtn.dataset.initialized === '1' && viewSupportsConsultantFilter(viewType)) {
                    var res = selectedConsultantId && allResources.length ? allResources.find(function(r) { return r.id === selectedConsultantId; }) : null;
                    consultantBtn.textContent = res ? res.title : 'Toda a equipa';
                }
                if (prevBtn) prevBtn.innerHTML = '<span class="fc-icon fc-icon-chevron-left"></span>';
                if (nextBtn) nextBtn.innerHTML = '<span class="fc-icon fc-icon-chevron-right"></span>';
                applyToolbarStyles();
                ensureAgendaSlot24hToggle();
            });
        },
        viewDidMount: function(info) {
            agendaCurrentViewType = info.view.type;
            const isConsultant = info.view.type === 'resourceTimeGridDay';
            const nextMode = isConsultant ? 'consultant' : 'normal';
            const modeChanged = currentViewMode !== nextMode;
            currentViewMode = nextMode;

            if (isConsultant) {
                calendar.refetchResources();
                calendar.refetchEvents();
            } else if (modeChanged) {
                /* Dia (recursos) → semana/mês: refetch sem for_resources evita eventos “fantasma” / duplicados visuais */
                calendar.refetchEvents();
            }

            // Fazer scroll para a hora atual se for uma vista de tempo
            if (info.view.type.includes('timeGrid') || info.view.type.includes('resourceTimeGrid')) {
                setTimeout(function() {
                    const now = new Date();
                    const currentTime = now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0') + ':00';
                    calendar.scrollToTime(currentTime);
                }, 100);
            }
            
            requestAnimationFrame(function() {
                applyToolbarStyles();
                ensureAgendaSlot24hToggle();
            });
            setTimeout(function() {
                initViewSelectorDropdown();
                updateViewSelectorButton(info.view.type);
                updateViewDropdownActive(info.view.type);
                initAdicionarDropdown();
                applyToolbarStyles();
                ensureAgendaSlot24hToggle();
            }, 0);
            
            const showConsultantFilter = viewSupportsConsultantFilter(info.view.type);
            const consultantBtn = calendarEl.querySelector('.fc-consultantFilter-button');
            if (consultantBtn) {
                consultantBtn.style.display = showConsultantFilter ? 'inline-block' : 'none';
                var parent = consultantBtn.parentElement;
                if (parent && parent.classList.contains('dropdown')) {
                    parent.style.display = showConsultantFilter ? 'inline-block' : 'none';
                }
            }
            if (showConsultantFilter) {
                setTimeout(function() {
                    initConsultantDropdown();
                    updateConsultantFilterButton();
                    applyToolbarStyles();
                    ensureAgendaSlot24hToggle();
                }, isConsultant ? 150 : 0);
            }
        }
    });
    calendar.render();

    // Clique no ícone de estado: abre só o dropdown de estados (não o modal). Captura em fase capture para correr antes do eventClick do FullCalendar.
    // Marcações faturadas (has_invoice): não abrir menu.
    calendarEl.addEventListener('click', function(e) {
        var btn = e.target.closest('.agenda-event-status-icon-btn');
        if (!btn) return;
        var evEl = btn.closest('.fc-event');
        if (!evEl || !evEl.dataset.eventId) return;
        var ev = calendar.getEventById(evEl.dataset.eventId);
        if (!ev) return;
        if (ev.extendedProps && ev.extendedProps.has_invoice) return;
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        showEventStatusQuickMenu(ev, e.clientX, e.clientY);
    }, true);

    /* TEST: overlay fora da tabela para evitar que as horas “mexam” na vertical; reverter este bloco se não correr bem */
    (function setupSlotHoverHighlight() {
        var calendarEl = $id('calendar');

        function updateHoverOverlay(e) {
            var target = e.target;
            if (!calendarEl.contains(target)) return;
            if ($id('agendaQuickMenu').classList.contains('is-open')) return;
            var slotEl = target.closest('[data-slot-date]');
            if (!slotEl) {
                clearAgendaHoverHighlight();
                return;
            }
            var slotTd = slotEl.closest('td');
            if (!slotTd) {
                clearAgendaHoverHighlight();
                return;
            }
            var dateStr = slotEl.getAttribute('data-slot-date');
            if (!dateStr) { clearAgendaHoverHighlight(); return; }
            var d = new Date(dateStr);
            var timeLabel = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
            var clientX = e.clientX;
            var colEl = null;
            var cols = $$('.fc-timegrid-col');
            for (var i = 0; i < cols.length; i++) {
                var r = cols[i].getBoundingClientRect();
                if (clientX >= r.left && clientX <= r.right) { colEl = cols[i]; break; }
            }
            if (!colEl) { clearAgendaHoverHighlight(); return; }

            var slotRect = slotTd.getBoundingClientRect();
            var colRect = colEl.getBoundingClientRect();
            if (colRect.width <= 0 || slotRect.height <= 0) return;

            /* Overlay em position:fixed e coordenadas viewport para ficar exactamente sobre a célula e visível (z-index alto) */
            if (!_agendaHoverHighlight) {
                var wrapper = document.createElement('div');
                wrapper.className = 'agenda-cell-highlight-hover';
                wrapper.setAttribute('role', 'presentation');
                wrapper.style.position = 'fixed';
                wrapper.style.zIndex = '9999';
                wrapper.style.pointerEvents = 'none';
                var timeSpan = document.createElement('span');
                timeSpan.className = 'agenda-cell-time-overlay';
                wrapper.appendChild(timeSpan);
                _agendaHoverHighlight = wrapper;
            }
            _agendaHoverHighlight.style.top = slotRect.top + 'px';
            _agendaHoverHighlight.style.left = colRect.left + 'px';
            _agendaHoverHighlight.style.width = colRect.width + 'px';
            _agendaHoverHighlight.style.height = slotRect.height + 'px';
            _agendaHoverHighlight.querySelector('.agenda-cell-time-overlay').textContent = timeLabel;
            if (!_agendaHoverHighlight.parentNode) document.body.appendChild(_agendaHoverHighlight);
        }
        function clearOnLeave(e) {
            if (!calendarEl.contains(e.relatedTarget)) clearAgendaHoverHighlight();
        }
        calendarEl.addEventListener('mousemove', updateHoverOverlay, { passive: true });
        calendarEl.addEventListener('mouseleave', clearOnLeave);
        /* Ao fazer scroll na grelha, remover o overlay para evitar desalinhamento; volta no próximo mousemove */
        calendarEl.addEventListener('scroll', function() {
            if (_agendaHoverHighlight) clearAgendaHoverHighlight();
        }, true);
    })();

    // Fazer scroll para a hora atual após render inicial (ou para a marcação se ?event=ID)
    (function initScrollAndOpenEvent() {
        const params = new URLSearchParams(window.location.search);
        const eventId = params.get('event');
        const novaMarcacao = params.get('novaMarcacao');
        const clientId = params.get('client_id');
        const userId = params.get('user_id');
        if (eventId) {
            eventDetailModalLoading = true;
            fetch((C.urlEvents || '') + '/' + eventId, { headers: { 'Accept': 'application/json' } })
                .then(function(r) {
                    if (!r.ok) throw new Error('Evento não encontrado');
                    return r.json();
                })
                .then(function(data) {
                    if (data.start_at) {
                        calendar.gotoDate(new Date(data.start_at));
                        if (calendar.view.type.includes('timeGrid') || calendar.view.type.includes('resourceTimeGrid')) {
                            var d = new Date(data.start_at);
                            calendar.scrollToTime(String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') + ':00');
                        }
                    }
                    if (data.event_type === 'tempo_pessoal') {
                        populateTempoPessoalModal(data);
                        bootstrap.Modal.getOrCreateInstance($id('tempoPessoalModal')).show();
                    } else {
                        populateEventDetailEditModal(data);
                        bootstrap.Modal.getOrCreateInstance($id('eventDetailEditModal')).show();
                    }
                    if (history.replaceState) {
                        history.replaceState({}, document.title, window.location.pathname);
                    }
                })
                .catch(function() {
                    showToast('Marcação não encontrada ou sem permissão para ver.', 'error');
                    var now = new Date();
                    var currentTime = now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0') + ':00';
                    if (calendar.view.type.includes('timeGrid') || calendar.view.type.includes('resourceTimeGrid')) {
                        calendar.scrollToTime(currentTime);
                    }
                })
                .finally(function() {
                    eventDetailModalLoading = false;
                });
        } else if (novaMarcacao === '1' && clientId && userId) {
            var now = new Date();
            var min = now.getMinutes();
            var roundedMin = Math.ceil(min / 15) * 15;
            if (roundedMin >= 60) { now.setHours(now.getHours() + 1); roundedMin = 0; }
            now.setMinutes(roundedMin);
            now.setSeconds(0, 0);
            var end = new Date(now.getTime() + 60 * 60 * 1000);
            var startStr = now.toISOString().slice(0, 19).replace('T', ' ');
            var endStr = end.toISOString().slice(0, 19).replace('T', ' ');
            openNovaMarcacaoModal(startStr, endStr, userId, clientId);
            if (history.replaceState) {
                history.replaceState({}, document.title, window.location.pathname);
            }
        } else {
            setTimeout(function() {
                var now = new Date();
                var currentTime = now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0') + ':00';
                if (calendar.view.type.includes('timeGrid') || calendar.view.type.includes('resourceTimeGrid')) {
                    calendar.scrollToTime(currentTime);
                }
            }, 200);
        }
    })();

    // Função para atualizar o texto do botão de seleção de vista
    function updateViewSelectorButton(viewType) {
        const viewBtn = calendarEl.querySelector('.fc-viewSelector-button');
        if (!viewBtn) return;
        
        const viewLabels = {
            'timeGridWeek': 'Semana',
            'timeGridThreeDay': '3 dias',
            'resourceTimeGridDay': 'Dia'
        };
        
        viewBtn.textContent = viewLabels[viewType] || 'Dia';
    }

    // Inicializar dropdown de vistas
    function initViewSelectorDropdown() {
        const viewBtn = calendarEl.querySelector('.fc-viewSelector-button');
        if (!viewBtn) return;
        
        // Se já foi inicializado, verificar se a estrutura ainda existe
        if (viewBtn.dataset.initialized === '1') {
            const wrapper = viewBtn.closest('.dropdown');
            const dropdown = wrapper ? wrapper.querySelector('#viewSelectorDropdown') : null;
            if (wrapper && dropdown) {
                // Estrutura existe, apenas garantir estilos com important
                wrapper.style.setProperty('padding', '0', 'important');
                wrapper.style.setProperty('display', 'inline-block', 'important');
                return;
            }
            // Estrutura perdida, re-inicializar
            viewBtn.dataset.initialized = '';
        }
        
        if (viewBtn.dataset.initialized) return;
        
        viewBtn.dataset.initialized = '1';
        
        // Garantir que temos um wrapper próprio para este dropdown
        let btnParent = viewBtn.parentElement;
        
        // Se o parent já tem outro dropdown ou não é um dropdown isolado, criar wrapper
        if (btnParent.querySelector('#consultantDropdown') || !btnParent.classList.contains('dropdown') || btnParent.querySelector('.fc-consultantFilter-button')) {
            // Criar um wrapper específico para o view selector
            const wrapper = document.createElement('div');
            wrapper.className = 'dropdown';
            wrapper.style.setProperty('display', 'inline-block', 'important');
            wrapper.style.setProperty('padding', '0', 'important');
            viewBtn.parentElement.insertBefore(wrapper, viewBtn);
            wrapper.appendChild(viewBtn);
            btnParent = wrapper;
        } else {
            btnParent.classList.add('dropdown');
            btnParent.style.setProperty('padding', '0', 'important');
            btnParent.style.setProperty('display', 'inline-block', 'important');
        }
        
        viewBtn.classList.add('dropdown-toggle');
        viewBtn.setAttribute('data-bs-toggle', 'dropdown');
        viewBtn.setAttribute('data-bs-target', '#viewSelectorDropdown');
        viewBtn.setAttribute('aria-expanded', 'false');
        viewBtn.setAttribute('id', 'viewSelectorBtn');
        
        // Remover dropdown existente se houver (mas não o consultantDropdown)
        const existingDropdown = btnParent.querySelector('#viewSelectorDropdown');
        if (existingDropdown) {
            existingDropdown.remove();
        }
        
        const dropdown = document.createElement('div');
        dropdown.className = 'dropdown-menu';
        dropdown.id = 'viewSelectorDropdown';
        dropdown.setAttribute('aria-labelledby', 'viewSelectorBtn');
        
        const views = [
            { type: 'resourceTimeGridDay', label: 'Dia' },
            { type: 'timeGridThreeDay', label: '3 dias' },
            { type: 'timeGridWeek', label: 'Semana' }
        ];
        
        views.forEach(function(view) {
            const option = document.createElement('a');
            option.className = 'dropdown-item';
            option.href = '#';
            option.textContent = view.label;
            option.dataset.viewType = view.type;
            option.addEventListener('click', function(e) {
                e.preventDefault();
                const viewBtn = calendarEl.querySelector('.fc-viewSelector-button');
                if (viewBtn) {
                    viewBtn.textContent = '';
                    viewBtn.textContent = view.label;
                }
                calendar.changeView(view.type);
                calendar.gotoDate(new Date());
                updateViewSelectorButton(view.type);
                updateViewDropdownActive(view.type);
            });
            dropdown.appendChild(option);
        });
        
        btnParent.appendChild(dropdown);
        
        // Atualizar estado inicial
        updateViewSelectorButton(calendar.view.type);
        updateViewDropdownActive(calendar.view.type);
    }

    function updateViewDropdownActive(viewType) {
        const dropdown = calendarEl.querySelector('#viewSelectorDropdown');
        if (!dropdown) return;
        dropdown.querySelectorAll('.dropdown-item').forEach(function(item) {
            item.classList.remove('active');
            if (item.dataset.viewType === viewType) {
                item.classList.add('active');
            }
        });
    }

    /** Devolve início e fim no slot mais próximo da hora atual (arredondado a 15 min), formato YYYY-MM-DDTHH:mm. Fim = início + 1h */
    function getClosestSlotToNow() {
        var d = new Date();
        var y = d.getFullYear();
        var m = d.getMonth();
        var day = d.getDate();
        var h = d.getHours();
        var min = d.getMinutes();
        var slotMin = Math.floor(min / 15) * 15;
        var startDate = new Date(y, m, day, h, slotMin, 0);
        var endDate = new Date(startDate.getTime() + 60 * 60 * 1000);
        var pad = function(n) { return String(n).padStart(2, '0'); };
        var startStr = startDate.getFullYear() + '-' + pad(startDate.getMonth() + 1) + '-' + pad(startDate.getDate()) + 'T' + pad(startDate.getHours()) + ':' + pad(startDate.getMinutes());
        var endStr = endDate.getFullYear() + '-' + pad(endDate.getMonth() + 1) + '-' + pad(endDate.getDate()) + 'T' + pad(endDate.getHours()) + ':' + pad(endDate.getMinutes());
        return { startStr: startStr, endStr: endStr };
    }

    function initAdicionarDropdown() {
        const addBtn = calendarEl.querySelector('.fc-adicionarDropdown-button');
        if (!addBtn) return;

        var existingDd = bootstrap.Dropdown.getInstance(addBtn);
        if (existingDd) existingDd.dispose();

        calendarEl.querySelectorAll('.agenda-adicionar-dropdown-menu').forEach(function(el) { el.remove(); });
        var orphanMenu = document.getElementById('adicionarDropdownMenu');
        if (orphanMenu) orphanMenu.remove();

        var isolated = addBtn.closest('.agenda-adicionar-dropdown-isolated');
        var isolatedOk = isolated && !isolated.querySelector('.fc-viewSelector-button') && !isolated.querySelector('.fc-consultantFilter-button');
        if (!isolatedOk) {
            var nw = document.createElement('div');
            nw.className = 'dropdown agenda-adicionar-dropdown-isolated';
            nw.style.setProperty('display', 'inline-block', 'important');
            nw.style.setProperty('margin', '0', 'important');
            nw.style.setProperty('padding', '0', 'important');
            addBtn.parentElement.insertBefore(nw, addBtn);
            nw.appendChild(addBtn);
        }

        var btnParent = addBtn.parentElement;
        btnParent.querySelectorAll('.agenda-adicionar-dropdown-menu').forEach(function(el) { el.remove(); });

        addBtn.dataset.initialized = '1';
        addBtn.classList.add('dropdown-toggle');
        addBtn.setAttribute('data-bs-toggle', 'dropdown');
        addBtn.setAttribute('data-bs-target', '#adicionarDropdownMenu');
        addBtn.setAttribute('aria-expanded', 'false');
        addBtn.id = 'adicionarDropdownBtn';
        var menu = document.createElement('div');
        menu.id = 'adicionarDropdownMenu';
        menu.className = 'dropdown-menu dropdown-menu-end agenda-adicionar-dropdown-menu';
        menu.setAttribute('aria-labelledby', 'adicionarDropdownBtn');
        var optMarcacao = document.createElement('a');
        optMarcacao.className = 'dropdown-item';
        optMarcacao.href = '#';
        optMarcacao.innerHTML = '<i class="bi bi-calendar-check me-2"></i> Nova marcação';
        optMarcacao.addEventListener('click', function(e) {
            e.preventDefault();
            var slot = getClosestSlotToNow();
            var resourceId = (viewSupportsConsultantFilter(calendar.view.type) && selectedConsultantId) ? selectedConsultantId : null;
            openNovaMarcacaoModal(slot.startStr, slot.endStr, resourceId);
            bootstrap.Dropdown.getInstance(addBtn)?.hide();
        });
        var optTempoPessoal = document.createElement('a');
        optTempoPessoal.className = 'dropdown-item';
        optTempoPessoal.href = '#';
        optTempoPessoal.innerHTML = '<i class="bi bi-person me-2"></i> Novo tempo pessoal';
        optTempoPessoal.addEventListener('click', function(e) {
            e.preventDefault();
            var slot = getClosestSlotToNow();
            var resourceId = (viewSupportsConsultantFilter(calendar.view.type) && selectedConsultantId) ? selectedConsultantId : null;
            openTempoPessoalModal(slot.startStr, slot.endStr, resourceId);
            bootstrap.Dropdown.getInstance(addBtn)?.hide();
        });
        menu.appendChild(optMarcacao);
        menu.appendChild(optTempoPessoal);
        btnParent.appendChild(menu);
        bootstrap.Dropdown.getOrCreateInstance(addBtn);
    }
    
    /** Aplica estilos estruturais da toolbar (cores vêm do CSS/theme: var(--surface-color), etc.). */
    function applyToolbarStyles() {
        var todayBtn = calendarEl.querySelector('.fc-today-button');
        if (todayBtn) {
            todayBtn.style.setProperty('border-width', '1px', 'important');
            todayBtn.style.setProperty('border-style', 'solid', 'important');
        }
        /* Não aplicar cores aos botões: agenda.css usa variáveis de tema (dark/light). Adicionar fica com estilo primary. */
    }
    
    // Observer para garantir que os estilos são mantidos após mudanças no DOM
    const toolbarObserver = new MutationObserver(function(mutations) {
        applyToolbarStyles();
    });
    
    // Observar mudanças no toolbar
    const headerToolbar = calendarEl.querySelector('.fc-header-toolbar');
    if (headerToolbar) {
        toolbarObserver.observe(headerToolbar, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style', 'class']
        });
    }
    
    // Garantir que os botões estão corretos após render inicial
    setTimeout(function() {
        // Inicializar dropdown de vistas
        initViewSelectorDropdown();
        
        // Aplicar estilos aos botões
        applyToolbarStyles();
        
        // Atualizar botão de data atual
        const currentDateBtn = calendarEl.querySelector('.fc-currentDate-button');
        if (currentDateBtn) {
            const view = calendar.view;
            // Para vista mensal, usar currentStart que representa o início do mês visualizado
            const startDate = view.type === 'dayGridMonth' ? view.currentStart : view.activeStart;
            currentDateBtn.textContent = formatCurrentDateButton(view.type, startDate, view.activeEnd);
            currentDateBtn.style.pointerEvents = 'none';
            currentDateBtn.style.cursor = 'default';
            currentDateBtn.style.fontWeight = '500';
            currentDateBtn.style.color = '#212529';
            currentDateBtn.style.opacity = '1';
        }
        
        // Garantir que prev/next têm apenas ícones
        const prevBtn = calendarEl.querySelector('.fc-prev-button');
        const nextBtn = calendarEl.querySelector('.fc-next-button');
        if (prevBtn) {
            prevBtn.innerHTML = '<span class="fc-icon fc-icon-chevron-left"></span>';
        }
        if (nextBtn) {
            nextBtn.innerHTML = '<span class="fc-icon fc-icon-chevron-right"></span>';
        }
        
        // Botão de refresh da agenda com ícone
        const refreshBtn = calendarEl.querySelector('.fc-refreshAgenda-button');
        if (refreshBtn && !refreshBtn.dataset._iconApplied) {
            refreshBtn.innerHTML = '<i class="ph ph-arrow-clockwise"></i>';
            refreshBtn.dataset._iconApplied = '1';
        }

        ensureAgendaSlot24hToggle();
        
        // Esconder título/chunk do center
        const titleEl = calendarEl.querySelector('.fc-toolbar-title');
        if (titleEl) {
            titleEl.closest('.fc-toolbar-chunk')?.style.setProperty('display', 'none', 'important');
        }
        
        // Reaplicar estilos após um pequeno delay para garantir que são aplicados
        setTimeout(function() {
            applyToolbarStyles();
        }, 50);
    }, 100);

    // Inicializar dropdown de consultores após render
    function initConsultantDropdown() {
        const consultantBtn = calendarEl.querySelector('.fc-consultantFilter-button');
        if (!consultantBtn) return;

        if (allResources.length === 0 && resourcesUrl) {
            fetch(resourcesUrl, { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    allResources = res || [];
                    initConsultantDropdown();
                })
                .catch(function() {});
            return;
        }

        // Se já foi inicializado, verificar se a estrutura ainda existe
        if (consultantBtn.dataset.initialized === '1') {
            const wrapper = consultantBtn.closest('.dropdown');
            const dropdown = wrapper ? wrapper.querySelector('#consultantDropdown') : null;
            if (wrapper && dropdown) {
                // Estrutura existe, apenas atualizar estado e garantir estilos com important
                updateDropdownActive();
                wrapper.style.setProperty('margin', '0', 'important');
                wrapper.style.setProperty('margin-left', '0', 'important');
                wrapper.style.setProperty('margin-right', '0', 'important');
                wrapper.style.setProperty('padding', '0', 'important');
                wrapper.style.setProperty('display', 'inline-block', 'important');
                consultantBtn.style.setProperty('margin', '0', 'important');
                consultantBtn.style.setProperty('margin-left', '0', 'important');
                consultantBtn.style.setProperty('margin-right', '0', 'important');
                return;
            }
            // Estrutura perdida, re-inicializar
            consultantBtn.dataset.initialized = '';
        }
        
        consultantBtn.dataset.initialized = '1';
        
        // Garantir que temos um wrapper próprio para este dropdown
        let btnParent = consultantBtn.parentElement;
        
        // Se o parent já tem outro dropdown ou não é um dropdown isolado, criar wrapper
        if (btnParent.querySelector('#viewSelectorDropdown') || !btnParent.classList.contains('dropdown') || btnParent.querySelector('.fc-viewSelector-button')) {
            // Criar um wrapper específico para o consultant filter
            const wrapper = document.createElement('div');
            wrapper.className = 'dropdown';
            wrapper.style.setProperty('display', 'inline-block', 'important');
            wrapper.style.setProperty('margin', '0', 'important');
            wrapper.style.setProperty('margin-left', '0', 'important');
            wrapper.style.setProperty('margin-right', '0', 'important');
            wrapper.style.setProperty('padding', '0', 'important');
            consultantBtn.parentElement.insertBefore(wrapper, consultantBtn);
            wrapper.appendChild(consultantBtn);
            btnParent = wrapper;
        } else {
            btnParent.classList.add('dropdown');
            btnParent.style.setProperty('margin', '0', 'important');
            btnParent.style.setProperty('margin-left', '0', 'important');
            btnParent.style.setProperty('margin-right', '0', 'important');
            btnParent.style.setProperty('padding', '0', 'important');
            btnParent.style.setProperty('display', 'inline-block', 'important');
        }
        
        consultantBtn.classList.add('dropdown-toggle');
        consultantBtn.setAttribute('data-bs-toggle', 'dropdown');
        consultantBtn.setAttribute('data-bs-target', '#consultantDropdown');
        consultantBtn.setAttribute('aria-expanded', 'false');
        consultantBtn.setAttribute('id', 'consultantFilterBtn');
        consultantBtn.style.setProperty('margin', '0', 'important');
        consultantBtn.style.setProperty('margin-left', '0', 'important');
        consultantBtn.style.setProperty('margin-right', '0', 'important');
        
        // Remover dropdown existente se houver (mas não o viewSelectorDropdown)
        const existingDropdown = btnParent.querySelector('#consultantDropdown');
        if (existingDropdown) {
            existingDropdown.remove();
        }
        
        const dropdown = document.createElement('div');
        dropdown.className = 'dropdown-menu';
        dropdown.id = 'consultantDropdown';
        dropdown.setAttribute('aria-labelledby', 'consultantFilterBtn');
        
        const allOption = document.createElement('a');
        allOption.className = 'dropdown-item' + (selectedConsultantId === '' ? ' active' : '');
        allOption.href = '#';
        allOption.textContent = 'Toda a equipa';
        allOption.addEventListener('click', function(e) {
            e.preventDefault();
            selectedConsultantId = '';
            consultantFilterIds = [];
            consultantBtn.textContent = '';
            consultantBtn.textContent = 'Toda a equipa';
            if (isResourceTimeGridDayView(calendar.view.type)) {
                calendar.refetchResources();
            }
            calendar.refetchEvents();
            updateDropdownActive();
        });
        dropdown.appendChild(allOption);
        (C.usersForConsultant || []).forEach(function(u) {
            var opt = document.createElement('a');
            opt.className = 'dropdown-item';
            opt.href = '#';
            opt.textContent = u.name;
            opt.addEventListener('click', function(e) {
                e.preventDefault();
                selectedConsultantId = String(u.id);
                consultantFilterIds = [String(u.id)];
                consultantBtn.textContent = u.name;
                if (isResourceTimeGridDayView(calendar.view.type)) {
                    calendar.refetchResources();
                }
                calendar.refetchEvents();
                updateDropdownActive();
            });
            dropdown.appendChild(opt);
        });
        btnParent.appendChild(dropdown);
    }

    function updateConsultantFilterButton() {
        const consultantBtn = calendarEl.querySelector('.fc-consultantFilter-button');
        if (consultantBtn && viewSupportsConsultantFilter(calendar.view.type)) {
            // Garantir que o dropdown está inicializado
            if (!consultantBtn.dataset.initialized) {
                initConsultantDropdown();
            }
            
            // Atualizar texto do botão (limpar primeiro)
            consultantBtn.textContent = '';
            if (selectedConsultantId && allResources.length > 0) {
                const resource = allResources.find(r => r.id === selectedConsultantId);
                if (resource) {
                    consultantBtn.textContent = resource.title;
                } else {
                    consultantBtn.textContent = 'Toda a equipa';
                }
            } else {
                consultantBtn.textContent = 'Toda a equipa';
            }
            
            // Atualizar estado ativo do dropdown
            updateDropdownActive();
            
            // Garantir que os estilos estão aplicados com important
            consultantBtn.style.setProperty('margin', '0', 'important');
            consultantBtn.style.setProperty('margin-left', '0', 'important');
            consultantBtn.style.setProperty('margin-right', '0', 'important');
            const consultantWrapper = consultantBtn.closest('.dropdown');
            if (consultantWrapper) {
                consultantWrapper.style.setProperty('margin', '0', 'important');
                consultantWrapper.style.setProperty('margin-left', '0', 'important');
                consultantWrapper.style.setProperty('margin-right', '0', 'important');
                consultantWrapper.style.setProperty('padding', '0', 'important');
                consultantWrapper.style.setProperty('display', 'inline-block', 'important');
            }
        }
    }

    function updateDropdownActive() {
        const dropdown = calendarEl.querySelector('#consultantDropdown');
        if (!dropdown) return;
        dropdown.querySelectorAll('.dropdown-item').forEach(item => {
            item.classList.remove('active');
        });
        const items = dropdown.querySelectorAll('.dropdown-item');
        if (selectedConsultantId === '') {
            items[0]?.classList.add('active');
        } else {
            const selected = Array.from(items).find(item => {
                const resource = allResources.find(r => r.id === selectedConsultantId);
                return resource && item.textContent === resource.title;
            });
            selected?.classList.add('active');
        }
    }

    // Status é guardado ao clicar Guardar no modal eventDetailEditModal

    function eventDetailPopulateTimeOptions(selectedTime) {
        var container = $('.event-detail-time-options');
        if (!container) return;
        container.innerHTML = '';
        for (var h = 0; h < 24; h++) {
            for (var m = 0; m < 60; m += 15) {
                var ts = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
                var a = document.createElement('a');
                a.href = '#';
                a.className = 'dropdown-item event-detail-time-opt' + (ts === selectedTime ? ' active' : '');
                a.dataset.time = ts;
                a.textContent = ts;
                a.addEventListener('click', function(e) { e.preventDefault(); EventDetail.applyNewStartTime(this.dataset.time); });
                container.appendChild(a);
            }
        }
    }
    $id('eventDetailTimeDropdownMenu')?.addEventListener('click', function(e) {
        var opt = e.target.closest('.event-detail-time-opt');
        if (opt) { e.preventDefault(); EventDetail.applyNewStartTime(opt.dataset.time); }
    });
    $id('eventDetailTimeToggle')?.addEventListener('show.bs.dropdown', function() {
        var startStr = $id('eventDetailEditStart')?.value;
        var timeStr = '';
        if (startStr) {
            var d = new Date(startStr);
            var min = d.getMinutes();
            var m = Math.round(min / 15) * 15;
            if (m === 60) { m = 0; }
            timeStr = String(d.getHours()).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }
        eventDetailPopulateTimeOptions(timeStr);
    });
    $id('eventDetailTimeToggle')?.addEventListener('shown.bs.dropdown', function() {
        var active = $('.event-detail-time-options .event-detail-time-opt.active');
        if (active) {
            active.scrollIntoView({ block: 'nearest', behavior: 'instant' });
        }
    });

    if (typeof flatpickr !== 'undefined') {
        var datePickerWrap = $id('eventDetailDatePickerWrap');
        if (datePickerWrap) {
            window.eventDetailDateFlatpickr = flatpickr(datePickerWrap, {
                inline: true,
                dateFormat: 'Y-m-d',
                locale: 'pt',
                allowInput: false,
                onChange: function(selectedDates, dateStr) {
                    if (dateStr) EventDetail.applyNewDate(dateStr);
                }
            });
        }
        var novaMarcacaoDatePickerWrap = $id('novaMarcacaoDatePickerWrap');
        if (novaMarcacaoDatePickerWrap) {
            window.novaMarcacaoDateFlatpickr = flatpickr(novaMarcacaoDatePickerWrap, {
                inline: true,
                dateFormat: 'Y-m-d',
                locale: 'pt',
                allowInput: false,
                onChange: function(selectedDates, dateStr) {
                    if (dateStr) NovaMarcacao.applyNewDate(dateStr);
                }
            });
        }
        var tempoPessoalDatePickerWrap = $id('tempoPessoalDatePickerWrap');
        if (tempoPessoalDatePickerWrap) {
            window.tempoPessoalDateFlatpickr = flatpickr(tempoPessoalDatePickerWrap, {
                inline: true,
                dateFormat: 'Y-m-d',
                locale: 'pt',
                allowInput: false,
                onChange: function(selectedDates, dateStr) {
                    if (dateStr) {
                        $id('tempoPessoalDateInput').value = dateStr;
                        var d = selectedDates[0];
                        if (d) {
                            $id('tempoPessoalDateToggle').textContent = DAYS_LONG[d.getDay()] + ', ' + d.getDate() + ' ' + MONTHS_LONG[d.getMonth()];
                        }
                        TempoPessoal.syncHiddenFromInputs();
                        var toggle = $id('tempoPessoalDateToggle');
                        if (toggle && bootstrap.Dropdown) bootstrap.Dropdown.getInstance(toggle)?.hide();
                    }
                }
            });
        }
    }

    $id('tempoPessoalStartTimeToggle')?.addEventListener('show.bs.dropdown', function() {
        var startStr = $id('tempoPessoalStart')?.value;
        var timeStr = '';
        if (startStr) {
            var d = new Date(startStr);
            var min = d.getMinutes();
            var m = Math.round(min / 15) * 15;
            if (m === 60) { m = 0; }
            timeStr = String(d.getHours()).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }
        TempoPessoal.populateTimeOptions('.tempo-pessoal-time-options', timeStr, TempoPessoal.applyNewStartTime);
    });
    $id('tempoPessoalStartTimeToggle')?.addEventListener('shown.bs.dropdown', function() {
        var active = $('.tempo-pessoal-time-options .tempo-pessoal-time-opt.active');
        if (active) active.scrollIntoView({ block: 'nearest', behavior: 'instant' });
    });
    $id('tempoPessoalEndTimeToggle')?.addEventListener('show.bs.dropdown', function() {
        var endStr = $id('tempoPessoalEnd')?.value;
        var timeStr = '';
        if (endStr) {
            var d = new Date(endStr);
            var min = d.getMinutes();
            var m = Math.round(min / 15) * 15;
            if (m === 60) { m = 0; }
            timeStr = String(d.getHours()).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }
        tempoPessoalPopulateTimeOptions('.tempo-pessoal-end-time-options', timeStr, tempoPessoalApplyNewEndTime);
    });
    $id('tempoPessoalEndTimeToggle')?.addEventListener('shown.bs.dropdown', function() {
        var active = $('.tempo-pessoal-end-time-options .tempo-pessoal-time-opt.active');
        if (active) active.scrollIntoView({ block: 'nearest', behavior: 'instant' });
    });

    $id('tempoPessoalTypeToggleGroup')?.addEventListener('click', function(e) {
        var card = e.target.closest('.tempo-pessoal-type-card');
        if (!card) return;
        $$('.tempo-pessoal-type-card').forEach(function(c) { c.classList.remove('active'); });
        card.classList.add('active');
        $id('tempoPessoalTipo').value = card.dataset.id || '';
        TempoPessoal.applyTypeDuration();
    });

    $id('tempoPessoalForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = $id('tempoPessoalSubmitBtn');
        var originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A guardar...';
        var id = $id('tempoPessoalEventId').value;
        var startVal = $id('tempoPessoalStart').value;
        var endVal = $id('tempoPessoalEnd').value;
        var memberVal = $id('tempoPessoalMembro').value;
        if (currentUserIsAdmin && !memberVal) {
            showToast('Selecione um membro.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            return;
        }
        var payload = {
            personal_time_type_id: $id('tempoPessoalTipo').value || null,
            event_type: 'tempo_pessoal',
            start_at: startVal.replace('T', ' ') + ':00',
            end_at: endVal.replace('T', ' ') + ':00',
            description: $id('tempoPessoalDescricao').value.trim() || null,
            user_id: currentUserIsAdmin ? (memberVal || null) : (memberVal || String(C.authId || ''))
        };
        var url = id ? (C.urlEvents || '') + '/' + id + '/update' : (C.urlEventsStore || '');
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(function(res) {
            if (res.success) {
                bootstrap.Modal.getInstance($id('tempoPessoalModal')).hide();
                if (res.event) {
                    if (id) {
                        var ev = calendar.getEventById(id);
                        if (ev) {
                            ev.setStart(res.event.start);
                            ev.setEnd(res.event.end);
                            ev.setProp('title', res.event.title);
                            ev.setExtendedProp('event_type', 'tempo_pessoal');
                            ev.setExtendedProp('personal_time_type_id', res.event.extendedProps?.personal_time_type_id ?? null);
                            ev.setExtendedProp('personal_time_type', res.event.extendedProps?.personal_time_type ?? null);
                            if (res.event.backgroundColor != null) ev.setProp('backgroundColor', res.event.backgroundColor);
                        }
                    } else {
                        calendar.refetchEvents();
                    }
                }
            } else {
                showToast(res.message || 'Erro ao guardar.', 'error');
            }
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        })
        .catch(function() {
            showToast('Erro de ligação.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    });

    $id('tempoPessoalDeleteBtn').addEventListener('click', function() {
        var id = $id('tempoPessoalEventId').value;
        if (!id || !confirm('Eliminar este tempo pessoal?')) return;
        var btn = this;
        btn.disabled = true;
        fetch((C.urlEvents || '') + '/' + id, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
        })
        .then(r => r.json())
        .then(function(res) {
            if (res.success) {
                var ev = calendar.getEventById(id);
                if (ev) ev.remove();
                bootstrap.Modal.getInstance($id('tempoPessoalModal')).hide();
            } else {
                showToast(res.message || 'Erro ao eliminar.', 'error');
            }
            btn.disabled = false;
        })
        .catch(function() {
            showToast('Erro de ligação.', 'error');
            btn.disabled = false;
        });
    });

    $id('novaMarcacaoTimeToggle')?.addEventListener('show.bs.dropdown', function() {
        var startStr = $id('novaMarcacaoStart')?.value;
        var timeStr = '';
        if (startStr) {
            var d = new Date(startStr);
            var min = d.getMinutes();
            var m = Math.round(min / 15) * 15;
            if (m === 60) { m = 0; }
            timeStr = String(d.getHours()).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }
        NovaMarcacao.populateTimeOptions(timeStr);
    });
    $id('novaMarcacaoTimeToggle')?.addEventListener('shown.bs.dropdown', function() {
        var active = $('.nova-marcacao-time-options .nova-marcacao-time-opt.active');
        if (active) {
            active.scrollIntoView({ block: 'nearest', behavior: 'instant' });
        }
    });

    $id('eventDetailStatusMenu').querySelectorAll('.event-detail-status-opt').forEach(function(opt) {
        opt.addEventListener('click', function(e) {
            e.preventDefault();
            var status = this.dataset.status;
            var labels = STATUS_LABELS;
            var icons = STATUS_ICONS;
            var evId = $id('eventDetailEditId')?.value;
            var previousStatus = $id('eventDetailStatus').value;
            bootstrap.Dropdown.getInstance($id('eventDetailStatusDropdownBtn'))?.hide();
            if (status === 'cancelar') {
                if (previousStatus === 'faltou' || previousStatus === 'cancelado') return;
                window._cancelMarcacaoConfirmed = false;
                window._cancelMarcacaoPreviousStatus = previousStatus;
                window._cancelMarcacaoContext = 'edit';
                window._eventDetailHideForCancelFlow = true;
                $id('cancelMarcacaoEventId').value = evId;
                var total = 0;
                (eventDetailSelectedServices || []).forEach(function(s) { total += parseFloat(s.price) || 0; });
                $id('cancelMarcacaoTotalPrice').textContent = total > 0 ? (total.toFixed(2).replace('.', ',') + ' €') : '0,00 €';
                $id('cancelMarcacaoQueAconteceu').value = 'faltou';
                $id('cancelMarcacaoReason').value = '';
                $id('cancelMarcacaoOutraTexto').value = '';
                $id('cancelMarcacaoOutraWrap').classList.add('d-none');
                $id('cancelMarcacaoRefund').value = '';
                $id('cancelMarcacaoAvisouPrazo').value = '';
                $id('cancelMarcacaoAvisouWrap').classList.add('d-none');
                var editModalInstance = bootstrap.Modal.getOrCreateInstance($id('eventDetailEditModal'));
                editModalInstance.hide();
                bootstrap.Modal.getOrCreateInstance($id('cancelMarcacaoModal')).show();
                return;
            }
            $id('eventDetailStatus').value = status;
            $id('eventDetailStatusLabel').textContent = labels[status] || status;
            var iconEl = $id('eventDetailStatusIcon');
            if (iconEl) {
                var ic = iconEl.querySelector('i');
                if (ic) ic.className = 'me-2 ph ' + (icons[status] || 'ph-clock');
            }
            if (evId && status !== previousStatus) {
                var payload = { status: status };
                fetch((C.urlEvents || '') + '/' + evId + '/status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload)
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        var ev = typeof calendar !== 'undefined' ? calendar.getEventById(evId) : null;
                        if (ev) {
                            ev.setExtendedProp('status', res.status);
                            ev.setExtendedProp('status_icon', res.status_icon);
                            ev.setExtendedProp('status_label', res.status_label);
                        }
                        showToast('Estado atualizado.', 'success');
                    } else {
                        $id('eventDetailStatus').value = previousStatus;
                        $id('eventDetailStatusLabel').textContent = labels[previousStatus] || previousStatus;
                        if (iconEl) {
                            var ic = iconEl.querySelector('i');
                            if (ic) ic.className = 'me-2 ph ' + (icons[previousStatus] || 'ph-clock');
                        }
                        showToast(res.message || 'Erro ao atualizar estado.', 'error');
                    }
                })
                .catch(function() {
                    $id('eventDetailStatus').value = previousStatus;
                    $id('eventDetailStatusLabel').textContent = labels[previousStatus] || previousStatus;
                    if (iconEl) {
                        var ic = iconEl.querySelector('i');
                        if (ic) ic.className = 'me-2 ph ' + (icons[previousStatus] || 'ph-clock');
                    }
                    showToast('Erro de ligação.', 'error');
                });
            }
        });
    });

    $id('cancelMarcacaoReason').addEventListener('change', function() {
        $id('cancelMarcacaoOutraWrap').classList.toggle('d-none', this.value !== 'outra');
    });

    $id('cancelMarcacaoQueAconteceu').addEventListener('change', function() {
        $id('cancelMarcacaoAvisouWrap').classList.toggle('d-none', this.value !== 'cancelado');
    });

    $id('cancelMarcacaoConfirmBtn').addEventListener('click', function() {
        var reasonSelect = $id('cancelMarcacaoReason');
        var reason = reasonSelect.value;
        if (reason === 'outra') {
            reason = $id('cancelMarcacaoOutraTexto').value.trim() || null;
        } else if (reason) {
            reason = reason;
        } else {
            reason = null;
        }
        var status = $id('cancelMarcacaoQueAconteceu').value;
        var refundEl = $id('cancelMarcacaoRefund');
        var refundReserva = refundEl.value === '' ? null : refundEl.value === '1';
        var avisouEl = $id('cancelMarcacaoAvisouPrazo');
        var avisouDentroPrazo = (status === 'cancelado' && avisouEl.value !== '') ? avisouEl.value === '1' : null;
        var evId = $id('cancelMarcacaoEventId').value;
        if (!evId) return;
        var btn = $id('cancelMarcacaoConfirmBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>A cancelar...';
        var payload = {
            status: status,
            cancellation_reason: reason,
            cancellation_type: status,
            refund_reserva: refundReserva,
            avisou_dentro_prazo: avisouDentroPrazo
        };
        fetch((C.urlEvents || '') + '/' + evId + '/status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btn.disabled = false;
            btn.innerHTML = 'Cancelar marcação';
            if (res.success) {
                window._cancelMarcacaoConfirmed = true;
                var ev = typeof calendar !== 'undefined' ? calendar.getEventById(evId) : null;
                if (ev) ev.remove();
                bootstrap.Modal.getInstance($id('cancelMarcacaoModal'))?.hide();
                bootstrap.Modal.getInstance($id('eventDetailEditModal'))?.hide();
                showToast('Marcação cancelada.', 'success');
            } else {
                showToast(res.message || 'Erro ao cancelar.', 'error');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = 'Cancelar marcação';
            showToast('Erro de ligação.', 'error');
        });
    });

    $id('cancelMarcacaoModal').addEventListener('hidden.bs.modal', function() {
        if (window._cancelMarcacaoConfirmed) return;
        var prev = window._cancelMarcacaoPreviousStatus;
        if (prev === undefined) return;
        var labels = { agendado: 'Agendado', confirmado: 'Confirmado', chegou: 'Chegou', iniciado: 'Iniciado', faltou: 'Faltou', cancelado: 'Cancelado' };
        var icons = { agendado: 'ph-clock', confirmado: 'ph-check', chegou: 'ph-map-pin', iniciado: 'ph-play', faltou: 'ph-prohibit', cancelado: 'ph-x-circle' };
        $id('eventDetailStatus').value = prev;
        $id('eventDetailStatusLabel').textContent = labels[prev] || prev;
        var iconEl = $id('eventDetailStatusIcon');
        if (iconEl) {
            var ic = iconEl.querySelector('i');
            if (ic) ic.className = 'me-2 ph ' + (icons[prev] || 'ph-clock');
        }
        var ctx = window._cancelMarcacaoContext || 'edit';
        window._cancelMarcacaoContext = null;
        if (ctx === 'edit') {
            bootstrap.Modal.getOrCreateInstance($id('eventDetailEditModal')).show();
        }
    });

    $id('eventDetailEditModal').addEventListener('hidden.bs.modal', function() {
        if (window._eventDetailHideForCancelFlow) {
            window._eventDetailHideForCancelFlow = false;
            return;
        }
        if (!eventDetailWasSaved) {
            var evId = $id('eventDetailEditId')?.value;
            if (evId && eventDetailOriginalStartAt && eventDetailOriginalEndAt && typeof calendar !== 'undefined') {
                var ev = calendar.getEventById(evId);
                if (ev) {
                    ev.setStart(new Date(eventDetailOriginalStartAt));
                    ev.setEnd(new Date(eventDetailOriginalEndAt));
                }
            }
        }
        eventDetailWasSaved = false;
        eventDetailModalLoading = false;
        eventDetailSelectedClient = null;
        eventDetailSelectedServices = [];
        window._eventDetailPreviousClient = null;
        $id('eventDetailClientCancelBtn').classList.add('d-none');
        eventDetailOriginalStartAt = null;
        eventDetailOriginalEndAt = null;
    });
});