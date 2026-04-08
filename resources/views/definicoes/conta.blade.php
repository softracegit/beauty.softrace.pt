@extends('definicoes.layout')

@section('definicoes_content')
  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">Preferências da conta</h5>
    </div>
    <div class="card-body">
      <form method="post" action="{{ route('definicoes.conta.update') }}">
        @csrf

        <div class="notification-setting">
          <div class="notification-setting-info">
            <div class="notification-setting-title">Agenda: abrir marcações em offcanvas (teste)</div>
            <div class="notification-setting-desc">
              Quando ativo, a criação de marcações abre no painel lateral direito de teste, em vez do modal.
            </div>
          </div>
          <div class="d-flex align-items-center ms-md-auto">
            <div class="form-check form-switch m-0">
              <input
                class="form-check-input"
                type="checkbox"
                name="agenda_use_offcanvas_marcacao_test"
                value="1"
                id="agenda_use_offcanvas_marcacao_test"
                @checked($agendaUseOffcanvasMarcacaoTest)
              >
            </div>
          </div>
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
