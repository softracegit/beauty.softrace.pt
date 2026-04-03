@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Relatórios — Comissões').' — '.config('app.name'))

@section('content')
  <div class="dash-welcome mb-4">
    <div class="dash-welcome-content">
      <h2 class="dash-welcome-title">Relatórios — Comissões</h2>
      <p class="dash-welcome-text">Comissões da equipa e repartições.</p>
    </div>
  </div>
  @include('relatorios._placeholder')
@endsection
