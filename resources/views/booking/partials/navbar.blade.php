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
            $bookingUserDisplayName = 'Conta';
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
    $bookingStepActive = match ($bookingRouteName) {
        'booking.index' => 1,
        'booking.technician' => 2,
        'booking.datetime' => 3,
        'booking.step3' => 4,
        'booking.confirm' => 5,
        default => null,
    };
    $bookingSteps = [
        ['label' => 'Serviços', 'route' => 'booking.index'],
        ['label' => 'Staff', 'route' => 'booking.technician'],
        ['label' => 'Dia / Hora', 'route' => 'booking.datetime'],
        ['label' => 'Confirmação', 'route' => 'booking.step3'],
    ];
@endphp
<nav class="navbar navbar-light bg-white border-bottom fixed-top booking-navbar shadow-sm py-0">
    <div class="container booking-container-wide py-2 booking-navbar__inner px-3">
        <div class="booking-navbar__cell booking-navbar__cell--start">
            <a href="{{ route('booking.index') }}" class="navbar-brand mb-0 d-inline-flex align-items-center booking-navbar__brand" aria-label="{{ $businessName }}">
                <img
                    src="{{ asset('booking-assets/img/logo-fada.png') }}"
                    alt="{{ $businessName }}"
                    class="booking-navbar__brand-logo"
                    loading="eager"
                    decoding="async"
                >
            </a>
        </div>
        @if ($showBookingSteps)
            <div class="booking-navbar__cell booking-navbar__cell--center">
                <ol class="booking-navbar-steps" aria-label="Passos da marcação">
                    @foreach ($bookingSteps as $idx => $step)
                        @php
                            $stepNum = $idx + 1;
                            $isConfirm = $bookingRouteName === 'booking.confirm';
                            $isPast = $bookingStepActive !== null && ($isConfirm || $stepNum < $bookingStepActive);
                            $isCurrent = ! $isConfirm && $bookingStepActive !== null && $stepNum === $bookingStepActive;
                            $isFuture = ! $isConfirm && $bookingStepActive !== null && $stepNum > $bookingStepActive;
                            $stepUrl = route($step['route']);
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
            </div>
        @else
            <div class="booking-navbar__cell booking-navbar__cell--center booking-navbar__cell--center--empty" aria-hidden="true"></div>
        @endif
        <div class="booking-navbar__cell booking-navbar__cell--end d-flex align-items-center gap-2">
            @if ($bookingClientAuthed)
                <div class="d-flex align-items-center gap-1 gap-md-2">
                    <a
                        href="{{ route('booking.index') }}"
                        class="booking-navbar-new-booking"
                        aria-label="Nova marcação"
                    >
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        <span class="d-none d-md-inline">Nova marcação</span>
                    </a>
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
                            aria-label="Menu da conta de {{ $bookingUserDisplayName }}"
                        >
                            <span class="booking-navbar-account__toggle-inner">
                                <i class="bi bi-person booking-navbar-account__toggle-icon" aria-hidden="true"></i>
                                <span class="booking-navbar-account__name d-none d-md-inline">{{ $bookingUserDisplayName }}</span>
                                <span class="booking-navbar-account__initials d-md-none" aria-hidden="true">{{ $bookingUserInitials }}</span>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end booking-navbar-account__menu" id="booking-navbar-account-dropdown" aria-labelledby="booking-navbar-account-menu">
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('booking.index') }}">
                                    <i class="bi bi-plus-circle booking-navbar-account__item-icon" aria-hidden="true"></i>
                                    <span>Nova marcação</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('booking.conta.index') }}">
                                    <i class="bi bi-person booking-navbar-account__item-icon" aria-hidden="true"></i>
                                    <span>Perfil</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('booking.conta.marcacoes') }}">
                                    <i class="bi bi-calendar3 booking-navbar-account__item-icon" aria-hidden="true"></i>
                                    <span>Marcações</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="#!">
                                    <i class="bi bi-wallet2 booking-navbar-account__item-icon" aria-hidden="true"></i>
                                    <span>Carteira</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('booking.conta.settings') }}">
                                    <i class="bi bi-gear booking-navbar-account__item-icon" aria-hidden="true"></i>
                                    <span>Definições</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="{{ route('logout') }}" class="px-0 mb-0">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2 w-100 text-start border-0 bg-transparent">
                                        <i class="bi bi-box-arrow-right booking-navbar-account__item-icon" aria-hidden="true"></i>
                                        <span>Terminar sessão</span>
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            @else
                <button type="button" class="btn btn-outline-dark btn-sm rounded-pill booking-navbar__auth-open text-nowrap js-booking-open-auth-modal" id="booking-navbar-open-auth">
                    Iniciar sessão
                </button>
            @endif
        </div>
    </div>
</nav>
