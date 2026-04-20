@php
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
            <span class="navbar-brand mb-0 fw-semibold text-dark text-truncate d-inline-block booking-navbar__brand">{{ $businessName }}</span>
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
        <div class="booking-navbar__cell booking-navbar__cell--end">
            <button
                type="button"
                class="btn btn-link text-dark p-0 rounded-2 booking-navbar__menu-btn"
                data-bs-toggle="offcanvas"
                data-bs-target="#bookingStoreDetails"
                aria-controls="bookingStoreDetails"
                aria-label="Abrir menu"
            >
                <i class="bi bi-list fs-4"></i>
            </button>
        </div>
    </div>
</nav>
