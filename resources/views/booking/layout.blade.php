<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <title>@yield('title', 'Marcação') — {{ config('app.name') }}</title>

    {{-- Mesma stack de fonte que o SmartAdmin (template público) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('template/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('template/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('booking-assets/css/app.css') }}?v={{ file_exists(public_path('booking-assets/css/app.css')) ? filemtime(public_path('booking-assets/css/app.css')) : time() }}">
    @stack('head')
</head>
<body class="booking-body @yield('body_class')" data-booking-index-url="{{ route('booking.index') }}">
    @yield('content')
    @include('booking.partials.service-modal')

    <script src="{{ asset('template/vendor/bootstrap/js/bootstrap.bundle.min.js') }}" defer></script>
    {{-- Stacks antes de app.js: páginas podem injectar intl-tel-input (etc.) para ficarem disponíveis no init --}}
    @stack('scripts')
    <script src="{{ asset('booking-assets/js/app.js') }}?v={{ file_exists(public_path('booking-assets/js/app.js')) ? filemtime(public_path('booking-assets/js/app.js')) : time() }}" defer></script>
</body>
</html>
