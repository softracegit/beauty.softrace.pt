@extends('definicoes.layout')

@section('definicoes_content')
  <form method="post" action="{{ route('definicoes.equipa.update') }}" id="definicoes-equipa-form">
    @csrf

    <div class="card mb-4">
      <div class="card-body">
        @include('stores.partials.operational-equipa-fields', [
          'agents' => $agents ?? collect(),
          'roles' => $roles ?? [],
          'showStoreColumn' => $showStoreColumn ?? true,
          'idPrefix' => 'def_',
          'showPersonalTimeSection' => false,
        ])
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <a href="{{ route('services.tecnicos') }}" class="card h-100 text-decoration-none text-body">
          <div class="card-body d-flex align-items-center justify-content-between gap-3">
            <h5 class="card-title mb-0">Serviços por equipa</h5>
            <i class="ph ph-arrow-square-out" aria-hidden="true"></i>
          </div>
        </a>
      </div>
      <div class="col-md-6">
        <a href="{{ route('services.extras-equipa') }}" class="card h-100 text-decoration-none text-body">
          <div class="card-body d-flex align-items-center justify-content-between gap-3">
            <h5 class="card-title mb-0">Extras por equipa</h5>
            <i class="ph ph-arrow-square-out" aria-hidden="true"></i>
          </div>
        </a>
      </div>
    </div>

    <div class="d-flex justify-content-end">
      <button type="submit" class="btn btn-primary">Guardar alterações</button>
    </div>
  </form>
@endsection
