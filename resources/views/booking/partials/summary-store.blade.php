@php
    $profile = $bookingStoreProfile ?? ($bookingStore?->publicBookingProfile() ?? []);
    $storeName = (string) ($profile['name'] ?? ($businessName ?? 'Loja'));
    $storePhotoUrl = (string) ($profile['photo'] ?? '');
    $weeklySchedule = $bookingWeeklySchedule ?? ($bookingStore?->normalizedWeeklySchedule() ?? \App\Models\Store::defaultWeeklySchedule());
    $hoursUi = \App\Support\BookingStoreOpenStatus::publicUiState($weeklySchedule);
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
            <span class="booking-summary-store__info-badge" aria-hidden="true">
                <i class="bi bi-info-lg"></i>
            </span>
        </span>
    </button>
</section>
