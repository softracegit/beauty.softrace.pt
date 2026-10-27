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

    <div class="d-flex justify-content-end">
      <button type="submit" class="btn btn-primary">Guardar alterações</button>
    </div>
  </form>
@endsection
