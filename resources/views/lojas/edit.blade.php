@extends('partials.layouts.main')
@section('title', 'Editar Loja | Beauty CRM')
@section('content')

<div class="mb-3">
  <h2 class="h5 mb-1">{{ $store->name }}</h2>
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
    'formAction' => route('lojas.update', $store),
    'formMethod' => 'put',
    'cancelUrl' => route('lojas.index'),
    'submitLabel' => 'Guardar Alterações',
])

@endsection
