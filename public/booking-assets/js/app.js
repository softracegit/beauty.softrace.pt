/**
 * Fluxo público de marcação — carrinho local + modal Bootstrap.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'booking_cart_v1';
    var TECH_STORAGE_KEY = 'booking_technician_v1';
    var DATETIME_STORAGE_KEY = 'booking_datetime_v1';
    var CONTACT_STORAGE_KEY = 'booking_contact_v1';
    var STRIPE_CHECKOUT_PUBLIC_ID_KEY = 'booking_checkout_public_id';
    var SLOT_HOLD_STORAGE_KEY = 'booking_slot_hold_v1';
    var bookingStorage = createBookingStorage();
    var dateTimeInitAttempts = 0;
    /** Definido em initDateTimeStep: volta a pedir horários com a duração atual do carrinho. */
    var bookingRefreshDateTimeSlotsFn = null;
    /** Definido em initDateTimeStep: alinha `selectedTime` com booking_datetime_v1 (evita re-acquire em loop após conflito). */
    var syncBookingDateTimeSlotFromStorage = null;

    var checkoutPaymentState = {
        clientSecret: null,
        publishableKey: null,
        bookingPublicId: null,
        stripe: null,
        elements: null,
    };

    function formatMoneyEUR(amount) {
        return (
            new Intl.NumberFormat('pt-PT', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(amount) + '\u00A0€'
        );
    }

    function createBookingStorage() {
        function makeNoopStorage() {
            return {
                setItem: function () {},
                getItem: function () {
                    return null;
                },
                removeItem: function () {},
            };
        }

        try {
            var testKeyLocal = '__booking_local_test__';
            window.localStorage.setItem(testKeyLocal, '1');
            window.localStorage.removeItem(testKeyLocal);
            return {
                setItem: function (key, value) {
                    window.localStorage.setItem(key, value);
                },
                getItem: function (key) {
                    var val = window.localStorage.getItem(key);
                    if (val != null) {
                        return val;
                    }
                    try {
                        var sessionVal = window.sessionStorage.getItem(key);
                        if (sessionVal != null) {
                            window.localStorage.setItem(key, sessionVal);
                        }
                        return sessionVal;
                    } catch (e) {
                        return null;
                    }
                },
                removeItem: function (key) {
                    window.localStorage.removeItem(key);
                    try {
                        window.sessionStorage.removeItem(key);
                    } catch (e) {
                        /* ignore */
                    }
                },
            };
        } catch (e) {
            try {
                var testKeySession = '__booking_session_test__';
                window.sessionStorage.setItem(testKeySession, '1');
                window.sessionStorage.removeItem(testKeySession);
                return {
                    setItem: function (key, value) {
                        window.sessionStorage.setItem(key, value);
                    },
                    getItem: function (key) {
                        return window.sessionStorage.getItem(key);
                    },
                    removeItem: function (key) {
                        window.sessionStorage.removeItem(key);
                    },
                };
            } catch (err) {
                return makeNoopStorage();
            }
        }
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

    var slotHoldState = {
        holdPublicId: '',
        sessionToken: '',
        expiresAt: '',
        date: '',
        time: '',
        agentId: '',
        servicesSignature: '',
    };
    var slotHoldTimer = {
        intervalId: null,
        expiredModalShown: false,
    };
    var suppressSlotHoldUiErrors = false;
    var slotHoldAcquirePromise = null;
    var slotHoldAcquireKey = '';
    var pendingDateTimeSlotsErrorNotice = '';
    var slotHoldShowZeroDuringExpiredModal = false;

    var els = {};

    function cacheElements() {
        els.modal = document.getElementById('bookingModal');
        els.modalTitle = document.getElementById('bookingModalTitle');
        els.modalMeta = document.getElementById('booking-modal-service-meta');
        els.modalOptionsWrap = document.getElementById('booking-modal-options-wrap');
        els.modalOptions = document.getElementById('booking-modal-options');
        els.modalOptionsError = document.getElementById('booking-modal-options-error');
        els.modalConfirm = document.getElementById('booking-modal-confirm');
        els.modalFooterAdd = document.getElementById('booking-modal-footer-add');
        els.modalFooterEdit = document.getElementById('booking-modal-footer-edit');
        els.modalRemoveLine = document.getElementById('booking-modal-remove-line');
        els.modalApplyEdit = document.getElementById('booking-modal-apply-edit');
        els.summaryEmpty = document.getElementById('booking-summary-empty');
        els.summaryList = document.getElementById('booking-summary-list');
        els.summaryTotal = document.getElementById('booking-summary-total');
        els.summaryTotalValue = document.getElementById('booking-summary-total-value');
        els.summaryTotalCount = document.getElementById('booking-summary-total-count');
        els.summaryTotalDuration = document.getElementById('booking-summary-total-duration');
        els.summaryTech = document.getElementById('booking-summary-technician');
        els.summaryTechAvatar = document.getElementById('booking-summary-tech-avatar');
        els.summaryTechName = document.getElementById('booking-summary-tech-name');
        els.summaryTechMeta = document.getElementById('booking-summary-tech-meta');
        els.summaryDateTime = document.getElementById('booking-summary-datetime');
        els.summaryDateLabel = document.getElementById('booking-summary-date-label');
        els.summaryTimeLabel = document.getElementById('booking-summary-time-label');
        els.summarySlotHold = document.getElementById('booking-summary-slot-hold');
        els.summarySlotHoldTime = document.getElementById('booking-summary-slot-hold-time');
        els.nextBtn = document.getElementById('booking-next');
        els.slotHoldExpiredModal = document.getElementById('booking-slot-hold-expired-modal');
        els.slotHoldRestartBtn = document.getElementById('booking-slot-hold-restart');
        els.slotHoldExtendBtn = document.getElementById('booking-slot-hold-extend');
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

    function formatDateLabelPt(dateIso) {
        if (!dateIso || !/^\d{4}-\d{2}-\d{2}$/.test(String(dateIso))) {
            return '';
        }
        var parts = String(dateIso).split('-');
        var year = Number(parts[0]);
        var month = Number(parts[1]) - 1;
        var day = Number(parts[2]);
        var d = new Date(year, month, day);
        if (isNaN(d.getTime())) {
            return '';
        }
        var weekday = new Intl.DateTimeFormat('pt-PT', { weekday: 'long' }).format(d);
        var monthLabel = new Intl.DateTimeFormat('pt-PT', { month: 'long' }).format(d);
        var weekdayCap = weekday.charAt(0).toUpperCase() + weekday.slice(1);
        var monthCap = monthLabel.charAt(0).toUpperCase() + monthLabel.slice(1);
        return weekdayCap + ', ' + day + ' ' + monthCap;
    }

    function formatEndTime(startTime, totalMinutes) {
        if (!startTime || !/^\d{2}:\d{2}$/.test(String(startTime))) {
            return '';
        }
        var h = Number(String(startTime).slice(0, 2));
        var m = Number(String(startTime).slice(3, 5));
        if (isNaN(h) || isNaN(m)) {
            return '';
        }
        var total = (h * 60 + m + Math.max(0, Math.round(Number(totalMinutes) || 0))) % (24 * 60);
        var hh = String(Math.floor(total / 60)).padStart(2, '0');
        var mm = String(total % 60).padStart(2, '0');
        return hh + ':' + mm;
    }

    function getTechnicianInitials(name) {
        if (!name || typeof name !== 'string') {
            return '?';
        }
        var parts = name.trim().split(/\s+/).filter(function (p) {
            return p.length > 0;
        });
        if (parts.length === 0) {
            return '?';
        }
        var first = parts[0];
        var last = parts[parts.length - 1];
        var a = first.charAt(0);
        var b =
            parts.length > 1
                ? last.charAt(0)
                : first.length > 1
                  ? first.charAt(1)
                  : '';
        var s = (a + b).toUpperCase();
        return s || '?';
    }

    function renderSummaryTechAvatar(tech) {
        if (!els.summaryTechAvatar) {
            return;
        }
        var container = els.summaryTechAvatar;
        container.innerHTML = '';
        container.classList.remove('booking-summary-tech__avatar--has-img');

        var rawUrl = tech.avatar != null ? String(tech.avatar).trim() : '';
        var initials = getTechnicianInitials(tech.name);

        function showInitials() {
            var span = document.createElement('span');
            span.className = 'booking-summary-tech__avatar-fallback';
            span.textContent = initials;
            container.appendChild(span);
        }

        if (rawUrl) {
            var img = document.createElement('img');
            img.alt = '';
            img.loading = 'lazy';
            img.decoding = 'async';
            img.src = rawUrl;
            img.addEventListener('load', function () {
                container.classList.add('booking-summary-tech__avatar--has-img');
            });
            img.addEventListener('error', function () {
                img.remove();
                showInitials();
            });
            container.appendChild(img);
        } else {
            showInitials();
        }
    }

    function renderSummaryExtras() {
        var hasItems = state.items.length > 0;
        var tech = getTechnicianSelection();
        var dt = getDateTimeSelection();

        if (els.summaryTech) {
            var showTech = hasItems && !!tech;
            els.summaryTech.classList.toggle('is-hidden', !showTech);
            if (showTech) {
                if (els.summaryTechName) {
                    els.summaryTechName.textContent = tech.name || '';
                }
                if (els.summaryTechMeta) {
                    els.summaryTechMeta.textContent = tech.specialization || '';
                }
                renderSummaryTechAvatar(tech);
            }
        }

        if (els.summaryDateTime) {
            var showDateTime = hasItems && !!dt;
            els.summaryDateTime.classList.toggle('is-hidden', !showDateTime);
            if (showDateTime) {
                if (els.summaryDateLabel) {
                    els.summaryDateLabel.textContent = formatDateLabelPt(dt.date);
                }
                if (els.summaryTimeLabel) {
                    var start = dt.time || '';
                    var end = formatEndTime(start, getTotalDurationMinutes());
                    els.summaryTimeLabel.textContent = end ? start + ' - ' + end : start;
                }
            }
        }
    }

    function isServiceParentInCart(serviceId) {
        if (serviceId == null || serviceId === '') {
            return false;
        }
        var sid = String(serviceId);
        return state.items.some(function (l) {
            return l && l.id != null && String(l.id) === sid;
        });
    }

    function renderSummary() {
        if (!els.summaryList || !els.summaryEmpty || !els.summaryTotal) {
            return;
        }
        renderSlotHoldBanner();

        if (state.items.length === 0) {
            els.summaryEmpty.classList.remove('is-hidden');
            els.summaryList.classList.add('is-hidden');
            els.summaryList.innerHTML = '';
            els.summaryTotal.classList.add('is-hidden');
            if (els.nextBtn) {
                els.nextBtn.disabled = true;
            }
            renderSummaryExtras();
            closeSummaryDrawer();
            releaseSlotHold('cart_empty');
            scheduleBookingSummaryFooterVisualBottom();
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

            var snap = line.editSnapshot;
            var hasOption =
                line.service_option_id != null &&
                line.service_option_id !== '' &&
                String(line.service_option_id) !== 'undefined';
            var parentName = hasOption && snap && snap.name ? snap.name : line.name || '';
            var optionName =
                hasOption && snap && snap.name && line.name && line.name !== snap.name ? line.name : '';

            var body = document.createElement('div');
            body.className = 'booking-summary-line__body';

            var serviceNameEl = document.createElement('span');
            serviceNameEl.className = 'booking-summary-line__service';
            serviceNameEl.textContent = parentName;
            body.appendChild(serviceNameEl);

            if (optionName) {
                var optionEl = document.createElement('span');
                optionEl.className = 'booking-summary-line__option';
                optionEl.textContent = optionName;
                body.appendChild(optionEl);
            }

            var durationEl = document.createElement('span');
            durationEl.className = 'booking-summary-line__duration';
            durationEl.textContent = line.duration || '';
            body.appendChild(durationEl);

            var side = document.createElement('div');
            side.className = 'booking-summary-line__side';

            var asideStack = document.createElement('div');
            asideStack.className = 'booking-summary-line__aside-stack';

            var price = document.createElement('span');
            price.className = 'booking-summary-line__price';
            price.textContent = line.priceFormatted || formatMoneyEUR(line.price);

            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'booking-summary-line__edit';
            var editLabel = optionName ? parentName + ' — ' + optionName : parentName;
            editBtn.setAttribute('aria-label', 'Editar ' + editLabel);
            editBtn.innerHTML = '<i class="bi bi-pencil-square" aria-hidden="true"></i>';
            editBtn.addEventListener('click', function () {
                openEditModal(line);
            });

            asideStack.appendChild(price);
            asideStack.appendChild(editBtn);
            side.appendChild(asideStack);

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
        renderSummaryExtras();
        renderSlotHoldBanner();
        updateCheckoutPaymentPreview();
        ensureSlotHoldForCurrentSelection();
        scheduleBookingSummaryFooterVisualBottom();
    }

    function removeLine(lineId) {
        state.items = state.items.filter(function (l) {
            return l.lineId !== lineId;
        });
        persist();
        renderSummary();
        if (state.items.length > 0 && bookingRefreshDateTimeSlotsFn) {
            bookingRefreshDateTimeSlotsFn();
        }
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
            bookingStorage.setItem(
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
            bookingStorage.setItem(
                TECH_STORAGE_KEY,
                JSON.stringify({
                    id: tech.id,
                    name: tech.name,
                    specialization: tech.specialization || '',
                    avatar: tech.avatar || '',
                    updatedAt: Date.now(),
                })
            );
        } catch (e) {
            /* ignore */
        }
    }

    function clearTechnicianSelection() {
        try {
            bookingStorage.removeItem(TECH_STORAGE_KEY);
        } catch (e) {
            /* ignore */
        }
        releaseSlotHold('technician_cleared');
    }

    function getTechnicianSelection() {
        try {
            var raw = bookingStorage.getItem(TECH_STORAGE_KEY);
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
            bookingStorage.setItem(
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
            var raw = bookingStorage.getItem(DATETIME_STORAGE_KEY);
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

    function loadSlotHoldState() {
        try {
            var raw = bookingStorage.getItem(SLOT_HOLD_STORAGE_KEY);
            if (!raw) {
                return;
            }
            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') {
                return;
            }
            slotHoldState = Object.assign({}, slotHoldState, parsed);
        } catch (e) {
            /* ignore */
        }
    }

    function persistSlotHoldState() {
        try {
            bookingStorage.setItem(SLOT_HOLD_STORAGE_KEY, JSON.stringify(slotHoldState));
        } catch (e) {
            /* ignore */
        }
    }

    function clearSlotHoldState() {
        slotHoldState = {
            holdPublicId: '',
            sessionToken: slotHoldState.sessionToken || '',
            expiresAt: '',
            date: '',
            time: '',
            agentId: '',
            servicesSignature: '',
        };
        try {
            bookingStorage.removeItem(SLOT_HOLD_STORAGE_KEY);
        } catch (e) {
            /* ignore */
        }
    }

    function ensureSlotHoldSessionToken() {
        if (slotHoldState.sessionToken) {
            return slotHoldState.sessionToken;
        }
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            slotHoldState.sessionToken = crypto.randomUUID().replace(/-/g, '');
        } else {
            slotHoldState.sessionToken =
                String(Date.now()) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
        }
        persistSlotHoldState();
        return slotHoldState.sessionToken;
    }

    function buildServicesForSlotHoldPayload() {
        return state.items.map(function (line) {
            var row = { id: Number(line.id) };
            if (line.service_option_id != null && line.service_option_id !== '') {
                row.service_option_id = Number(line.service_option_id);
            }
            return row;
        });
    }

    function computeServicesSignature(items) {
        var list = (items || [])
            .map(function (row) {
                var sid = Number(row && row.id);
                var opt = row && row.service_option_id != null && row.service_option_id !== '' ? Number(row.service_option_id) : null;
                if (!Number.isFinite(sid) || sid <= 0) {
                    return null;
                }
                return [sid, Number.isFinite(opt) ? opt : null];
            })
            .filter(function (x) {
                return !!x;
            })
            .sort(function (a, b) {
                if (a[0] === b[0]) {
                    return (a[1] == null ? -1 : a[1]) - (b[1] == null ? -1 : b[1]);
                }
                return a[0] - b[0];
            });
        return JSON.stringify(list);
    }

    function saveContactSelection(payload) {
        try {
            bookingStorage.setItem(
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
            var raw = bookingStorage.getItem(CONTACT_STORAGE_KEY);
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
        /** Convidado: ler sempre o DOM (sessionStorage pode estar vazio ou desatualizado). */
        var nameInput = document.getElementById('booking-contact-name');
        var emailInput = document.getElementById('booking-contact-email');
        var phoneHidden = document.getElementById('booking-contact-phone-e164');
        var phoneInput = document.getElementById('booking-contact-phone');
        var notesInput = document.getElementById('booking-contact-notes');
        if (nameInput || emailInput || phoneHidden || phoneInput) {
            var phoneE164 = phoneHidden && phoneHidden.value ? phoneHidden.value.trim() : '';
            if (!phoneE164 && phoneInput && typeof window.intlTelInput === 'function') {
                var iti = window.intlTelInput.getInstance(phoneInput);
                if (iti && typeof iti.getNumber === 'function' && phoneInput.value.trim() !== '') {
                    if (typeof iti.isValidNumber !== 'function' || iti.isValidNumber()) {
                        phoneE164 = iti.getNumber() || '';
                    }
                }
            }
            var stored = getContactSelection() || {};
            return {
                name: (nameInput && nameInput.value.trim()) || stored.name || '',
                email: (emailInput && emailInput.value.trim()) || stored.email || '',
                phone: phoneE164 || stored.phone || '',
                phoneDisplay: (phoneInput && phoneInput.value.trim()) || stored.phoneDisplay || '',
                notes: (notesInput && notesInput.value.trim()) || stored.notes || '',
            };
        }
        return getContactSelection();
    }

    function flushGuestContactFromDomToStorage() {
        if (isBookingCheckoutProfileMode()) {
            return;
        }
        var p = getCheckoutContactPayload();
        if (p && (p.name || p.email || p.phone)) {
            saveContactSelection(p);
        }
    }

    function updateCheckoutPaymentPreview() {
        var panel = document.getElementById('booking-payment-panel');
        if (!panel || !document.body.classList.contains('booking-page--step3')) {
            return;
        }
        if (!isCheckoutPaymentRequired()) {
            panel.classList.add('d-none');
            return;
        }
        if (state.items.length === 0) {
            panel.classList.add('d-none');
            return;
        }
        panel.classList.remove('d-none');
        var app = document.querySelector('.booking-app[data-booking-deposit-percent]');
        var pct = 20;
        if (app) {
            var rawPct = parseInt(app.getAttribute('data-booking-deposit-percent'), 10);
            if (Number.isFinite(rawPct) && rawPct >= 0 && rawPct <= 100) {
                pct = rawPct;
            }
        }
        var total = getTotalAmount();
        var paid = Math.round(total * (pct / 100) * 100) / 100;
        var remaining = Math.round((total - paid) * 100) / 100;
        if (!checkoutPaymentState.clientSecret) {
            var depEl = document.getElementById('booking-pay-deposit-amount');
            var pctEl = document.getElementById('booking-pay-deposit-pct');
            var remEl = document.getElementById('booking-pay-remaining-amount');
            if (depEl) {
                depEl.textContent = formatMoneyEUR(paid);
            }
            if (pctEl) {
                pctEl.textContent = String(pct);
            }
            if (remEl) {
                remEl.textContent = formatMoneyEUR(remaining);
            }
        }
        var hint = document.getElementById('booking-payment-stripe-hint');
        if (hint) {
            hint.classList.toggle('d-none', !!checkoutPaymentState.clientSecret);
        }
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

    function syncFlatpickrNavButtons(instance) {
        if (!instance || !instance.calendarContainer) {
            return;
        }
        var prev = instance.calendarContainer.querySelector('.flatpickr-prev-month');
        var next = instance.calendarContainer.querySelector('.flatpickr-next-month');
        if (prev) {
            prev.innerHTML = '<i class="bi bi-arrow-left booking-flatpickr-nav-icon" aria-hidden="true"></i>';
        }
        if (next) {
            next.innerHTML = '<i class="bi bi-arrow-right booking-flatpickr-nav-icon" aria-hidden="true"></i>';
        }
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

    /** For today, keep only slots at/after now + min lead minutes (default 30). */
    function filterSlotsForSelectedDay(selectedDate, slots) {
        var now = new Date();
        if (!isSameCalendarDay(selectedDate, now)) {
            return slots;
        }
        var leadMinutes = 30;
        var leadTarget = new Date(now.getTime() + leadMinutes * 60 * 1000);
        var nowMin = minutesSinceMidnight(leadTarget);
        return slots.filter(function (time) {
            return slotTimeToMinutes(time) >= nowMin;
        });
    }

    function initDateTimeStep() {
        var calendarInput = document.getElementById('booking-calendar');
        var weekView = document.getElementById('booking-week-view');
        var weekDays = document.getElementById('booking-week-days');
        var weekTitle = document.getElementById('booking-week-title');
        var weekPrev = document.getElementById('booking-week-prev');
        var weekNext = document.getElementById('booking-week-next');
        var calendarViewToggle = document.getElementById('booking-calendar-view-toggle');
        var dayTitle = document.getElementById('booking-slots-day');
        var morningWrap = document.getElementById('booking-slots-morning');
        var afternoonWrap = document.getElementById('booking-slots-afternoon');
        var slotsStatus = document.getElementById('booking-slots-status');
        var slotsPeriods = document.getElementById('booking-slots-periods');
        var suggestedWrap = document.getElementById('booking-slots-suggested-wrap');
        var suggestedList = document.getElementById('booking-slots-suggested-list');
        var moreBtn = document.getElementById('booking-slots-more');
        if (
            !calendarInput ||
            !weekView ||
            !weekDays ||
            !weekTitle ||
            !weekPrev ||
            !weekNext ||
            !calendarViewToggle ||
            !dayTitle ||
            !morningWrap ||
            !afternoonWrap ||
            !suggestedWrap ||
            !suggestedList ||
            !moreBtn
        ) {
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
        var cachedFilteredSlots = [];
        var slotsUiExpanded = false;
        var suggestedMorningSlots = 2;
        var suggestedAfternoonSlots = 2;

        /** Até 2 da manhã + 2 da tarde (ordem: manhã, depois tarde). */
        function pickSuggestedSlotTimes(filtered) {
            var split = splitSlotsMorningAfternoon(filtered);
            return split.morning.slice(0, suggestedMorningSlots).concat(split.afternoon.slice(0, suggestedAfternoonSlots));
        }

        /** Hora escolhida está fora dos sugeridos — ao voltar ao passo data/hora, abrir "outros horários" por defeito. */
        function shouldOpenMoreSlotsForSelection(filtered, time) {
            if (!time || !filtered || !filtered.length) {
                return false;
            }
            var suggestedTimes = pickSuggestedSlotTimes(filtered);
            var suggestedSet = {};
            suggestedTimes.forEach(function (t) {
                suggestedSet[t] = true;
            });
            var needMoreSlots = filtered.some(function (t) {
                return !suggestedSet[t];
            });
            if (!needMoreSlots) {
                return false;
            }
            return !suggestedSet[time];
        }

        var selected = getDateTimeSelection();
        var selectedDate = selected ? selected.date : null;
        var selectedTime = selected ? selected.time : null;
        var calendarView = 'week';
        var weekStart = null;
        var selectedDateObj = null;
        var today = new Date();
        today.setHours(0, 0, 0, 0);

        function parseIsoDate(iso) {
            if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(String(iso))) {
                return null;
            }
            var p = String(iso).split('-');
            var d = new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
            if (isNaN(d.getTime())) {
                return null;
            }
            d.setHours(0, 0, 0, 0);
            return d;
        }

        function startOfWeekMonday(date) {
            var d = new Date(date);
            d.setHours(0, 0, 0, 0);
            var day = d.getDay();
            var delta = day === 0 ? -6 : 1 - day;
            d.setDate(d.getDate() + delta);
            return d;
        }

        function addDays(date, days) {
            var d = new Date(date);
            d.setDate(d.getDate() + days);
            d.setHours(0, 0, 0, 0);
            return d;
        }

        function isBeforeToday(date) {
            return date.getTime() < today.getTime();
        }

        function sameDay(a, b) {
            return a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
        }

        function formatIsoDate(date) {
            return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
        }

        function formatPtWeekdayShort(date) {
            var text = new Intl.DateTimeFormat('pt-PT', { weekday: 'short' }).format(date).replace('.', '');
            return text.charAt(0).toUpperCase() + text.slice(1);
        }

        selectedDateObj = parseIsoDate(selectedDate) || new Date(today);
        weekStart = startOfWeekMonday(selectedDateObj);

        function syncSelectedTimeFromDateTimeStorage() {
            if (!selectedDate) {
                selectedTime = null;
                return;
            }
            var storedDt = getDateTimeSelection();
            if (!storedDt || !storedDt.time || storedDt.date !== selectedDate) {
                selectedTime = null;
                return;
            }
            selectedTime = storedDt.time;
        }

        syncBookingDateTimeSlotFromStorage = syncSelectedTimeFromDateTimeStorage;

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
                slotsPeriods.classList.toggle('d-none', !show);
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
            var holdToken = ensureSlotHoldSessionToken();
            if (holdToken) {
                url += '&hold_session_token=' + encodeURIComponent(holdToken);
            }
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
                    all.forEach(function (el) {
                        if (el.getAttribute('data-slot-time') === selectedTime) {
                            el.classList.add('is-active');
                        }
                    });
                    saveDateTimeSelection({
                        date: selectedDate,
                        time: selectedTime,
                    });
                    ensureSlotHoldForCurrentSelection();
                    renderSummary();
                    layoutSlotsPanels();
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

        function layoutSlotsPanels() {
            var filtered = cachedFilteredSlots;
            if (!filtered || filtered.length === 0) {
                return;
            }

            var split = splitSlotsMorningAfternoon(filtered);
            var suggestedTimes = pickSuggestedSlotTimes(filtered);
            var suggestedSet = {};
            suggestedTimes.forEach(function (t) {
                suggestedSet[t] = true;
            });
            var needMoreSlots = filtered.some(function (t) {
                return !suggestedSet[t];
            });

            suggestedWrap.classList.remove('d-none');
            renderSlotButtons(suggestedList, suggestedTimes);

            if (!needMoreSlots) {
                moreBtn.classList.add('d-none');
                setSlotsPeriodsWrapperVisible(false);
                fillSlotPeriod(morningWrap, []);
                fillSlotPeriod(afternoonWrap, []);
                return;
            }

            if (!slotsUiExpanded) {
                moreBtn.classList.remove('d-none');
                moreBtn.textContent = 'Ver mais horários';
                moreBtn.setAttribute('aria-expanded', 'false');
                setSlotsPeriodsWrapperVisible(false);
                fillSlotPeriod(morningWrap, []);
                fillSlotPeriod(afternoonWrap, []);
                return;
            }

            setSlotsPeriodsWrapperVisible(true);
            fillSlotPeriod(morningWrap, split.morning);
            fillSlotPeriod(afternoonWrap, split.afternoon);

            var cannotCollapse = selectedTime && !suggestedSet[selectedTime];
            if (cannotCollapse) {
                moreBtn.classList.add('d-none');
            } else {
                moreBtn.classList.remove('d-none');
                moreBtn.textContent = 'Ver menos horários';
                moreBtn.setAttribute('aria-expanded', 'true');
            }
        }

        function applySlotsToDom(date, slotList) {
            if (syncBookingDateTimeSlotFromStorage) {
                syncBookingDateTimeSlotFromStorage();
            }
            clearSlotsStatus();
            if (pendingDateTimeSlotsErrorNotice) {
                showSlotsStatus('error', pendingDateTimeSlotsErrorNotice);
                pendingDateTimeSlotsErrorNotice = '';
            }
            var filtered = filterSlotsForSelectedDay(date, slotList);
            cachedFilteredSlots = filtered.slice();

            if (filtered.length === 0) {
                selectedTime = null;
                slotsUiExpanded = false;
                suggestedWrap.classList.add('d-none');
                suggestedList.innerHTML = '';
                moreBtn.classList.add('d-none');
                fillSlotPeriod(morningWrap, []);
                fillSlotPeriod(afternoonWrap, []);
                setSlotsPeriodsWrapperVisible(true);
                saveDateTimeSelection({
                    date: selectedDate,
                    time: '',
                });
                releaseSlotHold('no_slots_for_day');
                renderSummary();
                return;
            }

            var valid = {};
            filtered.forEach(function (t) {
                valid[t] = true;
            });
            if (selectedTime && !valid[selectedTime]) {
                selectedTime = null;
                releaseSlotHold('slot_invalidated');
            }

            slotsUiExpanded =
                slotsUiExpanded || shouldOpenMoreSlotsForSelection(filtered, selectedTime);

            layoutSlotsPanels();

            if (!selectedTime) {
                saveDateTimeSelection({
                    date: selectedDate,
                    time: '',
                });
                releaseSlotHold('time_cleared');
            } else {
                saveDateTimeSelection({
                    date: selectedDate,
                    time: selectedTime,
                });
            }
            renderSummary();
        }

        function renderDaySlots(date) {
            selectedDateObj = new Date(date);
            selectedDateObj.setHours(0, 0, 0, 0);
            selectedDate = formatIsoDate(selectedDateObj);
            weekStart = startOfWeekMonday(selectedDateObj);
            dayTitle.textContent = formatPtDateHeading(date);
            var tech = getTechnicianSelection();
            var agentId = tech && tech.id != null && tech.id !== '' ? String(tech.id) : 'any';
            var durationM = bookingDurationMinutes();

            morningWrap.innerHTML = '';
            afternoonWrap.innerHTML = '';
            slotsUiExpanded = false;
            suggestedWrap.classList.add('d-none');
            suggestedList.innerHTML = '';
            moreBtn.classList.add('d-none');
            showSlotsStatus('loading', 'A carregar horários…');
            setSlotsPeriodsWrapperVisible(false);

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
                    slotsUiExpanded = false;
                    cachedFilteredSlots = [];
                    suggestedWrap.classList.add('d-none');
                    suggestedList.innerHTML = '';
                    moreBtn.classList.add('d-none');
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

        function renderWeekView() {
            weekTitle.textContent = formatPtMonthYear(weekStart.getMonth(), weekStart.getFullYear());
            weekDays.innerHTML = '';
            var i;
            for (i = 0; i < 7; i += 1) {
                var d = addDays(weekStart, i);
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'booking-week-view__day';
                btn.setAttribute('role', 'option');
                btn.setAttribute('data-date', formatIsoDate(d));
                btn.disabled = isBeforeToday(d);

                var wd = document.createElement('span');
                wd.className = 'booking-week-view__weekday';
                wd.textContent = formatPtWeekdayShort(d);
                var dn = document.createElement('span');
                dn.className = 'booking-week-view__daynum';
                dn.textContent = String(d.getDate());
                btn.appendChild(wd);
                btn.appendChild(dn);

                if (sameDay(d, today)) {
                    btn.classList.add('is-today');
                }
                if (sameDay(d, selectedDateObj)) {
                    btn.classList.add('is-active');
                    btn.setAttribute('aria-selected', 'true');
                } else {
                    btn.setAttribute('aria-selected', 'false');
                }

                btn.addEventListener('click', function () {
                    if (this.disabled) {
                        return;
                    }
                    var picked = parseIsoDate(this.getAttribute('data-date'));
                    if (!picked) {
                        return;
                    }
                    selectedTime = null;
                    fp.setDate(picked, false);
                    renderDaySlots(picked);
                    renderWeekView();
                });
                weekDays.appendChild(btn);
            }
            weekPrev.disabled = startOfWeekMonday(addDays(weekStart, 6)).getTime() <= startOfWeekMonday(today).getTime();
        }

        function setCalendarView(mode, fpInstance) {
            calendarView = mode === 'week' ? 'week' : 'month';
            var isWeek = calendarView === 'week';
            /* onReady corre antes de `var fp = flatpickr(...)` estar atribuído — usar instance do callback. */
            var calendarInstance = fpInstance || fp;
            if (calendarInstance && calendarInstance.calendarContainer) {
                calendarInstance.calendarContainer.classList.toggle('d-none', isWeek);
            }
            weekView.classList.toggle('d-none', !isWeek);
            calendarViewToggle.classList.toggle('is-week', isWeek);
            calendarViewToggle.setAttribute('aria-expanded', isWeek ? 'true' : 'false');
            calendarViewToggle.setAttribute('aria-label', isWeek ? 'Alternar para vista mensal' : 'Alternar para vista semanal');
            if (isWeek) {
                renderWeekView();
            }
        }

        var baseLocale = (flatpickr && flatpickr.l10ns && flatpickr.l10ns.pt) ? flatpickr.l10ns.pt : {};
        var calendarLocale = Object.assign({}, baseLocale, { firstDayOfWeek: 1 });

        var fp = flatpickr(calendarInput, {
            inline: true,
            locale: calendarLocale,
            dateFormat: 'Y-m-d',
            minDate: 'today',
            defaultDate: selectedDate || 'today',
            onChange: function (dates) {
                if (!dates || !dates.length) {
                    return;
                }
                selectedTime = null;
                renderDaySlots(dates[0]);
                if (calendarView === 'week') {
                    renderWeekView();
                }
            },
            onReady: function (dates, dateStr, instance) {
                syncFlatpickrMonthTitle(instance);
                syncFlatpickrNavButtons(instance);
                if (dates && dates.length) {
                    renderDaySlots(dates[0]);
                }
                setCalendarView('week', instance);
            },
            onMonthChange: function (dates, dateStr, instance) {
                syncFlatpickrMonthTitle(instance);
                syncFlatpickrNavButtons(instance);
            },
            onYearChange: function (dates, dateStr, instance) {
                syncFlatpickrMonthTitle(instance);
                syncFlatpickrNavButtons(instance);
            },
        });

        weekPrev.addEventListener('click', function () {
            weekStart = addDays(weekStart, -7);
            renderWeekView();
        });

        weekNext.addEventListener('click', function () {
            weekStart = addDays(weekStart, 7);
            renderWeekView();
        });

        calendarViewToggle.addEventListener('click', function () {
            setCalendarView(calendarView === 'week' ? 'month' : 'week');
        });

        moreBtn.addEventListener('click', function () {
            var list = cachedFilteredSlots;
            if (!list || !list.length) {
                return;
            }
            var sug = pickSuggestedSlotTimes(list);
            var sugSet = {};
            sug.forEach(function (t) {
                sugSet[t] = true;
            });
            if (!list.some(function (t) {
                return !sugSet[t];
            })) {
                return;
            }
            if (slotsUiExpanded) {
                if (selectedTime && !sugSet[selectedTime]) {
                    return;
                }
                slotsUiExpanded = false;
                layoutSlotsPanels();
            } else {
                slotsUiExpanded = true;
                var tech = getTechnicianSelection();
                var agentId = tech && tech.id != null && tech.id !== '' ? String(tech.id) : 'any';
                var durationM = bookingDurationMinutes();
                if (availabilityAbort) {
                    availabilityAbort.abort();
                }
                availabilityAbort = typeof AbortController !== 'undefined' ? new AbortController() : null;
                var signal = availabilityAbort ? availabilityAbort.signal : undefined;
                showSlotsStatus('loading', 'A atualizar horários…');
                fetchAvailabilitySlots(selectedDate, agentId, durationM, signal)
                    .then(function (slots) {
                        applySlotsToDom(selectedDateObj, slots);
                    })
                    .catch(function (err) {
                        if (err && err.name === 'AbortError') {
                            return;
                        }
                        clearSlotsStatus();
                        showSlotsStatus('error', 'Não foi possível atualizar os horários. Tenta novamente.');
                    });
            }
        });

        bookingRefreshDateTimeSlotsFn = function () {
            renderDaySlots(selectedDateObj);
        };

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
                var metaEl = row.querySelector('.booking-technician-row__meta');
                saveTechnicianSelection({
                    id: input.value,
                    name: nameEl ? nameEl.textContent.trim() : '',
                    specialization: metaEl ? metaEl.textContent.trim() : '',
                    avatar: row.getAttribute('data-tech-avatar') || '',
                });
                releaseSlotHold('technician_changed');
                renderSummary();
            });
        });
        renderSummary();
    }

    function loadFromStorage() {
        try {
            var raw = bookingStorage.getItem(STORAGE_KEY);
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

    function clearModalServiceOptions() {
        if (els.modalOptions) {
            els.modalOptions.innerHTML = '';
        }
        if (els.modalOptionsWrap) {
            els.modalOptionsWrap.classList.add('d-none');
        }
        clearModalOptionsError();
    }

    function clearModalOptionsError() {
        if (!els.modalOptionsError) {
            return;
        }
        els.modalOptionsError.textContent = '';
        els.modalOptionsError.classList.add('d-none');
    }

    function showModalOptionsError(message) {
        if (!els.modalOptionsError) {
            return;
        }
        els.modalOptionsError.textContent = message || '';
        els.modalOptionsError.classList.remove('d-none');
    }

    /**
     * Garante lista de opções como array (JSON/localStorage por vezes devolve object com chaves numéricas).
     */
    function getNormalizedServiceOptions(svc) {
        if (!svc || typeof svc !== 'object') {
            return [];
        }
        var raw = svc.options;
        if (Array.isArray(raw)) {
            return raw.filter(function (o) {
                return o && o.id != null && o.id !== '';
            });
        }
        if (raw && typeof raw === 'object') {
            var keys = Object.keys(raw);
            if (!keys.length) {
                return [];
            }
            var allNumeric = keys.every(function (k) {
                return /^\d+$/.test(k);
            });
            if (allNumeric) {
                return keys
                    .sort(function (a, b) {
                        return Number(a) - Number(b);
                    })
                    .map(function (k) {
                        return raw[k];
                    })
                    .filter(function (o) {
                        return o && o.id != null && o.id !== '';
                    });
            }
        }
        return [];
    }

    function syncPendingOptionFromDom() {
        if (!state.pending || !state.pending.service) {
            return;
        }
        var svc = state.pending.service;
        var opts = getNormalizedServiceOptions(svc);
        svc.options = opts;
        if (!opts.length) {
            state.pending.selectedOption = null;
            return;
        }
        var input =
            els.modalOptions && els.modalOptions.querySelector('input[name="booking-service-option"]:checked');
        if (!input) {
            state.pending.selectedOption = null;
            return;
        }
        var want = String(input.value);
        var found = null;
        opts.forEach(function (o) {
            if (String(o.id) === want) {
                found = o;
            }
        });
        state.pending.selectedOption = found;
    }

    function setModalConfirmEnabled(enabled) {
        if (els.modalConfirm) {
            els.modalConfirm.disabled = !enabled;
        }
    }

    function formatBookingMinutesLabel(totalMinutes) {
        var m = Math.max(0, Math.floor(Number(totalMinutes) || 0));
        var hours = Math.floor(m / 60);
        var mins = m % 60;
        if (hours > 0 && mins > 0) {
            return hours + 'h ' + mins + ' min';
        }
        if (hours > 0) {
            return hours + 'h';
        }
        return mins + ' min';
    }

    function modalSummaryFromOptions(service) {
        var opts = getNormalizedServiceOptions(service);
        if (!opts.length) {
            return { price: '', duration: '' };
        }
        var minPrice = Infinity;
        var minDur = Infinity;
        var maxDur = 0;
        opts.forEach(function (o) {
            var p = Number(o.price);
            if (!isNaN(p) && p < minPrice) {
                minPrice = p;
            }
            var dm = Number(o.durationMinutes);
            if (!isNaN(dm)) {
                if (dm < minDur) {
                    minDur = dm;
                }
                if (dm > maxDur) {
                    maxDur = dm;
                }
            }
        });
        var priceLabel =
            minPrice === Infinity ? '' : 'Desde ' + formatMoneyEUR(minPrice);
        var durationLabel =
            minDur === Infinity
                ? ''
                : minDur === maxDur
                  ? formatBookingMinutesLabel(minDur)
                  : formatBookingMinutesLabel(minDur) + '+';
        return { price: priceLabel, duration: durationLabel };
    }

    function joinPriceAndDuration(pricePart, durationPart) {
        var p = (pricePart || '').trim();
        var d = (durationPart || '').trim();
        if (p && d) {
            return p + ' · ' + d;
        }
        return p || d;
    }

    function cloneServiceForEditStorage(svc) {
        if (!svc) {
            return null;
        }
        var o = {
            id: svc.id,
            name: svc.name,
            options: [],
            duration: svc.duration,
            durationMinutes: Number(svc.durationMinutes) || 0,
            price: svc.price,
            priceFormatted: svc.priceFormatted,
            summaryPriceLabel: svc.summaryPriceLabel,
            summaryDurationLabel: svc.summaryDurationLabel,
        };
        var optList = getNormalizedServiceOptions(svc);
        if (optList.length) {
            o.options = optList.map(function (opt) {
                return {
                    id: opt.id,
                    name: opt.name,
                    duration: opt.duration,
                    durationMinutes: Number(opt.durationMinutes) || 0,
                    price: opt.price,
                    priceFormatted: opt.priceFormatted,
                };
            });
        }
        return o;
    }

    function buildFallbackEditSnapshot(line) {
        return {
            id: line.id,
            name: line.name || '',
            options: [],
            duration: line.duration,
            durationMinutes: Number(line.durationMinutes) || 0,
            price: line.price,
            priceFormatted: line.priceFormatted,
            summaryPriceLabel: '',
            summaryDurationLabel: '',
        };
    }

    function setModalFooterMode(mode) {
        var isEdit = mode === 'edit';
        if (els.modalFooterAdd) {
            els.modalFooterAdd.classList.toggle('d-none', isEdit);
        }
        if (els.modalFooterEdit) {
            els.modalFooterEdit.classList.toggle('d-none', !isEdit);
        }
    }

    function populateModalFromService(service, preselectOptionId) {
        if (!els.modalTitle || !els.modalMeta) {
            return;
        }
        clearModalServiceOptions();
        els.modalTitle.textContent = service.name || '';

        var opts = getNormalizedServiceOptions(service);
        if (opts.length) {
            service.options = opts;
        }

        if (opts.length > 0) {
            var sum = modalSummaryFromOptions(service);
            var dur = service.summaryDurationLabel || sum.duration;
            var price = service.summaryPriceLabel || sum.price;
            els.modalMeta.textContent = joinPriceAndDuration(price, dur);
            if (els.modalOptionsWrap) {
                els.modalOptionsWrap.classList.remove('d-none');
            }
            if (els.modalOptions) {
                opts.forEach(function (opt) {
                    var label = document.createElement('label');
                    label.className = 'booking-modal-option';
                    var main = document.createElement('div');
                    main.className = 'booking-modal-option__main';
                    var nameEl = document.createElement('span');
                    nameEl.className = 'booking-modal-option__name';
                    nameEl.textContent = opt.name;
                    var metaEl = document.createElement('span');
                    metaEl.className = 'booking-modal-option__meta';
                    metaEl.textContent = joinPriceAndDuration(
                        opt.priceFormatted != null ? opt.priceFormatted : formatMoneyEUR(opt.price),
                        opt.duration
                    );
                    main.appendChild(nameEl);
                    main.appendChild(metaEl);
                    var radWrap = document.createElement('div');
                    radWrap.className = 'booking-modal-option__radio';
                    var input = document.createElement('input');
                    input.type = 'radio';
                    input.name = 'booking-service-option';
                    input.value = String(opt.id);
                    input.setAttribute('aria-label', opt.name);
                    if (preselectOptionId != null && String(preselectOptionId) === String(opt.id)) {
                        input.checked = true;
                    }
                    input.addEventListener('change', function () {
                        syncPendingOptionFromDom();
                        clearModalOptionsError();
                    });
                    radWrap.appendChild(input);
                    label.appendChild(main);
                    label.appendChild(radWrap);
                    els.modalOptions.appendChild(label);
                });
            }
            syncPendingOptionFromDom();
        } else {
            els.modalMeta.textContent = joinPriceAndDuration(
                service.priceFormatted || formatMoneyEUR(service.price),
                service.duration
            );
        }

        setModalConfirmEnabled(true);
    }

    function mergeServiceSnapshotFromOptionsCatalog(svc, line) {
        if (getNormalizedServiceOptions(svc).length > 0) {
            return svc;
        }
        if (!line || line.id == null) {
            return svc;
        }
        var el = document.getElementById('booking-services-options-catalog');
        if (!el || !el.textContent) {
            return svc;
        }
        try {
            var catalog = JSON.parse(el.textContent);
            var entry = catalog[String(line.id)] != null ? catalog[String(line.id)] : catalog[line.id];
            if (!entry) {
                return svc;
            }
            var merged = Object.assign({}, svc, {
                name: entry.name || svc.name,
                summaryPriceLabel: entry.summaryPriceLabel,
                summaryDurationLabel: entry.summaryDurationLabel,
            });
            merged.options = getNormalizedServiceOptions(entry);
            if (merged.options.length) {
                return merged;
            }
        } catch (e2) {
            /* ignore */
        }
        return svc;
    }

    function openModal(service) {
        if (!els.modalTitle || !els.modalMeta) {
            return;
        }
        state.pending = {
            mode: 'add',
            lineId: null,
            service: service,
            selectedOption: null,
        };
        setModalFooterMode('add');
        populateModalFromService(service, null);
        var m = getModalInstance();
        if (m) {
            m.show();
        }
    }

    function openEditModal(line) {
        if (!els.modalTitle || !els.modalMeta) {
            return;
        }
        var raw = line.editSnapshot || buildFallbackEditSnapshot(line);
        var svc;
        try {
            svc = JSON.parse(JSON.stringify(raw));
        } catch (e) {
            svc = raw;
        }
        svc = mergeServiceSnapshotFromOptionsCatalog(svc, line);
        svc.options = getNormalizedServiceOptions(svc);
        state.pending = {
            mode: 'edit',
            lineId: line.lineId,
            service: svc,
            selectedOption: null,
        };
        setModalFooterMode('edit');
        var pre = line.service_option_id != null ? line.service_option_id : null;
        populateModalFromService(svc, pre);
        syncPendingOptionFromDom();
        clearModalOptionsError();
        var m = getModalInstance();
        if (m) {
            m.show();
        }
    }

    function confirmApplyEdit() {
        if (!state.pending || state.pending.mode !== 'edit' || !state.pending.service) {
            return;
        }
        var svc = state.pending.service;
        var lineId = state.pending.lineId;
        syncPendingOptionFromDom();
        var opt = state.pending.selectedOption;
        var editOpts = getNormalizedServiceOptions(svc);
        svc.options = editOpts;
        if (editOpts.length) {
            if (!opt) {
                showModalOptionsError('Seleciona uma opção para continuar.');
                return;
            }
            var line = state.items.find(function (l) {
                return l.lineId === lineId;
            });
            if (!line) {
                closeModal();
                return;
            }
            line.name = opt.name;
            line.duration = opt.duration;
            line.durationMinutes = Number(opt.durationMinutes) || 0;
            line.price = opt.price;
            line.priceFormatted = opt.priceFormatted;
            line.service_option_id = opt.id;
            line.editSnapshot = cloneServiceForEditStorage(svc);
            persist();
            renderSummary();
            if (bookingRefreshDateTimeSlotsFn) {
                bookingRefreshDateTimeSlotsFn();
            }
            closeModal();
            return;
        }
        closeModal();
    }

    function removeFromEditModal() {
        if (!state.pending || state.pending.mode !== 'edit') {
            return;
        }
        var lineId = state.pending.lineId;
        removeLine(lineId);
        closeModal();
    }

    function closeModal() {
        var m = getModalInstance();
        if (m) {
            m.hide();
        }
        state.pending = null;
        clearModalServiceOptions();
    }

    function confirmAdd() {
        if (!state.pending || state.pending.mode !== 'add' || !state.pending.service) {
            return;
        }
        var svc = state.pending.service;
        syncPendingOptionFromDom();
        var opt = state.pending.selectedOption;
        if (getNormalizedServiceOptions(svc).length) {
            if (!opt) {
                showModalOptionsError('Seleciona uma opção para continuar.');
                return;
            }
        }
        if (isServiceParentInCart(svc.id)) {
            showModalOptionsError(
                'Este serviço já está na marcação. Usa Editar no resumo para mudar a opção.'
            );
            return;
        }
        var line = {
            lineId: generateLineId(),
            id: svc.id,
            name: opt ? opt.name : svc.name,
            duration: opt ? opt.duration : svc.duration,
            durationMinutes: opt
                ? Number(opt.durationMinutes) || 0
                : Number(svc.durationMinutes) || 0,
            price: opt ? opt.price : svc.price,
            priceFormatted: opt ? opt.priceFormatted : svc.priceFormatted,
        };
        if (opt) {
            line.service_option_id = opt.id;
        }
        line.editSnapshot = cloneServiceForEditStorage(svc);
        state.items.push(line);
        persist();
        renderSummary();
        closeModal();
    }

    function bindServiceButtons() {
        var buttons = document.querySelectorAll('.booking-row--btn[data-service]');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var payload = parseServicePayload(btn.getAttribute('data-service'));
                if (!payload) {
                    return;
                }
                if (isServiceParentInCart(payload.id)) {
                    var existing = state.items.find(function (l) {
                        return l && l.id != null && String(l.id) === String(payload.id);
                    });
                    if (existing) {
                        openEditModal(existing);
                    }
                    return;
                }
                openModal(payload);
            });
        });
    }

    function bindModal() {
        if (els.modalConfirm) {
            els.modalConfirm.addEventListener('click', confirmAdd);
        }
        if (els.modalApplyEdit) {
            els.modalApplyEdit.addEventListener('click', confirmApplyEdit);
        }
        if (els.modalRemoveLine) {
            els.modalRemoveLine.addEventListener('click', removeFromEditModal);
        }
        if (els.modal) {
            els.modal.addEventListener('show.bs.modal', function () {
                document.body.classList.add('booking-modal-open');
                lockSummaryPanelDuringModal();
            });
            els.modal.addEventListener('hidden.bs.modal', function () {
                state.pending = null;
                clearModalServiceOptions();
                setModalFooterMode('add');
                if (els.modalTitle) {
                    els.modalTitle.textContent = '';
                }
                if (els.modalMeta) {
                    els.modalMeta.textContent = '';
                }
                clearModalOptionsError();
                document.body.classList.remove('booking-modal-open');
                /* Garante que o Bootstrap não deixa estilos inline que possam afetar sticky */
                document.body.style.removeProperty('padding-right');
                document.body.style.removeProperty('overflow');
                unlockSummaryPanelAfterModal();
            });
            els.modal.addEventListener('shown.bs.modal', function () {
                if (state.pending && state.pending.mode === 'edit' && els.modalApplyEdit) {
                    els.modalApplyEdit.focus();
                } else if (els.modalConfirm) {
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
        var lockedEmail = '';
        if (emailInput && emailInput.readOnly) {
            lockedEmail = String(emailInput.value || '').trim().toLowerCase();
        }
        if (lockedEmail && existing && existing.email) {
            var existingEmail = String(existing.email || '').trim().toLowerCase();
            if (existingEmail && existingEmail !== lockedEmail) {
                existing = null;
                saveContactSelection({
                    name: nameInput ? nameInput.value.trim() : '',
                    phone: phoneE164Input ? phoneE164Input.value.trim() : '',
                    phoneDisplay: phoneInput ? phoneInput.value.trim() : '',
                    email: lockedEmail,
                    notes: notesInput ? notesInput.value.trim() : '',
                });
            }
        }
        if (existing) {
            if (nameInput) nameInput.value = existing.name || '';
            if (phoneInput) phoneInput.value = existing.phoneDisplay || existing.phone || '';
            if (phoneE164Input) phoneE164Input.value = existing.phone || '';
            if (emailInput && !emailInput.readOnly) emailInput.value = existing.email || '';
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

    function setCheckoutNextBtnLoading(isLoading, label) {
        if (!els.nextBtn) {
            return;
        }
        if (isLoading) {
            els.nextBtn.disabled = true;
            els.nextBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            els.nextBtn.setAttribute('aria-label', label || 'A processar...');
            return;
        }
        els.nextBtn.disabled = false;
        els.nextBtn.removeAttribute('aria-label');
        if (label) {
            els.nextBtn.textContent = label;
        }
    }

    function setCheckoutNextBtnColor(isFinalPayment) {
        if (!els.nextBtn) {
            return;
        }
        els.nextBtn.classList.remove('btn-dark', 'btn-success');
        els.nextBtn.classList.add(isFinalPayment ? 'btn-success' : 'btn-dark');
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
    }

    function setDateTimeSlotsNotice(kind, message) {
        var el = document.getElementById('booking-slots-status');
        if (!el) {
            return;
        }
        el.textContent = message || '';
        var extra = kind === 'error' ? ' text-danger' : ' text-muted';
        el.className = 'booking-slots__status small mb-3' + (message ? extra : '');
    }

    function isCheckoutPaymentRequired() {
        var app = document.querySelector('.booking-app[data-booking-payment-required]');
        if (!app) {
            return true;
        }
        return app.getAttribute('data-booking-payment-required') !== '0';
    }

    function getBookingConfirmWithoutPaymentUrl() {
        var app = document.querySelector('.booking-app[data-booking-confirm-without-payment-url]');
        if (!app) {
            return '';
        }
        return app.getAttribute('data-booking-confirm-without-payment-url') || '';
    }

    function getBookingPaymentUrls() {
        var app = document.querySelector('.booking-app[data-booking-payment-intent-url]');
        if (!app) {
            return null;
        }
        return {
            intentUrl: app.getAttribute('data-booking-payment-intent-url') || '',
            completeUrl: app.getAttribute('data-booking-payment-complete-url') || '',
        };
    }

    function getSlotHoldUrls() {
        var body = document.body;
        if (!body) {
            return null;
        }
        return {
            acquireUrl: body.getAttribute('data-booking-slot-hold-acquire-url') || '',
            extendUrl: body.getAttribute('data-booking-slot-hold-extend-url') || '',
            releaseUrl: body.getAttribute('data-booking-slot-hold-release-url') || '',
        };
    }

    function postJsonWithCsrf(url, payload) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload || {}),
        }).then(function (r) {
            return r
                .json()
                .catch(function () {
                    return {};
                })
                .then(function (data) {
                    if (!r.ok) {
                        var msg = data && data.message ? data.message : 'Não foi possível processar o pedido.';
                        if (data && data.errors) {
                            var firstKey = Object.keys(data.errors)[0];
                            if (firstKey && Array.isArray(data.errors[firstKey]) && data.errors[firstKey][0]) {
                                msg = data.errors[firstKey][0];
                            }
                        }
                        throw new Error(msg);
                    }
                    return data || {};
                });
        });
    }

    function setStripeInlineError(message) {
        var el = document.getElementById('booking-stripe-error');
        if (!el) {
            return;
        }
        el.textContent = message || '';
        el.classList.toggle('d-none', !message);
    }

    function resetCheckoutPaymentUi() {
        checkoutPaymentState.clientSecret = null;
        checkoutPaymentState.publishableKey = null;
        checkoutPaymentState.bookingPublicId = null;
        checkoutPaymentState.stripe = null;
        checkoutPaymentState.elements = null;
        var mount = document.getElementById('booking-stripe-mount');
        if (mount) {
            mount.innerHTML = '';
        }
        var panel = document.getElementById('booking-payment-panel');
        if (panel) {
            panel.classList.add('d-none');
        }
        setStripeInlineError('');
        if (els.nextBtn) {
            setCheckoutNextBtnColor(false);
            els.nextBtn.textContent = isCheckoutPaymentRequired() ? 'Pagamento' : 'Marcar';
        }
        updateCheckoutPaymentPreview();
    }

    function formatHoldRemainingLabel(ms) {
        var total = Math.max(0, Math.ceil(ms / 1000));
        var mm = Math.floor(total / 60);
        var ss = total % 60;
        return mm + ':' + String(ss).padStart(2, '0');
    }

    function getCurrentSelectionForHold() {
        var dt = getDateTimeSelection();
        var tech = getTechnicianSelection();
        if (!dt || !dt.date || !dt.time || !tech || !tech.id || !state.items.length) {
            return null;
        }
        var services = buildServicesForSlotHoldPayload();
        if (!services.length) {
            return null;
        }
        return {
            date: dt.date,
            time: dt.time,
            agent_id: String(tech.id),
            services: services,
            servicesSignature: computeServicesSignature(services),
        };
    }

    function renderSlotHoldBanner() {
        if (!els.summarySlotHold || !els.summarySlotHoldTime) {
            return;
        }
        if (slotHoldShowZeroDuringExpiredModal) {
            els.summarySlotHoldTime.textContent = '0:00';
            els.summarySlotHold.classList.remove('is-hidden');
            return;
        }
        if (!slotHoldState.expiresAt || !slotHoldState.holdPublicId) {
            els.summarySlotHold.classList.add('is-hidden');
            return;
        }
        var remaining = new Date(slotHoldState.expiresAt).getTime() - Date.now();
        if (!Number.isFinite(remaining) || remaining <= 0) {
            els.summarySlotHoldTime.textContent = '0:00';
            els.summarySlotHold.classList.remove('is-hidden');
            return;
        }
        els.summarySlotHoldTime.textContent = formatHoldRemainingLabel(remaining);
        els.summarySlotHold.classList.remove('is-hidden');
    }

    function stopSlotHoldTimer() {
        if (slotHoldTimer.intervalId) {
            clearInterval(slotHoldTimer.intervalId);
            slotHoldTimer.intervalId = null;
        }
    }

    function releaseSlotHold(reason) {
        var holdId = slotHoldState.holdPublicId;
        var token = ensureSlotHoldSessionToken();
        slotHoldShowZeroDuringExpiredModal = false;
        if (!holdId) {
            return Promise.resolve();
        }
        var urls = getSlotHoldUrls();
        clearSlotHoldState();
        stopSlotHoldTimer();
        renderSlotHoldBanner();
        if (!urls || !urls.releaseUrl) {
            return Promise.resolve();
        }
        return postJsonWithCsrf(urls.releaseUrl, {
            hold_public_id: holdId,
            hold_session_token: token,
            reason: reason || 'manual',
        }).catch(function () {
            return null;
        });
    }

    function showSlotHoldExpiredModal() {
        if (slotHoldTimer.expiredModalShown) {
            return;
        }
        if (!els.slotHoldExpiredModal || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }
        var authModalEl = document.getElementById('booking-auth-modal');
        if (authModalEl && window.bootstrap && window.bootstrap.Modal) {
            var authModal = window.bootstrap.Modal.getInstance(authModalEl);
            if (authModalEl.classList.contains('show') && authModal) {
                authModal.hide();
            }
        }
        slotHoldTimer.expiredModalShown = true;
        var modal = window.bootstrap.Modal.getOrCreateInstance(els.slotHoldExpiredModal);
        modal.show();
    }

    function onSlotHoldExpired() {
        stopSlotHoldTimer();
        slotHoldShowZeroDuringExpiredModal = true;
        if (slotHoldState.expiresAt) {
            slotHoldState.expiresAt = new Date().toISOString();
            persistSlotHoldState();
        }
        renderSlotHoldBanner();
        suppressSlotHoldUiErrors = false;
        showSlotHoldExpiredModal();
        renderSummary();
    }

    function startSlotHoldTimer() {
        stopSlotHoldTimer();
        slotHoldTimer.expiredModalShown = false;
        slotHoldShowZeroDuringExpiredModal = false;
        renderSlotHoldBanner();
        if (!slotHoldState.expiresAt || !slotHoldState.holdPublicId) {
            return;
        }
        slotHoldTimer.intervalId = setInterval(function () {
            renderSlotHoldBanner();
            var remaining = new Date(slotHoldState.expiresAt).getTime() - Date.now();
            if (!Number.isFinite(remaining) || remaining <= 0) {
                onSlotHoldExpired();
            }
        }, 1000);
    }

    function ensureSlotHoldForCurrentSelection() {
        if (slotHoldShowZeroDuringExpiredModal) {
            return Promise.resolve();
        }
        var selected = getCurrentSelectionForHold();
        if (!selected) {
            return releaseSlotHold('selection_cleared');
        }
        var selectionKey =
            String(selected.date) +
            '|' +
            String(selected.time) +
            '|' +
            String(selected.agent_id) +
            '|' +
            String(selected.servicesSignature);

        var sameSelection =
            slotHoldState.holdPublicId &&
            slotHoldState.date === selected.date &&
            slotHoldState.time === selected.time &&
            slotHoldState.agentId === selected.agent_id &&
            slotHoldState.servicesSignature === selected.servicesSignature &&
            new Date(slotHoldState.expiresAt).getTime() > Date.now() + 15000;
        if (sameSelection) {
            renderSlotHoldBanner();
            return Promise.resolve();
        }

        if (slotHoldAcquirePromise) {
            if (slotHoldAcquireKey === selectionKey) {
                return slotHoldAcquirePromise;
            }
            return slotHoldAcquirePromise.finally(function () {
                return ensureSlotHoldForCurrentSelection();
            });
        }

        var urls = getSlotHoldUrls();
        if (!urls || !urls.acquireUrl) {
            return Promise.resolve();
        }
        var token = ensureSlotHoldSessionToken();
        slotHoldAcquireKey = selectionKey;
        slotHoldAcquirePromise = postJsonWithCsrf(urls.acquireUrl, {
            date: selected.date,
            time: selected.time,
            agent_id: selected.agent_id,
            services: selected.services,
            hold_session_token: token,
        }).then(function (res) {
            slotHoldState.holdPublicId = res.hold_public_id || '';
            slotHoldState.expiresAt = res.expires_at || '';
            slotHoldState.date = selected.date;
            slotHoldState.time = selected.time;
            slotHoldState.agentId = selected.agent_id;
            slotHoldState.servicesSignature = selected.servicesSignature;
            persistSlotHoldState();
            startSlotHoldTimer();
            renderSummary();
        }).catch(function (err) {
            releaseSlotHold('acquire_failed');
            var conflictMsg =
                err && err.message
                    ? err.message
                    : 'Esta hora já está reservada, por favor seleccione outra hora.';
            if (!suppressSlotHoldUiErrors) {
                setDateTimeSlotsNotice('error', conflictMsg);
            }
            saveDateTimeSelection({ date: selected.date, time: '' });
            if (syncBookingDateTimeSlotFromStorage) {
                syncBookingDateTimeSlotFromStorage();
            }
            renderSummary();
            if (bookingRefreshDateTimeSlotsFn) {
                pendingDateTimeSlotsErrorNotice = conflictMsg;
                bookingRefreshDateTimeSlotsFn();
            }
        }).finally(function () {
            slotHoldAcquirePromise = null;
            slotHoldAcquireKey = '';
        });
        return slotHoldAcquirePromise;
    }

    function bindSlotHoldExpiredModal() {
        if (!els.slotHoldExpiredModal || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }
        var modal = window.bootstrap.Modal.getOrCreateInstance(els.slotHoldExpiredModal);
        if (els.slotHoldRestartBtn) {
            els.slotHoldRestartBtn.addEventListener('click', function () {
                suppressSlotHoldUiErrors = true;
                slotHoldShowZeroDuringExpiredModal = false;
                releaseSlotHold('expired_restart').finally(function () {
                    clearTechnicianSelection();
                    saveDateTimeSelection({ date: '', time: '' });
                    modal.hide();
                    window.location.href = document.body.getAttribute('data-booking-index-url') || '/booking';
                });
            });
        }
        if (els.slotHoldExtendBtn) {
            els.slotHoldExtendBtn.addEventListener('click', function () {
                var urls = getSlotHoldUrls();
                var holdId = slotHoldState.holdPublicId;
                var token = ensureSlotHoldSessionToken();
                if (!urls || !urls.extendUrl || !holdId) {
                    return;
                }
                els.slotHoldExtendBtn.disabled = true;
                postJsonWithCsrf(urls.extendUrl, {
                    hold_public_id: holdId,
                    hold_session_token: token,
                }).then(function (res) {
                    slotHoldShowZeroDuringExpiredModal = false;
                    slotHoldState.expiresAt = res.expires_at || slotHoldState.expiresAt;
                    persistSlotHoldState();
                    startSlotHoldTimer();
                    modal.hide();
                }).catch(function (err) {
                    var msg = err && err.message ? err.message : 'Não foi possível prolongar a reserva.';
                    var slotTakenByOther =
                        msg.indexOf('Este horário acabou de ser reservado por outro cliente') !== -1;
                    if (slotTakenByOther) {
                        var savedDate = slotHoldState.date || '';
                        slotHoldShowZeroDuringExpiredModal = false;
                        clearSlotHoldState();
                        stopSlotHoldTimer();
                        renderSlotHoldBanner();
                        modal.hide();
                        setCheckoutError('');
                        setDateTimeSlotsNotice('error', msg);
                        pendingDateTimeSlotsErrorNotice = msg;
                        saveDateTimeSelection({ date: savedDate, time: '' });
                        if (syncBookingDateTimeSlotFromStorage) {
                            syncBookingDateTimeSlotFromStorage();
                        }
                        renderSummary();
                        if (bookingRefreshDateTimeSlotsFn) {
                            bookingRefreshDateTimeSlotsFn();
                        }
                    } else {
                        setCheckoutError(msg);
                    }
                }).finally(function () {
                    els.slotHoldExtendBtn.disabled = false;
                });
            });
        }
    }

    function mountStripePaymentElement() {
        if (!checkoutPaymentState.clientSecret || !checkoutPaymentState.publishableKey) {
            return;
        }
        var mount = document.getElementById('booking-stripe-mount');
        if (!mount) {
            return;
        }

        function tryMountOnce() {
            if (typeof window.Stripe !== 'function') {
                return false;
            }
            try {
                mount.innerHTML = '';
                checkoutPaymentState.elements = null;
                if (!checkoutPaymentState.stripe) {
                    checkoutPaymentState.stripe = window.Stripe(checkoutPaymentState.publishableKey, {
                        locale: 'pt',
                    });
                }
                var elements = checkoutPaymentState.stripe.elements({
                    clientSecret: checkoutPaymentState.clientSecret,
                    appearance: { theme: 'stripe' },
                    loader: 'auto',
                });
                checkoutPaymentState.elements = elements;
                var paymentEl = elements.create('payment');
                paymentEl.mount(mount);
                paymentEl.on('ready', function () {
                    setStripeInlineError('');
                });
                return true;
            } catch (e) {
                var errMsg = e && e.message ? e.message : 'Erro ao inicializar o Stripe.';
                setStripeInlineError(errMsg);
                return true;
            }
        }

        if (tryMountOnce()) {
            return;
        }

        var attempts = 0;
        var iv = setInterval(function () {
            attempts += 1;
            if (tryMountOnce() || attempts >= 80) {
                clearInterval(iv);
                if (typeof window.Stripe !== 'function') {
                    setStripeInlineError(
                        'O script do Stripe ainda não está disponível. Verifica a rede, desactiva bloqueadores de anúncios e recarrega a página.'
                    );
                }
            }
        }, 50);
    }

    function confirmStripePayment() {
        if (!checkoutPaymentState.stripe || !checkoutPaymentState.elements) {
            setStripeInlineError('Inicia o pagamento novamente.');
            return;
        }
        setCheckoutNextBtnLoading(true, 'A confirmar pagamento...');
        setStripeInlineError('');
        var contact = getCheckoutContactPayload() || {};
        var baseUrl = window.location.href.split('#')[0].split('?')[0];
        checkoutPaymentState.stripe
            .confirmPayment({
                elements: checkoutPaymentState.elements,
                confirmParams: {
                    return_url: baseUrl,
                    payment_method_data: {
                        billing_details: {
                            name: contact.name || undefined,
                            email: contact.email || undefined,
                            phone: contact.phone || undefined,
                        },
                    },
                },
                redirect: 'if_required',
            })
            .then(function (result) {
                if (result.error) {
                    setStripeInlineError(result.error.message || 'Pagamento recusado.');
                    setCheckoutNextBtnLoading(false, 'Pagar e confirmar');
                    return;
                }
                var pi = result.paymentIntent;
                if (pi && pi.status === 'succeeded' && checkoutPaymentState.bookingPublicId) {
                    finalizeBookingAfterPayment(checkoutPaymentState.bookingPublicId, pi.id);
                } else {
                    setCheckoutNextBtnLoading(false, 'Pagar e confirmar');
                }
            })
            .catch(function () {
                setStripeInlineError('Erro ao confirmar o pagamento. Tenta novamente.');
                setCheckoutNextBtnLoading(false, 'Pagar e confirmar');
            });
    }

    function finalizeBookingAfterPayment(bookingPublicId, paymentIntentId) {
        var urls = getBookingPaymentUrls();
        if (!urls || !urls.completeUrl) {
            setCheckoutError('Serviço de marcação indisponível.');
            setCheckoutNextBtnLoading(false, 'Pagar e confirmar');
            return;
        }
        fetch(urls.completeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                booking_public_id: bookingPublicId,
                payment_intent_id: paymentIntentId || null,
            }),
        })
            .then(function (r) {
                return r
                    .json()
                    .catch(function () {
                        return {};
                    })
                    .then(function (data) {
                        return { ok: r.ok, status: r.status, data: data };
                    });
            })
            .then(function (res) {
                if (res.ok && res.data && res.data.redirect) {
                    try {
                        bookingStorage.removeItem(STORAGE_KEY);
                        bookingStorage.removeItem(TECH_STORAGE_KEY);
                        bookingStorage.removeItem(DATETIME_STORAGE_KEY);
                        bookingStorage.removeItem(CONTACT_STORAGE_KEY);
                        bookingStorage.removeItem(STRIPE_CHECKOUT_PUBLIC_ID_KEY);
                        bookingStorage.removeItem(SLOT_HOLD_STORAGE_KEY);
                        if (window.location.search.indexOf('payment_intent=') !== -1) {
                            window.history.replaceState({}, '', window.location.pathname);
                        }
                    } catch (e) {
                        /* ignore */
                    }
                    stopSlotHoldTimer();
                    clearSlotHoldState();
                    resetCheckoutPaymentUi();
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
                setCheckoutError(msg);
                setCheckoutNextBtnLoading(false, 'Pagar e confirmar');
                renderSummary();
            })
            .catch(function () {
                setCheckoutError('Erro de rede. Tenta novamente.');
                setCheckoutNextBtnLoading(false, 'Pagar e confirmar');
                renderSummary();
            });
    }

    function tryResumeStripeRedirectOnCheckout() {
        if (!isCheckoutPaymentRequired()) {
            return;
        }
        var urls = getBookingPaymentUrls();
        if (!urls || !urls.completeUrl) {
            return;
        }
        var params = new URLSearchParams(window.location.search);
        var pi = params.get('payment_intent');
        var status = params.get('redirect_status');
        var storedId = null;
        try {
            storedId = bookingStorage.getItem(STRIPE_CHECKOUT_PUBLIC_ID_KEY);
        } catch (e) {
            /* ignore */
        }
        if (!pi || status !== 'succeeded' || !storedId) {
            return;
        }
        setCheckoutNextBtnLoading(true, 'A preparar pagamento...');
        finalizeBookingAfterPayment(storedId, pi);
    }

    function handleConfirmWithoutPaymentResponse(res) {
        if (res.ok && res.data && res.data.redirect) {
            try {
                bookingStorage.removeItem(STORAGE_KEY);
                bookingStorage.removeItem(TECH_STORAGE_KEY);
                bookingStorage.removeItem(DATETIME_STORAGE_KEY);
                bookingStorage.removeItem(CONTACT_STORAGE_KEY);
                bookingStorage.removeItem(STRIPE_CHECKOUT_PUBLIC_ID_KEY);
                bookingStorage.removeItem(SLOT_HOLD_STORAGE_KEY);
            } catch (e) {
                /* ignore */
            }
            stopSlotHoldTimer();
            clearSlotHoldState();
            resetCheckoutPaymentUi();
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
        setCheckoutError(msg);
        var btnFinal = isCheckoutPaymentRequired() ? 'Pagar e confirmar' : 'Marcar';
        setCheckoutNextBtnLoading(false, btnFinal);
        renderSummary();
    }

    function submitBookingConfirmWithoutPayment(postUrl, payload) {
        if (els.nextBtn) {
            els.nextBtn.disabled = true;
        }
        fetch(postUrl, {
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
                return r
                    .json()
                    .catch(function () {
                        return {};
                    })
                    .then(function (data) {
                        return { ok: r.ok, status: r.status, data: data };
                    });
            })
            .then(function (res) {
                handleConfirmWithoutPaymentResponse(res);
            })
            .catch(function () {
                setCheckoutError('Erro de rede. Tenta novamente.');
                setCheckoutNextBtnLoading(false, 'Marcar');
                renderSummary();
            });
    }

    function submitBookingCheckout() {
        var urls = getBookingPaymentUrls();
        var noPayUrl = getBookingConfirmWithoutPaymentUrl();
        if (isCheckoutPaymentRequired()) {
            if (!urls || !urls.intentUrl || !urls.completeUrl) {
                setCheckoutError('Serviço de marcação indisponível.');
                return;
            }
        } else if (!noPayUrl) {
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
        if (!slotHoldState.holdPublicId || !slotHoldState.expiresAt || new Date(slotHoldState.expiresAt).getTime() <= Date.now()) {
            setCheckoutError('A reserva temporária expirou. Escolhe novamente data e hora.');
            onSlotHoldExpired();
            return;
        }
        hideCheckoutError();
        setStripeInlineError('');
        var payload = {
            name: contact.name,
            email: contact.email,
            phone: contact.phone,
            notes: contact.notes || '',
            date: dt.date,
            time: dt.time,
            agent_id: String(tech.id),
            services: state.items.map(function (line) {
                var row = { id: line.id };
                if (line.service_option_id != null && line.service_option_id !== '') {
                    row.service_option_id = Number(line.service_option_id);
                }
                return row;
            }),
        };
        if (slotHoldState.holdPublicId) {
            payload.slot_hold_public_id = slotHoldState.holdPublicId;
        }
        if (slotHoldState.sessionToken) {
            payload.slot_hold_token = slotHoldState.sessionToken;
        }

        if (!isCheckoutPaymentRequired()) {
            setCheckoutNextBtnLoading(true, 'A confirmar marcação...');
            submitBookingConfirmWithoutPayment(noPayUrl, payload);
            return;
        }

        if (checkoutPaymentState.clientSecret) {
            confirmStripePayment();
            return;
        }

        if (els.nextBtn) {
            els.nextBtn.disabled = true;
        }
        fetch(urls.intentUrl, {
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
                return r
                    .json()
                    .catch(function () {
                        return {};
                    })
                    .then(function (data) {
                        return { ok: r.ok, status: r.status, data: data };
                    });
            })
            .then(function (res) {
                if (!res.ok || !res.data || !res.data.client_secret) {
                    var msg = 'Não foi possível iniciar o pagamento.';
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
                    setCheckoutError(msg);
                    setCheckoutNextBtnColor(false);
                    setCheckoutNextBtnLoading(false, 'Pagamento');
                    renderSummary();
                    return;
                }
                var d = res.data;
                checkoutPaymentState.clientSecret = d.client_secret;
                checkoutPaymentState.publishableKey = d.publishable_key;
                checkoutPaymentState.bookingPublicId = d.booking_public_id;
                try {
                    bookingStorage.setItem(STRIPE_CHECKOUT_PUBLIC_ID_KEY, d.booking_public_id);
                } catch (e) {
                    /* ignore */
                }
                var depEl = document.getElementById('booking-pay-deposit-amount');
                var pctEl = document.getElementById('booking-pay-deposit-pct');
                var remEl = document.getElementById('booking-pay-remaining-amount');
                if (depEl && d.paid_amount != null) {
                    depEl.textContent = formatMoneyEUR(Number(d.paid_amount));
                }
                if (pctEl && d.deposit_percent != null) {
                    pctEl.textContent = String(d.deposit_percent);
                }
                if (remEl && d.remaining_amount != null) {
                    remEl.textContent = formatMoneyEUR(Number(d.remaining_amount));
                }
                var panel = document.getElementById('booking-payment-panel');
                if (panel) {
                    panel.classList.remove('d-none');
                }
                mountStripePaymentElement();
                updateCheckoutPaymentPreview();
                if (els.nextBtn) {
                    setCheckoutNextBtnColor(true);
                    setCheckoutNextBtnLoading(false, 'Pagar e confirmar');
                }
                renderSummary();
            })
            .catch(function () {
                setCheckoutError('Erro de rede. Tenta novamente.');
                setCheckoutNextBtnColor(false);
                setCheckoutNextBtnLoading(false, 'Pagamento');
                renderSummary();
            });
    }

    function syncSummaryScrollPlacement() {
        var scroll = document.getElementById('booking-summary-scroll');
        var drawer = document.getElementById('booking-summary-mobile-drawer');
        var cardBody = document.querySelector('.booking-summary-card__body');
        var footer = document.querySelector('.booking-summary-footer');
        if (!scroll || !drawer || !cardBody || !footer) {
            return;
        }
        var mobile = window.matchMedia('(max-width: 991.98px)').matches;
        if (mobile) {
            if (scroll.parentElement !== drawer) {
                drawer.appendChild(scroll);
            }
        } else if (scroll.parentElement === drawer) {
            cardBody.insertBefore(scroll, footer);
        }
    }

    function closeSummaryDrawer() {
        var footer = document.querySelector('.booking-summary-footer');
        var drawer = document.getElementById('booking-summary-mobile-drawer');
        var btn = document.getElementById('booking-summary-total');
        if (footer) {
            footer.classList.remove('is-drawer-open');
        }
        if (drawer) {
            drawer.setAttribute('aria-hidden', 'true');
        }
        if (btn) {
            btn.setAttribute('aria-expanded', 'false');
        }
    }

    function bindSummaryDrawerToggle() {
        var btn = document.getElementById('booking-summary-total');
        var footer = document.querySelector('.booking-summary-footer');
        var drawer = document.getElementById('booking-summary-mobile-drawer');
        if (!btn || !footer || !drawer) {
            return;
        }
        function toggleDrawer() {
            if (!window.matchMedia('(max-width: 991.98px)').matches) {
                return;
            }
            var open = footer.classList.toggle('is-drawer-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        }
        btn.addEventListener('click', toggleDrawer);
        btn.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                toggleDrawer();
            }
        });
    }

    /**
     * Barra fixa do resumo (mobile): manter sempre colada ao fundo do ecrã.
     */
    function syncBookingSummaryFooterVisualBottom() {
        var footer = document.querySelector('.booking-summary-footer');
        if (!footer) {
            return;
        }
        if (!window.matchMedia('(max-width: 991.98px)').matches) {
            footer.style.removeProperty('bottom');
            return;
        }
        footer.style.bottom = '0px';
    }

    var bookingFooterVvRaf = 0;
    function scheduleBookingSummaryFooterVisualBottom() {
        if (bookingFooterVvRaf) {
            return;
        }
        bookingFooterVvRaf = window.requestAnimationFrame(function () {
            bookingFooterVvRaf = 0;
            syncBookingSummaryFooterVisualBottom();
        });
    }

    function bindBookingSummaryFooterVisualViewport() {
        syncBookingSummaryFooterVisualBottom();
        if (!window.visualViewport) {
            return;
        }
        var vv = window.visualViewport;
        vv.addEventListener('resize', scheduleBookingSummaryFooterVisualBottom);
        vv.addEventListener('scroll', scheduleBookingSummaryFooterVisualBottom);
    }

    function bindSummaryLayoutResize() {
        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                var footer = document.querySelector('.booking-summary-footer');
                var wasDrawerOpen = footer && footer.classList.contains('is-drawer-open');
                syncSummaryScrollPlacement();
                if (wasDrawerOpen && !window.matchMedia('(max-width: 991.98px)').matches) {
                    closeSummaryDrawer();
                }
                syncBookingSummaryFooterVisualBottom();
            }, 120);
        });
    }

    function bindCategoryChipsScroll() {
        var nav = document.querySelector('.booking-category-chips');
        if (!nav) {
            return;
        }
        var scroll = nav.querySelector('.booking-category-chips__scroll');
        if (!scroll) {
            return;
        }
        var arrowLeft = nav.querySelector('.booking-category-chips__arrow--left');
        var arrowRight = nav.querySelector('.booking-category-chips__arrow--right');
        var scrollByArrow = function (direction) {
            var distance = Math.max(140, Math.round(scroll.clientWidth * 0.6));
            scroll.scrollBy({
                left: direction * distance,
                behavior: 'smooth',
            });
            window.setTimeout(updateHints, 240);
        };

        var updateHints = function () {
            var maxScroll = Math.max(0, scroll.scrollWidth - scroll.clientWidth);
            var threshold = 2;
            var hasOverflow = maxScroll > threshold;
            var canScrollLeft = scroll.scrollLeft > threshold;
            var canScrollRight = scroll.scrollLeft < (maxScroll - threshold);

            nav.classList.toggle('has-overflow', hasOverflow);
            nav.classList.toggle('can-scroll-left', hasOverflow && canScrollLeft);
            nav.classList.toggle('can-scroll-right', hasOverflow && canScrollRight);
        };

        scroll.addEventListener('scroll', updateHints, { passive: true });
        window.addEventListener('resize', updateHints);
        window.addEventListener('orientationchange', updateHints);
        window.setTimeout(updateHints, 0);

        nav.addEventListener('click', function (ev) {
            var arrow = ev.target.closest('.booking-category-chips__arrow');
            if (arrow && nav.contains(arrow)) {
                if (arrow.classList.contains('booking-category-chips__arrow--left')) {
                    scrollByArrow(-1);
                } else if (arrow.classList.contains('booking-category-chips__arrow--right')) {
                    scrollByArrow(1);
                }
                return;
            }
            var chip = ev.target.closest('.booking-category-chip');
            if (!chip || !nav.contains(chip)) {
                return;
            }
            var targetId = chip.getAttribute('data-scroll-target');
            if (!targetId) {
                return;
            }
            var section = document.getElementById(targetId);
            if (!section) {
                return;
            }
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            window.setTimeout(updateHints, 220);
        });

        if (arrowLeft) {
            arrowLeft.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    ev.preventDefault();
                    scrollByArrow(-1);
                }
            });
        }
        if (arrowRight) {
            arrowRight.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    ev.preventDefault();
                    scrollByArrow(1);
                }
            });
        }
    }

    function initBookingAuthModal() {
        var authRoot = document.body;
        var modalEl = document.getElementById('booking-auth-modal');
        if (!authRoot || !modalEl || !window.bootstrap || !window.bootstrap.Modal) {
            return null;
        }
        var requestCodeUrl = authRoot.getAttribute('data-booking-auth-request-code-url') || '';
        var verifyCodeUrl = authRoot.getAttribute('data-booking-auth-verify-code-url') || '';
        var isAuthed = authRoot.getAttribute('data-booking-authenticated-client') === '1';
        var reloadAfterAuthSuccess = false;
        if (!requestCodeUrl || !verifyCodeUrl) {
            return null;
        }

        var modal = new window.bootstrap.Modal(modalEl);
        var stepEmail = document.getElementById('booking-auth-step-email');
        var stepCode = document.getElementById('booking-auth-step-code');
        var errorBox = document.getElementById('booking-auth-modal-error');
        var modalBackBtn = document.getElementById('booking-auth-modal-back');
        var modalTitle = document.getElementById('booking-auth-modal-title');
        var modalSubtitle = document.getElementById('booking-auth-modal-subtitle');
        var emailInput = document.getElementById('booking-auth-email');
        var emailNextBtn = document.getElementById('booking-auth-email-next');
        var codeInput = document.getElementById('booking-auth-code');
        var codeSubmitBtn = document.getElementById('booking-auth-code-submit');
        var codeResendBtn = document.getElementById('booking-auth-code-resend');
        var codeStatus = document.getElementById('booking-auth-code-status');

        if (!stepEmail || !stepCode || !errorBox || !modalBackBtn || !modalTitle || !modalSubtitle || !emailInput || !emailNextBtn || !codeInput || !codeSubmitBtn || !codeResendBtn || !codeStatus) {
            return null;
        }

        var currentEmail = '';
        var submittedResolver = null;
        var currentStep = 'email';
        var lastCodeRequestAt = 0;

        function showError(msg) {
            errorBox.textContent = msg || '';
            errorBox.classList.toggle('d-none', !msg);
        }
        function setLoading(btn, loading, labelLoading, labelDefault) {
            if (!btn) return;
            if (loading) {
                btn.disabled = true;
                btn.setAttribute('data-prev-text', btn.textContent || '');
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + (labelLoading || 'A processar...');
            } else {
                btn.disabled = false;
                btn.textContent = labelDefault || btn.getAttribute('data-prev-text') || 'Seguinte';
            }
        }
        function setStep(mode) {
            currentStep = mode;
            stepEmail.classList.toggle('d-none', mode !== 'email');
            stepCode.classList.toggle('d-none', mode !== 'code');
            modalBackBtn.classList.toggle('d-none', mode === 'email');
            errorBox.classList.add('d-none');
            errorBox.textContent = '';
            if (mode === 'email') {
                modalTitle.textContent = 'Entrar sem password';
                modalSubtitle.textContent = 'Recebe um código por email para entrar.';
            } else if (mode === 'code') {
                modalTitle.textContent = 'Introduza o código';
                modalSubtitle.textContent = 'Enviámos um código para ' + currentEmail + '.';
            }
        }
        function postJson(url, payload) {
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload || {}),
            }).then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (data) {
                    if (!r.ok) {
                        var msg = data && data.message ? data.message : 'Não foi possível processar o pedido.';
                        if (data && data.errors) {
                            var firstKey = Object.keys(data.errors)[0];
                            if (firstKey && Array.isArray(data.errors[firstKey]) && data.errors[firstKey][0]) {
                                msg = data.errors[firstKey][0];
                            }
                        }
                        throw new Error(msg);
                    }
                    return data || {};
                });
            });
        }
        function showCodeStatus(message) {
            codeStatus.textContent = message || '';
            codeStatus.classList.toggle('d-none', !message);
        }
        function requestCode(email) {
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showError('Indica um email válido.');
                return Promise.reject(new Error('invalid_email'));
            }
            currentEmail = email;
            setLoading(emailNextBtn, true, 'A enviar...', 'Enviar código');
            setLoading(codeResendBtn, true, 'A enviar...', 'Reenviar código');
            return postJson(requestCodeUrl, { email: currentEmail })
                .then(function (res) {
                    currentEmail = res && res.email ? String(res.email).trim().toLowerCase() : currentEmail;
                    lastCodeRequestAt = Date.now();
                    showCodeStatus('Código enviado. Verifique a sua caixa de email.');
                    setStep('code');
                    codeInput.focus();
                    return res;
                })
                .finally(function () {
                    setLoading(emailNextBtn, false, '', 'Enviar código');
                    setLoading(codeResendBtn, false, '', 'Reenviar código');
                });
        }
        function resetModal() {
            reloadAfterAuthSuccess = false;
            currentEmail = '';
            emailInput.value = '';
            codeInput.value = '';
            showCodeStatus('');
            setStep('email');
        }
        modalBackBtn.addEventListener('click', function () {
            if (currentStep === 'code') {
                setStep('email');
                emailInput.focus();
            }
        });

        emailNextBtn.addEventListener('click', function () {
            var email = String(emailInput.value || '').trim().toLowerCase();
            requestCode(email)
                .catch(function (err) {
                    if (err && err.message && err.message !== 'invalid_email') {
                        showError(err.message);
                    }
                });
        });

        codeSubmitBtn.addEventListener('click', function () {
            var code = String(codeInput.value || '').replace(/\D/g, '');
            if (code.length !== 6) {
                showError('Indique o código de 6 dígitos.');
                return;
            }
            if (!currentEmail) {
                setStep('email');
                return;
            }
            setLoading(codeSubmitBtn, true, 'A validar...', 'Entrar');
            postJson(verifyCodeUrl, { email: currentEmail, code: code })
                .then(function () {
                    var doReload = reloadAfterAuthSuccess;
                    modal.hide();
                    if (submittedResolver) submittedResolver(true);
                    if (doReload) {
                        window.location.reload();
                    }
                })
                .catch(function (err) {
                    showError(err && err.message ? err.message : 'Não foi possível validar o código.');
                })
                .finally(function () {
                    setLoading(codeSubmitBtn, false, '', 'Entrar');
                });
        });

        codeResendBtn.addEventListener('click', function () {
            if (!currentEmail) {
                setStep('email');
                return;
            }
            if (Date.now() - lastCodeRequestAt < 3000) {
                showCodeStatus('Aguarde 3 segundos antes de pedir um novo código.');
                return;
            }
            requestCode(currentEmail)
                .catch(function (err) {
                    if (err && err.message && err.message !== 'invalid_email') {
                        showError(err.message);
                    }
                });
        });

        codeInput.addEventListener('input', function () {
            var clean = String(codeInput.value || '').replace(/\D/g, '');
            if (clean !== codeInput.value) {
                codeInput.value = clean;
            }
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            if (submittedResolver) {
                submittedResolver(false);
                submittedResolver = null;
            }
            resetModal();
        });

        return {
            ensureAuth: function () {
                if (isAuthed) {
                    return Promise.resolve(true);
                }
                resetModal();
                modal.show();
                return new Promise(function (resolve) {
                    submittedResolver = function (ok) {
                        submittedResolver = null;
                        if (ok) {
                            isAuthed = true;
                        }
                        resolve(ok);
                    };
                });
            },
            openLoginModal: function (opts) {
                opts = opts || {};
                if (isAuthed) {
                    return;
                }
                resetModal();
                if (opts.email) {
                    emailInput.value = opts.email;
                }
                reloadAfterAuthSuccess = opts.reloadOnSuccess !== false;
                modal.show();
            },
            isAuthed: function () {
                return isAuthed;
            },
        };
    }

    var bookingAuthModalApiCache;

    function getBookingAuthModal() {
        if (bookingAuthModalApiCache === undefined) {
            bookingAuthModalApiCache = initBookingAuthModal();
        }
        return bookingAuthModalApiCache || null;
    }

    function initBookingNavbarAuth(authApi) {
        if (!authApi || typeof authApi.openLoginModal !== 'function') {
            return;
        }
        document.querySelectorAll('.js-booking-open-auth-modal').forEach(function (btn) {
            btn.addEventListener('click', function () {
                authApi.openLoginModal({});
                var oc = document.getElementById('bookingStoreDetails');
                if (oc && window.bootstrap && window.bootstrap.Offcanvas) {
                    var inst = window.bootstrap.Offcanvas.getInstance(oc);
                    if (inst) {
                        inst.hide();
                    }
                }
            });
        });
    }

    function tryOpenBookingAuthFromUrl(authApi) {
        if (!authApi || typeof authApi.openLoginModal !== 'function' || authApi.isAuthed()) {
            return;
        }
        try {
            var u = new URL(window.location.href);
            if (u.searchParams.get('open_auth') !== '1') {
                return;
            }
            var email = u.searchParams.get('email') || '';
            authApi.openLoginModal({ email: email, reloadOnSuccess: false });
            u.searchParams.delete('open_auth');
            u.searchParams.delete('email');
            var qs = u.searchParams.toString();
            var newUrl = u.pathname + (qs ? '?' + qs : '') + u.hash;
            var cur = window.location.pathname + window.location.search + window.location.hash;
            if (newUrl !== cur) {
                window.history.replaceState({}, '', newUrl);
            }
        } catch (e) {
            /* ignore */
        }
    }

    function bindNext() {
        if (!els.nextBtn) {
            return;
        }
        var authModalFlow = getBookingAuthModal();
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
                flushGuestContactFromDomToStorage();
                persist();
                if (!checkoutPaymentState.clientSecret) {
                    setCheckoutNextBtnLoading(
                        true,
                        isCheckoutPaymentRequired() ? 'A preparar pagamento...' : 'A confirmar marcação...'
                    );
                }
                submitBookingCheckout();
                return;
            }
            var url = els.nextBtn.getAttribute('data-next-url');
            if (requirement === 'datetime' && authModalFlow) {
                authModalFlow.ensureAuth().then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    persist();
                    if (url && url !== '#') {
                        window.location.href = url;
                    }
                });
                return;
            }
            persist();
            if (url && url !== '#') {
                window.location.href = url;
            }
        });
    }

    function init() {
        cacheElements();
        loadSlotHoldState();
        syncSummaryScrollPlacement();
        loadFromStorage();
        renderSummary();
        initTechnicianStep();
        initDateTimeStep();
        initCheckoutStep();
        bindServiceButtons();
        bindModal();
        bindCategoryChipsScroll();
        initBookingNavbarAuth(getBookingAuthModal());
        tryOpenBookingAuthFromUrl(getBookingAuthModal());
        bindSlotHoldExpiredModal();
        startSlotHoldTimer();
        ensureSlotHoldForCurrentSelection();
        bindNext();
        bindSummaryDrawerToggle();
        bindSummaryLayoutResize();
        bindBookingSummaryFooterVisualViewport();
        if (document.body && document.body.classList.contains('booking-page--step3')) {
            tryResumeStripeRedirectOnCheckout();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
