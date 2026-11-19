/**
 * Flatpickr CRM: locale PT + mês/ano clicáveis (painel custom, sem <select> nativo).
 * O select nativo do Flatpickr não abre bem em offcanvas/modais (overflow + transform).
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

  function yearBounds(fp) {
    var nowY = new Date().getFullYear();
    var minY = nowY - 120;
    var maxY = nowY + 20;
    if (fp.config.minDate instanceof Date && !isNaN(fp.config.minDate.getTime())) {
      minY = fp.config.minDate.getFullYear();
    }
    if (fp.config.maxDate instanceof Date && !isNaN(fp.config.maxDate.getTime())) {
      maxY = fp.config.maxDate.getFullYear();
    }
    if (minY > maxY) {
      var t = minY;
      minY = maxY;
      maxY = t;
    }
    return { minY: minY, maxY: maxY };
  }

  function monthNames(fp) {
    var loc = (fp.l10n && fp.l10n.months && fp.l10n.months.longhand) || null;
    if (loc && loc.length === 12) return loc;
    return [
      'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
      'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
    ];
  }

  function closeCrmPanels(fp) {
    if (!fp || !fp.calendarContainer) return;
    fp.calendarContainer.querySelectorAll('.flatpickr-crm-panel.is-open').forEach(function (p) {
      p.classList.remove('is-open');
    });
    fp.calendarContainer.classList.remove('flatpickr-crm-panel-open');
  }

  function syncCrmMonthYearLabels(fp) {
    if (!fp || !fp._crmMonthBtn || !fp._crmYearBtn) return;
    var months = monthNames(fp);
    fp._crmMonthBtn.textContent = months[fp.currentMonth] || '';
    fp._crmYearBtn.textContent = String(fp.currentYear);
  }

  function renderMonthPanel(fp) {
    var grid = fp._crmMonthPanel && fp._crmMonthPanel.querySelector('.flatpickr-crm-panel-grid');
    if (!grid) return;
    var months = monthNames(fp);
    var html = '';
    for (var i = 0; i < 12; i++) {
      html +=
        '<button type="button" class="flatpickr-crm-panel-item' +
        (i === fp.currentMonth ? ' is-selected' : '') +
        '" data-month="' + i + '">' +
        months[i] +
        '</button>';
    }
    grid.innerHTML = html;
  }

  function renderYearPanel(fp) {
    var panel = fp._crmYearPanel;
    if (!panel) return;
    var grid = panel.querySelector('.flatpickr-crm-panel-grid');
    var label = panel.querySelector('.flatpickr-crm-year-range');
    if (!grid) return;
    var bounds = yearBounds(fp);
    var start = fp._crmYearPageStart;
    if (start == null || isNaN(start)) {
      start = Math.floor(fp.currentYear / 12) * 12;
    }
    start = Math.max(bounds.minY, Math.min(start, bounds.maxY));
    start = Math.floor(start / 12) * 12;
    if (start < bounds.minY) start = bounds.minY;
    fp._crmYearPageStart = start;
    var end = Math.min(start + 11, bounds.maxY);
    if (label) label.textContent = start + ' – ' + end;

    var prevBtn = panel.querySelector('[data-year-nav="prev"]');
    var nextBtn = panel.querySelector('[data-year-nav="next"]');
    if (prevBtn) prevBtn.disabled = start <= bounds.minY;
    if (nextBtn) nextBtn.disabled = end >= bounds.maxY;

    var html = '';
    for (var y = start; y <= end; y++) {
      html +=
        '<button type="button" class="flatpickr-crm-panel-item' +
        (y === fp.currentYear ? ' is-selected' : '') +
        '" data-year="' + y + '">' +
        y +
        '</button>';
    }
    grid.innerHTML = html;
  }

  function openMonthPanel(fp) {
    if (!fp._crmMonthPanel) return;
    closeCrmPanels(fp);
    renderMonthPanel(fp);
    fp._crmMonthPanel.classList.add('is-open');
    fp.calendarContainer.classList.add('flatpickr-crm-panel-open');
  }

  function openYearPanel(fp) {
    if (!fp._crmYearPanel) return;
    closeCrmPanels(fp);
    fp._crmYearPageStart = Math.floor(fp.currentYear / 12) * 12;
    renderYearPanel(fp);
    fp._crmYearPanel.classList.add('is-open');
    fp.calendarContainer.classList.add('flatpickr-crm-panel-open');
  }

  /**
   * Substitui o select/input nativo de mês e ano por botões + painéis clicáveis.
   * Seguro para reaplicar (idempotente).
   */
  window.enhanceCrmFlatpickrInstance = function (fp) {
    if (!fp || !fp.calendarContainer) return;
    raiseCalendarZIndex(fp);

    if (fp._crmMonthYearEnhanced) {
      if (fp._crmMonthBtn && fp.calendarContainer.contains(fp._crmMonthBtn)) {
        syncCrmMonthYearLabels(fp);
        return;
      }
      /* Header reconstruído pelo Flatpickr — recriar controlos */
      fp._crmMonthYearEnhanced = false;
      fp._crmMonthBtn = null;
      fp._crmYearBtn = null;
      fp._crmMonthPanel = null;
      fp._crmYearPanel = null;
      fp.calendarContainer.querySelectorAll('.flatpickr-crm-panel, .flatpickr-crm-my-controls').forEach(function (n) {
        n.remove();
      });
    }
    fp._crmMonthYearEnhanced = true;

    var currentMonth = fp.calendarContainer.querySelector('.flatpickr-current-month');
    if (!currentMonth) return;

    var monthSelect = currentMonth.querySelector('select.flatpickr-monthDropdown-months');
    var yearWrap = currentMonth.querySelector('.numInputWrapper');
    if (monthSelect) monthSelect.style.display = 'none';
    if (yearWrap) yearWrap.style.display = 'none';

    var controls = document.createElement('div');
    controls.className = 'flatpickr-crm-my-controls';

    var monthBtn = document.createElement('button');
    monthBtn.type = 'button';
    monthBtn.className = 'flatpickr-crm-my-btn flatpickr-crm-month-btn';
    monthBtn.setAttribute('aria-label', 'Escolher mês');

    var yearBtn = document.createElement('button');
    yearBtn.type = 'button';
    yearBtn.className = 'flatpickr-crm-my-btn flatpickr-crm-year-btn';
    yearBtn.setAttribute('aria-label', 'Escolher ano');

    controls.appendChild(monthBtn);
    controls.appendChild(yearBtn);
    currentMonth.appendChild(controls);

    fp._crmMonthBtn = monthBtn;
    fp._crmYearBtn = yearBtn;
    syncCrmMonthYearLabels(fp);

    var monthPanel = document.createElement('div');
    monthPanel.className = 'flatpickr-crm-panel flatpickr-crm-month-panel';
    monthPanel.innerHTML = '<div class="flatpickr-crm-panel-grid"></div>';
    fp.calendarContainer.appendChild(monthPanel);
    fp._crmMonthPanel = monthPanel;

    var yearPanel = document.createElement('div');
    yearPanel.className = 'flatpickr-crm-panel flatpickr-crm-year-panel';
    yearPanel.innerHTML =
      '<div class="flatpickr-crm-year-nav">' +
      '<button type="button" class="flatpickr-crm-year-nav-btn" data-year-nav="prev" aria-label="Anos anteriores">‹</button>' +
      '<span class="flatpickr-crm-year-range"></span>' +
      '<button type="button" class="flatpickr-crm-year-nav-btn" data-year-nav="next" aria-label="Anos seguintes">›</button>' +
      '</div>' +
      '<div class="flatpickr-crm-panel-grid"></div>';
    fp.calendarContainer.appendChild(yearPanel);
    fp._crmYearPanel = yearPanel;

    monthBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (monthPanel.classList.contains('is-open')) {
        closeCrmPanels(fp);
      } else {
        openMonthPanel(fp);
      }
    });
    yearBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (yearPanel.classList.contains('is-open')) {
        closeCrmPanels(fp);
      } else {
        openYearPanel(fp);
      }
    });

    monthPanel.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-month]');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      var m = parseInt(btn.getAttribute('data-month'), 10);
      if (!isNaN(m)) fp.changeMonth(m, false);
      closeCrmPanels(fp);
      syncCrmMonthYearLabels(fp);
    });

    yearPanel.addEventListener('click', function (e) {
      var nav = e.target.closest('[data-year-nav]');
      if (nav) {
        e.preventDefault();
        e.stopPropagation();
        var dir = nav.getAttribute('data-year-nav');
        var bounds = yearBounds(fp);
        var start = fp._crmYearPageStart || Math.floor(fp.currentYear / 12) * 12;
        if (dir === 'prev') start = Math.max(bounds.minY, start - 12);
        if (dir === 'next') start = Math.min(bounds.maxY, start + 12);
        fp._crmYearPageStart = start;
        renderYearPanel(fp);
        return;
      }
      var yBtn = e.target.closest('[data-year]');
      if (!yBtn) return;
      e.preventDefault();
      e.stopPropagation();
      var y = parseInt(yBtn.getAttribute('data-year'), 10);
      if (!isNaN(y)) fp.changeYear(y);
      closeCrmPanels(fp);
      syncCrmMonthYearLabels(fp);
    });

    fp.calendarContainer.addEventListener('mousedown', function (e) {
      if (e.target.closest('.flatpickr-crm-panel') || e.target.closest('.flatpickr-crm-my-btn')) {
        e.stopPropagation();
      }
    }, true);
  };

  /** Junta hooks CRM a um objeto de opções Flatpickr (preserva callbacks existentes). */
  window.withCrmFlatpickrEnhancements = function (opts) {
    opts = Object.assign({}, opts || {});
    function chain(key, fn) {
      var prev = opts[key];
      opts[key] = function () {
        var args = Array.prototype.slice.call(arguments);
        var instance = args[2] || this;
        try { fn(instance); } catch (err) { /* ignore */ }
        if (typeof prev === 'function') return prev.apply(this, args);
        if (Array.isArray(prev)) {
          for (var i = 0; i < prev.length; i++) {
            if (typeof prev[i] === 'function') prev[i].apply(this, args);
          }
        }
      };
    }
    chain('onReady', function (inst) { window.enhanceCrmFlatpickrInstance(inst); });
    chain('onOpen', function (inst) {
      window.enhanceCrmFlatpickrInstance(inst);
      syncCrmMonthYearLabels(inst);
    });
    chain('onMonthChange', function (inst) {
      syncCrmMonthYearLabels(inst);
      closeCrmPanels(inst);
    });
    chain('onYearChange', function (inst) {
      syncCrmMonthYearLabels(inst);
      closeCrmPanels(inst);
    });
    chain('onClose', function (inst) { closeCrmPanels(inst); });
    return opts;
  };

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

    var dateOpts = window.withCrmFlatpickrEnhancements(Object.assign({}, baseOpts(), {
      dateFormat: 'Y-m-d'
    }));

    root.querySelectorAll('[data-crm-datepicker]').forEach(function (el) {
      if (el._flatpickr) {
        window.enhanceCrmFlatpickrInstance(el._flatpickr);
        return;
      }
      var opts = Object.assign({}, dateOpts);
      var max = el.getAttribute('data-max-date');
      if (max) opts.maxDate = max;
      var min = el.getAttribute('data-min-date');
      if (min) opts.minDate = min;
      flatpickr(el, opts);
    });

    root.querySelectorAll(
      'input[type="text"][name="marcacoes_desde"], input[type="text"][name="marcacoes_ate"], input[type="text"][name="vendas_desde"], input[type="text"][name="vendas_ate"], input[type="text"][name="comissoes_desde"], input[type="text"][name="comissoes_ate"], input[type="text"][name="sms_desde"], input[type="text"][name="sms_ate"]'
    ).forEach(function (el) {
      if (el._flatpickr) {
        window.enhanceCrmFlatpickrInstance(el._flatpickr);
        return;
      }
      if (el.type === 'hidden') return;
      flatpickr(el, Object.assign({}, dateOpts));
    });

    var dtOpts = window.withCrmFlatpickrEnhancements(Object.assign({}, baseOpts(), {
      enableTime: true,
      time_24hr: true,
      dateFormat: 'Y-m-d\\TH:i'
    }));

    root.querySelectorAll('[data-crm-datetime]').forEach(function (el) {
      if (el._flatpickr) {
        window.enhanceCrmFlatpickrInstance(el._flatpickr);
        return;
      }
      flatpickr(el, Object.assign({}, dtOpts));
    });
  };

  document.addEventListener('DOMContentLoaded', function () {
    window.initCrmFlatpickr(document);
  });
})();
