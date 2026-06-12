@php
    $bookingStoreSlug = $bookingStoreSlug ?? \App\Models\Store::defaultPublicBookingStoreSlug();
    $accountNavActive = $accountNavActive ?? null;
    $accountUserName = trim((string) (auth()->user()->name ?? ''));
    if ($accountUserName === '') {
        $accountUserName = trim((string) (auth()->user()->email ?? __('booking.account.sidebar_fallback')));
    }
@endphp

<aside @class([
    'booking-account-sidebar',
    'booking-account-sidebar--elegant' => ($bookingUsesRefinedLayout ?? false),
    'card border shadow-sm rounded-3' => ! ($bookingUsesRefinedLayout ?? false),
])>
    <div @class(['py-3', 'card-body' => ! ($bookingUsesRefinedLayout ?? false), 'booking-account-sidebar__inner' => ($bookingUsesRefinedLayout ?? false)])>
        <p @class([
            'small fw-semibold text-uppercase text-muted mb-3 ms-2 mt-2',
            'booking-account-sidebar__user' => ($bookingUsesRefinedLayout ?? false),
        ])>{{ $accountUserName }}</p>
        <nav class="booking-account-nav">
            <a href="{{ route('booking.conta.index', ['store' => $bookingStoreSlug], false) }}" class="booking-account-nav__link d-flex align-items-center gap-2 {{ $accountNavActive === 'perfil' ? 'is-active' : '' }}">
                <i class="bi bi-person" aria-hidden="true"></i>
                <span>{{ __('booking.nav.profile') }}</span>
            </a>
            <a href="{{ route('booking.conta.marcacoes', ['store' => $bookingStoreSlug], false) }}" class="booking-account-nav__link d-flex align-items-center gap-2 {{ $accountNavActive === 'marcacoes' ? 'is-active' : '' }}">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                <span>{{ __('booking.nav.appointments') }}</span>
            </a>
            <a href="{{ route('booking.conta.carteira', ['store' => $bookingStoreSlug], false) }}" class="booking-account-nav__link d-flex align-items-center gap-2 {{ $accountNavActive === 'carteira' ? 'is-active' : '' }}">
                <i class="bi bi-wallet2" aria-hidden="true"></i>
                <span>{{ __('booking.nav.wallet') }}</span>
            </a>
            <a href="{{ route('booking.conta.settings', ['store' => $bookingStoreSlug], false) }}" class="booking-account-nav__link d-flex align-items-center gap-2 {{ $accountNavActive === 'definicoes' ? 'is-active' : '' }}">
                <i class="bi bi-gear" aria-hidden="true"></i>
                <span>{{ __('booking.nav.settings') }}</span>
            </a>
            <a href="{{ route('booking.logout', ['store' => $bookingStoreSlug], false) }}" class="booking-account-nav__link booking-account-nav__link--danger d-flex align-items-center gap-2">
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                <span>{{ __('booking.nav.logout') }}</span>
            </a>
        </nav>
    </div>
</aside>
