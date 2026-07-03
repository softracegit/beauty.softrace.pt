@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Assistente AI').' — '.config('app.name'))

@section('css')
  <link rel="stylesheet" href="{{ asset('template/css/ai-assistant.css') }}?v={{ file_exists(public_path('template/css/ai-assistant.css')) ? filemtime(public_path('template/css/ai-assistant.css')) : time() }}">
@endsection

@section('content')
  <div class="ai-assistant-page">
  <div class="ai-assistant-shell card border-0 shadow-sm">
    <div class="ai-assistant-header">
      <div>
        <h2 class="ai-assistant-title mb-1">Assistente AI</h2>
        <p class="ai-assistant-subtitle mb-0 text-muted">Pergunte sobre os dados da loja: duplicados de clientes, relatórios de vendas em PDF, e mais.</p>
      </div>
      @unless($aiConfigured ?? false)
        <span class="badge text-bg-warning">API não configurada</span>
      @endunless
    </div>

    <div id="aiChatMessages" class="ai-assistant-messages" aria-live="polite">
      <div class="ai-message ai-message-assistant">
        <div class="ai-message-avatar" aria-hidden="true"><i class="ph ph-sparkle"></i></div>
        <div class="ai-message-bubble">
          <p class="mb-2">Olá! Posso ajudar a analisar dados da loja.</p>
          <p class="mb-0 text-muted small">Experimente: <em>«Identifica clientes duplicados»</em>, <em>«PDF de vendas do mês passado»</em> ou <em>«Vendas em rascunho dos últimos 6 meses»</em></p>
        </div>
      </div>
    </div>

    <form id="aiChatForm" class="ai-assistant-composer">
      <div class="ai-assistant-input-wrap">
        <textarea
          id="aiChatInput"
          class="form-control ai-assistant-input"
          rows="1"
          placeholder="Escreva a sua pergunta..."
          maxlength="4000"
          @unless($aiConfigured ?? false) disabled @endunless
        ></textarea>
        <button type="submit" class="btn btn-primary ai-assistant-send" @unless($aiConfigured ?? false) disabled @endunless aria-label="Enviar">
          <i class="ph ph-paper-plane-tilt"></i>
        </button>
      </div>
      @unless($aiConfigured ?? false)
        <p class="ai-assistant-hint text-muted small mb-0 mt-2">
          Configure <code>AI_ASSISTANT_API_KEY</code> no <code>.env</code> (ex.: Groq ou OpenRouter). Reinicie a aplicação depois de guardar.
        </p>
      @endunless
    </form>
  </div>
  </div>
@endsection

@section('js')
  <script>
    window.CrmAiAssistant = {
      chatUrl: @json(route('ai.chat')),
      clientesShowUrl: @json(url('clientes')),
      configured: @json((bool) ($aiConfigured ?? false)),
    };
  </script>
  <script src="{{ asset('template/js/ai-assistant.js') }}?v={{ file_exists(public_path('template/js/ai-assistant.js')) ? filemtime(public_path('template/js/ai-assistant.js')) : time() }}"></script>
@endsection
