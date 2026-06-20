<script>
  (function () {
    function initVendasDropdowns() {
      if (typeof bootstrap === 'undefined' || !bootstrap.Dropdown) return;
      document.querySelectorAll('.js-vendas-dropdown').forEach(function (btn) {
        bootstrap.Dropdown.getOrCreateInstance(btn, {
          boundary: 'viewport',
          popperConfig: function (defaultBsPopperConfig) {
            return Object.assign({}, defaultBsPopperConfig, { strategy: 'fixed' });
          },
        });
      });
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initVendasDropdowns);
    } else {
      initVendasDropdowns();
    }

    document.body.addEventListener('click', function (e) {
      var finalizeBtn = e.target.closest('.js-sale-finalize');
      if (finalizeBtn) {
        e.preventDefault();
        var saleId = finalizeBtn.getAttribute('data-sale-id');
        if (!saleId || typeof window.openSaleFinalizeInvoiceModal !== 'function') return;
        var dropdown = finalizeBtn.closest('.dropdown');
        if (dropdown && typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
          var toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
          if (toggle) bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        }
        window.openSaleFinalizeInvoiceModal({
          id: parseInt(saleId, 10),
          amount: parseFloat(finalizeBtn.getAttribute('data-sale-amount')) || 0,
          client_name: finalizeBtn.getAttribute('data-client-name') || '',
          client_email: finalizeBtn.getAttribute('data-client-email') || '',
          client_phone: finalizeBtn.getAttribute('data-client-phone') || '',
          client_nif: finalizeBtn.getAttribute('data-client-nif') || '',
          modal_label: finalizeBtn.getAttribute('data-modal-label') || 'Faturar',
        });
        return;
      }

      var btn = e.target.closest('.js-sale-revert');
      if (!btn) return;
      e.preventDefault();
      var url = btn.getAttribute('data-revert-url');
      if (!url) return;
      if (!window.confirm('Anular esta venda e registar nota de crédito? A marcação volta ao estado editável.')) return;
      var token = document.querySelector('meta[name="csrf-token"]');
      token = token ? token.getAttribute('content') : '';
      btn.disabled = true;
      fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': token,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: '{}'
      })
        .then(function (r) { return r.json().then(function (res) { return { ok: r.ok, res: res }; }); })
        .then(function (x) {
          btn.disabled = false;
          if (!x.ok || !x.res.success) {
            var msg = (x.res && (x.res.error || x.res.message)) || 'Erro ao anular.';
            if (window.showToast) window.showToast(msg, 'error'); else alert(msg);
            return;
          }
          if (window.showToast) window.showToast(x.res.message || 'Venda anulada.', 'success');
          window.location.reload();
        })
        .catch(function () {
          btn.disabled = false;
          if (window.showToast) window.showToast('Erro de ligação.', 'error'); else alert('Erro de ligação.');
        });
    });
  })();
</script>
<script>
  window.SALE_FINALIZE_CONFIG = {
    csrf: @json(csrf_token()),
    finalizeInvoiceBaseUrl: @json(url('sales')),
  };
</script>
<script src="{{ asset('template/js/sale-finalize-invoice-modal.js') }}?v={{ file_exists(public_path('template/js/sale-finalize-invoice-modal.js')) ? filemtime(public_path('template/js/sale-finalize-invoice-modal.js')) : time() }}"></script>
