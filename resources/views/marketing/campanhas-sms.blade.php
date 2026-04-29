@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Campanhas SMS').' — '.config('app.name'))

@section('content')
  <div class="dash-welcome mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
      <div class="dash-welcome-content mb-0 flex-grow-1 min-w-0">
        <h2 class="dash-welcome-title mb-1">Campanhas SMS</h2>
        <p class="text-muted small mb-0">Envio de teste via Twilio (remetente configurado no .env).</p>
      </div>
    </div>
  </div>

  @if (!($twilioConfigured ?? false))
    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
      <i class="ph ph-warning-circle flex-shrink-0 mt-1"></i>
      <div>
        <strong>Twilio por configurar.</strong> Adiciona ao <code>.env</code>:
        <code class="d-block small mt-1">TWILIO_ACCOUNT_SID</code>
        <code class="d-block small">TWILIO_AUTH_TOKEN</code>
        <code class="d-block small">TWILIO_SMS_FROM</code> <span class="text-muted">(ex.: Sender ID alfanumérico ou número E.164)</span>
      </div>
    </div>
  @else
    <div class="alert alert-light border d-flex align-items-center gap-2 mb-4" role="status">
      <i class="ph ph-paper-plane-tilt text-primary"></i>
      <span class="small">Remetente (From): <strong>{{ $smsFrom }}</strong></span>
    </div>
  @endif

  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <form method="post" action="{{ route('marketing.campanhas-sms.send') }}" class="crm-sms-test-form">
        @csrf
        <div class="mb-3">
          <label for="sms_phone" class="form-label">Telemóvel de destino</label>
          <input
            type="text"
            name="phone"
            id="sms_phone"
            class="form-control @error('phone') is-invalid @enderror"
            value="{{ old('phone') }}"
            placeholder="+351912345678 ou 912345678"
            autocomplete="tel"
            {{ !($twilioConfigured ?? false) ? 'disabled' : '' }}
            required
          >
          @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <div class="form-text">Formato internacional ou nacional PT; o sistema normaliza para E.164.</div>
        </div>
        <div class="mb-4">
          <label for="sms_message" class="form-label">Mensagem</label>
          <textarea
            name="message"
            id="sms_message"
            rows="5"
            class="form-control @error('message') is-invalid @enderror"
            maxlength="2000"
            placeholder="Texto do SMS de teste…"
            {{ !($twilioConfigured ?? false) ? 'disabled' : '' }}
            required
          >{{ old('message') }}</textarea>
          @error('message')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <div class="form-text">Máx. 2000 caracteres (mensagens longas são segmentadas pela rede).</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-primary" {{ !($twilioConfigured ?? false) ? 'disabled' : '' }}>
            <i class="ph ph-paper-plane-right me-1"></i> Enviar SMS
          </button>
        </div>
      </form>
    </div>
  </div>
@endsection

@section('js')
  @if (session('success'))
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.showToast === 'function') {
          window.showToast(@json(session('success')), 'success');
        }
      });
    </script>
  @endif
  @if (session('error'))
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.showToast === 'function') {
          window.showToast(@json(session('error')), 'error');
        }
      });
    </script>
  @endif
@endsection
