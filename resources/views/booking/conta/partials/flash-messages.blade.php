{{-- Avisos da área de conta: no topo do conteúdo, alinhados com o resto da página --}}
@if ($errors->any() || session('success'))
    <div class="booking-account-notices">
        @if ($errors->any())
            <div class="booking-account-notice booking-account-notice--error" role="alert">
                <i class="bi bi-exclamation-circle-fill booking-account-notice__icon" aria-hidden="true"></i>
                <p class="booking-account-notice__text">{{ $errors->first() }}</p>
                <button type="button" class="btn-close booking-account-notice__close" aria-label="{{ __('booking.auth.close_aria') }}" onclick="this.closest('.booking-account-notice').remove()"></button>
            </div>
        @endif
        @if (session('success'))
            <div class="booking-account-notice booking-account-notice--success" role="status">
                <i class="bi bi-check-circle-fill booking-account-notice__icon" aria-hidden="true"></i>
                <p class="booking-account-notice__text">{{ session('success') }}</p>
                <button type="button" class="btn-close booking-account-notice__close" aria-label="{{ __('booking.auth.close_aria') }}" onclick="this.closest('.booking-account-notice').remove()"></button>
            </div>
        @endif
    </div>
@endif
