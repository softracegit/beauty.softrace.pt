/**
 * Mensagens HTTP amigáveis (ex.: CSRF expirado) para fetch/AJAX no CRM e Booking.
 */
(function (global) {
    'use strict';

    var CSRF_FRIENDLY = 'A sua sessão expirou ou a página ficou aberta demasiado tempo. Atualize a página e tente novamente.';
    var SLOT_HOLD_RESTART = 'Ocorreu um erro, por favor pressione «Voltar ao início».';

    function isCsrfMismatch(status, message) {
        if (status === 419) {
            return true;
        }
        if (!message) {
            return false;
        }
        var m = String(message).toLowerCase();
        return (
            m.indexOf('csrf') !== -1 ||
            m.indexOf('token mismatch') !== -1 ||
            m.indexOf('page expired') !== -1 ||
            m.indexOf('sua sessão expirou') !== -1
        );
    }

    function messageFromResponse(status, data, fallback) {
        var raw = data && data.message ? String(data.message) : '';
        if (isCsrfMismatch(status, raw)) {
            return CSRF_FRIENDLY;
        }
        if (raw) {
            return raw;
        }
        if (status === 419) {
            return CSRF_FRIENDLY;
        }
        return fallback || 'Não foi possível processar o pedido. Tente novamente.';
    }

    function firstValidationMessage(data) {
        if (!data || !data.errors || typeof data.errors !== 'object') {
            return '';
        }
        var keys = Object.keys(data.errors);
        if (!keys.length) {
            return '';
        }
        var first = data.errors[keys[0]];
        if (Array.isArray(first) && first[0]) {
            return String(first[0]);
        }
        return '';
    }

    function resolveError(status, data, fallback) {
        var validation = firstValidationMessage(data);
        if (validation && !isCsrfMismatch(status, validation)) {
            return validation;
        }
        return messageFromResponse(status, data, fallback);
    }

    function slotHoldExtendError(status, message, fallback) {
        if (isCsrfMismatch(status, message)) {
            return SLOT_HOLD_RESTART;
        }
        if (message) {
            return String(message);
        }
        return fallback || 'Não foi possível prolongar a reserva.';
    }

    global.HttpFriendlyErrors = {
        CSRF_MESSAGE: CSRF_FRIENDLY,
        SLOT_HOLD_RESTART_MESSAGE: SLOT_HOLD_RESTART,
        isCsrfMismatch: isCsrfMismatch,
        messageFromResponse: messageFromResponse,
        resolveError: resolveError,
        slotHoldExtendError: slotHoldExtendError,
    };
})(typeof window !== 'undefined' ? window : globalThis);
