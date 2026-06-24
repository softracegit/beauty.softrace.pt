@php
    $supportWhatsappDigits = preg_replace('/\D+/', '', (string) config('booking.support_whatsapp', ''));
@endphp
@if ($supportWhatsappDigits !== '')
    <div class="modal-footer border-0 pt-0 booking-auth-modal__support">
        <a
            href="https://wa.me/{{ $supportWhatsappDigits }}"
            class="booking-support-whatsapp-help"
            target="_blank"
            rel="noopener noreferrer"
        >
            <i class="bi bi-whatsapp" aria-hidden="true"></i>
            <span>{{ __('booking.auth.support_help') }}</span>
        </a>
    </div>
@endif
