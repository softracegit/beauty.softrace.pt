@php
    $supportWhatsappDigits = preg_replace('/\D+/', '', (string) config('booking.support_whatsapp', ''));
@endphp
@if ($supportWhatsappDigits !== '')
    <a
        href="https://wa.me/{{ $supportWhatsappDigits }}"
        class="booking-support-whatsapp"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="{{ __('booking.partials.support_whatsapp_aria') }}"
    >
        <i class="bi bi-whatsapp" aria-hidden="true"></i>
    </a>
@endif
