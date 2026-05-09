var C = window.AGENDA_CONFIG || {};

document.addEventListener('DOMContentLoaded', function() {
    const $id = function(id) { return document.getElementById(id); };
    const $ = function(sel) { return document.querySelector(sel); };
    const $$ = function(sel) { return document.querySelectorAll(sel); };
    /** Faixa do total no rodapé do offcanvas editar (#eventDetailTotalRow). */
    function agendaMarcacaoTotalStrip(priceId) {
        var el = $id(priceId);
        if (!el) return null;
        return el.closest('.nova-marcacao-modal-total-strip') || el.closest('#eventDetailTotalRow') || null;
    }

    /** Painel scrollável do offcanvas «Ver / editar marcação» (âncora para quick menus). */
    function agendaEventDetailEditHostEl() {
        return $id('eventDetailEditPanel');
    }

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
    /** Pendente após revert no arrastar/redimensionar (marcação com cliente → modal de confirmação). */
    let agendaDragPending = null;
    /** true quando o modal de confirmação fechou após Guardar/Atualizar com sucesso — evita revert ao arrastar. */
    let agendaDragConfirmSucceeded = false;
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
            : { min: '09:00:00', max: '20:00:00' };
    }
    let agendaSlot24hEnabled = readAgendaSlot24hPreference();
    var initialAgendaSlots = getAgendaSlotRange(agendaSlot24hEnabled);
    function isAgendaMobileViewport() {
        return window.matchMedia('(max-width: 991.98px)').matches;
    }

    function viewSupportsConsultantFilter(viewType) {
        return viewType === 'resourceTimeGridDay' || viewType === 'timeGridWeek' || viewType === 'timeGridThreeDay';
    }
    function isResourceTimeGridDayView(viewType) {
        return viewType === 'resourceTimeGridDay';
    }

    /** Primeiro nome (primeira palavra) para cabeçalhos de coluna das técnicas. */
    function agendaTechnicianFirstName(fullName) {
        if (fullName == null || typeof fullName !== 'string') return '';
        var t = fullName.trim();
        if (!t) return '';
        var parts = t.split(/\s+/u);
        return parts[0] || t;
    }

    const DAYS_SHORT = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    const DAYS_LONG = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    const MONTHS_SHORT = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    const MONTHS_LONG = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

    const STATUS_LABELS = {
        agendado: 'Agendado',
        notificado: 'Notificado',
        confirmado: 'Confirmado',
        chegou: 'Chegou',
        iniciado: 'Iniciado',
        terminado: 'Terminado',
        faltou: 'Faltou',
        cancelado: 'Cancelado',
        anulado: 'Anulado',
        completo: 'Pago'
    };
    const STATUS_ICONS = {
        agendado: 'ph-clock',
        notificado: 'ph-bell agenda-status-icon-notificado',
        confirmado: 'ph-bell agenda-status-icon-confirmado',
        chegou: 'ph-map-pin',
        iniciado: 'ph-play',
        terminado: 'ph-check-circle agenda-status-icon-confirmado',
        faltou: 'ph-prohibit',
        cancelado: 'ph-x-circle',
        anulado: 'ph-x-circle',
        completo: 'ph-check-circle agenda-status-icon-confirmado'
    };
    const STORE_OPEN_HOUR = 9;
    const STORE_CLOSE_HOUR = 20;
    const HOLIDAYS_PT_SET = new Set((C.nationalHolidaysPt || []).map(function(d) { return String(d || '').slice(0, 10); }));
    function agendaDateToYmdLocal(d) {
        if (!(d instanceof Date) || isNaN(d.getTime())) return '';
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function isNationalHolidayPtAtDate(d) {
        var ymd = agendaDateToYmdLocal(d);
        return !!(ymd && HOLIDAYS_PT_SET.has(ymd));
    }
    function applyHolidayClassesToTimeGridColumns() {
        if (!calendarEl) return;
        calendarEl.querySelectorAll('.fc-timegrid-col[data-date]').forEach(function(col) {
            var ymd = String(col.getAttribute('data-date') || '').slice(0, 10);
            if (ymd && HOLIDAYS_PT_SET.has(ymd)) {
                col.classList.add('agenda-day-holiday');
            } else {
                col.classList.remove('agenda-day-holiday');
            }
        });
    }
    function applyMemberUnavailableClassesToTimeGridSlots() {
        if (!calendarEl) return;
        calendarEl.querySelectorAll('[data-slot-date]').forEach(function(slotEl) {
            // Em vista por recurso, o slot/célula costuma trazer data-resource-id.
            // Em vistas sem recurso, usa o consultor filtrado (se existir).
            var host = slotEl.closest('[data-resource-id]');
            var uid = host && host.getAttribute ? String(host.getAttribute('data-resource-id') || '') : '';
            if (!uid) uid = selectedConsultantId || '';
            var dt = slotEl.getAttribute('data-slot-date');
            var d = dt ? new Date(dt) : null;
            var memberUnavailable = !!(uid && d && isOutsideMemberWindowAtInstant(d, uid));
            slotEl.classList.toggle('agenda-slot-member-unavailable', memberUnavailable);
        });
    }
    var agendaMemberSlotClassRaf = null;
    function scheduleApplyMemberUnavailableClasses() {
        if (agendaMemberSlotClassRaf != null) return;
        agendaMemberSlotClassRaf = requestAnimationFrame(function() {
            agendaMemberSlotClassRaf = null;
            applyMemberUnavailableClassesToTimeGridSlots();
        });
    }
    function pad2(n) {
        return String(n).padStart(2, '0');
    }
    function formatLocalYmd(d) {
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }
    function addDaysLocal(d, days) {
        var x = new Date(d.getTime());
        x.setDate(x.getDate() + days);

        return x;
    }
    function generateMemberUnavailableBackgroundEvents(info, viewType) {
        if (!C.memberWeeklySchedules) return [];
        // Apenas em timeGrid (Dia/Semana/3 dias)
        if (!(viewType.indexOf('timeGrid') !== -1 || viewType.indexOf('resourceTimeGrid') !== -1)) return [];

        var out = [];
        var start = new Date(info.start);
        var end = new Date(info.end); // exclusivo
        if (isNaN(start.getTime()) || isNaN(end.getTime())) return out;
        start.setHours(0, 0, 0, 0);
        end.setHours(0, 0, 0, 0);

        var memberIds = [];
        if (viewType === 'resourceTimeGridDay') {
            memberIds = Object.keys(C.memberWeeklySchedules || {});
        } else if (selectedConsultantId) {
            memberIds = [String(selectedConsultantId)];
        } else {
            return out;
        }

        var storeStart = pad2(STORE_OPEN_HOUR) + ':00';
        var storeEnd = pad2(STORE_CLOSE_HOUR) + ':00';
        function clipToStoreWindow(segStart, segEnd) {
            var s = segStart < storeStart ? storeStart : segStart;
            var e = segEnd > storeEnd ? storeEnd : segEnd;
            if (s >= e) return null;

            return { start: s, end: e };
        }

        memberIds.forEach(function(uid) {
            var sched = C.memberWeeklySchedules[String(uid)];
            if (!sched) return;
            var day = new Date(start.getTime());
            while (day < end) {
                var key = weekKeyFromDate(day);
                var cfg = sched[key];
                var ymd = formatLocalYmd(day);
                var nextYmd = formatLocalYmd(addDaysLocal(day, 1));
                var baseId = 'member-unavail|' + uid + '|' + ymd;
                if (!cfg || !cfg.enabled) {
                    var fullDayClipped = clipToStoreWindow('00:00', '24:00');
                    if (fullDayClipped) {
                        out.push({
                            id: baseId + '|all',
                            start: ymd + 'T' + fullDayClipped.start + ':00',
                            end: ymd + 'T' + fullDayClipped.end + ':00',
                            display: 'background',
                            className: ['agenda-member-unavailable-bg'],
                            resourceId: viewType === 'resourceTimeGridDay' ? String(uid) : undefined,
                        });
                    }
                    day = addDaysLocal(day, 1);
                    continue;
                }

                var startTime = String(cfg.start || '00:00');
                var endTime = String(cfg.end || '24:00');
                if (startTime > '00:00') {
                    var beforeClipped = clipToStoreWindow('00:00', startTime);
                    if (beforeClipped) {
                        out.push({
                            id: baseId + '|before',
                            start: ymd + 'T' + beforeClipped.start + ':00',
                            end: ymd + 'T' + beforeClipped.end + ':00',
                            display: 'background',
                            className: ['agenda-member-unavailable-bg'],
                            resourceId: viewType === 'resourceTimeGridDay' ? String(uid) : undefined,
                        });
                    }
                }
                if (endTime < '24:00') {
                    var afterClipped = clipToStoreWindow(endTime, '24:00');
                    if (afterClipped) {
                        out.push({
                            id: baseId + '|after',
                            start: ymd + 'T' + afterClipped.start + ':00',
                            end: ymd + 'T' + afterClipped.end + ':00',
                            display: 'background',
                            className: ['agenda-member-unavailable-bg'],
                            resourceId: viewType === 'resourceTimeGridDay' ? String(uid) : undefined,
                        });
                    }
                }
                day = addDaysLocal(day, 1);
            }
        });

        return out;
    }
    function getMinutesFromDate(d) {
        return (d.getHours() * 60) + d.getMinutes();
    }
    function isOutsideStoreHoursAtDate(d) {
        if (!(d instanceof Date) || isNaN(d.getTime())) return false;
        var mins = getMinutesFromDate(d);
        return mins < (STORE_OPEN_HOUR * 60) || mins >= (STORE_CLOSE_HOUR * 60);
    }
    /** Parse YYYY-MM-DDTHH:mm (sem timezone) como hora local — evita bugs de new Date() em alguns browsers. */
    function parseAgendaLocalDateTime(str) {
        if (!str || typeof str !== 'string') return null;
        var t = str.trim();
        if (t.endsWith('Z') || /[+-]\d{2}:?\d{2}$/.test(t)) {
            var dz = new Date(t);
            return isNaN(dz.getTime()) ? null : dz;
        }
        var m = t.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{1,2}):(\d{2})/);
        if (!m) {
            var d = new Date(str);
            return isNaN(d.getTime()) ? null : d;
        }
        return new Date(
            parseInt(m[1], 10),
            parseInt(m[2], 10) - 1,
            parseInt(m[3], 10),
            parseInt(m[4], 10),
            parseInt(m[5], 10),
            0,
            0
        );
    }

    /** Início/fim do modal de edição: sempre hora local YYYY-MM-DDTHH:mm (evita misturar ISO Z num campo e local noutro). */
    function agendaFormatLocalDateTimeForInput(d) {
        if (!(d instanceof Date) || isNaN(d.getTime())) return '';
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + 'T' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    /**
     * Valor do input local (sem Z) → ISO UTC para o Laravel.
     * Sem isto, strings tipo "2025-08-15T18:00" podem ser interpretadas como UTC no servidor e o evento salta +1h na hora local (ex.: Portugal no verão).
     */
    function agendaLocalInputToUtcIso(str) {
        if (!str || typeof str !== 'string') return str;
        var d = parseAgendaLocalDateTime(str);
        if (!d || isNaN(d.getTime())) {
            d = new Date(str);
            if (isNaN(d.getTime())) return str;
        }
        return d.toISOString();
    }
    function intersectsOutsideStoreHours(start, end) {
        if (!(start instanceof Date) || isNaN(start.getTime())) return false;
        if (!(end instanceof Date) || isNaN(end.getTime())) {
            return isOutsideStoreHoursAtDate(start);
        }
        /* Fim antes do início: intervalo inválido — manter aviso */
        if (end.getTime() < start.getTime()) return true;
        /* Fim = início (duração 0, ex. ainda sem serviços): só o instante de início importa */
        if (end.getTime() === start.getTime()) {
            return isOutsideStoreHoursAtDate(start);
        }
        if (start.toDateString() !== end.toDateString()) return true;
        var startM = getMinutesFromDate(start);
        var endM = getMinutesFromDate(end);
        return startM < (STORE_OPEN_HOUR * 60) || endM > (STORE_CLOSE_HOUR * 60);
    }
    var WEEKDAY_KEYS_JS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    function weekKeyFromDate(d) {
        if (!(d instanceof Date) || isNaN(d.getTime())) return '';
        return WEEKDAY_KEYS_JS[d.getDay()];
    }
    function timeStrToMinutes(str) {
        var p = String(str || '').split(':');
        if (p.length < 2) return 0;
        return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
    }
    function isOutsideMemberWindowAtInstant(d, userId) {
        if (!userId || !C.memberWeeklySchedules) return false;
        var sched = C.memberWeeklySchedules[String(userId)];
        if (!sched) return false;
        if (!(d instanceof Date) || isNaN(d.getTime())) return false;
        var key = weekKeyFromDate(d);
        var day = sched[key];
        if (!day || !day.enabled) return true;
        var mins = getMinutesFromDate(d);
        var sm = timeStrToMinutes(day.start);
        var em = timeStrToMinutes(day.end);
        return mins < sm || mins >= em;
    }
    function resolveSlotUserId(arg) {
        var uid = '';
        if (arg && arg.resource && arg.resource.id != null) {
            uid = String(arg.resource.id);
        }
        if (!uid && arg && arg.el && typeof arg.el.closest === 'function') {
            var col = arg.el.closest('[data-resource-id]');
            if (col && col.getAttribute) {
                uid = String(col.getAttribute('data-resource-id') || '');
            }
        }
        if (!uid) uid = selectedConsultantId || '';

        return uid;
    }
    function intersectsOutsideMemberHours(start, end, userId) {
        if (!userId || !C.memberWeeklySchedules) return false;
        var sched = C.memberWeeklySchedules[String(userId)];
        if (!sched) return false;
        if (!(start instanceof Date) || isNaN(start.getTime())) return false;
        if (!(end instanceof Date) || isNaN(end.getTime())) {
            return isOutsideMemberWindowAtInstant(start, userId);
        }
        if (end.getTime() < start.getTime()) return true;
        if (start.toDateString() !== end.toDateString()) return true;
        if (end.getTime() === start.getTime()) {
            return isOutsideMemberWindowAtInstant(start, userId);
        }
        var key = weekKeyFromDate(start);
        var day = sched[key];
        if (!day || !day.enabled) return true;
        var sm = timeStrToMinutes(day.start);
        var em = timeStrToMinutes(day.end);
        var startM = getMinutesFromDate(start);
        var endM = getMinutesFromDate(end);
        return startM < sm || endM > em;
    }
    function shouldWarnOutOfHours(start, end, userId) {
        if (intersectsOutsideStoreHours(start, end)) return true;
        return intersectsOutsideMemberHours(start, end, userId);
    }
    function toggleOutOfHoursWarning(elId, isOutside, wrapId) {
        var el = $id(elId);
        if (!el) return;
        if (isOutside) {
            el.classList.remove('d-none');
        } else {
            el.classList.add('d-none');
        }
        if (wrapId) {
            var wrap = $id(wrapId);
            if (wrap) {
                if (isOutside) wrap.classList.remove('d-none');
                else wrap.classList.add('d-none');
            }
        }
    }
    function updateTempoPessoalOutOfHoursWarning() {
        var startStr = $id('tempoPessoalStart')?.value || '';
        var endStr = $id('tempoPessoalEnd')?.value || '';
        var start = startStr ? parseAgendaLocalDateTime(startStr) : null;
        var end = endStr ? parseAgendaLocalDateTime(endStr) : null;
        var memberId = $id('tempoPessoalMembro')?.value || '';
        if (!memberId && !currentUserIsAdmin) {
            memberId = String(C.authId || '');
        }
        toggleOutOfHoursWarning('tempoPessoalHorarioAviso', shouldWarnOutOfHours(start, end, memberId));
    }
    function updateEventDetailOutOfHoursWarning() {
        var startStr = $id('eventDetailEditStart')?.value || '';
        var endStr = $id('eventDetailEditEnd')?.value || '';
        var start = startStr ? parseAgendaLocalDateTime(startStr) : null;
        var end = endStr ? parseAgendaLocalDateTime(endStr) : null;
        var userId = $id('eventDetailEditUserId')?.value || '';
        toggleOutOfHoursWarning('eventDetailHorarioAviso', shouldWarnOutOfHours(start, end, userId), 'eventDetailHorarioAvisoWrap');
    }

    /** Na vista Dia (recursos), ao mudar o profissional no offcanvas, mover o evento para a coluna certa. */
    function syncEventDetailCalendarEventToSelectedMember() {
        if (typeof calendar === 'undefined' || !calendar) return;
        if (!isResourceTimeGridDayView(calendar.view.type)) return;
        var evId = $id('eventDetailEditId') && $id('eventDetailEditId').value;
        if (!evId) return;
        var ev = calendar.getEventById(evId);
        if (!ev) return;
        var extEv = ev.extendedProps || {};
        if ((extEv.event_type || '') !== 'marcacao') return;
        var uid = ($id('eventDetailOcMember') && $id('eventDetailOcMember').value || '').trim();
        if (eventDetailCurrentData) {
            eventDetailCurrentData.user_id = uid || null;
        }
        ev.setExtendedProp('user_id', uid || null);
        var newColor = null;
        var newName = null;
        if (uid && allResources && allResources.length) {
            var resObj = allResources.find(function(r) { return String(r.id) === String(uid); });
            if (resObj) {
                newColor = resObj.extendedProps ? resObj.extendedProps.color : null;
                newName = resObj.title || null;
            }
        }
        if (newName) ev.setExtendedProp('user_name', newName);
        if (typeof calendar.getResourceById === 'function' && typeof ev.setResources === 'function' && uid) {
            var rr = calendar.getResourceById(String(uid));
            if (rr) ev.setResources([rr]);
        }
        if (newColor) {
            ev.setProp('backgroundColor', newColor);
            var domEl = document.querySelector('[data-event-id="' + String(ev.id) + '"]');
            if (domEl) domEl.style.setProperty('background-color', newColor, 'important');
        }
        scheduleStackedEventClassRefresh();
    }

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
        wrapper.style.width = 'calc(' + colRect.width + 'px - 6px)';
        wrapper.style.height = 'calc(' + slotRect.height + 'px - 4px)';
        wrapper.style.margin = '2px 2px 0 3px';
        wrapper.style.zIndex = '4';
        wrapper.style.pointerEvents = 'none';
        var timeSpan = document.createElement('span');
        timeSpan.className = 'agenda-cell-time-overlay';
        timeSpan.textContent = timeLabel;
        wrapper.appendChild(timeSpan);
        if (calendarEl) calendarEl.appendChild(wrapper);
        else document.body.appendChild(wrapper);
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

        /** No mobile, o browser dispara um `click` sintético após o toque que abriu o menu; ignoramos esse primeiro “fora” para não fechar logo. */
        var suppressFirstOutsideClose = true;
        function closeHandler(e) {
            if (menu.contains(e.target)) return;
            if (suppressFirstOutsideClose) {
                suppressFirstOutsideClose = false;
                return;
            }
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
        if (currentStatus === 'faltou' || currentStatus === 'cancelado' || currentStatus === 'anulado') {
            return;
        }
        var statusOpts = [
            { status: 'agendado', label: 'Agendado', icon: 'ph ph-clock' },
            { status: 'notificado', label: 'Notificado', icon: 'ph ph-bell agenda-status-icon-notificado' },
            { status: 'confirmado', label: 'Confirmado', icon: 'ph ph-bell agenda-status-icon-confirmado' },
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
        var suppressFirstOutsideCloseStatus = true;
        function closeHandler(e) {
            if (menu.contains(e.target)) return;
            if (suppressFirstOutsideCloseStatus) {
                suppressFirstOutsideCloseStatus = false;
                return;
            }
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
            if (o.isCancelAction && (currentStatus === 'faltou' || currentStatus === 'cancelado' || currentStatus === 'anulado')) return;
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
                    $id('cancelMarcacaoNotifyClient').checked = false;
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
        if (agendaEventQuickviewShowTimeout) {
            clearTimeout(agendaEventQuickviewShowTimeout);
            agendaEventQuickviewShowTimeout = null;
        }
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
            notificado: 'ph ph-bell agenda-status-icon-notificado',
            confirmado: 'ph ph-bell agenda-status-icon-confirmado',
            chegou: 'ph ph-map-pin',
            iniciado: 'ph ph-play',
            terminado: 'ph ph-check-circle agenda-status-icon-confirmado',
            faltou: 'ph ph-prohibit',
            cancelado: 'ph ph-x-circle',
            completo: 'ph ph-check-circle agenda-status-icon-confirmado'
        };
        var isTempoPessoal = (ext.event_type || '') === 'tempo_pessoal';
        var personalTimeType = ext.personal_time_type || {};
        var status = ext.status || 'agendado';
        var statusLabel = isTempoPessoal ? 'Tempo pessoal' : (statusLabels[status] || status);
        var statusIcon = isTempoPessoal ? null : (statusIcons[status] || 'ph ph-clock');
        var invoiceSettled = !!ext.invoice_settled;
        var hasInvoice = !!ext.has_invoice;
        if (!isTempoPessoal && hasInvoice && !invoiceSettled) statusIcon = 'ph ph-clock';
        if (!isTempoPessoal && invoiceSettled) statusIcon = 'ph ph-check-circle';
        var start = event.start;
        var end = event.end;
        var fmt = function(d) { return d ? (String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')) : ''; };
        var startStr = fmt(start);
        var endStr = fmt(end);
        var timeStr = startStr && endStr ? (startStr + ' - ' + endStr) : (startStr || '…');
        var dayDateStr = start
            ? (DAYS_LONG[start.getDay()] + ', ' + start.getDate() + ' ' + MONTHS_LONG[start.getMonth()])
            : '…';
        var clientName = (ext.client_name || event.title || '…').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        var clientPhoneRaw = ext.client_formatted_phone || ext.client_phone || '';
        var clientPhone = String(clientPhoneRaw || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        var clientAvatarUrl = ext.client_avatar_url || '';
        var userName = (ext.user_name || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        var eventServices = ext.event_services || [];
        var totalPrice = 0;
        eventServices.forEach(function(s) {
            var basePrice = parseFloat(s.price) || 0;
            var extras = Array.isArray(s.extras) ? s.extras : [];
            var extrasTotal = 0;
            extras.forEach(function(ex) {
                var exPrice = parseFloat(ex.price) || 0;
                extrasTotal += exPrice;
            });
            totalPrice += basePrice + extrasTotal;
        });
        var totalPriceStr = totalPrice > 0 ? (totalPrice.toFixed(2).replace('.', ',') + ' €') : '';
        var totalAmount = parseFloat(ext.total_amount);
        if (isNaN(totalAmount)) totalAmount = totalPrice;
        var totalAmountStr = (totalAmount || 0).toFixed(2).replace('.', ',') + ' €';
        var bookingPaidAmount = parseFloat(ext.booking_paid_amount || 0) || 0;
        var finalPaidAmount = Math.max(0, (totalAmount || 0) - bookingPaidAmount);
        var amountDue = parseFloat(ext.amount_due);
        if (isNaN(amountDue)) amountDue = Math.max(0, totalAmount - bookingPaidAmount);
        var formatMinutes = function(minutesRaw) {
            var minutes = parseInt(minutesRaw || 0, 10) || 0;
            var h = Math.floor(minutes / 60);
            var m = minutes % 60;
            if (h > 0 && m > 0) return h + 'h' + m + 'min';
            if (h > 0) return h + 'h';
            return m + 'min';
        };

        var qv = $id('agendaEventQuickview');
        if (!qv) return;
        qv.innerHTML = '';

        var header = document.createElement('div');
        header.className = 'agenda-quickview-header';
        var timeSpan = document.createElement('span');
        timeSpan.className = 'agenda-quickview-time';
        timeSpan.innerHTML = '<span class="agenda-quickview-date-line">' + dayDateStr + '</span><span class="agenda-quickview-time-line">' + timeStr + '</span>';
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
            var typeIcon = personalTimeType.icon ? ('ph ' + personalTimeType.icon) : '';
            typeLeft.innerHTML = (typeIcon ? ('<i class="' + typeIcon + ' me-2"></i>') : '') + '<span class="agenda-quickview-service-name">' + (personalTimeType.name || event.title || '…').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>';
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
        var clientTextWrap = document.createElement('div');
        clientTextWrap.className = 'agenda-quickview-client-text';
        var nameSpan = document.createElement('span');
        nameSpan.className = 'agenda-quickview-client-name';
        nameSpan.textContent = clientName;
        clientTextWrap.appendChild(nameSpan);
        if (clientPhone) {
            var phoneSpan = document.createElement('span');
            phoneSpan.className = 'agenda-quickview-client-phone';
            phoneSpan.textContent = clientPhone;
            clientTextWrap.appendChild(phoneSpan);
        }
        clientRow.appendChild(clientTextWrap);
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
                nameEl.textContent = (s.name || '…').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                left.appendChild(nameEl);
                // Duração deste serviço (como nos extras, apenas duração)
                var meta = document.createElement('div');
                meta.className = 'agenda-quickview-service-meta';
                var metaParts = [];
                if (s.duration) {
                    metaParts.push(formatMinutes(s.duration));
                } else if (s.formatted_duration) {
                    metaParts.push(s.formatted_duration);
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
                    if (ex.duration) {
                        extraMetaParts.push(formatMinutes(ex.duration));
                    } else if (ex.formatted_duration) {
                        extraMetaParts.push(ex.formatted_duration);
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

        if (!isTempoPessoal) {
            var totalRow = document.createElement('div');
            totalRow.className = 'agenda-quickview-service-row';
            totalRow.style.marginTop = '0.5rem';
            totalRow.style.paddingTop = '0.5rem';
            totalRow.style.borderTop = '1px solid var(--border-color, rgba(0,0,0,0.1))';
            var totalLeft = document.createElement('div');
            totalLeft.className = 'agenda-quickview-service-left';
            totalLeft.textContent = 'Total';
            totalRow.appendChild(totalLeft);
            var totalVal = document.createElement('div');
            totalVal.className = 'agenda-quickview-service-price';
            totalVal.textContent = totalAmountStr;
            totalRow.appendChild(totalVal);
            body.appendChild(totalRow);

            if (bookingPaidAmount > 0.00001) {
                var reservaRow = document.createElement('div');
                reservaRow.className = 'agenda-quickview-service-row';
                var reservaLeft = document.createElement('div');
                reservaLeft.className = 'agenda-quickview-service-left';
                reservaLeft.innerHTML = '<span class="agenda-quickview-inline-badge agenda-quickview-inline-badge-reserva">Reserva</span>';
                reservaRow.appendChild(reservaLeft);
                var reservaVal = document.createElement('div');
                reservaVal.className = 'agenda-quickview-service-price';
                reservaVal.textContent = bookingPaidAmount.toFixed(2).replace('.', ',') + ' €';
                reservaRow.appendChild(reservaVal);
                body.appendChild(reservaRow);
            }

            if (invoiceSettled) {
                var paidRow = document.createElement('div');
                paidRow.className = 'agenda-quickview-service-row';
                var paidLeft = document.createElement('div');
                paidLeft.className = 'agenda-quickview-service-left';
                paidLeft.innerHTML = '<span class="agenda-quickview-inline-badge agenda-quickview-inline-badge-paid">Pagamento final</span>';
                paidRow.appendChild(paidLeft);
                var paidVal = document.createElement('div');
                paidVal.className = 'agenda-quickview-service-price';
                paidVal.textContent = finalPaidAmount.toFixed(2).replace('.', ',') + ' €';
                paidRow.appendChild(paidVal);
                body.appendChild(paidRow);
            } else {
                if (bookingPaidAmount > 0.00001) {
                    var dueRow = document.createElement('div');
                    dueRow.className = 'agenda-quickview-service-row';
                    var dueLeft = document.createElement('div');
                    dueLeft.className = 'agenda-quickview-service-left';
                    dueLeft.textContent = 'Falta pagar';
                    dueRow.appendChild(dueLeft);
                    var dueVal = document.createElement('div');
                    dueVal.className = 'agenda-quickview-service-price';
                    dueVal.textContent = amountDue.toFixed(2).replace('.', ',') + ' €';
                    dueRow.appendChild(dueVal);
                    body.appendChild(dueRow);
                }
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
        isCustomTypeSelected: function() {
            var selectedTypeId = ($id('tempoPessoalTipo')?.value || '').trim();
            return selectedTypeId === '';
        },
        syncCustomTitleField: function(prefillTitle) {
            var wrap = $id('tempoPessoalTituloWrap');
            var input = $id('tempoPessoalTitulo');
            if (!wrap || !input) return;
            var isCustom = TempoPessoal.isCustomTypeSelected();
            wrap.classList.toggle('d-none', !isCustom);
            input.disabled = !isCustom;
            if (isCustom) {
                if (typeof prefillTitle === 'string') input.value = prefillTitle;
                input.focus();
            } else {
                input.value = '';
            }
        },
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
            updateTempoPessoalOutOfHoursWarning();
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
            updateTempoPessoalOutOfHoursWarning();
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
            updateTempoPessoalOutOfHoursWarning();
            var toggle = $id('tempoPessoalEndTimeToggle');
            if (toggle && bootstrap.Dropdown) bootstrap.Dropdown.getInstance(toggle)?.hide();
        },
        syncHiddenFromInputs: function() {
            var dateStr = $id('tempoPessoalDateInput')?.value || '';
            var startTimeStr = ($id('tempoPessoalStartTimeToggle')?.textContent || '').trim();
            var endTimeStr = ($id('tempoPessoalEndTimeToggle')?.textContent || '').trim();
            if (!dateStr || !startTimeStr || startTimeStr === '…') return;
            if (!endTimeStr || endTimeStr === '…') endTimeStr = startTimeStr;
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
            updateTempoPessoalOutOfHoursWarning();
        }
    };

    function openTempoPessoalModal(startStr, endStr, resourceId) {
        $id('tempoPessoalEventId').value = '';
        $id('tempoPessoalStart').value = startStr || '';
        $id('tempoPessoalEnd').value = endStr || '';
        $id('tempoPessoalMembro').value = resourceId ? String(resourceId) : (currentUserIsAdmin ? '' : String(C.authId || ''));
        $id('tempoPessoalTitulo').value = '';
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
        TempoPessoal.syncCustomTitleField();
        updateTempoPessoalOutOfHoursWarning();
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
            selectedCard = Array.prototype.find.call(cards, function(c) { return (c.dataset.name || '').trim() === (data.title || '').trim(); });
        }
        if (!selectedCard && !typeId) {
            selectedCard = Array.prototype.find.call(cards, function(c) { return c.dataset.isCustom === '1'; }) || null;
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
        TempoPessoal.syncCustomTitleField(data.title || '');
        toggleOutOfHoursWarning('tempoPessoalHorarioAviso', false);
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
            updateTempoPessoalOutOfHoursWarning();
        }
    }

    var agendaMembersServicesUrl = (C.agendaMembersServicesUrl || '');
    var agendaClientsUrl = (C.agendaClientsUrl || '');

    function agendaEscAttr(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    }
    /** Telefone legível (servidor: formatted_phone); fallback para E.164 guardado. */
    function agendaClientPhoneLabel(c) {
        if (!c) return '…';
        if (c.formatted_phone) return c.formatted_phone;
        return c.phone || '…';
    }
    function agendaClientNifLabel(c) {
        if (!c) return 'Sem NIF';
        var nif = String(c.nif || '').trim();
        return nif !== '' ? ('NIF ' + nif) : 'Sem NIF';
    }
    /** Mensagem a partir da resposta 422 do POST de cliente rápido (agenda). */
    function agendaStoreClientCreateErrorMessage(data) {
        var msg = 'Erro ao criar cliente.';
        if (!data) return msg;
        if (data.errors) {
            var e = data.errors;
            if (e.phone && e.phone[0]) return e.phone[0];
            if (e.email && e.email[0]) return e.email[0];
            if (e.name && e.name[0]) return e.name[0];
        }
        if (data.message) return data.message;
        return msg;
    }
    var eventDetailSelectedServices = [];
    var eventDetailCurrentData = null;

    var eventDetailOriginalStartAt = null;
    var eventDetailOriginalEndAt = null;
    var eventDetailWasSaved = false;
    /** Se true, a duração do evento segue só a soma dos serviços+extras (heurística anti-duplicação desligada). */
    var eventDetailTrustServicesSumForDuration = false;
    function eventDetailMarkServiceListMutated() {
        eventDetailTrustServicesSumForDuration = true;
    }

    function agendaIsoTimesEqual(a, b) {
        if (a == null && b == null) return true;
        if (a == null || b == null) return false;
        var ta = new Date(a).getTime();
        var tb = new Date(b).getTime();
        if (!isNaN(ta) && !isNaN(tb)) return ta === tb;
        return String(a) === String(b);
    }
    function eventDetailScheduleTimesChanged(payload) {
        if (!eventDetailOriginalStartAt && !eventDetailOriginalEndAt) return false;
        return !agendaIsoTimesEqual(payload.start_at, eventDetailOriginalStartAt) ||
            !agendaIsoTimesEqual(payload.end_at, eventDetailOriginalEndAt);
    }
    function getEventDetailNotifyExtendedProps() {
        var name = (eventDetailSelectedClient && eventDetailSelectedClient.name) || (eventDetailCurrentData && eventDetailCurrentData.client_name) || 'o cliente';
        var email = (eventDetailSelectedClient && eventDetailSelectedClient.email) || (eventDetailCurrentData && eventDetailCurrentData.client_email) || '';
        var hasEmail = !!(email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email).trim()));
        return { client_name: name, client_has_email: hasEmail };
    }

    /** Soma (min) de duração base + extras por linha — pode duplicar o tempo real se o pivot já incluir extras. */
    function eventDetailServicesPartsSumMinutes() {
        return eventDetailSelectedServices.reduce(function(sum, s) {
            var d = (parseInt(s.duration, 10) || 0) + (s.extras || []).reduce(function(s2, e) {
                return s2 + (parseInt(e.duration, 10) || 0);
            }, 0);
            return sum + d;
        }, 0);
    }

    /**
     * Duração efetiva para fim da marcação / guardar: alinha ao intervalo start–end quando a soma das partes
     * é o dobro do slot (bug comum: pivot na BD já com tempo total e extras a somar outra vez).
     */
    function eventDetailEffectiveDurationMinutes() {
        var sumDur = eventDetailServicesPartsSumMinutes();
        if (eventDetailTrustServicesSumForDuration) {
            return sumDur;
        }
        var startEl = $id('eventDetailEditStart');
        var endEl = $id('eventDetailEditEnd');
        if (!startEl || !endEl) return sumDur;
        var startStr = startEl.value;
        var endStr = endEl.value;
        if (!startStr || !endStr) return sumDur;
        var slotDur = Math.round((new Date(endStr).getTime() - new Date(startStr).getTime()) / 60000);
        if (slotDur < 1 || isNaN(slotDur)) return sumDur;
        var origSlot = 0;
        if (eventDetailOriginalStartAt && eventDetailOriginalEndAt) {
            origSlot = Math.round((new Date(eventDetailOriginalEndAt).getTime() - new Date(eventDetailOriginalStartAt).getTime()) / 60000);
        }
        if (origSlot > 0 && slotDur === origSlot && Math.abs(sumDur - 2 * origSlot) < 1) {
            return origSlot;
        }
        if (Math.abs(sumDur - 2 * slotDur) < 1) {
            return slotDur;
        }
        return sumDur;
    }

    var eventDetailExistingSale = null;
    var eventDetailBookingPaidAmount = 0;

        function syncEventDetailFaturaButtons() {
        var wrap = $id('eventDetailFaturasWrap');
        if (!wrap) return;
        wrap.innerHTML = '';
        var list = (eventDetailCurrentData && Array.isArray(eventDetailCurrentData.sales_invoices)) ? eventDetailCurrentData.sales_invoices : [];
        if (!list.length) {
            wrap.classList.add('d-none');
            return;
        }
        if (list.length === 1) {
            var inv = list[0];
            var a = document.createElement('a');
            a.href = inv.vendus_url || inv.pdf_url || '#';
            a.target = '_blank';
            a.rel = 'noopener';
            a.className = 'btn btn-outline-primary btn-sm event-detail-invoice-btn d-inline-flex align-items-center justify-content-center';
            a.title = 'Ver fatura';
            a.setAttribute('aria-label', 'Ver fatura');
            a.innerHTML = '<i class="ph ph-receipt"></i>';
            if (inv.amount != null && !isNaN(parseFloat(inv.amount))) {
                var amountTip = parseFloat(inv.amount).toFixed(2).replace('.', ',') + ' €';
                a.title = 'Ver fatura (' + amountTip + ')';
            }
            wrap.appendChild(a);
            wrap.classList.remove('d-none');
            return;
        }

        var dropup = document.createElement('div');
        dropup.className = 'dropup';

        var toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'btn btn-outline-primary btn-sm event-detail-invoice-btn dropdown-toggle d-inline-flex align-items-center justify-content-center';
        toggleBtn.setAttribute('data-bs-toggle', 'dropdown');
        toggleBtn.setAttribute('aria-expanded', 'false');
        toggleBtn.setAttribute('aria-label', 'Ver faturas');
        toggleBtn.title = 'Ver faturas';
        toggleBtn.innerHTML = '<i class="ph ph-receipt"></i>';
        dropup.appendChild(toggleBtn);

        var menu = document.createElement('div');
        menu.className = 'dropdown-menu dropdown-menu-end';
        list.forEach(function (inv) {
            var item = document.createElement('a');
            item.href = inv.vendus_url || inv.pdf_url || '#';
            item.target = '_blank';
            item.rel = 'noopener';
            item.className = 'dropdown-item';
            if (inv.scope === 'booking_reserva') {
                item.textContent = 'Reserva';
            } else if (inv.scope === 'caixa_liquidacao') {
                item.textContent = 'Pagamento final';
            } else {
                item.textContent = inv.label || 'Fatura';
            }
            menu.appendChild(item);
        });
        dropup.appendChild(menu);
        wrap.appendChild(dropup);
        wrap.classList.remove('d-none');
    }

        function setEventDetailPaymentAndReadOnly(existingSale, eventType, servicesCount) {
        var payBtn = $id('eventDetailPaymentBtn');
        var revertBtn = $id('eventDetailReverterFaturaBtn');
        var saveBtn = $id('eventDetailSaveBtn');
        var closeWithoutSaveBtn = $id('eventDetailCloseWithoutSaveBtn');
        var status = eventDetailCurrentData ? String(eventDetailCurrentData.status || '') : '';
        var stLocked = status === 'completo' || status === 'faltou' || status === 'cancelado' || status === 'anulado';
        var isPartialSale = !!(existingSale && existingSale.is_partial);
        var readonly = (!!existingSale && !isPartialSale) || !!stLocked;
        if (payBtn) payBtn.classList.toggle('d-none', readonly || eventType !== 'marcacao' || servicesCount === 0);
        syncEventDetailFaturaButtons();
        if (revertBtn) {
            revertBtn.classList.toggle('d-none', !existingSale || isPartialSale);
            if (existingSale && !isPartialSale) revertBtn.dataset.saleId = String(existingSale.id);
        }
        if (saveBtn) {
            saveBtn.disabled = readonly;
            saveBtn.classList.toggle('d-none', readonly);
        }
        if (closeWithoutSaveBtn) closeWithoutSaveBtn.classList.toggle('d-none', readonly);
        var statusWrap = $id('eventDetailStatusDropdownWrap');
        var observacoes = $id('eventDetailOcObs');
        var addMoreBtn = $id('eventDetailOcAddMoreServicesBtn');
        var addMoreWrap = $id('eventDetailOcAddMoreServicesWrap');
        var ocMember = $id('eventDetailOcMember');
        var ocDate = $id('eventDetailOcDate');
        var ocTime = $id('eventDetailOcTime');
        var ocServiceWrap = $id('eventDetailOcServiceSelectWrap');
        var ocClientEdit = $id('eventDetailOcClientEditBtn');
        var ocClientCancelEdit = $id('eventDetailOcClientCancelEditBtn');
        var ocClientNifEdit = $id('eventDetailOcClientNifEditBtn');
        var ocClientNifInput = $id('eventDetailOcClientNifInput');
        var ocClientNifSave = $id('eventDetailOcClientNifSaveBtn');
        var ocClientNifCancel = $id('eventDetailOcClientNifCancelBtn');
        var ocClientProfile = $id('eventDetailOcClientProfileLink');
        var ocClientTabs = $id('eventDetailOcClientTabs');
        var ocNotSel = $id('eventDetailOcClientNotSelectedWrap');
        if (statusWrap) statusWrap.style.pointerEvents = readonly ? 'none' : '';
        if (observacoes) observacoes.disabled = readonly;
        if (addMoreBtn) { addMoreBtn.disabled = readonly; addMoreBtn.style.display = readonly ? 'none' : ''; }
        if (addMoreWrap) addMoreWrap.style.display = readonly ? 'none' : '';
        if (ocMember) ocMember.disabled = readonly;
        if (ocDate) ocDate.disabled = readonly;
        if (ocTime) ocTime.disabled = readonly;
        if (eventDetailOcChoicesInstances.member && typeof eventDetailOcChoicesInstances.member.disable === 'function') {
            if (readonly) {
                eventDetailOcChoicesInstances.member.disable();
            } else if (typeof eventDetailOcChoicesInstances.member.enable === 'function') {
                eventDetailOcChoicesInstances.member.enable();
            }
        }
        if (eventDetailOcChoicesInstances.time && typeof eventDetailOcChoicesInstances.time.disable === 'function') {
            if (readonly) {
                eventDetailOcChoicesInstances.time.disable();
            } else if (typeof eventDetailOcChoicesInstances.time.enable === 'function') {
                eventDetailOcChoicesInstances.time.enable();
            }
        }
        if (eventDetailOcChoicesInstances.service && typeof eventDetailOcChoicesInstances.service.disable === 'function') {
            if (readonly) {
                eventDetailOcChoicesInstances.service.disable();
            } else if (typeof eventDetailOcChoicesInstances.service.enable === 'function') {
                eventDetailOcChoicesInstances.service.enable();
            }
        }
        if (ocMember && ocMember.nextElementSibling && ocMember.nextElementSibling.classList && ocMember.nextElementSibling.classList.contains('choices')) {
            ocMember.nextElementSibling.style.pointerEvents = readonly ? 'none' : '';
            ocMember.nextElementSibling.style.opacity = readonly ? '0.7' : '';
        }
        if (ocTime && ocTime.nextElementSibling && ocTime.nextElementSibling.classList && ocTime.nextElementSibling.classList.contains('choices')) {
            ocTime.nextElementSibling.style.pointerEvents = readonly ? 'none' : '';
            ocTime.nextElementSibling.style.opacity = readonly ? '0.7' : '';
        }
        if (eventDetailOcDateFlatpickr) {
            try {
                eventDetailOcDateFlatpickr.set('clickOpens', !readonly);
            } catch (e) { /* ignore */ }
            if (eventDetailOcDateFlatpickr.altInput) {
                eventDetailOcDateFlatpickr.altInput.readOnly = !!readonly;
                eventDetailOcDateFlatpickr.altInput.style.pointerEvents = readonly ? 'none' : '';
                eventDetailOcDateFlatpickr.altInput.style.opacity = readonly ? '0.7' : '';
                if (!readonly) {
                    eventDetailOcDateFlatpickr.altInput.removeAttribute('readonly');
                }
            }
        }
        if (ocServiceWrap) ocServiceWrap.style.pointerEvents = readonly ? 'none' : '';
        if (ocClientEdit) ocClientEdit.style.pointerEvents = readonly ? 'none' : '';
        if (ocClientCancelEdit) ocClientCancelEdit.style.pointerEvents = readonly ? 'none' : '';
        if (ocClientNifEdit) ocClientNifEdit.style.pointerEvents = readonly ? 'none' : '';
        if (ocClientNifInput) ocClientNifInput.disabled = readonly;
        if (ocClientNifSave) ocClientNifSave.disabled = readonly;
        if (ocClientNifCancel) ocClientNifCancel.disabled = readonly;
        if (ocClientProfile) ocClientProfile.style.pointerEvents = '';
        if (ocClientTabs) ocClientTabs.style.pointerEvents = readonly ? 'none' : '';
        if (ocNotSel) ocNotSel.style.pointerEvents = readonly ? 'none' : '';
        var selectedList = $id('eventDetailOcSelectedServicesList');
        if (selectedList) selectedList.style.pointerEvents = readonly ? 'none' : '';
        var editModal = $id('eventDetailEditModal');
        if (editModal) editModal.classList.toggle('event-detail-readonly', readonly);
    }

    function populateEventDetailEditModal(data) {
        eventDetailOcTeardownUi();
        window._eventDetailOcPopulating = true;
        eventDetailCurrentData = data;
        eventDetailExistingSale = data.existing_sale || null;
        eventDetailBookingPaidAmount = Math.max(0, parseFloat(data.booking_paid_amount) || 0);
        eventDetailOriginalStartAt = data.start_at || null;
        eventDetailOriginalEndAt = data.end_at || null;
        eventDetailTrustServicesSumForDuration = false;
        eventDetailSelectedServices = [];
        var id = data.id;
        $id('eventDetailEditId').value = id;
        $id('eventDetailEditUserId').value = data.user_id || '';
        $id('eventDetailEditStart').value = data.start_at || '';
        $id('eventDetailEditEnd').value = data.end_at || '';
        if (data.start_at && data.end_at) {
            var dsNorm = new Date(data.start_at);
            var deNorm = new Date(data.end_at);
            if (!isNaN(dsNorm.getTime()) && !isNaN(deNorm.getTime())) {
                $id('eventDetailEditStart').value = agendaFormatLocalDateTimeForInput(dsNorm);
                $id('eventDetailEditEnd').value = agendaFormatLocalDateTimeForInput(deNorm);
            }
        }
        var statusVal = data.status || 'agendado';
        $id('eventDetailStatus').value = statusVal;
        $id('eventDetailStatusLabel').textContent = STATUS_LABELS[statusVal] || statusVal;
        var iconEl = $id('eventDetailStatusIcon');
        if (iconEl) {
            var ic = iconEl.querySelector('i');
            if (ic) ic.className = 'ph ' + (STATUS_ICONS[statusVal] || 'ph-clock');
        }
        var statusDropdownWrap = $id('eventDetailStatusDropdownWrap');
        var statusStatic = $id('eventDetailStatusStatic');
        var statusStaticIcon = $id('eventDetailStatusStaticIcon');
        var statusStaticLabel = $id('eventDetailStatusStaticLabel');
        var statusStaticOnly = (statusVal === 'completo' || statusVal === 'faltou' || statusVal === 'cancelado' || statusVal === 'anulado');
        if (statusStaticOnly) {
            if (statusDropdownWrap) statusDropdownWrap.classList.add('d-none');
            if (statusStatic) {
                statusStatic.classList.remove('d-none');
                statusStatic.classList.toggle('text-success', statusVal === 'completo');
                statusStatic.classList.toggle('text-danger', statusVal === 'faltou' || statusVal === 'cancelado' || statusVal === 'anulado');
                if (statusStaticLabel) statusStaticLabel.textContent = STATUS_LABELS[statusVal] || statusVal;
                if (statusStaticIcon) {
                    var si = statusStaticIcon.querySelector('i');
                    if (si) si.className = 'ph ' + (STATUS_ICONS[statusVal] || 'ph-clock');
                }
            }
        } else {
            if (statusDropdownWrap) statusDropdownWrap.classList.remove('d-none');
            if (statusStatic) {
                statusStatic.classList.add('d-none');
                statusStatic.classList.remove('text-success', 'text-danger');
            }
        }
        var cancelOpt = $id('eventDetailStatusMenu')?.querySelector('[data-status="cancelar"]');
        if (cancelOpt) cancelOpt.style.display = (statusVal === 'faltou' || statusVal === 'cancelado' || statusVal === 'anulado') ? 'none' : '';

        var obsEl = $id('eventDetailOcObs');
        if (obsEl) obsEl.value = data.description || '';

        var hasVisitLead = !!(data.visit || data.lead);
        var marcacaoSection = $id('eventDetailOcMarcacaoSection');
        var visitBlock = $id('eventDetailVisitLeadBlock');
        if (visitBlock) {
            visitBlock.classList.add('d-none');
            visitBlock.innerHTML = '';
        }
        if (marcacaoSection) {
            marcacaoSection.classList.toggle('d-none', hasVisitLead || data.event_type !== 'marcacao');
        }
        if (hasVisitLead && visitBlock) {
            visitBlock.classList.remove('d-none');
            if (data.visit) {
                visitBlock.innerHTML = '<h6 class="nova-marcacao-section-title">Cliente (Visita)</h6><div class="nova-marcacao-person"><div><strong>' + (data.visit.client_name || '…') + '</strong></div></div>' +
                    '<div class="mt-2"><a href="' + (data.visit.opportunity_id ? (C.urlOpportunities || '') + '/' + data.visit.opportunity_id : '#') + '" class="btn btn-sm btn-outline-primary"><i class="ph ph-briefcase me-1"></i>Ficha da Oportunidade</a></div>';
            } else if (data.lead) {
                visitBlock.innerHTML = '<h6 class="nova-marcacao-section-title">Lead</h6><div class="nova-marcacao-person"><div><strong>' + (data.lead.name || '…') + '</strong><span class="d-block small text-muted">' + [data.lead.email, data.lead.formatted_phone || data.lead.phone].filter(Boolean).join(' · ') + '</span></div></div>' +
                    '<div class="mt-2"><a href="' + (C.urlLeads || '') + '/' + data.lead.id + '" class="btn btn-sm btn-outline-primary"><i class="ph ph-file-text me-1"></i>Ficha da Lead</a></div>';
            }
        }

        if (hasVisitLead || data.event_type !== 'marcacao') {
            var svcCountEarly = (data.event_services && data.event_services.length) || 0;
            setEventDetailPaymentAndReadOnly(eventDetailExistingSale, data.event_type || 'marcacao', svcCountEarly);
            updateEventDetailOutOfHoursWarning();
            window._eventDetailOcPopulating = false;
            return;
        }

        eventDetailOcBindFormOnce();
        eventDetailOcPopulateMemberSelect(data.user_id || '');

        if (data.client_id && data.client_name) {
            var cObj = {
                id: data.client_id,
                name: data.client_name,
                email: data.client_email || '',
                phone: data.client_phone || '',
                nif: data.client_nif || '',
                formatted_phone: data.client_formatted_phone || '',
                avatar_url: data.client_avatar_url || ''
            };
            eventDetailOcInitClientChoicesSelect();
            eventDetailOcApplyClientFromApi(cObj);
            var phoneL = cObj.formatted_phone || cObj.phone || '';
            var cLabel = (cObj.name || '') + (phoneL ? ' · ' + phoneL : '');
            if (eventDetailOcChoicesInstances.client) {
                eventDetailOcChoicesInstances.client.setChoices([{ value: String(cObj.id), label: cLabel }], 'value', 'label', true);
                try { eventDetailOcChoicesInstances.client.setChoiceByValue(String(cObj.id)); } catch (e) { /* ignore */ }
            }
        } else {
            eventDetailOcEnterClientSearchMode();
        }

        (data.event_services || []).forEach(function(s) {
            var dur = s.duration || 60;
            var pr = parseFloat(s.price) || 0;
            var origPr = s.original_price != null ? parseFloat(s.original_price) : pr;
            var extras = (s.extras || []).map(function(e) {
                return { id: e.extra_id, name: e.name, duration: e.duration || 0, price: e.price || 0, formatted_duration: e.formatted_duration || (e.duration || 0) + ' min', formatted_price: e.formatted_price || (e.price || 0).toFixed(2).replace('.', ',') + ' €' };
            });
            eventDetailSelectedServices.push({
                service_id: s.id,
                service_option_id: s.service_option_id != null && s.service_option_id !== '' ? String(s.service_option_id) : '',
                name: s.name,
                duration: dur,
                price: pr,
                original_price: origPr,
                formatted_duration: (s.formatted_duration || dur + ' min'),
                formatted_price: s.formatted_price || (pr.toFixed(2).replace('.', ',') + ' €'),
                color: s.color || '#6c757d',
                category_name: s.category_name || '',
                available_extras: [],
                extras: extras
            });
        });
        eventDetailOcShowServicePicker = eventDetailSelectedServices.length === 0;

        var mid = String(data.user_id || '');
        eventDetailOcReloadServicesForMember(mid, function() {
            eventDetailOcRenderSelectedServices();
            EventDetail.updateTotal();
            EventDetail.updateEndTime();
            eventDetailOcSyncPickersFromHidden();
            setEventDetailPaymentAndReadOnly(eventDetailExistingSale, 'marcacao', eventDetailSelectedServices.length);
            updateEventDetailOutOfHoursWarning();
            window._eventDetailOcPopulating = false;
        });
    }

    var eventDetailSelectedClient = null;
    var eventDetailServicesData = null;

    /* ——— Offcanvas editar: mesmo fluxo que nova marcação (Choices cliente/serviço, cartão com lápis) ——— */
    var eventDetailOcChoicesInstances = { client: null, service: null, member: null };
    var eventDetailOcServicesFlat = [];
    var eventDetailOcShowServicePicker = true;
    var eventDetailOcDateFlatpickr = null;
    var eventDetailOcClientSearchTimer = null;
    var eventDetailOcClientRemoteSearchBound = false;
    var eventDetailOcClientChangeBound = false;
    var eventDetailOcClientBeforeEdit = null;
    var eventDetailOcClientNifEditing = false;
    var eventDetailOcClientNifSaving = false;
    var eventDetailOcFormBound = false;

    function eventDetailOcDestroyChoices() {
        ['client', 'service', 'member'].forEach(function(key) {
            var inst = eventDetailOcChoicesInstances[key];
            if (inst) {
                try { inst.destroy(); } catch (e) { /* ignore */ }
                eventDetailOcChoicesInstances[key] = null;
            }
        });
    }
    function eventDetailOcTeardownUi() {
        eventDetailOcDestroyChoices();
        if (eventDetailOcDateFlatpickr) {
            try { eventDetailOcDateFlatpickr.destroy(); } catch (e) { /* ignore */ }
            eventDetailOcDateFlatpickr = null;
        }
    }
    function eventDetailOcPopulateMemberSelect(selectedId) {
        var memSel = $id('eventDetailOcMember');
        if (!memSel) return;
        if (eventDetailOcChoicesInstances.member) {
            try { eventDetailOcChoicesInstances.member.destroy(); } catch (e) { /* ignore */ }
            eventDetailOcChoicesInstances.member = null;
        }
        memSel.innerHTML = '<option value="">Selecionar</option>';
        (C.usersForConsultant || []).forEach(function(u) {
            var opt = document.createElement('option');
            opt.value = String(u.id);
            opt.textContent = u.name || ('#' + u.id);
            memSel.appendChild(opt);
        });
        if (selectedId) {
            memSel.value = String(selectedId);
        }
        eventDetailOcChoicesInstances.member = new Choices(memSel, agendaOcCommonChoicesOpts());
        if (selectedId) {
            try { eventDetailOcChoicesInstances.member.setChoiceByValue(String(selectedId)); } catch (e) { /* ignore */ }
        }
    }
    function eventDetailOcClientChoicesOpts() {
        var o = agendaOcCommonChoicesOpts();
        o.searchChoices = false;
        o.searchFloor = 1;
        o.searchResultLimit = 50;
        o.placeholder = true;
        o.placeholderValue = 'Pesquisar cliente';
        o.searchPlaceholderValue = 'Pesquisar Nome, Telemóvel, Email...';
        o.noResultsText = 'Nenhum cliente encontrado.';
        return o;
    }
    function eventDetailOcOnClientSearchEvent(ev) {
        var inst = eventDetailOcChoicesInstances.client;
        if (!inst) return;
        var q = (ev.detail && ev.detail.value != null) ? String(ev.detail.value).trim() : '';
        clearTimeout(eventDetailOcClientSearchTimer);
        if (!q.length) return;
        eventDetailOcClientSearchTimer = setTimeout(function() {
            fetch(agendaClientsUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(clients) {
                    if (!eventDetailOcChoicesInstances.client) return;
                    var items = (clients || []).map(function(c) {
                        var phone = c.formatted_phone || c.phone || '';
                        var label = (c.name || '') + (phone ? ' · ' + phone : '');
                        return { value: String(c.id), label: label };
                    });
                    eventDetailOcChoicesInstances.client.setChoices(items, 'value', 'label', true);
                })
                .catch(function() { /* ignore */ });
        }, 300);
    }
    function eventDetailOcClearNewClientForm() {
        var n = $id('eventDetailOcNewClientName');
        var e = $id('eventDetailOcNewClientEmail');
        var p = $id('eventDetailOcNewClientPhone');
        var tabNew = $id('eventDetailOcTabNew');
        if (n) n.value = '';
        if (e) e.value = '';
        if (p) p.value = '';
        if (tabNew) destroyAgendaCreateClientIntl(tabNew);
    }
    function eventDetailOcInitNewClientPhoneIntl() {
        var phoneEl = $id('eventDetailOcNewClientPhone');
        var tabNew = $id('eventDetailOcTabNew');
        if (!phoneEl || !tabNew) return;
        initAgendaCreateClientIntl(phoneEl, tabNew);
    }
    function eventDetailOcResetClientUi() {
        var notSel = $id('eventDetailOcClientNotSelectedWrap');
        var card = $id('eventDetailOcClientSelectedCard');
        if (notSel) notSel.classList.remove('d-none');
        if (card) card.classList.add('d-none');
        eventDetailOcSetNifInlineMode(false);
        eventDetailSelectedClient = null;
        eventDetailOcClearNewClientForm();
        var tabBtn = $id('eventDetailOcTabExistingBtn');
        if (tabBtn && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            try { bootstrap.Tab.getOrCreateInstance(tabBtn).show(); } catch (err) { /* ignore */ }
        }
    }
    function eventDetailOcApplyClientFromApi(c) {
        if (!c) return;
        eventDetailSelectedClient = {
            id: String(c.id),
            name: c.name || '',
            phone: c.phone || '',
            nif: c.nif || '',
            formatted_phone: c.formatted_phone || '',
            email: c.email || '',
            avatar_url: c.avatar_url || ''
        };
        var av = $id('eventDetailOcClientAvatar');
        var fb = $id('eventDetailOcClientAvatarFallback');
        $id('eventDetailOcClientSelectedName').textContent = c.name || '';
        $id('eventDetailOcClientSelectedPhone').textContent = agendaClientPhoneLabel(eventDetailSelectedClient);
        var nifEl = $id('eventDetailOcClientSelectedNif');
        if (nifEl) nifEl.textContent = agendaClientNifLabel(eventDetailSelectedClient);
        var nifInput = $id('eventDetailOcClientNifInput');
        if (nifInput) nifInput.value = String(eventDetailSelectedClient.nif || '').trim();
        var pl = $id('eventDetailOcClientProfileLink');
        if (pl) {
            pl.href = clientesBaseUrl + '/' + c.id;
            pl.classList.remove('d-none');
        }
        if (c.avatar_url && av) {
            av.src = c.avatar_url;
            av.classList.remove('d-none');
            if (fb) fb.classList.add('d-none');
        } else if (av && fb) {
            av.classList.add('d-none');
            var initials = (c.name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
            fb.textContent = initials;
            fb.classList.remove('d-none');
        }
        var notSel = $id('eventDetailOcClientNotSelectedWrap');
        var sc = $id('eventDetailOcClientSelectedCard');
        if (notSel) notSel.classList.add('d-none');
        if (sc) sc.classList.remove('d-none');
        var cedit = $id('eventDetailOcClientEditBtn');
        var ceditCancel = $id('eventDetailOcClientCancelEditBtn');
        if (cedit) cedit.classList.remove('d-none');
        if (ceditCancel) ceditCancel.classList.add('d-none');
        eventDetailOcSetNifInlineMode(false);
        eventDetailOcClientBeforeEdit = null;
    }
    function eventDetailOcInitClientChoicesSelect() {
        var clientSel = $id('eventDetailOcClient');
        if (!clientSel) return;
        if (eventDetailOcChoicesInstances.client) {
            try { eventDetailOcChoicesInstances.client.destroy(); } catch (e) { /* ignore */ }
            eventDetailOcChoicesInstances.client = null;
        }
        clientSel.innerHTML = '<option value="">Pesquisar cliente</option>';
        eventDetailOcChoicesInstances.client = new Choices(clientSel, eventDetailOcClientChoicesOpts());
    }
    function eventDetailOcEnterClientSearchMode() {
        eventDetailOcClientBeforeEdit = eventDetailSelectedClient ? Object.assign({}, eventDetailSelectedClient) : null;
        eventDetailSelectedClient = null;
        eventDetailOcClearNewClientForm();
        var notSel = $id('eventDetailOcClientNotSelectedWrap');
        var card = $id('eventDetailOcClientSelectedCard');
        var cedit = $id('eventDetailOcClientEditBtn');
        var ceditCancel = $id('eventDetailOcClientCancelEditBtn');
        if (card) card.classList.add('d-none');
        if (notSel) notSel.classList.remove('d-none');
        if (cedit) cedit.classList.add('d-none');
        if (ceditCancel) ceditCancel.classList.remove('d-none');
        var tabBtn = $id('eventDetailOcTabExistingBtn');
        if (tabBtn && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            try { bootstrap.Tab.getOrCreateInstance(tabBtn).show(); } catch (err) { /* ignore */ }
        }
        eventDetailOcInitClientChoicesSelect();
        if (!eventDetailOcClientRemoteSearchBound) {
            eventDetailOcClientRemoteSearchBound = true;
            $id('eventDetailOcClient').addEventListener('search', eventDetailOcOnClientSearchEvent);
        }
    }
    function eventDetailOcCancelClientEdit() {
        var cedit = $id('eventDetailOcClientEditBtn');
        var ceditCancel = $id('eventDetailOcClientCancelEditBtn');
        if (cedit) cedit.classList.remove('d-none');
        if (ceditCancel) ceditCancel.classList.add('d-none');
        if (eventDetailOcClientBeforeEdit) {
            eventDetailOcApplyClientFromApi(eventDetailOcClientBeforeEdit);
            return;
        }
        eventDetailOcResetClientUi();
    }
    function eventDetailOcSetNifInlineMode(enabled) {
        var disp = $id('eventDetailOcClientNifDisplayWrap');
        var inputWrap = $id('eventDetailOcClientNifInputWrap');
        if (disp) disp.classList.toggle('d-none', !!enabled);
        if (inputWrap) inputWrap.classList.toggle('d-none', !enabled);
        eventDetailOcClientNifEditing = !!enabled;
    }
    function eventDetailOcStartClientNifEdit() {
        if (!eventDetailSelectedClient || !eventDetailSelectedClient.id) {
            showToast('Selecione um cliente primeiro.', 'error');
            return;
        }
        var input = $id('eventDetailOcClientNifInput');
        if (!input) return;
        input.value = String(eventDetailSelectedClient.nif || '').trim();
        eventDetailOcSetNifInlineMode(true);
        setTimeout(function() {
            try {
                input.focus();
                input.select();
            } catch (e) { /* ignore */ }
        }, 0);
    }
    function eventDetailOcCancelClientNifEdit() {
        if (eventDetailOcClientNifSaving) return;
        eventDetailOcSetNifInlineMode(false);
        var nifEl = $id('eventDetailOcClientSelectedNif');
        if (nifEl) nifEl.textContent = agendaClientNifLabel(eventDetailSelectedClient);
    }
    function eventDetailOcSaveClientNifInline() {
        if (eventDetailOcClientNifSaving) return;
        if (!eventDetailSelectedClient || !eventDetailSelectedClient.id) return;
        var input = $id('eventDetailOcClientNifInput');
        if (!input) return;
        var nif = String(input.value || '').trim();
        if (!/^\d{9}$/.test(nif)) {
            showToast('O NIF deve ter 9 dígitos.', 'error');
            try { input.focus(); input.select(); } catch (e) { /* ignore */ }
            return;
        }
        var saveBtn = $id('eventDetailOcClientNifSaveBtn');
        var cancelBtn = $id('eventDetailOcClientNifCancelBtn');
        eventDetailOcClientNifSaving = true;
        input.disabled = true;
        if (saveBtn) saveBtn.disabled = true;
        if (cancelBtn) cancelBtn.disabled = true;
        fetch(agendaClientsUrl + '/' + encodeURIComponent(eventDetailSelectedClient.id) + '/nif', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ nif: nif })
        })
            .then(function(r) {
                return r.json().then(function(data) {
                    if (!r.ok) throw new Error((data && data.message) ? data.message : 'Não foi possível atualizar o NIF.');
                    return data;
                });
            })
            .then(function(client) {
                eventDetailOcApplyClientFromApi(client);
                eventDetailOcSetNifInlineMode(false);
                showToast('NIF atualizado com sucesso.', 'success');
            })
            .catch(function(err) {
                showToast((err && err.message) ? err.message : 'Não foi possível atualizar o NIF.', 'error');
            })
            .finally(function() {
                eventDetailOcClientNifSaving = false;
                input.disabled = false;
                if (saveBtn) saveBtn.disabled = false;
                if (cancelBtn) cancelBtn.disabled = false;
            });
    }
    function eventDetailOcApplyServiceFieldVisibility() {
        var selWrap = $id('eventDetailOcServiceSelectWrap');
        var addWrap = $id('eventDetailOcAddMoreServicesWrap');
        if (!selWrap || !addWrap) return;
        var status = eventDetailCurrentData ? String(eventDetailCurrentData.status || '') : '';
        var isPartialSale = !!(eventDetailExistingSale && eventDetailExistingSale.is_partial);
        var readonly = (!!eventDetailExistingSale && !isPartialSale) || status === 'completo' || status === 'faltou' || status === 'cancelado' || status === 'anulado';
        if (readonly) {
            selWrap.classList.add('d-none');
            addWrap.classList.add('d-none');
            return;
        }
        var n = eventDetailSelectedServices.length;
        if (n === 0) {
            eventDetailOcShowServicePicker = true;
        }
        if (eventDetailOcShowServicePicker) {
            selWrap.classList.remove('d-none');
            addWrap.classList.add('d-none');
        } else {
            selWrap.classList.add('d-none');
            addWrap.classList.remove('d-none');
        }
    }
    function eventDetailOcReloadServicesForMember(memberId, done) {
        var svcSel = $id('eventDetailOcService');
        if (!svcSel) {
            if (done) done(null);
            return;
        }
        if (eventDetailOcChoicesInstances.service) {
            try { eventDetailOcChoicesInstances.service.destroy(); } catch (e) { /* ignore */ }
            eventDetailOcChoicesInstances.service = null;
        }
        if (!memberId) {
            eventDetailOcServicesFlat = [];
            svcSel.innerHTML = '<option value="">Escolha um profissional</option>';
            svcSel.disabled = true;
            eventDetailOcChoicesInstances.service = new Choices(svcSel, agendaOcCommonChoicesOpts());
            if (done) done(null);
            return;
        }
        svcSel.innerHTML = '<option value="">A carregar…</option>';
        svcSel.disabled = true;
        fetch(agendaMembersServicesUrl + '/' + memberId + '/services', { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                eventDetailServicesData = data;
                eventDetailOcServicesFlat = agendaOcFlattenServicesFromCategories(data.categories);
                agendaOcRebuildServiceSelect(svcSel, data.categories || []);
                eventDetailOcChoicesInstances.service = new Choices(svcSel, agendaOcCommonChoicesOpts());
                eventDetailSelectedServices.forEach(function(item) {
                    var availableExtras = [];
                    (data.categories || []).forEach(function(cat) {
                        (cat.services || []).forEach(function(svc) {
                            if (String(svc.id) === String(item.service_id)) {
                                availableExtras = svc.extras || [];
                                if (!item.category_name && cat.name) item.category_name = cat.name;
                            }
                        });
                    });
                    item.available_extras = availableExtras;
                    var flatMatch = eventDetailOcServicesFlat.find(function(fs) {
                        return String(fs.service_id) === String(item.service_id) && String(fs.service_option_id || '') === String(item.service_option_id || '');
                    });
                    if (flatMatch && flatMatch.category_name) item.category_name = flatMatch.category_name;
                });
                if (done) done(data);
            })
            .catch(function() {
                eventDetailOcServicesFlat = [];
                svcSel.innerHTML = '<option value="">Erro ao carregar</option>';
                svcSel.disabled = true;
                eventDetailOcChoicesInstances.service = new Choices(svcSel, agendaOcCommonChoicesOpts());
                if (done) done(null);
            });
    }
    function eventDetailOcSyncPickersFromHidden() {
        var startStr = $id('eventDetailEditStart') && $id('eventDetailEditStart').value;
        if (!startStr) return;
        var d = new Date(startStr);
        if (isNaN(d.getTime())) return;
        var ymd = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        var timeSel = $id('eventDetailOcTime');
        if (timeSel) {
            timeSel.innerHTML = '';
            timeSel.appendChild(agendaOcBuildTimeOptionElements());
            var hh = String(d.getHours()).padStart(2, '0');
            var mm = String(d.getMinutes()).padStart(2, '0');
            timeSel.value = hh + ':' + mm;
            if (!timeSel.value) timeSel.value = '09:00';
        }
        var dateIn = $id('eventDetailOcDate');
        if (dateIn && typeof flatpickr !== 'undefined') {
            if (!eventDetailOcDateFlatpickr) {
                eventDetailOcDateFlatpickr = flatpickr(dateIn, {
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'l, j F',
                    locale: (flatpickr && flatpickr.l10ns && flatpickr.l10ns.pt) ? flatpickr.l10ns.pt : undefined,
                    allowInput: true,
                    disableMobile: true,
                    onChange: function() {
                        eventDetailOcSyncHiddenFromPickers();
                        updateEventDetailOutOfHoursWarning();
                    }
                });
                if (eventDetailOcDateFlatpickr && eventDetailOcDateFlatpickr.altInput) {
                    eventDetailOcDateFlatpickr.altInput.removeAttribute('readonly');
                    eventDetailOcDateFlatpickr.altInput.style.cursor = 'pointer';
                }
            }
            if (eventDetailOcDateFlatpickr) eventDetailOcDateFlatpickr.setDate(ymd, false);
            else dateIn.value = ymd;
        }
    }
    function eventDetailOcSyncHiddenFromPickers() {
        var dStr = $id('eventDetailOcDate') && $id('eventDetailOcDate').value;
        var tStr = $id('eventDetailOcTime') && $id('eventDetailOcTime').value;
        if (!dStr || !tStr) return;
        var startLocal = dStr + 'T' + tStr;
        var startDate = parseAgendaLocalDateTime(startLocal);
        if (!startDate || isNaN(startDate.getTime())) return;
        var totalDur = eventDetailEffectiveDurationMinutes();
        if (totalDur < 1) totalDur = 60;
        var end = new Date(startDate.getTime() + totalDur * 60000);
        $id('eventDetailEditStart').value = agendaFormatLocalDateTimeForInput(startDate);
        $id('eventDetailEditEnd').value = agendaFormatLocalDateTimeForInput(end);
        var uid = ($id('eventDetailOcMember') && $id('eventDetailOcMember').value) || '';
        if (uid) $id('eventDetailEditUserId').value = uid;
        if (eventDetailCurrentData) {
            eventDetailCurrentData.start_at = $id('eventDetailEditStart').value;
            eventDetailCurrentData.end_at = $id('eventDetailEditEnd').value;
        }
        var evId = $id('eventDetailEditId') && $id('eventDetailEditId').value;
        if (evId && typeof calendar !== 'undefined') {
            var ev = calendar.getEventById(evId);
            if (ev) {
                ev.setStart(startDate);
                ev.setEnd(end);
            }
        }
    }
    /** Duração e categoria (texto muted) na lista de serviços dos offcanvas. */
    function agendaOcServiceDurationCategoryMeta(item) {
        var durNum = parseInt(item.duration, 10) || 0;
        var durText = item.formatted_duration || (durNum + ' min');
        var cat = String(item.category_name || '').trim();
        var catEsc = cat.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return '<div class="small text-muted mt-2">' + durText + (catEsc ? ' · ' + catEsc : '') + '</div>';
    }
    function eventDetailOcRenderSelectedServices() {
        var wrap = $id('eventDetailOcSelectedServicesList');
        if (!wrap) return;
        var totalStrip = agendaMarcacaoTotalStrip('eventDetailTotalPrice');
        var isCompleted = (eventDetailCurrentData && eventDetailCurrentData.status === 'completo');
        if (!eventDetailSelectedServices.length) {
            wrap.classList.add('d-none');
            wrap.innerHTML = '';
            eventDetailOcApplyServiceFieldVisibility();
            if (totalStrip) totalStrip.classList.remove('d-none');
            return;
        }
        wrap.classList.remove('d-none');
        if (totalStrip) totalStrip.classList.remove('d-none');
        var html = eventDetailSelectedServices.map(function(item, idx) {
            var dur = parseInt(item.duration, 10) || 0;
            var price = parseFloat(item.price) || 0;
            var originalPrice = item.original_price != null ? parseFloat(item.original_price) : price;
            var hasPriceChange = Math.abs(price - originalPrice) > 0.0001;
            var priceBlock = hasPriceChange
                ? '<span class="text-danger text-decoration-line-through small me-1">' + (originalPrice.toFixed(2).replace('.', ',') + ' €') + '</span><span class="fw-semibold text-nowrap">' + price.toFixed(2).replace('.', ',') + ' €</span>'
                : '<span class="fw-semibold text-nowrap">' + price.toFixed(2).replace('.', ',') + ' €</span>';
            var addedExtraIds = (item.extras || []).map(function(e) { return e.id; });
            var extrasToAdd = (!isCompleted && (item.available_extras || []).filter(function(e) { return addedExtraIds.indexOf(e.id) === -1; })) || [];
            var iconsRow = isCompleted ? ''
                : '<div class="d-flex gap-1 justify-content-end flex-shrink-0">' +
                    (extrasToAdd.length > 0
                        ? '<button type="button" class="btn btn-outline-secondary btn-icon btn-sm agenda-oc-add-extras-btn" data-idx="' + idx + '" title="Adicionar extras" aria-label="Adicionar extras"><i class="ph ph-plus-circle"></i></button>'
                        : ''
                    ) +
                    '<button type="button" class="btn btn-outline-secondary btn-icon btn-sm agenda-oc-edit-service-btn" data-idx="' + idx + '" title="Alterar opções" aria-label="Alterar opções"><i class="ph ph-pencil-simple"></i></button>' +
                    '<button type="button" class="btn btn-outline-danger btn-icon btn-sm agenda-oc-remove-service-btn" data-idx="' + idx + '" title="Eliminar" aria-label="Eliminar"><i class="ph ph-trash"></i></button>' +
                '</div>';
            var extrasLine = (item.extras && item.extras.length)
                ? item.extras.map(function(e, eIdx) {
                    var priceText = e.formatted_price || ((parseFloat(e.price) || 0).toFixed(2).replace('.', ',') + ' €');
                    var durText = e.formatted_duration || ((e.duration || 0) + ' min');
                    var rm = isCompleted ? '' :
                        '<button type="button" class="btn btn-link btn-sm p-0 ms-1 agenda-oc-remove-extra-btn" data-idx="' + idx + '" data-extra-index="' + eIdx + '" aria-label="Remover extra"><i class="ph ph-x"></i></button>';
                    return '<div class="agenda-oc-extra-row d-flex justify-content-between align-items-start mt-1 w-100">' +
                        '<div class="nova-marcacao-service-item-left d-flex flex-column min-w-0">' +
                            '<div class="d-flex align-items-center"><div class="small fw-medium">+ ' + (e.name || '').replace(/</g, '&lt;') + '</div>' + rm + '</div>' +
                            '<div class="small text-muted">' + durText + '</div></div>' +
                        '<div class="small text-nowrap">' + priceText + '</div></div>';
                }).join('')
                : '';
            var addExtrasPanelHtml = '';
            if (!isCompleted && extrasToAdd.length > 0) {
                addExtrasPanelHtml =
                    '<div class="agenda-oc-add-extras-panel d-none mt-2" data-add-extras-idx="' + idx + '">' +
                        '<div class="small fw-semibold text-body mb-2">Adicionar extra</div>' +
                        '<div class="d-flex flex-column gap-1 agenda-oc-add-extras-list">' +
                        extrasToAdd.map(function(ex) {
                            return '<button type="button" class="btn btn-light btn-sm w-100 text-start d-flex justify-content-between align-items-center agenda-oc-pick-extra-btn" data-idx="' + idx + '" data-extra-id="' + String(ex.id).replace(/"/g, '') + '">' +
                                '<span class="text-truncate me-2">' + (ex.name || '').replace(/</g, '&lt;') + '</span>' +
                                '<span class="small text-muted text-nowrap flex-shrink-0">' + (ex.formatted_price || ex.price + ' €') + ' · ' + (ex.formatted_duration || ex.duration + ' min') + '</span>' +
                            '</button>';
                        }).join('') +
                        '</div><div class="d-flex justify-content-end mt-2">' +
                        '<button type="button" class="btn btn-link btn-sm p-0 agenda-oc-add-extras-close-btn" data-idx="' + idx + '">Fechar</button></div></div>';
            }
            return '<div class="border rounded p-2 mb-2 agenda-oc-service-card" data-oc-idx="' + idx + '">' +
                '<div class="d-flex justify-content-between align-items-start gap-2">' +
                    '<div class="min-w-0"><div class="fw-semibold text-truncate">' + (item.name || 'Serviço') + '</div>' + agendaOcServiceDurationCategoryMeta(item) + '</div>' +
                    '<div class="d-flex flex-column align-items-end gap-1 flex-shrink-0 text-end">' +
                        '<div class="nova-marcacao-service-item-price-row text-nowrap">' + priceBlock + '</div>' + iconsRow + '</div></div>' +
                extrasLine + addExtrasPanelHtml +
                '<div class="agenda-oc-edit-service-panel d-none mt-2 pt-2 border-top" data-edit-idx="' + idx + '">' +
                    '<div class="row g-2">' +
                        '<div class="col-6"><label class="form-label form-label-sm small mb-1">Duração (min)</label><input type="number" min="1" step="1" class="form-control form-control-sm agenda-oc-edit-duration" value="' + dur + '"></div>' +
                        '<div class="col-6"><label class="form-label form-label-sm small mb-1">Preço (€)</label><input type="number" min="0" step="0.01" class="form-control form-control-sm agenda-oc-edit-price" value="' + price.toFixed(2) + '"></div>' +
                    '</div>' +
                    '<div class="d-flex justify-content-end gap-1 mt-2">' +
                        '<button type="button" class="btn btn-light btn-sm agenda-oc-edit-cancel-btn" data-idx="' + idx + '">Cancelar</button>' +
                        '<button type="button" class="btn btn-primary btn-sm agenda-oc-edit-save-btn" data-idx="' + idx + '">Guardar</button>' +
                    '</div></div></div>';
        }).join('');
        wrap.innerHTML = html;
        wrap.querySelectorAll('.agenda-oc-remove-service-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = parseInt(this.dataset.idx, 10);
                if (isNaN(idx) || !eventDetailSelectedServices[idx]) return;
                eventDetailMarkServiceListMutated();
                eventDetailSelectedServices.splice(idx, 1);
                if (!eventDetailSelectedServices.length) eventDetailOcShowServicePicker = true;
                eventDetailOcRenderSelectedServices();
                EventDetail.updateTotal();
                EventDetail.updateEndTime();
            });
        });
        wrap.querySelectorAll('.agenda-oc-add-extras-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var idx = parseInt(this.dataset.idx, 10);
                if (isNaN(idx)) return;
                var panel = wrap.querySelector('.agenda-oc-add-extras-panel[data-add-extras-idx="' + idx + '"]');
                if (!panel) return;
                var wasHidden = panel.classList.contains('d-none');
                wrap.querySelectorAll('.agenda-oc-add-extras-panel').forEach(function(p) { p.classList.add('d-none'); });
                wrap.querySelectorAll('.agenda-oc-edit-service-panel').forEach(function(p) { p.classList.add('d-none'); });
                if (wasHidden) panel.classList.remove('d-none');
            });
        });
        wrap.querySelectorAll('.agenda-oc-add-extras-close-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var idx = parseInt(this.dataset.idx, 10);
                var panel = wrap.querySelector('.agenda-oc-add-extras-panel[data-add-extras-idx="' + idx + '"]');
                if (panel) panel.classList.add('d-none');
            });
        });
        wrap.querySelectorAll('.agenda-oc-pick-extra-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var idx = parseInt(this.dataset.idx, 10);
                var exIdRaw = this.getAttribute('data-extra-id');
                if (isNaN(idx) || exIdRaw == null || exIdRaw === '') return;
                var svc = eventDetailSelectedServices[idx];
                if (!svc || !svc.available_extras) return;
                var ex = svc.available_extras.find(function(x) { return String(x.id) === String(exIdRaw); });
                if (!ex) return;
                if (!svc.extras) svc.extras = [];
                eventDetailMarkServiceListMutated();
                svc.extras.push({
                    id: ex.id, name: ex.name, duration: ex.duration || 0, price: ex.price || 0,
                    formatted_duration: ex.formatted_duration || (ex.duration || 0) + ' min',
                    formatted_price: ex.formatted_price || (parseFloat(ex.price) || 0).toFixed(2).replace('.', ',') + ' €'
                });
                eventDetailOcRenderSelectedServices();
                EventDetail.updateTotal();
                EventDetail.updateEndTime();
            });
        });
        wrap.querySelectorAll('.agenda-oc-remove-extra-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var sIdx = parseInt(this.dataset.idx, 10);
                var exIdx = parseInt(this.dataset.extraIndex, 10);
                if (!isNaN(sIdx) && !isNaN(exIdx) && eventDetailSelectedServices[sIdx] && Array.isArray(eventDetailSelectedServices[sIdx].extras)) {
                    eventDetailMarkServiceListMutated();
                    eventDetailSelectedServices[sIdx].extras.splice(exIdx, 1);
                    eventDetailOcRenderSelectedServices();
                    EventDetail.updateTotal();
                    EventDetail.updateEndTime();
                }
            });
        });
        wrap.querySelectorAll('.agenda-oc-edit-service-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = parseInt(this.dataset.idx, 10);
                if (isNaN(idx)) return;
                wrap.querySelectorAll('.agenda-oc-add-extras-panel').forEach(function(p) { p.classList.add('d-none'); });
                wrap.querySelectorAll('.agenda-oc-edit-service-panel').forEach(function(p) { p.classList.add('d-none'); });
                var panel = wrap.querySelector('.agenda-oc-edit-service-panel[data-edit-idx="' + idx + '"]');
                if (panel) panel.classList.remove('d-none');
            });
        });
        wrap.querySelectorAll('.agenda-oc-edit-cancel-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = parseInt(this.dataset.idx, 10);
                var panel = wrap.querySelector('.agenda-oc-edit-service-panel[data-edit-idx="' + idx + '"]');
                if (panel) panel.classList.add('d-none');
            });
        });
        wrap.querySelectorAll('.agenda-oc-edit-save-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = parseInt(this.dataset.idx, 10);
                if (isNaN(idx) || !eventDetailSelectedServices[idx]) return;
                var panel = wrap.querySelector('.agenda-oc-edit-service-panel[data-edit-idx="' + idx + '"]');
                if (!panel) return;
                var dur = parseInt(panel.querySelector('.agenda-oc-edit-duration').value, 10);
                var price = parseFloat(panel.querySelector('.agenda-oc-edit-price').value);
                if (isNaN(dur) || dur < 1) {
                    showToast('Duração inválida.', 'error');
                    return;
                }
                if (isNaN(price) || price < 0) {
                    showToast('Preço inválido.', 'error');
                    return;
                }
                eventDetailMarkServiceListMutated();
                eventDetailSelectedServices[idx].duration = dur;
                eventDetailSelectedServices[idx].price = price;
                eventDetailSelectedServices[idx].formatted_duration = dur + ' min';
                eventDetailSelectedServices[idx].formatted_price = price.toFixed(2).replace('.', ',') + ' €';
                eventDetailOcRenderSelectedServices();
                EventDetail.updateTotal();
                EventDetail.updateEndTime();
            });
        });
        eventDetailOcApplyServiceFieldVisibility();
    }
    function eventDetailOcBindFormOnce() {
        if (eventDetailOcFormBound) return;
        eventDetailOcFormBound = true;
        var mem = $id('eventDetailOcMember');
        if (mem) {
            mem.addEventListener('change', function() {
                if (window._eventDetailOcPopulating) return;
                $id('eventDetailEditUserId').value = this.value || '';
                syncEventDetailCalendarEventToSelectedMember();
                eventDetailSelectedServices = [];
                eventDetailTrustServicesSumForDuration = false;
                eventDetailOcShowServicePicker = true;
                eventDetailOcRenderSelectedServices();
                eventDetailOcReloadServicesForMember(this.value || '', function() {
                    eventDetailOcRenderSelectedServices();
                    updateEventDetailOutOfHoursWarning();
                });
            });
        }
        var timeSel = $id('eventDetailOcTime');
        if (timeSel) {
            timeSel.addEventListener('change', function() {
                eventDetailOcSyncHiddenFromPickers();
                updateEventDetailOutOfHoursWarning();
            });
            timeSel.addEventListener('focus', function() {
                var nowRounded = agendaOcCurrentRoundedTime();
                if (this.value !== nowRounded) this.value = nowRounded;
            });
        }
        var svcEl = $id('eventDetailOcService');
        if (svcEl) {
            svcEl.addEventListener('change', function() {
                var raw = (this.value || '').trim();
                if (!raw) return;
                var svc = agendaOcFindFlatServiceEntry(eventDetailOcServicesFlat, raw);
                if (!svc) return;
                if (eventDetailSelectedServices.some(function(s) {
                    return String(s.service_id) === String(svc.service_id) && String(s.service_option_id || '') === String(svc.service_option_id || '');
                })) {
                    showToast('Serviço já adicionado.', 'warning');
                } else {
                    eventDetailMarkServiceListMutated();
                    eventDetailSelectedServices.push({
                        service_id: svc.service_id,
                        service_option_id: svc.service_option_id || '',
                        name: svc.name || '',
                        duration: parseInt(svc.duration, 10) || 60,
                        price: parseFloat(svc.price) || 0,
                        original_price: svc.original_price != null ? svc.original_price : (parseFloat(svc.price) || 0),
                        formatted_duration: svc.formatted_duration || ((parseInt(svc.duration, 10) || 60) + ' min'),
                        formatted_price: svc.formatted_price || ((parseFloat(svc.price) || 0).toFixed(2).replace('.', ',') + ' €'),
                        category_name: svc.category_name || '',
                        available_extras: svc.available_extras || [],
                        extras: []
                    });
                    eventDetailOcShowServicePicker = false;
                    eventDetailOcRenderSelectedServices();
                    EventDetail.updateTotal();
                    EventDetail.updateEndTime();
                }
                this.value = '';
                if (eventDetailOcChoicesInstances.service && typeof eventDetailOcChoicesInstances.service.setChoiceByValue === 'function') {
                    try { eventDetailOcChoicesInstances.service.setChoiceByValue(''); } catch (e) { /* ignore */ }
                }
            });
        }
        var addMore = $id('eventDetailOcAddMoreServicesBtn');
        if (addMore) {
            addMore.addEventListener('click', function() {
                eventDetailOcShowServicePicker = true;
                eventDetailOcApplyServiceFieldVisibility();
                var ch = eventDetailOcChoicesInstances.service;
                var selWrap = $id('eventDetailOcServiceSelectWrap');
                setTimeout(function() {
                    if (ch && typeof ch.showDropdown === 'function') {
                        try { ch.showDropdown(true); return; } catch (e) { /* ignore */ }
                    }
                    var inner = selWrap && selWrap.querySelector('.choices__inner');
                    if (inner) inner.click();
                }, 0);
            });
        }
        var ctab = $id('eventDetailOcTabNewBtn');
        if (ctab) {
            ctab.addEventListener('shown.bs.tab', function() { eventDetailOcInitNewClientPhoneIntl(); });
        }
        var cedit = $id('eventDetailOcClientEditBtn');
        if (cedit) cedit.addEventListener('click', function() { eventDetailOcEnterClientSearchMode(); });
        var ceditCancel = $id('eventDetailOcClientCancelEditBtn');
        if (ceditCancel) ceditCancel.addEventListener('click', function() { eventDetailOcCancelClientEdit(); });
        var ceditNif = $id('eventDetailOcClientNifEditBtn');
        if (ceditNif) ceditNif.addEventListener('click', function() { eventDetailOcStartClientNifEdit(); });
        var nifSaveBtn = $id('eventDetailOcClientNifSaveBtn');
        if (nifSaveBtn) nifSaveBtn.addEventListener('click', function() { eventDetailOcSaveClientNifInline(); });
        var nifCancelBtn = $id('eventDetailOcClientNifCancelBtn');
        if (nifCancelBtn) nifCancelBtn.addEventListener('click', function() { eventDetailOcCancelClientNifEdit(); });
        var nifInput = $id('eventDetailOcClientNifInput');
        if (nifInput) {
            nifInput.addEventListener('keydown', function(ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    eventDetailOcSaveClientNifInline();
                }
            });
            nifInput.addEventListener('blur', function() {
                if (!eventDetailOcClientNifEditing || eventDetailOcClientNifSaving) return;
                var value = String(nifInput.value || '').trim();
                var current = String((eventDetailSelectedClient && eventDetailSelectedClient.nif) || '').trim();
                if (value === current) {
                    eventDetailOcCancelClientNifEdit();
                }
            });
        }
        if (!eventDetailOcClientChangeBound) {
            eventDetailOcClientChangeBound = true;
            $id('eventDetailOcClient').addEventListener('change', function() {
                var id = (this.value || '').trim();
                if (!id) {
                    eventDetailSelectedClient = null;
                    return;
                }
                fetch(agendaClientsUrl + '?client_id=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(clients) {
                        if (clients && clients[0]) eventDetailOcApplyClientFromApi(clients[0]);
                    })
                    .catch(function() { /* ignore */ });
            });
        }
        var ncBtn = $id('eventDetailOcNewClientSubmit');
        if (ncBtn) {
            ncBtn.addEventListener('click', function() {
                var name = ($id('eventDetailOcNewClientName') && $id('eventDetailOcNewClientName').value || '').trim();
                var email = ($id('eventDetailOcNewClientEmail') && $id('eventDetailOcNewClientEmail').value || '').trim();
                var tabNew = $id('eventDetailOcTabNew');
                var iti = tabNew && tabNew._agendaPhoneIti;
                if (!name) {
                    showToast('Preencha o nome.', 'error');
                    return;
                }
                if (!iti) {
                    eventDetailOcInitNewClientPhoneIntl();
                    iti = tabNew && tabNew._agendaPhoneIti;
                }
                if (!iti) {
                    showToast('Campo de telemóvel indisponível. Recarregue a página.', 'error');
                    return;
                }
                iti.promise.then(function() {
                    if (!iti.isValidNumber()) {
                        showToast('Indique um número de telemóvel válido para o país selecionado (ex.: 9 dígitos em Portugal).', 'error');
                        return;
                    }
                    var phone = iti.getNumber();
                    var btn = $id('eventDetailOcNewClientSubmit');
                    btn.disabled = true;
                    var origTxt = btn.textContent;
                    btn.textContent = 'A criar…';
                    fetch(agendaClientsUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ name: name, email: email || null, phone: phone })
                    })
                        .then(function(r) {
                            return r.json().then(function(data) {
                                if (!r.ok) {
                                    throw new Error(agendaStoreClientCreateErrorMessage(data));
                                }
                                return data;
                            });
                        })
                        .then(function(client) {
                            btn.disabled = false;
                            btn.textContent = origTxt;
                            eventDetailOcClearNewClientForm();
                            eventDetailOcApplyClientFromApi(client);
                            if (eventDetailOcChoicesInstances.client) {
                                var phoneL = client.formatted_phone || client.phone || '';
                                var label = (client.name || '') + (phoneL ? ' · ' + phoneL : '');
                                eventDetailOcChoicesInstances.client.setChoices([{ value: String(client.id), label: label }], 'value', 'label', true);
                                try { eventDetailOcChoicesInstances.client.setChoiceByValue(String(client.id)); } catch (e) { /* ignore */ }
                            }
                            showToast('Cliente criado com sucesso.', 'success');
                        })
                        .catch(function(err) {
                            btn.disabled = false;
                            btn.textContent = origTxt;
                            showToast(err.message || 'Erro ao criar cliente.', 'error');
                        });
                }).catch(function() {
                    showToast('Não foi possível validar o número. Verifique a ligação e tente de novo.', 'error');
                });
            });
        }
    }

    const EventDetail = {};

    EventDetail.renderSelectedServices = function() {
        eventDetailOcRenderSelectedServices();
    };

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
        var host = agendaEventDetailEditHostEl();
        if (host) host.appendChild(popup);
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
                eventDetailMarkServiceListMutated();
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
        var left;
        var top;
        if (host) {
            var hr = host.getBoundingClientRect();
            left = rect.left - hr.left + host.scrollLeft;
            top = (rect.bottom !== undefined ? rect.bottom : evt.clientY) + offset - hr.top + host.scrollTop;
        } else {
            left = rect.left || 0;
            top = (rect.bottom !== undefined ? rect.bottom : evt.clientY) + offset;
        }
        popup.style.left = (left || 0) + 'px';
        popup.style.top = (top || 0) + 'px';
        setTimeout(function() { document.addEventListener('click', ch); document.addEventListener('keydown', eh); }, 0);
    }

    EventDetail.updateTotal = function() {
        var total = eventDetailSelectedServices.reduce(function(sum, s) {
            var p = (parseFloat(s.price) || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (parseFloat(e.price) || 0); }, 0);
            return sum + p;
        }, 0);
        var totalText = total.toFixed(2).replace('.', ',') + ' €';
        $id('eventDetailTotalPrice').textContent = totalText;
        var totalInlineDefaultEl = $id('eventDetailTotalInlineDefaultPrice');
        if (totalInlineDefaultEl) {
            totalInlineDefaultEl.textContent = totalText;
        }
        var totalCompactEl = $id('eventDetailTotalCompactPrice');
        if (totalCompactEl) {
            totalCompactEl.textContent = totalText;
        }
        eventDetailSyncOffcanvasPaymentSummary(total);
    }

    function eventDetailMoney(amount) {
        return (Math.max(0, parseFloat(amount) || 0)).toFixed(2).replace('.', ',') + ' €';
    }

    function eventDetailSyncOffcanvasPaymentSummary(total) {
        var totalLabel = $id('eventDetailTotalLabel');
        var totalInlineDefault = $id('eventDetailTotalInlineDefault');
        var reservaSummary = $id('eventDetailReservaSummary');
        var pagoSummary = $id('eventDetailPagoSummary');
        var defaultRight = $id('eventDetailTotalDefaultRight');
        var compactRight = $id('eventDetailTotalCompactRight');
        if (!totalLabel || !totalInlineDefault || !reservaSummary || !pagoSummary || !defaultRight || !compactRight) {
            return;
        }

        var reservaAmount = Math.max(0, parseFloat(eventDetailBookingPaidAmount) || 0);
        var invoiceSettled = !!(eventDetailCurrentData && eventDetailCurrentData.invoice_settled);
        var finalAmount = Math.max(0, (parseFloat(total) || 0) - reservaAmount);

        var reservaAmountEl = $id('eventDetailReservaAmount');
        var reservaAmountPaidEl = $id('eventDetailReservaAmountPaid');
        var faltaAmountEl = $id('eventDetailFaltaPagarAmount');
        var pagoAmountEl = $id('eventDetailPagoAmount');
        if (reservaAmountEl) reservaAmountEl.textContent = eventDetailMoney(reservaAmount);
        if (reservaAmountPaidEl) reservaAmountPaidEl.textContent = eventDetailMoney(reservaAmount);
        if (faltaAmountEl) faltaAmountEl.textContent = eventDetailMoney(finalAmount);
        if (pagoAmountEl) pagoAmountEl.textContent = eventDetailMoney(finalAmount);

        // Sem reserva/pagamentos: mantém layout atual.
        if (reservaAmount <= 0) {
            totalLabel.classList.add('d-none');
            totalInlineDefault.classList.remove('d-none');
            reservaSummary.classList.add('d-none');
            pagoSummary.classList.add('d-none');
            defaultRight.classList.add('d-none');
            compactRight.classList.add('d-none');
            return;
        }

        // Com reserva: mostra "Total: X" e resumo tudo do lado esquerdo.
        totalLabel.classList.add('d-none');
        totalInlineDefault.classList.remove('d-none');
        defaultRight.classList.add('d-none');
        compactRight.classList.add('d-none');
        if (invoiceSettled) {
            reservaSummary.classList.add('d-none');
            pagoSummary.classList.remove('d-none');
        } else {
            reservaSummary.classList.remove('d-none');
            pagoSummary.classList.add('d-none');
        }
    }

    EventDetail.updateEndTime = function() {
        var startStr = $id('eventDetailEditStart').value;
        if (!startStr) return;
        var totalDur = eventDetailEffectiveDurationMinutes();
        var start = new Date(startStr);
        if (isNaN(start.getTime())) return;
        var end = new Date(start.getTime() + totalDur * 60 * 1000);
        $id('eventDetailEditStart').value = agendaFormatLocalDateTimeForInput(start);
        $id('eventDetailEditEnd').value = agendaFormatLocalDateTimeForInput(end);
        updateEventDetailOutOfHoursWarning();
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
        var host = agendaEventDetailEditHostEl();
        if (host) host.appendChild(popup);
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
                eventDetailMarkServiceListMutated();
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
            var panel = agendaEventDetailEditHostEl();
            var left;
            var top;
            var vw;
            var vh;
            if (panel) {
                var pr = panel.getBoundingClientRect();
                left = (rect.left || evt.clientX) - pr.left + panel.scrollLeft;
                top = (rect.bottom !== undefined ? rect.bottom : evt.clientY) + offset - pr.top + panel.scrollTop;
                vw = panel.clientWidth;
                vh = panel.clientHeight;
            } else {
                left = rect.left || evt.clientX;
                top = (rect.bottom !== undefined ? rect.bottom : evt.clientY) + offset;
                vw = window.innerWidth;
                vh = window.innerHeight;
            }
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
        eventDetailMarkServiceListMutated();
        eventDetailSelectedServices.splice(idx, 1);
        if (!eventDetailSelectedServices.length) eventDetailOcShowServicePicker = true;
        EventDetail.renderSelectedServices();
        EventDetail.updateTotal();
        EventDetail.updateEndTime();
    }
    /** Nova marcação em offcanvas + Choices (ver #agendaMarcacaoTestOffcanvas). */
    var agendaOcChoicesInstances = { client: null, service: null, member: null };
    var agendaOcServicesFlat = [];
    var agendaOcSelectedServices = [];
    /** Com serviços já escolhidos: se false, o select fica oculto até clicar em «Adicionar serviços». */
    var agendaOcShowServicePicker = true;
    var agendaOcTestFormBound = false;
    var agendaOcDateFlatpickr = null;
    var agendaOcClientSearchTimer = null;
    var agendaOcClientRemoteSearchBound = false;
    var agendaOcClientChangeBound = false;
    /** Dados do cliente escolhido no offcanvas (avatar, nome, telefone no cartão). */
    var agendaOcSelectedClient = null;

    function agendaOcCommonChoicesOpts() {
        return {
            searchEnabled: true,
            itemSelectText: '',
            shouldSort: false,
            searchPlaceholderValue: 'Pesquisar…'
        };
    }

    /** Cliente no offcanvas: resultados via API (?q=), não lista completa no DOM. */
    function agendaOcClientChoicesOpts() {
        var o = agendaOcCommonChoicesOpts();
        o.searchChoices = false;
        o.searchFloor = 1;
        o.searchResultLimit = 50;
        o.placeholder = true;
        o.placeholderValue = 'Pesquisar cliente';
        o.searchPlaceholderValue = 'Pesquisar Nome, Telemóvel, Email...';
        o.noResultsText = 'Nenhum cliente encontrado.';
        return o;
    }

    function agendaOcOnClientSearchEvent(ev) {
        var inst = agendaOcChoicesInstances.client;
        if (!inst) return;
        var q = (ev.detail && ev.detail.value != null) ? String(ev.detail.value).trim() : '';
        clearTimeout(agendaOcClientSearchTimer);
        if (!q.length) {
            return;
        }
        agendaOcClientSearchTimer = setTimeout(function() {
            fetch(agendaClientsUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(clients) {
                    if (!agendaOcChoicesInstances.client) return;
                    var items = (clients || []).map(function(c) {
                        var phone = c.formatted_phone || c.phone || '';
                        var label = (c.name || '') + (phone ? ' · ' + phone : '');
                        return { value: String(c.id), label: label };
                    });
                    agendaOcChoicesInstances.client.setChoices(items, 'value', 'label', true);
                })
                .catch(function() { /* ignore */ });
        }, 300);
    }

    function agendaOcClearNewClientForm() {
        var n = $id('agendaOcNewClientName');
        var e = $id('agendaOcNewClientEmail');
        var p = $id('agendaOcNewClientPhone');
        var tabNew = $id('agendaOcTabNew');
        if (n) n.value = '';
        if (e) e.value = '';
        if (p) p.value = '';
        if (tabNew) destroyAgendaCreateClientIntl(tabNew);
    }

    function agendaOcInitNewClientPhoneIntl() {
        var phoneEl = $id('agendaOcNewClientPhone');
        var tabNew = $id('agendaOcTabNew');
        if (!phoneEl || !tabNew) return;
        initAgendaCreateClientIntl(phoneEl, tabNew);
    }

    function agendaOcResetClientUi() {
        var notSel = $id('agendaOcClientNotSelectedWrap');
        var card = $id('agendaOcClientSelectedCard');
        if (notSel) notSel.classList.remove('d-none');
        if (card) card.classList.add('d-none');
        agendaOcSelectedClient = null;
        agendaOcClearNewClientForm();
        var tabBtn = $id('agendaOcTabExistingBtn');
        if (tabBtn && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            try {
                bootstrap.Tab.getOrCreateInstance(tabBtn).show();
            } catch (err) { /* ignore */ }
        }
    }

    function agendaOcApplyClientFromApi(c) {
        if (!c) return;
        agendaOcSelectedClient = {
            id: String(c.id),
            name: c.name || '',
            phone: c.phone || '',
            formatted_phone: c.formatted_phone || '',
            avatar_url: c.avatar_url || ''
        };
        var av = $id('agendaOcClientAvatar');
        var fb = $id('agendaOcClientAvatarFallback');
        $id('agendaOcClientSelectedName').textContent = c.name || '';
        $id('agendaOcClientSelectedPhone').textContent = agendaClientPhoneLabel(agendaOcSelectedClient);
        var pl = $id('agendaOcClientProfileLink');
        if (pl) {
            pl.href = clientesBaseUrl + '/' + c.id;
            pl.classList.remove('d-none');
        }
        if (c.avatar_url && av) {
            av.src = c.avatar_url;
            av.classList.remove('d-none');
            if (fb) fb.classList.add('d-none');
        } else if (av && fb) {
            av.classList.add('d-none');
            var initials = (c.name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
            fb.textContent = initials;
            fb.classList.remove('d-none');
        }
        var notSel = $id('agendaOcClientNotSelectedWrap');
        var sc = $id('agendaOcClientSelectedCard');
        if (notSel) notSel.classList.add('d-none');
        if (sc) sc.classList.remove('d-none');
    }

    function agendaOcInitClientChoicesSelect() {
        var clientSel = $id('agendaOcClient');
        if (!clientSel) return;
        if (agendaOcChoicesInstances.client) {
            try {
                agendaOcChoicesInstances.client.destroy();
            } catch (e) { /* ignore */ }
            agendaOcChoicesInstances.client = null;
        }
        clientSel.innerHTML = '<option value="">Pesquisar cliente</option>';
        agendaOcChoicesInstances.client = new Choices(clientSel, agendaOcClientChoicesOpts());
    }

    function agendaOcEnterClientSearchMode() {
        agendaOcSelectedClient = null;
        agendaOcClearNewClientForm();
        var notSel = $id('agendaOcClientNotSelectedWrap');
        var card = $id('agendaOcClientSelectedCard');
        if (card) card.classList.add('d-none');
        if (notSel) notSel.classList.remove('d-none');
        var tabBtn = $id('agendaOcTabExistingBtn');
        if (tabBtn && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            try {
                bootstrap.Tab.getOrCreateInstance(tabBtn).show();
            } catch (e) { /* ignore */ }
        }
        agendaOcInitClientChoicesSelect();
        if (!agendaOcClientRemoteSearchBound) {
            agendaOcClientRemoteSearchBound = true;
            $id('agendaOcClient').addEventListener('search', agendaOcOnClientSearchEvent);
        }
    }

    function agendaOcDestroyTestChoices() {
        ['client', 'service', 'member'].forEach(function(key) {
            var inst = agendaOcChoicesInstances[key];
            if (inst) {
                try {
                    inst.destroy();
                } catch (e) { /* ignore */ }
                agendaOcChoicesInstances[key] = null;
            }
        });
    }

    function agendaOcBuildDateOptions() {
        var opts = [];
        var base = new Date();
        base.setHours(12, 0, 0, 0);
        for (var i = -7; i <= 120; i++) {
            var d = new Date(base);
            d.setDate(d.getDate() + i);
            var ymd = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            opts.push({ value: ymd, label: DAYS_LONG[d.getDay()] + ', ' + d.getDate() + ' ' + MONTHS_LONG[d.getMonth()] });
        }
        return opts;
    }

    function agendaOcBuildTimeOptionElements() {
        var frag = document.createDocumentFragment();
        for (var h = 0; h < 24; h++) {
            for (var m = 0; m < 60; m += 15) {
                var v = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
                var opt = document.createElement('option');
                opt.value = v;
                opt.textContent = v;
                frag.appendChild(opt);
            }
        }
        return frag;
    }

    function agendaOcSnapTime15(d) {
        var h = d.getHours();
        var min = d.getMinutes();
        var rounded = Math.round(min / 15) * 15;
        if (rounded >= 60) {
            d.setHours(h + 1, 0, 0, 0);
        } else {
            d.setMinutes(rounded, 0, 0);
        }
        return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    function agendaOcCurrentRoundedTime() {
        return agendaOcSnapTime15(new Date());
    }

    /** Valor único no select: serviço simples = id; com variantes = "serviceId|optionId". */
    function agendaOcServicePicklistValue(entry) {
        if (!entry) return '';
        var sid = String(entry.service_id || '').trim();
        var oid = String(entry.service_option_id || '').trim();
        return oid ? (sid + '|' + oid) : sid;
    }

    function agendaOcFindFlatServiceEntry(flatList, rawValue) {
        var v = String(rawValue || '').trim();
        if (!v || !flatList || !flatList.length) return null;
        var pipe = v.indexOf('|');
        if (pipe !== -1) {
            var sid = v.slice(0, pipe);
            var oid = v.slice(pipe + 1);
            return flatList.find(function(s) {
                return String(s.service_id) === sid && String(s.service_option_id || '') === oid;
            }) || null;
        }
        return flatList.find(function(s) {
            return String(s.service_id) === v && !s.service_option_id;
        }) || null;
    }

    function agendaOcFlattenServicesFromCategories(categories) {
        var out = [];
        (categories || []).forEach(function(cat) {
            (cat.services || []).forEach(function(s) {
                var extras = Array.isArray(s.extras) ? s.extras : [];
                if (s.options && s.options.length > 0) {
                    (s.options || []).forEach(function(opt) {
                        var dur = parseInt(opt.duration, 10) || 60;
                        var priceNum = opt.price != null && opt.price !== '' ? parseFloat(opt.price) : 0;
                        out.push({
                            service_id: String(s.id),
                            service_option_id: String(opt.id),
                            name: String(opt.name || '').trim() || ('Opção #' + opt.id),
                            duration: dur,
                            price: priceNum,
                            original_price: priceNum,
                            formatted_duration: opt.formatted_duration || (dur + ' min'),
                            formatted_price: opt.formatted_price || '',
                            category_name: cat.name || '',
                            available_extras: extras,
                            extras: []
                        });
                    });
                } else {
                    var dur = parseInt(s.duration, 10) || 60;
                    var priceNum = s.price != null && s.price !== '' ? parseFloat(s.price) : 0;
                    out.push({
                        service_id: String(s.id),
                        service_option_id: '',
                        name: s.name || '',
                        duration: dur,
                        price: priceNum,
                        original_price: priceNum,
                        formatted_duration: s.formatted_duration || (dur + ' min'),
                        formatted_price: s.formatted_price || '',
                        category_name: cat.name || '',
                        available_extras: extras,
                        extras: []
                    });
                }
            });
        });
        return out;
    }

    function agendaOcApplyServiceFieldVisibility() {
        var selWrap = $id('agendaOcServiceSelectWrap');
        var addWrap = $id('agendaOcAddMoreServicesWrap');
        if (!selWrap || !addWrap) return;
        var n = agendaOcSelectedServices.length;
        if (n === 0) {
            agendaOcShowServicePicker = true;
            selWrap.classList.remove('d-none');
            addWrap.classList.add('d-none');
            return;
        }
        if (agendaOcShowServicePicker) {
            selWrap.classList.remove('d-none');
            addWrap.classList.add('d-none');
        } else {
            selWrap.classList.add('d-none');
            addWrap.classList.remove('d-none');
        }
    }

    function agendaOcRenderSelectedServices() {
        var wrap = $id('agendaOcSelectedServicesList');
        if (!wrap) return;
        if (!agendaOcSelectedServices.length) {
            wrap.classList.add('d-none');
            wrap.innerHTML = '';
            agendaOcApplyServiceFieldVisibility();
            return;
        }
        wrap.classList.remove('d-none');
        var html = agendaOcSelectedServices.map(function(item, idx) {
            var dur = parseInt(item.duration, 10) || 0;
            var price = parseFloat(item.price) || 0;
            var originalPrice = item.original_price != null ? parseFloat(item.original_price) : price;
            var hasPriceChange = Math.abs(price - originalPrice) > 0.0001;
            var priceBlock = hasPriceChange
                ? '<span class="text-danger text-decoration-line-through small me-1">' + (originalPrice.toFixed(2).replace('.', ',') + ' €') + '</span><span class="fw-semibold text-nowrap">' + price.toFixed(2).replace('.', ',') + ' €</span>'
                : '<span class="fw-semibold text-nowrap">' + price.toFixed(2).replace('.', ',') + ' €</span>';
            var addedExtraIds = (item.extras || []).map(function(e) { return e.id; });
            var extrasToAdd = (item.available_extras || []).filter(function(e) { return addedExtraIds.indexOf(e.id) === -1; });
            var iconsRow =
                '<div class="d-flex gap-1 justify-content-end flex-shrink-0">' +
                    (extrasToAdd.length > 0
                        ? '<button type="button" class="btn btn-outline-secondary btn-icon btn-sm agenda-oc-add-extras-btn" data-idx="' + idx + '" title="Adicionar extras" aria-label="Adicionar extras"><i class="ph ph-plus-circle"></i></button>'
                        : ''
                    ) +
                    '<button type="button" class="btn btn-outline-secondary btn-icon btn-sm agenda-oc-edit-service-btn" data-idx="' + idx + '" title="Alterar opções" aria-label="Alterar opções"><i class="ph ph-pencil-simple"></i></button>' +
                    '<button type="button" class="btn btn-outline-danger btn-icon btn-sm agenda-oc-remove-service-btn" data-idx="' + idx + '" title="Eliminar" aria-label="Eliminar"><i class="ph ph-trash"></i></button>' +
                '</div>';
            var extrasLine = (item.extras && item.extras.length)
                ? item.extras.map(function(e, eIdx) {
                    var priceText = e.formatted_price || ((parseFloat(e.price) || 0).toFixed(2).replace('.', ',') + ' €');
                    var durText = e.formatted_duration || ((e.duration || 0) + ' min');
                    return '' +
                        '<div class="agenda-oc-extra-row d-flex justify-content-between align-items-start mt-1 w-100" data-oc-idx="' + idx + '" data-extra-index="' + eIdx + '">' +
                            '<div class="nova-marcacao-service-item-left d-flex flex-column min-w-0">' +
                                '<div class="d-flex align-items-center">' +
                                    '<div class="small fw-medium">+ ' + (e.name || '').replace(/</g, '&lt;') + '</div>' +
                                    '<button type="button" class="btn btn-link btn-sm p-0 ms-1 agenda-oc-remove-extra-btn" data-idx="' + idx + '" data-extra-index="' + eIdx + '" aria-label="Remover extra">' +
                                        '<i class="ph ph-x"></i>' +
                                    '</button>' +
                                '</div>' +
                                '<div class="small text-muted">' + durText + '</div>' +
                            '</div>' +
                            '<div class="small text-nowrap">' + priceText + '</div>' +
                        '</div>';
                }).join('')
                : '';
            var addExtrasPanelHtml = '';
            if (extrasToAdd.length > 0) {
                addExtrasPanelHtml =
                    '<div class="agenda-oc-add-extras-panel d-none mt-2" data-add-extras-idx="' + idx + '">' +
                        '<div class="small fw-semibold text-body mb-2">Adicionar extra</div>' +
                        '<div class="d-flex flex-column gap-1 agenda-oc-add-extras-list">' +
                        extrasToAdd.map(function(ex) {
                            return '<button type="button" class="btn btn-light btn-sm w-100 text-start d-flex justify-content-between align-items-center agenda-oc-pick-extra-btn" data-idx="' + idx + '" data-extra-id="' + String(ex.id).replace(/"/g, '') + '">' +
                                '<span class="text-truncate me-2">' + (ex.name || '').replace(/</g, '&lt;') + '</span>' +
                                '<span class="small text-muted text-nowrap flex-shrink-0">' + (ex.formatted_price || ex.price + ' €') + ' · ' + (ex.formatted_duration || ex.duration + ' min') + '</span>' +
                            '</button>';
                        }).join('') +
                        '</div>' +
                        '<div class="d-flex justify-content-end mt-2">' +
                            '<button type="button" class="btn btn-link btn-sm p-0 agenda-oc-add-extras-close-btn" data-idx="' + idx + '">Fechar</button>' +
                        '</div>' +
                    '</div>';
            }
            return '<div class="border rounded p-2 mb-2 agenda-oc-service-card" data-oc-idx="' + idx + '">' +
                '<div class="d-flex justify-content-between align-items-start gap-2">' +
                    '<div class="min-w-0">' +
                        '<div class="fw-semibold text-truncate">' + (item.name || 'Serviço') + '</div>' +
                        agendaOcServiceDurationCategoryMeta(item) +
                    '</div>' +
                    '<div class="d-flex flex-column align-items-end gap-1 flex-shrink-0 text-end">' +
                        '<div class="nova-marcacao-service-item-price-row text-nowrap">' + priceBlock + '</div>' +
                        iconsRow +
                    '</div>' +
                '</div>' +
                extrasLine +
                addExtrasPanelHtml +
                '<div class="agenda-oc-edit-service-panel d-none mt-2 pt-2 border-top" data-edit-idx="' + idx + '">' +
                    '<div class="row g-2">' +
                        '<div class="col-6"><label class="form-label form-label-sm small mb-1">Duração (min)</label><input type="number" min="1" step="1" class="form-control form-control-sm agenda-oc-edit-duration" value="' + dur + '"></div>' +
                        '<div class="col-6"><label class="form-label form-label-sm small mb-1">Preço (€)</label><input type="number" min="0" step="0.01" class="form-control form-control-sm agenda-oc-edit-price" value="' + price.toFixed(2) + '"></div>' +
                    '</div>' +
                    '<div class="d-flex justify-content-end gap-1 mt-2">' +
                        '<button type="button" class="btn btn-light btn-sm agenda-oc-edit-cancel-btn" data-idx="' + idx + '">Cancelar</button>' +
                        '<button type="button" class="btn btn-primary btn-sm agenda-oc-edit-save-btn" data-idx="' + idx + '">Guardar</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        }).join('');
        wrap.innerHTML = html;

        wrap.querySelectorAll('.agenda-oc-remove-service-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = parseInt(this.dataset.idx, 10);
                if (isNaN(idx) || !agendaOcSelectedServices[idx]) return;
                agendaOcSelectedServices.splice(idx, 1);
                agendaOcRenderSelectedServices();
            });
        });
        wrap.querySelectorAll('.agenda-oc-add-extras-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var idx = parseInt(this.dataset.idx, 10);
                if (isNaN(idx)) return;
                var panel = wrap.querySelector('.agenda-oc-add-extras-panel[data-add-extras-idx="' + idx + '"]');
                if (!panel) return;
                var wasHidden = panel.classList.contains('d-none');
                wrap.querySelectorAll('.agenda-oc-add-extras-panel').forEach(function(p) { p.classList.add('d-none'); });
                wrap.querySelectorAll('.agenda-oc-edit-service-panel').forEach(function(p) { p.classList.add('d-none'); });
                if (wasHidden) panel.classList.remove('d-none');
            });
        });
        wrap.querySelectorAll('.agenda-oc-add-extras-close-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var idx = parseInt(this.dataset.idx, 10);
                var panel = wrap.querySelector('.agenda-oc-add-extras-panel[data-add-extras-idx="' + idx + '"]');
                if (panel) panel.classList.add('d-none');
            });
        });
        wrap.querySelectorAll('.agenda-oc-pick-extra-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var idx = parseInt(this.dataset.idx, 10);
                var exIdRaw = this.getAttribute('data-extra-id');
                if (isNaN(idx) || exIdRaw == null || exIdRaw === '') return;
                var svc = agendaOcSelectedServices[idx];
                if (!svc || !svc.available_extras) return;
                var ex = svc.available_extras.find(function(x) { return String(x.id) === String(exIdRaw); });
                if (!ex) return;
                if (!svc.extras) svc.extras = [];
                svc.extras.push({
                    id: ex.id,
                    name: ex.name,
                    duration: ex.duration || 0,
                    price: ex.price || 0,
                    formatted_duration: ex.formatted_duration || (ex.duration || 0) + ' min',
                    formatted_price: ex.formatted_price || (parseFloat(ex.price) || 0).toFixed(2).replace('.', ',') + ' €'
                });
                agendaOcRenderSelectedServices();
            });
        });
        wrap.querySelectorAll('.agenda-oc-remove-extra-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var sIdx = parseInt(this.dataset.idx, 10);
                var exIdx = parseInt(this.dataset.extraIndex, 10);
                if (!isNaN(sIdx) && !isNaN(exIdx) && agendaOcSelectedServices[sIdx] && Array.isArray(agendaOcSelectedServices[sIdx].extras)) {
                    agendaOcSelectedServices[sIdx].extras.splice(exIdx, 1);
                    agendaOcRenderSelectedServices();
                }
            });
        });
        wrap.querySelectorAll('.agenda-oc-edit-service-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = parseInt(this.dataset.idx, 10);
                if (isNaN(idx)) return;
                wrap.querySelectorAll('.agenda-oc-add-extras-panel').forEach(function(p) { p.classList.add('d-none'); });
                wrap.querySelectorAll('.agenda-oc-edit-service-panel').forEach(function(p) { p.classList.add('d-none'); });
                var panel = wrap.querySelector('.agenda-oc-edit-service-panel[data-edit-idx="' + idx + '"]');
                if (panel) panel.classList.remove('d-none');
            });
        });
        wrap.querySelectorAll('.agenda-oc-edit-cancel-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = parseInt(this.dataset.idx, 10);
                var panel = wrap.querySelector('.agenda-oc-edit-service-panel[data-edit-idx="' + idx + '"]');
                if (panel) panel.classList.add('d-none');
            });
        });
        wrap.querySelectorAll('.agenda-oc-edit-save-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = parseInt(this.dataset.idx, 10);
                if (isNaN(idx) || !agendaOcSelectedServices[idx]) return;
                var panel = wrap.querySelector('.agenda-oc-edit-service-panel[data-edit-idx="' + idx + '"]');
                if (!panel) return;
                var durInput = panel.querySelector('.agenda-oc-edit-duration');
                var priceInput = panel.querySelector('.agenda-oc-edit-price');
                var dur = parseInt(durInput && durInput.value, 10);
                var price = parseFloat(priceInput && priceInput.value);
                if (isNaN(dur) || dur < 1) {
                    showToast('Duração inválida.', 'error');
                    return;
                }
                if (isNaN(price) || price < 0) {
                    showToast('Preço inválido.', 'error');
                    return;
                }
                agendaOcSelectedServices[idx].duration = dur;
                agendaOcSelectedServices[idx].price = price;
                agendaOcSelectedServices[idx].formatted_duration = dur + ' min';
                agendaOcSelectedServices[idx].formatted_price = price.toFixed(2).replace('.', ',') + ' €';
                agendaOcRenderSelectedServices();
            });
        });
        agendaOcApplyServiceFieldVisibility();
    }

    /**
     * Select nativo com <optgroup>: categoria → serviços simples; categoria — serviço pai → só nomes das variantes.
     */
    function agendaOcRebuildServiceSelect(svcSel, categories) {
        svcSel.innerHTML = '<option value="">Selecionar serviço</option>';
        var hasAny = false;
        (categories || []).forEach(function(cat) {
            var catLabel = String(cat.name || '').trim() || 'Serviços';
            var services = cat.services || [];
            var simpleBatch = [];
            var multiList = [];
            services.forEach(function(s) {
                if (s.options && s.options.length > 0) {
                    multiList.push(s);
                } else {
                    simpleBatch.push(s);
                }
            });
            if (simpleBatch.length > 0) {
                hasAny = true;
                var ogSimple = document.createElement('optgroup');
                ogSimple.label = catLabel;
                simpleBatch.forEach(function(s) {
                    var opt = document.createElement('option');
                    opt.value = String(s.id);
                    var dur = parseInt(s.duration, 10) || 60;
                    var durPart = s.formatted_duration || (dur + ' min');
                    opt.textContent = (s.name || '') + ' (' + durPart + ')';
                    opt.dataset.duration = String(dur);
                    var priceNum = s.price != null && s.price !== '' ? parseFloat(s.price) : 0;
                    opt.dataset.price = String(priceNum);
                    opt.dataset.name = s.name || '';
                    ogSimple.appendChild(opt);
                });
                svcSel.appendChild(ogSimple);
            }
            multiList.forEach(function(s) {
                hasAny = true;
                var og = document.createElement('optgroup');
                og.label = catLabel + ' — ' + (s.name || ('Serviço #' + s.id));
                (s.options || []).forEach(function(opt) {
                    var optEl = document.createElement('option');
                    optEl.value = String(s.id) + '|' + String(opt.id);
                    optEl.textContent = String(opt.name || '').trim() || ('Opção #' + opt.id);
                    var dur = parseInt(opt.duration, 10) || 60;
                    var priceNum = opt.price != null && opt.price !== '' ? parseFloat(opt.price) : 0;
                    optEl.dataset.duration = String(dur);
                    optEl.dataset.price = String(priceNum);
                    optEl.dataset.name = String(opt.name || '').trim() || ('Opção #' + opt.id);
                    og.appendChild(optEl);
                });
                svcSel.appendChild(og);
            });
        });
        svcSel.disabled = !hasAny;
    }

    function agendaOcReloadServicesForMember(memberId, done) {
        var svcSel = $id('agendaOcService');
        if (!svcSel) {
            if (done) done();
            return;
        }
        if (agendaOcChoicesInstances.service) {
            try {
                agendaOcChoicesInstances.service.destroy();
            } catch (e) { /* ignore */ }
            agendaOcChoicesInstances.service = null;
        }
        if (!memberId) {
            agendaOcServicesFlat = [];
            svcSel.innerHTML = '<option value="">Escolha um profissional</option>';
            svcSel.disabled = true;
            agendaOcChoicesInstances.service = new Choices(svcSel, agendaOcCommonChoicesOpts());
            if (done) done();
            return;
        }
        svcSel.innerHTML = '<option value="">A carregar…</option>';
        svcSel.disabled = true;
        fetch(agendaMembersServicesUrl + '/' + memberId + '/services', { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                agendaOcServicesFlat = agendaOcFlattenServicesFromCategories(data.categories);
                agendaOcRebuildServiceSelect(svcSel, data.categories || []);
                agendaOcChoicesInstances.service = new Choices(svcSel, agendaOcCommonChoicesOpts());
                if (done) done();
            })
            .catch(function() {
                agendaOcServicesFlat = [];
                svcSel.innerHTML = '<option value="">Erro ao carregar</option>';
                svcSel.disabled = true;
                agendaOcChoicesInstances.service = new Choices(svcSel, agendaOcCommonChoicesOpts());
                if (done) done();
            });
    }

    function openAgendaMarcacaoTestOffcanvas(startStr, endStr, resourceId, preSelectedClientId) {
        if (typeof Choices === 'undefined') {
            showToast('Choices.js não está disponível.', 'error');
            return;
        }
        var ocEl = $id('agendaMarcacaoTestOffcanvas');
        if (!ocEl) {
            showToast('Offcanvas de teste não encontrado.', 'error');
            return;
        }

        agendaOcDestroyTestChoices();
        agendaOcResetClientUi();

        var startD = new Date(startStr);
        if (isNaN(startD.getTime())) {
            startD = new Date();
        }

        var hasResource = resourceId != null && String(resourceId) !== '';
        var memberId = '';
        if (hasResource) {
            memberId = String(resourceId);
        } else if (currentUserIsAdmin) {
            memberId = '';
        } else {
            var uid = String(C.authId || '');
            var inList = (C.usersForConsultant || []).some(function(u) { return String(u.id) === uid; });
            memberId = inList ? uid : '';
        }

        var memSel = $id('agendaOcMember');
        memSel.innerHTML = '<option value="">Selecionar</option>';
        (C.usersForConsultant || []).forEach(function(u) {
            var opt = document.createElement('option');
            opt.value = String(u.id);
            opt.textContent = u.name || ('#' + u.id);
            memSel.appendChild(opt);
        });
        if (memberId) {
            memSel.value = memberId;
        }

        var ymd = startD.getFullYear() + '-' + String(startD.getMonth() + 1).padStart(2, '0') + '-' + String(startD.getDate()).padStart(2, '0');
        /* Hora do intervalo passado (clique na grelha); toolbar/mobile «Adicionar» já envia getClosestSlotToNow() ≈ agora. */
        var timeRounded = agendaOcSnapTime15(new Date(startD.getTime()));

        var dateSel = $id('agendaOcDate');
        if (dateSel) {
            if (!agendaOcDateFlatpickr && typeof flatpickr !== 'undefined') {
                agendaOcDateFlatpickr = flatpickr(dateSel, {
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'l, j F',
                    locale: (flatpickr && flatpickr.l10ns && flatpickr.l10ns.pt) ? flatpickr.l10ns.pt : undefined,
                    allowInput: true,
                    disableMobile: true
                });
                if (agendaOcDateFlatpickr && agendaOcDateFlatpickr.altInput) {
                    agendaOcDateFlatpickr.altInput.removeAttribute('readonly');
                    agendaOcDateFlatpickr.altInput.style.cursor = 'pointer';
                }
            }
            if (agendaOcDateFlatpickr) {
                agendaOcDateFlatpickr.setDate(ymd, false);
            } else {
                dateSel.value = ymd;
            }
            dateSel.addEventListener('click', function() {
                if (agendaOcDateFlatpickr) agendaOcDateFlatpickr.open();
            }, { once: true });
        }

        var timeSel = $id('agendaOcTime');
        timeSel.innerHTML = '';
        timeSel.appendChild(agendaOcBuildTimeOptionElements());
        timeSel.value = timeRounded;
        if (!timeSel.value) {
            timeSel.value = '09:00';
        }

        $id('agendaOcObs').value = '';
        agendaOcSelectedServices = [];
        agendaOcShowServicePicker = true;
        agendaOcRenderSelectedServices();

        var clientSel = $id('agendaOcClient');
        clientSel.innerHTML = '<option value="">A carregar clientes…</option>';

        var svcSel = $id('agendaOcService');
        svcSel.innerHTML = '<option value="">A carregar…</option>';
        svcSel.disabled = true;

        agendaOcChoicesInstances.member = new Choices(memSel, agendaOcCommonChoicesOpts());

        var off = bootstrap.Offcanvas.getOrCreateInstance(ocEl, { scroll: true });
        off.show();

        (memberId
            ? fetch(agendaMembersServicesUrl + '/' + memberId + '/services', { headers: { 'Accept': 'application/json' } }).then(function(r) { return r.json(); })
            : Promise.resolve({ categories: [] })
        )
            .then(function(svcData) {
                agendaOcInitClientChoicesSelect();
                if (!agendaOcClientRemoteSearchBound) {
                    agendaOcClientRemoteSearchBound = true;
                    clientSel.addEventListener('search', agendaOcOnClientSearchEvent);
                }
                if (preSelectedClientId) {
                    fetch(agendaClientsUrl + '?client_id=' + encodeURIComponent(preSelectedClientId), { headers: { 'Accept': 'application/json' } })
                        .then(function(r) { return r.json(); })
                        .then(function(clients) {
                            if (!agendaOcChoicesInstances.client || !clients || !clients[0]) return;
                            var c = clients[0];
                            var phone = c.formatted_phone || c.phone || '';
                            var label = (c.name || '') + (phone ? ' · ' + phone : '');
                            agendaOcChoicesInstances.client.setChoices(
                                [{ value: String(c.id), label: label }],
                                'value',
                                'label',
                                true
                            );
                            try {
                                agendaOcChoicesInstances.client.setChoiceByValue(String(preSelectedClientId));
                            } catch (e) { /* ignore */ }
                            agendaOcApplyClientFromApi(c);
                        })
                        .catch(function() { /* ignore */ });
                }

                agendaOcServicesFlat = agendaOcFlattenServicesFromCategories(svcData.categories);
                agendaOcRebuildServiceSelect(svcSel, svcData.categories || []);
                agendaOcChoicesInstances.service = new Choices(svcSel, agendaOcCommonChoicesOpts());

            })
            .catch(function() {
                showToast('Erro ao preparar o formulário.', 'error');
            });

        if (!agendaOcTestFormBound) {
            agendaOcTestFormBound = true;
            $id('agendaOcMember').addEventListener('change', function() {
                agendaOcSelectedServices = [];
                agendaOcShowServicePicker = true;
                agendaOcRenderSelectedServices();
                agendaOcReloadServicesForMember(this.value || '', null);
            });
            $id('agendaOcService').addEventListener('change', function() {
                var raw = (this.value || '').trim();
                if (!raw) return;
                var svc = agendaOcFindFlatServiceEntry(agendaOcServicesFlat, raw);
                if (!svc) return;
                var already = agendaOcSelectedServices.some(function(s) {
                    return String(s.service_id) === String(svc.service_id) && String(s.service_option_id || '') === String(svc.service_option_id || '');
                });
                if (already) {
                    showToast('Serviço já adicionado.', 'warning');
                } else {
                    agendaOcSelectedServices.push({
                        service_id: svc.service_id,
                        service_option_id: svc.service_option_id || '',
                        name: svc.name || '',
                        duration: parseInt(svc.duration, 10) || 60,
                        price: parseFloat(svc.price) || 0,
                        original_price: svc.original_price != null ? svc.original_price : (parseFloat(svc.price) || 0),
                        formatted_duration: svc.formatted_duration || ((parseInt(svc.duration, 10) || 60) + ' min'),
                        formatted_price: svc.formatted_price || ((parseFloat(svc.price) || 0).toFixed(2).replace('.', ',') + ' €'),
                        category_name: svc.category_name || '',
                        available_extras: svc.available_extras || [],
                        extras: []
                    });
                    agendaOcShowServicePicker = false;
                    agendaOcRenderSelectedServices();
                }
                this.value = '';
                if (agendaOcChoicesInstances.service && typeof agendaOcChoicesInstances.service.setChoiceByValue === 'function') {
                    try { agendaOcChoicesInstances.service.setChoiceByValue(''); } catch (e) { /* ignore */ }
                }
            });
            $id('agendaOcAddMoreServicesBtn').addEventListener('click', function() {
                agendaOcShowServicePicker = true;
                agendaOcApplyServiceFieldVisibility();
                var ch = agendaOcChoicesInstances.service;
                var selWrap = $id('agendaOcServiceSelectWrap');
                setTimeout(function() {
                    if (ch && typeof ch.showDropdown === 'function') {
                        try {
                            ch.showDropdown(true);
                            return;
                        } catch (e) { /* ignore */ }
                    }
                    var inner = selWrap && selWrap.querySelector('.choices__inner');
                    if (inner) {
                        inner.click();
                    }
                }, 0);
            });
            $id('agendaMarcacaoTestForm').addEventListener('submit', function(e) {
                e.preventDefault();
                var mid = ($id('agendaOcMember').value || '').trim();
                if (!mid) {
                    showToast('Selecione um profissional.', 'error');
                    return;
                }
                var cid = (agendaOcSelectedClient && agendaOcSelectedClient.id)
                    ? String(agendaOcSelectedClient.id).trim()
                    : ($id('agendaOcClient').value || '').trim();
                if (!cid) {
                    showToast('Selecione ou crie um cliente.', 'error');
                    return;
                }
                if (!agendaOcSelectedServices.length) {
                    showToast('Adicione pelo menos um serviço.', 'error');
                    return;
                }
                var dStr = $id('agendaOcDate').value;
                var tStr = $id('agendaOcTime').value;
                if (!dStr || !tStr) {
                    showToast('Indique data e hora.', 'error');
                    return;
                }
                var startLocal = dStr + 'T' + tStr;
                var startDate = parseAgendaLocalDateTime(startLocal);
                if (!startDate || isNaN(startDate.getTime())) {
                    showToast('Data ou hora inválida.', 'error');
                    return;
                }
                var totalDuration = agendaOcSelectedServices.reduce(function(sum, s) {
                    var base = parseInt(s.duration, 10) || 0;
                    var ex = (s.extras || []).reduce(function(s2, e) { return s2 + (parseInt(e.duration, 10) || 0); }, 0);
                    return sum + base + ex;
                }, 0);
                if (totalDuration <= 0) totalDuration = 60;
                var endDate = new Date(startDate.getTime() + totalDuration * 60000);
                var endLocal = agendaFormatLocalDateTimeForInput(endDate);

                var clientName = (agendaOcSelectedClient && agendaOcSelectedClient.name) ? agendaOcSelectedClient.name.trim() : '';
                if (!clientName) {
                    var selOpt = $id('agendaOcClient').selectedOptions && $id('agendaOcClient').selectedOptions[0];
                    if (selOpt) {
                        clientName = (selOpt.textContent || '').split('·')[0].trim();
                    }
                }
                var serviceNames = agendaOcSelectedServices.map(function(s) { return s.name || ''; }).filter(Boolean).join(', ');
                var title = (clientName || 'Cliente') + ' - ' + (serviceNames || 'Serviço');
                var btn = $id('agendaOcSubmit');
                btn.disabled = true;
                var origHtml = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>A guardar…';
                var payload = {
                    title: title,
                    start_at: agendaLocalInputToUtcIso(startLocal),
                    end_at: agendaLocalInputToUtcIso(endLocal),
                    description: $id('agendaOcObs').value,
                    event_type: 'marcacao',
                    user_id: mid,
                    client_id: cid,
                    services: agendaOcSelectedServices.map(function(s) {
                        var row = {
                            service_id: s.service_id,
                            duration: s.duration,
                            price: s.price,
                            original_price: s.original_price != null ? s.original_price : s.price,
                            extras: (s.extras || []).map(function(ex) {
                                return { extra_id: ex.id, duration: ex.duration || 0, price: ex.price || 0 };
                            })
                        };
                        if (s.service_option_id) {
                            row.service_option_id = parseInt(s.service_option_id, 10);
                        }
                        return row;
                    })
                };
                fetch((C.urlEvents || ''), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': C.csrf || '', 'X-Requested-With': 'XMLHttpRequest' },
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
                        btn.innerHTML = origHtml;
                        if (res.success && res.event) {
                            if (typeof calendar !== 'undefined') {
                                calendar.refetchEvents();
                            }
                            bootstrap.Offcanvas.getInstance($id('agendaMarcacaoTestOffcanvas'))?.hide();
                        } else {
                            showToast(res.message || 'Erro ao criar marcação.', 'error');
                        }
                    })
                    .catch(function(err) {
                        btn.disabled = false;
                        btn.innerHTML = origHtml;
                        var msg = (err && err.message && String(err.message).indexOf('Unexpected') === -1) ? err.message : 'Erro de ligação.';
                        showToast(msg, 'error');
                    });
            });

            ocEl.addEventListener('hidden.bs.offcanvas', function() {
                clearTimeout(agendaOcClientSearchTimer);
                agendaOcClientSearchTimer = null;
                agendaOcDestroyTestChoices();
                agendaOcResetClientUi();
                agendaOcServicesFlat = [];
                agendaOcSelectedServices = [];
                agendaOcShowServicePicker = true;
                agendaOcRenderSelectedServices();
            });
            if (!agendaOcClientChangeBound) {
                agendaOcClientChangeBound = true;
                $id('agendaOcClient').addEventListener('change', function() {
                    var id = (this.value || '').trim();
                    if (!id) {
                        agendaOcSelectedClient = null;
                        return;
                    }
                    fetch(agendaClientsUrl + '?client_id=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } })
                        .then(function(r) { return r.json(); })
                        .then(function(clients) {
                            if (clients && clients[0]) {
                                agendaOcApplyClientFromApi(clients[0]);
                            }
                        })
                        .catch(function() { /* ignore */ });
                });
                $id('agendaOcClientEditBtn').addEventListener('click', function() {
                    agendaOcEnterClientSearchMode();
                });
                var newTabBtn = $id('agendaOcTabNewBtn');
                if (newTabBtn) {
                    newTabBtn.addEventListener('shown.bs.tab', function() {
                        agendaOcInitNewClientPhoneIntl();
                    });
                }
                var agendaOcNewClientSubmitBtn = $id('agendaOcNewClientSubmit');
                if (agendaOcNewClientSubmitBtn) {
                    agendaOcNewClientSubmitBtn.addEventListener('click', function() {
                        var name = ($id('agendaOcNewClientName') && $id('agendaOcNewClientName').value || '').trim();
                        var email = ($id('agendaOcNewClientEmail') && $id('agendaOcNewClientEmail').value || '').trim();
                        var tabNew = $id('agendaOcTabNew');
                        var iti = tabNew && tabNew._agendaPhoneIti;
                        if (!name) {
                            showToast('Preencha o nome.', 'error');
                            return;
                        }
                        if (!iti) {
                            agendaOcInitNewClientPhoneIntl();
                            iti = tabNew && tabNew._agendaPhoneIti;
                        }
                        if (!iti) {
                            showToast('Campo de telemóvel indisponível. Recarregue a página.', 'error');
                            return;
                        }
                        iti.promise.then(function() {
                            if (!iti.isValidNumber()) {
                                showToast('Indique um número de telemóvel válido para o país selecionado (ex.: 9 dígitos em Portugal).', 'error');
                                return;
                            }
                            var phone = iti.getNumber();
                            var btn = $id('agendaOcNewClientSubmit');
                            btn.disabled = true;
                            var origTxt = btn.textContent;
                            btn.textContent = 'A criar…';
                            fetch(agendaClientsUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                                body: JSON.stringify({ name: name, email: email || null, phone: phone })
                            })
                                .then(function(r) {
                                    return r.json().then(function(data) {
                                        if (!r.ok) {
                                            throw new Error(agendaStoreClientCreateErrorMessage(data));
                                        }
                                        return data;
                                    });
                                })
                                .then(function(client) {
                                    btn.disabled = false;
                                    btn.textContent = origTxt;
                                    agendaOcClearNewClientForm();
                                    agendaOcApplyClientFromApi(client);
                                    showToast('Cliente criado com sucesso.', 'success');
                                })
                                .catch(function(err) {
                                    btn.disabled = false;
                                    btn.textContent = origTxt;
                                    showToast(err.message || 'Erro ao criar cliente.', 'error');
                                });
                        }).catch(function() {
                            showToast('Não foi possível validar o número. Verifique a ligação e tente de novo.', 'error');
                        });
                    });
                }
            }
        }
    }

    function openNovaMarcacaoModal(startStr, endStr, resourceId, preSelectedClientId) {
        openAgendaMarcacaoTestOffcanvas(startStr, endStr, resourceId, preSelectedClientId);
    }

    /** intl-tel-input: aba «Novo cliente» no offcanvas (nova marcação / editar marcação) */
    function destroyAgendaCreateClientIntl(popup) {
        if (popup && popup._agendaPhoneIti) {
            try {
                popup._agendaPhoneIti.destroy();
            } catch (e) { /* ignore */ }
            popup._agendaPhoneIti = null;
        }
    }
    /** Carrega i18n PT (países + interface) como em clientes/partials/intl-phone-init. */
    function initAgendaCreateClientIntl(phoneInput, popup) {
        destroyAgendaCreateClientIntl(popup);
        if (!phoneInput || typeof window.intlTelInput !== 'function') {
            return Promise.resolve();
        }
        var intlPtBase = 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/js/i18n/pt';
        var loadPt = Promise.all([
            import(intlPtBase + '/countries.js'),
            import(intlPtBase + '/interface.js'),
        ]).then(function(mods) {
            return Object.assign({}, mods[0].default, mods[1].default);
        }).catch(function(err) {
            console.warn('intl-tel-input (agenda): locale PT não carregado', err);
            return {};
        });
        return loadPt.then(function(ptI18n) {
            var iti = window.intlTelInput(phoneInput, {
                initialCountry: 'pt',
                countryOrder: ['pt', 'br', 'es', 'fr', 'gb', 'de'],
                separateDialCode: true,
                strictMode: true,
                validationNumberType: 'MOBILE',
                utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/js/utils.js',
                i18n: Object.assign({}, ptI18n, {
                    searchPlaceholder: 'Pesquisar',
                    zeroSearchResults: 'Nenhum resultado',
                }),
            });
            popup._agendaPhoneIti = iti;
        });
    }

    $id('eventDetailEditForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (eventDetailCurrentData && eventDetailCurrentData.event_type === 'marcacao') {
            eventDetailOcSyncHiddenFromPickers();
        }
        var id = $id('eventDetailEditId').value;
        var title = eventDetailCurrentData?.title || '';
        if (eventDetailCurrentData?.event_type === 'marcacao' && eventDetailSelectedServices.length > 0) {
            var clientName = (eventDetailSelectedClient && eventDetailSelectedClient.name) || eventDetailCurrentData.client_name || '';
            var serviceNames = eventDetailSelectedServices.map(function(s) { return s.name; }).join(', ');
            title = (clientName || 'Cliente') + ' - ' + serviceNames;
        }
        var totalDur = eventDetailEffectiveDurationMinutes();
        var startStr = $id('eventDetailEditStart').value;
        var endStr = $id('eventDetailEditEnd').value || startStr;
        if (totalDur > 0 && startStr) {
            var start = new Date(startStr);
            if (!isNaN(start.getTime())) {
                var end = new Date(start.getTime() + totalDur * 60 * 1000);
                endStr = agendaFormatLocalDateTimeForInput(end);
            }
        }
        var obsVal = ($id('eventDetailOcObs') && $id('eventDetailOcObs').value) || '';
        var payload = {
            title: title,
            start_at: agendaLocalInputToUtcIso(startStr),
            end_at: agendaLocalInputToUtcIso(endStr),
            description: obsVal,
            status: $id('eventDetailStatus').value
        };
        if (eventDetailCurrentData?.event_type === 'marcacao') {
            var memV = ($id('eventDetailOcMember') && $id('eventDetailOcMember').value || '').trim();
            if (memV) payload.user_id = memV;
            payload.client_id = eventDetailSelectedClient ? eventDetailSelectedClient.id : null;
            payload.services = eventDetailSelectedServices.map(function(s) {
                var row = {
                    service_id: s.service_id,
                    duration: s.duration,
                    price: s.price,
                    original_price: s.original_price != null ? s.original_price : s.price,
                    extras: (s.extras || []).map(function(e) { return { extra_id: e.id, duration: e.duration || 0, price: e.price || 0 }; })
                };
                if (s.service_option_id) {
                    row.service_option_id = parseInt(s.service_option_id, 10);
                }
                return row;
            });
        }
        var btn = $id('eventDetailSaveBtn');
        var needNotifyConfirm = eventDetailCurrentData && eventDetailCurrentData.event_type === 'marcacao' && payload.client_id && eventDetailScheduleTimesChanged(payload);
        if (needNotifyConfirm) {
            agendaDragPending = {
                kind: 'eventDetail',
                eventId: id,
                putPayload: payload,
                extendedProps: getEventDetailNotifyExtendedProps()
            };
            openAgendaDragConfirmModal(agendaDragPending.extendedProps);
            return;
        }
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
                // Garante atualização visual imediata da duração no calendário
                // (especialmente quando há alterações de serviços/extras).
                calendar.refetchEvents();
                bootstrap.Offcanvas.getInstance($id('eventDetailEditModal'))?.hide();
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
        var paidOnline = Math.max(0, parseFloat(eventDetailBookingPaidAmount) || 0);
        var servicesDue = Math.max(0, sub - paidOnline);
        var gorjeta = parseFloat($id('paymentGorjeta').value) || 0;
        if (gorjeta < 0) gorjeta = 0;
        $id('paymentSubtotalDisplay').textContent = sub.toFixed(2).replace('.', ',') + ' €';
        var subtotalNoVatEl = $id('paymentSubtotalNoVatDisplay');
        if (subtotalNoVatEl) {
            subtotalNoVatEl.textContent = (sub / 1.23).toFixed(2).replace('.', ',') + ' €';
        }
        var paidLine = $id('paymentOnlinePaidLine');
        var paidDisplay = $id('paymentOnlinePaidDisplay');
        if (paidDisplay) paidDisplay.textContent = '-' + paidOnline.toFixed(2).replace('.', ',') + ' €';
        if (paidLine) paidLine.classList.toggle('d-none', paidOnline <= 0);
        var dueLine = $id('paymentServicesDueLine');
        var dueDisplay = $id('paymentServicesDueDisplay');
        if (dueDisplay) dueDisplay.textContent = servicesDue.toFixed(2).replace('.', ',') + ' €';
        if (dueLine) dueLine.classList.toggle('d-none', paidOnline <= 0);
        $id('paymentGorjetaDisplay').textContent = gorjeta.toFixed(2).replace('.', ',') + ' €';
        var gorjetaLine = $id('paymentGorjetaLine');
        if (gorjetaLine) gorjetaLine.classList.toggle('d-none', gorjeta <= 0);
        $id('paymentTotalDisplay').textContent = (servicesDue + gorjeta).toFixed(2).replace('.', ',') + ' €';
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
        var phoneWrap = $id('paymentMbwayPhoneWrap');
        var phoneInput = $id('paymentMbwayPhone');
        if (phoneWrap && phoneInput) {
            phoneWrap.classList.add('d-none');
            var rawPhone = '';
            if (eventDetailSelectedClient && eventDetailSelectedClient.phone) {
                rawPhone = String(eventDetailSelectedClient.phone || '');
            }
            phoneInput.value = rawPhone;
        }
        bootstrap.Modal.getOrCreateInstance($id('paymentModal')).show();
    });

    $('#paymentMethodToggleGroup').addEventListener('click', function(e) {
        var card = e.target.closest('.payment-method-card');
        if (!card) return;
        $$('#paymentMethodToggleGroup .payment-method-card').forEach(function(c) { c.classList.remove('active'); });
        card.classList.add('active');
        var method = card.dataset.method || '';
        $id('paymentMethodValue').value = method;
        var phoneWrap = $id('paymentMbwayPhoneWrap');
        if (phoneWrap) {
            phoneWrap.classList.toggle('d-none', method !== 'mbway');
        }
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
        if (method === 'mbway') {
            var mbwayPhoneInput = $id('paymentMbwayPhone');
            var mbwayPhone = mbwayPhoneInput ? String(mbwayPhoneInput.value || '').trim() : '';
            fetch(C.agendaCheckoutMbwayIntentUrl || '', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ event_id: eventId, gorjeta: gorjeta, items: items, mbway_phone: mbwayPhone })
            })
            .then(function(r) { return r.json().then(function(res) { return { ok: r.ok, res: res }; }); })
            .then(function(_) {
                var ok = _.ok;
                var res = _.res || {};
                if (!ok) {
                    btn.disabled = false;
                    btn.innerHTML = 'Confirmar e faturar';
                    showToast(res.error || res.message || 'Erro ao gerar pedido MB WAY.', 'error');
                    return;
                }
                showToast(res.message || 'Pedido MB WAY enviado para o cliente.', 'success');
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>A aguardar confirmação MB WAY...';
                var paymentIntentId = res.payment_intent_id;
                if (!paymentIntentId) {
                    btn.disabled = false;
                    btn.innerHTML = 'Confirmar e faturar';
                    showToast('Resposta MB WAY inválida.', 'error');
                    return;
                }
                var tries = 0;
                var maxTries = 30; // ~90s
                var iv = setInterval(function() {
                    tries += 1;
                    fetch(C.agendaCheckoutMbwayFinalizeUrl || '', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ payment_intent_id: paymentIntentId, event_id: eventId, gorjeta: gorjeta, items: items })
                    })
                    .then(function(r) { return r.json().then(function(res2) { return { ok: r.ok, status: r.status, res: res2 || {} }; }); })
                    .then(function(pack) {
                        if (pack.ok && pack.res && pack.res.success) {
                            clearInterval(iv);
                            btn.disabled = false;
                            btn.innerHTML = 'Confirmar e faturar';
                            bootstrap.Modal.getInstance($id('paymentModal')).hide();
                            showToast('Pagamento MB WAY confirmado e venda registada.', 'success');
                            if (typeof calendar !== 'undefined') {
                                calendar.refetchEvents();
                            }
                            eventDetailModalLoading = true;
                            fetch((C.urlEvents || '') + '/' + eventId, { headers: { 'Accept': 'application/json' } })
                            .then(function(r2) { return r2.json(); })
                            .then(function(data) {
                                populateEventDetailEditModal(data);
                                setEventDetailPaymentAndReadOnly(data.existing_sale || null, 'marcacao', eventDetailSelectedServices.length);
                            })
                            .finally(function() { eventDetailModalLoading = false; });
                            return;
                        }
                        if (pack.status === 202) {
                            if (tries >= maxTries) {
                                clearInterval(iv);
                                btn.disabled = false;
                                btn.innerHTML = 'Confirmar e faturar';
                                showToast('Pedido MB WAY enviado. Ainda pendente; pode confirmar mais tarde.', 'warning');
                            }
                            return;
                        }
                        clearInterval(iv);
                        btn.disabled = false;
                        btn.innerHTML = 'Confirmar e faturar';
                        showToast((pack.res && (pack.res.error || pack.res.message)) || 'Falha ao confirmar pagamento MB WAY.', 'error');
                    })
                    .catch(function() {
                        clearInterval(iv);
                        btn.disabled = false;
                        btn.innerHTML = 'Confirmar e faturar';
                        showToast('Erro de ligação ao validar MB WAY.', 'error');
                    });
                }, 3000);
            })
            .catch(function() {
                btn.disabled = false;
                btn.innerHTML = 'Confirmar e faturar';
                showToast('Erro de ligação ao gerar pedido MB WAY.', 'error');
            });
            return;
        }

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

    function executeSaleRevert(saleId, reason) {
        var revertUrl = (C.salesRevertUrl || '').replace(/\/$/, '') + '/' + saleId + '/revert';
        var confirmBtn = $id('revertSaleConfirmBtn');
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.dataset.originalText = confirmBtn.dataset.originalText || confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>A anular...';
        }
        fetch(revertUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ reason: reason })
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (confirmBtn) {
                confirmBtn.disabled = false;
                if (confirmBtn.dataset.originalText) confirmBtn.innerHTML = confirmBtn.dataset.originalText;
            }
            if (!res.success) {
                showToast(res.error || res.message || 'Erro ao reverter.', 'error');
                return;
            }
            bootstrap.Modal.getInstance($id('revertSaleModal'))?.hide();
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
            if (confirmBtn) {
                confirmBtn.disabled = false;
                if (confirmBtn.dataset.originalText) confirmBtn.innerHTML = confirmBtn.dataset.originalText;
            }
            showToast('Erro de ligação.', 'error');
        });
    }

    $id('eventDetailReverterFaturaBtn').addEventListener('click', function() {
        var saleId = this.dataset.saleId;
        if (!saleId) return;
        $id('revertSaleId').value = saleId;
        $id('revertSaleReason').value = '';
        bootstrap.Modal.getOrCreateInstance($id('revertSaleModal')).show();
    });

    $id('revertSaleConfirmBtn').addEventListener('click', function() {
        var saleId = String($id('revertSaleId').value || '').trim();
        if (!saleId) return;
        var reason = String($id('revertSaleReason').value || '').trim();
        if (!reason) {
            showToast('Indique a razão da anulação.', 'error');
            $id('revertSaleReason').focus();
            return;
        }
        executeSaleRevert(saleId, reason);
    });

    $id('revertSaleModal').addEventListener('hidden.bs.modal', function() {
        $id('revertSaleId').value = '';
        $id('revertSaleReason').value = '';
        var confirmBtn = $id('revertSaleConfirmBtn');
        if (confirmBtn) {
            confirmBtn.disabled = false;
            if (confirmBtn.dataset.originalText) confirmBtn.innerHTML = confirmBtn.dataset.originalText;
        }
    });

    function ensureAgendaSlot24hToggle() {
        if (isAgendaMobileViewport()) return;
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

    let stackedClassRefreshTimer = null;
    function scheduleStackedEventClassRefresh() {
        if (stackedClassRefreshTimer) clearTimeout(stackedClassRefreshTimer);
        stackedClassRefreshTimer = setTimeout(function() {
            stackedClassRefreshTimer = null;
            applyStackedEventClasses();
        }, 0);
    }

    function applyStackedEventClasses() {
        if (!calendarEl) return;
        var eventEls = calendarEl.querySelectorAll('.fc-event[data-event-id]');
        eventEls.forEach(function(el) { el.classList.remove('is-stacked'); });

        var events = (calendar && typeof calendar.getEvents === 'function') ? calendar.getEvents() : [];
        if (!events || events.length < 2) return;

        var groups = new Map();
        var stackedIds = new Set();
        for (var i = 0; i < events.length; i++) {
            var ev = events[i];
            if (!ev || ev.allDay || !ev.start) continue;
            var start = ev.start;
            var end = ev.end ? ev.end : new Date(start.getTime() + 15 * 60 * 1000);
            if (!(end > start)) end = new Date(start.getTime() + 15 * 60 * 1000);

            var resourceId = '';
            if (typeof ev.getResources === 'function') {
                var resources = ev.getResources();
                resourceId = resources && resources[0] ? String(resources[0].id || '') : '';
            }
            if (!resourceId && ev.extendedProps && ev.extendedProps.user_id != null) {
                resourceId = String(ev.extendedProps.user_id);
            }
            var dayKey = String(start.getFullYear()) + '-' + String(start.getMonth() + 1).padStart(2, '0') + '-' + String(start.getDate()).padStart(2, '0');
            var groupKey = resourceId + '|' + dayKey;
            if (!groups.has(groupKey)) groups.set(groupKey, []);
            groups.get(groupKey).push({
                id: String(ev.id),
                startMs: start.getTime(),
                endMs: end.getTime(),
            });
        }

        groups.forEach(function(items) {
            for (var a = 0; a < items.length; a++) {
                for (var b = a + 1; b < items.length; b++) {
                    var x = items[a];
                    var y = items[b];
                    if (x.startMs < y.endMs && y.startMs < x.endMs) {
                        stackedIds.add(x.id);
                        stackedIds.add(y.id);
                    }
                }
            }
        });

        if (stackedIds.size === 0) return;
        eventEls.forEach(function(el) {
            var id = el.getAttribute('data-event-id');
            if (id && stackedIds.has(String(id))) el.classList.add('is-stacked');
        });
    }

    function applyAgendaEventFromServer(ev, serverEvent) {
        if (!ev || !serverEvent) return;
        ev.setProp('title', serverEvent.title);
        ev.setStart(serverEvent.start);
        ev.setEnd(serverEvent.end);
        if (serverEvent.backgroundColor !== undefined) ev.setProp('backgroundColor', serverEvent.backgroundColor);
        var ep = serverEvent.extendedProps || {};
        Object.keys(ep).forEach(function(k) { ev.setExtendedProp(k, ep[k]); });
        var newColor = serverEvent.backgroundColor;
        var el = document.querySelector('[data-event-id="' + String(ev.id) + '"]');
        if (el) {
            if (newColor) el.style.setProperty('background-color', newColor, 'important');
            else el.style.removeProperty('background-color');
        }
        scheduleStackedEventClassRefresh();
    }

    function openAgendaDragConfirmModal(extendedProps) {
        hideEventQuickview();
        var chk = $id('agendaDragConfirmNotify');
        var nameEl = $id('agendaDragConfirmClientName');
        var noEmailEl = $id('agendaDragConfirmNoEmail');
        var submitBtn = $id('agendaDragConfirmSubmit');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Atualizar';
        }
        var clientName = (extendedProps && extendedProps.client_name) ? String(extendedProps.client_name) : 'o cliente';
        if (nameEl) nameEl.textContent = clientName;
        var hasEmail = extendedProps && extendedProps.client_has_email === true;
        if (chk) {
            chk.checked = false;
            chk.disabled = !hasEmail;
        }
        if (noEmailEl) noEmailEl.classList.toggle('d-none', hasEmail);
        bootstrap.Modal.getOrCreateInstance($id('agendaDragConfirmModal')).show();
    }

    function refreshAgendaData() {
        if (!calendar) return;
        if (isResourceTimeGridDayView(calendar.view.type)) {
            calendar.refetchResources();
        }
        calendar.refetchEvents();
    }

    /** Calendário popup (Flatpickr) ao clicar no título da data — navegar para o dia escolhido (mobile e desktop). */
    function ensureAgendaToolbarDateFlatpickr() {
        if (window.agendaToolbarDateFlatpickr) {
            return window.agendaToolbarDateFlatpickr;
        }
        var input = $id('agendaToolbarDatePicker');
        if (!input || typeof flatpickr === 'undefined') {
            return null;
        }
        var fp = flatpickr(input, {
            locale: 'pt',
            dateFormat: 'Y-m-d',
            allowInput: false,
            clickOpens: false,
            appendTo: document.body,
            disableMobile: true,
            onChange: function(selectedDates) {
                if (selectedDates && selectedDates[0]) {
                    calendar.gotoDate(selectedDates[0]);
                }
            }
        });
        window.agendaToolbarDateFlatpickr = fp;
        return fp;
    }

    function openAgendaToolbarDatePicker() {
        if (!calendar) {
            return;
        }
        var fp = ensureAgendaToolbarDateFlatpickr();
        if (!fp) {
            return;
        }
        var btn = calendarEl.querySelector('.fc-currentDate-button');
        if (btn) {
            fp._positionElement = btn;
        }
        fp.setDate(calendar.getDate(), false);
        fp.open();
    }

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'resourceTimeGridDay',
        locale: 'pt',
        editable: true,
        customButtons: {
            currentDate: {
                text: '',
                click: function() {
                    openAgendaToolbarDatePicker();
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
                    refreshAgendaData();
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
            if (arg.el && arg.date) {
                arg.el.setAttribute('data-slot-date', arg.date.toISOString());
                var uid = resolveSlotUserId(arg);
                var memberUnavailable = !!(uid && isOutsideMemberWindowAtInstant(arg.date, uid));
                arg.el.classList.toggle('agenda-slot-member-unavailable', memberUnavailable);
                scheduleApplyMemberUnavailableClasses();
            }
        },
        dayCellClassNames: function(arg) {
            return isNationalHolidayPtAtDate(arg.date) ? ['agenda-day-holiday'] : [];
        },
        slotLaneClassNames: function(arg) {
            var out = [];
            if (isOutsideStoreHoursAtDate(arg.date)) out.push('agenda-slot-outside-hours');
            if (isNationalHolidayPtAtDate(arg.date)) out.push('agenda-slot-holiday');
            var uid = resolveSlotUserId(arg);
            if (uid && isOutsideMemberWindowAtInstant(arg.date, uid)) {
                out.push('agenda-slot-member-unavailable');
            }
            return out;
        },
        dayMaxEvents: 2,
        dayMaxEventRows: 2,
        eventContent: function(arg) {
            if (arg.event.display === 'background') {
                return { html: '' };
            }
            const extProps = arg.event.extendedProps || {};
            const isTempoPessoal = (extProps.event_type || '') === 'tempo_pessoal';
            const statusIcon = isTempoPessoal
                ? (extProps.personal_time_type?.icon ? 'ph ' + extProps.personal_time_type.icon : null)
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

            // Ícone de estado: bloqueia apenas quando faturação está totalmente liquidada.
            var invoiceSettled = !!(extProps.invoice_settled);
            var hasInvoice = !!(extProps.has_invoice);
            var effectiveStatusIcon = statusIcon;
            if (!isTempoPessoal && invoiceSettled) {
                effectiveStatusIcon = 'ph ph-check-circle';
            }
            var iconHtml = '';
            if (effectiveStatusIcon) {
                if (isTempoPessoal) {
                    iconHtml = '<i class="' + effectiveStatusIcon + ' fc-event-status-icon"></i>';
                } else if (invoiceSettled) {
                    iconHtml = '<span class="fc-event-status-icon-completo agenda-event-status-icon-btn"><i class="' + effectiveStatusIcon + ' fc-event-status-icon"></i></span>';
                } else {
                    iconHtml = '<span class="agenda-event-status-icon-btn" role="button" tabindex="-1" title="Alterar estado"><i class="' + effectiveStatusIcon + ' fc-event-status-icon"></i></span>';
                }
            }

            // Linha 1: ícone + cliente (ou título)
            const line1 = iconHtml + '<strong class="fc-event-client">' + (clientName || fallbackTitle || '…') + '</strong>';

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

            var badgeHtml = '';
            if (!isTempoPessoal && invoiceSettled) {
                badgeHtml = '<span class="agenda-fc-event-badge agenda-fc-event-badge-paid">Pago</span>';
            }

            return { html: '<div class="fc-event-content-wrapper">' + badgeHtml + contentHtml + '</div>' };
        },
        eventAllow: function(dropInfo, draggedEvent) {
            var ext = (draggedEvent && draggedEvent.extendedProps) ? draggedEvent.extendedProps : {};
            var status = String(ext.status || '').toLowerCase();
            var invoiceSettled = !!ext.invoice_settled;
            var isTimeEditable = !!ext.is_time_editable;

            // Regra de negócio: marcações concluídas ou totalmente liquidadas nunca podem ser movidas/redimensionadas.
            if (status === 'completo' || invoiceSettled) {
                return false;
            }

            // Se o backend marcou como não editável por estado/origem, bloqueia qualquer drag.
            return isTimeEditable;
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
            var slotTd = null;
            var slotElFromTarget = target && target.closest ? target.closest('[data-slot-date]') : null;
            if (!slotElFromTarget) {
                // Clique em camadas intermédias (ex.: background de indisponível):
                // localizar o slot real por coordenadas para evitar highlights gigantes.
                var px = info.jsEvent.clientX;
                var py = info.jsEvent.clientY;
                if (px != null && py != null) {
                    var candidateSlots = calendarEl.querySelectorAll('[data-slot-date]');
                    for (var si = 0; si < candidateSlots.length; si++) {
                        var rs = candidateSlots[si].getBoundingClientRect();
                        if (px >= rs.left && px <= rs.right && py >= rs.top && py <= rs.bottom) {
                            slotElFromTarget = candidateSlots[si];
                            break;
                        }
                    }
                }
            }
            if (slotElFromTarget) {
                slotTd = slotElFromTarget.closest('td');
            }
            if (!slotTd && target && target.closest) {
                slotTd = target.closest('td');
            }
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
                    wrapper.style.zIndex = '4';
                    wrapper.style.pointerEvents = 'none';
                    var span = document.createElement('span');
                    span.className = 'agenda-cell-time-overlay';
                    span.textContent = timeLabel;
                    wrapper.appendChild(span);
                    if (calendarEl) calendarEl.appendChild(wrapper);
                    else document.body.appendChild(wrapper);
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
            var ev = info.jsEvent;
            var cx = ev.clientX;
            var cy = ev.clientY;
            if ((cx == null || cy == null) || (cx === 0 && cy === 0)) {
                var touch = (ev.changedTouches && ev.changedTouches[0]) || (ev.touches && ev.touches[0]);
                if (touch) {
                    cx = touch.clientX;
                    cy = touch.clientY;
                }
            }
            if (cx == null || cy == null || (cx === 0 && cy === 0)) {
                var rect = calendarEl.getBoundingClientRect();
                cx = rect.left + rect.width * 0.5;
                cy = rect.top + rect.height * 0.45;
            }
            showQuickMenu(cx, cy, headingLabel, options);
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
            const title = agendaTechnicianFirstName(res.title || '').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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
                var bgEvents = generateMemberUnavailableBackgroundEvents(info, vtEvents);
                if (bgEvents.length) {
                    events = (Array.isArray(events) ? events : []).concat(bgEvents);
                }
                successCallback(events);
                scheduleStackedEventClassRefresh();
            })
            .catch(failureCallback);
        },
        eventsSet: function() {
            scheduleStackedEventClassRefresh();
        },
        eventAdd: function() {
            scheduleStackedEventClassRefresh();
        },
        eventChange: function() {
            scheduleStackedEventClassRefresh();
        },
        eventRemove: function() {
            scheduleStackedEventClassRefresh();
        },
        eventDidMount: function(info) {
            if (info.event.display === 'background') return;
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
            scheduleStackedEventClassRefresh();
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
                    bootstrap.Offcanvas.getOrCreateInstance($id('eventDetailEditModal')).show();
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
            if (info.event.extendedProps.invoice_settled) { info.revert(); return; }
            if ((info.event.extendedProps.status || '') === 'completo') { info.revert(); return; }
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
            const ext = info.event.extendedProps || {};
            if (ext.event_type === 'marcacao' && ext.client_id) {
                agendaDragPending = {
                    eventId: id,
                    payload: payload,
                    info: info,
                    kind: 'drop',
                    needsCalendarRevert: true,
                    extendedProps: ext
                };
                openAgendaDragConfirmModal(ext);
                return;
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
                scheduleStackedEventClassRefresh();
            })
            .catch(function(err) {
                console.error('eventDrop error', err);
                info.revert();
                scheduleStackedEventClassRefresh();
            });
        },
        eventResize: function(info) {
            if (info.event.extendedProps.invoice_settled) { info.revert(); return; }
            if ((info.event.extendedProps.status || '') === 'completo') { info.revert(); return; }
            if (info.event.extendedProps.is_time_editable === false) { info.revert(); return; }
            const id = info.event.id;
            const start = info.event.start.toISOString();
            const end = info.event.end ? info.event.end.toISOString() : start;
            const ext = info.event.extendedProps || {};
            if (ext.event_type === 'marcacao' && ext.client_id) {
                agendaDragPending = {
                    eventId: id,
                    payload: { start_at: start, end_at: end },
                    info: info,
                    kind: 'resize',
                    needsCalendarRevert: true,
                    extendedProps: ext
                };
                openAgendaDragConfirmModal(ext);
                return;
            }
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
                    scheduleStackedEventClassRefresh();
                }
                scheduleStackedEventClassRefresh();
            })
            .catch(function(err) {
                console.error('eventResize error', err);
                info.revert();
                scheduleStackedEventClassRefresh();
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
                    currentDateBtn.style.pointerEvents = 'auto';
                    currentDateBtn.style.cursor = 'pointer';
                    currentDateBtn.style.fontWeight = '500';
                    currentDateBtn.style.color = '#212529';
                    currentDateBtn.style.opacity = '1';
                    currentDateBtn.setAttribute('title', 'Escolher data');
                    currentDateBtn.setAttribute('aria-label', 'Escolher data');
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
                applyHolidayClassesToTimeGridColumns();
                scheduleApplyMemberUnavailableClasses();
                syncAgendaMobileControls();
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
                applyHolidayClassesToTimeGridColumns();
                syncAgendaMobileControls();
            });
            setTimeout(function() {
                initViewSelectorDropdown();
                updateViewSelectorButton(info.view.type);
                updateViewDropdownActive(info.view.type);
                initAdicionarDropdown();
                applyToolbarStyles();
                ensureAgendaSlot24hToggle();
                applyHolidayClassesToTimeGridColumns();
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
                    applyHolidayClassesToTimeGridColumns();
                }, isConsultant ? 150 : 0);
            }
        }
    });
    calendar.render();

    /** Navegação por swipe (mobile): substitui as setas na toolbar + slide (arraste em tempo real + commit ao largar). */
    (function setupAgendaSwipeNavigation() {
        var startX = 0;
        var startY = 0;
        var touchId = null;
        var lastNavAt = 0;
        var minDx = 56;
        var minRatio = 1.2;
        var cooldownMs = 700;
        var agendaSlideLocked = false;
        var SLIDE_MS = 250;
        var SLIDE_EASING = 'cubic-bezier(0.22, 1, 0.36, 1)';
        var SLIDE_BG = 'var(--bs-body-bg, #fff)';

        var swipeActive = false;
        var currentDragPx = 0;
        var lastVelX = 0;
        var lastMoveX = 0;
        var lastMoveT = 0;
        var gestureAxisLock = null; // 'x' | 'y' | null
        var agendaSwipePreviewLayer = null;
        var agendaSwipePreviewHost = null;
        var agendaSwipePreviewCalendar = null;
        var agendaSwipePreviewDirection = null;
        var agendaSwipePreviewLockedDirection = null;
        var agendaSwipePreviewParent = null;
        var agendaSwipePreviewParentPrevOverflow = '';

        function prefersReducedMotion() {
            try {
                return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            } catch (e) {
                return false;
            }
        }

        function getFcRootEl() {
            if (calendarEl.classList && calendarEl.classList.contains('fc')) {
                return calendarEl;
            }
            return calendarEl.querySelector(':scope > .fc') || calendarEl;
        }

        function getScrollerHarnessInCell(td) {
            if (!td) {
                return null;
            }
            var sc = td.querySelector('.fc-scroller');
            if (sc) {
                var h0 = sc.querySelector('.fc-scroller-harness');
                if (h0) {
                    return h0;
                }
            }
            return td.querySelector('.fc-scroller-harness');
        }

        /** Slide global: move o calendário completo (toolbar + cabeçalhos + eixo + grelha). */
        function getSlideTransformEls() {
            var fcRoot = getFcRootEl();
            return fcRoot ? [fcRoot] : [];
        }

        function clearSlideElStyles() {
            var els = getSlideTransformEls();
            for (var i = 0; i < els.length; i++) {
                els[i].style.transition = '';
                els[i].style.transform = '';
                els[i].style.willChange = '';
                els[i].style.background = '';
            }
        }

        function getSlideWidth() {
            // Usar a largura do próprio painel que está a ser transformado.
            // O preview deve ter exatamente o mesmo retângulo para não sobrepor.
            var els = getSlideTransformEls();
            if (els.length) {
                var rw = Math.round(els[0].getBoundingClientRect().width);
                if (rw > 0) return rw;
            }
            if (agendaSwipePreviewLayer) {
                var pw = Math.round(agendaSwipePreviewLayer.getBoundingClientRect().width);
                if (pw > 0) return pw;
            }
            var vh = calendarEl.querySelector('.fc-view-harness');
            if (vh) {
                var vw = Math.round(vh.getBoundingClientRect().width);
                if (vw > 0) return vw;
            }
            return calendarEl ? Math.round(calendarEl.getBoundingClientRect().width) : 0;
        }

        function getAdjacentDateForDirection(direction) {
            try {
                var view = calendar.view;
                if (!view || !view.activeStart || !view.activeEnd) {
                    return null;
                }
                var spanMs = view.activeEnd.getTime() - view.activeStart.getTime();
                return new Date(view.activeStart.getTime() + (direction === 'next' ? spanMs : -spanMs));
            } catch (e) {
                return null;
            }
        }

        function destroyAgendaSwipePreview() {
            agendaSwipePreviewDirection = null;
            agendaSwipePreviewLockedDirection = null;
            if (agendaSwipePreviewCalendar) {
                try {
                    agendaSwipePreviewCalendar.destroy();
                } catch (e) {}
                agendaSwipePreviewCalendar = null;
            }
            if (agendaSwipePreviewLayer && agendaSwipePreviewLayer.parentNode) {
                agendaSwipePreviewLayer.parentNode.removeChild(agendaSwipePreviewLayer);
            }
            if (agendaSwipePreviewParent) {
                agendaSwipePreviewParent.style.overflow = agendaSwipePreviewParentPrevOverflow || '';
            }
            agendaSwipePreviewLayer = null;
            agendaSwipePreviewHost = null;
            agendaSwipePreviewParent = null;
            agendaSwipePreviewParentPrevOverflow = '';
            calendarEl.style.zIndex = '';
        }

        function syncAgendaSwipePreviewBounds() {
            if (!agendaSwipePreviewLayer || !calendarEl) return;
            var parent = agendaSwipePreviewLayer.parentElement;
            if (!parent) return;
            var pr = parent.getBoundingClientRect();
            var els = getSlideTransformEls();
            var rr = els.length ? els[0].getBoundingClientRect() : calendarEl.getBoundingClientRect();
            agendaSwipePreviewLayer.style.top = Math.round(rr.top - pr.top) + 'px';
            agendaSwipePreviewLayer.style.left = Math.round(rr.left - pr.left) + 'px';
            agendaSwipePreviewLayer.style.width = Math.round(rr.width) + 'px';
            agendaSwipePreviewLayer.style.height = Math.round(rr.height) + 'px';
        }

        function updateAgendaSwipePreviewTransform(dx, w, direction) {
            if (!agendaSwipePreviewLayer) return;
            var parent = agendaSwipePreviewLayer.parentElement;
            var slideEls = getSlideTransformEls();
            if (!parent || !slideEls.length) {
                return;
            }
            // Sincroniza com a geometria real do painel atual para ficarem sempre colados.
            var pr = parent.getBoundingClientRect();
            var cr = slideEls[0].getBoundingClientRect();
            var previewRect = agendaSwipePreviewLayer.getBoundingClientRect();
            var x;
            if (direction === 'next') {
                x = (cr.left - pr.left) + cr.width;
            } else {
                x = (cr.left - pr.left) - previewRect.width;
            }
            agendaSwipePreviewLayer.style.willChange = 'left, transform';
            agendaSwipePreviewLayer.style.transition = 'none';
            agendaSwipePreviewLayer.style.left = Math.round(x) + 'px';
            agendaSwipePreviewLayer.style.transform = 'translateX(0px)';
        }

        function buildAgendaSwipePreviewOptions(adjDate) {
            var get = function(k, fallback) {
                try {
                    var v = calendar.getOption(k);
                    return v !== undefined && v !== null ? v : fallback;
                } catch (e) {
                    return fallback;
                }
            };
            return {
                initialView: calendar.view.type,
                initialDate: adjDate,
                locale: get('locale', 'pt'),
                headerToolbar: get('headerToolbar', false),
                customButtons: get('customButtons'),
                buttonText: get('buttonText'),
                views: get('views'),
                slotMinTime: get('slotMinTime'),
                slotMaxTime: get('slotMaxTime'),
                slotDuration: get('slotDuration'),
                slotLabelInterval: get('slotLabelInterval'),
                slotLabelFormat: get('slotLabelFormat'),
                allDaySlot: get('allDaySlot'),
                nowIndicator: false,
                scrollTime: get('scrollTime'),
                scrollTimeReset: false,
                displayEventTime: get('displayEventTime'),
                dayHeaderFormat: get('dayHeaderFormat'),
                dayCellClassNames: get('dayCellClassNames'),
                slotLaneClassNames: get('slotLaneClassNames'),
                slotLaneDidMount: get('slotLaneDidMount'),
                dayMaxEvents: get('dayMaxEvents'),
                dayMaxEventRows: get('dayMaxEventRows'),
                resources: get('resources'),
                events: get('events'),
                eventContent: get('eventContent'),
                resourceLabelContent: get('resourceLabelContent'),
                editable: false,
                selectable: false,
                eventStartEditable: false,
                eventDurationEditable: false,
                eventResourceEditable: false
                ,
                datesSet: function(info) {
                    try {
                        var btn = agendaSwipePreviewHost && agendaSwipePreviewHost.querySelector('.fc-currentDate-button');
                        if (!btn) return;
                        var vt = info && info.view && info.view.type ? info.view.type : calendar.view.type;
                        var startDate = vt === 'dayGridMonth' ? info.view.currentStart : info.start;
                        btn.textContent = formatCurrentDateButton(vt, startDate, info.end);
                    } catch (e) {}
                }
            };
        }

        function ensureAgendaSwipePreview(direction) {
            if (!isAgendaMobileViewport()) return;
            if (agendaSwipePreviewCalendar && agendaSwipePreviewDirection === direction) return;
            var adjDate = getAdjacentDateForDirection(direction);
            if (!adjDate || isNaN(adjDate.getTime())) return;

            destroyAgendaSwipePreview();
            agendaSwipePreviewDirection = direction;

            var parent = calendarEl.parentElement || calendarEl;
            agendaSwipePreviewParent = parent;
            agendaSwipePreviewParentPrevOverflow = parent.style.overflow || '';
            parent.style.overflow = 'hidden';
            if (window.getComputedStyle(parent).position === 'static') {
                parent.style.position = 'relative';
            }
            calendarEl.style.position = 'relative';
            calendarEl.style.zIndex = '2';

            agendaSwipePreviewLayer = document.createElement('div');
            agendaSwipePreviewLayer.className = 'agenda-swipe-preview-layer';
            agendaSwipePreviewLayer.setAttribute('aria-hidden', 'true');
            agendaSwipePreviewHost = document.createElement('div');
            agendaSwipePreviewHost.className = 'agenda-swipe-preview-host';
            agendaSwipePreviewLayer.appendChild(agendaSwipePreviewHost);
            parent.appendChild(agendaSwipePreviewLayer);
            syncAgendaSwipePreviewBounds();

            try {
                agendaSwipePreviewCalendar = new FullCalendar.Calendar(agendaSwipePreviewHost, buildAgendaSwipePreviewOptions(adjDate));
                agendaSwipePreviewCalendar.render();
            } catch (e) {
                destroyAgendaSwipePreview();
            }
        }

        function clampDragPx(dx, w) {
            if (!w) return dx;
            var max = w;
            var min = -w;
            if (dx > max) {
                return max + (dx - max) * 0.22;
            }
            if (dx < min) {
                return min + (dx - min) * 0.22;
            }
            return dx;
        }

        function agendaNavigateSlideCleanup() {
            destroyAgendaSwipePreview();
            clearSlideElStyles();
            calendarEl.classList.remove('agenda-slide-running');
            calendarEl.style.overflow = '';
            agendaSlideLocked = false;
            lastNavAt = Date.now();
        }

        function agendaSwipeDragReset() {
            swipeActive = false;
            currentDragPx = 0;
            lastVelX = 0;
            destroyAgendaSwipePreview();
            calendarEl.classList.remove('agenda-swipe-dragging');
            clearSlideElStyles();
            calendarEl.style.overflow = '';
        }

        function agendaSwipeSnapBack(fromPx) {
            var slideEls = getSlideTransformEls();
            if (!slideEls.length) {
                agendaSwipeDragReset();
                return;
            }
            calendarEl.classList.remove('agenda-swipe-dragging');
            var w = getSlideWidth();
            if (agendaSwipePreviewLayer && w) {
                var off = agendaSwipePreviewDirection === 'next' ? w : -w;
                agendaSwipePreviewLayer.style.willChange = 'transform';
                agendaSwipePreviewLayer.style.transition = 'transform 220ms ' + SLIDE_EASING;
                agendaSwipePreviewLayer.style.transform = 'translateX(' + off + 'px)';
            }
            var si;
            for (si = 0; si < slideEls.length; si++) {
                slideEls[si].style.willChange = 'transform';
                slideEls[si].style.transition = 'transform 220ms ' + SLIDE_EASING;
                slideEls[si].style.transform = 'translateX(0px)';
                slideEls[si].style.background = SLIDE_BG;
            }
            setTimeout(function() {
                agendaSwipeDragReset();
            }, 250);
        }

        /**
         * Completa o slide a partir da posição atual do arraste (px), depois troca o dia e anima a entrada.
         */
        function agendaNavigateWithSlideFromDrag(direction, startPxPx) {
            var isNext = direction === 'next';
            if (!isAgendaMobileViewport() || prefersReducedMotion()) {
                agendaSwipeDragReset();
                if (isNext) {
                    calendar.next();
                } else {
                    calendar.prev();
                }
                lastNavAt = Date.now();
                return;
            }
            if (agendaSlideLocked) {
                return;
            }
            var slideEls = getSlideTransformEls();
            var w = getSlideWidth();
            if (!slideEls.length || !w) {
                agendaSwipeDragReset();
                if (isNext) {
                    calendar.next();
                } else {
                    calendar.prev();
                }
                lastNavAt = Date.now();
                return;
            }

            var targetPx = isNext ? -w : w;
            var remaining = Math.abs(targetPx - startPxPx);
            var exitMs = Math.min(280, Math.max(120, remaining * 0.78));
            ensureAgendaSwipePreview(direction);
            syncAgendaSwipePreviewBounds();
            updateAgendaSwipePreviewTransform(startPxPx, w, direction);
            var hasPreview = !!agendaSwipePreviewLayer;

            agendaSlideLocked = true;
            calendarEl.classList.remove('agenda-swipe-dragging');
            calendarEl.classList.add('agenda-slide-running');
            calendarEl.style.overflow = 'hidden';

            var ei;
            for (ei = 0; ei < slideEls.length; ei++) {
                slideEls[ei].style.willChange = 'transform';
                slideEls[ei].style.transition = 'transform ' + exitMs + 'ms ' + SLIDE_EASING;
                slideEls[ei].style.transform = 'translateX(' + targetPx + 'px)';
                slideEls[ei].style.background = SLIDE_BG;
            }
            if (hasPreview) {
                agendaSwipePreviewLayer.style.willChange = 'transform';
                agendaSwipePreviewLayer.style.transition = 'transform ' + exitMs + 'ms ' + SLIDE_EASING;
                agendaSwipePreviewLayer.style.transform = 'translateX(0px)';
            }

            setTimeout(function() {
                if (isNext) {
                    calendar.next();
                } else {
                    calendar.prev();
                }
                if (hasPreview) {
                    agendaNavigateSlideCleanup();
                    return;
                }

                slideEls = getSlideTransformEls();
                if (!slideEls.length) {
                    agendaNavigateSlideCleanup();
                    return;
                }
                var inFrom = isNext ? w : -w;
                var j;
                for (j = 0; j < slideEls.length; j++) {
                    slideEls[j].style.transition = 'none';
                    slideEls[j].style.transform = 'translateX(' + inFrom + 'px)';
                }
                if (slideEls[0]) {
                    slideEls[0].offsetHeight;
                }
                for (j = 0; j < slideEls.length; j++) {
                    slideEls[j].style.transition = 'transform ' + SLIDE_MS + 'ms ' + SLIDE_EASING;
                }
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        var k;
                        for (k = 0; k < slideEls.length; k++) {
                            slideEls[k].style.transform = 'translateX(0px)';
                        }
                    });
                });

                setTimeout(function() {
                    agendaNavigateSlideCleanup();
                }, SLIDE_MS + 40);
            }, exitMs);
        }

        function agendaNavigateWithSlide(direction) {
            agendaNavigateWithSlideFromDrag(direction, 0);
        }

        function swipeTargetOk(el) {
            if (!el || !calendarEl.contains(el)) return false;
            if (el.closest('.fc-event')) return false;
            if (el.closest('a, button, input, select, textarea, label')) return false;
            if (el.closest('.modal')) return false;
            return true;
        }

        calendarEl.addEventListener('touchstart', function(e) {
            if (!isAgendaMobileViewport()) return;
            if (agendaSlideLocked) return;
            if (e.touches.length !== 1) return;
            var t = e.touches[0];
            if (!swipeTargetOk(t.target)) return;
            touchId = t.identifier;
            startX = t.clientX;
            startY = t.clientY;
            swipeActive = false;
            gestureAxisLock = null;
            destroyAgendaSwipePreview();
            currentDragPx = 0;
            lastVelX = 0;
            lastMoveX = t.clientX;
            lastMoveT = Date.now();
        }, { passive: true });

        calendarEl.addEventListener('touchmove', function(e) {
            if (!isAgendaMobileViewport()) return;
            if (agendaSlideLocked) return;
            if (touchId === null) return;
            if (prefersReducedMotion()) return;
            var t = null;
            for (var i = 0; i < e.touches.length; i++) {
                if (e.touches[i].identifier === touchId) {
                    t = e.touches[i];
                    break;
                }
            }
            if (!t) return;

            var dx = t.clientX - startX;
            var dy = t.clientY - startY;

            if (!swipeActive && !gestureAxisLock) {
                if (Math.abs(dx) > 12 && Math.abs(dx) > Math.abs(dy) * 1.05) {
                    gestureAxisLock = 'x';
                    swipeActive = true;
                    calendarEl.classList.add('agenda-swipe-dragging');
                    calendarEl.style.overflow = 'hidden';
                } else if (Math.abs(dy) > 12 && Math.abs(dy) > Math.abs(dx) * 1.05) {
                    gestureAxisLock = 'y';
                    return;
                } else {
                    return;
                }
            }
            if (gestureAxisLock === 'y') {
                return; // scroll vertical livre; swipe horizontal bloqueado neste toque
            }

            var w = getSlideWidth();
            currentDragPx = clampDragPx(dx, w);
            if (!agendaSwipePreviewLockedDirection && Math.abs(currentDragPx) > 6) {
                agendaSwipePreviewLockedDirection = currentDragPx < 0 ? 'next' : 'prev';
            }
            var slideElsMove = getSlideTransformEls();
            var sem;
            for (sem = 0; sem < slideElsMove.length; sem++) {
                slideElsMove[sem].style.willChange = 'transform';
                slideElsMove[sem].style.transition = 'none';
                slideElsMove[sem].style.transform = 'translateX(' + currentDragPx + 'px)';
                slideElsMove[sem].style.background = SLIDE_BG;
            }
            var dragDir = agendaSwipePreviewLockedDirection || (currentDragPx < 0 ? 'next' : 'prev');
            ensureAgendaSwipePreview(dragDir);
            syncAgendaSwipePreviewBounds();
            updateAgendaSwipePreviewTransform(currentDragPx, w, dragDir);
            var now = Date.now();
            lastVelX = (t.clientX - lastMoveX) / Math.max(1, now - lastMoveT);
            lastMoveX = t.clientX;
            lastMoveT = now;

            e.preventDefault();
        }, { passive: false });

        calendarEl.addEventListener('touchend', function(e) {
            if (!isAgendaMobileViewport()) return;
            if (agendaSlideLocked) return;
            if (touchId === null) return;
            var touch = null;
            for (var j = 0; j < e.changedTouches.length; j++) {
                if (e.changedTouches[j].identifier === touchId) {
                    touch = e.changedTouches[j];
                    break;
                }
            }
            touchId = null;
            var wasVerticalLock = gestureAxisLock === 'y';
            gestureAxisLock = null;

            if (swipeActive) {
                if (!touch || !swipeTargetOk(e.target)) {
                    agendaSwipeSnapBack(currentDragPx);
                    swipeActive = false;
                    return;
                }
                var w = getSlideWidth();
                var dx = touch.clientX - startX;
                var commitDist = w * 0.26;
                var velOk = Math.abs(lastVelX) > 0.42 && Math.abs(dx) > 22;
                var velMatches = velOk && (dx < 0 ? lastVelX < 0 : lastVelX > 0);
                var distOk = Math.abs(dx) >= commitDist;
                var shouldCommit = (distOk || velMatches) && Date.now() - lastNavAt >= cooldownMs;

                if (shouldCommit) {
                    if (dx < 0) {
                        agendaNavigateWithSlideFromDrag('next', currentDragPx);
                    } else {
                        agendaNavigateWithSlideFromDrag('prev', currentDragPx);
                    }
                } else {
                    agendaSwipeSnapBack(currentDragPx);
                }
                swipeActive = false;
                return;
            }
            if (wasVerticalLock) {
                return;
            }

            if (!touch || !swipeTargetOk(e.target)) return;

            var dxEnd = touch.clientX - startX;
            var dyEnd = touch.clientY - startY;
            if (Math.abs(dxEnd) < minDx) return;
            if (Math.abs(dxEnd) < Math.abs(dyEnd) * minRatio) return;
            if (Date.now() - lastNavAt < cooldownMs) return;

            if (dxEnd < 0) {
                agendaNavigateWithSlide('next');
            } else {
                agendaNavigateWithSlide('prev');
            }
        }, { passive: true });

        calendarEl.addEventListener('touchcancel', function() {
            if (touchId === null) return;
            touchId = null;
            gestureAxisLock = null;
            if (swipeActive) {
                agendaSwipeSnapBack(currentDragPx);
                swipeActive = false;
            }
        }, { passive: true });
    })();

    let agendaMobileControlsBound = false;
    function ensureAgendaMobileControls() {
        if (document.getElementById('agendaMobileControls')) return;
        var controls = document.createElement('div');
        controls.id = 'agendaMobileControls';
        controls.className = 'agenda-mobile-controls';
        controls.innerHTML =
            '<button type="button" class="agenda-mobile-fab agenda-mobile-fab-ghost agenda-mobile-fab-cog" id="agendaMobileViewBtn" aria-label="Opções" title="Opções">' +
                '<i class="ph ph-gear-six"></i>' +
            '</button>' +
            '<button type="button" class="agenda-mobile-fab agenda-mobile-fab-secondary agenda-mobile-fab-refresh" id="agendaMobileRefreshBtn" aria-label="Atualizar agenda" title="Atualizar">' +
                '<i class="ph ph-arrow-clockwise"></i>' +
            '</button>' +
            '<button type="button" class="agenda-mobile-fab agenda-mobile-fab-primary agenda-mobile-fab-add" id="agendaMobileAddBtn" aria-label="Adicionar" title="Adicionar">' +
                '<i class="ph ph-plus"></i>' +
            '</button>';
        document.body.appendChild(controls);
    }

    function ensureAgendaMobileModals() {
        if (!document.getElementById('agendaMobileViewModal')) {
            var viewModal = document.createElement('div');
            viewModal.className = 'modal fade';
            viewModal.id = 'agendaMobileViewModal';
            viewModal.tabIndex = -1;
            viewModal.setAttribute('aria-labelledby', 'agendaMobileViewModalLabel');
            viewModal.setAttribute('aria-hidden', 'true');
            viewModal.innerHTML =
                '<div class="modal-dialog modal-dialog-centered modal-sm agenda-mobile-sheet-dialog">' +
                    '<div class="modal-content agenda-mobile-sheet">' +
                        '<div class="modal-header pb-2">' +
                            '<h5 class="modal-title" id="agendaMobileViewModalLabel">Opções</h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>' +
                        '</div>' +
                        '<div class="modal-body pt-2">' +
                            '<div class="agenda-mobile-option-group">' +
                                '<label class="form-label small text-muted mb-2">Vista</label>' +
                                '<div class="list-group list-group-flush" id="agendaMobileViewList"></div>' +
                            '</div>' +
                            '<div class="agenda-mobile-option-group">' +
                                '<label class="form-label small text-muted mb-2">Equipa</label>' +
                                '<select id="agendaMobileConsultantSelect" class="form-select form-select-sm"></select>' +
                            '</div>' +
                            '<div class="agenda-mobile-option-group">' +
                                '<div class="form-check form-switch mb-0">' +
                                    '<input class="form-check-input" type="checkbox" id="agendaMobileSlot24hToggle">' +
                                    '<label class="form-check-label" for="agendaMobileSlot24hToggle">Mostrar 24 horas</label>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(viewModal);
        }

        if (!document.getElementById('agendaMobileAddModal')) {
            var addModal = document.createElement('div');
            addModal.className = 'modal fade';
            addModal.id = 'agendaMobileAddModal';
            addModal.tabIndex = -1;
            addModal.setAttribute('aria-labelledby', 'agendaMobileAddModalLabel');
            addModal.setAttribute('aria-hidden', 'true');
            addModal.innerHTML =
                '<div class="modal-dialog modal-dialog-centered modal-sm agenda-mobile-sheet-dialog">' +
                    '<div class="modal-content agenda-mobile-sheet">' +
                        '<div class="modal-header pb-2">' +
                            '<h5 class="modal-title" id="agendaMobileAddModalLabel">Adicionar</h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>' +
                        '</div>' +
                        '<div class="modal-body pt-2">' +
                            '<div class="d-grid gap-2">' +
                                '<button type="button" class="btn btn-outline-primary btn-sm" id="agendaMobileAddBookingBtn"><i class="bi bi-calendar-check me-1"></i>Nova marcação</button>' +
                                '<button type="button" class="btn btn-outline-secondary btn-sm" id="agendaMobileAddPersonalTimeBtn"><i class="bi bi-person me-1"></i>Novo tempo pessoal</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(addModal);
        }
    }

    function updateAgendaMobileViewList() {
        var list = document.getElementById('agendaMobileViewList');
        if (!list) return;
        var views = [
            { type: 'resourceTimeGridDay', label: 'Dia' },
            { type: 'timeGridThreeDay', label: '3 dias' },
            { type: 'timeGridWeek', label: 'Semana' }
        ];
        list.innerHTML = '';
        views.forEach(function(view) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action d-flex align-items-center justify-content-between' + (calendar.view.type === view.type ? ' active' : '');
            btn.setAttribute('data-view-type', view.type);
            btn.innerHTML = '<span>' + view.label + '</span>' + (calendar.view.type === view.type ? '<i class="ph ph-check"></i>' : '');
            btn.addEventListener('click', function() {
                calendar.changeView(view.type);
                calendar.gotoDate(new Date());
                updateViewSelectorButton(view.type);
                updateViewDropdownActive(view.type);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('agendaMobileViewModal')).hide();
                syncAgendaMobileControls();
            });
            list.appendChild(btn);
        });
    }

    function updateAgendaMobileFilterOptions() {
        var select = document.getElementById('agendaMobileConsultantSelect');
        var toggle24h = document.getElementById('agendaMobileSlot24hToggle');
        if (!select || !toggle24h) return;

        var supportsFilter = viewSupportsConsultantFilter(calendar.view.type);
        select.innerHTML = '';
        var allOpt = document.createElement('option');
        allOpt.value = '';
        allOpt.textContent = 'Toda a equipa';
        select.appendChild(allOpt);
        (C.usersForConsultant || []).forEach(function(u) {
            var opt = document.createElement('option');
            opt.value = String(u.id);
            opt.textContent = u.name;
            select.appendChild(opt);
        });
        select.value = selectedConsultantId || '';
        select.disabled = !supportsFilter;
        toggle24h.checked = !!agendaSlot24hEnabled;
    }

    function bindAgendaMobileControls() {
        if (agendaMobileControlsBound) return;
        agendaMobileControlsBound = true;

        document.getElementById('agendaMobileRefreshBtn')?.addEventListener('click', function() {
            var btn = this;
            btn.classList.add('is-loading');
            refreshAgendaData();
            setTimeout(function() { btn.classList.remove('is-loading'); }, 500);
        });

        document.getElementById('agendaMobileViewBtn')?.addEventListener('click', function() {
            updateAgendaMobileViewList();
            if (allResources.length === 0 && resourcesUrl) {
                fetch(resourcesUrl, { headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(res) { allResources = res || []; })
                    .catch(function() {});
            }
            updateAgendaMobileFilterOptions();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('agendaMobileViewModal')).show();
        });

        document.getElementById('agendaMobileAddBtn')?.addEventListener('click', function() {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('agendaMobileAddModal')).show();
        });

        document.getElementById('agendaMobileConsultantSelect')?.addEventListener('change', function() {
            var selectedId = this.value || '';
            var label = 'Toda a equipa';
            if (selectedId) {
                var selectedUser = (C.usersForConsultant || []).find(function(u) { return String(u.id) === String(selectedId); });
                label = selectedUser ? selectedUser.name : label;
            }
            setConsultantFilterSelection(selectedId, label);
        });

        document.getElementById('agendaMobileSlot24hToggle')?.addEventListener('change', function() {
            agendaSlot24hEnabled = this.checked;
            var r = getAgendaSlotRange(agendaSlot24hEnabled);
            calendar.setOption('slotMinTime', r.min);
            calendar.setOption('slotMaxTime', r.max);
            try {
                localStorage.setItem(AGENDA_SLOT_STORAGE_KEY, agendaSlot24hEnabled ? '1' : '0');
            } catch (e) {}
            if (calendar.view.type.indexOf('timeGrid') !== -1 || calendar.view.type.indexOf('resourceTimeGrid') !== -1) {
                setTimeout(function() {
                    var now = new Date();
                    var currentTime = now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0') + ':00';
                    calendar.scrollToTime(currentTime);
                }, 50);
            }
        });

        document.getElementById('agendaMobileAddBookingBtn')?.addEventListener('click', function() {
            var slot = getClosestSlotToNow();
            var resourceId = (viewSupportsConsultantFilter(calendar.view.type) && selectedConsultantId) ? selectedConsultantId : null;
            openNovaMarcacaoModal(slot.startStr, slot.endStr, resourceId);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('agendaMobileAddModal')).hide();
        });

        document.getElementById('agendaMobileAddPersonalTimeBtn')?.addEventListener('click', function() {
            var slot = getClosestSlotToNow();
            var resourceId = (viewSupportsConsultantFilter(calendar.view.type) && selectedConsultantId) ? selectedConsultantId : null;
            openTempoPessoalModal(slot.startStr, slot.endStr, resourceId);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('agendaMobileAddModal')).hide();
        });
    }

    function syncAgendaMobileControls() {
        var controls = document.getElementById('agendaMobileControls');
        if (!controls) return;
        var isMobile = isAgendaMobileViewport();
        controls.classList.toggle('is-visible', isMobile);
        if (!isMobile) return;
        updateAgendaMobileViewList();
        updateAgendaMobileFilterOptions();
    }

    ensureAgendaMobileControls();
    ensureAgendaMobileModals();
    bindAgendaMobileControls();
    syncAgendaMobileControls();
    window.addEventListener('resize', syncAgendaMobileControls);

    // Clique no ícone de estado: abre só o dropdown de estados (não o modal). Captura em fase capture para correr antes do eventClick do FullCalendar.
    // Marcações faturadas (has_invoice): não abrir menu.
    calendarEl.addEventListener('click', function(e) {
        var btn = e.target.closest('.agenda-event-status-icon-btn');
        if (!btn) return;
        var evEl = btn.closest('.fc-event');
        if (!evEl || !evEl.dataset.eventId) return;
        var ev = calendar.getEventById(evEl.dataset.eventId);
        if (!ev) return;
        if (ev.extendedProps && ev.extendedProps.invoice_settled) return;
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
            /* Só mostrar overlay dentro da grelha de tempo; nunca na toolbar/cabeçalhos. */
            if (target.closest && (target.closest('.fc-header-toolbar') || target.closest('.fc-toolbar') || target.closest('.fc-col-header'))) {
                clearAgendaHoverHighlight();
                return;
            }
            var inTimeGrid = target.closest && (
                target.closest('.fc-timegrid-body') ||
                target.closest('.fc-timegrid-slots') ||
                target.closest('.fc-timegrid-cols') ||
                target.closest('.fc-timegrid-col')
            );
            if (!inTimeGrid) {
                clearAgendaHoverHighlight();
                return;
            }
            /* Por cima de um evento: não mostrar overlay de célula (evita “mancha” sobre a marcação; z-index do FC é pouco fiável) */
            if (target.closest && (target.closest('.fc-event') || target.closest('.fc-timegrid-more-link'))) {
                clearAgendaHoverHighlight();
                return;
            }
            var topAt = document.elementFromPoint(e.clientX, e.clientY);
            if (topAt && calendarEl.contains(topAt) && topAt.closest) {
                if (topAt.closest('.fc-event') || topAt.closest('.fc-timegrid-more-link') || topAt.closest('.fc-event-mirror')) {
                    clearAgendaHoverHighlight();
                    return;
                }
            }
            var slotEl = target.closest('[data-slot-date]');
            if (!slotEl) {
                // Fallback: quando o alvo é uma camada intermédia (ex.: bg-event),
                // resolve o slot pela posição do rato, igualando comportamento ao da loja.
                var probeX = e.clientX;
                var probeY = e.clientY;
                var allSlots = calendarEl.querySelectorAll('[data-slot-date]');
                for (var si = 0; si < allSlots.length; si++) {
                    var rr = allSlots[si].getBoundingClientRect();
                    if (probeX >= rr.left && probeX <= rr.right && probeY >= rr.top && probeY <= rr.bottom) {
                        slotEl = allSlots[si];
                        break;
                    }
                }
            }
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

            /* Overlay em position:fixed; z-index baixo para ficar abaixo dos eventos (.fc-timegrid-event-harness) */
            if (!_agendaHoverHighlight) {
                var wrapper = document.createElement('div');
                wrapper.className = 'agenda-cell-highlight-hover';
                wrapper.setAttribute('role', 'presentation');
                wrapper.style.position = 'fixed';
                wrapper.style.zIndex = '4';
                wrapper.style.pointerEvents = 'none';
                var timeSpan = document.createElement('span');
                timeSpan.className = 'agenda-cell-time-overlay';
                wrapper.appendChild(timeSpan);
                _agendaHoverHighlight = wrapper;
            }
            _agendaHoverHighlight.style.top = slotRect.top + 'px';
            _agendaHoverHighlight.style.left = colRect.left + 'px';
            _agendaHoverHighlight.style.width = 'calc(' + colRect.width + 'px - 6px)';
            _agendaHoverHighlight.style.height = 'calc(' + slotRect.height + 'px - 4px)';
            _agendaHoverHighlight.style.margin = '2px 2px 0 3px';
            _agendaHoverHighlight.querySelector('.agenda-cell-time-overlay').textContent = timeLabel;
            if (!_agendaHoverHighlight.parentNode) {
                if (calendarEl) calendarEl.appendChild(_agendaHoverHighlight);
                else document.body.appendChild(_agendaHoverHighlight);
            }
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
                        bootstrap.Offcanvas.getOrCreateInstance($id('eventDetailEditModal')).show();
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
        } else if (novaMarcacao === '1') {
            var now = new Date();
            var min = now.getMinutes();
            var roundedMin = Math.ceil(min / 15) * 15;
            if (roundedMin >= 60) { now.setHours(now.getHours() + 1); roundedMin = 0; }
            now.setMinutes(roundedMin);
            now.setSeconds(0, 0);
            var end = new Date(now.getTime() + 60 * 60 * 1000);
            var startStr = now.toISOString().slice(0, 19).replace('T', ' ');
            var endStr = end.toISOString().slice(0, 19).replace('T', ' ');
            openNovaMarcacaoModal(startStr, endStr, userId || '', clientId || null);
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
            currentDateBtn.style.pointerEvents = 'auto';
            currentDateBtn.style.cursor = 'pointer';
            currentDateBtn.style.fontWeight = '500';
            currentDateBtn.style.color = '#212529';
            currentDateBtn.style.opacity = '1';
            currentDateBtn.setAttribute('title', 'Escolher data');
            currentDateBtn.setAttribute('aria-label', 'Escolher data');
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

    function setConsultantFilterSelection(userId, userLabel) {
        selectedConsultantId = userId ? String(userId) : '';
        consultantFilterIds = selectedConsultantId ? [selectedConsultantId] : [];
        const consultantBtn = calendarEl.querySelector('.fc-consultantFilter-button');
        if (consultantBtn) {
            consultantBtn.textContent = userLabel || 'Toda a equipa';
        }
        refreshAgendaData();
        updateDropdownActive();
        syncAgendaMobileControls();
    }

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
            setConsultantFilterSelection('', 'Toda a equipa');
        });
        dropdown.appendChild(allOption);
        (C.usersForConsultant || []).forEach(function(u) {
            var opt = document.createElement('a');
            opt.className = 'dropdown-item';
            opt.href = '#';
            opt.textContent = u.name;
            opt.addEventListener('click', function(e) {
                e.preventDefault();
                setConsultantFilterSelection(String(u.id), u.name);
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

    // Status é guardado ao clicar Guardar no offcanvas eventDetailEditModal

    if (typeof flatpickr !== 'undefined') {
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
                        updateTempoPessoalOutOfHoursWarning();
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
        TempoPessoal.populateTimeOptions('.tempo-pessoal-end-time-options', timeStr, TempoPessoal.applyNewEndTime);
    });
    $id('tempoPessoalEndTimeToggle')?.addEventListener('shown.bs.dropdown', function() {
        var active = $('.tempo-pessoal-end-time-options .tempo-pessoal-time-opt.active');
        if (active) active.scrollIntoView({ block: 'nearest', behavior: 'instant' });
    });
    $id('tempoPessoalStartTimeToggle')?.addEventListener('hidden.bs.dropdown', function() {
        updateTempoPessoalOutOfHoursWarning();
    });
    $id('tempoPessoalEndTimeToggle')?.addEventListener('hidden.bs.dropdown', function() {
        updateTempoPessoalOutOfHoursWarning();
    });
    $id('tempoPessoalMembro')?.addEventListener('change', function() {
        updateTempoPessoalOutOfHoursWarning();
    });

    $id('tempoPessoalTypeToggleGroup')?.addEventListener('click', function(e) {
        var card = e.target.closest('.tempo-pessoal-type-card');
        if (!card) return;
        $$('.tempo-pessoal-type-card').forEach(function(c) { c.classList.remove('active'); });
        card.classList.add('active');
        $id('tempoPessoalTipo').value = card.dataset.id || '';
        TempoPessoal.applyTypeDuration();
        TempoPessoal.syncCustomTitleField();
        updateTempoPessoalOutOfHoursWarning();
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
        var isCustomTempoPessoal = TempoPessoal.isCustomTypeSelected();
        var payload = {
            personal_time_type_id: $id('tempoPessoalTipo').value || null,
            event_type: 'tempo_pessoal',
            start_at: agendaLocalInputToUtcIso(startVal),
            end_at: agendaLocalInputToUtcIso(endVal || startVal),
            description: $id('tempoPessoalDescricao').value.trim() || null,
            user_id: currentUserIsAdmin ? (memberVal || null) : (memberVal || String(C.authId || ''))
        };
        if (isCustomTempoPessoal) {
            payload.title = $id('tempoPessoalTitulo').value.trim() || '';
        }
        if (isCustomTempoPessoal && !payload.title) {
            showToast('Preencha o título para o tipo Outro.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            return;
        }
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
                if (previousStatus === 'faltou' || previousStatus === 'cancelado' || previousStatus === 'anulado') return;
                window._cancelMarcacaoConfirmed = false;
                window._cancelMarcacaoPreviousStatus = previousStatus;
                window._cancelMarcacaoContext = 'edit';
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
                $id('cancelMarcacaoNotifyClient').checked = false;
                bootstrap.Modal.getOrCreateInstance($id('cancelMarcacaoModal')).show();
                return;
            }
            $id('eventDetailStatus').value = status;
            $id('eventDetailStatusLabel').textContent = labels[status] || status;
            var iconEl = $id('eventDetailStatusIcon');
            if (iconEl) {
                var ic = iconEl.querySelector('i');
                if (ic) ic.className = 'ph ' + (icons[status] || 'ph-clock');
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
                            if (ic) ic.className = 'ph ' + (icons[previousStatus] || 'ph-clock');
                        }
                        showToast(res.message || 'Erro ao atualizar estado.', 'error');
                    }
                })
                .catch(function() {
                    $id('eventDetailStatus').value = previousStatus;
                    $id('eventDetailStatusLabel').textContent = labels[previousStatus] || previousStatus;
                    if (iconEl) {
                        var ic = iconEl.querySelector('i');
                        if (ic) ic.className = 'ph ' + (icons[previousStatus] || 'ph-clock');
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
            avisou_dentro_prazo: avisouDentroPrazo,
            notify_client: $id('cancelMarcacaoNotifyClient').checked
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
                bootstrap.Offcanvas.getInstance($id('eventDetailEditModal'))?.hide();
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

    $id('agendaDragConfirmSubmit').addEventListener('click', function() {
        if (!agendaDragPending) return;
        var p = agendaDragPending;
        var chk = $id('agendaDragConfirmNotify');
        var notify = chk && chk.checked && !chk.disabled;
        var btn = $id('agendaDragConfirmSubmit');
        var origText = 'Atualizar';
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + origText;

        if (p.kind === 'eventDetail') {
            var body = Object.assign({}, p.putPayload, { notify_client: !!notify });
            fetch((C.urlEvents || '') + '/' + p.eventId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(body)
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
                btn.textContent = origText;
                if (!res.success || !res.event) {
                    showToast(res.message || 'Erro ao guardar.', 'error');
                    return;
                }
                agendaDragConfirmSucceeded = true;
                agendaDragPending = null;
                bootstrap.Modal.getInstance($id('agendaDragConfirmModal')).hide();
                var ev = calendar.getEventById(p.eventId);
                if (ev && res.event) {
                    applyAgendaEventFromServer(ev, res.event);
                }
                // No fluxo com confirmação de notificação (agendaDragConfirm),
                // garantir re-sync completo para refletir a nova duração em tempo real.
                calendar.refetchEvents();
                eventDetailWasSaved = true;
                bootstrap.Offcanvas.getInstance($id('eventDetailEditModal'))?.hide();
                scheduleStackedEventClassRefresh();
            })
            .catch(function(err) {
                console.error('agendaDragConfirm eventDetail error', err);
                btn.disabled = false;
                btn.textContent = origText;
                var msg = (err && err.message && err.message.indexOf('Unexpected') === -1) ? err.message : 'Erro de ligação. Verifique os logs do servidor se o problema persistir.';
                showToast(msg, 'error');
            });
            return;
        }

        var bodyDrag = Object.assign({}, p.payload, { notify_client: !!notify });
        var url = (C.urlEvents || '') + '/' + p.eventId + '/update';
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify(bodyDrag)
        })
        .then(function(r) {
            if (!r.ok) throw new Error(r.statusText);
            return r.json();
        })
        .then(function(res) {
            btn.disabled = false;
            btn.textContent = origText;
            if (!res.success) {
                showToast(res.message || 'Erro ao atualizar.', 'error');
                return;
            }
            agendaDragConfirmSucceeded = true;
            agendaDragPending = null;
            bootstrap.Modal.getInstance($id('agendaDragConfirmModal')).hide();
            var ev = calendar.getEventById(p.eventId);
            if (ev && res.event) {
                applyAgendaEventFromServer(ev, res.event);
                if (p.kind === 'drop' && p.payload.user_id !== undefined && p.info && p.info.newResource) {
                    var newColor = res.event.backgroundColor;
                    if (newColor == null && allResources && allResources.length) {
                        var resObj = allResources.find(function(r) { return String(r.id) === String(p.info.newResource.id); });
                        newColor = resObj && resObj.extendedProps ? resObj.extendedProps.color : null;
                    }
                    if (newColor) {
                        ev.setProp('backgroundColor', newColor);
                        var domEl = document.querySelector('[data-event-id="' + String(ev.id) + '"]');
                        if (domEl) domEl.style.setProperty('background-color', newColor, 'important');
                    }
                    if (typeof calendar.getResourceById === 'function' && typeof ev.setResources === 'function') {
                        var rr = calendar.getResourceById(String(p.payload.user_id));
                        if (rr) ev.setResources([rr]);
                    }
                }
            }
            scheduleStackedEventClassRefresh();
        })
        .catch(function(err) {
            console.error('agendaDragConfirm error', err);
            btn.disabled = false;
            btn.textContent = origText;
            showToast('Erro de ligação.', 'error');
        });
    });

    $id('agendaDragConfirmModal').addEventListener('shown.bs.modal', function() {
        var backs = document.querySelectorAll('.modal-backdrop');
        if (backs.length > 1) {
            backs[backs.length - 1].style.zIndex = '1070';
        }
    });

    $id('agendaDragConfirmModal').addEventListener('hidden.bs.modal', function() {
        var p = agendaDragPending;
        var succeeded = agendaDragConfirmSucceeded;
        agendaDragConfirmSucceeded = false;
        agendaDragPending = null;
        if (!succeeded && p && p.needsCalendarRevert && p.info && typeof p.info.revert === 'function') {
            p.info.revert();
            scheduleStackedEventClassRefresh();
        }
    });

    $id('cancelMarcacaoModal').addEventListener('hidden.bs.modal', function() {
        if (window._cancelMarcacaoConfirmed) return;
        var prev = window._cancelMarcacaoPreviousStatus;
        if (prev === undefined) return;
        var labels = { agendado: 'Agendado', notificado: 'Notificado', confirmado: 'Confirmado', chegou: 'Chegou', iniciado: 'Iniciado', terminado: 'Terminado', faltou: 'Faltou', cancelado: 'Cancelado', anulado: 'Anulado', completo: 'Pago' };
        var icons = { agendado: 'ph-clock', notificado: 'ph-bell agenda-status-icon-notificado', confirmado: 'ph-bell agenda-status-icon-confirmado', chegou: 'ph-map-pin', iniciado: 'ph-play', terminado: 'ph-check-circle agenda-status-icon-confirmado', faltou: 'ph-prohibit', cancelado: 'ph-x-circle', anulado: 'ph-x-circle', completo: 'ph-check-circle agenda-status-icon-confirmado' };
        $id('eventDetailStatus').value = prev;
        $id('eventDetailStatusLabel').textContent = labels[prev] || prev;
        var iconEl = $id('eventDetailStatusIcon');
        if (iconEl) {
            var ic = iconEl.querySelector('i');
            if (ic) ic.className = 'ph ' + (icons[prev] || 'ph-clock');
        }
        window._cancelMarcacaoContext = null;
    });

    $id('eventDetailEditModal').addEventListener('hidden.bs.offcanvas', function() {
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
        eventDetailOcTeardownUi();
        var eDetCan = $id('eventDetailClientCancelBtn');
        if (eDetCan) eDetCan.classList.add('d-none');
        eventDetailOriginalStartAt = null;
        eventDetailOriginalEndAt = null;
    });
});