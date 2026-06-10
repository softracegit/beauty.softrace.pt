@php
    $bookingStoreKey = $bookingStoreSlug ?? \App\Models\Store::defaultPublicBookingStoreSlug();
    $backUrl = $backUrl ?? route('booking.index', ['store' => $bookingStoreKey], false);
@endphp
<a
    href="{{ $backUrl }}"
    class="btn btn-outline-secondary d-inline-flex d-lg-none justify-content-center align-items-center booking-summary-back-btn booking-page-back-mobile flex-shrink-0"
    aria-label="{{ __('booking.nav.back') }}"
>
    <i class="bi bi-chevron-left" aria-hidden="true"></i>
</a>
