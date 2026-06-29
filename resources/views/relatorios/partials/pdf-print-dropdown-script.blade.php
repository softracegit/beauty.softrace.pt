<script>
  (function () {
    document.querySelectorAll('.js-relatorio-pdf-print').forEach(function (root) {
      var scope = root.getAttribute('data-scope') || 'relatorio';
      var colsParam = root.getAttribute('data-cols-param') || 'pdf_cols';
      var orientationParam = root.getAttribute('data-orientation-param') || 'pdf_orientation';
      var columnOrder = [];
      try {
        columnOrder = JSON.parse(root.getAttribute('data-column-order') || '[]');
      } catch (e) {}
      if (!Array.isArray(columnOrder)) columnOrder = [];

      var storageColsKey = scope + '_pdf_cols';
      var storageOrientationKey = scope + '_pdf_orientation';
      var printLink = root.querySelector('.js-relatorio-pdf-print-link');
      var colsWarning = root.querySelector('.js-relatorio-pdf-cols-warning');
      var colCheckboxes = root.querySelectorAll('.js-relatorio-pdf-col');
      var orientationInputs = root.querySelectorAll('.js-relatorio-pdf-orientation');

      function readPdfColsPreference() {
        try {
          var stored = localStorage.getItem(storageColsKey);
          if (!stored) return null;
          var parsed = stored.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
          return parsed.length ? parsed : null;
        } catch (e) {}
        return null;
      }

      function savePdfColsPreference(cols) {
        try {
          localStorage.setItem(storageColsKey, cols.join(','));
        } catch (e) {}
      }

      function readPdfOrientationPreference() {
        try {
          var stored = localStorage.getItem(storageOrientationKey);
          if (stored === 'portrait' || stored === 'landscape') return stored;
        } catch (e) {}
        return 'landscape';
      }

      function savePdfOrientationPreference(orientation) {
        try {
          localStorage.setItem(storageOrientationKey, orientation);
        } catch (e) {}
      }

      function getSelectedPdfOrientation() {
        var selected = 'landscape';
        orientationInputs.forEach(function (input) {
          if (input.checked) selected = input.value;
        });
        return selected === 'portrait' ? 'portrait' : 'landscape';
      }

      function getSelectedPdfCols() {
        var selected = [];
        colCheckboxes.forEach(function (input) {
          if (input.checked) selected.push(input.value);
        });
        return columnOrder.filter(function (key) {
          return selected.indexOf(key) !== -1;
        });
      }

      function applyPdfColsPreference() {
        var preferred = readPdfColsPreference();
        if (!preferred) return;
        colCheckboxes.forEach(function (input) {
          input.checked = preferred.indexOf(input.value) !== -1;
        });
      }

      function applyPdfOrientationPreference() {
        var preferred = readPdfOrientationPreference();
        orientationInputs.forEach(function (input) {
          input.checked = input.value === preferred;
        });
      }

      function syncPrintLink() {
        if (!printLink) return;
        var baseHref = printLink.getAttribute('data-base-href') || printLink.getAttribute('href');
        try {
          var url = new URL(baseHref, window.location.origin);
          var cols = getSelectedPdfCols();
          if (cols.length) {
            url.searchParams.set(colsParam, cols.join(','));
          } else {
            url.searchParams.delete(colsParam);
          }
          url.searchParams.set(orientationParam, getSelectedPdfOrientation());
          printLink.setAttribute('href', url.pathname + url.search);
          printLink.classList.toggle('disabled', cols.length === 0);
          printLink.setAttribute('aria-disabled', cols.length === 0 ? 'true' : 'false');
          if (colsWarning) colsWarning.classList.toggle('d-none', cols.length > 0);
        } catch (e) {}
      }

      applyPdfColsPreference();
      applyPdfOrientationPreference();
      syncPrintLink();

      colCheckboxes.forEach(function (input) {
        input.addEventListener('change', function () {
          savePdfColsPreference(getSelectedPdfCols());
          syncPrintLink();
        });
      });

      orientationInputs.forEach(function (input) {
        input.addEventListener('change', function () {
          savePdfOrientationPreference(getSelectedPdfOrientation());
          syncPrintLink();
        });
      });

      if (printLink) {
        printLink.addEventListener('click', function (event) {
          if (getSelectedPdfCols().length === 0) {
            event.preventDefault();
          }
        });
      }
    });
  })();
</script>
