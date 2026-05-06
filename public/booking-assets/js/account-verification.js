(function () {
    'use strict';

    function getCsrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
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
            return r
                .json()
                .catch(function () {
                    return {};
                })
                .then(function (data) {
                    if (!r.ok) {
                        var msg = (data && data.message) || 'Não foi possível processar o pedido.';
                        if (data && data.errors) {
                            var firstKey = Object.keys(data.errors)[0];
                            if (firstKey && Array.isArray(data.errors[firstKey]) && data.errors[firstKey][0]) {
                                msg = data.errors[firstKey][0];
                            }
                        }
                        var err = new Error(msg);
                        if (data && data.retry_after_seconds != null && !isNaN(Number(data.retry_after_seconds))) {
                            err.retry_after_seconds = Number(data.retry_after_seconds);
                        }
                        throw err;
                    }

                    return data || {};
                });
        });
    }

    function init() {
        var root = document.getElementById('booking-contact-verification');
        if (!root || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }
        var requestUrl = root.getAttribute('data-request-url') || '';
        var confirmUrl = root.getAttribute('data-confirm-url') || '';
        if (!requestUrl || !confirmUrl) {
            return;
        }

        var openBtns = Array.prototype.slice.call(
            document.querySelectorAll('.js-open-contact-verification')
        );
        var modalEl = document.getElementById('booking-contact-verification-modal');
        var modalTitle = document.getElementById('booking-contact-verification-title');
        var modalSubtitle = document.getElementById('booking-contact-verification-subtitle');
        var errorBox = document.getElementById('booking-contact-verification-error');
        var codeHidden = document.getElementById('booking-contact-verification-code');
        var digits = Array.prototype.slice.call(
            document.querySelectorAll('.js-booking-contact-code-digit')
        );
        var submitBtn = document.getElementById('booking-contact-verification-submit');
        var resendBtn = document.getElementById('booking-contact-verification-resend');

        if (
            !modalEl ||
            !modalTitle ||
            !modalSubtitle ||
            !errorBox ||
            !codeHidden ||
            !submitBtn ||
            !resendBtn ||
            digits.length !== 6
        ) {
            return;
        }

        var modal = new window.bootstrap.Modal(modalEl);
        var currentChannel = '';
        var isSubmitting = false;
        var lastCodeSentAt = 0;
        var resendCooldownSeconds = 30;
        var resendCooldownTimerId = null;

        function showError(msg) {
            errorBox.textContent = msg || '';
            errorBox.classList.toggle('d-none', !msg);
        }

        function setLoading(btn, loading, loadingText, defaultText) {
            if (!btn) return;
            if (loading) {
                btn.disabled = true;
                btn.setAttribute('data-prev-text', btn.textContent || '');
                btn.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' +
                    (loadingText || 'A processar...');
            } else {
                btn.disabled = false;
                btn.textContent = defaultText || btn.getAttribute('data-prev-text') || 'Confirmar';
            }
        }

        function getCode() {
            return digits
                .map(function (i) {
                    return String(i.value || '').replace(/\D/g, '').slice(0, 1);
                })
                .join('');
        }

        function setCode(raw) {
            var clean = String(raw || '')
                .replace(/\D/g, '')
                .slice(0, 6);
            digits.forEach(function (i, idx) {
                i.value = clean[idx] || '';
            });
            codeHidden.value = clean;
            return clean;
        }

        function resetModal() {
            currentChannel = '';
            showError('');
            setCode('');
            lastCodeSentAt = 0;
            if (resendCooldownTimerId) {
                clearInterval(resendCooldownTimerId);
                resendCooldownTimerId = null;
            }
            resendBtn.disabled = false;
            resendBtn.textContent = 'Reenviar';
        }

        function updateResendCountdown(secondsLeft) {
            if (secondsLeft > 0) {
                resendBtn.disabled = true;
                resendBtn.textContent = 'Reenviar (' + secondsLeft + 's)';
            } else {
                if (resendCooldownTimerId) {
                    clearInterval(resendCooldownTimerId);
                    resendCooldownTimerId = null;
                }
                resendBtn.disabled = false;
                resendBtn.textContent = 'Reenviar';
            }
        }

        function startResendCooldownFromNow() {
            if (resendCooldownTimerId) {
                clearInterval(resendCooldownTimerId);
                resendCooldownTimerId = null;
            }
            var total = Math.max(0, parseInt(String(resendCooldownSeconds), 10) || 0);
            if (total <= 0) {
                updateResendCountdown(0);
                return;
            }
            var endAt = Date.now() + total * 1000;
            function tick() {
                var left = Math.ceil((endAt - Date.now()) / 1000);
                if (left <= 0) {
                    updateResendCountdown(0);
                    return;
                }
                updateResendCountdown(left);
            }
            tick();
            resendCooldownTimerId = setInterval(tick, 500);
        }

        function applySendSuccess(data) {
            lastCodeSentAt = Date.now();
            if (data && data.resend_cooldown_seconds != null && !isNaN(Number(data.resend_cooldown_seconds))) {
                resendCooldownSeconds = Math.max(0, Number(data.resend_cooldown_seconds));
            }
            window.setTimeout(function () {
                startResendCooldownFromNow();
            }, 0);
        }

        function sendCode(channel) {
            return postJson(requestUrl, { channel: channel });
        }

        function submitCode() {
            if (!currentChannel || isSubmitting) {
                return;
            }
            var code = getCode();
            if (code.length !== 6) {
                showError('Indique o código de 6 dígitos.');
                return;
            }
            isSubmitting = true;
            setLoading(submitBtn, true, 'A confirmar...', 'Confirmar');
            postJson(confirmUrl, { channel: currentChannel, code: code })
                .then(function () {
                    window.location.reload();
                })
                .catch(function (err) {
                    showError(err && err.message ? err.message : 'Não foi possível confirmar o código.');
                })
                .finally(function () {
                    isSubmitting = false;
                    setLoading(submitBtn, false, '', 'Confirmar');
                });
        }

        openBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var channel = btn.getAttribute('data-channel') || '';
                if (channel !== 'email' && channel !== 'phone') {
                    return;
                }
                resetModal();
                currentChannel = channel;
                modalTitle.textContent = channel === 'email' ? 'Verificar email' : 'Verificar telemóvel';
                modalSubtitle.textContent =
                    channel === 'email'
                        ? 'Enviámos um código para o seu email.'
                        : 'Enviámos um código por SMS para o seu telemóvel.';
                setLoading(btn, true, 'A enviar...', 'Verificar');
                sendCode(channel)
                    .then(function (data) {
                        applySendSuccess(data || {});
                        modal.show();
                        digits[0].focus();
                    })
                    .catch(function (err) {
                        showError(err && err.message ? err.message : 'Não foi possível enviar o código.');
                        modal.show();
                    })
                    .finally(function () {
                        setLoading(btn, false, '', 'Verificar');
                    });
            });
        });

        submitBtn.addEventListener('click', submitCode);
        resendBtn.addEventListener('click', function () {
            if (!currentChannel) {
                return;
            }
            var minMs = Math.max(0, parseInt(String(resendCooldownSeconds), 10) || 0) * 1000;
            if (minMs > 0 && Date.now() - lastCodeSentAt < minMs) {
                var waitSec = Math.max(1, Math.ceil((minMs - (Date.now() - lastCodeSentAt)) / 1000));
                showError('Aguarde ' + waitSec + ' segundos antes de pedir um novo código.');
                return;
            }
            setLoading(resendBtn, true, 'A reenviar...', 'Reenviar');
            sendCode(currentChannel)
                .then(function (data) {
                    applySendSuccess(data || {});
                })
                .catch(function (err) {
                    showError(err && err.message ? err.message : 'Não foi possível reenviar o código.');
                    if (err && err.retry_after_seconds != null && !isNaN(Number(err.retry_after_seconds))) {
                        resendCooldownSeconds = Math.max(resendCooldownSeconds, Number(err.retry_after_seconds));
                        lastCodeSentAt = Date.now();
                        window.setTimeout(function () {
                            startResendCooldownFromNow();
                        }, 0);
                    }
                })
                .finally(function () {
                    setLoading(resendBtn, false, '', 'Reenviar');
                });
        });

        if (digits[0]) {
            // iOS OTP autofill may inject all digits into first field.
            digits[0].setAttribute('maxlength', '6');
        }

        digits.forEach(function (input, idx) {
            input.addEventListener('input', function () {
                var clean = String(input.value || '').replace(/\D/g, '');
                if (clean.length > 1) {
                    var merged = getCode();
                    merged = merged.slice(0, idx) + clean + merged.slice(idx + 1);
                    setCode(merged);
                    digits[Math.min(5, idx + clean.length - 1)].focus();
                } else {
                    input.value = clean;
                    codeHidden.value = getCode();
                    if (clean && idx < 5) {
                        digits[idx + 1].focus();
                    }
                }
                if (getCode().length === 6) {
                    submitCode();
                }
            });
            input.addEventListener('keydown', function (ev) {
                if (ev.key === 'Backspace') {
                    if (input.value === '' && idx > 0) {
                        digits[idx - 1].focus();
                        digits[idx - 1].value = '';
                        codeHidden.value = getCode();
                        ev.preventDefault();
                    }
                    return;
                }
                if (ev.key === 'ArrowLeft' && idx > 0) {
                    digits[idx - 1].focus();
                    ev.preventDefault();
                } else if (ev.key === 'ArrowRight' && idx < 5) {
                    digits[idx + 1].focus();
                    ev.preventDefault();
                }
            });
            input.addEventListener('paste', function (ev) {
                var txt = (ev.clipboardData || window.clipboardData).getData('text') || '';
                var clean = txt.replace(/\D/g, '').slice(0, 6);
                if (!clean) {
                    return;
                }
                ev.preventDefault();
                setCode(clean);
                digits[Math.min(5, clean.length - 1)].focus();
                if (clean.length === 6) {
                    submitCode();
                }
            });
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            resetModal();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

