<script>
  (function () {
    var panelEl = document.getElementById('marcacaoDetalheModal');
    if (!panelEl) return;

    panelEl.addEventListener('show.bs.offcanvas', function (event) {
      var btn = event.relatedTarget;
      if (!btn || !btn.getAttribute('data-template-id')) return;
      var tid = btn.getAttribute('data-template-id');
      var tpl = document.getElementById(tid);
      var body = document.getElementById('marcacaoDetalheModalBody');
      var footer = document.getElementById('marcacaoDetalheModalFooter');
      if (!tpl || !body || !footer) return;
      body.innerHTML = '';
      footer.innerHTML = '';
      var frag = tpl.content.cloneNode(true);
      var bodyEl = frag.querySelector('.js-marcacao-modal-body');
      var footerEl = frag.querySelector('.js-marcacao-modal-footer');
      if (bodyEl) body.appendChild(bodyEl);
      if (footerEl) {
        while (footerEl.firstChild) {
          footer.appendChild(footerEl.firstChild);
        }
      }
    });

    document.addEventListener('keydown', function (e) {
      var trigger = e.target && e.target.closest
        ? e.target.closest('.js-marcacao-detail-trigger[data-bs-toggle="offcanvas"]')
        : null;
      if (!trigger) return;
      if (e.key !== 'Enter' && e.key !== ' ') return;
      e.preventDefault();
      trigger.click();
    });

    document.addEventListener('click', function (e) {
      var logTrigger = e.target && e.target.closest
        ? e.target.closest('.js-marcacao-activity-log-trigger')
        : null;
      if (!logTrigger) return;

      e.preventDefault();
      var url = String(logTrigger.getAttribute('data-activity-log-url') || '').trim();
      var logModalEl = document.getElementById('clienteMarcacaoActivityLogModal');
      var logBodyEl = document.getElementById('clienteMarcacaoActivityLogBody');
      if (!url || !logModalEl || !logBodyEl || typeof bootstrap === 'undefined') return;

      logBodyEl.innerHTML = '<p class="text-muted text-center py-3 mb-0"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>A carregar…</p>';
      bootstrap.Modal.getOrCreateInstance(logModalEl).show();

      fetch(url, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (r) {
          return r.json().then(function (res) {
            if (!r.ok) {
              throw new Error(res.message || res.error || 'Erro ao carregar logs.');
            }
            return res;
          });
        })
        .then(function (res) {
          logBodyEl.innerHTML = (res && res.html)
            ? res.html
            : '<p class="text-muted text-center py-3 mb-0">Nenhuma atividade registada.</p>';
        })
        .catch(function (err) {
          var msg = (err && err.message) ? String(err.message).replace(/</g, '&lt;') : 'Erro ao carregar logs.';
          logBodyEl.innerHTML = '<p class="text-danger text-center py-3 mb-0">' + msg + '</p>';
        });
    });

    var reativarModalEl = document.getElementById('reativarMarcacaoModal');
    if (!reativarModalEl) return;

    var loadingEl = document.getElementById('reativarMarcacaoLoading');
    var blockedEl = document.getElementById('reativarMarcacaoBlocked');
    var blockersEl = document.getElementById('reativarMarcacaoBlockers');
    var formEl = document.getElementById('reativarMarcacaoForm');
    var statusLabelEl = document.getElementById('reativarMarcacaoStatusLabel');
    var reasonEl = document.getElementById('reativarMarcacaoReason');
    var notifyEl = document.getElementById('reativarMarcacaoNotifyClient');
    var confirmBtn = document.getElementById('reativarMarcacaoConfirmBtn');
    var eventIdEl = document.getElementById('reativarMarcacaoEventId');
    var urlEl = document.getElementById('reativarMarcacaoUrl');
    var detailPanel = bootstrap.Offcanvas.getOrCreateInstance(panelEl);
    var reativarModal = bootstrap.Modal.getOrCreateInstance(reativarModalEl);

    function csrfToken() {
      var meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.getAttribute('content') : '';
    }

    function resetReativarModal() {
      if (loadingEl) loadingEl.classList.remove('d-none');
      if (blockedEl) blockedEl.classList.add('d-none');
      if (formEl) formEl.classList.add('d-none');
      if (confirmBtn) {
        confirmBtn.classList.add('d-none');
        confirmBtn.disabled = false;
      }
      if (blockersEl) blockersEl.innerHTML = '';
      if (reasonEl) reasonEl.value = '';
      if (notifyEl) notifyEl.checked = false;
    }

    function showBlocked(blockers) {
      if (loadingEl) loadingEl.classList.add('d-none');
      if (formEl) formEl.classList.add('d-none');
      if (confirmBtn) confirmBtn.classList.add('d-none');
      if (blockedEl) blockedEl.classList.remove('d-none');
      if (!blockersEl) return;
      blockersEl.innerHTML = '';
      (blockers || []).forEach(function (msg) {
        var li = document.createElement('li');
        li.textContent = msg;
        blockersEl.appendChild(li);
      });
    }

    function showForm(statusLabel) {
      if (loadingEl) loadingEl.classList.add('d-none');
      if (blockedEl) blockedEl.classList.add('d-none');
      if (formEl) formEl.classList.remove('d-none');
      if (confirmBtn) confirmBtn.classList.remove('d-none');
      if (statusLabelEl) statusLabelEl.textContent = statusLabel || '—';
    }

    panelEl.addEventListener('click', function (e) {
      var btn = e.target.closest('.js-reativar-marcacao');
      if (!btn) return;
      e.preventDefault();

      var previewUrl = btn.getAttribute('data-preview-url');
      var reativarUrl = btn.getAttribute('data-reativar-url');
      var statusLabel = btn.getAttribute('data-status-label') || '';
      var eventId = btn.getAttribute('data-event-id') || '';
      if (!previewUrl || !reativarUrl) return;

      resetReativarModal();
      if (eventIdEl) eventIdEl.value = eventId;
      if (urlEl) urlEl.value = reativarUrl;
      if (statusLabelEl) statusLabelEl.textContent = statusLabel;

      detailPanel.hide();
      reativarModal.show();

      fetch(previewUrl, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(function (r) {
          return r.json().then(function (res) {
            return { ok: r.ok, res: res };
          });
        })
        .then(function (x) {
          if (!x.ok || !x.res.success) {
            showBlocked([(x.res && (x.res.message || x.res.error)) || 'Não foi possível verificar a reativação.']);
            return;
          }
          if (!x.res.can_reactivate) {
            showBlocked(x.res.blockers && x.res.blockers.length ? x.res.blockers : ['Não é possível reativar esta marcação.']);
            return;
          }
          showForm(x.res.status_label || statusLabel);
        })
        .catch(function () {
          showBlocked(['Erro de ligação ao verificar a reativação.']);
        });
    });

    if (confirmBtn) {
      confirmBtn.addEventListener('click', function () {
        var url = urlEl ? urlEl.value : '';
        if (!url) return;
        confirmBtn.disabled = true;
        fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify({
            reactivation_reason: reasonEl ? reasonEl.value : '',
            notify_client: !!(notifyEl && notifyEl.checked)
          })
        })
          .then(function (r) {
            return r.json().then(function (res) {
              return { ok: r.ok, res: res };
            });
          })
          .then(function (x) {
            if (!x.ok || !x.res.success) {
              confirmBtn.disabled = false;
              if (x.res && x.res.blockers && x.res.blockers.length) {
                showBlocked(x.res.blockers);
                return;
              }
              var msg = (x.res && (x.res.message || x.res.error)) || 'Erro ao reativar.';
              if (window.showToast) window.showToast(msg, 'error'); else alert(msg);
              return;
            }
            if (window.showToast) window.showToast(x.res.message || 'Marcação reativada.', 'success');
            window.location.reload();
          })
          .catch(function () {
            confirmBtn.disabled = false;
            if (window.showToast) window.showToast('Erro de ligação.', 'error'); else alert('Erro de ligação.');
          });
      });
    }
  })();
</script>
