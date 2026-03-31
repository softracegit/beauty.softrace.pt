/**
 * Flatpickr para campos de data no CRM (locale PT, formato alinhado com filtros Desde/Até).
 */
(function () {
  'use strict';

  function ptLocale() {
    return (window.flatpickr && window.flatpickr.l10ns && window.flatpickr.l10ns.pt)
      ? window.flatpickr.l10ns.pt
      : undefined;
  }

  function baseOpts() {
    return {
      allowInput: true,
      locale: ptLocale()
    };
  }

  function raiseCalendarZIndex(fp) {
    if (fp.calendarContainer) {
      fp.calendarContainer.style.zIndex = '2100';
    }
  }

  /**
   * @param {HTMLElement|null} el
   * @param {Date|string|null} value — Date, string Y-m-d ou Y-m-dTH:i, ou '' para limpar
   */
  window.crmFlatpickrSetValue = function (el, value) {
    if (!el) return;
    if (el._flatpickr) {
      if (value === '' || value == null) {
        el._flatpickr.clear();
      } else {
        el._flatpickr.setDate(value, false);
      }
      return;
    }
    if (value instanceof Date) {
      el.value = value.toISOString().slice(0, 16);
    } else {
      el.value = value || '';
    }
  };

  window.initCrmFlatpickr = function (root) {
    if (typeof flatpickr === 'undefined') return;
    root = root || document;

    var dateOpts = Object.assign({}, baseOpts(), {
      dateFormat: 'Y-m-d',
      onOpen: function () {
        raiseCalendarZIndex(this);
      }
    });

    root.querySelectorAll('[data-crm-datepicker]').forEach(function (el) {
      if (el._flatpickr) return;
      var opts = Object.assign({}, dateOpts);
      var max = el.getAttribute('data-max-date');
      if (max) opts.maxDate = max;
      var min = el.getAttribute('data-min-date');
      if (min) opts.minDate = min;
      flatpickr(el, opts);
    });

    /* Ficha cliente: apenas inputs visíveis (hiddens com o mesmo name existem doutra tab) */
    root.querySelectorAll(
      'input[type="text"][name="marcacoes_desde"], input[type="text"][name="marcacoes_ate"], input[type="text"][name="vendas_desde"], input[type="text"][name="vendas_ate"]'
    ).forEach(function (el) {
      if (el._flatpickr) return;
      if (el.type === 'hidden') return;
      flatpickr(el, Object.assign({}, dateOpts));
    });

    var dtOpts = Object.assign({}, baseOpts(), {
      enableTime: true,
      time_24hr: true,
      dateFormat: 'Y-m-d\\TH:i',
      onOpen: function () {
        raiseCalendarZIndex(this);
      }
    });

    root.querySelectorAll('[data-crm-datetime]').forEach(function (el) {
      if (el._flatpickr) return;
      flatpickr(el, Object.assign({}, dtOpts));
    });
  };

  document.addEventListener('DOMContentLoaded', function () {
    window.initCrmFlatpickr(document);
  });
})();
