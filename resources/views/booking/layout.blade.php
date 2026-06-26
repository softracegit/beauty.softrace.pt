<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-E2MC9BE3P8"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-E2MC9BE3P8');
    </script>
    @php
        $bookingThemeId = $bookingTheme ?? \App\Support\BookingTheme::DEFAULT;
        $bookingThemeMeta = $bookingThemeMeta ?? \App\Support\BookingTheme::resolved();
        $bookingThemeFonts = $bookingThemeMeta['fonts'] ?? null;
        $bookingThemeCssFiles = \App\Support\BookingTheme::cssAssetPaths();
        $bookingLayoutIsRefined = \App\Support\BookingTheme::usesRefinedLayout($bookingThemeId);
    @endphp
    <meta name="theme-color" content="{{ \App\Support\BookingTheme::themeColor() }}">
    <title>@yield('title', __('booking.layout.default_title')) — {{ trim((string) ($businessName ?? config('app.name'))) }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @if ($bookingThemeFonts)
        <link href="{{ $bookingThemeFonts }}" rel="stylesheet">
    @endif

    <link rel="stylesheet" href="{{ asset('template/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('template/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/css/intlTelInput.css">
    <link rel="stylesheet" href="{{ asset('booking-assets/css/app.css') }}?v={{ file_exists(public_path('booking-assets/css/app.css')) ? filemtime(public_path('booking-assets/css/app.css')) : time() }}">
    @foreach ($bookingThemeCssFiles as $bookingThemeCss)
        @php($bookingThemeCssPath = public_path($bookingThemeCss))
        <link rel="stylesheet" href="{{ asset($bookingThemeCss) }}?v={{ file_exists($bookingThemeCssPath) ? filemtime($bookingThemeCssPath) : time() }}">
    @endforeach
    @stack('head')
</head>
@php($bookingStoreSlug = $bookingStoreSlug ?? \App\Models\Store::defaultPublicBookingStoreSlug())
@php($bookingPresetAgent = $bookingPresetAgent ?? null)
@php($bookingSkipStaffStep = (bool) ($bookingSkipStaffStep ?? false))
<body
    class="booking-body{{ $bookingLayoutIsRefined ? ' booking-theme--refined' : '' }} @yield('body_class'){{ $bookingSkipStaffStep ? ' booking-flow--preset-agent' : '' }}"
    data-booking-theme="{{ $bookingThemeId }}"
    data-booking-store-slug="{{ $bookingStoreSlug }}"
    data-booking-index-url="{{ route('booking.index', ['store' => $bookingStoreSlug], false) }}"
    @if($bookingPresetAgent)
        data-booking-preset-agent-id="{{ $bookingPresetAgent['id'] }}"
        data-booking-preset-agent-name="{{ $bookingPresetAgent['name'] }}"
        data-booking-preset-agent-specialization="{{ $bookingPresetAgent['specialization'] }}"
        data-booking-preset-agent-avatar="{{ $bookingPresetAgent['avatar'] ?? '' }}"
        data-booking-preset-agent-url="{{ $bookingPresetAgent['url'] }}"
        data-booking-skip-staff-step="1"
    @endif
    data-booking-auth-request-code-url="{{ route('booking.auth.request_code', ['store' => $bookingStoreSlug], false) }}"
    data-booking-auth-verify-code-url="{{ route('booking.auth.verify_code', ['store' => $bookingStoreSlug], false) }}"
    data-booking-auth-complete-registration-url="{{ route('booking.auth.complete_registration', ['store' => $bookingStoreSlug], false) }}"
    data-booking-slot-hold-acquire-url="{{ route('booking.slot_hold.acquire', ['store' => $bookingStoreSlug], false) }}"
    data-booking-slot-hold-extend-url="{{ route('booking.slot_hold.extend', ['store' => $bookingStoreSlug], false) }}"
    data-booking-slot-hold-release-url="{{ route('booking.slot_hold.release', ['store' => $bookingStoreSlug], false) }}"
    data-booking-slot-hold-seconds="{{ max(10, \App\Models\CrmSetting::bookingSlotHoldMinutes() * 60) }}"
    data-booking-session-ping-url="{{ route('booking.session.ping', ['store' => $bookingStoreSlug], false) }}"
    data-booking-session-keepalive-minutes="{{ config('booking.session_keepalive_interval_minutes') }}"
    data-booking-authenticated-client="{{ (auth()->user() instanceof \App\Models\User && auth()->user()->isBookingClient()) ? '1' : '0' }}"
>
    @yield('content')
    @include('booking.partials.support-whatsapp')
    @include('booking.partials.legal-footer')
    @include('booking.partials.slot-hold-expired-modal')
    @include('booking.partials.auth-modal')
    @include('booking.partials.service-modal')

    <script src="{{ asset('template/vendor/bootstrap/js/bootstrap.bundle.min.js') }}" defer></script>
    <script src="{{ asset('template/js/http-friendly-errors.js') }}?v={{ file_exists(public_path('template/js/http-friendly-errors.js')) ? filemtime(public_path('template/js/http-friendly-errors.js')) : time() }}"></script>
    {{-- Stacks antes de app.js: flatpickr, etc. --}}
    @stack('scripts')
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/js/intlTelInput.min.js" defer></script>
    <script>
        window.bookingLocale = @json(\App\Support\BookingLocale::intlLocale());
        window.bookingI18n = @json(trans('booking.js'));
    </script>
    <script src="{{ asset('booking-assets/js/app.js') }}?v={{ file_exists(public_path('booking-assets/js/app.js')) ? filemtime(public_path('booking-assets/js/app.js')) : time() }}" defer></script>
</body>
</html>
