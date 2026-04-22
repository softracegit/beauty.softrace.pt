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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/css/intlTelInput.css">
    <link rel="stylesheet" href="{{ asset('booking-assets/css/app.css') }}?v={{ file_exists(public_path('booking-assets/css/app.css')) ? filemtime(public_path('booking-assets/css/app.css')) : time() }}">
    @stack('head')
</head>
<body
    class="booking-body @yield('body_class')"
    data-booking-index-url="{{ route('booking.index') }}"
    data-booking-auth-check-email-url="{{ route('booking.auth.check_email') }}"
    data-booking-auth-login-url="{{ route('booking.auth.login') }}"
    data-booking-auth-register-url="{{ route('booking.auth.register') }}"
    data-booking-auth-password-link-url="{{ route('booking.auth.password_link') }}"
    data-booking-authenticated-client="{{ (auth()->user() instanceof \App\Models\User && auth()->user()->isBookingClient()) ? '1' : '0' }}"
>
    @yield('content')
    @include('booking.partials.auth-modal')
    @include('booking.partials.service-modal')

    <script src="{{ asset('template/vendor/bootstrap/js/bootstrap.bundle.min.js') }}" defer></script>
    {{-- Stacks antes de app.js: flatpickr, etc. --}}
    @stack('scripts')
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/js/intlTelInput.min.js" defer></script>
    <script src="{{ asset('booking-assets/js/app.js') }}?v={{ file_exists(public_path('booking-assets/js/app.js')) ? filemtime(public_path('booking-assets/js/app.js')) : time() }}" defer></script>
</body>
</html>
