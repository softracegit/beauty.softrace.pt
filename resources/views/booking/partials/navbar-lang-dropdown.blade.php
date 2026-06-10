<div class="dropdown booking-navbar-lang">
    <button
        type="button"
        class="btn btn-outline-dark btn-sm dropdown-toggle rounded-pill booking-navbar-lang__toggle"
        id="booking-navbar-lang-menu"
        data-bs-toggle="dropdown"
        data-bs-display="static"
        aria-expanded="false"
        aria-haspopup="menu"
        aria-controls="booking-navbar-lang-dropdown"
        aria-label="{{ __('booking.nav.language_menu_aria') }}"
    >
        <span class="booking-navbar-lang__toggle-inner">
            <i class="bi bi-globe2 booking-navbar-lang__toggle-icon" aria-hidden="true"></i>
            <span class="booking-navbar-lang__code">{{ $bookingCurrentLocaleCode }}</span>
        </span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end booking-navbar-lang__menu" id="booking-navbar-lang-dropdown" aria-labelledby="booking-navbar-lang-menu">
        @foreach ($bookingLocaleCodes as $localeCode => $localeLabel)
            @if ($localeCode !== $bookingCurrentLocale)
                <li>
                    <a
                        class="dropdown-item"
                        href="{{ request()->fullUrlWithQuery(['lang' => $localeCode]) }}"
                        hreflang="{{ $localeCode }}"
                    >{{ $localeLabel }}</a>
                </li>
            @endif
        @endforeach
    </ul>
</div>
