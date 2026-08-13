@extends('definicoes.layout')

@section('definicoes_content')
  @php
    $methods = collect($paymentMethods ?? []);
    $manualMethods = $methods->filter(fn ($m) => in_array($m['provider'] ?? '', ['manual', 'wallet'], true))->values();
    $stripeMethods = $methods->filter(fn ($m) => ($m['provider'] ?? '') === 'stripe')->values();
    $stripeReady = (bool) ($stripeReady ?? false);
    $channelMeta = [
      'agenda' => ['label' => 'Agenda'],
      'booking' => ['label' => 'Booking'],
    ];
    $openStripeModal = $errors->has('stripe_publishable_key')
      || $errors->has('stripe_secret_key')
      || $errors->has('stripe_webhook_secret')
      || old('stripe_modal_open') === '1';
  @endphp

  <style>
    .pay-stripe-brand {
      display: inline-flex;
      align-items: center;
      gap: 0.65rem;
    }
    .pay-stripe-brand__mark {
      width: 3.75rem;
      height: auto;
      color: #635BFF;
      flex-shrink: 0;
      display: block;
    }
    .pay-stripe-brand__name {
      font-weight: 650;
      letter-spacing: -0.02em;
      line-height: 1.15;
    }
    .pay-settings .pay-status-active {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      color: #166534;
      font-size: 0.8125rem;
      font-weight: 600;
    }
    .pay-settings .pay-status-active i {
      font-size: 1rem;
    }
    .pay-settings .pay-section-lead {
      margin: 0.2rem 0 0;
      font-size: 0.8rem;
      color: var(--bs-secondary-color);
    }
    .pay-settings .pay-methods {
      display: grid;
      gap: 0.65rem;
    }
    .pay-settings .pay-method {
      display: grid;
      grid-template-columns: minmax(0, 1.4fr) minmax(160px, 0.65fr);
      gap: 0.85rem 1rem;
      align-items: center;
      padding: 0.85rem 1rem;
      border: 1px solid var(--bs-border-color);
      border-radius: 0.5rem;
      background: var(--bs-body-bg);
    }
    .pay-settings .pay-method__main {
      display: flex;
      gap: 0.85rem;
      align-items: flex-start;
      min-width: 0;
    }
    .pay-settings .pay-method__glyph {
      width: 2.75rem;
      height: 2.75rem;
      border-radius: 0.55rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.4rem;
      flex-shrink: 0;
      background: var(--bs-secondary-bg);
      color: var(--bs-body-color);
    }
    .pay-settings .pay-method__glyph--manual {
      background: rgba(15, 118, 110, 0.1);
      color: #0f766e;
    }
    .pay-settings .pay-method__glyph--stripe {
      background: rgba(99, 91, 255, 0.1);
      color: #635BFF;
    }
    .pay-settings .pay-method__glyph--wallet {
      background: rgba(202, 138, 4, 0.12);
      color: #a16207;
    }
    .pay-settings .pay-method__name {
      margin: 0;
      font-weight: 600;
      font-size: 0.95rem;
    }
    .pay-settings .pay-method__desc {
      margin: 0.15rem 0 0;
      font-size: 0.8rem;
      color: var(--bs-secondary-color);
      line-height: 1.4;
    }
    .pay-settings .pay-channels {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.75rem;
    }
    .pay-settings .pay-channel {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 0.35rem;
      padding: 0.15rem 0;
      text-align: center;
      min-width: 0;
      margin: 0;
      cursor: pointer;
    }
    .pay-settings .pay-channel__label {
      font-size: 0.75rem;
      font-weight: 600;
      line-height: 1.1;
    }
    .pay-settings .pay-stripe-empty {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
      padding: 0.5rem 0 0.15rem;
    }
    .pay-settings .pay-stripe-empty__text {
      margin: 0;
      font-size: 0.875rem;
      color: var(--bs-secondary-color);
      max-width: 32rem;
      line-height: 1.45;
    }
    .pay-settings .pay-stripe-empty__cta {
      min-width: 11rem;
      padding: 0.65rem 1.35rem;
      font-weight: 600;
    }
    @media (max-width: 767.98px) {
      .pay-settings .pay-method {
        grid-template-columns: 1fr;
      }
    }
  </style>

  <form method="post" action="{{ route('definicoes.pagamentos.update') }}" class="pay-settings">
    @csrf
    @php $methodIndex = 0; @endphp

    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">Métodos de pagamento manuais</h5>
        <p class="pay-section-lead">Registo interno — sem cobrança automática</p>
      </div>
      <div class="card-body">
        <div class="pay-methods">
          @foreach($manualMethods as $method)
            @include('definicoes.partials.payment-method-card', [
              'method' => $method,
              'index' => $methodIndex,
              'channelMeta' => $channelMeta,
              'stripeReady' => true,
            ])
            @php $methodIndex++; @endphp
          @endforeach
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
          <div class="pay-stripe-brand">
            @include('definicoes.partials.stripe-logo', ['class' => 'pay-stripe-brand__mark'])
          </div>
          <p class="pay-section-lead">Cobrança automática via Stripe</p>
        </div>
        @if($stripeReady)
          <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#stripeConfigModal">
              <i class="ph ph-gear me-1" aria-hidden="true"></i> Definições
            </button>
            <span class="pay-status-active">
              <i class="ph ph-check-circle" aria-hidden="true"></i> Ativo
            </span>
          </div>
        @endif
      </div>
      <div class="card-body">
        @if($stripeReady)
          <div class="pay-methods">
            @foreach($stripeMethods as $method)
              @include('definicoes.partials.payment-method-card', [
                'method' => $method,
                'index' => $methodIndex,
                'channelMeta' => $channelMeta,
                'stripeReady' => true,
              ])
              @php $methodIndex++; @endphp
            @endforeach
          </div>
        @else
          {{-- Preserva flags dos métodos Stripe enquanto a integração está desligada --}}
          @foreach($stripeMethods as $method)
            <input type="hidden" name="methods[{{ $methodIndex }}][code]" value="{{ $method['code'] }}">
            <input type="hidden" name="methods[{{ $methodIndex }}][sort]" value="{{ $method['sort'] }}">
            <input type="hidden" name="methods[{{ $methodIndex }}][agenda]" value="{{ ($method['agenda'] ?? false) ? '1' : '0' }}">
            <input type="hidden" name="methods[{{ $methodIndex }}][booking]" value="{{ ($method['booking'] ?? false) ? '1' : '0' }}">
            @php $methodIndex++; @endphp
          @endforeach
          <div class="pay-stripe-empty">
            <p class="pay-stripe-empty__text">
              Ative o Stripe para configurar os métodos de pagamento automáticos.
            </p>
            <button type="button" class="btn btn-primary pay-stripe-empty__cta" data-bs-toggle="modal" data-bs-target="#stripeConfigModal">
              Ativar Stripe
            </button>
          </div>
        @endif
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">Comportamento</h5>
      </div>
      <div class="card-body">
        <div class="py-3 border-bottom">
          <div class="d-flex align-items-center justify-content-between gap-3">
            <label class="fw-semibold mb-0" for="pos_gorjeta_enabled" style="cursor: pointer">Gorjeta na caixa</label>
            <div class="form-check form-switch m-0 flex-shrink-0">
              <input type="hidden" name="pos_gorjeta_enabled" value="0">
              <input class="form-check-input" type="checkbox" name="pos_gorjeta_enabled" value="1" id="pos_gorjeta_enabled" @checked($posGorjetaEnabled)>
            </div>
          </div>
          <p class="small text-muted mb-0 mt-2">
            Quando desligada, o campo de gorjeta deixa de aparecer no modal de pagamento da agenda.
          </p>
        </div>

        <div class="py-3">
          <div class="d-flex align-items-center justify-content-between gap-3">
            <label class="fw-semibold mb-0" for="online_booking_payment_required" style="cursor: pointer">Pagamento nas marcações online</label>
            <div class="form-check form-switch m-0 flex-shrink-0">
              <input type="hidden" name="online_booking_payment_required" value="0">
              <input class="form-check-input" type="checkbox" name="online_booking_payment_required" value="1" id="online_booking_payment_required" @checked($onlineBookingPaymentRequired)>
            </div>
          </div>
          <p class="small text-muted mb-0 mt-2">
            Exige pagamento no site público de marcações.
          </p>
        </div>
      </div>
    </div>

    <div class="uedit-form-actions">
      <button type="submit" class="btn btn-primary">
        <i class="ph ph-check me-1"></i> Guardar alterações
      </button>
    </div>
  </form>

  {{-- Modal Stripe --}}
  <div class="modal fade" id="stripeConfigModal" tabindex="-1" aria-labelledby="stripeConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="post" action="{{ route('definicoes.pagamentos.stripe.update') }}">
          @csrf
          <input type="hidden" name="stripe_modal_open" value="1">
          <div class="modal-header">
            <div class="pay-stripe-brand">
              @include('definicoes.partials.stripe-logo', ['class' => 'pay-stripe-brand__mark'])
              <h5 class="modal-title" id="stripeConfigModalLabel">
                {{ $stripeReady ? 'Configurar' : 'Ativar' }}
              </h5>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <p class="small text-muted mb-3">
              Preencha as três chaves da conta Stripe desta loja.
              Webhook: <code class="user-select-all">{{ $stripeWebhookUrl ?? url('/stripe/webhook') }}</code>
            </p>

            <div class="mb-3">
              <label for="stripe_publishable_key" class="form-label fw-semibold">Publishable key <span class="text-danger">*</span></label>
              <input
                type="text"
                class="form-control font-monospace @error('stripe_publishable_key') is-invalid @enderror"
                id="stripe_publishable_key"
                name="stripe_publishable_key"
                value="{{ old('stripe_publishable_key', $stripePublishableKey ?? '') }}"
                placeholder="pk_live_… ou pk_test_…"
                autocomplete="off"
                spellcheck="false"
                required
              >
              @error('stripe_publishable_key')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="stripe_secret_key" class="form-label fw-semibold">Secret key <span class="text-danger">*</span></label>
              <input
                type="password"
                class="form-control font-monospace @error('stripe_secret_key') is-invalid @enderror"
                id="stripe_secret_key"
                name="stripe_secret_key"
                value=""
                placeholder="{{ ($stripeHasSecret ?? false) ? ('Guardada: '.($stripeSecretMasked ?? '')) : 'sk_live_… ou sk_test_…' }}"
                autocomplete="new-password"
                spellcheck="false"
                @required(!($stripeHasSecret ?? false))
              >
              @if($stripeHasSecret ?? false)
                <div class="form-text">Deixe em branco para manter a chave actual.</div>
              @endif
              @error('stripe_secret_key')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-0">
              <label for="stripe_webhook_secret" class="form-label fw-semibold">Webhook secret <span class="text-danger">*</span></label>
              <input
                type="password"
                class="form-control font-monospace @error('stripe_webhook_secret') is-invalid @enderror"
                id="stripe_webhook_secret"
                name="stripe_webhook_secret"
                value=""
                placeholder="{{ ($stripeHasWebhook ?? false) ? ('Guardado: '.($stripeWebhookMasked ?? '')) : 'whsec_…' }}"
                autocomplete="new-password"
                spellcheck="false"
                @required(!($stripeHasWebhook ?? false))
              >
              @if($stripeHasWebhook ?? false)
                <div class="form-text">Deixe em branco para manter o secret actual.</div>
              @endif
              @error('stripe_webhook_secret')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="modal-footer justify-content-between">
            @if($stripeReady)
              <button type="submit" name="stripe_action" value="disable" class="btn btn-outline-danger" formnovalidate>
                Desativar
              </button>
            @else
              <span></span>
            @endif
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" name="stripe_action" value="save" class="btn btn-primary">
                {{ $stripeReady ? 'Guardar' : 'Ativar' }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      @if($openStripeModal)
        var modalEl = document.getElementById('stripeConfigModal');
        if (modalEl && window.bootstrap && bootstrap.Modal) {
          bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
      @endif

      @if (session('status'))
        if (typeof window.showToast === 'function') {
          window.showToast(@json(session('status')), 'success');
        }
      @endif
    });
  </script>
@endsection
