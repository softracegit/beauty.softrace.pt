@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Definições').' — '.config('app.name'))

@section('content')  
  @yield('definicoes_content')
@endsection
