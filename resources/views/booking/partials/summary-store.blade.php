@php
    $store = config('booking.public_store', []);
    $storeName = (string) ($store['name'] ?? 'Loja');
    $rawPhoto = trim((string) ($store['photo'] ?? ''));
    $fallback = (string) ($store['photo_fallback'] ?? 'booking-assets/img/logo-fada.png');
    $storePhotoUrl = $rawPhoto !== ''
        ? (filter_var($rawPhoto, FILTER_VALIDATE_URL) ? $rawPhoto : asset(ltrim($rawPhoto, '/')))
        : asset(ltrim($fallback, '/'));
    $hoursUi = \App\Support\BookingStoreOpenStatus::publicUiState();
@endphp
<section class="booking-summary-store" aria-label="Loja">
    <button
        type="button"
        class="booking-summary-store__trigger"
        data-bs-toggle="offcanvas"
        data-bs-target="#bookingStoreDetails"
        aria-label="Ver informação da loja, morada e horários"
    >
        <span class="booking-summary-store__inner">
            <span class="booking-summary-store__photo">
                <img src="{{ $storePhotoUrl }}" alt="{{ $storeName }}" width="40" height="40" loading="lazy" decoding="async">
            </span>
            <span class="booking-summary-store__lines">
                <span class="booking-summary-store__line booking-summary-store__line--name">{{ $storeName }}</span>
                <span class="booking-summary-store__line booking-summary-store__line--status">
                    <span class="{{ $hoursUi['css_class'] }}">{{ $hoursUi['label'] }}</span>{{ $hoursUi['suffix'] }}
                </span>
            </span>
        </span>
    </button>
</section>
