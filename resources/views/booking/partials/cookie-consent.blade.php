@php
    $bookingGaId = 'G-E2MC9BE3P8';
@endphp
<div
    id="bookingCookieConsent"
    class="booking-cookie-consent d-none"
    role="dialog"
    aria-modal="false"
    aria-labelledby="bookingCookieConsentText"
    aria-describedby="bookingCookieConsentText"
>
    <div class="booking-cookie-consent__inner container-fluid">
        <p id="bookingCookieConsentText" class="booking-cookie-consent__text mb-0">
            {{ __('booking.cookie_consent.message') }}
            <a href="{{ route('legal.cookies') }}" class="booking-cookie-consent__link" target="_blank" rel="noopener noreferrer">{{ __('booking.legal.cookies') }}</a>.
        </p>
        <div class="booking-cookie-consent__actions">
            <button type="button" class="btn btn-sm booking-cookie-consent__refuse flex-shrink-0" id="bookingCookieConsentRefuse">
                {{ __('booking.cookie_consent.refuse') }}
            </button>
            <button type="button" class="btn btn-sm booking-cookie-consent__accept flex-shrink-0" id="bookingCookieConsentAccept">
                {{ __('booking.cookie_consent.accept') }}
            </button>
        </div>
    </div>
</div>
<script>
(function () {
    var STORAGE_KEY = 'booking_cookie_consent_v2';
    var LEGACY_KEY = 'booking_cookie_consent_v1';
    var GA_ID = @json($bookingGaId);
    var banner = document.getElementById('bookingCookieConsent');
    var acceptBtn = document.getElementById('bookingCookieConsentAccept');
    var refuseBtn = document.getElementById('bookingCookieConsentRefuse');
    if (!banner || !acceptBtn || !refuseBtn) return;

    function readConsent() {
        try {
            var value = window.localStorage.getItem(STORAGE_KEY);
            if (value === 'accepted' || value === 'rejected') {
                return value;
            }
            // Migração: v1 só tinha «Aceitar» (= consented)
            if (window.localStorage.getItem(LEGACY_KEY) === '1') {
                window.localStorage.setItem(STORAGE_KEY, 'accepted');
                return 'accepted';
            }
        } catch (e) {}
        return null;
    }

    function writeConsent(value) {
        try {
            window.localStorage.setItem(STORAGE_KEY, value);
            if (value === 'accepted') {
                window.localStorage.setItem(LEGACY_KEY, '1');
            } else {
                window.localStorage.removeItem(LEGACY_KEY);
            }
        } catch (e) {}
    }

    function hideBanner() {
        banner.classList.add('d-none');
        banner.setAttribute('aria-hidden', 'true');
    }

    function showBanner() {
        banner.classList.remove('d-none');
        banner.setAttribute('aria-hidden', 'false');
    }

    function enableAnalytics() {
        if (window.__bookingAnalyticsLoaded || !GA_ID) {
            return;
        }
        window.__bookingAnalyticsLoaded = true;
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () {
            window.dataLayer.push(arguments);
        };
        window.gtag('js', new Date());
        window.gtag('config', GA_ID);

        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA_ID);
        document.head.appendChild(script);
    }

    window.bookingEnableAnalytics = enableAnalytics;

    var consent = readConsent();
    if (consent === 'accepted') {
        hideBanner();
        enableAnalytics();
        return;
    }
    if (consent === 'rejected') {
        hideBanner();
        return;
    }

    showBanner();
    acceptBtn.addEventListener('click', function () {
        writeConsent('accepted');
        hideBanner();
        enableAnalytics();
    });
    refuseBtn.addEventListener('click', function () {
        writeConsent('rejected');
        hideBanner();
    });
})();
</script>
