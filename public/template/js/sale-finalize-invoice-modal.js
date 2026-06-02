(function () {
    'use strict';

    var C = window.SALE_FINALIZE_CONFIG || {};
    var state = {
        saleId: null,
        amount: 0,
        client: {},
        modalLabel: 'Faturar',
    };

    function $id(id) {
        return document.getElementById(id);
    }

    function $$(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : (C.csrf || '');
    }

    function toast(msg, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, type);
        } else {
            alert(msg);
        }
    }

    function moneyEur(amount) {
        var n = Math.max(0, parseFloat(amount) || 0);
        return n.toFixed(2).replace('.', ',') + ' €';
    }

    function clientNifDigits() {
        return String(state.client.nif || '').replace(/\D/g, '');
    }

    function clientHasEmail() {
        var em = String(state.client.email || '').trim();
        if (em.length < 5) return false;
        var at = em.indexOf('@');
        var dot = em.lastIndexOf('.');
        return at > 0 && dot > at + 1;
    }

    function populateClientHero() {
        var snap = state.client || {};
        var nameEl = $id('paymentModalHeroName');
        var phoneEl = $id('paymentModalHeroPhone');
        var emailEl = $id('paymentModalHeroEmail');
        var nifEl = $id('paymentModalHeroNif');
        var av = $id('paymentModalHeroAvatar');
        var fb = $id('paymentModalHeroAvatarFallback');
        if (nameEl) nameEl.textContent = (snap.name && String(snap.name).trim()) ? String(snap.name).trim() : '—';
        if (phoneEl) phoneEl.textContent = (snap.phone && String(snap.phone).trim()) ? String(snap.phone).trim() : '—';
        if (emailEl) emailEl.textContent = (snap.email && String(snap.email).trim()) ? String(snap.email).trim() : '—';
        if (nifEl) {
            var nif = String(snap.nif || '').trim();
            nifEl.textContent = nif !== '' ? ('NIF ' + nif) : 'Sem NIF';
        }
        if (av && fb) {
            av.classList.add('d-none');
            av.removeAttribute('src');
            var nm = (snap.name || '?').trim() || '?';
            var initials = nm.split(/\s+/).map(function (w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
            fb.textContent = initials;
            fb.classList.remove('d-none');
        }
    }

    function refreshHeroTotals() {
        var subtotalLbl = $id('paymentSubtotalLineLabel');
        var subDisp = $id('paymentSubtotalDisplay');
        if (subtotalLbl) subtotalLbl.textContent = 'Total a faturar:';
        if (subDisp) subDisp.textContent = moneyEur(state.amount);
        var feesWrap = $id('paymentFeesLines');
        if (feesWrap) {
            feesWrap.innerHTML = '';
            feesWrap.classList.add('d-none');
        }
        ['paymentLineCheckoutTotal', 'paymentLinePrepagamentoPaid', 'paymentLineDepositAmount', 'paymentLineTotalDue', 'paymentReservaCustomWrap', 'paymentGorjetaLine', 'paymentConsolidatedDetailsWrap'].forEach(function (id) {
            var el = $id(id);
            if (el) el.classList.add('d-none');
        });
    }

    function applyFiscalUi() {
        var mode = String(($id('paymentInvoiceFiscalMode') && $id('paymentInvoiceFiscalMode').value) || '').trim();
        var digits = clientNifDigits();
        var wrap = $id('paymentModalNifInlineWrap');
        if (wrap) {
            wrap.classList.toggle('d-none', !(mode === 'with_nif' && digits.length !== 9));
        }
    }

    function syncFiscalFromClient() {
        var digits = clientNifDigits();
        var mode = digits.length === 9 ? 'with_nif' : 'consumer';
        var hidden = $id('paymentInvoiceFiscalMode');
        if (hidden) hidden.value = mode;
        $$('#paymentFaturaTilesGrid .payment-invoice-fiscal-card').forEach(function (card) {
            var active = (card.dataset.fiscalMode || '') === mode;
            card.classList.toggle('active', active);
            card.setAttribute('aria-checked', active ? 'true' : 'false');
        });
        var nifInput = $id('paymentModalBillingNif');
        if (nifInput) nifInput.value = '';
        applyFiscalUi();
    }

    function initInvoiceDelivery() {
        var has = clientHasEmail();
        var hidden = $id('paymentInvoiceDelivery');
        var mode = has ? 'email' : 'print';
        if (hidden) hidden.value = mode;
        $$('#paymentFaturaTilesGrid .payment-invoice-delivery-card').forEach(function (card) {
            var del = card.dataset.invoiceDelivery || '';
            var active = del === mode;
            card.classList.toggle('active', active);
            card.setAttribute('aria-checked', active ? 'true' : 'false');
            if (del === 'email') {
                card.classList.toggle('payment-invoice-delivery-disabled', !has);
                card.setAttribute('aria-disabled', has ? 'false' : 'true');
            }
        });
    }

    function getFiscalPayload() {
        var mode = String(($id('paymentInvoiceFiscalMode') && $id('paymentInvoiceFiscalMode').value) || '').trim();
        var wrap = $id('paymentModalNifInlineWrap');
        var billing = '';
        if (wrap && !wrap.classList.contains('d-none')) {
            billing = String(($id('paymentModalBillingNif') && $id('paymentModalBillingNif').value) || '').replace(/\D/g, '');
        }
        return { invoice_fiscal_mode: mode, billing_nif: billing.length === 9 ? billing : '' };
    }

    function getInvoiceDelivery() {
        var h = $id('paymentInvoiceDelivery');
        var v = h ? String(h.value || '').trim() : '';
        return v === 'email' ? 'email' : 'print';
    }

    function validateFiscalForSubmit() {
        var mode = String(($id('paymentInvoiceFiscalMode') && $id('paymentInvoiceFiscalMode').value) || '').trim();
        if (mode !== 'with_nif') return true;
        if (clientNifDigits().length === 9) return true;
        var bill = String(($id('paymentModalBillingNif') && $id('paymentModalBillingNif').value) || '').replace(/\D/g, '');
        if (bill.length === 9) return true;
        toast('Para «Com NIF», indique 9 dígitos na ficha do cliente ou no campo «NIF nesta fatura».', 'error');
        return false;
    }

    function updateConfirmLabel() {
        var btn = $id('paymentConfirmBtn');
        if (!btn || btn.querySelector('.spinner-border')) return;
        btn.textContent = 'Faturar ' + moneyEur(state.amount);
        btn.disabled = false;
    }

    function applyFinalizeModeClasses() {
        var pm = $id('paymentModal');
        if (!pm) return;
        pm.classList.add('payment-pos-modal--invoice-only');
        pm.classList.remove('payment-pos-modal--reserva');
        var draftBtn = $id('paymentDraftBtn');
        if (draftBtn) draftBtn.classList.add('d-none');
    }

    function restoreConfirmButton() {
        var btn = $id('paymentConfirmBtn');
        if (btn) {
            btn.textContent = '';
            updateConfirmLabel();
        }
    }

    function submitFinalize() {
        if (!state.saleId) {
            toast('Venda não encontrada.', 'error');
            restoreConfirmButton();
            return;
        }
        if (!validateFiscalForSubmit()) {
            restoreConfirmButton();
            return;
        }
        var fiscal = getFiscalPayload();
        var baseUrl = String(C.finalizeInvoiceBaseUrl || '').replace(/\/$/, '');
        if (!baseUrl) {
            restoreConfirmButton();
            toast('URL de faturação não configurada.', 'error');
            return;
        }
        var btn = $id('paymentConfirmBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>A faturar...';
        }
        fetch(baseUrl + '/' + encodeURIComponent(String(state.saleId)) + '/finalize-invoice', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                invoice_fiscal_mode: fiscal.invoice_fiscal_mode,
                billing_nif: fiscal.billing_nif || null,
                invoice_delivery: getInvoiceDelivery(),
            }),
        })
            .then(function (r) { return r.json().then(function (res) { return { ok: r.ok, res: res }; }); })
            .then(function (pack) {
                restoreConfirmButton();
                if (!pack.ok || !pack.res || !pack.res.success) {
                    toast((pack.res && (pack.res.error || pack.res.message)) || 'Erro ao faturar.', 'error');
                    return;
                }
                var modalEl = $id('paymentModal');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getInstance(modalEl)?.hide();
                }
                var delivery = (pack.res && pack.res.invoice_delivery) || 'print';
                if (delivery === 'email' && pack.res && pack.res.invoice_email_sent) {
                    toast('Fatura emitida e enviada por email ao cliente.', 'success');
                } else if (delivery === 'email' && pack.res && pack.res.invoice_email_message) {
                    toast('Fatura emitida. ' + pack.res.invoice_email_message, 'warning');
                } else {
                    toast('Fatura emitida com sucesso.', 'success');
                }
                if (delivery === 'print') {
                    var vu = pack.res && pack.res.vendus_pdf_url;
                    if (vu) {
                        window.open(vu, '_blank', 'noopener,noreferrer');
                    }
                }
                window.location.reload();
            })
            .catch(function () {
                restoreConfirmButton();
                toast('Erro de ligação ao faturar.', 'error');
            });
    }

    function openSaleFinalizeInvoiceModal(payload) {
        payload = payload || {};
        if (!payload.id) return;
        state.saleId = parseInt(payload.id, 10);
        state.amount = Math.max(0, parseFloat(payload.amount) || 0);
        state.client = {
            name: payload.client_name || '',
            email: payload.client_email || '',
            phone: payload.client_phone || '',
            nif: payload.client_nif || '',
        };
        state.modalLabel = payload.modal_label || 'Faturar';
        var modalEl = $id('paymentModal');
        if (!modalEl) return;
        var pl = $id('paymentModalLabel');
        if (pl) pl.textContent = state.modalLabel;
        applyFinalizeModeClasses();
        populateClientHero();
        syncFiscalFromClient();
        initInvoiceDelivery();
        refreshHeroTotals();
        updateConfirmLabel();
        if (typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function bindOnce() {
        var modalEl = $id('paymentModal');
        if (!modalEl || modalEl.dataset.saleFinalizeBound === '1') return;
        modalEl.dataset.saleFinalizeBound = '1';

        var grid = $id('paymentFaturaTilesGrid');
        if (grid) {
            grid.addEventListener('click', function (e) {
                var deliveryCard = e.target.closest('.payment-invoice-delivery-card');
                if (deliveryCard) {
                    if (deliveryCard.classList.contains('payment-invoice-delivery-disabled')) {
                        toast('Indique um email válido na ficha do cliente para enviar a fatura por email.', 'error');
                        return;
                    }
                    var del = deliveryCard.dataset.invoiceDelivery || 'print';
                    $$('#paymentFaturaTilesGrid .payment-invoice-delivery-card').forEach(function (c) {
                        var active = c === deliveryCard;
                        c.classList.toggle('active', active);
                        c.setAttribute('aria-checked', active ? 'true' : 'false');
                    });
                    var hiddenD = $id('paymentInvoiceDelivery');
                    if (hiddenD) hiddenD.value = del;
                    return;
                }
                var fiscalCard = e.target.closest('.payment-invoice-fiscal-card');
                if (!fiscalCard) return;
                var mode = fiscalCard.dataset.fiscalMode || 'consumer';
                $$('#paymentFaturaTilesGrid .payment-invoice-fiscal-card').forEach(function (c) {
                    var active = c === fiscalCard;
                    c.classList.toggle('active', active);
                    c.setAttribute('aria-checked', active ? 'true' : 'false');
                });
                var hiddenF = $id('paymentInvoiceFiscalMode');
                if (hiddenF) hiddenF.value = mode;
                if (mode === 'consumer') {
                    var bn = $id('paymentModalBillingNif');
                    if (bn) bn.value = '';
                }
                applyFiscalUi();
            });
        }

        var nifEl = $id('paymentModalBillingNif');
        if (nifEl) {
            nifEl.addEventListener('input', function () {
                var v = String(this.value || '').replace(/\D/g, '').slice(0, 9);
                if (this.value !== v) this.value = v;
            });
        }

        var confirmBtn = $id('paymentConfirmBtn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                if (!state.saleId) return;
                submitFinalize();
            });
        }

        function hideModal() {
            if (typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getInstance(modalEl)?.hide();
            }
        }
        var cancelBtn = $id('paymentCancelBtn');
        var closeBtn = $id('paymentModalCloseBtn');
        if (cancelBtn) cancelBtn.addEventListener('click', hideModal);
        if (closeBtn) closeBtn.addEventListener('click', hideModal);

        modalEl.addEventListener('hidden.bs.modal', function () {
            state.saleId = null;
            modalEl.classList.remove('payment-pos-modal--invoice-only');
        });
    }

    window.openSaleFinalizeInvoiceModal = openSaleFinalizeInvoiceModal;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindOnce);
    } else {
        bindOnce();
    }
})();
