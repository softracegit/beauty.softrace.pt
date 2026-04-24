(function () {
    'use strict';

    function getCsrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function postJson(url, payload, method) {
        return fetch(url, {
            method: method || 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: payload ? JSON.stringify(payload) : null,
        }).then(function (r) {
            return r
                .json()
                .catch(function () {
                    return {};
                })
                .then(function (data) {
                    return { ok: r.ok, status: r.status, data: data };
                });
        });
    }

    function brandLabel(raw) {
        var value = String(raw || '').trim();
        return value ? value.toUpperCase() : 'CARD';
    }

    function renderCards(container, cards) {
        if (!Array.isArray(cards) || !cards.length) {
            container.innerHTML = '<p class="small text-muted mb-0">Sem cartões guardados.</p>';
            return;
        }
        container.innerHTML = cards
            .map(function (card) {
                var expMonth = card.exp_month != null ? String(card.exp_month).padStart(2, '0') : '--';
                var expYear = card.exp_year != null ? String(card.exp_year) : '----';
                var defaultHtml = card.is_default
                    ? '<span class="badge text-bg-success">Principal</span>'
                    : '<button type="button" class="btn btn-link btn-sm p-0 js-card-default" data-card-id="' +
                      card.id +
                      '">Definir principal</button>';
                return (
                    '<div class="d-flex align-items-center justify-content-between gap-2 py-2 border-top">' +
                    '<div class="small">' +
                    '<div class="fw-semibold text-dark">' +
                    brandLabel(card.brand) +
                    ' •••• ' +
                    card.last4 +
                    '</div>' +
                    '<div class="text-muted">Validade ' +
                    expMonth +
                    '/' +
                    expYear +
                    '</div>' +
                    '</div>' +
                    '<div class="d-flex align-items-center gap-2">' +
                    defaultHtml +
                    '<button type="button" class="btn btn-link btn-sm text-danger p-0 js-card-remove" data-card-id="' +
                    card.id +
                    '">Remover</button>' +
                    '</div>' +
                    '</div>'
                );
            })
            .join('');
    }

    function init() {
        var root = document.getElementById('booking-cards-wallet');
        if (!root) {
            return;
        }

        var setupUrl = root.getAttribute('data-setup-intent-url') || '';
        var syncUrl = root.getAttribute('data-sync-url') || '';
        var defaultUrlTpl = root.getAttribute('data-default-url-template') || '';
        var destroyUrlTpl = root.getAttribute('data-destroy-url-template') || '';
        var publishableKey = root.getAttribute('data-publishable-key') || '';

        if (!setupUrl || !syncUrl || !defaultUrlTpl || !destroyUrlTpl || !publishableKey) {
            return;
        }

        var list = document.getElementById('booking-cards-list');
        var addWrap = document.getElementById('booking-card-add-wrap');
        var addBtn = document.getElementById('booking-card-open-add');
        var cancelBtn = document.getElementById('booking-card-add-cancel');
        var submitBtn = document.getElementById('booking-card-add-submit');
        var errorEl = document.getElementById('booking-card-add-error');
        var mount = document.getElementById('booking-card-add-element');
        if (!list || !addWrap || !addBtn || !cancelBtn || !submitBtn || !errorEl || !mount) {
            return;
        }

        var stripe = null;
        var elements = null;
        var cardElement = null;
        var clientSecret = '';

        function setError(message) {
            errorEl.textContent = message || '';
            errorEl.classList.toggle('d-none', !message);
        }

        function setLoading(isLoading) {
            submitBtn.disabled = isLoading;
            submitBtn.textContent = isLoading ? 'A guardar...' : 'Guardar cartão';
        }

        function openAddCard() {
            addWrap.classList.remove('d-none');
            setError('');
            postJson(setupUrl, {})
                .then(function (res) {
                    if (!res.ok || !res.data || !res.data.client_secret) {
                        setError((res.data && res.data.message) || 'Não foi possível iniciar a validação do cartão.');
                        return;
                    }
                    clientSecret = res.data.client_secret;
                    if (typeof window.Stripe !== 'function') {
                        setError('Stripe.js não está disponível.');
                        return;
                    }
                    if (!stripe) {
                        stripe = window.Stripe(publishableKey, { locale: 'pt' });
                    }
                    if (mount) {
                        mount.innerHTML = '';
                    }
                    elements = stripe.elements({
                        clientSecret: clientSecret,
                        appearance: { theme: 'stripe' },
                    });
                    cardElement = elements.create('payment', {
                        fields: {
                            billingDetails: {
                                name: 'auto',
                                email: 'auto',
                            },
                        },
                    });
                    cardElement.mount(mount);
                })
                .catch(function () {
                    setError('Erro de rede ao preparar o cartão.');
                });
        }

        function closeAddCard() {
            addWrap.classList.add('d-none');
            setError('');
            clientSecret = '';
            try {
                if (cardElement && typeof cardElement.unmount === 'function') {
                    cardElement.unmount();
                }
            } catch (e) {
                /* ignore */
            }
            mount.innerHTML = '';
            elements = null;
            cardElement = null;
        }

        function confirmAddCard() {
            if (!stripe || !elements || !clientSecret) {
                setError('Inicie novamente o formulário de cartão.');
                return;
            }
            setLoading(true);
            stripe
                .confirmSetup({
                    elements: elements,
                    redirect: 'if_required',
                })
                .then(function (result) {
                    if (result.error) {
                        setError(result.error.message || 'Não foi possível validar o cartão.');
                        setLoading(false);
                        return;
                    }
                    var setupIntent = result.setupIntent;
                    if (!setupIntent || setupIntent.status !== 'succeeded') {
                        setError('A validação do cartão não foi concluída.');
                        setLoading(false);
                        return;
                    }
                    postJson(syncUrl, { setup_intent_id: setupIntent.id }).then(function (res) {
                        setLoading(false);
                        if (!res.ok || !res.data || !res.data.success) {
                            setError((res.data && res.data.message) || 'Não foi possível guardar o cartão.');
                            return;
                        }
                        renderCards(list, res.data.cards || []);
                        closeAddCard();
                    });
                })
                .catch(function () {
                    setError('Erro ao confirmar o cartão.');
                    setLoading(false);
                });
        }

        addBtn.addEventListener('click', openAddCard);
        cancelBtn.addEventListener('click', closeAddCard);
        submitBtn.addEventListener('click', confirmAddCard);

        list.addEventListener('click', function (event) {
            var defaultBtn = event.target.closest('.js-card-default');
            if (defaultBtn) {
                var cardId = defaultBtn.getAttribute('data-card-id');
                postJson(defaultUrlTpl.replace('__CARD__', cardId), {}).then(function (res) {
                    if (res.ok && res.data && res.data.success) {
                        renderCards(list, res.data.cards || []);
                    }
                });
                return;
            }

            var removeBtn = event.target.closest('.js-card-remove');
            if (removeBtn) {
                var removeId = removeBtn.getAttribute('data-card-id');
                if (!window.confirm('Remover este cartão guardado?')) {
                    return;
                }
                postJson(destroyUrlTpl.replace('__CARD__', removeId), null, 'DELETE').then(function (res) {
                    if (res.ok && res.data && res.data.success) {
                        renderCards(list, res.data.cards || []);
                    }
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
