@extends('definicoes.layout')

@section('definicoes_content')
  @if (session('status'))
    <div class="alert alert-success mb-3" role="alert">{{ session('status') }}</div>
  @endif

  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">Preferências de notificação</h5>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-4">Marcações: escolha, para cada tipo de alerta, se deseja notificação no CRM e/ou por email.</p>

      <form method="post" action="{{ route('definicoes.notificacoes.update') }}">
        @csrf

        <div class="d-none d-md-flex align-items-center justify-content-between pb-2 mb-2 border-bottom text-muted small fw-semibold">
          <div class="flex-grow-1">Tipo</div>
          <div class="d-flex ms-md-auto">
            <div class="text-end" style="width: 5.25rem;">CRM</div>
            <div class="text-end" style="width: 5.25rem;">Email</div>
          </div>
        </div>

        @foreach ($matrix as $key => $row)
          <div class="notification-setting flex-wrap gap-2 gap-md-0">
            <div class="notification-setting-info w-100 w-md-auto">
              <div class="notification-setting-title">{{ $row['label'] }}</div>
              <div class="notification-setting-desc">{{ $row['description'] }}</div>
            </div>
            <div class="d-flex align-items-center ms-md-auto">
              <div class="d-flex flex-column align-items-end" style="width: 5.25rem;">
                <span class="d-md-none text-muted small mb-1">CRM</span>
                <div class="form-check form-switch m-0 d-flex justify-content-end">
                  {{-- Sem hidden: o mesmo nome hidden+checkbox pode gerar array em PHP e estragar o guardado --}}
                  <input class="form-check-input" type="checkbox" name="bell[{{ $key }}]" value="1" id="bell_{{ $key }}" @checked($row['bell'])>
                </div>
              </div>
              <div class="d-flex flex-column align-items-end" style="width: 5.25rem;">
                <span class="d-md-none text-muted small mb-1">Email</span>
                <div class="form-check form-switch m-0 d-flex justify-content-end">
                  <input class="form-check-input" type="checkbox" name="email[{{ $key }}]" value="1" id="email_{{ $key }}" @checked($row['email'])>
                </div>
              </div>
            </div>
          </div>
        @endforeach

        <div class="uedit-form-actions pt-4 mt-2 border-top">
          <button type="submit" class="btn btn-primary">
            <i class="ph ph-check me-1"></i> Guardar alterações
          </button>
        </div>
      </form>
    </div>
  </div>
@endsection
