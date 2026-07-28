@extends('definicoes.layout')

@section('definicoes_content')
  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">Branding nos emails</h5>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-4">
        Opções de personalização dos emails enviados pelo CRM (confirmações, lembretes, faturas, etc.).
      </p>

      <form method="post" action="{{ route('definicoes.emails.update') }}">
        @csrf

        <div class="py-3">
          <div class="d-flex align-items-center justify-content-between gap-3">
            <label class="fw-semibold mb-0" for="email_use_business_branding" style="cursor: pointer">
              Usar dados do negócio nos emails
            </label>
            <div class="form-check form-switch m-0 flex-shrink-0">
              <input type="hidden" name="email_use_business_branding" value="0">
              <input
                class="form-check-input"
                type="checkbox"
                name="email_use_business_branding"
                value="1"
                id="email_use_business_branding"
                @checked(old('email_use_business_branding', $emailUseBusinessBranding ?? false))
              >
            </div>
          </div>
          <p class="small text-muted mb-0 mt-2 w-100">
            Quando activo, os emails usam o logotipo e o nome definidos em Negócio.
            Quando desactivado, usam o logotipo e o nome da aplicação.
          </p>
        </div>

        <div class="uedit-form-actions pt-4 mt-2 border-top">
          <button type="submit" class="btn btn-primary">
            <i class="ph ph-check me-1"></i> Guardar alterações
          </button>
        </div>
      </form>
    </div>
  </div>

  @if (session('status'))
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.showToast === 'function') {
          window.showToast(@json(session('status')), 'success');
        }
      });
    </script>
  @endif
@endsection
