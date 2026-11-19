@php
    $bookingStoreSlug = $bookingStoreSlug ?? \App\Models\Store::defaultPublicBookingStoreSlug();
@endphp
{{-- "Continuar a minha marcação": só aparece (via app.js) quando há serviços guardados no resumo --}}
<a
    href="{{ route('booking.index', ['store' => $bookingStoreSlug], false) }}"
    id="booking-account-resume"
    class="booking-account-resume d-none"
    data-count-one="{{ __('booking.account.resume_count_one') }}"
    data-count-many="{{ __('booking.account.resume_count_many') }}"
>
    <span class="booking-account-resume__body">
        <span class="booking-account-resume__title">{{ __('booking.account.resume_title') }}</span>
        <span id="booking-account-resume-list" class="booking-account-resume__list"></span>
        <span id="booking-account-resume-meta" class="booking-account-resume__meta"></span>
    </span>
    <span class="booking-account-resume__cta" aria-hidden="true"><i class="bi bi-arrow-right"></i></span>
</a>
