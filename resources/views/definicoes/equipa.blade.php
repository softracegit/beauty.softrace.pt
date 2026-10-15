@extends('definicoes.layout')

@section('definicoes_content')
  @if (!empty($settingsScopeNote))
    <div class="alert alert-light border mb-4 small">
      <i class="ph ph-buildings me-1" aria-hidden="true"></i>{{ $settingsScopeNote }}
    </div>
  @endif

  <form method="post" action="{{ route('definicoes.equipa.update') }}" id="definicoes-equipa-form">
    @csrf
    <div class="card">
      <div class="card-body">
        @include('stores.partials.operational-equipa-fields', [
          'agents' => $agents ?? collect(),
          'roles' => $roles ?? [],
          'personalTimeLimitStoreHours' => $personalTimeLimitStoreHours ?? false,
          'storeHoursLabel' => $storeHoursLabel ?? '09:00–20:00',
          'showStoreColumn' => $showStoreColumn ?? true,
          'idPrefix' => 'def_',
        ])
      </div>
    </div>

    @include('definicoes.partials.org-save-actions', [
      'storesWithSpecificSettings' => $storesWithSpecificSettings ?? [],
      'storesWithSpecificSettingsTab' => $storesWithSpecificSettingsTab ?? 'equipa',
      'formId' => 'definicoes-equipa-form',
    ])
  </form>
@endsection
