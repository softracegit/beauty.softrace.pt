<div
    id="bookingCookieConsent"
    class="booking-cookie-consent d-none"
    role="dialog"
    aria-modal="false"
    aria-describedby="bookingCookieConsentText"
>
    <div class="booking-cookie-consent__inner container-fluid">
        <p id="bookingCookieConsentText" class="booking-cookie-consent__text mb-0">
            {{ __('booking.cookie_consent.message') }}
            <a href="{{ route('legal.cookies') }}" class="booking-cookie-consent__link" target="_blank" rel="noopener noreferrer">{{ __('booking.legal.cookies') }}</a>.
        </p>
        <button type="button" class="btn btn-primary btn-sm booking-cookie-consent__accept flex-shrink-0" id="bookingCookieConsentAccept">
            {{ __('booking.cookie_consent.accept') }}
        </button>
    </div>
</div>
<script>
(function () {
    var STORAGE_KEY = 'booking_cookie_consent_v1';
    var banner = document.getElementById('bookingCookieConsent');
    var acceptBtn = document.getElementById('bookingCookieConsentAccept');
    if (!banner || !acceptBtn) return;

    function hasConsent() {
        try {
            return window.localStorage.getItem(STORAGE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function hideBanner() {
        banner.classList.add('d-none');
        banner.setAttribute('aria-hidden', 'true');
    }

    function showBanner() {
        banner.classList.remove('d-none');
        banner.setAttribute('aria-hidden', 'false');
    }

    if (hasConsent()) {
        hideBanner();
        return;
    }

    showBanner();
    acceptBtn.addEventListener('click', function () {
        try {
            window.localStorage.setItem(STORAGE_KEY, '1');
        } catch (e) {}
        hideBanner();
    });
})();
</script>
