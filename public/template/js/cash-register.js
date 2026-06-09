(function () {
    'use strict';

    var cfg = window.CrmCashRegister || {};
    var openModalEl = document.getElementById('crmCashRegisterOpenModal');
    var closeModalEl = document.getElementById('crmCashRegisterCloseModal');
    if (!openModalEl && !closeModalEl) {
        return;
    }

    var csrf = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrf ? csrf.getAttribute('content') : '';

    function formatMoney(amount) {
        var n = Number(amount);
        if (!isFinite(n)) {
            return '—';
        }
        return n.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }

    function setCashRegisterOpenState(isOpen) {
        cfg.isOpen = !!isOpen;
        document.querySelectorAll('[data-crm-cash-register-trigger]').forEach(function (el) {
            if (el.getAttribute('data-crm-cash-register-fixed') === 'open') {
                return;
            }
            var action = isOpen ? 'close' : 'open';
            el.setAttribute('data-crm-cash-register-trigger', action);
            var openLabel = el.getAttribute('data-crm-cash-register-title-open') || 'Caixa fechada';
            var closeLabel = el.getAttribute('data-crm-cash-register-title-closed') || 'Caixa aberta';
            var title = isOpen ? closeLabel : openLabel;
            el.setAttribute('title', title);
            el.setAttribute('aria-label', title);
            el.classList.toggle('header-cash-register-btn--open', isOpen);
            el.classList.toggle('header-cash-register-btn--closed', !isOpen);
        });
        document.dispatchEvent(new CustomEvent('crm:cash-register-changed', { detail: { isOpen: !!isOpen } }));
    }

    function getOpenModal() {
        return openModalEl && typeof bootstrap !== 'undefined'
            ? bootstrap.Modal.getOrCreateInstance(openModalEl)
            : null;
    }

    function getCloseModal() {
        return closeModalEl && typeof bootstrap !== 'undefined'
            ? bootstrap.Modal.getOrCreateInstance(closeModalEl)
            : null;
    }

    function resetOpenPendingPreview() {
        var wrap = document.getElementById('crmCashRegisterOpenPendingWrap');
        var loading = document.getElementById('crmCashRegisterOpenPendingLoading');
        var alert = document.getElementById('crmCashRegisterOpenPendingAlert');
        if (wrap) wrap.classList.add('d-none');
        if (loading) loading.classList.add('d-none');
        if (alert) alert.textContent = '';
    }

    function loadOpenPendingPreview() {
        if (!cfg.openPreviewUrl) {
            return;
        }
        var wrap = document.getElementById('crmCashRegisterOpenPendingWrap');
        var loading = document.getElementById('crmCashRegisterOpenPendingLoading');
        var alert = document.getElementById('crmCashRegisterOpenPendingAlert');
        resetOpenPendingPreview();
        if (loading) loading.classList.remove('d-none');

        fetch(cfg.openPreviewUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                });
            })
            .then(function (result) {
                if (loading) loading.classList.add('d-none');
                if (!result.ok || !result.data || !result.data.pending_booking) {
                    return;
                }
                var pending = result.data.pending_booking;
                if (!pending || !wrap || !alert) {
                    return;
                }
                var count = Number(pending.count) || 0;
                if (count <= 0) {
                    if (pending.has_previous_close) {
                        alert.textContent = 'Não há pré-pagamentos online por associar nesta abertura.';
                        wrap.classList.remove('d-none');
                    }
                    return;
                }
                var total = formatMoney(pending.total);
                alert.textContent = count === 1
                    ? '1 pré-pagamento online (' + total + ') será incluído nesta sessão.'
                    : count + ' pré-pagamentos online (' + total + ') serão incluídos nesta sessão.';
                wrap.classList.remove('d-none');
            })
            .catch(function () {
                if (loading) loading.classList.add('d-none');
            });
    }

    function showOpenModal() {
        var modal = getOpenModal();
        if (!modal) {
            return;
        }
        var input = document.getElementById('crmCashRegisterOpeningFloat');
        var err = document.getElementById('crmCashRegisterOpenFloatError');
        if (input) {
            input.classList.remove('is-invalid');
            if (!input.value) {
                input.value = '0.00';
            }
        }
        if (err) {
            err.textContent = '';
        }
        modal.show();
        if (input) {
            openModalEl.addEventListener('shown.bs.modal', function focusOnce() {
                input.focus();
                openModalEl.removeEventListener('shown.bs.modal', focusOnce);
            });
        }
    }

    function resetCloseModal() {
        var loading = document.getElementById('crmCashRegisterCloseLoading');
        var content = document.getElementById('crmCashRegisterCloseContent');
        var errBox = document.getElementById('crmCashRegisterCloseError');
        var submit = document.getElementById('crmCashRegisterCloseSubmit');
        if (loading) loading.classList.remove('d-none');
        if (content) content.classList.add('d-none');
        if (errBox) {
            errBox.classList.add('d-none');
            errBox.textContent = '';
        }
        if (submit) submit.classList.add('d-none');
        var tbody = document.getElementById('crmCashRegisterCloseMethodsBody');
        if (tbody) tbody.innerHTML = '';
        var counted = document.getElementById('crmCashRegisterCountedCash');
        if (counted) {
            counted.value = '';
            counted.classList.remove('is-invalid');
        }
        var notes = document.getElementById('crmCashRegisterCloseNotes');
        if (notes) notes.value = '';
    }

    function loadCloseSummary() {
        if (!cfg.summaryUrl) {
            return;
        }
        resetCloseModal();
        fetch(cfg.summaryUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, status: res.status, data: data };
                });
            })
            .then(function (result) {
                var loading = document.getElementById('crmCashRegisterCloseLoading');
                var content = document.getElementById('crmCashRegisterCloseContent');
                var errBox = document.getElementById('crmCashRegisterCloseError');
                var submit = document.getElementById('crmCashRegisterCloseSubmit');
                if (loading) loading.classList.add('d-none');

                if (!result.ok) {
                    if (errBox) {
                        errBox.textContent = (result.data && result.data.error) || 'Não foi possível carregar o resumo da caixa.';
                        errBox.classList.remove('d-none');
                    }
                    return;
                }

                var payload = result.data;
                var meta = document.getElementById('crmCashRegisterCloseSessionMeta');
                if (meta && payload.session) {
                    meta.textContent = 'Aberta ' + payload.session.opened_at_label
                        + ' · Fundo ' + formatMoney(payload.session.opening_float);
                }

                var tbody = document.getElementById('crmCashRegisterCloseMethodsBody');
                var methods = (payload.summary && payload.summary.methods) || [];
                if (tbody) {
                    if (!methods.length) {
                        tbody.innerHTML = '<tr><td colspan="2" class="text-muted">Sem vendas registadas nesta sessão.</td></tr>';
                    } else {
                        tbody.innerHTML = methods.map(function (row) {
                            var info = row.informational
                                ? ' <span class="text-muted small">(não entra no dinheiro físico)</span>'
                                : '';
                            return '<tr><td>' + row.label + info + '</td><td class="text-end text-nowrap">'
                                + formatMoney(row.amount) + '</td></tr>';
                        }).join('');
                    }
                }

                var summary = payload.summary || {};
                var expected = document.getElementById('crmCashRegisterCloseExpectedCash');
                if (expected) expected.textContent = formatMoney(summary.expected_cash_in_drawer);

                var hint = document.getElementById('crmCashRegisterCloseCountedHint');
                if (hint) {
                    hint.textContent = 'Fundo + vendas em dinheiro = ' + formatMoney(summary.expected_cash_in_drawer);
                }

                if (content) content.classList.remove('d-none');
                if (submit) submit.classList.remove('d-none');
                var counted = document.getElementById('crmCashRegisterCountedCash');
                if (counted) counted.focus();
            })
            .catch(function () {
                var loading = document.getElementById('crmCashRegisterCloseLoading');
                var errBox = document.getElementById('crmCashRegisterCloseError');
                if (loading) loading.classList.add('d-none');
                if (errBox) {
                    errBox.textContent = 'Erro de rede ao carregar o resumo.';
                    errBox.classList.remove('d-none');
                }
            });
    }

    function showCloseModal() {
        var modal = getCloseModal();
        if (!modal) {
            return;
        }
        modal.show();
    }

    function postForm(url, formData) {
        return fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: formData,
        }).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, status: res.status, data: data };
            });
        });
    }

    function handleTriggerClick(ev) {
        var el = ev.target.closest('[data-crm-cash-register-trigger]');
        if (!el) {
            return;
        }
        ev.preventDefault();
        var action = el.getAttribute('data-crm-cash-register-trigger');
        if (action === 'close') {
            showCloseModal();
        } else {
            showOpenModal();
        }
    }

    document.addEventListener('click', handleTriggerClick);

    if (openModalEl) {
        openModalEl.addEventListener('show.bs.modal', loadOpenPendingPreview);
    }

    if (closeModalEl) {
        closeModalEl.addEventListener('show.bs.modal', loadCloseSummary);
    }

    var openForm = document.getElementById('crmCashRegisterOpenForm');
    if (openForm) {
        openForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var submitBtn = document.getElementById('crmCashRegisterOpenSubmit');
            var input = document.getElementById('crmCashRegisterOpeningFloat');
            var err = document.getElementById('crmCashRegisterOpenFloatError');
            if (submitBtn) submitBtn.disabled = true;
            postForm(cfg.openUrl || openForm.action, new FormData(openForm))
                .then(function (result) {
                    if (submitBtn) submitBtn.disabled = false;
                    if (result.ok) {
                        setCashRegisterOpenState(true);
                        getOpenModal()?.hide();
                        var assigned = Number(result.data && result.data.assigned_booking_sales_count) || 0;
                        if (assigned > 0 && typeof window.showCrmToast === 'function') {
                            var msg = assigned === 1
                                ? 'Caixa aberta. 1 pré-pagamento online foi associado a esta sessão.'
                                : 'Caixa aberta. ' + assigned + ' pré-pagamentos online foram associados a esta sessão.';
                            window.showCrmToast(msg, 'success');
                        }
                        return;
                    }
                    if (input) input.classList.add('is-invalid');
                    if (err) {
                        var msg = (result.data && result.data.errors && result.data.errors.opening_float)
                            ? result.data.errors.opening_float[0]
                            : ((result.data && result.data.error) || 'Não foi possível abrir a caixa.');
                        err.textContent = msg;
                    }
                })
                .catch(function () {
                    if (submitBtn) submitBtn.disabled = false;
                    if (input) input.classList.add('is-invalid');
                    if (err) err.textContent = 'Erro de rede.';
                });
        });
    }

    var closeForm = document.getElementById('crmCashRegisterCloseForm');
    if (closeForm) {
        closeForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var submitBtn = document.getElementById('crmCashRegisterCloseSubmit');
            var counted = document.getElementById('crmCashRegisterCountedCash');
            var err = document.getElementById('crmCashRegisterCloseCountedError');
            if (submitBtn) submitBtn.disabled = true;
            postForm(cfg.closeUrl || closeForm.action, new FormData(closeForm))
                .then(function (result) {
                    if (submitBtn) submitBtn.disabled = false;
                    if (result.ok) {
                        setCashRegisterOpenState(false);
                        getCloseModal()?.hide();
                        return;
                    }
                    if (counted) counted.classList.add('is-invalid');
                    if (err) {
                        var msg = (result.data && result.data.errors && result.data.errors.counted_cash)
                            ? result.data.errors.counted_cash[0]
                            : ((result.data && result.data.error) || 'Não foi possível fechar a caixa.');
                        err.textContent = msg;
                    }
                })
                .catch(function () {
                    if (submitBtn) submitBtn.disabled = false;
                    if (counted) counted.classList.add('is-invalid');
                    if (err) err.textContent = 'Erro de rede.';
                });
        });
    }

    window.CrmCashRegister = window.CrmCashRegister || {};
    window.CrmCashRegister.showOpenModal = showOpenModal;
    window.CrmCashRegister.showCloseModal = showCloseModal;
    window.CrmCashRegister.isOpen = function () {
        return !!cfg.isOpen;
    };
})();
