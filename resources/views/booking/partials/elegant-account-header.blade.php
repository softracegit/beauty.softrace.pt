@php
    use App\Support\BookingTheme;

    if (! BookingTheme::usesRefinedLayout($bookingTheme ?? null)) {
        return;
    }

    $elegantAccountEyebrow = $elegantAccountEyebrow ?? __('booking.elegant.account_eyebrow');
    $elegantAccountTitle = $elegantAccountTitle ?? '';
    $elegantAccountSubtitle = $elegantAccountSubtitle ?? '';
@endphp
@if ($elegantAccountTitle !== '')
    <header class="booking-elegant-account-header mb-4">
        <p class="booking-elegant-account-header__eyebrow mb-2">{{ $elegantAccountEyebrow }}</p>
        <h1 class="booking-elegant-account-header__title mb-2">{{ $elegantAccountTitle }}</h1>
        @if ($elegantAccountSubtitle !== '')
            <p class="booking-elegant-account-header__subtitle mb-0">{{ $elegantAccountSubtitle }}</p>
        @endif
    </header>
@endif
