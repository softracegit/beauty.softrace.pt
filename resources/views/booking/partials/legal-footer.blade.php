@if (($bookingTheme ?? null) === \App\Support\BookingTheme::MINIMAL)
    {{-- Tema Minimal: links à esquerda, sem linha; inclui as línguas e o WhatsApp (que saem da navbar e do canto) --}}
    @php
        $footerLocaleCodes = ['pt' => 'PT', 'en' => 'EN', 'es' => 'ES'];
        $footerCurrentLocale = app()->getLocale();
        $footerWhatsappDigits = preg_replace('/\D+/', '', (string) config('booking.booking_whatsapp', ''));
    @endphp
    <footer class="booking-legal-footer booking-legal-footer--minimal mt-auto">
        <nav class="booking-legal-footer__nav" aria-label="{{ __('booking.legal.footer_aria') }}">
            <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer">{{ __('booking.legal.terms') }}</a>
            <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer">{{ __('booking.legal.privacy') }}</a>
            <a href="{{ route('legal.cookies') }}" target="_blank" rel="noopener noreferrer">{{ __('booking.legal.cookies') }}</a>
            <span class="booking-legal-footer__langs" role="group" aria-label="{{ __('booking.nav.language_menu_aria') }}">
                @foreach ($footerLocaleCodes as $localeCode => $localeLabel)
                    <a
                        href="{{ request()->fullUrlWithQuery(['lang' => $localeCode]) }}"
                        hreflang="{{ $localeCode }}"
                        lang="{{ $localeCode }}"
                        @class(['is-current' => $localeCode === $footerCurrentLocale])
                        @if ($localeCode === $footerCurrentLocale) aria-current="true" @endif
                    >{{ $localeLabel }}</a>
                @endforeach
            </span>
            @if ($footerWhatsappDigits !== '')
                <a
                    href="https://wa.me/{{ $footerWhatsappDigits }}"
                    class="booking-legal-footer__whatsapp"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="{{ __('booking.partials.support_whatsapp_aria') }}"
                >
                    <i class="bi bi-whatsapp" aria-hidden="true"></i>
                </a>
            @endif
        </nav>
        <p class="booking-legal-footer__powered">
            Powered by <a href="https://softrace.pt" target="_blank" rel="noopener">softrace.pt</a>
        </p>
    </footer>
@else
<footer class="booking-legal-footer text-center py-3 mt-auto border-top">
    <nav class="d-flex flex-wrap justify-content-center gap-3 small text-muted" aria-label="{{ __('booking.legal.footer_aria') }}">
        <a href="{{ route('legal.terms') }}" class="text-muted text-decoration-none" target="_blank" rel="noopener noreferrer">{{ __('booking.legal.terms') }}</a>
        <a href="{{ route('legal.privacy') }}" class="text-muted text-decoration-none" target="_blank" rel="noopener noreferrer">{{ __('booking.legal.privacy') }}</a>
        <a href="{{ route('legal.cookies') }}" class="text-muted text-decoration-none" target="_blank" rel="noopener noreferrer">{{ __('booking.legal.cookies') }}</a>
    </nav>
    <p class="booking-legal-footer__powered">
        Powered by <a href="https://softrace.pt" target="_blank" rel="noopener">softrace.pt</a>
    </p>
</footer>
@endif
