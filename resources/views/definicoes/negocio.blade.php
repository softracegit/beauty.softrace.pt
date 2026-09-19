@extends('definicoes.layout')

@section('definicoes_content')
  <div class="mb-3">
    <h2 class="h5 mb-1">Definições para {{ $store->name }}</h2>
    <p class="text-muted small mb-0">
      Campos com «igual ao da empresa» usam
      <a href="{{ route('definicoes.empresa') }}">Definições → Empresa</a>.
      Nome, slug, morada, mapa e PIN são sempre desta loja.
    </p>
  </div>

  @include('stores.partials.business-settings-form', [
    'context' => 'store',
    'store' => $store,
    'organization' => $organization ?? $store->organization,
    'entity' => $store,
    'weeklySchedule' => $weeklySchedule,
    'privacyLockIdleMinutes' => $privacyLockIdleMinutes,
    'privacyLockEnabled' => $privacyLockEnabled,
    'emailUseBusinessBranding' => $emailUseBusinessBranding,
    'activeTab' => $activeTab ?? 'dados',
    'inherit' => $inherit ?? [],
    'formAction' => route('definicoes.negocio.update'),
    'formMethod' => 'post',
    'submitLabel' => 'Guardar',
  ])

@endsection
