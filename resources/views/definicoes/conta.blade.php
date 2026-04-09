@extends('definicoes.layout')

@section('definicoes_content')
  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">Preferências da conta</h5>
    </div>
    <div class="card-body">
      <p class="text-muted mb-0">Neste momento não há preferências editáveis nesta página.</p>
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
