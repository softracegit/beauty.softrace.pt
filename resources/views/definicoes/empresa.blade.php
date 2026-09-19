@extends('definicoes.layout')

@section('definicoes_content')
  <div class="mb-3">
    <h2 class="h5 mb-1">Empresa — {{ $organization->name }}</h2>
  </div>

  @include('stores.partials.business-settings-form', [
    'context' => 'organization',
    'organization' => $organization,
    'entity' => $organization,
    'weeklySchedule' => $weeklySchedule,
    'privacyLockIdleMinutes' => $privacyLockIdleMinutes,
    'privacyLockEnabled' => false,
    'emailUseBusinessBranding' => $emailUseBusinessBranding,
    'activeTab' => $activeTab ?? 'dados',
    'formAction' => route('definicoes.empresa.update'),
    'formMethod' => 'post',
    'submitLabel' => 'Guardar',
  ])

@endsection
