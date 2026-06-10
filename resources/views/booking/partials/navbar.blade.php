@php
    $bookingClientAuthed = auth()->check()
        && auth()->user() instanceof \App\Models\User
        && auth()->user()->isBookingClient();
    $bookingUserDisplayName = '';
    $bookingUserInitials = '';
    if ($bookingClientAuthed) {
        $bookingUserDisplayName = trim((string) auth()->user()->name);
        if ($bookingUserDisplayName === '') {
            $bookingUserDisplayName = trim((string) (auth()->user()->email ?? ''));
        }
        if ($bookingUserDisplayName === '') {
            $bookingUserDisplayName = __('booking.nav.account_fallback');
        }

        $nameForInitials = trim((string) auth()->user()->name);
        if ($nameForInitials !== '') {
            $parts = preg_split('/\s+/u', $nameForInitials, -1, PREG_SPLIT_NO_EMPTY);
            if (count($parts) >= 2) {
                $first = (string) ($parts[0] ?? '');
                $last = (string) ($parts[count($parts) - 1] ?? '');
                $bookingUserInitials = strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));
            } else {
                $compact = preg_replace('/\s+/u', '', $nameForInitials);
                $bookingUserInitials = strtoupper(mb_substr($compact, 0, 2));
            }
        }
        if ($bookingUserInitials === '') {
            $emailRaw = trim((string) (auth()->user()->email ?? ''));
            if ($emailRaw !== '' && str_contains($emailRaw, '@')) {
                $local = explode('@', $emailRaw, 2)[0];
                $local = preg_replace('/[^a-zA-Z0-9]/u', '', $local);
                $bookingUserInitials = strtoupper(mb_substr($local, 0, 2));
            }
        }
        if ($bookingUserInitials === '') {
            $bookingUserInitials = '?';
        }
    }
    $bookingRouteName = request()->route()?->getName();
    $bookingFlowRoutes = ['booking.index', 'booking.technician', 'booking.datetime', 'booking.step3', 'booking.confirm'];
    $showBookingSteps = $bookingRouteName && in_array($bookingRouteName, $bookingFlowRoutes, true);
    $bookingDisableAuthModal = (bool) ($bookingDisableAuthModal ?? false);
    $bookingStepActive = match ($bookingRouteName) {
        'booking.index' => 1,
        'booking.technician' => 2,
        'booking.datetime' => 3,
        'booking.step3' => 4,
        'booking.confirm' => 5,
        default => null,
    };
    $bookingSteps = [
        ['label' => __('booking.nav.step_services'), 'route' => 'booking.index'],
        ['label' => __('booking.nav.step_staff'), 'route' => 'booking.technician'],
        ['label' => __('booking.nav.step_datetime'), 'route' => 'booking.datetime'],
        ['label' => __('booking.nav.step_confirmation'), 'route' => 'booking.step3'],
    ];
    $bookingBusinessDisplayName = trim((string) ($businessName ?? $bookingStore?->name ?? config('app.name', __('booking.nav.default_store'))));
    $bookingCurrentLocale = app()->getLocale();
    $bookingLocaleCodes = [
        'pt' => 'PT',
        'en' => 'EN',
        'es' => 'ES',
    ];
    $bookingCurrentLocaleCode = $bookingLocaleCodes[$bookingCurrentLocale] ?? strtoupper($bookingCurrentLocale);
    $bookingNavbarStoreTitleUpper = mb_strtoupper($bookingBusinessDisplayName, 'UTF-8');
    $bookingIndexUrl = route('booking.index', ['store' => $bookingStoreSlug], false);
    $bookingNavbarBackUrl = match ($bookingRouteName) {
        'booking.technician' => $bookingIndexUrl,
        'booking.datetime' => route('booking.technician', ['store' => $bookingStoreSlug], false),
        'booking.step3' => route('booking.datetime', ['store' => $bookingStoreSlug], false),
        default => null,
    };
@endphp
<nav class="navbar navbar-light bg-white border-bottom fixed-top booking-navbar shadow-sm py-0">
    <div class="container booking-container-wide py-2 booking-navbar__inner px-3">
        <div class="booking-navbar__cell booking-navbar__cell--start">
            <div class="booking-navbar__lead">
                @if ($bookingNavbarBackUrl)
                    <a
                        href="{{ $bookingNavbarBackUrl }}"
                        class="booking-navbar__back"
                        aria-label="{{ __('booking.nav.back') }}"
                    >
                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                    </a>
                @else
                    <span class="booking-navbar__lead-spacer" aria-hidden="true"></span>
                @endif
            </div>
        </div>
        <div class="booking-navbar__cell booking-navbar__cell--center">
            <span class="booking-navbar__store-title">{{ $bookingNavbarStoreTitleUpper }}</span>
            @if ($showBookingSteps)
                <ol class="booking-navbar-steps d-none" aria-label="{{ __('booking.nav.steps_aria') }}">
                    @foreach ($bookingSteps as $idx => $step)
                        @php
                            $stepNum = $idx + 1;
                            $isConfirm = $bookingRouteName === 'booking.confirm';
                            $isPast = $bookingStepActive !== null && ($isConfirm || $stepNum < $bookingStepActive);
                            $isCurrent = ! $isConfirm && $bookingStepActive !== null && $stepNum === $bookingStepActive;
                            $isFuture = ! $isConfirm && $bookingStepActive !== null && $stepNum > $bookingStepActive;
                            $stepUrl = route($step['route'], ['store' => $bookingStoreSlug], false);
                        @endphp
                        <li class="booking-navbar-steps__item">
                            @if ($idx > 0)
                                <span class="booking-navbar-steps__sep" aria-hidden="true">—</span>
                            @endif
                            @if ($isPast)
                                <a href="{{ $stepUrl }}" class="booking-navbar-steps__btn booking-navbar-steps__btn--past">{{ $step['label'] }}</a>
                            @elseif ($isCurrent)
                                <span class="booking-navbar-steps__btn booking-navbar-steps__btn--current" aria-current="step">{{ $step['label'] }}</span>
                            @else
                                <span class="booking-navbar-steps__btn booking-navbar-steps__btn--future">{{ $step['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
        <div class="booking-navbar__cell booking-navbar__cell--end d-flex align-items-center gap-2">
            @if ($bookingClientAuthed)
                <div class="d-flex align-items-center gap-1 gap-md-2">
                    <a
                        href="{{ route('booking.index', ['store' => $bookingStoreSlug], false) }}"
                        class="booking-navbar-new-booking d-none d-lg-inline-flex"
                        aria-label="{{ __('booking.nav.new_booking_aria') }}"
                    >
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    </a>
                    @include('booking.partials.navbar-lang-dropdown')
                    <div class="dropdown booking-navbar-account">
                        <button
                            type="button"
                            class="btn btn-outline-dark btn-sm dropdown-toggle rounded-pill booking-navbar-account__toggle"
                            id="booking-navbar-account-menu"
                            data-bs-toggle="dropdown"
                            data-bs-display="static"
                            aria-expanded="false"
                            aria-haspopup="menu"
                            aria-controls="booking-navbar-account-dropdown"
                            aria-label="{{ __('booking.nav.account_menu_aria', ['name' => $bookingUserDisplayName]) }}"
                        >
                            <span class="booking-navbar-account__toggle-inner">
                                <i class="bi bi-person booking-navbar-account__toggle-icon" aria-hidden="true"></i>
                                <span class="booking-navbar-account__name d-none d-md-inline">{{ $bookingUserDisplayName }}</span>
                                <span class="booking-navbar-account__initials d-md-none" aria-hidden="true">{{ $bookingUserInitials }}</span>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end booking-navbar-account__menu" id="booking-navbar-account-dropdown" aria-labelledby="booking-navbar-account-menu">
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('booking.index', ['store' => $bookingStoreSlug], false) }}">
                                    <i class="bi bi-plus-circle booking-navbar-account__item-icon" aria-hidden="true"></i>
                                    <span>{{ __('booking.nav.new_booking') }}</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('booking.conta.index', ['store' => $bookingStoreSlug], false) }}">
                                    <i class="bi bi-person booking-navbar-account__item-icon" aria-hidden="true"></i>
                                    <span>{{ __('booking.nav.profile') }}</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('booking.conta.marcacoes', ['store' => $bookingStoreSlug], false) }}">
                                    <i class="bi bi-calendar3 booking-navbar-account__item-icon" aria-hidden="true"></i>
                                    <span>{{ __('booking.nav.appointments') }}</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('booking.conta.carteira', ['store' => $bookingStoreSlug], false) }}">
                                    <i class="bi bi-wallet2 booking-navbar-account__item-icon" aria-hidden="true"></i>
                                    <span>{{ __('booking.nav.wallet') }}</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('booking.conta.settings', ['store' => $bookingStoreSlug], false) }}">
                                    <i class="bi bi-gear booking-navbar-account__item-icon" aria-hidden="true"></i>
                                    <span>{{ __('booking.nav.settings') }}</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger d-flex align-items-center gap-2" href="{{ route('booking.logout', ['store' => $bookingStoreSlug], false) }}">
                                    <i class="bi bi-box-arrow-right booking-navbar-account__item-icon" aria-hidden="true"></i>
                                    <span>{{ __('booking.nav.logout') }}</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            @else
                @include('booking.partials.navbar-lang-dropdown')
                @if ($bookingDisableAuthModal)
                    <a href="{{ route('booking.login', ['store' => $bookingStoreSlug], false) }}" class="btn btn-outline-dark btn-sm rounded-pill booking-navbar__auth-open text-nowrap" id="booking-navbar-open-auth">
                        <i class="bi bi-person d-lg-none booking-navbar__auth-open-icon" aria-hidden="true"></i>
                        <span class="visually-hidden d-lg-none">{{ __('booking.nav.login') }}</span>
                        <span class="d-none d-lg-inline">{{ __('booking.nav.login') }}</span>
                    </a>
                @else
                    <button type="button" class="btn btn-outline-dark btn-sm rounded-pill booking-navbar__auth-open text-nowrap js-booking-open-auth-modal" id="booking-navbar-open-auth">
                        <i class="bi bi-person d-lg-none booking-navbar__auth-open-icon" aria-hidden="true"></i>
                        <span class="visually-hidden d-lg-none">{{ __('booking.nav.login') }}</span>
                        <span class="d-none d-lg-inline">{{ __('booking.nav.login') }}</span>
                    </button>
                @endif
            @endif
        </div>
    </div>
</nav>
