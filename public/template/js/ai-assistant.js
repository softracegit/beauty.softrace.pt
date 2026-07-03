(function () {
  'use strict';

  var config = window.CrmAiAssistant || {};
  var form = document.getElementById('aiChatForm');
  var input = document.getElementById('aiChatInput');
  var messagesEl = document.getElementById('aiChatMessages');

  if (!form || !input || !messagesEl || !config.chatUrl) {
    return;
  }

  var history = [];
  var sending = false;

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatMultiline(text) {
    return escapeHtml(text).replace(/\n/g, '<br>');
  }

  function clientUrl(id) {
    var base = String(config.clientesShowUrl || '/clientes').replace(/\/$/, '');
    return base + '/' + encodeURIComponent(id);
  }

  function confidenceClass(level) {
    if (level === 'alta') return 'ai-confidence-alta';
    if (level === 'media') return 'ai-confidence-media';
    return 'ai-confidence-baixa';
  }

  function renderVendasPdfActions(data) {
    if (!data || !data.download_url) {
      return '';
    }

    return '<div class="ai-assistant-actions mt-3">'
      + '<a href="' + escapeHtml(data.download_url) + '" class="btn btn-sm btn-primary" target="_blank" rel="noopener">'
      + '<i class="ph ph-file-pdf me-1"></i> Descarregar PDF</a>'
      + (data.relatorios_url
        ? ' <a href="' + escapeHtml(data.relatorios_url) + '" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Ver no relatório</a>'
        : '')
      + '</div>';
  }

  function renderDuplicateTable(data) {
    var pairs = data && Array.isArray(data.pairs) ? data.pairs : [];
    if (!pairs.length) {
      return '';
    }

    var rows = pairs.map(function (pair) {
      return '<tr>'
        + '<td>' + escapeHtml(pair.name || '—') + '</td>'
        + '<td><a href="' + clientUrl(pair.client_a_id) + '">#' + escapeHtml(pair.client_a_id) + '</a><br><span class="text-muted">' + escapeHtml(pair.client_a_phone || '—') + '</span></td>'
        + '<td><a href="' + clientUrl(pair.client_b_id) + '">#' + escapeHtml(pair.client_b_id) + '</a><br><span class="text-muted">' + escapeHtml(pair.client_b_phone || '—') + '</span></td>'
        + '<td>' + escapeHtml(pair.reason || '—') + '</td>'
        + '<td><span class="' + confidenceClass(pair.confidence) + '">' + escapeHtml(pair.confidence || '—') + '</span></td>'
        + '<td>' + escapeHtml(String(pair.client_a_appointments)) + ' / ' + escapeHtml(String(pair.client_b_appointments)) + '</td>'
        + '<td>' + escapeHtml(String(pair.client_a_sales)) + ' / ' + escapeHtml(String(pair.client_b_sales)) + '</td>'
        + '<td>' + (pair.from_zappy ? 'Sim' : '—') + '</td>'
        + '</tr>';
    }).join('');

    return '<div class="ai-assistant-table-wrap"><table class="ai-assistant-table">'
      + '<thead><tr>'
      + '<th>Nome</th><th>Cliente A</th><th>Cliente B</th><th>Motivo</th><th>Conf.</th><th>Marc. A/B</th><th>Vendas A/B</th><th>Zappy</th>'
      + '</tr></thead><tbody>' + rows + '</tbody></table></div>';
  }

  function appendMessage(role, html, extraClass) {
    var wrapper = document.createElement('div');
    wrapper.className = 'ai-message ai-message-' + role + (extraClass ? ' ' + extraClass : '');

    var avatar = document.createElement('div');
    avatar.className = 'ai-message-avatar';
    avatar.setAttribute('aria-hidden', 'true');
    avatar.innerHTML = role === 'user'
      ? '<i class="ph ph-user"></i>'
      : '<i class="ph ph-sparkle"></i>';

    var bubble = document.createElement('div');
    bubble.className = 'ai-message-bubble';
    bubble.innerHTML = html;

    wrapper.appendChild(avatar);
    wrapper.appendChild(bubble);
    messagesEl.appendChild(wrapper);
    messagesEl.scrollTop = messagesEl.scrollHeight;

    return wrapper;
  }

  function setLoading(active) {
    var existing = messagesEl.querySelector('.ai-message-loading');
    if (!active) {
      if (existing) existing.remove();
      return;
    }
    if (existing) return;

    appendMessage(
      'assistant',
      '<span>A analisar</span><span class="ai-typing-dots" aria-hidden="true"><span></span><span></span><span></span></span>',
      'ai-message-loading'
    );
  }

  function autoResizeInput() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 160) + 'px';
  }

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  async function sendMessage(text) {
    if (!config.configured || sending) {
      return;
    }

    sending = true;
    form.querySelector('.ai-assistant-send').disabled = true;
    input.disabled = true;

    appendMessage('user', '<p class="mb-0">' + formatMultiline(text) + '</p>');
    history.push({ role: 'user', content: text });
    setLoading(true);

    try {
      var response = await fetch(config.chatUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          message: text,
          messages: history.slice(0, -1),
        }),
      });

      var payload = await response.json();
      setLoading(false);

      if (!response.ok) {
        appendMessage(
          'assistant',
          '<p class="mb-0">' + escapeHtml(payload.error || 'Não foi possível obter resposta.') + '</p>',
          'ai-message-error'
        );
        return;
      }

      var html = '<p class="mb-0">' + formatMultiline(payload.reply || '') + '</p>';
      if (payload.tool === 'auditoria_clientes_duplicados' && payload.data) {
        html += renderDuplicateTable(payload.data);
      }
      if (payload.tool === 'relatorio_vendas_pdf' && payload.data) {
        html += renderVendasPdfActions(payload.data);
      }

      appendMessage('assistant', html);
      history.push({ role: 'assistant', content: payload.reply || '' });
    } catch (error) {
      setLoading(false);
      appendMessage(
        'assistant',
        '<p class="mb-0">Erro de ligação. Verifique a rede e tente novamente.</p>',
        'ai-message-error'
      );
    } finally {
      sending = false;
      input.disabled = !config.configured;
      form.querySelector('.ai-assistant-send').disabled = !config.configured;
      input.focus();
    }
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var text = input.value.trim();
    if (!text) return;
    input.value = '';
    autoResizeInput();
    sendMessage(text);
  });

  input.addEventListener('input', autoResizeInput);
  input.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      form.requestSubmit();
    }
  });

  autoResizeInput();
})();
