@extends('definicoes.layout')

@section('definicoes_content')
  @include('definicoes.partials.client-tags-settings', ['clientTags' => $clientTags])
@endsection
