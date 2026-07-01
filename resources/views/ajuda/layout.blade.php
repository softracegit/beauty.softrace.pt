@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Ajuda').' | '.config('app.name'))

@section('css')
<link href="{{ asset('template/vendor/remixicon/remixicon.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ static_asset('template/css/ajuda.css') }}">
@endsection

@section('content')
@yield('ajuda_content')
@endsection
