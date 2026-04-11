/**
 * Fluxo público de marcação — carrinho local + modal Bootstrap.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'booking_cart_v1';
    var TECH_STORAGE_KEY = 'booking_technician_v1';
    var DATETIME_STORAGE_KEY = 'booking_datetime_v1';
    var CONTACT_STORAGE_KEY = 'booking_contact_v1';
    var dateTimeInitAttempts = 0;

    function formatMoneyEUR(amount) {
        return (
            new Intl.NumberFormat('pt-PT', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(amount) + '\u00A0€'
        );
    }

    function parseServicePayload(raw) {
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function generateLineId() {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        return 'line-' + Date.now() + '-' + Math.random().toString(36).slice(2, 9);
    }

    var state = {
        items: [],
        pending: null,
    };

    var els = {};

    function cacheElements() {
        els.modal = document.getElementById('bookingModal');
        els.modalName = document.getElementById('booking-modal-service-name');
        els.modalMeta = document.getElementById('booking-modal-service-meta');
        els.modalConfirm = document.getElementById('booking-modal-confirm');
        els.summaryEmpty = document.getElementById('booking-summary-empty');
        els.summaryList = document.getElementById('booking-summary-list');
        els.summaryTotal = document.getElementById('booking-summary-total');
        els.summaryTotalValue = document.getElementById('booking-summary-total-value');
        els.summaryTotalCount = document.getElementById('booking-summary-total-count');
        els.summaryTotalDuration = document.getElementById('booking-summary-total-duration');
        els.nextBtn = document.getElementById('booking-next');
    }

    function getModalInstance() {
        if (!els.modal || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return null;
        }
        return bootstrap.Modal.getOrCreateInstance(els.modal);
    }

    function lockSummaryPanelDuringModal() {
        var panel = document.querySelector('.booking-summary-panel');
        if (!panel) {
            return;
        }
        var rect = panel.getBoundingClientRect();
        if (!rect || rect.width <= 0) {
            return;
        }
        panel.setAttribute('data-modal-locked', '1');
        panel.style.position = 'fixed';
        panel.style.top = 'var(--booking-sticky-offset)';
        panel.style.left = rect.left + 'px';
        panel.style.width = rect.width + 'px';
        panel.style.zIndex = '1010';
    }

    function unlockSummaryPanelAfterModal() {
        var panel = document.querySelector('.booking-summary-panel[data-modal-locked="1"]');
        if (!panel) {
            return;
        }
        panel.removeAttribute('data-modal-locked');
        panel.style.removeProperty('position');
        panel.style.removeProperty('top');
        panel.style.removeProperty('left');
        panel.style.removeProperty('width');
        panel.style.removeProperty('z-index');
    }

    function getTotalAmount() {
        return state.items.reduce(function (sum, line) {
            return sum + (Number(line.price) || 0);
        }, 0);
    }

    function parseDurationToMinutes(text) {
        if (!text) {
            return 0;
        }
        var raw = String(text).toLowerCase();
        var hoursMatch = raw.match(/(\d+)\s*h/);
        var minsMatch = raw.match(/(\d+)\s*min/);
        var hours = hoursMatch ? Number(hoursMatch[1]) : 0;
        var mins = minsMatch ? Number(minsMatch[1]) : 0;
        return (hours * 60) + mins;
    }

    function getTotalDurationMinutes() {
        return state.items.reduce(function (sum, line) {
            var mins = Number(line.durationMinutes);
            if (!Number.isFinite(mins) || mins <= 0) {
                mins = parseDurationToMinutes(line.duration);
            }
            return sum + (Number.isFinite(mins) ? mins : 0);
        }, 0);
    }

    function formatDurationPT(totalMinutes) {
        var mins = Math.max(0, Math.round(Number(totalMinutes) || 0));
        var hours = Math.floor(mins / 60);
        var rest = mins % 60;
        if (hours > 0 && rest > 0) {
            return hours + 'h' + String(rest).padStart(2, '0') + 'min';
        }
        if (hours > 0) {
            return hours + 'h';
        }
        return rest + 'min';
    }

    function renderSummary() {
        if (!els.summaryList || !els.summaryEmpty || !els.summaryTotal) {
            return;
        }

        if (state.items.length === 0) {
            els.summaryEmpty.classList.remove('is-hidden');
            els.summaryList.classList.add('is-hidden');
            els.summaryList.innerHTML = '';
            els.summaryTotal.classList.add('is-hidden');
            if (els.nextBtn) {
                els.nextBtn.disabled = true;
            }
            return;
        }

        els.summaryEmpty.classList.add('is-hidden');
        els.summaryList.classList.remove('is-hidden');
        els.summaryTotal.classList.remove('is-hidden');
        if (els.nextBtn) {
            var requirement = els.nextBtn.getAttribute('data-next-requires');
            if (requirement === 'technician') {
                els.nextBtn.disabled = !hasSelectedTechnician();
            } else if (requirement === 'datetime') {
                els.nextBtn.disabled = !hasSelectedDateTime();
            } else if (requirement === 'checkout') {
                els.nextBtn.disabled = !hasCheckoutContact();
            } else {
                els.nextBtn.disabled = false;
            }
        }

        els.summaryList.innerHTML = '';
        state.items.forEach(function (line) {
            var li = document.createElement('li');
            li.className = 'booking-summary-line';
            li.setAttribute('data-line-id', line.lineId);

            var body = document.createElement('div');
            body.className = 'booking-summary-line__body';

            var name = document.createElement('span');
            name.className = 'booking-summary-line__name';
            name.textContent = line.name;

            var meta = document.createElement('span');
            meta.className = 'booking-summary-line__meta';
            meta.textContent = line.duration;

            body.appendChild(name);
            body.appendChild(meta);

            var side = document.createElement('div');
            side.className = 'booking-summary-line__side';

            var price = document.createElement('span');
            price.className = 'booking-summary-line__price';
            price.textContent = line.priceFormatted || formatMoneyEUR(line.price);

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'booking-summary-line__remove';
            removeBtn.setAttribute('aria-label', 'Remover ' + line.name);
            removeBtn.textContent = 'remover';
            removeBtn.addEventListener('click', function () {
                removeLine(line.lineId);
            });

            side.appendChild(price);
            side.appendChild(removeBtn);

            li.appendChild(body);
            li.appendChild(side);
            els.summaryList.appendChild(li);
        });

        if (els.summaryTotalValue) {
            els.summaryTotalValue.textContent = formatMoneyEUR(getTotalAmount());
        }
        if (els.summaryTotalCount) {
            var count = state.items.length;
            els.summaryTotalCount.textContent = count + ' ' + (count === 1 ? 'serviço' : 'serviços');
        }
        if (els.summaryTotalDuration) {
            els.summaryTotalDuration.textContent = formatDurationPT(getTotalDurationMinutes());
        }
    }

    function removeLine(lineId) {
        state.items = state.items.filter(function (l) {
            return l.lineId !== lineId;
        });
        persist();
        renderSummary();
        if (state.items.length === 0) {
            redirectToStep1IfNeeded();
        }
    }

    function redirectToStep1IfNeeded() {
        var body = document.body;
        if (!body) {
            return;
        }
        var classList = body.classList;
        var isStep2Or3 =
            classList.contains('booking-page--technician') ||
            classList.contains('booking-page--datetime') ||
            classList.contains('booking-page--step3');
        if (!isStep2Or3) {
            return;
        }
        var indexUrl = body.getAttribute('data-booking-index-url');
        if (indexUrl) {
            window.location.href = indexUrl;
        }
    }

    function persist() {
        try {
            sessionStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({
                    items: state.items,
                    updatedAt: Date.now(),
                })
            );
        } catch (e) {
            /* ignore */
        }
    }

    function getSelectedServiceIds() {
        var ids = {};
        state.items.forEach(function (line) {
            if (line && line.id != null) {
                ids[String(line.id)] = true;
            }
        });
        return Object.keys(ids);
    }

    function saveTechnicianSelection(tech) {
        try {
            sessionStorage.setItem(
                TECH_STORAGE_KEY,
                JSON.stringify({
                    id: tech.id,
                    name: tech.name,
                    updatedAt: Date.now(),
                })
            );
        } catch (e) {
            /* ignore */
        }
    }

    function clearTechnicianSelection() {
        try {
            sessionStorage.removeItem(TECH_STORAGE_KEY);
        } catch (e) {
            /* ignore */
        }
    }

    function getTechnicianSelection() {
        try {
            var raw = sessionStorage.getItem(TECH_STORAGE_KEY);
            if (!raw) {
                return null;
            }
            var data = JSON.parse(raw);
            if (!data || data.id == null) {
                return null;
            }
            return data;
        } catch (e) {
            return null;
        }
    }

    function hasSelectedTechnician() {
        return !!getTechnicianSelection();
    }

    function saveDateTimeSelection(payload) {
        try {
            sessionStorage.setItem(
                DATETIME_STORAGE_KEY,
                JSON.stringify({
                    date: payload.date || '',
                    time: payload.time || '',
                    updatedAt: Date.now(),
                })
            );
        } catch (e) {
            /* ignore */
        }
    }

    function getDateTimeSelection() {
        try {
            var raw = sessionStorage.getItem(DATETIME_STORAGE_KEY);
            if (!raw) {
                return null;
            }
            var data = JSON.parse(raw);
            if (!data || !data.date || !data.time) {
                return null;
            }
            return data;
        } catch (e) {
            return null;
        }
    }

    function hasSelectedDateTime() {
        return !!getDateTimeSelection();
    }

    function saveContactSelection(payload) {
        try {
            sessionStorage.setItem(
                CONTACT_STORAGE_KEY,
                JSON.stringify({
                    name: payload.name || '',
                    phone: payload.phone || '',
                    phoneDisplay: payload.phoneDisplay || '',
                    email: payload.email || '',
                    notes: payload.notes || '',
                    updatedAt: Date.now(),
                })
            );
        } catch (e) {
            /* ignore */
        }
    }

    function getContactSelection() {
        try {
            var raw = sessionStorage.getItem(CONTACT_STORAGE_KEY);
            if (!raw) {
                return null;
            }
            var data = JSON.parse(raw);
            if (!data) {
                return null;
            }
            return data;
        } catch (e) {
            return null;
        }
    }

    /**
     * Checkout com cliente autenticado: só hidden name/email/phone (sem campo tel visível).
     * Convidado também tem <input name="phone" type="hidden"> para E.164 — não confundir com perfil.
     */
    function isBookingCheckoutProfileMode() {
        var form = document.getElementById('booking-checkout-form');
        if (!form) {
            return false;
        }
        return !document.getElementById('booking-contact-phone');
    }

    /** Checkout com sessão de cliente: hidden name/email/phone no formulário. */
    function getCheckoutContactPayload() {
        var form = document.getElementById('booking-checkout-form');
        if (form && isBookingCheckoutProfileMode()) {
            var nameEl = form.querySelector('input[name="name"]');
            var emailEl = form.querySelector('input[name="email"]');
            var phoneEl = form.querySelector('input[name="phone"]');
            var notesEl = document.getElementById('booking-contact-notes');
            return {
                name: nameEl ? nameEl.value.trim() : '',
                email: emailEl ? emailEl.value.trim() : '',
                phone: phoneEl ? phoneEl.value.trim() : '',
                phoneDisplay: phoneEl ? phoneEl.value.trim() : '',
                notes: notesEl ? notesEl.value.trim() : '',
            };
        }
        return getContactSelection();
    }

    function hasCheckoutContact() {
        var form = document.getElementById('booking-checkout-form');
        if (form && isBookingCheckoutProfileMode()) {
            var p = form.querySelector('input[name="phone"]');
            var e = form.querySelector('input[name="email"]');
            var n = form.querySelector('input[name="name"]');
            return !!(p && p.value && e && e.value && n && n.value);
        }
        var c = getContactSelection();
        if (!c) {
            return false;
        }
        return !!(c.name && c.phone && c.email);
    }

    function pad2(n) {
        return String(n).padStart(2, '0');
    }

    function formatPtDateHeading(date) {
        var weekdays = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        var months = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        return weekdays[date.getDay()] + ', ' + date.getDate() + ' ' + months[date.getMonth()] + ' ' + date.getFullYear();
    }

    function formatPtMonthYear(monthIdx, year) {
        var months = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        return months[monthIdx] + ' ' + year;
    }

    function syncFlatpickrMonthTitle(instance) {
        if (!instance || !instance.calendarContainer) {
            return;
        }
        var currentMonthEl = instance.calendarContainer.querySelector('.flatpickr-current-month');
        if (!currentMonthEl) {
            return;
        }
        currentMonthEl.textContent = formatPtMonthYear(instance.currentMonth, instance.currentYear);
        currentMonthEl.setAttribute('aria-live', 'polite');
    }

    /** Manhã antes das 13:00; tarde a partir das 13:00 (horário contínuo 9h–20h). */
    function splitSlotsMorningAfternoon(timeStrings) {
        var splitMin = 13 * 60;
        var morning = [];
        var afternoon = [];
        timeStrings.forEach(function (t) {
            var mins = slotTimeToMinutes(t);
            if (mins < splitMin) {
                morning.push(t);
            } else {
                afternoon.push(t);
            }
        });
        return { morning: morning, afternoon: afternoon };
    }

    function isSameCalendarDay(a, b) {
        return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }

    function minutesSinceMidnight(date) {
        return date.getHours() * 60 + date.getMinutes();
    }

    function slotTimeToMinutes(time) {
        var parts = String(time).split(':');
        var h = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10);
        if (isNaN(h) || isNaN(m)) {
            return 0;
        }
        return h * 60 + m;
    }

    /** For the calendar day equal to “now”, keep only slots at or after the current clock time. */
    function filterSlotsForSelectedDay(selectedDate, slots) {
        var now = new Date();
        if (!isSameCalendarDay(selectedDate, now)) {
            return slots;
        }
        var nowMin = minutesSinceMidnight(now);
        return slots.filter(function (time) {
            return slotTimeToMinutes(time) >= nowMin;
        });
    }

    function initDateTimeStep() {
        var calendarInput = document.getElementById('booking-calendar');
        var dayTitle = document.getElementById('booking-slots-day');
        var morningWrap = document.getElementById('booking-slots-morning');
        var afternoonWrap = document.getElementById('booking-slots-afternoon');
        var slotsStatus = document.getElementById('booking-slots-status');
        var slotsPeriods = document.getElementById('booking-slots-periods');
        if (!calendarInput || !dayTitle || !morningWrap || !afternoonWrap) {
            return;
        }
        if (typeof flatpickr === 'undefined') {
            if (dateTimeInitAttempts < 20) {
                dateTimeInitAttempts += 1;
                window.setTimeout(initDateTimeStep, 100);
            }
            return;
        }
        if (calendarInput.getAttribute('data-flatpickr-ready') === '1') {
            return;
        }

        var bookingApp = document.querySelector('.booking-app[data-booking-availability-url]');
        var availabilityUrl = bookingApp ? bookingApp.getAttribute('data-booking-availability-url') : '';
        var availabilityAbort = null;

        var selected = getDateTimeSelection();
        var selectedDate = selected ? selected.date : null;
        var selectedTime = selected ? selected.time : null;

        function bookingDurationMinutes() {
            var m = getTotalDurationMinutes();
            if (!Number.isFinite(m) || m <= 0) {
                m = 30;
            }
            return Math.max(15, m);
        }

        function clearSlotsStatus() {
            if (!slotsStatus) {
                return;
            }
            slotsStatus.textContent = '';
            slotsStatus.className = 'booking-slots__status small mb-3';
        }

        function showSlotsStatus(kind, message) {
            if (!slotsStatus) {
                return;
            }
            slotsStatus.textContent = message;
            var extra = kind === 'error' ? ' text-danger' : ' text-muted';
            slotsStatus.className = 'booking-slots__status small mb-3' + extra;
        }

        function setSlotsPeriodsWrapperVisible(show) {
            if (slotsPeriods) {
                slotsPeriods.style.display = show ? '' : 'none';
            }
        }

        function fetchAvailabilitySlots(isoDate, agentId, durationMinutes, signal) {
            if (!availabilityUrl) {
                return Promise.resolve([]);
            }
            var url =
                availabilityUrl +
                '?date=' +
                encodeURIComponent(isoDate) +
                '&agent_id=' +
                encodeURIComponent(agentId) +
                '&duration=' +
                encodeURIComponent(String(durationMinutes));
            return fetch(url, { credentials: 'same-origin', signal: signal })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('availability HTTP ' + r.status);
                    }
                    return r.json();
                })
                .then(function (data) {
                    return Array.isArray(data.slots) ? data.slots : [];
                });
        }

        function renderSlotButtons(container, slots) {
            container.innerHTML = '';
            slots.forEach(function (time) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-outline-secondary booking-slot-pill';
                btn.textContent = time;
                btn.setAttribute('data-slot-time', time);
                if (selectedTime && selectedTime === time) {
                    btn.classList.add('is-active');
                }
                btn.addEventListener('click', function () {
                    selectedTime = time;
                    var all = document.querySelectorAll('.booking-slot-pill');
                    all.forEach(function (el) {
                        el.classList.remove('is-active');
                    });
                    btn.classList.add('is-active');
                    saveDateTimeSelection({
                        date: selectedDate,
                        time: selectedTime,
                    });
                    renderSummary();
                });
                container.appendChild(btn);
            });
        }

        function fillSlotPeriod(container, slots) {
            if (!slots || slots.length === 0) {
                container.innerHTML = '';
                var emptyEl = document.createElement('p');
                emptyEl.className = 'text-muted small mb-0';
                emptyEl.textContent = 'Sem horários disponíveis';
                container.appendChild(emptyEl);
                return;
            }
            renderSlotButtons(container, slots);
        }

        function applySlotsToDom(date, slotList) {
            clearSlotsStatus();
            setSlotsPeriodsWrapperVisible(true);
            var filtered = filterSlotsForSelectedDay(date, slotList);
            if (filtered.length === 0) {
                selectedTime = null;
                fillSlotPeriod(morningWrap, []);
                fillSlotPeriod(afternoonWrap, []);
                saveDateTimeSelection({
                    date: selectedDate,
                    time: '',
                });
                renderSummary();
                return;
            }
            var split = splitSlotsMorningAfternoon(filtered);
            var mSlots = split.morning;
            var aSlots = split.afternoon;
            var valid = {};
            mSlots.forEach(function (t) {
                valid[t] = true;
            });
            aSlots.forEach(function (t) {
                valid[t] = true;
            });
            if (selectedTime && !valid[selectedTime]) {
                selectedTime = null;
            }
            fillSlotPeriod(morningWrap, mSlots);
            fillSlotPeriod(afternoonWrap, aSlots);
            if (!selectedTime) {
                saveDateTimeSelection({
                    date: selectedDate,
                    time: '',
                });
            } else {
                saveDateTimeSelection({
                    date: selectedDate,
                    time: selectedTime,
                });
            }
            renderSummary();
        }

        function renderDaySlots(date) {
            selectedDate = date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
            dayTitle.textContent = formatPtDateHeading(date);
            var tech = getTechnicianSelection();
            var agentId = tech && tech.id != null && tech.id !== '' ? String(tech.id) : 'any';
            var durationM = bookingDurationMinutes();

            morningWrap.innerHTML = '';
            afternoonWrap.innerHTML = '';
            showSlotsStatus('loading', 'A carregar horários…');
            setSlotsPeriodsWrapperVisible(true);

            if (availabilityAbort) {
                availabilityAbort.abort();
            }
            availabilityAbort = typeof AbortController !== 'undefined' ? new AbortController() : null;
            var signal = availabilityAbort ? availabilityAbort.signal : undefined;

            if (!availabilityUrl) {
                applySlotsToDom(date, []);
                return;
            }

            fetchAvailabilitySlots(selectedDate, agentId, durationM, signal)
                .then(function (slots) {
                    applySlotsToDom(date, slots);
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    clearSlotsStatus();
                    showSlotsStatus(
                        'error',
                        'Não foi possível carregar os horários. Atualiza a página e tenta novamente.'
                    );
                    setSlotsPeriodsWrapperVisible(true);
                    fillSlotPeriod(morningWrap, []);
                    fillSlotPeriod(afternoonWrap, []);
                    selectedTime = null;
                    saveDateTimeSelection({
                        date: selectedDate,
                        time: '',
                    });
                    renderSummary();
                });
        }

        flatpickr(calendarInput, {
            inline: true,
            locale: (flatpickr && flatpickr.l10ns && flatpickr.l10ns.pt) ? flatpickr.l10ns.pt : undefined,
            dateFormat: 'Y-m-d',
            minDate: 'today',
            defaultDate: selectedDate || 'today',
            onChange: function (dates) {
                if (!dates || !dates.length) {
                    return;
                }
                selectedTime = null;
                renderDaySlots(dates[0]);
            },
            onReady: function (dates, dateStr, instance) {
                syncFlatpickrMonthTitle(instance);
                if (dates && dates.length) {
                    renderDaySlots(dates[0]);
                }
            },
            onMonthChange: function (dates, dateStr, instance) {
                syncFlatpickrMonthTitle(instance);
            },
            onYearChange: function (dates, dateStr, instance) {
                syncFlatpickrMonthTitle(instance);
            },
        });
        calendarInput.setAttribute('data-flatpickr-ready', '1');
    }

    function initTechnicianStep() {
        var list = document.getElementById('booking-technician-list');
        if (!list) {
            return;
        }
        var rows = Array.prototype.slice.call(list.querySelectorAll('.booking-technician-row'));
        var serviceIds = getSelectedServiceIds();
        var eligibleAll = [];
        var eligibleAny = [];

        rows.forEach(function (row) {
            var isAnyStaff = row.getAttribute('data-any-staff') === '1';
            if (isAnyStaff) {
                return;
            }
            var raw = row.getAttribute('data-tech-service-ids');
            var techServiceIds = [];
            try {
                techServiceIds = JSON.parse(raw || '[]').map(function (id) { return String(id); });
            } catch (e) {
                techServiceIds = [];
            }
            var coversAll = serviceIds.every(function (sid) {
                return techServiceIds.indexOf(String(sid)) !== -1;
            });
            var coversAny = serviceIds.some(function (sid) {
                return techServiceIds.indexOf(String(sid)) !== -1;
            });
            if (coversAll) {
                eligibleAll.push(row);
            }
            if (coversAny) {
                eligibleAny.push(row);
            }
        });

        var chosenSet = eligibleAll.length ? eligibleAll : eligibleAny;
        rows.forEach(function (row) {
            var isAnyStaff = row.getAttribute('data-any-staff') === '1';
            var isVisible = isAnyStaff || chosenSet.indexOf(row) !== -1;
            row.classList.toggle('is-hidden-tech', !isVisible);
            var input = row.querySelector('input[type="radio"]');
            if (input) {
                input.disabled = !isVisible;
            }
        });

        var previous = getTechnicianSelection();
        var previousId = previous ? String(previous.id) : null;
        var hasPreviousVisible = false;
        rows.forEach(function (row) {
            var input = row.querySelector('input[type="radio"]');
            if (!input) {
                return;
            }
            if (!row.classList.contains('is-hidden-tech') && previousId && String(input.value) === previousId) {
                input.checked = true;
                hasPreviousVisible = true;
            } else if (row.classList.contains('is-hidden-tech')) {
                input.checked = false;
            }
        });
        if (previousId && !hasPreviousVisible) {
            clearTechnicianSelection();
        }

        rows.forEach(function (row) {
            var input = row.querySelector('input[type="radio"]');
            if (!input) {
                return;
            }
            input.addEventListener('change', function () {
                if (!input.checked) {
                    return;
                }
                var nameEl = row.querySelector('.booking-technician-row__name');
                saveTechnicianSelection({
                    id: input.value,
                    name: nameEl ? nameEl.textContent.trim() : '',
                });
                renderSummary();
            });
        });
        renderSummary();
    }

    function loadFromStorage() {
        try {
            var raw = sessionStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return;
            }
            var data = JSON.parse(raw);
            if (data && Array.isArray(data.items)) {
                state.items = data.items.filter(function (line) {
                    return line && line.lineId && line.id != null && line.name;
                });
            }
        } catch (e) {
            state.items = [];
        }
    }

    function openModal(service) {
        if (!els.modalName || !els.modalMeta) {
            return;
        }
        state.pending = service;
        els.modalName.textContent = service.name;
        els.modalMeta.textContent =
            service.duration + ' · ' + (service.priceFormatted || formatMoneyEUR(service.price));
        var m = getModalInstance();
        if (m) {
            m.show();
        }
    }

    function closeModal() {
        var m = getModalInstance();
        if (m) {
            m.hide();
        }
        state.pending = null;
    }

    function confirmAdd() {
        if (!state.pending) {
            return;
        }
        var s = state.pending;
        state.items.push({
            lineId: generateLineId(),
            id: s.id,
            name: s.name,
            duration: s.duration,
            durationMinutes: Number(s.durationMinutes) || 0,
            price: s.price,
            priceFormatted: s.priceFormatted,
        });
        persist();
        renderSummary();
        closeModal();
    }

    function bindServiceButtons() {
        var buttons = document.querySelectorAll('.booking-row--btn[data-service]');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var payload = parseServicePayload(btn.getAttribute('data-service'));
                if (payload) {
                    openModal(payload);
                }
            });
        });
    }

    function bindModal() {
        if (els.modalConfirm) {
            els.modalConfirm.addEventListener('click', confirmAdd);
        }
        if (els.modal) {
            els.modal.addEventListener('show.bs.modal', function () {
                document.body.classList.add('booking-modal-open');
                lockSummaryPanelDuringModal();
            });
            els.modal.addEventListener('hidden.bs.modal', function () {
                state.pending = null;
                document.body.classList.remove('booking-modal-open');
                /* Garante que o Bootstrap não deixa estilos inline que possam afetar sticky */
                document.body.style.removeProperty('padding-right');
                document.body.style.removeProperty('overflow');
                unlockSummaryPanelAfterModal();
            });
            els.modal.addEventListener('shown.bs.modal', function () {
                if (els.modalConfirm) {
                    els.modalConfirm.focus();
                }
            });
        }
    }

    function initCheckoutStep() {
        var form = document.getElementById('booking-checkout-form');
        if (!form) {
            return;
        }
        var nameInput = document.getElementById('booking-contact-name');
        var phoneInput = document.getElementById('booking-contact-phone');
        var phoneE164Input = document.getElementById('booking-contact-phone-e164');
        var emailInput = document.getElementById('booking-contact-email');
        var notesInput = document.getElementById('booking-contact-notes');

        if (isBookingCheckoutProfileMode()) {
            var hName = form.querySelector('input[name="name"]');
            var hEmail = form.querySelector('input[name="email"]');
            var hPhone = form.querySelector('input[name="phone"]');
            saveContactSelection({
                name: hName ? hName.value.trim() : '',
                phone: hPhone ? hPhone.value.trim() : '',
                phoneDisplay: hPhone ? hPhone.value.trim() : '',
                email: hEmail ? hEmail.value.trim() : '',
                notes: notesInput ? notesInput.value.trim() : '',
            });
            if (notesInput) {
                ['input', 'blur', 'change'].forEach(function (evt) {
                    notesInput.addEventListener(evt, function () {
                        saveContactSelection({
                            name: hName ? hName.value.trim() : '',
                            phone: hPhone ? hPhone.value.trim() : '',
                            phoneDisplay: hPhone ? hPhone.value.trim() : '',
                            email: hEmail ? hEmail.value.trim() : '',
                            notes: notesInput ? notesInput.value.trim() : '',
                        });
                        renderSummary();
                    });
                });
            }
            renderSummary();
            return;
        }

        var existing = getContactSelection();
        if (existing) {
            if (nameInput) nameInput.value = existing.name || '';
            if (phoneInput) phoneInput.value = existing.phoneDisplay || existing.phone || '';
            if (phoneE164Input) phoneE164Input.value = existing.phone || '';
            if (emailInput) emailInput.value = existing.email || '';
            if (notesInput) notesInput.value = existing.notes || '';
        }

        function syncContactState() {
            var phoneE164 = phoneE164Input ? phoneE164Input.value : '';
            if (phoneInput && typeof window.intlTelInput === 'function') {
                var iti = window.intlTelInput.getInstance(phoneInput);
                if (iti && phoneInput.value.trim() !== '') {
                    if (typeof iti.isValidNumber === 'function' && iti.isValidNumber()) {
                        phoneE164 = iti.getNumber();
                    } else {
                        phoneE164 = '';
                    }
                } else if (phoneInput && phoneInput.value.trim() === '') {
                    phoneE164 = '';
                }
            }
            if (phoneE164Input) {
                phoneE164Input.value = phoneE164;
            }
            saveContactSelection({
                name: nameInput ? nameInput.value.trim() : '',
                phone: phoneE164,
                phoneDisplay: phoneInput ? phoneInput.value.trim() : '',
                email: emailInput ? emailInput.value.trim() : '',
                notes: notesInput ? notesInput.value.trim() : '',
            });
            renderSummary();
        }

        ['input', 'blur', 'change'].forEach(function (evt) {
            if (nameInput) nameInput.addEventListener(evt, syncContactState);
            if (emailInput) emailInput.addEventListener(evt, syncContactState);
            if (notesInput) notesInput.addEventListener(evt, syncContactState);
        });

        if (!phoneInput || typeof window.intlTelInput !== 'function') {
            syncContactState();
            return;
        }

        /** Mesma configuração que a agenda (PT + strictMode + limite de dígitos ao escrever). */
        var intlPtBase = 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/js/i18n/pt';
        var loadPt = Promise.all([
            import(intlPtBase + '/countries.js'),
            import(intlPtBase + '/interface.js'),
        ])
            .then(function (mods) {
                return Object.assign({}, mods[0].default, mods[1].default);
            })
            .catch(function (err) {
                console.warn('intl-tel-input (booking): locale PT não carregado', err);
                return {};
            });

        loadPt.then(function (ptI18n) {
            var phoneIti = window.intlTelInput(phoneInput, {
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
            phoneInput.addEventListener('countrychange', syncContactState);
            ['input', 'blur', 'change'].forEach(function (evt) {
                phoneInput.addEventListener(evt, syncContactState);
            });
            if (phoneIti && phoneIti.promise && typeof phoneIti.promise.then === 'function') {
                phoneIti.promise.then(function () {
                    var existingPhone = existing && existing.phone ? String(existing.phone).trim() : '';
                    if (existingPhone && existingPhone.indexOf('+') === 0 && phoneIti.setNumber) {
                        phoneIti.setNumber(existingPhone);
                    }
                    syncContactState();
                });
            } else {
                syncContactState();
            }
        });
    }

    function getCsrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') || '' : '';
    }

    function setCheckoutError(message) {
        var el = document.getElementById('booking-checkout-error');
        if (!el) {
            return;
        }
        el.textContent = message || '';
        el.classList.toggle('d-none', !message);
    }

    function hideCheckoutError() {
        setCheckoutError('');
        var magicWrap = document.getElementById('booking-checkout-magic-wrap');
        if (magicWrap) {
            magicWrap.classList.add('d-none');
        }
    }

    function submitBookingCheckout() {
        var bookingApp = document.querySelector('.booking-app[data-booking-submit-url]');
        var submitUrl = bookingApp ? bookingApp.getAttribute('data-booking-submit-url') : '';
        if (!submitUrl) {
            setCheckoutError('Serviço de marcação indisponível.');
            return;
        }
        var contact = getCheckoutContactPayload();
        var tech = getTechnicianSelection();
        var dt = getDateTimeSelection();
        if (!contact || !tech || !dt || !contact.phone || !state.items.length) {
            setCheckoutError('Falta informação. Volta atrás e completa todos os passos.');
            return;
        }
        hideCheckoutError();
        var payload = {
            name: contact.name,
            email: contact.email,
            phone: contact.phone,
            notes: contact.notes || '',
            date: dt.date,
            time: dt.time,
            agent_id: String(tech.id),
            services: state.items.map(function (line) {
                return { id: line.id };
            }),
        };
        if (els.nextBtn) {
            els.nextBtn.disabled = true;
        }
        fetch(submitUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        })
            .then(function (r) {
                return r.json().catch(function () {
                    return {};
                }).then(function (data) {
                    return { ok: r.ok, status: r.status, data: data };
                });
            })
            .then(function (res) {
                if (res.ok && res.data && res.data.redirect) {
                    try {
                        sessionStorage.removeItem(STORAGE_KEY);
                        sessionStorage.removeItem(TECH_STORAGE_KEY);
                        sessionStorage.removeItem(DATETIME_STORAGE_KEY);
                        sessionStorage.removeItem(CONTACT_STORAGE_KEY);
                    } catch (e) {
                        /* ignore */
                    }
                    window.location.href = res.data.redirect;
                    return;
                }
                var msg = 'Não foi possível concluir a marcação.';
                if (res.data && res.data.message) {
                    msg = res.data.message;
                }
                if (res.data && res.data.errors && typeof res.data.errors === 'object') {
                    var lines = [];
                    Object.keys(res.data.errors).forEach(function (k) {
                        var arr = res.data.errors[k];
                        if (Array.isArray(arr)) {
                            arr.forEach(function (t) {
                                lines.push(t);
                            });
                        }
                    });
                    if (lines.length) {
                        msg = lines.join(' ');
                    }
                }
                var magicWrap = document.getElementById('booking-checkout-magic-wrap');
                if (magicWrap) {
                    if (res.data && res.data.requires_login) {
                        magicWrap.classList.remove('d-none');
                    } else {
                        magicWrap.classList.add('d-none');
                    }
                }
                setCheckoutError(msg);
                if (els.nextBtn) {
                    els.nextBtn.disabled = false;
                }
                renderSummary();
            })
            .catch(function () {
                setCheckoutError('Erro de rede. Tenta novamente.');
                if (els.nextBtn) {
                    els.nextBtn.disabled = false;
                }
                renderSummary();
            });
    }

    function bindNext() {
        if (!els.nextBtn) {
            return;
        }
        els.nextBtn.addEventListener('click', function () {
            if (state.items.length === 0) {
                return;
            }
            var requirement = els.nextBtn.getAttribute('data-next-requires');
            if (requirement === 'checkout') {
                var checkoutForm = document.getElementById('booking-checkout-form');
                var phoneInput = document.getElementById('booking-contact-phone');
                if (checkoutForm && !checkoutForm.reportValidity()) {
                    return;
                }
                if (phoneInput && typeof window.intlTelInput === 'function' && phoneInput.value.trim() !== '') {
                    var iti = window.intlTelInput.getInstance(phoneInput);
                    if (iti && !iti.isValidNumber()) {
                        phoneInput.setCustomValidity('Indique um telemóvel válido para o país selecionado.');
                        phoneInput.reportValidity();
                        return;
                    }
                    phoneInput.setCustomValidity('');
                }
                persist();
                submitBookingCheckout();
                return;
            }
            persist();
            var url = els.nextBtn.getAttribute('data-next-url');
            if (url && url !== '#') {
                window.location.href = url;
            }
        });
    }

    function init() {
        cacheElements();
        loadFromStorage();
        renderSummary();
        initTechnicianStep();
        initDateTimeStep();
        initCheckoutStep();
        bindServiceButtons();
        bindModal();
        bindNext();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
